<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

View class for display general settings
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
	// check if a view-id was given
	// will call parent constructor!
	return new apdViewGeneral($mainContainer);
}

class apdViewGeneral implements apdIView
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
		include('templates/' . $this->mc->config['template'] . '/modules/general/general.html');
		$this->template = ob_get_contents();
		ob_clean();
		$this->viewList = $this->loadListOfViews();
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
		preg_match_all('#\{FOR_VIEWS(.*?)FOR_VIEWS\}#si', $this->template, $forViewList);
		$forViewList[0] = "";
		
		// get list of files
		for($i = 0; $i < count($this->viewList); $i++)
		{
			$currentView = $this->viewList[$i];
			$currentViewTpl = preg_replace('#\{VIEW_ID\}#si', $currentView['view_id'], $forViewList[1][0]);
			$currentViewTpl = preg_replace('#\{VIEW_NAME\}#si', $currentView['view_name'], $currentViewTpl);
			$currentViewTpl = preg_replace('#\{VIEW_SELECTED\}#si', ($currentView['view_start'] == 1 ? 'selected' : ''), $currentViewTpl);
				
			$forViewList[0] .= $currentViewTpl;
		}
		$this->template = preg_replace('#\{FOR_VIEWS(.*?)FOR_VIEWS\}#si', $forViewList[0], $this->template);
	
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
			$viewListQuery = $this->mc->database->query("SELECT view_id, view_name, view_start FROM " . $this->mc->config['database_pref'] . "views AS A WHERE 1 ORDER BY view_id", array(), array(array("views", "view_id")));
			
			foreach($viewListQuery->rows as $currentView)
			{
				$currentViewDetails = array();
				$currentViewDetails['view_id'] = $currentView->view_id;
				$currentViewDetails['view_start'] = $currentView->view_start;
				$currentViewDetails['view_name'] = $this->mc->language->getLocalisation($currentView->view_name);
				$this->viewList[] = $currentViewDetails;
			}
		}
		
		return $this->viewList;
	}
}