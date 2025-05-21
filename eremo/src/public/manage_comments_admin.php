<?php
// NOME FILE: manage_comments_admin.php
header('Content-Type: application/json; charset=utf-8');
ini_set('log_errors', 1);
// MODIFICA QUESTO PATH SE NECESSARIO, DEVE ESSERE SCRIVIBILE DAL SERVER WEB
ini_set('error_log', __DIR__ . '/php_errors_manage_comments_admin.log');

$host = "localhost";
$username_db = "eremofratefrancesco"; // Il tuo username DB
$password_db = ""; // La tua password DB
$dbname_db = "my_eremofratefrancesco"; // Il tuo nome DB

// Definizioni per l'identificazione dell'admin
$admin_email_identifier = 'admin@eremo.it'; // USA UN EMAIL CHE HAI INSERITO NELLA TABELLA utentiregistrati PER L'ADMIN
                                                   // Questo è cruciale se hai un foreign key constraint da commenti.Contatto a utentiregistrati.Contatto
$admin_display_name = 'Eremo Frate Francesco';
$admin_icon_path = 'images/logo.png'; // Path all'icona dell'admin (relativo alla root del sito)

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname_db;charset=utf8mb4", $username_db, $password_db, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("CRITICAL DB CONNECTION ERROR (manage_comments_admin): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore critico del server [CDB_INIT]. Impossibile connettersi al database.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$response = ['success' => false, 'message' => 'Azione non specificata o non valida.'];

