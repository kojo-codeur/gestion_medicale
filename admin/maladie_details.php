<?php
// admin/maladies.php
require_once '../config/database.php';
checkRole('admin');

$title = 'Gestion des Maladies';
require_once '../includes/header.php';

// Traitement CRUD
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Niveaux de gravité
$niveaux_gravite = ['faible', 'moderee', 'grave', 'tres_grave'];

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = sanitize($_POST);
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("
                INSERT INTO pathologies 
                (code_cim, nom, specialite_id, description, symptomes, causes, 
                 traitement, prevention, gravite, contagieux, chronique) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['code_cim'] ?? null,
                $data['nom'],
                $data['specialite_id'] ?? null,
                $data['description'] ?? null,
                $data['symptomes'] ?? null,
                $data['causes'] ?? null,
                $data['traitement'] ?? null,
                $data['prevention'] ?? null,
                $data['gravite'] ?? 'moderee',
                $data['contagieux'] ?? 0,
                $data['chronique'] ?? 0
            ]);
            
            $maladieId = $pdo->lastInsertId();
            
            // Journaliser l'action
            logAction('CREATE', 'pathologies', $maladieId, "Création maladie: {$data['nom']}");
            
            header("Location: maladies.php?success=Maladie ajoutée avec succès");
            exit();
            
        } elseif ($action === 'edit' && $id) {
            $stmt = $pdo->prepare("
                UPDATE pathologies SET 
                code_cim = ?, nom = ?, specialite_id = ?, description = ?, 
                symptomes = ?, causes = ?, traitement = ?, prevention = ?, 
                gravite = ?, contagieux = ?, chronique = ?, date_modification = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['code_cim'] ?? null,
                $data['nom'],
                $data['specialite_id'] ?? null,
                $data['description'] ?? null,
                $data['symptomes'] ?? null,
                $data['causes'] ?? null,
                $data['traitement'] ?? null,
                $data['prevention'] ?? null,
                $data['gravite'],
                $data['contagieux'] ?? 0,
                $data['chronique'] ?? 0,
                $id
            ]);
            
            // Journaliser l'action
            logAction('UPDATE', 'pathologies', $id, "Modification maladie ID: $id");
            
            header("Location: maladies.php?success=Maladie modifiée avec succès");
            exit();
            
        } elseif ($action === 'delete' && $id) {
            // Vérifier s'il y a des patients associés
            $hasPatients = $pdo->prepare("
                SELECT COUNT(*) FROM patient_pathologie WHERE pathologie_id = ?
            ")->execute([$id])->fetchColumn();
            
            if ($hasPatients > 0) {
                header("Location: maladies.php?error=Impossible de supprimer : des patients sont associés à cette maladie");
                exit();
            }
            
            $pdo->prepare("DELETE FROM pathologies WHERE id = ?")->execute([$id]);
            
            // Journaliser l'action
            logAction('DELETE', 'pathologies', $id, "Suppression maladie ID: $id");
            
            header("Location: maladies.php?success=Maladie supprimée avec succès");
            exit();
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: maladies.php?action=$action&id=$id&error=" . urlencode($e->getMessage()));
        exit();
    }
}

