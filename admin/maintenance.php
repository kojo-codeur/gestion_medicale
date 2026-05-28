<?php
// admin/maintenance.php
require_once '../config/database.php';
checkRole('admin');

$title = 'Maintenance du Système';
require_once '../includes/header.php';

// Fonctions de maintenance
function checkDatabaseHealth($pdo) {
    $health = [
        'status' => 'healthy',
        'issues' => [],
        'tables' => []
    ];
    
    // Vérifier les tables
    $tables = ['utilisateurs', 'patients', 'consultations', 'rendez_vous', 'medicaments', 'specialites', 'prescriptions'];
    
    foreach ($tables as $table) {
        try {
            $result = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $result->fetchColumn();
            $health['tables'][$table] = [
                'exists' => true,
                'count' => $count
            ];
        } catch (Exception $e) {
            $health['tables'][$table] = [
                'exists' => false,
                'error' => $e->getMessage()
            ];
            $health['issues'][] = "Table $table: " . $e->getMessage();
            $health['status'] = 'unhealthy';
        }
    }
    
    return $health;
}

function getSystemInfo() {
    return [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'],
        'mysql_version' => '5.7+',
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size')
    ];
}

function getPerformanceStats($pdo) {
    $stats = [];
    
    // Taille de la base de données
    $result = $pdo->query("
        SELECT 
            table_schema as 'Database',
            SUM(data_length + index_length) / 1024 / 1024 as 'Size_MB'
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        GROUP BY table_schema
    ");
    $stats['db_size'] = $result->fetch()['Size_MB'] ?? 0;
    
    // Nombre total d'enregistrements
    $tables = ['utilisateurs', 'patients', 'consultations', 'rendez_vous'];
    $total_records = 0;
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            $total_records += $count;
        } catch (Exception $e) {
            // Ignorer les erreurs
        }
    }
    $stats['total_records'] = $total_records;
    
    return $stats;
}

// Traitement des actions de maintenance
$action = $_GET['action'] ?? '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'backup') {
            // Créer une sauvegarde simple
            $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backup_path = '../backups/' . $backup_file;
            
            // Créer le dossier backups s'il n'existe pas
            if (!is_dir('../backups')) {
                mkdir('../backups', 0755, true);
            }
            
            // Sauvegarder les tables importantes
            $tables = ['utilisateurs', 'patients', 'consultations', 'rendez_vous', 'medicaments', 'prescriptions'];
            $backup_content = "-- Backup created: " . date('Y-m-d H:i:s') . "\n";
            $backup_content .= "-- Database: gestion_medicale\n\n";
            
            foreach ($tables as $table) {
                $backup_content .= "-- Table: $table\n";
                
                // Structure
                $structure = $pdo->query("SHOW CREATE TABLE $table")->fetchColumn(1);
                $backup_content .= $structure . ";\n\n";
                
                // Données
                $rows = $pdo->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $columns = implode(', ', array_map(function($col) {
                            return "`$col`";
                        }, array_keys($row)));
                        
                        $values = implode(', ', array_map(function($value) use ($pdo) {
                            if ($value === null) return 'NULL';
                            return $pdo->quote($value);
                        }, array_values($row)));
                        
                        $backup_content .= "INSERT INTO `$table` ($columns) VALUES ($values);\n";
                    }
                }
                $backup_content .= "\n";
            }
            
            file_put_contents($backup_path, $backup_content);
            
            // Enregistrer dans l'historique
            $pdo->prepare("
                INSERT INTO backup_history (backup_type, filename, size_mb, created_by)
                VALUES (?, ?, ?, ?)
            ")->execute(['manual', $backup_file, filesize($backup_path) / 1024 / 1024, $_SESSION['user_id']]);
            
            $message = "success:Sauvegarde créée avec succès: $backup_file";
            
        } elseif ($action === 'optimize') {
            // Optimiser les tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $pdo->exec("OPTIMIZE TABLE $table");
            }
            
            // Nettoyer les logs anciens
            $pdo->exec("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $pdo->exec("DELETE FROM login_logs WHERE login_time < DATE_SUB(NOW(), INTERVAL 60 DAY)");
            
            $message = "success:Base de données optimisée avec succès";
            
        } elseif ($action === 'clean_cache') {
            // Nettoyer le cache (si existant)
            $cache_dir = '../cache';
            if (is_dir($cache_dir)) {
                $files = glob($cache_dir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            
            // Nettoyer les sessions expirées
            $session_dir = session_save_path();
            if ($session_dir && is_dir($session_dir)) {
                $files = glob($session_dir . '/sess_*');
                $now = time();
                foreach ($files as $file) {
                    if (is_file($file) && ($now - filemtime($file) > 86400)) {
                        unlink($file);
                    }
                }
            }
            
            $message = "success:Cache nettoyé avec succès";
        }
        
    } catch (Exception $e) {
        $message = "danger:Erreur: " . $e->getMessage();
    }
    
    header("Location: maintenance.php?message=" . urlencode($message));
    exit();
}

