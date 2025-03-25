<?php

// Show all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$hostname = "localhost";
$database = "nobels1";
$username = "xlagin";
$password = "heslo-1234";


$pdo =connectDatabase($hostname, $database, $username, $password);
// Connect to the database using PDO
function connectDatabase($hostname, $database, $username, $password) {
    try {
        $conn = new PDO("mysql:host=$hostname;dbname=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
        return null;
    }
}

?>