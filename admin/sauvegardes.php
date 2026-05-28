<?php
// admin/sauvegardes.php
require_once '../config/database.php';
checkRole('admin');

$title = 'Gestion des Sauvegardes';
require_once '../includes/header.php';

// Types de sauvegarde
$backup_types = [
    'complete' => 'Complète (Base + Fichiers)',
    'database' => 'Base de données uniquement',
    'files' => 'Fichiers uniquement',
    'incremental' => 'Incrémentielle'
];

// Traitement des actions
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['create_backup'])) {
            $backup_type = sanitize($_POST['backup_type']);
            $backup_name = sanitize($_POST['backup_name']);
            $description = sanitize($_POST['description'] ?? '');
            
            // Créer la sauvegarde
            $backup_result = createBackup($backup_type, $backup_name, $description);
            
            if ($backup_result['success']) {
                $success = "Sauvegarde créée avec succès : " . $backup_result['filename'];
            } else {
                $error = "Erreur lors de la création : " . $backup_result['error'];
            }
            
        } elseif (isset($_POST['restore_backup']) && $id) {
            $stmt = $pdo->prepare("SELECT * FROM backup_history WHERE id = ?");
            $stmt->execute([$id]);
            $backup = $stmt->fetch();
            
            if (!$backup) {
                $error = "Sauvegarde non trouvée";
            } else {
                $restore_result = restoreBackup($id);
                
                if ($restore_result['success']) {
                    $success = "Restauration réussie. Redémarrage nécessaire.";
                    // Rediriger après 3 secondes
                    header("refresh:3;url=sauvegardes.php");
                } else {
                    $error = "Erreur lors de la restauration : " . $restore_result['error'];
                }
            }
            
        } elseif (isset($_POST['delete_backup']) && $id) {
            $stmt = $pdo->prepare("SELECT * FROM backup_history WHERE id = ?");
            $stmt->execute([$id]);
            $backup = $stmt->fetch();
            
            if (!$backup) {
                $error = "Sauvegarde non trouvée";
            } else {
                // Supprimer le fichier physique
                $backup_dir = '../backups/';
                $filepath = $backup_dir . $backup['filename'];
                
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                
                // Supprimer l'entrée en base
                $pdo->prepare("DELETE FROM backup_history WHERE id = ?")->execute([$id]);
                
                // Journaliser l'action
                logAction('DELETE', 'backup_history', $id, "Suppression sauvegarde: " . $backup['filename']);
                
                $success = "Sauvegarde supprimée avec succès";
            }
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupérer les sauvegardes
$sauvegardes = $pdo->query("
    SELECT bh.*, u.nom, u.prenom 
    FROM backup_history bh 
    LEFT JOIN utilisateurs u ON bh.created_by = u.id 
    ORDER BY bh.created_at DESC
")->fetchAll();

// Calculer les statistiques
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM backup_history")->fetchColumn(),
    'total_size' => $pdo->query("SELECT SUM(size_mb) FROM backup_history")->fetchColumn(),
    'last_backup' => $pdo->query("SELECT MAX(created_at) FROM backup_history")->fetchColumn(),
    'oldest_backup' => $pdo->query("SELECT MIN(created_at) FROM backup_history")->fetchColumn()
];
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-database me-2"></i>Gestion des Sauvegardes
        </h1>
        <p class="text-muted mb-0">Sauvegarde et restauration du système</p>
    </div>
    <div class="btn-toolbar">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBackupModal">
            <i class="fas fa-plus-circle me-1"></i>Nouvelle sauvegarde
        </button>
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

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Total sauvegardes</h6>
                        <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-save fa-2x opacity-50"></i>
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
                        <h6 class="text-white-50">Espace utilisé</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['total_size'], 1); ?> MB</h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-hdd fa-2x opacity-50"></i>
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
                        <h6 class="text-white-50">Dernière sauvegarde</h6>
                        <h5 class="mb-0">
                            <?php echo $stats['last_backup'] ? formatDate($stats['last_backup'], 'd/m H:i') : 'Jamais'; ?>
                        </h5>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-check fa-2x opacity-50"></i>
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
                        <h6 class="text-white-50">Plus ancienne</h6>
                        <h5 class="mb-0">
                            <?php echo $stats['oldest_backup'] ? formatDate($stats['oldest_backup'], 'd/m/Y') : 'N/A'; ?>
                        </h5>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-alt fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Onglets -->
