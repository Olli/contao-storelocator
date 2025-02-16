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
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\Module;
use Contao\StringUtil;
use Contao\System;


class ModuleStoreLocatorDetails extends Module {


    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_storelocator_details';


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

            $objTemplate->wildcard = '### '.$GLOBALS['TL_LANG']['FMD']['storelocator_details'][0].' ###';
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

        global $objPage;

        $this->Template = new FrontendTemplate($this->storelocator_details_tpl?:$this->strTemplate);
        $this->Template->referer = 'javascript:history.go(-1)';
        $this->Template->back = $GLOBALS['TL_LANG']['MSC']['goBack'];

        if( !isset($_GET['store']) && Config::get('useAutoItem') && isset($_GET['auto_item']) ) {
            Input::setGet('store', Input::get('auto_item'));
        }

        $alias = Input::get('store') ? Input::get('store') : null;

        $objStore = null;
        $objStore = StoresModel::findByIdOrAlias($alias);

        if( !$objStore ) {
            throw new PageNotFoundException('store not found');
        }

        // change page title
        $objPage->pageTitle = $objStore->name;

        // get image
        if( $objStore->singleSRC ) {

            $objFile = null;
            $objFile = FilesModel::findByUuid($objStore->singleSRC);
            $objStore->image = $objFile;
        }

        $this->Template->labelPhone = $GLOBALS['TL_LANG']['tl_storelocator']['field']['phone'];
        $this->Template->labelFax = $GLOBALS['TL_LANG']['tl_storelocator']['field']['fax'];
        $this->Template->labelEMail = $GLOBALS['TL_LANG']['tl_storelocator']['field']['email'];
        $this->Template->labelWWW = $GLOBALS['TL_LANG']['tl_storelocator']['field']['www'];

        $this->Template->maps_provider = $this->storelocator_provider;
        if( $this->storelocator_provider == 'google-maps' ) {
            $this->Template->mapsURI = sprintf(
                "https://www.google.com/maps/embed/v1/place?q=%s&key=%s"
                ,   rawurlencode($objStore->name.', '.$objStore->street.', '.$objStore->postal.' '.$objStore->city)
                ,   Config::get('google_maps_browser_key')
            );
        }

        if( $objStore->image ) {

            $temp = new \stdClass();

            // Contao >= 4.9
            if( method_exists($this, 'addImageToTemplate') ) {

                $this->addImageToTemplate($this->Template, [
                    'singleSRC' => $objStore->image->path
                ,   'size' => $this->imgSize
                ], null, null, $objFile);

            // Contao 5
            } else {

                $figureBuilder = System::getContainer()
                    ->get('contao.image.studio')
                    ->createFigureBuilder()
                    ->from($objStore->image->path)
                    ->setSize($this->imgSize);

                if( null !== ($figure = $figureBuilder->buildIfResourceExists()) ) {
                    $figure->applyLegacyTemplateData($this->Template);
                }
            }
        }

        StoreLocator::parseStoreData($objStore, $this);

        $this->Template->store = $objStore;
    }
}
