<?php
header('Content-Type: application/json; charset=utf-8'); // Assicura charset UTF-8
require_once 'config_session.php'; // Includi la configurazione della sessione

// Configurazione database (invariata)
$host = "localhost";
$user = "eremofratefrancesco";
$password_db = ""; // Inserisci la tua password se necessaria (rinominata per chiarezza)
$dbname = "my_eremofratefrancesco";

$conn = new mysqli($host, $user, $password_db, $dbname);

if ($conn->connect_errno) {
    error_log("Connessione al database fallita: (" . $conn->connect_errno . ") " . $conn->connect_error);
    // In un'API, è meglio non usare die() direttamente qui, ma restituire un JSON di errore.
    // Tuttavia, per coerenza con il codice originale, lo lascio, ma la gestione errore globale è preferibile.
    echo json_encode(['success' => false, 'message' => 'Errore di connessione al database.']);
    exit;
}
$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

if (!isset($_POST['login-email']) || !isset($_POST['login-password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email e password sono obbligatorie.']);
    exit;
}

$email = trim($_POST['login-email']);
$password_input = $_POST['login-password'];

if (empty($email) || empty($password_input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email e password non possono essere vuote.']);
    exit;
}

// MODIFICA QUI: Aggiungi 'Icon' alla query SELECT
$stmt = $conn->prepare("SELECT Contatto, Password, Nome, IsAdmin, Icon FROM utentiregistrati WHERE Contatto = ?");
if (!$stmt) {
    error_log("Errore nella preparazione dello statement: " . $conn->error);
    http_response_code(500); // Errore interno del server
    echo json_encode(['success' => false, 'message' => 'Errore interno del server (preparazione query).']);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Email non trovata.']);
    $stmt->close();
    $conn->close();
    exit();
}

$user_data = $result->fetch_assoc();

if (password_verify($password_input, $user_data['Password'])) {
    session_regenerate_id(true);

    $_SESSION['user_email'] = $user_data['Contatto'];
    $_SESSION['user_nome'] = $user_data['Nome'];
    $_SESSION['is_admin'] = (bool)$user_data['IsAdmin'];
    $_SESSION['user_icon'] = $user_data['Icon']; // Salva anche l'icona in sessione se ti serve lì
    $_SESSION['login_time'] = time();

    echo json_encode([
        'success' => true,
        'message' => 'Login riuscito!',
        'nome' => $user_data['Nome'],
        'email' => $user_data['Contatto'],
        'iconPath' => $user_data['Icon'], // MODIFICA QUI: Invia il percorso dell'icona
        'is_admin' => (bool)$user_data['IsAdmin']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Password errata.']);
}

$stmt->close();
$conn->close();
?>