<?php
namespace TYPO3\CMS\Viewpage\Controller;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

/**
 * Controller for viewing the frontend
 */
class ViewModuleController extends ActionController
{
    /**
     * @var string
     */
    protected $defaultViewObjectName = BackendTemplateView::class;

    /**
     * @var BackendTemplateView
     */
    protected $view;

    /**
     * Set up the doc header properly here
     *
     * @param ViewInterface $view
     */
    protected function initializeView(ViewInterface $view)
    {
        /** @var BackendTemplateView $view */
        parent::initializeView($view);
        $this->registerButtons();
    }

    /**
     * Registers the docheader buttons
     */
    protected function registerButtons()
    {
        $buttonBar = $this->view->getModuleTemplate()->getDocHeaderComponent()->getButtonBar();
        $showButton = $buttonBar->makeLinkButton()
            ->setHref($this->getTargetUrl())
            ->setOnClick('window.open(this.href, \'newTYPO3frontendWindow\').focus();return false;')
            ->setTitle($this->getLanguageService()->sL('LLL:EXT:lang/Resources/Private/Language/locallang_core.xlf:labels.showPage'))
            ->setIcon($this->view->getModuleTemplate()->getIconFactory()->getIcon('actions-document-view', Icon::SIZE_SMALL));
        $buttonBar->addButton($showButton);

        $refreshButton = $buttonBar->makeLinkButton()
            ->setHref('javascript:document.getElementById(\'tx_viewpage_iframe\').contentWindow.location.reload(true);')
            ->setTitle($this->getLanguageService()->sL('LLL:EXT:viewpage/Resources/Private/Language/locallang.xlf:refreshPage'))
            ->setIcon($this->view->getModuleTemplate()->getIconFactory()->getIcon('actions-refresh', Icon::SIZE_SMALL));
        $buttonBar->addButton($refreshButton, ButtonBar::BUTTON_POSITION_RIGHT, 1);

        $currentRequest = $this->request;
        $moduleName = $currentRequest->getPluginName();
        $getVars = $this->request->getArguments();
        $extensionName = $currentRequest->getControllerExtensionName();
        if (count($getVars) === 0) {
            $modulePrefix = strtolower('tx_' . $extensionName . '_' . $moduleName);
            $getVars = ['id', 'M', $modulePrefix];
        }
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setModuleName($moduleName)
            ->setGetVariables($getVars);
        $buttonBar->addButton($shortcutButton, ButtonBar::BUTTON_POSITION_RIGHT, 2);
    }

    /**
     * Gets called before each action
     */
    public function initializeAction()
    {
        $this->getLanguageService()->includeLLFile('EXT:viewpage/Resources/Private/Language/locallang.xlf');
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addInlineLanguageLabelFile('EXT:viewpage/Resources/Private/Language/locallang.xlf');
    }

    /**
     * Show selected page from pagetree in iframe
     */
    public function showAction()
    {
        $this->view->assign('widths', $this->getPreviewFrameWidths());
        $this->view->assign('url', $this->getTargetUrl());
        $this->view->assign('languages', $this->getPreviewLanguages());
        $this->view->assign('pageTitle', $this->getPageTitle());
    }

