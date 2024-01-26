<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2013 Leo Feyer
 * 
 * @copyright	Tim Gatzky 2018, Premium Contao Themes
 * @author		Tim Gatzky <info@tim-gatzky.de>
 * @package		pct_theme_updater
 */

/**
 * Namespace
 */
namespace PCT\ThemeUpdater\Contao4;

use Contao\System;
use ReflectionObject;

/**
 * Class file
 * InstallationController
 */
class InstallationController extends \Contao\InstallationBundle\Controller\InstallationController
{
	public function __construct()
	{
	}
	
	
	/**
	 * Call methods
	 * @param string Name of function
	 * @param array
	 */
	public function call($strMethod, $arrArguments=array())
	{
		$request = System::getContainer()->get('request_stack')->getCurrentRequest();
		if( $request && !System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request) )
		{
			throw new \Exception('Not allowed to be executed outside Contaos backend');
		}
		
		if (method_exists($this, $strMethod))
		{
			return call_user_func_array(array($this, $strMethod), $arrArguments);
		}
		throw new \RuntimeException('undefined method: '.get_class($this).'::'.$strMethod);
	}
	
	
	/** @return void  */
	public function purgeSymfonyCache()
	{
		$obj = new parent;
		$obj->container = System::getContainer();
		$reflector = new ReflectionObject( $obj );
		$method = $reflector->getMethod('purgeSymfonyCache');
		$method->setAccessible(true);
		$method->invoke($obj);
	}
}