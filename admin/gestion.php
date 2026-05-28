<?php
// admin/gestion.php
require_once '../config/database.php';
checkRole('admin');

$title = 'Gestion du Système';
require_once '../includes/header.php';

// Traitement des paramètres
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        try {
            foreach ($_POST['settings'] as $key => $value) {
                // Vérifier si le paramètre existe et est modifiable
                $stmt = $pdo->prepare("SELECT modifiable FROM parametres_systeme WHERE cle = ?");
                $stmt->execute([$key]);
                $param = $stmt->fetch();
                
                if ($param && $param['modifiable']) {
                    $pdo->prepare("UPDATE parametres_systeme SET valeur = ? WHERE cle = ?")
                        ->execute([$value, $key]);
                }
            }
            $success = "Paramètres mis à jour avec succès";
        } catch (Exception $e) {
            $error = "Erreur lors de la mise à jour des paramètres: " . $e->getMessage();
        }
    } elseif (isset($_POST['run_backup'])) {
        try {
            // Vérifier si le dossier de sauvegarde existe
            $backup_dir = '../backups/';
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
            
            // Créer une sauvegarde
            $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backup_path = $backup_dir . $backup_file;
            
            // Récupérer les informations de connexion
            require_once '../config/database.php'; // Charger les infos de connexion
            
            // Exécuter la commande mysqldump
            $command = "mysqldump -u " . escapeshellarg(DB_USER) . 
                      " -p" . escapeshellarg(DB_PASS) . 
                      " -h " . escapeshellarg(DB_HOST) . 
                      " " . escapeshellarg(DB_NAME) . 
                      " > " . escapeshellarg($backup_path);
            
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && file_exists($backup_path)) {
                // Enregistrer dans l'historique
                $size = filesize($backup_path);
                $pdo->prepare("INSERT INTO backup_history (backup_type, filename, size_mb, created_by) VALUES (?, ?, ?, ?)")
                    ->execute(['manuel', $backup_file, round($size / 1024 / 1024, 2), $_SESSION['user_id']]);
                
                $success = "Sauvegarde créée avec succès: $backup_file";
            } else {
                $error = "Erreur lors de la création de la sauvegarde";
            }
        } catch (Exception $e) {
            $error = "Erreur: " . $e->getMessage();
        }
    } elseif (isset($_POST['clear_cache'])) {
        try {
            // Nettoyer le cache
            $cache_dir = '../cache/';
            if (is_dir($cache_dir)) {
                $files = glob($cache_dir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                $success = "Cache nettoyé avec succès";
            } else {
                $error = "Le dossier cache n'existe pas";
            }
        } catch (Exception $e) {
            $error = "Erreur lors du nettoyage du cache: " . $e->getMessage();
        }
    }
}

// Récupérer les paramètres système
try {
    $parametres = $pdo->query("SELECT * FROM parametres_systeme ORDER BY categorie, cle")->fetchAll();
} catch (Exception $e) {
    $error = "Erreur lors de la récupération des paramètres: " . $e->getMessage();
    $parametres = [];
}

// Récupérer l'historique des sauvegardes
try {
    $backups = $pdo->query("SELECT * FROM backup_history ORDER BY created_at DESC LIMIT 10")->fetchAll();
} catch (Exception $e) {
    $error = "Erreur lors de la récupération des sauvegardes: " . $e->getMessage();
    $backups = [];
}

// Statistiques système
try {
    $system_stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM patients) as total_patients,
            (SELECT COUNT(*) FROM consultations) as total_consultations,
            (SELECT COUNT(*) FROM utilisateurs) as total_users,
            (SELECT COUNT(*) FROM prescriptions) as total_prescriptions,
            (SELECT COUNT(*) FROM rendez_vous) as total_rdv,
            (SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()) as logs_today,
            (SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) 
             FROM information_schema.tables 
             WHERE table_schema = DATABASE()) as db_size_mb,
            (SELECT COUNT(*) FROM notifications WHERE lu = 0) as unread_notifications
    ")->fetch();
    
    if (!$system_stats) {
        $system_stats = [
            'total_patients' => 0,
            'total_consultations' => 0,
            'total_users' => 0,
            'total_prescriptions' => 0,
            'total_rdv' => 0,
            'logs_today' => 0,
            'db_size_mb' => 0,
            'unread_notifications' => 0
        ];
    }
} catch (Exception $e) {
    $error = "Erreur lors de la récupération des statistiques: " . $e->getMessage();
    $system_stats = [
        'total_patients' => 0,
        'total_consultations' => 0,
        'total_users' => 0,
        'total_prescriptions' => 0,
        'total_rdv' => 0,
        'logs_today' => 0,
        'db_size_mb' => 0,
        'unread_notifications' => 0
    ];
}

