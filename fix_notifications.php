<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'backend/api.php';

echo "<h2>Fixing Database: Creating Notifications Table</h2>";

$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id          INT          NOT NULL AUTO_INCREMENT,
    user_id     INT          NOT NULL,
    message     TEXT         NOT NULL,
    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_noti_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql)) {
    echo "<p style='color: green;'>✅ Success: 'notifications' table created/verified.</p>";
} else {
    echo "<p style='color: red;'>❌ Error: " . $conn->error . "</p>";
}

echo "<p>You can now delete this file and try confirming the appointment again.</p>";
?>
