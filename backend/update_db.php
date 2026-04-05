<?php
require_once 'config.php';

echo "Starting Database Update...\n";

// 1. Ensure schedule_bookings has necessary columns
$columns_to_add = [
    'preferred_time' => "TIME NOT NULL AFTER preferred_date",
    'meet_link'      => "VARCHAR(255) NULL AFTER status",
    'status'         => "ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending' AFTER preferred_time"
];

foreach ($columns_to_add as $col => $definition) {
    $check = $conn->query("SHOW COLUMNS FROM schedule_bookings LIKE '$col'");
    if ($check->num_rows === 0) {
        $q = "ALTER TABLE schedule_bookings ADD $definition";
        if ($conn->query($q)) {
            echo "Added column '$col' to schedule_bookings.\n";
        } else {
            echo "Error adding '$col': " . $conn->error . "\n";
        }
    } else {
        echo "Column '$col' already exists in schedule_bookings.\n";
    }
}

// 2. Create notifications table
$q_notifications = "CREATE TABLE IF NOT EXISTS notifications (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_noti_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($q_notifications)) {
    echo "Table 'notifications' checked/created successfully.\n";
} else {
    echo "Error creating 'notifications' table: " . $conn->error . "\n";
}

echo "Database Update Completed.\n";
?>
