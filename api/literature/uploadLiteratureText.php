<?php

require_once __DIR__ . '/../../config/db_config.php';


$title   = $_POST["title"]   ?? null;
$litSummary = $_POST["summary"] ?? null;


if (!$litSummary || trim($litSummary) === "") {
    echo json_encode(["success" => false, "error" => "Literature Summary needed"]);
    exit();
}

// removing whitespace
$litSummary = trim($litSummary);

if (mb_strlen($litSummary) > 2000) {
    echo json_encode(["success" => false, "error" => "Literature Summary over 2000 chars"]);
    exit();
}

// Default title if empty
if (!$title || trim($title) === "") {
    $title = mb_substr($litSummary, 0, 50);
    if (mb_strlen($litSummary) > 50) {
        $title .= "...";
    }
}

// no PDF uploaded for text summaries
$pdfPath = null;
$source = "researcher_text_summary";

// insert into db
$stmt = $conn->prepare("
    INSERT INTO Literature_DB (Title, ReportText, Source, PDFPath)
    VALUES (?, ?, ?, ?)
");

if (!$stmt) {
    echo json_encode(["success" => false, "error" => "DB error"]);
    exit();
}

$stmt->bind_param("ssss", $title, $litSummary, $source, $pdfPath);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "error" => "Failed to save literature summary to db"]);
    exit();
}

$stmt->close();

// successful
echo json_encode([
    "success" => true,
    "message" => "Success",
]);

exit();
?>
