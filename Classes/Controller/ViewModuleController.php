<?php
namespace TYPO3\CMS\Viewpage\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2013 Felix Kopp <felix-source@phorax.com>
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
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Controller for viewing the frontend
 *
 * @author Felix Kopp <felix-source@phorax.com>
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class ViewModuleController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

	public function __construct() {
		parent::__construct();

		$GLOBALS['LANG']->includeLLFile('EXT:viewpage/Resources/Private/Language/locallang.xlf');
		$this->pageRenderer = $GLOBALS['TBE_TEMPLATE']->getPageRenderer();
		$this->pageRenderer->addInlineSettingArray('web_view', array(
			'States' => $GLOBALS['BE_USER']->uc['moduleData']['web_view']['States'],
		));
		$this->pageRenderer->addInlineLanguageLabelFile('EXT:viewpage/Resources/Private/Language/locallang.xlf');
	}

	/**
	 * Show selected page from pagetree in iframe
	 *
	 * @return void
	 */
	public function showAction() {
		$this->view->assignMultiple(
			array(
				'widths' => $this->getPreviewFrameWidths(),
				'url' => $this->getTargetUrl()
			)
		);
	}

	/**
	 * Determine the url to view
	 *
	 * @return string
	 */
	protected function getTargetUrl() {
		$pageIdToShow = intval(\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('id'));
		$adminCommand = $this->getAdminCommand($pageIdToShow);
		$domainName = $this->getDomainName($pageIdToShow);
		// Mount point overlay: Set new target page id and mp parameter
		/** @var \TYPO3\CMS\Frontend\Page\PageRepository $sysPage */
		$sysPage = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
		$sysPage->init(FALSE);
		$mountPointMpParameter = '';
		$finalPageIdToShow = $pageIdToShow;
		$mountPointInformation = $sysPage->getMountPointInfo($pageIdToShow);
		if ($mountPointInformation && $mountPointInformation['overlay']) {
			// New page id
			$finalPageIdToShow = $mountPointInformation['mount_pid'];
			$mountPointMpParameter = '&MP=' . $mountPointInformation['MPvar'];
		}
		// Modify relative path to protocol with host if domain record is given
		$protocolAndHost = '..';
		if ($domainName) {
			$protocol = 'http';
			$page = (array) $sysPage->getPage($finalPageIdToShow);
			if ($page['url_scheme'] == 2 || $page['url_scheme'] == 0 && \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_SSL')) {
				$protocol = 'https';
			}
			$protocolAndHost = $protocol . '://' . $domainName;
		}
		$url = $protocolAndHost . '/index.php?id=' . $finalPageIdToShow . $this->getTypeParameterIfSet($finalPageIdToShow) . $mountPointMpParameter . $adminCommand;
		return $url;
	}

	/**
	 * Get admin command
	 *
	 * @param integer $pageId
	 * @return string
	 */
	protected function getAdminCommand($pageId) {
		// The page will show only if there is a valid page and if this page may be viewed by the user
		$pageinfo = BackendUtility::readPageAccess($pageId, $GLOBALS['BE_USER']->getPagePermsClause(1));
		$addCommand = '';
		if (is_array($pageinfo)) {
			$addCommand = '&ADMCMD_view=1&ADMCMD_editIcons=1' . BackendUtility::ADMCMD_previewCmds($pageinfo);
		}
		return $addCommand;
	}

	/**
	 * With page TS config it is possible to force a specific type id via mod.web_view.type
	 * for a page id or a page tree.
	 * The method checks if a type is set for the given id and returns the additional GET string.
	 *
	 * @param integer $pageId
	 * @return string
	 */
	protected function getTypeParameterIfSet($pageId) {
		$typeParameter = '';
		$modTSconfig = BackendUtility::getModTSconfig($pageId, 'mod.web_view');
		$typeId = intval($modTSconfig['properties']['type']);
		if ($typeId > 0) {
			$typeParameter = '&type=' . $typeId;
		}
		return $typeParameter;
	}

	/**
	 * Get domain name for requested page id
	 *
	 * @param integer $pageId
	 * @return boolean|string Domain name if there is one, FALSE if not
	 */
	protected function getDomainName($pageId) {
		$domain = BackendUtility::firstDomainRecord(BackendUtility::BEgetRootLine($pageId));
		return $domain;
	}

	/**
	 * Get available widths for preview frame
	 *
	 * @return array
	 */
	protected function getPreviewFrameWidths() {
		$pageId = intval(\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('id'));
		$modTSconfig = BackendUtility::getModTSconfig($pageId, 'mod.web_view');
		$data = json_encode(array(
			'width' => '100%',
			'height' => "100%"
		));
		$widths = array(
			$data => $GLOBALS['LANG']->getLL('autoSize')
		);
		if (is_array($modTSconfig['properties']['previewFrameWidths.'])) {
			foreach ($modTSconfig['properties']['previewFrameWidths.'] as $item => $conf ){
				$label = '';

				$width = substr($item, 0, -1);
				$data = array('width' => $width);
				$label .= $width . 'px ';

				//if height is set
				if (isset($conf['height'])) {
					$label .= ' × ' . $conf['height'] . 'px ';
					$data['height'] = $conf['height'];
				}

				if (strcmp(substr($conf['label'], 0, 4), 'LLL:')) {
					$label .= $conf['label'];
				} else {
					$label .= $GLOBALS['LANG']->sL(trim($conf['label']));
				}
				$widths[json_encode($data)] = $label;
			}
		}
		return $widths;
	}
}
