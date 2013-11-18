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
	'version' => '0.9.6',
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
			'typo3' => '4.5.0-4.7.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:33:{s:20:"class.ext_update.php";s:4:"b038";s:12:"ext_icon.gif";s:4:"c9e1";s:17:"ext_localconf.php";s:4:"d138";s:14:"ext_tables.php";s:4:"3d8d";s:14:"ext_tables.sql";s:4:"9369";s:24:"ext_typoscript_setup.txt";s:4:"1f21";s:15:"flexform_ds.xml";s:4:"96ee";s:23:"icon_tx_jkpoll_poll.gif";s:4:"c9e1";s:16:"locallang_db.xml";s:4:"da71";s:7:"tca.php";s:4:"bee3";s:14:"doc/manual.sxw";s:4:"630b";s:19:"doc/wizard_form.dat";s:4:"4f02";s:20:"doc/wizard_form.html";s:4:"4495";s:16:"images/black.gif";s:4:"72b8";s:15:"images/blau.gif";s:4:"8875";s:15:"images/blue.gif";s:4:"8875";s:15:"images/gelb.gif";s:4:"1efd";s:16:"images/green.gif";s:4:"dfc5";s:16:"images/gruen.gif";s:4:"dfc5";s:14:"images/red.gif";s:4:"8234";s:14:"images/rot.gif";s:4:"8234";s:18:"images/schwarz.gif";s:4:"72b8";s:17:"images/yellow.gif";s:4:"1efd";s:14:"pi1/ce_wiz.gif";s:4:"02b6";s:27:"pi1/class.tx_jkpoll_pi1.php";s:4:"2d70";s:35:"pi1/class.tx_jkpoll_pi1_wizicon.php";s:4:"4852";s:13:"pi1/clear.gif";s:4:"cc11";s:17:"pi1/locallang.xml";s:4:"cbb5";s:15:"res/jk_poll.css";s:4:"5a1c";s:16:"res/jk_poll.tmpl";s:4:"7095";s:23:"res/jk_poll_images.tmpl";s:4:"775c";s:28:"res/jk_poll_images_list.tmpl";s:4:"8a5e";s:29:"res/jk_poll_results_list.tmpl";s:4:"7478";}',
);

?>