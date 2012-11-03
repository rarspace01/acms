<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Navigation class
--
will present the app structure as hierarchical
view in the navigation (left)
*/

if(!isset($configSet) OR !$configSet)
	exit();

class apdNavigation
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
		$this->mc = $mainContainer;
	}
	
	function createStructureOutput()
	{
		// get the template for the lefthandside navigation
		$this->template = $this->mc->template->loadNavigationTpl();
		// create the hierarchy for the app
		$appHierarchyActiveTabbars = $this->mc->appHierarchy->createHierarchy();
		$appHierarchyInactiveTabbars = $this->mc->appHierarchy->inactiveTabbars;
		$appHierarchyInactiveViews = $this->mc->appHierarchy->inactiveViews;
		
		// get "iterator"-template for all items
		preg_match_all('#\{FOR_ITEMS(.*?)FOR_ITEMS\}#si', $this->template, $forNavigationItems);
		preg_match_all('#\{DIVIDER(.*?)DIVIDER\}#si', $this->template, $forNavigationItemsDivider);
		
		$appStructureOutput = $forNavigationItemsDivider[1][0];
		
		// go through all tabbars that exist
		foreach($appHierarchyActiveTabbars as $currentTabbar)
		{
			// check if legit tabbar
			if($currentTabbar['id'] != -1 && trim($currentTabbar['name']) != '')
			{
				// create output for a tabbar
				$appStructureOutput .= $this->createTabbarOutput($currentTabbar, $forNavigationItems[1][0]);
			}
			else
			{
				// otherwise directly generate output for the view, starting from default view
				$appStructureOutput .= $this->createViewHierarchy($currentTabbar['tabs'][0]['views'], $forNavigationItems[1][0], true, true);
			}
		}
		$appStructureOutput .= $forNavigationItemsDivider[1][0];
		
		// go through all inactive tabbars that exist
		foreach($appHierarchyInactiveTabbars as $currentTabbar)
		{
			// no check for "legit tabbar" needed here, because they are explicitly tabbars
			// create output for a tabbar
			$appStructureOutput .= $this->createTabbarOutput($currentTabbar, $forNavigationItems[1][0]);
		}
		if(count($appHierarchyInactiveTabbars) > 0)
			$appStructureOutput .= $forNavigationItemsDivider[1][0];
		
		// go through all inactive views that exists
		foreach($appHierarchyInactiveViews as $currentTabbar)
		{
			// we know that these are just normal views and no tabbars, so directly create view-hierarchy
			$appStructureOutput .= $this->createViewHierarchy($currentTabbar['tabs'][0]['views'], $forNavigationItems[1][0], true, false);
		}
		if(count($appHierarchyInactiveViews) > 0)
			$appStructureOutput .= $forNavigationItemsDivider[1][0];
		
		$this->template = preg_replace('#\{FOR_ITEMS(.*?)FOR_ITEMS\}#si', $appStructureOutput, $this->template);
		$this->template = preg_replace('#\{DIVIDER(.*?)DIVIDER\}#si', '', $this->template);
		
		return $this->template;
	}
	
	function createTabbarOutput($tabbarData, $itemTemplate)
	{
		$originalTemplate = preg_replace('#\n\t#si', "\n				", $itemTemplate);
	
		/*
		==================
		details for tabbar
		==================
		*/
		// item type to display "TabBar" (visible as hover-title)
		$itemTemplate = preg_replace('#\{ITEM_TYPE\}#si', $this->mc->language->getLocalisation('tabbar'), $itemTemplate);
		// style type used for eventually extra css definitions
		$itemTemplate = preg_replace('#\{ITEM_STYLE_TYPE\}#si', 'tabbar', $itemTemplate);
		// concept type, used for GET-parameter for edit-link
		$itemTemplate = preg_replace('#\{ITEM_CONCEPT_TYPE\}#si', 'tabbar', $itemTemplate);
		// item id, used for GET-parameter for edit-link
		$itemTemplate = preg_replace('#\{ITEM_ID\}#si', $tabbarData['id'], $itemTemplate);
		// used for hiding/showing children of this element (javascript element-id)
		$itemTemplate = preg_replace('#\{ITEM_CHILDREN_ID\}#si', 'tabbar_' . $tabbarData['id'] . '_children', $itemTemplate);
		$itemTemplate = preg_replace('#\{UNIQUE\}#si', uniqid(), $itemTemplate);
		// name of this tabbar (only for user in backend, will not be visible in the app later on)
		$tabbarName = $tabbarData['name'];
		// add status icon for tabbar
		$tabbarName .= ' <img src="templates/' . $this->mc->config['template'] . '/grafix/';
		$tabbarName .= ($tabbarData['active'] == 1) ? 'ok.png" ' : 'disabled.png" ';
		$tabbarName .= 'title="' . $this->mc->language->getLocalisation('tabbar_' . (($tabbarData['active'] == 1)?'active':'inactive')) . '" />';
		$itemTemplate = preg_replace('#\{ITEM_NAME\}#si', $tabbarName, $itemTemplate);
		
		$tabbar_children = "";
		
		/*
		====================
		tabs for this tabbar
		====================
		*/
		foreach($tabbarData['tabs'] as $currentTab)
		{
			// item type to display "Tab"  (visible as hover-title)
			$tabTemplate = preg_replace('#\{ITEM_TYPE\}#si', $this->mc->language->getLocalisation('tab'), $originalTemplate);
			// css definition class
			$tabTemplate = preg_replace('#\{ITEM_STYLE_TYPE\}#si', 'tab', $tabTemplate);
			// javascript DOM id
			$tabTemplate = preg_replace('#\{ITEM_CHILDREN_ID\}#si', 'tabbar_' . $tabbarData['id'] . '_tab_' . $currentTab['tabposition'] . '_children', $tabTemplate);
			$itemTemplate = preg_replace('#\{UNIQUE\}#si', uniqid(), $itemTemplate);
			
			// concept type of the root-view of this tab (GET parameter for edit link)
			// first get view-id of the view
			$tabViewType = $currentTab['views']['view_type'];
			$tabViewId = $currentTab['views']['view_id'];
			// if root view is a navigationcontroller, get first child
			if($currentTab['views']['view_type'] == 'navigation')
			{
				$tabViewType = $currentTab['views']['view_children']['view_type'];
				$tabViewId = $currentTab['views']['view_children']['view_id'];
			}
			// load concept from database
			$conceptViewQuery = $this->mc->database->query("SELECT concept_key FROM " . $this->mc->config['database_pref'] . "concepts WHERE concept_id = ?", array(array($tabViewType, "i")));
			// insert concept type (GET parameter for edit link)
			$tabTemplate = preg_replace('#\{ITEM_CONCEPT_TYPE\}#si', $conceptViewQuery->rows[0]->concept_key, $tabTemplate);
			// view-id of the root-view of this tab (GET parameter for edit link)
			$tabTemplate = preg_replace('#\{ITEM_ID\}#si', $tabViewId, $tabTemplate);
			// name, equals the position of this tab
			$tabName = 'Tab ' . $currentTab['tabposition'];
			if($currentTab['default'] == 1)
				$tabName = '<b>' . $tabName . '</b>';
			$tabTemplate = preg_replace('#\{ITEM_NAME\}#si', $tabName, $tabTemplate);
		
			/*
			=============================
			children / views for this tab
			=============================
			*/
			$originalTemplate = preg_replace('#\n\t#si', "\n				", $originalTemplate);
			$tabTemplate = preg_replace('#\{ITEM_CHILDREN\}#si', $this->createViewHierarchy($currentTab['views'], $originalTemplate), $tabTemplate);
			
			$tabbar_children .= $tabTemplate;
		}
		
		// insert all tabs
		$itemTemplate = preg_replace('#\{ITEM_CHILDREN\}#si', $tabbar_children, $itemTemplate);
		// item is no leaf
		$itemTemplate = preg_replace('#\{NO_LEAF|NO_LEAF\}#si', '', $itemTemplate);
		$itemTemplate = preg_replace('#\{ITEM_LEAF\}#si', '', $itemTemplate);
		
		return $itemTemplate;
	}
	
	function createViewHierarchy($viewsArray, $itemTemplate, $addStatusIcon=false, $activeView=true)
	{
		$originalTemplate = $itemTemplate;
		
		// case 1: top view is a navigationbar
		if($viewsArray['view_type'] == 'navigation' && $viewsArray['view_id'] == -1)
		{
			// shown item type (NavigationBar) (visible as hover-title)
			$itemTemplate = preg_replace('#\{ITEM_TYPE\}#si', $this->mc->language->getLocalisation('navigationbar'), $originalTemplate);
			// css class definition
			$itemTemplate = preg_replace('#\{ITEM_STYLE_TYPE\}#si', 'navigationbar', $itemTemplate);
			// javascript DOM id
			$itemTemplate = preg_replace('#\{ITEM_CHILDREN_ID\}#si', 'navigationbar_' . $viewsArray['view_children'][0]['view_id'] . '_children', $itemTemplate);
			$itemTemplate = preg_replace('#\{UNIQUE\}#si', uniqid(), $itemTemplate);
			// item-id, for GET parameter (edit link)
			$itemTemplate = preg_replace('#\{ITEM_ID\}#si', $viewsArray['view_children'][0]['view_id'], $itemTemplate);
			// load concept from database
			$conceptViewQuery = $this->mc->database->query("SELECT concept_key FROM " . $this->mc->config['database_pref'] . "concepts WHERE concept_id = ?", array(array($viewsArray['view_children'][0]['view_type'], "i")));
			// insert concept type (GET parameter for edit link)
			$itemTemplate = preg_replace('#\{ITEM_CONCEPT_TYPE\}#si', $conceptViewQuery->rows[0]->concept_key, $itemTemplate);
			// name, equals type (no special name for navigationbars)
			$itemName = $this->mc->language->getLocalisation('navigationbar');
			if($addStatusIcon)
			{
				// add status icon for tabbar
				$itemName .= ' <img src="templates/' . $this->mc->config['template'] . '/grafix/';
				$itemName .= ($activeView) ? 'ok.png" ' : 'disabled.png" ';
				$itemName .= 'title="' . $this->mc->language->getLocalisation('view_' . (($activeView)?'active':'inactive')) . '" />';
			}
			$itemTemplate = preg_replace('#\{ITEM_NAME\}#si', $itemName, $itemTemplate);
			
			/*
			=================================
			create children for navigationbar
			=================================
			*/
			$originalTemplate = preg_replace('#\n\t#si', "\n				", $originalTemplate);
			$itemTemplate = preg_replace('#\{ITEM_CHILDREN\}#si', $this->createViewHierarchy($viewsArray['view_children'][0], $originalTemplate), $itemTemplate);
			$itemTemplate = preg_replace('#\{NO_LEAF|NO_LEAF\}#si', '', $itemTemplate);
			$itemTemplate = preg_replace('#\{ITEM_LEAF\}#si', '', $itemTemplate);
		}
		// case 2: it is a normal view
		else
		{
			// load concept-view key for this view
			$conceptViewQuery = $this->mc->database->query("SELECT concept_key FROM " . $this->mc->config['database_pref'] . "concepts WHERE concept_id = ?", array(array($viewsArray['view_type'], "i")));
			// item type (visible as hover-title)
			$itemTemplate = preg_replace('#\{ITEM_TYPE\}#si', $this->mc->language->getLocalisation($conceptViewQuery->rows[0]->concept_key), $originalTemplate);
			// concept type, for GET parameter (edit link)
			$itemTemplate = preg_replace('#\{ITEM_CONCEPT_TYPE\}#si', $conceptViewQuery->rows[0]->concept_key, $itemTemplate);
			// css class definition
			$itemTemplate = preg_replace('#\{ITEM_STYLE_TYPE\}#si', $conceptViewQuery->rows[0]->concept_key, $itemTemplate);
			// javascript DOM id
			$itemTemplate = preg_replace('#\{ITEM_CHILDREN_ID\}#si', $conceptViewQuery->rows[0]->concept_key . '_' . $viewsArray['view_id'] . '_children', $itemTemplate);
			$itemTemplate = preg_replace('#\{UNIQUE\}#si', uniqid(), $itemTemplate);
			// item-id for GET parameter (edit link)
			$itemTemplate = preg_replace('#\{ITEM_ID\}#si', $viewsArray['view_id'], $itemTemplate);
			// name of this view
			$itemName = $this->mc->language->getLocalisation($viewsArray['view_name']);
			if($addStatusIcon)
			{
				// add status icon for tabbar
				$itemName .= ' <img src="templates/' . $this->mc->config['template'] . '/grafix/';
				$itemName .= ($activeView) ? 'ok.png" ' : 'disabled.png" ';
				$itemName .= 'title="' . $this->mc->language->getLocalisation('view_' . (($activeView)?'active':'inactive')) . '" />';
			}
			if($viewsArray['view_start'] == 1)
			{
				$itemName = '<b>' . $itemName . '</b>';
			}
			$itemTemplate = preg_replace('#\{ITEM_NAME\}#si', $itemName, $itemTemplate);
			
			
			/*
			=====================
			check if it is a leaf
			=====================
			*/
			if(isset($viewsArray['view_children']) && count($viewsArray['view_children']) > 0)
			{
				$originalTemplate = preg_replace('#\n\t#si', "\n				", $originalTemplate);
				$allChildren = "";
				foreach($viewsArray['view_children'] as $currentChild)
				{
					$allChildren .= $this->createViewHierarchy($currentChild, $originalTemplate);
				}
				$itemTemplate = preg_replace('#\{ITEM_CHILDREN\}#si', $allChildren, $itemTemplate);
				$itemTemplate = preg_replace('#\{NO_LEAF|NO_LEAF\}#si', '', $itemTemplate);
				$itemTemplate = preg_replace('#\{ITEM_LEAF\}#si', '', $itemTemplate);
			}
			else
			{
				$itemTemplate = preg_replace('#\{NO_LEAF(.*?)NO_LEAF\}#si', '', $itemTemplate);
				$itemTemplate = preg_replace('#\{ITEM_LEAF\}#si', 'navigation_item_leaf', $itemTemplate);
			}
		}
		
		return $itemTemplate;
	}
}
?>