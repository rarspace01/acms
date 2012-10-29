<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

View class for display list of creatable
modules
*/

if(!isset($configSet) OR !$configSet)
	exit();
	
include('lib/views/iview.view.php');	

/**
* function - initCurrentView
* --
* in order to init this view dynamically, this function is needed
* which returns an instance without the caller knowing the class-name.
* --
* @param: $mainContainer
*		container that contains all instances
* @return: class
* --
*/
function initCurrentView($mainContainer)
{
	return new apdViewAdd($mainContainer);
}

class apdViewAdd implements apdIView
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
		$this->mc		= $mainContainer;
	}	
	
	/**
	* function - initTemplate
	* --
	* load template file
	* --
	* @param: none
	* @return: none
	* --
	*/
	function initTemplate()
	{
		include('templates/' . $this->mc->config['template'] . '/modules/add/add.html');
		$this->template = ob_get_contents();
		ob_clean();
	}
	
	/**
	* function - printTemplate
	* --
	* return complete template
	* --
	* @param: none
	* @return: (String)
	*		the complete filled template for
	*		page of current module
	* --
	*/
	function printTemplate()
	{		
		preg_match_all('#\{FOR_MODULES(.*?)FOR_MODULES\}#si', $this->template, $forCreateableViews);
		$forCreateableViews[0] = "";
			
		if($addableIconFolder = opendir('templates/' . $this->mc->config['template'] . '/modules/add/icons'))
		{
			while (false !== ($currentAddIcon = readdir($addableIconFolder)) )
			{
				if(preg_match('#^(.+?)\.png$#si', $currentAddIcon))
				{
					$currentAddIconTpl = preg_replace('#\{MODULETERM\}#si', preg_replace('#^add\-(.+?)\.png$#si', '$1', $currentAddIcon), $forCreateableViews[1][0]);
					$currentAddIconTpl = preg_replace('#\{MODULENAME\}#si', preg_replace('#^add\-(.+?)\.png$#si', '$1', $currentAddIcon), $currentAddIconTpl);
					$forCreateableViews[0] .= $currentAddIconTpl;
				}
			}
		}
		closedir($addableIconFolder);
		
		$this->template = preg_replace('#\{FOR_MODULES(.*?)FOR_MODULES\}#si', $forCreateableViews[0], $this->template);
		
		return $this->template;
	}
}