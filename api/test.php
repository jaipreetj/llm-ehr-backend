<?php
require __DIR__ . '/../config/db_config.php';

if ($conn->connect_error) {
    echo "DB NOT CONNECTED: " . $conn->connect_error;
} else {
    echo "DB CONNECTED SUCCESSFULLY!";
}