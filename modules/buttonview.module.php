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
		return new apdModuleButtonview($mainContainer,
			(
			(isset($_REQUEST['view_id']) && intval($_REQUEST['view_id']) >= 0)
				? intval($_REQUEST['view_id']) : -1
			));
	}
}

class apdModuleButtonview extends apdModuleBasicModule
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
				
		// go through list of sections
		$viewTypesQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "devices", array());
		foreach($viewTypesQuery->rows as $currentViewType)
		{
			$buttonCounter = 0; // "nice" button counter, do not allow gaps
			if(trim($_REQUEST['picture_name_' . $currentViewType->device_key]) != '')
			{
				for($i = 1; $i <= $_REQUEST['maxbuttonid_' . $currentViewType->device_key]; $i++)
				{
					// check if section exists
					if(isset($_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_key]))
					{
						$buttonCounter++;
						
						if($_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_key] >= 0)
						{
							$buttonLanguageKey = 'buttonview_button_view_' . $this->viewId . '_device_' . $currentViewType->device_id . '_id_' . $buttonCounter;
							
							// update localisation-value for button-titles
							foreach($availableLanguageQuery->rows as $availableLanguage)
								$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "localisation_keys (local_id, local_key, local_value, revision) VALUES(?,?,?,?)", array(array($availableLanguage->local_id, "i"), array($buttonLanguageKey), array($_REQUEST['buttonview_view_' . $i . '_' . $currentViewType->device_key . '_' . $availableLanguage->local_id]), array($this->mc->config['current_revision'], "i")));
							
							$actionCommandValue = ($_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_key] > 0) ? $_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_key] : $_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_key . '_custom'];
							
							// insert into concept_buttonview_actions
							$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_buttonview_actions (view_id, view_type, action_title, action_posx, action_posy, action_width, action_height, action_command, revision) VALUES(?,?,?,?,?,?,?,?,?)", array(array($this->viewId, "i"), array($currentViewType->device_id, "i"), array($buttonLanguageKey), array($_REQUEST['button_left_' . $i . '_' . $currentViewType->device_key], "i"), array($_REQUEST['button_top_' . $i . '_' . $currentViewType->device_key], "i"), array($_REQUEST['button_width_' . $i . '_' . $currentViewType->device_key], "i"), array($_REQUEST['button_height_' . $i . '_' . $currentViewType->device_key], "i"), array($actionCommandValue, "s"), array($this->mc->config['current_revision'], "i")));
							
							// it is a linked view
							if($_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_key] > 0)
							{	
								// check if link exists in database already
								$checkViewDestinationQuery = $this->mc->database->query("SELECT COUNT(*) as count FROM " . $this->mc->config['database_pref'] . "view_links WHERE view_id_parent = ? AND view_id_destination = ?", array(array($this->viewId, "i"), array($_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_key], "i")));
								if($checkViewDestinationQuery->rows[0]->count == 0)
								{
									// insert new link
									$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "view_links (view_id_parent, view_id_destination, revision) VALUES(?, ?, ?)", array(array($this->viewId, "i"), array($_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_key], "i"), array($this->mc->config['current_revision'], "i")));
								}
							}
						}
					}
				}
				$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_buttonview_images (view_id, view_type, image, width, height, revision) VALUES(?,?,?,0,0,?)", array(array($this->viewId, "i"), array($currentViewType->device_id, "i"), array($_REQUEST['picture_name_' . $currentViewType->device_key]), array($this->mc->config['current_revision'], "i")));
			}
		}		
		
		// update concept type id for this view
		$conceptQuery = $this->mc->database->query("SELECT concept_id FROM " . $this->mc->config['database_pref'] . "concepts WHERE concept_key = 'buttonview'", array());
		$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_c_type = ?, view_background = ? WHERE view_id = ?", array(array($conceptQuery->rows[0]->concept_id, "i"), array($this->viewDetails->view_name . '.png'), array($this->viewId, "i")), array(array("views", "view_id")));
		
		$this->cleanUpDirectory();
		$this->createBackgroundImages();
		$this->createXmlFile();
		
		// re-create main xml file and refresh filelist
		$this->mc->filecreator->createGeneralFiles();
		include('modules/filemanager.module.php');
		$fileManagerObj = new apdModuleFilemanager($this->mc);
		$fileManagerObj->refreshFilelist();
		
		header("Location: index.php?m=buttonview&view_id=" . $this->viewId);
	}
	
	/**
	* function - createBackgroundImages
	* --
	* creates resized images for this view
	* --
	* @param: none
	* @return: (boolean)
	*		true if all went correct
	* --
	*/
	function createBackgroundImages()
	{
		$imageFileName = "";
	
		$imageQuery = $this->mc->database->query("SELECT A.image, B.view_name, A.view_type, C.device_suffix, C.device_key FROM " . $this->mc->config['database_pref'] . "concept_buttonview_images AS A, " . $this->mc->config['database_pref'] . "views AS B, " . $this->mc->config['database_pref'] . "devices AS C WHERE A.view_id = ? AND A.view_id = B.view_id AND A.view_type = C.device_id", array(array($this->viewId, "i")), array(array("concept_buttonview_images", "view_id", "view_type"), array("views", "view_id")));
		foreach($imageQuery->rows as $currentImageFile)
		{
			$imageFileName = $currentImageFile->image;
			$filePath = $this->mc->config['upload_dir'] . 'modules/buttonview/pictures/' . $imageFileName;
			
			$fileNameParts	= pathinfo($filePath);
			$format = $fileNameParts['extension'];
			
			// check if file really exists
			if(!is_dir($filePath))
			{
				// Get new dimensions
				list($width_orig, $height_orig) = getimagesize($filePath);
				if($width_orig > 0 && $height_orig > 0)
				{				
					$image = null;
					if($format == 'jpg' || $format == 'jpeg')
					{
						$image = imagecreatefromjpeg($filePath);
					}
					else if($format == 'png')
					{
						$image = imagecreatefrompng($filePath);
					}
					else
						return false;
						
					$destinationFileName = $currentImageFile->view_name;
					
					// device-specific heights
					// will be "streched" on device probably, with tabbar and navigationbar
					// optimised image-size for retina
					$resizedWidth = ($currentImageFile->view_type == 2 ? 640 : 2048);
					$resizedHeight = ($currentImageFile->view_type == 2 ? 960 : 1536);
					
					$scaleDeviceWidth = ($currentImageFile->view_type == 2 ? 320 : 1024);
					$this->scale[$currentImageFile->device_key] = $scaleDeviceWidth / $_REQUEST['picture_org_dim_x_' . $currentImageFile->device_key];
					
					// Resample
					$resizedImage = imagecreatetruecolor($resizedWidth, $resizedHeight);
					// make image transparent
					imagealphablending($resizedImage, false);
					imagesavealpha($resizedImage,true);
					$transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
					imagefilledrectangle($resizedImage, 0, 0, $resizedWidth, $resizedHeight, $transparent);
								
					imagecopyresampled($resizedImage, $image,
						0, 0, // destination point
						0, 0, // source point
						$resizedWidth, $resizedHeight, // size destination
						$width_orig, $height_orig // size source
						);
							
					// Output
					$outputFileSuffix = (count($imageQuery->rows) == 1 ? '' : ($currentImageFile->device_suffix));
					imagepng($resizedImage, $this->mc->config['upload_dir'] . 'root/pictures/' . $destinationFileName . $outputFileSuffix . '.png', 7);
					imagedestroy($resizedImage);
					
					imagedestroy($image);
				}
			}
		}
		return false;
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
		<!-- buttonmenu -->
		<xml>	
			<button posx="010" posy="010" width="145" height="40" title="overviewdaten" action="loadPage::overviewdaten&amp;YES" />
		</xml>
		*/

		$deviceTypeQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "devices", array());
		foreach($deviceTypeQuery->rows as $currentDeviceType)
		{
			$output = '<?xml version="1.0" encoding="UTF-8"?><xml>';
			$buttonActionQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_buttonview_actions AS A WHERE view_id = ? AND view_type = ?", array(array($this->viewId, "i"), array($currentDeviceType->device_id, "i")), array(array("concept_buttonview_actions", "view_id", "view_type", "action_posx", "action_posy", "action_width", "action_height")));
			foreach($buttonActionQuery->rows as $currentButtonAction)
			{
				// check if buttonaction is suitable for this current tile
								
				$output .= '<button';
				$output .= ' posx="' . ($this->scale($currentButtonAction->action_posx, $currentDeviceType->device_key)) . '"';
				$output .= ' posy="' . ($this->scale($currentButtonAction->action_posy, $currentDeviceType->device_key)) . '"';
				$output .= ' height="' . ($this->scale($currentButtonAction->action_height, $currentDeviceType->device_key)) . '"';
				$output .= ' width="' . ($this->scale($currentButtonAction->action_width, $currentDeviceType->device_key)) . '"';
				$output .= ' title="' . $currentButtonAction->action_title . '"';
				
				if(is_numeric($currentButtonAction->action_command))
				{
					$actionViewQuery = $this->mc->database->query("SELECT A.view_name, B.concept_key FROM " . $this->mc->config['database_pref'] . "views AS A, " . $this->mc->config['database_pref'] . "concepts AS B WHERE A.view_id = ? AND A.view_c_type = B.concept_id", array(array($currentButtonAction->action_command, "i")));
					if(count($actionViewQuery->rows) > 0)
					{
						// check if there exists a special class for creating the xml definitions
						$className = "apdFilecreator" . strtoupper(substr($actionViewQuery->rows[0]->concept_key, 0, 1)) . substr($actionViewQuery->rows[0]->concept_key, 1);
						if(!class_exists($className))
						{
							include('modules/' . $actionViewQuery->rows[0]->concept_key . '.module.php');
						}
						if(class_exists($className))
						{
							$currentFileCreator = new $className;
							$output .= ' action="' . $currentFileCreator->createLink($actionViewQuery->rows[0]->view_name, $currentButtonAction->action_command, $currentDeviceType->device_key) . "\" />\n";
						}
						else
						{
							$output .= ' action="loadPage::' . $actionViewQuery->rows[0]->view_name . "&amp;YES\" />\n";
						}
					}
				}
				else
				{
					$output .= ' action="' . str_replace('"', '\"', $currentButtonAction->action_command) . "\" />\n";
				}
			}
				
			$output .= '</xml>';
			
			if(count($buttonActionQuery->rows) > 0)
			{
				$imageQuery = $this->mc->database->query("SELECT image FROM " . $this->mc->config['database_pref'] . "concept_buttonview_images AS A WHERE view_id = ?", array(array($this->viewId, "i")), array(array("concept_buttonview_images", "view_id", "view_type")));
				$outputFileSuffix = (count($imageQuery->rows) == 1 ? '' : ($currentDeviceType->device_suffix));
				$outputFileHandle = fopen($this->mc->config['upload_dir'] . '/root/xml/'. $this->viewDetails->view_name . $outputFileSuffix . '.xml', 'wb');
				fwrite($outputFileHandle, $output);
				fclose($outputFileHandle);
			}
		}
		return true;
	}
	
	function scale($dimension, $device)
	{
		return floor($dimension * $this->scale[$device]);
	}
	
	function cleanUpDirectory()
	{
		parent::cleanUpDirectory();
		$filePath = $this->mc->config['upload_dir'] . 'root/pictures/';
		if($buttonviewFolderHandle = opendir($filePath))
		{
			while (false !== ($currentPictureFile = readdir($buttonviewFolderHandle)) )
			{
				if(preg_match('#^' . $this->viewDetails->view_name . '(.*?)#si', $currentPictureFile))
				{
					unlink($filePath . $currentPictureFile);
				}
			}
		}
		closedir($buttonviewFolderHandle);
	}
}