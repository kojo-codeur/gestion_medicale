<?php
require_once '../config/database.php';
checkRole('admin');

$pdo = Database::getInstance()->getConnexion();

try {
    require_once('../fpdf/fpdf.php');
    
    // Récupérer les données (même requête que dans export_medicaments.php)
    $search = $_GET['search'] ?? '';
    $statut = $_GET['statut'] ?? '';
    $forme = $_GET['forme'] ?? '';
    $stock = $_GET['stock'] ?? '';
    
    $sql = "SELECT 
        m.code_cip,
        m.nom_commercial,
        m.nom_generique,
        m.laboratoire,
        m.forme,
        m.dosage,
        m.conditionnement,
        m.stock_actuel,
        m.stock_minimum,
        m.prix_unitaire,
        m.statut,
        (m.stock_actuel * m.prix_unitaire) as valeur_totale,
        CASE 
            WHEN m.stock_actuel = 0 THEN 'Rupture'
            WHEN m.stock_actuel <= m.stock_minimum * 0.3 THEN 'Critique'
            WHEN m.stock_actuel <= m.stock_minimum THEN 'Faible'
            ELSE 'Normal'
        END as niveau_stock
    FROM medicaments m 
    WHERE 1=1";
    
    $params = [];
    
    if ($search) {
        $sql .= " AND (m.nom_commercial LIKE ? OR m.nom_generique LIKE ? OR m.code_cip LIKE ?)";
        $search_term = "%$search%";
        $params = array_merge($params, [$search_term, $search_term, $search_term]);
    }
    
    if ($statut) {
        $sql .= " AND m.statut = ?";
        $params[] = $statut;
    }
    
    if ($forme) {
        $sql .= " AND m.forme = ?";
        $params[] = $forme;
    }
    
    if ($stock === 'faible') {
        $sql .= " AND m.stock_actuel <= m.stock_minimum AND m.stock_actuel > 0";
    } elseif ($stock === 'rupture') {
        $sql .= " AND m.stock_actuel = 0";
    }
    
    $sql .= " ORDER BY m.nom_commercial ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $medicaments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Créer le PDF avec FPDF
    $pdf = new FPDF('L', 'mm', 'A4');
    
    // Configuration du document
    $pdf->SetTitle('Inventaire des Médicaments');
    $pdf->SetAuthor('MedSystem');
    $pdf->SetCreator('Medical System');
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Titre
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Inventaire des Médicaments', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 10, 'Date: ' . date('d/m/Y H:i'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // En-têtes du tableau
    $pdf->SetFont('Arial', 'B', 9);
    
    // Largeurs des colonnes (adaptées à l'orientation paysage)
    $w = array(25, 65, 20, 20, 20, 25, 30, 25); // Total = 230mm (A4 paysage = 297mm, marge 15+15=30mm)
    
    // En-têtes
    $headers = array('Code CIP', 'Nom Commercial', 'Forme', 'Stock', 'Min', 'Prix €', 'Valeur €', 'Niveau');
    
    // Dessiner les en-têtes
    for($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($w[$i], 8, $headers[$i], 1, 0, 'C');
    }
    $pdf->Ln();
    
    // Données
    $pdf->SetFont('Arial', '', 8);
    $total_valeur = 0;
    $total_stock = 0;
    $row_height = 6;
    
    foreach($medicaments as $med) {
        $valeur_totale = $med['valeur_totale'];
        $total_valeur += $valeur_totale;
        $total_stock += $med['stock_actuel'];
        
        // Tronquer le nom commercial si trop long
        $nom_commercial = $med['nom_commercial'];
        if (strlen($nom_commercial) > 45) {
            $nom_commercial = substr($nom_commercial, 0, 42) . '...';
        }
        
        // Dessiner la ligne
        $pdf->Cell($w[0], $row_height, $med['code_cip'], 1, 0, 'L');
        $pdf->Cell($w[1], $row_height, $nom_commercial, 1, 0, 'L');
        $pdf->Cell($w[2], $row_height, $med['forme'], 1, 0, 'C');
        $pdf->Cell($w[3], $row_height, $med['stock_actuel'], 1, 0, 'C');
        $pdf->Cell($w[4], $row_height, $med['stock_minimum'], 1, 0, 'C');
        $pdf->Cell($w[5], $row_height, number_format($med['prix_unitaire'], 2), 1, 0, 'R');
        $pdf->Cell($w[6], $row_height, number_format($valeur_totale, 2), 1, 0, 'R');
        
        // Couleur selon le niveau de stock
        switch($med['niveau_stock']) {
            case 'Rupture':
                $pdf->SetFillColor(255, 200, 200); // Rouge clair
                break;
            case 'Critique':
                $pdf->SetFillColor(255, 220, 180); // Orange clair
                break;
            case 'Faible':
                $pdf->SetFillColor(255, 255, 180); // Jaune clair
                break;
            default:
                $pdf->SetFillColor(200, 255, 200); // Vert clair
        }
        
        $pdf->Cell($w[7], $row_height, $med['niveau_stock'], 1, 0, 'C', true);
        
        // Réinitialiser la couleur de remplissage
        $pdf->SetFillColor(255, 255, 255);
        
        $pdf->Ln();
    }
    
    $pdf->Ln(5);
    
    // Totaux
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 10, 'Total Stock: ' . number_format($total_stock) . ' unités', 0, 1);
    $pdf->Cell(0, 10, 'Valeur Totale du Stock: ' . number_format($total_valeur, 2) . ' €', 0, 1);
    
    $pdf->Ln(5);
    
    // Légende
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 5, 'Légende:', 0, 1);
    
    // Ligne 1 de légende
    $pdf->SetFillColor(200, 255, 200);
    $pdf->Cell(20, 5, 'Normal', 1, 0, 'C', true);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(5, 5, '=', 0, 0, 'C');
    $pdf->Cell(40, 5, 'Stock suffisant', 0, 0, 'L');
    
    $pdf->SetFillColor(255, 255, 180);
    $pdf->Cell(20, 5, 'Faible', 1, 0, 'C', true);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(5, 5, '=', 0, 0, 'C');
    $pdf->Cell(40, 5, 'Stock sous le minimum', 0, 0, 'L');
    $pdf->Ln(8);
    
    // Ligne 2 de légende
    $pdf->SetFillColor(255, 220, 180);
    $pdf->Cell(20, 5, 'Critique', 1, 0, 'C', true);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(5, 5, '=', 0, 0, 'C');
    $pdf->Cell(40, 5, 'Stock très bas', 0, 0, 'L');
    
    $pdf->SetFillColor(255, 200, 200);
    $pdf->Cell(20, 5, 'Rupture', 1, 0, 'C', true);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(5, 5, '=', 0, 0, 'C');
    $pdf->Cell(40, 5, 'Stock épuisé', 0, 0, 'L');
    
    // Pied de page
    $pdf->SetY(-15);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 10, 'Page ' . $pdf->PageNo(), 0, 0, 'C');
    
    // Nom du fichier
    $filename = 'inventaire_medicaments_' . date('Ymd_His') . '.pdf';
    
    // Output - téléchargement
    $pdf->Output('D', $filename);
    
} catch (Exception $e) {
    // Gestion des erreurs
    error_log("Erreur PDF: " . $e->getMessage());
    
    // Fallback si FPDF n'est pas installé
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erreur PDF</title>
        <style>
            body { 
                font-family: "Segoe UI", Arial, sans-serif; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .error-container {
                background: white;
                border-radius: 10px;
                padding: 40px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                max-width: 500px;
                width: 100%;
            }
            .error-icon {
                text-align: center;
                font-size: 64px;
                color: #dc3545;
                margin-bottom: 20px;
            }
            .error-title {
                color: #721c24;
                text-align: center;
                margin-bottom: 20px;
                font-size: 24px;
            }
            .error-content {
                color: #555;
                line-height: 1.6;
                margin-bottom: 25px;
            }
            .error-details {
                background-color: #f8f9fa;
                border-left: 4px solid #dc3545;
                padding: 15px;
                margin-bottom: 25px;
                border-radius: 4px;
            }
            .btn-group {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .btn {
                padding: 12px 24px;
                border-radius: 6px;
                text-decoration: none;
                font-weight: 500;
                transition: all 0.3s;
                flex: 1;
                min-width: 120px;
                text-align: center;
            }
            .btn-primary {
                background: #4361ee;
                color: white;
                border: none;
            }
            .btn-primary:hover {
                background: #3a56d4;
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
            }
            .btn-secondary {
                background: #6c757d;
                color: white;
                border: none;
            }
            .btn-secondary:hover {
                background: #5a6268;
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
            }
            @media (max-width: 768px) {
                .error-container {
                    padding: 20px;
                }
                .btn {
                    flex: 100%;
                }
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-file-pdf"></i>
            </div>
            <h2 class="error-title">Erreur lors de la génération du PDF</h2>
            
            <div class="error-content">
                <p>Une erreur est survenue pendant la création du fichier PDF :</p>
            </div>
            
            <div class="error-details">
                <p><strong>Message d\'erreur :</strong><br>' . htmlspecialchars($e->getMessage()) . '</p>
            </div>
            
            <div class="error-content">
                <p><strong>Solutions possibles :</strong></p>
                <ul>
                    <li>Vérifiez que FPDF est bien installé</li>
                    <li>Utilisez un autre format d\'export</li>
                    <li>Contactez l\'administrateur système</li>
                </ul>
            </div>
            
            <div class="btn-group">
                <a href="../medicaments/index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Retour
                </a>
                <a href="export_medicaments.php?' . htmlspecialchars($_SERVER['QUERY_STRING']) . '&format=csv" class="btn btn-primary">
                    <i class="fas fa-download me-2"></i>Télécharger CSV
                </a>
                <a href="export_medicaments.php?' . htmlspecialchars($_SERVER['QUERY_STRING']) . '&format=excel" class="btn btn-primary">
                    <i class="fas fa-file-excel me-2"></i>Télécharger Excel
                </a>
            </div>
        </div>
        
        <script>
            // Ajouter l\'icône FontAwesome dynamiquement
            if (!document.querySelector(\'link[href*="fontawesome"]\')) {
                const faLink = document.createElement(\'link\');
                faLink.rel = \'stylesheet\';
                faLink.href = \'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css\';
                document.head.appendChild(faLink);
            }
        </script>
    </body>
    </html>';
}
