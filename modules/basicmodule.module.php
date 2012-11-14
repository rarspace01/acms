<?php/*====================================AppPH Design (c) 2012 SHIN Solutions====================================Basic class for modules that processesinput data for creation or editingexisting views*/if(!isset($configSet) OR !$configSet)	exit();class apdModuleBasicModule{	/**	* function - Constructor	* --	* @param: $mainContainer	*		container that contains all instances	* @return: class	* --	*/	function __construct($mainContainer, $viewId=-1)	{		$this->mc		= $mainContainer;		$this->viewId	= intval($viewId);				// check if view is valid (edit mode or creating a new view?)		if($this->viewId >= 0)		{			// get details for this view			$viewDetailsQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "views AS A WHERE view_id = ?", array(array($this->viewId, "i")), array(array("views", "view_id")));			if(count($viewDetailsQuery->rows) > 0)				// if view-id was really valid, add details				$this->viewDetails = $viewDetailsQuery->rows[0];			else				// otherwise set view-id to invalid				$this->viewId = -1;		}	}		/**	* function - processForm	* --	* process a GET or POST request	* --	* @param: none	* @return: none	* --	*/	function processForm()	{		$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "revisions SET revision_active = max_revision+1,  max_revision = max_revision+1");		$maximumRevisionQuery = $this->mc->database->query("SELECT max_revision FROM " . $this->mc->config['database_pref'] . "revisions");		$this->mc->config['current_revision'] = $maximumRevisionQuery->rows[0]->max_revision;					/*		===========		insert mode		===========		*/		if($this->viewId < 0)		{			// get new view-id			$maximumViewIdQuery = $this->mc->database->query("SELECT MAX(view_id) AS max_id FROM " . $this->mc->config['database_pref'] . "views", array());			$maximumViewId = $maximumViewIdQuery->rows[0]->max_id + 1;						// create new view name for backend			$viewName = 'view_name_' . $maximumViewId;		}			/*		=========		edit mode		=========		*/		if($this->viewId >= 0)		{					// create entry in _views			$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "views (view_id, view_name, revision) VALUES(?,?,?)", array(array($maximumViewId, "i"), array($viewName), array($this->mc->config['current_revision'], "i")));						$this->viewId = $maximumViewId;			$viewDetailsQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "views AS A WHERE view_id = ?", array(array($this->viewId, "i")), array(array("views", "view_id")));			if(count($viewDetailsQuery->rows) > 0)				// if view-id was really valid, add details				$this->viewDetails = $viewDetailsQuery->rows[0];					// should this view be initialised with a navigationbar?			$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_navigationbar = ? WHERE view_id = ?", array(array((($_REQUEST['view_info_navigationbar'] == 'initialise') ? 1 : 0), "i"), array($this->viewId, "i")), array(array("views", "view_id")));						// should this view be the starting page?			$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_start = ? WHERE view_id = ?", array(array((($_REQUEST['view_info_startview'] == 'front') ? 1 : 0), "i"), array($this->viewId, "i")), array(array("views", "view_id")));			// there can only be 1 starting page, so set all others to 0			if($_REQUEST['view_info_startview'] == 'front')			{				$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_start = 0 WHERE view_id != ?", array(array($this->viewId, "i")), array(array("views", "view_id")));			}						// set background			if(isset($_REQUEST['view_info_background']))			{				$backgroundImage = preg_replace('#^(.+?)_(?:[a-zA-z]{2})\.([a-zA-Z]+?)$#si', '$1.$2', $_REQUEST['view_info_background']);				$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_background = ? WHERE view_id = ?", array(array($backgroundImage, "s"), array($this->viewId, "i")), array(array("views", "view_id")));			}						// should this view be initialised with a tabbar?			// NOTE: even if this view isn't *initialised* with a tabbar, it			// can still be part of a tabbar as a tab			$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_tabbar = ? WHERE view_id = ?", array(array($_REQUEST['view_info_tabbar'], "i"), array($this->viewId, "i")), array(array("views", "view_id")));									// update view-names in different languages			// first get all localisations			$availableLanguageQuery = $this->mc->database->query("SELECT local_id FROM " . $this->mc->config['database_pref'] . "localisations WHERE local_active = 1");			foreach($availableLanguageQuery->rows as $availableLanguage)			{				$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "localisation_keys (local_value, local_id, local_key, revision) VALUES(?, ?, ?, ?)", array(array($_REQUEST['view_info_name_' . $availableLanguage->local_id], "s"), array($availableLanguage->local_id, "i"), array($this->viewDetails->view_name), array($this->mc->config['current_revision'], "i")));			}						// check if since last update the tabbar changed			$this->checkTabbarActive();						// set status for current tabbar, if one is set			if($_REQUEST['view_info_tabbar'] >= 0)			{				$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "tabbars SET tabbar_active = 1 WHERE tabbar_id = ?", array(array($_REQUEST['view_info_tabbar'], "i")), array(array("tabbars", "tabbar_id")));			}		}	}		/**	* function - checkForParentsPath	* --	* checks if there exists a path from the current view	* to the starting page. Used for checking if a tabbar is	* (still) active	* --	* @param: $currentDestinationView - the current view to check	* @return: (boolean)			does a path exist?	* --	*/	function checkForParentsPath($currentDestinationView)	{		$viewParentsQuery = $this->mc->database->query("SELECT A.view_id_parent AS parent_id, B.view_start AS start FROM " . $this->mc->config['database_pref'] . "view_links AS A, " . $this->mc->config['database_pref'] . "views AS B WHERE A.view_id_destination = ? AND A.view_id_parent = B.view_id", array(array($currentDestinationView, "i")), array(array("view_links", "view_id_parent", "view_id_destination"), array("views", "view_id")));			// if current view does not have any parents its a dead end		if(count($viewParentsQuery->rows) == 0)			return false;					// otherwise check for parents		foreach($viewParentsQuery->rows as $viewParent)		{			// case 1: this parent is the starting page			if($viewParent->start == 1)				return true;							// otherwise, search recursively			return checkForParentsPath($viewParent->parent_id);		}	}		function checkTabbarActive()	{		$tabbarsQuery = $this->mc->database->query("SELECT tabbar_id FROM " . $this->mc->config['database_pref'] . "tabbars AS A WHERE 1", array(), array(array("tabbars", "tabbar_id")));		foreach($tabbarsQuery->rows as $currentTabbar)		{			$tabbarIsActive = false;			// first check all views that are initialised with the old tabbar			$activeTabbarQuery = $this->mc->database->query("SELECT view_id, view_start FROM " . $this->mc->config['database_pref'] . "views AS A WHERE view_tabbar = ?", array(array($currentTabbar->tabbar_id, "i")), array(array("views", "view_id")));			foreach($activeTabbarQuery->rows as $currentViewTabbar)			{				// now check if this view is active (somehow accessible from the startpage)									// case 1: this view is the startpage already				if($currentViewTabbar->view_start == 1)				{					$tabbarIsActive = true; break;				}									// case 2: not startpage, check for accessibility				// now we need to check the _view_links table for parents				// until we hit the root-element (start page)				$tabbarIsActive = $this->checkForParentsPath($currentViewTabbar->view_id);				if($tabbarIsActive)					break;			}						$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "tabbars (`tabbar_id`, `tabbar_name`, `tabbar_active`, `revision`) (SELECT `tabbar_id`, `tabbar_name`, ?, ? FROM " . $this->mc->config['database_pref'] . "tabbars AS A WHERE tabbar_id = ?)", array(array($tabbarIsActive?1:0, "i"), array($this->mc->config['current_revision'], "i"), array($currentTabbar->tabbar_id, "i")), array(array("tabbars", "tabbar_id"))						//$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "tabbars SET tabbar_active = ? WHERE tabbar_id = ?", array(array($tabbarIsActive?1:0, "i"), array($currentTabbar->tabbar_id, "i")), array(array("tabbars", "tabbar_id")));		}	}		function cleanUpDirectory()	{		$filePath = $this->mc->config['upload_dir'] . 'root/xml/';		if($xmlFolderHandle = opendir($filePath))		{			while (false !== ($currentXmlFile = readdir($xmlFolderHandle)) )			{				if(preg_match('#^' . $this->viewDetails->view_name . '#si', $currentXmlFile))				{					unlink($filePath . $currentXmlFile);				}			}		}		closedir($xmlFolderHandle);	}}