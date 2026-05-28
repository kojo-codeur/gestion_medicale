<?php
// docteur/patients.php
require_once '../config/database.php';
checkRole('docteur');

$title = 'Mes Patients';
$docteur_id = $_SESSION['user_id'];

require_once '../includes/header.php';

// Traitement CRUD
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Recherche et filtres
$search = $_GET['search'] ?? '';
$ville = $_GET['ville'] ?? '';
$sexe = $_GET['sexe'] ?? '';
$age_min = $_GET['age_min'] ?? '';
$age_max = $_GET['age_max'] ?? '';
$urgence = isset($_GET['urgence']) ? 1 : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = sanitize($_POST);
    
    if ($action === 'add_note' && $id) {
        // Ajouter une note au patient
        $pdo->prepare("
            UPDATE patients SET notes = CONCAT(COALESCE(notes, ''), '\n', ?) 
            WHERE id = ?
        ")->execute([date('d/m/Y H:i') . ' - ' . $data['note'], $id]);
        
        $_SESSION['success'] = "Note ajoutée au dossier patient";
        header("Location: patients.php?action=view&id=$id");
        exit();
        
    } elseif ($action === 'update_info' && $id) {
        // Mettre à jour les informations médicales
        $pdo->prepare("
            UPDATE patients SET 
            poids = ?, taille = ?, allergies = ?, antecedents_personnels = ?, 
            antecedents_familiaux = ?, medicaments_habituels = ?, habitudes = ?
            WHERE id = ?
        ")->execute([
            $data['poids'] ?? null,
            $data['taille'] ?? null,
            $data['allergies'] ?? null,
            $data['antecedents_personnels'] ?? null,
            $data['antecedents_familiaux'] ?? null,
            $data['medicaments_habituels'] ?? null,
            $data['habitudes'] ?? null,
            $id
        ]);
        
        $_SESSION['success'] = "Informations médicales mises à jour";
        header("Location: patients.php?action=view&id=$id");
        exit();
    }
}

// Récupérer la liste des patients
$sql = "SELECT DISTINCT p.*, 
        TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as age,
        (SELECT COUNT(*) FROM consultations WHERE patient_id = p.id AND docteur_id = ?) as consultations_count,
        (SELECT MAX(date_consultation) FROM consultations WHERE patient_id = p.id AND docteur_id = ?) as derniere_consultation,
        (SELECT COUNT(*) FROM prescriptions WHERE patient_id = p.id AND docteur_id = ? AND statut = 'active') as prescriptions_actives,
        (SELECT COUNT(*) FROM consultations WHERE patient_id = p.id AND docteur_id = ? AND urgence = 1 AND DATE(date_consultation) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as urgences_recentes
        FROM patients p
        INNER JOIN consultations c ON p.id = c.patient_id
        WHERE c.docteur_id = ? 
        AND p.statut = 'actif'";
        
$params = [$docteur_id, $docteur_id, $docteur_id, $docteur_id, $docteur_id];

if ($search) {
    $sql .= " AND (p.nom LIKE ? OR p.prenom LIKE ? OR p.code_patient LIKE ? OR p.telephone LIKE ? OR p.email LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, array_fill(0, 5, $searchTerm));
}

if ($ville) {
    $sql .= " AND p.ville = ?";
    $params[] = $ville;
}

if ($sexe) {
    $sql .= " AND p.sexe = ?";
    $params[] = $sexe;
}

if ($age_min) {
    $sql .= " AND TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) >= ?";
    $params[] = $age_min;
}

if ($age_max) {
    $sql .= " AND TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) <= ?";
    $params[] = $age_max;
}

if ($urgence) {
    $sql .= " AND EXISTS (
        SELECT 1 FROM consultations c2 
        WHERE c2.patient_id = p.id 
        AND c2.docteur_id = ? 
        AND c2.urgence = 1 
        AND DATE(c2.date_consultation) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    )";
    $params[] = $docteur_id;
}

$sql .= " GROUP BY p.id ORDER BY derniere_consultation DESC, p.nom, p.prenom";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

