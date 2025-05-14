<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2022 Leo Feyer
 * 
 * @copyright	Tim Gatzky 2022, Premium Contao Themes
 * @author		Tim Gatzky <info@tim-gatzky.de>
 * @package		pct_theme_updater
 */

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\System;

/**
 * Constants
 */
define('PCT_THEME_UPDATER', '4.0.2');
define('PCT_THEME_UPDATER_PATH','system/modules/pct_theme_updater');

if( version_compare(ContaoCoreBundle::getVersion(),'5.0','>=') )
{
	$rootDir = System::getContainer()->getParameter('kernel.project_dir');
	include( $rootDir.'/'.PCT_THEME_UPDATER_PATH.'/config/autoload.php' );
}

/**
 * Maintenance
 */
$GLOBALS['TL_MAINTENANCE'][] = 'PCT\ThemeUpdater\Maintenance';

/**
 * Globals
 */
$GLOBALS['PCT_THEME_UPDATER']['api_url'] = 'https://api.premium-contao-themes.com';
$GLOBALS['PCT_THEME_UPDATER']['config_url'] = 'https://update.premium-contao-themes.com/updater_pro.json';
$GLOBALS['PCT_THEME_UPDATER']['updater_api_url'] = 'https://update.premium-contao-themes.com/updater.php';
$GLOBALS['PCT_THEME_UPDATER']['tmpFolder'] = 'system/tmp/pct_theme_updater';
$GLOBALS['PCT_THEME_UPDATER']['logFile'] = 'var/pct_themeupdater_log.json';
$GLOBALS['PCT_THEME_UPDATER']['debug'] = false;

$GLOBALS['PCT_THEME_UPDATER']['THEMES']['eclipseX'] = array
(
	'label'	=> 'EclipseX',
	'zip_folder' => 'eclipseX_zip',
	'mandatory' => array('upload'), // mandatory zip content on first level
	'sql_templates' => array
	(
		'5.3' => 'eclipsex_contao_5_3.sql',
	),
);
$GLOBALS['PCT_THEME_UPDATER']['THEMES']['eclipseX_cc'] = array
(
	'label'	=> 'EclipseX + CustomCatalog Pro',
	'isCustomCatalog' => true,
	'zip_folder' => 'eclipseX_cc_zip',
	'mandatory' => array('upload'), // mandatory zip content on first level
	'sql_templates' => array
	(
		'5.3' => 'eclipsex_contao_5_3.sql',
	),
);

// Logic: STATUS.STEP
$GLOBALS['PCT_THEME_UPDATER']['status'] = array
(
	'WELCOME',
	'RESET',
	'VALIDATION',
	'ACCEPTED',
	'FILE_EXISTS',
	'FILE_NOT_EXISTS',
	'INSTALLATION.UNZIP',
	'INSTALLATION.COPY_FILES',
	'INSTALLATION.CLEAR_CACHE',
	'INSTALLATION.DB_UPDATE_MODULES',
	'INSTALLATION.SQL_TEMPLATE_WAIT',
	'INSTALLATION.SQL_TEMPLATE_IMPORT',
);

$GLOBALS['PCT_THEME_UPDATER']['breadcrumb_steps'] = array
(
	'WELCOME' => array
	(
		'label'	=> &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['welcome'][0],
		'description' => &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['welcome'][1],
		'href'	=> 'status=welcome',
		'protected'	=> true
	),
	'VALIDATION_UPDATER' => array
	(
		'label'	=> &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['validation_updater'][0],
		'description' => &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['validation_updater'][1],
		'href'	=> 'status=enter_theme_license',
		'protected'	=> true
	),
	'VALIDATION' => array
	(
		'label'	=> &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['validation'][0],
		'description' => &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['validation'][1],
		'href'	=> 'status=validation',
		'protected'	=> true
	),
	'ACCEPTED' => array
	(
		'label'	=> &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['accepted'][0],
		'description' => &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['accepted'][1],
		'href'	=> 'status=accepted',
		'protected'	=> true
	),
	'NOT_ACCEPTED' => array
	(
		'label'	=> &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['not_accepted'][0],
		'description' => &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['not_accepted'][1],
		'href'	=> 'status=not_accepted',
		'protected'	=> true
	),
	'LOADING' => array
	(
		'label'	=> &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['loading'][0],
		'description' => &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['loading'][1],
		'href'	=> 'status=loading',
	),
	'INSTALLATION.UNZIP' => array
	(
		'label'	=> &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['installation.unzip'][0],
		'description' => &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['installation.unzip'][1],
		'href'	=> 'status=installation&step=unzip',
	),
	'INSTALLATION.COPY_FILES' => array
	(
		'label'	=> &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['installation.copy_files'][0],
		'description' => &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['installation.copy_files'][1],
		'href'	=> 'status=installation&step=copy_files',
	),
	'INSTALLATION.CLEAR_CACHE' => array
	(
		'label'	=> &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['installation.clear_cache'][0],
		'description' => &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['installation.clear_cache'][1],
		'href'	=> 'status=installation&step=clear_cache',
	),
	'INSTALLATION.DB_UPDATE_MODULES' => array
	(
		'label'	=> &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['installation.db_update_modules'][0],
		'description' => &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['installation.db_update_modules'][1],
		'href'	=> 'status=installation&step=db_update_modules',
	),
	'INSTALLATION.SQL_TEMPLATE_WAIT' => array
	(
		'label'	=> &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['installation.sql_template_wait'][0],
		'description' => &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['installation.sql_template_wait'][1],
		'href'	=> 'status=installation&step=sql_template_wait',
		'protected'	=> true
	),
	'MANUAL_ADJUSTMENT' => array
	(
		'label'	=> &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['manual_adjustment'][0],
		'description' => &$GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['BREADCRUMB']['manual_adjustment'][1],
		'href'	=> 'status=manual_adjustment',
		'isLink'	=> true,
		'avoid_complete' => true
	),
);
// redirects
$GLOBALS['PCT_THEME_UPDATER']['routes'] = array
(
	'enter_theme_license' => 'enter_updater_license',
	'enter_updater_license' => 'ready',
);


/**
 * Register backend page / key
 */
$GLOBALS['BE_MOD']['system']['pct_theme_updater'] = array
(
	'callback'    	=> 'PCT\ThemeUpdater',
	'icon'	 		=> PCT_THEME_UPDATER_PATH.'/assets/img/icon.jpg',
	'stylesheet' 	=> PCT_THEME_UPDATER_PATH.'/assets/css/be_styles.css',
	'tables'		=> array('tl_module'), // just for faking Contao in Ajax requests
);

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['parseTemplate'][] = array('PCT\ThemeUpdater\SystemCallbacks','injectScripts');
$GLOBALS['TL_HOOKS']['initializeSystem'][] = array('PCT\ThemeUpdater\SystemCallbacks','initializeSystemCallback');
$GLOBALS['TL_HOOKS']['executePostActions'][] = array('PCT\ThemeUpdater\SystemCallbacks','executePostActionsCallback');
