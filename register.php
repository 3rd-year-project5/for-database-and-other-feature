<?php
require __DIR__ . '/db.php';

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

// Generate unique QR string
$qr_code = bin2hex(random_bytes(8)); // 16 chars
$expiry  = date('Y-m-d H:i:s', time() + 60); // expires in 1 min

$stmt = $mysqli->prepare(
    "INSERT INTO visitors(full_name,email,phone,purpose,host,notes,qr_code,expiry_at)
     VALUES(?,?,?,?,?,?,?,?)"
);
$stmt->bind_param('ssssssss', $full_name,$email,$phone,$purpose,$host,$notes,$qr_code,$expiry);
$stmt->execute();

// Return JSON for frontend
header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'qr_code' => $qr_code,
    'expiry_at' => $expiry
]);
