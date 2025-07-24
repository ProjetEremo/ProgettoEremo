<?php
// File: /api/proxy_elevenlabs_tts.php
// error_reporting(E_ALL); // Per debug, rimuovere/commentare in produzione
// ini_set('display_errors', 0); // Per debug, rimuovere/commentare in produzione
// ini_set('log_errors', 1);
// $log_file = __DIR__ . '/proxy_debug.log'; 
// file_put_contents($log_file, "-------------------------\n", FILE_APPEND);
// file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Proxy ElevenLabs: Script avviato.\n", FILE_APPEND);

$elevenlabs_api_key = null;

$db_host = "localhost";
$db_name = "my_eremofratefrancesco";
$db_user = "eremofratefrancesco";
$db_pass = "";
$db_charset = "utf8mb4";

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    $sql_key_obf = "SELECT valore_contenuto FROM pagina_contenuti WHERE pagina_nome = 'system_config' AND chiave_contenuto = 'elevenlabs_api_key_obfuscated'";
    $stmt_key_obf = $pdo->query($sql_key_obf);
    $obfuscatedApiKey = $stmt_key_obf->fetchColumn();

    $sql_decrypt = "SELECT valore_contenuto FROM pagina_contenuti WHERE pagina_nome = 'system_config' AND chiave_contenuto = 'elevenlabs_obfuscation_key'";
    $stmt_decrypt = $pdo->query($sql_decrypt);
    $decryptionKey = $stmt_decrypt->fetchColumn();

    if ($obfuscatedApiKey && $decryptionKey) {
        function simpleXorDecryptPhp($base64String, $key) {
            $encryptedText = base64_decode($base64String);
            if ($encryptedText === false) return null;
            $outText = '';
            $keyLength = strlen($key);
            for ($i = 0; $i < strlen($encryptedText); $i++) {
                $outText .= $encryptedText[$i] ^ $key[$i % $keyLength];
            }
            return $outText;
        }
        $elevenlabs_api_key = simpleXorDecryptPhp($obfuscatedApiKey, $decryptionKey);

        if (!$elevenlabs_api_key) {
            // file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Proxy ElevenLabs: Fallimento decifratura API Key.\n", FILE_APPEND);
        } else {
            // $log_key_display = substr($elevenlabs_api_key, 0, 3) . "..." . substr($elevenlabs_api_key, -3);
            // file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Proxy ElevenLabs: API Key decifrata (parziale): " . $log_key_display . "\n", FILE_APPEND);
        }
    } else {
        // file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Proxy ElevenLabs: Chiave API offuscata o chiave di decifrazione non trovate nel DB.\n", FILE_APPEND);
    }
} catch (PDOException $e) {
    // file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Proxy ElevenLabs: Errore Database: " . $e->getMessage() . "\n", FILE_APPEND);
}

if (!$elevenlabs_api_key) {
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(["error" => "API Key di ElevenLabs non configurata correttamente sul server (chiave mancante o decifratura fallita)."]);
    // file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Proxy ElevenLabs: Uscita a causa di API key mancante o decifratura fallita.\n", FILE_APPEND);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(["error" => "Metodo non permesso. Usare POST."]);
    // file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Proxy ElevenLabs: Metodo non POST.\n", FILE_APPEND);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$textToSpeak = isset($input['text']) ? trim($input['text']) : null;
$voiceId = isset($input['voice_id']) ? $input['voice_id'] : '21m00Tcm4TlvDq8ikWAM'; 

if (empty($textToSpeak)) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(["error" => "Testo mancante per la sintesi vocale."]);
    // file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Proxy ElevenLabs: Testo mancante.\n", FILE_APPEND);
    exit;
}

$url = "https://api.elevenlabs.io/v1/text-to-speech/" . $voiceId . "?optimize_streaming_latency=0";
$headers = [
    "Accept: audio/mpeg",
    "Content-Type: application/json",
    "xi-api-key: " . $elevenlabs_api_key
];
$data = [
    "text" => $textToSpeak,
    "model_id" => "eleven_multilingual_v2",
    "voice_settings" => [
        "stability" => 0.5,
        "similarity_boost" => 0.75,
        "style" => 0.0,
        "use_speaker_boost" => true
    ]
];

