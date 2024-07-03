<?php

require_once __DIR__ . '/../vendor/autoload.php';

class accDatabaseManager {
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
        SELECT a.id, t.timestamp, s.sensor_number, a.acceleration_value
        FROM accelerations AS a
        JOIN timestamps AS t ON a.timestamp_id = t.id
        JOIN sensors AS s ON a.sensor_id = s.id
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
                "id" => $row['id'],
                "timestamp" => $row['timestamp'],
                "sensor_number" => $row['sensor_number'],
                "acceleration_value" => $row['acceleration_value']
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
            $formatted_data[$timestamp]["sensor" . $entry['sensor_number']] = $entry['acceleration_value'];
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
                        $sensor_data["sensor" . $entry['sensor_number']] = $entry['acceleration_value'];
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