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
use Contao\BackendUser;
use Contao\Input;


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
		$request = System::getContainer()->get('request_stack')->getCurrentRequest();
		if( $request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request) )
		{
			$objUser = BackendUser::getInstance();
			if( !$objUser->admin )
			{
				unset( $GLOBALS['BE_MOD']['system']['pct_theme_updater'] );
			}

			// load jquery in theme updater backend module
			if( Input::get('do') == 'pct_theme_updater' && !isset($GLOBALS['PCT_AUTOGRID']['assetsLoaded']) )
			{
				$GLOBALS['TL_JAVASCRIPT'][] = System::getContainer()->get('contao.assets.assets_context')->getStaticUrl().'/jquery/js/jquery.min.js';
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
		$request = System::getContainer()->get('request_stack')->getCurrentRequest();
		if( $request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)	&& $objTemplate->getName() == 'be_main')
		{
			$objScripts = new \Contao\BackendTemplate('be_js_pct_theme_updater');
			$objTemplate->javascripts .= $objScripts->parse();
			
			if( Input::get('do') == 'pct_theme_updater' && !isset($GLOBALS['PCT_AUTOGRID']['assetsLoaded']) )
			{
				$objTemplate->javascripts .= '<script>jQuery.noConflict();</script>';
			}			
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
			$objSession = System::getContainer()->get('request_stack')->getSession();
			$arrSession = $objSession->get('pct_theme_updater');
			$arrSession[$strAction][Input::post('task')] = Input::post('checked');
			$objSession->set('pct_theme_updater',$arrSession);
		}
	}
}