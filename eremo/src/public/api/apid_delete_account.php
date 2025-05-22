<?php
// File: api/api_delete_account.php
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
    error_log("Errore DB (api_delete_account): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore del server [CDBPDO_DELACC].']);
    exit;
}
// --- Fine Configurazione Database ---

// --- Funzione per inviare email di conferma cancellazione ---
function inviaEmailConfermaCancellazione($destinatarioEmail, $nomeUtenteDestinatario = 'Utente') {
    $subject = "Conferma cancellazione account - Eremo Frate Francesco";
    $sitoUrlBase = "https://eremofratefrancesco.altervista.org"; // Assicurati che sia HTTPS se il tuo sito lo supporta

    $messaggioHTML = "
    <!DOCTYPE html><html lang='it'><head><meta charset='UTF-8'><title>" . htmlspecialchars($subject) . "</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; line-height: 1.6; color: #333333; background-color: #f8f5ef; margin: 0; padding: 20px; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e9e9e9; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.09); }
        .email-header { background-color: #365e51; color: #ffffff; padding: 20px; text-align: center; border-radius: 15px 15px 0 0; }
        .email-header h1 { margin: 0; font-size: 24px; font-family: 'Playfair Display', serif; }
        .email-content { padding: 25px; } .email-content p { margin-bottom: 18px; font-size: 16px; }
        .email-footer { text-align: center; font-size: 0.9em; color: #777777; margin-top: 20px; padding: 15px; background-color: #f0f0f0; border-radius: 0 0 15px 15px;}
        .email-footer p { margin: 5px 0; } a { color: #8db187; }
    </style></head><body><div class='email-container'>
    <div class='email-header'><h1>Eremo Frate Francesco</h1></div>
    <div class='email-content'>
      <p>Gentile " . htmlspecialchars($nomeUtenteDestinatario) . ",</p>
      <p>Ti confermiamo che il tuo account associato all'indirizzo email " . htmlspecialchars($destinatarioEmail) . " è stato eliminato con successo dal nostro sistema.</p>
      <p>Tutti i tuoi dati personali, prenotazioni e commenti sono stati rimossi come da tua richiesta.</p>
      <p>Ti ringraziamo per aver fatto parte della nostra comunità.</p>
      <p>Cordiali saluti,<br>Lo staff dell'Eremo Frate Francesco</p>
    </div><div class='email-footer'>
      <p>&copy; " . date("Y") . " Eremo Frate Francesco. Tutti i diritti riservati.</p>
      <p><a href='{$sitoUrlBase}'>{$sitoUrlBase}</a></p>
    </div></div></body></html>";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: Eremo Frate Francesco <noreply@eremofratefrancesco.altervista.org>' . "\r\n";

    if (mail($destinatarioEmail, $subject, $messaggioHTML, $headers)) {
        error_log("Email di conferma cancellazione account inviata a: {$destinatarioEmail}");
        return true;
    } else {
        error_log("ERRORE invio email di conferma cancellazione account a: {$destinatarioEmail}.");
        return false;
    }
}
// --- Fine Funzione Email ---


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

$user_email_session = $_SESSION['user_email'];
$data = json_decode(file_get_contents('php://input'), true);
$confirm_email = $data['email'] ?? null;

if (empty($confirm_email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email di conferma mancante.']);
    exit;
}

if ($confirm_email !== $user_email_session) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'L\'email di conferma non corrisponde all\'utente autenticato.']);
    exit;
}

// Recupera Nome e Cognome per l'email PRIMA di cancellare l'utente
$nomeUtenteDaEmail = "Utente"; // Valore di default
try {
    $stmtNome = $conn->prepare("SELECT Nome, Cognome FROM utentiregistrati WHERE Contatto = :user_email");
    $stmtNome->bindParam(':user_email', $user_email_session, PDO::PARAM_STR);
    $stmtNome->execute();
    $utenteInfo = $stmtNome->fetch();
    if ($utenteInfo) {
        $nomeCompleto = trim(($utenteInfo['Nome'] ?? '') . ' ' . ($utenteInfo['Cognome'] ?? ''));
        if (!empty($nomeCompleto)) {
            $nomeUtenteDaEmail = $nomeCompleto;
        }
    }
} catch (PDOException $e) {
    error_log("Errore PDO recupero nome utente per email cancellazione (utente: {$user_email_session}): " . $e->getMessage());
    // Non bloccare l'eliminazione per questo, usa il nome di default
}


try {
    $conn->beginTransaction();

    // 1. Ottieni gli ID delle prenotazioni dell'utente per cancellare i partecipanti (tabella MyISAM)
    $stmtBookings = $conn->prepare("SELECT Progressivo FROM prenotazioni WHERE Contatto = :user_email");
    $stmtBookings->bindParam(':user_email', $user_email_session, PDO::PARAM_STR);
    $stmtBookings->execute();
    $user_bookings_ids = $stmtBookings->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($user_bookings_ids)) {
        $placeholders = implode(',', array_fill(0, count($user_bookings_ids), '?'));
        $stmtDelPart = $conn->prepare("DELETE FROM Partecipanti WHERE Progressivo IN ($placeholders)");
        foreach ($user_bookings_ids as $k => $id) {
            $stmtDelPart->bindValue(($k + 1), $id, PDO::PARAM_INT);
        }
        $stmtDelPart->execute();
        error_log("Cancellati partecipanti per l'utente {$user_email_session} associati alle prenotazioni: " . implode(',', $user_bookings_ids));
    }

    // 2. Elimina l'utente da utentiregistrati.
    // Le cancellazioni ON DELETE CASCADE si occuperanno di: prenotazioni, utentiincoda, commenti.
    $stmtDeleteUser = $conn->prepare("DELETE FROM utentiregistrati WHERE Contatto = :user_email");
    $stmtDeleteUser->bindParam(':user_email', $user_email_session, PDO::PARAM_STR);
    $stmtDeleteUser->execute();

    if ($stmtDeleteUser->rowCount() > 0) {
        $conn->commit();

        // Invia email di conferma cancellazione
        inviaEmailConfermaCancellazione($user_email_session, $nomeUtenteDaEmail);

        // Distruggi la sessione per effettuare il logout
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        echo json_encode(['success' => true, 'message' => 'Account eliminato con successo. Riceverai una mail di conferma. Verrai disconnesso a breve.']);
    } else {
        $conn->rollBack();
        error_log("Tentativo di eliminare l'utente {$user_email_session} fallito (utente non trovato o nessuna riga affetta).");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Utente non trovato o eliminazione già avvenuta.']);
    }

} catch (PDOException $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Errore PDO in api_delete_account.php per utente {$user_email_session}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore database durante l\'eliminazione dell\'account. Contatta l\'assistenza.']);
}

$conn = null;
?>