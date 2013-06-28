<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2004 Johannes Krausmueller (johannes@krausmueller.de)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Plugin 'Poll' for the 'jk_poll' extension.
 *
 * @author	Johannes Krausmueller <johannes@krausmueller.de>
 */


if (t3lib_div::int_from_ver(TYPO3_version) < 6000000) {
	require_once(PATH_tslib . 'class.tslib_pibase.php');
}

class tx_jkpoll_pi1 extends tslib_pibase {
	public $prefixId      = 'tx_jkpoll_pi1';        // Same as class name
	public $scriptRelPath = 'pi1/class.tx_jkpoll_pi1.php';    // Path to this script relative to the extension dir.
	public $extKey        = 'jk_poll';    // The extension key.
	public $pi_checkCHash = FALSE;

	function main($content,$conf)	{
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_initPIflexForm();
		
		$this->pollEnableFields = $this->cObj->enableFields('tx_jkpoll_poll');
		
		// this will convert any string which is supplied as $_SERVER['REMOTE_ADDR'] into a valid ip address
		$this->REMOTE_ADDR = long2ip(ip2long($_SERVER['REMOTE_ADDR']));
		
		//initialize sr_freecap
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'captcha','s_poll') == "sr_freecap" || $this->conf['captcha'] == "sr_freecap") {
			if (t3lib_extMgm::isLoaded('sr_freecap') ) {
				require_once(t3lib_extMgm::extPath('sr_freecap').'pi2/class.tx_srfreecap_pi2.php');
				$this->freeCap = t3lib_div::makeInstance('tx_srfreecap_pi2');
			}
		}
		
		//Get translated text labels from locallang
/* !!!!! mit suchen und ersetzen an der entsprechenden Stelle ermitteln !!!! */
		$this->LL_no_poll_found = $this->pi_getLL('no_poll_found');
		$this->LL_poll_not_visible = $this->pi_getLL('poll_not_visible');
		$this->LL_votes_total = $this->pi_getLL('votes_total');
