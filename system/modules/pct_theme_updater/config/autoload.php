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

  
// path relative from composer directory
$path = \Contao\System::getContainer()->getParameter('kernel.project_dir').'/vendor/composer/../../system/modules/pct_theme_updater';

/**
 * Register the classes
 */
$classMap = array
(
	'PCT\ThemeUpdater' 							=> $path.'/PCT/ThemeUpdater.php',
	'PCT\ThemeUpdater\SystemCallbacks'			=> $path.'/PCT/ThemeUpdater/SystemCallbacks.php',
	'PCT\ThemeUpdater\Contao4\InstallationController'	=> $path.'/PCT/ThemeUpdater/Contao4/InstallationController.php',
);

$loader = new \Composer\Autoload\ClassLoader();
$loader->addClassMap($classMap);
$loader->register();

/**
 * Register the templates
 */
\Contao\TemplateLoader::addFiles(array
(
	'be_pct_theme_updater'					=> 'system/modules/pct_theme_updater/templates',
	'be_js_pct_theme_updater'				=> 'system/modules/pct_theme_updater/templates',
	'pct_theme_updater_breadcrumb'			=> 'system/modules/pct_theme_updater/templates',
));
