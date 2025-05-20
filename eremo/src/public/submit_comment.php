<?php
header('Content-Type: application/json; charset=utf-8');

// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }

$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // LA TUA PASSWORD DB
];

$response = ['success' => false, 'message' => ''];
$sql_debug = ''; // Inizializza per debug

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo non consentito.';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

$idEvento = filter_input(INPUT_POST, 'IDEvento', FILTER_VALIDATE_INT);
$descrizione = isset($_POST['Descrizione']) ? trim($_POST['Descrizione']) : '';
$contatto = filter_input(INPUT_POST, 'Contatto', FILTER_VALIDATE_EMAIL);
$codRisposta = filter_input(INPUT_POST, 'CodRisposta', FILTER_VALIDATE_INT);

if (!$idEvento || $idEvento <= 0) {
    $response['message'] = 'ID Evento mancante o non valido.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}
if (empty($descrizione)) {
    $response['message'] = 'Il testo del commento non può essere vuoto.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}
if (mb_strlen($descrizione) > 2000) {
    $response['message'] = 'Il commento è troppo lungo (max 2000 caratteri).';
    http_response_code(400);
    echo json_encode($response);
    exit;
}
if (!$contatto) {
    $response['message'] = 'Email utente non fornita o non valida.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

if ($codRisposta !== null && ($codRisposta === false || $codRisposta <= 0)) {
    $codRisposta = null;
}
if (isset($_POST['CodRisposta']) && empty($_POST['CodRisposta']) && $_POST['CodRisposta'] !== '0') {
    $codRisposta = null;
}

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

    $stmtCheckEvent = $conn->prepare("SELECT IDEvento FROM eventi WHERE IDEvento = :idEvento");
    $stmtCheckEvent->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    $stmtCheckEvent->execute();
    if ($stmtCheckEvent->fetchColumn() === false) {
        throw new Exception("L'evento specificato non esiste.");
    }

    if ($codRisposta !== null) {
        // Assicurati che il nome della tabella sia corretto (commenti vs Commenti)
        $stmtCheckParent = $conn->prepare("SELECT Progressivo FROM commenti WHERE Progressivo = :codRisposta AND IDEvento = :idEvento");
        $stmtCheckParent->bindParam(':codRisposta', $codRisposta, PDO::PARAM_INT);
        $stmtCheckParent->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtCheckParent->execute();
        if ($stmtCheckParent->fetchColumn() === false) {
            throw new Exception("Il commento a cui stai cercando di rispondere non esiste o non appartiene a questo evento.");
        }
    }

    // Query SQL CORRETTA per includere DataPubb e OraPubb
    // La colonna 'Data' originale potrebbe non essere più necessaria se hai DataPubb e OraPubb,
    // ma la mantengo per ora come nella tua ultima versione.
    // Se 'Data' è ridondante, puoi rimuoverla dall'INSERT e dalla tabella.
    $sql_debug = "INSERT INTO commenti (Descrizione, Data, DataPubb, OraPubb, CodRisposta, Contatto, IDEvento, NumLike)
                  VALUES (:descrizione, NOW(), NOW(), CURTIME(), :codRisposta, :contatto, :idEvento, 0)";
    $stmt = $conn->prepare($sql_debug);

    $stmt->bindParam(':descrizione', $descrizione, PDO::PARAM_STR);
    $stmt->bindParam(':codRisposta', $codRisposta, $codRisposta === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(':contatto', $contatto, PDO::PARAM_STR);
    $stmt->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    // Data, DataPubb, OraPubb e NumLike sono gestiti direttamente nella query con NOW(), CURTIME() e 0

    $stmt->execute();
    $newCommentId = $conn->lastInsertId();

    $response['success'] = true;
    $response['message'] = 'Commento inviato con successo!';
    $response['new_comment_id'] = $newCommentId;
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Errore PDO in submit_comment.php: " . $e->getMessage() . " - SQL: " . $sql_debug);
    $response['message'] = 'Errore database (submit): ' . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
    exit;
} catch (Exception $e) {
    error_log("Errore generico in submit_comment.php: " . $e->getMessage());
    $response['message'] = 'Errore: ' . $e->getMessage();
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$conn = null;
?>