if ($action === 'get_comments') {
    $eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
    if (!$eventId) {
        $response['message'] = 'ID Evento mancante o non valido per get_comments.';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }
    try {
        $stmtComments = $conn->prepare(
            "SELECT c.Progressivo, c.Descrizione, c.DataPubb, c.CodRisposta, c.Contatto, c.IDEvento, c.NumLike,
                    ur.Nome as utente_nome_registrato,
                    ur.Cognome as utente_cognome_registrato,
                    ur.Icon as utente_icon_path_raw
             FROM commenti c
             LEFT JOIN utentiregistrati ur ON c.Contatto = ur.Contatto
             WHERE c.IDEvento = :idEvento
             ORDER BY c.DataPubb ASC"
        );
        $stmtComments->bindParam(':idEvento', $eventId, PDO::PARAM_INT);
        $stmtComments->execute();
        $allCommentsRaw = $stmtComments->fetchAll();

        $commentsById = [];
        foreach ($allCommentsRaw as $comment) {
            $comment['is_admin_reply'] = ($comment['Contatto'] === $admin_email_identifier);

            if ($comment['is_admin_reply']) {
                $comment['utente_display_name'] = $admin_display_name;
                $comment['commenter_icon_path'] = $admin_icon_path;
            } else {
                $displayName = trim(($comment['utente_nome_registrato'] ?? '') . ' ' . ($comment['utente_cognome_registrato'] ?? ''));
                if (empty($displayName)) {
                    $displayName = !empty($comment['Contatto']) ? explode('@', $comment['Contatto'])[0] : 'Anonimo';
                }
                $comment['utente_display_name'] = $displayName;
                // Assicurati che il path dell'icona sia completo se necessario, o gestiscilo nel frontend
                $comment['commenter_icon_path'] = !empty($comment['utente_icon_path_raw']) ? $comment['utente_icon_path_raw'] : null;
            }

            $comment['data_creazione_formattata'] = isset($comment['DataPubb']) ? date('d/m/Y H:i', strtotime($comment['DataPubb'])) : 'Data non disponibile';
            $comment['NumLike'] = isset($comment['NumLike']) ? (int)$comment['NumLike'] : 0;
            $comment['replies'] = [];
            $commentsById[$comment['Progressivo']] = $comment;
        }

        $commentsThreaded = [];
        foreach ($commentsById as $commentIdKey => &$commentNode) {
            if ($commentNode['CodRisposta'] !== null && isset($commentsById[$commentNode['CodRisposta']])) {
                $commentsById[$commentNode['CodRisposta']]['replies'][] = &$commentNode;
            } else {
                $commentsThreaded[] = &$commentNode;
            }
        }
        unset($commentNode);
        $response = ['success' => true, 'comments' => $commentsThreaded];

    } catch (PDOException $e) {
        error_log("Errore PDO in manage_comments_admin (get_comments, Evento ID: {$eventId}): " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
        $response['message'] = 'Errore database nel recupero dei commenti.';
        http_response_code(500);
    }

} elseif ($action === 'submit_admin_reply') {
    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $parentCommentId = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT); // ID del commento a cui si risponde
    $replyText = trim($_POST['reply_text'] ?? '');

    if (!$eventId || !$parentCommentId || $replyText === '') { // Controlla anche empty string
        $response['message'] = 'Dati mancanti o non validi per la risposta (ID evento, ID commento padre, testo).';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }
    if (mb_strlen($replyText) > 2000) {
        $response['message'] = 'La risposta è troppo lunga (max 2000 caratteri).';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    try {
        $conn->beginTransaction();

        $stmtCheckEvent = $conn->prepare("SELECT IDEvento FROM eventi WHERE IDEvento = :idEvento FOR UPDATE");
        $stmtCheckEvent->bindParam(':idEvento', $eventId, PDO::PARAM_INT);
        $stmtCheckEvent->execute();
        if ($stmtCheckEvent->fetchColumn() === false) {
            $conn->rollBack();
            throw new Exception("L'evento specificato (ID: {$eventId}) per la risposta non esiste.");
        }

        $stmtCheckParent = $conn->prepare("SELECT Progressivo FROM commenti WHERE Progressivo = :parentCommentId AND IDEvento = :idEvento FOR UPDATE");
        $stmtCheckParent->bindParam(':parentCommentId', $parentCommentId, PDO::PARAM_INT);
        $stmtCheckParent->bindParam(':idEvento', $eventId, PDO::PARAM_INT);
        $stmtCheckParent->execute();
        if ($stmtCheckParent->fetchColumn() === false) {
            $conn->rollBack();
            throw new Exception("Il commento padre (ID: {$parentCommentId}) a cui stai cercando di rispondere non esiste o non appartiene a questo evento (ID: {$eventId}).");
        }

        // IMPORTANTE: Assicurati che $admin_email_identifier esista nella tabella utentiregistrati se hai un FK constraint.
        // Se non esiste, l'INSERT fallirà con errore di integrità referenziale.
        $sql_insert = "INSERT INTO commenti (Descrizione, Data, DataPubb, OraPubb, CodRisposta, Contatto, IDEvento, NumLike)
                       VALUES (:descrizione, CURDATE(), NOW(), CURTIME(), :codRisposta, :contatto, :idEvento, 0)";
        $stmt = $conn->prepare($sql_insert);

        $stmt->bindParam(':descrizione', $replyText, PDO::PARAM_STR);
        $stmt->bindParam(':codRisposta', $parentCommentId, PDO::PARAM_INT);
        $stmt->bindParam(':contatto', $admin_email_identifier, PDO::PARAM_STR);
        $stmt->bindParam(':idEvento', $eventId, PDO::PARAM_INT);

        $stmt->execute();
        $newCommentId = $conn->lastInsertId();
        $conn->commit();
        $response = ['success' => true, 'message' => 'Risposta inviata con successo!', 'new_comment_id' => $newCommentId];

    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        // Log più dettagliato per PDOException
        error_log("Errore PDO in manage_comments_admin (submit_admin_reply) - Evento: {$eventId}, Commento Padre: {$parentCommentId} - Errore: " . $e->getMessage() . " - SQLSTATE: " . $e->getCode() . " - Trace: " . $e->getTraceAsString());
        $response['message'] = 'Errore database durante l_invio della risposta. Controlla i log del server.'; // Messaggio per il client
        http_response_code(500);
    } catch (Exception $e) { // Catch generico per validazioni o altri problemi
        if ($conn->inTransaction()) $conn->rollBack();
        error_log("Errore Applicativo in manage_comments_admin (submit_admin_reply) - Evento: {$eventId}, Commento Padre: {$parentCommentId} - Errore: " . $e->getMessage());
        $response['message'] = $e->getMessage(); // Mostra il messaggio dell'eccezione custom
        http_response_code(400); // Bad request se l'evento o il commento padre non esistono
    }

} elseif ($action === 'delete_comment') {
    $commentId = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);
    if (!$commentId) {
        $response['message'] = 'ID Commento mancante o non valido per l_eliminazione.';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }
    try {
        $conn->beginTransaction();
        $descendants = [];
        $idsToCheck = [$commentId];
        while (!empty($idsToCheck)) {
            $currentId = array_shift($idsToCheck);
            $stmtFindChildren = $conn->prepare("SELECT Progressivo FROM commenti WHERE CodRisposta = :parentId");
            $stmtFindChildren->bindParam(':parentId', $currentId, PDO::PARAM_INT);
            $stmtFindChildren->execute();
            $children = $stmtFindChildren->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($children)) {
                $descendants = array_merge($descendants, $children);
                $idsToCheck = array_merge($idsToCheck, $children);
            }
        }
        $allToDelete = array_unique(array_merge($descendants, [$commentId]));
        $deletedCount = 0;
        if (!empty($allToDelete)) {
            $placeholders = implode(',', array_fill(0, count($allToDelete), '?'));
            $stmtDeleteAll = $conn->prepare("DELETE FROM commenti WHERE Progressivo IN ($placeholders)");
            foreach ($allToDelete as $k => $idToDelete) {
                $stmtDeleteAll->bindValue(($k + 1), $idToDelete, PDO::PARAM_INT);
            }
            if ($stmtDeleteAll->execute()) {
                 $deletedCount = $stmtDeleteAll->rowCount();
            }
        }
        if ($deletedCount > 0) {
            $conn->commit();
            $response = ['success' => true, 'message' => "Commento/i ({$deletedCount}) eliminato/i con successo."];
        } else {
            $conn->rollBack();
            $response['message'] = 'Commento non trovato o già eliminato.';
        }
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log("Errore PDO in manage_comments_admin (delete_comment, ID: {$commentId}): " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
        $response['message'] = 'Errore database durante l_eliminazione del commento.';
        http_response_code(500);
    }
} else {
     error_log("Azione non gestita o parametri mancanti in manage_comments_admin. Action: " . print_r($action, true) . " POST: " . print_r($_POST, true) . " GET: " . print_r($_GET, true));
}


echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$conn = null;
?>