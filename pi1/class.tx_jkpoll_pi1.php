<?php
/***************************************************************
 * Copyright notice
 *
 * (c) 2004 Johannes Krausmueller (johannes@krausmueller.de)
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Plugin 'Poll' for the 'jk_poll' extension.
 *
 * @author Johannes Krausmueller <johannes@krausmueller.de>
 */
class tx_jkpoll_pi1 extends \TYPO3\CMS\Frontend\Plugin\AbstractPlugin {

	public $prefixId = 'tx_jkpoll_pi1'; // Same as class name
	public $scriptRelPath = 'pi1/class.tx_jkpoll_pi1.php'; // Path to this script relative to the extension dir.
	public $extKey = 'jk_poll'; // The extension key.
	public $pi_checkCHash = FALSE;

	/**
	 * "answer" POST variable (containing the selected answers)
	 *
	 * @var array
	 */
	protected $answer;

	/**
	 * "captcha" POST variable
	 *
	 * @var string
	 */
	protected $captcha;

	/**
	 * Instance of srfreecap plugin if available
	 *
	 * @var object
	 */
	protected $freeCap;

	/**
	 * "go" POST variable
	 *
	 * @var string
	 */
	protected $go;

	/**
	 * Contains the enable field query for the tx_jkpoll_poll table
	 *
	 * @var string
	 */
	protected $pollEnableFields;

	/**
	 * Page UID where the votes are stored
	 *
	 * @var int
	 */
	protected $pid;

	/**
	 * The ID of the currenlty active poll
	 *
	 * @var int
	 */
	protected $pollID;

	/**
	 * If the current poll is a translation pollID_parent contains the
	 * UID of the non-translated version, otherwise 0
	 *
	 * @var int
	 */
	protected $pollID_parent;

	/**
	 * Contains the IP address of the current client
	 *
	 * @var string
	 */
	protected $REMOTE_ADDR;

	/**
	 * "captacha_response" POST variable
	 *
	 * @var string
	 */
	protected $sr_captcha;

	/**
	 * Template code
	 *
	 * @var string
	 */
	protected $templateCode;

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $typo3Db;

	/**
	 * TRUE if the valid_till date is not in the past
	 *
	 * @var boolean
	 */
	protected $valid;

	/**
	 * TRUE if current poll is votable. This will be FALSE if vote_old
	 * is set to FALSE and the current poll is not the newest poll.
	 *
	 * @var boolean
	 */
	protected $voteable;

