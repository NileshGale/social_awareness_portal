<?php
// fix_admin.php - Diagnostic + fix script for admin login issue
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

$email    = 'dhanashreegame@gmail.com';
$password = '@Dhanu#05';

echo "<h2>Admin Fix Tool</h2>";

// Step 1: Show all rows with this email
$stmt = $conn->prepare("SELECT id, full_name, email, is_admin, LENGTH(password) as hash_len, LEFT(password, 7) as hash_start FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Step 1: Rows found with email '$email'</h3>";
if ($result->num_rows === 0) {
    echo "<p style='color:red'>No rows found! Will insert a new admin user.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Full Name</th><th>is_admin</th><th>Hash Length</th><th>Hash Start</th></tr>";
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = $row['id'];
        echo "<tr><td>{$row['id']}</td><td>{$row['full_name']}</td><td>{$row['is_admin']}</td><td>{$row['hash_len']}</td><td>{$row['hash_start']}</td></tr>";
    }
    echo "</table>";
}
$stmt->close();

// Step 2: Delete all but keep one, then update with fresh hash
echo "<h3>Step 2: Fixing password hash</h3>";
$newHash = password_hash($password, PASSWORD_BCRYPT);
echo "<p>New hash generated: <code>" . substr($newHash, 0, 20) . "...</code></p>";

// Check if verify works locally first
if (password_verify($password, $newHash)) {
    echo "<p style='color:green'>✅ Hash verification test passed</p>";
} else {
    echo "<p style='color:red'>❌ Hash verification test failed! PHP issue.</p>";
}

// Step 3: Delete duplicates if any, keep only one
$stmt = $conn->prepare("SELECT id FROM users WHERE email=? ORDER BY id ASC");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$allIds = [];
while ($row = $result->fetch_assoc()) { $allIds[] = $row['id']; }
$stmt->close();

if (count($allIds) > 1) {
    // Keep the first, delete the rest
    $keepId = $allIds[0];
    for ($i = 1; $i < count($allIds); $i++) {
        $delId = $allIds[$i];
        $conn->query("DELETE FROM users WHERE id=$delId");
    }
    echo "<p style='color:orange'>⚠ Deleted " . (count($allIds)-1) . " duplicate row(s). Keeping ID: $keepId</p>";
}

// Step 4: Update or insert
$stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

if ($res->num_rows > 0) {
    $row  = $res->fetch_assoc();
    $id   = $row['id'];
    $stmt = $conn->prepare("UPDATE users SET password=?, is_admin=1, full_name='Dhanashree Game', mobile='1111100000', gender='Female' WHERE id=?");
    $stmt->bind_param("si", $newHash, $id);
    if ($stmt->execute()) {
        echo "<p style='color:green; font-weight:bold;'>✅ Admin user updated! ID=$id</p>";
    } else {
        echo "<p style='color:red'>❌ Update failed: " . $conn->error . "</p>";
    }
    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, mobile, gender, is_admin, password, created_at) VALUES ('Dhanashree Game', ?, '1111100000', 'Female', 1, ?, NOW())");
    $stmt->bind_param("ss", $email, $newHash);
    if ($stmt->execute()) {
        echo "<p style='color:green; font-weight:bold;'>✅ Admin user inserted!</p>";
    } else {
        echo "<p style='color:red'>❌ Insert failed: " . $conn->error . "</p>";
    }
    $stmt->close();
}

// Step 5: Final verification — simulate the login check
echo "<h3>Step 3: Final Login Simulation</h3>";
$stmt = $conn->prepare("SELECT password, is_admin FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
$userRow = $res->fetch_assoc();
$stmt->close();
$conn->close();

if ($userRow) {
    $verify = password_verify($password, $userRow['password']);
    echo "<p>is_admin: <strong>{$userRow['is_admin']}</strong></p>";
    if ($verify) {
        echo "<p style='color:green; font-weight:bold; font-size:1.2em;'>✅ Login simulation PASSED! You can now login successfully.</p>";
    } else {
        echo "<p style='color:red; font-weight:bold;'>❌ Login simulation FAILED. There is a deeper PHP bcrypt issue.</p>";
        echo "<p>Try upgrading XAMPP or check PHP version:</p>";
        echo "<pre>PHP: " . phpversion() . "</pre>";
    }
} else {
    echo "<p style='color:red'>Could not find user after update!</p>";
}

echo '<h3><a href="../frontend/login.html">→ Go to Login Page</a></h3>';
?>