// file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Proxy ElevenLabs: Chiamata a ElevenLabs URL: " . $url . " con testo: " . substr($textToSpeak, 0, 50) . "...\n", FILE_APPEND);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);   

// ------------- OPZIONI PROXY (DA DECOMMENTARE E CONFIGURARE SE NECESSARIO) -------------
// Se il tuo hosting (Altervista) richiede un proxy per le connessioni cURL in uscita,
// decommenta e configura le seguenti righe.
// Dovrai ottenere i dettagli del proxy (host, porta, utente, password) dal tuo provider di hosting.

// define('CURL_PROXY_HOST', "proxy.example.com"); // Sostituisci con l'host del proxy
// define('CURL_PROXY_PORT', "8080"); // Sostituisci con la porta del proxy
// define('CURL_PROXY_USER', "proxyuser"); // Sostituisci con l'utente del proxy (se richiesto)
// define('CURL_PROXY_PASS', "proxypassword"); // Sostituisci con la password del proxy (se richiesta)

// if (defined('CURL_PROXY_HOST') && defined('CURL_PROXY_PORT')) {
//     curl_setopt($ch, CURLOPT_PROXY, CURL_PROXY_HOST . ":" . CURL_PROXY_PORT);
//     // Se il proxy richiede autenticazione:
//     // if (defined('CURL_PROXY_USER') && defined('CURL_PROXY_PASS')) {
//     //     curl_setopt($ch, CURLOPT_PROXYUSERPWD, CURL_PROXY_USER . ":" . CURL_PROXY_PASS);
//     // }
//     // Per alcuni proxy HTTPS, potrebbe essere necessario forzare il tipo di tunnel:
//     // curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1); 
//     // file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Proxy ElevenLabs: Utilizzo proxy: " . CURL_PROXY_HOST . ":" . CURL_PROXY_PORT . "\n", FILE_APPEND);
// }
// ------------------------------------------------------------------------------------


$response_body = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error_msg = curl_error($ch); 
$curl_error_no = curl_errno($ch);   

curl_close($ch);

if ($curl_error_no) { 
    header("HTTP/1.1 500 Internal Server Error"); 
    $detailed_error = "Errore cURL (n." . $curl_error_no . ") verso ElevenLabs: " . $curl_error_msg;
    echo json_encode(["error" => $detailed_error]);
    // file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Proxy ElevenLabs: " . $detailed_error . "\n", FILE_APPEND);
    exit;
}

if ($http_code == 200) {
    header("Content-Type: audio/mpeg");
    // file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Proxy ElevenLabs: Successo, invio audio. Lunghezza: " . strlen($response_body) . "\n", FILE_APPEND);
    echo $response_body;
} else {
    header("HTTP/1.1 " . $http_code); 
    $error_details_msg = "Errore da API ElevenLabs (Codice HTTP: $http_code)";
    $json_response_body = json_decode($response_body, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($json_response_body['detail'])) {
        if (is_array($json_response_body['detail']) && isset($json_response_body['detail']['message'])) {
            $error_details_msg .= ": " . $json_response_body['detail']['message'];
        } elseif (is_string($json_response_body['detail'])) {
            $error_details_msg .= ": " . $json_response_body['detail'];
        } else {
            $error_details_msg .= ". Dettagli: " . json_encode($json_response_body['detail']);
        }
    } else {
         $error_details_msg .= ". Risposta grezza: " . substr($response_body, 0, 200); 
    }
    echo json_encode(["error" => $error_details_msg, "elevenlabs_raw_response_preview" => substr($response_body, 0, 500)]);
    // file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "Proxy ElevenLabs: " . $error_details_msg . ". Risposta grezza: " . $response_body . "\n", FILE_APPEND);
}
exit;
?>
