<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (C) 2005-2024 Leo Feyer
 *
 * @copyright Tim Gatzky 2024, Premium Contao Themes
 * @author  Tim Gatzky <info@tim-gatzky.de>
 * @package  pct_theme_updater
 */

/**
 * Namespace
 */
namespace PCT\ThemeUpdater;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Database;
use Contao\Environment;
use Contao\File;
use Contao\Input;
use Contao\MaintenanceModuleInterface;
use Contao\StringUtil;
use Contao\System;
use PCT\ThemeUpdater;
use PCT\ThemeUpdater\Maintenance\Jobs;

/**
 * Class file
 * Maintenance
 */
class Maintenance extends Backend implements MaintenanceModuleInterface
{
	public function isActive()
	{
		return Input::get('act') == 'themeupdater';
	}
	
	
	/**
	 * Generate the job list
	 * @return string
	 */
	public function run()
	{
		if( ThemeUpdater::validate() === false )
		{
			return '';
		}
		
		$objSession = System::getContainer()->get('request_stack')->getSession();
		$objDatabase = Database::getInstance();
		
		$arrJobs = array();
		foreach( array('news_order','center_center_to_crop','form_textfield_form_text') as $key)
		{
			$arrJobs[$key] = array
			(
				'id' => 'pct_themeupdater_jobs_'.$key,
				'name' => $key,
				'title' => $GLOBALS['TL_LANG']['tl_maintenance']['pct_themeupdater'][$key][0],
				'description' => $GLOBALS['TL_LANG']['tl_maintenance']['pct_themeupdater'][$key][1],
				'affected' => '',
				'callback' => array(Jobs::class,$key),
			);
		}

		$objResult = $objDatabase->prepare("SELECT id FROM tl_module WHERE news_order=?")->limit(1)->execute('date DESC');
		if($objResult->numRows < 1)
		{
			unset($arrJobs['news_order']);
		}
		
		$objTemplate = new BackendTemplate('be_maintenance_theme_updater');
		$objTemplate->isActive = $this->isActive();
		
		// Confirmation message
		if ($objSession->get('PCT_THEMEUPDATER_MESSAGE') != '')
		{
			$objTemplate->message = sprintf('<p class="tl_confirm">%s</p>' . "\n", $objSession->get('PCT_THEMEUPDATER_MESSAGE'));
			$objSession->remove('PCT_THEMEUPDATER_MESSAGE');
		}
		
		// Run the jobs
		if ( Input::post('FORM_SUBMIT') == 'pct_themeupdater_jobs' && Input::post('pct_themeupdater_job') !== null )
		{
			$jobs_done = array();
			foreach( Input::post('pct_themeupdater_job') as $key )
			{
				$job = $arrJobs[$key];
				
				$jobs_done[] = $job['name'];
				
				list($class, $method) = $job['callback'];
				$this->import($class);
				$this->$class->$method();
			}
			$objSession->set('PCT_THEMEUPDATER_MESSAGE',sprintf($GLOBALS['TL_LANG']['tl_maintenance']['pct_themeupdater']['jobs_done'], \implode(', ',$jobs_done) ));
			$this->reload();
		}
		
		$objTemplate->jobs = $arrJobs;
		$objTemplate->action = StringUtil::ampersand(\Contao\Environment::get('request'));
		$objTemplate->headline = $GLOBALS['TL_LANG']['tl_maintenance']['pct_themeupdater']['jobs'];
		$objTemplate->job = $GLOBALS['TL_LANG']['tl_maintenance']['job'];
		$objTemplate->description = $GLOBALS['TL_LANG']['tl_maintenance']['description'];
		$objTemplate->submit = \Contao\StringUtil::specialchars($GLOBALS['TL_LANG']['tl_maintenance']['pct_themeupdater']['run_job']);
		$objTemplate->help = ($GLOBALS['TL_CONFIG']['showHelp'] && ($GLOBALS['TL_LANG']['tl_maintenance']['cacheTables'][1] != '')) ? $GLOBALS['TL_LANG']['tl_maintenance']['cacheTables'][1] : '';
		
		return $objTemplate->parse();
	}	
}