	function main($content, $conf) {
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_initPIflexForm();
		$this->typo3Db = $GLOBALS['TYPO3_DB'];

		$this->pollEnableFields = $this->cObj->enableFields('tx_jkpoll_poll');

		// this will convert any string which is supplied as $_SERVER['REMOTE_ADDR'] into a valid ip address
		$currentRemoteAddress = \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('REMOTE_ADDR');
		$this->REMOTE_ADDR = long2ip(ip2long($currentRemoteAddress));

		// initialize sr_freecap
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'captcha', 's_poll') == "sr_freecap" || $this->conf['captcha'] == "sr_freecap") {
			if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('sr_freecap')) {
				require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('sr_freecap') . 'pi2/class.tx_srfreecap_pi2.php');
				$this->freeCap = GeneralUtility::makeInstance('tx_srfreecap_pi2');
			}
		}

		// Get ID of poll ($this->PollID) or error msg. if no poll was found
		if (!$this->getPollID()) {
			$content = '<div class="' . $this->getConfigValue('error_container_class') . '">' . $this->pi_getLL('no_poll_found') . '</div>';
			return $this->pi_wrapInBaseClass($content);
		}

		// Define CSS file (get from config or use default)
		if (($this->conf['css_file'] != "none") && ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'css_file', 'sDEF') != "none")) {
			if ($this->conf['css_file'] != "") {
				$cssFile = $this->conf['css_file'];
			} elseif ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'css_file', 'sDEF') != "") {
				$cssFile = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'css_file', 'sDEF');
			} else {
				$cssFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath($this->extKey) . 'res/jk_poll.css';
			}
			$GLOBALS['TSFE']->additionalHeaderData[$this->extKey] = '<link rel="stylesheet" href="' . $cssFile . '" type="text/css" />';
		}

		// Get template-file
		$templateFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath($this->extKey) . 'res/jk_poll.tmpl';
		$customTemplate = $this->getConfigValue('templatefile', 'sDEF');
		if (!empty($customTemplate)) {
			$templateFile = $customTemplate;
		}
		$this->templateCode = $this->cObj->fileResource($templateFile);

		// Poll should be displayed
		if (strchr($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'what_to_display', 'sDEF'), "POLL") || $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'what_to_display', 'sDEF') == '') {
			// The Get/Post variables
			$postVars = GeneralUtility::_GP($this->prefixId);
			$getVars = GeneralUtility::_GET($this->prefixId);
			if ($postVars['go']) {
				$this->go = $postVars['go'];
			} else {
				$this->go = $getVars['go'];
			}
			$this->answer = $postVars['answer'];
			$this->captcha = $postVars['captcha'];
			$this->sr_captcha = $postVars['captcha_response'];
			switch ($this->go) {
				case 'savevote':
					if ($postVars['pollID'] == $this->pollID) {
						$content = $this->savevote();
					} else {
						$content = $this->showpoll();
					}
					break;
				case 'list':
					$content = $this->showlist();
					break;
				case 'result':
					$content = $this->showresults();
					break;
				default:
					$content = $this->showpoll();
					break;
			}
		}
		// List should be displayed
		if (strchr($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'what_to_display', 'sDEF'), "LIST") || !strcmp($this->go, 'list')) {
			$content = $this->showlist();
		}
		// Result should be displayed
		if (strchr($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'what_to_display', 'sDEF'), "RESULT") || !strcmp($this->go, 'result')) {
			$content = $this->showresults();
		}
		return $this->pi_wrapInBaseClass($content);
	}

	/**
	 * Shows the poll questions and lets the user votes for one answer or shows results if user already voted
	 *
	 * @return string HTML to display in frontend
	 */
	function showpoll() {

		// Get poll data
		$res = $this->typo3Db->exec_SELECTquery(
			'*',
			'tx_jkpoll_poll',
				'uid=' . $this->pollID . ' AND sys_language_uid=' . $GLOBALS['TSFE']->sys_language_content . $this->pollEnableFields
		);

		$content = '';
		if ($res && $row = $this->typo3Db->sql_fetch_assoc($res)) {

			// Put answers and votes in array
			$answers = explode("\n", $row['answers']);
			$votes = explode("\n", $row['votes']);
			$answers_description = explode("\n", $row['answers_description']);
			$answers_image = explode(",", $row['answers_image']);

			// Put in a 0 if there are no votes yet:
			$needsupdate = FALSE;
			foreach ($answers as $i => $a) {
				if (!is_numeric(trim($votes[$i])) || $votes[$i] == '') {
					$votes[$i] = '0';
					$needsupdate = TRUE;
				}
			}
			// write votes back to DB
			if ($needsupdate) {
				$dataArr['votes'] = implode("\n", $votes);
				$this->typo3Db->exec_UPDATEquery(
					'tx_jkpoll_poll',
						'uid=' . $this->pollID,
					$dataArr
				);
			}

			$template = array();
			$template['poll_header'] = $this->cObj->getSubpart($this->templateCode, "###POLL_HEADER###");
			$template['poll_vote'] = $this->cObj->getSubpart($this->templateCode, "###POLL_VOTE###");
			$template['answer'] = $this->cObj->getSubpart($this->templateCode, "###ANSWER_VOTE###");

			// replace poll_header
			$markerArrayQuestion = array();
			$markerArrayQuestion["###TITLE###"] = $this->renderTitle($row['title']);
			$markerArrayQuestion["###QUESTION_IMAGE###"] = $this->getimage($this->pollID, '', '');
			$markerArrayQuestion["###QUESTIONTEXT###"] = $this->cObj->stdWrap($row['question'], $this->conf['rtefield_stdWrap.']);

			// include link to list
			if ($this->getConfigValue('list', 's_poll')) {
				// build url for linklist
				$getParams = array_merge(
					GeneralUtility::_GET(),
					array(
						$this->prefixId . '[go]' => 'list',
						$this->prefixId . '[uid]' => $this->pollID
					)
				);
				$ll_alink = $this->pi_getPageLink($GLOBALS['TSFE']->id, '', $getParams);
				$subpartArray["###LINKLIST###"] = '<a class="jk_poll_linklist" href="' . $ll_alink . '">' . $this->pi_getLL('linklist') . '</a>';
			} else {
				$subpartArray["###LINKLIST###"] = '';
			}

			$content .= $this->cObj->substituteMarkerArray($template["poll_header"], $markerArrayQuestion);

			if ((!$this->conf['check_language_specific'] && !$this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'check_language_specific', 'sDEF')) && $this->pollID_parent != 0) {
				$check_poll_id = $this->pollID_parent;
			} else {
				$check_poll_id = $this->pollID;
			}

			// check if enddate is set
			$this->valid = $this->checkPollValid($check_poll_id);

			// Check for logged IPs
			$ip_voted = FALSE;
			if ($this->getConfigValue('check_ip', 's_poll')) {
				// get timestamp after which vote is possible again
				$ipLockTime = intval($this->getConfigValue('check_ip_time', 's_poll', 'time'));
				if ($ipLockTime > 0) {
					$maxIpTimestamp = $GLOBALS['SIM_EXEC_TIME'] - $ipLockTime * 3600;
					$res = $this->typo3Db->exec_SELECTquery(
						'*',
						'tx_jkpoll_iplog',
						'pid=' . $check_poll_id . ' AND ip=' . $this->typo3Db->fullQuoteStr($this->REMOTE_ADDR, 'tx_jkpoll_iplog') . ' AND tstamp >= ' . $maxIpTimestamp
					);
					if ($this->typo3Db->sql_num_rows($res) > 0) {
						$ip_voted = TRUE;
					}
				}
			}

			// Check for fe_users who already voted
			$user_logged_in = FALSE;
			$user_voted = TRUE;
			$check_user = $this->getConfigValue('check_user', 's_poll', 'fe_user');
			if ($check_user) {
				$currentUserId = intval($GLOBALS['TSFE']->fe_user->user['uid']);
				if ($currentUserId > 0) {
					$user_logged_in = TRUE;
					$res = $this->typo3Db->exec_SELECTquery(
						'*',
						'tx_jkpoll_userlog',
						'pid=' . $check_poll_id . ' AND fe_user=' . $currentUserId
					);
					if ($this->typo3Db->sql_num_rows($res) === 0) {
						$user_voted = FALSE;
					}
				}
			} else {
				$user_voted = FALSE;
			}

			// Check for cookie. If not found show poll, if found show results.
			$cookieName = 't3_tx_jkpoll_' . $check_poll_id;
			$resultcontentAnswer = '';

			// build url for form
			// get the current GET params, so the language (and maybe more) is preserved within the submit link
			//				$getParams = GeneralUtility::_GET();
			$getParams = array(
				$this->prefixId . '[uid]' => $this->pollID,
			);
			// add get paramters to make it work with extension "comments"
			if ($this->getConfigValue('comments_on_result', 's_result')) {
				$getParams[$this->prefixId . '[uid_comments]'] = $this->pollID;
			}

			$alink = $this->pi_getPageLink($GLOBALS['TSFE']->id, '', $getParams);

			// include link to RESULT view
			if ($this->getConfigValue('link_to_result', 's_poll')) {
				// build url for linklist
				$ll_getParams = array($this->prefixId . '[go]' => 'result', $this->prefixId . '[uid]' => $this->pollID);
				$ll_alink = $this->pi_getPageLink($GLOBALS['TSFE']->id, '', $ll_getParams);
				$markerArray["###LINK_TO_RESULT###"] = '<a class="jk_poll_link_to_result" href="' . $ll_alink . '">' . $this->pi_getLL('link_to_result') . '</a>';
			} else {
				$markerArray["###LINK_TO_RESULT###"] = '';
			}

			// include link to list
			if ($this->getConfigValue('list', 's_poll')) {
				// build url for linklist
				$ll_getParams = array($this->prefixId . '[go]' => 'list');
				$ll_alink = $this->pi_getPageLink($GLOBALS['TSFE']->id, '', $ll_getParams);
				$markerArray["###LINKLIST###"] = '<a class="jk_poll_linklist" href="' . $ll_alink . '">' . $this->pi_getLL('linklist') . '</a>';
			} else {
				$markerArray["###LINKLIST###"] = '';
			}

			if (!isset($_COOKIE[$cookieName]) && !$ip_voted && !$user_voted && $this->voteable && $this->valid) {

				// Make radio buttons
				foreach ($answers as $i => $a) {
					$markerArrayAnswer = array();
					$choiceID = $this->prefixId . '_' . $this->pollID . '_' . $i;
					if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'checkbox', 's_poll') || $this->conf['checkbox']) {
						if ($i == 0 && ($this->conf['first_answer_selected'] || $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'first_answer_selected', 's_poll'))) {
							//							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="'. $this->prefixId. '[answer]" type="radio" checked="checked" value="'. $i .'" />';
							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="' . $this->prefixId . '[answer][]" type="checkbox" checked="checked" value="' . $i . '" ' . 'id="' . $choiceID . '" />';
						} else {
							//							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="'. $this->prefixId. '[answer]" type="radio" value="'. $i .'" />';
							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="' . $this->prefixId . '[answer][]" type="checkbox" value="' . $i . '" ' . 'id="' . $choiceID . '" />';
						}
					} else {
						if ($i == 0 && ($this->conf['first_answer_selected'] || $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'first_answer_selected', 's_poll'))) {
							//							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="'. $this->prefixId. '[answer]" type="radio" checked="checked" value="'. $i .'" />';
							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="' . $this->prefixId . '[answer][]" type="radio" checked="checked" value="' . $i . '" ' . 'id="' . $choiceID . '" />';
						} else {
							//							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="'. $this->prefixId. '[answer]" type="radio" value="'. $i .'" />';
							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="' . $this->prefixId . '[answer][]" type="radio" value="' . $i . '" ' . 'id="' . $choiceID . '" />';
						}
					}
					$markerArrayAnswer["###ANSWERTEXT_CHOICE_ID###"] = $choiceID;
					//					$markerArrayAnswer["###ANSWERTEXT_VALUE###"] = $answers[$i];
					$markerArrayAnswer["###ANSWERTEXT_VALUE###"] = trim($a);
					$markerArrayAnswer["###ANSWERTEXT_IMAGE###"] = $this->getAnswerImage($answers_image[$i]);
					$markerArrayAnswer["###ANSWERTEXT_DESCRIPTION###"] = $answers_description[$i];
					$resultcontentAnswer .= $this->cObj->substituteMarkerArrayCached($template['answer'], $markerArrayAnswer);
				}


				//				$markerArray["###SUBMIT###"] = '<input class="pollsubmit" type="submit" value="'.$this->pi_getLL('submit_button').'" />';
				// store [go] (for marking submitted forms) and a [pollID] (for multiple polls on the same page)
				$markerArray["###SUBMIT###"] = '
					<input type="hidden" name="' . $this->prefixId . '[pollID]" value="' . $this->pollID . '" />
					<input type="hidden" name="' . $this->prefixId . '[go]" value="savevote" />
					';
				if (!$this->conf['custom_submit']) {
					$markerArray["###SUBMIT###"] .= '<input class="pollsubmit" type="submit" value="' . $this->pi_getLL('submit_button') . '" ' . (($this->conf['submitbutton_params']) ? $this->conf['submitbutton_params'] . ' ' : '') . '/>';
				} else {
					$markerArray["###SUBMIT###"] .= $this->conf['custom_submit'];
				}

				// include captcha
				if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'captcha', 's_poll') != "" || $this->conf['captcha'] != "") {
					if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'captcha', 's_poll') == "captcha" || $this->conf['captcha'] == "captcha") {
						if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('captcha')) {
							$markerArray["###CAPTCHA_IMAGE###"] = '<img src="' . \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath('captcha') . 'captcha/captcha.php" alt="Captcha-Code" />';
							$markerArray["###CAPTCHA_INPUT###"] = '<input type="text" size="8" name="' . $this->prefixId . '[captcha]" value=""/>';
						}
					} else {
						$template["poll_vote"] = $this->cObj->substituteSubpart($template["poll_vote"], '###CAPTCHA_INSERT###', '');
					}
					// sr_freecap
					if (($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'captcha', 's_poll') == "sr_freecap" || $this->conf['captcha'] == "sr_freecap") && is_object($this->freeCap)) {
						$markerArray = array_merge($markerArray, $this->freeCap->makeCaptcha());
					} else {
						$template["poll_vote"] = $this->cObj->substituteSubpart($template["poll_vote"], '###SR_FREECAP_INSERT###', '');
					}
				} else {
					$template["poll_vote"] = $this->cObj->substituteSubpart($template["poll_vote"], '###SR_FREECAP_INSERT###', '');
					$template["poll_vote"] = $this->cObj->substituteSubpart($template["poll_vote"], '###CAPTCHA_INSERT###', '');
				}

				$template["poll_vote"] = $this->cObj->substituteSubpart($template["poll_vote"], '###ANSWER_VOTE###', $resultcontentAnswer);
				$pollVoteSubpart = $this->cObj->substituteSubpart($template["poll_vote"], '###POLL_VOTE_ERRORS###', '');
				$content .= $this->cObj->substituteMarkerArrayCached($pollVoteSubpart, $markerArray, array(), array());
				$content = '<form method="post" action="' . htmlspecialchars($alink) . '" id="jk_pollform_' . $this->pollID . '">' . $content;
				$content .= '</form>';
			} else {

				// if poll is not explicitly requested, redirect to results
				if ($this->piVars['go'] !== 'poll') {

					$getParams = array(
						$this->prefixId . '[uid]' => $this->pollID,
						$this->prefixId . '[go]' => 'result',
					);

					if ($this->getConfigValue('comments_on_result', 's_result')) {
						$getParams[$this->prefixId . '[uid_comments]'] = $this->pollID;
					}

					header('Location:' . GeneralUtility::locationHeaderUrl($this->pi_getPageLink($GLOBALS['TSFE']->id, '', $getParams)));
				}

				$errors = array();
				if (isset($_COOKIE[$cookieName])) {
					$errors[] = 'cookie_voted';
				}
				if ($ip_voted) {
					$errors[] = 'ip_voted';
				}
				if ($check_user) {
					if (!$user_logged_in) {
						$errors[] = 'user_not_logged_in';
					} elseif ($user_voted) {
						$errors[] = 'user_voted';
					}
				}
				if (!$this->voteable) {
					$errors[] = 'poll_finished';
				}
				if (!$this->valid) {
					$errors[] = 'poll_expired';
				}

				$errorsSubpart = $this->cObj->getSubpart($template['poll_vote'], '###POLL_VOTE_ERRORS###');
				$errorMessageContainer = $this->cObj->getSubpart($errorsSubpart, '###POLL_VOTE_ERROR_MESSAGE_CONTAINER###');

				$errorMessages = '';
				foreach ($errors as $errorMessage) {
					$errorMessage = $this->pi_getLL('poll_error_' . $errorMessage);
					$errorMessages .= $this->cObj->substituteMarker($errorMessageContainer, '###POLL_VOTE_ERROR_MESSAGE###', $errorMessage);
				}

				$errorsSubpart = $this->cObj->substituteMarker($errorsSubpart, '###POLL_VOTE_ERROR_HEADER###', $this->pi_getLL('poll_vote_error_header'));
				$errorsSubpart = $this->cObj->substituteMarker($errorsSubpart, '###UNVOTEABLE_MESSAGE_CONTAINER_CLASS###', $this->getConfigValue('unvoteable_message_container_class'));
				$errorsSubpart = $this->cObj->substituteSubpart($errorsSubpart, '###POLL_VOTE_ERROR_MESSAGE_CONTAINER###', $errorMessages);

				$markerArray['###SUBMIT###'] = '';
				$pollVoteSubpart = $this->cObj->substituteSubpart($template["poll_vote"], '###POLL_VOTE_FORM###', '');
				$pollVoteSubpart = $this->cObj->substituteSubpart($pollVoteSubpart, '###POLL_VOTE_ERRORS###', $errorsSubpart);
				$pollVoteSubpart = $this->cObj->substituteMarkerArrayCached($pollVoteSubpart, $markerArray);
				$content .= $pollVoteSubpart;
			}

			return $content;
		} else {
			return '<div class="' . $this->getConfigValue('error_container_class') . '">' . $this->pi_getLL('poll_not_visible') . '</div>';
		}
	}

	/**
	 * Shows the result of the poll
	 *
	 * @return string HTML to display in the frontend
	 */
	function showresults() {
		$res = $this->typo3Db->exec_SELECTquery(
			'*',
			'tx_jkpoll_poll',
				'uid=' . $this->pollID . ' AND sys_language_uid=' . $GLOBALS['TSFE']->sys_language_content . $this->pollEnableFields
		);

		// Get poll data
		if ($res && $row = $this->typo3Db->sql_fetch_assoc($res)) {

			// Get the votes, answers and colors
			$votes = explode("\n", $row['votes']);
			// if poll is translation get votes from parent poll
			if ($this->pollID_parent != 0 && (!$this->conf['vote_language_specific'] && !$this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'vote_language_specific', 'sDEF'))) {
				$res_votes = $this->typo3Db->exec_SELECTquery(
					'*',
					'tx_jkpoll_poll',
						'uid=' . $this->pollID_parent . $this->pollEnableFields
				);
				if ($res_votes && $row_votes = $this->typo3Db->sql_fetch_assoc($res_votes)) {
					$votes = explode("\n", $row_votes['votes']);
				}
			}

			$answers = explode("\n", $row['answers']);
			$colors = explode("\n", $row['colors']);
			$answers_description = explode("\n", $row['answers_description']);
			$answers_image = explode(",", $row['answers_image']);

			$total = 0;
			foreach ($answers as $i => $a) {
				$total += $votes[$i];
			}

			// Limit the amount of answers shown to the top x
			$limit = 0;
			if ($this->conf['result_limit']) {
				$limit = $this->conf['result_limit'];
			} elseif ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'result_limit', 's_result')) {
				$limit = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'result_limit', 's_result');
			}
			if ($limit) {
				$answers_count = count($answers);
				$colors = array_pad($colors, $answers_count, "");
				$answers_description = array_pad($answers_description, $answers_count, "");
				$answers_image = array_pad($answers_image, $answers_count, "");
				array_multisort($votes, SORT_DESC, $answers, $colors, $answers_description, $answers_image);
				$rest = array_slice($votes, $limit);
				$votes = array_slice($votes, 0, $limit);
				$answers = array_slice($answers, 0, $limit);
				$colors = array_slice($colors, 0, $limit);
				if (!$this->conf['result_limit_hide_other']) {
					$other = 0;
					foreach ($rest as $i => $a) {
						$other += $rest[$i];
					}
					$votes[] = $other;
					$answers[] = $this->pi_getLL('limit_other');
					//$colors[] = "blue";
				}
			}

			// Get type of poll
			if ($this->conf['type']) {
				$type = $this->conf['type'];
			} else {
				$type = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'type', 's_result');
			}
			// Get height_width
			$height_width = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'height_width', 's_result');
			if ($height_width == "" && $type == 0) {
				$height_width = 10;
			} elseif ($height_width == "" && $type == 1) {
				$height_width = 50;
			}
			// Get factor
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'factor', 's_result')) {
				$factor = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'factor', 's_result');
			} elseif ($this->conf['factor']) {
				$factor = $this->conf['factor'];
			} else {
				$factor = 1;
			}

			$template = array();
			$template['poll_header'] = $this->cObj->getSubpart($this->templateCode, "###POLL_HEADER###");
			if ($type == 0) {
				$template['answers'] = $this->cObj->getSubpart($this->templateCode, "###POLL_ANSWER_HORIZONTAL###");
			} elseif ($type == 1) {
				$template['answers'] = $this->cObj->getSubpart($this->templateCode, "###POLL_ANSWER_VERTICAL###");
			} else {
				$template['answers'] = $this->cObj->getSubpart($this->templateCode, "###POLL_ANSWER_GOOGLE###");
			}
			$template['answer_data'] = $this->cObj->getSubpart($template['answers'], "###ANSWER_RESULT###");

			$markerArrayQuestion = array();
			$markerArrayQuestion["###TITLE###"] = $this->renderTitle($row['title']);
			$markerArrayQuestion["###QUESTION_IMAGE###"] = $this->getimage($this->pollID, '', '');
			$markerArrayQuestion["###QUESTIONTEXT###"] = $this->cObj->stdWrap($row['question'], $this->conf['rtefield_stdWrap.']);
			$content = $this->cObj->substituteMarkerArrayCached($template['poll_header'], $markerArrayQuestion);

			$markerArray["###VOTES_LABEL###"] = $this->pi_getLL('votes_label');
			$markerArray["###VOTES###"] = $total;
			$markerArray["###VOTES_COUNT###"] = $row['votes_count'];

			$template['answers'] = $this->cObj->substituteMarkerArrayCached($template['answers'], $markerArray);

			// Get highest result
			$percents = array();
			foreach ($votes as $i => $a) {
				if ($total > 0) {
					$percent = round(($votes[$i] / $total) * 100, 1);
				} else {
					$percent = 0;
				}
				$percents[++$i] = $percent;
			}
			$max = max($percents);

			$google_percents = array();
			$google_answers = array();
			$google_colors = array();
			$resultcontentAnswer = '';

			foreach ($answers as $i => $a) {
				if (trim($colors[$i]) == "") {
					if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'color', 's_result') != '') {
						$colors[$i] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'color', 's_result');
					} elseif ($this->conf['color'] != '') {
						$colors[$i] = $this->conf['color'];
					} else {
						$colors[$i] = "blue";
					}
				}
				if ($total > 0) {
					$percent = round(($votes[$i] / $total) * 100, 1);
				} else {
					$percent = 0;
				}

				// Make result bars
				$markerArrayAnswer = array();
				// get path for images
				if ($this->conf['path_to_images']) {
					$pathToImages = $this->conf['path_to_images'];
				} elseif ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'path_to_images', 's_result')) {
					$pathToImages = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'path_to_images', 's_result');
				} else {
					$pathToImages = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath($this->extKey) . 'images/';
				}
				$bar = ($percent == 0 && ($this->conf['show_zero_percent'] || $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'show_zero_percent', 's_result'))) ? 1 : round($percent * $factor);
				if ($type == 0) {
					// horizontal
					if ($this->conf['show_css_bars'] || $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'show_css_bars', 's_result')) {
						$markerArrayAnswer["###IMG_PERCENTAGE_RESULT###"] = '<div style="float:left; background-image:url(\'' . $pathToImages . trim($colors[$i]) . '.' . $this->conf['image_type'] . '\'); width:' . $bar . 'px; height:' . $height_width . 'px;" title="' . $percent . '%"></div>';
					} else {
						$markerArrayAnswer["###IMG_PERCENTAGE_RESULT###"] = '<img src="' . $pathToImages . trim($colors[$i]) . '.' . $this->conf['image_type'] . '" width="' . $bar . '" height="' . $height_width . '" alt="' . $percent . '%" />';
					}
				} elseif ($type == 1) {
					// vertical
					if ($this->conf['show_css_bars'] || $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'show_css_bars', 's_result')) {
						$markerArrayAnswer["###IMG_PERCENTAGE_RESULT###"] = '<div style="height:' . ($max * $factor) . 'px; width:' . $height_width . 'px;"><div style="position:relative; top: ' . ($max * $factor - $percent * $factor) . 'px; bottom:0px; background-image:url(\'' . $pathToImages . trim($colors[$i]) . '.' . $this->conf['image_type'] . '\'); width:' . $height_width . 'px; height:' . $bar . 'px;"></div></div>';
					} else {
						$markerArrayAnswer["###IMG_PERCENTAGE_RESULT###"] = '<img src="' . \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath($this->extKey) . 'pi1/clear.gif" width="' . $height_width . '" height="' . (round($max) * $factor - $bar) . '" alt="" /><br /><img src="' . $pathToImages . trim($colors[$i]) . '.' . $this->conf['image_type'] . '" width="' . $height_width . '" height="' . $bar . '" alt="' . $percent . '%" />';
					}
				} elseif ($type == 2) {
					// Google Chart
					$google_percents[] = $percent;
					$google_answers[] = trim($answers[$i]);
					$google_colors[] = trim($colors[$i]);
				}
				$markerArrayAnswer["###PERCENTAGE_RESULT###"] = $percent . "%";
				$markerArrayAnswer["###ANSWERTEXT_RESULT###"] = trim($answers[$i]);
				$markerArrayAnswer["###ANSWERTEXT_IMAGE###"] = $answers_description[$i];
				$markerArrayAnswer["###ANSWERTEXT_DESCRIPTION###"] = $this->getAnswerImage($answers_image[$i]);
				$voteAmountLabel = $this->pi_getLL('amount_votes_label');
				switch ($votes[$i]) {
					case 0:
						// If a special label for no votes exists use that, otherwise use the amount of votes
						$noVoteLabel = $this->pi_getLL('amount_novote_label');
						$markerArrayAnswer["###AMOUNT_VOTES###"] = $noVoteLabel ? '' : $votes[$i] . ' ';
						$markerArrayAnswer["###AMOUNT_VOTES_LABEL###"] = $noVoteLabel ? $noVoteLabel : $voteAmountLabel;
						break;
					case 1:
						// If a special label for one vote exists use that, otherwise use the amount of votes
						$oneVoteLabel = $this->pi_getLL('amount_onevote_label');
						$markerArrayAnswer["###AMOUNT_VOTES###"] = $oneVoteLabel ? '' : $votes[$i] . ' ';
						$markerArrayAnswer["###AMOUNT_VOTES_LABEL###"] = $oneVoteLabel ? $oneVoteLabel : $voteAmountLabel;
						break;
					default:
						$markerArrayAnswer["###AMOUNT_VOTES###"] = $votes[$i] . ' ';
						$markerArrayAnswer["###AMOUNT_VOTES_LABEL###"] = $voteAmountLabel;
						break;
				}
				$resultcontentAnswer .= $this->cObj->substituteMarkerArrayCached($template['answer_data'], $markerArrayAnswer);
			}
			if ($type == 2) {
				if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'google_width', 's_result') != '') {
					$google_width = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'google_width', 's_result');
				} elseif ($this->conf['google_width'] != '') {
					$google_width = $this->conf['google_width'];
				} else {
					$google_width = "500";
				}
				if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'google_height', 's_result') != '') {
					$google_height = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'google_height', 's_result');
				} elseif ($this->conf['google_height'] != '') {
					$google_height = $this->conf['google_height'];
				} else {
					$google_height = "100";
				}
				$markerArray["###GOOGLE_CHART###"] = "http://chart.apis.google.com/chart?cht=p3&chd=t:" . implode(",", $google_percents) . "&chs=" . $google_width . "x" . $google_height . "&chl=" . implode("|", $google_answers) . "&chco=" . implode("|", $google_colors) . "";
				$template['answers'] = $this->cObj->substituteMarkerArrayCached($template['answers'], $markerArray);
			}
			$subpartArray["###ANSWER_RESULT###"] = $resultcontentAnswer;
			$subpartArray["###EXPLANATION###"] = $this->cObj->stdWrap($row['explanation'], $this->conf['rtefield_stdWrap.']);

			// include link to RESULT view
			if (($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'link_to_poll', 's_result') || $this->conf['link_to_poll']) && $this->voteable) {
				// build url for linklist
				$ll_getParams = array($this->prefixId . '[go]' => 'poll', $this->prefixId . '[uid]' => $this->pollID);
				$ll_alink = $this->pi_getPageLink($GLOBALS['TSFE']->id, '', $ll_getParams);
				$subpartArray["###LINK_TO_POLL###"] = '<a class="jk_poll_link_to_poll" href="' . $ll_alink . '">' . $this->pi_getLL('link_to_poll') . '</a>';
			} else {
				$subpartArray["###LINK_TO_POLL###"] = '';
			}

			// include link to list
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'list', 's_poll') || $this->conf['list']) {
				// build url for linklist
				$ll_getParams = array($this->prefixId . '[go]' => 'list');
				$ll_alink = $this->pi_getPageLink($GLOBALS['TSFE']->id, '', $ll_getParams);
				$subpartArray["###LINKLIST###"] = '<a class="jk_poll_linklist" href="' . $ll_alink . '">' . $this->pi_getLL('linklist') . '</a>';
			} else {
				$subpartArray["###LINKLIST###"] = '';
			}

			$content .= $this->cObj->substituteMarkerArrayCached($template["answers"], array(), $subpartArray, array());
			return $content;
		} else {
			return '<div class="' . $this->getConfigValue('error_container_class') . '">' . $this->pi_getLL('poll_not_visible') . '</div>';
		}
	}

	/**
	 * Saves the votes in the database. Checks cookies to prevent misuse
	 *
	 * @return string HTML to show in frontend
	 */
	function savevote() {
		if ((!$this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'check_language_specific', 'sDEF') && !$this->conf['check_language_specific']) && $this->pollID_parent != 0) {
			$check_poll_id = $this->pollID_parent;
		} else {
			$check_poll_id = $this->pollID;
		}

		// poll is allowed if cookie not set already
		$cookieName = 't3_tx_jkpoll_' . $check_poll_id;
		// Exit if cookie exists
		if (isset($_COOKIE[$cookieName])) {
			return '<div class="' . $this->getConfigValue('error_container_class') . '">' . $this->pi_getLL('has_voted') . '</div>';
		}

		// Exit if captcha was not right
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'captcha', 's_poll') != "" || $this->conf['captcha'] != "") {
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'captcha', 's_poll') == "captcha" || $this->conf['captcha'] == "captcha") {
				if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('captcha')) {
					session_start();
					$captchaStr = $_SESSION['tx_captcha_string'];
					$_SESSION['tx_captcha_string'] = '';
				} else {
					$captchaStr = -1;
				}
				if (!($captchaStr === -1 || ($captchaStr && $this->captcha === $captchaStr))) {
					return '<div class="' . $this->getConfigValue('error_container_class') . '">' . $this->pi_getLL('wrong_captcha') . '</div>';
				}
			} elseif (($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'captcha', 's_poll') == "sr_freecap" || $this->conf['captcha'] == "sr_freecap") && is_object($this->freeCap) && !$this->freeCap->checkWord($this->sr_captcha)) {
				return '<div class="' . $this->getConfigValue('error_container_class') . '">' . $this->pi_getLL('wrong_captcha') . '</div>';
			}
		}

		// Exit if fe_user already voted
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'fe_user', 's_poll')) {
			if ($GLOBALS['TSFE']->fe_user->user['uid'] != '') {
				$res = $this->typo3Db->exec_SELECTquery(
					'*',
					'tx_jkpoll_userlog',
						'pid=' . $check_poll_id . ' AND fe_user=\'' . $GLOBALS['TSFE']->fe_user->user['uid'] . '\''
				);
				$rows = array();
				if ($res) {
					while ($row = $this->typo3Db->sql_fetch_assoc($res)) {
						$rows[] = $row;
					}
				}
				if (count($rows)) {
					return '<div class="' . $this->getConfigValue('error_container_class') . '">' . $this->pi_getLL('has_voted') . '</div>';
				}
			} else {
				return '<div class="' . $this->getConfigValue('error_container_class') . '">' . $this->pi_getLL('no_login') . '</div>';
			}
		}

		// Exit if IP already logged
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'check_ip', 's_poll') || $this->conf['check_ip']) {
			// get timestamp after which vote is possible again
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'time', 's_poll') != "") {
				$vote_time = $GLOBALS['SIM_EXEC_TIME'] - ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'time', 's_poll') * 3600);
			} elseif ($this->conf['check_ip_time'] != "") {
				$vote_time = $GLOBALS['SIM_EXEC_TIME'] - ($this->conf['check_ip_time'] * 3600);
			} else {
				$vote_time = $GLOBALS['SIM_EXEC_TIME'];
			}

			$res = $this->typo3Db->exec_SELECTquery(
				'*',
				'tx_jkpoll_iplog',
					'pid=' . $check_poll_id . ' AND ip=' . $this->typo3Db->fullQuoteStr($this->REMOTE_ADDR, 'tx_jkpoll_iplog') . ' AND tstamp >= ' . $vote_time
			);
			$rows = array();
			if ($res) {
				while ($row = $this->typo3Db->sql_fetch_assoc($res)) {
					$rows[] = $row;
				}
			}
			if (count($rows)) {
				return '<div class="' . $this->getConfigValue('error_container_class') . '">' . $this->pi_getLL('has_voted') . '</div>';
			}
		}

		// check if an answer was selected
		if (!intval($this->answer[0]) && $this->answer[0] != '0') {
			return '<div class="' . $this->getConfigValue('error_container_class') . '">' . $this->pi_getLL('error_no_vote_selected') . '</div>';
		}

		// decide if cookie-path is to be set or not
		$cookiepath = NULL;
		if ($this->conf['cookie_domainpath'] == 1) {
			$cookiepath = '/';
		}

		// decide which type of cookie is to be set
		$cookieConfig = $this->getConfigValue('cookie', 's_poll');
		if (!intval($cookieConfig)) {
			// make non-persistent cookie if "off"
			if ($cookieConfig == 'session') {
				if (!setcookie($cookieName, 'voted:yes', 0, $cookiepath)) {
					return '<div class="' . $this->getConfigValue('error_container_class') . '">' . $this->pi_getLL('error_no_vote') . '</div>';
				}
			}
			// if no value set use 30 days
			elseif ($cookieConfig == 'on') {
				if (!setcookie($cookieName, 'voted:yes', $GLOBALS['SIM_EXEC_TIME'] + (3600 * 24 * 30), $cookiepath)) {
					return '<div class="' . $this->getConfigValue('error_container_class') . '">' . $this->pi_getLL('error_no_vote') . '</div>';
				}
			}
		} else {
			$cookieTime = $GLOBALS['SIM_EXEC_TIME'] + (3600 * 24 * intval($cookieConfig));
			if (!setcookie($cookieName, 'voted:yes', $cookieTime, $cookiepath)) {
				return '<div class="' . $this->getConfigValue('error_container_class') . '">' . $this->pi_getLL('error_no_vote') . '</div>';
			}
		}

		// Get the poll data so it can be updated
		if ($this->pollID_parent != 0 && (!$this->conf['vote_language_specific'] && !$this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'vote_language_specific', 'sDEF'))) {
			$res = $this->typo3Db->exec_SELECTquery(
				'*',
				'tx_jkpoll_poll',
					'uid=' . $this->pollID_parent . $this->pollEnableFields
			);
		} else {
			$res = $this->typo3Db->exec_SELECTquery(
				'*',
				'tx_jkpoll_poll',
					'uid=' . $this->pollID . $this->pollEnableFields
			);
		}
		if ($res) {
			$row = $this->typo3Db->sql_fetch_assoc($res);
		}

		// update number of votes
		$votes = isset($row['votes']) ? explode("\n", $row['votes']) : array();
		$newvotes = array();
		foreach ($votes as $i => $a) {
			// find the answer that was voted for
			foreach ($this->answer as $value) {
				if ($i == $value) {
					// update no. of votes
					$a = intval($votes[$i]) + 1;
				}
			}
			$newvotes[] = $a;
		}
		$votes_count = isset($row['votes_count']) ? $row['votes_count'] + 1 : 1;

		// write answers back to db
		$dataArr['votes'] = implode("\n", $newvotes);
		$dataArr['votes_count'] = $votes_count;
		if ($this->pollID_parent != 0 && (!$this->conf['vote_language_specific'] && !$this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'vote_language_specific', 'sDEF'))) {
			$this->typo3Db->exec_UPDATEquery(
				'tx_jkpoll_poll',
					'uid=' . $this->pollID_parent,
				$dataArr
			);
		} else {
			$this->typo3Db->exec_UPDATEquery(
				'tx_jkpoll_poll',
					'uid=' . $this->pollID,
				$dataArr
			);
		}

		// write IP of voter in db
		if ($this->getConfigValue('check_ip', 's_poll')) {
			$insertFields = array(
				'pid' => $check_poll_id,
				'ip' => $this->REMOTE_ADDR,
				'tstamp' => $GLOBALS['SIM_EXEC_TIME']
			);
			$this->typo3Db->exec_INSERTquery(
				'tx_jkpoll_iplog',
				$insertFields
			);
		}

		// write FE User in db
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'fe_user', 's_poll') || $this->conf['check_user']) {
			$insertFields = array(
				'pid' => $check_poll_id,
				'fe_user' => $GLOBALS['TSFE']->fe_user->user['uid'],
				'tstamp' => $GLOBALS['SIM_EXEC_TIME']
			);
			$this->typo3Db->exec_INSERTquery(
				'tx_jkpoll_userlog',
				$insertFields
			);
		}

		// Show the poll results or forward to page specified
		$getParams = array(
			$this->prefixId . '[uid]' => $this->pollID,
		);
		// add get paramters to make it work with extension "comments"
		if ($this->getConfigValue('comments_on_result', 's_result')) {
			$getParams[$this->prefixId . '[uid_comments]'] = $this->pollID;
		}
		$pidForward = intval($this->getConfigValue('PIDforward', 's_poll'));
		if ($pidForward) {
			header('Location:' . GeneralUtility::locationHeaderUrl($this->pi_getPageLink($pidForward, '', $getParams)));
			die();
		}

		header('Location:' . GeneralUtility::locationHeaderUrl($this->pi_getPageLink($GLOBALS['TSFE']->id, '', $getParams)));
		die();
	}

	/**
	 * Gets the newest active poll on the page / startingpoint page or the one specified via GET
	 *
	 * @return boolean pollID was found and set or not
	 */
	function getPollID() {

		// The id of the page with the poll to use. Take from template, or the starting point page or
		// by default use current page
		if ($this->conf['pid']) {
			$this->pid = $this->conf['pid'];
		} else {
			$this->pid = intval($this->cObj->data['pages'] ? $this->cObj->data['pages'] : $GLOBALS['TSFE']->id);
		}

		// Get the poll id from parameter or select newest active poll (only newest poll is voteable)
		$this->voteable = TRUE;
		if ($this->piVars['uid'] != "") {
			$this->pollID = intval($this->piVars['uid']);
			if (
				!$this->getConfigValue('vote_old', 's_list')
				&& $this->pollID !== $this->getLastPoll()
			) {
				$this->voteable = FALSE;
			}
		} else {
			// Get the last poll from storage page
			$this->pollID = $this->getLastPoll();
			// return false if no poll found
			if (!$this->pollID) {
				return FALSE;
			}

			// send tp page with poll uid as get paramter (needed to work with extension "comments")
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'comments', 's_poll') || $this->conf['comments'] || $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'comments_on_result', 's_result') || $this->conf['comments_on_result']) {
				header('Location:' . GeneralUtility::locationHeaderUrl($this->pi_getPageLink($GLOBALS['TSFE']->id, '', array('L' => $GLOBALS['TSFE']->sys_language_content, $this->prefixId . '[uid]' => $this->pollID))));
			}
		}
		$this->pollID_parent = $this->getPollIDParent($this->pollID);
		// check if poll is available for language selected
		$res_poll = $this->typo3Db->exec_SELECTquery(
			'*',
			'tx_jkpoll_poll',
				'uid=' . $this->pollID . ' AND sys_language_uid=' . $GLOBALS['TSFE']->sys_language_content . $this->pollEnableFields
		);
		if ($res_poll && $row_poll = $this->typo3Db->sql_fetch_assoc($res_poll)) {
			$poll_available = TRUE;
		} else {
			$poll_available = FALSE;
		}
		// not default language and poll with given id isn't available in current language
		if ($GLOBALS['TSFE']->sys_language_content != '0' && !$poll_available) {
			$res_language = $this->typo3Db->exec_SELECTquery(
				'*',
				'tx_jkpoll_poll',
					'l18n_parent=' . $this->pollID . ' AND sys_language_uid=' . $GLOBALS['TSFE']->sys_language_content . $this->pollEnableFields
			);
			// set pollid to id of language
			if ($res_language && $row_language = $this->typo3Db->sql_fetch_assoc($res_language)) {
				$this->pollID = $row_language['uid'];
				if ($this->pollID == $this->getLastPoll()) {
					$this->voteable = TRUE;
				}
			}
		} elseif (!$poll_available) {
			$res_language = $this->typo3Db->exec_SELECTquery(
				'*',
				'tx_jkpoll_poll',
					'uid=' . $this->pollID . $this->pollEnableFields
			);
			if ($res_language && $row_language = $this->typo3Db->sql_fetch_assoc($res_language)) {
				$this->pollID = $row_language['l18n_parent'];
			}
		}

		return TRUE;
	}


	/**
	 * Gets the parent uid of the poll if translated
	 *
	 * @param integer $uid : uid of poll which should be checked for parent uid
	 * @return integer parent uid of poll (0 if none found)
	 */
	function getPollIDParent($uid) {
		$res = $this->typo3Db->exec_SELECTquery(
			'*',
			'tx_jkpoll_poll',
				'sys_language_uid=' . $GLOBALS['TSFE']->sys_language_content . ' AND pid=' . $this->pid . ' AND uid=' . $uid . $this->pollEnableFields,
			'',
			'crdate DESC'
		);
		if ($res && $row = $this->typo3Db->sql_fetch_assoc($res)) {
			if ($row['l18n_parent'] != 0) {
				return $row['l18n_parent'];
			} else {
				return 0;
			}
		} else { // check if poll is translation of another poll
			$res = $this->typo3Db->exec_SELECTquery(
				'*',
				'tx_jkpoll_poll',
					'sys_language_uid=' . $GLOBALS['TSFE']->sys_language_content . ' AND pid=' . $this->pid . ' AND l18n_parent=' . $uid . $this->pollEnableFields,
				'',
				'crdate DESC'
			);
			if ($res && $row = $this->typo3Db->sql_fetch_assoc($res)) {
				$this->pollID = $row['l18n_parent'];
				return $uid;
			} else {
				return 0;
			}
		}
	}


	/**
	 * Gets the newest active poll on the page / startingpoint page and returns its ID
	 *
	 * @return string uid of the last active poll on the page / startingpoint
	 */
	function getLastPoll() {

		// Get the last poll from storage page

		// Find any poll records on the chosen page.
		// Polls that are not hidden or deleted and that are active according to start and end date
		$res = $this->typo3Db->exec_SELECTquery(
			'uid,l18n_parent',
			'tx_jkpoll_poll',
				'pid=' . $this->pid . ' AND sys_language_uid=' . $GLOBALS['TSFE']->sys_language_content . $this->pollEnableFields,
			'',
			'crdate DESC'
		);

		// return false if no poll found
		if ($this->typo3Db->sql_num_rows($res) == 0) {
			return FALSE;
		} else {
			$row = $this->typo3Db->sql_fetch_assoc($res);
			if ($row['l18n_parent'] != 0) {
				$this->pollID_parent = $row['l18n_parent'];
			}
			return intval($row['uid']);
		}
	}


	/**
	 * Shows a list of all polls
	 *
	 * @return string HTML list of all polls
	 */
	function showlist() {

		$current_poll = $this->pollID;

		if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('pagebrowse') && ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'pagebrowser_items', 's_list') != "" || intval($this->conf['list_pagebrowser']))) {
			$pagebrowser = 1;
		} else {
			$pagebrowser = 0;
		}

		// The id of the page with the poll to use. Take from the starting point page or
		// by default use current page
		if ($this->conf['pid']) {
			$this->pid = $this->conf['pid'];
		} else {
			$this->pid = intval($this->cObj->data['pages'] ? $this->cObj->data['pages'] : $GLOBALS['TSFE']->id);
		}

		// Get the page where the poll is located
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'PIDitemDisplay', 's_list') != "") {
			$id = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'PIDitemDisplay', 's_list');
		} else {
			$id = $GLOBALS["TSFE"]->id;
		}

		// Get the amount of polls that should be displayed
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'amount', 's_list') != "") {
			$limit = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'amount', 's_list');
		} elseif (intval($this->conf['list_limit'])) {
			$limit = intval($this->conf['list_limit']);
		} else {
			$limit = '';
		}

		// Find any poll records on the chosen page.
		// Polls that are not hidden or deleted and that are active according to start and end date
		$res = $this->typo3Db->exec_SELECTquery(
			'uid, title, l18n_parent',
			'tx_jkpoll_poll',
				'pid=' . $this->pid . ' AND sys_language_uid=' . $GLOBALS['TSFE']->sys_language_content . $this->pollEnableFields,
			'',
			'crdate DESC',
			$limit
		);

		$template['poll_list'] = $this->cObj->getSubpart($this->templateCode, "###POLL_LIST###");
		$template['link'] = $this->cObj->getSubpart($template['poll_list'], "###POLL_LINK###");
		$template['result'] = $this->cObj->getSubpart($template['link'], "###POLL_RESULT###");
		$content_tmp = '';

		$items = array();
		if ($res) {
			// show first poll in list?
			if (!$this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'show_first', 's_list') && !$this->conf['list_first']) {
				$this->typo3Db->sql_fetch_assoc($res);
			}
			while ($row = $this->typo3Db->sql_fetch_assoc($res)) {
				$markerArray = array();
				$getParams = array(
					$this->prefixId . "[uid]" => $row['uid'],
					$this->prefixId . "[no_cache]" => '1',
				);
				// add parameter for comments if voteing for old polls is not possible
				if (!$this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'vote_old', 's_list') && ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'comments_on_result', 's_result') || $this->conf['comments_on_result'])) {
					$getParams[$this->prefixId . '[uid_comments]'] = $row['uid'];
				}
				$markerArray["###LINK###"] = $this->pi_linkToPage($row['title'], $id, "", $getParams);
				$markerArray["###QUESTION_IMAGE###"] = $this->getimage($row['uid'], $this->conf['list_image_width'], $this->conf['list_image_height']);
				if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'show_results_in_list', 's_list') || $this->conf['show_results_in_list']) {
					$this->pollID = $row['uid'];
					$this->pollID_parent = $row['l18n_parent'];
					$markerArray["###RESULT###"] = $this->showresults();
					$subpartArray["###POLL_RESULT###"] = $this->cObj->substituteMarkerArrayCached($template['result'], $markerArray, array(), array());
				} else {
					$markerArray["###RESULT###"] = "";
				}
				$template['link'] = $this->cObj->substituteMarkerArrayCached($template['link'], array(), array(), array());
				if (!$this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'hide_current', 's_list') && !$this->conf['hide_current']) {
					if ($pagebrowser) {
						$items[] = $this->cObj->substituteMarkerArrayCached($template['link'], $markerArray, array(), array());
					} else {
						$content_tmp .= $this->cObj->substituteMarkerArrayCached($template['link'], $markerArray, array(), array());
					}

				} elseif ($current_poll != $row['uid']) {
					if ($pagebrowser) {
						$items[] = $this->cObj->substituteMarkerArrayCached($template['link'], $markerArray, array(), array());
					} else {
						$content_tmp .= $this->cObj->substituteMarkerArrayCached($template['link'], $markerArray, array(), array());
					}
				}
			}
		}

		$pagebrowserContent = '';
		if ($pagebrowser) {
			// Number of list items per page
			$itemsPerPage = ($this->conf['list_pagebrowser_items']) ? $this->conf['list_pagebrowser_items'] : $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'pagebrowser_items', 's_list');
			// split array into chunks
			$items = array_chunk($items, $itemsPerPage);
			// How much pages do we need
			$numberOfPages = count($items);
			$subpartArray = array();
			foreach ($items[intval($this->piVars['page'])] as $i) {
				$subpartArray["###POLL_LINK###"] .= $i;
			}
			$pagebrowserContent = $this->getListGetPageBrowser($numberOfPages);
		} else {
			$subpartArray = array();
			$subpartArray["###POLL_LINK###"] = $content_tmp;
		}

		// include link back to previews view
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'backlink', 's_list') || $this->conf['backlink']) {
			$subpartArray["###LINKVIEW###"] = '<a class="jk_poll_linklist" href="' . $_SERVER['HTTP_REFERER'] . '">' . $this->pi_getLL('linkview') . '</a>';
		} else {
			$subpartArray["###LINKVIEW###"] = '';
		}

		$content = $this->cObj->substituteMarkerArrayCached($template['poll_list'], array(), $subpartArray, array());
		$content .= $pagebrowserContent;

		return $content;
	}

	/**
	 * Returns the HTML for the image
	 *
	 * @param integer $uid : uid of poll
	 * @param integer $width : width of the picture
	 * @param integer $height : height of the picture
	 * @return string HTML for the image
	 */
	function getimage($uid, $width, $height) {

		// Get poll data
		$res = $this->typo3Db->exec_SELECTquery(
			'*',
			'tx_jkpoll_poll',
				'uid=' . $uid
		);
		if ($res) {
			$row = $this->typo3Db->sql_fetch_assoc($res);
		} else {
			return '';
		}

		if ($this->pollID_parent != 0) {
			$res_parent = $this->typo3Db->exec_SELECTquery(
				'*',
				'tx_jkpoll_poll',
					'uid=' . $this->pollID_parent
			);
			if ($res_parent) {
				$row_parent = $this->typo3Db->sql_fetch_assoc($res_parent);
			}

			if (empty($row_parent['image'])) {
				return '';
			}

			$imgTSConfig["file"] = "uploads/tx_jkpoll/" . $row_parent["image"];
			$width = ($width) ? $width : $row_parent["width"];
			$height = ($height) ? $height : $row_parent["height"];
			$clickenlarge = $row_parent["clickenlarge"];
		} else {

			if (empty($row[''])) {
				return '';
			}

			$imgTSConfig["file"] = "uploads/tx_jkpoll/" . $row["image"];
			if (!$width && !$height) {
				$width = $row["width"];
				$height = $row["height"];
			}
			//			$width = ($width && $height='') ? $width : $row["width"];
			// 			$height = ($height && $width='') ? $height : $row["height"];
			$clickenlarge = $row["clickenlarge"];
		}
		$imgTSConfig['altText'] = $row["alternative_tag"];
		$imgTSConfig['titleText'] = $row["title_tag"];
		$link = $row["link"];

		if ($width) {
			$imgTSConfig["file."]['width'] = $width;
		}
		if ($height) {
			$imgTSConfig["file."]['height'] = $height;
		}
		if ($clickenlarge) {
			$imgTSConfig['imageLinkWrap'] = 1;
			$imgTSConfig['imageLinkWrap.']['JSwindow'] = 1;
			$imgTSConfig['imageLinkWrap.']['bodyTag'] = '<body style="background-color: black;">';
			$imgTSConfig['imageLinkWrap.']['JSwindow.']['newWindow'] = 0;
			$imgTSConfig['imageLinkWrap.']['JSwindow.']['expand'] = '17,20';
			$imgTSConfig['imageLinkWrap.']['enable'] = 1;
			$imgTSConfig['imageLinkWrap.']['wrap'] = '<a href="javascript:close();"> | </a>';
			$imgTSConfig['imageLinkWrap.']['width'] = 800;
			$imgTSConfig['imageLinkWrap.']['height'] = 600;
		}
		if ($link && !$clickenlarge) {
			$imgTSConfig['imageLinkWrap'] = 1;
			$imgTSConfig['imageLinkWrap.']['enable'] = 1;
			$imgTSConfig['imageLinkWrap.']['typolink.']['parameter'] = $link;
		}
		return $this->cObj->IMAGE($imgTSConfig);
	}


	/**
	 * Returns the HTML for the image
	 *
	 * @param integer $image : name of the image
	 * @return integer HTML for the image
	 */
	function getAnswerImage($image) {

		$imgTSConfig["file"] = "uploads/tx_jkpoll/" . $image;
		$width = $this->conf['answers_image_width'];
		$height = $this->conf['answers_image_height'];

		if ($width) {
			$imgTSConfig["file."]['width'] = $width;
		}
		if ($height) {
			$imgTSConfig["file."]['height'] = $height;
		}

		return $this->cObj->IMAGE($imgTSConfig);
	}


	/**
	 * Returns if poll is still valid (no end date set)
	 *
	 * @param integer $uid : name of the poll to check
	 * @return boolean poll valid or not
	 */
	function checkPollValid($uid) {

		$res = $this->typo3Db->exec_SELECTquery(
			'*',
			'tx_jkpoll_poll',
				'uid=' . $uid . $this->pollEnableFields
		);
		if ($res && $row = $this->typo3Db->sql_fetch_assoc($res)) {
			if ($row['valid_till'] != 0) {
				if ($GLOBALS['SIM_EXEC_TIME'] > $row['valid_till']) {
					$valid = FALSE;
				} else {
					$valid = TRUE;
				}
			} else {
				$valid = TRUE;
			}
		} else {
			$valid = FALSE;
		}

		return $valid;
	}


	protected function getListGetPageBrowser($numberOfPages) {
		// Get default configuration
		$conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_pagebrowse_pi1.'];
		// Modify this configuration
		$conf += array(
			'pageParameterName' => $this->prefixId . '|page',
			'numberOfPages' => $numberOfPages,
		);
		// Get page browser
		$cObj = GeneralUtility::makeInstance('tslib_cObj');
		/* @var $cObj tslib_cObj */
		$cObj->start(array(), '');
		return $cObj->cObjGetSingle('USER', $conf);
	}

	/**
	 * Reads the configuration value for the requested configuration
	 * key from the TypoScript configuration and overwrites it with
	 * a flexform value if there is one.
	 *
	 * @param string $configKey
	 * @param string $flexFormSheet
	 * @param string $flexFormKey The flex form key, if not set the config key will be used
	 * @return null|string Config value or NULL if no value was found
	 */
	protected function getConfigValue($configKey, $flexFormSheet = NULL, $flexFormKey = NULL) {

		$configValue = NULL;

		if (!empty($this->conf[$configKey])) {
			$configValue = $this->conf[$configKey];
		}

		if (isset($flexFormSheet)) {

			if (!isset($flexFormKey)) {
				$flexFormKey = $configKey;
			}


			$flexFormValue = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], $flexFormKey, $flexFormSheet);
			if (!empty($flexFormValue)) {
				$configValue = $flexFormValue;
			}
		}

		return $configValue;
	}

	/**
	 * The title will be stored in the data array unsing tx_jkpoll_poll_title
	 * as key. The TypoScript configuration at rendering.title will be used
	 * ro tender the title.
	 *
	 * @param string $title
	 * @return string
	 */
	protected function renderTitle($title) {
		$this->cObj->data['tx_jkpoll_poll_title'] = $title;
		$title = $this->cObj->cObjGetSingle($this->conf['rendering.']['title'], $this->conf['rendering.']['title.']);
		return $title;
	}
}
