<?php
// assistant/consultations.php
require_once '../config/database.php';
checkRole('assistant');

$title = 'Gestion des Consultations';
$assistant_id = $_SESSION['user_id'];
require_once '../includes/header.php';

$pdo = Database::getInstance()->getConnection();

// --- Route AJAX interne pour récupérer les informations patient ---
if (isset($_GET['action']) && $_GET['action'] === 'get_patient_info' && isset($_GET['id'])) {
    $patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$patient_id) {
        echo '<div class="text-muted small">Patient invalide</div>';
        exit;
    }
    $stmt = $pdo->prepare("SELECT date_naissance, allergies, medicaments_habituels FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) {
        echo '<div class="text-muted small">Patient non trouvé</div>';
        exit;
    }
    $birth = new DateTime($patient['date_naissance']);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
    ?>
    <div class="card bg-light">
        <div class="card-body p-3">
            <small>
                <strong>Âge :</strong> <?php echo $age; ?> ans<br>
                <strong>Allergies :</strong> <?php echo htmlspecialchars($patient['allergies'] ?: 'Aucune'); ?><br>
                <strong>Traitements habituels :</strong> <?php echo htmlspecialchars($patient['medicaments_habituels'] ?: 'Aucun'); ?>
            </small>
        </div>
    </div>
    <?php
    exit;
}

// --- Récupération des paramètres GET ---
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?? 'list';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);

if ($patient_id && $action === 'list') {
    $action = 'add';
}
if (in_array($action, ['edit', 'delete']) && !$id) {
    $_SESSION['error'] = "Action invalide : identifiant manquant.";
    exit();
}

$types_consultation = ['premiere', 'suivi', 'urgence', 'controle'];

// --- Traitement POST (ajout / modification / suppression) ---
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
            $stmt->execute([
                'TEMP',
                $data['patient_id'],
                $data['docteur_id'],
                $assistant_id,
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
            logAction('CREATE', 'consultations', $pdo->lastInsertId(), "Création consultation");
            $_SESSION['success'] = "Consultation créée avec succès.";
            $pdo->commit();
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
                $assistant_id,
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
            logAction('UPDATE', 'consultations', $id, "Modification consultation");
            $_SESSION['success'] = "Consultation modifiée avec succès.";
            $pdo->commit();
           
        } elseif ($action === 'delete' && $id) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE consultation_id = ?");
            $check->execute([$id]);
            if ($check->fetchColumn() > 0) {
                throw new Exception("Cette consultation a des prescriptions associées. Impossible de supprimer.");
            }
            $del = $pdo->prepare("DELETE FROM consultations WHERE id = ?");
            $del->execute([$id]);
            logAction('DELETE', 'consultations', $id, "Suppression consultation");
            $_SESSION['success'] = "Consultation supprimée avec succès.";
            $pdo->commit();
           
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
       
    }
}

// --- Affichage des messages flash ---
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
         . htmlspecialchars($_SESSION['success']) .
         '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
         . htmlspecialchars($_SESSION['error']) .
         '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['error']);
}
?>

<!-- En-tête de la page -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0"><i class="fas fa-stethoscope me-2"></i>Gestion des Consultations</h1>
        <p class="text-muted mb-0">Administration des consultations médicales</p>
    </div>
    <div class="btn-toolbar">
        <?php if ($action === 'list'): ?>
            <div class="btn-group me-2">
                <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus-circle me-1"></i>Nouvelle consultation</a>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="agenda.php"><i class="fas fa-calendar-alt me-2"></i>Voir l'agenda</a>
                    <a class="dropdown-item" href="rapports.php?type=consultations"><i class="fas fa-chart-bar me-2"></i>Rapport consultations</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="#" onclick="exportConsultations()"><i class="fas fa-file-export me-2"></i>Exporter</a>
                </div>
            </div>
        <?php else: ?>
            <a href="consultations.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Retour à la liste</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Formulaire d'ajout / modification -->
