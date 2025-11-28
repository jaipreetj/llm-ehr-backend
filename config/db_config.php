<?php
$servername = "localhost";
$username   = "root";
$password = 'root';
$dbname     = "llm_ehr_db";

$conn = new mysqli($servername, $username, $password, $dbname, 8889);

if ($conn->connect_error) {
    die("Database connection failed");
}

// other definitions
define('BASE_SERVER_URL', 'http://localhost:8888/llm-ehr-backend');
define('BASE_APP_URL', 'http://localhost:3000');
define('API_KEY', 'AIzaSyAxvwJHNi45lC1fK575FF-QTVWKZi8qbQw');

header("Access-Control-Allow-Origin: " . BASE_APP_URL);
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


?>
