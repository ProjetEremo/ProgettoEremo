<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config_session.php'; // PRIMA COSA
// require_login(); // Rimosso o commentato: Verifica se l'utente è loggato - QUESTO PERMETTE L'ACCESSO ANCHE AI NON LOGGATI
require_once 'config/db_config.php'; // Adatta il percorso se necessario


$response = ['success' => false, 'contents' => null, 'message' => ''];
$pageName = isset($_GET['page']) ? trim($_GET['page']) : 'index'; // Default a 'index'

// È buona norma verificare la connessione qui, anche se db_config.php potrebbe già farlo.
if (!$conn || $conn->connect_errno) {
    error_log("Errore di connessione al database in api_get_page_content.php: " . ($conn ? $conn->connect_error : "Variabile \$conn non inizializzata"));
    $response['message'] = 'Errore di connessione al database.';
    http_response_code(500); // Internal Server Error
    echo json_encode($response);
    exit;
}

if (empty($pageName)) {
    $response['message'] = 'Nome pagina non specificato.';
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit;
}

try {
    // Prepara la query per selezionare i contenuti della pagina specificata
    $stmt = $conn->prepare("SELECT chiave_contenuto, valore_contenuto FROM pagina_contenuti WHERE pagina_nome = ?");
    if (!$stmt) {
        // Se la preparazione della query fallisce, registra l'errore e invia una risposta di errore
        throw new Exception("Errore preparazione query: " . $conn->error);
    }

    // Associa il parametro pageName alla query SQL
    $stmt->bind_param("s", $pageName);
    // Esegui la query
    $stmt->execute();
    // Ottieni il risultato della query
    $result = $stmt->get_result();

    $contents = [];
    // Itera sui risultati e popola l'array dei contenuti
    while ($row = $result->fetch_assoc()) {
        $contents[$row['chiave_contenuto']] = $row['valore_contenuto'];
    }

    if (!empty($contents)) {
        // Se sono stati trovati contenuti, imposta la risposta di successo
        $response['success'] = true;
        $response['contents'] = $contents;
    } else {
        // Se non sono stati trovati contenuti specifici per la pagina,
        // la risposta è comunque un successo (la query è andata a buon fine),
        // ma l'array dei contenuti sarà vuoto.
        // Il JavaScript lato client gestirà il fallback ai contenuti predefiniti.
        $response['success'] = true;
        $response['contents'] = []; // Invia un array vuoto
        $response['message'] = 'Nessun contenuto personalizzato trovato per la pagina: ' . htmlspecialchars($pageName) . '. Verranno usati i default se disponibili lato client.';
    }
    // Chiudi lo statement
    $stmt->close();
} catch (Exception $e) {
    // In caso di eccezione, registra l'errore e imposta un messaggio di errore generico
    error_log("Errore in api_get_page_content.php: " . $e->getMessage());
    $response['message'] = 'Errore del server durante il recupero dei contenuti.';
    http_response_code(500); // Internal Server Error
}

// Chiudi la connessione al database
if ($conn) {
    $conn->close();
}

// Invia la risposta JSON
echo json_encode($response);
?>
