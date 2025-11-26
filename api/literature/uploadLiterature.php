<?php
require_once __DIR__ . '/../../vendor/autoload.php';

if (!isset($_FILES["pdf"])) {
    echo json_encode(["success" => false, "error" => "No PDF uploaded"]);
    exit();
}

$pdf = $_FILES["pdf"];
$title = $_POST["title"] ?? $pdf["name"];

// Ensure upload folder exists
$uploadDir = __DIR__ . "/../../uploads/literature/";
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$targetPath = $uploadDir . basename($pdf["name"]);

if (!move_uploaded_file($pdf["tmp_name"], $targetPath)) {
    echo json_encode(["success" => false, "error" => "Failed to save file"]);
    exit();
}

// ----- extract text from PDF using Php lib -----
$parser = new \Smalot\PdfParser\Parser();
$uploadPdf = $parser->parseFile($targetPath);
$extractedText = $uploadPdf->getText();


// Save metadata to DB
require_once __DIR__ . '/../../config/db_config.php';

$stmt = $conn->prepare("
   INSERT INTO Literature_DB (Title, ReportText, Source, PDFPath)
    VALUES (?, ?, ?, ?)
");

$source = "uploaded_pdf";
$pdfPath = "/uploads/literature/" . basename($pdf["name"]);
$stmt->bind_param("ssss", $title, $extractedText, $source, $pdfPath);
$stmt->execute();

echo json_encode(["success" => true, "message" => "Uploaded successfully"]);
exit();

