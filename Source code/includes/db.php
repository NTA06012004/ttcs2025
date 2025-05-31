<?php
$db_host = "sql204.infinityfree.com";
$db_user = "if0_38906010"; // Your DB username
$db_pass = "fRvUNnsN2Hf";     // Your DB password
$db_name = "if0_38906010_school_manager";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>