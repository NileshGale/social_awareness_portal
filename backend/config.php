<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'social_awareness_portal');

// Email configuration
define('SMTP_FROM_EMAIL', 'dhanashreegame@gmail.com');
define('SMTP_FROM_NAME', 'Social Awareness Portal');

// PHPMailer SMTP settings (Gmail App Password)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'dhanashreegame@gmail.com');
define('SMTP_PASSWORD', 'dfyq rpes bucw ilbw');

// Upload paths
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
define('PROFILE_UPLOAD_PATH', UPLOAD_PATH . 'profiles/');
define('INCIDENT_UPLOAD_PATH', UPLOAD_PATH . 'incidents/');

// Start session before everything
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create DB connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateOTP() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
?>