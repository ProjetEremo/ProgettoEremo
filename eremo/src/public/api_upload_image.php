<?php
// File: api_upload_image.php (Assicurati sia nella root o aggiorna il percorso nel JS)
ini_set('display_errors', 0); // NON mostrare errori PHP direttamente nell'output JSON in produzione
error_reporting(E_ALL); // Logga tutti gli errori
// Solo per debug, puoi temporaneamente impostare display_errors a 1:
// ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => 'Errore generico upload.', 'filePath' => null];

// Verifica autenticazione Admin
if (!isset($_SESSION['user_email']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403); // Forbidden
    $response['message'] = 'Accesso non autorizzato. Solo gli amministratori possono caricare immagini.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Metodo non consentito.';
    echo json_encode($response);
    exit;
}

if (!isset($_FILES['imageFile']) || $_FILES['imageFile']['error'] != UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => "File troppo grande (limite server).",
        UPLOAD_ERR_FORM_SIZE  => "File troppo grande (limite form).",
        UPLOAD_ERR_PARTIAL    => "File caricato parzialmente.",
        UPLOAD_ERR_NO_FILE    => "Nessun file inviato.",
        UPLOAD_ERR_NO_TMP_DIR => "Cartella temporanea mancante.",
        UPLOAD_ERR_CANT_WRITE => "Impossibile scrivere il file su disco.",
        UPLOAD_ERR_EXTENSION  => "Upload bloccato da estensione PHP.",
    ];
    $errorCode = isset($_FILES['imageFile']['error']) ? $_FILES['imageFile']['error'] : UPLOAD_ERR_NO_FILE;
    $response['message'] = isset($uploadErrors[$errorCode]) ? $uploadErrors[$errorCode] : "Errore sconosciuto durante l'upload.";
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$file = $_FILES['imageFile'];
// page_name e content_key sono opzionali per la logica di base dell'upload,
// ma utili se vuoi organizzare i file o dare nomi più significativi.
$pageName = isset($_POST['page_name']) ? basename(preg_replace("/[^a-zA-Z0-9_-]/", "", $_POST['page_name'])) : 'global';
$contentKey = isset($_POST['content_key']) ? basename(preg_replace("/[^a-zA-Z0-9_-]/", "", $_POST['content_key'])) : 'image';

// Validazione tipo di file
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// Usa finfo per una validazione MIME più sicura
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$fileMimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($fileMimeType, $allowedMimes) || !in_array($fileExtension, $allowedExtensions)) {
    $response['message'] = 'Tipo di file non consentito. Ammessi: JPG, PNG, GIF, WEBP. Rilevato: ' . htmlspecialchars($fileMimeType);
    http_response_code(415); // Unsupported Media Type
    echo json_encode($response);
    exit;
}

// Validazione dimensione file (es. max 5MB)
$maxFileSize = 5 * 1024 * 1024; // 5 MB
if ($file['size'] > $maxFileSize) {
    $response['message'] = 'File troppo grande. Massimo 5MB.';
    http_response_code(413); // Payload Too Large
    echo json_encode($response);
    exit;
}

// Creazione percorso di salvataggio
// Assicurati che questa cartella esista e sia scrivibile dal server web!
$uploadDirParent = 'uploads'; // Cartella principale per gli upload
$uploadDirPageImages = $uploadDirParent . '/page_images'; // Sottocartella specifica
$uploadDirFinal = $uploadDirPageImages . '/' . $pageName . '/'; // Sottocartella per pagina

// Percorsi assoluti sul server
$basePath = __DIR__; // Directory corrente dello script PHP
$uploadDirAbsolute = $basePath . '/' . $uploadDirFinal;

if (!is_dir($uploadDirAbsolute)) {
    if (!mkdir($uploadDirAbsolute, 0775, true)) {
        $response['message'] = 'Impossibile creare la cartella di destinazione: ' . htmlspecialchars($uploadDirFinal);
        error_log('Failed to create directory: ' . $uploadDirAbsolute . ' - Verifica permessi e percorso.');
        http_response_code(500);
        echo json_encode($response);
        exit;
    }
}

// Genera un nome file univoco per evitare sovrascritture e problemi di cache
// Usa $contentKey per dare un nome più descrittivo, seguito da un hash o timestamp.
$safeContentKey = empty($contentKey) ? 'image' : $contentKey;
$newFileName = $safeContentKey . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
$filePathAbsolute = $uploadDirAbsolute . $newFileName;
$filePathRelative = $uploadDirFinal . $newFileName; // Percorso relativo dalla root del sito

if (move_uploaded_file($file['tmp_name'], $filePathAbsolute)) {
    $response['success'] = true;
    $response['message'] = 'Immagine caricata con successo!';
    // Restituisci il percorso relativo corretto che può essere usato in un tag <img>
    // Assicurati che questo percorso sia accessibile via web.
    $response['filePath'] = $filePathRelative;
} else {
    $response['message'] = 'Errore durante il salvataggio del file sul server.';
    error_log('Failed to move uploaded file from ' . $file['tmp_name'] . ' to: ' . $filePathAbsolute . ' (Errore PHP: ' . error_get_last()['message'] . ')');
    http_response_code(500);
}

echo json_encode($response);
?>