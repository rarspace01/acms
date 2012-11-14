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
	return new apdViewZoomimage($mainContainer,
		(
		(isset($_REQUEST['view_id']) && intval($_REQUEST['view_id']) >= 0)
			? intval($_REQUEST['view_id']):-1
		));
}

class apdViewZoomimage extends apdViewBasicModule
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
		include('templates/' . $this->mc->config['template'] . '/modules/zoomimage/zoomimage.html');
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
		
		$this->template = $this->mc->devicetypes->viewDeviceTemplates($this->template, $this);
		
		preg_match_all('#\{FOR_ZOOMIMAGES(.*?)FOR_ZOOMIMAGES\}#si', $this->template, $forZoomImages);
		$forZoomImages[0] = "";
		if($zoomimageFolderHandle = opendir($this->mc->config['upload_dir'] . 'modules/zoomimage/pictures/'))
		{
			while (false !== ($currentZoomimage = readdir($zoomimageFolderHandle)) )
			{
				if(!preg_match('#^\.|\.\.|/|\\\\$#si', $currentZoomimage))
				{
					$currentTabIconTpl = preg_replace('#\{IMAGENAME\}#si', $currentZoomimage, $forZoomImages[1][0]);
					$forZoomImages[0] .= $currentTabIconTpl;
				}
			}
		}
		closedir($zoomimageFolderHandle);
		$this->template = preg_replace('#\{FOR_ZOOMIMAGES(.*?)FOR_ZOOMIMAGES\}#si', $forZoomImages[0], $this->template);
		
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
		
		$this->template = preg_replace('#\{CONFIG_UPLOADDIR\}#si', $this->mc->config['upload_dir'], $this->template);
		
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
			$buttonQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_zoommap_actions AS A WHERE view_id = ? AND view_type = ? ORDER BY action_posy, action_posx ASC", array(array($this->viewId, "i"), array($deviceId, "i")), array(array("concept_zoommap_actions", "view_id", "view_type", "action_posx", "action_posy", "action_width", "action_height")));
			$forButtons[0] = "";
			foreach($buttonQuery->rows as $currentButton)
			{
				$currentButtonTpl = preg_replace('#\{BUTTON_X\}#si', $currentButton->action_posx, $forButtons[1][0]);
				$currentButtonTpl = preg_replace('#\{BUTTON_Y\}#si', $currentButton->action_posy, $currentButtonTpl);
				$currentButtonTpl = preg_replace('#\{BUTTON_WIDTH\}#si', $currentButton->action_width, $currentButtonTpl);
				$currentButtonTpl = preg_replace('#\{BUTTON_HEIGHT\}#si', $currentButton->action_height, $currentButtonTpl);
				$currentButtonTpl = preg_replace('#\{BUTTON_ACTION\}#si', $currentButton->action_command, $currentButtonTpl);
				$forButtons[0] .= $currentButtonTpl;
			}
			$template = preg_replace('#\{FOR_BUTTONS(.*?)FOR_BUTTONS\}#si', $forButtons[0], $template);
		}
			
		/*
		=====
		image
		=====
		*/
		$imageQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_zoommap_images AS A WHERE view_id = ? AND view_type = ?", array(array($this->viewId, "i"), array($deviceId, "i")), array(array("concept_zoommap_images", "view_id", "view_type")));
		if(count($imageQuery->rows) > 0)
		{
			$template = preg_replace('#\{IMAGENAME\}#si', $imageQuery->rows[0]->image, $template);
			$template = preg_replace('#\!\{DEVICE_TYPE_EXISTS\}#si', 'false', $template);
			$template = preg_replace('#\{DEVICE_TYPE_EXISTS\}#si', 'true', $template);
		}
		else
		{
			$template = preg_replace('#\{IMAGENAME\}#si', '', $template);
			$template = preg_replace('#\!\{DEVICE_TYPE_EXISTS\}#si', 'true', $template);
			$template = preg_replace('#\{DEVICE_TYPE_EXISTS\}#si', 'false', $template);
		}
		return $template;
	}
}