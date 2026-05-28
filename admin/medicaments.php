<?php
// admin/medicaments.php - Version corrigée
require_once '../config/database.php';
checkRole('admin');

$title = 'Gestion des Médicaments';
require_once '../includes/header.php';

// Vérifier si la table medicaments existe
$table_exists = false;
try {
    $result = $pdo->query("SELECT 1 FROM medicaments LIMIT 1");
    $table_exists = true;
} catch (PDOException $e) {
    $table_exists = false;
}

// Types de médicaments
$types_medicament = [
    'comprime' => 'Comprimé',
    'gelule' => 'Gélule',
    'sirop' => 'Sirop',
    'injectable' => 'Injectable',
    'pommade' => 'Pommade',
    'creme' => 'Crème',
    'suppositoire' => 'Suppositoire',
    'collyre' => 'Collyre',
    'spray' => 'Spray',
    'poudre' => 'Poudre',
    'autre' => 'Autre'
];

// Classes thérapeutiques
$classes_therapeutiques = [
    'Analgésique' => 'Analgésique',
    'Antibiotique' => 'Antibiotique',
    'Anti-inflammatoire' => 'Anti-inflammatoire',
    'Antihypertenseur' => 'Antihypertenseur',
    'Antidiabétique' => 'Antidiabétique',
    'Antidépresseur' => 'Antidépresseur',
    'Anxiolytique' => 'Anxiolytique',
    'Hypolipémiant' => 'Hypolipémiant',
    'Diurétique' => 'Diurétique',
    'Antihistaminique' => 'Antihistaminique',
    'Bronchodilatateur' => 'Bronchodilatateur',
    'Anticoagulant' => 'Anticoagulant',
    'Vitamine' => 'Vitamine',
    'Autre' => 'Autre'
];

