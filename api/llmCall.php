
<?php

header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../prompts/systemInstructions.php';


$input = json_decode(file_get_contents('php://input'), true);
$prompt = $input['prompt'] ?? '';

$result = callGeminiLLM($prompt);

echo json_encode($result);
?>


<?php
require_once __DIR__ . '/../prompts/systemInstructions.php';

function callGeminiLLM($prompt, $apiKey = "AIzaSyCUQWTW5L3q8tKYdF4mwu9W39YNmlSWaBA", $model = 'gemini-2.5-flash', $system = null) {
    if ($system === null) {
        global $systemInstructions;
        $system = $systemInstructions;
    }
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent";
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $system],
                    ['text' => $prompt],
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

    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $reply = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? 'No reply received.';
        return ['reply' => $reply];
    } else {
        return ['error' => 'Invalid JSON response', 'raw' => $response];
    }
}

?>
