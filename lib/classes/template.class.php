<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Template class
*/
	
class apdTemplate
{
	public $template;

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
		$this->mc = $mainContainer;
		
		// state variables for components
		$this->headerLoaded = false;
		$this->footerLoaded = false;
		$this->navigationLoaded = false;
		$this->contentLoaded = false;
	}
	
	/**
	* function - initTemplate
	* --
	* initialise the template, start the output-buffer.
	* --
	* @param: none
	* @return: none
	* --
	* NOTICE:	somewhere else the output-buffer should be
	*		flushed or the buffering should be ended!
	*/
	function initTemplate()
	{
		ob_start();
		include('templates/' . $this->mc->config['template'] . '/main.html');
		$this->template = ob_get_contents();
		ob_clean();
	}
	
	/**
	* function - loadHeader
	* --
	* load the header-template
	* --
	* @param: $path
	*		the path to the header-template, if some module-specific
	*		header should be used.
	* @return: none
	* --
	*/
	function loadHeader($path='body_default')
	{
		include('templates/' . $this->mc->config['template'] . '/' . $path . '/header.html');
		$header_template = ob_get_contents();
		$this->template = preg_replace('#\{HEADER\}#si', $header_template, $this->template);
		ob_clean();
		
		$this->headerLoaded = true;
	}
	
	/**
	* function - loadFooter
	* --
	* load the footer-template
	* --
	* @param: $path
	*		the path to the footer-template, if some module-specific
	*		footer should be used.
	* @return: none
	* --
	*/
	function loadFooter($path='body_default')
	{
		include('templates/' . $this->mc->config['template'] . '/' . $path . '/footer.html');
		$footer_template = ob_get_contents();
		$this->template = preg_replace('#\{FOOTER\}#si', $footer_template, $this->template);
		ob_clean();
		
		$this->footerLoaded = true;
	}
	
	/**
	* function - loadNavigationTpl
	* --
	* load the navigation-template
	* --
	* @param: $path
	*		the path to the navigation-template, if some module-specific
	*		navigation should be used.
	* @return: (String) the template for the navigation
	* --
	*/
	function loadNavigationTpl($path='body_default')
	{
		include('templates/' . $this->mc->config['template'] . '/' . $path . '/appstructure.html');
		$navigation_template = ob_get_contents();
		ob_clean();
		
		return $navigation_template;
	}
	
	/**
	* function - applyFilters
	* --
	* apply several filters on the template file,
	* for example the language filter which replaces all
	* {LANG:xxxx} occurences
	* --
	* @param: none
	* @return: (String) the template with applied filters
	*		(suitable replacements)
	* --
	*/
	function applyFilters()
	{
		// in case user is not logged in
		$this->template = preg_replace('#\{PERMISSION:guest\}(.*?){PERMISSION}#si', ($this->mc->config['user_rank'] == -1 ? "$1" : ""), $this->template);
		// replace other permission-variables
		$this->template = preg_replace('#\{PERMISSION:(.+?)\}(.*?){PERMISSION}#sie', '$this->mc->permissions->checkPermissionTemplate("$1", "$2")', $this->template);
		// replace language-variables
		$this->template = preg_replace('#\{LANG:(.+?)\}#sie', '$this->mc->language->getLocalisation("$1")', $this->template);
		// replace language-variables
		$this->template = preg_replace('#\{CONFIG_UPLOADDIR\}#si', $this->mc->config['upload_dir'], $this->template);
	}
	
	/**
	* function - prepareTemplate
	* --
	* prepares the template for output. This method checks if all
	* components have been loaded and loads them manually otherwise.
	* --
	* @param: none
	* @return: (String) template
	* --
	*/
	function prepareTemplate()
	{
		if(!$this->headerLoaded)
			$this->loadHeader();
		if(!$this->footerLoaded)
			$this->loadFooter();

		$this->applyFilters();
			
		return $this->template;
	}
}
?>