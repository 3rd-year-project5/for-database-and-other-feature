<?php
// thingspeak_bridge.php - FIXED FOR RATE LIMIT
require 'db.php';

// REPLACE WITH YOUR API KEYS
$THINGSPEAK_READ_API = "10C7QSEXQVSSE4OJ";
$THINGSPEAK_CHANNEL = "3176070";
$THINGSPEAK_WRITE_API = "JNAP3CJY7248NST6"; // Your write key

function processThingSpeakRegistrations() {
    global $mysqli, $THINGSPEAK_READ_API, $THINGSPEAK_CHANNEL, $THINGSPEAK_WRITE_API;
    
    // Get latest entries from ThingSpeak
    $url = "https://api.thingspeak.com/channels/{$THINGSPEAK_CHANNEL}/feeds.json?api_key={$THINGSPEAK_READ_API}&results=10";
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if (!isset($data['feeds'])) {
        echo "No feeds found or API error\n";
        return;
    }
    
    echo "Found " . count($data['feeds']) . " feeds to process\n";
    
    foreach ($data['feeds'] as $feed) {
        $entry_id = $feed['entry_id'];
        $action = $feed['field7'] ?? '';
        $status = $feed['field8'] ?? '';
        
        echo "Processing entry $entry_id: action=$action, status=$status\n";
        
        // Only process pending registration entries
        if ($action === 'register' && $status === 'pending') {
            
            // Check if we already processed this entry
            $check_stmt = $mysqli->prepare("SELECT COUNT(*) FROM processed_entries WHERE entry_id = ?");
            $check_stmt->bind_param('i', $entry_id);
            $check_stmt->execute();
            $check_stmt->bind_result($count);
            $check_stmt->fetch();
            $check_stmt->close();
            
            if ($count == 0) {
                // Process new registration
                $full_name = $feed['field1'] ?? '';
                $email = $feed['field2'] ?? '';
                $phone = $feed['field3'] ?? '';
                $purpose = $feed['field4'] ?? '';
                $host = $feed['field5'] ?? '';
                $notes = $feed['field6'] ?? '';
                
                if (!empty($full_name)) {
                    // Generate QR code
                    $qr_code = bin2hex(random_bytes(8));
                    $expiry = date('Y-m-d H:i:s', time() + 86400);
                    
                    // Insert into local database
                    $stmt = $mysqli->prepare(
                        "INSERT INTO visitors(full_name, email, phone, purpose, host, notes, qr_code, expiry_at) 
                         VALUES(?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param('ssssssss', $full_name, $email, $phone, $purpose, $host, $notes, $qr_code, $expiry);
                    
                    if ($stmt->execute()) {
                        $visitor_id = $mysqli->insert_id;
                        
                        // Mark as processed
                        $mark_stmt = $mysqli->prepare("INSERT INTO processed_entries(entry_id) VALUES(?)");
                        $mark_stmt->bind_param('i', $entry_id);
                        $mark_stmt->execute();
                        $mark_stmt->close();
                        
                        // ✅ FIX: Create NEW entry with all data including processed status
                        // This avoids the 15-second rate limit
                        $update_success = createProcessedEntry($full_name, $email, $phone, $purpose, $host, $notes, $qr_code, $visitor_id, $THINGSPEAK_WRITE_API);
                        
                        if ($update_success) {
                            echo "✅ Processed registration: $full_name (QR: $qr_code, ID: $visitor_id)\n";
                            echo "✅ ThingSpeak updated successfully\n";
                        } else {
                            echo "✅ Processed registration: $full_name (QR: $qr_code, ID: $visitor_id)\n";
                            echo "⚠️ Warning: ThingSpeak update may have failed (rate limit?)\n";
                        }
                        
                        error_log("ThingSpeak Bridge: Registered $full_name with QR $qr_code");
                    } else {
                        echo "❌ Database insert failed for: $full_name\n";
                    }
                    $stmt->close();
                } else {
                    echo "❌ Empty name for entry $entry_id\n";
                }
            } else {
                echo "Entry $entry_id already processed\n";
            }
        }
    }
}

function createProcessedEntry($full_name, $email, $phone, $purpose, $host, $notes, $qr_code, $visitor_id, $write_api) {
    // Create a NEW entry with all data + processed status
    $status_value = "processed:qr_" . $qr_code . "_id_" . $visitor_id;
    
    // Build update URL with ALL fields
    $url = "https://api.thingspeak.com/update?api_key=" . $write_api;
    $url .= "&field1=" . urlencode($full_name);
    $url .= "&field2=" . urlencode($email);
    $url .= "&field3=" . urlencode($phone);
    $url .= "&field4=" . urlencode($purpose);
    $url .= "&field5=" . urlencode($host);
    $url .= "&field6=" . urlencode($notes);
    $url .= "&field7=register";
    $url .= "&field8=" . urlencode($status_value);
    
    echo "Creating new ThingSpeak entry with processed status\n";
    echo "QR Code: $qr_code | Visitor ID: $visitor_id\n";
    
    // Wait 16 seconds to respect ThingSpeak rate limit
    echo "Waiting 16 seconds for ThingSpeak rate limit...\n";
    sleep(16);
    
    // Send update request
    $response = @file_get_contents($url);
    
    if ($response === false) {
        echo "❌ ThingSpeak update failed - no response\n";
        return false;
    }
    
    echo "ThingSpeak response: $response\n";
    
    // Response should be a positive number (entry ID)
    if (is_numeric($response) && intval($response) > 0) {
        return true;
    }
    
    return false;
}

// Run the bridge
echo "===========================================\n";
echo "QRGate ThingSpeak Bridge\n";
echo "===========================================\n";
echo "Execution time: " . date('Y-m-d H:i:s') . "\n";
echo "===========================================\n";

processThingSpeakRegistrations();

echo "===========================================\n";
echo "Bridge execution completed\n";
echo "===========================================\n";
?>