    /**
     * Determine the url to view
     *
     * @return string
     */
    protected function getTargetUrl()
    {
        $pageIdToShow = (int)GeneralUtility::_GP('id');

        $permissionClause = $this->getBackendUser()->getPagePermsClause(1);
        $pageRecord = BackendUtility::readPageAccess($pageIdToShow, $permissionClause);
        if ($pageRecord) {
            $this->view->getModuleTemplate()->getDocHeaderComponent()->setMetaInformation($pageRecord);

            $adminCommand = $this->getAdminCommand($pageIdToShow);
            $domainName = $this->getDomainName($pageIdToShow);
            $languageParameter = $this->getLanguageParameter();
            // Mount point overlay: Set new target page id and mp parameter
            /** @var \TYPO3\CMS\Frontend\Page\PageRepository $sysPage */
            $sysPage = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\PageRepository::class);
            $sysPage->init(false);
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
                // TCEMAIN.previewDomain can contain the protocol, check prevents double protocol URLs
                if (strpos($domainName, '://') !== false) {
                    $protocolAndHost = $domainName;
                } else {
                    $protocol = GeneralUtility::getIndpEnv('TYPO3_SSL') ? 'https' : 'http';
                    $protocolAndHost = $protocol . '://' . $domainName;
                }
            }
            return $protocolAndHost . '/index.php?id=' . $finalPageIdToShow . $this->getTypeParameterIfSet($finalPageIdToShow) . $mountPointMpParameter . $adminCommand . $languageParameter;
        }
        return '#';
    }

    /**
     * Get admin command
     *
     * @param int $pageId
     * @return string
     */
    protected function getAdminCommand($pageId)
    {
        // The page will show only if there is a valid page and if this page may be viewed by the user
        $pageinfo = BackendUtility::readPageAccess($pageId, $this->getBackendUser()->getPagePermsClause(1));
        $addCommand = '';
        if (is_array($pageinfo)) {
            $addCommand = '&ADMCMD_editIcons=1' . BackendUtility::ADMCMD_previewCmds($pageinfo);
        }
        return $addCommand;
    }

    /**
     * With page TS config it is possible to force a specific type id via mod.web_view.type
     * for a page id or a page tree.
     * The method checks if a type is set for the given id and returns the additional GET string.
     *
     * @param int $pageId
     * @return string
     */
    protected function getTypeParameterIfSet($pageId)
    {
        $typeParameter = '';
        $modTSconfig = BackendUtility::getModTSconfig($pageId, 'mod.web_view');
        $typeId = (int)$modTSconfig['properties']['type'];
        if ($typeId > 0) {
            $typeParameter = '&type=' . $typeId;
        }
        return $typeParameter;
    }

    /**
     * Get domain name for requested page id
     *
     * @param int $pageId
     * @return string|NULL Domain name from first sys_domains-Record or from TCEMAIN.previewDomain, NULL if neither is configured
     */
    protected function getDomainName($pageId)
    {
        $previewDomainConfig = $this->getBackendUser()->getTSConfig('TCEMAIN.previewDomain', BackendUtility::getPagesTSconfig($pageId));
        if ($previewDomainConfig['value']) {
            $domain = $previewDomainConfig['value'];
        } else {
            $domain = BackendUtility::firstDomainRecord(BackendUtility::BEgetRootLine($pageId));
        }
        return $domain;
    }

    /**
     * Get available widths for preview frame
     *
     * @return array
     */
    protected function getPreviewFrameWidths()
    {
        $pageId = (int)GeneralUtility::_GP('id');
        $modTSconfig = BackendUtility::getModTSconfig($pageId, 'mod.web_view');
        $widths = [
            '100%|100%' => $this->getLanguageService()->getLL('autoSize')
        ];
        if (is_array($modTSconfig['properties']['previewFrameWidths.'])) {
            foreach ($modTSconfig['properties']['previewFrameWidths.'] as $item => $conf) {
                $label = '';

                $width = substr($item, 0, -1);
                $data = ['width' => $width];
                $label .= $width . 'px ';

                //if height is set
                if (isset($conf['height'])) {
                    $label .= ' × ' . $conf['height'] . 'px ';
                    $data['height'] = $conf['height'];
                }

                if (substr($conf['label'], 0, 4) !== 'LLL:') {
                    $label .= $conf['label'];
                } else {
                    $label .= $this->getLanguageService()->sL(trim($conf['label']));
                }
                $value = ($data['width'] ?: '100%') . '|' . ($data['height'] ?: '100%');
                $widths[$value] = $label;
            }
        }
        return $widths;
    }

    /**
     * Returns the preview languages
     *
     * @return array
     */
    protected function getPreviewLanguages()
    {
        $pageIdToShow = (int)GeneralUtility::_GP('id');
        $modSharedTSconfig = BackendUtility::getModTSconfig($pageIdToShow, 'mod.SHARED');
        if ($modSharedTSconfig['properties']['view.']['disableLanguageSelector'] === '1') {
            return [];
        }
        $languages = [
            0 => isset($modSharedTSconfig['properties']['defaultLanguageLabel'])
                    ? $modSharedTSconfig['properties']['defaultLanguageLabel'] . ' (' . $this->getLanguageService()->sL('LLL:EXT:lang/Resources/Private/Language/locallang_mod_web_list.xlf:defaultLanguage') . ')'
                    : $this->getLanguageService()->sL('LLL:EXT:lang/Resources/Private/Language/locallang_mod_web_list.xlf:defaultLanguage')
        ];
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_language');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        if (!$this->getBackendUser()->isAdmin()) {
            $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        }

        $result = $queryBuilder->select('sys_language.uid', 'sys_language.title')
            ->from('sys_language')
            ->join(
                'sys_language',
                'pages_language_overlay',
                'o',
                $queryBuilder->expr()->eq('o.sys_language_uid', $queryBuilder->quoteIdentifier('sys_language.uid'))
            )
            ->where(
                $queryBuilder->expr()->eq(
                    'o.pid',
                    $queryBuilder->createNamedParameter($pageIdToShow, \PDO::PARAM_INT)
                )
            )
            ->groupBy('sys_language.uid', 'sys_language.title', 'sys_language.sorting')
            ->orderBy('sys_language.sorting')
            ->execute();

        while ($row = $result->fetch()) {
            if ($this->getBackendUser()->checkLanguageAccess($row['uid'])) {
                $languages[$row['uid']] = $row['title'];
            }
        }
        return $languages;
    }

    /**
     * Returns the page title
     *
     * @return string
     */
    protected function getPageTitle()
    {
        $pageIdToShow = (int)GeneralUtility::_GP('id');
        $pageRecord = BackendUtility::getRecord('pages', $pageIdToShow);
        $pageRecordTitle = is_array($pageRecord)
            ? BackendUtility::getRecordTitle('pages', $pageRecord)
            : '';

        return $pageRecordTitle;
    }

    /**
     * Gets the L parameter from the user session
     *
     * @return string
     */
    protected function getLanguageParameter()
    {
        $states = $this->getBackendUser()->uc['moduleData']['web_view']['States'];
        $languages = $this->getPreviewLanguages();
        $languageParameter = '';
        if (isset($states['languageSelectorValue']) && isset($languages[$states['languageSelectorValue']])) {
            $languageParameter = '&L=' . (int)$states['languageSelectorValue'];
        }
        return $languageParameter;
    }

    /**
     * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
