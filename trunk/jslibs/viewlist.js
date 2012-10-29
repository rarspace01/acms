/*
 * createViewList - creates a select-box with all current views
 *
 * name - the name for this view, will be set as id, too
 * viewId - the default selected view
 * defaultText - text for displaying as "null" option
 * customActionText - text for displaying as "custom action"
 * customAction - if there is one (viewId == 0!), it goes here
 * container - the container to append this selectbox to
 */
function createViewList(name, viewId, defaultText, customActionText, customAction, container)
{
	var selectBox = document.createElement('select');
	selectBox.setAttribute('name', name);
	selectBox.setAttribute('id', name);
	
	selectBox.onchange = function() { onchangeSelectBox(name, -1); };
	
	var optionDefaultField = document.createElement('option');
	optionDefaultField.setAttribute('value', -1);
	optionDefaultField.appendChild(document.createTextNode(defaultText));
	selectBox.appendChild(optionDefaultField);
	
	var optionCustomActionField = document.createElement('option');
	optionCustomActionField.setAttribute('value', 0);
	optionCustomActionField.appendChild(document.createTextNode(customActionText));
	selectBox.appendChild(optionCustomActionField);
	
	for(var i = 0; i < viewArray.length; i++)
	{
		var optionField = document.createElement('option');
		optionField.setAttribute('value', viewArray[i]["id"]);
		if(viewId >= 0 && viewArray[i]["id"] == viewId)
		optionField.setAttribute('selected', 'selected')
		optionField.appendChild(document.createTextNode('> ' + viewArray[i]["name"]));
		
		selectBox.appendChild(optionField);
	}
	
	container.appendChild(selectBox);
	
	if(viewId == 0 && customAction != -1)
	{
		selectBox.selectedIndex = 1;
		onchangeSelectBox(selectBox, customAction);
	}
	
	return selectBox;
}

function onchangeSelectBox(selectBoxId, customAction)
{
	var selectBoxElement = ((selectBoxId != null && selectBoxId.type == 'select-one') ? selectBoxId : document.getElementById(selectBoxId));
	selectBoxId = selectBoxElement.getAttribute('id');
	var inputField = document.getElementById(selectBoxId + '_custom');
	if(selectBoxElement.selectedIndex == 1)
	{
		if(inputField == null)
		{
			inputField = document.createElement('input');
			inputField.setAttribute('class', 'content_form_input_field');
			inputField.setAttribute('name', selectBoxId + '_custom');
			inputField.setAttribute('id', selectBoxId + '_custom');
			if(customAction != -1)
				inputField.setAttribute('value', customAction);
			selectBoxElement.parentElement.appendChild(inputField);
		}
		else
		{
			inputField.style.display = 'block';
		}
	}
	else
	{
		if(inputField != null)
		{
			inputField.style.display = 'none';
		}
	}
}