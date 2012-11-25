<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Permissions class
*/

if(!isset($configSet) OR !$configSet)
	exit();
	
class apdPermissions
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
		$this->mc = $mainContainer;
	}
	
	/**
	* function - checkPermissionTemplate
	* --
	* checks if the current user does have permission to view an object
	* --
	* @param: $permissionKey
	*		the key of the permission, "local_key" in the table sd_permissions
	* @param: $permissionContent
	*		the content that should be visible or hidden
	* @return: (String)
	*		content that is visible for the user
	* --
	*/
	function checkPermissionTemplate($permissionKey, $permissionContent)
	{
		return ( ($this->checkPermission($permissionKey)) ? $permissionContent : "" );
	}
	
	/**
	* function - checkPermission
	* --
	* checks if the current user does have permission for an action
	* --
	* @param: $permissionKey
	*		the key of the permission, "local_key" in the table sd_permissions
	* @return: (boolean)
	*		does user have permissions
	* --
	*/
	function checkPermission($permissionKey)
	{
		// check if permission exists in database
		$checkPermissionQuery = $this->mc->database->query("SELECT local_key FROM " . $this->mc->config['database_pref'] . "permissions AS A, " . $this->mc->config['database_pref'] . "user_groups_permissions AS B WHERE A.local_key = ? AND A.permission_id = B.permission_id AND B.group_id = ?", array(array($permissionKey), array($this->mc->config['user_rank'], "i")));
		return (count($checkPermissionQuery->rows) > 0);
	}
}
?>