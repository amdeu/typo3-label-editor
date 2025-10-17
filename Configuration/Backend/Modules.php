<?php

declare(strict_types=1);

use UBOS\LabelEditor\Backend\Controller\LabelEditorController;

return [
	'site_labeleditor' => [
		'parent' => 'site',
		'position' => ['after' => 'site_configuration'],
		'access' => 'admin',
		'workspaces' => 'live',
		'path' => '/module/site/label-editor',
		'labels' => 'LLL:EXT:label_editor/Resources/Private/Language/locallang_mod.xlf',
		'extensionName' => 'LabelEditor',
		'iconIdentifier' => 'module-label-editor',
		'controllerActions' => [
			LabelEditorController::class => [
				'index',
				'addExtension',
				'removeExtension',
				'editExtension',
				'saveTranslation',
			],
		],
	],
];
