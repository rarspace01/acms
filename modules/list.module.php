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
	return new apdModuleList($mainContainer,
		(
		(isset($_REQUEST['view_id']) && intval($_REQUEST['view_id']) >= 0)
			? intval($_REQUEST['view_id']) : -1
		));
}

class apdModuleList extends apdModuleBasicModule
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
		=========
		edit mode
		=========
		*/
		
		// set the action-file (content) to the name of the view (=name of the HTML file)
		$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_action = view_name WHERE view_id = ?", array(array($this->viewId, "i")));
		
		// go through list of languages
		$availableLanguageQuery = $this->mc->database->query("SELECT local_id FROM " . $this->mc->config['database_pref'] . "localisations WHERE local_active = 1");
		
		$sectionCounter = 0; // "nice" section counter, do not allow gaps
		// go through list of sections
		for($i = 1; $i <= $_REQUEST['maximumsectionid']; $i++)
		{
			// check if section exists
			if(isset($_REQUEST['section_name_' . $i . '_' . $availableLanguageQuery->rows[0]->local_id]))
			{
				$sectionCounter++;
				$sectionLanguageKey = 'list_section_' . $this->viewId . '_' . $sectionCounter;
				
				// update localisation-value for section-name
				foreach($availableLanguageQuery->rows as $availableLanguage)
					$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "localisation_keys (local_id, local_key, local_value, revision) VALUES(?,?,?,?)", array(array($availableLanguage->local_id, "i"), array($sectionLanguageKey), array($_REQUEST['section_name_' . $i . '_' . $availableLanguage->local_id]), array($this->mc->config['current_revision'], "i")));
					
				// insert into concept_list_section
				$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_list_section (view_id, section_id, local_key, revision) VALUES(?,?,?,?)", array(array($this->viewId, "i"), array($sectionCounter, "i"), array($sectionLanguageKey, "s"), array($this->mc->config['current_revision'], "i")));
					
				$rowCounter = 0; // "nice" row counter, do not allow gaps
				for($j = 1; $j <= $_REQUEST['section_' . $i . '_maximumrow']; $j++)
				{
					if(isset($_REQUEST['row_name_' . $i . '_' . $j . '_' . $availableLanguageQuery->rows[0]->local_id]))
					{
						$rowCounter++;
						$sectionRowLanguageKey = 'list_section_' . $this->viewId . '_' . $sectionCounter . '_row_' . $rowCounter;
						// update localisation-value for section-name
						foreach($availableLanguageQuery->rows as $availableLanguage)
							$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "localisation_keys (local_id, local_key, local_value, revision) VALUES(?,?,?,?)", array(array($availableLanguage->local_id, "i"), array($sectionRowLanguageKey), array($_REQUEST['row_name_' . $i . '_' . $j . '_' . $availableLanguage->local_id]), array($this->mc->config['current_revision'], "i")));
							
						// update concept_list_cells
						$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_list_cells (view_id, section_id, cell_position, cell_content, cell_action, cell_image, revision) VALUES(?, ?, ?, ?, ?, '', ?)", array(array($this->viewId, "i"), array($sectionCounter, "i"), array($rowCounter, "i"), array($sectionRowLanguageKey, "s"), array($_REQUEST['loadaction_view_' . $i . '_' . $j], "i"), array($this->mc->config['current_revision'], "i")));
					}
				}
			}
		}
		
		// update concept type id for this view
		$conceptQuery = $this->mc->database->query("SELECT concept_id FROM " . $this->mc->config['database_pref'] . "concepts WHERE concept_key = 'list'");
		$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_c_type = ? WHERE view_id = ?", array(array($conceptQuery->rows[0]->concept_id, "i"), array($this->viewId, "i")), array(array("views", "view_id")));
		
		$this->createXmlFile();
		
		// re-create main xml file and refresh filelist
		$this->mc->filecreator->createGeneralFiles();
		$configSet = true;
		include('modules/filemanager.module.php');
		$fileManagerObj = new apdModuleFilemanager($this->mc);
		$fileManagerObj->refreshFilelist();
		
		header("Location: index.php?m=list&view_id=" . $this->viewId);
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
		<?xml version="1.0" encoding="UTF-8"?>
		<!-- tableview -->
		<xml>
			<tableviewcells section="speisen" height="200" width="300">
				<row action="loadPage::touristinfoessenbrauerei&amp;YES" content="touristinfoessenbrauerei" image="brauereiausschank-icon.png" />
				<row action="loadWebsite::http://www.michels-restaurant.de&amp;Zum Hirsch&amp;YES" content="Michels Restaurant Zum Hirsch"  />
			</tableviewcells>
		</xml>
		*/
		$output = '<?xml version="1.0" encoding="UTF-8"?><xml>';
			
		$sectionQuery = $this->mc->database->query("SELECT section_id, local_key FROM " . $this->mc->config['database_pref'] . "concept_list_section AS A WHERE view_id = ? ORDER BY section_id ASC", array(array($this->viewId, "i")), array(array("concept_list_section", "view_id", "section_id")));
		foreach($sectionQuery->rows as $currentSection)
		{
			$output .= '<tableviewcells section="' . $currentSection->local_key . '">';
			$sectionRowQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_list_cells AS A WHERE view_id = ? AND section_id = ? ORDER BY cell_position ASC", array(array($this->viewId, "i"), array($currentSection->section_id, "i")), array(array("concept_list_cells", "view_id", "section_id", "cell_position")));
			foreach($sectionRowQuery->rows as $currentSectionRow)
			{
				$output .= '<row content="' . $currentSectionRow->cell_content . '"';
				if($currentSectionRow->cell_action >= 0)
				{
					$rowActionQuery = $this->mc->database->query("SELECT view_name FROM " . $this->mc->config['database_pref'] . "views WHERE view_id = ?", array(array($currentSectionRow->cell_action)));
					if(count($rowActionQuery->rows) > 0)
					{
						$output .= ' action="loadPage::' . $rowActionQuery->rows[0]->view_name . '&amp;YES"';
						
						// check if link exists in database already
						$checkViewDestinationQuery = $this->mc->database->query("SELECT COUNT(*) as count FROM " . $this->mc->config['database_pref'] . "view_links AS A WHERE view_id_parent = ? AND view_id_destination = ?", array(array($this->viewId, "i"), array($currentSectionRow->cell_action, "i")), array(array("view_links", "view_id_parent", "view_id_destination")));
						if($checkViewDestinationQuery->rows[0]->count == 0)
						{
							// insert new link
							$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "view_links (view_id_parent, view_id_destination, revision) VALUES(?, ?, ?)", array(array($this->viewId, "i"), array($currentSectionRow->cell_action, "i"), array($this->mc->config['current_revision'], "i")));
						}
					}
				}
				$output .= ' />';
			}
			$output .= '</tableviewcells>';
		}
			
		$output .= '</xml>';
		
		$outputFileHandle = fopen($this->mc->config['upload_dir'] . '/root/xml/'. $this->viewDetails->view_name . '.xml', 'w');
		fwrite($outputFileHandle, $output);
		fclose($outputFileHandle);
		
		return true;
	}
}