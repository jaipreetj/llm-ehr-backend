<?php
$servername = "localhost";
$username   = "root";
$password   = "root";      // MAMP default on Mac unless you changed it
$dbname     = "llm_ehr_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed");
}
?>
