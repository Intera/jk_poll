<?php
if (!defined ("TYPO3_MODE")) 	die ("Access denied.");

t3lib_extMgm::allowTableOnStandardPages("tx_jkpoll_poll");


t3lib_div::loadTCA("tt_content");
$TCA["tt_content"]["types"]["list"]["subtypes_excludelist"][$_EXTKEY."_pi1"]="layout,select_key";
$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY.'_pi1']='pi_flexform';

t3lib_extMgm::addPlugin(Array("LLL:EXT:jk_poll/locallang_db.xml:tt_content.list_type_pi1", $_EXTKEY."_pi1"),"list_type");
t3lib_extMgm::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'Poll');
t3lib_extMgm::addPiFlexFormValue($_EXTKEY.'_pi1', 'FILE:EXT:jk_poll/flexform_ds.xml');

if (TYPO3_MODE=="BE")	$TBE_MODULES_EXT["xMOD_db_new_content_el"]["addElClasses"]["tx_jkpoll_pi1_wizicon"] = t3lib_extMgm::extPath($_EXTKEY)."pi1/class.tx_jkpoll_pi1_wizicon.php";
?>
