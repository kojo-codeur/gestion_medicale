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
                code_cim = ?, nom = ?, specialite_id = ?, description = ?, symptomes = ?, 
                causes = ?, traitement = ?, prevention = ?, gravite = ?, 
                contagieux = ?, chronique = ?, date_modification = NOW()
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
            $hasPatients = $pdo->prepare("SELECT COUNT(*) FROM patient_pathologie WHERE pathologie_id = ?")->execute([$id])->fetchColumn();
            
            if ($hasPatients > 0) {
                throw new Exception("Cette maladie est associée à $hasPatients patient(s). Impossible de supprimer.");
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
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-virus me-2"></i>Gestion des Maladies
        </h1>
        <p class="text-muted mb-0">Répertoire des pathologies médicales</p>
    </div>
    <div class="btn-toolbar">
        <?php if ($action === 'list'): ?>
        <div class="btn-group me-2">
            <a href="?action=add" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i>Nouvelle maladie
            </a>
            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" 
                    data-bs-toggle="dropdown">
                <span class="visually-hidden">Options</span>
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="#" onclick="importMaladies()">
                    <i class="fas fa-file-import me-2"></i>Importer
                </a>
                <a class="dropdown-item" href="export_maladies.php">
                    <i class="fas fa-file-export me-2"></i>Exporter
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="statistiques_maladies.php">
                    <i class="fas fa-chart-bar me-2"></i>Statistiques
                </a>
            </div>
        </div>
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
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-file-medical me-2"></i>
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
                            <label class="form-label">Code CIM</label>
                            <input type="text" class="form-control" name="code_cim" 
                                   value="<?php echo $maladie['code_cim'] ?? ''; ?>"
                                   placeholder="Ex: I10, J45, E11...">
                            <small class="text-muted">Classification Internationale des Maladies</small>
                        </div>
                        
                        <!-- Spécialité -->
                        <div class="col-md-6">
                            <label class="form-label">Spécialité concernée</label>
                            <select class="form-select" name="specialite_id">
                                <option value="">Sélectionner une spécialité</option>
                                <?php
                                $specialites = $pdo->query("SELECT * FROM specialites WHERE statut = 'active' ORDER BY nom")->fetchAll();
                                foreach ($specialites as $spec): ?>
                                <option value="<?php echo $spec['id']; ?>" 
                                    <?php echo ($maladie['specialite_id'] ?? '') == $spec['id'] ? 'selected' : ''; ?>>
                                    <?php echo $spec['nom']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Niveau de gravité</label>
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
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="contagieux" value="1" 
                                       id="contagieuxCheck" <?php echo ($maladie['contagieux'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="contagieuxCheck">
                                    Maladie contagieuse
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="chronique" value="1" 
                                       id="chroniqueCheck" <?php echo ($maladie['chronique'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="chroniqueCheck">
                                    Maladie chronique
                                </label>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo $maladie['description'] ?? ''; ?></textarea>
                            <small class="text-muted">Description générale de la maladie</small>
                        </div>
                        
                        <!-- Symptômes -->
                        <div class="col-md-6">
                            <label class="form-label">Symptômes principaux</label>
                            <textarea class="form-control" name="symptomes" rows="4"><?php echo $maladie['symptomes'] ?? ''; ?></textarea>
                            <small class="text-muted">Liste des symptômes caractéristiques</small>
                        </div>
                        
                        <!-- Causes -->
                        <div class="col-md-6">
                            <label class="form-label">Causes</label>
                            <textarea class="form-control" name="causes" rows="4"><?php echo $maladie['causes'] ?? ''; ?></textarea>
                            <small class="text-muted">Facteurs étiologiques, facteurs de risque</small>
                        </div>
                        
                        <!-- Traitement -->
                        <div class="col-md-6">
                            <label class="form-label">Traitement</label>
                            <textarea class="form-control" name="traitement" rows="4"><?php echo $maladie['traitement'] ?? ''; ?></textarea>
                            <small class="text-muted">Traitements médicamenteux, chirurgicaux, etc.</small>
                        </div>
                        
                        <!-- Prévention -->
                        <div class="col-md-6">
                            <label class="form-label">Prévention</label>
                            <textarea class="form-control" name="prevention" rows="4"><?php echo $maladie['prevention'] ?? ''; ?></textarea>
                            <small class="text-muted">Mesures préventives, vaccination, dépistage</small>
                        </div>
                        
                        <!-- Statistiques (pour l'édition) -->
                        <?php if ($action === 'edit' && $maladie): 
                            $patient_count = $pdo->prepare("SELECT COUNT(*) FROM patient_pathologie WHERE pathologie_id = ?")->execute([$id])->fetchColumn();
                            $consultation_count = $pdo->prepare("
                                SELECT COUNT(DISTINCT c.id) 
                                FROM consultations c 
                                JOIN patient_pathologie pp ON c.patient_id = pp.patient_id 
                                WHERE pp.pathologie_id = ?
                            ")->execute([$id])->fetchColumn();
                        ?>
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body p-3">
                                    <h6 class="card-title">
                                        <i class="fas fa-chart-bar me-2"></i>Statistiques
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Patients diagnostiqués:</strong> <?php echo $patient_count; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Consultations associées:</strong> <?php echo $consultation_count; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i>
                            <?php echo $action === 'add' ? 'Créer la maladie' : 'Enregistrer les modifications'; ?>
                        </button>
                        <button type="reset" class="btn btn-secondary ms-2">Réinitialiser</button>
                        <a href="maladies.php" class="btn btn-outline-secondary ms-2">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Liste des maladies -->
<div class="card shadow-sm">
    <div class="card-header bg-white border-bottom">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Répertoire des pathologies
                </h6>
            </div>
            <div class="col-md-6">
                <form method="GET" class="row g-2">
                    <div class="col">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Rechercher une maladie..." value="<?php echo $_GET['search'] ?? ''; ?>"
                               id="searchInput">
                    </div>
                    <div class="col-auto">
                        <select class="form-select" name="specialite" onchange="this.form.submit()">
                            <option value="">Toutes spécialités</option>
                            <?php
                            $specialites = $pdo->query("SELECT * FROM specialites WHERE statut = 'active' ORDER BY nom")->fetchAll();
                            foreach ($specialites as $spec): ?>
                            <option value="<?php echo $spec['id']; ?>" 
                                <?php echo ($_GET['specialite'] ?? '') == $spec['id'] ? 'selected' : ''; ?>>
                                <?php echo $spec['nom']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
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
                        <th>Code CIM</th>
                        <th>Nom de la maladie</th>
                        <th>Spécialité</th>
                        <th>Gravité</th>
                        <th>Caractéristiques</th>
                        <th>Patients</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Construire la requête avec filtres
                    $sql = "SELECT p.*, s.nom as specialite_nom,
                                   (SELECT COUNT(*) FROM patient_pathologie WHERE pathologie_id = p.id) as patient_count
                            FROM pathologies p
                            LEFT JOIN specialites s ON p.specialite_id = s.id
                            WHERE 1=1";
                    
                    $params = [];
                    
                    // Filtre recherche
                    if (!empty($_GET['search'])) {
                        $sql .= " AND (p.nom LIKE ? OR p.code_cim LIKE ? OR p.description LIKE ?)";
                        $searchTerm = "%{$_GET['search']}%";
                        $params = array_fill(0, 3, $searchTerm);
                    }
                    
                    // Filtre spécialité
                    if (!empty($_GET['specialite'])) {
                        $sql .= " AND p.specialite_id = ?";
                        $params[] = $_GET['specialite'];
                    }
                    
                    // Filtre gravité
                    if (!empty($_GET['gravite'])) {
                        $sql .= " AND p.gravite = ?";
                        $params[] = $_GET['gravite'];
                    }
                    
                    $sql .= " ORDER BY p.nom";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $maladies = $stmt->fetchAll();
                    
                    foreach ($maladies as $maladie): 
                        $graviteColors = [
                            'faible' => 'success',
                            'moderee' => 'warning',
                            'grave' => 'danger',
                            'tres_grave' => 'dark'
                        ];
                    ?>
                    <tr>
                        <td>
                            <?php if ($maladie['code_cim']): ?>
                            <span class="badge bg-info"><?php echo $maladie['code_cim']; ?></span>
                            <?php else: ?>
                            <span class="text-muted small">Non classé</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo $maladie['nom']; ?></div>
                            <?php if ($maladie['description']): ?>
                            <small class="text-muted" title="<?php echo htmlspecialchars($maladie['description']); ?>">
                                <?php echo substr($maladie['description'], 0, 60); ?>...
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($maladie['specialite_nom']): ?>
                            <span class="badge bg-primary"><?php echo $maladie['specialite_nom']; ?></span>
                            <?php else: ?>
                            <span class="text-muted small">Général</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $graviteColors[$maladie['gravite']] ?? 'secondary'; ?>">
                                <?php echo ucfirst($maladie['gravite']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="small">
                                <?php if ($maladie['contagieux']): ?>
                                <span class="badge bg-danger me-1">Contagieux</span>
                                <?php endif; ?>
                                <?php if ($maladie['chronique']): ?>
                                <span class="badge bg-warning">Chronique</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="fw-semibold"><?php echo $maladie['patient_count']; ?></span>
                            <small class="text-muted">patient(s)</small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=edit&id=<?php echo $maladie['id']; ?>" 
                                   class="btn btn-outline-primary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="maladie_details.php?id=<?php echo $maladie['id']; ?>" 
                                   class="btn btn-outline-info" title="Détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $maladie['id']; ?>)" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($maladies)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-virus fa-2x text-muted mb-3"></i>
                            <p class="text-muted">Aucune maladie trouvée</p>
                            <a href="?action=add" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus-circle me-1"></i>Ajouter une maladie
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
                <small class="text-muted">
                    Total: <?php echo count($maladies); ?> maladie(s) référencée(s)
                </small>
            </div>
            <div>
                <button class="btn btn-sm btn-outline-secondary" onclick="exportMaladies()">
                    <i class="fas fa-file-export me-1"></i>Exporter
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques globales -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition par gravité
                </h6>
            </div>
            <div class="card-body">
                <canvas id="graviteChart" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Top 5 des maladies les plus fréquentes
                </h6>
            </div>
            <div class="card-body">
                <canvas id="frequenceChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Confirmer la suppression
function confirmDelete(maladieId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette maladie ?')) {
        window.location.href = `?action=delete&id=${maladieId}`;
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

// Importer des maladies
function importMaladies() {
    alert('Fonction d\'importation à implémenter');
}

// Exporter des maladies
function exportMaladies() {
    const search = document.querySelector('input[name="search"]')?.value || '';
    const specialite = document.querySelector('select[name="specialite"]')?.value || '';
    
    window.location.href = `export_maladies.php?search=${encodeURIComponent(search)}&specialite=${encodeURIComponent(specialite)}`;
}

// Initialiser les graphiques
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($action === 'list'): ?>
    // Données pour le graphique de gravité
    const graviteData = {
        labels: ['Faible', 'Modérée', 'Grave', 'Très grave'],
        datasets: [{
            data: [
                <?php echo $pdo->query("SELECT COUNT(*) FROM pathologies WHERE gravite = 'faible'")->fetchColumn(); ?>,
                <?php echo $pdo->query("SELECT COUNT(*) FROM pathologies WHERE gravite = 'moderee'")->fetchColumn(); ?>,
                <?php echo $pdo->query("SELECT COUNT(*) FROM pathologies WHERE gravite = 'grave'")->fetchColumn(); ?>,
                <?php echo $pdo->query("SELECT COUNT(*) FROM pathologies WHERE gravite = 'tres_grave'")->fetchColumn(); ?>
            ],
            backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#1f2937']
        }]
    };
    
    // Données pour le graphique de fréquence
    const topMaladies = <?php 
        $top = $pdo->query("
            SELECT p.nom, COUNT(pp.id) as count 
            FROM pathologies p 
            LEFT JOIN patient_pathologie pp ON p.id = pp.pathologie_id 
            GROUP BY p.id 
            ORDER BY count DESC 
            LIMIT 5
        ")->fetchAll();
        
        $labels = [];
        $data = [];
        foreach ($top as $row) {
            $labels[] = "'" . addslashes($row['nom']) . "'";
            $data[] = $row['count'];
        }
        
        echo json_encode([
            'labels' => $labels,
            'data' => $data
        ]);
    ?>;
    
    const frequenceData = {
        labels: topMaladies.labels,
        datasets: [{
            label: 'Nombre de patients',
            data: topMaladies.data,
            backgroundColor: '#4361ee',
            borderColor: '#3a56d4',
            borderWidth: 1
        }]
    };
    
    // Initialiser le graphique de gravité
    const graviteCtx = document.getElementById('graviteChart')?.getContext('2d');
    if (graviteCtx) {
        new Chart(graviteCtx, {
            type: 'doughnut',
            data: graviteData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Initialiser le graphique de fréquence
    const frequenceCtx = document.getElementById('frequenceChart')?.getContext('2d');
    if (frequenceCtx) {
        new Chart(frequenceCtx, {
            type: 'bar',
            data: frequenceData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    // Initialiser les tooltips
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