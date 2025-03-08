<?php

// Simple CSV file parser
function parseCSV($filename) {
    $handle = fopen($filename, "r");
    $data = array();
    while (($row = fgetcsv($handle, 0, ";")) !== FALSE) {
        $data[] = array_filter($row);  // push only non-empty values
    }
    fclose($handle);
    
    unset($data[0]);  // remove the first row (column names)
    return $data;
}

$parsed_data = parseCSV("nobel_v5.2_FYZ.csv");

echo "<pre>";
print_r($parsed_data);
echo "</pre>";