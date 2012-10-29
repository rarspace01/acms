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
	
	function query($sqlQuery, $parameters=array())
	{
		$result = new stdClass();
		
		if($this->sqlready) {
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