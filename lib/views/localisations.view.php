<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

View class for display localisations
*/

if(!isset($configSet) OR !$configSet)
	exit();
	
include('lib/views/iview.view.php');	

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
	return new apdViewLocalisations($mainContainer);
}

class apdViewLocalisations implements apdIView
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
		include('templates/' . $this->mc->config['template'] . '/modules/localisations/localisations.html');
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
		
		preg_match_all('#\{FOR_LANGUAGES(.*?)FOR_LANGUAGES\}#si', $this->template, $forLanguages);
		$forLanguages[0] = "";
		
		$availableLocalisations = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "localisations ORDER BY local_id ASC", array());
		foreach($availableLocalisations->rows as $currentLocale)
		{
			$currentLanguageTpl = preg_replace('#\{LANGUAGE\}#si', $this->mc->language->getLocalisation($currentLocale->local_name), $forLanguages[1][0]);
			$currentLanguageTpl = preg_replace('#\{LANGUAGEID\}#si', ($currentLocale->local_id), $currentLanguageTpl);
			$currentLanguageTpl = preg_replace('#\{LANGUAGEACTIVE\}#si', (($currentLocale->local_active == 1) ? 'checked="checked"' : ''), $currentLanguageTpl);
				
			$forLanguages[0] .= $currentLanguageTpl;
		}
		$this->template = preg_replace('#\{FOR_LANGUAGES(.*?)FOR_LANGUAGES\}#si', $forLanguages[0], $this->template);
		
		return $this->template;
	}
}