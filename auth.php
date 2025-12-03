<?php
// auth.php - Authentication System with Database
date_default_timezone_set('Asia/Manila');
session_start();
require __DIR__ . '/db.php';

function isLoggedIn() {
    return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function register($username, $email, $password) {
    global $mysqli;
    
    // Validate inputs
    if (strlen($username) < 3) {
        return ['success' => false, 'msg' => 'Username must be at least 3 characters'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'msg' => 'Invalid email format'];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'msg' => 'Password must be at least 6 characters'];
    }
    
    // Check if username exists
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['success' => false, 'msg' => 'Username already exists'];
    }
    
    // Check if email exists
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['success' => false, 'msg' => 'Email already registered'];
    }
    
    // Hash password and insert
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("INSERT INTO users(username, email, password) VALUES(?, ?, ?)");
    $stmt->bind_param('sss', $username, $email, $hashed_password);
    
    if ($stmt->execute()) {
        return ['success' => true, 'msg' => 'Registration successful!'];
    } else {
        return ['success' => false, 'msg' => 'Registration failed. Please try again.'];
    }
}

function login($username, $password) {
    global $mysqli;
    
    $stmt = $mysqli->prepare("SELECT user_id, username, email, password FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'msg' => 'Invalid username or password'];
    }
    
    $user = $result->fetch_assoc();
    
    if (password_verify($password, $user['password'])) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['login_time'] = time();
        
        return ['success' => true, 'msg' => 'Login successful'];
    } else {
        return ['success' => false, 'msg' => 'Invalid username or password'];
    }
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Handle logout request
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
}
?>