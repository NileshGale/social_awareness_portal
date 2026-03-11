<?php
require_once 'config.php';

$email = 'dhanashreegame@gmail.com';
$password = '@Dhanu#05';

$stmt = $conn->prepare("SELECT id, email, password, full_name, is_admin FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "User found: \n";
    print_r($user);
    if (password_verify($password, $user['password'])) {
        echo "Password verified successfully!\n";
    } else {
        echo "Password verification failed!\n";
        echo "Provided password: $password\n";
        echo "Stored hash: " . $user['password'] . "\n";
        echo "Let us do a new hash generation to see what is wrong: " . password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]) . "\n";
    }
} else {
    echo "User not found with email: $email\n";
}

$stmt->close();
$conn->close();
?>
