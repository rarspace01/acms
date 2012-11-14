<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

View class for display the filemanager
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
	return new apdViewFilemanager($mainContainer);
}

class apdViewFilemanager implements apdIView
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
		include('templates/' . $this->mc->config['template'] . '/modules/filemanager/filemanager.html');
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
		===============
		get device list
		===============
		*/
		$deviceListQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "devices ORDER BY device_default DESC, device_id ASC", array());
		/*
		===============
		get language list
		===============
		*/
		$languageListQuery = $this->mc->database->query("SELECT * FROM " . $this->mc->config['database_pref'] . "localisations WHERE local_active = 1", array());
	
		/*
		===================
		init displayed path
		===================
		*/
		$showedpath = $this->mc->config['upload_dir'] . '/root';
		$_REQUEST['path'] = preg_replace('#(/+)|(\\\\+)|((\.+)(/+))#si', '/', $_REQUEST['path']);
		if(!isset($_REQUEST['path']) OR trim($_REQUEST['path'] == ''))
		{
			$_REQUEST['path'] = '/';
			$home = $showedpath;
		}
		else
		{
			$_REQUEST['path'] = stripslashes(trim($_REQUEST['path']));
			$_REQUEST['path'] = preg_replace('#(/?([a-zA-z]+?)\.lproj(.*?))|(^/?xml(.*?)$)|(^/?tabbar(.*?)$)#si', '', $_REQUEST['path']);
			$home = $showedpath.'/'.$_REQUEST['path'];
		}
		$home = preg_replace('#/$#si', '', $home);
		if(substr($_REQUEST['path'], -1) != '/') $_REQUEST['path'] .= '/';
	
		/*
		==============================
		read out files and directories
		==============================
		*/
		preg_match_all('#\{FOR_FILELIST(.*?)FOR_FILELIST\}#si', $this->template, $forFileList);
		$forFileList[0] = "";
		preg_match_all('#\{FOR_DEVICE_ADAPTIONS(.*?)FOR_DEVICE_ADAPTIONS\}#si', $forFileList[1][0], $forDeviceSpecificFiles);
		preg_match_all('#\{FOR_LOCALISATIONS(.*?)FOR_LOCALISATIONS\}#si', $forFileList[1][0], $forLocalisations);
		
		$localisationList = array();
		$folderList = array();
		$fileList = array();
		// go through main-directory and localisation directories (***.lproj)
		for($i = -1; $i < count($languageListQuery->rows); $i++)
		{
			$currentFolder = $home;
			$currentLanguage = "generic";
			if($i >= 0)
			{
				$currentFolder .= '/' . $languageListQuery->rows[$i]->local_key . '.lproj';
				$currentLanguage = $languageListQuery->rows[$i]->local_name;
				
				$localisationList[$languageListQuery->rows[$i]->local_name] = $languageListQuery->rows[$i]->local_key;
			}
				
			if(file_exists($currentFolder))
			{
				$folderContents = scandir($currentFolder);
				foreach($folderContents as $file)
				{
					if (!preg_match('#^\.|\.\.|/+|\\\\+$#si', $file))
					{
						$path = $currentFolder . '/' . $file;
						if(is_dir($currentFolder.'/'.$file) && $currentFolder == $home)
						{
							if(!preg_match('#(^tabbar$)|(^xml$)|((.*?)\.lproj$)#si', $file) && count(scandir($currentFolder . '/' . $file)) > 2)
								$folderList[] = $file;
						}
						else if(!is_dir($currentFolder.'/'.$file))
						{
							if(!preg_match('#((.*?)\.(xml|html|htm)$)|(Localizable.strings)#si', $file))
							{
								// is this file device-specific?
								// if so, show only the meta-file and list it as "specialisation-file"
								$deviceSpecificFile = false;
								foreach($deviceListQuery->rows as $currentDeviceType)
								{
									if(preg_match('#^(.*?)' . $currentDeviceType->device_suffix . '((\.([a-zA-z0-9]+?))?)$#si', $file))
									{
										$metaFile = preg_replace('#^(.*?)' . $currentDeviceType->device_suffix . '((\.([a-zA-z0-9]+?))?)$#si', '$1$2', $file);
										if(!isset($fileList[$metaFile]))
										{
											$fileList[$metaFile] = array();
										}
										$fileList[$metaFile][$currentDeviceType->device_key][$currentLanguage] = array($currentFolder.'/'.$file, $currentDeviceType->device_suffix);
										$deviceSpecificFile = true;
									}
								}
								if(!$deviceSpecificFile)
								{
									if(!isset($fileList[$file]))
										$fileList[$file] = array();
									$fileList[$file]["generic"][$currentLanguage] = array($currentFolder.'/'.$file, '');
								}
							}
						}
					}
				}
			}
		}
		
		reset($folderList);
		sort($folderList);
		foreach($folderList as $currentFolder)
		{
			$currentFolderTpl = preg_replace('#\{ICONTYPE\}#si', 'folder', $forFileList[1][0]);
			$currentFolderTpl = preg_replace('#\{FILENAME\}#si', '<a href="index.php?m=filemanager&path='.$_REQUEST['folder'].'/'.$currentFolder.'">'.$currentFolder.'/</a>', $currentFolderTpl);
			$currentFolderTpl = preg_replace('#\{FILESIZE\}#si', '-', $currentFolderTpl);
			$currentFolderTpl = preg_replace('#\{FILEMODIFIED\}#si', date("d.m.Y H:i", filemtime($home.'/'.$currentFolder)), $currentFolderTpl);
			$currentFolderTpl = preg_replace('#\{FOR_DEVICE_ADAPTIONS(.*?)FOR_DEVICE_ADAPTIONS\}#si', '', $currentFolderTpl);
			$currentFolderTpl = preg_replace('#\{FOR_LOCALISATIONS(.*?)FOR_LOCALISATIONS\}#si', '', $currentFolderTpl);
			$currentFolderTpl = preg_replace('#\{NOFOLDER(.*?)NOFOLDER\}#si', '', $currentFolderTpl);
			$forFileList[0] .= $currentFolderTpl;
		}

		reset($fileList);
		ksort($fileList);
		
		//echo "<pre>" . print_r($fileList, true) . "</pre>";
		
		foreach($fileList as $metaFileName => $currentFiles)
		{
			/*
			=========================
			generic files / metafiles
			=========================
			*/
			$currentFileTpl = preg_replace('#\{ICONTYPE\}#si', $this->fileTypeImage($metaFileName), $forFileList[1][0]);
			$currentFileTpl = preg_replace('#\{UNIQUEID\}#si', uniqid(), $currentFileTpl);
			
			// there exists a generic file (identical with metafilename)
			if(isset($currentFiles["generic"]) && isset($currentFiles["generic"]["generic"]))
			{
				$currentFileTpl = preg_replace('#\{FILESIZE\}#si', $this->sizeOfFile(filesize($currentFiles["generic"]["generic"][0])), $currentFileTpl);
				$currentFileTpl = preg_replace('#\{FILEMODIFIED\}#si', date("d.m.Y H:i", filemtime($currentFiles["generic"]["generic"][0])), $currentFileTpl);
				$currentFileTpl = preg_replace('#\{GENERIC_DEVICE|GENERIC_DEVICE\}#si', '', $currentFileTpl);
				$currentFileTpl = preg_replace('#\{GENERIC_LOCALISATION|GENERIC_LOCALISATION\}#si', '', $currentFileTpl);
				// check if there are other devices OR other localisations
				if(count($currentFiles) > 1 || count($currentFiles["generic"]) > 1)
				{
					// print out warning that other devices OR localisations won't be recognised
					$currentFileTpl = preg_replace('#\{GENERIC_WARNING|GENERIC_WARNING\}#si', '', $currentFileTpl);
				}
			}
			else
			{
				$currentFileTpl = preg_replace('#\{FILESIZE\}#si', '-', $currentFileTpl);
				$currentFileTpl = preg_replace('#\{FILEMODIFIED\}#si', '-', $currentFileTpl);
				$currentFileTpl = preg_replace('#\{GENERIC_DEVICE(.*?)GENERIC_DEVICE\}#si', '', $currentFileTpl);
			}
			$currentFileTpl = preg_replace('#\{GENERIC_LOCALISATION(.*?)GENERIC_LOCALISATION\}#si', '', $currentFileTpl);
			$currentFileTpl = preg_replace('#\{GENERIC_WARNING(.*?)GENERIC_WARNING\}#si', '', $currentFileTpl);
			
			/*
			=====================
			device specific files
			=====================
			*/
			$deviceSpecificFilesList = "";
			// now check for device-specific files
			foreach($currentFiles as $deviceTypeKey => $deviceSpecificFile)
			{
				if($deviceTypeKey != "generic")
				{
					$localisationFileList = "";
					$currentDeviceSpecificTpl = preg_replace('#\{DEVICETYPE\}#si', '{LANG:' . $deviceTypeKey . 'view}', $forDeviceSpecificFiles[1][0]);
					$currentDeviceSpecificTpl = preg_replace('#\{UNIQUEID\}#si', uniqid(), $currentDeviceSpecificTpl);
								
					// check if there is a generic file
					if(isset($deviceSpecificFile["generic"]))
					{
						$currentDeviceSpecificTpl = preg_replace('#\{DEVICEADAPTIONSIZE\}#si', $this->sizeOfFile(filesize($deviceSpecificFile["generic"][0])), $currentDeviceSpecificTpl);
						$currentDeviceSpecificTpl = preg_replace('#\{DEVICEADAPTIONMODIFIED\}#si', date("d.m.Y H:i", filemtime($deviceSpecificFile["generic"][0])), $currentDeviceSpecificTpl);
						$currentDeviceSpecificTpl = preg_replace('#\{DEVICEADAPTIONFILENAME\}#si', basename($deviceSpecificFile["generic"][0]), $currentDeviceSpecificTpl);
						// check if there is a generic file AND localised files for this device
						if(count($deviceSpecificFile) > 1)
						{
							// print out warning that other devices won't be recognised
							$currentDeviceSpecificTpl = preg_replace('#\{GENERIC_WARNING|GENERIC_WARNING\}#si', '', $currentDeviceSpecificTpl);
						}
						$currentDeviceSpecificTpl = preg_replace('#\{GENERIC_LOCALISATION|GENERIC_LOCALISATION\}#si', '', $currentDeviceSpecificTpl);
					}
					else
					{
						$currentDeviceSpecificTpl = preg_replace('#\{DEVICEADAPTIONSIZE\}#si', '-', $currentDeviceSpecificTpl);
						$currentDeviceSpecificTpl = preg_replace('#\{DEVICEADAPTIONMODIFIED\}#si', '-', $currentDeviceSpecificTpl);
						$currentDeviceSpecificTpl = preg_replace('#\{GENERIC_LOCALISATION(.*?)GENERIC_LOCALISATION\}#si', '', $currentDeviceSpecificTpl);
					}
					$currentDeviceSpecificTpl = preg_replace('#\{GENERIC_WARNING(.*?)GENERIC_WARNING\}#si', '', $currentDeviceSpecificTpl);
					
					/*
					=================
					for localisations
					=================
					*/
					$deviceSuffix = "";
					$localisationFileList = "";
					foreach($deviceSpecificFile as $localisationKey => $localisationFile)
					{
						$deviceSuffix = $localisationFile[1];
						if($localisationKey != "generic")
						{
							$currentLocalisationTpl = preg_replace('#\{LOCALISATIONNAME\}#si', '{LANG:' . $localisationKey . '}', $forLocalisations[1][0]);
							$currentLocalisationTpl = preg_replace('#\{LOCALISATIONSIZE\}#si', $this->sizeOfFile(filesize($localisationFile[0])), $currentLocalisationTpl);
							$currentLocalisationTpl = preg_replace('#\{LOCALISATIONMODIFIED\}#si', date("d.m.Y H:i", filemtime($localisationFile[0])), $currentLocalisationTpl);
							$currentLocalisationTpl = preg_replace('#\{ON_GENERIC(.*?)ON_GENERIC\}#si', '&nbsp;', $currentLocalisationTpl);
							$localisationFileList .= $currentLocalisationTpl;
						}
					}
					$currentDeviceSpecificTpl = preg_replace('#\{DEVICE_SUFFIX\}#si', $deviceSuffix, $currentDeviceSpecificTpl);
					$deviceSpecificFilesList .= $currentDeviceSpecificTpl . $localisationFileList;
				}
			}
			$deviceSpecificFilesList = preg_replace('#\{PADDING\}#si', '20', $deviceSpecificFilesList);
			$currentFileTpl = preg_replace('#\{FOR_DEVICE_ADAPTIONS(.*?)FOR_DEVICE_ADAPTIONS\}#si', $deviceSpecificFilesList, $currentFileTpl);
			
			/*
			=================
			for localisations
			=================
			*/
			$localisationFileList = "";
			if(isset($currentFiles["generic"]))
			{
				foreach($currentFiles["generic"] as $localisationKey => $localisationFile)
				{
					if($localisationKey != "generic")
					{
						$currentLocalisationTpl = preg_replace('#\{LOCALISATIONNAME\}#si', '{LANG:' . $localisationKey . '}', $forLocalisations[1][0]);
						$currentLocalisationTpl = preg_replace('#\{UNIQUEID\}#si', uniqid(), $currentLocalisationTpl);
						$currentLocalisationTpl = preg_replace('#\{LOCALISATIONSIZE\}#si', $this->sizeOfFile(filesize($localisationFile[0])), $currentLocalisationTpl);
						$currentLocalisationTpl = preg_replace('#\{LOCALISATIONMODIFIED\}#si', date("d.m.Y H:i", filemtime($localisationFile[0])), $currentLocalisationTpl);
						$currentLocalisationTpl = preg_replace('#\{PADDING\}#si', '0', $currentLocalisationTpl);
						$currentLocalisationTpl = preg_replace('#\{CURRENTPATH\}#si', $_REQUEST['path'] . '/' . $localisationList[$localisationKey] . '.lproj/', $currentLocalisationTpl);
						$currentLocalisationTpl = preg_replace('#\{ON_GENERIC|ON_GENERIC\}#si', '', $currentLocalisationTpl);
						$localisationFileList .= $currentLocalisationTpl;
					}
				}
				$localisationFileList = preg_replace('#\{PADDING\}#si', '0', $localisationFileList);
			}
			$currentFileTpl = preg_replace('#\{FOR_LOCALISATIONS(.*?)FOR_LOCALISATIONS\}#si', $localisationFileList, $currentFileTpl);
			$currentFileTpl = preg_replace('#\{FILENAME\}#si', $metaFileName, $currentFileTpl);
			$forFileList[0] .= $currentFileTpl;
		}
		
		$forFileList[0] = preg_replace('#\{NOFOLDER|NOFOLDER\}#si', '', $forFileList[0]);
		$this->template = preg_replace('#\{FOR_FILELIST(.*?)FOR_FILELIST\}#si', $forFileList[0], $this->template);
		
		/*
		==============
		breadcrum path
		==============
		*/
		preg_match_all('#\{FOR_BREADCRUMPATH(.*?)FOR_BREADCRUMPATH\}#si', $this->template, $forBreadcrumPath);
		$forBreadcrumPath[0] = "";
		$completePath = "";
		$pathComponents = explode('/', $_REQUEST['path']);
		foreach($pathComponents as $currentPathFolder)
		{
			if(trim($currentPathFolder) != "")
			{
				$completePath .= '/' . $currentPathFolder;
				$currentBreadTpl = preg_replace('#\{LINKPATH\}#si', $completePath, $forBreadcrumPath[1][0]);
				$currentBreadTpl = preg_replace('#\{DISPLAYPATH\}#si', $currentPathFolder, $currentBreadTpl);
				$forBreadcrumPath[0] .= $currentBreadTpl;
			}
		}
		$this->template = preg_replace('#\{FOR_BREADCRUMPATH(.*?)FOR_BREADCRUMPATH\}#si', $forBreadcrumPath[0], $this->template);
		$this->template = preg_replace('#\{PARENTPATH\}#si', dirname($_REQUEST['path']), $this->template);
		$this->template = preg_replace('#\{CURRENTPATH\}#si', $_REQUEST['path'], $this->template);
	
		$this->template = preg_replace('#\{CONFIG_UPLOADDIR\}#si', $this->mc->config['upload_dir'], $this->template);
		return $this->template;
	}
	
	/**
	* function - fileTypeImage
	* --
	* return the icon-name for a given imagetype
	* --
	* @param: $file - the filename
	* @return: (String)
	*		icon-name for this file
	* --
	*/
	function fileTypeImage($file)
	{
		$filename = explode('.', $file);
		$max = count($filename);		
		$ext = strtolower($filename[($max-1)]);
		
		if($ext == 'exe') return 'binary';
		else if($ext == 'zip' or $ext == 'rar') return 'compressed';
		else if($ext == 'bmp' or $ext == 'tif' or $ext == 'ico') return 'image1';
		else if($ext == 'gif' or $ext == 'jpg' or $ext == 'jpeg' or $ext == 'png') return 'image2';
		else if($ext == 'avi' or $ext == 'mpg' or $ext == 'mpeg' or $ext == 'divx' or $ext == 'wmv') return 'movie';
		else if($ext == 'pdf') return 'pdf';
		else if($ext == 'php') return 'php';
		else if($ext == 'js' or $ext == 'cmd' or $ext == 'bat' or $ext == 'xml' or $ext == 'asp') return 'script';
		else if($ext == 'gz' or $ext == 'tgz' or $ext == 'bz2' or $ext == 'tbz') return 'tar';
		else if($ext == 'txt' or $ext == 'doc' or $ext == 'odt') return 'text';
		else return 'unknown';
	}
	
	/**
	* function - sizeOfFile
	* --
	* return a well-formatted filesize
	* --
	* @param: $size - size in bytes
	* @return: (String)
	*		size in maximum unit
	* --
	*/
	function sizeOfFile($size)
	{
		$extensions = array('KB', 'MB', 'GB', 'TB');
		$counter = -1;
		$ext = '';
		while($size >= 1024)
		{
			$size = $size / 1024;
			$counter++;
			$ext = $extensions[$counter];
		}
		$size = round($size, 0);
		$size = $size.' '.$ext;
		return $size;
	}
}