// Récupérer les spécialités pour le select
$specialites = $pdo->query("SELECT id, nom FROM specialites WHERE statut = 'active' ORDER BY nom")->fetchAll();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-virus me-2"></i>Gestion des Maladies
        </h1>
        <p class="text-muted mb-0">Base de données des pathologies médicales</p>
    </div>
    <div class="btn-toolbar">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus-circle me-1"></i>Nouvelle maladie
        </a>
        <?php else: ?>
        <a href="maladies.php" class="btn btn-secondary">
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
                    <i class="fas fa-virus me-2"></i>
                    <?php echo $action === 'add' ? 'Nouvelle maladie' : 'Modifier la maladie'; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php
                $maladie = null;
                if ($action === 'edit' && $id) {
                    $maladie = $pdo->prepare("SELECT * FROM pathologies WHERE id = ?")->execute([$id])->fetch();
                    if (!$maladie) {
                        echo '<div class="alert alert-danger">Maladie non trouvée</div>';
                        require_once '../includes/footer.php';
                        exit();
                    }
                }
                ?>
                
                <form method="POST" id="maladieForm" novalidate>
                    <div class="row g-3">
                        <!-- Informations de base -->
                        <div class="col-md-6">
                            <label class="form-label required">Nom de la maladie</label>
                            <input type="text" class="form-control" name="nom" 
                                   value="<?php echo $maladie['nom'] ?? ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Code CIM-10</label>
                            <input type="text" class="form-control" name="code_cim" 
                                   value="<?php echo $maladie['code_cim'] ?? ''; ?>"
                                   placeholder="Ex: I10, J45, E11...">
                        </div>
                        
                        <!-- Spécialité -->
                        <div class="col-md-6">
                            <label class="form-label">Spécialité concernée</label>
                            <select class="form-select" name="specialite_id">
                                <option value="">Sélectionner une spécialité</option>
                                <?php foreach ($specialites as $spec): ?>
                                <option value="<?php echo $spec['id']; ?>" 
                                    <?php echo ($maladie['specialite_id'] ?? '') == $spec['id'] ? 'selected' : ''; ?>>
                                    <?php echo $spec['nom']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Niveau de gravité</label>
                            <select class="form-select" name="gravite" required>
                                <?php foreach ($niveaux_gravite as $niveau): ?>
                                <option value="<?php echo $niveau; ?>" 
                                    <?php echo ($maladie['gravite'] ?? 'moderee') == $niveau ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($niveau); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Caractéristiques -->
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="contagieux" 
                                       id="contagieux" value="1" 
                                       <?php echo ($maladie['contagieux'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="contagieux">Contagieux</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="chronique" 
                                       id="chronique" value="1" 
                                       <?php echo ($maladie['chronique'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="chronique">Maladie chronique</label>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo $maladie['description'] ?? ''; ?></textarea>
                        </div>
                        
                        <!-- Symptômes -->
                        <div class="col-12">
                            <label class="form-label">Symptômes</label>
                            <textarea class="form-control" name="symptomes" rows="3"><?php echo $maladie['symptomes'] ?? ''; ?></textarea>
                            <small class="text-muted">Symptômes principaux et signes cliniques</small>
                        </div>
                        
                        <!-- Causes -->
                        <div class="col-md-6">
                            <label class="form-label">Causes</label>
                            <textarea class="form-control" name="causes" rows="3"><?php echo $maladie['causes'] ?? ''; ?></textarea>
                        </div>
                        
                        <!-- Traitement -->
                        <div class="col-md-6">
                            <label class="form-label">Traitement</label>
                            <textarea class="form-control" name="traitement" rows="3"><?php echo $maladie['traitement'] ?? ''; ?></textarea>
                        </div>
                        
                        <!-- Prévention -->
                        <div class="col-12">
                            <label class="form-label">Prévention</label>
                            <textarea class="form-control" name="prevention" rows="3"><?php echo $maladie['prevention'] ?? ''; ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Boutons de soumission -->
                    <div class="mt-4 border-top pt-4">
                        <div class="d-flex justify-content-between">
                            <div>
                                <?php if ($action === 'edit'): ?>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $id; ?>)">
                                    <i class="fas fa-trash me-1"></i>Supprimer
                                </button>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="maladies.php" class="btn btn-secondary me-2">
                                    <i class="fas fa-times me-1"></i>Annuler
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    <?php echo $action === 'add' ? 'Enregistrer' : 'Mettre à jour'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Total maladies</h6>
                        <?php
                        $total_maladies = $pdo->query("SELECT COUNT(*) FROM pathologies")->fetchColumn();
                        ?>
                        <h2 class="mb-0"><?php echo $total_maladies; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-virus fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Maladies chroniques</h6>
                        <?php
                        $chroniques = $pdo->query("SELECT COUNT(*) FROM pathologies WHERE chronique = 1")->fetchColumn();
                        ?>
                        <h2 class="mb-0"><?php echo $chroniques; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-heartbeat fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Maladies contagieuses</h6>
                        <?php
                        $contagieuses = $pdo->query("SELECT COUNT(*) FROM pathologies WHERE contagieux = 1")->fetchColumn();
                        ?>
                        <h2 class="mb-0"><?php echo $contagieuses; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-biohazard fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Maladies graves</h6>
                        <?php
                        $graves = $pdo->query("SELECT COUNT(*) FROM pathologies WHERE gravite IN ('grave', 'tres_grave')")->fetchColumn();
                        ?>
                        <h2 class="mb-0"><?php echo $graves; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres et recherche -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Rechercher</label>
                <div class="input-group">
                    <input type="text" class="form-control" name="search" 
                           placeholder="Nom, code CIM, symptômes..." 
                           value="<?php echo $_GET['search'] ?? ''; ?>">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Gravité</label>
                <select class="form-select" name="gravite">
                    <option value="">Tous les niveaux</option>
                    <?php foreach ($niveaux_gravite as $niveau): ?>
                    <option value="<?php echo $niveau; ?>" 
                        <?php echo ($_GET['gravite'] ?? '') == $niveau ? 'selected' : ''; ?>>
                        <?php echo ucfirst($niveau); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Spécialité</label>
                <select class="form-select" name="specialite_id">
                    <option value="">Toutes spécialités</option>
                    <?php foreach ($specialites as $spec): ?>
                    <option value="<?php echo $spec['id']; ?>" 
                        <?php echo ($_GET['specialite_id'] ?? '') == $spec['id'] ? 'selected' : ''; ?>>
                        <?php echo $spec['nom']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select class="form-select" name="type">
                    <option value="">Tous</option>
                    <option value="chronique" <?php echo ($_GET['type'] ?? '') == 'chronique' ? 'selected' : ''; ?>>Chronique</option>
                    <option value="contagieux" <?php echo ($_GET['type'] ?? '') == 'contagieux' ? 'selected' : ''; ?>>Contagieux</option>
                </select>
            </div>
            
            <div class="col-md-12">
                <div class="d-flex justify-content-between">
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i>Filtrer
                        </button>
                        <a href="maladies.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-times me-1"></i>Réinitialiser
                        </a>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-success" onclick="exportMaladies()">
                            <i class="fas fa-file-excel me-1"></i>Exporter
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste des maladies -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Liste des maladies</h6>
        <span class="badge bg-primary"><?php 
            $count_sql = "SELECT COUNT(*) FROM pathologies WHERE 1=1";
            $params = [];
            // Compter selon les filtres...
            echo $pdo->prepare($count_sql)->execute($params)->fetchColumn(); 
        ?> résultats</span>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code CIM</th>
                        <th>Nom de la maladie</th>
                        <th>Spécialité</th>
                        <th>Symptômes principaux</th>
                        <th>Gravité</th>
                        <th>Caractéristiques</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Construire la requête avec filtres
                    $sql = "SELECT p.*, s.nom as specialite_nom FROM pathologies p 
                            LEFT JOIN specialites s ON p.specialite_id = s.id 
                            WHERE 1=1";
                    $params = [];
                    
                    $search = $_GET['search'] ?? '';
                    $gravite = $_GET['gravite'] ?? '';
                    $specialite_id = $_GET['specialite_id'] ?? '';
                    $type = $_GET['type'] ?? '';
                    
                    if ($search) {
                        $sql .= " AND (p.nom LIKE ? OR p.code_cim LIKE ? OR p.symptomes LIKE ?)";
                        $search_term = "%$search%";
                        $params = array_merge($params, [$search_term, $search_term, $search_term]);
                    }
                    
                    if ($gravite) {
                        $sql .= " AND p.gravite = ?";
                        $params[] = $gravite;
                    }
                    
                    if ($specialite_id) {
                        $sql .= " AND p.specialite_id = ?";
                        $params[] = $specialite_id;
                    }
                    
                    if ($type === 'chronique') {
                        $sql .= " AND p.chronique = 1";
                    } elseif ($type === 'contagieux') {
                        $sql .= " AND p.contagieux = 1";
                    }
                    
                    $sql .= " ORDER BY p.nom ASC";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $maladies = $stmt->fetchAll();
                    
                    foreach ($maladies as $mal): 
                        // Déterminer la couleur de la gravité
                        $gravite_colors = [
                            'faible' => 'success',
                            'moderee' => 'warning',
                            'grave' => 'danger',
                            'tres_grave' => 'dark'
                        ];
                        $gravite_color = $gravite_colors[$mal['gravite']] ?? 'secondary';
                    ?>
                    <tr>
                        <td>
                            <?php if ($mal['code_cim']): ?>
                            <span class="badge bg-info"><?php echo $mal['code_cim']; ?></span>
                            <?php else: ?>
                            <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo $mal['nom']; ?></div>
                            <?php if ($mal['description']): ?>
                            <small class="text-muted"><?php echo substr($mal['description'], 0, 50); ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $mal['specialite_nom'] ?? 'Général'; ?></td>
                        <td>
                            <?php if ($mal['symptomes']): ?>
                            <small><?php echo substr($mal['symptomes'], 0, 80); ?>...</small>
                            <?php else: ?>
                            <span class="text-muted">Non spécifié</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $gravite_color; ?>">
                                <?php echo ucfirst($mal['gravite']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <?php if ($mal['chronique']): ?>
                                <span class="badge bg-warning">Chronique</span>
                                <?php endif; ?>
                                <?php if ($mal['contagieux']): ?>
                                <span class="badge bg-danger">Contagieux</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=edit&id=<?php echo $mal['id']; ?>" 
                                   class="btn btn-outline-primary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-outline-info" 
                                        onclick="viewDetails(<?php echo $mal['id']; ?>)" title="Détails">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-outline-success" 
                                        onclick="viewPatients(<?php echo $mal['id']; ?>)" title="Patients affectés">
                                    <i class="fas fa-user-injured"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $mal['id']; ?>)" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($maladies)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-virus fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">Aucune maladie trouvée</h6>
                                <p class="text-muted small">Utilisez le formulaire de recherche ou ajoutez une nouvelle maladie</p>
                                <a href="?action=add" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i>Ajouter une maladie
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if (count($maladies) > 0): ?>
    <div class="card-footer bg-white border-top">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <small class="text-muted">
                    Affichage de <?php echo count($maladies); ?> maladie(s)
                </small>
            </div>
            <div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item disabled">
                            <a class="page-link" href="#">Précédent</a>
                        </li>
                        <li class="page-item active">
                            <a class="page-link" href="#">1</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">2</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">3</a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#">Suivant</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Détails -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de la maladie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Chargé via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Modal Patients -->
<div class="modal fade" id="patientsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Patients affectés</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="patientsContent">
                <!-- Chargé via AJAX -->
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

<script>
// Fonctions pour les maladies
function viewDetails(maladieId) {
    fetch(`ajax/get_maladie_details.php?id=${maladieId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('detailsContent').innerHTML = data;
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        });
}

function viewPatients(maladieId) {
    fetch(`ajax/get_maladie_patients.php?id=${maladieId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('patientsContent').innerHTML = data;
            new bootstrap.Modal(document.getElementById('patientsModal')).show();
        });
}

function confirmDelete(maladieId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette maladie ?')) {
        window.location.href = `?action=delete&id=${maladieId}`;
    }
}

function exportMaladies() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = `export_maladies.php?${params.toString()}`;
}
</script>