//		$this->LL_votes_label = $this->pi_getLL('votes_label');
		$this->LL_novote_label = $this->pi_getLL('novote_label');
		$this->LL_onevote_label = $this->pi_getLL('onevote_label');
		$this->LL_votes_label = $this->pi_getLL('votes_label');
		$this->LL_amount_novote_label = $this->pi_getLL('amount_novote_label');
		$this->LL_amount_onevote_label = $this->pi_getLL('amount_onevote_label');
		$this->LL_amount_votes_label = $this->pi_getLL('amount_votes_label');
		$this->LL_submit_button = $this->pi_getLL('submit_button');
		$this->LL_submit_js_linktext = ($this->pi_getLL('submit_js_linktext')) ? $this->pi_getLL('submit_js_linktext') : $this->pi_getLL('submit_button');
		$this->LL_linklist = $this->pi_getLL('linklist');
		$this->LL_link_to_poll = $this->pi_getLL('link_to_poll');
		$this->LL_link_to_result = $this->pi_getLL('link_to_result');
		$this->LL_linkview = $this->pi_getLL('linkview');
		$this->LL_has_voted = $this->pi_getLL('has_voted');
		$this->LL_no_login = $this->pi_getLL('no_login');
		$this->LL_error_no_vote = $this->pi_getLL('error_no_vote');
		$this->LL_wrong_captcha = $this->pi_getLL('wrong_captcha');
		$this->LL_error_no_vote_selected = $this->pi_getLL('error_no_vote_selected');
		$this->LL_limit_other = $this->pi_getLL('limit_other');

		//Get ID of poll ($this->PollID) or error msg. if no poll was found
		if (!$this->getPollID()) {
			$content = '<div class="error">'. $this->LL_no_poll_found. '</div>';
			return $this->pi_wrapInBaseClass($content);
		}
		
		//Define CSS file (get from config or use default)
		if (($this->conf['css_file'] != "none") && ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'css_file','sDEF') != "none")) {	
			if ($this->conf['css_file'] != "") {
				$cssFile = $this->conf['css_file'];
			} elseif ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'css_file','sDEF') != "") {
				$cssFile = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'css_file','sDEF');
			} else {
				$cssFile = t3lib_extMgm::siteRelPath($this->extKey).'res/jk_poll.css';
			}
			$GLOBALS['TSFE']->additionalHeaderData[$this->extKey] = '<link rel="stylesheet" href="'.$cssFile.'" type="text/css" />';
		}
		
		//Get template-file
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'templatefile','sDEF') != "" && !is_null($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'templatefile','sDEF'))) {
        	$this->templateCode = $this->cObj->fileResource($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'templatefile','sDEF'));
		} elseif ($this->conf['template']) {
		    $this->templateCode = $this->cObj->fileResource($this->conf['template']);
		} else {
        	$this->templateCode = $this->cObj->fileResource(t3lib_extMgm::siteRelPath($this->extKey).'res/jk_poll.tmpl');
		}
        
    	//Poll should be displayed	
		if (strchr($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'what_to_display','sDEF'),"POLL") || $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'what_to_display','sDEF')=='' )	{
			//The Get/Post variables
			$postVars = t3lib_div::_GP($this->prefixId);
			$getVars = t3lib_div::_GET($this->prefixId); 
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
				default:
					$content = $this->showpoll();
					break;
			}
		}
		//List should be displayed	
		if (strchr($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'what_to_display','sDEF'),"LIST") || !strcmp($this->go,'list') ) {
			$content = $this->showlist();
		}
		//Result should be displayed	
		if (strchr($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'what_to_display','sDEF'),"RESULT") || !strcmp($this->go,'result') ) {
			$content = $this->showresults();
		}
		return $this->pi_wrapInBaseClass($content);
	}
	
	/**
     * Shows the poll questions and lets the user votes for one answer or shows results if user already voted
     *
     * @return   string      HTML to display in frontend
     */
	function showpoll() {
		
		//Get poll data
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_jkpoll_poll',
			'uid=' .$this->pollID. ' AND sys_language_uid='.$GLOBALS['TSFE']->sys_language_content . $this->pollEnableFields
		);
		if ($res && $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

			//Put answers and votes in array
			$answers = explode("\n", $row['answers']);
			$votes = explode("\n", $row['votes']);
			$answers_description = explode("\n", $row['answers_description']);
			$answers_image = explode(",", $row['answers_image']);

			//Put in a 0 if there are no votes yet:
	        $needsupdate = false;
	        foreach ($answers as $i => $a) {	        	
	            if (!is_numeric(trim($votes[$i])) || $votes[$i] == '') {
	                $votes[$i] = '0';
	                $needsupdate = true;
	            }
	        }
	        // write votes back to DB
	        if ($needsupdate) {
	            $dataArr['votes'] = implode("\n",$votes);
	            $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
	            	'tx_jkpoll_poll',
	            	'uid='.$this->pollID,
	            	$dataArr
	            );
	        }
			
			$template = array();
	    	$template['poll_header'] = $this->cObj->getSubpart($this->templateCode,"###POLL_HEADER###"); 
	    	$template['poll_vote'] = $this->cObj->getSubpart($this->templateCode,"###POLL_VOTE###");  
	    	$template['answer'] = $this->cObj->getSubpart($this->templateCode,"###ANSWER_VOTE###");
	        
	    	// replace poll_header
	    	$markerArrayQuestion = array();
			$markerArrayQuestion["###TITLE###"] = $row['title'];
			$markerArrayQuestion["###QUESTION_IMAGE###"] = $this->getimage($this->pollID,'','');
			$markerArrayQuestion["###QUESTIONTEXT###"] = $this->cObj->stdWrap($row['question'],$this->conf['rtefield_stdWrap.']);
			
			//include link to list
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'list','s_poll') || $this->conf['list']) {
				//build url for linklist
				$getParams = array_merge (
					t3lib_div::_GET(),
					array(
						$this->prefixId.'[go]' => 'list',
						$this->prefixId.'[uid]' => $this->pollID
					)
				);
				$ll_alink = $this->pi_getPageLink($GLOBALS['TSFE']->id,'',$getParams);
				$subpartArray["###LINKLIST###"] = '<a class="jk_poll_linklist" href="'.$ll_alink.'">'.$this->LL_linklist.'</a>';
			} else {
				$subpartArray["###LINKLIST###"] = '';
			}
			
			$content .= $this->cObj->substituteMarkerArray($template["poll_header"],$markerArrayQuestion);
			
			if ((!$this->conf['check_language_specific'] && !$this->pi_getFFvalue($this->cObj->data['pi_flexform'],'check_language_specific','sDEF')) && $this->pollID_parent != 0) {
				$check_poll_id = $this->pollID_parent;
			} else {
				$check_poll_id = $this->pollID;		
			}
			
			// check if enddate is set
			$this->valid = $this->checkPollValid($check_poll_id);

			//Check for logged IPs
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'check_ip','s_poll') || $this->conf['check_ip']) {
				//get timestamp after which vote is possible again
				if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'time','s_poll') != "") { 
					$vote_time = $GLOBALS['SIM_EXEC_TIME'] - ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'time','s_poll') * 3600);
				} elseif ($this->conf['check_ip_time'] != "") {
					$vote_time = $GLOBALS['SIM_EXEC_TIME'] - $this->conf['check_ip_time'] * 3600;
				} else { 
					$vote_time = $GLOBALS['SIM_EXEC_TIME'];
				}
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'*',
					'tx_jkpoll_iplog',
					'pid='.$check_poll_id.' AND ip='.$GLOBALS['TYPO3_DB']->fullQuoteStr($this->REMOTE_ADDR, 'tx_jkpoll_iplog').' AND tstamp >= '.$vote_time
				);
				$rows = array();
				if ($res) {
					while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						$rows[] = $row;
					}
				}
				if (count($rows)) {
					$ip_voted = true;
				} else { 
					$ip_voted = false;
				}	
			} else { 
				$ip_voted = false;	
			}
			
			//Check for fe_users who already voted
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'fe_user','s_poll') || $this->conf['check_user']) {
				if ($GLOBALS['TSFE']->fe_user->user['uid'] != '') {
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						'*',
						'tx_jkpoll_userlog',
						'pid='.$check_poll_id.' AND fe_user=\''.$GLOBALS['TSFE']->fe_user->user['uid'].'\''
					);
					$rows = array();
					if ($res) {
						while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
							$rows[] = $row;
						}
					}
					if (count($rows)) {
						$user_voted = true;
					} else { 
						$user_voted = false;
					}
				} else { 
					$user_voted = true;
				}
			} else { 
				$user_voted = false;
			}

			//Check for cookie. If not found show poll, if found show results.
			$cookieName = 't3_tx_jkpoll_'.$check_poll_id;
			if (!isset($_COOKIE[$cookieName]) && !$ip_voted && !$user_voted && $this->voteable && $this->valid) {

				//Make radio buttons
				foreach ($answers as $i => $a) {
					$markerArrayAnswer = array();
					$choiceID = $this->prefixId .'_'. $this->pollID .'_'. $i;
					if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'checkbox','s_poll') || $this->conf['checkbox']) {
						if ($i == 0 && ($this->conf['first_answer_selected'] || $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'first_answer_selected','s_poll'))) {
//							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="'. $this->prefixId. '[answer]" type="radio" checked="checked" value="'. $i .'" />';
							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="'. $this->prefixId. '[answer][]" type="checkbox" checked="checked" value="'. $i .'" '.'id="'. $choiceID .'" />';
						} else {
//							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="'. $this->prefixId. '[answer]" type="radio" value="'. $i .'" />';
							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="'. $this->prefixId. '[answer][]" type="checkbox" value="'. $i .'" '.'id="'. $choiceID .'" />';
						}
					} else {
					if ($i == 0 && ($this->conf['first_answer_selected'] || $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'first_answer_selected','s_poll'))) {
//							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="'. $this->prefixId. '[answer]" type="radio" checked="checked" value="'. $i .'" />';
							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="'. $this->prefixId. '[answer][]" type="radio" checked="checked" value="'. $i .'" '.'id="'. $choiceID .'" />';
						} else {
//							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="'. $this->prefixId. '[answer]" type="radio" value="'. $i .'" />';
							$markerArrayAnswer["###ANSWERTEXT_FORM###"] = '<input class="pollanswer" name="'. $this->prefixId. '[answer][]" type="radio" value="'. $i .'" '.'id="'. $choiceID .'" />';
						}
					}
					$markerArrayAnswer["###ANSWERTEXT_CHOICE_ID###"] = $choiceID;
//					$markerArrayAnswer["###ANSWERTEXT_VALUE###"] = $answers[$i];
					$markerArrayAnswer["###ANSWERTEXT_VALUE###"] = trim($a);
					$markerArrayAnswer["###ANSWERTEXT_IMAGE###"] = $this->getAnswerImage($answers_image[$i]);
					$markerArrayAnswer["###ANSWERTEXT_DESCRIPTION###"] = $answers_description[$i];
					$resultcontentAnswer .= $this->cObj->substituteMarkerArrayCached($template['answer'],$markerArrayAnswer);
				}
				
				//build url for form
				//get the current GET params, so the language (and maybe more) is preserved within the submit link
