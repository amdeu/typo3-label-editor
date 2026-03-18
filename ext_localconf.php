<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use Amdeu\LabelEditor\Backend\Service;

// Register icon
$iconRegistry = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
$iconRegistry->registerIcon(
	'module-label-editor',
	\TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
	['source' => 'EXT:label_editor/Resources/Public/Icons/Extension.svg']
);

$configurationService = GeneralUtility::makeInstance(Service\ConfigurationService::class, GeneralUtility::makeInstance(Typo3Version::class));

// Load translation overrides from registry
$registryFile = Environment::getVarPath() . '/label_editor/registry.json';

if (file_exists($registryFile)) {
	$registry = json_decode(file_get_contents($registryFile), true);

	if (is_array($registry) && !empty($registry)) {
		foreach ($registry as $sourceFile => $overrides) {
			if (isset($overrides['default'])) {
				$configurationService->setResourceOverride($sourceFile, $overrides['default']);
			}
			foreach ($overrides as $langKey => $overridePath) {
				if ($langKey !== 'default') {
					$configurationService->setResourceOverride($sourceFile, $overridePath);
				}
			}
		}
	}
}
