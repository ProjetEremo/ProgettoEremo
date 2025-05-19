<?php
header('Content-Type: application/json; charset=utf-8');

$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco', // Il tuo nome DB
    'user' => 'eremofratefrancesco',    // Il tuo username DB
    'pass' => ''                        // La tua password DB
];

$response = ['success' => false, 'data' => null, 'message' => ''];

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
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
    $stmtmedia = $conn->prepare("SELECT Progressivo, Percorso FROM media WHERE IDEvento = :idEvento ORDER BY Progressivo ASC");
    $stmtmedia->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    $stmtmedia->execute();
    $mediaItems = $stmtmedia->fetchAll();

    // 3. Recupera i commenti per l'evento
    $stmtComments = $conn->prepare(
        "SELECT Progressivo, Descrizione, Data, CodRisposta, Contatto, IDEvento
         FROM commenti
         WHERE IDEvento = :idEvento
         ORDER BY IFNULL(CodRisposta, 0) ASC, CodRisposta ASC, Data ASC, Progressivo ASC"
    ); //
    $stmtComments->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    $stmtComments->execute();
    $allCommentsRaw = $stmtComments->fetchAll();

    $commentsThreaded = [];
    $commentsMap = [];

    foreach ($allCommentsRaw as $comment) {
        // Formatta la data qui se non già formattata da SQL (ma la tua query lo fa)
        // $comment['Data'] = date('d/m/Y H:i', strtotime($comment['Data']));
        // L'ultima query che hai fornito non formatta più la data in SQL, quindi la formattiamo qui.
        // Se la query SQL precedente (con DATE_FORMAT) è ancora in uso, questa riga non è necessaria.
        // Assumendo che la Data dal DB sia in un formato che strtotime può leggere.
        if (isset($comment['Data'])) { // Controlla se la Data esiste prima di formattarla
             $comment['Data'] = date('d/m/Y H:i', strtotime($comment['Data']));
        } else {
             $comment['Data'] = 'Data non disponibile'; // O un altro placeholder
        }

        $commentsMap[$comment['Progressivo']] = $comment;
        $commentsMap[$comment['Progressivo']]['replies'] = [];
    }

    foreach ($commentsMap as $commentId => $comment) {
        if ($comment['CodRisposta'] !== null && isset($commentsMap[$comment['CodRisposta']])) {
            $commentsMap[$comment['CodRisposta']]['replies'][] = $comment; // Non usare & qui, copia l'array
        } else {
            $commentsThreaded[] = $comment; // Commento principale
        }
    }

    $response['success'] = true;
    $response['data'] = [
        'details'  => $eventDetails,
        'media'    => $mediaItems,
        'comments' => $commentsThreaded
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    error_log("Errore PDO in get_event_details.php: " . $e->getMessage() . " - Codice: " . $e->getCode() . " - File: " . $e->getFile() . " - Linea: " . $e->getLine());
    // Per debug, invia il messaggio di errore PDO al client.
    // **RICORDA DI RIMUOVERLO O COMMENTARLO IN PRODUZIONE PER SICUREZZA!**
    $response['message'] = 'Errore database: ' . $e->getMessage();
    // $response['message'] = 'Errore database durante il recupero dei dettagli evento.'; // Messaggio per produzione
    http_response_code(500);
    echo json_encode($response);
    exit; // Aggiungi exit qui per sicurezza
} catch (Exception $e) {
    error_log("Errore generico in get_event_details.php: " . $e->getMessage());
    $response['message'] = 'Errore generale: ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
    exit; // Aggiungi exit qui
}

$conn = null; // Questa riga ora potrebbe non essere raggiunta se c'è un exit prima
?>