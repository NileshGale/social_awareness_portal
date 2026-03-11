<?php
// setup_admin.php
// Place this in the backend/ folder and run it in the browser

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Admin User Setup</h2>";

$email = 'dhanashreegame@gmail.com';
$password = '@Dhanu#05';
// Generate the hash using the exact PHP version running on your web server
$hashed_password = password_hash($password, PASSWORD_BCRYPT);
$full_name = 'Dhanashree Game';
$mobile = '1111100000';
$gender = 'Female';
$is_admin = 1;

// 1. Check if user exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // 2. Update existing user
    $row = $result->fetch_assoc();
    $id = $row['id'];
    $stmt = $conn->prepare("UPDATE users SET full_name=?, mobile=?, gender=?, is_admin=?, password=? WHERE id=?");
    $stmt->bind_param("sssiis", $full_name, $mobile, $gender, $is_admin, $hashed_password, $id);
    if ($stmt->execute()) {
        echo "<p style='color: green; font-weight: bold;'>Successfully updated admin user: $email</p>";
        echo "<p>Password has been securely hashed and stored!</p>";
    } else {
        echo "<p style='color: red;'>Failed to update admin user: " . $conn->error . "</p>";
    }
} else {
    // 3. Insert new user
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, mobile, gender, is_admin, password, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssis", $full_name, $email, $mobile, $gender, $is_admin, $hashed_password);
    if ($stmt->execute()) {
        echo "<p style='color: green; font-weight: bold;'>Successfully inserted NEW admin user: $email</p>";
    } else {
        echo "<p style='color: red;'>Failed to insert admin user: " . $conn->error . "</p>";
    }
}

$stmt->close();
$conn->close();

echo '<h3><a href="../frontend/login.html">Click here to go to the Login Page</a></h3>';
?>
