<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Language class
--
gets the language-item from database for
a specific key
*/

if(!isset($configSet) OR !$configSet)
	exit();

class apdLanguage
{
	public $localeId;

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
		$this->mc			= $mainContainer;
		$this->language		= $this->mc->config['language'];
		$this->localeId	= $this->mc->database->query("SELECT local_id FROM " . $this->mc->config['database_pref'] . "localisations WHERE local_name = ?", array(array($this->language, "s")))->rows[0]->local_id;
	}
	
	/**
	* function - getLocalisation
	* --
	* obtains a language-term from the database
	* in the current language
	* --
	* @param: $key
	*		key for the language term
	* @return: (String) localise
	*		localised term
	* --
	*/
	function getLocalisation($key)
	{
		// first check if it is a "system"-term
		$localiseQuery = $this->mc->database->query("SELECT `" . $this->language . "` AS localise FROM " . $this->mc->config['database_pref'] . "languages WHERE `key` = ?", array(array($key, "s")));
		
		if(count($localiseQuery->rows) > 0)
			return $localiseQuery->rows[0]->localise;
		else
		{
			// if not, check if it is user-generated content
			$userLocalisationQuery = $this->mc->database->query("SELECT A.local_value AS value FROM " . $this->mc->config['database_pref'] . "localisation_keys AS A WHERE A.local_key = ? AND A.local_id = ?", array(array($key, "s"), array($this->localeId, "i")), array(array("localisation_keys", "local_id", "local_key")));
			if(count($userLocalisationQuery->rows) > 0)
				return $userLocalisationQuery->rows[0]->value;
		}
		return $key;
	}	
}
?>