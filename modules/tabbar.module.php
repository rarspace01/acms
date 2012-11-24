<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Processing a submitted form for tabbars
*/

if(!isset($configSet) OR !$configSet)
	exit();

/**
* function - initCurrentModule
* --
* in order to init this view dynamically, this function is needed
* which returns an instance without the caller knowing the class-name.
* --
* @param: $mainContainer
*		container that contains all instances
* @return: class
* --
*/
function initCurrentModule($mainContainer)
{
	// check if a view-id was given
	// will call parent constructor!
	return new apdModuleTabBar($mainContainer,
		(
		(isset($_REQUEST['view_id']) && intval($_REQUEST['view_id']) >= 0)
			? intval($_REQUEST['view_id']) : -1
		));
}

class apdModuleTabBar
{
	/**
	* function - Constructor
	* --
	* @param: $mainContainer
	*		container that contains all instances
	* @return: class
	* --
	*/
	function __construct($mainContainer, $tabBarId=-1)
	{
		$this->mc		= $mainContainer;
		$this->tabBarId	= intval($tabBarId);
		
		// load tabbar details and tabs
		if($this->tabBarId >= 0)
		{
			$tabBarDetailQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "tabbars AS A WHERE tabbar_id = ?", array(array($this->tabBarId, "i")), array(array("tabbars", "tabbar_id")));
			if(count($tabBarDetailQuery->rows) > 0)
			{
				$this->tabBarDetails = $tabBarDetailQuery->rows[0];
				
				$this->tabs = array();				
				$tabBarTabsQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "tabs AS A WHERE tabbar_id = ?", array(array($this->tabBarId, "i")), array(array("tabs", "tabbar_id", "tab_id")));
				foreach($tabBarTabsQuery->rows as $currentTab)
				{
					$this->tabs[] = $currentTab;
				}
				
			}
		}
	}	
	
	/**
	* function - processForm
	* --
	* process a GET or POST request
	* --
	* @param: none
	* @return: none
	* --
	*/
	function processForm()
	{
			
		$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "revisions SET revision_active = max_revision + 1, max_revision = max_revision + 1");
		$maximumRevisionIdQuery = $this->mc->database->query("SELECT max_revision FROM " . $this->mc->config['database_pref'] . "revisions");
		$this->mc->config['current_revision'] = $maximumRevisionIdQuery->rows[0]->max_revision;
			
		/*
		===========
		insert mode
		===========
		*/
		if($this->tabBarId < 0)
		{
			// get new tabbar-id
			$maximumTabBarIdQuery = $this->mc->database->query("SELECT MAX(tabbar_id) AS max_id FROM " . $this->mc->config['database_pref'] . "tabbars", array());
			$maximumTabBarId = $maximumTabBarIdQuery->rows[0]->max_id + 1;
			
			$this->tabBarId = $maximumTabBarId;
		}
	
		/*
		=========
		edit mode
		=========
		*/
		if($this->tabBarId >= 0)
		{
			$this->cleanUpDirectory();
		
		
			// create entry in _tabbars
			$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "tabbars (tabbar_id, tabbar_name, revision) VALUES(?, ?, ?)", array(array($this->tabBarId, "i"), array($_REQUEST['tabbar_name']), array($this->mc->config['current_revision'], "i")));
			
			// now insert new tabs from POST form
			$dataIdCount = 1; // will hold the current tab-id for database
			$frontIdCount = 1; // counter for iterating over elements in frontent
			$defaultTab = 1; // iterate over all items first to find last default tab
			$maximumTabId = $_REQUEST['maximumtabid']; // the maximum frontend-tab-id (for iterating)
			
			while($frontIdCount <= $maximumTabId)
			{
				if(isset($_REQUEST['tabbar_view_' . $frontIdCount]))
				{
					$tabbarIconPath = pathinfo($_REQUEST['tabbar_icon_' . $frontIdCount]);
					$normalTabbarIcon = $tabbarIconPath["filename"] . "." . $tabbarIconPath["extension"];
					$retinaTabbarIcon = $tabbarIconPath["filename"] . "@2x." . $tabbarIconPath["extension"];
					
					@copy('images/tabbar/' . $_REQUEST['tabbar_icon_' . $frontIdCount], $this->mc->config['upload_dir'] . '/root/tabbar/' . $normalTabbarIcon);
					if(file_exists('images/tabbar/' . $tabbarIconPath["dirname"] . '/' . $retinaTabbarIcon))
					{
						@copy('images/tabbar/' . $tabbarIconPath["dirname"] . '/' . $retinaTabbarIcon, $this->mc->config['upload_dir'] . '/root/tabbar/' . $retinaTabbarIcon);
					}
					
					// insert record in database
					$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "tabs (tabbar_id, tab_id, tab_position, tab_view, tab_icon, revision) VALUES(?,?,?,?,?,?)", array(array($this->tabBarId, "i"), array($dataIdCount, "i"), array($dataIdCount, "i"), array($_REQUEST['tabbar_view_' . $frontIdCount], "i") , array($normalTabbarIcon), array($this->mc->config['current_revision'], "i")));
					// check if this tab is set to default tab
					if(isset($_REQUEST['tabbar_view_default_' .$frontIdCount]) && $_REQUEST['tabbar_view_default_' .$frontIdCount] == 'front')
					{
						$defaultTab = $dataIdCount;
					}
					
					// increment ID
					$dataIdCount++;
				}
				// increment ID
				$frontIdCount++;
			}
			// update default tab
			$this->mc->database->query("UPDATE ". $this->mc->config['database_pref'] . "tabs SET tab_default = 1 WHERE tabbar_id = ? AND tab_id = ?", array(array($this->tabBarId, "i"), array($defaultTab, "i")), array(array("tabs", "tabbar_id", "tab_id")));
			
			// check if tabbar is currently active
			// (at least one active view that initialises the tabbar)
			$tabbarIsActive = false;
				
			// first check all views that are initialised with the old tabbar
			$viewTabbarQuery = $this->mc->database->query("SELECT view_id, view_start FROM " . $this->mc->config['database_pref'] . "views AS A WHERE view_tabbar = ?", array(array($this->tabBarId, "i")), array(array("views", "view_id")));
			foreach($viewTabbarQuery->rows as $currentViewTabbar)
			{
				// now check if this view is active (somehow accessible from the startpage)
					
				// case 1: this view is the startpage already
				if($currentViewTabbar->view_start == 1)
				{
					$tabbarIsActive = true; break;
				}
					
				// case 2: not startpage, check for accessibility
				// now we need to check the _view_links table for parents
				// until we hit the root-element (start page)
				$tabbarIsActive = $this->checkForParentsPath($currentViewTabbar->view_id);
				if($tabbarIsActive)
					break;
			}
				
			$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "tabbars AS A SET tabbar_active = ? WHERE tabbar_id = ?", array(array($tabbarIsActive?1:0, "i"), array($this->tabBarId, "i")), array(array("tabbars", "tabbar_id")));
		}
		
		// re-create main xml file and refresh filelist
		$this->mc->filecreator->createGeneralFiles();
		$configSet = true;
		include('modules/filemanager.module.php');
		$fileManagerObj = new apdModuleFilemanager($this->mc);
		$fileManagerObj->refreshFilelist();
		
		header("Location: index.php?m=tabbar&view_id=" . $this->tabBarId);
	}
	
	/**
	* function - checkForParentsPath
	* --
	* checks if there exists a path from the current view
	* to the starting page. Used for checking if a tabbar is
	* (still) active
	* --
	* @param: $currentDestinationView - the current view to check
	* @return: (boolean)
			does a path exist?
	* --
	*/
	function checkForParentsPath($currentDestinationView)
	{
		$viewParentsQuery = $this->mc->database->query("SELECT A.view_id_parent AS parent_id, B.view_start AS start FROM " . $this->mc->config['database_pref'] . "view_links AS A, " . $this->mc->config['database_pref'] . "views AS B WHERE A.view_id_destination = ? AND A.view_id_parent = B.view_id", array(array($currentDestinationView, "i")), array(array("view_links", "view_id_parent", "view_id_destination"), array("views", "view_id")));
	
		// if current view does not have any parents its a dead end
		if(count($viewParentsQuery->rows) == 0)
			return false;
			
		// otherwise check for parents
		foreach($viewParentsQuery->rows as $viewParent)
		{
			// case 1: this parent is the starting page
			if($viewParent->start == 1)
				return true;
				
			// otherwise, search recursively
			return checkForParentsPath($viewParent->parent_id);
		}
	}
	
	function cleanUpDirectory()
	{
		$filePath = $this->mc->config['upload_dir'] . 'root/tabbar/';
		if($tabbarIconFolderHandle = opendir($filePath))
		{
			while (false !== ($currentTabbarIcon = readdir($tabbarIconFolderHandle)) )
			{
				if(!preg_match('#^\.|\.\.|/+|\\\\+$#si', $currentTabbarIcon))
				{
					unlink($filePath . $currentTabbarIcon);
				}
			}
		}
		closedir($tabbarIconFolderHandle);
	}
}