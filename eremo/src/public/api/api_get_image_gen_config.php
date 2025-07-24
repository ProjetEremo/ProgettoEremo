<?php
session_start();
header('Content-Type: application/json');

// Impostazioni per il debug su AlterVista
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);    
// ini_set('error_log', '/membri/eremofratefrancesco/php_errors.log'); // Verifica se AlterVista permette di specificare un log custom

$response = ['success' => false, 'message' => 'Errore di inizializzazione script configurazione AI.'];

try {
    // === CONFIGURAZIONE DATABASE ===
    $servername = "localhost";
    $username = "eremofratefrancesco"; // SOSTITUISCI CON IL TUO USERNAME DATABASE REALE
    $password = ""; // SOSTITUISCI CON LA TUA PASSWORD DATABASE REALE
    $dbname = "my_eremofratefrancesco";
    // === FINE CONFIGURAZIONE DATABASE ===

    // Costanti per chiarezza
    $stability_service_name = 'stability_ai'; // Variabile invece di costante per bind_param
    $obfuscation_page_name = 'system_config'; 
    $obfuscation_content_key = 'groq_obfuscation_key';

    function simpleXorEncryptServer($text, $key) {
        $outText = '';
        if (empty($key)) {
            error_log("CHIAVE DI OFFUSCAMENTO VUOTA in simpleXorEncryptServer. Controlla la tabella pagina_contenuti per pagina_nome='" . $GLOBALS['obfuscation_page_name'] . "' e chiave_contenuto='" . $GLOBALS['obfuscation_content_key'] . "'.");
            throw new Exception("Configurazione interna errata: chiave di offuscamento mancante.");
        }
        for($i = 0; $i < strlen($text); $i++) {
            $outText .= chr(ord($text[$i]) ^ ord($key[$i % strlen($key)]));
        }
        return base64_encode($outText);
    }

    if (!isset($_SESSION['user_email'])) {
        $response['message'] = 'Autenticazione richiesta.';
        echo json_encode($response); 
        exit;
    }

    @$conn = new mysqli($servername, $username, $password, $dbname); 
    if ($conn->connect_error) {
        error_log("DB Connection failed for image_gen_config: " . $conn->connect_error . " (User: " . $username . ")");
        throw new Exception('Errore di connessione al database. Verifica le credenziali e la configurazione del server.');
    }
    $conn->set_charset("utf8mb4");

    // 1. Recupera la chiave API di Stability AI
    $stmt_api_key = $conn->prepare("SELECT api_key_value FROM api_keys WHERE service_name = ?");
    if (!$stmt_api_key) {
        throw new Exception("Errore SQL (prepare api_key): (" . $conn->errno . ") " . $conn->error);
    }
    // Utilizza la variabile $stability_service_name qui
    $stmt_api_key->bind_param("s", $stability_service_name); 
    if(!$stmt_api_key->execute()){
        throw new Exception("Errore SQL (execute api_key): " . $stmt_api_key->error);
    }
    $result_api_key = $stmt_api_key->get_result();
    $stability_api_key_value = null;
    if ($row_api_key = $result_api_key->fetch_assoc()) {
        $stability_api_key_value = $row_api_key['api_key_value'];
    }
    $stmt_api_key->close();

    if (!$stability_api_key_value) {
        throw new Exception('Chiave API per "' . $stability_service_name . '" non trovata nel database.');
    }

    // 2. Recupera la chiave di offuscamento/decifratura
    $stmt_obf_key = $conn->prepare("SELECT valore_contenuto FROM pagina_contenuti WHERE pagina_nome = ? AND chiave_contenuto = ?");
    if (!$stmt_obf_key) {
        throw new Exception("Errore SQL (prepare obf_key): (" . $conn->errno . ") " . $conn->error);
    }
    // Utilizza le variabili $obfuscation_page_name e $obfuscation_content_key qui
    $stmt_obf_key->bind_param("ss", $obfuscation_page_name, $obfuscation_content_key); 
    if(!$stmt_obf_key->execute()){
        throw new Exception("Errore SQL (execute obf_key): " . $stmt_obf_key->error);
    }
    $result_obf_key = $stmt_obf_key->get_result();
    $decryption_key_value = null; 
    if ($row_obf_key = $result_obf_key->fetch_assoc()) {
        $decryption_key_value = $row_obf_key['valore_contenuto'];
    }
    $stmt_obf_key->close();
    $conn->close(); 

    if (!$decryption_key_value) {
        throw new Exception('Chiave di offuscamento ("' . $obfuscation_content_key . '") non trovata per pagina_nome="'. $obfuscation_page_name .'".');
    }
    
    $obfuscated_stability_api_key = simpleXorEncryptServer($stability_api_key_value, $decryption_key_value);

    $response = [
        'success' => true,
        'obfuscatedStabilityApiKey' => $obfuscated_stability_api_key,
        'decryptionKey' => $decryption_key_value 
    ];

} catch (Exception $e) {
    error_log("Exception in api_get_image_gen_config.php: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    $clean_message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $response['success'] = false; 
    $response['message'] = "Errore interno del server durante la configurazione AI: " . $clean_message;
}

echo json_encode($response);
exit;
?>