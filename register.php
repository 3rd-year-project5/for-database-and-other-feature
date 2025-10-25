<?php
// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

require __DIR__ . '/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get and trim inputs
$full_name = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$purpose   = $_POST['purpose'] ?? '';
$host      = trim($_POST['host'] ?? '');
$notes     = trim($_POST['notes'] ?? '');

// =======================================
// VALIDATION FUNCTIONS
// ======================================

function validateFullName($name) {
    // Check if name is empty
    if (empty($name)) {
        return ['valid' => false, 'msg' => 'Name is required'];
    }
    
    // Check minimum length
    if (strlen($name) < 3) {
        return ['valid' => false, 'msg' => 'Name must be at least 3 characters'];
    }
    
    // Check if name has at least 2 words (First name + Last name)
    $name_parts = preg_split('/\s+/', $name); // Split by spaces
    if (count($name_parts) < 2) {
        return ['valid' => false, 'msg' => 'Please enter both first name and last name'];
    }
    
    // Check each part has at least 2 characters
    foreach ($name_parts as $part) {
        if (strlen($part) < 2) {
            return ['valid' => false, 'msg' => 'Each name part must be at least 2 characters'];
        }
    }
    
    // Check for valid characters (letters, spaces, hyphens, apostrophes only)
    if (!preg_match("/^[a-zA-Z\s\-'\.]+$/", $name)) {
        return ['valid' => false, 'msg' => 'Name can only contain letters, spaces, hyphens, and apostrophes'];
    }
    
    return ['valid' => true];
}

function validateEmail($email) {
    // Email is optional, but if provided must be valid
    if (empty($email)) {
        return ['valid' => true]; // Optional field
    }
    
    // Check email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'msg' => 'Invalid email format. Example: user@example.com'];
    }
    
    // Additional checks for common typos
    $common_domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
    $email_parts = explode('@', $email);
    
    if (count($email_parts) === 2) {
        $domain = strtolower($email_parts[1]);
        
        // Check for common typos (gmial.com, yahooo.com, etc.)
        $typos = [
            'gmial.com' => 'gmail.com',
            'gmai.com' => 'gmail.com',
            'gmil.com' => 'gmail.com',
            'yaho.com' => 'yahoo.com',
            'yahooo.com' => 'yahoo.com',
            'hotmial.com' => 'hotmail.com',
            'outlok.com' => 'outlook.com'
        ];
        
        if (isset($typos[$domain])) {
            return ['valid' => false, 'msg' => "Did you mean {$typos[$domain]}?"];
        }
        
        // Check if domain has at least one dot after @
        if (strpos($domain, '.') === false) {
            return ['valid' => false, 'msg' => 'Email domain must include a dot (e.g., .com, .net)'];
        }
    }
    
    return ['valid' => true];
}

function validatePhilippinePhone($phone) {
    // Phone is optional, but if provided must be valid
    if (empty($phone)) {
        return ['valid' => true]; // Optional field
    }
    
    // Remove common separators (spaces, dashes, parentheses)
    $phone_clean = preg_replace('/[\s\-\(\)]/', '', $phone);
    
    // Philippine phone formats:
    // 09XXXXXXXXX (11 digits - most common)
    // 639XXXXXXXXX (12 digits - with country code)
    // +639XXXXXXXXX (13 characters - international format)
    
    $valid_patterns = [
        '/^09\d{9}$/',           // 09123456789
        '/^639\d{9}$/',          // 639123456789
        '/^\+639\d{9}$/'         // +639123456789
    ];
    
    $is_valid = false;
    foreach ($valid_patterns as $pattern) {
        if (preg_match($pattern, $phone_clean)) {
            $is_valid = true;
            break;
        }
    }
    
    if (!$is_valid) {
        return ['valid' => false, 'msg' => 'Invalid Philippine phone number. Use format: 09XXXXXXXXX'];
    }
    
    return ['valid' => true];
}

// ========================================
// PERFORM VALIDATIONS
// ========================================

// Validate full name
$name_validation = validateFullName($full_name);
if (!$name_validation['valid']) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => $name_validation['msg']]));
}

// Validate email
$email_validation = validateEmail($email);
if (!$email_validation['valid']) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => $email_validation['msg']]));
}

// Validate phone
$phone_validation = validatePhilippinePhone($phone);
if (!$phone_validation['valid']) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => $phone_validation['msg']]));
}

// ========================================
// SANITIZE INPUTS (Security)
// ========================================

// Sanitize name (capitalize properly)
$full_name = ucwords(strtolower($full_name));

// Sanitize email (lowercase)
$email = strtolower($email);

// Clean phone number (store in consistent format)
if (!empty($phone)) {
    $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
}

// ========================================
// INSERT INTO DATABASE
// ========================================

try {
    // Generate unique QR string
    $qr_code = bin2hex(random_bytes(8)); // 16 character hex string
    
    // Set expiry time - 1 hour from now
    $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour
    
    // Debug logging
    error_log("Registration - Name: $full_name, Email: $email, Phone: $phone");
    error_log("Current time: " . date('Y-m-d H:i:s'));
    error_log("Expiry time: " . $expiry);
    
    // Prepare statement
    $stmt = $mysqli->prepare(
        "INSERT INTO visitors(full_name, email, phone, purpose, host, notes, qr_code, expiry_at)
         VALUES(?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    // Bind parameters
    $stmt->bind_param('ssssssss', $full_name, $email, $phone, $purpose, $host, $notes, $qr_code, $expiry);
    
    // Execute
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    // Success response
    echo json_encode([
        'ok' => true,
        'qr_code' => $qr_code,
        'expiry_at' => $expiry,
        'visitor_id' => $mysqli->insert_id,
        'visitor_name' => $full_name,
        'current_time' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false, 
        'msg' => 'Database error: ' . $e->getMessage()
    ]);
}
?>