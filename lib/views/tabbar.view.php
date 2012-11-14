<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

View class for display tabbars
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
	return new apdViewTabBar($mainContainer,
		(
		(isset($_REQUEST['view_id']) && intval($_REQUEST['view_id']) >= 0)
			? intval($_REQUEST['view_id']):-1
		));
}

class apdViewTabBar implements apdIView
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
				$tabBarTabsQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "tabs AS A WHERE tabbar_id = ? ORDER BY tab_position ASC", array(array($this->tabBarId, "i")), array(array("tabs", "tabbar_id", "tab_id")));
				foreach($tabBarTabsQuery->rows as $currentTab)
				{
					$this->tabs[] = $currentTab;
				}
				
			}
		}
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
		include('templates/' . $this->mc->config['template'] . '/modules/tabbar/tabbar.html');
		$this->template = ob_get_contents();
		ob_clean();
		
		$this->loadListOfViews();
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
		// default infos
		$this->template = preg_replace('#\{VIEWID\}#si', $this->tabBarId, $this->template);
		$this->template = preg_replace('#\{TABBAR_NAME\}#si', $this->tabBarDetails->tabbar_name, $this->template);
		
		/*
		=============
		list of views
		=============
		*/
		preg_match_all('#\{FOR_VIEWS(.*?)FOR_VIEWS\}#si', $this->template, $forTabViews);
		$forTabViews[0] = "";
		for($i = 0; $i < count($this->viewList); $i++)
		{
			$currentView = $this->viewList[$i];
			$currentViewTpl = preg_replace('#\{VIEW_ID\}#si', $currentView['view_id'], $forTabViews[1][0]);
			$currentViewTpl = preg_replace('#\{VIEW_NAME\}#si', $currentView['view_name'], $currentViewTpl);
			$currentViewTpl = preg_replace('#\{COMMA\}#si', ($i < (count($this->viewList)-1)) ? ',' : '', $currentViewTpl);
				
			$forTabViews[0] .= $currentViewTpl;
		}
		$this->template = preg_replace('#\{FOR_VIEWS(.*?)FOR_VIEWS\}#si', $forTabViews[0], $this->template);
		
		/*
		============
		TabBar Icons
		============
		*/
		// custom icon, user defined/uploaded
		$tabbarIconSetCustom = array();
		if($tabbarIconFolderHandle = opendir("images/tabbar/custom"))
		{
			while (false !== ($currentTabIcon = readdir($tabbarIconFolderHandle)) )
			{
				if(preg_match('#^(.+?)(?<!@2x)\.png$#si', $currentTabIcon))
					$tabbarIconSetCustom[] = $currentTabIcon;
			}
		}
		closedir($tabbarIconFolderHandle);
		reset($tabbarIconSetCustom);
		sort($tabbarIconSetCustom);
		
		// default icons (predefined "arbitrary" set)
		$tabbarIconSetDefault = array();
		if($tabbarIconFolderHandle = opendir("images/tabbar/default"))
		{
			while (false !== ($currentTabIcon = readdir($tabbarIconFolderHandle)) )
			{
				if(preg_match('#^(.+?)(?<!@2x)\.png$#si', $currentTabIcon))
					$tabbarIconSetDefault[] = $currentTabIcon;
			}
		}
		closedir($tabbarIconFolderHandle);
		reset($tabbarIconSetDefault);
		sort($tabbarIconSetDefault);
			
		// complete standard set (without default icons!)
		$tabbarIconSetStandard = array();
		if($tabbarIconFolderHandle = opendir("images/tabbar/icons"))
		{
			while (false !== ($currentTabIcon = readdir($tabbarIconFolderHandle)) )
			{
				if(preg_match('#^(.+?)(?<!@2x)\.png$#si', $currentTabIcon))
					$tabbarIconSetStandard[] = $currentTabIcon;
			}
		}
		closedir($tabbarIconFolderHandle);
		reset($tabbarIconSetStandard);
		sort($tabbarIconSetStandard);
		
		// insert in list in this order
		preg_match_all('#\{FOR_TABICONS(.*?)FOR_TABICONS\}#si', $this->template, $forTabBarTabIcons);
		$forTabBarTabIcons[0] = "";
		foreach($tabbarIconSetCustom as $currentTabIcon)
		{
			$currentTabIconTpl = preg_replace('#\{ICON_NAME\}#si', $currentTabIcon, $forTabBarTabIcons[1][0]);
			$currentTabIconTpl = preg_replace('#\{ICON_FILENAME\}#si', 'custom/' . $currentTabIcon, $currentTabIconTpl);
			$forTabBarTabIcons[0] .= $currentTabIconTpl;
		}
		foreach($tabbarIconSetDefault as $currentTabIcon)
		{
			$currentTabIconTpl = preg_replace('#\{ICON_NAME\}#si', $currentTabIcon, $forTabBarTabIcons[1][0]);
			$currentTabIconTpl = preg_replace('#\{ICON_FILENAME\}#si', 'default/' . $currentTabIcon, $currentTabIconTpl);
			$forTabBarTabIcons[0] .= $currentTabIconTpl;
		}
		foreach($tabbarIconSetStandard as $currentTabIcon)
		{
			$currentTabIconTpl = preg_replace('#\{ICON_NAME\}#si', $currentTabIcon, $forTabBarTabIcons[1][0]);
			$currentTabIconTpl = preg_replace('#\{ICON_FILENAME\}#si', 'icons/' . $currentTabIcon, $currentTabIconTpl);
			$forTabBarTabIcons[0] .= $currentTabIconTpl;
		}
		$this->template = preg_replace('#\{FOR_TABICONS(.*?), FOR_TABICONS\}#si', substr($forTabBarTabIcons[0], 0, -2), $this->template);
		
		/*
		=============
		existing tabs
		=============
		*/
		preg_match_all('#\{FOR_TABS(.*?)FOR_TABS\}#si', $this->template, $forTabBarTabs);
		$forTabBarTabs[0] = "";
		$maxTabId = 0;
		if(isset($this->tabs) && is_array($this->tabs))
		{
			// insert infos for all tabs
			foreach($this->tabs as $currentTab)
			{
				$iconPath = (in_array($currentTab->tab_icon, $tabbarIconSetCustom) ? 'custom' : (in_array($currentTab->tab_icon, $tabbarIconSetDefault) ? 'default' : 'icons'));
			
				$maxTabId = max($maxTabId, $currentTab->tab_id);
				$currentTabTpl = preg_replace('#\{TAB_ID\}#si', $currentTab->tab_id, $forTabBarTabs[1][0]);
				$currentTabTpl = preg_replace('#\{DEFAULT_TAB\}#si', ($currentTab->tab_default)?'true':'false', $currentTabTpl);
				$currentTabTpl = preg_replace('#\{TAB_VIEW_ID\}#si', $currentTab->tab_view, $currentTabTpl);
				$currentTabTpl = preg_replace('#\{TAB_ICON\}#si', 'new Array("' . $iconPath . '/' . $currentTab->tab_icon . '", "' . $currentTab->tab_icon . '")', $currentTabTpl);
				$forTabBarTabs[0] .= $currentTabTpl;
			}
		}
		$this->template = preg_replace('#\{FOR_TABS(.*?)FOR_TABS\}#si', $forTabBarTabs[0], $this->template);
		$this->template = preg_replace('#\{MAXTABID\}#si', $maxTabId, $this->template);
	
		return $this->template;
	}
	
	/**
	* function - loadListOfViews
	* --
	* returns a list with all current views
	* will initialise the list on first calling
	* --
	* @param: none
	* @return: (array)
	*		the complete list of views
	* --
	*/
	function loadListOfViews()
	{
		if(!isset($this->viewList))
		{
			$this->viewList = array();
			
			// query all views
			$viewListQuery = $this->mc->database->query("SELECT view_id, view_name FROM " . $this->mc->config['database_pref'] . "views AS A ORDER BY view_id", array(), array(array("views", "view_id")));
			
			foreach($viewListQuery->rows as $currentView)
			{
				$currentViewDetails = array();
				$currentViewDetails['view_id'] = $currentView->view_id;
				$currentViewDetails['view_name'] = $this->mc->language->getLocalisation($currentView->view_name);
				$this->viewList[] = $currentViewDetails;
			}
		}
		
		return $this->viewList;
	}
}