<?php
// admin/rendezvous.php
require_once '../config/database.php';
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
checkRole($role);

$pdo = Database::getInstance()->getConnection();

$title = 'Gestion des Rendez-vous';
require_once '../includes/header.php';

// Fonction PHP pour le temps écoulé (utilisée dans le tableau)
function timeElapsed($datetime) {
    $now = new DateTime();
    $date = new DateTime($datetime);
    $diff = $now->diff($date);
    
    if ($diff->days > 0) {
        return "il y a " . $diff->days . " jour" . ($diff->days > 1 ? "s" : "");
    } elseif ($diff->h > 0) {
        return "il y a " . $diff->h . " heure" . ($diff->h > 1 ? "s" : "");
    } elseif ($diff->i > 0) {
        return "il y a " . $diff->i . " minute" . ($diff->i > 1 ? "s" : "");
    } else {
        return "à l'instant";
    }
}

// Traitement CRUD
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
$patient_id = $_GET['patient_id'] ?? null;

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = sanitize($_POST);
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'add' || $action === 'edit') {
            // Valider les données
            if (empty($data['patient_id'])) {
                throw new Exception("Patient requis");
            }
            
            if (empty($data['docteur_id'])) {
                throw new Exception("Médecin requis");
            }
            
            if (empty($data['date_rdv'])) {
                throw new Exception("Date requise");
            }
            
            // Construire la datetime complète
            $datetime = $data['date_rdv'] . ' ' . $data['heure_rdv'] . ':00';
            
            // Vérifier la disponibilité du médecin
            $checkStmt = $pdo->prepare("
                SELECT id FROM rendez_vous 
                WHERE docteur_id = ? 
                AND date_rdv = ? 
                AND statut = 'confirme'
                AND id != ?
            ");
            $checkStmt->execute([
                $data['docteur_id'],
                $datetime,
                $id ?: 0
            ]);
            
            if ($checkStmt->fetch()) {
                throw new Exception("Le médecin a déjà un rendez-vous à cette heure");
            }
            
            if ($action === 'add') {
                $stmt = $pdo->prepare("
                    INSERT INTO rendez_vous 
                    (patient_id, docteur_id, date_rdv, duree, type_rdv, motif, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $data['patient_id'],
                    $data['docteur_id'],
                    $datetime,
                    $data['duree'] ?? 30,
                    $data['type_rdv'] ?? 'consultation',
                    $data['motif'] ?? null,
                    $data['notes'] ?? null,
                    $_SESSION['user_id']
                ]);
                
                $rdvId = $pdo->lastInsertId();
                
                // Récupérer les infos pour le log
                $patientStmt = $pdo->prepare("SELECT prenom, nom FROM patients WHERE id = ?");
                $patientStmt->execute([$data['patient_id']]);
                $patientInfo = $patientStmt->fetch();
                
                // Journaliser l'action
                logAction('CREATE', 'rendez_vous', $rdvId, 
                    "RDV créé pour " . ($patientInfo['prenom'] ?? '') . " " . ($patientInfo['nom'] ?? ''));
                
                $success = "Rendez-vous créé avec succès";
                
            } elseif ($action === 'edit' && $id) {
                $stmt = $pdo->prepare("
                    UPDATE rendez_vous SET 
                    patient_id = ?, docteur_id = ?, date_rdv = ?, duree = ?, 
                    type_rdv = ?, motif = ?, notes = ?, statut = ?,
                    updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $data['patient_id'],
                    $data['docteur_id'],
                    $datetime,
                    $data['duree'] ?? 30,
                    $data['type_rdv'] ?? 'consultation',
                    $data['motif'] ?? null,
                    $data['notes'] ?? null,
                    $data['statut'] ?? 'confirme',
                    $id
                ]);
                
                // Journaliser l'action
                logAction('UPDATE', 'rendez_vous', $id, "Modification RDV ID: $id");
                
                $success = "Rendez-vous modifié avec succès";
            }
            
        } elseif ($action === 'annuler' && $id) {
            $stmt = $pdo->prepare("UPDATE rendez_vous SET statut = 'annule', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            // Journaliser l'action
            logAction('UPDATE', 'rendez_vous', $id, "Annulation RDV ID: $id");
            
            $success = "Rendez-vous annulé";
            
        } elseif ($action === 'delete' && $id) {
            // Vérifier si c'est un RDV passé
            $rdvStmt = $pdo->prepare("SELECT date_rdv FROM rendez_vous WHERE id = ?");
            $rdvStmt->execute([$id]);
            $rdv = $rdvStmt->fetch();
            
            if ($rdv && strtotime($rdv['date_rdv']) < time()) {
                throw new Exception("Impossible de supprimer un rendez-vous passé");
            }
            
            $pdo->prepare("DELETE FROM rendez_vous WHERE id = ?")->execute([$id]);
            
            // Journaliser l'action
            logAction('DELETE', 'rendez_vous', $id, "Suppression RDV ID: $id");
            
            $success = "Rendez-vous supprimé avec succès";
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-calendar-alt me-2"></i>Gestion des Rendez-vous
        </h1>
        <p class="text-muted mb-0">Planification et suivi des rendez-vous</p>
    </div>
    <div class="btn-toolbar">
        <?php if ($action === 'list'): ?>
        <div class="btn-group me-2">
            <a href="?action=add<?php echo $patient_id ? '&patient_id=' . $patient_id : ''; ?>" 
               class="btn btn-primary">
                <i class="fas fa-calendar-plus me-1"></i>Nouveau rendez-vous
            </a>
            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" 
                    data-bs-toggle="dropdown">
                <span class="visually-hidden">Options</span>
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="#" onclick="importRendezVous()">
                    <i class="fas fa-file-import me-2"></i>Importer
                </a>
                <a class="dropdown-item" href="export_rendezvous.php">
                    <i class="fas fa-file-export me-2"></i>Exporter
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="rapports.php?type=rendezvous">
                    <i class="fas fa-chart-bar me-2"></i>Rapport RDV
                </a>
            </div>
        </div>
        <?php else: ?>
        <a href="rendezvous.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Retour à la liste
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Messages -->
<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Formulaire Ajout/Modification -->
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-edit me-2"></i>
                    <?php echo $action === 'add' ? 'Nouveau rendez-vous' : 'Modifier le rendez-vous'; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php
                $rdv = null;
                $patient = null;
                if ($action === 'edit' && $id) {
                    $stmt = $pdo->prepare("SELECT * FROM rendez_vous WHERE id = ?");
                    $stmt->execute([$id]);
                    $rdv = $stmt->fetch();
                    
                    if (!$rdv) {
                        echo '<div class="alert alert-danger">Rendez-vous non trouvé</div>';
                        require_once '../includes/footer.php';
                        exit();
                    }
                    
                    // Récupérer les infos du patient
                    $patientStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
                    $patientStmt->execute([$rdv['patient_id']]);
                    $patient = $patientStmt->fetch();
                } elseif ($patient_id) {
                    // Pour un nouveau RDV avec patient pré-sélectionné
                    $patientStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
                    $patientStmt->execute([$patient_id]);
                    $patient = $patientStmt->fetch();
                }
                ?>
                
                <form method="POST" id="rdvForm" novalidate>
                    <!-- Patient -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label required">Patient</label>
                            <?php if ($patient): ?>
                            <div class="card bg-light">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-3">
                                            <?php echo strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']); ?></div>
                                            <small class="text-muted">
                                                Code: <?php echo htmlspecialchars($patient['code_patient']); ?> | 
                                                Tél: <?php echo htmlspecialchars($patient['telephone']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                            <?php else: ?>
                            <select class="form-select" name="patient_id" required id="patientSelect">
                                <option value="">Sélectionner un patient</option>
                                <?php
                                $patients = $pdo->query("SELECT id, code_patient, nom, prenom, telephone FROM patients WHERE statut = 'actif' ORDER BY nom, prenom")->fetchAll();
                                foreach ($patients as $p):
                                ?>
                                <option value="<?php echo $p['id']; ?>" 
                                    <?php echo ($rdv['patient_id'] ?? '') == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['prenom'] . ' ' . $p['nom']); ?> 
                                    (<?php echo htmlspecialchars($p['code_patient']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label required">Médecin</label>
                            <select class="form-select" name="docteur_id" required id="docteurSelect">
                                <option value="">Sélectionner un médecin</option>
                                <?php
                                $docteurs = $pdo->query("SELECT id, nom, prenom, specialite FROM utilisateurs WHERE role = 'docteur' AND statut = 'actif' ORDER BY nom, prenom")->fetchAll();
                                foreach ($docteurs as $doc):
                                ?>
                                <option value="<?php echo $doc['id']; ?>" 
                                    <?php echo ($rdv['docteur_id'] ?? '') == $doc['id'] ? 'selected' : ''; ?>
                                    data-specialite="<?php echo htmlspecialchars($doc['specialite']); ?>">
                                    Dr. <?php echo htmlspecialchars($doc['prenom'] . ' ' . $doc['nom']); ?>
                                    <?php if ($doc['specialite']): ?>
                                    (<?php echo htmlspecialchars($doc['specialite']); ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Date et heure -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label required">Date</label>
                            <input type="date" class="form-control" name="date_rdv" 
                                   value="<?php echo $rdv ? date('Y-m-d', strtotime($rdv['date_rdv'])) : date('Y-m-d'); ?>" 
                                   required id="dateInput">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Heure</label>
                            <input type="time" class="form-control" name="heure_rdv" 
                                   value="<?php echo $rdv ? date('H:i', strtotime($rdv['date_rdv'])) : '09:00'; ?>" 
                                   required id="heureInput" step="900">
                        </div>
                    </div>
                    
                    <!-- Détails du RDV -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Durée (minutes)</label>
                            <select class="form-select" name="duree">
                                <option value="15" <?php echo ($rdv['duree'] ?? 30) == 15 ? 'selected' : ''; ?>>15 min</option>
                                <option value="30" <?php echo ($rdv['duree'] ?? 30) == 30 ? 'selected' : ''; ?>>30 min</option>
                                <option value="45" <?php echo ($rdv['duree'] ?? 30) == 45 ? 'selected' : ''; ?>>45 min</option>
                                <option value="60" <?php echo ($rdv['duree'] ?? 30) == 60 ? 'selected' : ''; ?>>1 heure</option>
                                <option value="90" <?php echo ($rdv['duree'] ?? 30) == 90 ? 'selected' : ''; ?>>1h30</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type_rdv">
                                <option value="consultation" <?php echo ($rdv['type_rdv'] ?? 'consultation') == 'consultation' ? 'selected' : ''; ?>>Consultation</option>
                                <option value="controle" <?php echo ($rdv['type_rdv'] ?? '') == 'controle' ? 'selected' : ''; ?>>Contrôle</option>
                                <option value="urgence" <?php echo ($rdv['type_rdv'] ?? '') == 'urgence' ? 'selected' : ''; ?>>Urgence</option>
                                <option value="autre" <?php echo ($rdv['type_rdv'] ?? '') == 'autre' ? 'selected' : ''; ?>>Autre</option>
                            </select>
                        </div>
                        
                        <?php if ($action === 'edit'): ?>
                        <div class="col-md-6">
                            <label class="form-label">Statut</label>
                            <select class="form-select" name="statut">
                                <option value="confirme" <?php echo ($rdv['statut'] ?? 'confirme') == 'confirme' ? 'selected' : ''; ?>>Confirmé</option>
                                <option value="annule" <?php echo ($rdv['statut'] ?? '') == 'annule' ? 'selected' : ''; ?>>Annulé</option>
                                <option value="reporte" <?php echo ($rdv['statut'] ?? '') == 'reporte' ? 'selected' : ''; ?>>Reporté</option>
                                <option value="present" <?php echo ($rdv['statut'] ?? '') == 'present' ? 'selected' : ''; ?>>Présent</option>
                                <option value="absent" <?php echo ($rdv['statut'] ?? '') == 'absent' ? 'selected' : ''; ?>>Absent</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Motif et notes -->
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Motif</label>
                            <textarea class="form-control" name="motif" rows="3"><?php echo htmlspecialchars($rdv['motif'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars($rdv['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Disponibilité -->
                    <div class="mt-4 p-3 bg-light rounded" id="disponibilityCheck">
                        <h6 class="mb-3"><i class="fas fa-clock me-2"></i>Vérification de disponibilité</h6>
                        <p class="text-muted small mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Sélectionnez un médecin, une date et une heure pour vérifier la disponibilité
                        </p>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i>
                            <?php echo $action === 'add' ? 'Créer le rendez-vous' : 'Enregistrer'; ?>
                        </button>
                        <a href="rendezvous.php" class="btn btn-outline-secondary ms-2">Annuler</a>
                        
                        <?php if ($action === 'edit'): ?>
                        <button type="button" class="btn btn-danger ms-2" 
                                onclick="confirmAnnuler(<?php echo $id; ?>)">
                            <i class="fas fa-times me-1"></i>Annuler RDV
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Liste des rendez-vous -->
<div class="card shadow-sm">
    <div class="card-header bg-white border-bottom">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h6 class="mb-0">
                    <i class="fas fa-calendar me-2"></i>
                    Calendrier des rendez-vous
                </h6>
            </div>
            <div class="col-md-6">
                <form method="GET" class="row g-2" id="filterForm">
                    <div class="col">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Patient, médecin, motif..." 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" id="searchInput">
                    </div>
                    <div class="col-auto">
                        <select class="form-select" name="statut" onchange="this.form.submit()">
                            <option value="">Tous statuts</option>
                            <option value="confirme" <?php echo ($_GET['statut'] ?? '') === 'confirme' ? 'selected' : ''; ?>>Confirmé</option>
                            <option value="annule" <?php echo ($_GET['statut'] ?? '') === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                            <option value="present" <?php echo ($_GET['statut'] ?? '') === 'present' ? 'selected' : ''; ?>>Présent</option>
                            <option value="absent" <?php echo ($_GET['statut'] ?? '') === 'absent' ? 'selected' : ''; ?>>Absent</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <select class="form-select" name="periode" onchange="this.form.submit()">
                            <option value="today" <?php echo ($_GET['periode'] ?? 'today') === 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                            <option value="week" <?php echo ($_GET['periode'] ?? '') === 'week' ? 'selected' : ''; ?>>Cette semaine</option>
                            <option value="month" <?php echo ($_GET['periode'] ?? '') === 'month' ? 'selected' : ''; ?>>Ce mois</option>
                            <option value="future" <?php echo ($_GET['periode'] ?? '') === 'future' ? 'selected' : ''; ?>>À venir</option>
                            <option value="past" <?php echo ($_GET['periode'] ?? '') === 'past' ? 'selected' : ''; ?>>Passés</option>
                            <option value="all" <?php echo ($_GET['periode'] ?? '') === 'all' ? 'selected' : ''; ?>>Tous</option>
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
                        <th>Date & Heure</th>
                        <th>Patient</th>
                        <th>Médecin</th>
                        <th>Type</th>
                        <th>Motif</th>
                        <th>Durée</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Construire la requête avec filtres
                    $sql = "SELECT r.*, 
                                   p.nom as patient_nom, p.prenom as patient_prenom, p.code_patient, p.telephone,
                                   d.nom as docteur_nom, d.prenom as docteur_prenom, d.specialite
                            FROM rendez_vous r
                            JOIN patients p ON r.patient_id = p.id
                            JOIN utilisateurs d ON r.docteur_id = d.id
                            WHERE 1=1";
                    
                    $params = [];
                    
                    // Filtre recherche
                    if (!empty($_GET['search'])) {
                        $sql .= " AND (p.nom LIKE ? OR p.prenom LIKE ? OR p.code_patient LIKE ? OR p.telephone LIKE ? 
                                  OR d.nom LIKE ? OR d.prenom LIKE ? OR r.motif LIKE ? OR r.notes LIKE ?)";
                        $searchTerm = "%{$_GET['search']}%";
                        $params = array_fill(0, 8, $searchTerm);
                    }
                    
                    // Filtre statut
                    if (!empty($_GET['statut'])) {
                        $sql .= " AND r.statut = ?";
                        $params[] = $_GET['statut'];
                    }
                    
                    // Filtre période
                    $periode = $_GET['periode'] ?? 'today';
                    switch ($periode) {
                        case 'today':
                            $sql .= " AND DATE(r.date_rdv) = CURDATE()";
                            break;
                        case 'week':
                            $sql .= " AND YEARWEEK(r.date_rdv, 1) = YEARWEEK(CURDATE(), 1)";
                            break;
                        case 'month':
                            $sql .= " AND MONTH(r.date_rdv) = MONTH(CURDATE()) AND YEAR(r.date_rdv) = YEAR(CURDATE())";
                            break;
                        case 'future':
                            $sql .= " AND r.date_rdv >= CURDATE()";
                            break;
                        case 'past':
                            $sql .= " AND r.date_rdv < CURDATE()";
                            break;
                        // 'all' ne filtre pas par date
                    }
                    
                    // Tri par date
                    $sql .= " ORDER BY r.date_rdv ASC";
                    
                    // Pagination
                    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                    $limit = 15;
                    $offset = ($page - 1) * $limit;
                    
                    $sql .= " LIMIT $limit OFFSET $offset";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $rendezvous = $stmt->fetchAll();
                    
                    foreach ($rendezvous as $rdv): 
                        $isPast = strtotime($rdv['date_rdv']) < time();
                        $isToday = date('Y-m-d', strtotime($rdv['date_rdv'])) == date('Y-m-d');
                        
                        // Couleur selon statut
                        $statusColor = '';
                        if ($rdv['statut'] == 'annule') $statusColor = 'secondary';
                        elseif ($rdv['statut'] == 'present') $statusColor = 'success';
                        elseif ($rdv['statut'] == 'absent') $statusColor = 'danger';
                        elseif ($isPast && $rdv['statut'] == 'confirme') $statusColor = 'warning';
                        else $statusColor = 'primary';
                        
                        // Couleur selon urgence
                        $rowClass = '';
                        if ($rdv['type_rdv'] == 'urgence') $rowClass = 'table-danger';
                        elseif ($isToday) $rowClass = 'table-info';
                        elseif ($isPast && $rdv['statut'] == 'confirme') $rowClass = 'table-warning';
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <td>
                            <div class="fw-semibold"><?php echo date('d/m/Y', strtotime($rdv['date_rdv'])); ?></div>
                            <small class="text-muted"><?php echo date('H:i', strtotime($rdv['date_rdv'])); ?></small>
                            <?php if ($isPast): ?>
                            <br><small class="text-muted"><?php echo timeElapsed($rdv['date_rdv']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($rdv['patient_prenom'] . ' ' . $rdv['patient_nom']); ?></div>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($rdv['code_patient']); ?><br>
                                <?php echo htmlspecialchars($rdv['telephone']); ?>
                            </small>
                        </td>
                        <td>
                            <div class="fw-semibold">Dr. <?php echo htmlspecialchars($rdv['docteur_prenom'] . ' ' . $rdv['docteur_nom']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($rdv['specialite']); ?></small>
                        </td>
                        <td>
                            <?php 
                                $typeLabels = [
                                    'consultation' => 'Consultation',
                                    'controle' => 'Contrôle',
                                    'urgence' => 'Urgence',
                                    'autre' => 'Autre'
                                ];
                                $typeLabel = $typeLabels[$rdv['type_rdv']] ?? $rdv['type_rdv'];
                            ?>
                            <span class="badge bg-light text-dark"><?php echo $typeLabel; ?></span>
                        </td>
                        <td>
                            <?php if ($rdv['motif']): ?>
                            <span title="<?php echo htmlspecialchars($rdv['motif']); ?>">
                                <?php echo htmlspecialchars(substr($rdv['motif'], 0, 30)); ?>
                                <?php if (strlen($rdv['motif']) > 30): ?>...<?php endif; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $rdv['duree']; ?> min</td>
                        <td>
                            <span class="badge bg-<?php echo $statusColor; ?>">
                                <?php echo ucfirst($rdv['statut']); ?>
                                <?php if ($isPast && $rdv['statut'] == 'confirme'): ?>
                                (Non honoré)
                                <?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=edit&id=<?php echo $rdv['id']; ?>" 
                                   class="btn btn-outline-primary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="../docteur/consultations.php?action=add&patient_id=<?php echo $rdv['patient_id']; ?>&rdv_id=<?php echo $rdv['id']; ?>" 
                                   class="btn btn-outline-success" title="Créer consultation">
                                    <i class="fas fa-stethoscope"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $rdv['id']; ?>)" 
                                        title="Supprimer" <?php echo $isPast ? 'disabled' : ''; ?>>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($rendezvous)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-calendar-times fa-2x text-muted mb-3"></i>
                            <p class="text-muted">Aucun rendez-vous trouvé</p>
                            <a href="?action=add" class="btn btn-primary btn-sm">
                                <i class="fas fa-calendar-plus me-1"></i>Créer un rendez-vous
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
                $countSql = "SELECT COUNT(*) FROM rendez_vous r
                             JOIN patients p ON r.patient_id = p.id
                             JOIN utilisateurs d ON r.docteur_id = d.id
                             WHERE 1=1";
                
                if (!empty($_GET['search'])) {
                    $countSql .= " AND (p.nom LIKE ? OR p.prenom LIKE ? OR p.code_patient LIKE ? OR p.telephone LIKE ? 
                                  OR d.nom LIKE ? OR d.prenom LIKE ? OR r.motif LIKE ? OR r.notes LIKE ?)";
                }
                if (!empty($_GET['statut'])) {
                    $countSql .= " AND r.statut = ?";
                }
                
                switch ($periode) {
                    case 'today':
                        $countSql .= " AND DATE(r.date_rdv) = CURDATE()";
                        break;
                    case 'week':
                        $countSql .= " AND YEARWEEK(r.date_rdv, 1) = YEARWEEK(CURDATE(), 1)";
                        break;
                    case 'month':
                        $countSql .= " AND MONTH(r.date_rdv) = MONTH(CURDATE()) AND YEAR(r.date_rdv) = YEAR(CURDATE())";
                        break;
                    case 'future':
                        $countSql .= " AND r.date_rdv >= CURDATE()";
                        break;
                    case 'past':
                        $countSql .= " AND r.date_rdv < CURDATE()";
                        break;
                }
                
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $totalRdv = $countStmt->fetchColumn();
                $totalPages = ceil($totalRdv / $limit);
                ?>
                <small class="text-muted">
                    Affichage <?php echo min(($page - 1) * $limit + 1, $totalRdv); ?>-<?php echo min($page * $limit, $totalRdv); ?> 
                    sur <?php echo $totalRdv; ?> rendez-vous
                </small>
            </div>
            <div>
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&statut=<?php echo urlencode($_GET['statut'] ?? ''); ?>&periode=<?php echo urlencode($periode); ?>" 
                   class="btn btn-sm btn-outline-secondary me-2">
                    <i class="fas fa-chevron-left me-1"></i>Précédent
                </a>
                <?php endif; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&statut=<?php echo urlencode($_GET['statut'] ?? ''); ?>&periode=<?php echo urlencode($periode); ?>" 
                   class="btn btn-sm btn-outline-secondary">
                    Suivant<i class="fas fa-chevron-right ms-1"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mt-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="card-title">Aujourd'hui</h6>
                <?php
                $todayStmt = $pdo->prepare("
                    SELECT COUNT(*) as total,
                           SUM(CASE WHEN statut = 'present' THEN 1 ELSE 0 END) as presents,
                           SUM(CASE WHEN statut = 'absent' THEN 1 ELSE 0 END) as absents
                    FROM rendez_vous 
                    WHERE DATE(date_rdv) = CURDATE()
                ");
                $todayStmt->execute();
                $today = $todayStmt->fetch();
                ?>
                <h3 class="mb-0"><?php echo $today['total'] ?? 0; ?></h3>
                <small class="opacity-75">
                    <?php echo $today['presents'] ?? 0; ?> présents, 
                    <?php echo $today['absents'] ?? 0; ?> absents
                </small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="card-title">À venir (7j)</h6>
                <?php
                $weekStmt = $pdo->prepare("
                    SELECT COUNT(*) as total
                    FROM rendez_vous 
                    WHERE date_rdv BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                    AND statut = 'confirme'
                ");
                $weekStmt->execute();
                $week = $weekStmt->fetch();
                ?>
                <h3 class="mb-0"><?php echo $week['total'] ?? 0; ?></h3>
                <small class="opacity-75">Prochains 7 jours</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6 class="card-title">Médecins disponibles</h6>
                <?php
                $doctorsStmt = $pdo->query("
                    SELECT COUNT(DISTINCT docteur_id) as total
                    FROM rendez_vous 
                    WHERE DATE(date_rdv) = CURDATE()
                    AND statut = 'confirme'
                ");
                $doctors = $doctorsStmt->fetch();
                ?>
                <h3 class="mb-0"><?php echo $doctors['total'] ?? 0; ?></h3>
                <small class="opacity-75">Avec RDV aujourd'hui</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6 class="card-title">Taux d'occupation</h6>
                <?php
                // Calculer le taux d'occupation moyen des médecins aujourd'hui
                $occupationStmt = $pdo->query("
                    SELECT AVG(occupation_rate) as taux
                    FROM (
                        SELECT docteur_id, 
                               COUNT(*) * 100.0 / 8 as occupation_rate
                        FROM rendez_vous 
                        WHERE DATE(date_rdv) = CURDATE()
                        AND statut = 'confirme'
                        GROUP BY docteur_id
                    ) as subquery
                ");
                $occupation = $occupationStmt->fetch();
                ?>
                <h3 class="mb-0"><?php echo number_format($occupation['taux'] ?? 0, 1); ?>%</h3>
                <small class="opacity-75">Moyenne des médecins</small>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

<script>
// Vérifier la disponibilité
function checkDisponibility() {
    const docteurId = document.getElementById('docteurSelect').value;
    const dateInput = document.getElementById('dateInput').value;
    const heureInput = document.getElementById('heureInput').value;
    const rdvId = <?php echo $id ?? 'null'; ?>;
    
    if (!docteurId || !dateInput || !heureInput) {
        return;
    }
    
    const dateTime = dateInput + ' ' + heureInput + ':00';
    
    fetch('../ajax/check_disponibility.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `docteur_id=${docteurId}&date_rdv=${encodeURIComponent(dateTime)}&rdv_id=${rdvId || ''}`
    })
    .then(response => response.json())
    .then(data => {
        const checkDiv = document.getElementById('disponibilityCheck');
        if (data.available) {
            checkDiv.innerHTML = `
                <h6 class="mb-3 text-success"><i class="fas fa-check-circle me-2"></i>Disponible</h6>
                <p class="text-success mb-0">
                    <i class="fas fa-check me-1"></i>
                    Le médecin est disponible à cette heure.
                </p>
            `;
        } else {
            checkDiv.innerHTML = `
                <h6 class="mb-3 text-danger"><i class="fas fa-times-circle me-2"></i>Non disponible</h6>
                <p class="text-danger mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    ${data.message || 'Le médecin a déjà un rendez-vous à cette heure.'}
                </p>
                ${data.suggestions ? `
                <div class="mt-2">
                    <small class="text-muted">Suggestions :</small>
                    <div class="mt-1">${data.suggestions}</div>
                </div>
                ` : ''}
            `;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
    });
}

// Écouter les changements
document.getElementById('docteurSelect')?.addEventListener('change', checkDisponibility);
document.getElementById('dateInput')?.addEventListener('change', checkDisponibility);
document.getElementById('heureInput')?.addEventListener('change', checkDisponibility);

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
    window.location.href = 'rendezvous.php';
}

// Confirmer l'annulation
function confirmAnnuler(rdvId) {
    if (confirm('Annuler ce rendez-vous ? Le patient sera notifié.')) {
        window.location.href = `?action=annuler&id=${rdvId}`;
    }
}

// Confirmer la suppression
function confirmDelete(rdvId) {
    if (confirm('Supprimer définitivement ce rendez-vous ?')) {
        window.location.href = `?action=delete&id=${rdvId}`;
    }
}

// Formater la date et l'heure pour le formulaire
function updateDateTime() {
    const dateInput = document.getElementById('dateInput');
    const heureInput = document.getElementById('heureInput');
    
    if (dateInput && heureInput && !dateInput.value) {
        dateInput.valueAsDate = new Date();
        
        // Arrondir à l'heure suivante
        const now = new Date();
        const minutes = now.getMinutes();
        const roundedMinutes = Math.ceil(minutes / 15) * 15;
        
        if (roundedMinutes >= 60) {
            now.setHours(now.getHours() + 1);
            now.setMinutes(0);
        } else {
            now.setMinutes(roundedMinutes);
        }
        
        heureInput.value = now.toTimeString().substring(0, 5);
    }
}

// Importer des rendez-vous
function importRendezVous() {
    alert('Fonctionnalité d\'import en développement');
}

// Initialiser au chargement
document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    checkDisponibility();
    
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
.required::after {
    content: " *";
    color: #dc3545;
}
.table th {
    font-weight: 600;
    color: #6b7280;
    background-color: #f9fafb;
    border-bottom: 2px solid #e5e7eb;
    padding: 1rem;
    text-transform: uppercase;
    font-size: 0.75rem;
}
.table td {
    padding: 1rem;
    vertical-align: middle;
}
.table-info {
    background-color: rgba(13, 202, 240, 0.1);
}
.table-warning {
    background-color: rgba(255, 193, 7, 0.1);
}
.table-danger {
    background-color: rgba(220, 53, 69, 0.1);
}
</style>