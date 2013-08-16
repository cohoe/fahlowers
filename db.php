<?php

# Adapted from sample code from Charlie Hayes (http://cybercomment.com)

$dbFileName = "db.sqlite3";

if(!file_exists($dbFileName)) {
	throw new Exception("Database file does not exist!");
}

$db = new PDO("sqlite:$dbFileName");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function exceptionHandler($e) {
	print $e->getMessage();
	die;
}

function getDbScalar($sql)
{
	global $db;
	try {
		$q = $db->prepare($sql);
		$q->execute();
		//return $q->fetchAll()[0][0];
	} catch (Exception $ex) {
		exceptionHandler($ex);
		return array();
	}
}

function getDbResults($sql){
	global $db;
	try{
		$q = $db->prepare($sql);
		$q->execute();
		return $q->fetchAll();
	}catch (Exception $ex){
		exceptionHandler($ex);
		return array();
	}
}

function getDbResultsWithParams($sql, $params)
{
	global $db;
	try {
		$q = $db->prepare($sql);
		$q->execute($params);
		return $q->fetchAll();
	} catch (Exception $ex) {
		exceptionHandler($ex);
		return array();
	}
}

function dbExecute($sql)
{
	global $db;
	try {
		$q = $db->prepare($sql);
		$q->execute();
		return true;
	} catch (Exception $ex) {
		exceptionHandler($ex);
		return false;
	}
}

function dbExecuteWithParams($sql, $params)
{
	global $db;
	try {
		$q = $db->prepare($sql);
		$q->execute($params);
		return true;
	} catch (Exception $ex) {
		exceptionHandler($ex);
		return $ex->getMessage();
	}
}
