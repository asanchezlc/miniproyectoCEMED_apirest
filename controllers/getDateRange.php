<?php
header('Content-Type: application/json');

// Permite solicitudes CORS de cualquier origen
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Authorization, Content-Type, User');

require_once __DIR__ . '/../vendor/autoload.php'; // for allowing to use Flight library
require_once __DIR__ . '/../src/accDatabaseManager.php'; // Include the DatabaseManager class
require_once __DIR__ . '/../src/thDatabaseManager.php'; // Include the DatabaseManager class

/*
FILE EXPLANATION:
This file is used as the MAIN ENDPOINT for API interactions (mainly through python, as
postman cannot manage such a big amount of data).
3 types of requests (date range, last minutes and date +- minutes, call this file)
are available, and all of them use this file as endpoint.
*/

// Directory path where files are stored, adjust according to the environment
$pathConfig = require __DIR__ . '/../config/path_config.php';

$postData = json_decode(file_get_contents('php://input'), true);
$params = $postData['params'] ?? [];

// 2. Make the db query
$db = $params["db_type"];
if ($db == "accelerations") {
    $dbPath = $pathConfig['db_accelerations'];
    $databaseManager = new accDatabaseManager($dbPath);
} elseif ($db == "temperature_humidity") {
    $dbPath = $pathConfig['db_temperature_humidity'];
    $databaseManager = new thDatabaseManager($dbPath);
}

// Get required data
$startDateStr = $params["start_date"];
$endDateStr = $params["end_date"];
$startDate = strtotime($startDateStr);
$endDate = strtotime($endDateStr);
$results = $databaseManager->GetInfoBetweenTimestamps($startDate, $endDate);

if ($results) {

    list($data, $timestamps) = $databaseManager->FetchAllResults($results);

    if (isset($params["decimation_factor"])) {
        $batchSize = intval($params["decimation_factor"]);
        $data = $databaseManager->DecimateData($data, $timestamps, $batchSize);
    } else {
        $data = $databaseManager->FormatData($data);
    }
    
    echo json_encode($data);
    
} else {
    echo json_encode(array("error" => "No data found within the specified dates."));
}

$databaseManager->close();