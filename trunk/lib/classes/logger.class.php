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
	public $errorOccured = false;

	function log($text, $sql_param = null)
	{
		$output = date("d.m.Y H:i:s") . " - " . $text . "\n";
		if($sql_param != null) {
			$output .= print_r($sql_param, true) . "\n--------\n";
		}
		echo $output;
		
		$logFile = fopen('includes/logfile.log', 'a+');
		fwrite($logFile, $output);
		fclose($logFile);
		
		$this->errorOccured = true;
	}
	
	function errorHappened()
	{
		$e=error_get_last();
		if(trim($e['message']) !== "" && ($e['type'] !== E_WARNING && $e['type'] !== E_NOTICE && $e['type'] !== E_CORE_WARNING && $e['type'] !== E_COMPILE_WARNING && $e['type'] !== E_COMPILE_WARNING && $e['type'] !== E_STRICT ))
		{
			ob_end_flush();
			echo "Error!<br>";
			die(print_r($e, true));
		}
	}
}

?>