//				$getParams = t3lib_div::_GET();
$getParams = array(
	'L' => $GLOBALS['TSFE']->sys_language_content,
);
//parameter id für seiten_id aus array entfernen
				// add get paramters to make it work with extension "comments" 
				if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'comments','s_poll') || $this->conf['comments']) {
					$getParams[$this->prefixId.'[uid]'] = $this->pollID;
				}
				if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'comments_on_result','s_result') || $this->conf['comments_on_result']) {
					$getParams[$this->prefixId.'[uid]'] = $this->pollID;
					$getParams[$this->prefixId.'[uid_comments]'] = $this->pollID;
				}

				$alink = $this->pi_getPageLink($GLOBALS['TSFE']->id,'',$getParams);
				
//				$markerArray["###SUBMIT###"] = '<input class="pollsubmit" type="submit" value="'.$this->LL_submit_button.'" />';
				// store [go] (for marking submitted forms) and a [pollID] (for multiple polls on the same page)
				$markerArray["###SUBMIT###"] = '
					<input type="hidden" name="'.$this->prefixId.'[pollID]" value="'.$this->pollID.'" />
					<input type="hidden" name="'.$this->prefixId.'[go]" value="savevote" />
					';
				if (!$this->conf['custom_submit']) {
					$markerArray["###SUBMIT###"] .= '<input class="pollsubmit" type="submit" value="'.$this->LL_submit_button.'" '.(($this->conf['submitbutton_params']) ? $this->conf['submitbutton_params'].' ' : '').'/>';
				} else {
					$markerArray["###SUBMIT###"] .= $this->conf['custom_submit'];
				}

				//include captcha
				if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'captcha','s_poll') != "" || $this->conf['captcha'] != "") {
					if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'captcha','s_poll') == "captcha" || $this->conf['captcha'] == "captcha") {
						if (t3lib_extMgm::isLoaded('captcha'))	{
							$markerArray["###CAPTCHA_IMAGE###"] = '<img src="'.t3lib_extMgm::siteRelPath('captcha').'captcha/captcha.php" alt="Captcha-Code" />';
							$markerArray["###CAPTCHA_INPUT###"] = '<input type="text" size="8" name="'. $this->prefixId. '[captcha]" value=""/>';
						}
					} else {
						$template["poll_vote"] = $this->cObj->substituteSubpart($template["poll_vote"],'###CAPTCHA_INSERT###','');
					}
					//sr_freecap
					if (($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'captcha','s_poll') == "sr_freecap" || $this->conf['captcha'] == "sr_freecap") && is_object($this->freeCap)) {
						$markerArray = array_merge($markerArray, $this->freeCap->makeCaptcha());
					} 
					else {
						$template["poll_vote"] = $this->cObj->substituteSubpart($template["poll_vote"],'###SR_FREECAP_INSERT###','');
					}
				} else {
					$template["poll_vote"] = $this->cObj->substituteSubpart($template["poll_vote"],'###SR_FREECAP_INSERT###','');
					$template["poll_vote"] = $this->cObj->substituteSubpart($template["poll_vote"],'###CAPTCHA_INSERT###','');
				}
				
				//include link to RESULT view
				if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'link_to_result','s_poll') || $this->conf['link_to_result']) {
					//build url for linklist
					$ll_getParams = array($this->prefixId.'[go]' => 'result', $this->prefixId.'[uid]' => $this->pollID);
					$ll_alink = $this->pi_getPageLink($GLOBALS['TSFE']->id,'',$ll_getParams);
					$markerArray["###LINK_TO_RESULT###"] = '<a class="jk_poll_link_to_result" href="'.$ll_alink.'">'.$this->LL_link_to_result.'</a>';
				} else {
					$markerArray["###LINK_TO_RESULT###"] = '';
				}
				
				//include link to list
				if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'list','s_poll') || $this->conf['list']) {
					//build url for linklist
					$ll_getParams = array($this->prefixId.'[go]' => 'list');
					$ll_alink = $this->pi_getPageLink($GLOBALS['TSFE']->id,'',$ll_getParams);
					$markerArray["###LINKLIST###"] = '<a class="jk_poll_linklist" href="'.$ll_alink.'">'.$this->LL_linklist.'</a>';
				} else {
					$markerArray["###LINKLIST###"] = '';
				}
				
				$template["poll_vote"] = $this->cObj->substituteSubpart($template["poll_vote"],'###ANSWER_VOTE###',$resultcontentAnswer);
				$content .= $this->cObj->substituteMarkerArrayCached($template["poll_vote"], $markerArray, array(), array());
	        	$content = '<form method="post" action="'. htmlspecialchars($alink). '" id="jk_pollform_'.$this->pollID.'">'.$content;
				$content .= '</form>';
				
			} else {
				$getVars = t3lib_div::_GET($this->prefixId); 
				// add get paramters to make it work with extension "comments"
				if (($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'comments_on_result','s_result') || $this->conf['comments_on_result']) && $getVars['uid_comments'] == "") {
					$getParams = array(
						'L' => $GLOBALS['TSFE']->sys_language_content,
						$this->prefixId.'[uid]' => $this->pollID,
						$this->prefixId.'[uid_comments]' => $this->pollID,
					);
					header('Location:'.t3lib_div::locationHeaderUrl($this->pi_getPageLink($GLOBALS['TSFE']->id,'',$getParams)));
				} else {				
					//Show result
					$content = $this->showresults();
				}
			}
			
	        return $content;
		} else {
			return '<div class="error">' .$this->LL_poll_not_visible. '</div>';
		}
	}

	/**
	 * Shows the result of the poll
	 * 
	 * @return	string		HTML to display in the frontend
	 */	
	function showresults() {
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_jkpoll_poll',
			'uid=' .$this->pollID.' AND sys_language_uid='.$GLOBALS['TSFE']->sys_language_content . $this->pollEnableFields
		);
				
		//Get poll data
		if ($res && $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

			//Get the votes, answers and colors		
			$votes = explode("\n", $row['votes']);
			//if poll is translation get votes from parent poll
			if ($this->pollID_parent != 0 && (!$this->conf['vote_language_specific'] && !$this->pi_getFFvalue($this->cObj->data['pi_flexform'],'vote_language_specific','sDEF'))) {							
				$res_votes = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'*',
					'tx_jkpoll_poll',
					'uid=' .$this->pollID_parent . $this->pollEnableFields
				);
				if ($res_votes && $row_votes = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res_votes)) {					
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
			
			//Limit the amount of answers shown to the top x
			if ($this->conf['result_limit']) {
			    $limit = $this->conf['result_limit'];
			} elseif ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'result_limit','s_result')) {
			    $limit = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'result_limit','s_result');
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
					$answers[] = $this->LL_limit_other;
					//$colors[] = "blue";
				}
			}
			
			//Get type of poll
			if ($this->conf['type']) {
			    $type = $this->conf['type'];
			} else {
			    $type = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'type','s_result');
			}
			//Get height_width
			$height_width = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'height_width','s_result');
			if ($height_width == "" && $type == 0) {
				$height_width = 10;
			} elseif ($height_width == "" && $type == 1) {
				$height_width = 50;
			}
			//Get factor
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'factor','s_result')) {
				$factor = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'factor','s_result');
			} elseif ($this->conf['factor']) {
				$factor = $this->conf['factor'];
			} else {
				$factor = 1;
			}
			
			$template = array();
	    	$template['poll_header'] = $this->cObj->getSubpart($this->templateCode,"###POLL_HEADER###"); 
	    	if ($type == 0) { 
	            $template['answers'] = $this->cObj->getSubpart($this->templateCode,"###POLL_ANSWER_HORIZONTAL###"); 
	    	} elseif ($type == 1) {
	            $template['answers'] = $this->cObj->getSubpart($this->templateCode,"###POLL_ANSWER_VERTICAL###");
	    	} else {
	            $template['answers'] = $this->cObj->getSubpart($this->templateCode,"###POLL_ANSWER_GOOGLE###");
	    	} 
	    	$template['answer_data'] = $this->cObj->getSubpart($template['answers'],"###ANSWER_RESULT###"); 
			
	    	$markerArrayQuestion = array();
			$markerArrayQuestion["###TITLE###"] = $row['title'];
			$markerArrayQuestion["###QUESTION_IMAGE###"] = $this->getimage($this->pollID,'','');
			$markerArrayQuestion["###QUESTIONTEXT###"] = $this->cObj->stdWrap($row['question'],$this->conf['rtefield_stdWrap.']);
			$content = $this->cObj->substituteMarkerArrayCached($template['poll_header'],$markerArrayQuestion,$subpartArray,$wrappedSubpartArray);
			
			$markerArray["###VOTES_LABEL###"] = $this->LL_votes_label;
			$markerArray["###VOTES###"] = $total;
			$markerArray["###VOTES_COUNT###"] = $row['votes_count'];

