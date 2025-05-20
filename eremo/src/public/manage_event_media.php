<?php
header('Content-Type: application/json; charset=utf-8');

// Configurazione DB e connessione
$host = "localhost";
$username = "eremofratefrancesco"; // Il tuo username DB
$password = "";                   // La tua password DB
$dbname = "my_eremofratefrancesco";   // Il tuo nome DB
define('EVENT_MEDIA_UPLOAD_DIR', 'uploads/event_gallery/');

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors_manage_media.log');

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("DB Connection failed: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => "Errore di connessione al database."]);
    exit;
}
$conn->set_charset("utf8mb4");

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$output = ['success' => false, 'message' => 'Azione non valida.'];

function getMediaTypeFromExtension($extension) {
    $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $videoExt = ['mp4', 'webm', 'ogg', 'mov', 'avi'];
    if (in_array(strtolower($extension), $imageExt)) return 'image';
    if (in_array(strtolower($extension), $videoExt)) return 'video';
    return 'other';
}

function handleMediaFileUpload($fileInputArray, $allowedExtensions, $uploadDir) {
    $uploadedFilesMeta = [];
    $errors = [];
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return ['files' => [], 'errors' => ['Impossibile creare la cartella di upload: ' . $uploadDir]];
        }
    }
    if (isset($fileInputArray) && is_array($fileInputArray['name'])) {
        $fileCount = count($fileInputArray['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($fileInputArray['error'][$i] === UPLOAD_ERR_OK) {
                $fileTmpPath = $fileInputArray['tmp_name'][$i];
                $fileName = basename($fileInputArray['name'][$i]);
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $baseName = preg_replace('/[^A-Za-z0-9.\-_]/', '', pathinfo($fileName, PATHINFO_FILENAME));
                $baseName = substr($baseName, 0, 50);
                $newFileName = time() . '_' . uniqid('', true) . '_' . $baseName . '.' . $fileExtension;
                if (in_array($fileExtension, $allowedExtensions)) {
                    $dest_path = $uploadDir . $newFileName;
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        $uploadedFilesMeta[] = ['percorso' => $dest_path, 'original_name' => $fileName];
                    } else {
                        $errors[] = 'Errore nel salvataggio del file sul server: ' . $fileName;
                        error_log("move_uploaded_file failed for {$fileName} to {$dest_path}. Check permissions and path.");
                    }
                } else {
                    $errors[] = 'Tipo di file non permesso per ' . $fileName . '. Estensione: ' . $fileExtension;
                }
            } elseif ($fileInputArray['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Errore di upload PHP per ' . ($fileInputArray['name'][$i] ?? 'file sconosciuto') . ': codice ' . $fileInputArray['error'][$i];
            }
        }
    } else if (isset($fileInputArray) && isset($fileInputArray['error']) && $fileInputArray['error'] !== UPLOAD_ERR_NO_FILE && $fileInputArray['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Errore di upload PHP per ' . ($fileInputArray['name'] ?? 'file sconosciuto') . ': codice ' . $fileInputArray['error'];
    }
    return ['files' => $uploadedFilesMeta, 'errors' => $errors];
}

