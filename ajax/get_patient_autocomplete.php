<?php
// ajax/get_patient_autocomplete.php
require_once '../config/database.php';

$term = $_GET['term'] ?? '';

if (strlen($term) < 2) {
    echo json_encode([]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT id, code_patient, nom, prenom, telephone, ville
        FROM patients 
        WHERE statut = 'actif'
        AND (nom LIKE ? OR prenom LIKE ? OR code_patient LIKE ? OR telephone LIKE ?)
        ORDER BY nom, prenom
        LIMIT 10
    ");
    
    $searchTerm = "%$term%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $patients = $stmt->fetchAll();
    
    $results = [];
    foreach ($patients as $patient) {
        $results[] = [
            'id' => $patient['id'],
            'value' => $patient['prenom'] . ' ' . $patient['nom'],
            'label' => $patient['prenom'] . ' ' . $patient['nom'] . 
                      ' (' . $patient['code_patient'] . ') - ' . 
                      ($patient['telephone'] ?: 'Pas de téléphone') .
                      ($patient['ville'] ? ' - ' . $patient['ville'] : ''),
            'code' => $patient['code_patient'],
            'telephone' => $patient['telephone']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($results);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([]);
}
?>
