<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

View class for display Presentations
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
	return new apdViewPresentation($mainContainer,
		(
		(isset($_REQUEST['view_id']) && intval($_REQUEST['view_id']) >= 0)
			? intval($_REQUEST['view_id']):-1
		));
}

class apdViewPresentation extends apdViewBasicModule
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
		include('templates/' . $this->mc->config['template'] . '/modules/presentation/presentation.html');
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
		
		$currentImagesQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_presentation WHERE view_id = ?", array(array($this->viewId, "i")));
		
		/*
		=============
		existing PDFs
		=============
		*/
		// set of PDFs
		$pdfSet = array();
		if($mainFolderHandle = opendir($this->mc->config['upload_dir'] . '/root'))
		{
			while (false !== ($currentPdf = readdir($mainFolderHandle)) )
			{
				if(preg_match('#^(.*?)\.pdf$#si', $currentPdf))
					$pdfSet[] = $currentPdf;
			}
		}
		closedir($mainFolderHandle);
		reset($pdfSet);
		sort($pdfSet);
		
		preg_match_all('#\{FOR_PDFS(.*?)FOR_PDFS\}#si', $this->template, $forPdfsTemplate);
		$forPdfsTemplate[0] = "";
		foreach($pdfSet as $currentPdf)
		{
			$currentPdfTpl = preg_replace('#\{PDFNAME\}#si', $currentPdf, $forPdfsTemplate[1][0]);
			if(count($currentImagesQuery) > 0 && $currentImagesQuery->rows[0]->image_path == $currentPdf)
			{
				$currentPdfTpl = preg_replace('#\{HTMLSELECTED\}#si', 'selected', $currentPdfTpl);
			}
			else
			{
				$currentPdfTpl = preg_replace('#\{HTMLSELECTED\}#si', '', $currentPdfTpl);
			}
			$forPdfsTemplate[0] .= $currentPdfTpl;
		}
		$this->template = preg_replace('#\{FOR_PDFS(.*?)FOR_PDFS\}#si', $forPdfsTemplate[0], $this->template);
		
		return $this->template;
	}
}