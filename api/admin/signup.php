<?php

require_once __DIR__ . '/../../config/db_config.php';

// Read JSON body
$data = json_decode(file_get_contents("php://input"), true);

if (
    !isset($data['username']) ||
    !isset($data['password']) ||
    !isset($data['confirmPassword']) ||
    !isset($data['name']) ||
    !isset($data['lastName']) ||
    !isset($data['role'])
) {
    http_response_code(400);
    echo json_encode(["error" => "All fields are required"]);
    exit;
}

$username = $data['username'];
$password = $data['password'];
$confirmPassword = $data['confirmPassword'];
$name = $data['name'];
$lastName = $data['lastName'];
$role = $data['role'];

// Password match validation
if ($password !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(["error" => "Passwords do not match"]);
    exit;
}

$validRoles = ['clinician','researcher','admin'];
if (!in_array($role, $validRoles)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid role"]);
    exit;
}

// Hash the password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Insert user (now role is dynamic)
$stmt = $conn->prepare("
    INSERT INTO Users (Username, Name, LastName, PasswordHash, Role)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->bind_param("sssss", $username, $name, $lastName, $hashedPassword, $role);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "User registered successfully",
        "user" => [
            "id" => $stmt->insert_id,
            "name" => $name,
            "lastName" => $lastName,
            "username" => $username,
            "role" => $role
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to register user"]);
}

$stmt->close();
$conn->close();
?>
