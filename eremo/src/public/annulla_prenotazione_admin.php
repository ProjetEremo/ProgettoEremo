<?php
// NOME FILE: annulla_prenotazione_admin.php
header('Content-Type: application/json; charset=utf-8');
require_once 'config_session.php'; // PRIMA COSA
require_login(); // Verifica se l'utente è loggato
// Configurazione e connessione PDO ($conn) come sopra

$config = [ /* ... come sopra ... */ ];
// ... (Connessione PDO) ...
$host = "localhost";
$username_db = "eremofratefrancesco";
$password_db = "";
$dbname_db = "my_eremofratefrancesco";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname_db;charset=utf8mb4", $username_db, $password_db, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Errore DB (annulla_prenotazione_admin): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore del server [CDB].']);
    exit;
}

// Funzione per inviare email HTML
function inviaEmailNotificaCoda($destinatarioEmail, $nomeEvento, $postiRichiestiOriginalmenteInCoda, $postiOraDisponibiliEvento, $idEvento, $nomeUtenteDestinatario = 'Utente') {
    $subject = "Posti nuovamente disponibili per l'evento: " . htmlspecialchars($nomeEvento);
    $sitoUrlBase = "https://eremofratefrancesco.altervista.org"; // Assicurati che sia HTTPS se il tuo sito lo supporta
    
    // MODIFICA 1: Link punta a eventiincorsoaccesso.html
    $linkPaginaEventi = $sitoUrlBase . "/eventiincorso.html"; 

    // MODIFICA 2: Testo dell'email aggiornato
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
        .email-button { display: inline-block; background-color: #8db187; color: white !important; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: 500; font-size:16px; }
        .email-button:hover { background-color: #365e51; }
        .email-footer { text-align: center; font-size: 0.9em; color: #777777; margin-top: 20px; padding: 15px; background-color: #f0f0f0; border-radius: 0 0 15px 15px;}
        .email-footer p { margin: 5px 0; }
        a { color: #8db187; }
      </style>
    </head>
    <body>
      <div class='email-container'>
        <div class='email-header'><h1>Eremo Frate Francesco</h1></div>
        <div class='email-content'>
          <p>Gentile " . htmlspecialchars($nomeUtenteDestinatario) . ",</p>
          <p>Buone notizie! Si sono liberati dei posti per l'evento \"<strong>" . htmlspecialchars($nomeEvento) . "</strong>\", per il quale eri in lista d'attesa.</p>
          <p>Al momento, sull'evento risultano nuovamente disponibili <strong>" . $postiOraDisponibiliEvento . "</strong> posti.</p>
          <p>Se sei ancora interessato/a, ti invitiamo a visitare la nostra pagina del calendario attività per cercare l'evento e procedere con la prenotazione il prima possibile. 
             <strong>Affrettati, i posti sono limitati e verranno assegnati ai primi che completano la prenotazione!</strong></p>
          <p style='text-align:center;'><a href='" . $linkPaginaEventi . "' class='email-button'>Vai al Calendario Attività</a></p>
          <p>Grazie,<br>Lo staff dell'Eremo Frate Francesco</p>
        </div>
        <div class='email-footer'>
          <p>&copy; " . date("Y") . " Eremo Frate Francesco. Tutti i diritti riservati.</p>
          <p>Se effettuerai una prenotazione per questo evento, verrai automaticamente rimosso dalla lista d'attesa. Se non sei più interessato, puoi ignorare questa email.</p>
          <p><a href='{$sitoUrlBase}'>{$sitoUrlBase}</a></p>
        </div>
      </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: Eremo Frate Francesco <noreply@eremofratefrancesco.altervista.org>' . "\r\n";
    // $headers .= 'Reply-To: indirizzo_reale_per_risposte@example.com' . "\r\n"; // Opzionale

    // Per debug, potresti voler loggare l'email prima dell'invio:
    // file_put_contents('email_debug_coda.log', "To: $destinatarioEmail\nSubject: $subject\nMessage:\n$messaggioHTML\nHeaders:\n$headers\n---\n\n", FILE_APPEND);

    if (mail($destinatarioEmail, $subject, $messaggioHTML, $headers)) {
        error_log("Email di notifica coda inviata con successo a: {$destinatarioEmail} per evento ID {$idEvento}");
        return true;
    } else {
        error_log("ERRORE nell'invio dell'email di notifica coda a: {$destinatarioEmail} per evento ID {$idEvento}. Controllare configurazione mail() PHP e log del server di posta.");
        return false;
    }
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

$idPrenotazione = filter_input(INPUT_POST, 'id_prenotazione', FILTER_VALIDATE_INT);

if (!$idPrenotazione) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID Prenotazione mancante o non valido.']);
    exit;
}

$output = ['success' => false, 'message' => 'Errore durante l\'annullamento.'];

try {
    $conn->beginTransaction();

    $stmtPrenotazione = $conn->prepare("SELECT IDEvento, NumeroPosti, Contatto FROM prenotazioni WHERE Progressivo = :idPrenotazione");
    $stmtPrenotazione->bindParam(':idPrenotazione', $idPrenotazione, PDO::PARAM_INT);
    $stmtPrenotazione->execute();
    $prenotazione = $stmtPrenotazione->fetch();

    if (!$prenotazione) {
        throw new Exception("Prenotazione (ID: {$idPrenotazione}) non trovata.");
    }
    $idEvento = (int)$prenotazione['IDEvento'];
    $postiDaRipristinare = (int)$prenotazione['NumeroPosti'];

    $stmtDelPart = $conn->prepare("DELETE FROM Partecipanti WHERE Progressivo = :idPrenotazione");
    $stmtDelPart->bindParam(':idPrenotazione', $idPrenotazione, PDO::PARAM_INT);
    $stmtDelPart->execute();

    $stmtDelPren = $conn->prepare("DELETE FROM prenotazioni WHERE Progressivo = :idPrenotazione");
    $stmtDelPren->bindParam(':idPrenotazione', $idPrenotazione, PDO::PARAM_INT);
    $stmtDelPren->execute();

    if ($stmtDelPren->rowCount() > 0) {
        $stmtUpdateEvent = $conn->prepare("UPDATE eventi SET PostiDisponibili = PostiDisponibili + :postiRipristinati WHERE IDEvento = :idEvento");
        $stmtUpdateEvent->bindParam(':postiRipristinati', $postiDaRipristinare, PDO::PARAM_INT);
        $stmtUpdateEvent->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtUpdateEvent->execute();

        // Logica di notifica per la coda di attesa
        $stmtEventoInfo = $conn->prepare("SELECT Titolo, PostiDisponibili FROM eventi WHERE IDEvento = :idEvento");
        $stmtEventoInfo->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
        $stmtEventoInfo->execute();
        $eventoInfo = $stmtEventoInfo->fetch();
        
        $nomeEvento = $eventoInfo ? $eventoInfo['Titolo'] : "Evento ID " . $idEvento;
        $postiOraDisponibiliEvento = $eventoInfo ? (int)$eventoInfo['PostiDisponibili'] : 0;

        $emailsInviate = 0;
        if ($postiOraDisponibiliEvento > 0) {
            // Aggiungi una colonna TimestampInserimentoCoda DATETIME DEFAULT CURRENT_TIMESTAMP a utentiincoda e fai ORDER BY TimestampInserimentoCoda ASC
            $stmtCoda = $conn->prepare(
                "SELECT uc.Contatto, uc.NumeroInCoda, ur.Nome, ur.Cognome 
                 FROM utentiincoda uc
                 JOIN utentiregistrati ur ON uc.Contatto = ur.Contatto
                 WHERE uc.IDEvento = :idEvento 
                 ORDER BY uc.Contatto ASC" // AGGIUNGI ORDER BY TimestampInserimentoCoda ASC se aggiungi la colonna
            );
            $stmtCoda->bindParam(':idEvento', $idEvento, PDO::PARAM_INT);
            $stmtCoda->execute();
            $utentiInCoda = $stmtCoda->fetchAll();

            $postiDisponibiliPerNotifica = $postiOraDisponibiliEvento;
            foreach ($utentiInCoda as $utenteCoda) {
                if ($postiDisponibiliPerNotifica <= 0) break; // Non ci sono più posti per cui notificare

                // Notifica solo se i posti richiesti dall'utente in coda sono ora potenzialmente disponibili
                // E se ci sono ancora posti per cui notificare (non necessariamente i *suoi* posti, ma posti in generale)
                // Questo invierà una notifica a più persone se si liberano molti posti.
                // Una logica più complessa potrebbe assegnare "slot" di notifica.
                
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
                // Non decrementare $postiDisponibiliPerNotifica qui a meno che non si voglia "riservare" mentalmente i posti notificati.
                // La notifica informa che CI SONO posti, non che sono riservati.
            }
        }
        
        $conn->commit();
        $msgSuccess = "Prenotazione annullata. {$postiDaRipristinare} posto/i ripristinato/i.";
        if ($emailsInviate > 0) {
            $msgSuccess .= " {$emailsInviate} email di notifica inviate agli utenti in coda.";
        }
        $output = ['success' => true, 'message' => $msgSuccess];
    } else {
        $conn->rollBack();
        throw new Exception("Impossibile annullare la prenotazione (ID: {$idPrenotazione}) o già annullata.");
    }

} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Errore in annulla_prenotazione_admin.php (ID Pren: {$idPrenotazione}): " . $e->getMessage());
    http_response_code(400);
    $output = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$conn = null;
?>