<?php
// assistant/consultation_details.php
require_once '../config/database.php';
checkRole('assistant');

$title = 'Détails de la consultation';
require_once '../includes/header.php';

$pdo = Database::getInstance()->getConnection();

// Récupération et validation de l'ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    $_SESSION['error'] = "Consultation non spécifiée.";
    header('Location: consultations.php');
    exit();
}

// Requête principale
$sql = "SELECT c.*, 
               p.nom as patient_nom, p.prenom as patient_prenom, p.code_patient, p.date_naissance,
               p.telephone, p.email, p.allergies, p.medicaments_habituels,
               d.nom as docteur_nom, d.prenom as docteur_prenom, d.specialite,
               a.nom as assistant_nom, a.prenom as assistant_prenom
        FROM consultations c
        JOIN patients p ON c.patient_id = p.id
        JOIN utilisateurs d ON c.docteur_id = d.id
        LEFT JOIN utilisateurs a ON c.assistant_id = a.id
        WHERE c.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$consultation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$consultation) {
    echo '<div class="alert alert-danger">Consultation introuvable.</div>';
    require_once '../includes/footer.php';
    exit();
}

// Récupérer les prescriptions
$prescriptions = [];
$presStmt = $pdo->prepare("SELECT * FROM prescriptions WHERE consultation_id = ? ORDER BY date_prescription DESC");
$presStmt->execute([$id]);
$prescriptions = $presStmt->fetchAll();

// Calcul de l'âge
$age = null;
if ($consultation['date_naissance']) {
    $birth = new DateTime($consultation['date_naissance']);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
}

