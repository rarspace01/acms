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
		return new apdModuleLocalisation($mainContainer);
	}
}

class apdModuleLocalisation
{
	
	/**
	* function - Constructor
	* --
	* @param: $mainContainer
	*		container that contains all instances
	* @return: class
	* --
	*/
	function __construct($mainContainer)
	{
		$this->mc		= $mainContainer;
	}	

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
		// go through list of languages
		$availableLanguageQuery = $this->mc->database->query("SELECT local_id FROM " . $this->mc->config['database_pref'] . "localisations");
		foreach($availableLanguageQuery->rows as $lang)
		{
			// for every language, create an entry in _concept_text for the html-text
			$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "localisations SET local_active = ? WHERE local_id = ?", array(array(((isset($_REQUEST['language_' . $lang->local_id]) && $_REQUEST['language_' . $lang->local_id] == 'active') ? 1 : 0) , "i"), array($lang->local_id, "i")));
		}
		
		header("Location: index.php?m=localisations");
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