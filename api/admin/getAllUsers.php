<?php

require_once __DIR__ . '/../../config/db_config.php';

try {
    $sql = "SELECT UserID, Name, LastName, Username, Role FROM Users";
    $result = $conn->query($sql);

    $users = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    echo json_encode([
        "success" => true,
        "data" => $users
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn->connect_error === null) {
        $conn->close();
    }
}
