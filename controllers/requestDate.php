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
This file is used as an auxiliar ENDPOINT for getting the timestamp corresponding to
the last measurement available, as well as for X minutes before.
This information will be eventually used in dbGetDateRange.php endpoint
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

$resultLatestTimestamp = $databaseManager->GetLastTimestamp();

if ($resultLatestTimestamp) {

    // Get required data
    $minutesBefore = intval($params["minutes"]);
    $latestTimestamp = floatval($resultLatestTimestamp['timestamp']);
    $timeBefore = $latestTimestamp - ($minutesBefore * 60);
    
    // Format the dates
    $latestDate = date('Y-m-d H:i:s', $latestTimestamp);
    $timeBeforeDate = date('Y-m-d H:i:s', $timeBefore);

    $response = [
        'latestTimestamp' => $latestDate,
        'timeBefore' => $timeBeforeDate
    ];

    echo json_encode($response);
}
else {
    echo json_encode(array("error" => "No data found in the timestamps table."));
}

$databaseManager->close();