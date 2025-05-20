<?php
// File: api/api_get_user_waitlist.php
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
    error_log("Errore DB (api_get_user_waitlist): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore del server [CDBPDO_GWL].']);
    exit;
}
// --- Fine Configurazione Database ---

if (!isset($_SESSION['user_email'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato.', 'waitlistEntries' => []]);
    exit;
}

$user_email = $_SESSION['user_email'];
$waitlistEntries = [];

try {
    $sql = "SELECT
                uc.IDEvento AS idEvento,
                uc.NumeroInCoda AS numeroPostiInCoda,
                e.Titolo AS eventTitolo,
                e.Data AS eventDataInizio,
                e.FotoCopertina AS eventFotoCopertina,
                e.PostiDisponibili AS eventPostiDisponibili, /* Per sapere se si sono liberati posti */
                e.FlagPrenotabile AS eventFlagPrenotabile   /* Per sapere se è ancora prenotabile */
            FROM utentiincoda uc
            JOIN eventi e ON uc.IDEvento = e.IDEvento
            WHERE uc.Contatto = :user_email
            ORDER BY e.Data ASC"; // Ordina per data evento

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_email', $user_email, PDO::PARAM_STR);
    $stmt->execute();
    $waitlistEntries = $stmt->fetchAll();

    echo json_encode(['success' => true, 'waitlistEntries' => $waitlistEntries]);

} catch (PDOException $e) {
    error_log("Errore PDO in api_get_user_waitlist.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore durante il recupero della lista d\'attesa.', 'waitlistEntries' => []]);
}

$conn = null; // Chiude la connessione PDO
?>