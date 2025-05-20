<?php
// NOME FILE: manage_comments_admin.php
header('Content-Type: application/json; charset=utf-8');
// Configurazione e connessione PDO ($conn) come sopra

$config = [ /* ... come in get_dashboard_event_data.php ... */ ];
// ... (Connessione PDO, assicurati di includerla o replicarla) ...
$host = "localhost";
$username_db = "eremofratefrancesco";
$password_db = "";
$dbname_db = "my_eremofratefrancesco";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname_db;charset=utf8mb4", $username_db, $password_db, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Errore DB (manage_comments_admin): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore del server [CDB].']);
    exit;
}


$action = $_GET['action'] ?? $_POST['action'] ?? null;
$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
$commentId = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);

$response = ['success' => false, 'message' => 'Azione non valida o dati mancanti.'];

if ($action === 'get_comments' && $eventId) {
    try {
        $stmtComments = $conn->prepare(
            // Recupera anche Nome e Cognome dell'utente se disponibili
            "SELECT c.Progressivo, c.Descrizione, c.DataPubb, c.CodRisposta, c.Contatto, c.IDEvento, c.NumLike,
                    ur.Nome as utente_nome_registrato, ur.Cognome as utente_cognome_registrato
             FROM commenti c
             LEFT JOIN utentiregistrati ur ON c.Contatto = ur.Contatto
             WHERE c.IDEvento = :idEvento
             ORDER BY c.DataPubb ASC" // Ordina per data per mantenere l'ordine cronologico generale
        );
        $stmtComments->bindParam(':idEvento', $eventId, PDO::PARAM_INT);
        $stmtComments->execute();
        $allCommentsRaw = $stmtComments->fetchAll();

        $commentsById = [];
        foreach ($allCommentsRaw as $comment) {
            $displayName = trim(($comment['utente_nome_registrato'] ?? '') . ' ' . ($comment['utente_cognome_registrato'] ?? ''));
            if (empty($displayName)) {
                $displayName = $comment['Contatto']; // Fallback all'email se nome/cognome non ci sono
            }
            $comment['utente_display_name'] = $displayName;

            if (isset($comment['DataPubb'])) {
                $comment['data_creazione_formattata'] = date('d/m/Y H:i', strtotime($comment['DataPubb']));
            } else {
                $comment['data_creazione_formattata'] = 'Data non disponibile';
            }
            $comment['NumLike'] = isset($comment['NumLike']) ? (int)$comment['NumLike'] : 0;
            $comment['replies'] = []; // Inizializza l'array per le risposte
            $commentsById[$comment['Progressivo']] = $comment;
        }

        // Costruisci la struttura ad albero (threaded)
        $commentsThreaded = [];
        foreach ($commentsById as $commentIdKey => &$commentNode) { // Usa il riferimento &
            if ($commentNode['CodRisposta'] !== null && isset($commentsById[$commentNode['CodRisposta']])) {
                // Questo è una risposta, aggiungila all'array 'replies' del suo genitore
                $commentsById[$commentNode['CodRisposta']]['replies'][] = &$commentNode;
            } else {
                // Questo è un commento principale (root)
                $commentsThreaded[] = &$commentNode;
            }
        }
        unset($commentNode); // Rimuovi il riferimento per sicurezza

        $response = ['success' => true, 'comments' => $commentsThreaded];

    } catch (PDOException $e) {
        error_log("Errore DB in manage_comments_admin (get_comments): " . $e->getMessage());
        $response['message'] = 'Errore database nel recupero dei commenti.';
        http_response_code(500);
    }
} elseif ($action === 'delete_comment' && $commentId) {
    try {
        $conn->beginTransaction();

        // Approccio: elimina il commento e le sue risposte dirette.
        // Per risposte nidificate più profondamente, sarebbe necessaria una ricorsione o un loop.
        // Per ora, semplice eliminazione del commento e delle risposte di primo livello.

        // 1. Trova tutte le risposte dirette al commento da eliminare
        $stmtFindReplies = $conn->prepare("SELECT Progressivo FROM commenti WHERE CodRisposta = :parentCommentId");
        $stmtFindReplies->bindParam(':parentCommentId', $commentId, PDO::PARAM_INT);
        $stmtFindReplies->execute();
        $repliesToDelete = $stmtFindReplies->fetchAll(PDO::FETCH_COLUMN);

        // 2. Elimina le risposte dirette (se ce ne sono)
        if (!empty($repliesToDelete)) {
            $placeholders = implode(',', array_fill(0, count($repliesToDelete), '?'));
            $stmtDeleteReplies = $conn->prepare("DELETE FROM commenti WHERE Progressivo IN ($placeholders)");
            foreach ($repliesToDelete as $k => $replyId) {
                $stmtDeleteReplies->bindValue(($k + 1), $replyId, PDO::PARAM_INT);
            }
            $stmtDeleteReplies->execute();
        }

        // 3. Elimina il commento principale
        $stmtDelete = $conn->prepare("DELETE FROM commenti WHERE Progressivo = :commentId");
        $stmtDelete->bindParam(':commentId', $commentId, PDO::PARAM_INT);
        $stmtDelete->execute();

        if ($stmtDelete->rowCount() > 0) {
            $conn->commit();
            $response = ['success' => true, 'message' => 'Commento (e relative risposte dirette) eliminato con successo.'];
        } else {
            $conn->rollBack();
            $response['message'] = 'Commento non trovato o già eliminato.';
        }
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log("Errore DB in manage_comments_admin (delete_comment): " . $e->getMessage());
        $response['message'] = 'Errore database durante l_eliminazione del commento.';
        http_response_code(500);
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$conn = null;
?>