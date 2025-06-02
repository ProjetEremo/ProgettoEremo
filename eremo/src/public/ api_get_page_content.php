<?php
header('Content-Type: application/json; charset=utf-8');
require_login(); // Verifica se l'utente è loggato
require_once 'config/db_config.php'; // Adatta il percorso se necessario


$response = ['success' => false, 'contents' => null, 'message' => ''];
$pageName = isset($_GET['page']) ? trim($_GET['page']) : 'index'; // Default a 'index'

if (!$conn || $conn->connect_errno) {
    $response['message'] = 'Errore di connessione al database.';
    http_response_code(500);
    echo json_encode($response);
    exit;
}

if (empty($pageName)) {
    $response['message'] = 'Nome pagina non specificato.';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT chiave_contenuto, valore_contenuto FROM pagina_contenuti WHERE pagina_nome = ?");
    if (!$stmt) {
        throw new Exception("Errore preparazione query: " . $conn->error);
    }
    $stmt->bind_param("s", $pageName);
    $stmt->execute();
    $result = $stmt->get_result();

    $contents = [];
    while ($row = $result->fetch_assoc()) {
        $contents[$row['chiave_contenuto']] = $row['valore_contenuto'];
    }

    if (!empty($contents)) {
        $response['success'] = true;
        $response['contents'] = $contents;
    } else {
        // Se non trova contenuti specifici, potrebbe inviare un array vuoto
        // o un messaggio, o i default (ma i default sono gestiti dal JS come fallback)
        $response['success'] = true; // Successo nel recuperare, ma magari è vuoto
        $response['contents'] = []; // Invia un array vuoto
        $response['message'] = 'Nessun contenuto personalizzato trovato per la pagina: ' . htmlspecialchars($pageName) . '. Verranno usati i default.';
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Errore in api_get_page_content.php: " . $e->getMessage());
    $response['message'] = 'Errore del server durante il recupero dei contenuti.';
    http_response_code(500);
}

$conn->close();
echo json_encode($response);
?>