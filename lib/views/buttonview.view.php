<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

View class for display Zoomimage concepts
*/

if(!isset($configSet) OR !$configSet)
	exit();

// load basic view	
include('lib/views/basicmodule.view.php');

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
	return new apdViewButtonview($mainContainer,
		(
		(isset($_REQUEST['view_id']) && intval($_REQUEST['view_id']) >= 0)
			? intval($_REQUEST['view_id']):-1
		));
}

class apdViewButtonview extends apdViewBasicModule
{
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
		include('templates/' . $this->mc->config['template'] . '/modules/buttonview/buttonview.html');
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
		/*
		==========
		basic info
		==========
		*/
		// save current template
		$currentTemplate = $this->template;
		// call parent template to insert form for basic info for this view
		parent::initTemplate();
		$this->template = preg_replace('#\{BASIC_INFO\}#si', parent::printTemplate(), $currentTemplate);

		/*
		============
		languages
		=============
		*/		
		// load all languages from database, these are the columns in the language-table
		$languageQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "localisations WHERE local_active = 1 ORDER BY local_id ASC", array());		
		preg_match_all('#\{FOR_LANGUAGES(.*?)FOR_LANGUAGES\}#si', $this->template, $forLanguages);
		$forLanguages[0] = ""; $languageList = "";
		
		// go through list of languages
		for($i = 0; $i < count($languageQuery->rows); $i++)
		{
			$availableLanguage = $languageQuery->rows[$i];
			// key for this language
			$currentLanguageTpl = preg_replace('#\{LANGUAGEID\}#si', $availableLanguage->local_id, $forLanguages[1][0]);
			// display name for this language in current session-language
			$currentLanguageTpl = preg_replace('#\{LANGUAGE\}#si', $this->mc->language->getLocalisation($availableLanguage->local_name), $currentLanguageTpl);
			$currentLanguageTpl = preg_replace('#\{COMMA\}#si', ($i < (count($languageQuery->rows)-1)) ? ',' : '', $currentLanguageTpl);
			$languageList .= "''" . (($i < (count($languageQuery->rows)-1)) ? ',' : '');
			
			$forLanguages[0] .= $currentLanguageTpl;
		}		
		$this->template = preg_replace('#\{FOR_LANGUAGES(.*?)FOR_LANGUAGES\}#si', $forLanguages[0], $this->template);
		$this->template = preg_replace('#\{LANGUAGE_LIST\}#si', $languageList, $this->template);
		
		$this->template = $this->mc->devicetypes->viewDeviceTemplates($this->template, $this);
		
		preg_match_all('#\{FOR_BUTTONVIEW_BACKGROUND(.*?)FOR_BUTTONVIEW_BACKGROUND\}#si', $this->template, $forLandingpageBackground);
		$forLandingpageBackground[0] = "";
		if($buttonviewFolderHandle = opendir($this->mc->config['upload_dir'] . 'modules/buttonview/pictures/'))
		{
			while (false !== ($currentBackgroundImg = readdir($buttonviewFolderHandle)) )
			{
				if(!preg_match('#^\.|\.\.|/|\\\\$#si', $currentBackgroundImg))
				{
					$currentBackgroundTpl = preg_replace('#\{IMAGENAME\}#si', $currentBackgroundImg, $forLandingpageBackground[1][0]);
					$forLandingpageBackground[0] .= $currentBackgroundTpl;
				}
			}
		}
		closedir($buttonviewFolderHandle);
		$this->template = preg_replace('#\{FOR_BUTTONVIEW_BACKGROUND(.*?)FOR_BUTTONVIEW_BACKGROUND\}#si', $forLandingpageBackground[0], $this->template);
		
		preg_match_all('#\{FOR_VIEWS(.*?)FOR_VIEWS\}#si', $this->template, $forTabViews);
		$forTabViews[0] = "";
		
		// get list of files
		for($i = 0; $i < count($this->viewList); $i++)
		{
			$currentView = $this->viewList[$i];
			$currentViewTpl = preg_replace('#\{VIEW_ID\}#si', $currentView['view_id'], $forTabViews[1][0]);
			$currentViewTpl = preg_replace('#\{VIEW_NAME\}#si', $currentView['view_name'], $currentViewTpl);
			$currentViewTpl = preg_replace('#\{COMMA\}#si', ($i < (count($this->viewList)-1)) ? ',' : '', $currentViewTpl);
				
			$forTabViews[0] .= $currentViewTpl;
		}
		$this->template = preg_replace('#\{FOR_VIEWS(.*?)FOR_VIEWS\}#si', $forTabViews[0], $this->template);
		
		return $this->template;
	}
	
	/**
	* function - customDeviceTemplate
	* --
	* replaces device-specifc and module-specific content
	* in the current template
	* --
	* @param: $template
	*		template for processing
	* @param $deviceKey
	*		key like "iphone" or "ipad"
	* @param $deviceId
	*		id (primary key in database)
	* @return: (String) template
	*		finished template
	* --
	*/
	function customDeviceTemplate($template, $deviceKey, $deviceId)
	{
		/*
		========
		buttons
		========
		*/
		// get template for buttons
		if(preg_match_all('#\{FOR_BUTTONS(.*?)FOR_BUTTONS\}#si', $template, $forButtons) > 0)
		{
			// get buttons
			$buttonQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_buttonview_actions WHERE view_id = ? AND view_type = ? ORDER BY action_posy, action_posx ASC", array(array($this->viewId, "i"), array($deviceId, "i")));
			$forButtons[0] = "";
			foreach($buttonQuery->rows as $currentButton)
			{
				$currentButtonTpl = preg_replace('#\{BUTTON_X\}#si', $currentButton->action_posx, $forButtons[1][0]);
				$currentButtonTpl = preg_replace('#\{BUTTON_Y\}#si', $currentButton->action_posy, $currentButtonTpl);
				$currentButtonTpl = preg_replace('#\{BUTTON_WIDTH\}#si', $currentButton->action_width, $currentButtonTpl);
				$currentButtonTpl = preg_replace('#\{BUTTON_HEIGHT\}#si', $currentButton->action_height, $currentButtonTpl);
				$currentButtonTpl = preg_replace('#\{BUTTON_ACTION\}#si', $currentButton->action_command, $currentButtonTpl);
				$currentButtonTitleNames = "";
				// get all localised names for this section
				$buttonTitleQuery = $this->mc->database->query("SELECT local_value FROM " . $this->mc->config['database_pref'] . "localisation_keys WHERE local_key = ? ORDER BY local_id ASC", array(array($currentButton->action_title)));
				for($i = 0; $i < count($buttonTitleQuery->rows); $i++)
				{
					$currentButtonTitleNames .= '"' . $buttonTitleQuery->rows[$i]->local_value . '"' . ( ($i < (count($buttonTitleQuery->rows)-1)) ? ',' : '');
				}
				$currentButtonTpl = preg_replace('#\{BUTTON_TITLES\}#si', $currentButtonTitleNames, $currentButtonTpl);
				$forButtons[0] .= $currentButtonTpl;
			}
			$template = preg_replace('#\{FOR_BUTTONS(.*?)FOR_BUTTONS\}#si', $forButtons[0], $template);
		}
			
		/*
		=====
		image
		=====
		*/
		$imageQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_buttonview_images WHERE view_id = ? AND view_type = ?", array(array($this->viewId, "i"), array($deviceId, "i")));
		if(count($imageQuery->rows) > 0)
		{
			$template = preg_replace('#\{IMAGENAME\}#si', $imageQuery->rows[0]->image, $template);
			$template = preg_replace('#\{DEVICE_TYPE_EXISTS\}#si', 'true', $template);
			$template = preg_replace('#\!\{DEVICE_TYPE_EXISTS\}#si', 'false', $template);
		}
		else
		{
			$template = preg_replace('#\{IMAGENAME\}#si', '', $template);
			$template = preg_replace('#\{DEVICE_TYPE_EXISTS\}#si', 'false', $template);
			$template = preg_replace('#\!\{DEVICE_TYPE_EXISTS\}#si', 'true', $template);
		}
		return $template;
	}
}