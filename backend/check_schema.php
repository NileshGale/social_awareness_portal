<?php
require_once 'config.php';
$table = 'schedule_bookings';
$result = $conn->query("DESCRIBE $table");
$columns = [];
while($row = $result->fetch_assoc()) {
    $columns[] = $row;
}
echo json_encode($columns, JSON_PRETTY_PRINT);
?>
