<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

abstract class for creating xml-files
*/

class apdIFilecreator
{
	public $mc;

	/**
	* function - Constructor
	* --
	* @param: $mainContainer
	*		container that contains all instances
	* @return: class
	* --
	*/
	/*function __construct($mainContainer)
	{
		$this->mc		= $mainContainer;
	}*/
	
	/**
	* function - createLink
	* --
	* returns an action-string to intialise the given view
	* --
	* @param: $viewName
	* @param: $viewId
	* @param: $deviceKey
	* @return: (String) action-string like "loadPage::view"
	* --
	*/
	function createLink($viewName, $viewId = -1, $deviceKey = 0)
	{
		return "loadPage::" . $viewName . "&amp;YES";
	}
	
	/**
	* function - createMainXmlPages
	* --
	* returns appropriate xml-definitions of <page>...</page>
	* --
	* @param: $viewId
	* @param: $deviceKey
	* @return: (String)
	*		string for page-definition
	* --
	*/
	function createMainXmlPages($viewId, $deviceKey = 0)
	{
		$viewQuery = $this->mc->database->query("SELECT A.*, B.concept_view FROM " . $this->mc->config['database_pref'] . "views AS A, " . $this->mc->config['database_pref'] . "concepts AS B WHERE A.view_id = ? AND A.view_c_type = B.concept_id", array(array($viewId, "i")), array(array("views", "view_id")));

		$output = "";
		// general page information
		$output .= '<page pageid="' . $viewQuery->rows[0]->view_name . '"';
		if($viewQuery->rows[0]->view_start == 1)
			$output .= ' front="true"'; // front page?
		if($viewQuery->rows[0]->view_navigationbar == 1)
			$output .= ' initWithNaviCtrl="true"'; // has a navigation controller?
		if($viewQuery->rows[0]->view_tabbar >= 0)
		{
			// check if tabbar is active
			$tabbarActiveCheck = $this->mc->database->query("SELECT tabbar_active FROM " . $this->mc->config['database_pref'] . "tabbars AS A WHERE A.tabbar_id = ?", array(array($viewQuery->rows[0]->view_tabbar, "i")), array("tabbars", "tabbar_id"));
			if(count($tabbarActiveCheck->rows) > 0 && $tabbarActiveCheck->rows[0]->tabbar_active == 1)
			{
				$output .= ' tabbarid="' . $viewQuery->rows[0]->view_tabbar . '"'; // initialises a tabbar?
			}
		}
		if($viewQuery->rows[0]->view_background != '' && $viewQuery->rows[0]->view_background != null)
			$output .= ' background="' . $viewQuery->rows[0]->view_background . '"'; // has a set background?
		$output .= '>';
		// view information
			$output .= '<view type="' . $viewQuery->rows[0]->concept_view . '" action="' . $viewQuery->rows[0]->view_action . '" />';
		$output .= '</page>';
		
		return $output;
	}
}