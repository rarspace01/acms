/*
 * creates and manages actions for an image.
 * initialise by calling showImageActionSelection with an array
 * of filenames, only the first element will be considered.
 *
 * requirements:
 * - the original-image has to be located at imageUploadDir + fileNames[0]
 * - the area for the image is called image_edit_area
 * - the form has to have the ID imageselection_form
 * - there has to be an input-field picture_name for the imagename
 * - there have to be input-fields for the image-dimensions picture_org_dim_x and picture_org_dim_y
 * - there has to be an input field maxbuttonid for the number of the (last) created action(s)
 * - there has to be an function imageLoaded() which is called when the image has been loaded
 */

var templatePath = 'templates/default/';
var actionContainerClass = 'imageaction';
var previewImageClass = 'previewimage';

var textActionContainer = '';
var textActionHelpContainer = '';
var textViewSelectionDefault = '';
var textViewSelectionCustom = '';

var imageUploadDir = 'root/pictures/';
var imageOriginalHeight = 0;
var imageOriginalWidth = 0;
var imagePreview;

var currentDiv = null;
var currentDivOriginalTop = 0;
var currentDivOriginalLeft = 0;
var lastMousePosX = 0;
var lastMousePoxY = 0;
var currentButtonId = 0;

var selectionStarted = false;

function showImageActionSelection(fileNames)
{
	emptyQueueAction();

	var currentFileName = document.getElementById('picture_name').value;
	if(currentFileName != "")
	{
		var keepCurrentImage = false;
		var fileExtension = currentFileName.split(".");
		fileExtension = fileExtension[fileExtension.length-1];
		currentFileName = currentFileName.substr(0, currentFileName.length - fileExtension.length - 1);
		// check if only a "double resolution" image
		var isDoubleResRegExp = new RegExp(currentFileName + '-2x', 'g');
		if(fileNames[0].match(isDoubleResRegExp))
		{
			// if it is only a double resolution image, do not change the original file
			fileNames[0] = currentFileName + '.' + fileExtension;
			keepCurrentImage = true;
		}
		for(var i = document.getElementById('maxbuttonid').value; i >= 1 ; i--)
		{
			if(document.getElementById('action_container_' + i) != null)
			{
				if(keepCurrentImage)
					document.getElementById('action_container_' + i).style.display = 'block';
				else
				{
					removeAction(i);
				}
			}
		}
		if(!keepCurrentImage)
		{
			currentButtonid = 0;
			document.getElementById('maxbuttonid').value = 0;
		}
	}

	if(document.getElementById('filemanagercontainer') != null)
		document.getElementById('filemanagercontainer').style.display = 'none';
	document.getElementById('imageselection_form').style.display = 'block';
	document.getElementById('picture_name').value = fileNames[0];
	
	var imageArea = document.getElementById('image_edit_area');
	while(imageArea.firstChild != null)
	{
		imageArea.removeChild(imageArea.firstChild);
	}
	
	imageArea.appendChild(document.createTextNode(fileNames[0] + ':'));
	var pElement = document.createElement('p');
	imageArea.appendChild(pElement);
	
	imagePreview = new Image();
	imagePreview.src = imageUploadDir + fileNames[0];
	imagePreview.onload = function()
		{
			document.getElementById('picture_org_dim_x').value = imagePreview.width;
			document.getElementById('picture_org_dim_y').value = imagePreview.height;
			imageLoaded();
		};
	imagePreview.setAttribute('class', previewImageClass);
	imagePreview.setAttribute('unselectable', 'on');
	imagePreview.onselectstart = function() { return false; };
	imagePreview.ondragstart = function() { return false; };
	
	// selection process
	imagePreview.onmousedown = startActionSelection;
	imagePreview.onmousemove = moveActionSelection;
	imagePreview.onmouseup = endActionSelection;
	
	imageArea.appendChild(imagePreview);
}

function startActionSelection(mouseEvent) 
{
	if (!mouseEvent)
		mouseEvent = window.event;
	
	selectionStarted = true;
	
	currentButtonId++;
	var absoluteTop = currentDivOriginalTop = lastMousePosX = mouseEvent.pageY;
	var absoluteLeft = currentDivOriginalLeft = lastMousePoxY = mouseEvent.pageX;
	document.getElementById('maxbuttonid').value = currentButtonId;
	
	currentDiv = document.createElement('div');
	currentDiv.setAttribute('id', 'action_container_' + currentButtonId);
	currentDiv.setAttribute('class', actionContainerClass);
	currentDiv.style.top = absoluteTop + 'px';
	currentDiv.style.left = absoluteLeft + 'px';
	
	currentDiv.onmousemove = moveActionSelection;	
	currentDiv.onmouseup = endActionSelection;
	
	document.getElementsByTagName('body')[0].appendChild(currentDiv);
}

