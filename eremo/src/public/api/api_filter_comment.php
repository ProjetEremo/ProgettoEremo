<?php
// File: api/api_filter_comment.php
session_start();
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => '', 'offensiveness_score' => null];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo non consentito.';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

$inputData = json_decode(file_get_contents('php://input'), true);
$commentText = isset($inputData['commentText']) ? trim($inputData['commentText']) : '';

if (empty($commentText)) {
    $response['message'] = 'Testo del commento mancante.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

// Configurazione Groq
$GROQ_API_KEY = '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';
$GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';

try {
    // Preparazione prompt per Groq
    $messages = [
        [
            "role" => "system",
            "content" => "Ciao sei un ai che si occupa di analizzare i commenti di un sito di una fraternità. Riceverai come input un commento e dovrai dare come output un numero da 1 a 10 (1 per nulla offensivo), 10 (ultra offensivo). Dovrai rispondere solo con il numero e nient'altro. Commento:"
        ],
        [
            "role" => "user",
            "content" => $commentText
        ]
    ];

    // Chiamata API a Groq
    $ch = curl_init($GROQ_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "messages" => $messages,
        "model" => "gemma2-9b-it",
        "temperature" => 1,
        "max_tokens" => 1, // Limitiamo a 1 token per ottenere solo il numero
        "stop" => ["\n"]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $GROQ_API_KEY,
        'Content-Type: application/json'
    ]);

    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Errore nella chiamata API Groq: HTTP $httpCode");
    }

    $responseData = json_decode($apiResponse, true);
    $scoreText = $responseData['choices'][0]['message']['content'] ?? '';
    $score = intval(trim($scoreText));

    if ($score < 1 || $score > 10) {
        throw new Exception("Punteggio non valido ricevuto: $score");
    }

    $response['success'] = true;
    $response['offensiveness_score'] = $score;
    $response['message'] = 'Commento analizzato con successo.';
    
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Errore in api_filter_comment.php: " . $e->getMessage());
    $response['message'] = 'Errore durante l\'analisi del commento: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}
?>