<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (C) 2005-2013 Leo Feyer
 *
 * @copyright Tim Gatzky 2018, Premium Contao Themes
 * @author  Tim Gatzky <info@tim-gatzky.de>
 * @package  pct_theme_updater
 */

/**
 * Namespace
 */
namespace PCT;

/**
 * Imports 
 */
use Contao\Automator;
use Contao\System;
use Contao\Environment;
use Contao\Backend;
use Contao\Input;
use Contao\Config;
use Contao\Controller;
use Contao\Message;
use Contao\Files;
use Contao\File;
use Contao\StringUtil;
use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\Database;
use Contao\Date;
use Contao\Folder;
use PCT\ThemeUpdater\InstallerHelper;

/**
 * Class file
 * ThemeUpdater
 */
class ThemeUpdater extends \Contao\BackendModule
{
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_pct_theme_updater';

	/**
	 * Template for the breadcrumb
	 * @var string
	 */
	protected $strTemplateBreadcrumb = 'pct_theme_updater_breadcrumb';

	/**
	 * The name of the theme
	 * @var string
	 */
	protected $strTheme = '';

	/**
	 * The session name
	 * @var string
	 */
	protected $strSession = 'pct_theme_updater';


	/**
	 * Generate the module
	 */
	protected function compile()
	{
		\error_reporting(E_ERROR | E_PARSE | E_NOTICE);
		
		// validate
		$blnIsValid = static::validate();
		
		$full_version = ContaoCoreBundle::getVersion();
		$version = substr( $full_version, 0, strrpos($full_version, '.') );
		$rootDir = System::getContainer()->getParameter('kernel.project_dir');
		$uploadPath = Config::get('uploadPath') ?? 'files';

		// check contao version
		$blnAllowed = false;
		if( \version_compare($version, '4.13','==') || \version_compare($version, '5.3','>=') )
		{
			$blnAllowed = true;
		}

		// not supported
		if(Input::get('status') != 'version_conflict' && $blnAllowed === false)
		{
			$this->redirect( Backend::addToUrl('status=version_conflict',true,array('step','action')) );
		}
		//--
		System::loadLanguageFile('pct_theme_updater');
		System::loadLanguageFile('exception');
		System::loadLanguageFile('default');

		// @var object Session
		$objSession = System::getContainer()->get('request_stack')->getSession();
		$arrSession = $objSession->get($this->strSession);
		
		$arrErrors = array();
		$arrParams = array();		
		
		// updater config
		$objConfig = \json_decode($this->request($GLOBALS['PCT_THEME_UPDATER']['config_url']));
		$objConfig->local_version = $this->getThemeVersion();
		//--

		// check client version
		if( Input::get('status') != 'error' && \version_compare(\PCT_THEME_UPDATER,$objConfig->min_client_version,'<') )
		{
			$arrSession['errors'] = array($GLOBALS['TL_LANG']['XPT']['pct_theme_updater']['client_version_conflict']);
			$objSession->set($this->strSession,$arrSession);
			System::getContainer()->get('monolog.logger.contao.error')->info('Current version: '.\PCT_THEME_UPDATER.' Min client version required: '.$objConfig->min_client_version);

			$this->redirect( Backend::addToUrl('status=error',true,array('step','action')) );
		}

		// updater license
		$objUpdaterLicense = $arrSession['updater_license'] ?? null;
		if( isset($arrSession['updater_license']) && \is_string($arrSession['updater_license']) && empty($arrSession['updater_license']) === false)
		{
			$objUpdaterLicense = \json_decode($arrSession['updater_license']);
		}
		//--

		// theme license
		$objLicense = $arrSession['license'] ?? null;
		if( isset($arrSession['license']) && \is_string($arrSession['license']) && empty($arrSession['license']) === false)
		{
			$objLicense = \json_decode($arrSession['license']);
		}
		//--


		$strStatus = Input::get('status');
		
		// template vars
		$strForm = 'pct_theme_updater';
		$this->Template->status = '';
		$this->Template->action = Environment::get('request');
		$this->Template->formId = $strForm;
		$this->Template->content = '';
		$this->Template->breadcrumb = $this->getBreadcrumb(Input::get('status'), Input::get('step'));
		$this->Template->href = $this->getReferer(true);
		$this->Template->title = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']);
		$this->Template->button = $GLOBALS['TL_LANG']['MSC']['backBT'];
		$this->Template->resetUrl = Backend::addToUrl('status=reset');
		$this->Template->messages = Message::generate();
		$this->Template->label_key = $GLOBALS['TL_LANG']['pct_theme_updater']['label_key'] ?? 'License / Order number';
		$this->Template->label_email = $GLOBALS['TL_LANG']['pct_theme_updater']['label_email'] ?? 'Order email address';
		$this->Template->placeholder_license = '';
		$this->Template->placeholder_email = '';
		$this->Template->label_submit = $GLOBALS['TL_LANG']['pct_theme_updater']['label_submit'] ?? 'Submit';
		$this->Template->value_submit = $GLOBALS['TL_LANG']['pct_theme_updater']['value_submit'] ?? 'Submit';
		$this->Template->file_written_response = 'file_written';
		$this->Template->file_target_directory = $GLOBALS['PCT_THEME_UPDATER']['tmpFolder'];
		$this->Template->ajax_action = 'theme_updater_loading'; // just a simple action status message
		$this->Template->test_license = $GLOBALS['PCT_THEME_UPDATER']['test_license'] ?? null;
		$this->Template->license = $objLicense;
		$this->Template->up_to_date = false;	
		$this->Template->language = System::getContainer()->get('request_stack')->getCurrentRequest()->getLocale();
		$this->Template->version_conflict = '';
		$this->Template->contao_version_short = $version;
		$this->Template->contao_version_full = $full_version;

		$blnAjax = false;
		if(Input::get('action') != '' && Environment::get('isAjaxRequest'))
		{
			$blnAjax = true;
		}
		$this->Template->ajax_running = $blnAjax;

		// the theme or module name of this lizence
		$this->strTheme = $objLicense->name ?? '';
		if( isset($objLicense->file->name) )
		{
			$this->strTheme = basename($objLicense->file->name,'.zip');
			$this->Template->theme = $this->strTheme;
		}

		if( isset($GLOBALS['PCT_THEME_UPDATER']['test_product']) && !empty($GLOBALS['PCT_THEME_UPDATER']['test_product']) )
		{
			$this->strTheme = $GLOBALS['PCT_THEME_UPDATER']['test_product'];
		}

		// check if there are updater information for current theme
		$objUpdate = $objConfig->themes->{\strtolower($this->strTheme)} ?? null;
		if( !\in_array($strStatus, array('done','reset')) && ($objUpdate === null || \version_compare($objUpdate->version, $objConfig->local_version,'==') ) )
		{
			$this->Template->up_to_date = true;	
		}

		// check if local version matches the required contao version
		if( $strStatus !== 'error' && \version_compare($objConfig->local_version,'4','<') && \version_compare($version,'4.9','>') )
		{
			#Message::addInfo($GLOBALS['TL_LANG']['XPT']['pct_theme_updater']['theme_compatiblity_conflict']);
			$this->Template->version_conflict = $GLOBALS['TL_LANG']['XPT']['pct_theme_updater']['theme_compatiblity_conflict'];
			#
	#		$error = \sprintf($GLOBALS['TL_LANG']['XPT']['pct_theme_updater']['theme_compatiblity_conflict'],$objConfig->local_version,$version,'4.9');
	#		$arrSession['errors'] = array($error);
	#		$objSession->set($this->strSession,$arrSession);

			// redirect
	#		$this->redirect( Backend::addToUrl('status=error',true,array('step','action')) );
		}


		// check supported contao versions
		if( isset($objUpdate->contao) && !empty($objUpdate->contao) )
		{
			$blnSupported = false;
		
			foreach($objUpdate->contao as $version)
			{
				if( \version_compare($version,$version,'==') )
				{
					$blnSupported = true;
				}
			}
			
			if( $strStatus !== 'error' && $blnSupported === false && !empty($this->strTheme) )
			{
				$error = \sprintf($GLOBALS['TL_LANG']['XPT']['pct_theme_updater']['theme_version_conflict'],$version, \implode(',',$objUpdate->contao));
				$arrSession['errors'] = array($error);
				$objSession->set($this->strSession,$arrSession);
	
				// redirect
				$this->redirect( Backend::addToUrl('status=error',true,array('step','action')) );
			}
		}
		
		
//! status : ""

		
		if( Input::get('status') == '' )
		{
			if( $objLicense === null || $objUpdaterLicense === null || empty($this->strTheme) )
			{
				$this->redirect( Backend::addToUrl('status=enter_theme_license') );
			}

			// reset session but the lisense information
			$objSession->remove($this->strSession);
			$arrSession = array
			(
				'license' => $objLicense,
				'updater_license' => $objUpdaterLicense,
			);
			$objSession->set($this->strSession,$arrSession);
			// back to ready
			$this->redirect( Backend::addToUrl('status=ready',true,array('status','step')) );
			return;
		}

		
//! status : VERSION_CONFLICT


		if(Input::get('status') == 'version_conflict')
		{
			$this->Template->status = 'VERSION_CONFLICT';
			$this->Template->errors = array($GLOBALS['TL_LANG']['XPT']['pct_theme_updater']['version_conflict'] ?: 'Please use the LTS version 4.9');
			return;
		}
		

//! status: VALIDATION: ENTER UPDATER LICENSE


		// check : UPDATER-LICENSE FILE
		#if( $objUpdaterLicense->status != 'OK' && !in_array($strStatus,array('welcome','access_denied','enter_updater_license','enter_theme_license','reset','error','version_conflict')))
		#{
		#	$this->redirect( Backend::addToUrl('status=enter_theme_license',true) );
		#}
		
		if( Input::get('status') == 'enter_updater_license' )
		{
			$this->Template->status = 'ENTER_UPDATER_LICENSE';
			$this->Template->breadcrumb = '';
			
			// reset updater license 
			$objUpdaterLicense = null;

			// theme license has domain errors
			if( isset($objLicense->registration->hasError) )
			{
				$this->Template->errors = array(\sprintf($GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['TEMPLATE']['domainRegistrationError'],Environment::get('host'),$objLicense->key,Environment::get('host')) );
			}
		
			$strLicense = '';
			$objLicenseFile = new File('var/pct_license_themeupdater');
			if( $objLicenseFile->exists() )
			{
				$strLicense = \trim( $objLicenseFile->getContent() ?: '' );
			}
			
			// license has been submitted
			if(Input::post('license') != '' && Input::post('FORM_SUBMIT') == $strForm)
			{
				$strLicense = \trim( Input::post('license') );
			}

			$objThemeLicenseFile = new File('var/pct_license');
			if( $objThemeLicenseFile->exists() )
			{
				$strThemeLicense = \trim( $objThemeLicenseFile->getContent() ?: '' );
			}

			// license has been submitted
			if(Input::post('license_theme') != '' && Input::post('FORM_SUBMIT') == $strForm)
			{
				$strThemeLicense = \trim( Input::post('license_theme') );
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
				// request license
				$objUpdaterLicense = \json_decode( $this->request($GLOBALS['PCT_THEME_UPDATER']['api_url'].'/license_api.php',$arrParams) );
			}
			
			// create license file, if not exists
			if( !$objLicenseFile->exists() && $objUpdaterLicense !== null && $objUpdaterLicense->status == 'OK' )
			{
				$objLicenseFile->write($objUpdaterLicense->key);
				$objLicenseFile->close();
			}
			// create theme license file, if not exists
			if( !$objThemeLicenseFile->exists() && $objUpdaterLicense !== null && $objUpdaterLicense->status == 'OK' )
			{
				$objThemeLicenseFile->write($strThemeLicense);
				$objThemeLicenseFile->close();
			}
	
			// template variables
			$this->Template->strLicense;
			$this->Template->strThemeLicense;
			$this->Template->themeLicenseFileExists = $objThemeLicenseFile->exists();
					
			// redirect to theme license
			if( $objUpdaterLicense !== null && $objUpdaterLicense->status == 'OK' )
			{	
				// update license session
				$arrSession['updater_status'] = $objUpdaterLicense->status;
				$arrSession['updater_license'] = $objUpdaterLicense;
				$objSession->set($this->strSession,$arrSession);

				$this->redirect( Backend::addToUrl('status='.$GLOBALS['PCT_THEME_UPDATER']['routes'][$strStatus],true) );
			}

			// access_denied
			if( $objUpdaterLicense !== null && $objUpdaterLicense->status == 'ACCESS_DENIED' )
			{
				$arrSession['status'] = $objUpdaterLicense->status;
				$arrSession['errors'] = array($objUpdaterLicense->error);
				$arrSession['updater_license'] = $objUpdaterLicense;
				$arrSession['key'] = $strLicense;
				$arrSession['license_type'] = 'theme_updater';
				$objSession->set($this->strSession,$arrSession);
				$this->redirect( Backend::addToUrl('status=access_denied',true) );
			}

			// elapsed
			if( $objUpdaterLicense !== null && $objUpdaterLicense->status == 'ELAPSED' )
			{
				$arrSession['status'] = $objUpdaterLicense->status;
				$arrSession['errors'] = array($objUpdaterLicense->error);
				$arrSession['updater_license'] = $objUpdaterLicense;
				$arrSession['key'] = $strLicense;
				$arrSession['license_type'] = 'theme_updater';
				$objSession->set($this->strSession,$arrSession);
				$this->redirect( Backend::addToUrl('status=error',true) );
			}

			return;
		}


//! status: VALIDATION: ENTER THEME LICENSE


		if( Input::get('status') == 'enter_theme_license' )
		{	
			$this->Template->status = 'ENTER_THEME_LICENSE';
			$this->Template->breadcrumb = '';
			
			// reset any license objects before
			$objLicense = null;

			// check if license file exists and if so, validate the license
			$objLicenseFile = new File('var/pct_license');
			if( $objLicenseFile->exists() )
			{
				$strLicense = \trim( $objLicenseFile->getContent() ?: '' );
				$arrParams = array
				(
					'domain'	=> StringUtil::decodeEntities( Environment::get('host') ),
					'key'		=> $strLicense,
				);

				// validation
				$objLicense = \json_decode( $this->request($GLOBALS['PCT_THEME_UPDATER']['api_url'].'/license_api.php',$arrParams) );
			
				if( $objLicense !== null && $objLicense->status == 'OK' )
				{
					$arrParams = array
					(
						'key'   => trim($strLicense),
						'email'  => trim($objLicense->email),
						'domain' => StringUtil::decodeEntities( Environment::get('host') ),
					);
	
					if(Input::post('product') != '')
					{
						$arrParams['product'] = Input::post('product');
					}

					// point to a product
					if( isset($GLOBALS['PCT_THEME_UPDATER']['product']) && empty($GLOBALS['PCT_THEME_UPDATER']['product']) === false )
					{
						$product = strtolower($GLOBALS['PCT_THEME_UPDATER']['product']);
						if( $product == 'eclipsex' )
						{
							$arrParams['product'] = $GLOBALS['PCT_THEME_UPDATER']['THEMES']['eclipseX']['product_id'] ?? 158;
						}
						if( $product == 'eclipsex_cc' )
						{
							$arrParams['product'] = $GLOBALS['PCT_THEME_UPDATER']['THEMES']['eclipseX_cc']['product_id'] ?? 163;
						}
					}
				
					$objLicense = \json_decode( $this->request($GLOBALS['PCT_THEME_UPDATER']['api_url'].'/api.php',$arrParams) );
				}
			}
			
			// check license from formular
			if(Input::post('license') != '' && Input::post('email') != '' && Input::post('FORM_SUBMIT') == $strForm)
			{
				$arrParams = array
				(
					'key'   => trim(Input::post('license')),
					'email'  => trim(Input::post('email')),
					'domain' => StringUtil::decodeEntities( Environment::get('host') ),
				);

				if(Input::post('product') != '')
				{
					$arrParams['product'] = Input::post('product');
				}

				// point to a product
				if( isset($GLOBALS['PCT_THEME_UPDATER']['product']) && empty($GLOBALS['PCT_THEME_UPDATER']['product']) === false )
				{
					$product = strtolower($GLOBALS['PCT_THEME_UPDATER']['product']);
					if( $product == 'eclipsex' )
					{
						$arrParams['product'] = $GLOBALS['PCT_THEME_UPDATER']['THEMES']['eclipseX']['product_id'] ?? 158;
					}
					if( $product == 'eclipsex_cc' )
					{
						$arrParams['product'] = $GLOBALS['PCT_THEME_UPDATER']['THEMES']['eclipseX_cc']['product_id'] ?? 163;
					}
				}
			
				$objLicense = \json_decode( $this->request($GLOBALS['PCT_THEME_UPDATER']['api_url'].'/api.php',$arrParams) );
			}
			
			// license is ok
			if( $objLicense !== null && $objLicense->status == 'OK' )
			{
				// store the api response in the session
				$arrSession['status'] = $objLicense->status;
				$arrSession['license'] = $objLicense;
				$objSession->set($this->strSession,$arrSession);
				
				$objThemeLicenseFile = new File('var/pct_license');
				if( !$objLicenseFile->exists() )
				{
					$objThemeLicenseFile->write($objLicense->key);
					$objThemeLicenseFile->close();
				}

				// redirect to the beginning
				#$this->redirect( Backend::addToUrl('status=ready',true) );
				$this->redirect( Backend::addToUrl('status='.$GLOBALS['PCT_THEME_UPDATER']['routes'][$strStatus],true) );
			}

			// access_denied
			if( $objLicense !== null && $objLicense->status == 'ACCESS_DENIED' )
			{
				$arrSession['status'] = $objLicense->status;
				$arrSession['errors'] = array($objLicense->error);
				$arrSession['key'] = $strLicense;
				$arrSession['license'] = $objLicense;
				$arrSession['license_type'] = 'theme';
				$objSession->set($this->strSession,$arrSession);
				$this->redirect( Backend::addToUrl('status=access_denied',true) );
			}

			return;
		}

	
//! status : DONE COMPLETED


		if(Input::get('status') == 'done')
		{
			$this->Template->status = 'DONE';
			$this->Template->breadcrumb = '';
			
			// no update information for installed product
			if( $objUpdate === null )
			{
				$this->Template->errors = array('No update information found for product: '.$this->strTheme);
			}
			
			// @var object The whole updater config object
			$this->Template->Config = $objConfig;
			$this->Template->ThemeConfig = $objUpdate;
			$this->Template->changelog_txt = $objUpdate->changelog;
			$this->Template->local_version = $objConfig->local_version;
			$this->Template->live_version = $objUpdate->version;

			return;
		}


//! status : RESET


		// clear the session on status reset
		if(Input::get('status') == 'reset')
		{
			$objLicense = null;
			$objLicenseUpdater = null;
			$objSession->remove( $this->strSession );
			
			$objFile = new File('var/pct_license');
			if( $objFile->exists() )
			{
				$objFile->delete();
			}
			$objFile = new File('var/pct_license_themeupdater');
			if( $objFile->exists() )
			{
				$objFile->delete();
			}

			// redirect to the beginning
			$this->redirect( Backend::addToUrl('do=pct_theme_updater',true,array('status','step')) );
		}
				


//! status : ERROR


		if(Input::get('status') == 'error')
		{
			$this->Template->status = 'ERROR';
			$this->Template->breadcrumb = '';
			$this->Template->errors = $arrSession['errors'];
			return;
		}


//! status : ACCESS_DENIED


		if( Input::get('status') == 'access_denied' )
		{
			$this->Template->status = 'ACCESS_DENIED';
			#$this->Template->errors = $arrSession['errors'];

			// log errers
			System::getContainer()->get('monolog.logger.contao.error')->info( \implode(',',$arrSession['errors']) );

			return;
		}


//! status : MANUAL ADJUSTMENT


		if( $strStatus == 'manual_adjustment' )
		{
			$this->Template->status = 'MANUAL_ADJUSTMENT';
			// form id
			$strForm = 'theme_updater_tasks';
			$this->Template->formId = $strForm;
			// @var object Theme related config object
			$objUpdate = $objConfig->themes->{\strtolower($this->strTheme)};
			// @var object The whole updater config object
			$this->Template->Config = $objConfig;
			$this->Template->ThemeConfig = $objUpdate;
			// @var object The current backend user
			$objUser = BackendUser::getInstance();
			// @var the tasks to be done
			$objTasks = $objUpdate->tasks;
			// check if user has checked any tasks
			$objLogFile = new File( $GLOBALS['PCT_THEME_UPDATER']['logFile'] );
			
			$arrLogs = array();
			$arrLogsFile = array();

			if( $objLogFile->exists() )
			{
				$arrLogs = \json_decode( $objLogFile->getContent(), true );		
				$arrLogsFile = $arrLogs;

				// get last log
				if( count($arrLogs) > 1 )
				{
					$keys = \array_keys($arrLogs) ?? array();
					$k = end( $keys );
					$tmp = $arrLogs;
					$arrLogs = array();
					$arrLogs[$k] = $tmp[$k];
					unset($tmp);
					unset($k);
				}
			}
			
			// get the task status from the log file
			$arrTaskLog = array();
			
			$arrTasksDone = array();
			foreach($arrLogs as $log)
			{
				if( !isset($log['tasks']) || \is_array($log['tasks']) === false )
				{
					continue;
				}
				foreach($log['tasks'] as $task)
				{
					$arrTaskLog[ $task['id'] ] = $task;
					if( $task['status'] == 'done')
					{
						$arrTasksDone[] = $task['id'];
					}
				}
			}
			
			// tasks
			$intTasks = 0;
			foreach($objTasks as $k => $category)
			{
				$category->title = $GLOBALS['TL_LANG']['PCT_THEME_UPDATER']['CATEGORIES'][$k] ?? $k;
				
				$objSubTasks = $category->tasks ?? array();
				foreach($objSubTasks as $i => $task)
				{
					// skip tasked when current theme version is higher than task version
					if( isset($task->version) && empty($task->version) === false && \version_compare($task->version, $objConfig->local_version,'<=') )
					{
						unset($objSubTasks[$i]);
					}
						
					if( \in_array($task->id, $arrTasksDone) )
					{
						$task->checked = true;
						$task->user = $arrTaskLog[ $task->id ]['user'];
						$task->tstamp = $arrTaskLog[ $task->id ]['tstamp'];
					}

					// fetch documentation template
					if( isset($task->template) && empty($task->template) === false )
					{
						$strTemplate = $this->request( $GLOBALS['PCT_THEME_UPDATER']['updater_api_url'], array('template'=>$task->template,'key'=>$objUpdaterLicense->key) );
						$task->documentation = $strTemplate;
					}
				}
				
				if( empty($objSubTasks) )
				{
					unset($objTasks->{$k});
					continue;
				}

				// update the tasks of the category
				$category->tasks = $objSubTasks;

				// count number of tasks
				$intTasks += count($objSubTasks);			
			}
			
			// write log when checking in or when done
			if( Input::post('FORM_SUBMIT') == $strForm && (Input::post('commit') !== null || Input::post('done') !== null) )
			{
				$intTime = time();
				
				$strKey = Date::parse('Y-m-d h:i:s',$intTime); 
				
				// build log data
				$arrData = array
				(
					'tstamp' 	=> $intTime,
					'date' 		=> $strKey,
					'user'	 	=> $objUser->id,
				);
			
				$arrTasks = Input::post('tasks') ?? array();
				foreach($objTasks as $k => $category)
				{
					$objSubTasks = $category->tasks ?? array();
					foreach($objSubTasks as $task)
					{
						// skip not checked tasks
						if( !\in_array( $task->id, $arrTasks ) )
						{
							continue;
						}
						
						$tmp = array
						(
							'id' => $task->id,
							'tstamp' => $intTime,
							'user' => $objUser->id,
						);
						if( isset($arrTasks[$task->id]) && empty($arrTasks[$task->id]) === false )
						{
							$tmp['status'] = 'done';
						}

						$arrData['tasks'][ $task->id ] = $tmp;
						unset($tmp);
					}
				}

				// flag final
				if( Input::post('done') !== null )
				{
					$arData['final'] = 'true';
				}

				// append new log data
				$arrLogsFile[$strKey] = $arrData;
				
				// write log file
				$objLogFile->write( \json_encode( $arrLogsFile ) );
				$objLogFile->close();

				unset($arrData);
				unset($arrLogs);

				// redirect on done
				if( Input::post('done') !== null )
				{			
					// delete demo_files
					if( Input::post('delete_demo_files') !== null )
					{
						$objFolder = new Folder('templates/demo_installer');
						if( $objFolder->isEmpty() === false )
						{
							$objFolder->purge();
						}
						$objFolder = new Folder('files/cto_layout/img/delete_this');
						if( $objFolder->isEmpty() === false )
						{
							$objFolder->purge();
						}
					}
					
					// remove version file
					$objVersionFile = new File('var/pct_theme_version');
					if ( $objVersionFile->exists() )
					{
						$objVersionFile->delete();
					}
					
					$this->redirect( Backend::addToUrl('do=pct_theme_updater',true,array('step','status')) );
				}

				// reload to flush cache
				Controller::reload();

			}

			if( empty( array_filter( (array)$objTasks) ) )
			{
				$objTasks = null;
			}
			
			$this->Template->tasks = $objTasks;
			$this->Template->numberOfTasks = $intTasks;
			$this->Template->changelog_txt = $objUpdate->changelog;
			$this->Template->live_version = $objUpdate->version;
			
			return;
		}


//! status: INSTALLATION | no step -> reset


		if(Input::get('status') == 'installation' && Input::get('step') == '')
		{
			// redirect to the beginning
			$this->redirect( Backend::addToUrl('status=reset',true,array('step')) );
		}


		//! status: INSTALLATION | STEP 1.0: Unpack the zip
		if(Input::get('status') == 'installation' && Input::get('step') == 'unzip')
		{
			// check if file still exists
			if(empty($arrSession['file']) || !file_exists($rootDir.'/'.$arrSession['file']))
			{
				$this->Template->status = 'FILE_NOT_EXISTS';

				// log
				System::getContainer()->get('monolog.logger.contao.error')->info( 'Theme Installer: File not found' );

				// track error				
				$arrSession['errors'] = array('File not found');
				$objSession->set($this->strSession,$arrSession);

				// redirect
				$this->redirect( Backend::addToUrl('status=error',true,array('step','action')) );

				return;
			}

			$this->Template->status = 'INSTALLATION';
			$this->Template->step = 'UNZIP';
			
			$objFile = new File($arrSession['file'],true);
			$this->Template->file = $objFile;

			// check the file size
			#if($objFile->__get('size') < 30000)
			#{
			# // log that file is too small
			# System::log('The file '.$objFile->path.' is too small. Please retry or contact us.',__METHOD__,TL_ERROR);
			#
			# $this->redirect( Backend::addToUrl('status=reset',true,array('step')) );
			# return;
			#}

			// the target folder to extract to
			$strTargetDir = $GLOBALS['PCT_THEME_UPDATER']['tmpFolder'].'/'.basename($arrSession['file'], ".zip").'_zip';

			if(Input::get('action') == 'run')
			{
				// extract zip
				$objZip = new \ZipArchive;
				if($objZip->open($rootDir.'/'.$objFile->path) === true && !isset($arrSession['unzipped']))
				{
					$objZip->extractTo($rootDir.'/'.$strTargetDir);
					$objZip->close();

					// flag that the zip file has been extracted
					$arrSession['unzipped'] = true;
					$objSession->set($this->strSession,$arrSession);

					// ajax done
					die('Zip extracted to: '.$strTargetDir);
				}
				// zip already extracted
				elseif($arrSession['unzipped'] && is_dir($rootDir.'/'.$strTargetDir))
				{
					// ajax done
					die('Zip extracted to: '.$strTargetDir);
				}
				// extraction failed
				else
				{
					$log = sprintf($GLOBALS['TL_LANG']['XPT']['pct_theme_updater']['unzip_error'],$arrSession['file']);
					System::getContainer()->get('monolog.logger.contao.error')->info( $log );
				}

				// redirect to the beginning
				#$this->redirect( Backend::addToUrl('status=installation&step=copy_files') );
			}

			return;
		}
		//! status: INSTALLATION | STEP 2.0: Copy files
		else if(Input::get('status') == 'installation' && Input::get('step') == 'copy_files')
		{
			$this->Template->status = 'INSTALLATION';
			$this->Template->step = 'COPY_FILES';

			// the target folder to extract to
			$strTargetDir = $GLOBALS['PCT_THEME_UPDATER']['tmpFolder'].'/'.basename($arrSession['file'], ".zip").'_zip';
			$strFolder = $strTargetDir; #$strTargetDir.'/'.basename($arrSession['file'], ".zip");


			if(Input::get('action') == 'run' && is_dir($rootDir.'/'.$strFolder))
			{
				// backup an existing customize.css
				$blnCustomizeCss = false;
				if(file_exists($rootDir.'/'.$uploadPath.'/cto_layout/css/customize.css'))
				{
					if (Files::getInstance()->copy($uploadPath.'/cto_layout/css/customize.css',$GLOBALS['PCT_THEME_UPDATER']['tmpFolder'].'/customize.css') )
					{
						$blnCustomizeCss = true;
					}
					
				}
				$blnCustomizeJs = false;
				if(file_exists($rootDir.'/'.$uploadPath.'/cto_layout/scripts/customize.js'))
				{
					if( Files::getInstance()->copy($uploadPath.'/cto_layout/scripts/customize.js',$GLOBALS['PCT_THEME_UPDATER']['tmpFolder'].'/customize.js') )
					{
						$blnCustomizeJs = true;
					}
				}

				// favicon folder
				$blnFavicon = false;
				if(is_dir($rootDir.'/'.$uploadPath.'/cto_layout/img/favicon'))
				{
					$folder = new Folder( $uploadPath.'/cto_layout/img/favicon' );
					if( $folder->copyTo( $GLOBALS['PCT_THEME_UPDATER']['tmpFolder'].'/favicon') )
					{
						$blnFavicon = true;
					}
				}

				$objFiles = Files::getInstance();
				$arrIgnore = array('.ds_store','customize.css','customize.js');

				// folder to copy
				$arrFolders = Folder::scan( $rootDir.'/'.$strFolder.'/upload' );

				// clear /templates folders in "pct_"-modules
				$arrModulesFolders = Folder::scan( $rootDir.'/'.$strFolder.'/upload/system/modules' );
				foreach($arrModulesFolders as $name)
				{
					$objFiles->rrdir('system/modules/'.$name,true);
				}
				
				// skip pct_theme_installer when installed
				$bundles = array_keys( System::getContainer()->getParameter('kernel.bundles') );
				if( \in_array('pct_theme_installer',$bundles) )
				{
					$skip = new File('system/modules/pct_theme_installer/.skip');
					$skip->write('');
					$skip->close();
				}

				foreach($arrFolders as $f)
				{
					if(in_array(strtolower($f), $arrIgnore))
					{
						continue;
					}

					//-- copy the /upload/files/ folder
					$strSource = $strFolder.'/upload/'.$f;
					$strDestination = $f;
					if($f == 'files')
					{
						$strDestination = $uploadPath ?: 'files';
					}

					$objFiles->rcopy($strSource,$strDestination);
					
					#if($objFiles->rcopy($strSource,$strDestination) !== true)
					#{
					#	$arrErrors[] = 'Copy "'.$strSource.'" to "'.$strDestination.'" failed';
					#}
				}

				// reinstall the customize.css
				if($blnCustomizeCss)
				{
					Files::getInstance()->copy($GLOBALS['PCT_THEME_UPDATER']['tmpFolder'].'/customize.css',$uploadPath.'/cto_layout/css/customize.css');
				}
				if($blnCustomizeJs)
				{
					Files::getInstance()->copy($GLOBALS['PCT_THEME_UPDATER']['tmpFolder'].'/customize.js',$uploadPath.'/cto_layout/scripts/customize.js');
				}

				// favicon folder
				if($blnFavicon)
				{
					$tmp_folder = new Folder( $GLOBALS['PCT_THEME_UPDATER']['tmpFolder'].'/favicon' );
					$folder = new Folder( $uploadPath.'/cto_layout/img/favicon' );
					$folder->purge();
					$tmp_folder->copyTo( $folder->__get('path') );
				}
				
				// log errors
				if(count($arrErrors) > 0)
				{
					System::getContainer()->get('monolog.logger.contao.error')->info( 'Theme Updater: Copy files: '.implode(', ', $arrErrors) );

					// track error				
					$arrSession['errors'] = $arrErrors;
					$objSession->set($this->strSession,$arrSession);
					if(!$blnAjax)
					{
						$this->redirect( Backend::addToUrl('status=error',true,array('step','action')) );
					}
					else
					{
						die('Theme Installer: Copy files: '.implode(', ', $arrErrors));
					}
				}
				// no errors
				else
				{
					// write log
					System::getContainer()->get('monolog.logger.contao.cron')->info( sprintf($GLOBALS['TL_LANG']['pct_theme_updater']['copy_files_completed'],$arrSession['file']) );

					// ajax done
					if($blnAjax)
					{
						die('Coping files completed');
					}
				}
			}
			else
			{
				#die('Zip folder '.$strTargetDir.'/'.$strFolder.' does not exist or is not a directory');
			}

			return ;
		}
		//! status: INSTALLATION | STEP 3.0 : Clear internal caches
		else if(Input::get('status') == 'installation' && Input::get('step') == 'clear_cache')
		{
			$this->Template->status = 'INSTALLATION';
			$this->Template->step = 'CLEAR_CACHE';

			if(Input::get('action') == 'run')
			{
				#$objContainer = System::getContainer();
				#$strCacheDir = StringUtil::stripRootDir($objContainer->getParameter('kernel.cache_dir'));
				
				// @var object Contao\Automator
				$objAutomator = new Automator;
				// generate symlinks to /assets, /files, /system
				$objAutomator->generateSymlinks();
				
				// purge the whole folder
				Files::getInstance()->rrdir('var/cache',true);
				
				die('Symlinks created and Symphony cache cleared');
			}
			
			return;
		}

		//! status: INSTALLATION | STEP 4.0 : DB Update for modules
		else if(Input::get('status') == 'installation' && Input::get('step') == 'db_update_modules')
		{
			$this->Template->status = 'INSTALLATION';
			$this->Template->step = 'DB_UPDATE_MODULES';
			
			$arrErrors = array();
			$arrStatements = array();

			try
			{
				$objInstallHelper = new InstallerHelper;
				$arrSQL = $objInstallHelper->sqlCompileCommandsCallback(array());

				if(!empty($arrSQL) && is_array($arrSQL))
				{
					foreach($arrSQL as $operation => $sql)
					{
						// never run operations
						if(in_array($operation, array('DELETE','DROP','ALTER_DROP','ALTER_CHANGE')))
						{
							continue;
						}
						
						foreach($sql as $statement)
						{
							if( \strpos($statement,'ADD KEY') || \strpos($statement,'ADD UNIQUE KEY') )
							{
								continue;
							}	
							// track the statements executed
							$arrStatements[] = $statement;
							// execute
							$this->Database->query( $statement );
						}
					}
				}		
			}
			catch(\Exception $e)
			{
				$arrErrors[] = $e->getMessage();
			}

			// update database
			try
			{
				$objDatabase = Database::getInstance();

				// update tl_module [html] templates
				$query = "UPDATE `tl_module` SET `customTpl` = 'mod_html_phone' WHERE `customTpl` = 'mod_phone';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_email' WHERE `customTpl` = 'mod_email';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_mobilenav_trigger' WHERE `customTpl` = 'mod_mobilenav_trigger';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_socials' WHERE `customTpl` = 'mod_socials';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_offcanvas_top' WHERE `customTpl` = 'mod_offcanvas_top';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_offcanvas_top_trigger' WHERE `customTpl` = 'mod_offcanvas_top_trigger';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_search_trigger' WHERE `customTpl` = 'mod_search_trigger';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_smartmenu_trigger' WHERE `customTpl` = 'mod_smartmenu_trigger';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_totop_link' WHERE `customTpl` = 'mod_totop_link';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_cookiebar' WHERE `customTpl` = 'mod_cookiebar';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_cookiebar_medium' WHERE `customTpl` = 'mod_cookiebar_medium';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_cookiebar_slim' WHERE `customTpl` = 'mod_cookiebar_slim';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_customcatalog_view_switch' WHERE `customTpl` = 'mod_customcatalog_view_switch';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_mobilenav_trigger',`html`='' WHERE `html` LIKE '%layout/mod_mobilenav_trigger%';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_totop_link',`html`='' WHERE `html` LIKE '%layout/mod_totop_link%';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_offcanvas_top_trigger',`html`='' WHERE `html` LIKE '%layout/mod_offcanvas_top_trigger%';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_search_trigger',`html`='' WHERE `html` LIKE '%layout/mod_search_trigger%';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_smartmenu_trigger',`html`='' WHERE `html` LIKE '%layout/mod_smartmenu_trigger%';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_mobilenav_trigger',`html`='' WHERE `html` LIKE '%layout/mod_html_mobilenav_trigger%';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_totop_link',`html`='' WHERE `html` LIKE '%layout/mod_html_totop_link%';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_offcanvas_top_trigger',`html`='' WHERE `html` LIKE '%layout/mod_html_offcanvas_top_trigger%';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_search_trigger',`html`='' WHERE `html` LIKE '%layout/mod_html_search_trigger%';
				UPDATE `tl_module` SET `customTpl` = 'mod_html_smartmenu_trigger',`html`='' WHERE `html` LIKE '%layout/mod_html_smartmenu_trigger%';";
				foreach( array_filter( explode(';', $query) ) as $stmt )
				{
					$objDatabase->query($stmt);
				}

				// update tl_module [newslist,eventlist teaser] templates
				$query = "UPDATE `tl_module` SET `customTpl` = 'mod_eventlist_teaser_v1' WHERE `customTpl` = 'mod_eventteaser_v1';
				UPDATE `tl_module` SET `customTpl` = 'mod_newslist_teaser' WHERE `customTpl` = 'mod_newsteaser';
				UPDATE `tl_module` SET `customTpl` = 'mod_newslist_teaser_v6' WHERE `customTpl` = 'mod_newsteaser_v6';";
				foreach( array_filter( explode(';', $query) ) as $stmt )
				{
					$objDatabase->query($stmt);
				}
				
				// update tl_module [mod_navigation_] templates
				$query = "UPDATE `tl_module` SET `customTpl` = 'mod_navigation_mobile_vertical' WHERE `customTpl` = 'mod_navigation_mobile';";
				foreach( array_filter( explode(';', $query) ) as $stmt )
				{
					$objDatabase->query($stmt);
				}
				
				// update tl_content [accordion] templates
				$query = "UPDATE `tl_content` SET `customTpl` = 'ce_accordionStart_v2' WHERE `customTpl` = 'ce_accordion_v2';
				UPDATE `tl_content` SET `customTpl` = 'ce_accordionSingle_v2' WHERE `customTpl` = 'ce_accordion_single_v2';";
				foreach( array_filter( explode(';', $query) ) as $stmt )
				{
					$objDatabase->query($stmt);
				}
				
				// update tl_form_field [text] templates
				$query = "UPDATE `tl_form_field` SET `customTpl` = 'form_text_datepicker_short' WHERE `customTpl` = 'form_textfield_datepicker_short';
				UPDATE `tl_form_field` SET `customTpl` = 'form_text_datepicker' WHERE `customTpl` = 'form_textfield_datepicker';
				UPDATE `tl_form_field` SET `customTpl` = 'form_text_floatlabel' WHERE `customTpl` = 'form_textfield_floatlabel';
				UPDATE `tl_form_field` SET `customTpl` = 'form_text_timepicker' WHERE `customTpl` = 'form_textfield_timepicker';
				UPDATE `tl_form_field` SET `customTpl` = 'form_text' WHERE `customTpl` = 'form_textfield';";
				foreach( array_filter( explode(';', $query) ) as $stmt )
				{
					$objDatabase->query($stmt);
				}

				// update tl_page: remove font-icon from cssClass
				$objResult = $objDatabase->prepare("SELECT * FROM tl_page WHERE addFontIcon=1 AND cssClass!=''")->execute();
				while( $objResult->next() )
				{
					$row = $objResult->row();
					$cssClass = \str_replace($row['fontIcon'],' ', $row['cssClass']);
					$cssClass = \implode(' ', array_filter( \explode(' ', $cssClass)) );
					$objDatabase->prepare("UPDATE tl_page %s WHERE id=?")->set( array('cssClass' => $cssClass ?? '') )->execute($objResult->id);
				}
			}
			catch(\Exception $e)
			{
				$arrErrors[] = $e->getMessage();
			}
			
			// template updates
			try
			{
				// rename templates/layout folder
				$rootDir = System::getContainer()->getParameter('kernel.project_dir');

				if( \is_dir($rootDir.'/templates/layout') )
				{
					$objFolder = new Folder('templates/layout');
					$objFolder->renameTo('templates/layout_backup');
				}

				// rename be_tinyMCE to be_tinyMCE_backup.html5
				if( \file_exists($rootDir.'/templates/be_tinyMCE.html5') )
				{
					$objFile = new File('templates/be_tinyMCE.html5');
					$objFile->renameTo('templates/be_tinyMCE_backup.html5');
				}

				// copy /templates/set_, import_ templates to /templates/theme_updater_backup
				if( \is_dir($rootDir.'/templates/theme_updater_backup') === false )
				{
					$objFolder = new Folder('templates/theme_updater_backup');
				}

				$arrFiles = Folder::scan( $rootDir.'/templates' ) ?? array();
				foreach($arrFiles as $file)
				{
					// set_, import_ templates
					if( \is_dir($rootDir.'/templates/'.$file) === false && ( \strpos($file,'set_') === 0 || \strpos($file,'import_') === 0) )
					{
						$objFile = new File( 'templates/'.$file );
						$objFile->copyTo( 'templates/theme_updater_backup/'.$file );
						$objFile->delete();
					}
					// demo_ templates
					else if( \is_dir($rootDir.'/templates/'.$file) === false && \strpos($file,'demo_') === 0 )
					{
						$objFile = new File( 'templates/'.$file );
						$objFile->delete();
					}
				}

				// rename fe_page fe_page_backup_themeupdater.html5
				if( \file_exists($rootDir.'/templates/fe_page.html5') )
				{
					$objFile = new File('templates/fe_page.html5');
					$objFile->renameTo('templates/fe_page_backup_themeupdater.html5');
				}
				// if update version is EX5 or smaller, copy fe_page legacy template to templates folder
				$file = 'system/modules/pct_theme_templates/deprecated/theme/fe_page.html5';
				if( \version_compare($objConfig->local_version,'6.0','<') && \file_exists($rootDir.'/'.$file) )
				{
					$objFile = new File( $file );
					$objFile->copyTo( 'templates/fe_page.html5' );
				}

			}
			catch(\Exception $e)
			{
				$arrErrors[] = $e->getMessage();
			}
			
			// log errors and redirect
			if(count($arrErrors) > 0)
			{
				System::getContainer()->get('monolog.logger.contao.error')->info( 'Theme Installer: Database update returned errors: '.implode(', ', $arrErrors) );

				// track error				
				$arrSession['errors'] = $arrErrors;
				$objSession->set($this->strSession,$arrSession);

				$this->redirect( Backend::addToUrl('status=error',true,array('step','action')) );
			}

			return;
		}


//! status: FILE_LOADED ... FILE_CREATED


		// file loaded
		if($arrSession['status'] == 'FILE_CREATED' && !empty($arrSession['file']))
		{
			// check if file still exists
			if(!file_exists($rootDir.'/'.$arrSession['file']))
			{
				$this->Template->status = 'FILE_NOT_EXISTS';

				// log
				System::getContainer()->get('monolog.logger.contao.error')->info( 'Theme Installer: File not found or file could not be created' );

				// track error				
				$arrSession['errors'] = array('File not found or file could not be created');
				$objSession->set($this->strSession,$arrSession);
				
				// redirect
				$this->redirect( Backend::addToUrl('status=error',true,array('step','action')) );

				return;
			}


			$this->Template->status = 'FILE_EXISTS';

			$objFile = new File($arrSession['file'],true);
			$this->Template->file = $objFile;

			// set file path
			$this->strFile = $objFile->path;

			// redirect to step: 1 (unzipping) of the installation
			$this->redirect( Backend::addToUrl('status=installation') );
		}


//! status: READY, waiting for GO


		if(Input::get('status') == 'ready' && $objLicense->status == 'OK')
		{
			$this->Template->status = 'READY';
			$this->Template->license = $objLicense;

			$objUpdate = $objConfig->themes->{\strtolower($this->strTheme)};
			
			// min memory_limit
			$arrErrors = array();
			if( (int)ini_get('memory_limit') < 512 && (int)ini_get('memory_limit') > 0)
			{
				$arrErrors[] = \sprintf($GLOBALS['TL_LANG']['XPT']['pct_theme_updater']['memory_limit'],ini_get('memory_limit')) ?: 'Min. required memory_limit is 512M';
			}

			// registration error
			if( isset($objLicense->registration->hasError) )
			{
				$this->Template->hasRegistrationError = true;
			}

			// get the installed theme version from version file or changelog.txt
			$strLocalVersion = $objConfig->local_version;
			
			if( empty($strLocalVersion) )
			{
				$arrErrors[] = $GLOBALS['TL_LANG']['XPT']['pct_theme_updater']['changelog_not_found'];
			}
			$this->Template->errors = $arrErrors;
			
			$this->Template->theme = $this->strTheme;
			$this->Template->local_version = $strLocalVersion ;
			$this->Template->live_version = $objUpdate->version;
			$this->Template->changelog_txt = $objUpdate->changelog;
			
			if( isset($objUpdate->release) )
			{
				$objDate = new Date($objUpdate->release,'Y-m-d');
				$this->Template->release_date = \Contao\Date::parse('d.m.Y',$objDate->tstamp);
			}

			if(Input::post('install') != '' && Input::post('FORM_SUBMIT') == $strForm)
			{
				$this->redirect( Backend::addToUrl('status=loading',true) );
			}

			return;
		}


//! status: LICENSE = OK -> LOADING... and FILE CREATION


		// if all went good and the license etc. is all valid, we get an secured hash and download will be available
		if(Input::get('status') == 'loading' && $objLicense->status == 'OK' && !empty($objLicense->hash))
		{
			$this->Template->status = 'LOADING';
			$this->Template->license = $objLicense;
			$arrErrors = array();
			

			// coming from ajax request
			if(Input::get('action') == 'run')
			{
				$arrParams['email'] = $objLicense->email;
				$arrParams['key'] = $objLicense->key;
				$arrParams['hash'] = $objLicense->hash;
				$arrParams['domain'] = $objLicense->domain;
				$arrParams['sendToAjax'] = 1;
				$arrParams['product'] = $objLicense->file->id;

				$strFileRequest = html_entity_decode( $GLOBALS['PCT_THEME_UPDATER']['api_url'].'/api.php?'.http_build_query($arrParams) );
				try
				{
					$curl = curl_init();
					curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($curl, CURLOPT_URL, $strFileRequest);
					curl_setopt($curl, CURLOPT_HEADER, 0);
					curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
					curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		
					$strFileResponse = curl_exec($curl);
					curl_close($curl);
					unset($curl);
					
					// response is a json object and not the file content
					$_test = json_decode($strFileResponse);
					
					if(json_last_error() === JSON_ERROR_NONE)
					{
						$objResponse = json_decode($strFileResponse);
						$arrErrors[] = $objResponse->error;
						// log
						//System::log('Theme Installer: '. $objResponse->error,__METHOD__,TL_ERROR);
					}
					else if(!empty($strFileResponse))
					{
						$objFile = new File($GLOBALS['PCT_THEME_UPDATER']['tmpFolder'].'/'.$objLicense->file->name);
						$objFile->write( $strFileResponse );
						$objFile->close();

						$arrSession['status'] = 'FILE_CREATED';
						$arrSession['file'] = $objFile->path;
						$objSession->set($this->strSession,$arrSession);

						// tell ajax that the file has been written
						die($this->Template->file_written_response);

						#// flush post and make session active
						#$this->reload();
					}
				}
				catch(\Exception $e)
				{
					$arrErrors[] = $e->getMessage();
				}
			}

			// log errors and redirect to error page
			if(count($arrErrors) > 0)
			{
				System::getContainer()->get('monolog.logger.contao.error')->info( 'Theme Updater: '.implode(', ', $arrErrors) );

				// track error				
				$arrSession['errors'] = $arrErrors;
				$objSession->set($this->strSession,$arrSession);
				
				$this->redirect( Backend::addToUrl('status=error',true,array('step','action')) );
			}

			return;
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
			$objScripts = new BackendTemplate('be_js_pct_theme_updater');

			$arrTexts = array
			(
				'hallo' => 'welt',
			);
			$objScripts->texts = json_encode($arrTexts);
			$objTemplate->javascripts .= $objScripts->parse();
		}
	}


