
<?php
require_once __DIR__ . '/../vendor/autoload.php';  // dompdf autoloader
use Dompdf\Dompdf;
// dummy data

$ehrData = [
    "patient" => [
        "name" => "Michael Rodriguez",
        "dob" => "1975-04-22",
        "sex" => "Male",
        "mrn" => "00291837"
    ],
    "encounter" => [
        "date" => "2025-05-01",
        "type" => "Outpatient",
        "clinician" => "Dr. Aisha Patel"
    ],
    "chief_complaint" => "Chronic lower back pain",
    "history_of_present_illness" => "Pain for 3 months, worse when sitting, no trauma, no numbness/tingling.",
    "past_medical_history" => [
        "Hypertension",
        "Hyperlipidemia"
    ],
    "medications" => [
        [ "name" => "Amlodipine", "dose" => "5mg daily" ],
        [ "name" => "Atorvastatin", "dose" => "20mg daily" ]
    ],
    "allergies" => [],
    "vitals" => [
        "bp" => "132/84",
        "hr" => 78,
        "temp" => "36.7C",
        "spo2" => "98%"
    ],
    "exam" => [
        "musculoskeletal" => "Tenderness over L4-L5, limited forward flexion"
    ],
    "assessment" => [
        "Chronic mechanical low back pain"
    ],
    "plan" => [
        "Physiotherapy referral",
        "Core strengthening exercises",
        "NSAIDs PRN",
        "Follow up in 4 weeks"
    ]
];


require_once __DIR__ . '/../prompts/systemInstructions.php';
require_once __DIR__ . '/../config/db_config.php';


$input = json_decode(file_get_contents('php://input'), true);
$prompt = $input['prompt'] ?? '';
// $fileData = $input['file'] ?? null;


// if ($fileData) {
//     $fileName = $fileData['name'] ?? 'file.pdf';
//     $fileBase64 = $fileData['data'] ?? '';
//     // Remove the "data:application/pdf;base64," prefix if present
//     $fileBase64 = preg_replace('#^data:application/\w+;base64,#i', '', $fileBase64);

//     // Decode and save the file
//     $decoded = base64_decode($fileBase64);
//     $uploadDir = __DIR__ . '/uploads/ehr/';
//     if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

//     $filePath = $uploadDir . $fileName;
//     file_put_contents($filePath, $decoded);
//     error_log("File saved to: $filePath");
// }


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
require_once __DIR__ . '/../vendor/autoload.php';  // dompdf autoloader

require_once __DIR__ . '/../prompts/systemInstructions.php';

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

    // ------------------------------------------------
    // ✨ CREATE PDF USING DOMPDF
    // ------------------------------------------------

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

    $reportDir = __DIR__ . '/../reports/';
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
