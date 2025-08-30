<?php
require __DIR__.'/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$qr = $_GET['qr'] ?? '';
if ($qr===''){ echo json_encode(['status'=>'Invalid']); exit; }

$stmt = $mysqli->prepare("SELECT visitor_id, expiry_at FROM visitors WHERE qr_code=? LIMIT 1");
$stmt->bind_param('s',$qr);
$stmt->execute();
$res = $stmt->get_result();

$now = new DateTimeImmutable('now');

if ($res->num_rows===0){
    echo json_encode(['status'=>'Invalid']);
    exit;
}

$row = $res->fetch_assoc();
$expiry = new DateTimeImmutable($row['expiry_at']);
if ($expiry < $now){
    echo json_encode(['status'=>'Expired']);
    exit;
}

echo json_encode(['status'=>'Valid','visitor_id'=>$row['visitor_id']]);
