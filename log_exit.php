<?php
// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

require __DIR__ . '/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$qr_code = trim($_POST['qr_code'] ?? '');

if (empty($qr_code)) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => 'QR code is required']));
}

try {
    // Get visitor information
    $stmt = $mysqli->prepare("
        SELECT visitor_id, full_name, email, phone, purpose, host, 
               expiry_at, created_at, exit_time 
        FROM visitors 
        WHERE qr_code = ? 
        LIMIT 1
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $stmt->bind_param('s', $qr_code);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'ok' => false, 
            'msg' => 'QR code not found. Please check and try again.'
        ]);
        exit;
    }
    
    $visitor = $result->fetch_assoc();
    $current_time = date('Y-m-d H:i:s');
    
    // Check if already exited
    if (!empty($visitor['exit_time'])) {
        echo json_encode([
            'ok' => false, 
            'msg' => 'This visitor has already logged an exit at ' . date('M d, Y h:i A', strtotime($visitor['exit_time']))
        ]);
        exit;
    }
    
    // Check if QR code is expired
    $expiry = new DateTimeImmutable($visitor['expiry_at'], new DateTimeZone('Asia/Manila'));
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    
    if ($expiry < $now) {
        echo json_encode([
            'ok' => false, 
            'msg' => 'QR code has expired. Cannot log exit.',
            'expired_at' => $visitor['expiry_at']
        ]);
        exit;
    }
    
    // Update exit time
    $update_stmt = $mysqli->prepare("
        UPDATE visitors 
        SET exit_time = ?, last_status = 'Exited', last_scan = ? 
        WHERE visitor_id = ?
    ");
    
    if (!$update_stmt) {
        throw new Exception("Update prepare failed: " . $mysqli->error);
    }
    
    $update_stmt->bind_param('ssi', $current_time, $current_time, $visitor['visitor_id']);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Update execute failed: " . $update_stmt->error);
    }
    
    // FIXED: Log the exit in logs table with correct parameters
    $log_stmt = $mysqli->prepare("
        INSERT INTO logs(visitor_id, qr_code, status) 
        VALUES(?, ?, ?)
    ");
    
    if ($log_stmt) {
        $status = 'Exited';
        $log_stmt->bind_param('iss', $visitor['visitor_id'], $qr_code, $status);
        $log_stmt->execute();
    }
    
    // Success response
    echo json_encode([
        'ok' => true,
        'msg' => 'Exit logged successfully! Thank you for visiting.',
        'visitor_id' => $visitor['visitor_id'],
        'visitor_name' => $visitor['full_name'],
        'email' => $visitor['email'],
        'phone' => $visitor['phone'],
        'purpose' => $visitor['purpose'],
        'host' => $visitor['host'],
        'entry_time' => date('M d, Y h:i A', strtotime($visitor['created_at'])),
        'exit_time' => date('M d, Y h:i A', strtotime($current_time)),
        'duration' => calculateDuration($visitor['created_at'], $current_time)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false, 
        'msg' => 'Database error: ' . $e->getMessage()
    ]);
}

function calculateDuration($entry_time, $exit_time) {
    $tz = new DateTimeZone('Asia/Manila');
    $entry = new DateTime($entry_time, $tz);
    $exit = new DateTime($exit_time, $tz);
    $interval = $entry->diff($exit);
    
    $duration = '';
    if ($interval->h > 0) {
        $duration .= $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ';
    }
    if ($interval->i > 0) {
        $duration .= $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
    }
    if (empty($duration)) {
        $duration = 'Less than a minute';
    }
    
    return trim($duration);
}
?>