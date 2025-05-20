<?php
header('Content-Type: application/json; charset=utf-8');

// Assicurati che il file di log per questo script sia configurato se necessario
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php_errors_get_event_details.log');


$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // LA TUA PASSWORD DB
];

$response = ['success' => false, 'data' => null, 'message' => ''];

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT) || (int)$_GET['id'] <= 0) {
    $response['message'] = 'ID Evento mancante o non valido.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}
$idEvento = (int)$_GET['id'];

try {
    $conn = new PDO(
        "mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // 1. Recupera i dettagli dell'evento
    $stmtDetails = $conn->prepare("SELECT * FROM eventi WHERE IDEvento = :idEvento");
    $stmtDetails->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    $stmtDetails->execute();
    $eventDetails = $stmtDetails->fetch();

    if (!$eventDetails) {
        $response['message'] = 'Evento non trovato.';
        http_response_code(404);
        echo json_encode($response);
        exit;
    }

    // 2. Recupera i media per l'evento
    $stmtMedia = $conn->prepare("SELECT Progressivo, Percorso, Descrizione FROM media WHERE IDEvento = :idEvento ORDER BY Progressivo ASC");
    $stmtMedia->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    $stmtMedia->execute();
    $mediaItems = $stmtMedia->fetchAll();

    // 3. Recupera i commenti con informazioni sull'utente (Nome, Cognome, Icona)
    $stmtComments = $conn->prepare(
        "SELECT
            c.Progressivo, c.Descrizione, c.DataPubb, c.CodRisposta, c.Contatto, c.IDEvento, c.NumLike,
            u.Nome AS CommenterNome,
            u.Cognome AS CommenterCognome,
            u.Icon AS CommenterIconPath
         FROM commenti c
         JOIN utentiregistrati u ON c.Contatto = u.Contatto
         WHERE c.IDEvento = :idEvento
         ORDER BY c.DataPubb ASC"
    );
    $stmtComments->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    $stmtComments->execute();
    $allCommentsRaw = $stmtComments->fetchAll();

    $commentsById = [];
    foreach ($allCommentsRaw as $comment) {
        if (isset($comment['DataPubb'])) {
            $comment['DataVisualizzata'] = date('d/m/Y H:i', strtotime($comment['DataPubb']));
        } else {
            $comment['DataVisualizzata'] = 'Data non disponibile';
        }
        $comment['NumLike'] = isset($comment['NumLike']) ? (int)$comment['NumLike'] : 0;
        // Inizializza il nome completo del commentatore
        $comment['CommenterNomeCompleto'] = trim(($comment['CommenterNome'] ?? '') . ' ' . ($comment['CommenterCognome'] ?? ''));
        if (empty($comment['CommenterNomeCompleto'])) {
            $comment['CommenterNomeCompleto'] = !empty($comment['Contatto']) ? explode('@', $comment['Contatto'])[0] : 'Anonimo';
        }
        $comment['replies'] = [];
        $commentsById[$comment['Progressivo']] = $comment;
    }

    $commentsThreaded = [];
    foreach ($commentsById as $commentId => &$commentNode) { // Usa riferimento & per modificare l'array originale
        if ($commentNode['CodRisposta'] !== null && isset($commentsById[$commentNode['CodRisposta']])) {
            $commentsById[$commentNode['CodRisposta']]['replies'][] = &$commentNode;
        } else {
            $commentsThreaded[] = &$commentNode;
        }
    }
    unset($commentNode); // Rimuovi il riferimento

    $response['success'] = true;
    $response['data'] = [
        'details'  => $eventDetails,
        'media'    => $mediaItems,
        'comments' => $commentsThreaded
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    error_log("Errore PDO in get_event_details.php (ID Evento: {$idEvento}): " . $e->getMessage());
    $response['message'] = 'Errore database durante il recupero dei dettagli.'; // Messaggio più generico per l'utente
    http_response_code(500);
    echo json_encode($response);
    exit;
} catch (Exception $e) {
    error_log("Errore generico in get_event_details.php (ID Evento: {$idEvento}): " . $e->getMessage());
    $response['message'] = 'Errore generale durante il recupero dei dettagli.'; // Messaggio più generico
    http_response_code(500);
    echo json_encode($response);
    exit;
}

$conn = null;
?>