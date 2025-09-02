<?php
require 'db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$sql = "SELECT visitor_id, full_name, email, phone, purpose, host, notes, qr_code, expiry_at, last_status, last_scan, created_at
        FROM visitors ORDER BY visitor_id DESC";

try {
    $result = $mysqli->query($sql);
    
    if (!$result) {
        throw new Exception("Query failed: " . $mysqli->error);
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Check if expired
        $now = new DateTimeImmutable('now');
        $expiry = new DateTimeImmutable($row['expiry_at']);
        $row['is_expired'] = $expiry < $now;
        
        $data[] = $row;
    }
    
    echo json_encode(['ok' => true, 'data' => $data]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Database error: ' . $e->getMessage()]);
}
?>