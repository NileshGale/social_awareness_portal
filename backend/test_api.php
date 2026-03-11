<?php
$ch = curl_init('http://localhost/social_awareness/backend/api.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['action' => 'login', 'email' => 'dhanashreegame@gmail.com', 'password' => '@Dhanu#05']);
$response = curl_exec($ch);
file_put_contents('debug_response.txt', $response);
echo "Response saved to debug_response.txt\n";
?>
