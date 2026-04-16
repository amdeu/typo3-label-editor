<?php

$EM_CONF[$_EXTKEY] = [
	'title' => 'Label Editor',
	'description' => 'Allows managing translation overrides for locallang files directly from the backend',
	'category' => 'be',
	'author' => 'Amadeus Kiener',
	'state' => 'stable',
	'version' => '1.1.1',
	'constraints' => [
		'depends' => [
			'typo3' => '13.4.0-13.99.99',
		],
		'conflicts' => [],
	],
];