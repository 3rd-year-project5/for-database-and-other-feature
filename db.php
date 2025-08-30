<?php
$mysqli = new mysqli('localhost','root','','qrgate_db');
if ($mysqli->connect_errno) {
    exit('DB connection failed: '.$mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');
?>
