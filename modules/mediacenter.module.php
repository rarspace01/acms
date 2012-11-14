<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Class for processing a sent form for this
view-concept
*/

if(!isset($configSet) OR !$configSet)
	exit();

// load basic view	
include('modules/basicmodule.module.php');

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
	return new apdModuleMediacenter($mainContainer,
		(
		(isset($_REQUEST['view_id']) && intval($_REQUEST['view_id']) >= 0)
			? intval($_REQUEST['view_id']) : -1
		));
}

class apdModuleMediacenter extends apdModuleBasicModule
{
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
		$originalViewId = $this->viewId;
		parent::processForm();
		
		$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_action = view_name WHERE view_id = ?", array(array($this->viewId, "i")));
		
		//if(isset($_REQUEST['view_videoselection']) && $_REQUEST['view_videoselection'] != -1)
			// TODO: currently just statically inserted
			$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_mediacenter (view_id, video_name, video_text, video_length, video_thumbnail, revision) VALUES(?, 'The Superbowl XLVI 2012.mp4', 'Superbowl XLVI Trailer', '4:16', 'superbowl_logo.jpg', ?)", array(array($this->viewId, "i"), array($this->mc->config['current_revision'], "i")));
			$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_mediacenter (view_id, video_name, video_text, video_length, video_thumbnail, revision) VALUES(?, 'Ahmad Bradshaw Touchdown.mp4', 'Ahmad Bradshaw Touchdown', '0:24', 'superbowl_shot.jpg', ?)", array(array($this->viewId, "i"), array($this->mc->config['current_revision'], "i")));
			$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_mediacenter (view_id, video_name, video_text, video_length, video_thumbnail, revision) VALUES(?, 'Jerome Simpson Touchdown Flip.mp4', 'Jerome Simpson Touchdown Flip', '0:26', 'nfl_logo_rasen.jpg', ?)", array(array($this->viewId, "i"), array($this->mc->config['current_revision'], "i")));
			$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_mediacenter (view_id, video_name, video_text, video_length, video_thumbnail, revision) VALUES(?, 'Tom Brady Final Throw.mp4', 'Tom Brady Final Throw', '1:33', 'nfl_logl_original.jpg', ?)", array(array($this->viewId, "i"), array($this->mc->config['current_revision'], "i")));
			$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_mediacenter (view_id, video_name, video_text, video_length, video_thumbnail, revision) VALUES(?, 'Best SuperBowl Moments.mp4', 'Best SuperBowl Moments', '3:19', 'superbowl_moments.jpg', ?)", array(array($this->viewId, "i"), array($this->mc->config['current_revision'], "i")));
	
		// update concept type id for this view
		$conceptQuery = $this->mc->database->query("SELECT concept_id FROM " . $this->mc->config['database_pref'] . "concepts WHERE concept_key = 'mediacenter'");
		$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_c_type = ? WHERE view_id = ?", array(array($conceptQuery->rows[0]->concept_id, "i"), array($this->viewId, "i")));
		
		$this->cleanUpDirectory();
		$this->createXmlFile();
		
		// re-create main xml file and refresh filelist
		$this->mc->filecreator->createGeneralFiles();
		$configSet = true;
		include('modules/filemanager.module.php');
		$fileManagerObj = new apdModuleFilemanager($this->mc);
		$fileManagerObj->refreshFilelist();
		
		header("Location: index.php?m=mediacenter&view_id=" . $this->viewId);
	}
	
	/**
	* function - createXmlFile
	* --
	* creates the XML-file for this view
	* --
	* @param: none
	* @return: (boolean)
	*		true if all went correct
	* --
	*/
	function createXmlFile()
	{
		/*
		<xml>
			<videofile src="eafade3f55760e4cdb44f82f2a4141f6ac439c5f" thumbnail="e45829281e341081e43c4394544ddcee403ddc0b" length="01:27" text="Massmann 1" />
		</xml>
		*/
		$output = '<?xml version="1.0" encoding="UTF-8"?><xml>';
	
		$videosQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_mediacenter AS A WHERE view_id = ?", array(array($this->viewId, "i")), array(array("concept_mediacenter", "view_id", "video_name")));
		foreach($videosQuery->rows as $currentVideo)
		{
			$output .= '<videofile src="' . $currentVideo->video_name . '" thumbnail="' . $currentVideo->video_thumbnail . '" length="' . $currentVideo->video_length . '" text="' . $currentVideo->video_text . '" />';
		}
		$output .= '</xml>';
		
		$outputFileSuffix = "";
		$outputFileHandle = fopen($this->mc->config['upload_dir'] . '/root/xml/'. $this->viewDetails->view_name . $outputFileSuffix . '.xml', 'wb');
		fwrite($outputFileHandle, $output);
		fclose($outputFileHandle);
		
		return true;
	}
}