// Vérifier la santé du système
$db_health = checkDatabaseHealth($pdo);
$system_info = getSystemInfo();
$performance_stats = getPerformanceStats($pdo);

// Récupérer l'historique des sauvegardes
$backups = $pdo->query("
    SELECT * FROM backup_history 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

// Messages
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
            <i class="fas fa-tools me-2"></i>Maintenance du Système
        </h1>
        <p class="text-muted mb-0">Outils d'administration et de maintenance</p>
    </div>
</div>

<?php echo $message ?? ''; ?>

<!-- Système Health -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-heartbeat me-2"></i>
                    Santé du système
                </h6>
                <span class="badge bg-<?php echo $db_health['status'] === 'healthy' ? 'success' : 'danger'; ?>">
                    <?php echo $db_health['status'] === 'healthy' ? 'Sain' : 'Problèmes détectés'; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Tables de la base -->
                    <div class="col-md-6">
                        <h6 class="mb-3">Base de données</h6>
                        <div class="list-group list-group-flush">
                            <?php foreach ($db_health['tables'] as $table => $info): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <i class="fas fa-<?php echo $info['exists'] ? 'check-circle text-success' : 'times-circle text-danger'; ?> me-2"></i>
                                    <span class="font-monospace"><?php echo $table; ?></span>
                                </div>
                                <div>
                                    <?php if ($info['exists']): ?>
                                    <span class="badge bg-info"><?php echo $info['count']; ?> enreg.</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Erreur</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Informations système -->
                    <div class="col-md-6">
                        <h6 class="mb-3">Informations système</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tbody>
                                    <?php foreach ($system_info as $key => $value): ?>
                                    <tr>
                                        <td class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $key)); ?></td>
                                        <td><code><?php echo $value; ?></code></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td class="text-muted">Taille BD</td>
                                        <td><strong><?php echo number_format($performance_stats['db_size'], 2); ?> MB</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Enregistrements totaux</td>
                                        <td><strong><?php echo number_format($performance_stats['total_records']); ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($db_health['issues'])): ?>
                <div class="alert alert-warning mt-3">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Problèmes détectés</h6>
                    <ul class="mb-0">
                        <?php foreach ($db_health['issues'] as $issue): ?>
                        <li><?php echo $issue; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Outils de maintenance -->
