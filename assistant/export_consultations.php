<?php
// assistant/export_consultations.php
require_once '../config/database.php';
checkRole('assistant');

// Récupération des filtres (identique à consultations.php)
$search = $_GET['search'] ?? '';
$statut = $_GET['statut'] ?? '';
$date = $_GET['date'] ?? '';

$pdo = Database::getInstance()->getConnection();

// Construction de la requête
$sql = "SELECT c.reference, c.date_consultation, c.motif, c.diagnostic, c.statut,
               p.nom as patient_nom, p.prenom as patient_prenom, p.code_patient,
               d.nom as docteur_nom, d.prenom as docteur_prenom, d.specialite
        FROM consultations c
        JOIN patients p ON c.patient_id = p.id
        JOIN utilisateurs d ON c.docteur_id = d.id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.nom LIKE ? OR p.prenom LIKE ? OR c.reference LIKE ? OR c.motif LIKE ?)";
    $term = "%$search%";
    $params = array_fill(0, 4, $term);
}
if (!empty($statut)) {
    $sql .= " AND c.statut = ?";
    $params[] = $statut;
}
if (!empty($date)) {
    $sql .= " AND DATE(c.date_consultation) = ?";
    $params[] = $date;
}
$sql .= " ORDER BY c.date_consultation DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$consultations = $stmt->fetchAll();

// Définition des en-têtes CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="consultations_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
// Entêtes de colonnes
fputcsv($output, [
    'Référence', 'Date', 'Patient', 'Code patient', 'Médecin', 'Spécialité',
    'Motif', 'Diagnostic', 'Statut'
]);

foreach ($consultations as $c) {
    fputcsv($output, [
        $c['reference'],
        date('d/m/Y H:i', strtotime($c['date_consultation'])),
        $c['patient_prenom'] . ' ' . $c['patient_nom'],
        $c['code_patient'],
        'Dr ' . $c['docteur_prenom'] . ' ' . $c['docteur_nom'],
        $c['specialite'],
        $c['motif'],
        $c['diagnostic'],
        $c['statut']
    ]);
}
fclose($output);
exit;