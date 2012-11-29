<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Processing a setting state back to specific revision
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
		return new apdModuleRevisions($mainContainer);
	}
}

class apdModuleRevisions
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
		if(isset($_REQUEST['setback']) && is_numeric($_REQUEST['setback']) && $_REQUEST['setback'] > 1)
		{
			if($_REQUEST['setback'] >= $this->mc->database->query("SELECT max_revision FROM " . $this->mc->config['database_pref'] . "revisions")->rows[0]->max_revision)
				header("Location: index.php?m=revisions");
				
			// go through all tables, check if revision-column exists and delete all columns bigger than the re-set revision
			$allTablesQuery = $this->mc->database->query("SHOW TABLE STATUS");
			foreach($allTablesQuery->rows as $currentTableRow)
			{
				if(count($this->mc->database->query("SHOW COLUMNS FROM `" . $currentTableRow->Name . "` LIKE 'revision'")->rows) > 0)
				{
					$currentRevisionQuery = $this->mc->database->query("DELETE FROM `" . $currentTableRow->Name . "` WHERE revision > ?", array(array($_REQUEST['setback'], "i")));
				}
			}
			// set the main revision back
			$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "revisions SET revision_active = ?, max_revision = ?", array(array($_REQUEST['setback'], "i"), array($_REQUEST['setback'], "i")));
		}
		
		// re-create main xml file and refresh filelist
		$this->mc->filecreator->createGeneralFiles();
		$configSet = true;
		include('modules/filemanager.module.php');
		$fileManagerObj = new apdModuleFilemanager($this->mc);
		$fileManagerObj->refreshFilelist();
		
		header("Location: index.php?m=revisions");
	}
	
	function cleanUpDirectory()
	{
		$filePath = $this->mc->config['upload_dir'] . 'root/tabbar/';
		if($tabbarIconFolderHandle = opendir($filePath))
		{
			while (false !== ($currentTabbarIcon = readdir($tabbarIconFolderHandle)) )
			{
				if(!preg_match('#^\.|\.\.|/+|\\\\+$#si', $currentTabbarIcon))
				{
					unlink($filePath . $currentTabbarIcon);
				}
			}
		}
		$filePath = $this->mc->config['upload_dir'] . 'root/xml/';
		if($xmlFolderHandle = opendir($filePath))
		{
			while (false !== ($currentXmlFile = readdir($xmlFolderHandle)) )
			{
				if(preg_match('#^' . $this->viewDetails->view_name . '#si', $currentXmlFile))
				{
					unlink($filePath . $currentXmlFile);
				}
			}
		}
		closedir($tabbarIconFolderHandle);
	}
}