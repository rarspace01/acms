<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Hierarchy class
--
will create an array containing the app as hierarchical
structure with tabbars and navigation controllers
*/

if(!isset($configSet) OR !$configSet)
	exit();

class apdFileCreator
{
	/**
	* function - Constructor
	* --
	* @param: $mainContainer
	*		container object for all instances
	* @return: class
	* --
	*/
	function __construct($mainContainer)
	{
		$this->mc = $mainContainer;
	}
	
	
	/**
	* function - createGeneralFiles
	* --
	* create all files automatically
	* --
	* @param: none
	* @return:  none
	* --
	*/
	function createGeneralFiles()
	{
		$this->createMainXml();
		$this->createLocalisations();
	}
	
	
	/**
	* function - createMainXml
	* --
	* @param: none
	* @return: (String) content for the main xml
			which contains all tabbars and views
	* --
	*/
	function createMainXml()
	{
		$output = '<?xml version="1.0" encoding="UTF-8"?><app>';
		/*
		===============
		get all tabbars
		===============
		*/
		// NOTE: we will only consider the "active" tabbars here
		$tabbarQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "tabbars WHERE tabbar_active = 1 ORDER BY tabbar_id", array());
		$tabbarOutput = '<tabbars>';
		foreach($tabbarQuery->rows as $currentTabbar)
		{
			$tabbarOutput .= '<tabbar tabid="' . $currentTabbar->tabbar_id . '">';
			$tabbarTabQuery = $this->mc->database->query("SELECT A.tab_default, B.view_name, A.tab_icon FROM " . $this->mc->config['database_pref'] . "tabs AS A, " . $this->mc->config['database_pref'] . "views AS B WHERE A.tabbar_id = ? AND A.tab_view = B.view_id ORDER BY tab_position ASC", array(array($currentTabbar->tabbar_id, "i")));
			// iterate here, because we need the index as position
			// (do not rely that tab_position is coherent)
			for($i = 0; $i < count($tabbarTabQuery->rows); $i++)
			{
				$tabbarOutput .= '<button buttonid="' . ($i+1) . '" src="' . $tabbarTabQuery->rows[$i]->tab_icon . '" action="loadPage::' . $tabbarTabQuery->rows[$i]->view_name . '"' . ( ($tabbarTabQuery->rows[$i]->tab_default == 1) ? ' front="true"' : '') . ' />';
			}
			$tabbarOutput .= '</tabbar>';
		}
		if($tabbarOutput != '<tabbars>')
		{
			$output .= $tabbarOutput . '</tabbars>';
		}
		
		/*
		===============
		get all views
		===============
		*/
		$output .= '<pages>';
		$viewQuery = $this->mc->database->query("SELECT A.*, B.concept_view FROM " . $this->mc->config['database_pref'] . "views AS A, " . $this->mc->config['database_pref'] . "concepts AS B WHERE A.view_c_type = B.concept_id", array());
		foreach($viewQuery->rows as $currentView)
		{
			// general page information
			$output .= '<page pageid="' . $currentView->view_name . '"';
			if($currentView->view_start == 1)
				$output .= ' front="true"'; // front page?
			if($currentView->view_navigationbar == 1)
				$output .= ' initWithNaviCtrl="true"'; // has a navigation controller?
			if($currentView->view_tabbar >= 0)
				$output .= ' tabbarid="' . $currentView->view_tabbar . '"'; // initialises a tabbar?
			if($currentView->view_background != '' && $currentView->view_background != null)
				$output .= ' background="' . $currentView->view_background . '"'; // has a set background?
			$output .= '>';
				// view information
				$output .= '<view type="' . $currentView->concept_view . '" action="' . $currentView->view_action . '" />';
			$output .= '</page>';
		}
		$output .= '</pages>';
		
		$output .= '</app>';
		
		$outputFileHandle = fopen($this->mc->config['upload_dir'] . '/root/app_structure.xml', 'w');
		fwrite($outputFileHandle, $output);
		fclose($outputFileHandle);
		
		return $output;
	}

	/**
	* function - createLocalisations
	* --
	* create files with localisation strings
	* --
	* @param: none
	* @return: none
	* --
	*/
	function createLocalisations()
	{
		$localisationQuery = $this->mc->database->query("SELECT local_id, local_key FROM " . $this->mc->config['database_pref'] . "localisations WHERE local_active = 1", array());
		foreach($localisationQuery->rows as $currentLocale)
		{
			$currentLocaleOutput = "";
			$localStringsQuery = $this->mc->database->query("SELECT local_key, local_value FROM " . $this->mc->config['database_pref'] . "localisation_keys WHERE local_id = ?", array(array($currentLocale->local_id, "i")));
			foreach($localStringsQuery->rows as $currentLocalString)
			{
				$currentLocaleOutput .= '"' . $currentLocalString->local_key . '" = "' . $currentLocalString->local_value . '";' . "\n";
			}
			$outputFileHandle = fopen($this->mc->config['upload_dir'] . '/root/' . $currentLocale->local_key . '.lproj/Localizable.strings', 'wb');
			fwrite($outputFileHandle, $currentLocaleOutput);
			fclose($outputFileHandle);
		}
	}
	
}
?>