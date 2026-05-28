<?php
// assistant/prescription_create.php
require_once '../config/database.php';
checkRole('assistant');

$consultation_id = filter_input(INPUT_GET, 'consultation_id', FILTER_VALIDATE_INT);
if (!$consultation_id) {
    die('Consultation non spécifiée.');
}

$pdo = Database::getInstance()->getConnection();

// Récupérer les infos de la consultation
$stmt = $pdo->prepare("
    SELECT c.id, c.patient_id, c.docteur_id, p.nom, p.prenom, u.nom as docteur_nom, u.prenom as docteur_prenom
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    JOIN utilisateurs u ON c.docteur_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$consultation_id]);
$consult = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$consult) {
    die('Consultation introuvable.');
}

$title = 'Créer une prescription';
require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-prescription"></i> Nouvelle prescription</h1>
        <a href="consultations.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="card">
        <div class="card-header bg-white">
            <strong>Patient :</strong> <?php echo htmlspecialchars($consult['prenom'] . ' ' . $consult['nom']); ?><br>
            <strong>Médecin :</strong> Dr <?php echo htmlspecialchars($consult['docteur_prenom'] . ' ' . $consult['docteur_nom']); ?>
        </div>
        <div class="card-body">
            <form method="POST" action="prescription_save.php">
                <input type="hidden" name="consultation_id" value="<?php echo $consultation_id; ?>">
                <input type="hidden" name="patient_id" value="<?php echo $consult['patient_id']; ?>">
                <input type="hidden" name="docteur_id" value="<?php echo $consult['docteur_id']; ?>">

                <div class="mb-3">
                    <label class="form-label">Date de prescription</label>
                    <input type="date" name="date_prescription" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Médicaments (un par ligne, format: Nom - Dosage - Posologie)</label>
                    <textarea name="medicaments" class="form-control" rows="5" placeholder="Exemple:&#10;Amoxicilline - 500mg - 2 comprimés par jour pendant 7 jours&#10;Paracétamol - 1g - si douleur" required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Durée du traitement</label>
                    <input type="text" name="duree_traitement" class="form-control" placeholder="ex: 7 jours, 1 mois...">
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="renouvelable" value="1" class="form-check-input" id="renouvelable">
                    <label class="form-check-label" for="renouvelable">Ordonnance renouvelable</label>
                </div>

                <div class="mb-3">
                    <label class="form-label">Notes complémentaires</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer la prescription</button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>