// Récupérer les villes distinctes pour le filtre
$villes = $pdo->query("
    SELECT DISTINCT ville FROM patients 
    WHERE ville IS NOT NULL AND ville != '' 
    AND statut = 'actif'
    ORDER BY ville
")->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-user-injured me-2"></i>Mes Patients
        </h1>
        <p class="text-muted mb-0">
            <?php echo count($patients); ?> patient(s) suivis • 
            Dr. <?php echo $_SESSION['prenom'] . ' ' . $_SESSION['nom']; ?>
        </p>
    </div>
    <div class="btn-toolbar">
        <?php if ($action === 'list'): ?>
        <button class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#filterModal">
            <i class="fas fa-filter me-1"></i>Filtres
        </button>
        <a href="patients.php?export=csv" class="btn btn-sm btn-outline-success me-2">
            <i class="fas fa-file-export me-1"></i>Exporter
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['success']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['error']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['error']); endif; ?>

<?php if ($action === 'view' && $id): ?>
<!-- Vue détaillée d'un patient -->
<?php
$patient = $pdo->prepare("
    SELECT p.*, TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as age
    FROM patients p 
    WHERE p.id = ? AND p.statut = 'actif'
")->execute([$id])->fetch();

if (!$patient) {
    echo '<div class="alert alert-danger">Patient non trouvé</div>';
    require_once '../includes/footer.php';
    exit();
}

// Récupérer les consultations avec ce patient
$consultations = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM prescriptions WHERE consultation_id = c.id) as prescriptions_count,
           (SELECT COUNT(*) FROM documents_medicaux WHERE consultation_id = c.id) as documents_count
    FROM consultations c
    WHERE c.patient_id = ? AND c.docteur_id = ?
    ORDER BY c.date_consultation DESC
")->execute([$id, $docteur_id])->fetchAll();

