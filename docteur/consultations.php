<?php
// docteur/consultations.php
require_once '../config/database.php';
checkRole('docteur');

$title = 'Gestion des Consultations';
$docteur_id = $_SESSION['user_id'];

require_once '../includes/header.php';

// Traitement CRUD
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$patient_id = $_GET['patient_id'] ?? null;
$rdv_id = $_GET['rdv_id'] ?? null;

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = sanitize($_POST);
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'add') {
            // Créer une nouvelle consultation
            $stmt = $pdo->prepare("
                INSERT INTO consultations 
                (patient_id, docteur_id, date_consultation, duree, type_consultation, 
                 motif, histoire_maladie, examen_clinique, examen_complementaire, 
                 diagnostic, traitement, recommandations, notes, statut, urgence, 
                 confidentialite, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['patient_id'],
                $docteur_id,
                $data['date_consultation'],
                $data['duree'] ?? 30,
                $data['type_consultation'] ?? 'suivi',
                $data['motif'] ?? null,
                $data['histoire_maladie'] ?? null,
                $data['examen_clinique'] ?? null,
                $data['examen_complementaire'] ?? null,
                $data['diagnostic'] ?? null,
                $data['traitement'] ?? null,
                $data['recommandations'] ?? null,
                $data['notes'] ?? null,
                $data['statut'] ?? 'planifie',
                $data['urgence'] ?? 0,
                $data['confidentialite'] ?? 'normal',
                $docteur_id
            ]);
            
            $consultation_id = $pdo->lastInsertId();
            
            // Mettre à jour le statut du RDV si lié
            if (!empty($data['rdv_id'])) {
                $pdo->prepare("UPDATE rendez_vous SET statut = 'present' WHERE id = ?")
                    ->execute([$data['rdv_id']]);
            }
            
            // Ajouter des pathologies si spécifiées
            if (!empty($data['pathologies'])) {
                foreach ($data['pathologies'] as $pathologie_id) {
                    $pdo->prepare("
                        INSERT INTO patient_pathologie 
                        (patient_id, pathologie_id, date_diagnostic, diagnostic_par, gravite, statut) 
                        VALUES (?, ?, CURDATE(), ?, 'moderee', 'active')
                    ")->execute([$data['patient_id'], $pathologie_id, $docteur_id]);
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success'] = "Consultation créée avec succès";
            header("Location: consultations.php?action=view&id=$consultation_id");
            exit();
            
        } elseif ($action === 'edit' && $id) {
            // Mettre à jour une consultation
            $stmt = $pdo->prepare("
                UPDATE consultations SET 
                date_consultation = ?, duree = ?, type_consultation = ?, 
                motif = ?, histoire_maladie = ?, examen_clinique = ?, 
                examen_complementaire = ?, diagnostic = ?, traitement = ?, 
                recommandations = ?, notes = ?, statut = ?, urgence = ?, 
                confidentialite = ?, updated_at = NOW() 
                WHERE id = ? AND docteur_id = ?
            ");
            
            $stmt->execute([
                $data['date_consultation'],
                $data['duree'] ?? 30,
                $data['type_consultation'] ?? 'suivi',
                $data['motif'] ?? null,
                $data['histoire_maladie'] ?? null,
                $data['examen_clinique'] ?? null,
                $data['examen_complementaire'] ?? null,
                $data['diagnostic'] ?? null,
                $data['traitement'] ?? null,
                $data['recommandations'] ?? null,
                $data['notes'] ?? null,
                $data['statut'],
                $data['urgence'] ?? 0,
                $data['confidentialite'] ?? 'normal',
                $id,
                $docteur_id
            ]);
            
            $pdo->commit();
            
            $_SESSION['success'] = "Consultation mise à jour avec succès";
            // header("Location: consultations.php?action=view&id=$id");
            // exit();
            
        } elseif ($action === 'update_status' && $id) {
            // Changer le statut seulement
            $pdo->prepare("
                UPDATE consultations SET statut = ?, updated_at = NOW() 
                WHERE id = ? AND docteur_id = ?
            ")->execute([$data['statut'], $id, $docteur_id]);
            
            $pdo->commit();
            
            $_SESSION['success'] = "Statut mis à jour";
            header("Location: consultations.php?action=view&id=$id");
            exit();
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
}

// Afficher les messages
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            ' . $_SESSION['success'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            ' . $_SESSION['error'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['error']);
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-stethoscope me-2"></i>
            <?php echo $title; ?>
        </h1>
        <p class="text-muted mb-0">
            Dr. <?php echo $_SESSION['prenom'] . ' ' . $_SESSION['nom']; ?>
        </p>
    </div>
    <div class="btn-toolbar">
        <?php if ($action === 'list'): ?>
        <button class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#filterModal">
            <i class="fas fa-filter me-1"></i>Filtres
        </button>
        <a href="consultations.php?action=add" class="btn btn-sm btn-primary">
            <i class="fas fa-plus-circle me-1"></i>Nouvelle consultation
        </a>
        <?php elseif ($action !== 'add' && $action !== 'edit'): ?>
        <a href="consultations.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Retour à la liste
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Formulaire de consultation -->
<?php
$consultation = null;
$patient = null;
$rdv = null;

if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("
        SELECT c.*, p.nom as patient_nom, p.prenom as patient_prenom, p.date_naissance, p.code_patient
        FROM consultations c
        JOIN patients p ON c.patient_id = p.id
        WHERE c.id = ? AND c.docteur_id = ?
    ");
    $stmt->execute([$id, $docteur_id]);
    $consultation = $stmt->fetch();
    
    if (!$consultation) {
        echo '<div class="alert alert-danger">Consultation non trouvée ou non autorisée</div>';
        require_once '../includes/footer.php';
        exit();
    }
    
    $patient_id = $consultation['patient_id'];
}

if ($patient_id) {
    $stmt = $pdo->prepare("
        SELECT p.*, TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as age
        FROM patients p 
        WHERE p.id = ? AND p.statut = 'actif'
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
}

if ($rdv_id) {
    $stmt = $pdo->prepare("SELECT * FROM rendez_vous WHERE id = ? AND docteur_id = ?");
    $stmt->execute([$rdv_id, $docteur_id]);
    $rdv = $stmt->fetch();
}

// Récupérer les pathologies courantes
$pathologies = $pdo->query("
    SELECT * FROM pathologies ORDER BY nom
")->fetchAll();

// Récupérer les pathologies du patient
$patient_pathologies = [];
if ($patient_id) {
    $stmt = $pdo->prepare("
        SELECT pp.pathologie_id 
        FROM patient_pathologie pp
        WHERE pp.patient_id = ? AND pp.statut IN ('active', 'chronique', 'en_suivi')
    ");
    $stmt->execute([$patient_id]);
    $patient_pathologies = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<div class="row">
    <div class="col-lg-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-file-medical me-2"></i>
                    <?php echo $action === 'add' ? 'Nouvelle consultation' : 'Modifier la consultation'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="consultationForm">
                    <input type="hidden" name="rdv_id" value="<?php echo htmlspecialchars($rdv_id); ?>">
                    
                    <!-- Sélection du patient -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Patient *</label>
                            <?php if ($patient): ?>
                            <div class="card border p-3">
                                <div class="d-flex align-items-center">
                                    <div class="avatar me-3">
                                        <?php echo strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']); ?></h6>
                                        <div class="small text-muted">
                                            <?php echo htmlspecialchars($patient['code_patient']); ?> • 
                                            <?php echo htmlspecialchars($patient['age']); ?> ans • 
                                            <?php echo $patient['sexe'] == 'M' ? 'Homme' : 'Femme'; ?>
                                            <?php if ($patient['telephone']): ?>
                                            • <?php echo htmlspecialchars($patient['telephone']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient['id']); ?>">
                            </div>
                            <?php else: ?>
                            <select class="form-select" name="patient_id" id="patientSelect" required>
                                <option value="">Sélectionner un patient</option>
                                <?php
                                $stmt = $pdo->prepare("
                                    SELECT DISTINCT p.* 
                                    FROM patients p
                                    INNER JOIN consultations c ON p.id = c.patient_id
                                    WHERE c.docteur_id = ? AND p.statut = 'actif'
                                    ORDER BY p.nom, p.prenom
                                ");
                                $stmt->execute([$docteur_id]);
                                $patients = $stmt->fetchAll();
                                
                                foreach ($patients as $p): ?>
                                <option value="<?php echo htmlspecialchars($p['id']); ?>" 
                                    <?php echo ($patient_id == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['prenom'] . ' ' . $p['nom']); ?> 
                                    (<?php echo htmlspecialchars($p['code_patient']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="mt-2">
                                <a href="patients.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-search me-1"></i>Rechercher un patient
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Date et heure *</label>
                                    <input type="datetime-local" class="form-control" name="date_consultation" 
                                           value="<?php echo $consultation ? date('Y-m-d\TH:i', strtotime($consultation['date_consultation'])) : 
                                                   ($rdv ? date('Y-m-d\TH:i', strtotime($rdv['date_rdv'])) : date('Y-m-d\TH:i')); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Durée (minutes)</label>
                                    <input type="number" class="form-control" name="duree" 
                                           value="<?php echo htmlspecialchars($consultation['duree'] ?? 30); ?>" min="5" max="180">
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label">Type de consultation</label>
                                    <select class="form-select" name="type_consultation">
                                        <option value="premiere" <?php echo ($consultation['type_consultation'] ?? '') == 'premiere' ? 'selected' : ''; ?>>Première</option>
                                        <option value="suivi" <?php echo ($consultation['type_consultation'] ?? '') == 'suivi' ? 'selected' : ''; ?>>Suivi</option>
                                        <option value="urgence" <?php echo ($consultation['type_consultation'] ?? '') == 'urgence' ? 'selected' : ''; ?>>Urgence</option>
                                        <option value="controle" <?php echo ($consultation['type_consultation'] ?? '') == 'controle' ? 'selected' : ''; ?>>Contrôle</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confidentialité</label>
                                    <select class="form-select" name="confidentialite">
                                        <option value="normal" <?php echo ($consultation['confidentialite'] ?? '') == 'normal' ? 'selected' : ''; ?>>Normal</option>
                                        <option value="confidentiel" <?php echo ($consultation['confidentialite'] ?? '') == 'confidentiel' ? 'selected' : ''; ?>>Confidentiel</option>
                                        <option value="tres_confidentiel" <?php echo ($consultation['confidentialite'] ?? '') == 'tres_confidentiel' ? 'selected' : ''; ?>>Très confidentiel</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informations de base -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <label class="form-label">Motif de la consultation *</label>
                            <textarea class="form-control" name="motif" rows="2" required><?php echo htmlspecialchars($consultation['motif'] ?? ($rdv['motif'] ?? '')); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Anamnèse -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Anamnèse</h6>
                                </div>
                                <div class="card-body">
                                    <label class="form-label">Histoire de la maladie</label>
                                    <textarea class="form-control" name="histoire_maladie" rows="4"><?php echo htmlspecialchars($consultation['histoire_maladie'] ?? ''); ?></textarea>
                                    
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Examen clinique</label>
                                            <textarea class="form-control" name="examen_clinique" rows="4"><?php echo htmlspecialchars($consultation['examen_clinique'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Examens complémentaires</label>
                                            <textarea class="form-control" name="examen_complementaire" rows="4"><?php echo htmlspecialchars($consultation['examen_complementaire'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Diagnostic et traitement -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card border h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-diagnoses me-2"></i>Diagnostic</h6>
                                </div>
                                <div class="card-body">
                                    <label class="form-label">Diagnostic principal *</label>
                                    <textarea class="form-control" name="diagnostic" rows="4" required><?php echo htmlspecialchars($consultation['diagnostic'] ?? ''); ?></textarea>
                                    
                                    <div class="mt-3">
                                        <label class="form-label">Pathologies associées</label>
                                        <div class="border rounded p-2" style="max-height: 150px; overflow-y: auto;">
                                            <?php foreach ($pathologies as $patho): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="pathologies[]" 
                                                       value="<?php echo htmlspecialchars($patho['id']); ?>" 
                                                       id="patho_<?php echo htmlspecialchars($patho['id']); ?>"
                                                       <?php echo in_array($patho['id'], $patient_pathologies) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="patho_<?php echo htmlspecialchars($patho['id']); ?>">
                                                    <?php echo htmlspecialchars($patho['nom']); ?>
                                                    <?php if ($patho['code_cim']): ?>
                                                    <span class="text-muted">(<?php echo htmlspecialchars($patho['code_cim']); ?>)</span>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-pills me-2"></i>Traitement et recommandations</h6>
                                </div>
                                <div class="card-body">
                                    <label class="form-label">Traitement prescrit</label>
                                    <textarea class="form-control" name="traitement" rows="4"><?php echo htmlspecialchars($consultation['traitement'] ?? ''); ?></textarea>
                                    
                                    <div class="mt-3">
                                        <label class="form-label">Recommandations</label>
                                        <textarea class="form-control" name="recommandations" rows="3"><?php echo htmlspecialchars($consultation['recommandations'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <label class="form-label">Notes complémentaires</label>
                                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($consultation['notes'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Options et statut -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="urgence" id="urgenceCheck" 
                                       value="1" <?php echo ($consultation['urgence'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold text-danger" for="urgenceCheck">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Consultation urgente
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Statut de la consultation</label>
                            <select class="form-select" name="statut" required>
                                <option value="planifie" <?php echo ($consultation['statut'] ?? '') == 'planifie' ? 'selected' : ''; ?>>Planifiée</option>
                                <option value="en_cours" <?php echo ($consultation['statut'] ?? '') == 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                                <option value="termine" <?php echo ($consultation['statut'] ?? '') == 'termine' ? 'selected' : ''; ?>>Terminée</option>
                                <option value="annule" <?php echo ($consultation['statut'] ?? '') == 'annule' ? 'selected' : ''; ?>>Annulée</option>
                                <option value="reporte" <?php echo ($consultation['statut'] ?? '') == 'reporte' ? 'selected' : ''; ?>>Reportée</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="createPrescriptionCheck">
                                <label class="form-check-label" for="createPrescriptionCheck">
                                    Créer une prescription après enregistrement
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Boutons d'action -->
                    <div class="d-flex justify-content-between">
                        <div>
                            <a href="consultations.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>Annuler
                            </a>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                <?php echo $action === 'add' ? 'Enregistrer la consultation' : 'Mettre à jour'; ?>
                            </button>
                            <?php if ($action === 'add'): ?>
                            <button type="submit" name="save_and_prescribe" value="1" class="btn btn-success ms-2">
                                <i class="fas fa-prescription me-1"></i>Enregistrer et créer une prescription
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'view' && $id): ?>
<!-- Vue détaillée d'une consultation -->
<?php
$stmt = $pdo->prepare("
    SELECT c.*, p.nom as patient_nom, p.prenom as patient_prenom, p.date_naissance, 
           p.code_patient, p.telephone, p.email, p.groupe_sanguin, p.allergies,
           TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as patient_age,
           u.nom as docteur_nom, u.prenom as docteur_prenom, u.specialite
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    JOIN utilisateurs u ON c.docteur_id = u.id
    WHERE c.id = ? AND c.docteur_id = ?
");
$stmt->execute([$id, $docteur_id]);
$consultation = $stmt->fetch();

if (!$consultation) {
    echo '<div class="alert alert-danger">Consultation non trouvée ou non autorisée</div>';
    require_once '../includes/footer.php';
    exit();
}

// Récupérer les prescriptions liées
$stmt = $pdo->prepare("
    SELECT p.* 
    FROM prescriptions p
    WHERE p.consultation_id = ?
    ORDER BY p.date_prescription DESC
");
$stmt->execute([$id]);
$prescriptions = $stmt->fetchAll();

// Récupérer les documents médicaux
$stmt = $pdo->prepare("
    SELECT d.* 
    FROM documents_medicaux d
    WHERE d.consultation_id = ?
    ORDER BY d.created_at DESC
");
$stmt->execute([$id]);
$documents = $stmt->fetchAll();

// Récupérer les pathologies diagnostiquées
$stmt = $pdo->prepare("
    SELECT pp.*, pat.nom as pathologie_nom, pat.code_cim
    FROM patient_pathologie pp
    JOIN pathologies pat ON pp.pathologie_id = pat.id
    WHERE pp.patient_id = ? AND pp.diagnostic_par = ? 
    AND DATE(pp.date_diagnostic) = DATE(?)
    ORDER BY pp.gravite DESC
");
$stmt->execute([$consultation['patient_id'], $docteur_id, $consultation['date_consultation']]);
$pathologies = $stmt->fetchAll();
?>
<div class="row">
    <!-- Informations consultation -->
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Consultation <?php echo htmlspecialchars($consultation['reference'] ?? 'N/A'); ?></h5>
                    <div class="text-muted small">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('d/m/Y H:i', strtotime($consultation['date_consultation'])); ?>
                        • <?php echo htmlspecialchars($consultation['duree']); ?> minutes
                        • <?php echo ucfirst($consultation['type_consultation']); ?>
                    </div>
                </div>
                <div class="btn-group">
                    <a href="consultations.php?action=edit&id=<?php echo htmlspecialchars($consultation['id']); ?>" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="prescriptions.php?action=add&consultation_id=<?php echo htmlspecialchars($consultation['id']); ?>" 
                       class="btn btn-sm btn-outline-success">
                        <i class="fas fa-prescription"></i>
                    </a>
                    <a href="documents.php?action=add&consultation_id=<?php echo htmlspecialchars($consultation['id']); ?>" 
                       class="btn btn-sm btn-outline-info">
                        <i class="fas fa-file-medical"></i>
                    </a>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Patient -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Patient</h6>
                        <div class="d-flex align-items-center">
                            <div class="avatar me-3">
                                <?php echo strtoupper(substr($consultation['patient_prenom'], 0, 1) . substr($consultation['patient_nom'], 0, 1)); ?>
                            </div>
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($consultation['patient_prenom'] . ' ' . $consultation['patient_nom']); ?></h5>
                                <div class="small text-muted">
                                    <?php echo htmlspecialchars($consultation['code_patient']); ?> • 
                                    <?php echo htmlspecialchars($consultation['patient_age']); ?> ans • 
                                    <?php echo htmlspecialchars($consultation['telephone']); ?>
                                    <?php if ($consultation['groupe_sanguin']): ?>
                                    • <span class="badge bg-danger"><?php echo htmlspecialchars($consultation['groupe_sanguin']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Médecin</h6>
                        <div class="d-flex align-items-center">
                            <div class="avatar me-3 bg-primary">
                                <?php echo strtoupper(substr($consultation['docteur_prenom'], 0, 1) . substr($consultation['docteur_nom'], 0, 1)); ?>
                            </div>
                            <div>
                                <h5 class="mb-1">Dr. <?php echo htmlspecialchars($consultation['docteur_prenom'] . ' ' . $consultation['docteur_nom']); ?></h5>
                                <div class="small text-muted">
                                    <?php echo htmlspecialchars($consultation['specialite']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statut et urgence -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex align-items-center">
                            <?php 
                            $statusColors = [
                                'planifie' => 'warning',
                                'en_cours' => 'info',
                                'termine' => 'success',
                                'annule' => 'danger',
                                'reporte' => 'secondary'
                            ];
                            $confidentialiteColors = [
                                'normal' => 'success',
                                'confidentiel' => 'warning',
                                'tres_confidentiel' => 'danger'
                            ];
                            ?>
                            <span class="badge bg-<?php echo $statusColors[$consultation['statut']] ?? 'secondary'; ?> me-3">
                                <?php echo ucfirst($consultation['statut']); ?>
                            </span>
                            
                            <?php if ($consultation['urgence']): ?>
                            <span class="badge bg-danger me-3">
                                <i class="fas fa-exclamation-triangle me-1"></i>Urgent
                            </span>
                            <?php endif; ?>
                            
                            <span class="badge bg-<?php echo $confidentialiteColors[$consultation['confidentialite']] ?? 'secondary'; ?>">
                                <?php echo ucfirst($consultation['confidentialite']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Motif -->
                <?php if ($consultation['motif']): ?>
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Motif de la consultation</h6>
                    <div class="card border bg-light">
                        <div class="card-body">
                            <?php echo nl2br(htmlspecialchars($consultation['motif'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Anamnèse -->
                <?php if ($consultation['histoire_maladie'] || $consultation['examen_clinique'] || $consultation['examen_complementaire']): ?>
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Anamnèse</h6>
                    <div class="card border">
                        <div class="card-body">
                            <?php if ($consultation['histoire_maladie']): ?>
                            <div class="mb-3">
                                <strong>Histoire de la maladie:</strong>
                                <div class="mt-1"><?php echo nl2br(htmlspecialchars($consultation['histoire_maladie'])); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($consultation['examen_clinique']): ?>
                            <div class="mb-3">
                                <strong>Examen clinique:</strong>
                                <div class="mt-1"><?php echo nl2br(htmlspecialchars($consultation['examen_clinique'])); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($consultation['examen_complementaire']): ?>
                            <div>
                                <strong>Examens complémentaires:</strong>
                                <div class="mt-1"><?php echo nl2br(htmlspecialchars($consultation['examen_complementaire'])); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Diagnostic -->
                <?php if ($consultation['diagnostic']): ?>
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Diagnostic</h6>
                    <div class="card border border-success">
                        <div class="card-body">
                            <?php echo nl2br(htmlspecialchars($consultation['diagnostic'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Pathologies diagnostiquées -->
                <?php if (!empty($pathologies)): ?>
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Pathologies diagnostiquées</h6>
                    <div class="card border">
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($pathologies as $patho): 
                                    $graviteColors = [
                                        'legere' => 'success',
                                        'moderee' => 'warning',
                                        'grave' => 'danger'
                                    ];
                                ?>
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                                        <div>
                                            <strong><?php echo htmlspecialchars($patho['pathologie_nom']); ?></strong>
                                            <div class="small text-muted"><?php echo htmlspecialchars($patho['code_cim']); ?></div>
                                        </div>
                                        <span class="badge bg-<?php echo $graviteColors[$patho['gravite']] ?? 'secondary'; ?>">
                                            <?php echo ucfirst($patho['gravite']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Traitement et recommandations -->
                <?php if ($consultation['traitement'] || $consultation['recommandations']): ?>
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Traitement et recommandations</h6>
                    <div class="card border border-primary">
                        <div class="card-body">
                            <?php if ($consultation['traitement']): ?>
                            <div class="mb-3">
                                <strong>Traitement prescrit:</strong>
                                <div class="mt-1"><?php echo nl2br(htmlspecialchars($consultation['traitement'])); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($consultation['recommandations']): ?>
                            <div>
                                <strong>Recommandations:</strong>
                                <div class="mt-1"><?php echo nl2br(htmlspecialchars($consultation['recommandations'])); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Notes -->
                <?php if ($consultation['notes']): ?>
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Notes complémentaires</h6>
                    <div class="card border bg-light">
                        <div class="card-body">
                            <?php echo nl2br(htmlspecialchars($consultation['notes'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar avec prescriptions et documents -->
    <div class="col-lg-4">
        <!-- Prescriptions -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-prescription me-2"></i>Prescriptions</h6>
                <a href="prescriptions.php?action=add&consultation_id=<?php echo htmlspecialchars($consultation['id']); ?>" 
                   class="btn btn-sm btn-outline-success">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($prescriptions)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-prescription-bottle-alt fa-2x text-muted mb-3"></i>
                    <p class="text-muted small">Aucune prescription</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($prescriptions as $pres): ?>
                    <div class="list-group-item border-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($pres['reference']); ?></h6>
                                <div class="small text-muted">
                                    <?php echo date('d/m/Y', strtotime($pres['date_prescription'])); ?>
                                    • <?php echo htmlspecialchars($pres['duree_traitement']); ?>
                                </div>
                                <div class="mt-2">
                                    <span class="badge bg-<?php echo $pres['statut'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($pres['statut']); ?>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <a href="prescriptions.php?action=view&id=<?php echo htmlspecialchars($pres['id']); ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Documents médicaux -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-file-medical me-2"></i>Documents</h6>
                <a href="documents.php?action=add&consultation_id=<?php echo htmlspecialchars($consultation['id']); ?>" 
                   class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($documents)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-file-alt fa-2x text-muted mb-3"></i>
                    <p class="text-muted small">Aucun document</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($documents as $doc): 
                        $typeIcons = [
                            'ordonnance' => 'fas fa-prescription',
                            'certificat' => 'fas fa-certificate',
                            'resultat_analyse' => 'fas fa-vial',
                            'compte_rendu' => 'fas fa-file-medical',
                            'imagerie' => 'fas fa-x-ray',
                            'autre' => 'fas fa-file'
                        ];
                    ?>
                    <div class="list-group-item border-0">
                        <div class="d-flex align-items-start">
                            <div class="me-3">
                                <i class="<?php echo $typeIcons[$doc['type_document']] ?? 'fas fa-file'; ?> fa-lg text-primary"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($doc['titre']); ?></h6>
                                <div class="small text-muted">
                                    <?php echo ucfirst(str_replace('_', ' ', $doc['description'])); ?>
                                    • <?php echo date('d/m/Y', strtotime($doc['created_at'])); ?>
                                </div>
                            </div>
                            <div>
                                <a href="../assets/documents/<?php echo htmlspecialchars($doc['fichier']); ?>" 
                                   target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                        <i class="fas fa-sync me-1"></i>Changer le statut
                    </button>
                    <a href="consultations.php?action=print&id=<?php echo htmlspecialchars($consultation['id']); ?>" 
                       target="_blank" class="btn btn-outline-success">
                        <i class="fas fa-print me-1"></i>Imprimer le compte-rendu
                    </a>
                    <button class="btn btn-outline-info" onclick="duplicateConsultation(<?php echo htmlspecialchars($consultation['id']); ?>)">
                        <i class="fas fa-copy me-1"></i>Dupliquer la consultation
                    </button>
                    <button class="btn btn-outline-warning" onclick="createFollowUp(<?php echo htmlspecialchars($consultation['id']); ?>)">
                        <i class="fas fa-calendar-plus me-1"></i>Planifier un suivi
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour changer le statut -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Changer le statut de la consultation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?action=update_status&id=<?php echo htmlspecialchars($consultation['id']); ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nouveau statut</label>
                        <select class="form-select" name="statut" required>
                            <option value="planifie" <?php echo $consultation['statut'] == 'planifie' ? 'selected' : ''; ?>>Planifiée</option>
                            <option value="en_cours" <?php echo $consultation['statut'] == 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                            <option value="termine" <?php echo $consultation['statut'] == 'termine' ? 'selected' : ''; ?>>Terminée</option>
                            <option value="annule" <?php echo $consultation['statut'] == 'annule' ? 'selected' : ''; ?>>Annulée</option>
                            <option value="reporte" <?php echo $consultation['statut'] == 'reporte' ? 'selected' : ''; ?>>Reportée</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Liste des consultations -->
<?php
// Filtres
$date = $_GET['date'] ?? '';
$statut = $_GET['statut'] ?? '';
$type = $_GET['type'] ?? '';
$urgence = isset($_GET['urgence']) ? 1 : 0;
// CORRECTION: Ajouter la définition de $search
$search = $_GET['search'] ?? '';

// Construire la requête
$sql = "SELECT c.*, p.nom as patient_nom, p.prenom as patient_prenom, p.code_patient,
               TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as patient_age,
               (SELECT COUNT(*) FROM prescriptions WHERE consultation_id = c.id) as prescriptions_count,
               (SELECT COUNT(*) FROM documents_medicaux WHERE consultation_id = c.id) as documents_count
        FROM consultations c
        JOIN patients p ON c.patient_id = p.id
        WHERE c.docteur_id = ?";
        
$params = [$docteur_id];

if ($date) {
    $sql .= " AND DATE(c.date_consultation) = ?";
    $params[] = $date;
}

if ($statut) {
    $sql .= " AND c.statut = ?";
    $params[] = $statut;
}

if ($type) {
    $sql .= " AND c.type_consultation = ?";
    $params[] = $type;
}

if ($urgence) {
    $sql .= " AND c.urgence = 1";
}

// CORRECTION: Vérifier si $search n'est pas vide avant d'ajouter la condition
if (!empty($search)) {
    $sql .= " AND (p.nom LIKE ? OR p.prenom LIKE ? OR p.code_patient LIKE ? OR c.reference LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, array_fill(count($params), 4, $searchTerm));
}

$sql .= " ORDER BY c.date_consultation DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$consultations = $stmt->fetchAll();
?>
<div class="card shadow-sm">
    <div class="card-header bg-white border-bottom">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Historique des consultations
                </h6>
            </div>
            <div class="col-md-6">
                <form method="GET" class="d-flex">
                    <input type="hidden" name="action" value="list">
                    <input type="text" class="form-control me-2" name="search" 
                           placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Motif</th>
                        <th>Diagnostic</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($consultations as $consult): 
                        $statusColors = [
                            'planifie' => 'warning',
                            'en_cours' => 'info',
                            'termine' => 'success',
                            'annule' => 'danger',
                            'reporte' => 'secondary'
                        ];
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo date('d/m H:i', strtotime($consult['date_consultation'])); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($consult['duree']); ?> min</small>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($consult['patient_prenom'] . ' ' . $consult['patient_nom']); ?></div>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($consult['code_patient']); ?> • 
                                <?php echo htmlspecialchars($consult['patient_age']); ?> ans
                            </small>
                        </td>
                        <td>
                            <?php if ($consult['motif']): ?>
                            <span title="<?php echo htmlspecialchars($consult['motif']); ?>">
                                <?php echo substr($consult['motif'], 0, 50); ?>
                                <?php if (strlen($consult['motif']) > 50): ?>...<?php endif; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">Non spécifié</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($consult['diagnostic']): ?>
                            <span title="<?php echo htmlspecialchars($consult['diagnostic']); ?>">
                                <?php echo substr($consult['diagnostic'], 0, 50); ?>
                                <?php if (strlen($consult['diagnostic']) > 50): ?>...<?php endif; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">Non spécifié</span>
                            <?php endif; ?>
                            <div class="mt-1">
                                <?php if ($consult['prescriptions_count'] > 0): ?>
                                <span class="badge bg-info me-1">
                                    <i class="fas fa-prescription me-1"></i><?php echo htmlspecialchars($consult['prescriptions_count']); ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($consult['documents_count'] > 0): ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-file me-1"></i><?php echo htmlspecialchars($consult['documents_count']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $statusColors[$consult['statut']] ?? 'secondary'; ?>">
                                <?php echo ucfirst($consult['statut']); ?>
                            </span>
                            <?php if ($consult['urgence']): ?>
                            <span class="badge bg-danger ms-1">
                                <i class="fas fa-exclamation-triangle"></i>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=view&id=<?php echo htmlspecialchars($consult['id']); ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=edit&id=<?php echo htmlspecialchars($consult['id']); ?>" 
                                   class="btn btn-outline-secondary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($consult['statut'] == 'termine'): ?>
                                <a href="prescriptions.php?action=add&consultation_id=<?php echo htmlspecialchars($consult['id']); ?>" 
                                   class="btn btn-outline-success">
                                    <i class="fas fa-prescription"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($consultations)): ?>
        <div class="text-center py-5">
            <i class="fas fa-stethoscope fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Aucune consultation trouvée</h5>
            <p class="text-muted">Commencez par créer votre première consultation</p>
            <a href="consultations.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Créer une consultation
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="card-footer bg-white border-top">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <small class="text-muted">
                    Total: <?php echo count($consultations); ?> consultation(s)
                </small>
            </div>
            <div>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                    <i class="fas fa-filter me-1"></i>Filtres
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Filtres -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filtrer les consultations</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="GET" id="filterForm">
                <input type="hidden" name="action" value="list">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Statut</label>
                            <select class="form-select" name="statut">
                                <option value="">Tous les statuts</option>
                                <option value="planifie" <?php echo $statut == 'planifie' ? 'selected' : ''; ?>>Planifiée</option>
                                <option value="en_cours" <?php echo $statut == 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                                <option value="termine" <?php echo $statut == 'termine' ? 'selected' : ''; ?>>Terminée</option>
                                <option value="annule" <?php echo $statut == 'annule' ? 'selected' : ''; ?>>Annulée</option>
                                <option value="reporte" <?php echo $statut == 'reporte' ? 'selected' : ''; ?>>Reportée</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <option value="">Tous les types</option>
                                <option value="premiere" <?php echo $type == 'premiere' ? 'selected' : ''; ?>>Première</option>
                                <option value="suivi" <?php echo $type == 'suivi' ? 'selected' : ''; ?>>Suivi</option>
                                <option value="urgence" <?php echo $type == 'urgence' ? 'selected' : ''; ?>>Urgence</option>
                                <option value="controle" <?php echo $type == 'controle' ? 'selected' : ''; ?>>Contrôle</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Options</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="urgence" id="urgenceCheck" 
                                       value="1" <?php echo $urgence ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="urgenceCheck">
                                    Consultations urgentes seulement
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="consultations.php" class="btn btn-secondary">Réinitialiser</a>
                    <button type="submit" class="btn btn-primary">Appliquer les filtres</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

<script>
// Initialiser les tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
    
    // Charger les informations du patient lorsqu'il est sélectionné
    const patientSelect = document.getElementById('patientSelect');
    if (patientSelect) {
        patientSelect.addEventListener('change', function() {
            loadPatientInfo(this.value);
        });
    }
});

// Charger les informations d'un patient
function loadPatientInfo(patientId) {
    if (!patientId) return;
    
    fetch(`ajax/get_patient_info.php?id=${patientId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour les informations dans le formulaire
                updatePatientInfo(data.patient);
            }
        });
}

function updatePatientInfo(patient) {
    // Vous pouvez ajouter ici la logique pour pré-remplir certains champs
    console.log('Patient info loaded:', patient);
}

// Dupliquer une consultation
function duplicateConsultation(consultationId) {
    if (confirm('Dupliquer cette consultation ?')) {
        window.location.href = `consultations.php?action=add&duplicate=${consultationId}`;
    }
}

// Créer un rendez-vous de suivi
function createFollowUp(consultationId) {
    window.open(`rendezvous.php?action=add&consultation_id=${consultationId}`, '_blank');
}

// Imprimer une consultation
function printConsultation(consultationId) {
    window.open(`consultations.php?action=print&id=${consultationId}`, '_blank');
}

// Exporter les consultations
function exportConsultations() {
    const params = new URLSearchParams(window.location.search);
    params.append('export', 'csv');
    window.location.href = `consultations.php?${params.toString()}`;
}
</script>

<style>
.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #4361ee;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

/* Styles pour les formulaires */
.form-section {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
}

.form-section h6 {
    border-bottom: 2px solid #4361ee;
    padding-bottom: 10px;
    margin-bottom: 20px;
    color: #374151;
}

/* Styles pour les statuts */
.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
    .btn-group {
        flex-wrap: wrap;
    }
    
    .btn-group .btn {
        margin-bottom: 5px;
    }
}
</style>