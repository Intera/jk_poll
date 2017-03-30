<?php

########################################################################
# Extension Manager/Repository config file for ext "jk_poll".
#
# Auto generated 30-08-2012 22:50
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Poll',
	'description' => 'A poll based on the extension quickpoll. A template-file can be used to define the output in the frontend. It is also possible to create a horiontal or vertical display of the percentage of users voted for an answer.',
	'category' => 'plugin',
	'shy' => 1,
	'version' => '1.1.0',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'state' => 'beta',
	'uploadfolder' => 1,
	'createDirs' => '',
	'modify_tables' => '',
	'clearcacheonload' => 0,
	'lockType' => '',
	'author' => 'Johannes Krausmueller',
	'author_email' => 'johannes@krausmueller.de',
	'author_company' => '',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'constraints' => array(
		'depends' => array(
			'typo3' => '7.6.0-7.6.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
);
