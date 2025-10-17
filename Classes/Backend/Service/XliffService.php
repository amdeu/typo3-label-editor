<?php

declare(strict_types=1);

namespace UBOS\LabelEditor\Backend\Service;

use TYPO3\CMS\Core\Localization\Parser\XliffParser;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class XliffService
{
	public function __construct(
		private readonly XliffParser $xliffParser,
		private readonly SiteFinder $siteFinder
	) {}

	public function getTranslations(string $sourceFile, string $overridePath = ''): array
	{
		// Parse original file
		$originalPath = GeneralUtility::getFileAbsFileName($sourceFile);
		$original = $this->xliffParser->getParsedData($originalPath, 'default');

		$translations = [];
		foreach ($original['default'] as $key => $data) {
			$translations[$key] = [
				'key' => $key,
				'source' => $data[0]['source'] ?? '',
				'override' => '',
			];
		}

		// Parse override if exists
		if ($overridePath && file_exists($overridePath)) {
			$override = $this->xliffParser->getParsedData($overridePath, 'default');
			foreach ($override['default'] as $key => $data) {
				if (isset($translations[$key])) {
					$translations[$key]['override'] = $data[0]['target'] ?? $data[0]['source'] ?? '';
				}
			}
		}

		return $translations;
	}

	public function getLanguageTranslations(string $sourceFile, string $languageKey, string $overridePath = ''): array
	{
		$originalPath = GeneralUtility::getFileAbsFileName($sourceFile);

		// Try to find original language file
		$langFilePath = $this->getLanguageFilePath($originalPath, $languageKey);

		$translations = [];

		// Parse original file for keys
		$original = $this->xliffParser->getParsedData($originalPath, 'default');
		foreach ($original['default'] as $key => $data) {
			$translations[$key] = [
				'key' => $key,
				'source' => $data[0]['source'] ?? '',
				'translation' => '',
				'override' => '',
			];
		}

		// Parse language file if exists
		if ($langFilePath && file_exists($langFilePath)) {
			$langData = $this->xliffParser->getParsedData($langFilePath, $languageKey);
			foreach ($langData[$languageKey] as $key => $data) {
				if (isset($translations[$key])) {
					$translations[$key]['translation'] = $data[0]['target'] ?? '';
				}
			}
		}

		// Parse override if exists
		if ($overridePath && file_exists($overridePath)) {
			$override = $this->xliffParser->getParsedData($overridePath, $languageKey);
			foreach ($override[$languageKey] as $key => $data) {
				if (isset($translations[$key])) {
					$translations[$key]['override'] = $data[0]['target'] ?? '';
				}
			}
		}

		return $translations;
	}

	public function saveTranslations(string $overridePath, array $translations, string $languageKey = 'default', string $sourceFile = ''): void
	{
		// Ensure directory exists
		GeneralUtility::mkdir_deep(dirname($overridePath));

		// Filter out empty overrides
		$translationsToSave = array_filter($translations, function($translation) {
			return !empty($translation['override']);
		});

		// Build XLIFF content
		$xliff = $this->buildXliffContent($translationsToSave, $languageKey, $sourceFile);

		file_put_contents($overridePath, $xliff);
	}

	private function buildXliffContent(array $translations, string $languageKey, string $sourceFile): string
	{
		$isDefault = $languageKey === 'default';
		$targetLang = $isDefault ? '' : " target-language=\"{$languageKey}\"";

		$xliff = <<<XML
<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<xliff version="1.0">
    <file source-language="en"{$targetLang} datatype="plaintext" original="{$sourceFile}" date="DATE_PLACEHOLDER" product-name="label_editor">
        <header/>
        <body>

XML;

		foreach ($translations as $key => $translation) {
			$overrideValue = $translation['override'] ?? '';

			if (empty($overrideValue)) {
				continue;
			}

			$source = htmlspecialchars($translation['source'] ?? '', ENT_XML1, 'UTF-8');
			$target = htmlspecialchars($overrideValue, ENT_XML1, 'UTF-8');

			if ($isDefault) {
				$xliff .= <<<XML
            <trans-unit id="{$key}">
                <source>{$target}</source>
            </trans-unit>

XML;
			} else {
				$xliff .= <<<XML
            <trans-unit id="{$key}">
                <source>{$source}</source>
                <target>{$target}</target>
            </trans-unit>

XML;
			}
		}

		$xliff .= <<<XML
        </body>
    </file>
</xliff>
XML;

		return str_replace('DATE_PLACEHOLDER', date('Y-m-d\TH:i:s\Z'), $xliff);
	}

	public function getAvailableLanguages(): array
	{
		$languages = [
			'default' => 'Default (English)',
		];

		foreach ($this->siteFinder->getAllSites() as $site) {
			foreach ($site->getAllLanguages() as $language) {
				$langKey = $language->getTypo3Language();
				if (!isset($languages[$langKey])) {
					$languages[$langKey] = $language->getTitle();
				}
			}
		}

		return $languages;
	}

	private function getLanguageFilePath(string $originalPath, string $languageKey): string
	{
		$dir = dirname($originalPath);
		$filename = basename($originalPath);
		return $dir . '/' . $languageKey . '.' . $filename;
	}

}
