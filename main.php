÷<?php

define("USERNAME", "pjones@apttusdevpj2.com");
define("PASSWORD", "Lacrosse1");
define("SECURITY_TOKEN", "OsLvpzv91XW1U6Jl7aIKGSxx1");

require_once ('soapclient/SforceEnterpriseClient.php');
require_once('parsecsv.lib.php');

$mySforceConnection = new SforceEnterpriseClient();
$mySforceConnection->createConnection("enterprise.wsdl.xml");
$mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);

$sObjectIdMap = array();
main(false, $mySforceConnection, $sObjectIdMap);

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
				insertSObjects($mySforceConnection, $file, $fieldArray, $sObjectIdMap);
			}else if($isDetail && $processChildren){
				insertSObjects($mySforceConnection, $file, $fieldArray, $sObjectIdMap);
			}
		}
	}
	if(!$processChildren){
		main(true, $mySforceConnection, $sObjectIdMap);
	}else{
		createIdHashMap($mySforceConnection, $sObjectIdMap);
	}
}

function insertSObjects($mySforceConnection, $file, $fieldArray, &$sObjectIdMap){
	$csv = new parseCSV();
	$csv->auto('data/'.$file);
	$sObjects = array();
	foreach ($csv->data as $key => $row){
		$sObject = new stdClass();
		foreach ($row as $column => $value){
			echo $file . ' ~!~ ' . $column . ':' . $fieldArray[$column] . ', value: ' . $value . "\n";
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
		$objName = basename($file,'.csv');
		$results = $mySforceConnection->create($sObjects,$objName);
		print_r($results);
		foreach ($csv->data as $key => $row){
			echo 'old id: ' . $row['Id'] . ' - new id: ' . 
			$results[$key]->id . ' :: ' . $key . "\n";
			
			$sObjectIdMap[$row['Id']] = $results[$key]->id;
		}
		print_r($sObjectIdMap);
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
				$fileContents = str_replace($oldId, $newId, $fileContents);
			}
			$handle = fopen('data/'.$file,'w');
			fwrite($handle,$fileContents);
			fclose($handle);
		}
	}
}