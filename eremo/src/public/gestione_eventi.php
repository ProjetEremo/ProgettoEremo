<?php
// Configurazione del database
$host = "localhost";
$username = "eremofratefrancesco";
$password = "";
$dbname = "my_eremofratefrancesco";

// Connessione al database
$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => "Connessione fallita: " . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_event') {
    // Dati obbligatori
    $required = ['event-title', 'event-date', 'event-speaker', 'event-seats', 'event-short-desc'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            die(json_encode(['success' => false, 'message' => "Il campo $field è obbligatorio"]));
        }
    }

    // Preparazione dati con valori di default
    $titolo = $conn->real_escape_string($_POST['event-title']);
    $data = $conn->real_escape_string($_POST['event-date']);
    $orario = $conn->real_escape_string($_POST['event-time'] ?? '');
    $descrizione = $conn->real_escape_string($_POST['event-short-desc']);
    $descrizione_estesa = $conn->real_escape_string($_POST['event-long-desc'] ?? '');
    $prefisso_relatore = $conn->real_escape_string($_POST['event-prefix'] ?? 'Relatore');
    $relatore = $conn->real_escape_string($_POST['event-speaker']);
    $associazione = $conn->real_escape_string($_POST['event-association'] ?? '');
    $posti_disponibili = intval($_POST['event-seats']);
    $prenotabile = isset($_POST['event-booking']) ? 1 : 0;
    $costo_volontario = isset($_POST['event-voluntary']) ? 1 : 0;
    $costo = $costo_volontario ? 0 : floatval($_POST['event-price'] ?? 0);

    // Gestione immagine (opzionale)
    $foto_copertina = null;
    $foto_param_type = 'b';
    if (isset($_FILES['event-image']) && $_FILES['event-image']['error'] === UPLOAD_ERR_OK) {
        $foto_copertina = file_get_contents($_FILES['event-image']['tmp_name']);
    } else {
        // Se non viene fornita un'immagine, impostiamo il parametro a NULL
        $foto_param_type = 's'; // Cambiamo il tipo a stringa per NULL
        $foto_copertina = NULL;
    }

    // Query con gestione condizionale dell'immagine
    if ($foto_copertina !== NULL) {
        $sql = "INSERT INTO eventi (
            Titolo, Data, Durata, Descrizione, DescrizioneEstesa,
            Associazione, FlagPrenotabile, PostiDisponibili, Costo,
            PrefissoRelatore, Relatore, FotoCopertina
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die(json_encode(['success' => false, 'message' => "Errore preparazione query: " . $conn->error]));
        }

        $stmt->bind_param(
            "ssssssiiidss",
            $titolo, $data, $orario, $descrizione, $descrizione_estesa,
            $associazione, $prenotabile, $posti_disponibili, $costo,
            $prefisso_relatore, $relatore, $foto_copertina // Aggiunta $foto_copertina
        );
    }else {
        $sql = "INSERT INTO eventi (
            Titolo, Data, Durata, Descrizione, DescrizioneEstesa,
            Associazione, FlagPrenotabile, PostiDisponibili, Costo,
            PrefissoRelatore, Relatore
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die(json_encode(['success' => false, 'message' => "Errore preparazione query: " . $conn->error]));
        }

        $stmt->bind_param(
            "ssssssiiids",
            $titolo, $data, $orario, $descrizione, $descrizione_estesa,
            $associazione, $prenotabile, $posti_disponibili, $costo,
            $prefisso_relatore, $relatore
        );
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Evento aggiunto con successo!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore durante il salvataggio: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Richiesta non valida']);
}

$conn->close();
?>