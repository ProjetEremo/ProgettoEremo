<?php
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configurazione del database
$config = [
    'host' => 'localhost',
    'db'   => 'my_eremofratefrancesco',
    'user' => 'eremofratefrancesco',
    'pass' => '' // !!! INSERISCI QUI LA TUA PASSWORD DEL DATABASE !!!
];

// Connessione al database con MySQLi
$conn = new mysqli($config['host'], $config['user'], $config['pass'], $config['db']);

if ($conn->connect_error) {
    http_response_code(500);
    error_log("Errore di connessione al database (prenota_evento.php): " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Errore di connessione al database. Riprova più tardi.']);
    exit;
}
$conn->set_charset("utf8mb4");


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

// Recupero e validazione dati
$eventId = filter_input(INPUT_POST, 'eventId', FILTER_VALIDATE_INT);
$numeroPostiRichiesti = filter_input(INPUT_POST, 'numeroPosti', FILTER_VALIDATE_INT);
$contattoUtente = filter_input(INPUT_POST, 'contatto', FILTER_VALIDATE_EMAIL);

$nomiPartecipanti = isset($_POST['partecipanti_nomi']) && is_array($_POST['partecipanti_nomi']) ? $_POST['partecipanti_nomi'] : [];
$cognomiPartecipanti = isset($_POST['partecipanti_cognomi']) && is_array($_POST['partecipanti_cognomi']) ? $_POST['partecipanti_cognomi'] : [];

$errorMessages = [];
if (!$eventId) $errorMessages[] = 'ID Evento non valido.';
if (!$numeroPostiRichiesti || $numeroPostiRichiesti < 1 || $numeroPostiRichiesti > 5) $errorMessages[] = 'Il numero di posti richiesti deve essere tra 1 e 5.';
if (!$contattoUtente) $errorMessages[] = 'Email del contatto non valida.';
if (count($nomiPartecipanti) !== $numeroPostiRichiesti || count($cognomiPartecipanti) !== $numeroPostiRichiesti) {
    $errorMessages[] = 'Il numero di partecipanti non corrisponde ai nomi/cognomi forniti.';
} else {
    for ($i = 0; $i < $numeroPostiRichiesti; $i++) {
        if (empty(trim($nomiPartecipanti[$i])) || empty(trim($cognomiPartecipanti[$i]))) {
            $errorMessages[] = 'Nome e cognome sono obbligatori per tutti i partecipanti.';
            break;
        }
    }
}

if (!empty($errorMessages)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errorMessages)]);
    exit;
}

