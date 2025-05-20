<?php
// generate_event_pdf.php

// 1. Assicurati che il percorso alla libreria FPDF sia corretto!
// Se hai messo la cartella 'fpdf' nella stessa directory di questo script:
require_once('fpdf/fpdf.php');
// Altrimenti, adatta il percorso: require_once('../libs/fpdf/fpdf.php'); ecc.

// --- Inizio Connessione e Recupero Dati ---
$host = "localhost";
$username = "eremofratefrancesco"; // Il tuo username DB
$password = "";                   // La tua password DB
$dbname = "my_eremofratefrancesco";   // Il tuo nome DB

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    // Se c'è un errore DB prima di generare il PDF, invia un errore testuale semplice
    // o un JSON se preferisci gestirlo diversamente dal client.
    // Per ora, un semplice messaggio di errore.
    header('Content-Type: text/plain; charset=utf-8');
    die("Errore di connessione al database: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
if (!$eventId) {
    header('Content-Type: text/plain; charset=utf-8');
    die('ID Evento mancante o non valido. Impossibile generare PDF.');
}

// Recupera dettagli evento
$stmtEvent = $conn->prepare("SELECT Titolo, Data, Durata, Relatore, PrefissoRelatore, Associazione FROM eventi WHERE IDEvento = ?");
if (!$stmtEvent) {
    header('Content-Type: text/plain; charset=utf-8');
    die("Errore nella preparazione della query evento: " . $conn->error);
}
$stmtEvent->bind_param("i", $eventId);
$stmtEvent->execute();
$resultEvent = $stmtEvent->get_result();
if ($resultEvent->num_rows === 0) {
    header('Content-Type: text/plain; charset=utf-8');
    die('Evento non trovato. Impossibile generare PDF.');
}
$eventDetails = $resultEvent->fetch_assoc(); // Rinomino in $eventDetails per chiarezza
$stmtEvent->close();

// Recupera partecipanti
// La query sembra corretta per ottenere nome, cognome del partecipante e contatto di chi ha prenotato
$stmtParticipants = $conn->prepare(
    "SELECT p.Nome, p.Cognome, pr.Contatto
     FROM Partecipanti p
     JOIN prenotazioni pr ON p.Progressivo = pr.Progressivo
     WHERE pr.IDEvento = ? ORDER BY p.Cognome, p.Nome"
);
if (!$stmtParticipants) {
    header('Content-Type: text/plain; charset=utf-8');
    die("Errore nella preparazione della query partecipanti: " . $conn->error);
}
$stmtParticipants->bind_param("i", $eventId);
$stmtParticipants->execute();
$resultParticipants = $stmtParticipants->get_result();
$participants = [];
while($row = $resultParticipants->fetch_assoc()){
    $participants[] = $row;
}
$stmtParticipants->close();
$conn->close();

// --- Logica di Generazione PDF con FPDF ---

class PDF_Event_Participants extends FPDF {
    private $eventData; // Proprietà per memorizzare i dati dell'evento

    // Costruttore per passare i dati dell'evento
    function __construct($orientation='P', $unit='mm', $size='A4', $event_data = []) {
        parent::__construct($orientation, $unit, $size);
        $this->eventData = $event_data;
        $this->SetMargins(15, 15, 15); // Margini sinistro, alto, destro
        $this->SetAutoPageBreak(true, 15); // Margine inferiore per il page break
    }

    // Intestazione personalizzata
    function Header() {
        if (empty($this->eventData)) return;

        // Logo (opzionale, se hai un logo)
        // $this->Image('path/to/logo.png',10,6,30); // Esempio: Logo a 10mm da sx, 6mm da top, larghezza 30mm

        $this->SetFont('Arial','B',16); // Font più grande per il titolo
        // Multicell per il titolo se può essere lungo e andare a capo
        $this->MultiCell(0, 10, utf8_decode("Report Evento: " . $this->eventData['Titolo']), 0, 'C');
        $this->Ln(2); // Spazio ridotto dopo il titolo

        $this->SetFont('Arial','',10);
        $dataFormatted = $this->eventData['Data'] ? date("d/m/Y", strtotime($this->eventData['Data'])) : 'N/D';
        $durata = $this->eventData['Durata'] ?? 'N/D';
        $this->Cell(0, 7, utf8_decode("Data: " . $dataFormatted . "  |  Orario/Durata: " . $durata), 0, 1, 'C');

        if (!empty($this->eventData['Relatore'])) {
            $relatoreInfo = ($this->eventData['PrefissoRelatore'] ?? 'Relatore') . ": " . $this->eventData['Relatore'];
            if (!empty($this->eventData['Associazione'])) {
                $relatoreInfo .= " (" . $this->eventData['Associazione'] . ")";
            }
            $this->Cell(0, 7, utf8_decode($relatoreInfo), 0, 1, 'C');
        } elseif (!empty($this->eventData['Associazione'])) {
             $this->Cell(0, 7, utf8_decode("Organizzato da: " . $this->eventData['Associazione']), 0, 1, 'C');
        }

        $this->Ln(7); // Spazio prima della tabella o del contenuto principale
    }

    // Piè di pagina personalizzato
    function Footer() {
        $this->SetY(-15); // Posizione a 1.5 cm dal basso
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10, utf8_decode('Pagina ').$this->PageNo().'/{nb}',0,0,'C'); // Numero pagina
    }

    // Tabella partecipanti migliorata
    function ParticipantTable($header, $data) {
        $this->SetFillColor(230, 230, 230); // Colore di sfondo per l'header della tabella
        $this->SetTextColor(0);
        $this->SetDrawColor(128, 128, 128); // Colore bordi tabella
        $this->SetFont('Arial','B',10);

        // Larghezze colonne: Num., Cognome, Nome, Contatto Email
        // Larghezza totale A4 (210mm) - margini (15mm sx + 15mm dx = 30mm) = 180mm disponibili
        $w = array(15, 55, 55, 55); // Adatta queste larghezze se necessario

        for($i=0; $i<count($header); $i++) {
            $this->Cell($w[$i], 8, utf8_decode($header[$i]), 1, 0, 'C', true); // Header con sfondo
        }
        $this->Ln();

        $this->SetFont('Arial','',9); // Font più piccolo per i dati della tabella
        $this->SetFillColor(245, 245, 245); // Sfondo alternato per righe (opzionale)
        $fill = false;
        $counter = 1;

        if (empty($data)) {
            $this->Cell(array_sum($w), 10, utf8_decode('Nessun partecipante registrato per questo evento.'), 1, 1, 'C');
            return;
        }

        foreach($data as $row) {
            $this->Cell($w[0], 7, $counter++, 1, 0, 'C', $fill);
            $this->Cell($w[1], 7, utf8_decode($row['Cognome'] ?? ''), 'LR', 0, 'L', $fill); // LR per bordi solo laterali se vuoi
            $this->Cell($w[2], 7, utf8_decode($row['Nome'] ?? ''), 'LR', 0, 'L', $fill);
            $this->Cell($w[3], 7, utf8_decode($row['Contatto'] ?? ''), 'LR', 0, 'L', $fill);
            $this->Ln();
            $fill = !$fill; // Alterna colore di sfondo riga
        }
        // Linea di chiusura tabella
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

// Creazione del PDF
$pdf = new PDF_Event_Participants('P', 'mm', 'A4', $eventDetails); // Passa i dettagli evento al costruttore
$pdf->AliasNbPages(); // Necessario per {nb} nel footer
$pdf->AddPage();

// Aggiunta di una breve descrizione dell'evento (opzionale)
// if (!empty($eventDetails['Descrizione'])) { // Se hai un campo Descrizione breve
//     $pdf->SetFont('Arial','',10);
//     $pdf->MultiCell(0, 6, utf8_decode("Dettagli: " . $eventDetails['Descrizione']));
//     $pdf->Ln(5);
// }

$pdf->SetFont('Arial','B',12);
$pdf->Cell(0, 10, utf8_decode('Elenco dei Partecipanti Registrati'), 0, 1, 'L');
$pdf->Ln(2);

$tableHeader = array('Nr.', 'Cognome', 'Nome', 'Email Prenotazione');
$pdf->ParticipantTable($tableHeader, $participants);

// Nome del file PDF per il download
$pdfFileName = 'Lista_Partecipanti_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $eventDetails['Titolo']) . '.pdf';

// Output del PDF
// 'D': forza il download
// 'I': invia al browser inline
// 'F': salva su file locale
// 'S': restituisce come stringa
$pdf->Output('D', $pdfFileName);
exit; // Termina lo script dopo aver inviato il PDF

?>