<?php
// File: api/api_cancel_waitlist.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// --- Configurazione Database (PDO) ---
$host = "localhost";
$username_db = "eremofratefrancesco"; // Il tuo utente DB
$password_db = "";                   // LA TUA PASSWORD DB
$dbname_db = "my_eremofratefrancesco";   // Il tuo nome DB
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname_db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $username_db, $password_db, $options);
} catch (\PDOException $e) {
    error_log("Errore DB (api_cancel_waitlist): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore del server [CDBPDO_CWL].']);
    exit;
}
// --- Fine Configurazione Database ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

if (!isset($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato.']);
    exit;
}

$user_email = $_SESSION['user_email'];
$data = json_decode(file_get_contents('php://input'), true);
$idEvento = $data['idEvento'] ?? null;

if (empty($idEvento) || !filter_var($idEvento, FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID Evento non valido fornito.']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM utentiincoda WHERE Contatto = :user_email AND IDEvento = :id_evento");
    $stmt->bindParam(':user_email', $user_email, PDO::PARAM_STR);
    $stmt->bindParam(':id_evento', $idEvento, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Rimozione dalla lista d\'attesa effettuata con successo.']);
    } else {
        // Potrebbe essere che l'utente non fosse in lista o fosse già stato rimosso
        echo json_encode(['success' => true, 'message' => 'Nessuna iscrizione alla lista d\'attesa trovata per questo evento o utente, oppure già rimossa.']);
    }
} catch (PDOException $e) {
    error_log("Errore PDO in api_cancel_waitlist.php (User: {$user_email}, Evento: {$idEvento}): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore database durante la rimozione dalla lista d\'attesa.']);
}

$conn = null;
?>