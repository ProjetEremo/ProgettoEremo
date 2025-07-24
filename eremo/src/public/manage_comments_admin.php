<?php
// NOME FILE: manage_comments_admin.php
header('Content-Type: application/json; charset=utf-8');
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors_manage_comments_admin.log'); 

$host = "localhost";
$username_db = "eremofratefrancesco"; 
$password_db = ""; // <<< INSERISCI QUI LA TUA PASSWORD DEL DATABASE
$dbname_db = "my_eremofratefrancesco"; 

// Definizioni globali per l'identificazione e la visualizzazione dell'admin
$admin_email_identifier = 'admin@eremo.it';         // Email univoca che identifica l'admin
$admin_display_name     = 'Eremo Frate Francesco (Staff)'; // Nome visualizzato per i commenti dell'admin
$admin_icon_path        = 'images/logo.png';         // Percorso icona per i commenti dell'admin

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
            "SELECT c.Progressivo, c.Descrizione, c.DataPubb, c.CodRisposta, 
                    c.Contatto, -- Email dell'autore del commento 'c'
                    c.IDEvento, c.NumLike, 
                    ur.Nome as AuthorNome, 
                    ur.Cognome as AuthorCognome,
                    ur.Icon as AuthorIconPathDB, -- Icona dell'autore del commento 'c' dal DB
                    ur.IsAdmin as AuthorIsAdminFlag -- Flag IsAdmin dell'autore del commento 'c' dal DB
             FROM commenti c
             LEFT JOIN utentiregistrati ur ON c.Contatto = ur.Contatto
             WHERE c.IDEvento = :idEvento
             ORDER BY c.DataPubb ASC"
        );
        $stmtComments->bindParam(':idEvento', $eventId, PDO::PARAM_INT);
        $stmtComments->execute();
        $allCommentsRaw = $stmtComments->fetchAll();

        $commentsById = [];
        foreach ($allCommentsRaw as $current_comment_data) {
            // Determina se QUESTO specifico commento ($current_comment_data) è stato scritto da un admin.
            $authorIsAdminDB = isset($current_comment_data['AuthorIsAdminFlag']) && (int)$current_comment_data['AuthorIsAdminFlag'] === 1;
            $authorHasAdminEmail = isset($current_comment_data['Contatto']) && $current_comment_data['Contatto'] === $admin_email_identifier;
            
            $isThisCommentAuthoredByAdmin = $authorIsAdminDB || $authorHasAdminEmail;
            
            // 'is_admin_reply' è il flag usato dal JavaScript della dashboard
            $current_comment_data['is_admin_reply'] = $isThisCommentAuthoredByAdmin; 

            // Imposta il nome visualizzato e l'icona per QUESTO commento
            if ($isThisCommentAuthoredByAdmin) {
                // L'autore di QUESTO commento è un admin.
                $current_comment_data['utente_display_name'] = $admin_display_name;
                $current_comment_data['commenter_icon_path'] = $admin_icon_path;
            } else {
                // L'autore di QUESTO commento è un utente normale (o sconosciuto).
                $nome = $current_comment_data['AuthorNome'] ?? '';
                $cognome = $current_comment_data['AuthorCognome'] ?? '';
                $nomeCompleto = trim($nome . ' ' . $cognome);
                if (empty($nomeCompleto)) {
                    $nomeCompleto = !empty($current_comment_data['Contatto']) ? explode('@', $current_comment_data['Contatto'])[0] : 'Anonimo';
                }
                $current_comment_data['utente_display_name'] = $nomeCompleto;
                $current_comment_data['commenter_icon_path'] = $current_comment_data['AuthorIconPathDB'] ?? null;
                // Esempio per completare il path se necessario (da adattare):
                // if ($current_comment_data['commenter_icon_path'] && !filter_var($current_comment_data['commenter_icon_path'], FILTER_VALIDATE_URL) && strpos($current_comment_data['commenter_icon_path'], '/') === false) {
                //    $current_comment_data['commenter_icon_path'] = 'uploads/user_icons/' . $current_comment_data['commenter_icon_path'];
                // }
            }
            
            $current_comment_data['data_creazione_formattata'] = isset($current_comment_data['DataPubb']) ? date('d/m/Y H:i', strtotime($current_comment_data['DataPubb'])) : 'Data non disponibile';
            $current_comment_data['NumLike'] = isset($current_comment_data['NumLike']) ? (int)$current_comment_data['NumLike'] : 0;
            $current_comment_data['replies'] = [];
            $commentsById[$current_comment_data['Progressivo']] = $current_comment_data;
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
    $parentCommentId = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);
    $replyText = trim($_POST['reply_text'] ?? '');

    if (!$eventId || !$parentCommentId || $replyText === '') { 
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

        $sql_insert = "INSERT INTO commenti (Descrizione, Data, DataPubb, OraPubb, CodRisposta, Contatto, IDEvento, NumLike)
                       VALUES (:descrizione, CURDATE(), NOW(), CURTIME(), :codRisposta, :contatto, :idEvento, 0)";
        $stmt = $conn->prepare($sql_insert);

        $stmt->bindParam(':descrizione', $replyText, PDO::PARAM_STR);
        $stmt->bindParam(':codRisposta', $parentCommentId, PDO::PARAM_INT);
        $stmt->bindParam(':contatto', $admin_email_identifier, PDO::PARAM_STR); // La risposta è dell'admin
        $stmt->bindParam(':idEvento', $eventId, PDO::PARAM_INT);
        
        $stmt->execute();
        $newCommentId = $conn->lastInsertId();
        $conn->commit();
        $response = ['success' => true, 'message' => 'Risposta inviata con successo!', 'new_comment_id' => $newCommentId];

    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log("Errore PDO in manage_comments_admin (submit_admin_reply) - Evento: {$eventId}, Commento Padre: {$parentCommentId} - Errore: " . $e->getMessage() . " - SQLSTATE: " . $e->getCode() . " - Trace: " . $e->getTraceAsString());
        $response['message'] = 'Errore database durante l_invio della risposta. Controlla i log del server.';
        http_response_code(500);
    } catch (Exception $e) { 
        if ($conn->inTransaction()) $conn->rollBack();
        error_log("Errore Applicativo in manage_comments_admin (submit_admin_reply) - Evento: {$eventId}, Commento Padre: {$parentCommentId} - Errore: " . $e->getMessage());
        $response['message'] = $e->getMessage(); 
        http_response_code(400); 
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
        $currentLevelIds = [$commentId];

        while (!empty($currentLevelIds)) {
            $placeholders = implode(',', array_fill(0, count($currentLevelIds), '?'));
            $stmtFindChildren = $conn->prepare("SELECT Progressivo FROM commenti WHERE CodRisposta IN ($placeholders)");
            // Bind parameters one by one for safety if array_values isn't appropriate for your PDO version/config with IN
            $paramIndex = 1;
            foreach($currentLevelIds as $levelId){
                $stmtFindChildren->bindValue($paramIndex++, $levelId, PDO::PARAM_INT);
            }
            $stmtFindChildren->execute();
            $children = $stmtFindChildren->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($children)) {
                $descendants = array_merge($descendants, $children);
                $currentLevelIds = $children; 
            } else {
                $currentLevelIds = []; 
            }
        }
        
        $allToDelete = array_unique(array_merge($descendants, [$commentId]));
        $deletedCount = 0;

        if (!empty($allToDelete)) {
            $placeholders_delete = implode(',', array_fill(0, count($allToDelete), '?'));
            $stmtDeleteAll = $conn->prepare("DELETE FROM commenti WHERE Progressivo IN ($placeholders_delete)");
            // Bind parameters one by one
            $paramIndexDel = 1;
            foreach($allToDelete as $idToDelete){
                 $stmtDeleteAll->bindValue($paramIndexDel++, $idToDelete, PDO::PARAM_INT);
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
            $response['message'] = 'Commento non trovato o già eliminato (o nessun discendente da eliminare).';
        }
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log("Errore PDO in manage_comments_admin (delete_comment, ID: {$commentId}): " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
        $response['message'] = 'Errore database durante l_eliminazione del commento.';
        http_response_code(500);
    }
} else {
     error_log("Azione non gestita o parametri mancanti in manage_comments_admin. Action: " . print_r($action, true) . " POST: " . print_r($_POST, true) . " GET: " . print_r($_GET, true));
     $response['message'] = 'Azione specificata non valida o mancante.'; // Messaggio per il client
     http_response_code(400); // Bad Request
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$conn = null;
?>