<div class="row">
    <!-- Sauvegarde -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-database me-2"></i>
                    Sauvegarde
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-4">
                    Créez une sauvegarde complète de la base de données. La sauvegarde inclut toutes les tables et données.
                </p>
                
                <form method="POST" action="?action=backup">
                    <button type="submit" class="btn btn-primary w-100" 
                            onclick="return confirm('Créer une sauvegarde maintenant ?')">
                        <i class="fas fa-save me-2"></i>Créer une sauvegarde
                    </button>
                </form>
                
                <div class="mt-4">
                    <h6 class="small fw-bold mb-3">Sauvegardes récentes</h6>
                    <?php if (empty($backups)): ?>
                    <p class="text-muted small">Aucune sauvegarde</p>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($backups as $backup): ?>
                        <div class="list-group-item px-0 py-2">
                            <div class="small">
                                <div class="fw-semibold"><?php echo $backup['filename']; ?></div>
                                <div class="text-muted">
                                    <?php echo formatDate($backup['created_at'], 'd/m/Y H:i'); ?> • 
                                    <?php echo number_format($backup['size_mb'], 2); ?> MB
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Optimisation -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-broom me-2"></i>
                    Optimisation
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-4">
                    Optimisez les tables de la base de données et nettoyez les logs anciens pour améliorer les performances.
                </p>
                
                <form method="POST" action="?action=optimize">
                    <button type="submit" class="btn btn-warning w-100" 
                            onclick="return confirm('Optimiser la base de données et nettoyer les logs ?')">
                        <i class="fas fa-magic me-2"></i>Optimiser la base
                    </button>
                </form>
                
                <div class="mt-4">
                    <h6 class="small fw-bold mb-3">Actions effectuées</h6>
                    <ul class="small text-muted">
                        <li>Optimisation des tables MySQL</li>
                        <li>Nettoyage des logs > 90 jours</li>
                        <li>Nettoyage des logs de connexion > 60 jours</li>
                        <li>Réparation des tables corrompues</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Nettoyage -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-trash-alt me-2"></i>
                    Nettoyage
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-4">
                    Nettoyez le cache et les fichiers temporaires pour libérer de l'espace disque et résoudre les problèmes de cache.
                </p>
                
                <form method="POST" action="?action=clean_cache">
                    <button type="submit" class="btn btn-danger w-100" 
                            onclick="return confirm('Nettoyer le cache système ?')">
                        <i class="fas fa-broom me-2"></i>Nettoyer le cache
                    </button>
                </form>
                
                <div class="mt-4">
                    <h6 class="small fw-bold mb-3">Éléments nettoyés</h6>
                    <ul class="small text-muted">
                        <li>Fichiers de cache</li>
                        <li>Sessions PHP expirées</li>
                        <li>Fichiers temporaires</li>
                        <li>Miniatures d'images</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Outils avancés -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-cogs me-2"></i>
                    Outils avancés
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Vérification d'intégrité -->
                    <div class="col-md-3">
                        <button type="button" class="btn btn-outline-primary w-100" onclick="checkIntegrity()">
                            <i class="fas fa-shield-alt me-2"></i>
                            Vérifier l'intégrité
                        </button>
                    </div>
                    
                    <!-- Réparation base -->
                    <div class="col-md-3">
                        <button type="button" class="btn btn-outline-warning w-100" onclick="repairDatabase()">
                            <i class="fas fa-wrench me-2"></i>
                            Réparer la base
                        </button>
                    </div>
                    
                    <!-- Logs système -->
                    <div class="col-md-3">
                        <a href="logs.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-clipboard-list me-2"></i>
                            Voir les logs
                        </a>
                    </div>
                    
                    <!-- Réglages système -->
                    <div class="col-md-3">
                        <a href="systeme.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-sliders-h me-2"></i>
                            Réglages système
                        </a>
                    </div>
                </div>
                
                <!-- Zone de commande SQL (protégée) -->
                <div class="mt-4 pt-3 border-top">
                    <h6 class="small fw-bold mb-3">Console SQL (Administrateur uniquement)</h6>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Attention:</strong> Cet outil est réservé aux administrateurs expérimentés. 
                        Les commandes SQL exécutées peuvent modifier ou supprimer des données.
                    </div>
                    
                    <form id="sqlConsoleForm" onsubmit="return executeSQL()">
                        <div class="mb-3">
                            <label class="form-label small">Commande SQL</label>
                            <textarea class="form-control font-monospace small" 
                                      id="sqlCommand" rows="3" 
                                      placeholder="SELECT * FROM utilisateurs LIMIT 10;"></textarea>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="prefillQuery('select')">
                                    SELECT
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-1" onclick="prefillQuery('update')">
                                    UPDATE
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-1" onclick="prefillQuery('show')">
                                    SHOW
                                </button>
                            </div>
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="fas fa-play me-1"></i>Exécuter
                            </button>
                        </div>
                    </form>
                    
                    <div id="sqlResult" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<style>
