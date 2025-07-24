<?php
// NOME FILE: richiesta_recupero_password.php
header('Content-Type: application/json; charset=utf-8');

// Configurazione DB (adattala se necessario)
$host = "localhost";
$username_db = "eremofratefrancesco"; // Il tuo username DB Altervista
$password_db = "";                   // La tua password DB Altervista
$dbname_db = "my_eremofratefrancesco";   // Il tuo nome DB Altervista

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname_db;charset=utf8mb4", $username_db, $password_db, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Errore DB (richiesta_recupero_password): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore del server [DB_CONN].']);
    exit;
}

// Funzione per inviare email di recupero password
function inviaEmailRecuperoPassword($destinatarioEmail, $nomeUtenteDestinatario, $token) {
    $subject = "Recupero Password per Eremo Frate Francesco";
    $sitoUrlBase = "https://eremofratefrancesco.altervista.org"; // Assicurati che sia HTTPS se il tuo sito lo supporta
    $linkRecupero = $sitoUrlBase . "/reset_password.php?token=" . urlencode($token);

    $messaggioHTML = "
    <!DOCTYPE html>
    <html lang='it'>
    <head>
      <meta charset='UTF-8'>
      <title>" . htmlspecialchars($subject) . "</title>
      <style>
        body { font-family: 'Segoe UI', sans-serif; line-height: 1.6; color: #333333; background-color: #f8f5ef; margin: 0; padding: 20px; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e9e9e9; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.09); }
        .email-header { background-color: #365e51; color: #ffffff; padding: 20px; text-align: center; border-radius: 15px 15px 0 0; }
        .email-header h1 { margin: 0; font-size: 24px; font-family: 'Playfair Display', serif; }
        .email-content { padding: 25px; }
        .email-content p { margin-bottom: 18px; font-size: 16px; }
        .email-button { display: inline-block; background-color: #d18c60; color: white !important; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: 500; font-size:16px; }
        .email-button:hover { background-color: #365e51; }
        .email-footer { text-align: center; font-size: 0.9em; color: #777777; margin-top: 20px; padding: 15px; background-color: #f0f0f0; border-radius: 0 0 15px 15px;}
        .email-footer p { margin: 5px 0; }
        a { color: #d18c60; }
      </style>
    </head>
    <body>
      <div class='email-container'>
        <div class='email-header'><h1>Eremo Frate Francesco</h1></div>
        <div class='email-content'>
          <p>Gentile " . htmlspecialchars($nomeUtenteDestinatario) . ",</p>
          <p>Abbiamo ricevuto una richiesta di reimpostazione della password per il tuo account.</p>
          <p>Se non hai richiesto tu questa modifica, puoi ignorare questa email.</p>
          <p>Altrimenti, per procedere con la reimpostazione della password, clicca sul seguente link:</p>
          <p style='text-align:center;'><a href='" . $linkRecupero . "' class='email-button'>Reimposta la tua Password</a></p>
          <p>Il link scadrà tra 1 ora.</p>
          <p>Grazie,<br>Lo staff dell'Eremo Frate Francesco</p>
        </div>
        <div class='email-footer'>
          <p>&copy; " . date("Y") . " Eremo Frate Francesco. Tutti i diritti riservati.</p>
           <p><a href='{$sitoUrlBase}'>{$sitoUrlBase}</a></p>
        </div>
      </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: Eremo Frate Francesco <noreply@eremofratefrancesco.altervista.org>' . "\r\n";

    if (mail($destinatarioEmail, $subject, $messaggioHTML, $headers)) {
        error_log("Email di recupero password inviata a: {$destinatarioEmail}");
        return true;
    } else {
        error_log("ERRORE invio email recupero password a: {$destinatarioEmail}. Controllare config mail() e log server posta.");
        return false;
    }
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

$email = filter_input(INPUT_POST, 'forgot-email', FILTER_VALIDATE_EMAIL);

if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Indirizzo email non valido.']);
    exit;
}

$output = ['success' => false, 'message' => 'Se un account con questa email esiste, riceverai un link per il recupero. Controlla anche la cartella spam.']; // Messaggio generico

try {
    $stmt = $conn->prepare("SELECT Nome, Cognome FROM utentiregistrati WHERE Contatto = :email");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32)); // Token sicuro
        $expires_at = date('Y-m-d H:i:s', time() + 3600); // Scade tra 1 ora

        // Prima cancella eventuali token vecchi per questa email
        $stmtDeleteOld = $conn->prepare("DELETE FROM password_reset_tokens WHERE email = :email");
        $stmtDeleteOld->bindParam(':email', $email, PDO::PARAM_STR);
        $stmtDeleteOld->execute();
        
        $stmtInsert = $conn->prepare("INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (:email, :token, :expires_at)");
        $stmtInsert->bindParam(':email', $email, PDO::PARAM_STR);
        $stmtInsert->bindParam(':token', $token, PDO::PARAM_STR);
        $stmtInsert->bindParam(':expires_at', $expires_at, PDO::PARAM_STR);
        
        if ($stmtInsert->execute()) {
            $nomeUtente = trim(($user['Nome'] ?? '') . ' ' . ($user['Cognome'] ?? ''));
            if(empty($nomeUtente)) $nomeUtente = "Utente";

            if (inviaEmailRecuperoPassword($email, $nomeUtente, $token)) {
                 $output['success'] = true; // Manteniamo il messaggio generico per privacy
            } else {
                error_log("Richiesta recupero per $email: fallito invio email MA token generato.");
                 $output['message'] = 'Impossibile inviare l\'email di recupero al momento. Riprova più tardi.';
                 // Non impostare success = false per non rivelare se l'email esiste
            }
        } else {
            error_log("Richiesta recupero per $email: fallito inserimento token DB.");
            $output['message'] = 'Errore del server durante la generazione del link [TOKEN_DB].';
        }
    } else {
        // Email non trovata, ma restituiamo comunque un messaggio generico per non confermare l'esistenza dell'email
        error_log("Tentativo recupero password per email non esistente: $email");
    }

} catch (PDOException $e) {
    error_log("Errore PDO in richiesta_recupero_password.php: " . $e->getMessage());
    $output['message'] = 'Errore del server [PDO_EXC].';
} catch (Exception $e) {
    error_log("Errore generico in richiesta_recupero_password.php: " . $e->getMessage());
    $output['message'] = 'Errore del server [GEN_EXC].';
}

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$conn = null;
?>