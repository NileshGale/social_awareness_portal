<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

$email = 'dhanashreegame@gmail.com';

$stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode($result->fetch_assoc());
} else {
    echo "User not found";
}
?>
