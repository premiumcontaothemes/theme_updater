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
		$objSession = System::getContainer()->get('request_stack')->getSession();
		
		// check license
		$arrSession = $objSession->get('pct_theme_updater');
		$objUpdaterLicense = $arrSession['updater_license'] ?? null;
		if( isset($arrSession['updater_license']) && \is_string($arrSession['updater_license']) && empty($arrSession['updater_license']) === false)
		{
			$objUpdaterLicense = \json_decode($arrSession['updater_license']);
		}
		else
		{
			$objFile = new File('var/pct_license');
			if( $objFile->exists() === true )
			{
				$strThemeLicense = trim( $objFile->getContent() );
			}
			$objFile = new File('var/pct_license_themeupdater');
			if( $objFile->exists() === true )
			{
				$strLicense = trim( $objFile->getContent() );
			}

			// registration logic
			$strRegistration = $strThemeLicense.'___'.StringUtil::decodeEntities( str_replace(array('www.'),'',Environment::get('host')) );
			
			// validate
			$arrParams = array
			(
				'domain'	=> $strRegistration,
				'key'		=> $strLicense,
			);

			if( empty($strLicense) === false )
			{
				$objThemeUpdater = new ThemeUpdater;
				// request license
				$objUpdaterLicense = \json_decode( $objThemeUpdater->request($GLOBALS['PCT_THEME_UPDATER']['api_url'].'/license_api.php',$arrParams) );
				if( $objUpdaterLicense->status == 'OK' )
				{
					$arrSession['updater_license'] = $objUpdaterLicense;
					$objSession->set('pct_theme_updater',$arrSession);
				}
			}
		}

		if( $objUpdaterLicense === null || !isset($objUpdaterLicense->status) || $objUpdaterLicense->status != 'OK')
		{
			return '';
		}
		//---

		$arrJobs = array();
		foreach( array('news_order','center_center_to_crop') as $key)
		{
			$arrJobs[$key] = array
			(
				'id' => 'pct_customelements_jobs_'.$key,
				'name' => $key,
				'title' => $GLOBALS['TL_LANG']['tl_maintenance']['pct_themeupdater'][$key][0],
				'description' => $GLOBALS['TL_LANG']['tl_maintenance']['pct_themeupdater'][$key][1],
				'affected' => '',
				'callback' => array(Jobs::class,$key),
			);
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