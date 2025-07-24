<?php
// File: api/get_api_keys.php
// OBIETTIVO: Sostituisce get_groq_config.php. Fornisce TUTTE le chiavi API necessarie
// al client in un unico, strutturato oggetto JSON.
session_start();
header('Content-Type: application/json; charset=utf-8');

// Inserisci qui i dati corretti per la connessione al tuo database
$config_db = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // <<< INSERISCI QUI LA TUA PASSWORD DEL DATABASE REALE
];

// Struttura della risposta JSON
$response = [
    'success' => false,
    'keys' => [
        'groq' => null,
        'playht' => null
    ],
    'message' => ''
];

if (empty($config_db['pass'])) {
    $response['message'] = 'Errore di configurazione lato server: password DB mancante.';
    http_response_code(500);
    echo json_encode($response);
    exit;
}

try {
    $conn = new PDO(
        "mysql:host={$config_db['host']};dbname={$config_db['db']};charset=utf8mb4",
        $config_db['user'],
        $config_db['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // Prepara una query per recuperare tutte le chiavi di configurazione del sistema
    $stmt = $conn->prepare("SELECT chiave_contenuto, valore_contenuto FROM pagina_contenuti WHERE pagina_nome = 'system_config'");
    $stmt->execute();
    $all_keys = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Crea un array associativo chiave => valore

    // Popola la sezione Groq
    if (isset($all_keys['groq_api_key_obfuscated']) && isset($all_keys['groq_obfuscation_key'])) {
        $response['keys']['groq'] = [
            'obfuscatedKey' => $all_keys['groq_api_key_obfuscated'],
            'decryptionKey' => $all_keys['groq_obfuscation_key']
        ];
    }

    // Popola la sezione Play.ht
    if (isset($all_keys['playht_api_key_obfuscated']) && isset($all_keys['playht_obfuscation_key']) && isset($all_keys['playht_user_id'])) {
        $response['keys']['playht'] = [
            'obfuscatedKey' => $all_keys['playht_api_key_obfuscated'],
            'decryptionKey' => $all_keys['playht_obfuscation_key'],
            'userId'        => $all_keys['playht_user_id']
        ];
    }
    
    // Controlla se almeno un set di chiavi è stato trovato
    if ($response['keys']['groq'] || $response['keys']['playht']) {
        $response['success'] = true;
    } else {
        $response['message'] = 'Nessuna configurazione API trovata nel database.';
        http_response_code(404);
    }

} catch (PDOException $e) {
    error_log("Errore PDO in get_api_keys.php: " . $e->getMessage());
    $response['message'] = 'Errore database durante il recupero della configurazione API.';
    http_response_code(500);
} catch (Exception $e) {
    error_log("Errore generico in get_api_keys.php: " . $e->getMessage());
    $response['message'] = 'Errore imprevisto durante il recupero della configurazione API.';
    http_response_code(500);
}

echo json_encode($response);
$conn = null;
?>
