<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

View class for display revision options
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
	return new apdViewRevisions($mainContainer);
}

class apdViewRevisions extends apdViewBasicModule
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
		include('templates/' . $this->mc->config['template'] . '/modules/revisions/revisions.html');
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
	
		preg_match_all('#\{FOR_REVISIONS(.*?)FOR_REVISIONS\}#si', $this->template, $forRevisions);
		$forRevisions[0] = "";
		preg_match_all('#\{FOR_CHANGES(.*?)FOR_CHANGES\}#si', $forRevisions[1][0], $forRevisionChanges);
		$forRevisionChanges[0] = "";
		
		$revisionQuery = $this->mc->database->query("SELECT revision_active, max_revision FROM " . $this->mc->config['database_pref'] . "revisions");
		$currentMaxRevision = $revisionQuery->rows[0]->max_revision;
		
		for($i = $currentMaxRevision; $i >= max(1, $currentMaxRevision - 10); $i--)
		{
			$currentRevisionTemplate = preg_replace('#\{REVISION_ID\}#si', $i, $forRevisions[1][0]);
			$forRevisionChanges[0] = "";
			
			// "heuristic", human readable changes
			$changedViewName = "";
			$changedViewId = 0;
			$changedStartview = 0;
			$changedViewType = "";
			$changedTabbarName = "";
			$changedTabbarState = 0;
			
			$allTablesQuery = $this->mc->database->query("SHOW TABLE STATUS");
			foreach($allTablesQuery->rows as $currentTableRow)
			{
				if(count($this->mc->database->query("SHOW COLUMNS FROM `" . $currentTableRow->Name . "` LIKE 'revision'")->rows) > 0)
				{
					$currentRevisionQuery = $this->mc->database->query("SELECT * FROM `" . $currentTableRow->Name . "` WHERE revision = ?", array(array($i, "i")));
					foreach($currentRevisionQuery->rows as $currentRevisionDetails)
					{
						// get values for "human readable" change log
						// get (localised) name for changed view (if any)
						if(preg_match('#^(.*?)_localisation_keys$#si', $currentTableRow->Name))
						{
							if($currentRevisionDetails->local_id == $this->mc->language->localeId && preg_match('#^view_name_([0-9]+?)$#si', $currentRevisionDetails->local_key))
							{
								$changedViewName = $currentRevisionDetails->local_value;
							}
						}
						// get details about tabbar
						if(preg_match('#^(.*?)_tabbars$#si', $currentTableRow->Name))
						{
							$changedTabbarName = $currentRevisionDetails->tabbar_name;
							$changedTabbarState = $currentRevisionDetails->tabbar_active;
						}
						// get view-type for changed view (if any)
						if(preg_match('#^(.*?)_views$#si', $currentTableRow->Name))
						{
							$changedViewId = $currentRevisionDetails->view_id;
							$changedViewType = $this->mc->language->getLocalisation($this->mc->database->query("SELECT concept_key FROM " . $this->mc->config['database_pref'] . "concepts WHERE concept_id = ?", array(array($currentRevisionDetails->view_c_type, "i")))->rows[0]->concept_key);
							if($currentRevisionDetails->view_start == 1)
								$changedStartview = $currentRevisionDetails->view_id;
						}
						
						$currentRevisionChange = "INSERT INTO `" . $currentTableRow->Name . "` (";
						$currentRevisonChangeCols = "";
						$currentRevisionChangeVals = "";
						$revisionQryObjectVars = get_object_vars($currentRevisionDetails);
						
						foreach($revisionQryObjectVars as $currentObjKey => $currentObjVar)
						{
							$currentRevisonChangeCols .= "`" . $currentObjKey . "`, ";
							$currentRevisionChangeVals .= "'" . $currentObjVar . "', ";
						}
						$currentRevisonChangeCols = substr($currentRevisonChangeCols, 0, - 2);
						$currentRevisionChangeVals = substr($currentRevisionChangeVals, 0, - 2);
						$currentRevisionChange .= $currentRevisonChangeCols . ") VALUES(" . $currentRevisionChangeVals . ");";
						$forRevisionChanges[0] .= preg_replace('#\{CURRENT_CHANGE\}#si', $currentRevisionChange, $forRevisionChanges[1][0]);
					}
				}
			}
			// detailed technical change log
			$currentRevisionTemplate = preg_replace('#\{FOR_CHANGES(.*?)FOR_CHANGES\}#si', $forRevisionChanges[0], $currentRevisionTemplate);
			
			// human readable change log (minimalistic)
			$humanReadableLog = "";
			if(trim($changedViewName) != "")
			{
				$humanReadableLog .= "{LANG:changedview} <b>" . $changedViewName . " (#" . $changedViewId . ")</b> (" . $changedViewType . ")<br />";
			}
			if(trim($changedTabbarName) != "")
			{
				$humanReadableLog .= "{LANG:changedtabbar} <b>" . $changedTabbarName . "</b> {LANG:newtabbarstate} {LANG:" . ($changedTabbarState == 0 ? "tabbar_inactive" : "tabbar_active") . "}<br />";
			}
			if($changedStartview > 0)
			{
				if(trim($changedViewName) == "")
				{
					$humanReadableLog .= "{LANG:changedstartview} <b>" . $this->mc->language->getLocalisation($this->mc->database->query("SELECT view_name FROM " . $this->mc->config['database_pref'] . "views WHERE view_id = ?", array(array($changedStartview, "i")))->rows[0]->view_name) . "</b><br />";
				}
			}
			$currentRevisionTemplate = preg_replace('#\{HUMAN_READABLE_LOG\}#si', $humanReadableLog, $currentRevisionTemplate);	
			
			$forRevisions[0] .= $currentRevisionTemplate;
		}
		
		$this->template = preg_replace('#\{FOR_REVISIONS(.*?)FOR_REVISIONS\}#si', $forRevisions[0], $this->template);
	
		return $this->template;
	}
}