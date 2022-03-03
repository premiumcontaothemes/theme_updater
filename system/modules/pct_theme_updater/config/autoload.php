<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2013 Leo Feyer
 *
 * @package pct_theme_updater
 * @link    https://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */
 
namespace Contao;

/**
 * Register the namespaces
 */
ClassLoader::addNamespaces(array
(
	'PCT',
));


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	'PCT\ThemeUpdater' 							=> 'system/modules/pct_theme_updater/PCT/ThemeUpdater.php',
	'PCT\ThemeUpdater\SystemCallbacks'			=> 'system/modules/pct_theme_updater/PCT/ThemeUpdater/SystemCallbacks.php',
));

ClassLoader::addClasses(array
(
	'PCT\ThemeUpdater\Contao4\InstallationController'		=> 'system/modules/pct_theme_updater/PCT/ThemeUpdater/Contao4/InstallationController.php',
));

/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	'be_pct_theme_updater'					=> 'system/modules/pct_theme_updater/templates',
	'be_js_pct_theme_updater'				=> 'system/modules/pct_theme_updater/templates',
	'pct_theme_updater_breadcrumb'			=> 'system/modules/pct_theme_updater/templates',
));
