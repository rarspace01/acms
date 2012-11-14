<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Hierarchy class
--
will create an array containing the app as hierarchical
structure with tabbars and navigation controllers
*/

if(!isset($configSet) OR !$configSet)
	exit();

class apdAppHierarchy
{
	public $activeTabbars;		// active tabbars / views
	public $inactiveTabbars;	// inactive tabbars (not in $activeTabbars)
	public $inactiveViews;		// inactive views (not in $activeTabbars or $inactiveTabbars)

	/**
	* function - Constructor
	* --
	* @param: $mainContainer
	*		container object for all instances
	* @return: class
	* --
	*/
	function __construct($mainContainer)
	{
		$this->mc = $mainContainer;
	}

	/**
	* function - createHierarchy
	* --
	* creates an array with the app-structure
	* --
	* @param: none
	* @return: (array) containing the app-structure that
	*			can be used to create HTML output
	* --
	*/
	function createHierarchy()
	{
		/*
		---------
		Step 1:
			Check if there are tabbars, as they are first order
			in hierarchy
		---------
		*/
	
		$tabbars = array();
		$tabbarsInactive = array();
		// get all tabbars
		$tabbarsQuery = $this->mc->database->query("SELECT tabbar_id, tabbar_name, tabbar_active FROM " . $this->mc->config['database_pref'] . "tabbars AS A WHERE 1 ORDER BY tabbar_name ASC", array(), array(array("tabbars", "tabbar_id")));
		// go through list of tabbars
		foreach($tabbarsQuery->rows as $tabbarRow)
		{
			$currentTabbar = array();
			$currentTabbar['id']		= $tabbarRow->tabbar_id;
			$currentTabbar['name']		= $tabbarRow->tabbar_name;
			$currentTabbar['active']	= $tabbarRow->tabbar_active;
				
			$currentTabbar['tabs'] = array();
				
			// get different tabs
			$tabbarTabQuery = $this->mc->database->query("SELECT tab_view, tab_default, tab_position FROM " . $this->mc->config['database_pref'] . "tabs AS A WHERE A.tabbar_id = ? ORDER BY tab_position ASC", array(array($tabbarRow->tabbar_id, "i")), array(array("tabs", "tabbar_id", "tab_id")));
			// go through list of tabs
			foreach($tabbarTabQuery->rows as $tabbarTab)
			{
				$currentTab = array();
				$currentTab['default']	= $tabbarTab->tab_default; // boolean, indicating of this tab is default
				$currentTab['tabposition'] = $tabbarTab->tab_position;
				$currentTab['tabid'] = $tabbarTab->tab_id;
				// views for this tab
				$currentTab['views'] = $this->createViewHierarchy($tabbarTab->tab_view);
				
				$currentTabbar['tabs'][] = $currentTab;
			}
			

			if($tabbarRow->tabbar_active == 1)
			{			
				$tabbars[] = $currentTabbar;
			}
			else
			{
				$tabbarsInactive[] = $currentTabbar;
			}
		}
		
		if(count($tabbars) == 0)
		{		
			/*
			---------
			Step 2:
				if there are no tabbars, load views manually
				including navigationcontrollers, child-views...
			---------
			*/
			// create dummy tabbar with invalid id
			$dummyTabbar = array();
			$dummyTabbar['id'] = -1;
			$dummyTabbar['name'] = '';
			$dummyTabbar['tabs'] = array();
			
			// one dummy tab
			$dummyTab = array();
			$dummyTab['default'] = 1;
			$dummyTab['tabid'] = -1;
			
			// get start view, as this is our starting point
			$startviewQuery = $this->mc->database->query("SELECT view_id FROM " . $this->mc->config['database_pref'] . "views AS A WHERE view_start = 1 ORDER BY view_id ASC LIMIT 0,1", array(), array(array("views", "view_id")));
			$dummyTab['views'] = $this->createViewHierarchy($startviewQuery->rows[0]->view_id);

			// add dummytab to dummytabbar
			$dummyTabbar['tabs'][] = $dummyTab;
			// add dummytabbar to list of tabbars
			$tabbars[] = $dummyTabbar;
		}
		
		$this->activeTabbars = $tabbars;
		$this->inactiveTabbars = $tabbarsInactive;
		
		/*
		---------
		Step 3:
			now add all views that are not "active" in any tabbar
		---------
		*/		
		$this->inactiveViews = array();
		$inactiveViewsQuery = $this->mc->database->query("SELECT view_id FROM " . $this->mc->config['database_pref'] . "views AS A WHERE 1", array(), array(array("views", "view_id")));
		foreach($inactiveViewsQuery->rows as $currentInactiveView)
		{
			if($this->isInactiveView($currentInactiveView->view_id))
			{
				// create dummy tabbar with invalid id
				$dummyTabbar = array();
				$dummyTabbar['id'] = -1;
				$dummyTabbar['name'] = '';
				$dummyTabbar['tabs'] = array();
					
				// one dummy tab
				$dummyTab = array();
				$dummyTab['default'] = 1;
				$dummyTab['tabid'] = -1;
				$dummyTab['views'] = $this->createViewHierarchy($currentInactiveView->view_id);
				
				// add dummytab to dummytabbar
				$dummyTabbar['tabs'][] = $dummyTab;
				// add dummytabbar to list of tabbars
				$this->inactiveViews[] = $dummyTabbar;
			}
		}
		
		return $tabbars;
	}
	