<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-file-medical me-2"></i><?php echo $action === 'add' ? 'Nouvelle consultation' : 'Modifier la consultation'; ?></h5>
            </div>
            <div class="card-body">
                <?php
                $consultation = null;
                $date_consultation = date('Y-m-d');
                $heure_consultation = '09:00';
                $selected_patient_id = $patient_id; 

                if ($action === 'edit' && $id) {
                    $stmt = $pdo->prepare("SELECT * FROM consultations WHERE id = ?");
                    $stmt->execute([$id]);
                    $consultation = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$consultation) {
                        echo '<div class="alert alert-danger">Consultation non trouvée</div>';
                        require_once '../includes/footer.php';
                        exit();
                    }
                    $date_consultation = date('Y-m-d', strtotime($consultation['date_consultation']));
                    $heure_consultation = date('H:i', strtotime($consultation['date_consultation']));
                    $selected_patient_id = $consultation['patient_id'];
                }

                // Récupérer les infos du patient pour affichage initial (si un patient est sélectionné)
                $patient_info = null;
                if ($selected_patient_id) {
                    $pinfo = $pdo->prepare("SELECT date_naissance, allergies, medicaments_habituels FROM patients WHERE id = ?");
                    $pinfo->execute([$selected_patient_id]);
                    $patient_info = $pinfo->fetch(PDO::FETCH_ASSOC);
                }
                ?>
                <form method="POST" id="consultationForm" novalidate>
                    <!-- Onglets -->
                    <ul class="nav nav-tabs mb-4" id="consultationTabs" role="tablist">
                        <li class="nav-item"><button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">Informations générales</button></li>
                        <li class="nav-item"><button class="nav-link" id="medical-tab" data-bs-toggle="tab" data-bs-target="#medical" type="button">Examen médical</button></li>
                        <li class="nav-item"><button class="nav-link" id="result-tab" data-bs-toggle="tab" data-bs-target="#result" type="button">Diagnostic et traitement</button></li>
                    </ul>
                    <div class="tab-content">
                        <!-- Onglet Informations générales -->
                        <div class="tab-pane fade show active" id="info">
                            <div class="row g-3">
                                <!-- Patient -->
                                <div class="col-md-6">
                                    <label class="form-label required">Patient</label>
                                    <select class="form-select" name="patient_id" id="patientSelect" required onchange="loadPatientInfo(this.value)">
                                        <option value="">Sélectionner un patient</option>
                                        <?php
                                        $patients = $pdo->query("SELECT id, nom, prenom, code_patient FROM patients WHERE statut = 'actif' ORDER BY nom, prenom")->fetchAll();
                                        foreach ($patients as $patient): ?>
                                            <option value="<?php echo $patient['id']; ?>" <?php echo ($selected_patient_id == $patient['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($patient['prenom'] . ' ' . $patient['nom'] . ' (' . $patient['code_patient'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Informations patient (chargées via PHP initial + AJAX ensuite) -->
                                <div class="col-md-6" id="patientInfo">
                                    <?php if ($patient_info): ?>
                                        <div class="card bg-light">
                                            <div class="card-body p-3">
                                                <small>
                                                    <strong>Âge:</strong> <?php echo calculateAge($patient_info['date_naissance']); ?> ans<br>
                                                    <strong>Allergies:</strong> <?php echo htmlspecialchars($patient_info['allergies'] ?: 'Aucune'); ?><br>
                                                    <strong>Traitements habituels:</strong> <?php echo htmlspecialchars($patient_info['medicaments_habituels'] ?: 'Aucun'); ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted small">Sélectionnez un patient pour voir ses informations</div>
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
                                            <option value="<?php echo $docteur['id']; ?>" <?php echo ($consultation['docteur_id'] ?? '') == $docteur['id'] ? 'selected' : ''; ?>>
                                                Dr. <?php echo htmlspecialchars($docteur['prenom'] . ' ' . $docteur['nom']); ?> - <?php echo htmlspecialchars($docteur['specialite']); ?>
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
                                            <option value="<?php echo $assistant['id']; ?>" <?php echo ($consultation['assistant_id'] ?? '') == $assistant['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($assistant['prenom'] . ' ' . $assistant['nom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Date et heure -->
                                <div class="col-md-6">
                                    <label class="form-label required">Date de consultation</label>
                                    <input type="date" class="form-control" name="date_consultation" value="<?php echo $date_consultation; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Heure</label>
                                    <input type="time" class="form-control" name="heure_consultation" value="<?php echo $heure_consultation; ?>" required>
                                </div>
                                <!-- Durée et type -->
                                <div class="col-md-6">
                                    <label class="form-label">Durée (minutes)</label>
                                    <input type="number" class="form-control" name="duree" value="<?php echo $consultation['duree'] ?? '30'; ?>" min="5" max="240">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Type de consultation</label>
                                    <select class="form-select" name="type_consultation" required>
                                        <option value="">Sélectionner</option>
                                        <?php foreach ($types_consultation as $type): ?>
                                            <option value="<?php echo $type; ?>" <?php echo ($consultation['type_consultation'] ?? '') == $type ? 'selected' : ''; ?>><?php echo ucfirst($type); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Motif -->
                                <div class="col-12">
                                    <label class="form-label required">Motif de consultation</label>
                                    <textarea class="form-control" name="motif" rows="2" required><?php echo htmlspecialchars($consultation['motif'] ?? ''); ?></textarea>
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
                                        <input class="form-check-input" type="checkbox" name="urgence" value="1" id="urgenceCheck" <?php echo ($consultation['urgence'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label text-danger fw-semibold" for="urgenceCheck"><i class="fas fa-exclamation-triangle me-1"></i>Consultation urgente</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Onglet Examen médical -->
                        <div class="tab-pane fade" id="medical">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Histoire de la maladie</label>
                                    <textarea class="form-control" name="histoire_maladie" rows="4"><?php echo htmlspecialchars($consultation['histoire_maladie'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Examen clinique</label>
                                    <textarea class="form-control" name="examen_clinique" rows="6"><?php echo htmlspecialchars($consultation['examen_clinique'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Examens complémentaires</label>
                                    <textarea class="form-control" name="examen_complementaire" rows="6"><?php echo htmlspecialchars($consultation['examen_complementaire'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <!-- Onglet Diagnostic et traitement -->
                        <div class="tab-pane fade" id="result">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Diagnostic</label>
                                    <textarea class="form-control" name="diagnostic" rows="4"><?php echo htmlspecialchars($consultation['diagnostic'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Traitement prescrit</label>
                                    <textarea class="form-control" name="traitement" rows="4"><?php echo htmlspecialchars($consultation['traitement'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Recommandations</label>
                                    <textarea class="form-control" name="recommandations" rows="3"><?php echo htmlspecialchars($consultation['recommandations'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes complémentaires</label>
                                    <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($consultation['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i><?php echo $action === 'add' ? 'Créer la consultation' : 'Enregistrer les modifications'; ?></button>
                        <button type="reset" class="btn btn-secondary ms-2">Réinitialiser</button>
                        <a href="consultations.php" class="btn btn-outline-secondary ms-2">Annuler</a>
                        <?php if ($action === 'edit'): ?>
                            <button type="button" class="btn btn-success ms-2" onclick="generatePrescription(<?php echo $id; ?>)"><i class="fas fa-prescription me-1"></i>Générer une prescription</button>
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
            <div class="col-md-6"><h6 class="mb-0"><i class="fas fa-list me-2"></i>Liste des consultations</h6></div>
            <div class="col-md-6">
                <form method="GET" class="row g-2">
                    <div class="col"><input type="text" class="form-control" name="search" placeholder="Rechercher..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" id="searchInput"></div>
                    <div class="col-auto"><select class="form-select" name="statut" onchange="this.form.submit()"><option value="">Tous les statuts</option><option value="planifie" <?php echo ($_GET['statut'] ?? '') === 'planifie' ? 'selected' : ''; ?>>Planifié</option><option value="en_cours" <?php echo ($_GET['statut'] ?? '') === 'en_cours' ? 'selected' : ''; ?>>En cours</option><option value="termine" <?php echo ($_GET['statut'] ?? '') === 'termine' ? 'selected' : ''; ?>>Terminé</option><option value="annule" <?php echo ($_GET['statut'] ?? '') === 'annule' ? 'selected' : ''; ?>>Annulé</option></select></div>
                    <div class="col-auto"><input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>" onchange="this.form.submit()"></div>
                    <div class="col-auto"><button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i></button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Référence</th><th>Patient</th><th>Médecin</th><th>Date/Heure</th><th>Motif</th><th>Diagnostic</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php
                    // Construction de la requête avec filtres
                    $sql = "SELECT c.*, p.nom as patient_nom, p.prenom as patient_prenom, p.code_patient,
                                   d.nom as docteur_nom, d.prenom as docteur_prenom, d.specialite
                            FROM consultations c
                            JOIN patients p ON c.patient_id = p.id
                            JOIN utilisateurs d ON c.docteur_id = d.id
                            WHERE 1=1";
                    $params = [];
                    if (!empty($_GET['search'])) {
                        $sql .= " AND (p.nom COLLATE utf8mb4_unicode_ci LIKE ? OR p.prenom COLLATE utf8mb4_unicode_ci LIKE ? 
                        OR c.reference COLLATE utf8mb4_unicode_ci LIKE ? OR c.motif COLLATE utf8mb4_unicode_ci LIKE ?)";
                        $searchTerm = "%{$_GET['search']}%";
                        $params = array_fill(0, 4, $searchTerm);
                    }
                    if (!empty($_GET['statut'])) {
                        $sql .= " AND c.statut = ?";
                        $params[] = $_GET['statut'];
                    }
                    if (!empty($_GET['date'])) {
                        $sql .= " AND DATE(c.date_consultation) = ?";
                        $params[] = $_GET['date'];
                    }
                    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                    $limit = 20;
                    $offset = ($page - 1) * $limit;
                    $sql .= " ORDER BY c.date_consultation DESC LIMIT $limit OFFSET $offset";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $consultations = $stmt->fetchAll();
                    $statusColors = ['planifie' => 'warning', 'en_cours' => 'info', 'termine' => 'success', 'annule' => 'danger', 'reporte' => 'secondary'];
                    foreach ($consultations as $consult): ?>
                        <tr>
                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($consult['reference']); ?></span><?php if ($consult['urgence']) echo '<span class="badge bg-danger ms-1">URGENT</span>'; ?></td>
                            <td><div class="fw-semibold"><?php echo htmlspecialchars($consult['patient_prenom'] . ' ' . $consult['patient_nom']); ?></div><small class="text-muted"><?php echo htmlspecialchars($consult['code_patient']); ?></small></td>
                            <td><div>Dr. <?php echo htmlspecialchars($consult['docteur_prenom'] . ' ' . $consult['docteur_nom']); ?></div><small class="text-muted"><?php echo htmlspecialchars($consult['specialite']); ?></small></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($consult['date_consultation'])); ?><br><small class="text-muted"><?php echo $consult['duree']; ?> min</small></td>
                            <td><span class="small" title="<?php echo htmlspecialchars($consult['motif']); ?>"><?php echo htmlspecialchars(substr($consult['motif'], 0, 50)); ?>...</span></td>
                            <td><span class="small" title="<?php echo htmlspecialchars($consult['diagnostic']); ?>"><?php echo htmlspecialchars(substr($consult['diagnostic'], 0, 50)); ?>...</span></td>
                            <td><span class="badge bg-<?php echo $statusColors[$consult['statut']] ?? 'secondary'; ?>"><?php echo ucfirst($consult['statut']); ?></span></td>
                            <td><div class="btn-group btn-group-sm"><a href="?action=edit&id=<?php echo $consult['id']; ?>" class="btn btn-outline-primary" title="Modifier"><i class="fas fa-edit"></i></a><a href="consultation_details.php?id=<?php echo $consult['id']; ?>" class="btn btn-outline-info" title="Détails"><i class="fas fa-eye"></i></a><button type="button" class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $consult['id']; ?>)" title="Supprimer"><i class="fas fa-trash"></i></button></div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($consultations)): ?>
                        <tr><td colspan="8" class="text-center py-4"><i class="fas fa-stethoscope fa-2x text-muted mb-3"></i><p class="text-muted">Aucune consultation trouvée</p><a href="?action=add" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle me-1"></i>Créer une consultation</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-top">
        <div class="d-flex justify-content-between align-items-center">
            <div><small class="text-muted">Page <?php echo $page; ?></small></div>
            <div>
                <?php
                // Compter le total pour la pagination
                $countSql = "SELECT COUNT(*) FROM consultations c WHERE 1=1";
                if (!empty($_GET['search'])) $countSql .= " AND (EXISTS (SELECT 1 FROM patients p WHERE p.id = c.patient_id AND (p.nom LIKE ? OR p.prenom LIKE ?)) OR c.reference LIKE ? OR c.motif LIKE ?)";
                if (!empty($_GET['statut'])) $countSql .= " AND c.statut = ?";
                if (!empty($_GET['date'])) $countSql .= " AND DATE(c.date_consultation) = ?";
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $total = $countStmt->fetchColumn();
                $totalPages = ceil($total / $limit);
                if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&statut=<?php echo urlencode($_GET['statut'] ?? ''); ?>&date=<?php echo urlencode($_GET['date'] ?? ''); ?>" class="btn btn-sm btn-outline-secondary me-2"><i class="fas fa-chevron-left me-1"></i>Précédent</a>
                <?php endif;
                if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&statut=<?php echo urlencode($_GET['statut'] ?? ''); ?>&date=<?php echo urlencode($_GET['date'] ?? ''); ?>" class="btn btn-sm btn-outline-secondary">Suivant<i class="fas fa-chevron-right ms-1"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

<script>
// Charger les informations du patient (AJAX pour changement dynamique)
async function loadPatientInfo(patientId) {
    if (!patientId) {
        document.getElementById('patientInfo').innerHTML = '<div class="text-muted small">Sélectionnez un patient pour voir ses informations</div>';
        return;
    }
    try {
        const response = await fetch(`../ajax/get_patient_info.php?id=${patientId}`);
        const data = await response.text();
        document.getElementById('patientInfo').innerHTML = data;
    } catch (error) {
        console.error('Erreur lors du chargement des infos patient:', error);
        document.getElementById('patientInfo').innerHTML = '<div class="alert alert-warning small">Impossible de charger les informations du patient.</div>';
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
    searchTimeout = setTimeout(() => this.form.submit(), 500);
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
    document.querySelectorAll('[title]').forEach(el => new bootstrap.Tooltip(el));
});
</script>

<style>
.nav-tabs .nav-link { color: #6b7280; font-weight: 500; border: none; padding: 0.75rem 1.5rem; }
.nav-tabs .nav-link.active { color: #4361ee; border-bottom: 2px solid #4361ee; background-color: transparent; }
.required::after { content: " *"; color: #dc3545; }
.tab-content { padding-top: 1rem; }
.table th { font-weight: 600; color: #6b7280; background-color: #f9fafb; border-bottom: 2px solid #e5e7eb; padding: 1rem; text-transform: uppercase; font-size: 0.75rem; }
.table td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #e5e7eb; }
</style>