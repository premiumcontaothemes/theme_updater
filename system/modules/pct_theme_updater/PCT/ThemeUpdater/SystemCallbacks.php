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
	 * Remove backend module for non admins
	 * 
	 * Called from [initializeSystem] Hook
	 */
	public function initializeSystemCallback()
	{
		if( TL_MODE == 'BE' )
		{
			$objUser = BackendUser::getInstance();
			if( !$objUser->admin )
			{
				unset( $GLOBALS['BE_MOD']['system']['pct_theme_updater'] );
			}

			// load jquery in theme updater backend module
			if( Input::get('do') == 'pct_theme_updater' && !isset($GLOBALS['PCT_AUTOGRID']['assetsLoaded']) )
			{
				$GLOBALS['TL_JAVASCRIPT'][] = '//code.jquery.com/jquery-3.6.0.min.js';
				$GLOBALS['TL_HEAD'][] = '<script>jQuery.noConflict();</script>';
			}
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