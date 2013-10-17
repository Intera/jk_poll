<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

// Extending TypoScript from static template uid=43 to set up userdefined tag:
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript($_EXTKEY, 'editorcfg', '
	tt_content.CSS_editor.ch.tx_jkpoll_pi1 = < plugin.tx_jkpoll_pi1.CSS_editor
', 43);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPItoST43($_EXTKEY, 'pi1/class.tx_jkpoll_pi1.php', '_pi1', 'list_type', 0);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript($_EXTKEY, 'setup', '
	tt_content.shortcut.20.0.conf.tx_jkpoll_poll = < plugin.' . \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getCN($_EXTKEY) . '_pi1
	tt_content.shortcut.20.0.conf.tx_jkpoll_poll.CMD = singleView
', 43);

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cms']['db_layout']['addTables']['tx_jkpoll_poll'][0] = array(
	'fList' => 'title',
	'icon' => TRUE
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('

mod.wizards.newContentElement.wizardItems.special {

	elements.tx_jk_poll_pi1 {
		icon = ' . \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('jk_poll') . 'pi1/ce_wiz.gif
		title = LLL:EXT:jk_poll/locallang_db.xml:pi1_title
		description = LLL:EXT:jk_poll/locallang_db.xml:pi1_plus_wiz_description
		tt_content_defValues {
			CType = list
			list_type = jk_poll_pi1
		}
    }

    show := addToList(tx_jk_poll_pi1)
}
');
