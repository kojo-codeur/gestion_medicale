<?php
// assistant/patients.php
require_once '../config/database.php';
checkRole('assistant');

$title = 'Gestion des Patients';
$assistant_id = $_SESSION['user_id'];

require_once '../includes/header.php';

$pdo = Database::getInstance()->getConnection();


// Récupération sécurisée des paramètres GET
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?? 'list';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// DÉBOGAGE - À SUPPRIMER EN PRODUCTION
if ($action === 'edit' && !$id) {
    error_log("GET: action=edit mais id manquant ou invalide. URL : " . $_SERVER['REQUEST_URI']);
    $_SESSION['error'] = "ID patient manquant pour la modification.";
    header('Location: patients.php');
    exit();
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = sanitize($_POST);
    
    if ($action === 'add') {
        try {
            // Générer code patient automatique
            $code_patient = 'PAT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            $stmt = $pdo->prepare("
                INSERT INTO patients 
                (code_patient, nom, prenom, date_naissance, sexe, telephone, email, 
                 adresse, ville, code_postal, groupe_sanguin, antecedents_personnels, 
                 allergies, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                $data['groupe_sanguin'] ?? null,
                $data['antecedents_personnels'] ?? null,
                $data['allergies'] ?? null,
                $assistant_id
            ]);
            
            $_SESSION['success'] = "Patient ajouté avec succès (Code: $code_patient)";
            
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur: " . $e->getMessage();
        }
        
    } elseif ($action === 'edit' && $id) {
        try {
            $stmt = $pdo->prepare("
                UPDATE patients SET 
                nom = ?, prenom = ?, date_naissance = ?, sexe = ?, telephone = ?, email = ?,
                adresse = ?, ville = ?, code_postal = ?, groupe_sanguin = ?, 
                antecedents_personnels = ?, allergies = ?, poids = ?, taille = ?
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
                $data['groupe_sanguin'] ?? null,
                $data['antecedents_personnels'] ?? null,
                $data['allergies'] ?? null,
                $data['poids'] ?? null,
                $data['taille'] ?? null,
                $id
            ]);
            
            $_SESSION['success'] = "Patient modifié avec succès";
            header('Location: patients.php');
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur: " . $e->getMessage();
        }
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

