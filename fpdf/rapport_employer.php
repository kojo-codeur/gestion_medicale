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

// Requête SQL pour récupérer les données
$sql = "SELECT matricule, nom, prenom, adresse, telephone, nationalite, email, fonction, etat, role FROM employer_mairie";
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

        // Titre centré avec plus de style
        $this->SetFont('Arial', 'B', 18);
        $this->SetXY(10, 25);
        $this->Cell(0, 10, 'Liste des employés de Mairie de Bujumbura', 0, 1, 'C');
        $this->Ln(6); // Réduire l'espace avant la ligne de soulignement
        $this->Cell(0, 0, '', 'B', 1, 'C'); // Ligne soulignée

        // Ligne de séparation sous l'en-tête
        $this->SetDrawColor(50, 50, 100);
        $this->Line(10, 40, 200, 40);
        $this->Ln(6); // Espacement après la ligne de soulignement
    }

    function Footer()
    {
        // Positionnement à 1.5 cm du bas
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Fonction pour ajouter des bordures arrondies autour du tableau
    function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $this->SetLineWidth(0.3);
        $this->SetDrawColor(0, 0, 0);
        $this->SetFillColor(255, 255, 255);
        $this->SetFillColor(240, 240, 255); // couleur de fond pour le tableau
        $this->Rect($x, $y, $w, $h, 'DF');
    }
}

// Initialisation du PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 9);

// En-tête du tableau avec une meilleure couleur de fond
$header = ['Matricule', 'Nom', 'Prenom', 'Adresse', 'Téléphone', 'Nationalité', 'Email', 'Rôle'];
$widths = [20, 25, 25, 26, 20, 20, 33, 22]; // Largeurs des colonnes

$pdf->SetFillColor(150, 200, 255); // Nouveau fond pour l'en-tête
$pdf->SetTextColor(0);

foreach ($header as $key => $col) {
    $pdf->Cell($widths[$key], 10, $col, 1, 0, 'C', true);
}
$pdf->Ln();

// Contenu du tableau avec alternance de couleurs de fond pour chaque ligne
$pdf->SetFont('Arial', '', 8);
$pdf->SetFillColor(240, 240, 240);
$fill = false;

foreach ($donnees as $donnee) {
    $pdf->Cell($widths[0], 10, $donnee['matricule'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[1], 10, $donnee['nom'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[2], 10, $donnee['prenom'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[3], 10, substr($donnee['adresse'], 0, 23), 1, 0, 'L', $fill);
    $pdf->Cell($widths[4], 10, $donnee['telephone'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[5], 10, $donnee['nationalite'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[6], 10, $donnee['email'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[7], 10, $donnee['role'], 1, 0, 'C', $fill);
    $pdf->Ln();
    $fill = !$fill;
}

// Sortie du PDF
$pdf->Output('I', 'rapport_employes.pdf');
?>