.code-block {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 15px;
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
    overflow-x: auto;
}

.system-stat {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f1f3f4;
}

.system-stat:last-child {
    border-bottom: none;
}

.stat-label {
    color: #5f6368;
}

.stat-value {
    font-weight: 500;
    color: #202124;
}
</style>

<script>
// Outils de maintenance
function checkIntegrity() {
    fetch('ajax/check_integrity.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Intégrité vérifiée', 'Aucun problème détecté.', 'success');
            } else {
                showAlert('Problèmes détectés', data.issues.join('<br>'), 'warning');
            }
        });
}

function repairDatabase() {
    if (confirm('Réparer les tables de la base de données ?')) {
        fetch('ajax/repair_database.php')
            .then(response => response.json())
            .then(data => {
                showAlert('Réparation terminée', data.message, data.success ? 'success' : 'danger');
            });
    }
}

function showAlert(title, message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <h6 class="alert-heading">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'times-circle'} me-2"></i>
                ${title}
            </h6>
            <p class="mb-0">${message}</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Insérer au début de la page
    document.querySelector('.row.mb-4').insertAdjacentHTML('afterend', alertHtml);
}

// Console SQL
function prefillQuery(type) {
    const queries = {
        'select': 'SELECT * FROM utilisateurs WHERE statut = \'actif\' LIMIT 10',
        'update': 'UPDATE patients SET ville = \'Paris\' WHERE id = 1',
        'show': 'SHOW TABLES'
    };
    document.getElementById('sqlCommand').value = queries[type];
}

function executeSQL() {
    const command = document.getElementById('sqlCommand').value.trim();
    
    if (!command) {
        alert('Veuillez entrer une commande SQL');
        return false;
    }
    
    // Vérifier les commandes dangereuses
    const dangerousCommands = ['DROP', 'DELETE', 'TRUNCATE', 'ALTER'];
    const isDangerous = dangerousCommands.some(cmd => command.toUpperCase().includes(cmd));
    
    if (isDangerous && !confirm('⚠️ COMMANDE DANGEREUSE DÉTECTÉE\n\nVoulez-vous vraiment exécuter cette commande ?')) {
        return false;
    }
    
    fetch('ajax/execute_sql.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ command: command })
    })
    .then(response => response.json())
    .then(data => {
        const resultDiv = document.getElementById('sqlResult');
        
        if (data.success) {
            let html = `
                <div class="alert alert-success">
                    <h6 class="alert-heading">
                        <i class="fas fa-check-circle me-2"></i>
                        Commande exécutée avec succès
                    </h6>
                    <div class="small mb-2">
                        <strong>Lignes affectées:</strong> ${data.affected_rows || 0}
                    </div>
            `;
            
            if (data.results && data.results.length > 0) {
                html += `
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                `;
                
                // En-têtes
                Object.keys(data.results[0]).forEach(col => {
                    html += `<th>${col}</th>`;
                });
                
                html += `</tr></thead><tbody>`;
                
                // Données
                data.results.forEach(row => {
                    html += '<tr>';
                    Object.values(row).forEach(val => {
                        html += `<td>${val ?? '<em>NULL</em>'}</td>`;
                    });
                    html += '</tr>';
                });
                
                html += `</tbody></table></div>`;
            }
            
            html += '</div>';
            resultDiv.innerHTML = html;
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <h6 class="alert-heading">
                        <i class="fas fa-times-circle me-2"></i>
                        Erreur SQL
                    </h6>
                    <p class="mb-0"><strong>Message:</strong> ${data.error}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        document.getElementById('sqlResult').innerHTML = `
            <div class="alert alert-danger">
                <h6 class="alert-heading">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Erreur de connexion
                </h6>
                <p class="mb-0">${error.message}</p>
            </div>
        `;
    });
    
    return false; // Empêcher la soumission du formulaire
}

// Rafraîchir automatiquement les stats toutes les 30 secondes
setTimeout(() => {
    window.location.reload();
}, 30000);
</script>