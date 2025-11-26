<?php

require_once __DIR__ . '/../../config/db_config.php';

// ----------------------
// Validate required fields
// ----------------------
$firstName = $_POST["firstName"] ?? null;
$lastName = $_POST["lastName"] ?? null;
$dob = $_POST["dob"] ?? null;
$sex = $_POST["sex"] ?? null;

if (!$firstName || !$lastName || !$dob || !$sex) {
    echo json_encode(["success" => false, "error" => "Missing required fields"]);
    exit();
}

// Normally from auth session. For now:
// $uploadedBy = 1;

// ----------------------
// STEP 1 — Create Patient
// ----------------------
$stmt = $conn->prepare("
    INSERT INTO Patients (FirstName, LastName, DateOfBirth, Sex)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("ssss", $firstName, $lastName, $dob, $sex);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "error" => "Failed to create patient"]);
    exit();
}

$patientID = $stmt->insert_id; // ⭐ newly created patient
$stmt->close();

// ----------------------
// STEP 2 — Handle PDF upload
// ----------------------
$pdfPath = null;

if (isset($_FILES["pdf"])) {
    $pdf = $_FILES["pdf"];

    // Folder
    $uploadDir = __DIR__ . "/../../uploads/ehr/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Avoid name collisions
    $newName = "ehr_" . time() . "_" . basename($pdf["name"]);
    $targetPath = $uploadDir . $newName;

    if (!move_uploaded_file($pdf["tmp_name"], $targetPath)) {
        echo json_encode(["success" => false, "error" => "File upload failed"]);
        exit();
    }

    // Save relative path for DB
    $pdfPath = "/uploads/ehr/" . $newName;
}

// ----------------------
// STEP 3 — Create EHR_Inputs entry
// ----------------------
$stmt = $conn->prepare("
    INSERT INTO EHR_Inputs (PatientID, InputJSON, PDFPath)
    VALUES (?, ?, ?)
");

$emptyJson = json_encode(["note" => "initial EHR entry"]);
$stmt->bind_param("iss", $patientID, $emptyJson, $pdfPath);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "error" => "Unable to create EHR entry"]);
    exit();
}

$ehrID = $stmt->insert_id;
$stmt->close();

// ----------------------
// SUCCESS RESPONSE
// ----------------------
echo json_encode([
    "success" => true,
    "message" => "Patient + EHR created",
    "patientID" => $patientID,
    "ehrID" => $ehrID,
    "pdfPath" => $pdfPath
]);

exit();
?>
