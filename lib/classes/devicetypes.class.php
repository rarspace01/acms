<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

DeviceTypes class
--
framework for handling different devices
for views/output and input/processing
*/

if(!isset($configSet) OR !$configSet)
	exit();

class apdDeviceTypes
{
	/**
	* function - Constructor
	* --
	* @param: $mainContainer
	*		container that contains all instances
	* @return: class
	* --
	*/
	function __construct($mainContainer)
	{
		$this->mc			= $mainContainer;
	}
	
	/**
	* function - viewDeviceTemplates
	* --
	* obtains a language-term from the database
	* in the current language
	* --
	* @param: $template
	*		template for processing
	* @param $receiver
	*		if unknown/custom template-definitions are made, the receiver
	*		gets the change to replace them. The following method is called:
	*		customDeviceTemplate()
	* @return: (String) template
	*		finished template
	* --
	*/
	function viewDeviceTemplates($template, $receiver)
	{
		/*
		================
		device templates
		================
		*/
		preg_match_all('#\{FOR_DEVICE_TYPES_(?:[0-9]+?)(.*?)FOR_DEVICE_TYPES_([0-9]+?)\}#si', $template, $forDeviceTemplate, PREG_SET_ORDER);
		$deviceTypeQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "devices", array());
		$defaultDevice = '';
		foreach($forDeviceTemplate as $currentTemplate)
		{
			$completeTemplate = "";
			$i = 0;
			foreach($deviceTypeQuery->rows as $currentDeviceType)
			{
				$i++;
				$currentDeviceTpl = preg_replace('#\{DEVICE_TYPE\}#si', $currentDeviceType->device_key, $currentTemplate[1]);
				$currentDeviceTpl = preg_replace('#\{LANG_DEVICE_TAB\}#si', $this->mc->language->getLocalisation($currentDeviceType->device_key . 'view'), $currentDeviceTpl);
				$currentDeviceTpl = preg_replace('#\{LASTTAB(.*?)LASTTAB\}#si', ($i == count($deviceTypeQuery->rows) ? '$1' : ''), $currentDeviceTpl);
				$currentDeviceTpl = preg_replace('#\{DEFAULTTAB(.*?)DEFAULTTAB\}#si', ($currentDeviceType->device_default == 1 ? '$1' : ''), $currentDeviceTpl);
				$currentDeviceTpl = preg_replace('#\{DEVICE_ID\}#si', $currentDeviceType->device_id, $currentDeviceTpl);
				$currentDeviceTpl = $receiver->customDeviceTemplate($currentDeviceTpl, $currentDeviceType->device_key, $currentDeviceType->device_id);
				$completeTemplate .= $currentDeviceTpl;
				if($currentDeviceType->device_default == 1)
					$defaultDevice = $currentDeviceType->device_key;
			}
			reset($deviceTypeQuery->rows);
			
			$template = preg_replace('#\{FOR_DEVICE_TYPES(.*?)FOR_DEVICE_TYPES_' . $currentTemplate[2] . '\}#si', $completeTemplate, $template);
		}
		$template = preg_replace('#\{DEFAULT_DEVICE_TYPE\}#si', $defaultDevice, $template);
		return $template;
	}	
}
?>