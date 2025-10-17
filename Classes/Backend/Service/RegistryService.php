<?php

declare(strict_types=1);

namespace UBOS\LabelEditor\Backend\Service;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RegistryService
{
	private string $registryFile;
	private string $overrideBasePath;
	private string $extensionDataPath;

	public function __construct()
	{
		$this->extensionDataPath = Environment::getVarPath() . '/label_editor';
		$this->overrideBasePath = $this->extensionDataPath . '/overrides';
		$this->registryFile = $this->extensionDataPath . '/registry.json';
	}


	public function getRegistry(): array
	{
		if (!file_exists($this->registryFile)) {
			return [];
		}

		$content = file_get_contents($this->registryFile);
		return json_decode($content, true) ?? [];
	}

	public function getManagedExtensions(): array
	{
		$registry = $this->getRegistry();
		$extensions = [];

		foreach ($registry as $sourceFile => $overrides) {
			// Extract extension key from EXT:extension_key/...
			if (preg_match('/^EXT:([^\/]+)\//', $sourceFile, $matches)) {
				$extensionKey = $matches[1];
				if (!isset($extensions[$extensionKey])) {
					$extensions[$extensionKey] = [];
				}
				$extensions[$extensionKey][] = $sourceFile;
			}
		}

		return $extensions;
	}

	public function addExtension(string $extensionKey, array $locallangFiles): void
	{
		$registry = $this->getRegistry();

		foreach ($locallangFiles as $sourceFile) {
			if (!isset($registry[$sourceFile])) {
				$registry[$sourceFile] = $this->createOverridePaths($sourceFile);
			}
		}

		$this->saveRegistry($registry);
	}

	public function removeExtension(string $extensionKey): void
	{
		$registry = $this->getRegistry();

		foreach (array_keys($registry) as $sourceFile) {
			if (str_starts_with($sourceFile, "EXT:{$extensionKey}/")) {
				unset($registry[$sourceFile]);
			}
		}

		$this->saveRegistry($registry);
	}

	public function getOverridePath(string $sourceFile, string $languageKey = 'default'): string
	{
		$registry = $this->getRegistry();
		return $registry[$sourceFile][$languageKey] ?? '';
	}

	private function createOverridePaths(string $sourceFile): array
	{
		// Extract extension key and relative path
		// e.g., EXT:puck/Resources/Private/Language/Backend/locallang.xlf
		preg_match('/^EXT:([^\/]+)\/Resources\/Private\/Language\/(.+)$/', $sourceFile, $matches);

		if (!$matches) {
			// Fallback if path doesn't match expected pattern
			preg_match('/^EXT:([^\/]+)\/(.+)$/', $sourceFile, $matches);
		}

		$extensionKey = $matches[1];
		$relativeFilePath = $matches[2] ?? basename($sourceFile);

		// Create directory structure: var/label_editor/overrides/{ext}/{relative-to-Language}
		$overrideDir = $this->overrideBasePath . '/' . $extensionKey;
		if (str_contains($relativeFilePath, '/')) {
			// If file is in subdirectory (e.g., Backend/locallang.xlf), preserve that
			$overrideDir .= '/' . dirname($relativeFilePath);
		}

		GeneralUtility::mkdir_deep($overrideDir);

		$filename = basename($relativeFilePath);

		// Create paths for each language
		$paths = [
			'default' => $overrideDir . '/' . $filename,
		];

		// Get available site languages
		$siteFinder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Site\SiteFinder::class);
		foreach ($siteFinder->getAllSites() as $site) {
			foreach ($site->getAllLanguages() as $language) {
				$langKey = $language->getTypo3Language();
				if ($langKey !== 'default' && !isset($paths[$langKey])) {
					$paths[$langKey] = $overrideDir . '/' . $langKey . '.' . $filename;
				}
			}
		}

		return $paths;
	}

	private function saveRegistry(array $registry): void
	{
		GeneralUtility::mkdir_deep($this->extensionDataPath);
		file_put_contents(
			$this->registryFile,
			json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
		);
	}
}