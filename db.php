<?php
date_default_timezone_set('Asia/Manila');

$host     = getenv('MYSQLHOST')     ?: 'localhost';
$user     = getenv('MYSQLUSER')     ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: '';
$database = getenv('MYSQLDATABASE') ?: 'qrgate_db';
$port     = getenv('MYSQLPORT')     ?: 3306;

$mysqli = new mysqli($host, $user, $password, $database, $port);

if ($mysqli->connect_errno) {
    exit('DB connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');
$mysqli->query("SET time_zone = '+08:00'");

?>