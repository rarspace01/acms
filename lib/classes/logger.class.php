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
		echo $text . "<br>";
		if($sql_param != null) {
			echo "<pre>";
			print_r($sql_param);
			echo "</pre><br>";
		}
	}
}

?>