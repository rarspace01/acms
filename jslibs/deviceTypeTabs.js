
var currentViewType = '';

function changeTab(type)
{
	if(currentViewType != type)
	{					
		var oldType = currentViewType;
		currentViewType = type;

		if(oldType != '')
		{
			document.getElementById('device_type_tab_' + oldType).className = 
				document.getElementById('device_type_tab_' + oldType).className.replace(/ content_tab_active/g, '');
				
			document.getElementById('content_edit_area_' + oldType).style.display = 'none';
		}
		
		document.getElementById('device_type_tab_' + type).className += ' content_tab_active';
		document.getElementById('content_edit_area_' + type).style.display = 'block';
	}
	
	if(typeof changeTabCustom == 'function')
	{
		changeTabCustom(type);
	}
}