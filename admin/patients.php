<?php
// admin/patients.php
require_once '../config/database.php';
checkRole('admin');

$title = 'Gestion des Patients';
require_once '../includes/header.php';

// Traitement CRUD
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = sanitize($_POST);
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'add') {
            // Générer code patient
            $code_patient = 'PAT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            $stmt = $pdo->prepare("
                INSERT INTO patients 
                (code_patient, nom, prenom, date_naissance, sexe, telephone, email, 
                 adresse, ville, code_postal, pays, groupe_sanguin, rhésus, 
                 antecedents_familiaux, antecedents_personnels, allergies, 
                 medicaments_habituels, habitudes, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $code_patient,
                $data['nom'],
                $data['prenom'],
                $data['date_naissance'],
                $data['sexe'],
                $data['telephone'],
                $data['email'] ?? null,
                $data['adresse'] ?? null,
                $data['ville'] ?? null,
                $data['code_postal'] ?? null,
                $data['pays'] ?? 'France',
                $data['groupe_sanguin'] ?? null,
                $data['rhesus'] ?? null,
                $data['antecedents_familiaux'] ?? null,
                $data['antecedents_personnels'] ?? null,
                $data['allergies'] ?? null,
                $data['medicaments_habituels'] ?? null,
                $data['habitudes'] ?? null,
                $data['notes'] ?? null,
                $_SESSION['user_id']
            ]);
            
            $patientId = $pdo->lastInsertId();
            
            // Journaliser l'action
            logAction('CREATE', 'patients', $patientId, "Création patient: {$data['prenom']} {$data['nom']}");
            
            header("Location: patients.php?success=Patient créé avec succès (Code: $code_patient)");
            exit();
            
        } elseif ($action === 'edit' && $id) {
            $stmt = $pdo->prepare("
                UPDATE patients SET 
                nom = ?, prenom = ?, date_naissance = ?, sexe = ?, telephone = ?, email = ?,
                adresse = ?, ville = ?, code_postal = ?, pays = ?, groupe_sanguin = ?, rhésus = ?,
                poids = ?, taille = ?, antecedents_familiaux = ?, antecedents_personnels = ?,
                allergies = ?, medicaments_habituels = ?, habitudes = ?, notes = ?,
                date_modification = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['nom'],
                $data['prenom'],
                $data['date_naissance'],
                $data['sexe'],
                $data['telephone'],
                $data['email'] ?? null,
                $data['adresse'] ?? null,
                $data['ville'] ?? null,
                $data['code_postal'] ?? null,
                $data['pays'] ?? 'France',
                $data['groupe_sanguin'] ?? null,
                $data['rhesus'] ?? null,
                $data['poids'] ?? null,
                $data['taille'] ?? null,
                $data['antecedents_familiaux'] ?? null,
                $data['antecedents_personnels'] ?? null,
                $data['allergies'] ?? null,
                $data['medicaments_habituels'] ?? null,
                $data['habitudes'] ?? null,
                $data['notes'] ?? null,
                $id
            ]);
            
            // Journaliser l'action
            logAction('UPDATE', 'patients', $id, "Modification patient ID: $id");
            
            header("Location: patients.php?success=Patient modifié avec succès");
            exit();
            
        } elseif ($action === 'delete' && $id) {
            // Vérifier s'il y a des consultations
            $hasConsultations = $pdo->prepare("SELECT COUNT(*) FROM consultations WHERE patient_id = ?")->execute([$id])->fetchColumn();
            
            if ($hasConsultations > 0) {
                // Archiver au lieu de supprimer
                $pdo->prepare("UPDATE patients SET statut = 'archive' WHERE id = ?")->execute([$id]);
                $message = "Le patient a été archivé (consultations préservées)";
            } else {
                $pdo->prepare("DELETE FROM patients WHERE id = ?")->execute([$id]);
                $message = "Patient supprimé avec succès";
            }
            
            // Journaliser l'action
            logAction('DELETE', 'patients', $id, "Suppression patient ID: $id");
            
            header("Location: patients.php?success=" . urlencode($message));
            exit();
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: patients.php?action=$action&id=$id&error=" . urlencode($e->getMessage()));
        exit();
    }
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-user-injured me-2"></i>Gestion des Patients
        </h1>
        <p class="text-muted mb-0">Administration du registre des patients</p>
    </div>
    <div class="btn-toolbar">
        <?php if ($action === 'list'): ?>
        <div class="btn-group me-2">
            <a href="?action=add" class="btn btn-primary">
                <i class="fas fa-user-plus me-1"></i>Nouveau patient
            </a>
            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" 
                    data-bs-toggle="dropdown">
                <span class="visually-hidden">Options</span>
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="#" onclick="importPatients()">
                    <i class="fas fa-file-import me-2"></i>Importer
                </a>
                <a class="dropdown-item" href="export_patients.php">
                    <i class="fas fa-file-export me-2"></i>Exporter
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="rapports.php?type=patients">
                    <i class="fas fa-chart-bar me-2"></i>Rapport patients
                </a>
            </div>
        </div>
        <?php else: ?>
        <a href="patients.php" class="btn btn-secondary">
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
                    <i class="fas fa-user-edit me-2"></i>
                    <?php echo $action === 'add' ? 'Ajouter un patient' : 'Modifier le patient'; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php
                    $patient = null;
                    if ($action === 'edit' && $id) {
                        $patient = $pdo->prepare("SELECT * FROM patients WHERE id = ?")->execute([$id]);
                        
                        if (!$patient) {
                            echo '<div class="alert alert-danger">Patient non trouvé</div>';
                            require_once '../includes/footer.php';
                            exit();
                        }
                    }
                ?>
                
                <form method="POST" id="patientForm" novalidate>
                    <!-- Onglets -->
                    <ul class="nav nav-tabs mb-4" id="patientTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="info-tab" data-bs-toggle="tab" 
                                    data-bs-target="#info" type="button">Informations personnelles</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="medical-tab" data-bs-toggle="tab" 
                                    data-bs-target="#medical" type="button">Antécédents médicaux</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="contact-tab" data-bs-toggle="tab" 
                                    data-bs-target="#contact" type="button">Contact et adresse</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="patientTabsContent">
                        <!-- Onglet Informations personnelles -->
                        <div class="tab-pane fade show active" id="info" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required">Nom</label>
                                    <input type="text" class="form-control" name="nom" 
                                           value="<?php echo $patient['nom'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Prénom</label>
                                    <input type="text" class="form-control" name="prenom" 
                                           value="<?php echo $patient['prenom'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label required">Date de naissance</label>
                                    <input type="date" class="form-control" name="date_naissance" 
                                           value="<?php echo $patient['date_naissance'] ?? ''; ?>" required
                                           onchange="calculateAge(this.value)">
                                    <small class="text-muted" id="ageDisplay"></small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Sexe</label>
                                    <select class="form-select" name="sexe" required>
                                        <option value="">Sélectionner</option>
                                        <option value="M" <?php echo ($patient['sexe'] ?? '') == 'M' ? 'selected' : ''; ?>>Masculin</option>
                                        <option value="F" <?php echo ($patient['sexe'] ?? '') == 'F' ? 'selected' : ''; ?>>Féminin</option>
                                    </select>
                                </div>
                                
                                <?php if ($action === 'edit'): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Poids (kg)</label>
                                    <input type="number" step="0.1" class="form-control" name="poids" 
                                           value="<?php echo $patient['poids'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Taille (cm)</label>
                                    <input type="number" step="0.1" class="form-control" name="taille" 
                                           value="<?php echo $patient['taille'] ?? ''; ?>"
                                           onchange="calculateBMI()">
                                    <small class="text-muted" id="bmiDisplay"></small>
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Groupe sanguin</label>
                                    <select class="form-select" name="groupe_sanguin">
                                        <option value="">Sélectionner</option>
                                        <option value="A+" <?php echo ($patient['groupe_sanguin'] ?? '') == 'A+' ? 'selected' : ''; ?>>A+</option>
                                        <option value="A-" <?php echo ($patient['groupe_sanguin'] ?? '') == 'A-' ? 'selected' : ''; ?>>A-</option>
                                        <option value="B+" <?php echo ($patient['groupe_sanguin'] ?? '') == 'B+' ? 'selected' : ''; ?>>B+</option>
                                        <option value="B-" <?php echo ($patient['groupe_sanguin'] ?? '') == 'B-' ? 'selected' : ''; ?>>B-</option>
                                        <option value="AB+" <?php echo ($patient['groupe_sanguin'] ?? '') == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                        <option value="AB-" <?php echo ($patient['groupe_sanguin'] ?? '') == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                        <option value="O+" <?php echo ($patient['groupe_sanguin'] ?? '') == 'O+' ? 'selected' : ''; ?>>O+</option>
                                        <option value="O-" <?php echo ($patient['groupe_sanguin'] ?? '') == 'O-' ? 'selected' : ''; ?>>O-</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Rhésus</label>
                                    <select class="form-select" name="rhesus">
                                        <option value="">Sélectionner</option>
                                        <option value="+" <?php echo ($patient['rhésus'] ?? '') == '+' ? 'selected' : ''; ?>>Positif (+)</option>
                                        <option value="-" <?php echo ($patient['rhésus'] ?? '') == '-' ? 'selected' : ''; ?>>Négatif (-)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Onglet Antécédents médicaux -->
                        <div class="tab-pane fade" id="medical" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Antécédents familiaux</label>
                                    <textarea class="form-control" name="antecedents_familiaux" rows="4"><?php echo $patient['antecedents_familiaux'] ?? ''; ?></textarea>
                                    <small class="text-muted">Maladies héréditaires, cancer, diabète, hypertension...</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Antécédents personnels</label>
                                    <textarea class="form-control" name="antecedents_personnels" rows="4"><?php echo $patient['antecedents_personnels'] ?? ''; ?></textarea>
                                    <small class="text-muted">Chirurgies, hospitalisations, maladies chroniques...</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Allergies connues</label>
                                    <textarea class="form-control" name="allergies" rows="3"><?php echo $patient['allergies'] ?? ''; ?></textarea>
                                    <small class="text-muted">Médicaments, aliments, environnement...</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Médicaments habituels</label>
                                    <textarea class="form-control" name="medicaments_habituels" rows="3"><?php echo $patient['medicaments_habituels'] ?? ''; ?></textarea>
                                    <small class="text-muted">Traitements en cours</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Habitudes de vie</label>
                                    <textarea class="form-control" name="habitudes" rows="3"><?php echo $patient['habitudes'] ?? ''; ?></textarea>
                                    <small class="text-muted">Tabac, alcool, activité physique, alimentation...</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Notes médicales</label>
                                    <textarea class="form-control" name="notes" rows="3"><?php echo $patient['notes'] ?? ''; ?></textarea>
                                    <small class="text-muted">Informations complémentaires</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Onglet Contact et adresse -->
                        <div class="tab-pane fade" id="contact" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required">Téléphone</label>
                                    <input type="tel" class="form-control" name="telephone" 
                                           value="<?php echo $patient['telephone'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo $patient['email'] ?? ''; ?>">
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Adresse</label>
                                    <input type="text" class="form-control" name="adresse" 
                                           value="<?php echo $patient['adresse'] ?? ''; ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Ville</label>
                                    <input type="text" class="form-control" name="ville" 
                                           value="<?php echo $patient['ville'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Code Postal</label>
                                    <input type="text" class="form-control" name="code_postal" 
                                           value="<?php echo $patient['code_postal'] ?? ''; ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Pays</label>
                                    <input type="text" class="form-control" name="pays" 
                                           value="<?php echo $patient['pays'] ?? 'France'; ?>">
                                </div>
                                
                                <?php if ($action === 'edit' && $patient): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Informations système</label>
                                    <div class="card bg-light">
                                        <div class="card-body p-3">
                                            <small>
                                                <strong>Code patient:</strong> <?php echo $patient['code_patient']; ?><br>
                                                <strong>Créé le:</strong> <?php echo date('d/m/Y', strtotime($patient['date_enregistrement'])); ?><br>
                                                <strong>Dernière modif:</strong> <?php echo date('d/m/Y', strtotime($patient['date_modification'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i>
                            <?php echo $action === 'add' ? 'Créer le patient' : 'Enregistrer les modifications'; ?>
                        </button>
                        <button type="reset" class="btn btn-secondary ms-2">Réinitialiser</button>
                        <a href="patients.php" class="btn btn-outline-secondary ms-2">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Liste des patients -->
<div class="card shadow-sm">
    <div class="card-header bg-white border-bottom">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Registre des patients
                </h6>
            </div>
            <div class="col-md-6">
                <form method="GET" class="row g-2">
                    <div class="col">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Nom, prénom, téléphone..." value="<?php echo $_GET['search'] ?? ''; ?>"
                               id="searchInput">
                    </div>
                    <div class="col-auto">
                        <select class="form-select" name="statut" onchange="this.form.submit()">
                            <option value="">Tous les statuts</option>
                            <option value="actif" <?php echo ($_GET['statut'] ?? '') === 'actif' ? 'selected' : ''; ?>>Actif</option>
                            <option value="archive" <?php echo ($_GET['statut'] ?? '') === 'archive' ? 'selected' : ''; ?>>Archivé</option>
                            <option value="decede" <?php echo ($_GET['statut'] ?? '') === 'decede' ? 'selected' : ''; ?>>Décédé</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                            <i class="fas fa-times"></i>
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
                        <th>Code</th>
                        <th>Patient</th>
                        <th>Âge</th>
                        <th>Contact</th>
                        <th>Ville</th>
                        <th>Dernière consultation</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Construire la requête avec filtres
                    $sql = "SELECT p.*, 
                                   TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as age,
                                   (SELECT MAX(date_consultation) FROM consultations WHERE patient_id = p.id) as derniere_consult,
                                   (SELECT COUNT(*) FROM consultations WHERE patient_id = p.id) as nb_consultations
                            FROM patients p 
                            WHERE 1=1";
                    
                    $params = [];
                    
                    // Filtre recherche
                    if (!empty($_GET['search'])) {
                        $sql .= " AND (p.nom LIKE ? OR p.prenom LIKE ? OR p.telephone LIKE ? OR p.code_patient LIKE ? OR p.email LIKE ?)";
                        $searchTerm = "%{$_GET['search']}%";
                        $params = array_fill(0, 5, $searchTerm);
                    }
                    
                    // Filtre statut
                    if (!empty($_GET['statut'])) {
                        $sql .= " AND p.statut = ?";
                        $params[] = $_GET['statut'];
                    } else {
                        // Par défaut, ne pas montrer les patients décédés
                        $sql .= " AND p.statut != 'decede'";
                    }
                    
                    // Pagination
                    $page = $_GET['page'] ?? 1;
                    $limit = 20;
                    $offset = ($page - 1) * $limit;
                    
                    $sql .= " ORDER BY p.date_enregistrement DESC LIMIT $limit OFFSET $offset";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $patients = $stmt->fetchAll();
                    
                    foreach ($patients as $patient): 
                        $age = $patient['age'];
                        $ageClass = $age < 18 ? 'text-info' : ($age > 60 ? 'text-warning' : '');
                        $statusColor = $patient['statut'] == 'actif' ? 'success' : 
                                     ($patient['statut'] == 'archive' ? 'secondary' : 'danger');
                    ?>
                    <tr>
                        <td>
                            <span class="badge bg-primary"><?php echo $patient['code_patient']; ?></span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar me-3">
                                    <?php echo strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?php echo $patient['prenom'] . ' ' . $patient['nom']; ?></div>
                                    <small class="text-muted"><?php echo $patient['sexe'] == 'M' ? 'Homme' : 'Femme'; ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="fw-bold <?php echo $ageClass; ?>"><?php echo $age; ?> ans</span>
                            <?php if ($patient['poids'] && $patient['taille']): 
                                $bmi = $patient['poids'] / pow($patient['taille'] / 100, 2);
                                $bmiClass = $bmi < 18.5 ? 'text-info' : ($bmi < 25 ? 'text-success' : ($bmi < 30 ? 'text-warning' : 'text-danger'));
                            ?>
                            <br><small class="<?php echo $bmiClass; ?>">IMC: <?php echo number_format($bmi, 1); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?php echo $patient['telephone']; ?></div>
                            <?php if ($patient['email']): ?>
                            <small class="text-muted"><?php echo $patient['email']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $patient['ville'] ?? 'Non renseigné'; ?></td>
                        <td>
                            <?php if ($patient['derniere_consult']): ?>
                            <span class="small"><?php echo date('d/m/Y', strtotime($patient['derniere_consult'])); ?></span>
                            <br><small class="text-muted">(<?php echo $patient['nb_consultations']; ?> cons.)</small>
                            <?php else: ?>
                            <span class="text-muted small">Jamais</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $statusColor; ?>">
                                <?php echo ucfirst($patient['statut']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=edit&id=<?php echo $patient['id']; ?>" 
                                   class="btn btn-outline-primary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="../docteur/consultations.php?patient_id=<?php echo $patient['id']; ?>" 
                                   class="btn btn-outline-success" title="Nouvelle consultation">
                                    <i class="fas fa-stethoscope"></i>
                                </a>
                                <a href="patient_details.php?id=<?php echo $patient['id']; ?>" 
                                   class="btn btn-outline-info" title="Détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $patient['id']; ?>)" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-user-injured fa-2x text-muted mb-3"></i>
                            <p class="text-muted">Aucun patient trouvé</p>
                            <a href="?action=add" class="btn btn-primary btn-sm">
                                <i class="fas fa-user-plus me-1"></i>Ajouter un patient
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
                $countSql = "SELECT COUNT(*) FROM patients WHERE 1=1";
                if (!empty($_GET['search'])) {
                    $countSql .= " AND (nom LIKE ? OR prenom LIKE ? OR telephone LIKE ? OR code_patient LIKE ? OR email LIKE ?)";
                }
                if (!empty($_GET['statut'])) {
                    $countSql .= " AND statut = ?";
                } else {
                    $countSql .= " AND statut != 'decede'";
                }
                
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $totalPatients = $countStmt->fetchColumn();
                $totalPages = ceil($totalPatients / $limit);
                ?>
                <small class="text-muted">
                    Affichage <?php echo min(($page - 1) * $limit + 1, $totalPatients); ?>-<?php echo min($page * $limit, $totalPatients); ?> 
                    sur <?php echo $totalPatients; ?> patient(s)
                </small>
            </div>
            <div>
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&statut=<?php echo urlencode($_GET['statut'] ?? ''); ?>" 
                   class="btn btn-sm btn-outline-secondary me-2">
                    <i class="fas fa-chevron-left me-1"></i>Précédent
                </a>
                <?php endif; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&statut=<?php echo urlencode($_GET['statut'] ?? ''); ?>" 
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
// Calcul de l'âge
function calculateAge(birthdate) {
    if (!birthdate) return;
    
    const birthDate = new Date(birthdate);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    const ageDisplay = document.getElementById('ageDisplay');
    if (ageDisplay) {
        ageDisplay.textContent = `${age} ans`;
    }
}

// Calcul de l'IMC
function calculateBMI() {
    const poids = parseFloat(document.querySelector('input[name="poids"]')?.value);
    const taille = parseFloat(document.querySelector('input[name="taille"]')?.value);
    
    if (poids && taille && taille > 0) {
        const bmi = poids / Math.pow(taille / 100, 2);
        const bmiDisplay = document.getElementById('bmiDisplay');
        
        if (bmiDisplay) {
            let category = '';
            if (bmi < 18.5) category = 'Insuffisance pondérale';
            else if (bmi < 25) category = 'Poids normal';
            else if (bmi < 30) category = 'Surpoids';
            else category = 'Obésité';
            
            bmiDisplay.textContent = `IMC: ${bmi.toFixed(1)} (${category})`;
        }
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

// Réinitialiser les filtres
function resetFilters() {
    window.location.href = 'patients.php';
}

// Confirmer la suppression
function confirmDelete(patientId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce patient ? Cette action est irréversible.')) {
        window.location.href = `?action=delete&id=${patientId}`;
    }
}

// Importer des patients
function importPatients() {
    const modal = new bootstrap.Modal(document.getElementById('importModal') || createImportModal());
    modal.show();
}

function createImportModal() {
    const modal = document.createElement('div');
    modal.id = 'importModal';
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Importer des patients</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Téléchargez le modèle CSV pour l'importation.</p>
                    <a href="templates/patients_template.csv" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-download me-1"></i>Télécharger modèle
                    </a>
                    <hr>
                    <form id="importForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Fichier CSV</label>
                            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" onclick="submitImport()">Importer</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    return modal;
}

async function submitImport() {
    const form = document.getElementById('importForm');
    const formData = new FormData(form);
    
    try {
        const response = await fetch('ajax/import_patients.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`${result.count} patient(s) importé(s) avec succès`);
            location.reload();
        } else {
            alert('Erreur: ' + result.message);
        }
    } catch (error) {
        alert('Erreur lors de l\'importation');
    }
}

// Initialiser les calculs si des données existent
document.addEventListener('DOMContentLoaded', function() {
    const birthdateInput = document.querySelector('input[name="date_naissance"]');
    if (birthdateInput && birthdateInput.value) {
        calculateAge(birthdateInput.value);
    }
    
    const tailleInput = document.querySelector('input[name="taille"]');
    if (tailleInput && tailleInput.value) {
        calculateBMI();
    }
    
    // Initialiser les tooltips
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
});
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