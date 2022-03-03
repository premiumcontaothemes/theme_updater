<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (C) 2005-2013 Leo Feyer
 *
 * @copyright Tim Gatzky 2021, Premium Contao Themes
 * @author  Tim Gatzky <info@tim-gatzky.de>
 * @package  pct_theme_updater
 */

/**
 * Namespace
 */
namespace PCT\ThemeUpdater;

/**
 * Imports 
 */
use Contao\System;
use Contao\Environment;
use Contao\BackendUser;
use Contao\Input;
use Contao\Config;
use Contao\Controller;
use Contao\Message;
use Contao\Files;
use Contao\Session;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Class file
 * SystemCallbacks
 */
class SystemCallbacks extends System
{
	/**
	 * Installation completed when contao quits back to the login screen
	 * 
	 * Called from [initializeSystem] Hook
	 */
	public function installationCompletedStatus()
	{
		if(version_compare(VERSION, '4.4', '<') && Config::get('adminEmail') == '')
		{
			return;
		}
		
		if(TL_MODE != 'BE' || Environment::get('isAjaxRequest'))
		{
			return;
		}
		
		$objUser = BackendUser::getInstance();
		if((int)$objUser->id > 0)
		{
			return;
		}
		
		// load language files
		System::loadLanguageFile('default');
		
		$objSession = System::getContainer()->get('session');
		$arrSession = $objSession->get('pct_theme_updater');
		
		if(Input::get('welcome') != '')
		{
			// check if theme data exists
			if(!isset($GLOBALS['PCT_THEME_UPDATER']['THEMES'][ Input::get('welcome') ]))
			{
				$url = \Contao\StringUtil::decodeEntities( Controller::addToUrl('',false,array('welcome')) );
				$this->redirect($url);
			}
			
			$strName = $GLOBALS['PCT_THEME_UPDATER']['THEMES'][ Input::get('welcome') ]['label'] ?: Input::get('welcome');
			
			// add backend message
			Message::addInfo( sprintf($GLOBALS['TL_LANG']['pct_theme_updater']['completeStatusMessage'],$strName) );
			
			return;
		}
		
		if((int)Input::get('completed') == 1 && Input::get('theme') != '')
		{
			// remove the tmp.SQL file
			$strTemplate = Input::get('sql');
			if(file_exists(TL_ROOT.'/templates/tmp_'.$strTemplate))
			{
				Files::getInstance()->delete('templates/tmp_'.$strTemplate);
			}
			
			$url = \Contao\StringUtil::decodeEntities( Controller::addToUrl('welcome='.Input::get('theme'),false,array('completed','theme','sql','referer','rt','ref')) );
			$this->redirect($url);
		}
	}
	
	
	/**
	 * Inject javascript templates in the backend page
	 * @param object
	 *
	 * Called from [parseTemplate] Hook
	 */
	public function injectScripts($objTemplate)
	{
		if(TL_MODE == 'BE' && $objTemplate->getName() == 'be_main')
		{
			$objScripts = new \Contao\BackendTemplate('be_js_pct_theme_updater');
			$objTemplate->javascripts .= $objScripts->parse();
		}
	}


	/**
	 * Handle backend POST ajax requests
	 * @param mixed $strAction 
	 * @param mixed $dc 
	 * 
	 * called from executePostActions Calback
	 */
	public function executePostActionsCallback($strAction)
	{
		// store the checked tasks in the session
		if( $strAction == 'toggle_tasks' )
		{
			$objSession = System::getContainer()->get('session');
			$arrSession = $objSession->get('pct_theme_updater');
			$arrSession[$strAction][Input::post('task')] = Input::post('checked');
			$objSession->set('pct_theme_updater',$arrSession);
		}
	}
}