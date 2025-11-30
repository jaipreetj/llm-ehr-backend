
<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use Dompdf\Dompdf;
use Smalot\PdfParser\Parser;

require_once __DIR__ . '/../../prompts/systemInstructions.php';
require_once __DIR__ . '/../../config/db_config.php';


$input = json_decode(file_get_contents('php://input'), true);
$prompt = $input['prompt'] ?? '';
$ehrId = $input['ehrId'] ?? '';

// get ehr data from db and parse it to obtain the json ehr json vlaue
$stmt = $conn->prepare("SELECT PDFPath FROM EHR_Inputs WHERE EHRID = ?");
$stmt->bind_param("i", $ehrId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "EHR not found"]);
    exit;
}

$row = $result->fetch_assoc();
$pdfPath = $row['PDFPath'];

if (!$pdfPath) {
    http_response_code(400);
    echo json_encode(["error" => "No PDFPath found for this EHR"]);
    exit;
}

$absolutePath = __DIR__ . '/../../' . ltrim($pdfPath, '/');

if (!file_exists($absolutePath)) {
    error_log("PASSSS 1");
    http_response_code(500);
    echo json_encode(["error" => "PDF file not found"]);
    exit;
}


$parser = new Parser();
$pdf = $parser->parseFile($absolutePath);

$text = $pdf->getText();
$clean = trim(preg_replace('/\s+/', ' ', $text));
$ehrData = json_decode($clean, true);

if ($ehrData === null) {
    error_log("JSON decode failed, cleaned text:");
    error_log($clean);

    http_response_code(500);
    echo json_encode(["error" => "Extracted PDF text is not valid JSON"]);
    exit;
}

// Call your LLM function with relevant literature
$literature = getRelevantLiterature($conn, $ehrData);

$litText = "";
foreach ($literature as $doc) {
    $litText .= "Title: " . $doc['Title'] . "\n";
    $litText .= "Report: " . $doc['ReportText'] . "\n";
    $litText .= "Source: " . $doc['Source'] . "\n\n";
}

$ehrJson = json_encode($ehrData, JSON_PRETTY_PRINT);
$augmentedPrompt = "
    You are a medical assistant AI. Use your general medical knowledge **and** the literature provided to answer the user prompt.

    --- Literature Context ---
    $litText

    --- EHR Data ---
    $ehrJson

    --- User Prompt ---
    $prompt
";

$result = callGeminiLLM($augmentedPrompt, $ehrJson);

$EHRID = $input['ehrId'] ?? 1;
$GeneratedBy = 1;

$stmt = $conn->prepare("
    INSERT INTO LLM_Reports (EHRID, GeneratedBy, PDFPath, Prompt)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("isss", $EHRID, $GeneratedBy, $result['file_url'], $prompt);
$stmt->execute();
$reportID = $stmt->insert_id;
$stmt->close();

// update prompt history as well
$stmt = $conn->prepare("
    INSERT INTO Prompt_History (PromptText, LLMReportPath)
    VALUES (?, ?)
");
$stmt->bind_param("ss", $augmentedPrompt, $result['file_url']);
$stmt->execute();
$reportID = $stmt->insert_id;
$stmt->close();


echo json_encode($result);

?>


<?php
require_once __DIR__ . '/../../vendor/autoload.php';  // dompdf autoloader

require_once __DIR__ . '/../../prompts/systemInstructions.php';

function getRelevantLiterature($conn, $ehrData, $maxResults = 5) {
    // first generate the keywords
    $keywords = [];

    if (!empty($ehrData['chief_complaint'])) {
        $keywords[] = $ehrData['chief_complaint'];
    }
    if (!empty($ehrData['allergies']) && is_array($ehrData['allergies'])) {
        $keywords = array_merge($keywords, $ehrData['allergies']);
    }
    if (!empty($ehrData['past_medical_history']) && is_array($ehrData['past_medical_history'])) {
        $keywords = array_merge($keywords, $ehrData['past_medical_history']);
    }
    if (!empty($ehrData['medications']) && is_array($ehrData['medications'])) {
        $medNames = array_map(fn($m) => $m['name'], $ehrData['medications']);
        $keywords = array_merge($keywords, $medNames);
    }

    $likeClauses = array_fill(0, count($keywords), "CONCAT_WS(' ', Title, ReportText) LIKE ?");
    $sql = "SELECT Title, ReportText, Source, PDFPath
        FROM Literature_DB
        WHERE " . implode(" OR ", $likeClauses);

    $stmt = $conn->prepare($sql);
    $types = str_repeat("s", count($keywords));
    $params = array_map(fn($k) => "%$k%", $keywords);
    $stmt->bind_param($types, ...$params);

    $stmt->execute();
    $result = $stmt->get_result();
    $literature = [];
    while ($row = $result->fetch_assoc()) {
        $literature[] = $row;
    }
    $stmt->close();
    return $literature;
}


function callGeminiLLM($prompt, $ehrJson = "", $apiKey = API_KEY, $model = 'gemini-2.5-flash', $system = null) {



    if ($system === null) {
        global $systemInstructions;
        $system = $systemInstructions;
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent";
    $data = [
        "contents" => [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $system],
                    ["text" => "User Prompt:\n$prompt"],
                    ["text" => "EHR Data:\n$ehrJson"]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return ['error' => curl_error($ch)];
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['error' => "HTTP Error: $httpCode"];
    }

    // Decode LLM response
    $decoded = json_decode($response, true);
    $reply = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? 'No reply received.';

    // HTML template for PDF
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset='utf-8'>
    <title>EHR Report</title>
    </head>
    <body>
    <h1>EHR Report</h1>
    <p><strong>Generated:</strong> " . date("Y-m-d H:i:s") . "</p>
    <hr>
    <pre style='font-size: 12px; font-family: monospace; white-space: pre-wrap;'>$reply</pre>
    </body>
    </html>
    ";

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $reportDir = __DIR__ . '/../../reports/';
    if (!is_dir($reportDir)) mkdir($reportDir, 0777, true);
    $filename = 'ehr_report_' . time() . '.pdf';
    $filePath = $reportDir . $filename;

    file_put_contents($filePath, $dompdf->output());

    // Public URL
    $publicUrl = BASE_SERVER_URL . "/reports/" . $filename;

    return [
        'reply' => $reply,
        'file_url' => $publicUrl,
        'file_path' => $filePath
    ];
}
?>
