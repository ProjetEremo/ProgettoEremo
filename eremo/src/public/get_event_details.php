<?php
session_start(); // Inizia la sessione per recuperare l'utente loggato
header('Content-Type: application/json; charset=utf-8');

// Impostazioni PHP (decommentare per debug, ma commentate in produzione)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors_get_event_details.log'); // Assicurati che questo percorso sia scrivibile

$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // <<< INSERISCI QUI LA TUA PASSWORD DEL DATABASE
];

// Definizioni globali per l'identificazione e la visualizzazione dell'admin
$admin_email_identifier = 'admin@eremo.it';         // Email univoca che identifica l'admin
$admin_display_name     = 'Eremo Frate Francesco (Staff)'; // Nome visualizzato per i commenti dell'admin
$admin_icon_path        = 'images/logo.png';         // Percorso icona per i commenti dell'admin (relativo alla root del sito)

$response = ['success' => false, 'data' => null, 'message' => ''];

// Recupera l'utente loggato dalla sessione (o null se non loggato)
$currentUserEmail = $_SESSION['user_email'] ?? null;

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

    // 3. Recupera i commenti con informazioni sull'utente e SUL LIKE DELL'UTENTE CORRENTE
    $sqlComments = "
        SELECT
            c.Progressivo, c.Descrizione, c.DataPubb, c.CodRisposta,
            c.Contatto, c.IDEvento, c.NumLike,
            u.Nome AS AuthorNome,
            u.Cognome AS AuthorCognome,
            u.Icon AS AuthorIconPathDB,
            u.IsAdmin AS AuthorIsAdminFlag";

    // Aggiungi la parte per 'is_liked_by_current_user' solo se l'utente è loggato
    if ($currentUserEmail) {
        $sqlComments .= ",
            (SELECT COUNT(*)
             FROM likes_commenti lc
             WHERE lc.IDCommento = c.Progressivo AND lc.Contatto = :currentUserEmail
            ) AS is_liked_by_current_user";
    } else {
        // Se l'utente non è loggato, il campo sarà sempre 0 (false)
        $sqlComments .= ", 0 AS is_liked_by_current_user";
    }

    $sqlComments .= "
         FROM commenti c
         LEFT JOIN utentiregistrati u ON c.Contatto = u.Contatto
         WHERE c.IDEvento = :idEvento
         ORDER BY c.DataPubb DESC"; // MODIFICA: cambiato da ASC a DESC per ordine LIFO

    $stmtComments = $conn->prepare($sqlComments);
    $stmtComments->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    // Binda il parametro solo se esiste (utente loggato)
    if ($currentUserEmail) {
        $stmtComments->bindParam(':currentUserEmail', $currentUserEmail, PDO::PARAM_STR);
    }
    $stmtComments->execute();
    $allCommentsRaw = $stmtComments->fetchAll();


    $commentsById = [];
    foreach ($allCommentsRaw as $current_comment_data) {
        // Determina se QUESTO specifico commento è stato scritto da un admin.
        $authorIsAdminDB = isset($current_comment_data['AuthorIsAdminFlag']) && (int)$current_comment_data['AuthorIsAdminFlag'] === 1;
        $authorHasAdminEmail = isset($current_comment_data['Contatto']) && $current_comment_data['Contatto'] === $admin_email_identifier;
        $isThisCommentAuthoredByAdmin = $authorIsAdminDB || $authorHasAdminEmail;
        $current_comment_data['is_admin_comment'] = $isThisCommentAuthoredByAdmin;

        // Imposta il nome visualizzato e l'icona
        if ($isThisCommentAuthoredByAdmin) {
            $current_comment_data['CommenterNomeCompleto'] = $admin_display_name;
            $current_comment_data['CommenterIconPath'] = $admin_icon_path;
        } else {
            $nome = $current_comment_data['AuthorNome'] ?? '';
            $cognome = $current_comment_data['AuthorCognome'] ?? '';
            $nomeCompleto = trim($nome . ' ' . $cognome);
            $current_comment_data['CommenterNomeCompleto'] = empty($nomeCompleto) ? (!empty($current_comment_data['Contatto']) ? explode('@', $current_comment_data['Contatto'])[0] : 'Anonimo') : $nomeCompleto;
            $current_comment_data['CommenterIconPath'] = $current_comment_data['AuthorIconPathDB'] ?? null;
        }

        // Formattazione data e numero di like
        $current_comment_data['DataVisualizzata'] = isset($current_comment_data['DataPubb']) ? date('d/m/Y H:i', strtotime($current_comment_data['DataPubb'])) : 'Data non disponibile';
        $current_comment_data['NumLike'] = isset($current_comment_data['NumLike']) ? (int)$current_comment_data['NumLike'] : 0;

        // Imposta 'is_liked_by_current_user' (convertito in booleano)
        $current_comment_data['is_liked_by_current_user'] = isset($current_comment_data['is_liked_by_current_user']) ? ((int)$current_comment_data['is_liked_by_current_user'] > 0) : false;

        $current_comment_data['replies'] = []; // Array per eventuali risposte
        $commentsById[$current_comment_data['Progressivo']] = $current_comment_data; // Salva il commento processato
    }

    // Costruisci la struttura ad albero dei commenti (threading).
    $commentsThreaded = [];
    foreach ($commentsById as $commentId => &$commentNode) {
        if ($commentNode['CodRisposta'] !== null && isset($commentsById[$commentNode['CodRisposta']])) {
            $commentsById[$commentNode['CodRisposta']]['replies'][] = &$commentNode;
        } else {
            $commentsThreaded[] = &$commentNode;
        }
    }
    unset($commentNode);

    $response['success'] = true;
    $response['data'] = [
        'details'  => $eventDetails,
        'media'    => $mediaItems,
        'comments' => $commentsThreaded
    ];
    // Rimosso JSON_PRETTY_PRINT per una risposta più compatta in produzione
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    error_log("Errore PDO in get_event_details.php (ID Evento: {$idEvento}): " . $e->getMessage());
    $response['message'] = 'Errore database. Riprova più tardi.';
    http_response_code(500);
    echo json_encode($response);
    exit;
} catch (Exception $e) {
    error_log("Errore generico in get_event_details.php (ID Evento: {$idEvento}): " . $e->getMessage());
    $response['message'] = 'Errore generale. Riprova più tardi.';
    http_response_code(500);
    echo json_encode($response);
    exit;
}

$conn = null;
?>
