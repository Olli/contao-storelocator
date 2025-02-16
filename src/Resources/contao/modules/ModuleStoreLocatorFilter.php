<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2022 Leo Feyer
 *
 * @package   StoreLocator
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL
 * @copyright 2022 numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\StoreLocator;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Environment;
use Contao\FormSubmit;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\Module;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\RequestStack;


class ModuleStoreLocatorFilter extends Module {


    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_storelocator_filter';


    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate(): string {

        $scopeMatcher = System::getContainer()->get('contao.routing.scope_matcher');
        $requestStack = System::getContainer()->get('request_stack');

        if( $scopeMatcher->isBackendRequest($requestStack->getCurrentRequest()) ) {

            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### '.$GLOBALS['TL_LANG']['FMD']['storelocator_filter'][0].' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        return parent::generate();
    }


    /**
     * Generate module
     */
    protected function compile(): void {

        $page = null;
        $page = System::getContainer()->get('request_stack')->getCurrentRequest()->get('pageModel');

        $this->Template = new FrontendTemplate($this->storelocator_filter_tpl?:$this->strTemplate);
        $this->Template->formId = 'storelocator_filter_'.$this->id;
        $this->Template->action = Environment::get('request');
        $this->Template->requestToken = (defined('VERSION') ? '{{request_token}}' : System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue());

        if( !isset($_GET['search']) && Config::get('useAutoItem') && isset($_GET['auto_item']) ) {
            Input::setGet('search', Input::get('auto_item'));
        }

        $sSearchVal = Input::get('search') ? Input::get('search') : null;

        $aSearchValues = StoreLocator::parseSearchValue($sSearchVal);

        // generate form elements
        $fieldClass = class_exists('\Contao\FormText')?'\Contao\FormText':'\Contao\FormTextField';

        $widgetFilter = null;
        $widgetFilter = new $fieldClass($fieldClass::getAttributesFromDca(
                [
                    'name'          => 'filter'
                ,   'label'         => &$GLOBALS['TL_LANG']['tl_storelocator']['filter']['filter_label']
                ,   'inputType'     => 'text'
                ,    'eval'         => ['mandatory'=>true]
                ]
            ,   'filter'
            ,   $aSearchValues['filter']??null
            )
        );

        $widgetSubmit = null;
        $widgetSubmit = new FormSubmit();
        $widgetSubmit->id = 'filtering';
        $widgetSubmit->label = $GLOBALS['TL_LANG']['tl_storelocator']['filter']['filter'];

        $this->Template->labelReset = $GLOBALS['TL_LANG']['tl_storelocator']['filter']['filter_reset'];
        $this->Template->hrefReset = $page->getFrontendUrl('/clear/filter');

        if( Input::get('clear') == 'filter' ) {

            $aSearchValues['filter'] = null;
            $aSearchValues['order'] = null;
            $aSearchValues['sort'] = null;

            $strData = StoreLocator::generateSearchValue($aSearchValues);

            if( strlen($strData) == 0 ) {

                $href = $page->getFrontendUrl();

            } else {

                $href = $page->getFrontendUrl(sprintf(((Config::get('useAutoItem') && !Config::get('disableAlias')) ? '/%s' : '/search/%s'), $strData));
            }

            $this->redirect( $href );
        }

        // redirect to listing page
        if( Input::post('FORM_SUBMIT') == $this->Template->formId ) {

            $widgetFilter->validate();
            $filter = $widgetFilter->value;

            if( !empty($filter) ) {

                $aSearchValues['filter'] = $filter;

                $strData = StoreLocator::generateSearchvalue($aSearchValues);
                $objListPage = $this->jumpTo ? PageModel::findWithDetails($this->jumpTo) : $page;

                $href = $page->getFrontendUrl(sprintf(((Config::get('useAutoItem') && !Config::get('disableAlias')) ? '/%s' : '/search/%s'), $strData));

                $this->redirect($href);
            }

        } else if( Input::get('order') && Input::get('sort') ) {

            $aSearchValues['order'] = Input::get('order');
            $aSearchValues['sort'] = Input::get('sort');

            $strData = StoreLocator::generateSearchvalue($aSearchValues);

            $href = $page->getFrontendUrl(sprintf((Config::get('useAutoItem') && !Config::get('disableAlias')) ? '/%s' : '/search/%s', $strData));

            $this->redirect($href);
        }

        $sortFilter = [];
        $sortableFields = StringUtil::deserialize($this->storelocator_sortable)??[];
        foreach( $sortableFields as $key => $value ) {

            $active = !empty($aSearchValues['order'])&&$aSearchValues['order']==$value;
            $newSort = ($active&&!empty($aSearchValues['sort'])&&$aSearchValues['sort']=='asc')?'desc':'asc';

            $strData = StoreLocator::generateSearchvalue($aSearchValues);
            if( $strData ) {

                $href = $page->getFrontendUrl(sprintf(((Config::get('useAutoItem') && !Config::get('disableAlias')) ? '/%s' : '/search/%s' ).'/order/%s/sort/%s', $strData, $value, $newSort));

            } else {

                $href = $page->getFrontendUrl(sprintf('/order/%s/sort/%s', $value, $newSort));
            }

            $sortFilter[] = [
                'label' => $GLOBALS['TL_LANG']['tl_storelocator']['filter'][$value]
            ,   'href' => $href
            ,   'title' => $GLOBALS['TL_LANG']['tl_storelocator']['filter'][$value]." ".$GLOBALS['TL_LANG']['tl_storelocator']['filter']['order'][$newSort]
            ,   'class' => ($active?"active ".$newSort:"")
            ];
        }

        $this->Template->labelFilter = $GLOBALS['TL_LANG']['tl_storelocator']['filter']['filter_label'];
        $this->Template->labelSorting = $GLOBALS['TL_LANG']['tl_storelocator']['filter']['order_label'];

        $this->Template->filterField = $widgetFilter;
        $this->Template->sortFields = $sortFilter;
        $this->Template->submitButton = $widgetSubmit;
    }
}
