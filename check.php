<?php
// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

require __DIR__ . '/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$qr = $_GET['qr'] ?? '';
if ($qr === '') {
    http_response_code(400);
    echo json_encode(['status'=>'Invalid', 'msg'=>'QR code parameter missing']);
    exit;
}

try {
    $stmt = $mysqli->prepare("SELECT visitor_id, full_name, email, phone, purpose, host, expiry_at, last_status FROM visitors WHERE qr_code=? LIMIT 1");
    if (!$stmt) throw new Exception("Prepare failed: " . $mysqli->error);

    $stmt->bind_param('s', $qr);
    if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

    $res = $stmt->get_result();
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));

    if ($res->num_rows === 0) {
        $log = $mysqli->prepare("INSERT INTO logs(qr_code, status) VALUES(?, 'Invalid')");
        if ($log) { $log->bind_param('s', $qr); $log->execute(); }
        echo json_encode(['status'=>'Invalid', 'msg'=>'QR code not found']);
        exit;
    }

    $row        = $res->fetch_assoc();
    $visitor_id = $row['visitor_id'];
    $expiry     = new DateTimeImmutable($row['expiry_at'], new DateTimeZone('Asia/Manila'));
    $current_time = $now->format('Y-m-d H:i:s');

    // ── 1. Expired ──────────────────────────────────────────────────────────
    if ($expiry < $now) {
        $log = $mysqli->prepare("INSERT INTO logs(visitor_id, qr_code, status) VALUES(?, ?, 'Expired')");
        if ($log) { $log->bind_param('is', $visitor_id, $qr); $log->execute(); }

        $upd = $mysqli->prepare("UPDATE visitors SET last_status='Expired', last_scan=? WHERE visitor_id=?");
        if ($upd) { $upd->bind_param('si', $current_time, $visitor_id); $upd->execute(); }

        echo json_encode(['status'=>'Expired', 'msg'=>'QR code has expired', 'visitor_id'=>$visitor_id]);
        exit;
    }

    // ── 2. Already exited — block re-entry ───────────────────────────────────
    if ($row['last_status'] === 'Exited') {
        $log = $mysqli->prepare("INSERT INTO logs(visitor_id, qr_code, status) VALUES(?, ?, 'Invalid')");
        if ($log) { $log->bind_param('is', $visitor_id, $qr); $log->execute(); }

        echo json_encode([
            'status' => 'AlreadyExited',
            'msg'    => 'Visitor has already exited. Re-entry is not allowed.',
            'visitor_id'   => $visitor_id,
            'visitor_name' => $row['full_name']
        ]);
        exit;
    }

    // ── 3. Valid — allow entry ────────────────────────────────────────────────
    $log = $mysqli->prepare("INSERT INTO logs(visitor_id, qr_code, status) VALUES(?, ?, 'Valid')");
    if ($log) { $log->bind_param('is', $visitor_id, $qr); $log->execute(); }

    // entry_scan is set only on the very first scan-in
    $upd = $mysqli->prepare("UPDATE visitors SET last_status='Inside', last_scan=?, entry_scan=IF(entry_scan IS NULL, ?, entry_scan) WHERE visitor_id=?");
    if ($upd) { $upd->bind_param('ssi', $current_time, $current_time, $visitor_id); $upd->execute(); }

    echo json_encode([
        'status'       => 'Inside',
        'visitor_id'   => $visitor_id,
        'visitor_name' => $row['full_name'],
        'email'        => $row['email'],
        'phone'        => $row['phone'],
        'purpose'      => $row['purpose'],
        'host'         => $row['host'],
        'expires_at'   => $row['expiry_at'],
        'current_time' => $current_time
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false, 
        'msg' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

