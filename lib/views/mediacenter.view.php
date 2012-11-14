<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

View class for display Presentations
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
	return new apdViewMediacenter($mainContainer,
		(
		(isset($_REQUEST['view_id']) && intval($_REQUEST['view_id']) >= 0)
			? intval($_REQUEST['view_id']):-1
		));
}

class apdViewMediacenter extends apdViewBasicModule
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
		include('templates/' . $this->mc->config['template'] . '/modules/mediacenter/mediacenter.html');
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
		
		// TODO: print videos
		$currentVideosQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_mediacenter AS A WHERE view_id = ?", array(array($this->viewId, "i")), array(array("concept_mediacenter", "view_id", "view_name")));
		
		/*
		=============
		existing PDFs
		=============
		*/
		// set of PDFs
		$videoSet = array();
		if($mainFolderHandle = opendir($this->mc->config['upload_dir'] . '/root/videos'))
		{
			while (false !== ($currentVideo = readdir($mainFolderHandle)) )
			{
				if(preg_match('#^(.*?)\.(mp4|avi|mpg|mpeg|m4v)$#si', $currentVideo))
					$videoSet[] = $currentVideo;
			}
		}
		closedir($mainFolderHandle);
		reset($videoSet);
		sort($videoSet);
		
		preg_match_all('#\{FOR_VIDEOS(.*?)FOR_VIDEOS\}#si', $this->template, $forVideosTemplate);
		$forVideosTemplate[0] = "";
		foreach($videoSet as $currentVideo)
		{
			$currentVideoTpl = preg_replace('#\{VIDEONAME\}#si', $currentVideo, $forVideosTemplate[1][0]);
			if(count($currentVideosQuery) > 0 && $currentVideosQuery->rows[0]->video_name == $currentVideo)
			{
				$currentVideoTpl = preg_replace('#\{HTMLSELECTED\}#si', 'selected', $currentVideoTpl);
			}
			else
			{
				$currentVideoTpl = preg_replace('#\{HTMLSELECTED\}#si', '', $currentVideoTpl);
			}
			$forVideosTemplate[0] .= $currentVideoTpl;
		}
		$this->template = preg_replace('#\{FOR_VIDEOS(.*?)FOR_VIDEOS\}#si', $forVideosTemplate[0], $this->template);
		
		return $this->template;
	}
}