<?php
// File: /api/get_elevenlabs_config.php

// ======== DEBUGGING ========
// Scommenta le righe seguenti SOLO per il debug se hai problemi e Altervista lo permette.
// ATTENZIONE: Non lasciare 'display_errors' su '1' in un ambiente di produzione!
// error_reporting(E_ALL);
// ini_set('display_errors', 1); // Mostra errori PHP direttamente (per debug)
// ===========================

// Imposta l'header Content-Type all'inizio
if (!headers_sent()) {
    header('Content-Type: application/json');
}

// Funzione per inviare una risposta JSON di errore e terminare
function send_json_error($message, $http_code = 500, $details = null) {
    if (!headers_sent()) { // Assicurati che l'header sia JSON
        header('Content-Type: application/json');
    }
    http_response_code($http_code);
    $error_response = ['success' => false, 'message' => $message];
    if ($details !== null) {
        $error_response['details'] = $details;
    }
    echo json_encode($error_response);
    exit;
}

// Logga l'avvio dello script (se hai accesso ai log PHP del server)
error_log("get_elevenlabs_config.php: Script avviato.");

// Credenziali del database fornite dall'utente
$db_host = "localhost";
$db_name = "my_eremofratefrancesco";
$db_user = "eremofratefrancesco";
$db_pass = ""; // Password vuota come indicato
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
    error_log("get_elevenlabs_config.php: Connessione PDO al database riuscita.");
} catch (PDOException $e) {
    error_log("get_elevenlabs_config.php: ERRORE Connessione PDO: " . $e->getMessage());
    send_json_error(
        "Errore di connessione al database.",
        500,
        // Includi il messaggio di errore PDO solo se sei in un ambiente di debug sicuro,
        // altrimenti loggalo solo lato server.
        // (isset($_GET['debug_pdo']) ? $e->getMessage() : "Dettagli non disponibili") 
        "Controlla i log del server PHP per i dettagli dell'errore PDO."
    );
}

$response = ['success' => false, 'message' => 'Configurazione API ElevenLabs non inizializzata.'];

try {
    error_log("get_elevenlabs_config.php: Tentativo di recupero chiavi API dal DB.");

    $sql_key = "SELECT valore_contenuto FROM pagina_contenuti WHERE pagina_nome = 'system_config' AND chiave_contenuto = 'elevenlabs_api_key_obfuscated'";
    $stmt_key = $pdo->query($sql_key);
    $obfuscatedApiKey = $stmt_key->fetchColumn();

    if ($obfuscatedApiKey === false) {
        error_log("get_elevenlabs_config.php: Chiave 'elevenlabs_api_key_obfuscated' non trovata nel DB.");
    } else {
        error_log("get_elevenlabs_config.php: 'elevenlabs_api_key_obfuscated' recuperata.");
    }

    $sql_decrypt_key = "SELECT valore_contenuto FROM pagina_contenuti WHERE pagina_nome = 'system_config' AND chiave_contenuto = 'elevenlabs_obfuscation_key'";
    $stmt_decrypt_key = $pdo->query($sql_decrypt_key);
    $decryptionKey = $stmt_decrypt_key->fetchColumn();

    if ($decryptionKey === false) {
        error_log("get_elevenlabs_config.php: Chiave 'elevenlabs_obfuscation_key' non trovata nel DB.");
    } else {
        error_log("get_elevenlabs_config.php: 'elevenlabs_obfuscation_key' recuperata.");
    }

    if ($obfuscatedApiKey && $decryptionKey) {
        $response = [
            'success' => true,
            'obfuscatedElevenLabsApiKey' => $obfuscatedApiKey,
            'decryptionKeyElevenLabs' => $decryptionKey,
            'message' => 'Configurazione API ElevenLabs caricata con successo.'
        ];
        error_log("get_elevenlabs_config.php: Configurazione caricata con successo.");
    } else {
        $missing_items = [];
        if (!$obfuscatedApiKey) $missing_items[] = "'elevenlabs_api_key_obfuscated'";
        if (!$decryptionKey) $missing_items[] = "'elevenlabs_obfuscation_key'";
        $response['message'] = 'Dati API ElevenLabs mancanti nel database: assicurati che le righe per ' . implode(' e ', $missing_items) . ' esistano in pagina_contenuti con pagina_nome = system_config.';
        error_log("get_elevenlabs_config.php: Dati API mancanti: " . implode(' e ', $missing_items));
    }

} catch (PDOException $e) { // Errore durante le query
    error_log("get_elevenlabs_config.php: ERRORE PDO durante recupero chiavi: " . $e->getMessage());
    send_json_error(
        "Errore database durante il recupero della configurazione ElevenLabs.",
        500,
        "Controlla i log del server PHP per i dettagli dell'errore PDO."
    );
} catch (Exception $e) { // Altri errori generici
    error_log("get_elevenlabs_config.php: ERRORE Generico: " . $e->getMessage());
    send_json_error(
        "Errore generico durante il recupero della configurazione ElevenLabs.",
        500,
        "Controlla i log del server PHP per i dettagli."
    );
}

// Invia la risposta JSON finale
if (!headers_sent()) { // Assicurati che l'header sia JSON
    header('Content-Type: application/json');
}
echo json_encode($response);
error_log("get_elevenlabs_config.php: Risposta inviata: " . json_encode($response));
exit;
?>
