<?php
// assistant/prescription_save.php
require_once '../config/database.php';
checkRole('assistant');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: consultations.php');
    exit;
}

$pdo = Database::getInstance()->getConnection();

$data = sanitize($_POST);
try {
    $stmt = $pdo->prepare("
        INSERT INTO prescriptions 
        (consultation_id, patient_id, docteur_id, date_prescription, medicaments, duree_traitement, renouvelable, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['consultation_id'],
        $data['patient_id'],
        $data['docteur_id'],
        $data['date_prescription'],
        $data['medicaments'],
        $data['duree_traitement'] ?? null,
        isset($data['renouvelable']) ? 1 : 0,
        $data['notes'] ?? null,
        $_SESSION['user_id']
    ]);
    $_SESSION['success'] = "Prescription enregistrée avec succès.";
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur : " . $e->getMessage();
}
header("Location: consultations.php?action=edit&id=" . $data['consultation_id']);
exit;