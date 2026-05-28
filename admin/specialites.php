<?php
// admin/specialites.php
require_once '../config/database.php';
checkRole('admin');

$title = 'Gestion des Spécialités Médicales';
require_once '../includes/header.php';

// Traitement des actions CRUD
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = sanitize($_POST);
    
    if ($action === 'add' || $action === 'edit') {
        try {
            $pdo->beginTransaction();
            
            if ($action === 'add') {
                // Vérifier si la spécialité existe déjà
                $exists = $pdo->prepare("SELECT id FROM specialites WHERE code = ? OR nom = ?")
                    ->execute([$data['code'], $data['nom']])->fetch();
                
                if ($exists) {
                    throw new Exception('Une spécialité avec ce code ou nom existe déjà');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO specialites 
                    (code, nom, description, couleur, icon, statut) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    strtoupper($data['code']),
                    $data['nom'],
                    $data['description'] ?? null,
                    $data['couleur'],
                    $data['icon'],
                    $data['statut'] ?? 'active'
                ]);
                
                $specialiteId = $pdo->lastInsertId();
                
                // Journaliser l'action
                $pdo->prepare("
                    INSERT INTO audit_logs 
                    (user_id, action, table_name, record_id, ip_address) 
                    VALUES (?, ?, 'specialites', ?, ?)
                ")->execute([$_SESSION['user_id'], 'CREATE', $specialiteId, $_SERVER['REMOTE_ADDR']]);
                
                $message = "success:Spécialité créée avec succès";
                
            } elseif ($action === 'edit' && $id) {
                // Vérifier les doublons (sauf l'enregistrement actuel)
                $exists = $pdo->prepare("
                    SELECT id FROM specialites 
                    WHERE (code = ? OR nom = ?) AND id != ?
                ")->execute([$data['code'], $data['nom'], $id])->fetch();
                
                if ($exists) {
                    throw new Exception('Une spécialité avec ce code ou nom existe déjà');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE specialites SET 
                    code = ?, nom = ?, description = ?, couleur = ?, 
                    icon = ?, statut = ?, date_modification = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    strtoupper($data['code']),
                    $data['nom'],
                    $data['description'] ?? null,
                    $data['couleur'],
                    $data['icon'],
                    $data['statut'],
                    $id
                ]);
                
                $pdo->prepare("
                    INSERT INTO audit_logs 
                    (user_id, action, table_name, record_id, ip_address) 
                    VALUES (?, ?, 'specialites', ?, ?)
                ")->execute([$_SESSION['user_id'], 'UPDATE', $id, $_SERVER['REMOTE_ADDR']]);
                
                $message = "success:Spécialité modifiée avec succès";
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "danger:Erreur: " . $e->getMessage();
        }
        
    } elseif ($action === 'delete' && $id) {
        try {
            // Vérifier s'il y a des médecins associés à cette spécialité
            $hasDoctors = $pdo->prepare("
                SELECT COUNT(*) FROM docteur_specialite WHERE specialite_id = ?
                UNION ALL
                SELECT COUNT(*) FROM utilisateurs WHERE specialite = (SELECT nom FROM specialites WHERE id = ?)
            ")->execute([$id, $id])->fetchColumn();
            
            if ($hasDoctors > 0) {
                // Désactiver au lieu de supprimer
                $pdo->prepare("UPDATE specialites SET statut = 'inactive' WHERE id = ?")->execute([$id]);
                $message = "warning:Cette spécialité est utilisée par des médecins. Elle a été désactivée.";
            } else {
                // Supprimer définitivement
                $pdo->prepare("DELETE FROM specialites WHERE id = ?")->execute([$id]);
                $message = "success:Spécialité supprimée avec succès";
            }
            
            $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action, table_name, record_id, ip_address) 
                VALUES (?, ?, 'specialites', ?, ?)
            ")->execute([$_SESSION['user_id'], 'DELETE', $id, $_SERVER['REMOTE_ADDR']]);
            
        } catch (Exception $e) {
            $message = "danger:Erreur: " . $e->getMessage();
        }
    }
    
    header("Location: specialites.php?message=" . urlencode($message));
    exit();
}

// Afficher les messages
if (isset($_GET['message'])) {
    list($type, $text) = explode(':', $_GET['message'], 2);
    $message = "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                    <i class='fas fa-info-circle me-2'></i>$text
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
}
?>

<!-- Content Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-stethoscope me-2"></i>Spécialités Médicales
        </h1>
        <p class="text-muted mb-0">Gestion des spécialités médicales disponibles dans le système</p>
    </div>
    <div class="btn-toolbar">
        <?php if ($action === 'list'): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSpecialiteModal">
            <i class="fas fa-plus me-1"></i>Nouvelle spécialité
        </button>
        <?php else: ?>
        <a href="specialites.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Retour à la liste
        </a>
        <?php endif; ?>
    </div>
</div>

<?php echo $message ?? ''; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Formulaire Ajout/Modification -->
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?> me-2"></i>
                    <?php echo $action === 'add' ? 'Ajouter une spécialité' : 'Modifier la spécialité'; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php
                $specialite = null;
                if ($action === 'edit' && $id) {
                    $specialite = $pdo->prepare("SELECT * FROM specialites WHERE id = ?")->execute([$id])->fetch();
                    if (!$specialite) {
                        echo "<div class='alert alert-danger'>Spécialité non trouvée</div>";
                        require_once '../includes/footer.php';
                        exit();
                    }
                }
                ?>
                
                <form method="POST" id="specialiteForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Code *</label>
                            <input type="text" class="form-control" name="code" 
                                   value="<?php echo $specialite['code'] ?? ''; ?>" 
                                   placeholder="Ex: CARDIO" required
                                   pattern="[A-Z0-9]+" maxlength="10">
                            <small class="text-muted">Code unique en majuscules (max 10 caractères)</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Nom *</label>
                            <input type="text" class="form-control" name="nom" 
                                   value="<?php echo $specialite['nom'] ?? ''; ?>" 
                                   placeholder="Ex: Cardiologie" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Description détaillée de la spécialité..."><?php echo $specialite['description'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Couleur *</label>
                            <div class="input-group color-picker">
                                <input type="color" class="form-control form-control-color" name="couleur" 
                                       value="<?php echo $specialite['couleur'] ?? '#4361ee'; ?>" 
                                       title="Choisir une couleur">
                                <input type="text" class="form-control" 
                                       value="<?php echo $specialite['couleur'] ?? '#4361ee'; ?>" 
                                       readonly>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Icône Font Awesome *</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas <?php echo $specialite['icon'] ?? 'fa-stethoscope'; ?>"></i>
                                </span>
                                <input type="text" class="form-control" name="icon" 
                                       value="<?php echo $specialite['icon'] ?? 'fa-stethoscope'; ?>" 
                                       placeholder="fa-stethoscope" required>
                            </div>
                            <small class="text-muted">
                                <a href="https://fontawesome.com/icons" target="_blank" class="text-decoration-none">
                                    <i class="fas fa-external-link-alt me-1"></i>Voir les icônes disponibles
                                </a>
                            </small>
                        </div>
                        
                        <?php if ($action === 'edit'): ?>
                        <div class="col-md-6">
                            <label class="form-label">Statut *</label>
                            <select class="form-select" name="statut" required>
                                <option value="active" <?php echo ($specialite['statut'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($specialite['statut'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Médecins associés</label>
                            <div class="form-control bg-light">
                                <?php
                                $doctorsCount = $pdo->prepare("
                                    SELECT COUNT(*) FROM docteur_specialite 
                                    WHERE specialite_id = ?
                                ")->execute([$id])->fetchColumn();
                                ?>
                                <span class="badge bg-info"><?php echo $doctorsCount; ?> médecin(s)</span>
                                <?php if ($doctorsCount > 0): ?>
                                <button type="button" class="btn btn-sm btn-outline-info ms-2" 
                                        onclick="showDoctors(<?php echo $id; ?>)">
                                    Voir la liste
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i>
                            <?php echo $action === 'add' ? 'Créer' : 'Modifier'; ?>
                        </button>
                        <a href="specialites.php" class="btn btn-secondary ms-2">Annuler</a>
                        
                        <?php if ($action === 'edit'): ?>
                        <button type="button" class="btn btn-danger ms-2" 
                                onclick="confirmDelete(<?php echo $specialite['id']; ?>)">
                            <i class="fas fa-trash me-1"></i>Supprimer
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-primary border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Spécialités actives</div>
                        <div class="h3 mb-0">
                            <?php echo $pdo->query("SELECT COUNT(*) FROM specialites WHERE statut = 'active'")->fetchColumn(); ?>
                        </div>
                    </div>
                    <div class="rounded-circle bg-primary-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-check-circle text-primary fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-success border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Médecins par spécialité</div>
                        <div class="h3 mb-0">
                            <?php echo $pdo->query("SELECT COUNT(DISTINCT docteur_id) FROM docteur_specialite")->fetchColumn(); ?>
                        </div>
                    </div>
                    <div class="rounded-circle bg-success-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-user-md text-success fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-warning border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Consultations ce mois</div>
                        <div class="h3 mb-0">
                            <?php 
                            $consultations = $pdo->query("
                                SELECT COUNT(*) FROM consultations 
                                WHERE MONTH(date_consultation) = MONTH(CURDATE())
                            ")->fetchColumn();
                            echo number_format($consultations);
                            ?>
                        </div>
                    </div>
                    <div class="rounded-circle bg-warning-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-stethoscope text-warning fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-info border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Top spécialité</div>
                        <div class="h4 mb-0">
                            <?php
                            $topSpecialite = $pdo->query("
                                SELECT s.nom, COUNT(c.id) as consultations
                                FROM specialites s
                                LEFT JOIN docteur_specialite ds ON s.id = ds.specialite_id
                                LEFT JOIN utilisateurs u ON ds.docteur_id = u.id
                                LEFT JOIN consultations c ON u.id = c.docteur_id
                                WHERE s.statut = 'active'
                                GROUP BY s.id
                                ORDER BY consultations DESC
                                LIMIT 1
                            ")->fetch();
                            echo $topSpecialite ? $topSpecialite['nom'] : 'Aucune';
                            ?>
                        </div>
                    </div>
                    <div class="rounded-circle bg-info-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-trophy text-info fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Liste des spécialités -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Liste des spécialités médicales
                </h6>
            </div>
            <div class="col-md-6">
                <form method="GET" class="d-flex">
                    <input type="text" class="form-control me-2" name="search" 
                           placeholder="Rechercher une spécialité..."
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
                        <th style="width: 50px;"></th>
                        <th>Code</th>
                        <th>Spécialité</th>
                        <th>Description</th>
                        <th>Médecins</th>
                        <th>Statut</th>
                        <th style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $search = $_GET['search'] ?? '';
                    $filter = $_GET['filter'] ?? 'all';
                    
                    $sql = "SELECT s.*, 
                                   (SELECT COUNT(*) FROM docteur_specialite WHERE specialite_id = s.id) as doctors_count,
                                   (SELECT COUNT(*) FROM consultations c 
                                    JOIN utilisateurs u ON c.docteur_id = u.id 
                                    JOIN docteur_specialite ds ON u.id = ds.docteur_id 
                                    WHERE ds.specialite_id = s.id 
                                    AND MONTH(c.date_consultation) = MONTH(CURDATE())) as monthly_consultations
                            FROM specialites s 
                            WHERE 1=1";
                    
                    $params = [];
                    
                    if ($search) {
                        $sql .= " AND (s.code LIKE ? OR s.nom LIKE ? OR s.description LIKE ?)";
                        $searchTerm = "%$search%";
                        $params = array_fill(0, 3, $searchTerm);
                    }
                    
                    if ($filter === 'active') {
                        $sql .= " AND s.statut = 'active'";
                    } elseif ($filter === 'inactive') {
                        $sql .= " AND s.statut = 'inactive'";
                    }
                    
                    $sql .= " ORDER BY s.nom";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $specialites = $stmt->fetchAll();
                    
                    if (empty($specialites)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-stethoscope fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Aucune spécialité trouvée</h5>
                                <p class="text-muted mb-4">Commencez par ajouter votre première spécialité médicale</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSpecialiteModal">
                                    <i class="fas fa-plus me-1"></i>Ajouter une spécialité
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php else: 
                        foreach ($specialites as $spec): 
                            $color = $spec['couleur'] ?: '#4361ee';
                            $icon = $spec['icon'] ?: 'fa-stethoscope';
                    ?>
                    <tr>
                        <td>
                            <div class="specialite-icon" style="background-color: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?php echo $spec['code']; ?></span>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo $spec['nom']; ?></div>
                            <small class="text-muted">
                                <i class="fas fa-calendar-alt me-1"></i>
                                Créé le: <?php echo formatDate($spec['date_creation'], 'd/m/Y'); ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($spec['description']): ?>
                            <span class="small text-truncate d-block" style="max-width: 250px;" 
                                  title="<?php echo htmlspecialchars($spec['description']); ?>">
                                <?php echo substr($spec['description'], 0, 80); ?>
                                <?php if (strlen($spec['description']) > 80): ?>...<?php endif; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted small">Aucune description</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-info me-2"><?php echo $spec['doctors_count']; ?></span>
                                <div>
                                    <div class="small"><?php echo $spec['monthly_consultations']; ?> consultations ce mois</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($spec['statut'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=edit&id=<?php echo $spec['id']; ?>" 
                                   class="btn btn-outline-primary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-outline-info" 
                                        onclick="showSpecialiteDetails(<?php echo $spec['id']; ?>)" title="Détails">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $spec['id']; ?>)" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; 
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if (!empty($specialites)): ?>
    <div class="card-footer bg-white border-top">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <small class="text-muted">
                    Total: <?php echo count($specialites); ?> spécialité(s)
                </small>
                <div class="btn-group btn-group-sm ms-3">
                    <a href="?filter=all" class="btn btn-sm btn-outline-secondary <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        Toutes
                    </a>
                    <a href="?filter=active" class="btn btn-sm btn-outline-secondary <?php echo $filter === 'active' ? 'active' : ''; ?>">
                        Actives
                    </a>
                    <a href="?filter=inactive" class="btn btn-sm btn-outline-secondary <?php echo $filter === 'inactive' ? 'active' : ''; ?>">
                        Inactives
                    </a>
                </div>
            </div>
            <div>
                <button class="btn btn-sm btn-outline-secondary" onclick="exportSpecialites()">
                    <i class="fas fa-file-export me-1"></i>Exporter
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Ajout Rapide -->
<div class="modal fade" id="addSpecialiteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>Ajouter une nouvelle spécialité
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickAddForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Code *</label>
                            <input type="text" class="form-control" name="code" required 
                                   placeholder="Ex: CARDIO" maxlength="10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nom *</label>
                            <input type="text" class="form-control" name="nom" required 
                                   placeholder="Ex: Cardiologie">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2" 
                                      placeholder="Description courte..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Couleur</label>
                            <input type="color" class="form-control form-control-color" 
                                   name="couleur" value="#4361ee">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Icône</label>
                            <select class="form-select" name="icon">
                                <option value="fa-stethoscope">Stethoscope</option>
                                <option value="fa-heartbeat">Cardiologie</option>
                                <option value="fa-brain">Neurologie</option>
                                <option value="fa-allergies">Dermatologie</option>
                                <option value="fa-eye">Ophtalmologie</option>
                                <option value="fa-baby">Pédiatrie</option>
                                <option value="fa-female">Gynécologie</option>
                                <option value="fa-x-ray">Radiologie</option>
                                <option value="fa-bone">Orthopédie</option>
                                <option value="fa-tooth">Dentisterie</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="quickAddSpecialite()">
                    <i class="fas fa-plus me-1"></i>Ajouter
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Détails -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de la spécialité</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Chargé via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Modal Médecins -->
<div class="modal fade" id="doctorsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Médecins de la spécialité</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="doctorsContent">
                <!-- Chargé via AJAX -->
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

<style>
.specialite-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.color-picker .form-control-color {
    width: 60px;
    height: 38px;
    padding: 3px;
}

.color-picker input[type="text"] {
    border-left: none;
}

.specialite-card {
    border-left: 4px solid;
    transition: all 0.3s;
}

.specialite-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.icon-preview {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--primary-light);
    color: var(--primary);
}
</style>

<script>
// Gestion du picker de couleur
document.querySelectorAll('.color-picker input[type="color"]').forEach(picker => {
    picker.addEventListener('input', function() {
        this.closest('.color-picker').querySelector('input[type="text"]').value = this.value;
    });
});

// Ajout rapide via modal
function quickAddSpecialite() {
    const form = document.getElementById('quickAddForm');
    const formData = new FormData(form);
    const button = document.querySelector('#addSpecialiteModal .btn-primary');
    const originalText = button.innerHTML;
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Ajout en cours...';
    button.disabled = true;
    
    fetch('ajax/add_specialite.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Erreur: ' + data.error);
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        alert('Erreur: ' + error.message);
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Afficher les détails
function showSpecialiteDetails(id) {
    fetch(`../ajax/get_specialite_details.php?id=${id}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('detailsContent').innerHTML = data;
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        });
}

// Afficher les médecins
function showDoctors(id) {
    fetch(`../ajax/get_specialite_doctors.php?id=${id}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('doctorsContent').innerHTML = data;
            new bootstrap.Modal(document.getElementById('doctorsModal')).show();
        });
}

// Confirmer la suppression
function confirmDelete(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette spécialité ?')) {
        window.location.href = `?action=delete&id=${id}`;
    }
}

// Exporter les spécialités
function exportSpecialites() {
    const search = new URLSearchParams(window.location.search).get('search') || '';
    const filter = new URLSearchParams(window.location.search).get('filter') || 'all';
    
    fetch(`../ajax/export_specialites.php?search=${encodeURIComponent(search)}&filter=${filter}`)
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `specialites_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        });
}

// Validation du formulaire
document.getElementById('specialiteForm')?.addEventListener('submit', function(e) {
    const code = this.querySelector('input[name="code"]');
    if (code && !/^[A-Z0-9]+$/.test(code.value)) {
        e.preventDefault();
        alert('Le code ne doit contenir que des lettres majuscules et des chiffres');
        code.focus();
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

// Mise à jour de l'aperçu de l'icône
const iconInput = document.querySelector('input[name="icon"]');
iconInput?.addEventListener('input', function() {
    const preview = this.closest('.input-group').querySelector('.input-group-text i');
    if (preview) {
        preview.className = `fas ${this.value}`;
    }
});
</script>