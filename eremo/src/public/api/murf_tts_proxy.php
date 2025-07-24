<?php
// File: /api/murf_tts_proxy.php
// Proxy per le chiamate all'API Text-to-Speech di Murf AI

// --- INIZIO CONFIGURAZIONE DATABASE ---
// SOSTITUISCI CON LE TUE CREDENZIALI REALI DEL DATABASE ALTERVISTA
$servername = "localhost"; // Di solito è localhost per Altervista
$username = "eremofratefrancesco"; // Il tuo username database Altervista
$password = "TUA_PASSWORD_DATABASE"; // LA TUA PASSWORD DATABASE
$dbname = "my_eremofratefrancesco"; // Il nome del tuo database
// --- FINE CONFIGURAZIONE DATABASE ---

// Chiave API Murf AI - Recuperala in modo sicuro dal database
$murfApiKey = null;
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => "Proxy TTS: Connessione al database fallita: " . $conn->connect_error]);
    exit;
}
$conn->set_charset("utf8mb4");

$sqlMurfKey = "SELECT api_key_value FROM api_keys WHERE service_name = 'murph' LIMIT 1";
$resultMurfKey = $conn->query($sqlMurfKey);

if ($resultMurfKey && $resultMurfKey->num_rows > 0) {
    $row = $resultMurfKey->fetch_assoc();
    $murfApiKey = $row['api_key_value'];
}
$conn->close();

if (empty($murfApiKey)) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => "Proxy TTS: Chiave API Murf non configurata sul server."]);
    exit;
}

// Verifica che la richiesta sia POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Proxy TTS: Metodo non consentito. Usare POST.']);
    exit;
}

// Recupera i dati inviati dal JavaScript (da FormData)
$textToSynthesize = isset($_POST['text']) ? trim($_POST['text']) : null;
$voiceId = isset($_POST['voice']) ? trim($_POST['voice']) : null;
$voiceStyle = isset($_POST['style']) ? trim($_POST['style']) : 'Conversational'; // Default style

if (empty($textToSynthesize) || empty($voiceId)) {
    header('Content-Type: application/json');
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Proxy TTS: Parametri "text" e "voice" mancanti.']);
    exit;
}

// Prepara la chiamata all'API di Murf AI
$murfApiUrl = 'https://api.murf.ai/v1/speech';
$payload = json_encode([
    'text' => $textToSynthesize,
    'voice' => $voiceId,
    'style' => $voiceStyle,
    'format' => 'mp3', // Puoi rendere configurabile anche questo se necessario
    'quality' => 'medium' // Puoi rendere configurabile anche questo se necessario
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $murfApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'api-key: ' . $murfApiKey // La chiave API reale
]);
// Per il debug su Altervista, potresti aver bisogno di specificare opzioni SSL
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Metti a true in produzione se hai certificati validi
// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);   // Metti a 2 in produzione

$responseBody = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Proxy TTS: Errore cURL chiamando Murf AI: ' . $curlError]);
    exit;
}

if ($httpcode == 200) {
    // Inoltra la risposta audio (blob) al client
    header('Content-Type: audio/mpeg'); // O il tipo corretto restituito da Murf (es. audio/wav)
    // header('Content-Length: ' . strlen($responseBody)); // Opzionale, ma buona pratica
    echo $responseBody;
} else {
    // Errore dall'API Murf AI
    header('Content-Type: application/json');
    http_response_code($httpcode); // Inoltra il codice di errore di Murf
    // Tenta di parsare la risposta di errore di Murf se è JSON
    $errorDetails = json_decode($responseBody, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($errorDetails['message'])) {
        echo json_encode(['error' => 'Proxy TTS: Errore da Murf AI API: ' . $errorDetails['message'], 'details' => $errorDetails]);
    } else {
        echo json_encode(['error' => 'Proxy TTS: Errore da Murf AI API (codice ' . $httpcode . '). Risposta: ' . substr($responseBody, 0, 200)]);
    }
}
?>
