<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

View class for display List concepts
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
	return new apdViewList($mainContainer,
		(
		(isset($_REQUEST['view_id']) && intval($_REQUEST['view_id']) >= 0)
			? intval($_REQUEST['view_id']):-1
		));
}

class apdViewList extends apdViewBasicModule
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
		include('templates/' . $this->mc->config['template'] . '/modules/list/list.html');
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
		$forLanguages[0] = "";
		
		// go through list of languages
		for($i = 0; $i < count($languageQuery->rows); $i++)
		{
			$availableLanguage = $languageQuery->rows[$i];
			// key for this language
			$currentLanguageTpl = preg_replace('#\{LANGUAGEID\}#si', $availableLanguage->local_id, $forLanguages[1][0]);
			// display name for this language in current session-language
			$currentLanguageTpl = preg_replace('#\{LANGUAGE\}#si', $this->mc->language->getLocalisation($availableLanguage->local_name), $currentLanguageTpl);
			$currentLanguageTpl = preg_replace('#\{COMMA\}#si', ($i < (count($languageQuery->rows)-1)) ? ',' : '', $currentLanguageTpl);
			
			
			$forLanguages[0] .= $currentLanguageTpl;
		}		
		$this->template = preg_replace('#\{FOR_LANGUAGES(.*?)FOR_LANGUAGES\}#si', $forLanguages[0], $this->template);
		
		$maxSectionId = 0;
		if(isset($this->viewId) && $this->viewId >= 0)
		{
			/*
			========
			sections
			========
			*/
			// get sections
			$sectionQuery = $this->mc->database->query("SELECT section_id, local_key FROM " . $this->mc->config['database_pref'] . "concept_list_section WHERE view_id = ?", array(array($this->viewId, "i")));
			preg_match_all('#\{FOR_SECTIONS(.*?)FOR_SECTIONS\}#si', $this->template, $forSections);
			$forSections[0] = "";
			foreach($sectionQuery->rows as $currentSection)
			{
				$maxSectionId = max($maxSectionId, $currentSection->section_id);
				$currentSectionTpl = preg_replace('#\{SECTION_ID\}#si', $currentSection->section_id, $forSections[1][0]);
				$currentSectionNamesArray = "";
				// get all localised names for this section
				$sectionNameQuery = $this->mc->database->query("SELECT local_value FROM " . $this->mc->config['database_pref'] . "localisation_keys WHERE local_key = ? ORDER BY local_id ASC", array(array($currentSection->local_key)));
				for($i = 0; $i < count($sectionNameQuery->rows); $i++)
				{
					$currentSectionNamesArray .= '"' . $sectionNameQuery->rows[$i]->local_value . '"' . ( ($i < (count($sectionNameQuery->rows)-1)) ? ',' : '');
				}
				$currentSectionTpl = preg_replace('#\{SECTION_NAMES\}#si', $currentSectionNamesArray, $currentSectionTpl);
				$forSections[0] .= $currentSectionTpl;
			}
			$this->template = preg_replace('#\{FOR_SECTIONS(.*?)FOR_SECTIONS\}#si', $forSections[0], $this->template);
			
			/*
			========
			rows
			========
			*/
			// get rows
			$sectionRowQuery = $this->mc->database->query("SELECT section_id, cell_position, cell_content, cell_action, cell_image FROM " . $this->mc->config['database_pref'] . "concept_list_cells WHERE view_id = ? ORDER BY section_id, cell_position ASC", array(array($this->viewId, "i")));
			preg_match_all('#\{FOR_SECTIONROWS(.*?)FOR_SECTIONROWS\}#si', $this->template, $forSectionRows);
			$forSectionRows[0] = "";
			foreach($sectionRowQuery->rows as $currentSectionRow)
			{
				$currentSectionRowTpl = preg_replace('#\{SECTION_ID\}#si', $currentSectionRow->section_id, $forSectionRows[1][0]);
				$currentSectionRowTpl = preg_replace('#\{ROW_ID\}#si', $currentSectionRow->cell_position, $currentSectionRowTpl);
				$currentSectionRowTpl = preg_replace('#\{ROW_IMAGE\}#si', '"' . $currentSectionRow->cell_image . '"', $currentSectionRowTpl);
				$currentSectionRowTpl = preg_replace('#\{ROW_ACTION\}#si', '"' . $currentSectionRow->cell_action . '"', $currentSectionRowTpl);
				$currentSectionNamesArray = "";
				// get all localised names for this section
				$sectionNameQuery = $this->mc->database->query("SELECT local_value FROM " . $this->mc->config['database_pref'] . "localisation_keys WHERE local_key = ? ORDER BY local_id ASC", array(array($currentSectionRow->cell_content)));
				for($i = 0; $i < count($sectionNameQuery->rows); $i++)
				{
					$currentSectionNamesArray .= '"' . $sectionNameQuery->rows[$i]->local_value . '"' . (($i < (count($sectionNameQuery->rows)-1)) ? ',' : '');
				}
				$currentSectionRowTpl = preg_replace('#\{ROW_NAMES\}#si', $currentSectionNamesArray, $currentSectionRowTpl);
				$forSectionRows[0] .= $currentSectionRowTpl;
			}
			$this->template = preg_replace('#\{FOR_SECTIONROWS(.*?)FOR_SECTIONROWS\}#si', $forSectionRows[0], $this->template);
		}
		else
		{
			$this->template = preg_replace('#\{FOR_SECTIONS(.*?)FOR_SECTIONS\}#si', '', $this->template);
			$this->template = preg_replace('#\{FOR_SECTIONROWS(.*?)FOR_SECTIONROWS\}#si', '', $this->template);
		}
		$this->template = preg_replace('#\{MAXIMUMSECTIONID\}#si', $maxSectionId, $this->template);
		
		
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
}