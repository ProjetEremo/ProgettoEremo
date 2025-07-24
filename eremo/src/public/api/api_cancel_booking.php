<?php
// File: api/api_cancel_booking.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// --- Configurazione Database (PDO, come in annulla_prenotazione_admin.php) ---
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
    error_log("Errore DB (api_cancel_booking): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore del server [CDBPDO].']);
    exit;
}
// --- Fine Configurazione Database ---


// --- Funzione per inviare email HTML (copiata da annulla_prenotazione_admin.php) ---
function inviaEmailNotificaCoda($destinatarioEmail, $nomeEvento, $postiRichiestiOriginalmenteInCoda, $postiOraDisponibiliEvento, $idEvento, $nomeUtenteDestinatario = 'Utente') {
    $subject = "Posti nuovamente disponibili per l'evento: " . htmlspecialchars($nomeEvento);
    $sitoUrlBase = "https://eremofratefrancesco.altervista.org"; // Assicurati che sia HTTPS se il tuo sito lo supporta

    $linkPaginaEventi = $sitoUrlBase . "/eventiincorso.html";

    $messaggioHTML = "
    <!DOCTYPE html><html lang='it'><head><meta charset='UTF-8'><title>" . htmlspecialchars($subject) . "</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; line-height: 1.6; color: #333333; background-color: #f8f5ef; margin: 0; padding: 20px; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e9e9e9; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.09); }
        .email-header { background-color: #365e51; color: #ffffff; padding: 20px; text-align: center; border-radius: 15px 15px 0 0; }
        .email-header h1 { margin: 0; font-size: 24px; font-family: 'Playfair Display', serif; }
        .email-content { padding: 25px; } .email-content p { margin-bottom: 18px; font-size: 16px; }
        .email-button { display: inline-block; background-color: #8db187; color: white !important; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: 500; font-size:16px; }
        .email-button:hover { background-color: #365e51; }
        .email-footer { text-align: center; font-size: 0.9em; color: #777777; margin-top: 20px; padding: 15px; background-color: #f0f0f0; border-radius: 0 0 15px 15px;}
        .email-footer p { margin: 5px 0; } a { color: #8db187; }
    </style></head><body><div class='email-container'>
    <div class='email-header'><h1>Eremo Frate Francesco</h1></div>
    <div class='email-content'>
      <p>Gentile " . htmlspecialchars($nomeUtenteDestinatario) . ",</p>
      <p>Buone notizie! Si sono liberati dei posti per l'evento \"<strong>" . htmlspecialchars($nomeEvento) . "</strong>\", per il quale eri in lista d'attesa.</p>
      <p>Al momento, sull'evento risultano nuovamente disponibili <strong>" . $postiOraDisponibiliEvento . "</strong> posti.</p>
      <p>Se sei ancora interessato/a, ti invitiamo a visitare la nostra pagina del calendario attività per cercare l'evento e procedere con la prenotazione il prima possibile.
         <strong>Affrettati, i posti sono limitati e verranno assegnati ai primi che completano la prenotazione!</strong></p>
      <p style='text-align:center;'><a href='" . $linkPaginaEventi . "' class='email-button'>Vai al Calendario Attività</a></p>
      <p>Grazie,<br>Lo staff dell'Eremo Frate Francesco</p>
    </div><div class='email-footer'>
      <p>&copy; " . date("Y") . " Eremo Frate Francesco. Tutti i diritti riservati.</p>
      <p>Se effettuerai una prenotazione per questo evento, verrai automaticamente rimosso dalla lista d'attesa. Se non sei più interessato, puoi ignorare questa email.</p>
      <p><a href='{$sitoUrlBase}'>{$sitoUrlBase}</a></p>
    </div></div></body></html>";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: Eremo Frate Francesco <noreply@eremofratefrancesco.altervista.org>' . "\r\n";

    if (mail($destinatarioEmail, $subject, $messaggioHTML, $headers)) {
        error_log("Email di notifica coda (da API utente) inviata con successo a: {$destinatarioEmail} per evento ID {$idEvento}");
        return true;
    } else {
        error_log("ERRORE invio email di notifica coda (da API utente) a: {$destinatarioEmail} per evento ID {$idEvento}.");
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

$user_email = $_SESSION['user_email'];
$data = json_decode(file_get_contents('php://input'), true);
$idPrenotazione = $data['idPrenotazione'] ?? null;

if (empty($idPrenotazione) || !filter_var($idPrenotazione, FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID Prenotazione non valido.']);
    exit;
}

$output = ['success' => false, 'message' => 'Errore durante l\'annullamento della prenotazione.'];

try {
    $conn->beginTransaction();

    // 1. Verifica che la prenotazione appartenga all'utente e recupera IDEvento e NumeroPosti
    $stmtPrenotazione = $conn->prepare("SELECT IDEvento, NumeroPosti FROM prenotazioni WHERE Progressivo = :idPrenotazione AND Contatto = :contattoUtente");
    $stmtPrenotazione->bindParam(':idPrenotazione', $idPrenotazione, PDO::PARAM_INT);
    $stmtPrenotazione->bindParam(':contattoUtente', $user_email, PDO::PARAM_STR);
    $stmtPrenotazione->execute();
    $prenotazione = $stmtPrenotazione->fetch();

    if (!$prenotazione) {
        throw new Exception("Prenotazione (ID: {$idPrenotazione}) non trovata o non autorizzata per l'utente.");
    }
    $idEvento = (int)$prenotazione['IDEvento'];
    $postiDaRipristinare = (int)$prenotazione['NumeroPosti'];

    // 2. Elimina i partecipanti associati (la tabella Partecipanti ha `Progressivo` come FK a `prenotazioni.Progressivo`)
    $stmtDelPart = $conn->prepare("DELETE FROM Partecipanti WHERE Progressivo = :idPrenotazione");
    $stmtDelPart->bindParam(':idPrenotazione', $idPrenotazione, PDO::PARAM_INT);
    $stmtDelPart->execute();
    // Non è un errore critico se non ci sono partecipanti, quindi non controlliamo rowCount qui.

    // 3. Elimina la prenotazione
    $stmtDelPren = $conn->prepare("DELETE FROM prenotazioni WHERE Progressivo = :idPrenotazione");
    $stmtDelPren->bindParam(':idPrenotazione', $idPrenotazione, PDO::PARAM_INT);
    $stmtDelPren->execute();

    if ($stmtDelPren->rowCount() > 0) {
        // 4. Aggiorna i posti disponibili nell'evento
        $stmtUpdateEvent = $conn->prepare("UPDATE eventi SET PostiDisponibili = PostiDisponibili + :postiRipristinati WHERE IDEvento = :idEvento");
        $stmtUpdateEvent->bindParam(':postiRipristinati', $postiDaRipristinare, PDO::PARAM_INT);
        $stmtUpdateEvent->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtUpdateEvent->execute();

        // --- Logica di notifica per la coda di attesa (come da annulla_prenotazione_admin.php) ---
        $stmtEventoInfo = $conn->prepare("SELECT Titolo, PostiDisponibili FROM eventi WHERE IDEvento = :idEvento");
        $stmtEventoInfo->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtEventoInfo->execute();
        $eventoInfo = $stmtEventoInfo->fetch();

        $nomeEvento = $eventoInfo ? $eventoInfo['Titolo'] : "Evento ID " . $idEvento;
        $postiOraDisponibiliEvento = $eventoInfo ? (int)$eventoInfo['PostiDisponibili'] : 0;
        $emailsInviate = 0;

        if ($postiOraDisponibiliEvento > 0) {
            // Se hai aggiunto TimestampInserimentoCoda, usa "ORDER BY uc.TimestampInserimentoCoda ASC"
            // Altrimenti, l'ordine potrebbe non essere garantito o basato su PK.
            // Per ora, uso Contatto come fallback per l'ordinamento se non hai il timestamp.
            $sqlCoda = "SELECT uc.Contatto, uc.NumeroInCoda, ur.Nome, ur.Cognome
                        FROM utentiincoda uc
                        JOIN utentiregistrati ur ON uc.Contatto = ur.Contatto
                        WHERE uc.IDEvento = :idEvento
                        ORDER BY uc.Contatto ASC"; // CAMBIA IN uc.TimestampInserimentoCoda ASC SE ESISTE
            
            $stmtCoda = $conn->prepare($sqlCoda);
            $stmtCoda->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
            $stmtCoda->execute();
            $utentiInCoda = $stmtCoda->fetchAll();

            foreach ($utentiInCoda as $utenteCoda) {
                // Logica di notifica identica allo script admin
                $nomeUtenteDest = trim(($utenteCoda['Nome'] ?? '') . ' ' . ($utenteCoda['Cognome'] ?? ''));
                if(empty($nomeUtenteDest)) $nomeUtenteDest = "Utente";

                if (inviaEmailNotificaCoda(
                    $utenteCoda['Contatto'],
                    $nomeEvento,
                    (int)$utenteCoda['NumeroInCoda'],
                    $postiOraDisponibiliEvento,
                    $idEvento,
                    $nomeUtenteDest
                )) {
                    $emailsInviate++;
                }
            }
        }
        // --- Fine Logica Notifica Coda ---

        $conn->commit();
        $msgSuccess = "Prenotazione annullata con successo. {$postiDaRipristinare} posto/i è/sono stato/i ripristinato/i per l'evento.";
        if ($emailsInviate > 0) {
            $msgSuccess .= " Inoltre, {$emailsInviate} email di notifica sono state inviate agli utenti in coda.";
        }
        $output = ['success' => true, 'message' => $msgSuccess];

    } else {
        $conn->rollBack(); // Se la cancellazione della prenotazione non ha interessato righe
        throw new Exception("Impossibile annullare la prenotazione (ID: {$idPrenotazione}) o già annullata.");
    }

} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Errore in api_cancel_booking.php (ID Pren: {$idPrenotazione}): " . $e->getMessage());
    http_response_code(400); // O 500 a seconda dell'errore
    $output = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$conn = null; // Chiude la connessione PDO
?>