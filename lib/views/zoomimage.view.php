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
		
		if(isset($this->viewId) && $this->viewId >= 0)
		{
			/*
			========
			buttons
			========
			*/
			// get buttons
			$buttonQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_zoommap_actions WHERE view_id = ? ORDER BY action_posy, action_posx ASC", array(array($this->viewId, "i")));
			preg_match_all('#\{FOR_BUTTONS_IPHONE(.*?)FOR_BUTTONS_IPHONE\}#si', $this->template, $forButtonsIphone);
			$forButtonsIphone[0] = "";
			$forButtonsIpad = "";
			foreach($buttonQuery->rows as $currentButton)
			{
				$currentButtonTpl = preg_replace('#\{BUTTON_X\}#si', $currentButton->action_posx, $forButtonsIphone[1][0]);
				$currentButtonTpl = preg_replace('#\{BUTTON_Y\}#si', $currentButton->action_posy, $currentButtonTpl);
				$currentButtonTpl = preg_replace('#\{BUTTON_WIDTH\}#si', $currentButton->action_width, $currentButtonTpl);
				$currentButtonTpl = preg_replace('#\{BUTTON_HEIGHT\}#si', $currentButton->action_height, $currentButtonTpl);
				$currentButtonTpl = preg_replace('#\{BUTTON_ACTION\}#si', $currentButton->action_command, $currentButtonTpl);
				if($currentButton->view_type == 2)
				{
					$forButtonsIphone[0] .= $currentButtonTpl;
				}
				else if($currentButton->view_type == 1)
				{
					$forButtonsIpad .= $currentButtonTpl;
				}
			}
			$this->template = preg_replace('#\{FOR_BUTTONS_IPHONE(.*?)FOR_BUTTONS_IPHONE\}#si', $forButtonsIphone[0], $this->template);
			$this->template = preg_replace('#\{FOR_BUTTONS_IPAD(.*?)FOR_BUTTONS_IPAD\}#si', $forButtonsIpad, $this->template);
			
			/*
			=====
			image
			=====
			*/
			$imageQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_zoommap_images WHERE view_id = ?", array(array($this->viewId, "i")));
			if(count($imageQuery->rows) > 0)
			{
				foreach($imageQuery->rows as $currentImageData)
				{
					if($currentImageData->view_type == 2)
					{
						$this->template = preg_replace('#\{IMAGENAME_IPHONE\}#si', $currentImageData->image, $this->template);
						$this->template = preg_replace('#\{ON_IMAGE_IPHONE|ON_IMAGE_IPHONE\}#si', '', $this->template);
					}
					else if($currentImageData->view_type == 1)
					{
						$this->template = preg_replace('#\{IMAGENAME_IPAD\}#si', $currentImageData->image, $this->template);
						$this->template = preg_replace('#\{ON_IMAGE_IPAD|ON_IMAGE_IPAD\}#si', '', $this->template);
					}
				}
			}
		}
	
		$this->template = preg_replace('#\{FOR_BUTTONS_IPHONE(.*?)FOR_BUTTONS_IPHONE\}#si', '', $this->template);
		$this->template = preg_replace('#\{FOR_BUTTONS_IPAD(.*?)FOR_BUTTONS_IPAD\}#si', '', $this->template);
		$this->template = preg_replace('#\{ON_IMAGE_IPHONE(.*?)ON_IMAGE_IPHONE\}#si', '', $this->template);
		$this->template = preg_replace('#\{ON_IMAGE_IPAD(.*?)ON_IMAGE_IPAD\}#si', '', $this->template);
		$this->template = preg_replace('#\{IMAGENAME_IPHONE\}#si', '', $this->template);
		$this->template = preg_replace('#\{IMAGENAME_IPAD\}#si', '', $this->template);
		
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
}