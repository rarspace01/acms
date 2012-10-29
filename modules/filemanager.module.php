<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Processing a submitted file / upload
*/

if(!isset($configSet) OR !$configSet)
	exit();

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
if(!function_exists('initCurrentModule'))
{
	function initCurrentModule($mainContainer)
	{
		// check if a view-id was given
		// will call parent constructor!
		return new apdModuleFilemanager($mainContainer);
	}
}

class apdModuleFilemanager
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
		
		// when uploading files, check these regexs here for file-extesnsion
		// in order to put files into special folders
		$this->regExFileExtensions = array();
		$this->regExFileExtensions[] = array('jpg|jpeg|png', 'pictures');
		$this->regExFileExtensions[] = array('mp4|mpg|mpeg|m4v|avi', 'videos');
		$this->regExFileExtensions[] = array('mp3|m4a|wav|wave|flac', 'audio');
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
		/*
		==================
		ajax file uploader
		==================
		*/
		if(isset($_REQUEST['type']) && substr($_REQUEST['type'],0,6) == 'upload')
		{
			/**
			 * PHP Real Ajax Uploader
			 * Copyright @Alban Xhaferllari
			 * albanx@gmail.com
			 * www.albanx.com
			 */
			 
			$uploadType = substr($_REQUEST['type'], 6);
			$this->uploadPath	= $this->mc->config['upload_dir'];
			if($uploadType == 'zoomimage')
			{
				$this->uploadPath .= 'modules/zoomimage/';
			}
			else
			{
				$this->uploadPath .= 'root/';
			}
			$fileName	= $_REQUEST['ax-file-name'];
			$currByte	= $_REQUEST['ax-start-byte'];
			$this->maxFileSize= $_REQUEST['ax-maxFileSize'];
			$html5fsize	= $_REQUEST['ax-fileSize'];
			$isLast		= $_REQUEST['isLast'];

			//if set generates thumbs only on images type files
			$thumbHeight	= $_REQUEST['ax-thumbHeight'];
			$thumbWidth		= $_REQUEST['ax-thumbWidth'];
			$thumbPostfix	= $_REQUEST['ax-thumbPostfix'];
			$thumbPath		= $_REQUEST['ax-thumbPath'];
			$thumbFormat	= $_REQUEST['ax-thumbFormat'];

			$this->allowExt	= (empty($_REQUEST['ax-allow-ext']))?array():explode('|', $_REQUEST['ax-allow-ext']);
			$this->uploadPath	.= (!in_array(substr($this->uploadPath, -1), array('\\','/') ) )?'/':'';//normalize path

			if(!file_exists($this->uploadPath) && !empty($this->uploadPath))
			{
				mkdir($this->uploadPath, 0777, true);
			}

			if(!file_exists($thumbPath) && !empty($thumbPath))
			{
				mkdir($thumbPath, 0777, true);
			}

			$this->mc->template->template = '';
			ob_clean();
			
			if(isset($_FILES['ax-files'])) 
			{
				//for eahc theorically runs only 1 time, since i upload i file per time
				foreach ($_FILES['ax-files']['error'] as $key => $error)
				{
					if ($error == UPLOAD_ERR_OK)
					{
						$newName = !empty($fileName)? $fileName:$_FILES['ax-files']['name'][$key];
						$fullPath = $this->checkFilename($newName, $_FILES['ax-files']['size'][$key]);
						
						if($fullPath)
						{
							move_uploaded_file($_FILES['ax-files']['tmp_name'][$key], $fullPath);
							//if(!empty($thumbWidth) || !empty($thumbHeight))
							//	$this->createThumbGD($fullPath, $thumbPath, $thumbPostfix, $thumbWidth, $thumbHeight, $thumbFormat);
								
							echo json_encode(array('name'=>basename($fullPath), 'size'=>filesize($fullPath), 'status'=>'uploaded', 'info'=>'File uploaded'));
						}
					}
					else
					{
						echo json_encode(array('name'=>basename($_FILES['ax-files']['name'][$key]), 'size'=>$_FILES['ax-files']['size'][$key], 'status'=>'error', 'info'=>$error));	
					}
				}
			}
			elseif(isset($_REQUEST['ax-file-name'])) 
			{
				//check only the first piece
				$fullPath = ($currByte != 0) ? $this->checkFilename($fileName, $html5fsize, 'ignore') : $this->checkFilename($fileName, $html5fsize);
				
				if($fullPath)
				{
					$flag			= ($currByte == 0) ? 0 : FILE_APPEND;
					$receivedBytes	= file_get_contents('php://input');
					//strange bug on very fast connections like localhost, some times cant write on file
					//TODO future version save parts on different files and then make join of parts
					while(@file_put_contents($fullPath, $receivedBytes, $flag) === false)
					{
						usleep(50);
					}
					
					/*if($isLast=='true')
					{
						$this->createThumbGD($fullPath, $thumbPath, $thumbPostfix, $thumbWidth, $thumbHeight, $thumbFormat);
					}*/
					echo json_encode(array('name'=>basename($fullPath), 'size'=>$currByte, 'status'=>'uploaded', 'info'=>'File/chunk uploaded'));
				}
			}
			
			$this->mc->template->template = ob_get_contents();
			ob_clean();
		}
		/*
		===========================
		"cleanup" after file upload
		===========================
		*/
		else if($_REQUEST['type'] == 'finishedupload')
		{
			// refresh file list
			$this->refreshFilelist();
			header("Location: index.php?m=filemanager");
		}
		/*
		==================
		get specific file
		==================
		*/
		else if($_REQUEST['type'] == 'getfile')
		{
			if(isset($_REQUEST['mfid']))
			{
				// empty template file
				$this->mc->template->template = '';
				if(isset($_REQUEST['devicetype']) && intval($_REQUEST['devicetype']) >= 0)
					$this->deliverFile($_REQUEST['mfid'], intval($_REQUEST['devicetype']));
				else
					$this->deliverFile($_REQUEST['mfid']);
			}
		}
		else if($_REQUEST['type'] == 'filelist')
		{
			// replace complete template with filelist
			if(isset($_REQUEST['devicetype']) && intval($_REQUEST['devicetype']) >= 0)
				$this->mc->template->template = $this->showFilelist($_REQUEST['devicetype']);
			else
				$this->mc->template->template = $this->showFilelist();
			// set output header
			header ("Content-Type:text/xml");
		}
		else if($_REQUEST['type'] == 'refreshfilelist')
		{
			$this->refreshFilelist();
		}
	}
	
	/**
	* function - deliverFile
	* --
	* returns a bytestream that can be downloaded by the user
	* for the given metafile-id.
	* --
	* @param: $mfid
	* @param: $deviceType - will be 0 for general, 1 for ipad, 2 for iphone
	* @return: none
	* --
	*/
	function deliverFile($mfid, $deviceType = 0)
	{	
		$fileQuery = $this->mc->database->query("SELECT path, filename, mfilename FROM " . $this->mc->config['database_pref'] . "fm_files NATURAL JOIN " . $this->mc->config['database_pref'] . "fm_metafiles WHERE (devicetype IN(?,0)) AND MFID = ? LIMIT 0,1", array(array($deviceType, "i"), array($mfid)));
		
		if(count($fileQuery->rows) > 0)
		{
			$fileName = $this->mc->config['upload_dir'] . '/root/' . $fileQuery->rows[0]->path . $fileQuery->rows[0]->filename;
			
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename='.basename($fileQuery->rows[0]->mfilename));
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . filesize($fileName));
			set_time_limit(0);
			
			readfile($fileName);	
		}
		else
		{
			return false;
		}
	}
	
	/**
	* function - showFilelist
	* --
	* returns the filelist as XML file
	* --
	* @param: $deviceType - will be 0 for general, 1 for ipad, 2 for iphone
	* @return: (String)
	*		the filelist as xml-represented string
	* --
	*/
	function showFilelist($deviceType = 0)
	{
		$returnFilelist = "<filelist>\n";
		$lastdir = "";
		
		$fileQuery = $this->mc->database->query("SELECT MFID, path, mfilename, size, hash FROM " . $this->mc->config['database_pref'] . "fm_files NATURAL JOIN " . $this->mc->config['database_pref'] . "fm_metafiles WHERE (devicetype IN(?,0)) ORDER BY path", array(array($deviceType, "i")));

		/*
		will return a folder-hierarchy like the following:
		
		<folder name="./root">
			<file id="FILEID" name="REALFILENAME" hash="FILECONTENTHASH" size="FILESIZE" />
		</folder>
		*/		
		foreach($fileQuery->rows as $currentFile)
		{	
			//	check if directory changed since last file
			if($currentFile->path != $lastdir)
			{
				//close old folder if it isnt the first folder
				if($lastdir != "")
				{
					$returnFilelist .= "\t</folder>\n";
				}	
				//start new folder
				$returnFilelist .= "\t<folder name=\"" . preg_replace('#' . $this->mc->config['upload_dir'] . '(/?)#si', '', $currentFile->path) . "\">\n";
				$lastdir = $currentFile->path;
			}
			// file entry
			$returnFilelist .= "\t\t<file id=\"" . $currentFile->MFID ."\" name=\"" . $currentFile->mfilename ."\" size=\"" . $currentFile->size ."\" hash=\"" . $currentFile->hash ."\" />\n";
		}
		$returnFilelist .= "\t</folder>\n</filelist>";
		
		return $returnFilelist;
	}

	function refreshFilelist()
	{
		$starttime = time();
		$this->directoryToArray($this->mc->config['upload_dir']);
		$this->removeOldFiles($starttime);
	}

	/**
     * Get an array that represents directory tree
     * @param string $directory		Directory path
     * @param bool $recursive		Include sub directories
     * @param bool $listDirs		Include directories on listing
     * @param bool $listFiles		Include files on listing
     * @param regex $exclude		Exclude paths that matches this regex
     */
    function directoryToArray($directory, $recursive = true, $listDirs = false, $listFiles = true, $exclude = '')
	{
	    $arrayItems = array();
        $skipByExclude = false;
        $handle = opendir($directory);
        if ($handle)
		{
            while (false !== ($file = readdir($handle)))
			{
				preg_match("/(^(([\.]){1,2})$|(\.(svn|git|md))|(Thumbs\.db|\.DS_STORE))$/iu", $file, $skip);
				if($exclude)
				{
					preg_match($exclude, $file, $skipByExclude);
				}
				if (!$skip && !$skipByExclude)
				{
					if (is_dir($directory. '/' . $file))
					{
						//is dir
						if($recursive)
						{
							$arrayItems = array_merge($arrayItems, $this->directoryToArray($directory. '/' . $file, $recursive, $listDirs, $listFiles, $exclude));
						}
						if($listDirs)
						{
							$file = $directory . '/' . $file;
							$arrayItems[] = $file;
							//echo "Dir: ".$file."<br/>";
						}
					}
					else
					{
						//is file
						//get hashsum
						if($listFiles)
						{
							$filef = $directory . '/' . $file;
                        
							// create / update specific file
							$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "fm_files (GFID,MFID,path,filename,size,hash,lastscan,devicetype) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE size = ?, hash = ?, lastscan= ?, MFID = ?, devicetype = ?", array(array(hash('sha1',$filef)), array($this->getMetaFileHashFromMetaFile($filef)), array($directory.'/'), array($file),  array(filesize($filef), "i"), array(hash_file('sha1',$filef)), array(time(), "i"), array($this->detectFileType($file)), array(filesize($filef), "i"), array(hash_file('sha1',$filef)), array(time(), "i"), array($this->getMetaFileHashFromMetaFile($filef)), array($this->detectFileType($file))));

							// create / update metafile
							$this->mc->database->query("INSERT INTO " . $this->mc->config['database_pref'] . "fm_metafiles (MFID,mfilename,mlastscan) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE mfilename = ?, mlastscan = ?", array(array($this->getMetaFileHashFromMetaFile($filef)), array($this->getMetaFileName($file)), array(time(), "i"), array($this->getMetaFileName($file)), array(time(), "i")));
							
							//DEBUG
							/*
							echo "Original Filename: [".$filef."]<br/>";
							echo "Filetype: [".detectFileType($filef)."]<br/>";
							echo "Metafilename: [".getMetaFileName($filef)."]<br/>";
							echo "Meta Filehash: [".getMetaFileHashFromMetaFile($filef)."]<br/><br/>";
							*/
							//ENDDEBUG
							
							$arrayItems[] = $filef;
						}
					}
				}
			}	
			closedir($handle);
		}
        return $arrayItems;
    }

	function removeOldFiles($starttime)
	{
		$pretime=15;
		$worktime = $starttime - $pretime;
		
		$this->mc->database->query("DELETE FROM " . $this->mc->config['database_pref'] . "fm_files WHERE lastscan < ?", array(array($worktime, "i")));
		
		$this->mc->database->query("DELETE FROM " . $this->mc->config['database_pref'] . "fm_metafiles WHERE mlastscan < ?", array(array($worktime, "i")));	
	}

	function detectFileType($filename)
	{
		// 0 = all
		// 1 = iPad
		// 2 = iPhone/iPod

		if(preg_match('/^(.+?)_ia\.(.+?)$/si', $filename))
		{
			return 1; // ipad file
		}
		elseif(preg_match('/^(.+?)_io\.(.+?)$/si', $filename))
		{
			return 2; // iphone file
		}
		return 0; // generic file
	}

	function getMetaFileName($filename)
	{
		if(preg_match('/^(.+?)_i[ao]\.(.+?)$/si',$filename))
		{
			$filename = preg_replace('/^(.+?)_i[ao]\.(.+?)$/si','$1.$2',$filename);
		}
		return $filename;
	}

	function getMetaFileHashFromMetaFile($metafilename)
	{
		return hash('sha1', $this->getMetaFileName($metafilename));
	}
	
	/**
	* function - createThumbGD
	* --
	* PHP Real Ajax Uploader function
	* --
	* @param: none
	* @return: none
	* --
	*/	
	function createThumbGD($filepath, $thumbPath, $postfix, $maxwidth, $maxheight, $format='jpg', $quality=75)
	{	
		if($maxwidth<=0 && $maxheight<=0)
		{
			return 'No valid width and height given';
		}
		
		$gd_formats	= array('jpg','jpeg','png','gif');//web formats
		$file_name	= pathinfo($filepath);
		if(empty($format)) $format = $file_name['extension'];
		
		if(!in_array(strtolower($file_name['extension']), $gd_formats))
		{
			return false;
		}
		
		$thumb_name	= $file_name['filename'].$postfix.'.'.$format;
		
		if(empty($thumbPath))
		{
			$thumbPath=$file_name['dirname'];	
		}
		$thumbPath.= (!in_array(substr($thumbPath, -1), array('\\','/') ) )?'/':'';//normalize path
		
		// Get new dimensions
		list($width_orig, $height_orig) = getimagesize($filepath);
		if($width_orig>0 && $height_orig>0)
		{
			$ratioX	= $maxwidth/$width_orig;
			$ratioY	= $maxheight/$height_orig;
			$ratio 	= min($ratioX, $ratioY);
			$ratio	= ($ratio==0)?max($ratioX, $ratioY):$ratio;
			$newW	= $width_orig*$ratio;
			$newH	= $height_orig*$ratio;
				
			// Resample
			$thumb = imagecreatetruecolor($newW, $newH);
			$image = imagecreatefromstring(file_get_contents($filepath));
				
			imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newW, $newH, $width_orig, $height_orig);
			
			// Output
			switch (strtolower($format)) {
				case 'png':
					imagepng($thumb, $thumbPath.$thumb_name, 9);
				break;
				
				case 'gif':
					imagegif($thumb, $thumbPath.$thumb_name);
				break;
				
				default:
					imagejpeg($thumb, $thumbPath.$thumb_name, $quality);;
				break;
			}
			imagedestroy($image);
			imagedestroy($thumb);
		}
		else 
		{
			return false;
		}
	}

	/**
	* function - createThumbIM
	* --
	* PHP Real Ajax Uploader function
	* --
	* @param: none
	* @return: none
	* --
	*/	
	function createThumbIM($filepath, $thumbPath, $postfix, $maxwidth, $maxheight, $format)
	{
		$file_name	= pathinfo($filepath);
		$thumb_name	= $file_name['filename'].$postfix.'.'.$format;
		
		if(empty($thumbPath))
		{
			$thumbPath=$file_name['dirname'];	
		}
		$thumbPath.= (!in_array(substr($thumbPath, -1), array('\\','/') ) )?'/':'';//normalize path
		
		$image = new Imagick($filepath);
		$image->thumbnailImage($maxwidth, $maxheight);
		$images->writeImages($thumbPath.$thumb_name);
	}


	/**
	* function - checkFilename
	* --
	* PHP Real Ajax Uploader function
	* --
	* @param: none
	* @return: none
	* --
	*/	
	function checkFilename($fileName, $size, $newName = '')
	{		
		//------------------max file size check from js
		$maxsize_regex = preg_match("/^(?'size'[\\d]+)(?'rang'[a-z]{0,1})$/i", $this->maxFileSize, $match);
		$maxSize=4*1024*1024;//default 4 M
		if($maxsize_regex && is_numeric($match['size']))
		{
			switch (strtoupper($match['rang']))//1024 or 1000??
			{
				case 'K': $maxSize = $match[1]*1024; break;
				case 'M': $maxSize = $match[1]*1024*1024; break;
				case 'G': $maxSize = $match[1]*1024*1024*1024; break;
				case 'T': $maxSize = $match[1]*1024*1024*1024*1024; break;
				default: $maxSize = $match[1];//default 4 M
			}
		}

		if(!empty($this->maxFileSize) && $size>$maxSize)
		{
			echo json_encode(array('name'=>$fileName, 'size'=>$size, 'status'=>'error', 'info'=>'File size not allowed.'));
			return false;
		}
		//-----------------End max file size check
		
		
		//comment if not using windows web server
		$windowsReserved	= array('CON', 'PRN', 'AUX', 'NUL','COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
								'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9');    
		$badWinChars		= array_merge(array_map('chr', range(0,31)), array("<", ">", ":", '"', "/", "\\", "|", "?", "*"));

		$fileName	= str_replace($badWinChars, '', $fileName);
		$fileInfo	= pathinfo($fileName);
		$fileExt	= $fileInfo['extension'];
		$fileBase	= $fileInfo['filename'];
		
		//check if legal windows file name
		if(in_array($fileName, $windowsReserved))
		{
			echo json_encode(array('name'=>$fileName, 'size'=>0, 'status'=>'error', 'info'=>'File name not allowed. Windows reserverd.'));	
			return false;
		}
		
		//check if is allowed extension
		if(!in_array($fileExt, $this->allowExt) && count($this->allowExt))
		{
			echo json_encode(array('name'=>$fileName, 'size'=>0, 'status'=>'error', 'info'=>"Extension [$fileExt] not allowed."));	
			return false;
		}
		
		$uploadPath = $this->uploadPath;
		// check if file should be put into a special folder
		foreach($this->regExFileExtensions as $currentRegEx)
		{
			if(preg_match('#(' . $currentRegEx[0] . ')$#si', $fileExt))
			{
				$uploadPath .= '/' . $currentRegEx[1] . '/';
			}
		}
		$fullPath = $uploadPath.$fileName;
		if($newName == '')
		{
			$c = 0;
			while(file_exists($fullPath))
			{
				$c++;
				$fileName	= $fileBase."($c).".$fileExt;
				$fullPath 	= $uploadPath.$fileName;
			}
		}
		return $fullPath;
	}
}
?>