// Traitement CRUD
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Fonction utilitaire pour logger les actions
function logAction($action, $table, $recordId, $description = '') {
    global $pdo, $_SESSION;
    try {
        $pdo->prepare("
            INSERT INTO audit_logs 
            (user_id, action, table_name, record_id, description, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $_SESSION['user_id'] ?? 1,
            $action,
            $table,
            $recordId,
            $description,
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch (Exception $e) {
        // Ignorer les erreurs de log
    }
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = sanitize($_POST);
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("
                INSERT INTO medicaments 
                (code_cip, nom_commercial, nom_generique, laboratoire, forme, dosage, 
                 classe_therapeutique, indications, contre_indications, effets_secondaires, 
                 posologie, precautions, interactions, conditionnement, stock_actuel, 
                 stock_minimum, prix_unitaire, remboursement, statut) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['code_cip'] ?? null,
                $data['nom_commercial'],
                $data['nom_generique'] ?? null,
                $data['laboratoire'] ?? null,
                $data['forme'],
                $data['dosage'] ?? null,
                $data['classe_therapeutique'] ?? null,
                $data['indications'] ?? null,
                $data['contre_indications'] ?? null,
                $data['effets_secondaires'] ?? null,
                $data['posologie'] ?? null,
                $data['precautions'] ?? null,
                $data['interactions'] ?? null,
                $data['conditionnement'] ?? null,
                $data['stock_actuel'] ?? 0,
                $data['stock_minimum'] ?? 10,
                $data['prix_unitaire'] ?? 0,
                $data['remboursement'] ?? 0,
                'actif'
            ]);
            
            $medicamentId = $pdo->lastInsertId();
            
            // Journaliser l'action
            logAction('CREATE', 'medicaments', $medicamentId, "Création médicament: {$data['nom_commercial']}");
            
            header("Location: medicaments.php?success=Médicament ajouté avec succès");
            exit();
            
        } elseif ($action === 'edit' && $id) {
            $stmt = $pdo->prepare("
                UPDATE medicaments SET 
                code_cip = ?, nom_commercial = ?, nom_generique = ?, laboratoire = ?, 
                forme = ?, dosage = ?, classe_therapeutique = ?, indications = ?, 
                contre_indications = ?, effets_secondaires = ?, posologie = ?, 
                precautions = ?, interactions = ?, conditionnement = ?, stock_actuel = ?, 
                stock_minimum = ?, prix_unitaire = ?, remboursement = ?, statut = ?, 
                updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['code_cip'] ?? null,
                $data['nom_commercial'],
                $data['nom_generique'] ?? null,
                $data['laboratoire'] ?? null,
                $data['forme'],
                $data['dosage'] ?? null,
                $data['classe_therapeutique'] ?? null,
                $data['indications'] ?? null,
                $data['contre_indications'] ?? null,
                $data['effets_secondaires'] ?? null,
                $data['posologie'] ?? null,
                $data['precautions'] ?? null,
                $data['interactions'] ?? null,
                $data['conditionnement'] ?? null,
                $data['stock_actuel'],
                $data['stock_minimum'],
                $data['prix_unitaire'],
                $data['remboursement'],
                $data['statut'],
                $id
            ]);
            
            // Journaliser l'action
            logAction('UPDATE', 'medicaments', $id, "Modification médicament ID: $id");
            
            //header("Location: medicaments.php?success=Médicament modifié avec succès");
            //exit();
            
        } elseif ($action === 'delete' && $id) {
            // Vérifier s'il y a des prescriptions associées
            try {
                $hasPrescriptions = $pdo->prepare("
                    SELECT COUNT(*) FROM prescriptions 
                    WHERE JSON_CONTAINS(medicaments, ?)
                ");
                $hasPrescriptions->execute([json_encode(['medicament_id' => $id])]);
                $prescriptionCount = $hasPrescriptions->fetchColumn();
            } catch (Exception $e) {
                $prescriptionCount = 0;
            }
            
            if ($prescriptionCount > 0) {
                // Désactiver au lieu de supprimer
                $pdo->prepare("UPDATE medicaments SET statut = 'inactif' WHERE id = ?")->execute([$id]);
                $message = "Le médicament a été désactivé (prescriptions associées)";
            } else {
                $pdo->prepare("DELETE FROM medicaments WHERE id = ?")->execute([$id]);
                $message = "Médicament supprimé avec succès";
            }
            
            // Journaliser l'action
            logAction('DELETE', 'medicaments', $id, "Suppression médicament ID: $id");
            
            header("Location: medicaments.php?success=" . urlencode($message));
            exit();
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: medicaments.php?action=$action&id=$id&error=" . urlencode($e->getMessage()));
        exit();
    }
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-pills me-2"></i>Gestion des Médicaments
        </h1>
        <p class="text-muted mb-0">Base de données des médicaments</p>
    </div>
    <div class="btn-toolbar">
        <?php if ($action === 'list'): ?>
        <div class="btn-group me-2">
            <a href="?action=add" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i>Nouveau médicament
            </a>
            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" 
                    data-bs-toggle="dropdown">
                <span class="visually-hidden">Options</span>
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="#" onclick="importMedicaments()">
                    <i class="fas fa-file-import me-2"></i>Importer
                </a>
                <a class="dropdown-item" href="#" onclick="exportMedicaments()">
                    <i class="fas fa-file-export me-2"></i>Exporter
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="inventaire.php">
                    <i class="fas fa-boxes me-2"></i>Inventaire
                </a>
                <a class="dropdown-item" href="#" onclick="showStockAlerts()">
                    <i class="fas fa-exclamation-triangle me-2"></i>Alertes stock
                </a>
            </div>
        </div>
        <?php else: ?>
        <a href="medicaments.php" class="btn btn-secondary">
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

<!-- Avertissement si table n'existe pas -->
<?php if (!$table_exists): ?>
<div class="alert alert-warning">
    <h6><i class="fas fa-exclamation-triangle me-2"></i>Table non initialisée</h6>
    <p>La table des médicaments n'existe pas encore. Cliquez sur le bouton ci-dessous pour l'initialiser.</p>
    <button class="btn btn-warning btn-sm" onclick="initMedicamentsTable()">
        <i class="fas fa-database me-1"></i>Initialiser la table
    </button>
</div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Formulaire Ajout/Modification -->
<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-prescription me-2"></i>
                    <?php echo $action === 'add' ? 'Nouveau médicament' : 'Modifier le médicament'; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php
                $medicament = null;
                if ($action === 'edit' && $id && $table_exists) {
                    // CORRECTION: Séparer le prepare() et l'execute()
                    $stmt = $pdo->prepare("SELECT * FROM medicaments WHERE id = ?");
                    $stmt->execute([$id]);
                    $medicament = $stmt->fetch();
                    
                    if (!$medicament) {
                        echo '<div class="alert alert-danger">Médicament non trouvé</div>';
                        require_once '../includes/footer.php';
                        exit();
                    }
                }
                ?>
                
                <form method="POST" id="medicamentForm" novalidate>
                    <!-- Onglets -->
                    <ul class="nav nav-tabs mb-4" id="medicamentTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="info-tab" data-bs-toggle="tab" 
                                    data-bs-target="#info" type="button">Informations générales</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="medical-tab" data-bs-toggle="tab" 
                                    data-bs-target="#medical" type="button">Informations médicales</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="stock-tab" data-bs-toggle="tab" 
                                    data-bs-target="#stock" type="button">Stock et prix</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="medicamentTabsContent">
                        <!-- Onglet Informations générales -->
                        <div class="tab-pane fade show active" id="info" role="tabpanel">
                            <div class="row g-3">
                                <!-- Informations de base -->
                                <div class="col-md-6">
                                    <label class="form-label required">Nom commercial *</label>
                                    <input type="text" class="form-control" name="nom_commercial" 
                                           value="<?php echo htmlspecialchars($medicament['nom_commercial'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nom générique</label>
                                    <input type="text" class="form-control" name="nom_generique" 
                                           value="<?php echo htmlspecialchars($medicament['nom_generique'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Code CIP</label>
                                    <input type="text" class="form-control" name="code_cip" 
                                           value="<?php echo htmlspecialchars($medicament['code_cip'] ?? ''); ?>"
                                           placeholder="Code Identifiant de Présentation">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Laboratoire</label>
                                    <input type="text" class="form-control" name="laboratoire" 
                                           value="<?php echo htmlspecialchars($medicament['laboratoire'] ?? ''); ?>">
                                </div>
                                
                                <!-- Forme et dosage -->
                                <div class="col-md-6">
                                    <label class="form-label required">Forme pharmaceutique *</label>
                                    <select class="form-select" name="forme" required>
                                        <option value="">Sélectionner</option>
                                        <?php foreach ($types_medicament as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" 
                                            <?php echo ($medicament['forme'] ?? '') == $value ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Dosage</label>
                                    <input type="text" class="form-control" name="dosage" 
                                           value="<?php echo htmlspecialchars($medicament['dosage'] ?? ''); ?>"
                                           placeholder="Ex: 500mg, 20mg/ml, 1%...">
                                </div>
                                
                                <!-- Classe thérapeutique -->
                                <div class="col-md-6">
                                    <label class="form-label">Classe thérapeutique</label>
                                    <select class="form-select" name="classe_therapeutique">
                                        <option value="">Sélectionner</option>
                                        <?php foreach ($classes_therapeutiques as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" 
                                            <?php echo ($medicament['classe_therapeutique'] ?? '') == $value ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Conditionnement</label>
                                    <input type="text" class="form-control" name="conditionnement" 
                                           value="<?php echo htmlspecialchars($medicament['conditionnement'] ?? ''); ?>"
                                           placeholder="Ex: Boîte de 30 comprimés">
                                </div>
                                
                                <!-- Statut (pour l'édition) -->
                                <?php if ($action === 'edit'): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Statut *</label>
                                    <select class="form-select" name="statut" required>
                                        <option value="actif" <?php echo ($medicament['statut'] ?? '') == 'actif' ? 'selected' : ''; ?>>Actif</option>
                                        <option value="inactif" <?php echo ($medicament['statut'] ?? '') == 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                                        <option value="rupture" <?php echo ($medicament['statut'] ?? '') == 'rupture' ? 'selected' : ''; ?>>Rupture de stock</option>
                                        <option value="retire" <?php echo ($medicament['statut'] ?? '') == 'retire' ? 'selected' : ''; ?>>Retiré du marché</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Onglet Informations médicales -->
                        <div class="tab-pane fade" id="medical" role="tabpanel">
                            <div class="row g-3">
                                <!-- Indications -->
                                <div class="col-12">
                                    <label class="form-label">Indications thérapeutiques</label>
                                    <textarea class="form-control" name="indications" rows="3" 
                                              placeholder="Maladies et conditions pour lesquelles le médicament est indiqué"><?php echo htmlspecialchars($medicament['indications'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Contre-indications -->
                                <div class="col-12">
                                    <label class="form-label">Contre-indications</label>
                                    <textarea class="form-control" name="contre_indications" rows="3" 
                                              placeholder="Situations où le médicament ne doit pas être utilisé"><?php echo htmlspecialchars($medicament['contre_indications'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Effets secondaires -->
                                <div class="col-12">
                                    <label class="form-label">Effets secondaires</label>
                                    <textarea class="form-control" name="effets_secondaires" rows="3" 
                                              placeholder="Effets indésirables possibles"><?php echo htmlspecialchars($medicament['effets_secondaires'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Posologie -->
                                <div class="col-12">
                                    <label class="form-label">Posologie recommandée</label>
                                    <textarea class="form-control" name="posologie" rows="3" 
                                              placeholder="Doses usuelles pour adultes et enfants"><?php echo htmlspecialchars($medicament['posologie'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Précautions d'emploi -->
                                <div class="col-12">
                                    <label class="form-label">Précautions d'emploi</label>
                                    <textarea class="form-control" name="precautions" rows="3" 
                                              placeholder="Précautions particulières, conduite automobile, etc."><?php echo htmlspecialchars($medicament['precautions'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Interactions médicamenteuses -->
                                <div class="col-12">
                                    <label class="form-label">Interactions médicamenteuses</label>
                                    <textarea class="form-control" name="interactions" rows="3" 
                                              placeholder="Interactions avec d'autres médicaments"><?php echo htmlspecialchars($medicament['interactions'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Onglet Stock et prix -->
                        <div class="tab-pane fade" id="stock" role="tabpanel">
                            <div class="row g-3">
                                <!-- Stock -->
                                <div class="col-md-4">
                                    <label class="form-label required">Stock actuel *</label>
                                    <input type="number" class="form-control" name="stock_actuel" 
                                           value="<?php echo $medicament['stock_actuel'] ?? 0; ?>" min="0" required>
                                    <small class="text-muted">Quantité en stock</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required">Stock minimum *</label>
                                    <input type="number" class="form-control" name="stock_minimum" 
                                           value="<?php echo $medicament['stock_minimum'] ?? 10; ?>" min="0" required>
                                    <small class="text-muted">Seuil d'alerte</small>
                                </div>
                                
                                <!-- Prix et remboursement -->
                                <div class="col-md-4">
                                    <label class="form-label required">Prix unitaire (€) *</label>
                                    <input type="number" step="0.01" class="form-control" name="prix_unitaire" 
                                           value="<?php echo $medicament['prix_unitaire'] ?? 0; ?>" min="0" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Taux de remboursement (%)</label>
                                    <input type="number" class="form-control" name="remboursement" 
                                           value="<?php echo $medicament['remboursement'] ?? 0; ?>" min="0" max="100">
                                    <small class="text-muted">Pourcentage remboursé par la sécurité sociale</small>
                                </div>
                                
                                <!-- Alertes stock -->
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Gestion des stocks</strong>
                                        <div class="mt-2">
                                            <?php 
                                            $stock_actuel = $medicament['stock_actuel'] ?? 0;
                                            $stock_minimum = $medicament['stock_minimum'] ?? 10;
                                            $stock_pourcentage = $stock_minimum > 0 ? ($stock_actuel / $stock_minimum) * 100 : 0;
                                            
                                            $stock_class = 'success';
                                            $stock_message = 'Stock suffisant';
                                            
                                            if ($stock_actuel <= 0) {
                                                $stock_class = 'danger';
                                                $stock_message = 'Rupture de stock';
                                            } elseif ($stock_actuel <= $stock_minimum * 0.3) {
                                                $stock_class = 'danger';
                                                $stock_message = 'Stock très faible';
                                            } elseif ($stock_actuel <= $stock_minimum) {
                                                $stock_class = 'warning';
                                                $stock_message = 'Stock faible';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $stock_class; ?>"><?php echo $stock_message; ?></span>
                                            <span class="ms-2">Actuel: <?php echo $stock_actuel; ?> | Minimum: <?php echo $stock_minimum; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                                <a href="medicaments.php" class="btn btn-secondary me-2">
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

<?php elseif ($action === 'list' && $table_exists): ?>
<!-- Statistiques -->
<div class="row mb-4">
    <?php 
    // Récupérer les statistiques avec gestion d'erreur
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM medicaments WHERE statut = 'actif'");
        $total_actifs = $stmt->fetchColumn();
    } catch (Exception $e) {
        $total_actifs = 0;
    }
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM medicaments WHERE statut = 'rupture'");
        $total_rupture = $stmt->fetchColumn();
    } catch (Exception $e) {
        $total_rupture = 0;
    }
    
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM medicaments 
            WHERE statut = 'actif' 
            AND stock_actuel <= stock_minimum 
            AND stock_actuel > 0
        ");
        $stock_faible = $stmt->fetchColumn();
    } catch (Exception $e) {
        $stock_faible = 0;
    }
    
    try {
        $stmt = $pdo->query("SELECT SUM(stock_actuel) FROM medicaments");
        $total_stock = $stmt->fetchColumn();
    } catch (Exception $e) {
        $total_stock = 0;
    }
    ?>
    
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Médicaments actifs</h6>
                        <h2 class="mb-0"><?php echo $total_actifs; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-pills fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">En rupture</h6>
                        <h2 class="mb-0"><?php echo $total_rupture; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
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
                        <h6 class="text-white-50">Stock faible</h6>
                        <h2 class="mb-0"><?php echo $stock_faible; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-box fa-2x opacity-50"></i>
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
                        <h6 class="text-white-50">Inventaire total</h6>
                        <h2 class="mb-0"><?php echo number_format($total_stock); ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-boxes fa-2x opacity-50"></i>
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
                           placeholder="Nom commercial, générique, code CIP..." 
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Statut</label>
                <select class="form-select" name="statut">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?php echo ($_GET['statut'] ?? '') == 'actif' ? 'selected' : ''; ?>>Actif</option>
                    <option value="inactif" <?php echo ($_GET['statut'] ?? '') == 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                    <option value="rupture" <?php echo ($_GET['statut'] ?? '') == 'rupture' ? 'selected' : ''; ?>>Rupture</option>
                    <option value="retire" <?php echo ($_GET['statut'] ?? '') == 'retire' ? 'selected' : ''; ?>>Retiré</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Forme</label>
                <select class="form-select" name="forme">
                    <option value="">Toutes les formes</option>
                    <?php foreach ($types_medicament as $value => $label): ?>
                    <option value="<?php echo $value; ?>" 
                        <?php echo ($_GET['forme'] ?? '') == $value ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Stock</label>
                <select class="form-select" name="stock">
                    <option value="">Tous</option>
                    <option value="faible" <?php echo ($_GET['stock'] ?? '') == 'faible' ? 'selected' : ''; ?>>Stock faible</option>
                    <option value="rupture" <?php echo ($_GET['stock'] ?? '') == 'rupture' ? 'selected' : ''; ?>>Rupture</option>
                </select>
            </div>
            
            <div class="col-md-12">
                <div class="d-flex justify-content-between">
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i>Filtrer
                        </button>
                        <a href="medicaments.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-times me-1"></i>Réinitialiser
                        </a>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-success" onclick="exportMedicaments()">
                            <i class="fas fa-file-excel me-1"></i>Exporter
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste des médicaments -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Liste des médicaments</h6>
        <span class="badge bg-primary" id="medicamentCount">0 résultats</span>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Médicament</th>
                        <th>Forme/Dosage</th>
                        <th>Laboratoire</th>
                        <th>Stock</th>
                        <th>Prix</th>
                        <th>Remb.</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="medicamentsTable">
                    <?php
                    // Construire la requête avec filtres
                    $sql = "SELECT * FROM medicaments WHERE 1=1";
                    $params = [];
                    
                    $search = $_GET['search'] ?? '';
                    $statut = $_GET['statut'] ?? '';
                    $forme = $_GET['forme'] ?? '';
                    $stock = $_GET['stock'] ?? '';
                    
                    if ($search) {
                        $sql .= " AND (nom_commercial LIKE ? OR nom_generique LIKE ? OR code_cip LIKE ?)";
                        $search_term = "%$search%";
                        $params = array_merge($params, [$search_term, $search_term, $search_term]);
                    }
                    
                    if ($statut) {
                        $sql .= " AND statut = ?";
                        $params[] = $statut;
                    }
                    
                    if ($forme) {
                        $sql .= " AND forme = ?";
                        $params[] = $forme;
                    }
                    
                    if ($stock === 'faible') {
                        $sql .= " AND stock_actuel <= stock_minimum AND stock_actuel > 0";
                    } elseif ($stock === 'rupture') {
                        $sql .= " AND stock_actuel = 0";
                    }
                    
                    $sql .= " ORDER BY nom_commercial ASC";
                    
                    try {
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $medicaments = $stmt->fetchAll();
                    } catch (Exception $e) {
                        $medicaments = [];
                    }
                    
                    foreach ($medicaments as $med): 
                        // Déterminer la couleur du statut stock
                        $stock_class = 'success';
                        if ($med['stock_actuel'] <= 0) {
                            $stock_class = 'danger';
                        } elseif ($med['stock_actuel'] <= $med['stock_minimum']) {
                            $stock_class = 'warning';
                        }
                        
                        // Déterminer la couleur du statut général
                        $status_class = 'success';
                        if ($med['statut'] === 'inactif') $status_class = 'secondary';
                        if ($med['statut'] === 'rupture') $status_class = 'danger';
                        if ($med['statut'] === 'retire') $status_class = 'dark';
                    ?>
                    <tr>
                        <td>
                            <span class="badge bg-info"><?php echo htmlspecialchars($med['code_cip'] ?? 'N/A'); ?></span>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($med['nom_commercial']); ?></div>
                            <?php if ($med['nom_generique']): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($med['nom_generique']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?php echo $types_medicament[$med['forme']] ?? ucfirst($med['forme']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($med['dosage'] ?? 'N/A'); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($med['laboratoire'] ?? 'N/A'); ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                    <?php 
                                    $stock_percent = $med['stock_minimum'] > 0 ? 
                                        min(100, ($med['stock_actuel'] / $med['stock_minimum']) * 100) : 0;
                                    ?>
                                    <div class="progress-bar bg-<?php echo $stock_class; ?>" 
                                         style="width: <?php echo $stock_percent; ?>%"></div>
                                </div>
                                <span class="badge bg-<?php echo $stock_class; ?>">
                                    <?php echo $med['stock_actuel']; ?>
                                </span>
                            </div>
                            <small class="text-muted">Min: <?php echo $med['stock_minimum']; ?></small>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo number_format($med['prix_unitaire'], 2); ?>€</div>
                        </td>
                        <td>
                            <?php if ($med['remboursement'] > 0): ?>
                            <span class="badge bg-success"><?php echo $med['remboursement']; ?>%</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Non</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $status_class; ?>">
                                <?php echo ucfirst($med['statut']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=edit&id=<?php echo $med['id']; ?>" 
                                   class="btn btn-outline-primary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-outline-info" 
                                        onclick="viewDetails(<?php echo $med['id']; ?>)" title="Détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-outline-warning" 
                                        onclick="adjustStock(<?php echo $med['id']; ?>)" title="Ajuster stock">
                                    <i class="fas fa-box"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $med['id']; ?>)" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($medicaments)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-pills fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">Aucun médicament trouvé</h6>
                                <p class="text-muted small">Utilisez le formulaire de recherche ou ajoutez un nouveau médicament</p>
                                <a href="?action=add" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i>Ajouter un médicament
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
    <?php if (count($medicaments) > 0): ?>
    <div class="card-footer bg-white border-top">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <small class="text-muted">
                    Affichage de <?php echo count($medicaments); ?> médicament(s)
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
                <h5 class="modal-title">Détails du médicament</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Chargé via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajuster Stock -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajuster le stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="stockForm">
                    <input type="hidden" id="stock_medicament_id">
                    <div class="mb-3">
                        <label class="form-label">Type d'opération</label>
                        <select class="form-select" id="stock_operation">
                            <option value="add">Ajouter au stock</option>
                            <option value="remove">Retirer du stock</option>
                            <option value="set">Définir la quantité</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantité</label>
                        <input type="number" class="form-control" id="stock_quantity" min="1" value="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motif</label>
                        <textarea class="form-control" id="stock_reason" rows="2" 
                                  placeholder="Raison de l'ajustement..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="saveStockAdjustment()">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

<script>
// Mettre à jour le compteur
document.addEventListener('DOMContentLoaded', function() {
    const medicamentCount = <?php echo count($medicaments); ?>;
    document.getElementById('medicamentCount').textContent = medicamentCount + ' résultat' + (medicamentCount !== 1 ? 's' : '');
});

// Initialiser les onglets
document.addEventListener('DOMContentLoaded', function() {
    const tabEls = document.querySelectorAll('#medicamentTabs button');
    tabEls.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.getAttribute('data-bs-target');
            const tabPane = document.querySelector(tabId);
            
            // Sauvegarder l'onglet actif
            localStorage.setItem('activeTab', tabId);
        });
    });
    
    // Restaurer l'onglet actif
    const activeTab = localStorage.getItem('activeTab');
    if (activeTab) {
        const tab = document.querySelector(`#medicamentTabs button[data-bs-target="${activeTab}"]`);
        if (tab) {
            new bootstrap.Tab(tab).show();
        }
    }
});

// Fonctions pour les médicaments
function viewDetails(medicamentId) {
    showToast('Chargement des détails...', 'info');
    
    fetch(`../ajax/get_medicament_details.php?id=${medicamentId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau: ' + response.status);
            }
            return response.text();
        })
        .then(data => {
            document.getElementById('detailsContent').innerHTML = data;
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        })
        .catch(error => {
            console.error('Erreur:', error);
            document.getElementById('detailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Erreur de chargement</h6>
                    <p>Impossible de charger les détails du médicament.</p>
                    <p class="small text-muted">${error.message}</p>
                </div>
            `;
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        });
}

function adjustStock(medicamentId) {
    document.getElementById('stock_medicament_id').value = medicamentId;
    document.getElementById('stock_quantity').value = 1;
    document.getElementById('stock_reason').value = '';
    new bootstrap.Modal(document.getElementById('stockModal')).show();
}

function saveStockAdjustment() {
    const medicamentId = document.getElementById('stock_medicament_id').value;
    const operation = document.getElementById('stock_operation').value;
    const quantity = parseInt(document.getElementById('stock_quantity').value);
    const reason = document.getElementById('stock_reason').value;
    
    if (!quantity || quantity <= 0) {
        showToast('Veuillez entrer une quantité valide', 'warning');
        return;
    }

    showToast('Mise à jour du stock...', 'info');

    fetch('../ajax/adjust_stock.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ 
            medicament_id: medicamentId, 
            operation: operation, 
            quantity: quantity, 
            reason: reason 
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('stockModal')).hide();
            showToast('Stock mis à jour avec succès', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Erreur: ' + data.error, 'danger');
        }
    })
    .catch(error => {
        showToast('Erreur de connexion: ' + error.message, 'danger');
    });
}

function confirmDelete(medicamentId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce médicament ?')) {
        window.location.href = `?action=delete&id=${medicamentId}`;
    }
}

function exportMedicaments() {
    const params = new URLSearchParams(window.location.search);
    showToast('Génération du fichier d\'export...', 'info');
    window.open(`export_medicaments.php?${params.toString()}`, '_blank');
}

function importMedicaments() {
    showToast('Fonction d\'import à implémenter', 'info');
}

function showStockAlerts() {
    window.location.href = 'medicaments.php?stock=faible';
}

function initMedicamentsTable() {
    if (confirm('Voulez-vous initialiser la table des médicaments ? Cela créera la table avec des données de démonstration.')) {
        showToast('Initialisation en cours...', 'info');
        fetch('../ajax/init_medicaments_table.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Table initialisée avec succès', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Erreur: ' + data.error, 'danger');
                }
            })
            .catch(error => {
                showToast('Erreur: ' + error.message, 'danger');
            });
    }
}

// Afficher un toast
function showToast(message, type = 'info') {
    // Supprimer les toasts existants
    const existingToasts = document.querySelectorAll('.toast');
    existingToasts.forEach(toast => toast.remove());
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    const container = document.getElementById('toastContainer') || createToastContainer();
    container.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}

// Recherche en temps réel
const searchInput = document.querySelector('input[name="search"]');
if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (this.value.length >= 2 || this.value.length === 0) {
                this.closest('form').submit();
            }
        }, 500);
    });
}
</script>

<style>
.stat-card {
    border-radius: 10px;
    transition: transform 0.2s;
    border: none;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.empty-state {
    text-align: center;
    padding: 2rem;
}

.form-label.required::after {
    content: ' *';
    color: #dc3545;
}

.progress {
    min-width: 80px;
    background-color: #e9ecef;
}

.progress-bar {
    transition: width 0.3s ease;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

.btn-group-sm .btn:first-child {
    border-top-left-radius: 4px;
    border-bottom-left-radius: 4px;
}

.btn-group-sm .btn:last-child {
    border-top-right-radius: 4px;
    border-bottom-right-radius: 4px;
}

.nav-tabs .nav-link {
    border: 1px solid transparent;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    background-color: #f8f9fa;
    border-color: #dee2e6 #dee2e6 #f8f9fa;
    color: #4361ee;
    border-bottom-color: transparent;
}

.table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.05em;
    color: #6b7280;
}

.table td {
    vertical-align: middle;
}

.badge {
    font-weight: 500;
    padding: 0.35em 0.65em;
}

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
}

.card {
    border: 1px solid rgba(0,0,0,.125);
    border-radius: 12px;
}

.card-header {
    border-bottom: 1px solid rgba(0,0,0,.125);
    background-color: rgba(255,255,255,.8);
}

.alert {
    border-radius: 8px;
    border: none;
}

.toast {
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.9rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.2rem 0.4rem;
    }
    
    .stat-card .h2 {
        font-size: 1.5rem;
    }
}
</style>