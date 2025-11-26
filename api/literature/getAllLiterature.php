<?php

require_once __DIR__ . '/../../config/db_config.php';

// Fetch all literature
$sql = "SELECT * FROM Literature_DB ORDER BY CreatedAt DESC";
$result = $conn->query($sql);

$literature = [];


if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Construct PDF URL (assuming PDFs are in uploads/)
        $pdfUrl = !empty($row['PDFPath']) ? BASE_SERVER_URL . $row['PDFPath'] : null;
        $literature[] = [
            "DocID" => $row["DocID"],
            "Title" => $row["Title"],
            "Source" => $row["Source"],
            "ReportText" => $row["ReportText"],
            "PDFUrl" => $pdfUrl,
            "CreatedAt" => $row["CreatedAt"]
        ];
    }
}

echo json_encode(["success" => true, "data" => $literature]);

$conn->close();
