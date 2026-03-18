<?php

namespace Amdeu\LabelEditor\Backend\Service;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\Parser;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationService
{
	public function __construct(
		private readonly Typo3Version $typo3Version
	) {}

	public function getLoaderForFile(string $filePath): Parser\LocalizationParserInterface
	{
		$loaderClassName = $this->getLoaderClassNameForFormat(pathinfo($filePath, PATHINFO_EXTENSION));
		if (!$loaderClassName || !class_exists($loaderClassName)) {
			throw new \RuntimeException(sprintf('No loader found for file format: %s', $filePath));
		}
		return GeneralUtility::makeInstance($loaderClassName);
	}

	public function getLoaderClassNameForFormat(string $format): string
	{
		if ($this->typo3Version->getMajorVersion() < 14) {
			return $GLOBALS['TYPO3_CONF_VARS']['SYS']['lang']['parser'][$format] ?? '';
		}
		return $GLOBALS['TYPO3_CONF_VARS']['LANG']['loader'][$format] ?? '';
	}

	public function getFormatPriority(): string
	{
		$priorityString = $this->typo3Version->getMajorVersion() < 14
			? $GLOBALS['TYPO3_CONF_VARS']['SYS']['lang']['format']['priority'] ?? ''
			: $GLOBALS['TYPO3_CONF_VARS']['LANG']['format']['priority'] ?? '';

		return $priorityString ?: 'xlf,yaml,json,php';
	}

	public function setResourceOverride(string $originalPath, string $overridePath): void
	{
		if ($this->typo3Version->getMajorVersion() < 14) {
			$GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride'][$originalPath][] = $overridePath;
		} else {
			$GLOBALS['TYPO3_CONF_VARS']['LANG']['resourceOverrides'][$originalPath][] = $overridePath;
		}
	}
}