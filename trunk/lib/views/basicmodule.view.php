<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Basic class for displaying editpages
for concept views
*/

if(!isset($configSet) OR !$configSet)
	exit();

include('lib/views/iview.view.php');

class apdViewBasicModule implements apdIView
{
	/**
	* function - Constructor
	* --
	* @param: $mainContainer
	*		container that contains all instances
	* @return: class
	* --
	*/
	function __construct($mainContainer, $viewId=-1)
	{
		$this->mc		= $mainContainer;
		$this->viewId	= intval($viewId);
		
		// check if view is valid (edit mode or creating a new view?)
		if($this->viewId >= 0)
		{
			// get details for this view
			$viewDetailsQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "views AS A WHERE view_id = ?", array(array($this->viewId, "i")), array(array("views", "view_id")));
			if(count($viewDetailsQuery->rows) > 0)
				// if view-id was really valid, add details
				$this->viewDetails = $viewDetailsQuery->rows[0];
			else
				// otherwise set view-id to invalid
				$this->viewId = -1;
		}
		
		$this->loadListOfViews();
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
		include('templates/' . $this->mc->config['template'] . '/modules/basic/basic.html');
		$this->template = ob_get_contents();
		ob_clean();
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
		// if there is a valid view that exists already (edit mode)
		if($this->viewId >= 0)
		{
			// insert known view-id for this view
			$this->template = preg_replace('#\{VIEWID\}#si', $this->viewDetails->view_id, $this->template);
			$this->template = preg_replace('#\{VIEWNAME\}#si', $this->viewDetails->view_name, $this->template);
			$this->template = preg_replace('#\{CURRENT_BACKGROUND\}#si', $this->viewDetails->view_background, $this->template);
		}
		else
		{
			// otherwise this is a new creation of a view
			$this->template = preg_replace('#\{VIEWID\}#si', -1, $this->template);
			$this->template = preg_replace('#\{VIEWNAME\}#si', '', $this->template);
			$this->template = preg_replace('#\{CURRENT_BACKGROUND\}#si', '', $this->template);
		}
		
		
		preg_match_all('#\{FOR_BACKGROUNDS(.*?)FOR_BACKGROUNDS\}#si', $this->template, $forBackgrounds);
		$forBackgrounds[0] = "";
		$pictureFolderPath = $this->mc->config['upload_dir'] . 'root/pictures/';
		if($pictureFolderHandle = opendir($pictureFolderPath))
		{
			while (false !== ($currentPicture = readdir($pictureFolderHandle)) )
			{
				if(!is_dir($pictureFolderPath . $currentPicture) && !preg_match('#^\.|\.\.|/+|\\\\+$#si', $currentPicture))
				{
					list($bgImgWidth, $bgImgHeight) = getimagesize($pictureFolderPath . $currentPicture);
					if($bgImgWidth >= 320 && $bgImgHeight >= 440)
					{
						$backgroundImagePlain = preg_replace('#^(.+?)_(?:[a-zA-z]{2})\.([a-zA-Z]+?)$#si', '$1.$2', $currentPicture);
						$currentBackgroundTpl = preg_replace('#\{BACKGROUNDIMAGE\}#si', $currentPicture, $forBackgrounds[1][0]);
						$currentBackgroundTpl = preg_replace('#\{BACKGROUNDIMAGEPLAIN\}#si', $backgroundImagePlain, $currentBackgroundTpl);
						$currentBackgroundTpl = preg_replace('#\{ONSELECTED(.*?)ONSELECTED\}#si', (($this->viewId >= 0 && $this->viewDetails->view_background == $currentPicture) ? '$1' : ''), $currentBackgroundTpl);
						$forBackgrounds[0] .= $currentBackgroundTpl;
					}
				}
			}
		}
		closedir($pictureFolderHandle);
		$this->template = preg_replace('#\{FOR_BACKGROUNDS(.*?)FOR_BACKGROUNDS\}#si', $forBackgrounds[0], $this->template);
		
