<?php

require_once __DIR__ . '/../vendor/autoload.php';

class thDatabaseManager {
    private $db;

    public function __construct($dbPath) {
        $this->db = new SQLite3($dbPath);
    }

    public function GetLastTimestamp() {
        $query = "
        SELECT timestamp
        FROM timestamps
        ORDER BY timestamp DESC
        LIMIT 1;
        ";
        return $this->db->querySingle($query, true);
    }

    public function GetInfoBetweenTimestamps($timeBefore, $latestTimestamp) {
        $query = "
        SELECT t.timestamp, 
               COALESCE(s_temp.sensor_number, s_hum.sensor_number) AS sensor_number,
               temp.temperature,
               hum.humidity
        FROM timestamps AS t
        LEFT JOIN temperature AS temp ON t.id = temp.timestamp_id
        LEFT JOIN sensors AS s_temp ON temp.sensor_id = s_temp.id
        LEFT JOIN humidity AS hum ON t.id = hum.timestamp_id
        LEFT JOIN sensors AS s_hum ON hum.sensor_id = s_hum.id
        WHERE t.timestamp BETWEEN $timeBefore AND $latestTimestamp
        ORDER BY t.timestamp ASC;
        ";

        $result = $this->db->query($query);
        if (!$result) {
            echo "SQL Error: " . $this->db->lastErrorMsg();
        }

        return $result;
    }

    public function FetchAllResults($results) {
        $data = array();
        $timestamps = array();
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $data[] = array(
                "timestamp" => $row['timestamp'],
                "sensor_number" => $row['sensor_number'],
                "temperature" => $row['temperature'],
                "humidity" => $row['humidity']
            );
            $timestamps[] = $row['timestamp'];
        }
        return [$data, $timestamps];
    }

    public function FormatData($data) {
        $formatted_data = array();
        $timestamps = array();

        foreach ($data as $entry) {
            $timestamp = $entry['timestamp'];
            if (!isset($formatted_data[$timestamp])) {
                $formatted_data[$timestamp] = array("time" => $timestamp);
            }
            if (isset($entry['temperature'])) {
                $formatted_data[$timestamp]["sensor" . $entry['sensor_number'] . "_temperature"] = $entry['temperature'];
            }
            if (isset($entry['humidity'])) {
                $formatted_data[$timestamp]["sensor" . $entry['sensor_number'] . "_humidity"] = $entry['humidity'];
            }
            $timestamps[] = $timestamp;
        }

        return array_values($formatted_data); // Return as an indexed array
    }

    public function DecimateData($data, $timestamps, $batchSize) {

        // Get unique timestamps and sort them
        $unique_timestamps = array_unique($timestamps);
        sort($unique_timestamps);
    
        // Initialize variables
        $decimated_data = array();
        $counter = 0;
    
        // Select one timestamp out of every batchSize
        foreach ($unique_timestamps as $timestamp) {
            if ($counter % $batchSize == 0) {
                // Add all sensor data for the selected timestamp
                $sensor_data = array("time" => $timestamp);
                foreach ($data as $entry) {
                    if ($entry['timestamp'] == $timestamp) {
                        $sensor_data["sensor" . $entry['sensor_number'] . "_temperature"] = $entry['temperature'];
                        $sensor_data["sensor" . $entry['sensor_number'] . "_humidity"] = $entry['humidity'];
                    }
                }
                $decimated_data[] = $sensor_data;
            }
            $counter++;
        }
    
        return $decimated_data;
    }

    public function close() {
        $this->db->close();
    }
}