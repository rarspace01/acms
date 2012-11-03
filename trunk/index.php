<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Index class
*/

//error_reporting(E_ALL);

/*
========
including files
========
*/

$configSet = true;

// config-file
include('includes/config.php');
include('lib/classes/apphierarchy.class.php');	// creates the app hierarchy
include('lib/classes/database.class.php');		// database connection
include('lib/classes/devicetypes.class.php');	// handling different devicetypes
include('lib/classes/filecreator.class.php');	// creates the xml-files for output
include('lib/classes/language.class.php');		// language support
include('lib/classes/logger.class.php');		// logger
include('lib/classes/navigation.class.php');	// creates the navigation / structure of the app
include('lib/classes/template.class.php');		// template engine


class adpMainContainer
{
	public $appHierarchy;
	public $database;
	public $devicetypes;
	public $filecreator;
	public $language;
	public $logger;
	public $navigation;
	public $template;
	
	public $config;
	
	function adpMainContainer($config)
	{
		$this->config = $config;
		
		/*
		==============
		initialisation
		==============
		*/
		// apphierarchy generates the structure of the app
		$this->appHierarchy	= new apdAppHierarchy($this);
		// init the database and connect
		$this->database		= new apdDatabase($this);
		// handling different devicetypes
		$this->devicetypes	= new apdDeviceTypes($this);
		// creates the xml-files for the final app
		$this->filecreator	= new apdFileCreator($this);
		// multi language support
		$this->language		= new apdLanguage($this);
		// default logger for errors
		$this->logger		= new apdLogger();
		// navigation, creating the visible app hierarchy for navigation
		$this->navigation	= new apdNavigation($this);
		// template engine
		$this->template		= new apdTemplate($this);
	}
}

/*
==================================
init container and template engine
==================================
*/
$mainContainer = new adpMainContainer($config);
$mainContainer->template->initTemplate();

/*
=====================================
load respective current active module
=====================================
*/

// module and parameter
$currentModule = 'home';

if(isset($_REQUEST['m']) && file_exists('lib/views/' . basename($_REQUEST['m']) . '.view.php'))
{
	$currentModule = basename($_REQUEST['m']);
}

/*
===============
form processing
===============
*/
if(isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'form')
{
	// check if processing module exists
	if(file_exists('modules/' . $currentModule . '.module.php'))
	{
		include('modules/' . $currentModule . '.module.php');
		if(function_exists('initCurrentModule'))
		{
			// initialise an instance of the processing class
			$currentProcessingModule = initCurrentModule($mainContainer);
			// process the current form
			$currentProcessingModule->processForm();
		}
	}
	
	// TODO: update files, (Localisable.string, xml-files...)
	// needs a merge with "filemanager" here...
}

/*
==========================
create the navigation menu
==========================
*/
$mainContainer->template->template = preg_replace('#\{NAVIGATION\}#si', $mainContainer->navigation->createStructureOutput(), $mainContainer->template->template);
$mainContainer->template->navigationLoaded = true;


/*
=============
view (output)
=============
*/
include('lib/views/' . $currentModule . '.view.php');
if(function_exists('initCurrentView'))
{
	$currentViewModule = initCurrentView($mainContainer);
	$currentViewModule->initTemplate();	
	$mainContainer->template->template = preg_replace('#\{CONTENT\}#si', $currentViewModule->printTemplate(), $mainContainer->template->template);

}

// prepare the template for presentation
echo $mainContainer->template->prepareTemplate();
?>