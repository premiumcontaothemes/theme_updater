<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2013 Leo Feyer
 * 
 * @copyright	Tim Gatzky 2019
 * @author		Tim Gatzky <info@tim-gatzky.de>
 * @package		pct_customelements
 */

/**
 * Namespace
 */
namespace PCT\ThemeUpdater;

/**
 * Imports
 */
use Exception;
use Contao\Config;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\DcaExtractor;
use PCT\CustomElements\Plugins\CustomCatalog\Core\CustomCatalogFactory;
use PCT\CustomElements\Models\CustomCatalogModel;


/**
 * Class
 * InstallerHelper
 * Provide various function to help managing the database for Contao high than 4.4
 */
class InstallerHelper extends \Contao\Database\Installer
{
	/**
	 * Current object instance (Singleton)
	 * @var object
	 */
	protected static $objInstance;
	
	/**
	 * Init
	 */
	public function __construct()
	{
		if( $this->Database === null )
		{
			$this->Database = \Contao\Database::getInstance();
		}

		// clear opcache
		if (\function_exists('opcache_reset')) 
		{
			opcache_reset();
		}
	}
	
	/**
	 * Instantiate this class and return it (Factory)
	 * @return object
	 * @throws Exception
	 */
	public static function getInstance()
	{
		if (!is_object(static::$objInstance))
		{
			static::$objInstance = new static();
		}
		return static::$objInstance;
	}
	
	
	/**
	 * Call methods
	 * @param string Name of function
	 * @param array
	 */
	public function call($strMethod, $arrArguments=array())
	{
		$request = \Contao\System::getContainer()->get('request_stack')->getCurrentRequest();
		if( $request && \Contao\System::getContainer()->get('contao.routing.scope_matcher')->isFrontendRequest($request) )
		{
			throw new \Exception('Not allowed to be executed outside Contaos backend');
		}
		
		if (method_exists($this, $strMethod))
		{
			return call_user_func_array(array($this, $strMethod), $arrArguments);
		}
		throw new \RuntimeException('undefined method: '.get_class($this).'::'.$strMethod);
	}

	
	/**
	 * Add CustomCatalog database information to Contaos SQL commands
	 * @param array
	 * @return array
	 * 
	 * called from sqlCompileCommands Hook
	 */
	public function sqlCompileCommandsCallback($return)
	{
		$sql_target = $this->getFromDca();
		// create table information by dca
		$return = \array_merge_recursive($return, $this->_compileCommands( $sql_target ) );
		return array_filter($return);
	}

	
	/**
	 * Compile the database information array
	 * @return array
	 */
	protected function _compileCommands($sql_target)
	{
		$version = ContaoCoreBundle::getVersion();
		$drop = array();
		$create = array();
		$return = array
		(
			'CREATE' => [],
			'DROP' => [],
			'ALTER_DROP' => [],
			'ALTER_CHANGE' => [],
			'ALTER_ADD' => []
		);

		$sql_current = $this->_getFromDb();
		
		// Create tables
		foreach (array_diff(array_keys($sql_target), array_keys($sql_current)) as $table)
		{
			$sql = "CREATE TABLE `" . $table . "` ( " . implode(",\n  ", $sql_target[$table]['TABLE_FIELDS']) . (!empty($sql_target[$table]['TABLE_CREATE_DEFINITIONS']) ? ',' . "\n  " . implode(",\n  ", $sql_target[$table]['TABLE_CREATE_DEFINITIONS']) : '') . "\n)" . $sql_target[$table]['TABLE_OPTIONS'] . ';';
			$return['CREATE'][ md5($sql) ] = $sql;
			$create[] = $table;
		}
		
		// Add or change fields
		foreach ($sql_target as $k=>$v)
		{
			if (in_array($k, $create))
			{
				continue;
			}

			$fields = array();
			foreach($this->Database->listFields($k,true) as $field)
			{
				$fields[ $field['name'] ] = $field;
			}

			// Fields
			if (is_array($v['TABLE_FIELDS']))
			{
				foreach ($v['TABLE_FIELDS'] as $kk=>$vv)
				{
					if (!isset($sql_current[$k]['TABLE_FIELDS'][$kk]))
					{
						if( \is_array($vv) && !empty($vv) && isset($vv['type']) )
						{
							if($vv['type'] == 'boolean')
							{
								$vv = $vv['name'].' TINYINT(1) DEFAULT 0 NOT NULL';
								if( \version_compare($version,'4.13','<=') )
								{
									$vv = $vv['name']." char(1) NOT NULL default ''";
								}
							}
						}
						
						$sql = 'ALTER TABLE `'.$k.'` ADD '.$vv.';';
						$return['ALTER_ADD'][ md5($sql) ] = $sql;
					}
					elseif ($sql_current[$k]['TABLE_FIELDS'][$kk] != $vv && (Config::get('dbCollation') !== null && $sql_current[$k]['TABLE_FIELDS'][$kk] != str_replace(' COLLATE ' . Config::get('dbCollation'), '', $vv)) )
					{
						// do not change collation of alias fields
						if( \strpos($vv,'BINARY NOT NULL') && $fields[$kk]['collation'] == 'utf8mb4_bin' )
						{
							continue;
						}
						// do not change collation of blob fields
						else if( \strpos($vv,'blob NULL') )
						{
							continue;
						}
						
						// check collation changes
						if( Config::get('dbCollation') !== null && $fields[$kk]['collation'] != Config::get('dbCollation') && empty($fields[$kk]['collation']) === false )
						{
							$vv .= ' COLLATE '.Config::get('dbCollation');
						}

						$sql = 'ALTER TABLE '.$k.' CHANGE '.$kk.' '.$vv.';';
						$return['ALTER_CHANGE'][ md5($sql) ] = $sql;
					
					}
				}
			}

			// Create definitions
			if (is_array($v['TABLE_CREATE_DEFINITIONS']))
			{
				foreach ($v['TABLE_CREATE_DEFINITIONS'] as $kk=>$vv)
				{
					if (!isset($sql_current[$k]['TABLE_CREATE_DEFINITIONS'][$kk]))
					{
						$sql = 'ALTER TABLE `'.$k.'` ADD '.$vv.';';
						$return['ALTER_ADD'][ md5($sql) ] = $sql;
					}
					elseif ($sql_current[$k]['TABLE_CREATE_DEFINITIONS'][$kk] != str_replace('FULLTEXT ', '', $vv))
					{
						$sql = 'ALTER TABLE `'.$k.'` DROP INDEX `'.$kk.'`, ADD '.$vv.';';
						$return['ALTER_CHANGE'][ md5($sql) ] = $sql;
					}
				}
			}

			// Move auto_increment fields to the end of the array
			if (is_array($return['ALTER_ADD']))
			{
				foreach (preg_grep('/auto_increment/i', $return['ALTER_ADD']) as $kk=>$vv)
				{
					unset($return['ALTER_ADD'][$kk]);
					$return['ALTER_ADD'][$kk] = $vv;
				}
			}

			if (is_array($return['ALTER_CHANGE']))
			{
				foreach (preg_grep('/auto_increment/i', $return['ALTER_CHANGE']) as $kk=>$vv)
				{
					unset($return['ALTER_CHANGE'][$kk]);
					$return['ALTER_CHANGE'][$kk] = $vv;
				}
			}
		}

		// Drop tables
		foreach (array_diff(array_keys($sql_current), array_keys($sql_target)) as $table)
		{
			$sql = 'DROP TABLE `'.$table.'`;';
			$return['DROP'][ md5($sql) ] = $sql;
			$drop[] = $table;
		}

		// Drop fields
		foreach ($sql_current as $k=>$v)
		{
			if (!in_array($k, $drop))
			{
				// Create definitions
				if (is_array($v['TABLE_CREATE_DEFINITIONS']))
				{
					foreach ($v['TABLE_CREATE_DEFINITIONS'] as $kk=>$vv)
					{
						if (!isset($sql_target[$k]['TABLE_CREATE_DEFINITIONS'][$kk]))
						{
							$sql = 'ALTER TABLE `'.$k.'` DROP INDEX `'.$kk.'`;';
							$return['ALTER_DROP'][ md5($sql) ] = $sql;
						}
					}
				}

				// Fields
				if (is_array($v['TABLE_FIELDS']))
				{
					foreach ($v['TABLE_FIELDS'] as $kk=>$vv)
					{
						if (!isset($sql_target[$k]['TABLE_FIELDS'][$kk]))
						{
							$sql = 'ALTER TABLE `'.$k.'` DROP `'.$kk.'`;';
							$return['ALTER_DROP'][ md5($sql) ] = $sql;
						}
					}
				}
			}
		}
		
		return $return;
	}
	
	
	/**
	 * Get table information from all tables in the database
	 * @return array
	 */
	protected function _getFromDb()
	{
		$tables = $this->Database->listTables(null, true);

		if (empty($tables))
		{
			return array();
		}

		$return = array();
		$quote = static function ($item) { return '`' . $item . '`'; };

		foreach ($tables as $table)
		{
			$fields = $this->Database->listFields($table, true);

			foreach ($fields as $field)
			{
				$name = $field['name'];
				$field['name'] = $quote($field['name']);

				if ($field['type'] != 'index')
				{
					unset($field['index'], $field['origtype']);

					// Field type
					if ( isset($field['length']) )
					{
						$field['type'] .= '(' . $field['length'] . (isset($field['precision']) ? ',' . $field['precision'] : '') . ')';

						unset($field['length'], $field['precision']);
					}

					// Variant collation
					if ($field['collation'] && $field['collation'] != Config::get('dbCollation'))
					{
						$field['collation'] = 'COLLATE ' . $field['collation'];
					}
					else
					{
						unset($field['collation']);
					}

					// Default values
					if ($field['default'] === null || stripos($field['extra'], 'auto_increment') !== false || strtolower($field['null']) == 'null' || \in_array(strtolower($field['type']), array('text', 'tinytext', 'mediumtext', 'longtext', 'blob', 'tinyblob', 'mediumblob', 'longblob')))
					{
						unset($field['default']);
					}
					// Date/time constants (see #5089)
					elseif (\in_array(strtolower($field['default']), array('current_date', 'current_time', 'current_timestamp')))
					{
						$field['default'] = "default " . $field['default'];
					}
					// Everything else
					else
					{
						$field['default'] = "default '" . $field['default'] . "'";
					}

					$return[$table]['TABLE_FIELDS'][$name] = trim(implode(' ', $field));
				}

				// Indexes
				if (isset($field['index']) && $field['index_fields'])
				{
					// Quote the field names
					$index_fields = implode(
						', ',
						array_map(
							static function ($item) use ($quote)
							{
								if (strpos($item, '(') === false)
								{
									return $quote($item);
								}

								list($name, $length) = explode('(', rtrim($item, ')'));

								return $quote($name) . '(' . $length . ')';
							},
							$field['index_fields']
						)
					);

					switch ($field['index'])
					{
						case 'UNIQUE':
							if ($name == 'PRIMARY')
							{
								$return[$table]['TABLE_CREATE_DEFINITIONS'][$name] = 'PRIMARY KEY  (' . $index_fields . ')';
							}
							else
							{
								$return[$table]['TABLE_CREATE_DEFINITIONS'][$name] = 'UNIQUE KEY `' . $name . '` (' . $index_fields . ')';
							}
							break;

						case 'FULLTEXT':
							$return[$table]['TABLE_CREATE_DEFINITIONS'][$name] = 'FULLTEXT KEY `' . $name . '` (' . $index_fields . ')';
							break;

						default:
							$return[$table]['TABLE_CREATE_DEFINITIONS'][$name] = 'KEY `' . $name . '` (' . $index_fields . ')';
							break;
					}

					unset($field['index_fields'], $field['index']);
				}
			}
		}

		return $return;
	}
}