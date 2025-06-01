<?php
$db_host = "localhost";
$db_user = "root"; // Your DB username
$db_pass = "";     // Your DB password
$db_name = "school_manager";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>