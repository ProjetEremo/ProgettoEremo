<?php
// Configurazione del database
$host = "localhost";
$username = "eremofratefrancesco"; // Sostituisci con il tuo username DB Altervista
$password = "";                   // Sostituisci con la tua password DB Altervista (spesso è vuota per l'utente principale)
$dbname = "my_eremofratefrancesco";   // Sostituisci con il tuo nome DB Altervista (es. my_tuonomeutente)

define('UPLOAD_DIR', 'uploads/event_images/'); // Assicurati che esista e sia scrivibile! DEVE TERMINARE CON /

// Impostazioni PHP per Produzione
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors_gestione_eventi.log');


header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log("Connessione DB fallita: " . $conn->connect_error);
    die(json_encode(['success' => false, 'message' => "Errore di connessione al database. Controlla i log del server."]));
}
$conn->set_charset("utf8mb4");

// Funzione helper per gestire upload di un file (immagine o volantino)
function handleFileUpload($fileInputName, $allowedExtensions, $existingFilePath = null) {
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES[$fileInputName]['tmp_name'];
        $fileName = basename($_FILES[$fileInputName]['name']);
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $baseName = preg_replace('/[^A-Za-z0-9.\-_]/', '', pathinfo($fileName, PATHINFO_FILENAME));
        $baseName = substr($baseName, 0, 50);
        $newFileName = time() . '_' . uniqid('', true) . '_' . $baseName . '.' . $fileExtension;

        if (in_array($fileExtension, $allowedExtensions)) {
            if (!is_dir(UPLOAD_DIR)) {
                if (!mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
                     error_log('Impossibile creare la cartella di upload: ' . UPLOAD_DIR);
                     return ['success' => false, 'message' => 'Errore server: config. upload dir.'];
                }
            }
            $dest_path = UPLOAD_DIR . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                if ($existingFilePath && file_exists($existingFilePath) && $dest_path !== $existingFilePath) {
                    if(!unlink($existingFilePath)) {
                        error_log("Impossibile eliminare il vecchio file: " . $existingFilePath);
                    }
                }
                return ['success' => true, 'path' => $dest_path];
            } else {
                error_log('Errore move_uploaded_file per ' . $fileInputName . ' a ' . $dest_path . ' (PHP error: ' . $_FILES[$fileInputName]['error'] . ')');
                return ['success' => false, 'message' => 'Errore salvataggio file: ' . $fileInputName];
            }
        } else {
            return ['success' => false, 'message' => 'Tipo file non consentito per ' . $fileInputName . '. Permessi: ' . implode(', ', $allowedExtensions)];
        }
    } elseif (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => $existingFilePath];
    } elseif (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        error_log('Errore upload PHP per ' . $fileInputName . ': error code ' . $_FILES[$fileInputName]['error']);
        return ['success' => false, 'message' => 'Errore caricamento file: ' . $fileInputName . ' (codice: ' . $_FILES[$fileInputName]['error'] . ')'];
    }
    return ['success' => true, 'path' => $existingFilePath];
}

/**
 * Invia email di notifica per un nuovo evento.
 * @param int $eventId L'ID del nuovo evento.
 * @param mysqli $conn L'oggetto di connessione al database.
 */