/*			switch ($total) {
				case 0:
					$markerArray["###VOTES###"] = ($this->LL_novote_label) ? '' : $total;
					$markerArray["###VOTES_LABEL###"] =  ($this->LL_novote_label) ? $this->LL_novote_label : $this->LL_votes_label;
					break;
				case 1:
					$markerArray["###VOTES###"] = ($this->LL_onevote_label) ? '' : $total;
					$markerArray["###VOTES_LABEL###"] =  ($this->LL_onevote_label) ? $this->LL_onevote_label : $this->LL_votes_label;
					break;
				default:
					$markerArray["###VOTES###"] = $total;
					$markerArray["###VOTES_LABEL###"] = $this->LL_votes_label;
					break;
			}
*/			$template['answers'] = $this->cObj->substituteMarkerArrayCached($template['answers'],$markerArray,$subpartArray,$wrappedSubpartArray);
			
			//Get highest result
			$i=0;
			foreach ($votes as $i => $a) {
				if ($total > 0) {
					$percent = round(($votes[$i] / $total)*100,1);
				} else {
					$percent = 0;
				}
				$percents[++$i]=$percent;
			}
			$max=max($percents);
				
			foreach ($answers as $i => $a) {				
				if (trim($colors[$i]) == "") {
					if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'color','s_result') != '') {
						$colors[$i] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'color','s_result');
					} elseif ($this->conf['color'] != '') {
						$colors[$i] = $this->conf['color'];
					} else {
						$colors[$i]="blue";
					}
				}
				if ($total > 0) {
					$percent = round(($votes[$i] / $total)*100,1);
				} else {
					$percent = 0;
				}			
				
				//Make result bars
				$markerArrayAnswer = array();
				//get path for images
				if ($this->conf['path_to_images']) {
					$pathToImages = $this->conf['path_to_images'];
				} elseif ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'path_to_images','s_result')) {
					$pathToImages = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'path_to_images','s_result');
				} else {
					$pathToImages = t3lib_extMgm::siteRelPath($this->extKey).'images/';
				}
				$bar = ($percent==0 && ($this->conf['show_zero_percent'] || $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'show_zero_percent','s_result'))) ? 1 : round($percent*$factor);
				if ($type == 0) { 
					//horizontal
					if ($this->conf['show_css_bars'] || $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'show_css_bars','s_result')) {
						$markerArrayAnswer["###IMG_PERCENTAGE_RESULT###"] = '<div style="float:left; background-image:url(\''.$pathToImages.trim($colors[$i]).'.'.$this->conf['image_type'].'\'); width:'.$bar.'px; height:'.$height_width.'px;" title="'.$percent.'%"></div>';
					} else {
						$markerArrayAnswer["###IMG_PERCENTAGE_RESULT###"] = '<img src="'.$pathToImages.trim($colors[$i]).'.'.$this->conf['image_type'].'" width="'.$bar.'" height="'.$height_width.'" alt="'.$percent.'%" />';
					}
				} elseif ($type == 1) { 
					// vertical
					if ($this->conf['show_css_bars'] || $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'show_css_bars','s_result')) {
						$markerArrayAnswer["###IMG_PERCENTAGE_RESULT###"] = '<div style="height:'.($max*$factor).'px; width:'.$height_width.'px;"><div style="position:relative; top: '.($max*$factor-$percent*$factor).'px; bottom:0px; background-image:url(\''.$pathToImages.trim($colors[$i]).'.'.$this->conf['image_type'].'\'); width:'.$height_width.'px; height:'.$bar.'px;"></div></div>';
					} else {
						$markerArrayAnswer["###IMG_PERCENTAGE_RESULT###"] = '<img src="'.t3lib_extMgm::siteRelPath($this->extKey).'pi1/clear.gif" width="'.$height_width.'" height="'.(round($max)*$factor-$bar).'" alt="" /><br /><img src="'.$pathToImages.trim($colors[$i]).'.'.$this->conf['image_type'].'" width="'.$height_width.'" height="'.$bar.'" alt="'.$percent.'%" />';
					}
				} elseif ($type == 2) {
					// Google Chart
 					$google_percents[] =  $percent;
 					$google_answers[] = trim($answers[$i]);
 					$google_colors[] = trim($colors[$i]);
 				}
				$markerArrayAnswer["###PERCENTAGE_RESULT###"] = $percent." %";
				$markerArrayAnswer["###ANSWERTEXT_RESULT###"] = trim($answers[$i]);
				$markerArrayAnswer["###ANSWERTEXT_IMAGE###"] = $answers_description[$i];
				$markerArrayAnswer["###ANSWERTEXT_DESCRIPTION###"] = $this->getAnswerImage($answers_image[$i]);
				switch ($votes[$i]) {
					case 0:
						$markerArrayAnswer["###AMOUNT_VOTES###"] = ($this->LL_amount_novote_label) ? '' : $votes[$i].' ';
						$markerArrayAnswer["###AMOUNT_VOTES_LABEL###"] = ($this->LL_amount_novote_label) ? $this->LL_amount_novote_label : $markerArrayAnswer["###AMOUNT_VOTES_LABEL###"];
						break;
					case 1:
						$markerArrayAnswer["###AMOUNT_VOTES###"] = ($this->LL_amount_onevote_label) ? '' : $votes[$i].' ';
						$markerArrayAnswer["###AMOUNT_VOTES_LABEL###"] = ($this->LL_amount_onevote_label) ? $this->LL_amount_onevote_label : $markerArrayAnswer["###AMOUNT_VOTES_LABEL###"];
						break;
					default:
						$markerArrayAnswer["###AMOUNT_VOTES###"] = $votes[$i].' ';
						$markerArrayAnswer["###AMOUNT_VOTES_LABEL###"] = $this->LL_amount_votes_label;
						break;
				}
 				$resultcontentAnswer .= $this->cObj->substituteMarkerArrayCached($template['answer_data'],$markerArrayAnswer,$subpartArray,$wrappedSubpartArray);
			}
			if ($type == 2) {
				if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'google_width','s_result') != '') {
						$google_width = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'google_width','s_result');
				} elseif ($this->conf['google_width'] != '') {
						$google_width = $this->conf['google_width'];
				} else {
						$google_width="500";
				}
				if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'google_height','s_result') != '') {
						$google_height = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'google_height','s_result');
				} elseif ($this->conf['google_height'] != '') {
						$google_height = $this->conf['google_height'];
				} else {
						$google_height="100";
				}
 				$markerArray["###GOOGLE_CHART###"] = "http://chart.apis.google.com/chart?cht=p3&chd=t:".implode(",",$google_percents)."&chs=".$google_width."x".$google_height."&chl=".implode("|",$google_answers)."&chco=".implode("|",$google_colors)."";
 				$template['answers'] = $this->cObj->substituteMarkerArrayCached($template['answers'],$markerArray,$subpartArray,$wrappedSubpartArray);				
 			}
			$subpartArray["###ANSWER_RESULT###"] = $resultcontentAnswer;
			$subpartArray["###EXPLANATION###"] = $this->cObj->stdWrap($row['explanation'],$this->conf['rtefield_stdWrap.']);
			
			//include link to RESULT view
			if (($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'link_to_poll','s_result') || $this->conf['link_to_poll']) && $this->voteable) {
				//build url for linklist
				$ll_getParams = array($this->prefixId.'[go]' => 'poll', $this->prefixId.'[uid]' => $this->pollID);
				$ll_alink = $this->pi_getPageLink($GLOBALS['TSFE']->id,'',$ll_getParams);
				$subpartArray["###LINK_TO_POLL###"] = '<a class="jk_poll_link_to_poll" href="'.$ll_alink.'">'.$this->LL_link_to_poll.'</a>';
			} else {
				$subpartArray["###LINK_TO_POLL###"] = '';
			}
			
			//include link to list
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'list','s_poll') || $this->conf['list']) {
				//build url for linklist
				$ll_getParams = array($this->prefixId.'[go]' => 'list');
				$ll_alink = $this->pi_getPageLink($GLOBALS['TSFE']->id,'',$ll_getParams);
				$subpartArray["###LINKLIST###"] = '<a class="jk_poll_linklist" href="'.$ll_alink.'">'.$this->LL_linklist.'</a>';
			} else {
				$subpartArray["###LINKLIST###"] = '';
			}
	    	
			$content .= $this->cObj->substituteMarkerArrayCached($template["answers"], array(), $subpartArray, array());
	    	return $content;
		} else {
	    	return '<div class="error">' .$this->LL_poll_not_visible. '</div>';
		}
	}

	/**
     * Saves the votes in the database. Checks cookies to prevent misuse
     *
     * @return   string      HTML to show in frontend
     */
	function savevote() {		
		if ((!$this->pi_getFFvalue($this->cObj->data['pi_flexform'],'check_language_specific','sDEF') && !$this->conf['check_language_specific']) && $this->pollID_parent != 0) {
			$check_poll_id = $this->pollID_parent;
		} else {
			$check_poll_id = $this->pollID;
		}
		
		// poll is allowed if cookie not set already
		$cookieName = 't3_tx_jkpoll_'.$check_poll_id;
		//Exit if cookie exists		
		if (isset($_COOKIE[$cookieName])) {
			return '<div class="error">'. $this->LL_has_voted. '</div>';
		}
			
		//Exit if captcha was not right
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'captcha','s_poll') != "" || $this->conf['captcha'] != "") {
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'captcha','s_poll') == "captcha" || $this->conf['captcha'] == "captcha") {
				if (t3lib_extMgm::isLoaded('captcha'))	{
					session_start();
					$captchaStr = $_SESSION['tx_captcha_string'];
					$_SESSION['tx_captcha_string'] = '';
				} else {
					$captchaStr = -1;
				}
				if (!($captchaStr===-1 || ($captchaStr && $this->captcha===$captchaStr))) {
					return '<div class="error">' .$this->LL_wrong_captcha. '</div>';
				}
			} elseif (($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'captcha','s_poll') == "sr_freecap" || $this->conf['captcha'] == "sr_freecap") && is_object($this->freeCap) && !$this->freeCap->checkWord($this->sr_captcha)) {
				return '<div class="error">' .$this->LL_wrong_captcha. '</div>';
			}
		}
		
		//Exit if fe_user already voted
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'fe_user','s_poll')) {
			if ($GLOBALS['TSFE']->fe_user->user['uid'] != '') {
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'*',
					'tx_jkpoll_userlog',
					'pid='.$check_poll_id.' AND fe_user=\''.$GLOBALS['TSFE']->fe_user->user['uid'].'\''
				);
				$rows = array();
				if ($res) {
					while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						$rows[] = $row;
					}
				}
				if (count($rows)) {
					return '<div class="error">' .$this->LL_has_voted. '</div>';
				}
			}
			else {
				return '<div class="error">' .$this->LL_no_login. '</div>';
			}
		}

		//Exit if IP already logged
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'check_ip','s_poll') || $this->conf['check_ip']) {
			//get timestamp after which vote is possible again
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'time','s_poll') != "") { 
				$vote_time = $GLOBALS['SIM_EXEC_TIME'] - ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'time','s_poll') * 3600);
			} elseif ($this->conf['check_ip_time'] != "") { 
				$vote_time = $GLOBALS['SIM_EXEC_TIME'] - ($this->conf['check_ip_time'] * 3600);
			} else { 
				$vote_time = $GLOBALS['SIM_EXEC_TIME'];
			}
			
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'*',
				'tx_jkpoll_iplog',
				'pid='.$check_poll_id.' AND ip='.$GLOBALS['TYPO3_DB']->fullQuoteStr($this->REMOTE_ADDR, 'tx_jkpoll_iplog').' AND tstamp >= '.$vote_time
			);
			$rows = array();
			if ($res) {
				while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					$rows[] = $row;
				}
			}
			if (count($rows)) {
				return '<div class="error">' .$this->LL_has_voted. '</div>';
			}
		}

		//check if an answer was selected
		if(!intval($this->answer[0]) && $this->answer[0]!='0') {
			return '<div class="error">'. $this->LL_error_no_vote_selected. '</div>';
		}

		//decide if cookie-path is to be set or not
		if ($this->conf['cookie_domainpath'] == 1) {
			$cookiepath = '/';
		}
		
		//decide which type of cookie is to be set		 
		if ((!intval($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'cookie','s_poll')) && $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'cookie','s_poll')) || (!intval($this->conf['cookie'])) && $this->conf['cookie']) {
			//make non-persistent cookie if "off"
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'cookie','s_poll') == "off" || $this->conf['cookie'] == "off") {
				if(!setcookie($cookieName,'voted:yes',0,$cookiepath)) {
					return '<div class="error">'. $this->LL_error_no_vote. '</div>';
				}
			}
			//don't use cookies if "no"
			elseif ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'cookie','s_poll') == "no" || $this->conf['cookie'] == "no") {
				//do nothing
			}
			//if no value set use 30 days
			elseif ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'cookie','s_poll')==='' || $this->conf['cookie']==='') {
				if(!setcookie($cookieName,'voted:yes',$GLOBALS['SIM_EXEC_TIME'] + (3600*24*30),$cookiepath)) {
					return '<div class="error">'. $this->LL_error_no_vote. '</div>';
				}
			}
		} else {
			//delete cookie after time set via flexform
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'cookie','s_poll') && intval($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'cookie','s_poll'))) {
				$cookieTime = $GLOBALS['SIM_EXEC_TIME'] + (3600*24*intval($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'cookie','s_poll')));    
			} else {
			    $cookieTime = $GLOBALS['SIM_EXEC_TIME'] + (3600*24*intval($this->conf['cookie']));
			}
			if(!setcookie($cookieName,'voted:yes',$cookieTime,$cookiepath)) {
				return '<div class="error">'. $this->LL_error_no_vote. '</div>';
			}
		}
		
		//Get the poll data so it can be updated
		if ($this->pollID_parent != 0 && (!$this->conf['vote_language_specific'] && !$this->pi_getFFvalue($this->cObj->data['pi_flexform'],'vote_language_specific','sDEF'))) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'*',
				'tx_jkpoll_poll',
				'uid=' .$this->pollID_parent . $this->pollEnableFields
			);
		} else {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'*',
				'tx_jkpoll_poll',
				'uid=' .$this->pollID . $this->pollEnableFields
			);
		}
		if ($res) {
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		}
		
		//update number of votes
		$votes = explode("\n", $row['votes']);
		foreach ($votes as $i => $a) {
			//find the answer that was voted for
			foreach ($this->answer as $value) {
				if ($i == $value) {
					//update no. of votes
					$a = trim($votes[$i])+1; 
				}				
			}
			$newvotes[] = $a;
		}
		$votes_count = $row['votes_count'] + 1;
		
		// write answers back to db
		$dataArr['votes']=implode("\n",$newvotes);
		$dataArr['votes_count']=$votes_count;
		if ($this->pollID_parent != 0 && (!$this->conf['vote_language_specific'] && !$this->pi_getFFvalue($this->cObj->data['pi_flexform'],'vote_language_specific','sDEF'))) {
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				'tx_jkpoll_poll',
				'uid='.$this->pollID_parent,
				$dataArr
			);
		} else {
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				'tx_jkpoll_poll',
				'uid='.$this->pollID,
				$dataArr
			);
		}

		//write IP of voter in db
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'check_ip','s_poll') || $this->conf['check_ip']) {
			$insertFields = array(
				'pid' => $check_poll_id,
				'ip' => $this->REMOTE_ADDR,
				'tstamp' => $GLOBALS['SIM_EXEC_TIME']
			);
			$GLOBALS['TYPO3_DB']->exec_INSERTquery(
				'tx_jkpoll_iplog',
				$insertFields
			);
		}
		
		//write FE User in db
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'fe_user','s_poll') || $this->conf['check_user']) {
			$insertFields = array(
				'pid' => $check_poll_id,
				'fe_user' => $GLOBALS['TSFE']->fe_user->user['uid'],
				'tstamp' => $GLOBALS['SIM_EXEC_TIME']
			);
			$GLOBALS['TYPO3_DB']->exec_INSERTquery(
				'tx_jkpoll_userlog',
				$insertFields
			);
		}
		
		//Show the poll results or forward to page specified
		$getParams = array(
			'L' => $GLOBALS['TSFE']->sys_language_content,
		);
		// add get paramters to make it work with extension "comments" 
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'comments','s_poll') || $this->conf['comments']) {
			$getParams[$this->prefixId.'[uid]'] = $this->pollID;
		}
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'comments_on_result','s_result') || $this->conf['comments_on_result']) {
			$getParams[$this->prefixId.'[uid]'] = $this->pollID;
			$getParams[$this->prefixId.'[uid_comments]'] = $this->pollID;
		}
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'PIDforward','s_poll')) {
			header('Location:'.t3lib_div::locationHeaderUrl($this->pi_getPageLink($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'PIDforward','s_poll'),'',$getParams)));
		} elseif ($this->conf['PIDforward']) {
			header('Location:'.t3lib_div::locationHeaderUrl($this->pi_getPageLink($this->conf['PIDforward'],'',$getParams)));	
		} else {
			// send to url if comments are enabled, otherwise just show the result
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'comments','s_poll') || $this->conf['comments'] || $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'comments_on_result','s_result') || $this->conf['comments_on_result']) {
				header('Location:'.t3lib_div::locationHeaderUrl($this->pi_getPageLink($GLOBALS['TSFE']->id,'',$getParams)));
			} else {
				$content = $this->showresults();
			}	
		}

		return $content;
		
	}
	
	/**
     * Gets the newest active poll on the page / startingpoint page or the one specified via GET
     *
     * @return   boolean      pollID was found and set or not
     */
	function getPollID () {
		
		//The id of the page with the poll to use. Take from template, or the starting point page or
		//by default use current page
		if ($this->conf['pid']) {
			$this->pid = $this->conf['pid'];
		} else {
			$this->pid = intval($this->cObj->data['pages'] ? $this->cObj->data['pages'] : $GLOBALS['TSFE']->id);
		}
		
		//Get the poll id from parameter or select newest active poll (only newest poll is voteable)	
		if ($this->piVars['uid'] != "") {			
			if (!$this->pi_getFFvalue($this->cObj->data['pi_flexform'],'vote_old','s_list')) {
				if ($this->piVars['uid'] == $this->getLastPoll()) { 
					$this->voteable = true;
				} else { 
					$this->voteable = false;
				}
				$this->pollID = intval($this->piVars['uid']);
				//check if poll is translated
				$this->pollID_parent = $this->getPollIDParent($this->pollID);
			} else {
				$this->pollID = intval($this->piVars['uid']);
				$this->voteable = true;
				$this->pollID_parent = $this->getPollIDParent($this->pollID);		 
			}			
		} else {
			//Get the last poll from storage page
			$this->pollID = $this->getLastPoll();
			$this->pollID_parent = $this->getPollIDParent($this->pollID);
			//return false if no poll found
			if (!$this->pollID) {
				return false;
			}
			
			$this->voteable = true;

			// send tp page with poll uid as get paramter (needed to work with extension "comments")
			if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'comments','s_poll') || $this->conf['comments'] || $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'comments_on_result','s_result') || $this->conf['comments_on_result']) {
				header('Location:'.t3lib_div::locationHeaderUrl($this->pi_getPageLink($GLOBALS['TSFE']->id,'',array('L'=>$GLOBALS['TSFE']->sys_language_content,$this->prefixId.'[uid]'=>$this->pollID))));
			}
		}
		//check if poll is available for language selected
		$res_poll = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_jkpoll_poll',
			'uid=' .$this->pollID.' AND sys_language_uid='.$GLOBALS['TSFE']->sys_language_content . $this->pollEnableFields
		);
		if ($res_poll && $row_poll = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res_poll)) {
			$poll_available = true;
		} else {
			$poll_available = false;
		}
		//not default language and poll with given id isn't available in current language
		if($GLOBALS['TSFE']->sys_language_content != '0' && !$poll_available) {
			$res_language = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'*',
				'tx_jkpoll_poll',
				'l18n_parent=' .$this->pollID.' AND sys_language_uid='.$GLOBALS['TSFE']->sys_language_content . $this->pollEnableFields
			);
			//set pollid to id of language
			if ($res_language && $row_language = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res_language)) {
				$this->pollID = $row_language['uid'];
				if ($this->pollID == $this->getLastPoll()) { 
					$this->voteable = true;
				}
			}
		} elseif (!$poll_available) {
			$res_language = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'*',
				'tx_jkpoll_poll',
				'uid=' .$this->pollID . $this->pollEnableFields
			);
			if ($res_language && $row_language = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res_language)) {
				$this->pollID = $row_language['l18n_parent'];
			}
		}

		return true;
	}
	
	
	/**
	 * Gets the parent uid of the poll if translated
	 * 
	 * @param	integer		$uid : uid of poll which should be checked for parent uid
	 * @return	integer		parent uid of poll (0 if none found)
	 */	
	function getPollIDParent($uid) {		
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_jkpoll_poll',
			'sys_language_uid='.$GLOBALS['TSFE']->sys_language_content . ' AND pid=' .$this->pid. ' AND uid='.$uid . $this->pollEnableFields,
			'',
			'crdate DESC'
		);
		if ($res && $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			if ($row['l18n_parent'] != 0) {
				return $row['l18n_parent'];
			} else {
				return 0;
			}
		}
		else { 		// check if poll is translation of another poll
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'*',
				'tx_jkpoll_poll',
				'sys_language_uid='.$GLOBALS['TSFE']->sys_language_content . ' AND pid=' .$this->pid. ' AND l18n_parent='.$uid . $this->pollEnableFields,
				'',
				'crdate DESC'
			);
			if ($res && $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$this->pollID = $row['l18n_parent'];
				return $uid;
			} else {
				return 0;
			}
		}
	}
	
	
	/**
     *  Gets the newest active poll on the page / startingpoint page and returns its ID
     *
     * @return   string      uid of the last active poll on the page / startingpoint
     */	
	function getLastPoll () {
		
		//Get the last poll from storage page

		//Find any poll records on the chosen page.
		//Polls that are not hidden or deleted and that are active according to start and end date
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'uid,l18n_parent',
			'tx_jkpoll_poll',
			'pid=' .$this->pid.' AND sys_language_uid='.$GLOBALS['TSFE']->sys_language_content . $this->pollEnableFields,
			'',
			'crdate DESC'
		);

		//return false if no poll found
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) == 0) {
			return false;
		} else {
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			if ($row['l18n_parent'] != 0) {
				$this->pollID_parent = $row['l18n_parent'];
			} 			
			return $row['uid'];
		}
	}
	
	
	/**
     *  Shows a list of all polls
     *
     * @return   string      HTML list of all polls
     */
	function showlist() {
		
		$current_poll = $this->pollID;
		
		if (t3lib_extMgm::isLoaded('pagebrowse') && ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'pagebrowser_items','s_list') != "" || intval($this->conf['list_pagebrowser'])))	{
			$pagebrowser = 1;
		} else {
			$pagebrowser = 0;
		}		

		//The id of the page with the poll to use. Take from the starting point page or
		//by default use current page
		if ($this->conf['pid']) {
		    $this->pid = $this->conf['pid'];
		} else {
		    $this->pid = intval($this->cObj->data['pages'] ? $this->cObj->data['pages'] : $GLOBALS['TSFE']->id);
		}
		
		//Get the page where the poll is located
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'PIDitemDisplay','s_list') != "") {
			$id = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'PIDitemDisplay','s_list');
		} else { 
			$id=$GLOBALS["TSFE"]->id;
		}
		
		//Get the amount of polls that should be displayed
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'amount','s_list') != "") {
			$limit = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'amount','s_list');
		} elseif (intval($this->conf['list_limit'])) {
			$limit = intval($this->conf['list_limit']);
		} else { 
			$limit='';
		}
		
		//Find any poll records on the chosen page. 
		//Polls that are not hidden or deleted and that are active according to start and end date
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'uid, title, l18n_parent',
			'tx_jkpoll_poll',
			'pid='.$this->pid.' AND sys_language_uid='.$GLOBALS['TSFE']->sys_language_content . $this->pollEnableFields,
			'',
			'crdate DESC',
			$limit
		);
		
		$template['poll_list'] = $this->cObj->getSubpart($this->templateCode,"###POLL_LIST###"); 
        $template['link'] = $this->cObj->getSubpart($template['poll_list'],"###POLL_LINK###");
        $template['result'] = $this->cObj->getSubpart($template['link'],"###POLL_RESULT###"); 
		
        if ($res) {
	        //show first poll in list?
	        if (!$this->pi_getFFvalue($this->cObj->data['pi_flexform'],'show_first','s_list') && !$this->conf['list_first'] ) {
	        	$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
	        }
	        while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$markerArray = array();
				$getParams =array(
					$this->prefixId."[uid]" => $row['uid'],
					$this->prefixId."[no_cache]" => '1',
				);
				// add parameter for comments if voteing for old polls is not possible
				if (!$this->pi_getFFvalue($this->cObj->data['pi_flexform'],'vote_old','s_list') && ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'comments_on_result','s_result') || $this->conf['comments_on_result'])) {
					$getParams[$this->prefixId.'[uid_comments]'] = $row['uid'];
				}
				$markerArray["###LINK###"] = $this->pi_linkToPage($row['title'], $id,"",$getParams);				
				$markerArray["###QUESTION_IMAGE###"] = $this->getimage($row['uid'],$this->conf['list_image_width'],$this->conf['list_image_height']);
 	        	if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'show_results_in_list','s_list') || $this->conf['show_results_in_list'] ) {
					$this->pollID = $row['uid'];
					$this->pollID_parent = $row['l18n_parent'];
					$markerArray["###RESULT###"] = $this->showresults();
					$subpartArray["###POLL_RESULT###"] = $this->cObj->substituteMarkerArrayCached($template['result'],$markerArray, array(), array());
 				} else {
 					$markerArray["###RESULT###"] = "";
 				}
 				$template['link'] = $this->cObj->substituteMarkerArrayCached($template['link'], array(), array(), array()); 				
 				if (!$this->pi_getFFvalue($this->cObj->data['pi_flexform'],'hide_current','s_list') && !$this->conf['hide_current'] ) { 				
					if ($pagebrowser) {
						$items[]=$this->cObj->substituteMarkerArrayCached($template['link'],$markerArray, array(), array());
					} else {
						$content_tmp .= $this->cObj->substituteMarkerArrayCached($template['link'],$markerArray, array(), array());
					}
					
 				} elseif ($current_poll != $row['uid'] ) {
 					if ($pagebrowser) {
						$items[]=$this->cObj->substituteMarkerArrayCached($template['link'],$markerArray, array(), array());
 					} else {
 						$content_tmp .= $this->cObj->substituteMarkerArrayCached($template['link'],$markerArray, array(), array());
 					}
 				} 
	        }
        }

		if ($pagebrowser) {
			// Number of list items per page
			$itemsPerPage = ($this->conf['list_pagebrowser_items']) ? $this->conf['list_pagebrowser_items'] : $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'pagebrowser_items','s_list');
			// split array into chunks
			$items = array_chunk($items, $itemsPerPage);
			// How much pages do we need
			$numberOfPages = count($items);
			$subpartArray = array();
			foreach ($items[intval($this->piVars['page'])] as $i) {
				$subpartArray["###POLL_LINK###"] .= $i; 
			}
		} else {	
			$subpartArray = array();
			$subpartArray["###POLL_LINK###"] = $content_tmp;
		}
		
		//include link back to previews view
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'backlink','s_list') || $this->conf['backlink']) {
			$subpartArray["###LINKVIEW###"] = '<a class="jk_poll_linklist" href="'.$_SERVER['HTTP_REFERER'].'">'.$this->LL_linkview.'</a>';
		} else {
			$subpartArray["###LINKVIEW###"] = '';
		}
		
		$content .= $this->cObj->substituteMarkerArrayCached($template['poll_list'], array(), $subpartArray, array());

		// Add page browser
		if ($pagebrowser) {
		$content .= $this->getListGetPageBrowser($numberOfPages);
		}
				
        return $content;
	}

	/**
	 * Returns the HTML for the image
	 * 
	 * @param	integer		$uid : uid of poll	 
	 * @param	integer		$width : width of the picture
	 * @param	integer		$height : height of the picture
	 * @return	integer		HTML for the image
	 */
	function getimage($uid, $width, $height) {

		//Get poll data
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_jkpoll_poll',
			'uid=' .$uid
		);
		if ($res) {
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		}
		
		if ($this->pollID_parent != 0) {
			$res_parent = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'*',
				'tx_jkpoll_poll',
				'uid=' .$this->pollID_parent
			);
			if ($res_parent) {
				$row_parent = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res_parent);
			}
			
			$imgTSConfig["file"] = "uploads/tx_jkpoll/".$row_parent["image"];
  			$width = ($width) ? $width : $row_parent["width"];
  			$height = ($height) ? $height : $row_parent["height"];
  			$clickenlarge = $row_parent["clickenlarge"];
		} else {
			$imgTSConfig["file"] = "uploads/tx_jkpoll/".$row["image"];
			if (!$width && !$height) {
				$width = $row["width"];
				$height = $row["height"];
			}
//			$width = ($width && $height='') ? $width : $row["width"];
// 			$height = ($height && $width='') ? $height : $row["height"];
  			$clickenlarge = $row["clickenlarge"];
		}
		$imgTSConfig['altText']   = $row["alternative_tag"];
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
  			$imgTSConfig['imageLinkWrap.']['bodyTag'] = '<body bgcolor="black">';
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
	 * @param	integer		$image : name of the image	 
	 * @return	integer		HTML for the image
	 */
	function getAnswerImage($image) {
		
		$imgTSConfig["file"] = "uploads/tx_jkpoll/".$image;
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
	 * @param	integer		$uid : name of the poll to check	 
	 * @return  boolean     poll valid or not
	 */
	function checkPollValid($uid) {
		
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_jkpoll_poll',
			'uid=' .$uid. $this->pollEnableFields
		);
		if ($res && $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			if ($row['valid_till'] != 0) {
				if ($GLOBALS['SIM_EXEC_TIME'] > $row['valid_till']) {
					$valid = false;
				} else { 
					$valid = true;
				}
			} else { 
				$valid = true;
			}
		} else {
			$valid = false;
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
		$cObj = t3lib_div::makeInstance('tslib_cObj');
		/* @var $cObj tslib_cObj */
		$cObj->start(array(), '');
		return $cObj->cObjGetSingle('USER', $conf);
	}
	
}

if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/jk_poll/pi1/class.tx_jkpoll_pi1.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/jk_poll/pi1/class.tx_jkpoll_pi1.php"]);
}

?>