	/**
	* function - createViewHierarchy
	* --
	* from a given view-id this creates the complete hierarchy
	* including navigationcontroller, childviews
	* will avoid endless loops, too!
	* --
	* @param: (int) viewId
	* @param: (array) parents
				this contains all view-ids of the parent-views in order
				to avoid endless loops
	* @return: (array) containing the hierarchy for this view
	* --
	*/
	function createViewHierarchy($viewId, $parents=array())
	{
		// array containing information about this view and the subview
		$returnArray = array();
	
		// read out information about current view
		$viewDetailQuery = $this->mc->database->query("SELECT view_id, view_c_type, view_name, view_navigationbar, view_start FROM " . $this->mc->config['database_pref'] . "views AS A WHERE view_id = ?", array(array($viewId, "i")), array(array("views", "view_id")));
		
		// concept type (ID in database)
		$returnArray['view_type'] = $viewDetailQuery->rows[0]->view_c_type;
		// id for this view
		$returnArray['view_id'] = $viewDetailQuery->rows[0]->view_id;
		// key for translations in database
		$returnArray['view_name'] = $viewDetailQuery->rows[0]->view_name;
		// is this the start view?
		$returnArray['view_start'] = $viewDetailQuery->rows[0]->view_start;
		
		$parents[] = $viewDetailQuery->rows[0]->view_id;
		
		// now read out all children views
		$childrenQuery = $this->mc->database->query("SELECT view_id_destination FROM " . $this->mc->config['database_pref'] . "view_links AS A WHERE view_id_parent = ?", array(array($viewId, "i")), array(array("view_links", "view_id_parent", "view_id_destination")));
		if(count($childrenQuery->rows) > 0)
		{
			$returnArray['view_children'] = array();
			// go through list of children
			foreach($childrenQuery->rows as $childView)
			{
				if(!in_array($childView->view_id_destination, $parents))
				{
					$returnArray['view_children'][] = $this->createViewHierarchy($childView->view_id_destination, $parents);
				}
			}
		}
		
		// check if this view has a navigationcontroller as "parent"
		if($viewDetailQuery->rows[0]->view_navigationbar == 1)
		{
			$newParentView = array();
			$newParentView['view_type'] = 'navigation';
			$newParentView['view_id'] = -1;
			$newParentView['view_name'] = '';
			$newParentView['view_children'][] = $returnArray;
			$returnArray = $newParentView;
		}
		
		return $returnArray;
	}
	
	/**
	* function - isInactiveView
	* --
	* will return if the current view is really inactive, hence
	* not mentioned in $this->activeTabbars or $this->inactiveTabbars
	* NOTE: if view is a childview of a view already in $this->inactiveViews
	* this function will return false!
	* --
	* @param: (int) $viewId
	* @param: (array) $searchArray
	*		the array of views to be searched
	* @return: (boolean)
	* --
	*/
	function isInactiveView($viewId, $searchArray="initialise")
	{
		/*
		======================
		case 1: starting point
		======================
		*/
		if($searchArray == "initialise")
		{			
			// check for view occurence in ACTIVE (pseudo)tabbars
			foreach($this->activeTabbars as $activeTabbar)
			{
				foreach($activeTabbar['tabs'] as $activeTabViews)
				{
					if(!$this->isInactiveView($viewId, $activeTabViews['views']))
					{
						return false;
					}
				}
			}
			// check for view occurence in INACTIVE (pseudo)tabbars
			foreach($this->inactiveTabbars as $inactiveTabbar)
			{
				foreach($inactiveTabbar['tabs'] as $inactiveTabViews)
				{
					if(!$this->isInactiveView($viewId, $inactiveTabViews['views']))
						return false;
				}
			}
			
			// case with no tabbars at all
			//return !($this->checkForParentsPath($viewId));
			
			// trivial case: if not yet occured and no inactive views yet,
			// it is an inactive view
			if(count($this->inactiveViews) == 0)
				return true;
			// otherwise go through list of pseudo-tabbars for inactive views
			foreach($this->inactiveViews as $inactiveViewPseudoTabbars)
			{
				foreach($inactiveViewPseudoTabbars['tabs'] as $inactiveTabViews)
				{
					if(!$this->isInactiveView($viewId, $inactiveTabViews['views']))
						return false;
				}
			}
			// if we got til here, it is an inactive view
			return true;
		}
		
		/*
		======================
		case 2: view hierarchy
		======================
		*/
		if($searchArray == null)
			return true;
		
		// view found, not inactive!
		if($searchArray['view_id'] == $viewId)
		{
			return false;
		}
		// we hit the bottom, view not found
		if(!isset($searchArray['view_children']) || count($searchArray['view_children']) == 0)
		{
			return true;
		}
	
		$result = true;
		foreach($searchArray['view_children'] as $currentChild)
		{
			if(is_array($currentChild))
			{
				// otherwise, search deeper
				if(!$this->isInactiveView($viewId, $currentChild))
				{	
					return false;
				}
			}
		}
		return true;
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
}

?>