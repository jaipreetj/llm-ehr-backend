<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use Dompdf\Dompdf;

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../prompts/systemInstructions2.php';

$input = json_decode(file_get_contents("php://input"), true);
$prompt = $input["prompt"] ?? "";
$docId = $input["docId"] ?? null;

if (!$prompt || !$docId) {
    echo json_encode([ "error" => "Missing prompt or docId" ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT Title, ReportText, Source, PDFPath
    FROM Literature_DB
    WHERE DocID = ?
");
$stmt->bind_param("i", $docId);
$stmt->execute();
$result = $stmt->get_result();
$doc = $result->fetch_assoc();
$stmt->close();

if (!$doc) {
    echo json_encode([ "error" => "Document not found" ]);
    exit;
}

// Extract the ReportText to feed to the LLM
$literatureText = $doc["ReportText"] ?? "";

$fullPrompt = "
You are a medical assistant AI. Use your general medical knowledge AND the following literature to answer the user prompt.

--- Literature (from DocID: $docId, Title: {$doc['Title']}) ---
$literatureText

--- User Prompt ---
$prompt
";


$response = callGeminiLLM_Literature($fullPrompt, $literatureText);
echo json_encode($response);
exit;

function callGeminiLLM_Literature($prompt, $literature, $apiKey = API_KEY, $model = 'gemini-2.5-flash') {
    global $systemInstructions;

    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent";
    $data = [
        "contents" => [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $systemInstructions],
                    ["text" => "Literature:\n$literature"],
                    ["text" => "User Prompt:\n$prompt"]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "x-goog-api-key: $apiKey"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return [ "error" => "HTTP Error: $httpCode", "response" => $response ];
    }

    $decoded = json_decode($response, true);
    $reply = $decoded["candidates"][0]["content"]["parts"][0]["text"] ?? "No reply received.";


    $html = "
        <html>
        <head><meta charset='utf-8'><title>Literature Report</title></head>
        <body>
            <h1>Literature-Based Report</h1>
            <p><strong>Generated:</strong> " . date("Y-m-d H:i:s") . "</p>
            <hr>
            <pre style='font-size: 12px; white-space: pre-wrap;'>$reply</pre>
        </body>
        </html>
    ";

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $reportDir = __DIR__ . '/../../reports/';
    if (!is_dir($reportDir)) mkdir($reportDir, 0777, true);

    $filename = "literature_report_" . time() . ".pdf";
    $filePath = $reportDir . $filename;
    file_put_contents($filePath, $dompdf->output());

    $publicUrl = BASE_SERVER_URL . "/reports/" . $filename;

    return [
        "reply" => $reply,
        "file_url" => $publicUrl,
        "file_path" => $filePath
    ];
}
?>
