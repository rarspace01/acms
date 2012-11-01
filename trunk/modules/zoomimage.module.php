<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Class for processing a sent form for this
view-concept
*/

if(!isset($configSet) OR !$configSet)
	exit();

// load basic view	
include('modules/basicmodule.module.php');

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
	return new apdModuleZoomimage($mainContainer,
		(
		(isset($_REQUEST['view_id']) && intval($_REQUEST['view_id']) >= 0)
			? intval($_REQUEST['view_id']) : -1
		));
}

class apdModuleZoomimage extends apdModuleBasicModule
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
		// imagetilesize is 256x256 pixel (maximum)
		$this->imageTileSize = 256;
	
		$originalViewId = $this->viewId;
		parent::processForm();		
		
		/*
		=========
		edit mode
		=========
		*/
		
		// set the action-file (content) to the name of the view (=name of the HTML file)
		$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_action = view_name WHERE view_id = ?", array(array($this->viewId, "i")));
		
		// first delete all existing links to simplify matters
		// later we check for links to other views (if the command loadPage::XXXX is called)
		$this->mc->database->query("DELETE FROM " . $this->mc->config['database_pref'] . "view_links WHERE view_id_parent = ?", array(array($this->viewId, "i")));
		
		// delete all content for this view
		$this->mc->database->query("DELETE FROM " . $this->mc->config['database_pref'] . "concept_zoommap_images WHERE view_id = ?", array(array($this->viewId, "i")));
		$this->mc->database->query("DELETE FROM " . $this->mc->config['database_pref'] . "concept_zoommap_actions WHERE view_id = ?", array(array($this->viewId, "i")));
				
		$buttonCounter = 0; // "nice" button counter, do not allow gaps
		// go through list of sections
		$viewTypesQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "devices", array());
		foreach($viewTypesQuery->rows as $currentViewType)
		{
			if(trim($_REQUEST['picture_name_' . $currentViewType->device_id]) != '')
			{
				for($i = 1; $i <= $_REQUEST['maxbuttonid_' . $currentViewType->device_id]; $i++)
				{
					// check if section exists
					if(isset($_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_id]))
					{
						$buttonCounter++;
						
						if($_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_id] >= 0)
						{
							// it is a linked view
							if($_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_id] > 0)
							{
								// insert into concept_zoommap_actions
								$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_zoommap_actions (view_id, view_type, action_posx, action_posy, action_width, action_height, action_command) VALUES(?,?,?,?,?,?,?)", array(array($this->viewId, "i"), array($currentViewType->device_id, "i"),array($_REQUEST['button_left_' . $i . '_' . $currentViewType->device_id], "i"), array($_REQUEST['button_top_' . $i . '_' . $currentViewType->device_id], "i"), array($_REQUEST['button_width_' . $i . '_' . $currentViewType->device_id], "i"), array($_REQUEST['button_height_' . $i . '_' . $currentViewType->device_id], "i"), array($_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_id], "i")));
								
								// check if link exists in database already
								$checkViewDestinationQuery = $this->mc->database->query("SELECT COUNT(*) as count FROM " . $this->mc->config['database_pref'] . "view_links WHERE view_id_parent = ? AND view_id_destination = ?", array(array($this->viewId, "i"), array($_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_id], "i")));
								if($checkViewDestinationQuery->rows[0]->count == 0)
								{
									// insert new link
									$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "view_links (view_id_parent, view_id_destination) VALUES(?, ?)", array(array($this->viewId, "i"), array($_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_id], "i")));
								}
							}
							else
							{
								// insert into concept_zoommap_actions
								$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_zoommap_actions (view_id, view_type, action_posx, action_posy, action_width, action_height, action_command) VALUES(?,?,?,?,?,?,?)", array(array($this->viewId, "i"), array($currentViewType->device_id, "i"), array($_REQUEST['button_left_' . $i . '_' . $currentViewType->device_id], "i"), array($_REQUEST['button_top_' . $i . '_' . $currentViewType->device_id], "i"), array($_REQUEST['button_width_' . $i . '_' . $currentViewType->device_id], "i"), array($_REQUEST['button_height_' . $i . '_' . $currentViewType->device_id], "i"), array($_REQUEST['loadaction_view_' . $i . '_' . $currentViewType->device_id . '_custom'], "s")));
							}
						}
					}
				}
				$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_zoommap_images (view_id, view_type, image, width, height) VALUES(?,?,?,0,0)", array(array($this->viewId, "i"), array($currentViewType->device_id, "i"), array($_REQUEST['picture_name_' . $currentViewType->device_id])));
			}
		}		
		
		// update concept type id for this view
		$conceptQuery = $this->mc->database->query("SELECT concept_id FROM " . $this->mc->config['database_pref'] . "concepts WHERE concept_key = 'zoomimage'", array());
		$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_c_type = ? WHERE view_id = ?", array(array($conceptQuery->rows[0]->concept_id, "i"), array($this->viewId, "i")));
		
		$this->createXmlFile();
		$this->createImageTiles();
		
		// re-create main xml file and refresh filelist
		$this->mc->filecreator->createGeneralFiles();
		$configSet = true;
		include('modules/filemanager.module.php');
		$fileManagerObj = new apdModuleFilemanager($this->mc);
		$fileManagerObj->refreshFilelist();
		
		header("Location: index.php?m=zoomimage&view_id=" . $this->viewId);
	}
	
	/**
	* function - createImageTiles
	* --
	* creates the XML-file for this view
	* --
	* @param: none
	* @return: (boolean)
	*		true if all went correct
	* --
	*/
	function createImageTiles()
	{
		$imageFileName = "";
	
		$imageQuery = $this->mc->database->query("SELECT A.image, B.view_name, A.view_type, C.device_suffix FROM " . $this->mc->config['database_pref'] . "concept_zoommap_images AS A, " . $this->mc->config['database_pref'] . "views AS B, " . $this->mc->config['database_pref'] . "devices AS C WHERE A.view_id = ? AND A.view_id = B.view_id AND A.view_type = C.device_id", array(array($this->viewId, "i")));
		foreach($imageQuery->rows as $currentImageFile)
		{
			$imageFileName = $currentImageFile->image;
			$filePath = $this->mc->config['upload_dir'] . 'modules/zoomimage/pictures/' . $imageFileName;
			
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
						
					$destinationFileName = $currentImageFile->view_name . '_tile_';
					// go through list of tiles, first horizontally
					for($horizTile = 0; $horizTile < ceil($width_orig / $this->imageTileSize); $horizTile++)
					{				
						// and then vertically
						for($vertiTile = 0; $vertiTile < ceil($height_orig / $this->imageTileSize); $vertiTile++)
						{
							$tileWidth = min($this->imageTileSize, ($width_orig - ($horizTile * $this->imageTileSize)));
							$tileHeight = min($this->imageTileSize, ($height_orig - ($vertiTile * $this->imageTileSize)));
							// Resample
							$tileImage = imagecreatetruecolor($tileWidth, $tileHeight);
							// make image transparent
							imagealphablending($tileImage, false);
							imagesavealpha($tileImage,true);
							$transparent = imagecolorallocatealpha($tileImage, 255, 255, 255, 127);
							imagefilledrectangle($tileImage, 0, 0, $tileWidth, $tileHeight, $transparent);
								
							imagecopyresampled($tileImage, $image,
								// destination point
								0, 0,
								// source point
								$horizTile * $this->imageTileSize, $vertiTile * $this->imageTileSize,
								// size
								$tileWidth, $tileHeight, $tileWidth, $tileHeight);
							
							// Output
							$outputFileSuffix = (count($imageQuery->rows) == 1 ? '' : ($currentImageFile->device_suffix));
							imagepng($tileImage, $this->mc->config['upload_dir'] . 'root/scrolltiles/' . $destinationFileName . $vertiTile . '_' . $horizTile . $outputFileSuffix . '.png', 9);
							imagedestroy($tileImage);
						}
					}
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
		<xml>
			<imagetile src="overview-rundgang-eg-1" posx="0" posy="0" width="320" height="208">
				<action posx="230" posy="23" width="86" height="86" command="performZoom" />
				<action posx="230" posy="113" width="86" height="86" command="loadPage::overviewrundgangnavigation2" />
			</imagetile>
		</xml>
		*/		
			
		$imageFileName = "";
		$imageQuery = $this->mc->database->query("SELECT A.image, B.view_name, A.view_type, C.device_suffix FROM " . $this->mc->config['database_pref'] . "concept_zoommap_images AS A, " . $this->mc->config['database_pref'] . "views AS B, " . $this->mc->config['database_pref'] . "devices AS C WHERE A.view_id = ? AND A.view_id = B.view_id AND A.view_type = C.device_id", array(array($this->viewId, "i")));
		foreach($imageQuery->rows as $currentImageFile)
		{
			$output = '<?xml version="1.0" encoding="UTF-8"?><xml>';
			$imageFileName = $currentImageFile->image;
		
			$filePath = $this->mc->config['upload_dir'] . 'modules/zoomimage/pictures/' . $imageFileName;
			$fileNameParts	= pathinfo($filePath);
			// check if file really exists
			if(!is_dir($filePath))
			{		
				// Get new dimensions
				list($width_orig, $height_orig) = getimagesize($filePath);
				if($width_orig > 0 && $height_orig > 0)
				{
					// as the picture will not be shown in original size, all dimensions have to be scaled
					// to original image size
					$this->scale = $width_orig / $_REQUEST['picture_org_dim_x'];
					$this->yScale = $height_orig / $_REQUEST['picture_org_dim_y'];
				
					// go through list of tiles, first horizontally
					for($horizTile = 0; $horizTile < ceil($width_orig / $this->imageTileSize); $horizTile++)
					{				
						// and then vertically
						for($vertiTile = 0; $vertiTile < ceil($height_orig / $this->imageTileSize); $vertiTile++)
						{
							$tileWidth = min($this->imageTileSize, ($width_orig - ($horizTile * $this->imageTileSize)));
							$tileHeight = min($this->imageTileSize, ($height_orig - ($vertiTile * $this->imageTileSize)));
							
							$output .= '<imagetile src="' . $currentImageFile->view_name . '_tile_' . $vertiTile . '_' . $horizTile .'.png" posx="' . ($horizTile * $this->imageTileSize) . '" posy="' . ($vertiTile * $this->imageTileSize) . '" width="' . $tileWidth . '" height="' . $tileHeight . '">';
							
							$buttonActionQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_zoommap_actions WHERE view_id = ? AND view_type = ?", array(array($this->viewId, "i"), array($currentImageFile->view_type, "i")));
							foreach($buttonActionQuery->rows as $currentButtonAction)
							{
								// check if buttonaction is suitable for this current tile
								
								$buttonOutput = "";
								// case 1: action starts within this tile
								if(
									// contains X
									($this->scale($currentButtonAction->action_posx) >= ($horizTile * $this->imageTileSize) && $this->scale($currentButtonAction->action_posx) <= ($horizTile * $this->imageTileSize + $tileWidth)) &&
									// contains Y
									($this->scale($currentButtonAction->action_posy) >= ($vertiTile * $this->imageTileSize) && $this->scale($currentButtonAction->action_posy) <= ($vertiTile * $this->imageTileSize + $tileHeight))
								)
								{
									$buttonOutput .= '<action';
									$buttonOutput .= ' posx="' . ($this->scale($currentButtonAction->action_posx) - ($horizTile * $this->imageTileSize)) . '"';
									$buttonOutput .= ' posy="' . ($this->scale($currentButtonAction->action_posy) - ($vertiTile * $this->imageTileSize)) . '"';
									$buttonOutput .= ' height="' . min($this->scale($currentButtonAction->action_height), (($tileHeight + $vertiTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posy))) . '"';
									$buttonOutput .= ' width="' . min($this->scale($currentButtonAction->action_width), (($tileWidth + $horizTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posx))) . '"';
								}
								// case 2: action started above this tile
								else if(
									// contains X
									($this->scale($currentButtonAction->action_posx) >= ($horizTile * $this->imageTileSize) && $this->scale($currentButtonAction->action_posx) <= ($horizTile * $this->imageTileSize + $tileWidth)) &&
									// does not contain Y
									($this->scale($currentButtonAction->action_posy) <= ($vertiTile * $this->imageTileSize) && ($this->scale($currentButtonAction->action_posy) + $this->scale($currentButtonAction->action_height)) >= ($vertiTile * $this->imageTileSize))
								)
								{
									$buttonOutput .= '<action';
									$buttonOutput .= ' posx="' . ($this->scale($currentButtonAction->action_posx) - ($horizTile * $this->imageTileSize)) . '"';
									$buttonOutput .= ' posy="0"';
									$buttonOutput .= ' height="' . ($this->scale($currentButtonAction->action_height) - (($vertiTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posy))) . '"';
									$buttonOutput .= ' width="' . min($this->scale($currentButtonAction->action_width), (($tileWidth + $horizTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posx))) . '"';
								}
								// case 3: action started left to this tile
								else if(
									// does not containx X
									($this->scale($currentButtonAction->action_posx) <= ($horizTile * $this->imageTileSize) && ($this->scale($currentButtonAction->action_posx) + $this->scale($currentButtonAction->action_width)) >= ($horizTile * $this->imageTileSize)) &&
									// contains Y
									($this->scale($currentButtonAction->action_posy) >= ($vertiTile * $this->imageTileSize) && $this->scale($currentButtonAction->action_posy) <= ($vertiTile * $this->imageTileSize + $tileHeight))
								)
								{
									$buttonOutput .= '<action';
									$buttonOutput .= ' posx="0"';
									$buttonOutput .= ' posy="' . ($this->scale($currentButtonAction->action_posy) - ($vertiTile * $this->imageTileSize)) . '"';
									$buttonOutput .= ' height="' . min($this->scale($currentButtonAction->action_height), (($tileHeight + $vertiTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posy))) . '"';
									$buttonOutput .= ' width="' . ($this->scale($currentButtonAction->action_width) - (($horizTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posx))) . '"';
								}
								// case 4: action started above and left to this tile
								else if(
									// does not containx X
									($this->scale($currentButtonAction->action_posx) <= ($horizTile * $this->imageTileSize) && ($this->scale($currentButtonAction->action_posx) + $this->scale($currentButtonAction->action_width)) >= ($horizTile * $this->imageTileSize)) &&
									// does not contain Y
									($this->scale($currentButtonAction->action_posy) <= ($vertiTile * $this->imageTileSize) && ($this->scale($currentButtonAction->action_posy) + $this->scale($currentButtonAction->action_height)) >= ($vertiTile * $this->imageTileSize))
								)
								{
									$buttonOutput .= '<action';
									$buttonOutput .= ' posx="0"';
									$buttonOutput .= ' posy="0"';
									$buttonOutput .= ' height="' . ($this->scale($currentButtonAction->action_height) - (($vertiTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posy))) . '"';
									$buttonOutput .= ' width="' . ($this->scale($currentButtonAction->action_width) - (($horizTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posx))) . '"';
								}
								if($buttonOutput != "")
								{
									if(is_numeric($currentButtonAction->action_command))
									{
										$actionViewQuery = $this->mc->database->query("SELECT view_name FROM " . $this->mc->config['database_pref'] . "views WHERE view_id = ?", array(array($currentButtonAction->action_command, "i")));
										if(count($actionViewQuery->rows) > 0)
											$buttonOutput .= ' action="loadPage::' . $actionViewQuery->view_name . "&amp;YES\" />\n";
									}
									else
									{
										$buttonOutput .= ' action="' . str_replace('"', '\"', $currentButtonAction->action_command) . "\" />\n";
									}
									$output .= $buttonOutput;
								}
							}
							
							$output .= "</imagetile>\n";
						}
					}
				}
			}
				
			$output .= '</xml>';
			
			$outputFileSuffix = (count($imageQuery->rows) == 1 ? '' : ($currentImageFile->device_suffix));
			$outputFileHandle = fopen($this->mc->config['upload_dir'] . '/root/xml/'. $this->viewDetails->view_name . $outputFileSuffix . '.xml', 'w');
			fwrite($outputFileHandle, $output);
			fclose($outputFileHandle);
		}
		return true;
	}
	
	function scale($dimension)
	{
		return floor($dimension * $this->scale);
	}
}