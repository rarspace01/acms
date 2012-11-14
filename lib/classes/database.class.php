<?php
/*
====================================
AppPH Design (c) 2012 SHIN Solutions
====================================

Database class
--
wrapper for connection to database including

*/

if(!isset($configSet) OR !$configSet)
	exit();
	
class apdDatabase
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
	
		$this->connection = new mysqli(
			// prepending p: to the address makes it a persistent/resusable connection
			$this->mc->config['database_addr'],
			$this->mc->config['database_user'],
			$this->mc->config['database_pass'],
			$this->mc->config['database_name']);
			
		if(mysqli_connect_errno()) {
			$this->sqlerror = 'Connection failed: ' . mysqli_connect_error();
			$this->mc->logger->log("SQL-Error: " . $this->sqlerror);
			$this->sqlready = false;
			return;
		}
		
		$this->connection->autocommit(TRUE);
		
		$this->sqlready = true;
	}
	
	function query($sqlQuery, $parameters=array(), $revisionQuery=false)
	{
		$result = new stdClass();
		
		if($this->sqlready) {
		
			// first check if revision-modifier needs to be inserted
			if($revisionQuery != false && is_array($revisionQuery) && count($revisionQuery) > 0)
			{
				$wherePosition = strpos($sqlQuery, 'WHERE ');
				$addingWhere = "";
				$letters = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P");

				// in an update query we can't append the check for revisions inside the query
				// because MySQL doesn't support "nested queries" within UPDATE
				// so extract all important information here
				if(strpos($sqlQuery, 'UPDATE ') === 0) {
					if($wherePosition != false && $wherePosition > 1) {
						// get part of the WHERE clause
						$whereClause = substr($sqlQuery, $wherePosition+6);
						
						$tempArray = array();
						// count how many parameters ? are before the first WHERE
						$numberOfParams = preg_match_all('#\?#si', substr($sqlQuery, 0, $wherePosition+6), $tempArray);						
						$currentMaxRevisionQuery = $this->query("SELECT MAX(revision) AS current_revision FROM " . $this->mc->config['database_pref'] . $revisionQuery[0][0] . " WHERE " . $whereClause, array_slice($parameters, $numberOfParams));
						if(count($currentMaxRevisionQuery->rows) > 0)
						{
							$addingWhere .= "revision = '" . $currentMaxRevisionQuery->rows[0]->current_revision . "' AND ";
						}
					}
				}
				// otherwise we have a SELECT query, here we can handle it normally
				else if(strpos($sqlQuery, 'SELECT ') === 0 || strpos($sqlQuery, 'INSERT INTO ') === 0) {
				
					/*
					SELECT tabbar_id, tabbar_name, tabbar_active FROM sd_tabbars AS A
						WHERE revision = (	SELECT MAX( revision ) FROM sd_tabbars WHERE tabbar_id = A.tabbar_id AND revision <= ( SELECT revision_active FROM sd_revisions ) )
						ORDER BY tabbar_name ASC 
					*/
				
					// first insert all tables
					for($i = 0; $i < count($revisionQuery); $i++)
					{					
						$addingWhere .= $letters[$i] . ".revision = ( SELECT MAX(revision) AS current_revision FROM " . $this->mc->config['database_pref'] . $revisionQuery[$i][0] . " WHERE ";
						for($j = 1; $j < count($revisionQuery[$i]); $j++)
						{
							$addingWhere .= $letters[$i] . "." . $revisionQuery[$i][$j] . " = " . $revisionQuery[$i][$j] . " AND ";
						}
						$addingWhere .= " revision <= ( SELECT revision_active FROM " . $this->mc->config['database_pref'] . "revisions ) ) AND ";
					}
				}
				
				// check if we should append the where-clause for revision
				if($addingWhere != "" && $wherePosition != false)
				{
					$sqlQuery = substr($sqlQuery, 0, $wherePosition+6) . $addingWhere . substr($sqlQuery, $wherePosition+6);
				}
			}
		
			// create a new statement and prepare the sql-query
			if($sqlStatement = $this->connection->prepare($sqlQuery)) {
				// now bind parameters
				// the parameter-array has a different format than needed for mysqli_stmt_bind_param
				// either one param is assumed a string and it is a simple string as array-element in $parameters
				// or it is an array itself, containing the parameter as first value and the type as second
				// in $bindParameters all concatenated types will be the first element followed by all parameters
				// Example:
				// $parameters = array("string1", array("string2", "s"), array(1, "i"), array(13.37, "d"))
				// =>
				// $bindParameters = array("ssid", "string1", "string2", 1, 13.37)
				$bindParameters = array();
				if(count($parameters) > 0)
					$bindParameters[0] = "";
				for($a = 0; $a < count($parameters); $a++) {
					$currentParameter = $parameters[$a];
					$fieldType = "s"; // default field-type is string
					unset($fieldContent);
					$fieldContent = "";
					if(is_array($currentParameter)) { // if current parameter is an array, a special field-type is defined
						if(count($currentParameter) > 1) { // double check if a field-type is really defined
							switch($currentParameter[1]) {
								case "i":
									$fieldType = "i";
									break;
								case "d":
									$fieldType = "d";
									break;
								default:
									break;
							}
						}
						$fieldContent = $currentParameter[0];
					}
					else
						$fieldContent = $currentParameter;
					// concatenate type to the first array-element
					$bindParameters[0] .= $fieldType;
					// add parameter as new array-element
					$bindParameters[$a+1] = &$fieldContent;
				}
				// invoke bind_param with all parameters, this is needed because
				// mysqli_stmt_bind_param wants all parameters as "list" and not as array
				// so this workaround is needed for dynamic number of parameters
				if(count($bindParameters) > 0) {
					$bindMethod = new ReflectionMethod('mysqli_stmt', 'bind_param');
					$bindMethod->invokeArgs($sqlStatement, $bindParameters);
				}
				// execute the sql-query with inserted parameters
				$sqlStatement->execute();
				if($sqlStatement->errno) {
					$this->mc->logger->log("SQL-Error: " . $sqlStatement->error . " in " . $sqlQuery, $bindParameters);
				}
				// check if there was any metadata (this would be returned rows for SELECT queries)
				$metadata = $sqlStatement->result_metadata();
				if(!$metadata) {
					// if not, just return the affected rows
					$result->affected_rows = $sqlStatement->affected_rows;
					$result->insert_id = $sqlStatement->insert_id;
				}
				else {
					// otherwise prepare the result
					$sqlStatement->store_result();
					// we want to have an associative array as return, so set every array-element of $params
					// to the reference of the array-element in the associative array $row
					$params = array();
					$row = array();
					while($field = $metadata->fetch_field()) {
						$params[] = &$row[$field->name];
					}
					// no metadata needed any more, so close them
					$metadata->close();
					
					// now obtain the result
					$bindMethod = new ReflectionMethod('mysqli_stmt', 'bind_result');
					// same as above, mysqli_stmt_bind_result wants a "list" of parameters, not an array
					// so invoke the method using the ReflectionMethod-object
					// this binds the results to $params or rather $row
					$bindMethod->invokeArgs($sqlStatement, $params);
					$result->rows = array();
					// get the actual data, write them to $params as the binding above describes
					while($sqlStatement->fetch()) {
						// create new statement-object
						$obj = new stdClass();
						// write the pairs $key=>$val from array $row to $obj
						foreach($row as $key => $val) {
							$obj->{$key} = $val;
						}
						// add the current result-object $obj to the global result-object $result
						$result->rows[] = $obj;
					}
					// clear the result
					$sqlStatement->free_result();
				}
				// close the statement
				$sqlStatement->close();
			}
			else
				$this->mc->logger->log("SQL-Error: Not executed: " . $sqlQuery . " " . $this->sqlerror);
		}
		else {
			$result = $this->sqlerror;
		}
		
		return $result;
	}
	
	function close() {
		$this->connection->close();
	}
}

?>