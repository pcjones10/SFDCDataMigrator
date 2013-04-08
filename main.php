÷<?php

define("USERNAME", "pjones@apttusdevpj3.com");
define("PASSWORD", "Lacrosse1");
define("SECURITY_TOKEN", "AloffpMAl876xrkc4lhuC1x3");

require_once ('soapclient/SforceEnterpriseClient.php');
require_once('parsecsv.lib.php');

$mySforceConnection = new SforceEnterpriseClient();
$mySforceConnection->createConnection("Enterprise.wsdl.xml");
$mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);

$sObjectIdMap = array();
main(false, $mySforceConnection, $sObjectIdMap);
updateAllObjects($mySforceConnection);
function main($processChildren, $mySforceConnection, &$sObjectIdMap){
	$files = scandir('data/');
	foreach($files as $file){
		$fieldArray = array();
		echo 'Processing file : ' . $file . "\n";
		$validObj = true;
		try{
			$objName = basename($file,'.csv');
			$describedObj = $mySforceConnection->describeSObject($objName);
		}catch(Exception $e){
			$validObj = false;
		}
		if($validObj){
			$isDetail = false;
			$isRequired = false;
			
			//loop through fields of object and determine if field should be set in object.
			foreach($describedObj->fields as $field){
				//if field is updateable and not a lookup then use field
				if($field->updateable == '1' && $field->type != 'reference'){
					$fieldArray[$field->name] = true;
				
				//if not updateable, is a lookup, is a custom field and processing child
				//then set special case 
				}else if($field->updateable != '1' 
				&& $field->type == 'reference'
				&& strrpos($field->name,'__c') != false
				&& $processChildren){
					echo $field->name . " is detail!!\n";
					$fieldArray[$field->name] = 'detail';
				
				//else do not use field
				}else{
					$fieldArray[$field->name] = false;
					
				}
				
				//if field is lookup, not updateable and custom then set object as detail
				if(($field->type == 'reference' 
				&& $field->updateable != '1' 
				&& strrpos($field->name,'__c') != false)){
					$isDetail = true;
				}
			}
			
			if(!$isDetail && !$processChildren){
				insertSObjects($mySforceConnection, $file, $fieldArray, $sObjectIdMap, $processChildren);
			}else if($isDetail && $processChildren){
				insertSObjects($mySforceConnection, $file, $fieldArray, $sObjectIdMap, $processChildren);
			}
		}
	}
	if(!$processChildren){
		main(true, $mySforceConnection, $sObjectIdMap);
	}else{
		createIdHashMap($mySforceConnection, $sObjectIdMap);
	}
}

function insertSObjects($mySforceConnection, $file, $fieldArray, &$sObjectIdMap, $processingChildren){
	$csv = new parseCSV();
	$csv->auto('data/'.$file);
	$sObjects = array();
	$objName = basename($file,'.csv');
	foreach ($csv->data as $key => $row){
		$sObject = new stdClass();
		if($objName == 'Attachment'){
			$sObject->Body = base64_encode(file_get_contents('data/Attachments/' . $row['Id']));
			$sObject->ParentId =  $row['ParentId'];
		}
		foreach ($row as $column => $value){
			echo $objName . ' ~' . $processingChildren . '~ ' . $column . ':' . $fieldArray[$column] . ', value: ' . $value . "\n";
			if($fieldArray[$column] === 'detail' && strlen($value) > 0){
				echo 'old parent id:' . $value . 
				' - new parent id:' . $sObjectIdMap[$value] . "\n";
				$sObject->$column = $sObjectIdMap[$value];
			}else if($fieldArray[$column] && strlen($value) > 0){
				$sObject->$column = utf8_encode($value);
			}
		}
		print_r($sObject);
		array_push($sObjects,$sObject);
	}
	if(count($sObjects) > 0){
		$sObjectsToInsert = array();
		foreach($sObjects as $sObject){
			$created = false;
			if(count($sObjectsToInsert) >= 199){
				$results = $mySforceConnection->create($sObjectsToInsert,$objName);
				print_r($results);
				foreach ($csv->data as $key => $row){
					echo 'old id: ' . $row['Id'] . ' - new id: ' . 
					$results[$key]->id . ' :: ' . $key . "\n";
					
					$sObjectIdMap[$row['Id']] = $results[$key]->id;
				}
				$sObjectsToInsert = array();
				$created = true;
			}
			array_push($sObjectsToInsert,$sObject);
		}
		if(!$created){
			$results = $mySforceConnection->create($sObjectsToInsert,$objName);
			print_r($results);
			foreach ($csv->data as $key => $row){
				echo 'old id: ' . $row['Id'] . ' - new id: ' . 
				$results[$key]->id . ' :: ' . $key . "\n";
				
				$sObjectIdMap[$row['Id']] = $results[$key]->id;
			}
		}
	}
}

function createIdHashMap($mySforceConnection, &$sObjectIdMap){
	$files = scandir('data/');
	foreach($files as $file){
		$validObj = true;
		try{
			$objName = basename($file,'.csv');
			$describedObj = $mySforceConnection->describeSObject($objName);
		}catch(Exception $e){
			$validObj = false;
		}
		echo $validObj . "\n";
		if($validObj){
			echo "writing file...\n";
			$fileContents = file_get_contents('data/'.$file);
			foreach ($sObjectIdMap as $oldId => $newId){
				if(strlen($newId) > 0){
					$fileContents = str_replace($oldId, $newId, $fileContents);
				}
			}
			$handle = fopen('data/'.$file,'w');
			fwrite($handle,$fileContents);
			fclose($handle);
		}
	}
}

function updateAllObjects($mySforceConnection){
	$files = scandir('data/');
	foreach($files as $file){
		$fieldArray = array();
		echo 'Processing file : ' . $file . "\n";
		$validObj = true;
		
		try{
			$objName = basename($file,'.csv');
			$describedObj = $mySforceConnection->describeSObject($objName);
		}catch(Exception $e){
			$validObj = false;
		}
		
		if($validObj){
			//loop through fields of object and determine if field should be set in object.
			foreach($describedObj->fields as $field){
				if($field->updateable == '1' && $field->name != 'OwnerId'){
					//echo $field->name . ' ~!: ' . $field->type . "\n";
					$fieldArray[$field->name] = true;
					if(!(strrpos($field->type,'date') === false)){
						//echo $field->type . " ISDATE\n";
						$fieldArray[$field->name] = 'date';
					}
				}
			}
		}
		$csv = new parseCSV();
		$csv->auto('data/'.$file);
		$sObjects = array();
		foreach ($csv->data as $key => $row){
			$sObject = new stdClass();
			$sObject->Id = $row["Id"];
			print_r($row);
			foreach ($row as $column => $value){
				if($fieldArray[$column] === 'date' && strlen($value) > 0){
					$sObject->$column = gmdate("Y-m-d\TH:i:s\Z",$value);
					//echo 'DATEFORMAT ' . $value . ' === ' . $fieldsToUpdate[$column] . "\n";
				}else if($fieldArray[$column] && strlen($value) > 0){
					echo $column . ' == ' . $value . "\n";
					$sObject->$column = utf8_encode($value);
				}
			}
			print_r($sObject);
			array_push($sObjects,$sObject);
		}
		
		$sObjectsToUpdate = array();
		foreach($sObjects as $sObject){
			//print_r($sObjectsToUpdate);
			$results = $mySforceConnection->update($sObjectsToUpdate,$objName);
			print_r($results);
			$sObjectsToUpdate = array();
			array_push($sObjectsToUpdate,$sObject);
		}
	}
}