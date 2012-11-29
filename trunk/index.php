<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Index class
*/

error_reporting(E_ALL);

/*
========
including files
========
*/


$configSet = true;

// config-file
include('includes/config.php');

// classes
include('lib/classes/apphierarchy.class.php');	// creates the app hierarchy
include('lib/classes/database.class.php');		// database connection
include('lib/classes/devicetypes.class.php');	// handling different devicetypes
include('lib/classes/filecreator.class.php');	// creates the xml-files for output
include('lib/classes/language.class.php');		// language support
include('lib/classes/logger.class.php');		// logger
include('lib/classes/navigation.class.php');	// creates the navigation / structure of the app
include('lib/classes/permissions.class.php');	// handles permissions for users
include('lib/classes/template.class.php');		// template engine

// interfaces and abstract classes
include('modules/basicmodule.module.php');
include('modules/ifilecreator.module.php');

class adpMainContainer
{
	public $appHierarchy;
	public $database;
	public $devicetypes;
	public $filecreator;
	public $language;
	public $logger;
	public $navigation;
	public $permissions;
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
		// handles permissions for users
		$this->permissions	= new apdPermissions($this);
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

$mainContainer->config['user_rank'] = -1;
if(isset($_COOKIE[$config['user_cookie'] . 'userid']) && trim($_COOKIE[$config['user_cookie'] . 'userid']) != '')
{
	$selectUserRank = $mainContainer->database->query("SELECT `group_id`, `user_passkey` FROM `" . $config['database_pref'] . "users` WHERE `user_id` = ?", array(array($_COOKIE[$config['user_cookie'] . 'userid'])));
	if(count($selectUserRank->rows) > 0) // check if user is existing anyway
	{
		// if password in database is equal to password in cookie, set user-rank
		if($_COOKIE[$config['user_cookie'] . 'passkey'] === $selectUserRank->rows[0]->user_passkey)
		{
			// save rank from database in config-array
			$mainContainer->config['user_rank'] = $selectUserRank->rows[0]->group_id;
		}
	}
}
if($mainContainer->config['user_rank'] == -1 && isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'login')
{
	$getPasswordFromDB = $mainContainer->database->query("SELECT `user_id`, `group_id`, `user_passkey` FROM `" . $config['database_pref'] . "users` WHERE `user_name` = ?", array(array($_REQUEST['loginname'])));
	// if password is equal with sha1-hash from DB, set cookie
	if(sha1($config['user_pw_salt'] . sha1($_REQUEST['loginpassword'])) == $getPasswordFromDB->rows[0]->user_passkey)
	{ // cookie is valid for 1 year
		setcookie($config['user_cookie'] . 'userid', $getPasswordFromDB->rows[0]->user_id, (time() + 60*60*24*365)); // username
		setcookie($config['user_cookie'] . 'passkey', $getPasswordFromDB->rows[0]->user_passkey, (time() + 60*60*24*365)); // sha1-hash with password
		setcookie($config['user_cookie'] . 'logindate', time(), (time() + 60*60*24*365)); // login-date
	}
	header("Location: index.php");
}
if(isset($_REQUEST['submit']) && $_REQUEST['submit'] == 'logout')
{
	$currentModule = 'home';
	$mainContainer->config['user_rank'] = -1;
}

if($mainContainer->config['user_rank'] == -1 && $currentModule != 'login')
{
	setcookie($config['user_cookie'] . 'userid', '', (time() - 60*60*24*365));
	setcookie($config['user_cookie'] . 'passkey', '', (time() - 60*60*24*365));
	
	// if filelist should be downloaded from app, do not forward to login-page,
	// but put HTTP statuscode/error 500 in header
	if(isset($_REQUEST['m']) && $_REQUEST['m'] == 'filemanager' && isset($_REQUEST['type']) && ($_REQUEST['type'] == 'filelist' || $_REQUEST['type'] == 'getfile'))
	{
		header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
		exit();
	}
	else
	{
		// go to login screen
		header("Location: index.php?m=login");
	}
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
echo stripslashes($mainContainer->template->prepareTemplate());
?>