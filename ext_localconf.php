<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// Register icon
$iconRegistry = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
$iconRegistry->registerIcon(
	'module-label-editor',
	\TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
	['source' => 'EXT:label_editor/Resources/Public/Icons/Extension.svg']
);

// Load translation overrides from registry
$registryFile = Environment::getVarPath() . '/label_editor/registry.json';

if (file_exists($registryFile)) {
	$registry = json_decode(file_get_contents($registryFile), true);

	if (is_array($registry) && !empty($registry)) {
		foreach ($registry as $sourceFile => $overrides) {
			if (isset($overrides['default'])) {
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride'][$sourceFile][] =
					$overrides['default'];
			}

			foreach ($overrides as $langKey => $overridePath) {
				if ($langKey !== 'default') {
					$GLOBALS['TYPO3_CONF_VARS']['SYS']['locallangXMLOverride'][$langKey][$sourceFile][] =
						$overridePath;
				}
			}
		}
	}
}
