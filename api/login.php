<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/db_config.php';

// Read JSON body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !isset($data['password']) || !isset($data['role'])) {
    http_response_code(400);
    echo json_encode(["error" => "Email, password and role are required"]);
    exit;
}

$email = $data['email'];
$password = $data['password'];
$roleInput = $data['role'];

// Query user from DB
$stmt = $conn->prepare("
    SELECT UserID, Username, Name, LastName, PasswordHash, Role
    FROM Users
    WHERE Username = ?
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid email or password"]);
    exit;
}

$user = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $user['PasswordHash'])) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid email or password"]);
    exit;
}

// Optional: verify role matches what frontend sent
if ($user['Role'] !== $roleInput) {
    http_response_code(403);
    echo json_encode(["error" => "User does not have this role"]);
    exit;
}

// Successful login
echo json_encode([
    "success" => true,
    "user" => [
        "id" => $user['UserID'],
        "username" => $user['Username'],
        "name" => $user['Name'],
        "lastName" => $user['LastName'],
        "role" => $user['Role']
    ]
]);

$stmt->close();
$conn->close();
?>