if ($action === 'upload_media') {
    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    // Recupera la descrizione dal POST
    $description_media = $conn->real_escape_string(trim($_POST['description'] ?? ''));

    if (!$eventId) {
        $output['message'] = 'ID Evento mancante.';
        echo json_encode($output);
        exit;
    }
    $eventCheckStmt = $conn->prepare("SELECT IDEvento FROM eventi WHERE IDEvento = ?");
    if (!$eventCheckStmt) {
        error_log("DB Prepare error (event check): " . $conn->error);
        $output['message'] = "Errore database durante la verifica dell'evento.";
        echo json_encode($output);
        exit;
    }
    $eventCheckStmt->bind_param("i", $eventId);
    $eventCheckStmt->execute();
    $eventResult = $eventCheckStmt->get_result();
    if ($eventResult->num_rows === 0) {
        $output['message'] = 'Evento specificato non trovato.';
        echo json_encode($output);
        exit;
    }
    $eventCheckStmt->close();

    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'webm', 'avi'];
    $uploadResult = handleMediaFileUpload($_FILES['mediaFiles'], $allowedExt, EVENT_MEDIA_UPLOAD_DIR);

    $allErrors = $uploadResult['errors'];
    $successCount = 0;

    if (!empty($uploadResult['files'])) {
        // AGGIORNATO: Query SQL per includere Descrizione
        $stmt = $conn->prepare("INSERT INTO media (IDEvento, Percorso, Descrizione) VALUES (?, ?, ?)");
        if (!$stmt) {
            error_log("DB Prepare error (insert media): " . $conn->error);
            $allErrors[] = "Errore di preparazione del database per l'inserimento dei media.";
        } else {
            foreach ($uploadResult['files'] as $mediaFile) {
                // AGGIORNATO: bind_param per includere la descrizione
                $stmt->bind_param("iss", $eventId, $mediaFile['percorso'], $description_media);
                if ($stmt->execute()) {
                    $successCount++;
                } else {
                    error_log("DB Execute error (insert media): " . $stmt->error . " for file " . $mediaFile['percorso']);
                    $allErrors[] = "Errore nel salvataggio di " . $mediaFile['original_name'] . " nel database.";
                    if (file_exists($mediaFile['percorso'])) {
                        unlink($mediaFile['percorso']);
                    }
                }
            }
            $stmt->close();
        }
    } elseif (empty($allErrors)) {
        $allErrors[] = 'Nessun file valido è stato selezionato o ricevuto per il caricamento.';
    }

    if ($successCount > 0) {
        $output['success'] = true;
        $output['message'] = "$successCount file multimediale(i) caricato(i) con successo.";
        if (!empty($allErrors)) {
            $output['message'] .= " Tuttavia, si sono verificati alcuni errori durante il processo: " . implode("; ", $allErrors);
        }
    } else {
        $output['success'] = false;
        if (!empty($allErrors)) {
            $output['message'] = "Caricamento fallito. Errori: " . implode("; ", $allErrors);
        } else {
            $output['message'] = "Caricamento fallito. Nessun file è stato processato o salvato.";
        }
    }
} elseif ($action === 'get_event_media') {
    $eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
    if (!$eventId) {
        $output['message'] = 'ID Evento mancante.';
        echo json_encode($output);
        exit;
    }
    $mediaItems = [];
    // AGGIORNATO: Query SQL per includere Descrizione
    $sql = "SELECT Progressivo as id_media, IDEvento, Percorso as url_media, Descrizione
            FROM media
            WHERE IDEvento = ?
            ORDER BY Progressivo DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("DB Prepare error (get_event_media): " . $conn->error);
        $output['message'] = 'Errore del database nel recuperare i media.';
        echo json_encode($output);
        exit;
    }
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (isset($row['url_media'])) {
            $row['tipo_media'] = getMediaTypeFromExtension(pathinfo($row['url_media'], PATHINFO_EXTENSION));
        } else {
            $row['tipo_media'] = 'other';
        }
        // Descrizione è già inclusa da $row = $result->fetch_assoc()
        $mediaItems[] = $row;
    }
    $stmt->close();
    $output = ['success' => true, 'media' => $mediaItems];
} elseif ($action === 'delete_event_media') {
    // Questa sezione rimane invariata, dato che l'eliminazione si basa su Progressivo
    // e il file fisico su Percorso, entrambi già gestiti correttamente.
    $mediaId = filter_input(INPUT_POST, 'media_id', FILTER_VALIDATE_INT);
    if (!$mediaId) {
        $output['message'] = 'ID Media (Progressivo) mancante.';
        echo json_encode($output);
        exit;
    }
    $stmtSelect = $conn->prepare("SELECT Percorso FROM media WHERE Progressivo = ?");
    if (!$stmtSelect) {
        error_log("DB Prepare error (select media for delete): " . $conn->error);
        $output['message'] = 'Errore DB (selezione).';
        echo json_encode($output);
        exit;
    }
    $stmtSelect->bind_param("i", $mediaId);
    $stmtSelect->execute();
    $resultSelect = $stmtSelect->get_result();
    $mediaPath = null;
    if ($row = $resultSelect->fetch_assoc()) {
        $mediaPath = $row['Percorso'];
    }
    $stmtSelect->close();

    if ($mediaPath) {
        $stmtDelete = $conn->prepare("DELETE FROM media WHERE Progressivo = ?");
        if (!$stmtDelete) {
            error_log("DB Prepare error (delete media): " . $conn->error);
            $output['message'] = 'Errore DB (eliminazione).';
            echo json_encode($output);
            exit;
        }
        $stmtDelete->bind_param("i", $mediaId);
        if ($stmtDelete->execute()) {
            if ($stmtDelete->affected_rows > 0) {
                if (file_exists($mediaPath) && is_file($mediaPath)) {
                    if (!unlink($mediaPath)) {
                        error_log("Fallita eliminazione del file fisico: " . $mediaPath . ". Controllare i permessi.");
                    }
                } else {
                    error_log("File fisico non trovato o non è un file durante l'eliminazione: " . $mediaPath);
                }
                $output = ['success' => true, 'message' => 'Media eliminato con successo dal database.'];
            } else {
                $output['message'] = 'Media non trovato nel database (ID: ' . $mediaId . ') o nessuna riga modificata.';
            }
        } else {
            error_log("DB Execute error (delete media): " . $stmtDelete->error);
            $output['message'] = 'Errore durante l\'eliminazione del media dal database.';
        }
        $stmtDelete->close();
    } else {
        $output['message'] = 'Record media non trovato nel database per l\'eliminazione (ID: ' . $mediaId . ').';
    }
}

$conn->close();
echo json_encode($output);
?>