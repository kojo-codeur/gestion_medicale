<?php
// ajax/get_patient_info.php
require_once '../config/database.php';
header('Content-Type: text/html; charset=utf-8');

$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$patient_id) {
    echo '<div class="text-muted small">Patient invalide</div>';
    exit;
}

$pdo = Database::getInstance()->getConnection();

// Récupérer les infos nécessaires
$stmt = $pdo->prepare("SELECT date_naissance, allergies, medicaments_habituels FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    echo '<div class="text-muted small">Patient non trouvé</div>';
    exit;
}

// Calcul de l'âge
$date_naissance = new DateTime($patient['date_naissance']);
$today = new DateTime();
$age = $today->diff($date_naissance)->y;
?>
<div class="card bg-light">
    <div class="card-body p-3">
        <small>
            <strong>Âge:</strong> <?php echo $age; ?> ans<br>
            <strong>Allergies:</strong> <?php echo htmlspecialchars($patient['allergies'] ?: 'Aucune'); ?><br>
            <strong>Traitements habituels:</strong> <?php echo htmlspecialchars($patient['medicaments_habituels'] ?: 'Aucun'); ?>
        </small>
    </div>
</div>