	/**
	 * Generate a breadcrumb
	 */
//! Breadcrumb
	public function getBreadcrumb($strStatus='',$strStep='')
	{
		$strCurrent = $strStatus.($strStep != '' ? '.'.$strStep : '');

		$arrItems = array();
		$i = 0;

		$objSession = System::getContainer()->get('request_stack')->getSession();
		$arrSession = $objSession->get($this->strSession);
		
		// store the processed steps
		if( !isset($arrSession['BREADCRUMB']['completed']) || !is_array($arrSession['BREADCRUMB']['completed']))
		{
			$arrSession['BREADCRUMB']['completed'] = array();
		}

		foreach($GLOBALS['PCT_THEME_UPDATER']['breadcrumb_steps'] as $k => $data)
		{
			$status = strtolower($k);

			// css class
			$class = array('item',$status);
			if( isset($data['protected']) && $data['protected'])
			{
				$class[] = 'hidden';
			}

			($i%2 == 0 ? $class[] = 'even' : $class[] = 'odd');
			($i == 0 ? $class[] = 'first' : '');
			($i == count($GLOBALS['PCT_THEME_UPDATER']['breadcrumb_steps'] ?? array() ) - 1 ? $class[] = 'last' : '');

			if( !isset($data['label']) || empty($data['label']) )
			{
				$data['label'] = $k;
			}

			// title
			if( !isset($data['title']) || empty($data['title']) )
			{
				$data['title'] = $data['label'];
			}

			$avoid_complete =  $data['avoid_complete'] ?? false;

			// active
			if($strCurrent == $status && $avoid_complete === false )
			{
				$data['isActive'] = true;
				$class[] = 'tl_green';
				$class[] = 'active';

				$arrSession['BREADCRUMB']['completed'][$k] = true;
			}

			$data['completed'] = false;
			$data['isActive'] = false;

			// completed
			if( isset($arrSession['BREADCRUMB']['completed'][$k]) && $arrSession['BREADCRUMB']['completed'][$k] === true && $strCurrent != $status)
			{
				$data['completed'] = true;
				$class[] = 'completed';
			}

			// sill waiting
			if(!$data['isActive'] && !$data['completed'])
			{
				$data['pending'] = true;
				$class[] = 'pending';
			}

			$strToken = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();
			$data['href'] = Controller::addToUrl($data['href'].'&rt='.$strToken,true,array('step'));
			$data['class'] = implode(' ', array_unique($class));
			
			if( !isset($data['isLink']) )
			{
				$data['isLink'] = false;
			}
			
			$arrItems[ $k ] = $data;

			$i++;
		}

		// update session
		$objSession->set($this->strSession,$arrSession);

		// @var object
		$objTemplate = new BackendTemplate($this->strTemplateBreadcrumb);
		$objTemplate->items = $arrItems;

		return $objTemplate->parse();
	}


