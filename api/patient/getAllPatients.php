<?php
require_once __DIR__ . '/../../config/db_config.php';

// Query: get all patient info + their EHR pdfurl
$sql = "
    SELECT
        p.PatientID,
        p.FirstName,
        p.LastName,
        p.Sex,
        e.EhrID,
        e.PDFPath
    FROM Patients p
    LEFT JOIN EHR_Inputs e ON p.PatientID = e.PatientID
";

$result = $conn->query($sql);


if (!$result) {
    echo json_encode([
        "success" => false,
        "message" => "DB Query Failed",
        "error" => $conn->error
    ]);
    exit();
}

$patients = [];

while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}

echo json_encode([
    "success" => true,
    "data" => $patients
]);

$conn->close();
