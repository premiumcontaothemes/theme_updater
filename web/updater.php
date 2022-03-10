<?php 
// ne key, no access
if( empty($_GET['key']) === true )
{
	die('No license key');
}


// fetch a template
if( empty($_GET['template']) === false )
{
	$strName = $_GET['template'];
	$strFile = realpath(__DIR__. DIRECTORY_SEPARATOR . '../templates/'.$strName.'.html5');
	if( file_exists($strFile) === false )
	{
		die('');
		#die('File '.$strName.'.html5 does not exist in templates folder');
	}	
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="'.$strName.'.html5"');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: ' . filesize($strFile));

	readfile($strFile);
	exit;
}	
	
?>