<!-- Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <h1 class="h2 mb-0">
        <i class="fas fa-user-injured me-2"></i>Gestion des Patients
    </h1>
    <div class="btn-toolbar">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-user-plus me-1"></i>Nouveau patient
        </a>
        <?php else: ?>
        <a href="patients.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Retour à la liste
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Formulaire patient -->
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
                    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
                    $stmt->execute([$id]);
                    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$patient) {
                        echo '<div class="alert alert-danger">Patient non trouvé</div>';
                        require_once '../includes/footer.php';
                        exit();
                    }
                }
                ?>
                
                <form method="POST" id="patientForm">
                    <div class="row g-3">
                        <!-- Informations personnelles -->
                        <div class="col-md-6">
                            <label class="form-label">Nom *</label>
                            <input type="text" class="form-control" name="nom" 
                                   value="<?php echo htmlspecialchars($patient['nom'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prénom *</label>
                            <input type="text" class="form-control" name="prenom" 
                                   value="<?php echo htmlspecialchars($patient['prenom'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date de naissance *</label>
                            <input type="date" class="form-control" name="date_naissance" 
                                   value="<?php echo $patient['date_naissance'] ?? ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sexe *</label>
                            <select class="form-select" name="sexe" required>
                                <option value="">Sélectionner</option>
                                <option value="M" <?php echo ($patient['sexe'] ?? '') == 'M' ? 'selected' : ''; ?>>Masculin</option>
                                <option value="F" <?php echo ($patient['sexe'] ?? '') == 'F' ? 'selected' : ''; ?>>Féminin</option>
                            </select>
                        </div>
                        
                        <!-- Contact -->
                        <div class="col-md-6">
                            <label class="form-label">Téléphone *</label>
                            <input type="tel" class="form-control" name="telephone" 
                                   value="<?php echo htmlspecialchars($patient['telephone'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>">
                        </div>
                        
                        <!-- Adresse -->
                        <div class="col-12">
                            <label class="form-label">Adresse</label>
                            <input type="text" class="form-control" name="adresse" 
                                   value="<?php echo htmlspecialchars($patient['adresse'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Ville</label>
                            <input type="text" class="form-control" name="ville" 
                                   value="<?php echo htmlspecialchars($patient['ville'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Code Postal</label>
                            <input type="text" class="form-control" name="code_postal" 
                                   value="<?php echo htmlspecialchars($patient['code_postal'] ?? ''); ?>">
                        </div>
                        
                        <!-- Informations médicales -->
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
                        
                        <?php if ($action === 'edit'): ?>
                        <div class="col-md-3">
                            <label class="form-label">Poids (kg)</label>
                            <input type="number" step="0.1" class="form-control" name="poids" 
                                   value="<?php echo $patient['poids'] ?? ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Taille (cm)</label>
                            <input type="number" step="0.1" class="form-control" name="taille" 
                                   value="<?php echo $patient['taille'] ?? ''; ?>">
                        </div>
                        <?php endif; ?>
                        
                        <!-- Antécédents et allergies -->
                        <div class="col-md-6">
                            <label class="form-label">Antécédents personnels</label>
                            <textarea class="form-control" name="antecedents_personnels" rows="3"><?php echo htmlspecialchars($patient['antecedents_personnels'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Allergies connues</label>
                            <textarea class="form-control" name="allergies" rows="3"><?php echo htmlspecialchars($patient['allergies'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i>
                            <?php echo $action === 'add' ? 'Enregistrer' : 'Modifier'; ?>
                        </button>
                        <button type="reset" class="btn btn-secondary ms-2">Annuler</button>
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
                    Liste des patients
                </h6>
            </div>
            <div class="col-md-6">
                <form method="GET" class="d-flex">
                    <input type="text" class="form-control me-2" name="search" 
                           placeholder="Rechercher par nom, prénom ou téléphone..."
                           value="<?php echo $_GET['search'] ?? ''; ?>">
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
                    $search = $_GET['search'] ?? '';
                    $page = $_GET['page'] ?? 1;
                    $limit = 15;
                    $offset = ($page - 1) * $limit;
                    
                    $sql = "SELECT p.*, 
                                   TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as age,
                                   (SELECT MAX(date_consultation) FROM consultations WHERE patient_id = p.id) as derniere_consult
                            FROM patients p 
                            WHERE p.statut = 'actif'";
                    
                    $params = [];
                    
                    if ($search) {
                        $sql .= " AND (p.nom LIKE ? OR p.prenom LIKE ? OR p.telephone LIKE ? OR p.code_patient LIKE ?)";
                        $searchTerm = "%$search%";
                        $params = array_fill(0, 4, $searchTerm);
                    }
                    
                    $sql .= " ORDER BY p.date_enregistrement DESC LIMIT $limit OFFSET $offset";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $patients = $stmt->fetchAll();
                    
                    foreach ($patients as $patient): 
                        $age = $patient['age'];
                        $ageClass = $age < 18 ? 'text-info' : ($age > 60 ? 'text-warning' : '');
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
                            <span class="small"><?php echo formatDate($patient['derniere_consult'], 'd/m/Y'); ?></span>
                            <?php else: ?>
                            <span class="text-muted small">Jamais</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-success">Actif</span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=edit&id=<?php echo $patient['id']; ?>" 
                                   class="btn btn-outline-primary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="consultations.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-outline-success" title="Nouvelle consultation">
                                    <i class="fas fa-stethoscope"></i>
                                </a>
                                <a href="patient_details.php?id=<?php echo $patient['id']; ?>" 
                                   class="btn btn-outline-info" title="Détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-outline-warning" 
                                        onclick="archivePatient(<?php echo $patient['id']; ?>)" title="Archiver">
                                    <i class="fas fa-archive"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card-footer bg-white border-top">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <small class="text-muted">
                    Total: <?php echo count($patients); ?> patient(s)
                </small>
            </div>
            <div>
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" 
                   class="btn btn-sm btn-outline-secondary me-2">
                    <i class="fas fa-chevron-left me-1"></i>Précédent
                </a>
                <?php endif; ?>
                
                <?php
                // Calculer le nombre total de pages
                $countSql = "SELECT COUNT(*) FROM patients WHERE statut = 'actif'";
                if ($search) {
                    $countSql .= " AND (nom LIKE ? OR prenom LIKE ? OR telephone LIKE ? OR code_patient LIKE ?)";
                }
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $totalPatients = $countStmt->fetchColumn();
                $totalPages = ceil($totalPatients / $limit);
                
                if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" 
                   class="btn btn-sm btn-outline-secondary">
                    Suivant<i class="fas fa-chevron-right ms-1"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'archivage -->
<div class="modal fade" id="archiveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Archiver le patient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir archiver ce patient ?</p>
                <p class="text-muted small">Le patient sera marqué comme archivé mais les données seront conservées.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-warning" id="confirmArchive">Archiver</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

<script>
// Gestion de l'archivage
let patientToArchive = null;

function archivePatient(patientId) {
    patientToArchive = patientId;
    const modal = new bootstrap.Modal(document.getElementById('archiveModal'));
    modal.show();
}

document.getElementById('confirmArchive').addEventListener('click', function() {
    if (patientToArchive) {
        window.location.href = `ajax/archive_patient.php?id=${patientToArchive}`;
    }
});

// Recherche en temps réel
const searchInput = document.querySelector('input[name="search"]');
let searchTimeout;

searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 500);
});
</script>