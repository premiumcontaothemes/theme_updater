<?php

namespace PCT\ThemeUpdater\Maintenance;

use Contao\StringUtil;
use Contao\Database;

class Jobs
{
	public static function news_order()
	{
		$objDatabase = Database::getInstance();

		// tl_module.news_order
		$objDatabase->prepare("UPDATE tl_module %s WHERE news_order='date DESC'")->set( array('news_order'=>'order_date_desc') )->execute();
	}


	public static function center_center_to_crop()
	{
		$objDatabase = Database::getInstance();
		
		// tl_module.imgSize
		$field = 'imgSize';
		$objResult = $objDatabase->prepare("SELECT * FROM tl_module WHERE $field LIKE '%center_center%'")->execute();
		while($objResult->next())
		{
			$set = array();
			$imgSize = StringUtil::deserialize( $objResult->{$field} );
			if( isset($imgSize[2]) && $imgSize[2] == 'center_center' )
			{
				$imgSize[2] = 'crop';
				$set[$field] = $imgSize;
			}
			
			if( !empty($set) )
			{
				$objDatabase->prepare("UPDATE tl_module %s WHERE id=?")->set($set)->execute($objResult->id);
			}
		}

		// tl_module.customcatalog_imgSize
		$field = 'customcatalog_imgSize';
		if( $objDatabase->fieldExists('customcatalog_imgSize','tl_module') )
		{
			$objResult = $objDatabase->prepare("SELECT * FROM tl_module WHERE $field LIKE '%center_center%'")->execute();
			while($objResult->next())
			{
				$set = array();
				$imgSize = StringUtil::deserialize( $objResult->{$field} );
				if( isset($imgSize[2]) && $imgSize[2] == 'center_center' )
				{
					$imgSize[2] = 'crop';
					$set[$field] = $imgSize;
				}
				
				if( !empty($set) )
				{
					$objDatabase->prepare("UPDATE tl_module %s WHERE id=?")->set($set)->execute($objResult->id);
				}
			}
		}

		// tl_content.size aendern
		$field = 'size';
		$objResult = $objDatabase->prepare("SELECT * FROM tl_content WHERE $field LIKE '%center_center%'")->execute();
		while($objResult->next())
		{
			$set = array();
			$imgSize = StringUtil::deserialize( $objResult->{$field} );
			if( isset($imgSize[2]) && $imgSize[2] == 'center_center' )
			{
				$imgSize[2] = 'crop';
				$set[$field] = $imgSize;
			}
			if( !empty($set) )
			{
				$objDatabase->prepare("UPDATE tl_content %s WHERE id=?")->set($set)->execute($objResult->id);
			}
		}

		// tl_pct_customelement_attribute.size aendern
		$field = 'size';
		$objResult = $objDatabase->prepare("SELECT * FROM tl_pct_customelement_attribute WHERE $field LIKE '%center_center%'")->execute();
		while($objResult->next())
		{
			$set = array();
			$imgSize = StringUtil::deserialize( $objResult->{$field} );
			if( isset($imgSize[2]) && $imgSize[2] == 'center_center' )
			{
				$imgSize[2] = 'crop';
				$set[$field] = $imgSize;
			}
			if( !empty($set) )
			{
				$objDatabase->prepare("UPDATE tl_pct_customelement_attribute %s WHERE id=?")->set($set)->execute($objResult->id);
			}
		}

		// tl_content.pct_customelement
		$field = 'pct_customelement';
		$objResult = $objDatabase->prepare("SELECT * FROM tl_content WHERE $field LIKE '%center_center%'")->execute();
		while($objResult->next())
		{
			$set = array();
			$update = false;
			$data = StringUtil::deserialize( $objResult->{$field} );
			foreach($data['values'] as $k => $v)
			{
				if( strpos($k,'_size') === false )
				{
					continue;
				}

				$imgSize = StringUtil::deserialize( $v );
				if( isset($imgSize[2]) && $imgSize[2] == 'center_center' )
				{
					$imgSize[2] = 'crop';
					$data['values'][$k] = $imgSize;

					$update = true;
				}	
			}

			if( $update )
			{
				$set[$field] = $data;
			}	

			if( !empty($set) )
			{
				$objDatabase->prepare("UPDATE tl_content %s WHERE id=?")->set($set)->execute($objResult->id);
			}
		}


		// tl_module.pct_customelement
		$field = 'pct_customelement';
		$objResult = $objDatabase->prepare("SELECT * FROM tl_module WHERE $field LIKE '%center_center%'")->execute();
		while($objResult->next())
		{
			$set = array();
			$update = false;
			$data = StringUtil::deserialize( $objResult->{$field} );
			foreach($data['values'] as $k => $v)
			{
				if( strpos($k,'_size') === false )
				{
					continue;
				}

				$imgSize = StringUtil::deserialize( $v );
				if( isset($imgSize[2]) && $imgSize[2] == 'center_center' )
				{
					$imgSize[2] = 'crop';
					$data['values'][$k] = $imgSize;

					$update = true;
				}	
			}

			if( $update )
			{
				$set[$field] = $data;
			}	

			if( !empty($set) )
			{
				$objDatabase->prepare("UPDATE tl_module %s WHERE id=?")->set($set)->execute($objResult->id);
			}
		}
	}
}