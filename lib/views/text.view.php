<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

View class for display Home
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
	return new apdViewText($mainContainer,
		(
		(isset($_REQUEST['view_id']) && intval($_REQUEST['view_id']) >= 0)
			? intval($_REQUEST['view_id']):-1
		));
}

class apdViewText extends apdViewBasicModule
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
		include('templates/' . $this->mc->config['template'] . '/modules/text/text.html');
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
		textfields
		=============
		*/		
		// load all languages from database, these are the columns in the language-table
		$languageQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "localisations WHERE local_active = 1");		
		// get snippet to display different textfields for all languages
		preg_match_all('#\{FOR_LANGUAGES(.*?)FOR_LANGUAGES\}#si', $this->template, $forLanguages);
		$forLanguages[0] = "";
		
		// go through list of languages
		foreach($languageQuery->rows as $availableLanguage)
		{
			// key for this language
			$currentLanguageTpl = preg_replace('#\{LANGUAGEID\}#si', $availableLanguage->local_id, $forLanguages[1][0]);
			// display name for this language in current session-language
			$currentLanguageTpl = preg_replace('#\{LANGUAGE\}#si', $this->mc->language->getLocalisation($availableLanguage->local_name), $currentLanguageTpl);
			
			// if there is a valid view that exists already (edit mode)
			if($this->viewId >= 0)
			{
				// load content from database
				$textContentQuery = $this->mc->database->query("SELECT content FROM " . $this->mc->config['database_pref'] . "concept_text AS A WHERE view_id = ? AND language = ?", array(array($this->viewId, "i"), array($availableLanguage->local_id, "s")), array(array("concept_text", "view_id", "language")));
				$currentLanguageTpl = preg_replace('#\{TEXTCONTENT\}#si', $textContentQuery->rows[0]->content, $currentLanguageTpl);
			}
			else
				$currentLanguageTpl = preg_replace('#\{TEXTCONTENT\}#si', '', $currentLanguageTpl);
			
			$forLanguages[0] .= $currentLanguageTpl;
		}
		
		$this->template = preg_replace('#\{FOR_LANGUAGES(.*?)FOR_LANGUAGES\}#si', $forLanguages[0], $this->template);
		
		return $this->template;
	}
}