		// get template-snippet for tabbar-dropdown menu
		preg_match_all('#\{FOR_TABBARS(.*?)FOR_TABBARS\}#si', $this->template, $forTabbars);
		$forTabbars[0] = "";
		// select all tabbars
		$tabbarQuery = $this->mc->database->query("SELECT tabbar_id, tabbar_name FROM " . $this->mc->config['database_pref'] . "tabbars AS A ORDER BY tabbar_name ASC", array(), array(array("tabbars", "tabbar_id")));
		foreach($tabbarQuery->rows as $currentTabbar)
		{
			$currentTabbarTpl = preg_replace('#\{TABBARID\}#si', $currentTabbar->tabbar_id, $forTabbars[1][0]);
			$currentTabbarTpl = preg_replace('#\{TABBARNAME\}#si', $currentTabbar->tabbar_name, $currentTabbarTpl);
			// check if a specific tabbar was selected
			if($this->viewId >= 0 && $this->viewDetails->view_tabbar == $currentTabbar->tabbar_id)
				$currentTabbarTpl = preg_replace('#\{SELECTED\}#si', ' selected', $currentTabbarTpl);
			else
				$currentTabbarTpl = preg_replace('#\{SELECTED\}#si', '', $currentTabbarTpl);
			
			$forTabbars[0] .= $currentTabbarTpl;
		}
		$this->template = preg_replace('#\{FOR_TABBARS(.*?)FOR_TABBARS\}#si', $forTabbars[0], $this->template);
		
		// check for navigationbar
		if($this->viewId >= 0 && $this->viewDetails->view_navigationbar == 1)
			$this->template = preg_replace('#\{NAVIGATIONBARCHECKED\}#si', 'checked="checked"', $this->template);
		else
			$this->template = preg_replace('#\{NAVIGATIONBARCHECKED\}#si', '', $this->template);
			
		// check for starting page
		if($this->viewId >= 0 && $this->viewDetails->view_start == 1)
			$this->template = preg_replace('#\{STARTVIEWCHECKED\}#si', 'checked="checked"', $this->template);
		else
			$this->template = preg_replace('#\{STARTVIEWCHECKED\}#si', '', $this->template);
	
		// get all languages that exist from "languages" table in database
		// we need all columns
		$languageQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "localisations WHERE local_active = 1");
		
		// get template-snippet for language-listing
		preg_match_all('#\{FOR_LANGUAGES_BASIC(.*?)FOR_LANGUAGES_BASIC\}#si', $this->template, $forLanguagesBasic);
		$forLanguagesBasic[0] = "";
		
		// go through list of languages (all columns in table sd_languages except the first one)
		foreach($languageQuery->rows as $availableLanguage)
		{
			// key for this language
			$currentLanguageTpl = preg_replace('#\{LANGUAGEID\}#si', $availableLanguage->local_id, $forLanguagesBasic[1][0]);
			// display name of this language (in current session-language)
			$currentLanguageTpl = preg_replace('#\{LANGUAGE\}#si', $this->mc->language->getLocalisation($availableLanguage->local_name), $currentLanguageTpl);
			
			// if there is a valid view that exists already (edit mode)
			if($this->viewId >= 0)
			{
				// localised name in current language, load from database
				$localiseQuery = $this->mc->database->query("SELECT local_value FROM " . $this->mc->config['database_pref'] . "localisation_keys AS A WHERE local_id = ? AND local_key = ?", array(array($availableLanguage->local_id, "i"), array($this->viewDetails->view_name, "s")), array(array("localisation_keys", "local_id", "local_key")));
				// check if localisation exists
				$viewNameLocalised = $this->viewDetails->view_name;
				if(count($localiseQuery->rows) > 0)
					$viewNameLocalised = $localiseQuery->rows[0]->local_value;
				// insert localisation
				$currentLanguageTpl = preg_replace('#\{LOCALISEDNAME\}#si', $viewNameLocalised, $currentLanguageTpl);
			}
			else
				$currentLanguageTpl = preg_replace('#\{LOCALISEDNAME\}#si', '', $currentLanguageTpl);
			
			$forLanguagesBasic[0] .= $currentLanguageTpl;
		}
		
		$this->template = preg_replace('#\{FOR_LANGUAGES_BASIC(.*?)FOR_LANGUAGES_BASIC\}#si', $forLanguagesBasic[0], $this->template);
	
		$this->template = preg_replace('#\{CONFIG_UPLOADDIR\}#si', $this->mc->config['upload_dir'], $this->template);
	
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