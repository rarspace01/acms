<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

View class for display Home
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
	return new apdViewHome($mainContainer);
}

class apdViewHome implements apdIView
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
		include('templates/' . $this->mc->config['template'] . '/modules/home/home.html');
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
		return $this->template;
	}
}