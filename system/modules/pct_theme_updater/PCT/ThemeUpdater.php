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
use Contao\Database;
use Contao\Backend;
use Contao\Input;
use Contao\Config;
use Contao\Controller;
use Contao\Message;
use Contao\Files;
use Contao\File;
use Contao\StringUtil;
use Contao\Session;
use Contao\BackendTemplate;


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
		System::loadLanguageFile('pct_theme_updater');
		System::loadLanguageFile('exception');

		// @var object Session
		$objSession = System::getContainer()->get('session');
		$arrSession = $objSession->get($this->strSession);
		
		$objDatabase = Database::getInstance();
		$arrErrors = array();
		$arrParams = array();
		$objLicense = $arrSession['license'] ? json_decode($arrSession['license']) : null;
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
		$this->Template->label_key = $GLOBALS['TL_LANG']['pct_theme_updater']['label_key'] ?: 'License / Order number';
		$this->Template->label_email = $GLOBALS['TL_LANG']['pct_theme_updater']['label_email'] ?: 'Order email address';
		$this->Template->placeholder_license = '';
		$this->Template->placeholder_email = '';
		$this->Template->label_submit = $GLOBALS['TL_LANG']['pct_theme_updater']['label_submit'];
		$this->Template->value_submit = $GLOBALS['TL_LANG']['pct_theme_updater']['value_submit'];
		$this->Template->file_written_response = 'file_written';
		$this->Template->file_target_directory = $GLOBALS['PCT_THEME_UPDATER']['tmpFolder'];
		$this->Template->ajax_action = 'theme_installer_loading'; // just a simple action status message
		$this->Template->test_license = $GLOBALS['PCT_THEME_UPDATER']['test_license'];
		$this->Template->license = $objLicense;

		$blnAjax = false;
		if(Input::get('action') != '' && Environment::get('isAjaxRequest'))
		{
			$blnAjax = true;
		}
		$this->Template->ajax_running = $blnAjax;


//! status : SESSION_LOST


		if(empty($objLicense) && !in_array(Input::get('status'),array('welcome','reset','error','version_conflict')))
		{
			$this->Template->status = 'SESSION_LOST';
			$this->Template->content = $GLOBALS['TL_LANG']['XPT']['pct_theme_updater']['session_lost'];
			$this->Template->breadcrumb = '';

			// redirect to the beginning
			$this->redirect( Backend::addToUrl('status=reset') );

			return;
		}

		// the theme or module name of this lizence
		$this->strTheme = $objLicense->name ?: $objLicense->file->name ?: '';
		if($objLicense->file->name)
		{
			$this->strTheme = basename($objLicense->file->name,'.zip');
			$this->Template->theme = $this->strTheme;
		}


//! status : VERSION_CONFLICT


		// support current LTS 4.9
		if(Input::get('status') != 'version_conflict' && (version_compare(VERSION, '4.4','<=') || (version_compare(VERSION, '4.5','>=') && version_compare(VERSION, '4.8','<=')) || version_compare(VERSION, '4.9','>')) )
		{
			$this->redirect( Backend::addToUrl('status=version_conflict',true,array('step','action')) );
		}
		
		if(Input::get('status') == 'version_conflict')
		{
			$this->Template->status = 'VERSION_CONFLICT';
			$this->Template->errors = array($GLOBALS['TL_LANG']['XPT']['pct_theme_updater']['version_conflict'] ?: 'Please use the LTS version 4.9');
			return;
		}

	
//! status : COMPLETED


		if(Input::get('status') == 'completed')
		{
			#$_SESSION['pct_theme_updater']['completed'] = true;
			#$_SESSION['pct_theme_updater']['license']['name'] = $objLicense->name;
			#$_SESSION['pct_theme_updater']['sql'] = $strOrigTemplate;
			// redirect to contao login
			$url = StringUtil::decodeEntities( Environment::get('base').'contao?installation_completed=1&theme='.Input::get('theme').'&sql='.$_SESSION['pct_theme_updater']['sql']);
			
			if( \version_compare(VERSION,'4.9','>=') )
			{
				$url = StringUtil::decodeEntities( Environment::get('base').'contao/login?installation_completed=1&theme='.Input::get('theme').'&sql='.$_SESSION['pct_theme_updater']['sql']);
			}
			
			$this->redirect($url);

			return;
		}


//! status : RESET


		// clear the session on status reset
		if(Input::get('status') == 'reset' || Input::get('status') == '')
		{
			$objLicense = null;
			$objSession->remove('pct_theme_updater');

			// redirect to the beginning
			$this->redirect( Backend::addToUrl('status=welcome',true,array('step')) );
		}
				

