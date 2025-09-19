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

        // MODIFICA CORRETTIVA: Blocco per la gestione dei posti in aggiornamento
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
        // MODIFICA: Gestione del costo come float
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
            $sql = "INSERT INTO eventi (Titolo, Data, Durata, Descrizione, DescrizioneEstesa, Associazione, FlagPrenotabile, PostiDisponibili, Costo, PrefissoRelatore, Relatore, FotoCopertina, VolantinoUrl, IDCategoria)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("Errore prepare INSERT: " . $conn->error);
                die(json_encode(['success' => false, 'message' => 'Errore server (DB Insert).']));
            }
            // MODIFICA: Cambiato il tipo di 'Costo' da 'i' a 'd' (da integer a double)
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
            // MODIFICA: Cambiato il tipo di 'Costo' da 'i' a 'd' (da integer a double)
            $stmt->bind_param("ssssssiidssssi",
                $titolo, $data, $orario, $descrizione, $descrizione_estesa, $associazione,
                $prenotabile, $posti_disponibili, $costo,
                $prefisso_relatore, $relatore,
                $percorso_foto_copertina_db, $percorso_volantino_db,
                $eventId
            );
        }

        if ($stmt->execute()) {
            $message = ($action === 'add_event') ? 'Evento aggiunto con successo!' : 'Evento aggiornato con successo!';
            echo json_encode(['success' => true, 'message' => $message, 'event_id' => ($action === 'add_event' ? $conn->insert_id : $eventId)]);
        } else {
            error_log("Errore execute $action: " . $stmt->error . " SQL: " . $sql);
            echo json_encode(['success' => false, 'message' => 'Errore durante il salvataggio nel database: ' . $stmt->error]);
        }
        $stmt->close();

    } elseif ($action === 'delete_event') {
        $eventId = $_POST['event_id'] ?? null;
        if (empty($eventId) || !ctype_digit((string)$eventId)) {
            die(json_encode(['success' => false, 'message' => 'ID evento non valido per eliminazione.']));
        }

        $fotoPath = null; $volantinoPath = null;
        $stmt_select_files = $conn->prepare("SELECT FotoCopertina, VolantinoUrl FROM eventi WHERE IDEvento = ?");
        if ($stmt_select_files) {
            $stmt_select_files->bind_param("i", $eventId); $stmt_select_files->execute();
            $stmt_select_files->bind_result($fotoPath, $volantinoPath); $stmt_select_files->fetch();
            $stmt_select_files->close();
        } else { error_log("Errore prep select for delete files: " . $conn->error); }

        $stmt_delete = $conn->prepare("DELETE FROM eventi WHERE IDEvento = ?");
        if ($stmt_delete) {
            $stmt_delete->bind_param("i", $eventId);
            if ($stmt_delete->execute()) {
                if ($stmt_delete->affected_rows > 0) {
                    if ($fotoPath && file_exists($fotoPath)) { if(!unlink($fotoPath)) error_log("Impossibile eliminare FotoCopertina: ".$fotoPath);}
                    if ($volantinoPath && file_exists($volantinoPath)) { if(!unlink($volantinoPath)) error_log("Impossibile eliminare VolantinoUrl: ".$volantinoPath);}
                    echo json_encode(['success' => true, 'message' => 'Evento eliminato con successo.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Evento non trovato o già eliminato.']);
                }
            } else { error_log("Errore exec delete: " . $stmt_delete->error); echo json_encode(['success' => false, 'message' => 'Errore eliminazione dal DB.']);}
            $stmt_delete->close();
        } else { error_log("Errore prep delete: " . $conn->error); echo json_encode(['success' => false, 'message' => 'Errore server (DB Delete).']);}
    } else {
        echo json_encode(['success' => false, 'message' => 'Azione non valida o non specificata.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Metodo di richiesta non valido.']);
}
$conn->close();
?>