<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

General settings for the app
*/
	
if(!function_exists('initCurrentModule'))
{
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
		return new apdModuleGeneral($mainContainer);
	}
}

class apdModuleGeneral
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
		$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "revisions SET revision_active = max_revision+1,  max_revision = max_revision+1");
		$maximumRevisionQuery = $this->mc->database->query("SELECT max_revision FROM " . $this->mc->config['database_pref'] . "revisions");
		$this->mc->config['current_revision'] = $maximumRevisionQuery->rows[0]->max_revision;
		
		if(isset($_REQUEST['startview']) && is_numeric($_REQUEST['startview']) && trim($_REQUEST['startview']) != "" && $_REQUEST['startview'] > 0)
		{
			// select old startview
			$oldStartviewQuery = $this->mc->database->query("SELECT view_id FROM " . $this->mc->config['database_pref'] . "views AS A WHERE view_start = 1", array(), array(array("views", "view_id")));
			if(count($oldStartviewQuery->rows) > 0)
			{		
				// should this view be the starting page?
				$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "views (`view_id`, `view_name`, `view_c_type`, `view_action`, `view_background`, `view_navigationbar`, `view_tabbar`, `view_start`, `revision`) (SELECT view_id, view_name, view_c_type, view_action, view_background, view_navigationbar, view_tabbar, 1, ? FROM " . $this->mc->config['database_pref'] . "views AS A WHERE view_id = ?)", array(array($this->mc->config['current_revision'], "i"), array($_REQUEST['startview'], "i")), array(array("views", "view_id")));
				foreach($oldStartviewQuery->rows as $currentStartview)
				{
					if($currentStartview->view_id != $_REQUEST['startview'])
					{
						$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "views (`view_id`, `view_name`, `view_c_type`, `view_action`, `view_background`, `view_navigationbar`, `view_tabbar`, `view_start`, `revision`) (SELECT view_id, view_name, view_c_type, view_action, view_background, view_navigationbar, view_tabbar, 0, ? FROM " . $this->mc->config['database_pref'] . "views AS A WHERE view_id = ?)", array(array($this->mc->config['current_revision'], "i"), array($currentStartview->view_id, "i")), array(array("views", "view_id")));
					}
				}
			}
		}
		header("Location: index.php?m=general");
	}
}
?>