<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit();
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'suggestions' => []]);
    exit();
}

try {
    $pdo = getPDO();
    $suggestions = [];
    
    // Suggestions de patients
    $sql = "SELECT CONCAT(prenom, ' ', nom) as label, 'patient' as type, id 
            FROM patients 
            WHERE nom LIKE ? OR prenom LIKE ? OR email LIKE ?
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$query%", "%$query%", "%$query%"]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($patients as $patient) {
        $suggestions[] = [
            'label' => $patient['label'],
            'type' => 'patient',
            'value' => $patient['label'],
            'link' => 'patient.php?id=' . $patient['id']
        ];
    }
    
    // Suggestions de diagnostics courants
    $commonDiagnostics = [
        'Grippe', 'Angine', 'Hypertension', 'Diabète', 'Asthme',
        'Migraine', 'Arthrose', 'Bronchite', 'Sinusite', 'Gastro'
    ];
    
    foreach ($commonDiagnostics as $diagnostic) {
        if (stripos($diagnostic, $query) !== false) {
            $suggestions[] = [
                'label' => $diagnostic,
                'type' => 'diagnostic',
                'value' => $diagnostic,
                'link' => 'search.php?q=' . urlencode($diagnostic) . '&category=consultations'
            ];
        }
    }
    
    echo json_encode(['success' => true, 'suggestions' => $suggestions]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}