<?php
require('fpdf.php');

try {
    // Configuration de la connexion PDO avec gestion des erreurs
    $db = new PDO('mysql:host=localhost;dbname=stagiaire', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Requête SQL pour récupérer les données avec jointure
$sql = "SELECT p.id_presence, p.date_prese, p.heure_entre, p.heure_sortie, p.signature, s.nom_stag, s.prenom_stag FROM presence_stage p JOIN tab_stagiaire s 
    ON p.id_stagiaire = s.id_stagiaire
";
$stmt = $db->query($sql);
$donnees = $stmt->fetchAll();

if ($donnees === false) {
    die("Erreur dans la requête SQL.");
}

// Classe personnalisée pour le design
class PDF extends FPDF
{
    function Header()
    {
        // Logo
        $this->Image('logo.jpg', 10, 10, 20);

        // Date à droite
        $this->SetFont('Arial', '', 10);
        $this->SetXY(-50, 15);
        $this->Cell(0, 10, 'Date: ' . date('d/m/Y'), 0, 0, 'R');

        // Titre centré
        $this->SetFont('Arial', 'B', 15);
        $this->SetXY(10, 25);
        $this->Cell(0, 10, 'Liste de présence des stagiaires de Mairie de Bujumbura', 0, 1, 'C');
        $this->Ln(6); // Espacement
        $this->Cell(0, 0, '', 'B', 1, 'C'); // Ligne soulignée
        $this->Ln(6); // Espacement après la ligne de soulignement
    }

    function Footer()
    {
        // Positionnement à 1.5 cm du bas
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Initialisation du PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 10);

// En-tête du tableau
$header = ['Numero', 'Date', 'Heure Entrée', 'Heure Sortie', 'Signature', 'Nom du Stagiaire'];
$widths = [20, 30, 30, 30, 30, 50]; // Largeurs des colonnes
$pdf->SetFillColor(200, 220, 255); // Couleur de fond des en-têtes
$pdf->SetTextColor(0);
$pdf->SetDrawColor(50, 50, 100); // Couleur des bordures

foreach ($header as $key => $col) {
    $pdf->Cell($widths[$key], 10, $col, 1, 0, 'C', true);
}
$pdf->Ln();

// Contenu du tableau
$pdf->SetFont('Arial', '', 9);
$pdf->SetFillColor(240, 240, 240); // Couleur alternée pour les lignes
$pdf->SetTextColor(0);
$fill = false;
foreach ($donnees as $donnee) {
    $pdf->Cell($widths[0], 10, $donnee['id_presence'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[1], 10, $donnee['date_prese'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[2], 10, $donnee['heure_entre'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[3], 10, $donnee['heure_sortie'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[4], 10, $donnee['signature'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[5], 10, $donnee['nom_stag'] . ' ' . $donnee['prenom_stag'], 1, 0, 'L', $fill);
    $pdf->Ln();
    $fill = !$fill; // Alterner la couleur de fond
}

// Sortie du PDF
$pdf->Output('I', 'rapport_presence_stagiaires.pdf');
?>