function moveActionSelection(mouseEvent) 
{
	if(currentDiv != null && selectionStarted)
	{
		if (!mouseEvent) mouseEvent = window.event;

		if(Math.abs(lastMousePosX - mouseEvent.pageX) < 3 && Math.abs(lastMousePosY - mouseEvent.pageY < 3))
			return;

		var absoluteTop = lastMousePosY = mouseEvent.pageY; var absoluteLeft = lastMousePosX = mouseEvent.pageX;
		var topPos = currentDivOriginalTop; var leftPos = currentDivOriginalLeft;
		
		currentDiv.style.height = ( (absoluteTop - topPos) > 0 ? (absoluteTop - topPos) : (topPos - absoluteTop) ) + 'px';
		currentDiv.style.top = Math.min(topPos, absoluteTop) + 'px';
		
		currentDiv.style.width = ( (absoluteLeft - leftPos) > 0 ? (absoluteLeft - leftPos) : (leftPos - absoluteLeft) ) + 'px';
		currentDiv.style.left = Math.min(absoluteLeft, leftPos) + 'px';
	}
}
function endActionSelection(mouseEvent)
{
	if(currentDiv != null && selectionStarted)
	{
		if (!mouseEvent) mouseEvent = window.event;
		
		if(Math.abs(currentDivOriginalLeft - mouseEvent.pageX) < 3 && Math.abs(currentDivOriginalTop - mouseEvent.pageY < 3))
		{
			removeAction(currentButtonId);
			currentButtonId--;
			currentDiv = null;
			return;
		}
		else
		{
			createAction(currentDiv, false, -1);	
			currentDiv = null;
		}
		
		selectionStarted = false;
	}
}