function inviaNotificheNuovoEvento($eventId, $conn) {
    $stmtEvento = $conn->prepare("SELECT Titolo, Data, Durata, Descrizione, FotoCopertina, Relatore, PrefissoRelatore FROM eventi WHERE IDEvento = ?");
    if (!$stmtEvento) { error_log("Errore prepare SELECT evento per email: " . $conn->error); return; }
    $stmtEvento->bind_param("i", $eventId);
    $stmtEvento->execute();
    $resultEvento = $stmtEvento->get_result();
    $evento = $resultEvento->fetch_assoc();
    $stmtEvento->close();

    if (!$evento) { error_log("Impossibile trovare i dettagli per l'evento ID: " . $eventId); return; }

    $stmtUtenti = $conn->prepare("SELECT Nome, Contatto FROM utentiregistrati WHERE avvisi_eventi = 1");
    if (!$stmtUtenti) { error_log("Errore prepare SELECT utenti per email: " . $conn->error); return; }
    $stmtUtenti->execute();
    $resultUtenti = $stmtUtenti->get_result();
    $utentiDaAvvisare = $resultUtenti->fetch_all(MYSQLI_ASSOC);
    $stmtUtenti->close();

    if (empty($utentiDaAvvisare)) { return; }
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . $domainName;

    $datiEmail = [
        'titolo' => $evento['Titolo'],
        'descrizione' => $evento['Descrizione'],
        'immagine_url' => $evento['FotoCopertina'] ? $baseUrl . '/' . $evento['FotoCopertina'] : $baseUrl . '/images/default-event.jpg',
        'data' => date("d F Y", strtotime($evento['Data'])),
        'relatore' => ($evento['PrefissoRelatore'] ? $evento['PrefissoRelatore'] . ' ' : '') . $evento['Relatore'],
        'orario' => $evento['Durata'],
        'link_evento' => $baseUrl . '/eventiincorso.html',
        'base_url' => $baseUrl
    ];

    $oggetto = "Nuovo Evento all'Eremo: " . $evento['Titolo'];
    $headers = "From: Eremo Frate Francesco <noreply@" . $domainName . ">\r\n";
    $headers .= "Reply-To: noreply@" . $domainName . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    foreach ($utentiDaAvvisare as $utente) {
        $datiEmail['nome_utente'] = $utente['Nome'];
        $corpoEmail = preparaCorpoEmail('template_email_nuovo_evento.html', $datiEmail);
        if ($corpoEmail) {
            mail($utente['Contatto'], $oggetto, $corpoEmail, $headers);
        }
    }
}

/**
 * NUOVA FUNZIONE: Invia email di notifica per un evento annullato.
 * @param int $eventId L'ID dell'evento annullato.
 * @param mysqli $conn L'oggetto di connessione al database.
 */
function inviaNotificheAnnullamentoEvento($eventId, $conn) {
    // 1. Recupera i dettagli dell'evento
    $stmtEvento = $conn->prepare("SELECT Titolo, Data FROM eventi WHERE IDEvento = ?");
    if (!$stmtEvento) { error_log("Errore prepare SELECT evento per email annullamento: " . $conn->error); return; }
    $stmtEvento->bind_param("i", $eventId);
    $stmtEvento->execute();
    $resultEvento = $stmtEvento->get_result();
    $evento = $resultEvento->fetch_assoc();
    $stmtEvento->close();

    if (!$evento) { error_log("Impossibile trovare dettagli per evento da annullare ID: " . $eventId); return; }

    // 2. Recupera tutti gli utenti PRENOTATI a questo specifico evento
    $stmtUtenti = $conn->prepare("
        SELECT u.Nome, u.Contatto 
        FROM utentiregistrati u
        JOIN prenotazioni p ON u.Contatto = p.Contatto
        WHERE p.IDEvento = ?
    ");
    if (!$stmtUtenti) { error_log("Errore prepare SELECT utenti prenotati: " . $conn->error); return; }
    $stmtUtenti->bind_param("i", $eventId);
    $stmtUtenti->execute();
    $resultUtenti = $stmtUtenti->get_result();
    $utentiPrenotati = $resultUtenti->fetch_all(MYSQLI_ASSOC);
    $stmtUtenti->close();

    if (empty($utentiPrenotati)) { return; } // Nessun prenotato, nessun avviso da inviare.

    // 3. Prepara i dati per l'email
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . $domainName;

    $datiEmail = [
        'titolo_evento' => $evento['Titolo'],
        'data_evento' => date("d F Y", strtotime($evento['Data'])),
        'link_calendario' => $baseUrl . '/eventiincorso.html',
        'base_url' => $baseUrl
    ];

    $oggetto = "Annullamento Evento: " . $evento['Titolo'];
    $headers = "From: Eremo Frate Francesco <noreply@" . $domainName . ">\r\n";
    $headers .= "Reply-To: noreply@" . $domainName . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    // 4. Invia email a ogni utente prenotato
    foreach ($utentiPrenotati as $utente) {
        $datiEmail['nome_utente'] = $utente['Nome'];
        $corpoEmail = preparaCorpoEmail('template_email_annullamento_evento.html', $datiEmail);
        if ($corpoEmail) {
            mail($utente['Contatto'], $oggetto, $corpoEmail, $headers);
        }
    }
}

/**
 * NUOVA FUNZIONE HELPER: Costruisce il corpo HTML dell'email partendo da un template.
 * @param string $templateFileName Il nome del file del template.
 * @param array $dati I dati da inserire nel template.
 * @return string|false Il corpo dell'email in HTML o false in caso di errore.
 */
function preparaCorpoEmail($templateFileName, $dati) {
    $templatePath = __DIR__ . '/' . $templateFileName;
    if (!file_exists($templatePath)) {
        error_log("Template email non trovato in: " . $templatePath);
        return false;
    }

    $template = file_get_contents($templatePath);
    
    foreach ($dati as $key => $value) {
        $template = str_replace('{{' . strtoupper($key) . '}}', htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), $template);
    }
    
    return $template;
}