// Récupérer les prescriptions actives
$prescriptions = $pdo->prepare("
    SELECT p.*
    FROM prescriptions p
    WHERE p.patient_id = ? AND p.docteur_id = ? AND p.statut = 'active'
    ORDER BY p.date_prescription DESC
")->execute([$id, $docteur_id])->fetchAll();

// Récupérer les pathologies du patient
$pathologies = $pdo->prepare("
    SELECT pp.*, pat.nom as pathologie_nom, pat.code_cim
    FROM patient_pathologie pp
    JOIN pathologies pat ON pp.pathologie_id = pat.id
    WHERE pp.patient_id = ? AND pp.statut IN ('active', 'chronique', 'en_suivi')
    ORDER BY pp.date_diagnostic DESC
")->execute([$id])->fetchAll();
?>
<div class="row">
    <!-- Informations patient -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Informations patient</h6>
                    <div class="btn-group">
                        <a href="patients.php?action=edit&id=<?php echo $patient['id']; ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="consultations.php?action=add&patient_id=<?php echo $patient['id']; ?>" 
                           class="btn btn-sm btn-outline-success">
                            <i class="fas fa-stethoscope"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="avatar-lg mx-auto mb-3">
                        <?php echo strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1)); ?>
                    </div>
                    <h4><?php echo $patient['prenom'] . ' ' . $patient['nom']; ?></h4>
                    <div class="text-muted">
                        <span class="badge bg-info"><?php echo $patient['code_patient']; ?></span>
                        <span class="badge bg-primary ms-1"><?php echo $patient['age']; ?> ans</span>
                        <span class="badge bg-secondary ms-1"><?php echo $patient['sexe'] == 'M' ? 'Homme' : 'Femme'; ?></span>
                    </div>
                </div>
                
                <div class="patient-info">
                    <div class="info-item mb-3">
                        <strong><i class="fas fa-phone me-2 text-muted"></i>Téléphone:</strong>
                        <div><?php echo $patient['telephone']; ?></div>
                    </div>
                    
                    <div class="info-item mb-3">
                        <strong><i class="fas fa-envelope me-2 text-muted"></i>Email:</strong>
                        <div><?php echo $patient['email'] ?? 'Non renseigné'; ?></div>
                    </div>
                    
                    <div class="info-item mb-3">
                        <strong><i class="fas fa-map-marker-alt me-2 text-muted"></i>Adresse:</strong>
                        <div>
                            <?php echo $patient['adresse'] ?? 'Non renseigné'; ?><br>
                            <?php if ($patient['code_postal'] && $patient['ville']): ?>
                            <?php echo $patient['code_postal'] . ' ' . $patient['ville']; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($patient['groupe_sanguin']): ?>
                    <div class="info-item mb-3">
                        <strong><i class="fas fa-tint me-2 text-muted"></i>Groupe sanguin:</strong>
                        <div>
                            <span class="badge bg-danger"><?php echo $patient['groupe_sanguin']; ?></span>
                            <?php if ($patient['rhésus']): ?>
                            <span class="badge bg-dark">Rhésus <?php echo $patient['rhésus']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($patient['poids'] && $patient['taille']): 
                        $imc = $patient['poids'] / (($patient['taille']/100) * ($patient['taille']/100));
                        $imcClass = $imc < 18.5 ? 'text-info' : ($imc < 25 ? 'text-success' : ($imc < 30 ? 'text-warning' : 'text-danger'));
                    ?>
                    <div class="info-item mb-3">
                        <strong><i class="fas fa-weight me-2 text-muted"></i>IMC:</strong>
                        <div>
                            <span class="<?php echo $imcClass; ?> fw-semibold"><?php echo number_format($imc, 1); ?></span>
                            <small class="text-muted">(<?php echo $patient['poids']; ?> kg / <?php echo $patient['taille']; ?> cm)</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($patient['profession']): ?>
                    <div class="info-item mb-3">
                        <strong><i class="fas fa-briefcase me-2 text-muted"></i>Profession:</strong>
                        <div><?php echo $patient['profession']; ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Informations médicales -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white">
                <h6 class="mb-0">Informations médicales</h6>
            </div>
            <div class="card-body">
                <?php if ($patient['allergies']): ?>
                <div class="mb-3">
                    <strong class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Allergies:</strong>
                    <div class="small text-muted"><?php echo nl2br($patient['allergies']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['antecedents_personnels']): ?>
                <div class="mb-3">
                    <strong>Antécédents personnels:</strong>
                    <div class="small text-muted"><?php echo nl2br($patient['antecedents_personnels']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['antecedents_familiaux']): ?>
                <div class="mb-3">
                    <strong>Antécédents familiaux:</strong>
                    <div class="small text-muted"><?php echo nl2br($patient['antecedents_familiaux']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['medicaments_habituels']): ?>
                <div class="mb-3">
                    <strong>Médicaments habituels:</strong>
                    <div class="small text-muted"><?php echo nl2br($patient['medicaments_habituels']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['habitudes']): ?>
                <div class="mb-3">
                    <strong>Habitudes:</strong>
                    <div class="small text-muted"><?php echo nl2br($patient['habitudes']); ?></div>
                </div>
                <?php endif; ?>
                
                <button class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#updateMedicalInfoModal">
                    <i class="fas fa-edit me-1"></i>Mettre à jour
                </button>
            </div>
        </div>
    </div>
    
    <!-- Contenu principal -->
    <div class="col-lg-8 mb-4">
        <!-- Pathologies -->
        <?php if (!empty($pathologies)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-virus me-2"></i>Pathologies suivies</h6>
                <a href="#" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addPathologieModal">
                    <i class="fas fa-plus me-1"></i>Ajouter
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Pathologie</th>
                                <th>Code CIM</th>
                                <th>Date diagnostic</th>
                                <th>Gravité</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pathologies as $patho): 
                                $graviteColors = [
                                    'legere' => 'success',
                                    'moderee' => 'warning',
                                    'grave' => 'danger'
                                ];
                                $statutColors = [
                                    'active' => 'primary',
                                    'chronique' => 'info',
                                    'en_suivi' => 'warning',
                                    'guerie' => 'success'
                                ];
                            ?>
                            <tr>
                                <td><?php echo $patho['pathologie_nom']; ?></td>
                                <td><span class="badge bg-secondary"><?php echo $patho['code_cim']; ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($patho['date_diagnostic'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $graviteColors[$patho['gravite']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($patho['gravite']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $statutColors[$patho['statut']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($patho['statut']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewPathologie(<?php echo $patho['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-warning" onclick="editPathologie(<?php echo $patho['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Consultations récentes -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Consultations récentes</h6>
                <a href="consultations.php?action=add&patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i>Nouvelle consultation
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($consultations)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-stethoscope fa-2x text-muted mb-3"></i>
                    <p class="text-muted">Aucune consultation enregistrée</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($consultations as $consult): 
                        $statusColors = [
                            'planifie' => 'warning',
                            'en_cours' => 'info',
                            'termine' => 'success',
                            'annule' => 'danger',
                            'reporte' => 'secondary'
                        ];
                    ?>
                    <div class="list-group-item border-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    <h6 class="mb-0 me-3">
                                        Consultation <?php echo $consult['reference']; ?>
                                    </h6>
                                    <span class="badge bg-<?php echo $statusColors[$consult['statut']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($consult['statut']); ?>
                                    </span>
                                    <?php if ($consult['urgence']): ?>
                                    <span class="badge bg-danger ms-2">
                                        <i class="fas fa-exclamation-triangle me-1"></i>Urgent
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted mb-2">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($consult['date_consultation'])); ?>
                                    • <?php echo $consult['duree']; ?> minutes
                                    • <?php echo ucfirst($consult['type_consultation']); ?>
                                </div>
                                <?php if ($consult['motif']): ?>
                                <p class="mb-2">
                                    <strong>Motif:</strong> <?php echo $consult['motif']; ?>
                                </p>
                                <?php endif; ?>
                                <?php if ($consult['diagnostic']): ?>
                                <p class="mb-2">
                                    <strong>Diagnostic:</strong> <?php echo substr($consult['diagnostic'], 0, 100); ?>
                                    <?php if (strlen($consult['diagnostic']) > 100): ?>...<?php endif; ?>
                                </p>
                                <?php endif; ?>
                                <div class="d-flex gap-2 mt-2">
                                    <?php if ($consult['prescriptions_count'] > 0): ?>
                                    <span class="badge bg-info">
                                        <i class="fas fa-prescription me-1"></i><?php echo $consult['prescriptions_count']; ?> prescription(s)
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($consult['documents_count'] > 0): ?>
                                    <span class="badge bg-warning">
                                        <i class="fas fa-file me-1"></i><?php echo $consult['documents_count']; ?> document(s)
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="btn-group btn-group-sm ms-3">
                                <a href="consultations.php?action=view&id=<?php echo $consult['id']; ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($consult['statut'] == 'termine'): ?>
                                <a href="prescriptions.php?action=add&consultation_id=<?php echo $consult['id']; ?>" 
                                   class="btn btn-outline-success">
                                    <i class="fas fa-prescription"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($consultations)): ?>
            <div class="card-footer bg-white border-top">
                <a href="consultations.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-outline-primary">
                    Voir toutes les consultations
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Prescriptions actives -->
        <?php if (!empty($prescriptions)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-prescription me-2"></i>Prescriptions actives</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($prescriptions as $pres): ?>
                    <div class="list-group-item border-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">Prescription <?php echo $pres['reference']; ?></h6>
                                <div class="small text-muted mb-2">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('d/m/Y', strtotime($pres['date_prescription'])); ?>
                                    • Durée: <?php echo $pres['duree_traitement']; ?>
                                </div>
                                <div class="small">
                                    <?php 
                                    $medicaments = json_decode($pres['medicaments'], true);
                                    if (is_array($medicaments)) {
                                        echo '<strong>Médicaments:</strong> ';
                                        $medNames = array_column($medicaments, 'nom');
                                        echo implode(', ', $medNames);
                                    }
                                    ?>
                                </div>
                            </div>
                            <div>
                                <a href="prescriptions.php?action=view&id=<?php echo $pres['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="addNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?action=add_note&id=<?php echo $patient['id']; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Note médicale</label>
                        <textarea class="form-control" name="note" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter la note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="updateMedicalInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mettre à jour les informations médicales</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?action=update_info&id=<?php echo $patient['id']; ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Poids (kg)</label>
                            <input type="number" step="0.1" class="form-control" name="poids" 
                                   value="<?php echo $patient['poids']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Taille (cm)</label>
                            <input type="number" step="0.1" class="form-control" name="taille" 
                                   value="<?php echo $patient['taille']; ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Allergies connues</label>
                            <textarea class="form-control" name="allergies" rows="3"><?php echo $patient['allergies']; ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Antécédents personnels</label>
                            <textarea class="form-control" name="antecedents_personnels" rows="3"><?php echo $patient['antecedents_personnels']; ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Antécédents familiaux</label>
                            <textarea class="form-control" name="antecedents_familiaux" rows="3"><?php echo $patient['antecedents_familiaux']; ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Médicaments habituels</label>
                            <textarea class="form-control" name="medicaments_habituels" rows="3"><?php echo $patient['medicaments_habituels']; ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Habitudes (tabac, alcool, sport, etc.)</label>
                            <textarea class="form-control" name="habitudes" rows="3"><?php echo $patient['habitudes']; ?></textarea>
                        </div>
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
<!-- Liste des patients -->
<div class="card shadow-sm">
    <div class="card-header bg-white border-bottom">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Liste de mes patients
                </h6>
            </div>
            <div class="col-md-6">
                <form method="GET" class="d-flex">
                    <input type="text" class="form-control me-2" name="search" 
                           placeholder="Rechercher un patient..." value="<?php echo htmlspecialchars($search); ?>">
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
                        <th>Patient</th>
                        <th>Âge</th>
                        <th>Contact</th>
                        <th>Consultations</th>
                        <th>Dernière visite</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): 
                        $lastConsult = $patient['derniere_consultation'] ? 
                            date('d/m/Y', strtotime($patient['derniere_consultation'])) : 
                            'Jamais';
                        $daysSinceLast = $patient['derniere_consultation'] ? 
                            floor((time() - strtotime($patient['derniere_consultation'])) / (60 * 60 * 24)) : 
                            null;
                        $lastConsultClass = $daysSinceLast > 180 ? 'text-danger' : 
                                          ($daysSinceLast > 90 ? 'text-warning' : 'text-success');
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar me-3">
                                    <?php echo strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?php echo $patient['prenom'] . ' ' . $patient['nom']; ?></div>
                                    <small class="text-muted"><?php echo $patient['code_patient']; ?></small>
                                    <?php if ($patient['urgences_recentes'] > 0): ?>
                                    <div class="mt-1">
                                        <span class="badge bg-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <?php echo $patient['urgences_recentes']; ?> urgence(s)
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="fw-semibold"><?php echo $patient['age']; ?> ans</span><br>
                            <small class="text-muted"><?php echo $patient['sexe'] == 'M' ? 'Homme' : 'Femme'; ?></small>
                        </td>
                        <td>
                            <div><?php echo $patient['telephone']; ?></div>
                            <small class="text-muted"><?php echo $patient['ville'] ?? 'Non renseigné'; ?></small>
                        </td>
                        <td>
                            <div class="text-center">
                                <div class="fw-bold"><?php echo $patient['consultations_count']; ?></div>
                                <small class="text-muted">consultations</small>
                            </div>
                        </td>
                        <td>
                            <div class="<?php echo $lastConsultClass; ?> fw-semibold"><?php echo $lastConsult; ?></div>
                            <?php if ($daysSinceLast): ?>
                            <small class="text-muted">Il y a <?php echo $daysSinceLast; ?> jours</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($patient['prescriptions_actives'] > 0): ?>
                            <span class="badge bg-info"><?php echo $patient['prescriptions_actives']; ?> prescription(s) active(s)</span>
                            <?php else: ?>
                            <span class="badge bg-success">Suivi normal</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=view&id=<?php echo $patient['id']; ?>" 
                                   class="btn btn-outline-primary" title="Détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="consultations.php?action=add&patient_id=<?php echo $patient['id']; ?>" 
                                   class="btn btn-outline-success" title="Nouvelle consultation">
                                    <i class="fas fa-stethoscope"></i>
                                </a>
                                <a href="prescriptions.php?action=add&patient_id=<?php echo $patient['id']; ?>" 
                                   class="btn btn-outline-warning" title="Nouvelle prescription">
                                    <i class="fas fa-prescription"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($patients)): ?>
        <div class="text-center py-5">
            <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Aucun patient trouvé</h5>
            <p class="text-muted">Commencez par créer une consultation avec un patient</p>
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
                    Total: <?php echo count($patients); ?> patient(s)
                </small>
            </div>
            <div>
                <a href="patients.php?export=csv" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-file-excel me-1"></i>Exporter CSV
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Filtres -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filtrer les patients</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="GET" id="filterForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Ville</label>
                            <select class="form-select" name="ville">
                                <option value="">Toutes les villes</option>
                                <?php foreach ($villes as $v): ?>
                                <option value="<?php echo $v; ?>" <?php echo $ville == $v ? 'selected' : ''; ?>>
                                    <?php echo $v; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sexe</label>
                            <select class="form-select" name="sexe">
                                <option value="">Tous</option>
                                <option value="M" <?php echo $sexe == 'M' ? 'selected' : ''; ?>>Homme</option>
                                <option value="F" <?php echo $sexe == 'F' ? 'selected' : ''; ?>>Femme</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Âge minimum</label>
                            <input type="number" class="form-control" name="age_min" value="<?php echo $age_min; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Âge maximum</label>
                            <input type="number" class="form-control" name="age_max" value="<?php echo $age_max; ?>">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="urgence" id="urgenceCheck" 
                                       value="1" <?php echo $urgence ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="urgenceCheck">
                                    Patients avec consultations urgentes récentes (30 derniers jours)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="patients.php" class="btn btn-secondary">Réinitialiser</a>
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
});

// Exporter les données
function exportToCSV() {
    const table = document.querySelector('table');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = Array.from(cols).map(col => {
            let text = col.textContent.trim();
            // Supprimer les icônes et badges
            text = text.replace(/[^\x20-\x7E]/g, '');
            return `"${text}"`;
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    link.href = URL.createObjectURL(blob);
    link.download = 'patients_' + new Date().toISOString().slice(0, 10) + '.csv';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Voir les détails d'une pathologie
function viewPathologie(id) {
    fetch(`ajax/get_pathologie.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Afficher les détails dans un modal
                const modal = new bootstrap.Modal(document.getElementById('pathologieDetailsModal'));
                document.getElementById('pathologieDetails').innerHTML = `
                    <h5>${data.pathologie.nom}</h5>
                    <p><strong>Code CIM:</strong> ${data.pathologie.code_cim}</p>
                    <p><strong>Date diagnostic:</strong> ${data.pathologie.date_diagnostic}</p>
                    <p><strong>Gravité:</strong> ${data.pathologie.gravite}</p>
                    <p><strong>Statut:</strong> ${data.pathologie.statut}</p>
                    ${data.pathologie.traitement_actuel ? `<p><strong>Traitement:</strong> ${data.pathologie.traitement_actuel}</p>` : ''}
                    ${data.pathologie.notes ? `<p><strong>Notes:</strong> ${data.pathologie.notes}</p>` : ''}
                `;
                modal.show();
            } else {
                alert('Erreur: ' + data.error);
            }
        });
}

// Éditer une pathologie
function editPathologie(id) {
    // Rediriger vers la page d'édition
    window.location.href = `pathologies.php?action=edit&id=${id}&patient_id=<?php echo $id ?? ''; ?>`;
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

.avatar-lg {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background-color: #4361ee;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 24px;
    margin: 0 auto;
}

.info-item {
    border-bottom: 1px solid #f1f1f1;
    padding-bottom: 10px;
}

.info-item:last-child {
    border-bottom: none;
}
</style>