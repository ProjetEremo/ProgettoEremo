<?php
// File: /api/get_murf_config.php

// Header CORS - Adattare l'origine se necessario.
// Assicurati che l'URL qui corrisponda esattamente a come il tuo sito è servito (http o https, www o non-www)
header("Access-Control-Allow-Origin: https://eremofratefrancesco.altervista.org");
header("Access-Control-Allow-Methods: GET, OPTIONS"); // Solo GET è necessario per questo script
header("Access-Control-Allow-Headers: Content-Type"); // Header standard che il browser potrebbe inviare

// Gestione della richiesta OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Il browser sta chiedendo il permesso, rispondi positivamente.
    exit(0);
}

header('Content-Type: application/json');

// --- INIZIO CONFIGURAZIONE DATABASE ---
// SOSTITUISCI CON LE TUE CREDENZIALI REALI DEL DATABASE ALTERVISTA
$servername = "localhost"; // Di solito è localhost per Altervista
$username = "eremofratefrancesco"; // Il tuo username database Altervista
$password = "TUA_PASSWORD_DATABASE"; // LA TUA PASSWORD DATABASE
$dbname = "my_eremofratefrancesco"; // Il nome del tuo database
// --- FINE CONFIGURAZIONE DATABASE ---

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => "Connessione al database fallita: " . $conn->connect_error,
        'error_details' => [ // Aggiunto per debug
            'host' => $servername,
            'user' => $username,
            'db' => $dbname
        ]
    ]);
    exit;
}
$conn->set_charset("utf8mb4"); // Consigliato per compatibilità caratteri

$murfApiKey = null;
$decryptionKey = null; // Questa sarà la chiave usata per la cifratura/decifratura XOR

// 1. Recupera la chiave API Murf REALE dalla tabella api_keys
$sqlMurfKey = "SELECT api_key_value FROM api_keys WHERE service_name = 'murph' LIMIT 1"; // 'murph' come nel tuo SQL
$resultMurfKey = $conn->query($sqlMurfKey);

if ($resultMurfKey && $resultMurfKey->num_rows > 0) {
    $row = $resultMurfKey->fetch_assoc();
    $murfApiKey = $row['api_key_value'];
} else {
    echo json_encode([
        'success' => false,
        'message' => "Chiave API Murf non trovata nel database (service_name='murph'). Controlla la tabella 'api_keys'."
    ]);
    $conn->close();
    exit;
}

// 2. Recupera/Definisci la CHIAVE DI OFFUSCAMENTO
//    Nel tuo DB `pagina_contenuti`, hai 'elevenlabs_obfuscation_key'.
//    Assumiamo che tu voglia usare la stessa logica/chiave per Murf.
$sqlDecryptionKey = "SELECT valore_contenuto FROM pagina_contenuti WHERE pagina_nome = 'system_config' AND chiave_contenuto = 'elevenlabs_obfuscation_key' LIMIT 1";
$resultDecryptionKey = $conn->query($sqlDecryptionKey);

if ($resultDecryptionKey && $resultDecryptionKey->num_rows > 0) {
    $rowKey = $resultDecryptionKey->fetch_assoc();
    $decryptionKey = $rowKey['valore_contenuto'];
} else {
    // Fallback a una chiave fissa se non trovata nel DB, come nell'esempio precedente.
    // **IMPORTANTE**: Questa chiave DEVE corrispondere a quella che ti aspetti venga usata per la decifratura
    // e a quella usata per generare il valore 'elevenlabs_api_key_obfuscated' se lo stai riutilizzando.
    // Se hai inserito la chiave Murf *non offuscata* in `pagina_contenuti` per errore,
    // allora questa logica di offuscamento qui non è necessaria o va adattata.
    // Per ora, presumo che tu voglia offuscare la chiave Murf presa da `api_keys` usando una chiave di offuscamento.
    $decryptionKey = "PasswordEremo25!"; // CHIAVE DI OFFUSCAMENTO DI ESEMPIO
    // Se questa chiave non è corretta o non corrisponde a come hai generato i dati offuscati, la decifratura fallirà.
}


if (empty($murfApiKey) || empty($decryptionKey)) {
     echo json_encode([
        'success' => false,
        'message' => "Chiave API Murf o chiave di decifratura non configurata correttamente dopo il recupero dal DB."
    ]);
    $conn->close();
    exit;
}

// Funzione di cifratura XOR (per offuscare la chiave API Murf prima di inviarla al client)
function simpleXorEncryptForPHP($text, $key) {
    $outText = '';
    $keyLen = strlen($key);
    $textLen = strlen($text);
    for ($i = 0; $i < $textLen; $i++) {
        $outText .= $text[$i] ^ $key[$i % $keyLen];
    }
    return base64_encode($outText);
}

// Offusca la chiave API Murf (presa da api_keys) usando la chiave di decifratura/offuscamento
$obfuscatedApiKeyToSendToClient = simpleXorEncryptForPHP($murfApiKey, $decryptionKey);

echo json_encode([
    'success' => true,
    'obfuscatedMurfApiKey' => $obfuscatedApiKeyToSendToClient, // Questa è la chiave Murf offuscata
    'decryptionKeyMurf' => $decryptionKey                  // Questa è la chiave per DEcifrarla nel JS
]);

$conn->close();
?>
