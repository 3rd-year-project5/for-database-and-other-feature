<?php
// Fix for register.php - Add timezone setting at the top

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

require __DIR__ . '/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$full_name = $_POST['full_name'] ?? '';
$email     = $_POST['email'] ?? '';
$phone     = $_POST['phone'] ?? '';
$purpose   = $_POST['purpose'] ?? '';
$host      = $_POST['host'] ?? '';
$notes     = $_POST['notes'] ?? '';

if ($full_name === '') {
    http_response_code(400);
    exit(json_encode(['ok'=>false,'msg'=>'Name is required']));
}

try {
    // Generate unique QR string - this is what goes in the QR code
    $qr_code = bin2hex(random_bytes(8)); // 16 character hex string
    
    // Set expiry time - 1 hour from now in Philippines time
    $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour
    
    // Debug: Show current time and expiry time
    error_log("Current time: " . date('Y-m-d H:i:s:'));
    error_log("Expiry time: " . $expiry);
    
    $stmt = $mysqli->prepare(
        "INSERT INTO visitors(full_name,email,phone,purpose,host,notes,qr_code,expiry_at)
         VALUES(?,?,?,?,?,?,?,?)"
    );
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $stmt->bind_param('ssssssss', $full_name,$email,$phone,$purpose,$host,$notes,$qr_code,$expiry);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    // Return the hex code that will be embedded in the QR image
    echo json_encode([
        'ok' => true,
        'qr_code' => $qr_code,  // This hex string is what the QR code will contain
        'expiry_at' => $expiry,
        'visitor_id' => $mysqli->insert_id,
        'current_time' => date('Y-m-d H:i:s') // For debugging
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Database error: ' . $e->getMessage()]);
}
?>