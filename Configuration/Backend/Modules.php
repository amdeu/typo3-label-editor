<?php

declare(strict_types=1);

use Amdeu\LabelEditor\Backend\Controller\LabelEditorController;

return [
	'web_labeleditor' => [
		'parent' => 'web',
		'position' => ['after' => 'web_list'],
		'access' => 'user',
		'workspaces' => 'live',
		'path' => '/module/web/label-editor',
		'labels' => 'LLL:EXT:label_editor/Resources/Private/Language/locallang_mod.xlf',
		'extensionName' => 'LabelEditor',
		'iconIdentifier' => 'module-label-editor',
		'inheritNavigationComponentFromMainModule' => false,
		'controllerActions' => [
			LabelEditorController::class => [
				'index',
				'addExtension',
				'removeExtension',
				'editExtension',
				'saveTranslation',
				'addLabel',
				'removeLabel'
			],
		],
	],
];