<ul class="nav nav-tabs mb-4" id="backupTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="list-tab" data-bs-toggle="tab" 
                data-bs-target="#list" type="button">
            <i class="fas fa-list me-2"></i>Sauvegardes
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" 
                data-bs-target="#schedule" type="button">
            <i class="fas fa-clock me-2"></i>Planification
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="settings-tab" data-bs-toggle="tab" 
                data-bs-target="#settings" type="button">
            <i class="fas fa-cog me-2"></i>Paramètres
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="logs-tab" data-bs-toggle="tab" 
                data-bs-target="#logs" type="button">
            <i class="fas fa-history me-2"></i>Logs
        </button>
    </li>
</ul>

<!-- Contenu des onglets -->
<div class="tab-content" id="backupTabsContent">
    <!-- Onglet Liste des sauvegardes -->
    <div class="tab-pane fade show active" id="list" role="tabpanel">
        <!-- Filtres -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type">
                            <option value="">Tous les types</option>
                            <?php foreach ($backup_types as $key => $label): ?>
                            <option value="<?php echo $key; ?>" 
                                <?php echo ($_GET['type'] ?? '') == $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Date de début</label>
                        <input type="date" class="form-control" name="date_from" 
                               value="<?php echo $_GET['date_from'] ?? ''; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Date de fin</label>
                        <input type="date" class="form-control" name="date_to" 
                               value="<?php echo $_GET['date_to'] ?? ''; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-1"></i>Filtrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Liste des sauvegardes -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-save me-2"></i>Sauvegardes disponibles</h6>
                <span class="badge bg-primary"><?php echo count($sauvegardes); ?> sauvegarde(s)</span>
            </div>
            
            <div class="card-body p-0">
                <?php if (empty($sauvegardes)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-database fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">Aucune sauvegarde disponible</h6>
                    <p class="text-muted small">Créez votre première sauvegarde pour protéger vos données</p>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" 
                            data-bs-target="#createBackupModal">
                        <i class="fas fa-plus me-1"></i>Créer une sauvegarde
                    </button>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Taille</th>
                                <th>Créée le</th>
                                <th>Créée par</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sauvegardes as $backup): 
                                $type_colors = [
                                    'complete' => 'primary',
                                    'database' => 'success',
                                    'files' => 'warning',
                                    'incremental' => 'info'
                                ];
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo $type_colors[$backup['backup_type']] ?? 'secondary'; ?>">
                                        <?php echo $backup_types[$backup['backup_type']] ?? $backup['backup_type']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo number_format($backup['size_mb'], 2); ?> MB</div>
                                </td>
                                <td>
                                    <div><?php echo formatDate($backup['created_at'], 'd/m/Y'); ?></div>
                                    <small class="text-muted"><?php echo formatDate($backup['created_at'], 'H:i'); ?></small>
                                </td>
                                <td>
                                    <?php if ($backup['nom']): ?>
                                    <div><?php echo $backup['prenom'] . ' ' . $backup['nom']; ?></div>
                                    <?php else: ?>
                                    <span class="text-muted">Système</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    // Vérifier si le fichier existe
                                    $backup_dir = '../backups/';
                                    $filepath = $backup_dir . $backup['filename'];
                                    $file_exists = file_exists($filepath);
                                    ?>
                                    <?php if ($file_exists): ?>
                                    <span class="badge bg-success">Disponible</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Fichier manquant</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($file_exists): ?>
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="downloadBackup(<?php echo $backup['id']; ?>)">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-success" 
                                                onclick="showRestoreModal(<?php echo $backup['id']; ?>)">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-info" 
                                                onclick="showBackupDetails(<?php echo $backup['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="showDeleteModal(<?php echo $backup['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Onglet Planification -->
    <div class="tab-pane fade" id="schedule" role="tabpanel">
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Planification automatique</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="scheduleForm">
                            <input type="hidden" name="update_schedule" value="1">
                            
                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="autoBackupEnabled" 
                                           name="auto_backup_enabled" value="1" checked>
                                    <label class="form-check-label fw-semibold" for="autoBackupEnabled">
                                        Activer les sauvegardes automatiques
                                    </label>
                                </div>
                                <p class="text-muted small mt-2">
                                    Le système créera automatiquement des sauvegardes selon la planification définie
                                </p>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Fréquence</label>
                                    <select class="form-select" name="frequency" id="frequency">
                                        <option value="daily">Quotidienne</option>
                                        <option value="weekly">Hebdomadaire</option>
                                        <option value="monthly">Mensuelle</option>
                                        <option value="custom">Personnalisée</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Type de sauvegarde</label>
                                    <select class="form-select" name="schedule_type">
                                        <?php foreach ($backup_types as $key => $label): ?>
                                        <option value="<?php echo $key; ?>">
                                            <?php echo $label; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Heure d'exécution</label>
                                    <input type="time" class="form-control" name="execution_time" 
                                           value="02:00">
                                    <small class="text-muted">Heure recommandée : 2h du matin</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Conserver pendant</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="retention_days" 
                                               value="30" min="1" max="365">
                                        <span class="input-group-text">jours</span>
                                    </div>
                                    <small class="text-muted">Les sauvegardes plus anciennes seront supprimées automatiquement</small>
                                </div>
                                
                                <!-- Configuration hebdomadaire -->
                                <div class="col-12" id="weeklyConfig">
                                    <label class="form-label">Jours de la semaine</label>
                                    <div class="row">
                                        <?php
                                        $week_days = [
                                            'monday' => 'Lundi',
                                            'tuesday' => 'Mardi',
                                            'wednesday' => 'Mercredi',
                                            'thursday' => 'Jeudi',
                                            'friday' => 'Vendredi',
                                            'saturday' => 'Samedi',
                                            'sunday' => 'Dimanche'
                                        ];
                                        ?>
                                        <?php foreach ($week_days as $key => $day): ?>
                                        <div class="col-4 col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="week_days[]" value="<?php echo $key; ?>" 
                                                       id="day_<?php echo $key; ?>">
                                                <label class="form-check-label" for="day_<?php echo $key; ?>">
                                                    <?php echo $day; ?>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Configuration mensuelle -->
                                <div class="col-12 d-none" id="monthlyConfig">
                                    <label class="form-label">Jour du mois</label>
                                    <input type="number" class="form-control" name="month_day" 
                                           min="1" max="31" value="1">
                                    <small class="text-muted">1-31 (dernier jour du mois pour 31)</small>
                                </div>
                            </div>
                            
                            <div class="mt-4 border-top pt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer la planification
                                </button>
                                <button type="button" class="btn btn-outline-secondary ms-2" onclick="testSchedule()">
                                    <i class="fas fa-play me-1"></i>Tester la planification
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Prochaines exécutions -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Prochaines sauvegardes</h6>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php
                            // Simuler les 5 prochaines sauvegardes planifiées
                            $next_backups = [];
                            $now = new DateTime();
                            
                            for ($i = 1; $i <= 5; $i++) {
                                $date = clone $now;
                                $date->modify("+{$i} days");
                                $next_backups[] = $date->format('d/m/Y H:i');
                            }
                            
                            foreach ($next_backups as $backup_date):
                            ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary">
                                    <i class="fas fa-save"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-1">Sauvegarde automatique</h6>
                                        <small class="text-muted"><?php echo $backup_date; ?></small>
                                    </div>
                                    <p class="mb-1 small">Type : Base de données</p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Statistiques planification -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistiques</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Sauvegardes automatiques</span>
                                <span class="fw-semibold">24</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Taux de réussite</span>
                                <span class="fw-semibold text-success">98.5%</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Dernière exécution</span>
                                <span class="fw-semibold">Aujourd'hui 02:00</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Prochaine exécution</span>
                                <span class="fw-semibold">Demain 02:00</span>
                            </li>
                        </ul>
                        
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-primary w-100 mb-2" onclick="runBackupNow()">
                                <i class="fas fa-play-circle me-1"></i>Exécuter maintenant
                            </button>
                            <button type="button" class="btn btn-outline-danger w-100" onclick="disableAutoBackup()">
                                <i class="fas fa-stop-circle me-1"></i>Désactiver
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Onglet Paramètres -->
    <div class="tab-pane fade" id="settings" role="tabpanel">
        <div class="row">
            <div class="col-lg-6">
                <!-- Paramètres de stockage -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-hdd me-2"></i>Stockage</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="storageSettings">
                            <input type="hidden" name="update_storage_settings" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label">Répertoire de sauvegarde</label>
                                <input type="text" class="form-control" name="backup_dir" 
                                       value="../backups/" readonly>
                                <small class="text-muted">Chemin absolu : <?php echo realpath('../backups/'); ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Espace maximum (MB)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="max_storage_mb" 
                                           value="1024" min="100" step="100">
                                    <span class="input-group-text">MB</span>
                                </div>
                                <small class="text-muted">
                                    <?php
                                    $backup_dir = '../backups/';
                                    $total_size = 0;
                                    if (is_dir($backup_dir)) {
                                        $files = glob($backup_dir . '*');
                                        foreach ($files as $file) {
                                            $total_size += filesize($file);
                                        }
                                        $total_size_mb = round($total_size / 1024 / 1024, 2);
                                        echo "Actuellement utilisé : {$total_size_mb} MB";
                                    }
                                    ?>
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Compression</label>
                                <select class="form-select" name="compression">
                                    <option value="gzip">GZIP (recommandé)</option>
                                    <option value="zip">ZIP</option>
                                    <option value="none">Aucune</option>
                                </select>
                                <small class="text-muted">GZIP offre le meilleur taux de compression</small>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="encrypt_backups" 
                                           id="encryptBackups" value="1">
                                    <label class="form-check-label" for="encryptBackups">
                                        Chiffrer les sauvegardes
                                    </label>
                                </div>
                                <small class="text-muted">
                                    Les sauvegardes seront chiffrées avec AES-256
                                </small>
                            </div>
                            
                            <div class="mb-3" id="encryptionKeyField" style="display: none;">
                                <label class="form-label">Clé de chiffrement</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="encryption_key" 
                                           id="encryptionKey">
                                    <button class="btn btn-outline-secondary" type="button" 
                                            onclick="toggleEncryptionKey()">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">
                                    Conservez cette clé en lieu sûr. Sans elle, les sauvegardes ne pourront pas être restaurées.
                                </small>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer les paramètres
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <!-- Stockage cloud -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-cloud me-2"></i>Stockage Cloud</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="cloudBackupEnabled" 
                                       name="cloud_backup_enabled" value="1">
                                <label class="form-check-label fw-semibold" for="cloudBackupEnabled">
                                    Activer la sauvegarde cloud
                                </label>
                            </div>
                            <p class="text-muted small mt-2">
                                Les sauvegardes seront automatiquement envoyées vers le cloud
                            </p>
                        </div>
                        
                        <div id="cloudConfig">
                            <div class="mb-3">
                                <label class="form-label">Fournisseur cloud</label>
                                <select class="form-select" name="cloud_provider" id="cloudProvider">
                                    <option value="">Sélectionner un fournisseur</option>
                                    <option value="aws">Amazon S3</option>
                                    <option value="google">Google Cloud Storage</option>
                                    <option value="azure">Microsoft Azure</option>
                                    <option value="dropbox">Dropbox</option>
                                    <option value="custom">Serveur FTP/SFTP</option>
                                </select>
                            </div>
                            
                            <!-- Configuration AWS -->
                            <div class="cloud-config-field" id="awsConfig" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Clé d'accès AWS</label>
                                    <input type="text" class="form-control" name="aws_access_key">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Clé secrète AWS</label>
                                    <input type="password" class="form-control" name="aws_secret_key">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nom du bucket</label>
                                    <input type="text" class="form-control" name="aws_bucket">
                                </div>
                            </div>
                            
                            <!-- Configuration FTP -->
                            <div class="cloud-config-field" id="ftpConfig" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Serveur FTP</label>
                                    <input type="text" class="form-control" name="ftp_server" 
                                           placeholder="ftp.example.com">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Port</label>
                                    <input type="number" class="form-control" name="ftp_port" value="21">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Utilisateur</label>
                                    <input type="text" class="form-control" name="ftp_username">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mot de passe</label>
                                    <input type="password" class="form-control" name="ftp_password">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Répertoire distant</label>
                                    <input type="text" class="form-control" name="ftp_directory" 
                                           value="/backups/">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sync_existing" 
                                           id="syncExisting" value="1">
                                    <label class="form-check-label" for="syncExisting">
                                        Synchroniser les sauvegardes existantes
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="button" class="btn btn-primary" onclick="testCloudConnection()">
                                    <i class="fas fa-plug me-1"></i>Tester la connexion
                                </button>
                                <button type="button" class="btn btn-outline-success ms-2" onclick="syncToCloud()">
                                    <i class="fas fa-sync me-1"></i>Synchroniser maintenant
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Maintenance -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-tools me-2"></i>Maintenance</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>Nettoyage des sauvegardes</h6>
                            <p class="text-muted small">
                                Supprimez les sauvegardes corrompues ou inutiles
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-primary w-100 mb-2" 
                                    onclick="verifyBackups()">
                                <i class="fas fa-check-circle me-1"></i>Vérifier l'intégrité
                            </button>
                            <button type="button" class="btn btn-outline-warning w-100 mb-2" 
                                    onclick="cleanOldBackups()">
                                <i class="fas fa-broom me-1"></i>Nettoyer les anciennes
                            </button>
                            <button type="button" class="btn btn-outline-danger w-100" 
                                    onclick="deleteCorruptedBackups()">
                                <i class="fas fa-trash me-1"></i>Supprimer les corrompues
                            </button>
                        </div>
                        
                        <div class="mt-4">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Important :</strong> 
                                <ul class="mb-0 mt-2 small">
                                    <li>Testez toujours une sauvegarde avant de supprimer l'ancienne</li>
                                    <li>Conservez au moins 3 sauvegardes récentes</li>
                                    <li>Vérifiez régulièrement l'intégrité des sauvegardes</li>
                                    <li>Stockez les sauvegardes dans un emplacement sécurisé</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Onglet Logs -->
    <div class="tab-pane fade" id="logs" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Journal des activités de sauvegarde</h6>
            </div>
            <div class="card-body">
                <!-- Ici serait normalement le code pour afficher les logs -->
                <p>Cette fonctionnalité sera implémentée dans une future version.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="createBackupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Créer une nouvelle sauvegarde</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom de la sauvegarde</label>
                        <input type="text" class="form-control" name="backup_name" required
                               value="sauvegarde_<?php echo date('Y-m-d_H-i'); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Type de sauvegarde</label>
                        <select class="form-select" name="backup_type" required>
                            <?php foreach ($backup_types as $key => $label): ?>
                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description (optionnel)</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note :</strong> La création d'une sauvegarde complète peut prendre plusieurs minutes.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="create_backup" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Créer la sauvegarde
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="restoreForm">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la restauration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="restoreBackupId">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle fa-lg me-2"></i>
                        <strong>Attention !</strong>
                        <p class="mt-2 mb-0">
                            La restauration va écraser toutes les données actuelles. 
                            Cette action est irréversible. Assurez-vous d'avoir une sauvegarde récente avant de continuer.
                        </p>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmRestore" required>
                        <label class="form-check-label" for="confirmRestore">
                            Je confirme vouloir restaurer cette sauvegarde
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="restore_backup" class="btn btn-danger">
                        <i class="fas fa-undo me-1"></i>Restaurer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="deleteForm">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="deleteBackupId">
                    <p>Êtes-vous sûr de vouloir supprimer cette sauvegarde ? Cette action est irréversible.</p>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmDelete" required>
                        <label class="form-check-label" for="confirmDelete">
                            Je confirme vouloir supprimer cette sauvegarde
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="delete_backup" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Fonctions JavaScript pour gérer les actions
function downloadBackup(id) {
    window.location.href = 'download_backup.php?id=' + id;
}

function showRestoreModal(id) {
    document.getElementById('restoreBackupId').value = id;
    new bootstrap.Modal(document.getElementById('restoreModal')).show();
}

function showDeleteModal(id) {
    document.getElementById('deleteBackupId').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function showBackupDetails(id) {
    // Pourrait ouvrir un modal avec les détails de la sauvegarde
    alert('Détails de la sauvegarde #' + id + ' (fonctionnalité à implémenter)');
}

// Gestion de la configuration cloud
document.getElementById('cloudProvider').addEventListener('change', function() {
    // Cacher toutes les configurations
    document.querySelectorAll('.cloud-config-field').forEach(el => {
        el.style.display = 'none';
    });
    
    // Afficher la configuration sélectionnée
    if (this.value === 'aws') {
        document.getElementById('awsConfig').style.display = 'block';
    } else if (this.value === 'custom') {
        document.getElementById('ftpConfig').style.display = 'block';
    }
});

// Gestion du chiffrement
document.getElementById('encryptBackups').addEventListener('change', function() {
    document.getElementById('encryptionKeyField').style.display = 
        this.checked ? 'block' : 'none';
});

function toggleEncryptionKey() {
    const input = document.getElementById('encryptionKey');
    input.type = input.type === 'password' ? 'text' : 'password';
}

// Gestion de la fréquence
document.getElementById('frequency').addEventListener('change', function() {
    document.getElementById('weeklyConfig').style.display = 
        this.value === 'weekly' ? 'block' : 'none';
    document.getElementById('monthlyConfig').classList.toggle('d-none', 
        this.value !== 'monthly');
});

// Fonctions de test (placeholder)
function testSchedule() {
    alert('Fonction de test à implémenter');
}

function testCloudConnection() {
    alert('Test de connexion cloud à implémenter');
}

function runBackupNow() {
    if (confirm('Exécuter une sauvegarde maintenant ?')) {
        // Appel AJAX pour démarrer une sauvegarde immédiate
        alert('Démarrage de la sauvegarde...');
    }
}

function disableAutoBackup() {
    if (confirm('Désactiver les sauvegardes automatiques ?')) {
        document.getElementById('autoBackupEnabled').checked = false;
        alert('Sauvegardes automatiques désactivées');
    }
}

function verifyBackups() {
    alert('Vérification de l\'intégrité à implémenter');
}

function cleanOldBackups() {
    if (confirm('Nettoyer les sauvegardes anciennes ?')) {
        alert('Nettoyage à implémenter');
    }
}

function deleteCorruptedBackups() {
    if (confirm('Supprimer les sauvegardes corrompues ?')) {
        alert('Suppression à implémenter');
    }
}

function syncToCloud() {
    alert('Synchronisation cloud à implémenter');
}
</script>

<?php
require_once '../includes/footer.php';
?>