	/**
	 * Return the installed theme version from pct_theme_version file or changelog.txt
	 * @return string
	 */
	// ! theme version
	public function getThemeVersion()
	{
		$objVersionFile = new File('var/pct_theme_version');
		if( $objVersionFile->exists() )
		{
			return $objVersionFile->getContent();
		}
		
		$objChangelog = new File('templates/changelog.txt');
		if( $objChangelog->exists() )
		{
			$c = $objChangelog->getContent();
			$strLocalVersion = \trim( \str_replace('###','',\substr($c,0,\strpos($c,"\n")) ) );

			// store version in a file
			$objVersionFile = new File('var/pct_theme_version');
			$objVersionFile->write($strLocalVersion);
			$objVersionFile->close();

			return $strLocalVersion;
		}

		return '';
	}


	/**
	 * Send requests
	 */
	// ! send requests
	protected function request($strUrl,$arrParams=array())
	{
		$strRequest = \html_entity_decode($strUrl.(count($arrParams) > 0 ? '?'.\http_build_query($arrParams) : '') );
		// log
		if( $GLOBALS['PCT_THEME_UPDATER']['debug'] === true )
		{
			System::getContainer()->get('monolog.logger.contao.general')->info( 'Sending request: '.$strRequest  );
		}
		// validate the license
		$curl = \curl_init();
		\curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		\curl_setopt($curl, CURLOPT_URL, $strRequest);
		\curl_setopt($curl, CURLOPT_HEADER, 0);
		\curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		\curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
	
		$strResponse = \curl_exec($curl);
		\curl_close($curl);
		unset($curl);

		return $strResponse;
	}


	/**
	 * Check if the theme updater license is valid
	 * @return boolean
	 */
	// ! validate
	public static function validate()
	{
		$objSession = System::getContainer()->get('request_stack')->getSession();
		$strLicense = '';
		$strThemeLicense = '';
			
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
			}
		}

		// all good, update the session
		if( $objUpdaterLicense !== null && isset($objUpdaterLicense->status) && \strtolower($objUpdaterLicense->status) == 'ok')
		{
			$arrSession['updater_license'] = $objUpdaterLicense;
			$objSession->set('pct_theme_updater',$arrSession);
			return true;
		}

		return false;
	}
}