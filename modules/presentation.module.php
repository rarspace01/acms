<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Class for processing a sent form for this
view-concept
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
		return new apdModulePresentation($mainContainer,
			(
			(isset($_REQUEST['view_id']) && intval($_REQUEST['view_id']) >= 0)
				? intval($_REQUEST['view_id']) : -1
			));
	}
}

class apdModulePresentation extends apdModuleBasicModule
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
	
		if(isset($_REQUEST['view_pdfselection']) && $_REQUEST['view_pdfselection'] != -1)
		{
			$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_presentation (view_id, image_position, image_path, revision) VALUES(?, 0, ?, ?)", array(array($this->viewId, "i"), array($_REQUEST['view_pdfselection']), array($this->mc->config['current_revision'], "i")));
		}
		else
		{
		}
	
		// update concept type id for this view
		$conceptQuery = $this->mc->database->query("SELECT concept_id FROM " . $this->mc->config['database_pref'] . "concepts WHERE concept_key = 'presentation'", array());
		$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_c_type = ? WHERE view_id = ?", array(array($conceptQuery->rows[0]->concept_id, "i"), array($this->viewId, "i")));
		
		$this->cleanUpDirectory();
		$this->createXmlFile();
		
		// re-create main xml file and refresh filelist
		$this->mc->filecreator->createGeneralFiles();
		$configSet = true;
		include_once('modules/filemanager.module.php');
		$fileManagerObj = new apdModuleFilemanager($this->mc);
		$fileManagerObj->refreshFilelist();
		
		header("Location: index.php?m=presentation&view_id=" . $this->viewId);
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
			<galleryimage src="gallery-gewehrkammer.jpg" id="0" text="overviewrundganggewehrkammer" />
		</xml>
		*/
		$output = '<?xml version="1.0" encoding="UTF-8"?><xml>';
	
		$imagesQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_presentation AS A WHERE view_id = ? ORDER BY image_position ASC", array(array($this->viewId, "i")), array(array("concept_presentation", "view_id", "image_position")));
		foreach($imagesQuery->rows as $currentImage)
		{
			$output .= '<galleryimage src="' . $currentImage->image_path . '" id="' . $currentImage->image_position . '" />';
		}
		$output .= '</xml>';
		
		//$outputFileSuffix = (count($imageQuery->rows) == 1 ? '' : ($currentDeviceType->device_suffix));
		$outputFileSuffix = "";
		$outputFileHandle = fopen($this->mc->config['upload_dir'] . '/root/xml/'. $this->viewDetails->view_name . $outputFileSuffix . '.xml', 'wb');
		fwrite($outputFileHandle, $output);
		fclose($outputFileHandle);
		
		return true;
	}
}