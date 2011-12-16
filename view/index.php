<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2011 Kasper Skårhøj (kasperYYYY@typo3.com)
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
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Module: View
 *
 * Views the webpage
 *
 * @author	Kasper Skårhøj <kasperYYYY@typo3.com>
 */

unset($MCONF);
require ('conf.php');
require ($BACK_PATH.'init.php');
//require ($BACK_PATH.'template.php');
$BE_USER->modAccess($MCONF,1);



/**
 * Script Class for the Web > View
 *
 * @author	Kasper Skårhøj <kasperYYYY@typo3.com>
 * @package TYPO3
 * @subpackage core
 */
class SC_mod_web_view_index {
	var $MCONF=array();
	var $MOD_MENU=array();
	var $MOD_SETTINGS=array();

	var $perms_clause;
	var $modTSconfig;
	var $type;
	var $pageinfo;
	var $url;
	var $id;
	var $wsInstruction='';

	/**
	 * Initialization of module
	 *
	 * @return	void
	 */
	function init()	{
		$this->MCONF = $GLOBALS['MCONF'];
		$this->id = intval(t3lib_div::_GP('id'));

		$this->perms_clause = $GLOBALS['BE_USER']->getPagePermsClause(1);

			// page/be_user TSconfig settings and blinding of menu-items
		$this->modTSconfig = t3lib_BEfunc::getModTSconfig($this->id,'mod.'.$this->MCONF['name']);
		$this->type = intval($this->modTSconfig['properties']['type']);
	}

	/**
	 * Main function
	 *
	 * @return	void
	 */
	function main()	{

			// Access check...
			// The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;
		$addCmd='';
		if ($this->id && $access)	{
			$addCmd = '&ADMCMD_view=1&ADMCMD_editIcons=1'.t3lib_BEfunc::ADMCMD_previewCmds($this->pageinfo);
		}

		$parts = parse_url(t3lib_div::getIndpEnv('TYPO3_SITE_URL'));
		$dName = t3lib_BEfunc::getDomainStartPage($parts['host'],$parts['path']) ?
						t3lib_BEfunc::firstDomainRecord(t3lib_BEfunc::BEgetRootLine($this->id)):
						'';

			// preview of mount pages
		$sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
		/** @var t3lib_pageSelect $sys_page */
		$sys_page->init(FALSE);
		$mountPointInfo = $sys_page->getMountPointInfo($this->id);
		if ($mountPointInfo && $mountPointInfo['overlay']) {
			$this->id = $mountPointInfo['mount_pid'];
			$addCmd .= '&MP=' . $mountPointInfo['MPvar'];
		}

		$page = (array)$sys_page->getPage($this->id);

		$urlScheme = 'http';
		if ($page['url_scheme'] == 2 || $page['url_scheme'] == 0 && t3lib_div::getIndpEnv('TYPO3_SSL')) {
			$urlScheme = 'https';
		}

		$this->url .= ($dName
			? $urlScheme . '://' . $dName
			: $GLOBALS['BACK_PATH'] . '..') . '/index.php?id=' . $this->id .
				($this->type ? '&type='.$this->type : '') . $addCmd;
	}

	/**
	 * Redirect URL
	 *
	 * @return	void
	 */
	function printContent()	{
		t3lib_utility_Http::redirect($this->url);
	}
}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/viewpage/view/index.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/viewpage/view/index.php']);
}



// Make instance:
$SOBE = t3lib_div::makeInstance('SC_mod_web_view_index');
$SOBE->init();
$SOBE->main();
$SOBE->printContent();

?>