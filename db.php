<?php
date_default_timezone_set('Asia/Manila');
$mysqli = new mysqli(
    'localhost',           // Host
    'root',                      // Username
    '',              // Password
    'qrgate_db'            // Database name
);
if ($mysqli->connect_errno) {
    exit('DB connection failed: '.$mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');
$mysqli->query("SET time_zone = '+08:00'");
?>
