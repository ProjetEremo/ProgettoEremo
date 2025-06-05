<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config_session.php'; // PRIMA COSA
require_login(); // Verifica se l'utente è loggato
require_once 'config/db_config.php'; // Adatta il percorso se necessario

$response = ['success' => false, 'message' => ''];

if (!$conn || $conn->connect_errno) {
    $response['message'] = 'Errore di connessione al database.';
    http_response_code(500);
    echo json_encode($response);
    exit;
}

// Verifica autenticazione Admin (CONTROLLA CHE LE CHIAVI SESSIONE SIANO CORRETTE)
// Assumo che login.php imposti $_SESSION['is_admin'] === true per gli admin
if (!isset($_SESSION['user_email']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403); // Forbidden
    $response['message'] = 'Accesso non autorizzato. Solo gli amministratori possono salvare i contenuti.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Metodo non consentito.';
    echo json_encode($response);
    exit;
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if (empty($input) || !isset($input['page_name']) || !isset($input['contents']) || !is_array($input['contents'])) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Dati mancanti o malformati per il salvataggio.';
    echo json_encode($response);
    exit;
}

$pageName = trim($input['page_name']);
$contentsToSave = $input['contents'];

if (empty($pageName)) {
    $response['message'] = 'Nome pagina non specificato per il salvataggio.';
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO pagina_contenuti (pagina_nome, chiave_contenuto, valore_contenuto) VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE valore_contenuto = VALUES(valore_contenuto)");
    if (!$stmt) {
        throw new Exception("Errore preparazione query (salvataggio): " . $conn->error);
    }

    $savedCount = 0;
    $updatedCount = 0;

    foreach ($contentsToSave as $key => $value) {
        $keyTrimmed = trim($key);
        // Assicurati che $value sia una stringa, anche se vuota.
        // Se il database accetta NULL per valore_contenuto e vuoi salvare stringhe vuote come NULL:
        // $valueToStore = ($value === '') ? null : $value;
        $valueToStore = strval($value); // Converte in stringa

        $stmt->bind_param("sss", $pageName, $keyTrimmed, $valueToStore);
        if ($stmt->execute()) {
            // affected_rows: 1 per INSERT, 2 per UPDATE se il valore cambia, 0 se il valore non cambia
            if ($stmt->affected_rows > 0) {
                 // Per distinguere tra INSERT e UPDATE, potresti fare una SELECT prima,
                 // ma ON DUPLICATE KEY UPDATE è più efficiente.
                 // Per semplicità, contiamo solo se qualcosa è stato "toccato".
                $savedCount++;
            }
        } else {
            error_log("Errore esecuzione salvataggio per chiave $keyTrimmed: " . $stmt->error);
            // Considera se vuoi interrompere o continuare con gli altri.
            // Per ora, continuiamo.
        }
    }
    $stmt->close();
    $conn->commit();
    $response['success'] = true;
    $response['message'] = "Contenuti della pagina '" . htmlspecialchars($pageName) . "' salvati con successo. Record modificati/inseriti: " . $savedCount . ".";

} catch (Exception $e) {
    $conn->rollback();
    error_log("Errore in api_save_page_content.php: " . $e->getMessage());
    $response['message'] = 'Errore del server durante il salvataggio dei contenuti: ' . $e->getMessage();
    http_response_code(500);
}

$conn->close();
echo json_encode($response);
?>