<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() == PHP_SESSION_NONE) {
    // session_start(); // Avvia solo se usi la sessione PHP per l'autenticazione
}

$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // LA TUA PASSWORD DB - LASCIA VUOTA SE NON HAI PASSWORD PER QUESTO UTENTE
];

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Metodo non consentito.';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

// Recupero e validazione dei dati dal POST
$idEvento = filter_input(INPUT_POST, 'IDEvento', FILTER_VALIDATE_INT);
$descrizione = isset($_POST['Descrizione']) ? trim($_POST['Descrizione']) : '';
$contatto = filter_input(INPUT_POST, 'Contatto', FILTER_VALIDATE_EMAIL); // Email utente da JS (currentUser.email)
$codRisposta = filter_input(INPUT_POST, 'CodRisposta', FILTER_VALIDATE_INT);

// Validazioni di base
if (!$idEvento) {
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
if (mb_strlen($descrizione) > 2000) { // Limite lunghezza commento
    $response['message'] = 'Il commento è troppo lungo (max 2000 caratteri).';
    http_response_code(400);
    echo json_encode($response);
    exit;
}
if (!$contatto) {
    // Questa validazione è cruciale. Assicurati che il client JS invii SEMPRE l'email dell'utente loggato.
    // Idealmente, dovresti anche verificare lato server che l'utente sia effettivamente loggato
    // (es. tramite token di sessione o API key se stai costruendo un'API più complessa).
    $response['message'] = 'Email utente (Contatto) non fornita o non valida. Devi essere loggato per commentare.';
    http_response_code(400); // O 401 Unauthorized se preferisci
    echo json_encode($response);
    exit;
}

// Se codRisposta è inviato ma non è un intero valido (o è 0, che non dovrebbe essere un ID), impostalo a NULL
if ($codRisposta !== null && ($codRisposta === false || $codRisposta === 0)) {
    $codRisposta = null;
}
// Se 'CodRisposta' non è presente nel POST o è una stringa vuota, filter_input restituirà null o false,
// quindi la gestione sopra dovrebbe bastare. Ma per sicurezza:
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

    // Opzionale: Verifica esistenza evento
    $stmtCheckEvent = $conn->prepare("SELECT IDEvento FROM eventi WHERE IDEvento = :idEvento");
    $stmtCheckEvent->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
    $stmtCheckEvent->execute();
    if ($stmtCheckEvent->fetchColumn() === false) {
        throw new Exception("L'evento a cui stai cercando di commentare non esiste.");
    }

    // Opzionale: Verifica esistenza commento padre (se CodRisposta è fornito)
    if ($codRisposta !== null) {
        $stmtCheckParent = $conn->prepare("SELECT Progressivo FROM Commenti WHERE Progressivo = :codRisposta AND IDEvento = :idEvento");
        $stmtCheckParent->bindParam(':codRisposta', $codRisposta, PDO::PARAM_INT);
        $stmtCheckParent->bindParam(':idEvento', $idEvento, PDO::PARAM_INT); // Importante: deve essere dello stesso evento!
        $stmtCheckParent->execute();
        if ($stmtCheckParent->fetchColumn() === false) {
            throw new Exception("Il commento a cui stai cercando di rispondere non esiste o non appartiene a questo evento.");
        }
    }

    // Inserimento del commento
    // La colonna 'Data' usa NOW() per il timestamp corrente del database
    $sql = "INSERT INTO Commenti (Descrizione, Data, CodRisposta, Contatto, IDEvento)
            VALUES (:descrizione, NOW(), :codRisposta, :contatto, :idEvento)";
    $stmt = $conn->prepare($sql);

    $stmt->bindParam(':descrizione', $descrizione, PDO::PARAM_STR);
    $stmt->bindParam(':codRisposta', $codRisposta, $codRisposta === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(':contatto', $contatto, PDO::PARAM_STR); // Email dell'utente
    $stmt->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);

    $stmt->execute();
    $newCommentId = $conn->lastInsertId();

    $response['success'] = true;
    $response['message'] = 'Commento inviato con successo!';
    $response['new_comment_id'] = $newCommentId; // Potrebbe essere utile al client
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Errore PDO in submit_comment.php: " . $e->getMessage());
    $response['message'] = 'Errore del database durante il salvataggio del commento.';
    // $response['debug_error'] = $e->getMessage(); // Non inviare in produzione
    if (strpos(strtolower($e->getMessage()), 'foreign key constraint') !== false) {
         $response['message'] = 'Errore di riferimento: l\'evento o il commento genitore potrebbero non esistere.';
    }
    http_response_code(500);
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Errore generico in submit_comment.php: " . $e->getMessage());
    $response['message'] = $e->getMessage(); // Usa il messaggio dell'eccezione personalizzata
    http_response_code(400); // Generalmente un errore del client se l'eccezione è stata lanciata da una validazione
    echo json_encode($response);
}

$conn = null;
?>