<?php

require('fpdf.php'); // Inclure la bibliothèque FPDF

class PDF extends FPDF
{
    function Header()
    {
        $this->Image('logo.jpg', 10, 10, 20);
        $this->SetFont('Arial', '', 10);
        $this->SetXY(-50, 15);
        $this->Cell(0, 10, 'Date: ' . date('d/m/Y'), 0, 0, 'R');
        $this->SetFont('Arial', 'B', 15);
        $this->SetXY(10, 25);
        $this->Cell(0, 10, 'Rapport complet des employés, stagiaires et utilisateurs', 0, 1, 'C');
        $this->Ln(6);
        $this->Cell(0, 0, '', 'B', 1, 'C');
        $this->SetDrawColor(50, 50, 100);
        $this->Line(10, 40, 200, 40);
        $this->Ln(6);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function CheckBox($x, $y, $size, $checked)
    {
        if ($checked) {
            $this->SetFillColor(0, 0, 0);
            $this->Rect($x, $y, $size, $size, 'F');
        } else {
            $this->Rect($x, $y, $size, $size);
        }
    }
}

try {
    $db = new PDO('mysql:host=localhost;dbname=stagiaire', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Requête SQL mise à jour
$sql = "
SELECT 
    i.id_inscription AS ID, 
    COALESCE(e.matricule, '') AS Matricule, 
    COALESCE(s.id_stagiaire, '') AS Stagiaire_ID, 
    i.nom AS Nom, 
    i.prenom AS Prenom, 
    i.telephone AS Telephone, 
    i.adresse AS Adresse, 
    i.fonction AS Fonction, 
    i.email AS Email, 
    CASE
        WHEN e.matricule IS NOT NULL THEN 'Employé' 
        WHEN s.id_stagiaire IS NOT NULL THEN 'Stagiaire' 
        ELSE 'Utilisateur Simple'
    END AS Type, 
    COALESCE(COUNT(p.id_presence), 0) AS Presences  -- Remplacer NULL par 0
FROM inscription i
LEFT JOIN employer_mairie e ON i.id_inscription = e.id_inscription
LEFT JOIN tab_stagiaire s ON i.id_inscription = s.id_inscription
LEFT JOIN presence_stage p ON s.id_stagiaire = p.id_stagiaire
GROUP BY i.id_inscription, e.matricule, s.id_stagiaire, i.nom, i.prenom, i.telephone, i.adresse, i.fonction, i.email
";

$stmt = $db->query($sql);
$donnees = $stmt->fetchAll();

if ($donnees === false) {
    die("Erreur dans la requête SQL.");
}

$pdf = new PDF();
$pdf->AddPage('L');
$pdf->SetFont('Arial', 'B', 10);

$header = ['Num', 'Matricule', 'ID Stagiaire', 'Nom', 'Prenom', 'Telephone', 'Adresse', 'Fonction', 'Email', 'Stagiaire', 'Présences'];
$widths = [10, 20, 22, 25, 25, 30, 30, 30, 40, 21, 23];

$pdf->SetFillColor(200, 220, 255); // Couleur de fond des en-têtes
$pdf->SetTextColor(0);
$pdf->SetDrawColor(50, 50, 100); // Couleur des bordures

// En-tête du tableau
foreach ($header as $key => $col) {
    $pdf->Cell($widths[$key], 10, $col, 1, 0, 'C', true); // Utilisation de la couleur de fond rouge
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 9);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0);
$fill = false;

// Contenu du tableau
foreach ($donnees as $donnee) {
    // Réinitialisation des couleurs avant chaque ligne
    $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);

    $pdf->Cell($widths[0], 10, $donnee['ID'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[1], 10, $donnee['Matricule'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[2], 10, $donnee['Stagiaire_ID'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[3], 10, $donnee['Nom'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[4], 10, $donnee['Prenom'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[5], 10, $donnee['Telephone'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[6], 10, substr($donnee['Adresse'], 0, 30) . (strlen($donnee['Adresse']) > 30 ? '...' : ''), 1, 0, 'L', $fill);
    $pdf->Cell($widths[7], 10, $donnee['Fonction'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[8], 10, $donnee['Email'], 1, 0, 'L', $fill);

    // Cases à cocher
    $isEmployer = !empty($donnee['Matricule']);
    $isStagiaire = !empty($donnee['Stagiaire_ID']);
    $pdf->Cell($widths[9], 10, '', 1, 0, 'C', $fill);
    $pdf->CheckBox($pdf->GetX() - 15, $pdf->GetY() + 2, 5, $isEmployer);
    $pdf->CheckBox($pdf->GetX() - 10, $pdf->GetY() + 2, 5, $isStagiaire);

    // Remplacer toute valeur NULL par 0 pour 'Presences'
    $presences = isset($donnee['Presences']) ? $donnee['Presences'] : 0;

    $pdf->Cell($widths[10], 10, $presences, 1, 0, 'C', $fill);
    $pdf->Ln();

    // Alterner les couleurs
    $fill = !$fill;
}

$pdf->Output('I', 'rapport_final.pdf');
?>