function createAction(divContainer, dimension, command)
{
	if(divContainer == null)
	{
		currentButtonId++;
		document.getElementById('maxbuttonid').value = currentButtonId;
		divContainer = document.createElement('div');
		divContainer.setAttribute('id', 'action_container_' + currentButtonId);
		divContainer.setAttribute('class', actionContainerClass);
		divContainer.style.top = dimension[0] + getAbsoluteTopPos(imagePreview) + 'px';
		divContainer.style.left = dimension[1] + getAbsoluteLeftPos(imagePreview) + 'px';
		divContainer.style.height = dimension[2] + 'px';
		divContainer.style.width = dimension[3] + 'px';
		
		document.getElementsByTagName('body')[0].appendChild(divContainer);
	}
	
	divContainer.style.width = Math.max(16, parseFloat(divContainer.style.width)) + 'px';
	divContainer.style.height = Math.max(16, parseFloat(divContainer.style.height)) + 'px';
	
	var divButtonId = document.createElement('div');
	divButtonId.setAttribute('class', actionContainerClass + '_id');
	divButtonId.style.left = Math.floor(parseFloat(divContainer.style.width) / 2) - (8 * numberOfDigits(currentButtonId)) + 'px';
	divButtonId.style.top = Math.floor(parseFloat(divContainer.style.height) / 2) - 17 + 'px';
	divButtonId.appendChild(document.createTextNode(currentButtonId));
	divContainer.appendChild(divButtonId);
	
	var removeButton = document.createElement('a');
	removeButton.href = 'javascript:void(0);';
	removeButton.onclick = (function(actionId) { return function() { removeAction(actionId); }; })(currentButtonId);
	var removeButtonImg = new Image();
	removeButtonImg.src = templatePath + '/grafix/disabled.png';
	removeButton.appendChild(removeButtonImg);
	removeButton.setAttribute('class', actionContainerClass + '_button');
	removeButton.style.left = Math.floor(parseFloat(divContainer.style.width) / 2) - 8 + 'px';
	removeButton.style.top = Math.floor(parseFloat(divContainer.style.height) / 2) - 8 + 'px';
	divContainer.appendChild(removeButton);
		
	var imageArea = document.getElementById('image_edit_area');
	
	var divButtonActionContainer = document.createElement('div');
	divButtonActionContainer.setAttribute('id', 'button_action_container_' + currentButtonId);
	divButtonActionContainer.onmouseover = (function(actionId) { return function() { highlightAction(actionId, true); }; })(currentButtonId);
	divButtonActionContainer.onmouseout = (function(actionId) { return function() { highlightAction(actionId, false); }; })(currentButtonId);
	
	var divButtonActionText = document.createElement('div');
	divButtonActionText.setAttribute('class', 'content_form_text');
	divButtonActionText.appendChild(document.createTextNode('(' + currentButtonId + ') ' + textActionContainer));
	
	var divButtonActionInput = document.createElement('div');
	divButtonActionInput.setAttribute('class', 'content_form_input');
	createViewList('loadaction_view_' + currentButtonId, ((parseInt(command) > 0) ? command : 0), textViewSelectionDefault, textViewSelectionCustom, command, divButtonActionInput);
	
	var divButtonActionHelp = document.createElement('div');
	divButtonActionHelp.setAttribute('class', 'content_form_details');
	divButtonActionHelp.appendChild(document.createTextNode(textActionHelpContainer));
	
	var hiddenInputButtonTop = document.createElement('input');
	hiddenInputButtonTop.setAttribute('type', 'hidden');
	hiddenInputButtonTop.setAttribute('name', 'button_top_' + currentButtonId);
	hiddenInputButtonTop.value = getAbsoluteTopPos(divContainer) - getAbsoluteTopPos(imagePreview);
	var hiddenInputButtonLeft = document.createElement('input');
	hiddenInputButtonLeft.setAttribute('type', 'hidden');
	hiddenInputButtonLeft.setAttribute('name', 'button_left_' + currentButtonId);
	hiddenInputButtonLeft.value = getAbsoluteLeftPos(divContainer) - getAbsoluteLeftPos(imagePreview);
	var hiddenInputButtonHeight = document.createElement('input');
	hiddenInputButtonHeight.setAttribute('type', 'hidden');
	hiddenInputButtonHeight.setAttribute('name', 'button_height_' + currentButtonId);
	hiddenInputButtonHeight.value = parseFloat(divContainer.style.height);
	var hiddenInputButtonWidth = document.createElement('input');
	hiddenInputButtonWidth.setAttribute('type', 'hidden');
	hiddenInputButtonWidth.setAttribute('name', 'button_width_' + currentButtonId);
	hiddenInputButtonWidth.value = parseFloat(divContainer.style.width);
	
	divButtonActionContainer.appendChild(hiddenInputButtonTop);
	divButtonActionContainer.appendChild(hiddenInputButtonLeft);
	divButtonActionContainer.appendChild(hiddenInputButtonHeight);
	divButtonActionContainer.appendChild(hiddenInputButtonWidth);
	
	divButtonActionContainer.appendChild(divButtonActionText);
	divButtonActionContainer.appendChild(divButtonActionInput);
	divButtonActionContainer.appendChild(divButtonActionHelp);
	
	imageArea.appendChild(divButtonActionContainer);
}

function removeAction(actionId)
{
	document.getElementsByTagName('body')[0].removeChild(document.getElementById('action_container_' + actionId));
	if(document.getElementById('button_action_container_' + actionId) != null)
		document.getElementById('image_edit_area').removeChild(document.getElementById('button_action_container_' + actionId));
}

function numberOfDigits(id)
{
	var digits = Math.ceil((Math.log(id) / Math.LN10));
	if(id == 1)
		digits++;
	if(id % 10 == 0)
		digits++;
	return digits;
}

function getAbsoluteTopPos(element)
{
	var topPos = 0;
	var currentElement = element;
	while(currentElement.offsetParent != null)
	{
		topPos += currentElement.offsetTop;
		currentElement = currentElement.offsetParent;
	}
	return topPos;
}

function getAbsoluteLeftPos(element)
{
	var leftPos = 0;
	var currentElement = element;
	while(currentElement.offsetParent != null)
	{
		leftPos += currentElement.offsetLeft;
		currentElement = currentElement.offsetParent;
	}
	return leftPos;
}

function highlightAction(actionId, highlight)
{
	var container = document.getElementById('action_container_' + actionId);
	if(highlight)
	{
		container.style.border = '4px solid red';
		container.style.top = parseFloat(container.style.top) - 3 + 'px';
		container.style.left = parseFloat(container.style.left) - 3 + 'px';
	}
	else
	{
		container.style.border = '1px solid black';
		container.style.top = parseFloat(container.style.top) + 3 + 'px';
		container.style.left = parseFloat(container.style.left) + 3 + 'px';
	}
}