//! status : NOT_SUPPORTED

		
		if($objLicense->status == 'NOT_SUPPORTED' && Input::get('status') != 'not_supported')
		{
			// redirect to the not supported page
			$this->redirect( Backend::addToUrl('status=not_supported',true,array('step')) );
		}
		
		if(Input::get('status') == 'not_supported')
		{
			$this->Template->status = 'NOT_SUPPORTED';
			return;
		}


//! status : ERROR


		if(Input::get('status') == 'error')
		{
			$this->Template->status = 'ERROR';
			$this->Template->breadcrumb = '';
			$this->Template->errors = $arrSession['errors'];
			return;
		}


//! status : WELCOME


		if(Input::get('status') == 'welcome' && !$_POST)
		{
			$this->Template->status = 'WELCOME';
			$this->Template->breadcrumb = '';
			return;
		}


//! status : COMPLETE (probably never been called)


		if(Input::get('status') == 'complete')
		{
			$this->Template->status = 'COMPLETE';
			return;
		}


//! status : ACCESS_DENIED


		if($objLicense->status == 'ACCESS_DENIED' || Input::get('status') == 'access_denied')
		{
			$this->Template->status = 'ACCESS_DENIED';
			
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
			if(empty($arrSession['file']) || !file_exists(TL_ROOT.'/'.$arrSession['file']))
			{
				$this->Template->status = 'FILE_NOT_EXISTS';

				// log
				System::log('Theme Installer: File not found',__METHOD__,TL_ERROR);
				
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
			$strTargetDir = $GLOBALS['pct_theme_updater']['tmpFolder'].'/'.basename($arrSession['file'], ".zip").'_zip';

			if(Input::get('action') == 'run')
			{
				// extract zip
				$objZip = new \ZipArchive;
				if($objZip->open(TL_ROOT.'/'.$objFile->path) === true && !$arrSession['unzipped'])
				{
					$objZip->extractTo(TL_ROOT.'/'.$strTargetDir);
					$objZip->close();

					// flag that the zip file has been extracted
					$arrSession['unzipped'] = true;
					$objSession->set($this->strSession,$arrSession);

					// ajax done
					die('Zip extracted to: '.$strTargetDir);
				}
				// zip already extracted
				elseif($arrSession['unzipped'] && is_dir(TL_ROOT.'/'.$strTargetDir))
				{
					// ajax done
					die('Zip extracted to: '.$strTargetDir);
				}
				// extraction failed
				else
				{
					$log = sprintf($GLOBALS['TL_LANG']['XPT']['pct_theme_updater']['unzip_error'],$arrSession['file']);
					System::log($log,__METHOD__,TL_ERROR);
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
			$strTargetDir = $GLOBALS['pct_theme_updater']['tmpFolder'].'/'.basename($arrSession['file'], ".zip").'_zip';
			$strFolder = $strTargetDir; #$strTargetDir.'/'.basename($arrSession['file'], ".zip");

			if(Input::get('action') == 'run' && is_dir(TL_ROOT.'/'.$strFolder))
			{
				// backup an existing customize.css
				$blnCustomizeCss = false;
				if(file_exists(TL_ROOT.'/'.Config::get('uploadPath').'/cto_layout/css/customize.css'))
				{
					if( Files::getInstance()->copy(Config::get('uploadPath').'/cto_layout/css/customize.css',$GLOBALS['pct_theme_updater']['tmpFolder'].'/customize.css') )
					{
						$blnCustomizeCss = true;
					}
				}

				$objFiles = Files::getInstance();
				$arrIgnore = array('.ds_store');

				// folder to copy
				$arrFolders = scan(TL_ROOT.'/'.$strFolder.'/upload');

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
						$strDestination = Config::get('uploadPath') ?: 'files';
					}
					
					if($objFiles->rcopy($strSource,$strDestination) !== true)
					{
						$arrErrors[] = 'Copy "'.$strSource.'" to "'.$strDestination.'" failed';
					}
				}

				// reinstall the customize.css
				if($blnCustomizeCss)
				{
					Files::getInstance()->copy($GLOBALS['pct_theme_updater']['tmpFolder'].'/customize.css',Config::get('uploadPath') ?: 'files'.'/cto_layout/css/customize.css');
				}
				
				// log errors
				if(count($arrErrors) > 0)
				{
					System::log('Theme Installer: Copy files: '.implode(', ', $arrErrors),__METHOD__,TL_ERROR);
					
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
					System::log( sprintf($GLOBALS['TL_LANG']['pct_theme_updater']['copy_files_completed'],$arrSession['file']),__METHOD__,TL_CRON);

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
				// clear internal cache of Contao 4.4
				$objContainer = System::getContainer();
				$strCacheDir = StringUtil::stripRootDir($objContainer->getParameter('kernel.cache_dir'));
				$strRootDir = $objContainer->getParameter('kernel.project_dir');
				$strWebDir = $objContainer->getParameter('contao.web_dir');
				$arrBundles = $objContainer->getParameter('kernel.bundles');
				
				// @var object Contao\Automator
				$objAutomator = new Automator;
				// generate symlinks to /assets, /files, /system
				$objAutomator->generateSymlinks();
				// generate bundles symlinks
				$objSymlink = new \Contao\CoreBundle\Util\SymlinkUtil;
				$arrBundles = array('calendar','comments','core','faq','news','newsletter');
				foreach($arrBundles as $bundle)
				{
					$from = $strRootDir.'/vendor/contao/'.$bundle.'-bundle/src/Resources/public';
					$to = $strWebDir.'/bundles/contao'.$bundle;
					$objSymlink::symlink($from, $to,$strRootDir);
				}

				// clear the internal cache
				if ( \version_compare(VERSION,'4.4','<=') )
				{
					$objAutomator->purgeInternalCache();
					// rebuild the internal cache
					$objAutomator->generateInternalCache();
				}
				// purge the whole folder
				#Files::getInstance()->rrdir($strCacheDir,true);

				// try to rebuild the symphony cache
				$objInstallationController = new \PCT\ThemeInstaller\Contao4\InstallationController;
				$objInstallationController->call('purgeSymfonyCache');
				#$objInstallationController->call('warmUpSymfonyCache');

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
			try
			{
				// Contao 4.4 >=
				if(version_compare(VERSION, '4.4','>='))
				{
					// @var object \PCT\ThemeInstaller\InstallationController
					#$objInstaller = new \PCT\ThemeInstaller\InstallationController;
					$objContainer = System::getContainer();
					$objInstaller = $objContainer->get('contao.installer');
					// compile sql
					$arrSQL = $objInstaller->getCommands();
					if(!empty($arrSQL) && is_array($arrSQL))
					{
						foreach($arrSQL as $operation => $sql)
						{
							// never run operations
							if(in_array($operation, array('DELETE','DROP','ALTER_DROP')))
							{
								continue;
							}

							foreach($sql as $hash => $statement)
							{
								$objInstaller->execCommand($hash);
							}
						}
					}
				}
			}
			catch(\Exception $e)
			{
				$arrErrors[] = $e->getMessage();
			}
			
			// log errors and redirect
			if(count($arrErrors) > 0)
			{
				System::log('Theme Installer: Database update returned errors: '.implode(', ', $arrErrors),__METHOD__,TL_ERROR);
				
				// track error				
				$arrSession['errors'] = $arrErrors;
				$objSession->set($this->strSession,$arrSession);

				$this->redirect( Backend::addToUrl('status=error',true,array('step','action')) );
			}

			return;
		}
		//! status: INSTALLATION | STEP 5.0 : SQL_TEMPLATE_WAIT : Wait for user input
		else if(Input::get('status') == 'installation' && Input::get('step') == 'sql_template_wait')
		{
			// get the template by contao version
			$strTemplate = $GLOBALS['pct_theme_updater']['THEMES'][$this->strTheme]['sql_templates'][VERSION];

			$this->Template->status = 'INSTALLATION';
			$this->Template->step = 'SQL_TEMPLATE_WAIT';
			$this->Template->sql_template_info = sprintf($GLOBALS['TL_LANG']['pct_theme_updater']['sql_template_info'],$strTemplate);
			
			// when not in "update" mode, continue sql template installation
			if(Input::get('mode') == 'install' || Input::get('mode') == '')
			{
				$this->redirect( Backend::addToUrl('status=installation&step=sql_template_import') );
			}
			
			return;
		}
		//! status: INSTALLATION | STEP 6.0 : SQL_TEMPLATE_IMPORT : Import the sql file
		else if(Input::get('status') == 'installation' && Input::get('step') == 'sql_template_import')
		{
			$this->Template->status = 'INSTALLATION';
			$this->Template->step = 'SQL_TEMPLATE_IMPORT';
			
			// get the template by contao version
			$strTemplate = $GLOBALS['pct_theme_updater']['THEMES'][$this->strTheme]['sql_templates'][VERSION];
			
			if(empty($strTemplate))
			{
				$this->Template->error = $GLOBALS['TL_LANG']['XPT']['pct_theme_updater']['sql_not_found'];
				return;
			}
			// create a tmp copy
			$strTmpTemplate = 'tmp_'.$strTemplate;
			$strOrigTemplate = $strTemplate;
			$blnIsCustomCatalog = (boolean)$GLOBALS['pct_theme_updater']['THEMES'][$this->strTheme]['isCustomCatalog'];
			
			if( $blnIsCustomCatalog === false && \file_exists(TL_ROOT.'/templates/'.$strOrigTemplate) )
			{
				if(Files::getInstance()->copy('templates/'.$strTemplate,'templates/tmp_'.$strTemplate))
				{
					$file = fopen(TL_ROOT.'/templates/tmp_'.$strTemplate,'r');

					$str = '';
					while(!feof($file))
					{
						$line = fgets($file);
						if(strlen(strpos($line, 'INSERT INTO `tl_user`')) > 0)
						{
							continue;
						}

						$str .= $line;
					}
					fclose($file);
					unset($file);

					// fetch tl_user information
					$objUsers = $objDatabase->prepare("SELECT * FROM tl_user")->execute();
					while($objUsers->next())
					{
						$str .= $objDatabase->prepare("INSERT INTO `tl_user` %s")->set( $objUsers->row() )->__get('query') . "\n";
					}

					$objFile = new File('templates/tmp_'.$strTemplate);
					$objFile->write($str);
					$objFile->close();

					unset($str);

					$strTemplate = $strTmpTemplate;
				}
			}
			
			$this->Template->sqlFile = $strOrigTemplate;
			
			// @author Leo Feyer
			$objDatabase->query("SET AUTOCOMMIT = 0");

			// Eclipse + CustomCatalog sqls
			$strZipFolder = $GLOBALS['pct_theme_updater']['THEMES'][$this->strTheme]['zip_folder'];
			$strFileCC = TL_ROOT.'/'.$GLOBALS['pct_theme_updater']['tmpFolder'].'/'.$strZipFolder.'/'.$strTemplate;
			if(Input::get('action') == 'run' && $blnIsCustomCatalog === true && file_exists($strFileCC))
			{
				$skipTables = array('tl_user','tl_user_group','tl_member','tl_member_group','tl_session','tl_repository_installs','tl_repository_instfiles','tl_undo','tl_log','tl_version');
				
				$objFile = fopen($strFileCC,'r');
				
				// find multiline CREATE, ALTER statements
				$create_sql = array();
				$alter_sql = array();
				if($objFile)
				{
					$create_table = '';
					$alter_table = '';

					while(!feof($objFile))
					{
						$line = fgets($objFile);

						// CREATE
						if(strpos($line, 'CREATE TABLE') !== false)
						{
							if(preg_match('/`(.*?)\`/', $line,$result))
							{
								$create_table = $result[1];
							}
						}
						// ALTER
						if(strpos($line, 'ALTER TABLE') !== false)
						{
							if(preg_match('/`(.*?)\`/', $line,$result))
							{
								$alter_table = $result[1];
							}
						}

						if(strlen($create_table) > 0)
						{
							$create_sql[$create_table] .= trim($line);
						}

						if(strlen($alter_table) > 0)
						{
							$alter_sql[$alter_table] .= trim($line);
						}

						if(strpos($line, 'CHARSET=utf8') !== false && strlen($create_table) > 0)
						{
							$create_table = '';
						}
						if(strpos($line, ';') !== false && strlen($alter_table) > 0)
						{
							$alter_table = '';
						}
					}
					fclose($objFile);
					unset($create_table);
					unset($alter_table);
				}
				
				try
				{
					// DROP tables that will be created anyways
					foreach(array_keys($create_sql) as $table)
					{
						if($objDatabase->tableExists($table,null,true) === true && !in_array($table, $skipTables))
						{
							$objDatabase->query('DROP TABLE '.$table);
						}
					}

					// CREATE tables
					foreach($create_sql as $table => $query)
					{
						if($objDatabase->tableExists($table,null,true) === false && !in_array($table, $skipTables))
						{
							$objDatabase->query($query);
						}
					}

					// ALTER tables
					foreach($alter_sql as $table => $query)
					{
						if($objDatabase->tableExists($table,null,true) === false || in_array($table, $skipTables))
						{
							continue;
						}

						foreach(array_filter(explode(';', $query)) as $q)
						{
							$objDatabase->query($q.';');
						}
					}
					unset($create_sql);
					unset($alter_sql);

					// TRUNCATE and INSERT
					$sql = preg_grep('/^INSERT /', file($strFileCC) );

					// TRUNCATE and INSERT
					$truncated = array();
					foreach($sql as $query)
					{
						if(preg_match('/`(.*?)\`/', $query,$result))
						{
							if($objDatabase->tableExists( $result[1],null,true ) === true && !in_array($result[1], $truncated) && !in_array($result[1], $skipTables))
							{
								$objDatabase->query('TRUNCATE TABLE '.$result[1]);
								$truncated[] = $result[1];
							}

							if($objDatabase->tableExists( $result[1],null,true ) === true && !in_array($result[1], $skipTables))
							{
								$objDatabase->query($query);
							}
						}
					}
				}
				catch(\Exception $e)
				{
					$arrErrors[] = $e->getMessage();
				}

				unset($skipTables);
				unset($objFile);
				unset($sql);
				unset($truncated);

				// @author Leo Feyer
				$objDatabase->query("SET AUTOCOMMIT = 1");

				if(!empty($arrErrors))
				{
					System::log('Theme installation finished with errors: '.implode(', ', $arrErrors),__METHOD__,TL_ERROR);
					
					// track error				
					$arrSession['errors'] = $arrErrors;
					$objSession->set($this->strSession,$arrSession);
					
					$this->redirect( Backend::addToUrl('status=error',true,array('step','action')) );
				}

				// mark as being completed
				$_SESSION['pct_theme_updater']['completed'] = true;
				$_SESSION['pct_theme_updater']['theme'] = $this->strTheme;
				$_SESSION['pct_theme_updater']['sql'] = $strOrigTemplate;
				$objSession->set('pct_theme_updater',$_SESSION['pct_theme_updater']);
				
				// log out
				#$objUser = \BackendUser::getInstance();
				#$objUser->logout();

				// redirect to contao login if not from ajax
				if(!Environment::get('isAjaxRequest'))
				{
					$url = StringUtil::decodeEntities( Environment::get('base').'contao?completed=1&theme='.$this->strTheme.'&sql='.$strOrigTemplate );
					$this->redirect($url);
				}

				return;
			}

			if(Input::get('action') == 'run')
			{
				// mark as being completed
				$_SESSION['pct_theme_updater']['completed'] = true;
				$_SESSION['pct_theme_updater']['theme'] = $this->strTheme;
				$_SESSION['pct_theme_updater']['sql'] = $strOrigTemplate;
				$objSession->set('pct_theme_updater',$_SESSION['pct_theme_updater']);		
				
				$objContainer = System::getContainer();
				$objInstall = $objContainer->get('contao.install_tool');
				// let the install tool import the sql templates
				$objInstall->importTemplate($strTemplate);
				#$objInstall->persistConfig('exampleWebsite', time());
				
				if(!Environment::get('isAjaxRequest'))
				{
					$url = StringUtil::decodeEntities( Environment::get('base').'contao?completed=1&theme='.$this->strTheme.'&sql='.$strOrigTemplate );
					$this->redirect($url);
				}	
			}

			return;
		}


//! status: FILE_LOADED ... FILE_CREATED


		// file loaded
		if($arrSession['status'] == 'FILE_CREATED' && !empty($arrSession['file']))
		{
			// check if file still exists
			if(!file_exists(TL_ROOT.'/'.$arrSession['file']))
			{
				$this->Template->status = 'FILE_NOT_EXISTS';

				// log
				System::log('Theme Installer: File not found or file could not be created',__METHOD__,TL_ERROR);
				
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


//! status: VALIDATION: Fetch the license information


		if(Input::post('license') != '' && Input::post('email') != '' && Input::post('FORM_SUBMIT') == $strForm)
		{
			$this->Template->status = 'VALIDATION';

			$arrParams = array
			(
				'key'   => trim(Input::post('license')),
				'email'  => trim(Input::post('email')),
				'domain' => Environment::get('url'),
			);

			if(Input::post('product') != '')
			{
				$arrParams['product'] = Input::post('product');
			}

			$strRequest = html_entity_decode(  $GLOBALS['pct_theme_updater']['api_url'].'/api.php?'.http_build_query($arrParams) );
			
			// validate the license
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_URL, $strRequest);
			curl_setopt($curl, CURLOPT_HEADER, 0);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		
			$strResponse = curl_exec($curl);
			curl_close($curl);
			unset($curl);

			$objLicense = json_decode($strResponse);

			// store the api response in the session
			$arrSession['status'] = $objLicense->status;
			$arrSession['license'] = $strResponse;
			$objSession->set($this->strSession,$arrSession);
			
			// flush post and make session active
			// redirect to the beginning
			$this->redirect( Backend::addToUrl('status=ready',true) );
		}


//! status: CHOOSE_PRODUCT, waiting for user to choose the product


		if(Input::get('status') == 'choose_product' && $objLicense->status == 'OK')
		{
			$this->Template->status = 'CHOOSE_PRODUCT';
			$this->Template->license = $objLicense;
			$this->Template->breadcrumb = '';

			// registration error
			if($objLicense->registration->hasError)
			{
				$this->Template->hasRegistrationError = true;
			}

			return;
		}


//! status: READY, waiting for installation GO


		if(Input::get('status') == 'ready' && $objLicense->status == 'OK')
		{
			$this->Template->status = 'READY';
			$this->Template->license = $objLicense;

			// min memory_limit
			if( (int)ini_get('memory_limit') < 512 && (int)ini_get('memory_limit') > 0)
			{
				$this->Template->errors = array( \sprintf($GLOBALS['TL_LANG']['XPT']['pct_theme_updater']['memory_limit'],ini_get('memory_limit')) ?: 'Min. required memory_limit is 512M');
			}

			// registration error
			if($objLicense->registration->hasError)
			{
				$this->Template->hasRegistrationError = true;
			}

			// has more than one product to choose
			if(!empty($objLicense->products))
			{
				$this->redirect( Backend::addToUrl('status=choose_product',true) );
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

			// write license file
			$objFile = new File('var/pct_license');
			$objFile->write($objLicense->key);
			$objFile->close();
		
			// coming from ajax request
			if(Input::get('action') == 'run')
			{
				$arrParams['email'] = $objLicense->email;
				$arrParams['key'] = $objLicense->key;
				$arrParams['hash'] = $objLicense->hash;
				$arrParams['domain'] = $objLicense->domain;
				$arrParams['sendToAjax'] = 1;
				$arrParams['product'] = $objLicense->file->id;

				$strFileRequest = html_entity_decode( $GLOBALS['pct_theme_updater']['api_url'].'/api.php?'.http_build_query($arrParams) );
				
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
						$objFile = new File($GLOBALS['pct_theme_updater']['tmpFolder'].'/'.$objLicense->file->name);
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
				System::log('Theme Installer: '.implode(', ', $arrErrors),__METHOD__,TL_ERROR);
				
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
		if(TL_MODE == 'BE' && $objTemplate->getName() == 'be_main')
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
	public function getBreadcrumb($strStatus='',$strStep='')
	{
		$strCurrent = $strStatus.($strStep != '' ? '.'.$strStep : '');

		$arrItems = array();
		$i = 0;

		$objSession = System::getContainer()->get('session');
		$arrSession = $objSession->get($this->strSession);
		
		// store the processed steps
		if(!is_array($arrSession['BREADCRUMB']['completed']))
		{
			$arrSession['BREADCRUMB']['completed'] = array();
		}

		foreach($GLOBALS['pct_theme_updater']['breadcrumb_steps'] as $k => $data)
		{
			$status = strtolower($k);

			// css class
			$class = array('item',$status);
			if($data['protected'])
			{
				$class[] = 'hidden';
			}

			($i%2 == 0 ? $class[] = 'even' : $class[] = 'odd');
			($i == 0 ? $class[] = 'first' : '');
			($i == count($GLOBALS['pct_theme_updater']['breadcrumb_steps']) - 1 ? $class[] = 'last' : '');

			if(!$data['label'])
			{
				$data['label'] = $k;
			}

			// title
			if(!$data['title'])
			{
				$data['title'] = $data['label'];
			}

			// active
			if($strCurrent == $status)
			{
				$data['isActive'] = true;
				$class[] = 'tl_green';
				$class[] = 'active';

				$arrSession['BREADCRUMB']['completed'][$k] = true;
			}

			// completed
			if($arrSession['BREADCRUMB']['completed'][$k] === true && $strCurrent != $status)
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

			$data['href'] = Controller::addToUrl($data['href'].'&rt='.REQUEST_TOKEN,true,array('step'));
			$data['class'] = implode(' ', array_unique($class));

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
}