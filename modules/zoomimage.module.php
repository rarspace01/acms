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
		for($i = 1; $i <= $_REQUEST['maxbuttonid']; $i++)
		{
			// check if section exists
			if(isset($_REQUEST['loadaction_view_' . $i]))
			{
				$buttonCounter++;
				
				if($_REQUEST['loadaction_view_' . $i] >= 0)
				{
					// it is a linked view
					if($_REQUEST['loadaction_view_' . $i] > 0)
					{
						// insert into concept_zoommap_actions
						$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_zoommap_actions (view_id, action_posx, action_posy, action_width, action_height, action_command) VALUES(?,?,?,?,?,?)", array(array($this->viewId, "i"), array($_REQUEST['button_left_' . $i], "i"), array($_REQUEST['button_top_' . $i], "i"), array($_REQUEST['button_width_' . $i], "i"), array($_REQUEST['button_height_' . $i], "i"), array($_REQUEST['loadaction_view_' . $i], "i")));
						
						// check if link exists in database already
						$checkViewDestinationQuery = $this->mc->database->query("SELECT COUNT(*) as count FROM " . $this->mc->config['database_pref'] . "view_links WHERE view_id_parent = ? AND view_id_destination = ?", array(array($this->viewId, "i"), array($_REQUEST['loadaction_view_' . $i], "i")));
						if($checkViewDestinationQuery->rows[0]->count == 0)
						{
							// insert new link
							$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "view_links (view_id_parent, view_id_destination) VALUES(?, ?)", array(array($this->viewId, "i"), array($_REQUEST['loadaction_view_' . $i], "i")));
						}
					}
					else
					{
						// insert into concept_zoommap_actions
						$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_zoommap_actions (view_id, action_posx, action_posy, action_width, action_height, action_command) VALUES(?,?,?,?,?,?)", array(array($this->viewId, "i"), array($_REQUEST['button_left_' . $i], "i"), array($_REQUEST['button_top_' . $i], "i"), array($_REQUEST['button_width_' . $i], "i"), array($_REQUEST['button_height_' . $i], "i"), array($_REQUEST['loadaction_view_' . $i . '_custom'], "s")));
					}
				}
			}
		}
		
		$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "concept_zoommap_images (view_id, image, width, height) VALUES(?,?,0,0)", array(array($this->viewId, "i"), array($_REQUEST['picture_name'])));
		
		// update concept type id for this view
		$conceptQuery = $this->mc->database->query("SELECT concept_id FROM " . $this->mc->config['database_pref'] . "concepts WHERE concept_key = 'zoomimage'", array());
		$this->mc->database->query("UPDATE " . $this->mc->config['database_pref'] . "views SET view_c_type = ? WHERE view_id = ?", array(array($conceptQuery->rows[0]->concept_id, "i"), array($this->viewId, "i")));
		
		$this->createXmlFile();
		$this->createImageTiles();
		
		// re-create main xml file and refresh filelist
		$this->mc->filecreator->createMainXml();
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
	
		$imageQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_zoommap_images WHERE view_id = ?", array(array($this->viewId, "i")));
		if(count($imageQuery->rows) > 0)
		{
			$imageFileName = $imageQuery->rows[0]->image;
		}
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
				if($format == 'png')
				{
					$image = imagecreatefrompng($filePath);
				}
				else
					return false;
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
						imagepng($tileImage, $this->mc->config['upload_dir'] . 'root/scrolltiles/' . $fileNameParts['filename'] . '_' . $vertiTile . '_' . $horizTile . '.png', 9);
						imagedestroy($tileImage);
					}
				}
				imagedestroy($image);
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
		$output = '<?xml version="1.0" encoding="UTF-8"?><xml>';
		
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
		$imageQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "concept_zoommap_images WHERE view_id = ?", array(array($this->viewId, "i")));
		if(count($imageQuery->rows) > 0)
		{
			$imageFileName = $imageQuery->rows[0]->image;
		}
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
						
						$output .= '<imagetile src="' . $fileNameParts['filename'] . '_' . $vertiTile . '_' . $horizTile .'" posx="' . ($horizTile * $this->imageTileSize) . '" posy="' . ($vertiTile * $this->imageTileSize) . '" width="' . $tileWidth . '" height="' . $tileHeight . '">';
						
						$buttonActionQuery = $this->mc->database->query("SELECT A.*, B.view_name FROM " . $this->mc->config['database_pref'] . "concept_zoommap_actions AS A, " . $this->mc->config['database_pref'] . "views AS B WHERE A.view_id = ? AND A.action_command = B.view_id", array(array($this->viewId, "i")));
						foreach($buttonActionQuery->rows as $currentButtonAction)
						{
							//print_r($currentButtonAction);
							// check if buttonaction is suitable for this current tile
							
							// case 1: action starts within this tile
							if(
								// contains X
								($this->scale($currentButtonAction->action_posx) >= ($horizTile * $this->imageTileSize) && $this->scale($currentButtonAction->action_posx) <= ($horizTile * $this->imageTileSize + $tileWidth)) &&
								// contains Y
								($this->scale($currentButtonAction->action_posy) >= ($vertiTile * $this->imageTileSize) && $this->scale($currentButtonAction->action_posy) <= ($vertiTile * $this->imageTileSize + $tileHeight))
							)
							{
								$output .= '<action';
								$output .= ' posx="' . ($this->scale($currentButtonAction->action_posx) - ($horizTile * $this->imageTileSize)) . '"';
								$output .= ' posy="' . ($this->scale($currentButtonAction->action_posy) - ($vertiTile * $this->imageTileSize)) . '"';
								$output .= ' height="' . min($this->scale($currentButtonAction->action_height), (($tileHeight + $vertiTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posy))) . '"';
								$output .= ' width="' . min($this->scale($currentButtonAction->action_width), (($tileWidth + $horizTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posx))) . '"';
								$output .= ' action="loadPage::' . $currentButtonAction->view_name . "&amp;YES\" />\n";
							}
							// case 2: action started above this tile
							else if(
								// contains X
								($this->scale($currentButtonAction->action_posx) >= ($horizTile * $this->imageTileSize) && $this->scale($currentButtonAction->action_posx) <= ($horizTile * $this->imageTileSize + $tileWidth)) &&
								// does not contain Y
								($this->scale($currentButtonAction->action_posy) <= ($vertiTile * $this->imageTileSize) && ($this->scale($currentButtonAction->action_posy) + $this->scale($currentButtonAction->action_height)) >= ($vertiTile * $this->imageTileSize))
							)
							{
								$output .= '<action';
								$output .= ' posx="' . ($this->scale($currentButtonAction->action_posx) - ($horizTile * $this->imageTileSize)) . '"';
								$output .= ' posy="0"';
								$output .= ' height="' . ($this->scale($currentButtonAction->action_height) - (($vertiTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posy))) . '"';
								$output .= ' width="' . min($this->scale($currentButtonAction->action_width), (($tileWidth + $horizTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posx))) . '"';
								$output .= ' action="loadPage::' . $currentButtonAction->view_name . "&amp;YES\" />\n";
							}
							// case 3: action started left to this tile
							else if(
								// does not containx X
								($this->scale($currentButtonAction->action_posx) <= ($horizTile * $this->imageTileSize) && ($this->scale($currentButtonAction->action_posx) + $this->scale($currentButtonAction->action_width)) >= ($horizTile * $this->imageTileSize)) &&
								// contains Y
								($this->scale($currentButtonAction->action_posy) >= ($vertiTile * $this->imageTileSize) && $this->scale($currentButtonAction->action_posy) <= ($vertiTile * $this->imageTileSize + $tileHeight))
							)
							{
								$output .= '<action';
								$output .= ' posx="0"';
								$output .= ' posy="' . ($this->scale($currentButtonAction->action_posy) - ($vertiTile * $this->imageTileSize)) . '"';
								$output .= ' height="' . min($this->scale($currentButtonAction->action_height), (($tileHeight + $vertiTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posy))) . '"';
								$output .= ' width="' . ($this->scale($currentButtonAction->action_width) - (($horizTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posx))) . '"';
								$output .= ' action="loadPage::' . $currentButtonAction->view_name . "&amp;YES\" />\n";
							}
							// case 4: action started above and left to this tile
							else if(
								// does not containx X
								($this->scale($currentButtonAction->action_posx) <= ($horizTile * $this->imageTileSize) && ($this->scale($currentButtonAction->action_posx) + $this->scale($currentButtonAction->action_width)) >= ($horizTile * $this->imageTileSize)) &&
								// does not contain Y
								($this->scale($currentButtonAction->action_posy) <= ($vertiTile * $this->imageTileSize) && ($this->scale($currentButtonAction->action_posy) + $this->scale($currentButtonAction->action_height)) >= ($vertiTile * $this->imageTileSize))
							)
							{
								$output .= '<action';
								$output .= ' posx="0"';
								$output .= ' posy="0"';
								$output .= ' height="' . ($this->scale($currentButtonAction->action_height) - (($vertiTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posy))) . '"';
								$output .= ' width="' . ($this->scale($currentButtonAction->action_width) - (($horizTile * $this->imageTileSize) - $this->scale($currentButtonAction->action_posx))) . '"';
								$output .= ' action="loadPage::' . $currentButtonAction->view_name . "&amp;YES\" />\n";
							}
						}
						
						$output .= "</imagetile>\n";
					}
				}
			}
		}
			
		$output .= '</xml>';
		
		$outputFileHandle = fopen($this->mc->config['upload_dir'] . '/root/xml/'. $this->viewDetails->view_name . '.xml', 'w');
		fwrite($outputFileHandle, $output);
		fclose($outputFileHandle);
		
		return true;
	}
	
	function scale($dimension)
	{
		return floor($dimension * $this->scale);
	}
}