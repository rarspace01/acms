<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Logger class
--
logging errors

*/

if(!isset($configSet) OR !$configSet)
	exit();
	
class apdLogger
{	
	function log($text, $sql_param = null)
	{
		$output = $text . "\n";
		if($sql_param != null) {
			$output .= print_r($sql_param, true) . "\n--------\n";
		}
		echo $output;
		
		$logFile = fopen('config/logfile.log', 'a+');
		fwrite($logFile, $output);
		fclose($logFile);
	}
}

?>