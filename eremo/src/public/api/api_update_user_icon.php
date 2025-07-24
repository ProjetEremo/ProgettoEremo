<?php
// File: api/api_update_user_icon.php
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
    error_log("Errore DB (api_update_user_icon): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore del server [CDBPDO_UUI].']);
    exit;
}
// --- Fine Configurazione Database ---

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
$icon_web_path = $data['icon'] ?? null; // Es. "/uploads/icons/pray.png"

if (empty($icon_web_path)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Percorso icona mancante.']);
    exit;
}

// Validazione di base del percorso web per sicurezza
$valid_icon_path_prefix = '/uploads/icons/';
if (strpos($icon_web_path, $valid_icon_path_prefix) !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Prefisso percorso icona non valido. Assicurati che inizi con ' . $valid_icon_path_prefix]);
    exit;
}

// Costruisci il percorso *filesystem* completo per verificare l'esistenza del file
// __DIR__ è la directory dello script corrente (es. /vostro_percorso_completo_su_server/api)
// dirname(__DIR__) è la directory genitore (es. /vostro_percorso_completo_su_server/ <- questa dovrebbe essere la root del vostro sito)
$base_filesystem_path = dirname(__DIR__); // Root del sito sul filesystem
$file_system_path_to_check = $base_filesystem_path . $icon_web_path; // Es. /vostro_percorso_completo_su_server/uploads/icons/pray.png

// Normalizza il percorso e verifica l'esistenza e che sia un file
$real_file_system_path = realpath($file_system_path_to_check);

if ($real_file_system_path === false || !is_file($real_file_system_path)) {
    // Log per debug
    error_log("Controllo esistenza file icona fallito. Percorso web ricevuto: " . $icon_web_path . ". Percorso filesystem tentato: " . $file_system_path_to_check . ". Risultato realpath(): " . var_export($real_file_system_path, true));
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Il file icona specificato non esiste sul server o non è un file valido.']);
    exit;
}

// Importante: Verifica che il percorso normalizzato sia ancora all'interno della directory prevista per le icone
// Questo è un controllo di sicurezza per prevenire path traversal se $icon_web_path fosse manipolato in modo anomalo.
$expected_icons_dir_on_filesystem = realpath($base_filesystem_path . $valid_icon_path_prefix);
if (!$expected_icons_dir_on_filesystem || strpos($real_file_system_path, $expected_icons_dir_on_filesystem) !== 0) {
    error_log("Tentativo di accesso a percorso icona non consentito. Originale: " . $icon_web_path . ", Risolto: " . $real_file_system_path . ", Atteso in: " . $expected_icons_dir_on_filesystem);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Percorso icona non consentito.']);
    exit;
}


// Se il file esiste ed è valido, procedi con l'aggiornamento del database
// Nel database salviamo il percorso web (es. /uploads/icons/pray.png)
try {
    $stmt = $conn->prepare("UPDATE utentiregistrati SET Icon = :icon_path WHERE Contatto = :user_email");
    $stmt->bindParam(':icon_path', $icon_web_path, PDO::PARAM_STR);
    $stmt->bindParam(':user_email', $user_email, PDO::PARAM_STR);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Icona aggiornata con successo.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiornamento dell\'icona nel database.']);
    }
} catch (PDOException $e) {
    error_log("Errore PDO in api_update_user_icon.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore database durante l\'aggiornamento dell\'icona.']);
}

$conn = null;
?>