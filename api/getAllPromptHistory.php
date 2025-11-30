<?php

require_once __DIR__ . '/../config/db_config.php';

// Fetch all prompt history in chronological order (newest first)
$sql = "SELECT * FROM Prompt_History ORDER BY CreatedAt DESC";
$result = $conn->query($sql);

$history = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        // Build full URL for the report file
        $reportUrl = !empty($row['LLMReportPath'])
            ? $row['LLMReportPath']
            : null;

        $history[] = [
            "PromptID"      => $row["PromptID"],
            "UserID"        => $row["UserID"],
            "PromptText"    => $row["PromptText"],
            "LLMReportPath" => $reportUrl,
            "CreatedAt"     => $row["CreatedAt"]
        ];
    }
}

echo json_encode([
    "success" => true,
    "data" => $history
]);

$conn->close();
