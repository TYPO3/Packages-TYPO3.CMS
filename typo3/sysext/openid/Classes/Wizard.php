<?php
namespace TYPO3\CMS\Openid;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Christian Weiske <cweiske@cweiske.de>
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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;

/**
 * OpenID selection wizard for the backend
 *
 * @author Christian Weiske <cweiske@cweiske.de>
 */
class Wizard extends OpenidService {
	protected $claimedId;
	protected $parentFormItemName;

	public function main() {
		$p = GeneralUtility::_GP['P'];
		if (isset($p['itemName'])) {
			$this->parentFormItemName = $p['itemName'];
		}

		if (\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('tx_openid_mode') === 'finish'
			&& $this->openIDResponse === NULL
		) {
			$this->includePHPOpenIDLibrary();
			$openIdConsumer = $this->getOpenIDConsumer();
			$this->openIDResponse = $openIdConsumer->complete($this->getReturnUrl());
			$this->handleResponse();
			$this->renderHtml();
			return;
		} elseif (GeneralUtility::_POST('openid_url') != '') {
			$this->openIDIdentifier = GeneralUtility::_POST('openid_url');
			$this->sendOpenIDRequest();
		}
		return $this->renderHtml();
	}

	/**
	 * Return URL to this wizard
	 *
	 * @return string Full URL with protocol and hostname
	 */
	protected function getSelfUrl() {
		return GeneralUtility::getIndpEnv('TYPO3_SITE_URL') . TYPO3_mainDir .
			'sysext/' . $this->extKey . '/wizard/index.php';
	}

	/**
	 * Return URL that shall be called by the OpenID server
	 *
	 * @return string Full URL with protocol and hostname
	 */
	protected function getReturnUrl() {
		return $this->getSelfURL() .
			'?tx_openid_mode=finish' .
			'&P[itemName]=' . $this->parentFormItemName;
	}

	/**
	 * Check OpenID response and set flash messages depending on its state
	 *
	 * @return void
	 *
	 * @uses $openIDResponse
	 */
	protected function handleResponse() {
		if (!$this->openIDResponse instanceof \Auth_OpenID_ConsumerResponse) {
			$flashMessage = GeneralUtility::makeInstance(
				'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
				'We got no OpenID response.',
				'Error during OpenID authentication',
				\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR
			);
		} elseif ($this->openIDResponse->status == Auth_OpenID_SUCCESS) {
				//all fine
			$openIdIdentifier = $this->getSignedParameter('openid_claimed_id');
			$flashMessage = GeneralUtility::makeInstance(
				'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
				'OpenID is: <tt>' . htmlspecialchars($openIdIdentifier) . '</tt>',
				'OpenID authentication successful',
				\TYPO3\CMS\Core\Messaging\FlashMessage::OK
			);
			$this->claimedId = $openIdIdentifier;
		} elseif ($this->openIDResponse->status == Auth_OpenID_CANCEL) {
			$flashMessage = GeneralUtility::makeInstance(
				'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
				'OpenID authentication has been cancelled',
				'Error during OpenID authentication',
				\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR
			);
		} else {
				//another failure. show error message and form again
			$flashMessage = GeneralUtility::makeInstance(
				'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
				'Status: ' . htmlspecialchars($this->openIDResponse->status) .
				'<br/>Message: ' . htmlspecialchars($this->openIDResponse->message),
				'Error during OpenID authentication',
				\TYPO3\CMS\Core\Messaging\FlashMessage::ERROR
			);
		}

		$this->addFlashMessage($flashMessage);
	}

	/**
	 * Add flash message to message queue
	 *
	 * @param \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage
	 * @return void
	 */
	protected function addFlashMessage(\TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage) {
		/** @var $flashMessageService \TYPO3\CMS\Core\Messaging\FlashMessageService */
		$flashMessageService = GeneralUtility::makeInstance(
			'TYPO3\\CMS\\Core\\Messaging\\FlashMessageService'
		);
		$defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
		$defaultFlashMessageQueue->enqueue($flashMessage);
	}

	/**
	 * Render HTML with messagse and OpenID form and output it
	 *
	 * @return void
	 */
	protected function renderHtml() {
			// start template object
		$this->doc = GeneralUtility::makeInstance('template');
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->docType = 'xhtml_trans';

			// use FLUID standalone view for wizard content
		$view = GeneralUtility::makeInstance('Tx_Fluid_View_StandaloneView');
		$view->setTemplatePathAndFilename(
			\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('openid') .
			'Resources/Private/Templates/Wizard/Content.html'
		);

		/** @var $flashMessageService \TYPO3\CMS\Core\Messaging\FlashMessageService */
		$flashMessageService = GeneralUtility::makeInstance(
			'TYPO3\\CMS\\Core\\Messaging\\FlashMessageService'
		);
		$defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
		$messages = array();
		foreach ($defaultFlashMessageQueue->getAllMessagesAndFlush() as $message) {
			$messages[] = $message->render();
		}
		$view->assign('messages', $messages);
		$view->assign('formAction', $this->getSelfURL());
		$view->assign('claimedId', $this->claimedId);
		$view->assign('parentFormItemName', $this->parentFormItemName);
		$view->assign('showForm', TRUE);
		if (isset($_REQUEST['openid_url'])) {
			$view->assign('openid_url', $_REQUEST['openid_url']);
		}

			// renders the view
		$wizardContent = $this->doc->header('OpenID registration') .
			$view->render();

			// finish template object
		$content = $this->doc->render(
			'OpenID registration', $wizardContent
		);

			// print content
		header('HTTP/1.0 200 OK');
		echo $content;
	}
}
?>