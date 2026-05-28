<?php
// admin/export_medicaments.php
require_once '../config/database.php';
checkRole('admin');

// Récupérer les paramètres
$search = $_GET['search'] ?? '';
$statut = $_GET['statut'] ?? '';
$forme = $_GET['forme'] ?? '';
$stock = $_GET['stock'] ?? '';
$format = $_GET['format'] ?? 'csv';

// Construire la requête
$sql = "SELECT 
    m.code_cip,
    m.nom_commercial,
    m.nom_generique,
    m.laboratoire,
    m.forme,
    m.dosage,
    m.classe_therapeutique,
    m.conditionnement,
    m.stock_actuel,
    m.stock_minimum,
    m.prix_unitaire,
    m.remboursement,
    m.statut,
    (m.stock_actuel * m.prix_unitaire) as valeur_totale,
    CASE 
        WHEN m.stock_actuel = 0 THEN 'Rupture'
        WHEN m.stock_actuel <= m.stock_minimum * 0.3 THEN 'Critique'
        WHEN m.stock_actuel <= m.stock_minimum THEN 'Faible'
        ELSE 'Normal'
    END as niveau_stock,
    m.created_at,
    m.updated_at
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

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $medicaments = $stmt->fetchAll();
} catch (Exception $e) {
    die('Erreur lors de la récupération des données: ' . $e->getMessage());
}

// Fonction pour exporter en CSV
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // En-têtes
    fputcsv($output, [
        'Code CIP',
        'Nom Commercial',
        'Nom Générique',
        'Laboratoire',
        'Forme',
        'Dosage',
        'Classe Thérapeutique',
        'Conditionnement',
        'Stock Actuel',
        'Stock Minimum',
        'Prix Unitaire (€)',
        'Remboursement',
        'Statut',
        'Valeur Totale (€)',
        'Niveau Stock',
        'Date Création',
        'Date Mise à Jour'
    ], ';');
    
    // Données
    foreach ($data as $med) {
        fputcsv($output, [
            $med['code_cip'],
            $med['nom_commercial'],
            $med['nom_generique'],
            $med['laboratoire'],
            $med['forme'],
            $med['dosage'],
            $med['classe_therapeutique'],
            $med['conditionnement'],
            $med['stock_actuel'],
            $med['stock_minimum'],
            number_format($med['prix_unitaire'], 2, ',', ''),
            $med['remboursement'],
            $med['statut'],
            number_format($med['valeur_totale'], 2, ',', ''),
            $med['niveau_stock'],
            date('d/m/Y H:i', strtotime($med['created_at'])),
            date('d/m/Y H:i', strtotime($med['updated_at']))
        ], ';');
    }
    
    fclose($output);
}

// Fonction pour exporter en JSON
function exportToJSON($data, $filename) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Fonction pour exporter en Excel (HTML table)
function exportToExcel($data, $filename) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            table { border-collapse: collapse; width: 100%; }
            th { background-color: #4361ee; color: white; font-weight: bold; padding: 8px; text-align: left; }
            td { border: 1px solid #ddd; padding: 8px; }
            tr:nth-child(even) { background-color: #f2f2f2; }
            .total { font-weight: bold; background-color: #e9ecef; }
        </style>
    </head>
    <body>
        <h2>Inventaire des Médicaments</h2>
        <p>Exporté le: ' . date('d/m/Y H:i') . '</p>
        <table>
            <thead>
                <tr>
                    <th>Code CIP</th>
                    <th>Nom Commercial</th>
                    <th>Nom Générique</th>
                    <th>Forme</th>
                    <th>Stock</th>
                    <th>Min</th>
                    <th>Prix Unitaire (€)</th>
                    <th>Valeur Totale (€)</th>
                    <th>Niveau Stock</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>';
    
    $total_valeur = 0;
    $total_stock = 0;
    
    foreach ($data as $med) {
        $valeur_totale = $med['valeur_totale'];
        $total_valeur += $valeur_totale;
        $total_stock += $med['stock_actuel'];
        
        echo '<tr>
                <td>' . $med['code_cip'] . '</td>
                <td>' . htmlspecialchars($med['nom_commercial']) . '</td>
                <td>' . htmlspecialchars($med['nom_generique']) . '</td>
                <td>' . $med['forme'] . '</td>
                <td>' . $med['stock_actuel'] . '</td>
                <td>' . $med['stock_minimum'] . '</td>
                <td>' . number_format($med['prix_unitaire'], 2, ',', '') . '</td>
                <td>' . number_format($valeur_totale, 2, ',', '') . '</td>
                <td>' . $med['niveau_stock'] . '</td>
                <td>' . $med['statut'] . '</td>
              </tr>';
    }
    
    echo '<tr class="total">
                <td colspan="4" align="right"><strong>TOTAL:</strong></td>
                <td><strong>' . number_format($total_stock) . '</strong></td>
                <td></td>
                <td></td>
                <td><strong>' . number_format($total_valeur, 2, ',', '') . ' €</strong></td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
    </body>
    </html>';
}

// Gérer l'export selon le format demandé
$timestamp = date('Ymd_His');
$filename = 'inventaire_medicaments_' . $timestamp;

switch ($format) {
    case 'csv':
        $filename .= '.csv';
        exportToCSV($medicaments, $filename);
        break;
        
    case 'json':
        $filename .= '.json';
        exportToJSON($medicaments, $filename);
        break;
        
    case 'excel':
        $filename .= '.html';
        exportToExcel($medicaments, $filename);
        break;
        
    case 'pdf':
        // Rediriger vers la version PDF si disponible
        header('Location: export_inventory_pdf.php?' . $_SERVER['QUERY_STRING']);
        break;
        
    default:
        die('Format non supporté');
}
exit();
?>