// Logs récents
try {
    $recent_logs = $pdo->query("
        SELECT a.*, u.nom, u.prenom 
        FROM audit_logs a 
        LEFT JOIN utilisateurs u ON a.user_id = u.id 
        ORDER BY a.created_at DESC 
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $error = "Erreur lors de la récupération des logs: " . $e->getMessage();
    $recent_logs = [];
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-cogs me-2"></i>Gestion du Système
        </h1>
        <p class="text-muted mb-0">Configuration et administration du système médical</p>
    </div>
    <div class="btn-toolbar">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-secondary" onclick="refreshSystemInfo()">
                <i class="fas fa-sync-alt me-1"></i>Actualiser
            </button>
            <button type="button" class="btn btn-outline-secondary" onclick="showSystemInfo()">
                <i class="fas fa-info-circle me-1"></i>Info système
            </button>
        </div>
    </div>
</div>

<!-- Messages -->
<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stats System -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-primary border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Taille base de données</div>
                        <div class="h3 mb-0"><?php echo htmlspecialchars($system_stats['db_size_mb']); ?> MB</div>
                        <small class="text-muted">
                            <?php echo htmlspecialchars($system_stats['total_patients']); ?> patients
                        </small>
                    </div>
                    <div class="rounded-circle bg-primary-light d-flex align-items-center justify-content-center" 
                         style="width: 60px; height: 60px;">
                        <i class="fas fa-database text-primary fa-2x"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="optimizeDatabase()">
                        <i class="fas fa-magic me-1"></i>Optimiser
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-success border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Utilisateurs actifs</div>
                        <div class="h3 mb-0"><?php echo htmlspecialchars($system_stats['total_users']); ?></div>
                        <small class="text-success">
                            <?php 
                            try {
                                echo htmlspecialchars($pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE derniere_connexion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn());
                            } catch (Exception $e) {
                                echo '0';
                            }
                            ?> aujourd'hui
                        </small>
                    </div>
                    <div class="rounded-circle bg-success-light d-flex align-items-center justify-content-center" 
                         style="width: 60px; height: 60px;">
                        <i class="fas fa-users text-success fa-2x"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="utilisateurs.php" class="text-decoration-none small">
                        <i class="fas fa-list me-1"></i>Gérer les utilisateurs
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-warning border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Activité système</div>
                        <div class="h3 mb-0"><?php echo htmlspecialchars($system_stats['logs_today']); ?></div>
                        <small class="text-muted">
                            logs aujourd'hui
                        </small>
                    </div>
                    <div class="rounded-circle bg-warning-light d-flex align-items-center justify-content-center" 
                         style="width: 60px; height: 60px;">
                        <i class="fas fa-history text-warning fa-2x"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="logs.php" class="text-decoration-none small">
                        <i class="fas fa-search me-1"></i>Voir les logs
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-danger border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Notifications</div>
                        <div class="h3 mb-0"><?php echo htmlspecialchars($system_stats['unread_notifications']); ?></div>
                        <small class="text-danger">
                            non lues
                        </small>
                    </div>
                    <div class="rounded-circle bg-danger-light d-flex align-items-center justify-content-center" 
                         style="width: 60px; height: 60px;">
                        <i class="fas fa-bell text-danger fa-2x"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearNotifications()">
                        <i class="fas fa-trash me-1"></i>Effacer toutes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Onglets -->
<ul class="nav nav-tabs mb-4" id="systemTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="settings-tab" data-bs-toggle="tab" 
                data-bs-target="#settings" type="button">Paramètres</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="backup-tab" data-bs-toggle="tab" 
                data-bs-target="#backup" type="button">Sauvegardes</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="logs-tab" data-bs-toggle="tab" 
                data-bs-target="#logs" type="button">Logs système</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" 
                data-bs-target="#maintenance" type="button">Maintenance</button>
    </li>
</ul>

<div class="tab-content" id="systemTabsContent">
    <!-- Onglet Paramètres -->
    <div class="tab-pane fade show active" id="settings" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-sliders-h me-2"></i>
                    Paramètres du système
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" id="settingsForm">
                    <?php 
                    $current_category = '';
                    foreach ($parametres as $param): 
                        if ($param['categorie'] != $current_category):
                            $current_category = $param['categorie'];
                            if ($current_category != ''): ?>
                                </div></div>
                            <?php endif; ?>
                            <div class="mb-4">
                                <h6 class="mb-3 text-uppercase text-muted">
                                    <i class="fas fa-folder me-2"></i><?php echo htmlspecialchars(ucfirst($current_category)); ?>
                                </h6>
                                <div class="row g-3">
                        <?php endif; ?>
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <?php echo htmlspecialchars($param['description'] ?? $param['cle']); ?>
                                <?php if (!$param['modifiable']): ?>
                                <span class="badge bg-secondary ms-1">Lecture seule</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($param['type'] == 'texte'): ?>
                            <input type="text" class="form-control" 
                                   name="settings[<?php echo htmlspecialchars($param['cle']); ?>]"
                                   value="<?php echo htmlspecialchars($param['valeur'] ?? ''); ?>"
                                   <?php echo !$param['modifiable'] ? 'readonly' : ''; ?>>
                            
                            <?php elseif ($param['type'] == 'nombre'): ?>
                            <input type="number" class="form-control" 
                                   name="settings[<?php echo htmlspecialchars($param['cle']); ?>]"
                                   value="<?php echo htmlspecialchars($param['valeur'] ?? ''); ?>"
                                   <?php echo !$param['modifiable'] ? 'readonly' : ''; ?>>
                            
                            <?php elseif ($param['type'] == 'booleen'): ?>
                            <select class="form-select" name="settings[<?php echo htmlspecialchars($param['cle']); ?>]"
                                    <?php echo !$param['modifiable'] ? 'disabled' : ''; ?>>
                                <option value="1" <?php echo ($param['valeur'] ?? '') == '1' ? 'selected' : ''; ?>>Activé</option>
                                <option value="0" <?php echo ($param['valeur'] ?? '') == '0' ? 'selected' : ''; ?>>Désactivé</option>
                            </select>
                            
                            <?php elseif ($param['type'] == 'couleur'): ?>
                            <input type="color" class="form-control" 
                                   name="settings[<?php echo htmlspecialchars($param['cle']); ?>]"
                                   value="<?php echo htmlspecialchars($param['valeur'] ?? '#4361ee'); ?>"
                                   <?php echo !$param['modifiable'] ? 'readonly' : ''; ?>>
                            
                            <?php else: ?>
                            <textarea class="form-control" rows="2"
                                      name="settings[<?php echo htmlspecialchars($param['cle']); ?>]"
                                      <?php echo !$param['modifiable'] ? 'readonly' : ''; ?>><?php echo htmlspecialchars($param['valeur'] ?? ''); ?></textarea>
                            <?php endif; ?>
                            
                            <?php if ($param['modifiable']): ?>
                            <small class="text-muted">Clé: <code><?php echo htmlspecialchars($param['cle']); ?></code></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                                </div>
                            </div>
                    
                    <div class="mt-4">
                        <button type="submit" name="update_settings" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i>Enregistrer les paramètres
                        </button>
                        <button type="reset" class="btn btn-secondary ms-2">Réinitialiser</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Onglet Sauvegardes -->
    <div class="tab-pane fade" id="backup" role="tabpanel">
        <div class="row">
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">
                            <i class="fas fa-database me-2"></i>
                            Sauvegarde manuelle
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Créez une sauvegarde complète de la base de données.
                        </div>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Type de sauvegarde</label>
                                <select class="form-select" name="backup_type">
                                    <option value="complet">Sauvegarde complète</option>
                                    <option value="structure">Structure seule</option>
                                    <option value="donnees">Données seulement</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Options</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="compress" id="compress">
                                    <label class="form-check-label" for="compress">
                                        Compresser la sauvegarde (gzip)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="email_backup" id="email_backup">
                                    <label class="form-check-label" for="email_backup">
                                        Envoyer par email
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" name="run_backup" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Créer une sauvegarde
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            Historique des sauvegardes
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshBackups()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Taille</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($backup['created_at']))); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $backup['backup_type'] == 'auto' ? 'success' : 'primary'; ?>">
                                                <?php echo htmlspecialchars($backup['backup_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($backup['size_mb']); ?> MB</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if (file_exists('../backups/' . $backup['filename'])): ?>
                                                <a href="../backups/<?php echo htmlspecialchars($backup['filename']); ?>" 
                                                   class="btn btn-outline-primary" title="Télécharger" download>
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <?php else: ?>
                                                <button type="button" class="btn btn-outline-secondary" disabled title="Fichier non trouvé">
                                                    <i class="fas fa-exclamation-circle"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-outline-success" 
                                                        onclick="restoreBackup('<?php echo htmlspecialchars($backup['filename']); ?>')" title="Restaurer">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="deleteBackup('<?php echo htmlspecialchars($backup['filename']); ?>')" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($backups)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <i class="fas fa-database fa-2x text-muted mb-3"></i>
                                            <p class="text-muted">Aucune sauvegarde trouvée</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Sauvegardes automatiques
                        </h6>
                    </div>
                    <div class="card-body">
                        <form id="autoBackupForm">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Fréquence</label>
                                    <select class="form-select" name="auto_backup_frequency">
                                        <option value="daily">Quotidienne</option>
                                        <option value="weekly">Hebdomadaire</option>
                                        <option value="monthly">Mensuelle</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Heure d'exécution</label>
                                    <input type="time" class="form-control" name="auto_backup_time" value="02:00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Conserver pendant</label>
                                    <select class="form-select" name="auto_backup_retention">
                                        <option value="7">7 jours</option>
                                        <option value="30" selected>30 jours</option>
                                        <option value="90">90 jours</option>
                                        <option value="365">1 an</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="button" class="btn btn-primary" onclick="saveAutoBackupSettings()">
                                    <i class="fas fa-save me-1"></i>Enregistrer les paramètres
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Onglet Logs système -->
    <div class="tab-pane fade" id="logs" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-clipboard-list me-2"></i>
                    Logs système récents
                </h6>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="refreshLogs()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" 
                            onclick="clearLogs()">
                        <i class="fas fa-trash"></i> Effacer les logs
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date/Heure</th>
                                <th>Utilisateur</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>Description</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date('d/m H:i', strtotime($log['created_at']))); ?></td>
                                <td>
                                    <?php if ($log['user_id']): ?>
                                    <span class="small"><?php echo htmlspecialchars(($log['prenom'] ?? '') . ' ' . ($log['nom'] ?? '')); ?></span>
                                    <?php else: ?>
                                    <span class="text-muted small">Système</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $log['action'] == 'CREATE' ? 'success' : 
                                             ($log['action'] == 'UPDATE' ? 'warning' : 
                                             ($log['action'] == 'DELETE' ? 'danger' : 'info'));
                                    ?>">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td><code><?php echo htmlspecialchars($log['table_name']); ?></code></td>
                                <td>
                                    <span class="small" title="<?php echo htmlspecialchars($log['description'] ?? ''); ?>">
                                        <?php echo htmlspecialchars(substr($log['description'] ?? '', 0, 50)); ?>...
                                    </span>
                                </td>
                                <td><span class="text-muted small"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($recent_logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-clipboard-list fa-2x text-muted mb-3"></i>
                                    <p class="text-muted">Aucun log système récent</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-top">
                <a href="logs.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-list me-1"></i>Voir tous les logs
                </a>
            </div>
        </div>
    </div>
    
    <!-- Onglet Maintenance -->
    <div class="tab-pane fade" id="maintenance" role="tabpanel">
        <div class="row">
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">
                            <i class="fas fa-tools me-2"></i>
                            Outils de maintenance
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                    onclick="optimizeDatabase()">
                                <div>
                                    <i class="fas fa-magic me-2 text-primary"></i>
                                    <strong>Optimiser la base de données</strong>
                                    <div class="small text-muted">Défragmenter et optimiser les tables</div>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            
                            <form method="POST" class="d-inline">
                                <button type="submit" name="clear_cache" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center w-100 text-start">
                                    <div>
                                        <i class="fas fa-broom me-2 text-success"></i>
                                        <strong>Nettoyer le cache</strong>
                                        <div class="small text-muted">Supprimer les fichiers temporaires</div>
                                    </div>
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </form>
                            
                            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                    onclick="rebuildIndexes()">
                                <div>
                                    <i class="fas fa-search me-2 text-info"></i>
                                    <strong>Reconstruire les index</strong>
                                    <div class="small text-muted">Améliorer les performances de recherche</div>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            
                            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                    onclick="checkIntegrity()">
                                <div>
                                    <i class="fas fa-shield-alt me-2 text-warning"></i>
                                    <strong>Vérifier l'intégrité</strong>
                                    <div class="small text-muted">Vérifier la cohérence des données</div>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2 text-danger"></i>
                            Mode maintenance
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            Le mode maintenance bloque l'accès aux utilisateurs non-administrateurs.
                        </div>
                        
                        <form id="maintenanceForm">
                            <div class="mb-3">
                                <label class="form-label">Message de maintenance</label>
                                <textarea class="form-control" rows="3" name="maintenance_message" 
                                          placeholder="Le système est actuellement en maintenance. Veuillez réessayer plus tard."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Durée estimée</label>
                                <input type="text" class="form-control" name="maintenance_duration" 
                                       placeholder="Ex: 2 heures">
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" 
                                       id="maintenanceMode" onchange="toggleMaintenanceMode(this.checked)">
                                <label class="form-check-label fw-semibold" for="maintenanceMode">
                                    Activer le mode maintenance
                                </label>
                            </div>
                            
                            <div id="maintenanceSchedule" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Programmer la maintenance</label>
                                    <div class="row g-2">
                                        <div class="col">
                                            <input type="date" class="form-control" name="maintenance_date">
                                        </div>
                                        <div class="col">
                                            <input type="time" class="form-control" name="maintenance_time">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Performances système
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <div class="display-5 fw-bold text-primary" id="responseTime">0.12</div>
                                <small class="text-muted">Temps de réponse (s)</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="display-5 fw-bold text-success" id="memoryUsage">45</div>
                                <small class="text-muted">Mémoire utilisée (%)</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="display-5 fw-bold text-warning" id="cpuUsage">12</div>
                                <small class="text-muted">CPU utilisé (%)</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="display-5 fw-bold text-info" id="activeConnections">8</div>
                                <small class="text-muted">Connexions actives</small>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="runPerformanceTest()">
                                <i class="fas fa-play me-1"></i>Lancer un test de performance
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// Rafraîchir les informations système
function refreshSystemInfo() {
    location.reload();
}

// Afficher les informations système détaillées
function showSystemInfo() {
    alert('Informations système détaillées...\n' +
          'PHP Version: <?php echo phpversion(); ?>\n' +
          'Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu'; ?>');
}

// Optimiser la base de données
async function optimizeDatabase() {
    if (confirm('Optimiser la base de données ? Cette opération peut prendre quelques minutes.')) {
        try {
            const response = await fetch('ajax/optimize_database.php');
            const result = await response.json();
            
            if (result.success) {
                alert('Base de données optimisée avec succès');
            } else {
                alert('Erreur: ' + result.message);
            }
        } catch (error) {
            alert('Erreur lors de l\'optimisation');
        }
    }
}

// Nettoyer les notifications
async function clearNotifications() {
    if (confirm('Effacer toutes les notifications non lues ?')) {
        try {
            const response = await fetch('ajax/clear_notifications.php');
            const result = await response.json();
            
            if (result.success) {
                alert('Notifications effacées avec succès');
                location.reload();
            }
        } catch (error) {
            alert('Erreur lors du nettoyage des notifications');
        }
    }
}

// Restaurer une sauvegarde
function restoreBackup(filename) {
    if (confirm('ATTENTION : Restaurer cette sauvegarde écrasera toutes les données actuelles. Continuer ?')) {
        if (confirm('Êtes-vous ABSOLUMENT SÛR ? Cette action est irréversible.')) {
            window.location.href = `restore_backup.php?file=${encodeURIComponent(filename)}`;
        }
    }
}

// Supprimer une sauvegarde
async function deleteBackup(filename) {
    if (confirm('Supprimer cette sauvegarde ?')) {
        try {
            const response = await fetch(`ajax/delete_backup.php?file=${encodeURIComponent(filename)}`);
            const result = await response.json();
            
            if (result.success) {
                alert('Sauvegarde supprimée avec succès');
                location.reload();
            } else {
                alert('Erreur: ' + result.message);
            }
        } catch (error) {
            alert('Erreur lors de la suppression');
        }
    }
}

// Sauvegarder les paramètres de sauvegarde automatique
async function saveAutoBackupSettings() {
    const formData = new FormData(document.getElementById('autoBackupForm'));
    
    try {
        const response = await fetch('ajax/save_auto_backup.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Paramètres sauvegardés avec succès');
        } else {
            alert('Erreur: ' + result.message);
        }
    } catch (error) {
        alert('Erreur lors de la sauvegarde');
    }
}

// Rafraîchir les logs
function refreshLogs() {
    location.reload();
}

// Effacer les logs
async function clearLogs() {
    if (confirm('Effacer tous les logs système ? Cette action est irréversible.')) {
        try {
            const response = await fetch('ajax/clear_logs.php');
            const result = await response.json();
            
            if (result.success) {
                alert('Logs effacés avec succès');
                location.reload();
            }
        } catch (error) {
            alert('Erreur lors du nettoyage des logs');
        }
    }
}

// Reconstruire les index
async function rebuildIndexes() {
    if (confirm('Reconstruire tous les index de la base de données ?')) {
        try {
            const response = await fetch('ajax/rebuild_indexes.php');
            const result = await response.json();
            
            if (result.success) {
                alert('Index reconstruits avec succès');
            } else {
                alert('Erreur: ' + result.message);
            }
        } catch (error) {
            alert('Erreur lors de la reconstruction des index');
        }
    }
}

// Vérifier l'intégrité
async function checkIntegrity() {
    try {
        const response = await fetch('ajax/check_integrity.php');
        const result = await response.json();
        
        if (result.success) {
            alert('Vérification d\'intégrité terminée : ' + result.message);
        } else {
            alert('Problèmes détectés : ' + result.message);
        }
    } catch (error) {
        alert('Erreur lors de la vérification');
    }
}

// Basculer le mode maintenance
function toggleMaintenanceMode(enabled) {
    const schedule = document.getElementById('maintenanceSchedule');
    schedule.style.display = enabled ? 'block' : 'none';
    
    if (enabled) {
        if (!confirm('Activer le mode maintenance ? Les utilisateurs non-administrateurs ne pourront plus se connecter.')) {
            document.getElementById('maintenanceMode').checked = false;
            schedule.style.display = 'none';
            return;
        }
    }
    
    // Envoyer la requête AJAX
    fetch('ajax/toggle_maintenance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ enabled: enabled })
    });
}

// Lancer un test de performance
async function runPerformanceTest() {
    const startTime = performance.now();
    
    try {
        const response = await fetch('ajax/performance_test.php');
        const endTime = performance.now();
        
        const responseTime = (endTime - startTime).toFixed(2);
        document.getElementById('responseTime').textContent = (responseTime / 1000).toFixed(2);
        
        // Simuler d'autres métriques
        document.getElementById('memoryUsage').textContent = Math.floor(Math.random() * 30 + 20);
        document.getElementById('cpuUsage').textContent = Math.floor(Math.random() * 20 + 10);
        document.getElementById('activeConnections').textContent = Math.floor(Math.random() * 10 + 5);
        
        alert(`Test de performance terminé\nTemps de réponse: ${(responseTime / 1000).toFixed(2)}s`);
    } catch (error) {
        alert('Erreur lors du test de performance');
    }
}

// Mettre à jour les métriques en temps réel
function updateMetrics() {
    // Simuler des métriques dynamiques
    setInterval(() => {
        const memory = parseInt(document.getElementById('memoryUsage').textContent);
        const cpu = parseInt(document.getElementById('cpuUsage').textContent);
        const connections = parseInt(document.getElementById('activeConnections').textContent);
        
        // Petites variations aléatoires
        document.getElementById('memoryUsage').textContent = Math.max(20, Math.min(80, memory + (Math.random() * 4 - 2)));
        document.getElementById('cpuUsage').textContent = Math.max(5, Math.min(40, cpu + (Math.random() * 3 - 1.5)));
        document.getElementById('activeConnections').textContent = Math.max(3, Math.min(15, connections + (Math.random() * 2 - 1)));
    }, 5000);
}

// Initialiser
document.addEventListener('DOMContentLoaded', function() {
    updateMetrics();
    
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

.tab-content {
    padding-top: 1rem;
}

.list-group-item {
    border: 1px solid #e5e7eb;
    margin-bottom: 0.5rem;
    border-radius: 8px;
    transition: all 0.2s;
}

.list-group-item:hover {
    background-color: #f8fafc;
    border-color: #4361ee;
}

.bg-primary-light { background-color: rgba(67, 97, 238, 0.1); }
.bg-success-light { background-color: rgba(16, 185, 129, 0.1); }
.bg-warning-light { background-color: rgba(245, 158, 11, 0.1); }
.bg-danger-light { background-color: rgba(239, 68, 68, 0.1); }

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

.display-5 {
    font-size: 2.5rem;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>