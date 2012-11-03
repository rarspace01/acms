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
			$tabBarDetailQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "tabbars WHERE tabbar_id = ?", array(array($this->tabBarId, "i")));
			if(count($tabBarDetailQuery->rows) > 0)
			{
				$this->tabBarDetails = $tabBarDetailQuery->rows[0];
				
				$this->tabs = array();				
				$tabBarTabsQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "tabs WHERE tabbar_id = ?", array(array($this->tabBarId, "i")));
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
			
			// create entry in _tabbars
			$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "tabbars (tabbar_id) VALUES(?)", array(array($maximumTabBarId, "i")));
			
			$this->tabBarId = $maximumTabBarId;
		}
	
		/*
		=========
		edit mode
		=========
		*/
		if($this->tabBarId >= 0)
		{
			// set the tabbar-name
			$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "tabbars SET tabbar_name = ? WHERE tabbar_id = ?", array(array($_REQUEST['tabbar_name']), array($this->tabBarId, "i")));
			
			// update tabbar tabs
			// to simplify matters, first delete all current tabs
			$this->mc->database->query("DELETE FROM " . $this->mc->config['database_pref'] . "tabs WHERE tabbar_id = ?", array(array($this->tabBarId, "i")));
			
			// now insert new tabs from POST form
			$dataIdCount = 1; // will hold the current tab-id for database
			$frontIdCount = 1; // counter for iterating over elements in frontent
			$defaultTab = 1; // iterate over all items first to find last default tab
			$maximumTabId = $_REQUEST['maximumtabid']; // the maximum frontend-tab-id (for iterating)
			
			while($frontIdCount <= $maximumTabId)
			{
				if(isset($_REQUEST['tabbar_view_' . $frontIdCount]))
				{
					// insert record in database
					$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "tabs (tabbar_id, tab_id, tab_position, tab_view, tab_icon) VALUES(?,?,?,?,?)", array(array($this->tabBarId, "i"), array($dataIdCount, "i"), array($dataIdCount, "i"), array($_REQUEST['tabbar_view_' . $frontIdCount], "i") , array($_REQUEST['tabbar_icon_' . $frontIdCount])));
					// check if this tab is set to default tab
					if(isset($_REQUEST['tabbar_view_default_' .$frontIdCount]) && $_REQUEST['tabbar_view_default_' .$frontIdCount] == 'front')
						$defaultTab = $dataIdCount;
					
						$tabbarIconPath = pathinfo($_REQUEST['tabbar_icon_' . $frontIdCount]);
						$retinaTabbarIcon = $tabbarIconPath["filename"] . "@2x." . $tabbarIconPath["extension"];
						@copy('images/tabbar/icons/' . $_REQUEST['tabbar_icon_' . $frontIdCount], $this->mc->config['upload_dir'] . '/root/tabbar/' . $_REQUEST['tabbar_icon_' .$frontIdCount]);
						if(file_exists('images/tabbar/icons/' . $retinaTabbarIcon))
						{
							@copy('images/tabbar/icons/' . $retinaTabbarIcon, $this->mc->config['upload_dir'] . '/root/tabbar/' . $retinaTabbarIcon);
						}
						
					// increment ID
					$dataIdCount++;
				}
				// increment ID
				$frontIdCount++;
			}
			// update default tab
			$this->mc->database->query("UPDATE ". $this->mc->config['database_pref'] . "tabs SET tab_default = 1 WHERE tabbar_id = ? AND tab_id = ?", array(array($this->tabBarId, "i"), array($defaultTab, "i")));
			
			// check if tabbar is currently active
			// (at least one active view that initialises the tabbar)
			$tabbarIsActive = false;
				
			// first check all views that are initialised with the old tabbar
			$viewTabbarQuery = $this->mc->database->query("SELECT view_id, view_start FROM " . $this->mc->config['database_pref'] . "views WHERE view_tabbar = ?", array(array($this->tabBarId, "i")));
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
				
			$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "tabbars SET tabbar_active = ? WHERE tabbar_id = ?", array(array($tabbarIsActive?1:0, "i"), array($this->tabBarId, "i")));
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
		$viewParentsQuery = $this->mc->database->query("SELECT A.view_id_parent AS parent_id, B.view_start AS start FROM " . $this->mc->config['database_pref'] . "view_links AS A, " . $this->mc->config['database_pref'] . "views AS B WHERE A.view_id_destination = ? AND A.view_id_parent = B.view_id", array(array($currentDestinationView, "i")));
	
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
}