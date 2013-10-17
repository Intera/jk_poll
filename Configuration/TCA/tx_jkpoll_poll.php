<?php
return array(
	'ctrl' => array(
		'title' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll',
		'label' => 'title',
		'default_sortby' => 'ORDER BY crdate DESC',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'delete' => 'deleted',
		'enablecolumns' => array(
			'disabled' => 'hidden',
			'starttime' => 'starttime',
			'endtime' => 'endtime',
			'fe_group' => 'fe_group',
		),
		'iconfile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('jk_poll') . 'icon_tx_jkpoll_poll.gif',

		'prependAtCopy' => 'LLL:EXT:lang/locallang_general.php:LGL.prependAtCopy',
		'copyAfterDuplFields' => 'sys_language_uid',
		'useColumnsForDefaultValues' => 'sys_language_uid',
		'transOrigPointerField' => 'l18n_parent',
		'transOrigDiffSourceField' => 'l18n_diffsource',
		'languageField' => 'sys_language_uid',
		'dividers2tabs' => 1

	),
	'feInterface' => array(
		'fe_admin_fieldList' => 'hidden, starttime, endtime, fe_group, title, image, question, votes, answers, colors, valid_till, answers_image, answers_description, explanation',
	),
	'interface' => array(
		'showRecordFieldList' => 'hidden,starttime,endtime,fe_group,title,image,question,votes,answers,colors,crdate'
	),
	'columns' => array(
		'crdate' => array(
			'exclude' => 1,
			'l10n_mode' => 'mergeIfNotBlank',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.crdate',
			'config' => array(
				'type' => 'input',
				'size' => '15',
				'eval' => 'date',
			)
		),
		'hidden' => array(
			'exclude' => 1,
			'l10n_mode' => 'mergeIfNotBlank',
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.hidden',
			'config' => array(
				'type' => 'check',
				'default' => '0'
			)
		),
		'starttime' => array(
			'exclude' => 1,
			'l10n_mode' => 'mergeIfNotBlank',
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.starttime',
			'config' => array(
				'type' => 'input',
				'size' => '8',
				'max' => '20',
				'eval' => 'date',
				'default' => '0',
				'checkbox' => '0'
			)
		),
		'endtime' => array(
			'exclude' => 1,
			'l10n_mode' => 'mergeIfNotBlank',
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.endtime',
			'config' => array(
				'type' => 'input',
				'size' => '8',
				'max' => '20',
				'eval' => 'date',
				'checkbox' => '0',
				'default' => '0',
				'range' => array(
					'upper' => mktime(0, 0, 0, 12, 31, 2020),
					'lower' => mktime(0, 0, 0, date('m') - 1, date('d'), date('Y'))
				)
			)
		),
		'fe_group' => array(
			'exclude' => 1,
			'l10n_mode' => 'mergeIfNotBlank',
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.fe_group',
			'config' => array(
				'type' => 'select',
				'items' => array(
					array('', 0),
					array('LLL:EXT:lang/locallang_general.php:LGL.hide_at_login', -1),
					array('LLL:EXT:lang/locallang_general.php:LGL.any_login', -2),
					array('LLL:EXT:lang/locallang_general.php:LGL.usergroups', '--div--')
				),
				'foreign_table' => 'fe_groups'
			)
		),
		'title' => array(
			'exclude' => 1,
			'l10n_mode' => 'prefixLangTitle',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.title',
			'config' => array(
				'type' => 'input',
				'size' => '30',
				'eval' => 'required',
			)
		),
		'image' => array(
			'exclude' => 1,
			'l10n_mode' => 'exclude',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.image',
			'config' => array(
				'type' => 'group',
				'internal_type' => 'file',
				'allowed' => $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'],
				'max_size' => 500,
				'uploadfolder' => 'uploads/tx_jkpoll',
				'show_thumbs' => 1,
				'size' => 1,
				'minitems' => 0,
				'maxitems' => 1,
			)
		),
		'question' => array(
			'exclude' => 1,
			'l10n_mode' => 'prefixLangTitle',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.question',
			'config' => array(
				'type' => 'text',
				'cols' => '30',
				'rows' => '5',
				'wizards' => array(
					'_PADDING' => 2,
					'RTE' => array(
						'notNewRecords' => 1,
						'RTEonly' => 1,
						'type' => 'script',
						'title' => 'Full screen Rich Text Editing|Formatteret redigering i hele vinduet',
						'icon' => 'wizard_rte2.gif',
						'script' => 'wizard_rte.php',
					),
				),
			)
		),
		'votes' => array(
			'exclude' => 1,
			'l10n_mode' => 'exclude',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.votes',
			'config' => array(
				'type' => 'none',
				'cols' => '30',
				'rows' => '5',
			)
		),
		'answers' => array(
			'exclude' => 1,
			'l10n_mode' => 'prefixLangTitle',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.answers',
			'config' => array(
				'type' => 'text',
				'cols' => '30',
				'rows' => '5',
			)
		),
		'colors' => array(
			'exclude' => 1,
			'l10n_mode' => 'mergeIfNotBlank',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.colors',
			'config' => array(
				'type' => 'text',
				'cols' => '30',
				'rows' => '5',
			)
		),
		'votes_count' => array(
			'exclude' => 1,
			'l10n_mode' => 'exclude',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.votes_count',
			'config' => array(
				'type' => 'none',
				'cols' => '30',
			)
		),
		'valid_till' => array(
			'exclude' => 1,
			'l10n_mode' => 'mergeIfNotBlank',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.valid_till',
			'config' => array(
				'type' => 'input',
				'size' => '8',
				'max' => '20',
				'eval' => 'date',
				'checkbox' => '0',
				'default' => '0'
			),
		),
		'title_tag' => array(
			'exclude' => 1,
			'l10n_mode' => 'prefixLangTitle',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.title_tag',
			'config' => array(
				'type' => 'text',
				'cols' => '30',
				'rows' => '2',
			)
		),
		'alternative_tag' => array(
			'exclude' => 1,
			'l10n_mode' => 'prefixLangTitle',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.alternative_tag',
			'config' => array(
				'type' => 'text',
				'cols' => '30',
				'rows' => '2',
			)
		),
		'width' => array(
			'exclude' => 1,
			'l10n_mode' => 'exclude',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.width',
			'config' => array(
				'type' => 'input',
				'size' => '4',
				'max' => '4',
				'eval' => 'int',
				'checkbox' => '0',
				'range' => array(
					'upper' => '1000',
					'lower' => '10'
				),
				'default' => 0
			)
		),
		'height' => array(
			'exclude' => 1,
			'l10n_mode' => 'exclude',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.height',
			'config' => array(
				'type' => 'input',
				'size' => '4',
				'max' => '4',
				'eval' => 'int',
				'checkbox' => '0',
				'range' => array(
					'upper' => '1000',
					'lower' => '10'
				),
				'default' => 0
			)
		),
		'link' => array(
			'exclude' => 1,
			'l10n_mode' => 'mergeIfNotBlank',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.link',
			'config' => array(
				'type' => 'input',
				'size' => '15',
				'max' => '255',
				'checkbox' => '',
				'eval' => 'trim',
				'wizards' => array(
					'_PADDING' => 2,
					'link' => array(
						'type' => 'popup',
						'title' => 'Link',
						'icon' => 'link_popup.gif',
						'script' => 'browse_links.php?mode=wizard',
						'JSopenParams' => 'height=300,width=500,status=0,menubar=0,scrollbars=1'
					)
				)
			)
		),
		'clickenlarge' => array(
			'exclude' => 1,
			'l10n_mode' => 'exclude',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.clickenlarge',
			'config' => array(
				'type' => 'check',
			)
		),
		'answers_image' => array(
			'exclude' => 1,
			'l10n_mode' => 'exclude',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.answers_image',
			'config' => array(
				'type' => 'group',
				'internal_type' => 'file',
				'allowed' => $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'],
				'max_size' => 500,
				'uploadfolder' => 'uploads/tx_jkpoll',
				'show_thumbs' => 1,
				'size' => 3,
				'minitems' => 0,
				'maxitems' => 20,
			)
		),
		'answers_description' => array(
			'exclude' => 1,
			'l10n_mode' => 'prefixLangTitle',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.answers_description',
			'config' => array(
				'type' => 'text',
				'cols' => '30',
				'rows' => '5',
			)
		),
		'explanation' => array(
			'exclude' => 1,
			'l10n_mode' => 'prefixLangTitle',
			'label' => 'LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.explanation',
			'config' => array(
				'type' => 'text',
				'cols' => '30',
				'rows' => '3',
				'wizards' => array(
					'_PADDING' => 2,
					'RTE' => array(
						'notNewRecords' => 1,
						'RTEonly' => 1,
						'type' => 'script',
						'title' => 'Full screen Rich Text Editing|Formatteret redigering i hele vinduet',
						'icon' => 'wizard_rte2.gif',
						'script' => 'wizard_rte.php',
					),
				),
			)
		),
		'sys_language_uid' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.language',
			'config' => array(
				'type' => 'select',
				'foreign_table' => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title',
				'items' => array(
					array('LLL:EXT:lang/locallang_general.php:LGL.allLanguages', -1),
					array('LLL:EXT:lang/locallang_general.php:LGL.default_value', 0)
				)
			)
		),
		'l18n_parent' => array(
			'displayCond' => 'FIELD:sys_language_uid:>:0',
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.l18n_parent',
			'config' => array(
				'type' => 'select',
				'items' => array(
					array('', 0),
				),
				'foreign_table' => 'tx_jkpoll_poll',
				'foreign_table_where' => 'AND tx_jkpoll_poll.uid=###REC_FIELD_l18n_parent### AND tx_jkpoll_poll.sys_language_uid IN (-1,0)',
				'wizards' => array(
					'_PADDING' => 2,
					'_VERTICAL' => 1,
					'edit' => array(
						'type' => 'popup',
						'title' => 'edit default language version of this record ',
						'script' => 'wizard_edit.php',
						'popup_onlyOpenIfSelected' => 1,
						'icon' => 'edit2.gif',
						'JSopenParams' => 'height=600,width=700,status=0,menubar=0,scrollbars=1,resizable=1',
					)
				)
			)
		),
		'l18n_diffsource' => array(
			'config' => array(
				'type' => 'passthrough'
			)
		),
	),
	'types' => array(
		'0' => array(
			'showitem' => 'crdate, title, question;;4;richtext[paste|bold|italic|underline|formatblock|class|left|center|right|orderedlist|unorderedlist|outdent|indent|link|image]:rte_transform[mode=ts], answers;;2, colors, explanation;;;richtext[paste|bold|italic|underline|formatblock|class|left|center|right|orderedlist|unorderedlist|outdent|indent|link|image]:rte_transform[mode=ts];1-1-1,
				--div--;LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.tabs.image, image;;3, title_tag, alternative_tag, width, height,
				--div--;LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.tabs.answers, answers_image, answers_description,
				--div--;LLL:EXT:jk_poll/locallang_db.xml:tx_jkpoll_poll.tabs.access, hidden;;1, valid_till'
		),
	),
	'palettes' => array(
		'1' => array('showitem' => 'starttime, endtime, fe_group'),
		'2' => array('showitem' => 'votes_count, votes'),
		'3' => array('showitem' => 'link, clickenlarge'),
	),
);