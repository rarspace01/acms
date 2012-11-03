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
	return new apdModuleText($mainContainer,
		(
		(isset($_REQUEST['view_id']) && intval($_REQUEST['view_id']) >= 0)
			? intval($_REQUEST['view_id']) : -1
		));
}

class apdModuleText extends apdModuleBasicModule
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
		
		/*
		===========
		insert mode
		===========
		*/
		if($originalViewId < 0)
		{
			// go through list of languages
			$availableLanguageQuery = $this->mc->database->query("SELECT local_id FROM " . $this->mc->config['database_pref'] . "localisations", array());
			foreach($availableLanguageQuery->rows as $availableLanguage)
			{
				// for every language, create an entry in _concept_text for the html-text
				$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_text (view_id, language) VALUES(?, ?)", array(array($this->viewId, "i"), array($availableLanguage->local_id, "i")));
			}
		}
		
		
		/*
		=========
		edit mode
		=========
		*/
		
		// set the action-file (content) to the name of the view (=name of the HTML file)
		$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_action = view_name WHERE view_id = ?", array(array($this->viewId, "i")));
		
		// first delete all existing links to simplify matters
		// later we check for links to other views (if the command loadPage::XXXX is called)
		$this->mc->database->query("DELETE FROM " . $this->mc->config['database_pref'] . "view_links WHERE view_id_parent = ?", array(array($this->viewId, "i")));
		
		ob_clean();
		include('templates/' . $this->mc->config['template'] . '/modules/text/output_io.html');
		$textOutputTemplateIphone = ob_get_contents();
		ob_clean();
		include('templates/' . $this->mc->config['template'] . '/modules/text/output_ia.html');
		$textOutputTemplateIpad = ob_get_contents();
		ob_clean();
		
		// go through list of languages
		$availableLanguageQuery = $this->mc->database->query("SELECT local_id, local_key FROM " . $this->mc->config['database_pref'] . "localisations", array());
		foreach($availableLanguageQuery->rows as $availableLanguage)
		{
			$currentContent = $_REQUEST['content_' . $availableLanguage->local_id];
			if(get_magic_quotes_gpc())
			{
				$currentContent = preg_replace('#\\\\(\'|")#si', '$1', $currentContent);
			}
			if (mb_detect_encoding($currentContent, 'UTF-8', true) === FALSE)
			{
				$currentContent = utf8_encode($currentContent);
			}
			$currentLanguageOutput = preg_replace('#\{CONTENT\}#si', $currentContent, $textOutputTemplateIphone);
			$outputFileHandle = fopen($this->mc->config['upload_dir'] . '/root/' . $availableLanguage->local_key . '.lproj/' . $this->viewDetails->view_name . '_io.html', 'wb');
			fwrite($outputFileHandle, $currentLanguageOutput);
			fclose($outputFileHandle);
			
			$currentLanguageOutput = preg_replace('#\{CONTENT\}#si', $currentContent, $textOutputTemplateIpad);
			$outputFileHandle = fopen($this->mc->config['upload_dir'] . '/root/' . $availableLanguage->local_key . '.lproj/' . $this->viewDetails->view_name . '_ia.html', 'wb');
			fwrite($outputFileHandle, $currentLanguageOutput);
			fclose($outputFileHandle);
		
			// for every language, create an entry in _concept_text for the html-text
			$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "concept_text SET content = ? WHERE view_id = ? AND language = ?", array(array($currentContent), array($this->viewId, "i"), array($availableLanguage->local_id, "i")));
			
			// now search for a regular expression with loadPage::XXXX
			preg_match_all('#(?:(?:<a)|(?:<script))(?:.+?)loadPage::(.+?)["\'&> ;\r\n](?:.*?)(?:(?:</a>)|(?:</script>))#si', $_REQUEST['content_' . $availableLanguage->local_id], $allOutgoingLinks, PREG_SET_ORDER);
			// go through list of matches
			foreach($allOutgoingLinks as $currentLink)
			{
				$destinationViewName = $currentLink[1];
				// get view-id of destination
				$destinationViewIdQuery = $this->mc->database->query("SELECT view_id FROM " . $this->mc->config['database_pref'] . "views WHERE view_name = ?", array(array($destinationViewName)));
				
				// check if link exists in database already
				$checkViewDestinationQuery = $this->mc->database->query("SELECT COUNT(*) as count FROM " . $this->mc->config['database_pref'] . "view_links WHERE view_id_parent = ? AND view_id_destination = ?", array(array($this->viewId, "i"), array($destinationViewIdQuery->rows[0]->view_id, "i")));
				if($checkViewDestinationQuery->rows[0]->count == 0)
				{
					// insert new link
					$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "view_links (view_id_parent, view_id_destination) VALUES(?, ?)", array(array($this->viewId, "i"), array($destinationViewIdQuery->rows[0]->view_id, "i")));
				}
			}
		}
		
		// update concept type id for this view
		$conceptQuery = $this->mc->database->query("SELECT concept_id FROM " . $this->mc->config['database_pref'] . "concepts WHERE concept_key = 'text'", array());
		$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_c_type = ? WHERE view_id = ?", array(array($conceptQuery->rows[0]->concept_id, "i"), array($this->viewId, "i")));
		
		// re-create main xml file and refresh filelist
		$this->mc->filecreator->createGeneralFiles();
		$configSet = true;
		include('modules/filemanager.module.php');
		$fileManagerObj = new apdModuleFilemanager($this->mc);
		$fileManagerObj->refreshFilelist();
		
		header("Location: index.php?m=text&view_id=" . $this->viewId);
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
		// nothing to do here
		return true;
	}
}