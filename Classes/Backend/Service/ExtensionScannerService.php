<?php

declare(strict_types=1);

namespace UBOS\LabelEditor\Backend\Service;

use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

class ExtensionScannerService
{
	public function __construct(
		private readonly PackageManager $packageManager
	) {}

	public function getAvailableExtensions(): array
	{
		$extensions = [];

		foreach ($this->packageManager->getAvailablePackages() as $package) {
			$extensionKey = $package->getPackageKey();
			$icon = $package->getPackageIcon();
			$extensions[$extensionKey] = [
				'key' => $extensionKey,
				'title' => $package->getPackageMetaData()->getTitle() ?: $extensionKey,
				'path' => $package->getPackagePath(),
				'icon' => $icon ? PathUtility::getAbsoluteWebPath($package->getPackagePath() . $icon) : '',
			];
		}

		ksort($extensions);
		return $extensions;
	}

	public function findLocallangFiles(string $extensionKey): array
	{
		$package = $this->packageManager->getPackage($extensionKey);
		$languagePath = $package->getPackagePath() . 'Resources/Private/Language/';

		if (!is_dir($languagePath)) {
			return [];
		}

		$files = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($languagePath, \RecursiveDirectoryIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getExtension() === 'xlf') {
				$filename = $file->getFilename();

				// Only base files, not translations (e.g., de.locallang.xlf)
				if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?\\./', $filename)) {
					$relativePath = str_replace($package->getPackagePath(), '', $file->getPathname());
					$sourceFile = 'EXT:' . $extensionKey . '/' . $relativePath;
					$files[] = $sourceFile;
				}
			}
		}

		sort($files);
		return $files;
	}
}
