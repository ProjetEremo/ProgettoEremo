<?php
// require('fpdf/fpdf.php'); // Includi la libreria FPDF

// --- INIZIO: Logica di base (SENZA LIBRERIA FPDF VERA E PROPRIA) ---
// Questa è una simulazione concettuale. Per un PDF reale, integra una libreria.

header('Content-Type: application/json; charset=utf-8'); // Inizialmente JSON per feedback
$host = "localhost"; $username = "eremofratefrancesco"; $password = ""; $dbname = "my_eremofratefrancesco";
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) { die(json_encode(['success' => false, 'message' => "Errore DB."])); }
$conn->set_charset("utf8mb4");

$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
if (!$eventId) {
    die(json_encode(['success' => false, 'message' => 'ID Evento mancante. Impossibile generare PDF.']));
}

// Fetch event details
$stmtEvent = $conn->prepare("SELECT Titolo, Data, Durata FROM eventi WHERE IDEvento = ?");
$stmtEvent->bind_param("i", $eventId);
$stmtEvent->execute();
$resultEvent = $stmtEvent->get_result();
if ($resultEvent->num_rows === 0) {
    die(json_encode(['success' => false, 'message' => 'Evento non trovato. Impossibile generare PDF.']));
}
$event = $resultEvent->fetch_assoc();
$stmtEvent->close();

// Fetch participants
$stmtParticipants = $conn->prepare(
    "SELECT p.Nome, p.Cognome, pr.Contatto
     FROM Partecipanti p
     JOIN prenotazioni pr ON p.Progressivo = pr.Progressivo
     WHERE pr.IDEvento = ? ORDER BY p.Cognome, p.Nome"
);
$stmtParticipants->bind_param("i", $eventId);
$stmtParticipants->execute();
$resultParticipants = $stmtParticipants->get_result();
$participants = [];
while($row = $resultParticipants->fetch_assoc()){
    $participants[] = $row;
}
$stmtParticipants->close();
$conn->close();

// --- QUI INIZIEREBBE LA LOGICA FPDF ---
/*
// Esempio con FPDF (necessita installazione e setup corretto del path)
require_once('path/to/fpdf.php'); // Assicurati che il path sia corretto

class PDF extends FPDF {
    function Header() {
        global $event; // Rendi $event accessibile
        $this->SetFont('Arial','B',15);
        $this->Cell(0,10, utf8_decode($event['Titolo']),0,1,'C'); // Titolo evento
        $this->SetFont('Arial','',10);
        $this->Cell(0,7, 'Data: ' . date("d/m/Y", strtotime($event['Data'])) . ' Orario: ' . ($event['Durata'] ?? 'N/D'),0,1,'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Pagina '.$this->PageNo().'/{nb}',0,0,'C');
    }

    function ParticipantTable($header, $data) {
        $this->SetFont('Arial','B',10);
        $w = array(15, 60, 60, 50); // Larghezze colonne: Num, Cognome, Nome, Contatto
        for($i=0;$i<count($header);$i++)
            $this->Cell($w[$i],7,utf8_decode($header[$i]),1,0,'C');
        $this->Ln();
        $this->SetFont('Arial','',10);
        $counter = 1;
        foreach($data as $row) {
            $this->Cell($w[0],6,$counter++,1,0,'C');
            $this->Cell($w[1],6,utf8_decode($row['Cognome']),1);
            $this->Cell($w[2],6,utf8_decode($row['Nome']),1);
            $this->Cell($w[3],6,utf8_decode($row['Contatto']),1);
            $this->Ln();
        }
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,10,utf8_decode('Lista Partecipanti'),0,1,'L');

$tableHeader = array('Nr.', 'Cognome', 'Nome', 'Contatto Prenotazione');
$pdf->ParticipantTable($tableHeader, $participants);

$pdf->Output('D', 'Lista_Partecipanti_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $event['Titolo']) . '.pdf'); // D per download
exit;
*/

// --- FINE: Logica di base (SENZA LIBRERIA FPDF VERA E PROPRIA) ---
// Se arrivi qui, significa che la parte FPDF è commentata.
// Manda un JSON per indicare che i dati sono stati raccolti, ma il PDF non generato da questo script base.
echo json_encode([
    'success' => true,
    'message' => 'Dati per PDF raccolti. Integrazione libreria PDF richiesta per la generazione.',
    'event' => $event,
    'participants_count' => count($participants),
    // 'participants_data' => $participants // Non mandare troppi dati se non necessario per il client
]);

?>