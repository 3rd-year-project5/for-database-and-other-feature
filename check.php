<?php
require __DIR__.'/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$qr = $_GET['qr'] ?? '';
if ($qr === '') { 
    http_response_code(400);
    echo json_encode(['status'=>'Invalid', 'msg'=>'QR code parameter missing']); 
    exit; 
}

try {
    // Get visitor info from visitors table
    $stmt = $mysqli->prepare("SELECT visitor_id, full_name, email, phone, purpose, host, expiry_at FROM visitors WHERE qr_code=? LIMIT 1");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $stmt->bind_param('s', $qr);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $res = $stmt->get_result();
    $now = new DateTimeImmutable('now');

    if ($res->num_rows === 0) {
        // Log invalid attempt
        $log_stmt = $mysqli->prepare("INSERT INTO logs(qr_code, status) VALUES(?, 'Invalid')");
        if ($log_stmt) {
            $log_stmt->bind_param('s', $qr);
            $log_stmt->execute();
        }
        
        echo json_encode(['status'=>'Invalid', 'msg'=>'QR code not found']);
        exit;
    }

    $row = $res->fetch_assoc();
    $expiry = new DateTimeImmutable($row['expiry_at']);
    
    if ($expiry < $now) {
        // Log expired attempt
        $log_stmt = $mysqli->prepare("INSERT INTO logs(visitor_id, qr_code, status) VALUES(?, ?, 'Expired')");
        if ($log_stmt) {
            $log_stmt->bind_param('is', $row['visitor_id'], $qr);
            $log_stmt->execute();
        }
        
        echo json_encode([
            'status'=>'Expired', 
            'msg'=>'QR code has expired',
            'visitor_id'=>$row['visitor_id'],
            'expired_at'=>$row['expiry_at']
        ]);
        exit;
    }

    // Valid QR code - log successful scan
    $log_stmt = $mysqli->prepare("INSERT INTO logs(visitor_id, qr_code, status) VALUES(?, ?, 'Valid')");
    if ($log_stmt) {
        $log_stmt->bind_param('is', $row['visitor_id'], $qr);
        $log_stmt->execute();
    }

    echo json_encode([
        'status'=>'Valid',
        'visitor_id'=>$row['visitor_id'],
        'visitor_name'=>$row['full_name'],
        'email'=>$row['email'],
        'phone'=>$row['phone'],
        'purpose'=>$row['purpose'],
        'host'=>$row['host'],
        'expires_at'=>$row['expiry_at']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'Error', 'msg'=>'Database error: ' . $e->getMessage()]);
}
?>