// Fonction pour obtenir la classe Bootstrap selon le statut (compatible PHP 7)
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'planifie': return 'warning';
        case 'en_cours': return 'info';
        case 'termine': return 'success';
        case 'annule': return 'danger';
        case 'reporte': return 'secondary';
        default: return 'secondary';
    }
}
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-file-medical me-2"></i>Détails de la consultation
            <small class="text-muted">#<?php echo htmlspecialchars($consultation['reference']); ?></small>
        </h1>
        <div class="btn-toolbar">
            <a href="consultations.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>Retour à la liste
            </a>
            <a href="consultations.php?action=edit&id=<?php echo $id; ?>" class="btn btn-primary ms-2">
                <i class="fas fa-edit me-1"></i>Modifier
            </a>
            <?php if (empty($prescriptions)): ?>
            <button onclick="confirmDelete(<?php echo $id; ?>)" class="btn btn-danger ms-2">
                <i class="fas fa-trash me-1"></i>Supprimer
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Colonne principale -->
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations générales</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr><th>Date & heure :</th><td><?php echo date('d/m/Y H:i', strtotime($consultation['date_consultation'])); ?></td></tr>
                                <tr><th>Durée :</th><td><?php echo $consultation['duree']; ?> minutes</td></tr>
                                <tr><th>Type :</th><td><?php echo ucfirst($consultation['type_consultation']); ?></td></tr>
                                <tr><th>Statut :</th>
                                    <td><span class="badge bg-<?php echo getStatusBadgeClass($consultation['statut']); ?>"><?php echo ucfirst($consultation['statut']); ?></span></td>
                                </tr>
                                <tr><th>Urgence :</th><td><?php echo $consultation['urgence'] ? '<span class="badge bg-danger">Oui</span>' : '<span class="badge bg-secondary">Non</span>'; ?></td></tr>
                                <tr><th>Confidentialité :</th><td><?php echo ucfirst($consultation['confidentialite']); ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr><th>Patient :</th>
                                    <td>
                                        <a href="patient_details.php?id=<?php echo $consultation['patient_id']; ?>">
                                            <?php echo htmlspecialchars($consultation['patient_prenom'] . ' ' . $consultation['patient_nom']); ?>
                                        </a>
                                        <br><small class="text-muted">Code : <?php echo htmlspecialchars($consultation['code_patient']); ?></small>
                                    </td>
                                </tr>
                                <tr><th>Âge :</th><td><?php echo $age; ?> ans</td></tr>
                                <tr><th>Médecin :</th><td>Dr <?php echo htmlspecialchars($consultation['docteur_prenom'] . ' ' . $consultation['docteur_nom']); ?> (<?php echo htmlspecialchars($consultation['specialite']); ?>)</td></tr>
                                <tr><th>Assistant :</th><td><?php echo $consultation['assistant_prenom'] ? htmlspecialchars($consultation['assistant_prenom'] . ' ' . $consultation['assistant_nom']) : 'Non assigné'; ?></td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="mt-2">
                        <strong>Motif :</strong>
                        <p class="mt-1"><?php echo nl2br(htmlspecialchars($consultation['motif'] ?? 'Non renseigné')); ?></p>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-stethoscope me-2"></i>Examen médical</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Histoire de la maladie</strong>
                            <div class="border rounded p-2 mt-1 bg-light"><?php echo nl2br(htmlspecialchars($consultation['histoire_maladie'] ?? 'Non renseigné')); ?></div>
                        </div>
                        <div class="col-md-6">
                            <strong>Examen clinique</strong>
                            <div class="border rounded p-2 mt-1 bg-light"><?php echo nl2br(htmlspecialchars($consultation['examen_clinique'] ?? 'Non renseigné')); ?></div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <strong>Examens complémentaires</strong>
                        <div class="border rounded p-2 mt-1 bg-light"><?php echo nl2br(htmlspecialchars($consultation['examen_complementaire'] ?? 'Non renseigné')); ?></div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-diagnoses me-2"></i>Diagnostic et traitement</h5>
                </div>
                <div class="card-body">
                    <strong>Diagnostic</strong>
                    <div class="border rounded p-2 mt-1 bg-light"><?php echo nl2br(htmlspecialchars($consultation['diagnostic'] ?? 'Non renseigné')); ?></div>
                    
                    <strong class="mt-3 d-block">Traitement prescrit</strong>
                    <div class="border rounded p-2 mt-1 bg-light"><?php echo nl2br(htmlspecialchars($consultation['traitement'] ?? 'Non renseigné')); ?></div>
                    
                    <strong class="mt-3 d-block">Recommandations</strong>
                    <div class="border rounded p-2 mt-1 bg-light"><?php echo nl2br(htmlspecialchars($consultation['recommandations'] ?? 'Non renseigné')); ?></div>
                    
                    <strong class="mt-3 d-block">Notes complémentaires</strong>
                    <div class="border rounded p-2 mt-1 bg-light"><?php echo nl2br(htmlspecialchars($consultation['notes'] ?? 'Non renseigné')); ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Fiche patient rapide -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user-injured me-2"></i>Patient</h5>
                </div>
                <div class="card-body">
                    <p><strong>Nom :</strong> <?php echo htmlspecialchars($consultation['patient_prenom'] . ' ' . $consultation['patient_nom']); ?></p>
                    <p><strong>Code :</strong> <?php echo htmlspecialchars($consultation['code_patient']); ?></p>
                    <p><strong>Téléphone :</strong> <?php echo htmlspecialchars($consultation['telephone'] ?? 'Non renseigné'); ?></p>
                    <p><strong>Email :</strong> <?php echo htmlspecialchars($consultation['email'] ?? 'Non renseigné'); ?></p>
                    <p><strong>Allergies :</strong> <?php echo nl2br(htmlspecialchars($consultation['allergies'] ?: 'Aucune')); ?></p>
                    <p><strong>Traitements habituels :</strong> <?php echo nl2br(htmlspecialchars($consultation['medicaments_habituels'] ?: 'Aucun')); ?></p>
                    <a href="../assistant/patient_details.php?id=<?php echo $consultation['patient_id']; ?>" class="btn btn-sm btn-outline-primary w-100">Voir dossier complet</a>
                </div>
            </div>

            <!-- Prescriptions -->
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-prescription me-2"></i>Prescriptions</h5>
                    <a href="prescription_create.php?consultation_id=<?php echo $id; ?>" class="btn btn-sm btn-success">
                        <i class="fas fa-plus"></i> Nouvelle
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($prescriptions)): ?>
                        <div class="text-center py-4 text-muted">Aucune prescription pour cette consultation.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($prescriptions as $pres): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($pres['reference']); ?></h6>
                                        <small><?php echo date('d/m/Y', strtotime($pres['date_prescription'])); ?></small>
                                    </div>
                                    <p class="mb-1 small"><?php echo nl2br(htmlspecialchars(substr($pres['medicaments'], 0, 100) . (strlen($pres['medicaments']) > 100 ? '...' : ''))); ?></p>
                                    <div class="mt-2">
                                        <span class="badge bg-<?php echo $pres['statut'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($pres['statut']); ?>
                                        </span>
                                        <?php if ($pres['renouvelable']): ?>
                                            <span class="badge bg-info">Renouvelable</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2">
                                        <a href="prescription_details.php?id=<?php echo $pres['id']; ?>" class="btn btn-sm btn-outline-secondary">Détails</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette consultation ? Toutes les données associées seront perdues.')) {
        window.location.href = `?action=delete&id=${id}`;
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>