<?php
// File: api/get_groq_config.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// !!! ATTENZIONE ALLA SICUREZZA !!!
// Questo approccio è intrinsecamente insicuro perché la chiave API
// sarà ricostruita e utilizzata nel client.

$config_db = [ // Assicurati che queste credenziali siano corrette
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco', // Dal tuo file .sql
    'user' => 'eremofratefrancesco',    // Dal tuo file .sql
    'pass' => '' // <<< INSERISCI QUI LA TUA PASSWORD DEL DATABASE REALE
];

$response = ['success' => false, 'obfuscatedApiKey' => null, 'decryptionKey' => null, 'message' => ''];

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

    $stmtKey = $conn->prepare("SELECT valore_contenuto FROM pagina_contenuti WHERE pagina_nome = 'system_config' AND chiave_contenuto = 'groq_api_key_obfuscated'");
    $stmtKey->execute();
    $keyRow = $stmtKey->fetch();

    $stmtXor = $conn->prepare("SELECT valore_contenuto FROM pagina_contenuti WHERE pagina_nome = 'system_config' AND chiave_contenuto = 'groq_obfuscation_key'");
    $stmtXor->execute();
    $xorRow = $stmtXor->fetch();

    if ($keyRow && isset($keyRow['valore_contenuto']) && $xorRow && isset($xorRow['valore_contenuto'])) {
        $response['success'] = true;
        $response['obfuscatedApiKey'] = $keyRow['valore_contenuto'];
        $response['decryptionKey'] = $xorRow['valore_contenuto'];
    } else {
        $response['message'] = 'Configurazione API per la moderazione non trovata nel database.';
        http_response_code(500);
    }

} catch (PDOException $e) {
    error_log("Errore PDO in get_groq_config.php: " . $e->getMessage());
    $response['message'] = 'Errore database durante il recupero della configurazione API.';
    http_response_code(500);
} catch (Exception $e) {
    error_log("Errore generico in get_groq_config.php: " . $e->getMessage());
    $response['message'] = 'Errore imprevisto durante il recupero della configurazione API.';
    http_response_code(500);
}

echo json_encode($response);
$conn = null;
?>