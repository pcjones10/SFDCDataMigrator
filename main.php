÷<?php

define("USERNAME", "pjones@apttusdevpj2.com");
define("PASSWORD", "Lacrosse1");
define("SECURITY_TOKEN", "OsLvpzv91XW1U6Jl7aIKGSxx1");

require_once ('soapclient/SforceEnterpriseClient.php');
require_once('parsecsv.lib.php');

$mySforceConnection = new SforceEnterpriseClient();
$mySforceConnection->createConnection("enterprise.wsdl.xml");
$mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);

$files = scandir('data/');
$sObjectIdMap = array();
foreach($files as $file){
	$sObjects = array();
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
		
		foreach($describedObj->fields as $field){
			if($field->updateable == '1' && $field->type != 'reference'){
				$fieldArray[$field->name] = true;
			}else{
				$fieldArray[$field->name] = false;
			}
			
			if(($field->type == 'reference' && $field->updateable != '1' && strrpos($field->name,'__c') != false)){
				//echo $objName . ' : ' . $field->label . ', ' . $field->type . ' - ' . $field->updateable;
				$isDetail = true;
			}
		}
		
		if(!$isDetail){
			echo 'Parent found : ' . $file . "\n";
			$csv = new parseCSV();
			$csv->auto('data/'.$file);
			foreach ($csv->data as $key => $row){
				$sObject = new stdClass();
				foreach ($row as $column => $value){
					if($fieldArray[$column] && strlen($value) > 0){
						$sObject->$column = utf8_encode($value);
					}
				}
				print_r($sObject);
				array_push($sObjects,$sObject);
				try{
					//$results = $mySforceConnection->create(array($sObject),$objName);
					//$sObjectIdMap[$sObject->Id] = $results[0]->Id;
				}catch(Exception $e){
				}
			}
		}	
	}
	if(count($sObjects) > 0){
		$results = $mySforceConnection->create($sObjects,$objName);
		echo(count($sObjects) . " Reslults:\n");
		print_r($results);
		return;
	}
}
/*
foreach($files as $file){
	// read the file
	$fileContents = file_get_contents($file);
	// replace the data
	foreach ($sObjectIdMap as $oldId => $newId){
		$fileContents = str_replace($oldId, $newId, $fileContents);
	}
	// write the file
	file_put_contents($file, $fileContents);
}
print_r($sObjectIdMap);*/