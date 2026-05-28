<?php
// admin/consultations.php
require_once '../config/database.php';
checkRole('admin');

$title = 'Gestion des Consultations';
require_once '../includes/header.php';

$pdo = Database::getInstance()->getConnection();


// Traitement CRUD
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Types de consultations
$types_consultation = ['premiere', 'suivi', 'urgence', 'controle'];

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = sanitize($_POST);
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("
                INSERT INTO consultations 
                (reference, patient_id, docteur_id, assistant_id, date_consultation, duree,
                 type_consultation, motif, histoire_maladie, examen_clinique, 
                 examen_complementaire, diagnostic, traitement, recommandations,
                 notes, statut, urgence, confidentialite, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // La référence est générée automatiquement par le trigger
            $stmt->execute([
                'TEMP', // Sera remplacé par le trigger
                $data['patient_id'],
                $data['docteur_id'],
                $data['assistant_id'] ?? null,
                $data['date_consultation'] . ' ' . $data['heure_consultation'],
                $data['duree'] ?? 30,
                $data['type_consultation'],
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
                $_SESSION['user_id']
            ]);
            
            $consultationId = $pdo->lastInsertId();
            
            // Journaliser l'action
            logAction('CREATE', 'consultations', $consultationId, "Création consultation");
            
            header("Location: consultations.php?success=Consultation créée avec succès");
            exit();
            
        } elseif ($action === 'edit' && $id) {
            $stmt = $pdo->prepare("
                UPDATE consultations SET 
                patient_id = ?, docteur_id = ?, assistant_id = ?, date_consultation = ?, duree = ?,
                type_consultation = ?, motif = ?, histoire_maladie = ?, examen_clinique = ?, 
                examen_complementaire = ?, diagnostic = ?, traitement = ?, recommandations = ?,
                notes = ?, statut = ?, urgence = ?, confidentialite = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['patient_id'],
                $data['docteur_id'],
                $data['assistant_id'] ?? null,
                $data['date_consultation'] . ' ' . $data['heure_consultation'],
                $data['duree'] ?? 30,
                $data['type_consultation'],
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
                $id
            ]);
            
            // Journaliser l'action
            logAction('UPDATE', 'consultations', $id, "Modification consultation ID: $id");
            
            header("Location: consultations.php?success=Consultation modifiée avec succès");
            exit();
            
        } elseif ($action === 'delete' && $id) {
            // Vérifier s'il y a des prescriptions associées
            $hasPrescriptions = $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE consultation_id = ?")->execute([$id])->fetchColumn();
            
            if ($hasPrescriptions > 0) {
                throw new Exception("Cette consultation a des prescriptions associées. Impossible de supprimer.");
            }
            
            $pdo->prepare("DELETE FROM consultations WHERE id = ?")->execute([$id]);
            
            // Journaliser l'action
            logAction('DELETE', 'consultations', $id, "Suppression consultation ID: $id");
            
            header("Location: consultations.php?success=Consultation supprimée avec succès");
            exit();
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: consultations.php?action=$action&id=$id&error=" . urlencode($e->getMessage()));
        exit();
    }
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-stethoscope me-2"></i>Gestion des Consultations
        </h1>
        <p class="text-muted mb-0">Administration des consultations médicales</p>
    </div>
    <div class="btn-toolbar">
        <?php if ($action === 'list'): ?>
        <div class="btn-group me-2">
            <a href="?action=add" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i>Nouvelle consultation
            </a>
            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" 
                    data-bs-toggle="dropdown">
                <span class="visually-hidden">Options</span>
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="agenda.php">
                    <i class="fas fa-calendar-alt me-2"></i>Voir l'agenda
                </a>
                <a class="dropdown-item" href="rapports.php?type=consultations">
                    <i class="fas fa-chart-bar me-2"></i>Rapport consultations
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#" onclick="exportConsultations()">
                    <i class="fas fa-file-export me-2"></i>Exporter
                </a>
            </div>
        </div>
        <?php else: ?>
        <a href="consultations.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Retour à la liste
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Messages -->
<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Formulaire Ajout/Modification -->
<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-file-medical me-2"></i>
                    <?php echo $action === 'add' ? 'Nouvelle consultation' : 'Modifier la consultation'; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php
                $consultation = null;
                if ($action === 'edit' && $id) {
                    $consultation = $pdo->prepare("SELECT * FROM consultations WHERE id = ?")->execute([$id]);
                    if (!$consultation) {
                        echo '<div class="alert alert-danger">Consultation non trouvée</div>';
                        require_once '../includes/footer.php';
                        exit();
                    }
                    
                    // Séparer date et heure
                    $date_consultation = date('Y-m-d', strtotime($consultation['date_consultation']));
                    $heure_consultation = date('H:i', strtotime($consultation['date_consultation']));
                }
                ?>
                
                <form method="POST" id="consultationForm" novalidate>
                    <!-- Onglets -->
                    <ul class="nav nav-tabs mb-4" id="consultationTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="info-tab" data-bs-toggle="tab" 
                                    data-bs-target="#info" type="button">Informations générales</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="medical-tab" data-bs-toggle="tab" 
                                    data-bs-target="#medical" type="button">Examen médical</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="result-tab" data-bs-toggle="tab" 
                                    data-bs-target="#result" type="button">Diagnostic et traitement</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="consultationTabsContent">
                        <!-- Onglet Informations générales -->
                        <div class="tab-pane fade show active" id="info" role="tabpanel">
                            <div class="row g-3">
                                <!-- Patient -->
                                <div class="col-md-6">
                                    <label class="form-label required">Patient</label>
                                    <select class="form-select" name="patient_id" id="patientSelect" required 
                                            onchange="loadPatientInfo(this.value)">
                                        <option value="">Sélectionner un patient</option>
                                        <?php
                                        $patients = $pdo->query("SELECT id, nom, prenom, code_patient FROM patients WHERE statut = 'actif' ORDER BY nom, prenom")->fetchAll();
                                        foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>" 
                                            <?php echo ($consultation['patient_id'] ?? '') == $patient['id'] ? 'selected' : ''; ?>>
                                            <?php echo $patient['prenom'] . ' ' . $patient['nom'] . ' (' . $patient['code_patient'] . ')'; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Informations patient (chargées via AJAX) -->
                                <div class="col-md-6" id="patientInfo">
                                    <?php if ($action === 'edit' && $consultation): 
                                        $patient = $pdo->prepare("SELECT * FROM patients WHERE id = ?")->execute([$consultation['patient_id']]);
                                        if ($patient): ?>
                                        <div class="card bg-light">
                                            <div class="card-body p-3">
                                                <small>
                                                    <strong>Âge:</strong> <?php echo calculateAge($patient['date_naissance']); ?> ans<br>
                                                    <strong>Allergies:</strong> <?php echo $patient['allergies'] ?: 'Aucune'; ?><br>
                                                    <strong>Traitements:</strong> <?php echo $patient['medicaments_habituels'] ?: 'Aucun'; ?>
                                                </small>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Médecin -->
                                <div class="col-md-6">
                                    <label class="form-label required">Médecin</label>
                                    <select class="form-select" name="docteur_id" required>
                                        <option value="">Sélectionner un médecin</option>
                                        <?php
                                        $docteurs = $pdo->query("SELECT id, nom, prenom, specialite FROM utilisateurs WHERE role = 'docteur' AND statut = 'actif' ORDER BY nom, prenom")->fetchAll();
                                        foreach ($docteurs as $docteur): ?>
                                        <option value="<?php echo $docteur['id']; ?>" 
                                            <?php echo ($consultation['docteur_id'] ?? '') == $docteur['id'] ? 'selected' : ''; ?>>
                                            Dr. <?php echo $docteur['prenom'] . ' ' . $docteur['nom']; ?> - <?php echo $docteur['specialite']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Assistant -->
                                <div class="col-md-6">
                                    <label class="form-label">Assistant</label>
                                    <select class="form-select" name="assistant_id">
                                        <option value="">Sélectionner un assistant</option>
                                        <?php
                                        $assistants = $pdo->query("SELECT id, nom, prenom FROM utilisateurs WHERE role = 'assistant' AND statut = 'actif' ORDER BY nom, prenom")->fetchAll();
                                        foreach ($assistants as $assistant): ?>
                                        <option value="<?php echo $assistant['id']; ?>" 
                                            <?php echo ($consultation['assistant_id'] ?? '') == $assistant['id'] ? 'selected' : ''; ?>>
                                            <?php echo $assistant['prenom'] . ' ' . $assistant['nom']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Date et heure -->
                                <div class="col-md-6">
                                    <label class="form-label required">Date de consultation</label>
                                    <input type="date" class="form-control" name="date_consultation" 
                                           value="<?php echo $date_consultation ?? date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Heure</label>
                                    <input type="time" class="form-control" name="heure_consultation" 
                                           value="<?php echo $heure_consultation ?? '09:00'; ?>" required>
                                </div>
                                
                                <!-- Durée et type -->
                                <div class="col-md-6">
                                    <label class="form-label">Durée (minutes)</label>
                                    <input type="number" class="form-control" name="duree" 
                                           value="<?php echo $consultation['duree'] ?? '30'; ?>" min="5" max="240">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Type de consultation</label>
                                    <select class="form-select" name="type_consultation" required>
                                        <option value="">Sélectionner</option>
                                        <?php foreach ($types_consultation as $type): ?>
                                        <option value="<?php echo $type; ?>" 
                                            <?php echo ($consultation['type_consultation'] ?? '') == $type ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($type); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Motif -->
                                <div class="col-12">
                                    <label class="form-label required">Motif de consultation</label>
                                    <textarea class="form-control" name="motif" rows="2" required><?php echo $consultation['motif'] ?? ''; ?></textarea>
                                </div>
                                
                                <!-- Statut et confidentialité -->
                                <div class="col-md-6">
                                    <label class="form-label required">Statut</label>
                                    <select class="form-select" name="statut" required>
                                        <option value="planifie" <?php echo ($consultation['statut'] ?? 'planifie') == 'planifie' ? 'selected' : ''; ?>>Planifié</option>
                                        <option value="en_cours" <?php echo ($consultation['statut'] ?? '') == 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                                        <option value="termine" <?php echo ($consultation['statut'] ?? '') == 'termine' ? 'selected' : ''; ?>>Terminé</option>
                                        <option value="annule" <?php echo ($consultation['statut'] ?? '') == 'annule' ? 'selected' : ''; ?>>Annulé</option>
                                        <option value="reporte" <?php echo ($consultation['statut'] ?? '') == 'reporte' ? 'selected' : ''; ?>>Reporté</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confidentialité</label>
                                    <select class="form-select" name="confidentialite">
                                        <option value="normal" <?php echo ($consultation['confidentialite'] ?? 'normal') == 'normal' ? 'selected' : ''; ?>>Normal</option>
                                        <option value="confidentiel" <?php echo ($consultation['confidentialite'] ?? '') == 'confidentiel' ? 'selected' : ''; ?>>Confidentiel</option>
                                        <option value="tres_confidentiel" <?php echo ($consultation['confidentialite'] ?? '') == 'tres_confidentiel' ? 'selected' : ''; ?>>Très confidentiel</option>
                                    </select>
                                </div>
                                
                                <!-- Urgence -->
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="urgence" value="1" 
                                               id="urgenceCheck" <?php echo ($consultation['urgence'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label text-danger fw-semibold" for="urgenceCheck">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Consultation urgente
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Onglet Examen médical -->
                        <div class="tab-pane fade" id="medical" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Histoire de la maladie</label>
                                    <textarea class="form-control" name="histoire_maladie" rows="4"><?php echo $consultation['histoire_maladie'] ?? ''; ?></textarea>
                                    <small class="text-muted">Début, évolution, symptômes, facteurs déclenchants...</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Examen clinique</label>
                                    <textarea class="form-control" name="examen_clinique" rows="6"><?php echo $consultation['examen_clinique'] ?? ''; ?></textarea>
                                    <small class="text-muted">Signes physiques, constantes, examen système par système...</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Examens complémentaires</label>
                                    <textarea class="form-control" name="examen_complementaire" rows="6"><?php echo $consultation['examen_complementaire'] ?? ''; ?></textarea>
                                    <small class="text-muted">Biologie, imagerie, autres examens demandés...</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Onglet Diagnostic et traitement -->
                        <div class="tab-pane fade" id="result" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Diagnostic</label>
                                    <textarea class="form-control" name="diagnostic" rows="4"><?php echo $consultation['diagnostic'] ?? ''; ?></textarea>
                                    <small class="text-muted">Diagnostic principal et diagnostics associés</small>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Traitement prescrit</label>
                                    <textarea class="form-control" name="traitement" rows="4"><?php echo $consultation['traitement'] ?? ''; ?></textarea>
                                    <small class="text-muted">Médicaments, posologie, durée, autres traitements...</small>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Recommandations</label>
                                    <textarea class="form-control" name="recommandations" rows="3"><?php echo $consultation['recommandations'] ?? ''; ?></textarea>
                                    <small class="text-muted">Conseils hygiéno-diététiques, suivi, précautions...</small>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Notes complémentaires</label>
                                    <textarea class="form-control" name="notes" rows="3"><?php echo $consultation['notes'] ?? ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i>
                            <?php echo $action === 'add' ? 'Créer la consultation' : 'Enregistrer les modifications'; ?>
                        </button>
                        <button type="reset" class="btn btn-secondary ms-2">Réinitialiser</button>
                        <a href="consultations.php" class="btn btn-outline-secondary ms-2">Annuler</a>
                        
                        <?php if ($action === 'edit'): ?>
                        <button type="button" class="btn btn-success ms-2" onclick="generatePrescription(<?php echo $id; ?>)">
                            <i class="fas fa-prescription me-1"></i>Générer une prescription
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Liste des consultations -->
<div class="card shadow-sm">
    <div class="card-header bg-white border-bottom">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Liste des consultations
                </h6>
            </div>
            <div class="col-md-6">
                <form method="GET" class="row g-2">
                    <div class="col">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Rechercher..." value="<?php echo $_GET['search'] ?? ''; ?>"
                               id="searchInput">
                    </div>
                    <div class="col-auto">
                        <select class="form-select" name="statut" onchange="this.form.submit()">
                            <option value="">Tous les statuts</option>
                            <option value="planifie" <?php echo ($_GET['statut'] ?? '') === 'planifie' ? 'selected' : ''; ?>>Planifié</option>
                            <option value="en_cours" <?php echo ($_GET['statut'] ?? '') === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                            <option value="termine" <?php echo ($_GET['statut'] ?? '') === 'termine' ? 'selected' : ''; ?>>Terminé</option>
                            <option value="annule" <?php echo ($_GET['statut'] ?? '') === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <input type="date" class="form-control" name="date" 
                               value="<?php echo $_GET['date'] ?? ''; ?>" 
                               onchange="this.form.submit()">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Référence</th>
                        <th>Patient</th>
                        <th>Médecin</th>
                        <th>Date/Heure</th>
                        <th>Motif</th>
                        <th>Diagnostic</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Construire la requête avec filtres
                    $sql = "SELECT c.*, 
                                   p.nom as patient_nom, p.prenom as patient_prenom, p.code_patient,
                                   d.nom as docteur_nom, d.prenom as docteur_prenom, d.specialite
                            FROM consultations c
                            JOIN patients p ON c.patient_id = p.id
                            JOIN utilisateurs d ON c.docteur_id = d.id
                            WHERE 1=1";
                    
                    $params = [];
                    
                    // Filtre recherche
                    if (!empty($_GET['search'])) {
                        $sql .= " AND (p.nom LIKE ? OR p.prenom LIKE ? OR c.reference LIKE ? OR c.motif LIKE ?)";
                        $searchTerm = "%{$_GET['search']}%";
                        $params = array_fill(0, 4, $searchTerm);
                    }
                    
                    // Filtre statut
                    if (!empty($_GET['statut'])) {
                        $sql .= " AND c.statut = ?";
                        $params[] = $_GET['statut'];
                    }
                    
                    // Filtre date
                    if (!empty($_GET['date'])) {
                        $sql .= " AND DATE(c.date_consultation) = ?";
                        $params[] = $_GET['date'];
                    }
                    
                    // Pagination
                    $page = $_GET['page'] ?? 1;
                    $limit = 20;
                    $offset = ($page - 1) * $limit;
                    
                    $sql .= " ORDER BY c.date_consultation DESC LIMIT $limit OFFSET $offset";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $consultations = $stmt->fetchAll();
                    
                    foreach ($consultations as $consult): 
                        $statusColors = [
                            'planifie' => 'warning',
                            'en_cours' => 'info',
                            'termine' => 'success',
                            'annule' => 'danger',
                            'reporte' => 'secondary'
                        ];
                        
                        $urgenceBadge = $consult['urgence'] ? '<span class="badge bg-danger ms-1">URGENT</span>' : '';
                    ?>
                    <tr>
                        <td>
                            <span class="badge bg-primary"><?php echo $consult['reference']; ?></span>
                            <?php echo $urgenceBadge; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo $consult['patient_prenom'] . ' ' . $consult['patient_nom']; ?></div>
                            <small class="text-muted"><?php echo $consult['code_patient']; ?></small>
                        </td>
                        <td>
                            <div>Dr. <?php echo $consult['docteur_prenom'] . ' ' . $consult['docteur_nom']; ?></div>
                            <small class="text-muted"><?php echo $consult['specialite']; ?></small>
                        </td>
                        <td>
                            <?php echo date('d/m/Y H:i', strtotime($consult['date_consultation'])); ?>
                            <br><small class="text-muted"><?php echo $consult['duree']; ?> min</small>
                        </td>
                        <td>
                            <?php if ($consult['motif']): ?>
                            <span class="small" title="<?php echo htmlspecialchars($consult['motif']); ?>">
                                <?php echo substr($consult['motif'], 0, 50); ?>...
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($consult['diagnostic']): ?>
                            <span class="small" title="<?php echo htmlspecialchars($consult['diagnostic']); ?>">
                                <?php echo substr($consult['diagnostic'], 0, 50); ?>...
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $statusColors[$consult['statut']] ?? 'secondary'; ?>">
                                <?php echo ucfirst($consult['statut']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=edit&id=<?php echo $consult['id']; ?>" 
                                   class="btn btn-outline-primary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="consultation_details.php?id=<?php echo $consult['id']; ?>" 
                                   class="btn btn-outline-info" title="Détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $consult['id']; ?>)" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($consultations)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-stethoscope fa-2x text-muted mb-3"></i>
                            <p class="text-muted">Aucune consultation trouvée</p>
                            <a href="?action=add" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus-circle me-1"></i>Créer une consultation
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card-footer bg-white border-top">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <?php
                // Compter le total
                $countSql = "SELECT COUNT(*) FROM consultations c WHERE 1=1";
                if (!empty($_GET['search'])) {
                    $countSql .= " AND (EXISTS (SELECT 1 FROM patients p WHERE p.id = c.patient_id AND (p.nom LIKE ? OR p.prenom LIKE ?)) OR c.reference LIKE ? OR c.motif LIKE ?)";
                }
                if (!empty($_GET['statut'])) {
                    $countSql .= " AND c.statut = ?";
                }
                if (!empty($_GET['date'])) {
                    $countSql .= " AND DATE(c.date_consultation) = ?";
                }
                
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $totalConsultations = $countStmt->fetchColumn();
                $totalPages = ceil($totalConsultations / $limit);
                ?>
                <small class="text-muted">
                    Affichage <?php echo min(($page - 1) * $limit + 1, $totalConsultations); ?>-<?php echo min($page * $limit, $totalConsultations); ?> 
                    sur <?php echo $totalConsultations; ?> consultation(s)
                </small>
            </div>
            <div>
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&statut=<?php echo urlencode($_GET['statut'] ?? ''); ?>&date=<?php echo urlencode($_GET['date'] ?? ''); ?>" 
                   class="btn btn-sm btn-outline-secondary me-2">
                    <i class="fas fa-chevron-left me-1"></i>Précédent
                </a>
                <?php endif; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&statut=<?php echo urlencode($_GET['statut'] ?? ''); ?>&date=<?php echo urlencode($_GET['date'] ?? ''); ?>" 
                   class="btn btn-sm btn-outline-secondary">
                    Suivant<i class="fas fa-chevron-right ms-1"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

<script>
// Charger les informations du patient
async function loadPatientInfo(patientId) {
    if (!patientId) {
        document.getElementById('patientInfo').innerHTML = '';
        return;
    }
    
    try {
        const response = await fetch(`ajax/get_patient_info.php?id=${patientId}`);
        const data = await response.text();
        
        document.getElementById('patientInfo').innerHTML = data;
    } catch (error) {
        console.error('Erreur lors du chargement des informations du patient:', error);
    }
}

// Générer une prescription
function generatePrescription(consultationId) {
    if (confirm('Créer une prescription pour cette consultation ?')) {
        window.open(`prescription_create.php?consultation_id=${consultationId}`, '_blank');
    }
}

// Confirmer la suppression
function confirmDelete(consultationId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette consultation ? Cette action est irréversible.')) {
        window.location.href = `?action=delete&id=${consultationId}`;
    }
}

// Recherche en temps réel
let searchTimeout;
document.getElementById('searchInput')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 500);
});

// Exporter les consultations
function exportConsultations() {
    const search = document.querySelector('input[name="search"]')?.value || '';
    const statut = document.querySelector('select[name="statut"]')?.value || '';
    const date = document.querySelector('input[name="date"]')?.value || '';
    
    window.location.href = `export_consultations.php?search=${encodeURIComponent(search)}&statut=${encodeURIComponent(statut)}&date=${encodeURIComponent(date)}`;
}

// Initialiser les tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
});
</script>

<style>
.nav-tabs .nav-link {
    color: #6b7280;
    font-weight: 500;
    border: none;
    padding: 0.75rem 1.5rem;
}

.nav-tabs .nav-link.active {
    color: #4361ee;
    border-bottom: 2px solid #4361ee;
    background-color: transparent;
}

.required::after {
    content: " *";
    color: #dc3545;
}

.tab-content {
    padding-top: 1rem;
}

.table th {
    font-weight: 600;
    color: #6b7280;
    background-color: #f9fafb;
    border-bottom: 2px solid #e5e7eb;
    padding: 1rem;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

.table td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #e5e7eb;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>