$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add_event' || $action === 'update_event') {
        $eventId = ($action === 'update_event') ? ($_POST['event-id'] ?? null) : null;

        $required = ['event-title', 'event-date', 'event-speaker', 'event-seats', 'event-short-desc'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                die(json_encode(['success' => false, 'message' => "Campo '$field' obbligatorio."]));
            }
        }
        if ($action === 'update_event' && (empty($eventId) || !ctype_digit((string)$eventId))) {
             die(json_encode(['success' => false, 'message' => "ID evento non valido per l'aggiornamento."]));
        }

        if ($action === 'update_event') {
            $posti_gia_prenotati = 0;
            $stmt_booked = $conn->prepare("SELECT SUM(NumeroPosti) FROM prenotazioni WHERE IDEvento = ?");
            if ($stmt_booked) {
                $stmt_booked->bind_param("i", $eventId);
                $stmt_booked->execute();
                $stmt_booked->bind_result($posti_gia_prenotati);
                $stmt_booked->fetch();
                $stmt_booked->close();
                $posti_gia_prenotati = (int)$posti_gia_prenotati;
            } else {
                error_log("Errore prepare SELECT SUM(NumeroPosti): " . $conn->error);
                die(json_encode(['success' => false, 'message' => 'Errore server nel verificare le prenotazioni esistenti.']));
            }
            
            $nuova_capacita_totale = intval($_POST['event-seats']);

            if ($nuova_capacita_totale < $posti_gia_prenotati) {
                die(json_encode([
                    'success' => false,
                    'message' => "Modifica non consentita: la nuova capacità ($nuova_capacita_totale posti) è inferiore al numero di posti già prenotati ($posti_gia_prenotati)."
                ]));
            }
        }

        $titolo = trim($_POST['event-title']);
        $data = trim($_POST['event-date']);
        $orario = trim($_POST['event-time'] ?? '');
        $descrizione = trim($_POST['event-short-desc']);
        $descrizione_estesa = trim($_POST['event-long-desc'] ?? '');
        $prefisso_relatore = trim($_POST['event-prefix'] ?? 'Relatore');
        $relatore = trim($_POST['event-speaker']);
        $associazione = trim($_POST['event-association'] ?? '');
        
        $posti_disponibili = intval($_POST['event-seats']);
        $prenotabile = isset($_POST['event-booking']) && $_POST['event-booking'] === 'on' ? 1 : 0;
        $costo = (isset($_POST['event-voluntary']) && $_POST['event-voluntary'] === 'on') ? 0.00 : floatval(str_replace(',', '.', $_POST['event-price'] ?? 0.00));


        $percorso_foto_copertina_db = null;
        $percorso_volantino_db = null;

        if ($action === 'update_event') {
            $stmt_select_paths = $conn->prepare("SELECT FotoCopertina, VolantinoUrl FROM eventi WHERE IDEvento = ?");
            if ($stmt_select_paths) {
                $stmt_select_paths->bind_param("i", $eventId);
                $stmt_select_paths->execute();
                $stmt_select_paths->bind_result($percorso_foto_copertina_db, $percorso_volantino_db);
                $stmt_select_paths->fetch();
                $stmt_select_paths->close();
            } else { error_log("Errore prep select percorsi esistenti: " . $conn->error); }
        }

        $uploadCopertinaResult = handleFileUpload('event-image', ['jpg', 'jpeg', 'png', 'gif', 'webp'], $percorso_foto_copertina_db);
        if (!$uploadCopertinaResult['success']) { die(json_encode($uploadCopertinaResult)); }
        $percorso_foto_copertina_db = $uploadCopertinaResult['path'];

        $uploadVolantinoResult = handleFileUpload('event-flyer', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'], $percorso_volantino_db);
        if (!$uploadVolantinoResult['success']) { die(json_encode($uploadVolantinoResult)); }
        $percorso_volantino_db = $uploadVolantinoResult['path'];

        if ($action === 'add_event') {
            $sql = "INSERT INTO eventi (Titolo, Data, Durata, Descrizione, DescrizioneEstesa, Associazione, FlagPrenotabile, PostiDisponibili, Costo, PrefissoRelatore, Relatore, FotoCopertina, VolantinoUrl, Stato, IDCategoria)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'attivo', NULL)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("Errore prepare INSERT: " . $conn->error);
                die(json_encode(['success' => false, 'message' => 'Errore server (DB Insert).']));
            }
            $stmt->bind_param("ssssssiidssss",
                $titolo, $data, $orario, $descrizione, $descrizione_estesa, $associazione,
                $prenotabile, $posti_disponibili, $costo,
                $prefisso_relatore, $relatore,
                $percorso_foto_copertina_db, $percorso_volantino_db
            );
        } else { // update_event
            $sql = "UPDATE eventi SET Titolo=?, Data=?, Durata=?, Descrizione=?, DescrizioneEstesa=?, Associazione=?, FlagPrenotabile=?, PostiDisponibili=?, Costo=?, PrefissoRelatore=?, Relatore=?, FotoCopertina=?, VolantinoUrl=?
                    WHERE IDEvento = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("Errore prepare UPDATE: " . $conn->error);
                die(json_encode(['success' => false, 'message' => 'Errore server (DB Update).']));
            }
            $stmt->bind_param("ssssssiidssssi",
                $titolo, $data, $orario, $descrizione, $descrizione_estesa, $associazione,
                $prenotabile, $posti_disponibili, $costo,
                $prefisso_relatore, $relatore,
                $percorso_foto_copertina_db, $percorso_volantino_db,
                $eventId
            );
        }

        if ($stmt->execute()) {
            $new_event_id = ($action === 'add_event') ? $conn->insert_id : $eventId;
            
            if ($action === 'add_event') {
                inviaNotificheNuovoEvento($new_event_id, $conn);
            }

            $message = ($action === 'add_event') ? 'Evento aggiunto con successo!' : 'Evento aggiornato con successo!';
            echo json_encode(['success' => true, 'message' => $message, 'event_id' => $new_event_id]);
        } else {
            error_log("Errore execute $action: " . $stmt->error . " SQL: " . $sql);
            echo json_encode(['success' => false, 'message' => 'Errore durante il salvataggio nel database: ' . $stmt->error]);
        }
        $stmt->close();

    } elseif ($action === 'delete_event') {
        $eventId = $_POST['event_id'] ?? null;
        if (empty($eventId) || !ctype_digit((string)$eventId)) {
            die(json_encode(['success' => false, 'message' => 'ID evento non valido.']));
        }

        // ========== INIZIO LOGICA DI ANNULLAMENTO EVENTO (SOSTITUISCE LA DELETE) ==========
        
        // 1. (Opzionale, ma consigliato) Invia le notifiche di annullamento PRIMA di modificare lo stato.
        // Assicurati che la funzione inviaNotificheAnnullamentoEvento sia presente nel file.
        inviaNotificheAnnullamentoEvento($eventId, $conn);

        // 2. Aggiorna lo stato dell'evento a 'cancellato' e lo rende non prenotabile.
        $stmt_update_status = $conn->prepare("UPDATE eventi SET Stato = 'cancellato', FlagPrenotabile = 0 WHERE IDEvento = ?");
        if ($stmt_update_status) {
            $stmt_update_status->bind_param("i", $eventId);
            if ($stmt_update_status->execute()) {
                if ($stmt_update_status->affected_rows > 0) {
                    echo json_encode(['success' => true, 'message' => 'Evento annullato con successo e notifiche inviate.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Evento non trovato o già annullato.']);
                }
            } else {
                error_log("Errore nell'aggiornare lo stato dell'evento a cancellato: " . $stmt_update_status->error);
                echo json_encode(['success' => false, 'message' => 'Errore durante l\'annullamento dell\'evento nel DB.']);
            }
            $stmt_update_status->close();
        } else {
            error_log("Errore nella preparazione dello statement per annullare l'evento: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Errore server (DB Update Status).']);
        }
        // ========== FINE LOGICA DI ANNULLAMENTO EVENTO ==========

    } else {
        echo json_encode(['success' => false, 'message' => 'Azione non valida o non specificata.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Metodo di richiesta non valido.']);
}
$conn->close();
?>