try {
    $conn->begin_transaction();

    // 1. Controlla prenotazioni utente
    $stmtCheckUser = $conn->prepare("SELECT SUM(NumeroPosti) FROM prenotazioni WHERE Contatto = ? AND IDEvento = ?");
    $stmtCheckUser->bind_param('si', $contattoUtente, $eventId);
    $stmtCheckUser->execute();
    $resultUser = $stmtCheckUser->get_result();
    $postiGiaPrenotatiUtente = (int)($resultUser->fetch_row()[0] ?? 0);
    $stmtCheckUser->close();

    if (($postiGiaPrenotatiUtente + $numeroPostiRichiesti) > 5) {
        $postiAncoraPrenotabili = 5 - $postiGiaPrenotatiUtente;
        $msg = "Limite di 5 posti per utente superato. " . ($postiAncoraPrenotabili > 0 ? "Puoi prenotarne altri {$postiAncoraPrenotabili}." : "Hai raggiunto il limite.");
        throw new Exception($msg);
    }

    // 2. Controlla evento (CON LOCK)
    $stmtEvento = $conn->prepare("SELECT Titolo, PostiDisponibili, FlagPrenotabile, Data, Durata, Costo, FotoCopertina FROM eventi WHERE IDEvento = ? FOR UPDATE");
    $stmtEvento->bind_param('i', $eventId);
    $stmtEvento->execute();
    $evento = $stmtEvento->get_result()->fetch_assoc();
    $stmtEvento->close();

    if (!$evento) throw new Exception("Evento non trovato.");
    if (!$evento['FlagPrenotabile']) throw new Exception("L'evento '" . htmlspecialchars($evento['Titolo']) . "' non è prenotabile.");
    if ((int)$evento['PostiDisponibili'] < $numeroPostiRichiesti) throw new Exception("Posti non sufficienti. Rimasti: " . $evento['PostiDisponibili']);

    // 3. Inserisci prenotazione
    $stmtPrenotazione = $conn->prepare("INSERT INTO prenotazioni (NumeroPosti, Contatto, IDEvento) VALUES (?, ?, ?)");
    $stmtPrenotazione->bind_param('isi', $numeroPostiRichiesti, $contattoUtente, $eventId);
    $stmtPrenotazione->execute();
    $idPrenotazione = $conn->insert_id;
    $stmtPrenotazione->close();

    // 4. Inserisci partecipanti
    $stmtPartecipante = $conn->prepare("INSERT INTO Partecipanti (Nome, Cognome, Progressivo) VALUES (?, ?, ?)");
    for ($i = 0; $i < $numeroPostiRichiesti; $i++) {
        $nomeSan = trim($nomiPartecipanti[$i]);
        $cognomeSan = trim($cognomiPartecipanti[$i]);
        $stmtPartecipante->bind_param('ssi', $nomeSan, $cognomeSan, $idPrenotazione);
        $stmtPartecipante->execute();
    }
    $stmtPartecipante->close();

    // 5. Aggiorna posti evento
    $nuoviPosti = (int)$evento['PostiDisponibili'] - $numeroPostiRichiesti;
    $stmtAggiorna = $conn->prepare("UPDATE eventi SET PostiDisponibili = ? WHERE IDEvento = ?");
    $stmtAggiorna->bind_param('ii', $nuoviPosti, $eventId);
    $stmtAggiorna->execute();
    $stmtAggiorna->close();

    $conn->commit();

    // 6. Rimuovi da coda di attesa (se presente)
    $stmtRimuoviCoda = $conn->prepare("DELETE FROM utentiincoda WHERE Contatto = ? AND IDEvento = ?");
    $stmtRimuoviCoda->bind_param('si', $contattoUtente, $eventId);
    $stmtRimuoviCoda->execute();
    $stmtRimuoviCoda->close();

    // === BLOCCO INVIO EMAIL (dopo il commit) ===
    try {
        $datiEmail = [
            'info_utente' => ['email' => $contattoUtente],
            'info_evento' => $evento,
            'info_prenotazione' => [
                'id' => $idPrenotazione, 'posti' => $numeroPostiRichiesti,
                'nomi_partecipanti' => $nomiPartecipanti, 'cognomi_partecipanti' => $cognomiPartecipanti
            ]
        ];
        sendBookingConfirmationEmail($datiEmail, $conn);
    } catch (Exception $eMail) {
        error_log("ERRORE INVIO EMAIL conferma per prenotazione #{$idPrenotazione}: " . $eMail->getMessage());
    }
    // === FINE BLOCCO EMAIL ===

    echo json_encode([
        'success' => true,
        'message' => "Prenotazione per '" . htmlspecialchars($evento['Titolo']) . "' effettuata con successo!",
        'idPrenotazione' => $idPrenotazione
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Errore prenotazione (prenota_evento.php): " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}

/**
 * Prepara e invia l'email di conferma prenotazione.
 *
 * @param array $data Dati per l'email.
 * @param mysqli $db Connessione al DB per recuperare dettagli utente.
 */
function sendBookingConfirmationEmail(array $data, mysqli $db) {
    // Recupera nome e cognome dell'utente
    $stmtUtente = $db->prepare("SELECT Nome, Cognome FROM utentiregistrati WHERE Contatto = ?");
    $stmtUtente->bind_param('s', $data['info_utente']['email']);
    $stmtUtente->execute();
    $utente = $stmtUtente->get_result()->fetch_assoc();
    $stmtUtente->close();
    $nomeCompleto = $utente ? trim($utente['Nome'] . ' ' . $utente['Cognome']) : explode('@', $data['info_utente']['email'])[0];

    // Carica il template HTML
    $templatePath = __DIR__ . '/template_email_conferma_prenotazione.html';
    if (!file_exists($templatePath)) {
        error_log("Template email non trovato in: " . $templatePath);
        return;
    }
    $body = file_get_contents($templatePath);

    // Formattazione dati per il template
    setlocale(LC_TIME, 'it_IT.UTF-8');
    $dataFormattata = strftime('%A %d %B %Y', strtotime($data['info_evento']['Data']));
    $costoFormattato = floatval($data['info_evento']['Costo']) > 0 ? '€ ' . number_format($data['info_evento']['Costo'], 2, ',', '.') : 'Offerta libera';
    
    $listaPartecipantiHtml = '<ul>';
    for($i = 0; $i < count($data['info_prenotazione']['nomi_partecipanti']); $i++){
        $nome = htmlspecialchars(trim($data['info_prenotazione']['nomi_partecipanti'][$i]));
        $cognome = htmlspecialchars(trim($data['info_prenotazione']['cognomi_partecipanti'][$i]));
        $listaPartecipantiHtml .= "<li>{$nome} {$cognome}</li>";
    }
    $listaPartecipantiHtml .= '</ul>';

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . $host;
    
    // Sostituzione dei placeholder
    $replacements = [
        '{{NOME_UTENTE}}' => htmlspecialchars($nomeCompleto),
        '{{TITOLO_EVENTO}}' => htmlspecialchars($data['info_evento']['Titolo']),
        '{{IMMAGINE_URL}}' => $data['info_evento']['FotoCopertina'] ? $baseUrl . '/' . ltrim($data['info_evento']['FotoCopertina'], '/') : $baseUrl . '/images/default-event.jpg',
        '{{DATA_EVENTO}}' => ucfirst($dataFormattata),
        '{{ORARIO_EVENTO}}' => htmlspecialchars($data['info_evento']['Durata'] ?: 'Non specificato'),
        '{{ID_PRENOTAZIONE}}' => $data['info_prenotazione']['id'],
        '{{NUMERO_POSTI}}' => $data['info_prenotazione']['posti'],
        '{{COSTO_EVENTO}}' => $costoFormattato,
        '{{BASE_URL}}' => $baseUrl,
        '{{LINK_PAGINA_EVENTI}}' => $baseUrl . '/eventiincorso.html'
    ];

    foreach ($replacements as $placeholder => $value) {
        $body = str_replace($placeholder, $value, $body);
    }
    // Sostituisci la lista dei partecipanti (che è già HTML sicuro)
    $body = str_replace('{{LISTA_PARTECIPANTI}}', $listaPartecipantiHtml, $body);

    // Invio con la funzione mail() di PHP
    $oggetto = 'Conferma della tua prenotazione per: ' . $data['info_evento']['Titolo'];
    $domainName = $_SERVER['HTTP_HOST'] ?? 'eremofratefrancesco.it';
    $headers = "From: Eremo Frate Francesco <noreply@" . $domainName . ">\r\n";
    $headers .= "Reply-To: info@" . $domainName . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    mail($data['info_utente']['email'], $oggetto, $body, $headers);
}
?>

