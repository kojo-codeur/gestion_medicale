<?php
// admin/logs.php - Gestion des logs système
require_once '../config/database.php';
checkRole('assistant');

$title = 'Journaux système';
require_once '../includes/header.php';

// Paramètres de filtrage
$filters = [
    'type' => $_GET['type'] ?? 'all',
    'user' => $_GET['user'] ?? '',
    'role' => $_GET['role'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? '',
    'page' => $_GET['page'] ?? 1
];

// Types de logs disponibles
$logTypes = [
    'all' => 'Tous les logs',
    'login' => 'Connexions',
    'audit' => 'Audit',
    'system' => 'Système',
    'error' => 'Erreurs',
    'security' => 'Sécurité'
];

// Rôles disponibles
$roles = ['admin', 'docteur', 'secretaire', 'assistant'];

// Récupérer les utilisateurs pour le filtre
$userQuery = "SELECT id, CONCAT(prenom, ' ', nom) as name, role 
              FROM utilisateurs 
              WHERE statut = 'actif' 
              ORDER BY role, nom";
$users = $pdo->query($userQuery)->fetchAll();

// Construire la requête SQL
$sql = "
    SELECT 
        l.*,
        u.nom as user_nom,
        u.prenom as user_prenom,
        u.role as user_role,
        u.email as user_email
    FROM audit_logs l
    LEFT JOIN utilisateurs u ON l.user_id = u.id
    WHERE 1=1
";

$params = [];

// Appliquer les filtres
if ($filters['type'] !== 'all') {
    if ($filters['type'] === 'login') {
        $sql .= " AND l.table_name IN ('login_logs', 'sessions')";
    } elseif ($filters['type'] === 'audit') {
        $sql .= " AND l.table_name NOT IN ('login_logs', 'sessions')";
    } elseif ($filters['type'] === 'security') {
        $sql .= " AND l.action IN ('login_failed', 'password_change', 'permission_denied', 'role_change')";
    } elseif ($filters['type'] === 'error') {
        $sql .= " AND (l.action LIKE '%error%' OR l.action LIKE '%failed%')";
    } elseif ($filters['type'] === 'system') {
        $sql .= " AND l.table_name IN ('system_logs', 'maintenance', 'backup')";
    }
}

if ($filters['user']) {
    $sql .= " AND l.user_id = ?";
    $params[] = $filters['user'];
}

if ($filters['role']) {
    $sql .= " AND u.role = ?";
    $params[] = $filters['role'];
}

if ($filters['date_from']) {
    $sql .= " AND DATE(l.created_at) >= ?";
    $params[] = $filters['date_from'];
}

if ($filters['date_to']) {
    $sql .= " AND DATE(l.created_at) <= ?";
    $params[] = $filters['date_to'];
}

if ($filters['search']) {
    $sql .= " AND (
        l.action LIKE ? OR 
        l.table_name LIKE ? OR 
        l.ip_address LIKE ? OR 
        u.nom LIKE ? OR 
        u.prenom LIKE ? OR
        l.description LIKE ? OR
        l.details LIKE ?
    )";
    $searchTerm = "%{$filters['search']}%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}

// Ordre et pagination
$sql .= " ORDER BY l.created_at DESC";

// Pagination
$limit = 50;
$offset = ($filters['page'] - 1) * $limit;
$sql_with_limit = $sql . " LIMIT $limit OFFSET $offset";

// Exécuter la requête
$stmt = $pdo->prepare($sql_with_limit);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Compter le total pour la pagination
$countSql = "SELECT COUNT(*) as total FROM audit_logs l 
             LEFT JOIN utilisateurs u ON l.user_id = u.id 
             WHERE 1=1";
if ($filters['type'] !== 'all') {
    $wherePos = strpos($sql, 'WHERE');
    if ($wherePos !== false) {
        $whereClause = substr($sql, $wherePos);
        $countSql .= " " . substr($whereClause, strpos($whereClause, 'AND'));
    }
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalResult = $countStmt->fetch();
$totalLogs = $totalResult['total'] ?? 0;
$totalPages = ceil($totalLogs / $limit);

// Statistiques des logs
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT ip_address) as unique_ips
    FROM audit_logs 
    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
";

$statsStmt = $pdo->prepare($statsQuery);
$statsStmt->execute();
$statsResult = $statsStmt->fetch();
$stats = $statsResult ?: ['total' => 0, 'unique_users' => 0, 'unique_ips' => 0];

// Statistiques par rôle
$roleStats = $pdo->query("
    SELECT 
        u.role,
        COUNT(l.id) as log_count,
        COUNT(DISTINCT l.user_id) as user_count
    FROM audit_logs l
    LEFT JOIN utilisateurs u ON l.user_id = u.id
    WHERE l.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY u.role
    ORDER BY log_count DESC
")->fetchAll();

// Actions critiques récentes
$criticalActions = $pdo->query("
    SELECT l.*, u.nom, u.prenom 
    FROM audit_logs l
    LEFT JOIN utilisateurs u ON l.user_id = u.id
    WHERE l.action IN ('delete', 'password_change', 'role_change', 'permission_change')
    ORDER BY l.created_at DESC
    LIMIT 10
")->fetchAll();

// Fonction pour déterminer la couleur selon l'action
function getActionColor($action) {
    $action = strtolower($action);
    if (strpos($action, 'delete') !== false) return 'danger';
    if (strpos($action, 'create') !== false) return 'success';
    if (strpos($action, 'update') !== false) return 'primary';
    if (strpos($action, 'login') !== false) return 'info';
    if (strpos($action, 'error') !== false) return 'warning';
    if (strpos($action, 'failed') !== false) return 'warning';
    return 'secondary';
}

// Fonction pour déterminer la couleur selon le rôle
function getRoleColor($role) {
    switch ($role) {
        case 'admin': return 'danger';
        case 'docteur': return 'primary';
        case 'secretaire': return 'success';
        case 'assistant': return 'warning';
        default: return 'secondary';
    }
}

// Fonction pour formater l'action
function formatAction($action) {
    $action = strtolower($action);
    $translations = [
        'create' => 'Création',
        'update' => 'Modification',
        'delete' => 'Suppression',
        'login' => 'Connexion',
        'logout' => 'Déconnexion',
        'read' => 'Consultation',
        'password_change' => 'Changement MDP',
        'role_change' => 'Changement rôle',
        'permission_change' => 'Changement permission'
    ];
    
    foreach ($translations as $key => $translation) {
        if (strpos($action, $key) !== false) {
            return $translation;
        }
    }
    
    return ucfirst(str_replace('_', ' ', $action));
}

// Fonction pour construire l'URL de pagination
function buildPaginationUrl($filters, $page) {
    $params = [];
    foreach ($filters as $key => $value) {
        if ($key === 'page') continue;
        if (!empty($value)) {
            $params[] = $key . '=' . urlencode($value);
        }
    }
    $params[] = 'page=' . $page;
    return implode('&', $params);
}

// Fonction pour tronquer un texte
function truncate($text, $length = 50) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}
?>

<!-- Content Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-clipboard-list me-2"></i>Journaux système
        </h1>
        <p class="text-muted mb-0">Surveillance et historique des activités système</p>
    </div>
    <div class="btn-toolbar">
        <button class="btn btn-outline-primary me-2" onclick="exportLogs()">
            <i class="fas fa-download me-1"></i>Exporter
        </button>
        <button class="btn btn-danger" onclick="clearOldLogs()">
            <i class="fas fa-trash-alt me-1"></i>Nettoyer
        </button>
    </div>
</div>

<!-- Alertes importantes -->
<?php if (!empty($criticalActions)): ?>
<div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
    <div class="d-flex align-items-center">
        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
        <div>
            <h5 class="alert-heading mb-1">Actions critiques détectées</h5>
            <p class="mb-0">
                <?php echo count($criticalActions); ?> actions critiques ont été enregistrées récemment. 
                <a href="#critical-actions" class="alert-link">Voir les détails</a>
            </p>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-primary border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total des logs</div>
                        <div class="h3 mb-0"><?php echo number_format($totalLogs); ?></div>
                    </div>
                    <div class="rounded-circle bg-primary-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px; background-color: rgba(67, 97, 238, 0.1);">
                        <i class="fas fa-database text-primary fa-lg"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <small class="text-muted">Filtrés selon critères</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-success border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Utilisateurs uniques</div>
                        <div class="h3 mb-0"><?php echo $stats['unique_users']; ?></div>
                    </div>
                    <div class="rounded-circle bg-success-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px; background-color: rgba(40, 167, 69, 0.1);">
                        <i class="fas fa-users text-success fa-lg"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <small class="text-muted">Utilisateurs ayant généré des logs</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-warning border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">IPs uniques</div>
                        <div class="h3 mb-0"><?php echo $stats['unique_ips']; ?></div>
                    </div>
                    <div class="rounded-circle bg-warning-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px; background-color: rgba(255, 193, 7, 0.1);">
                        <i class="fas fa-network-wired text-warning fa-lg"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <small class="text-muted">Adresses IP distinctes</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-info border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Logs aujourd'hui</div>
                        <div class="h3 mb-0">
                            <?php
                            $today = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
                            echo number_format($today);
                            ?>
                        </div>
                    </div>
                    <div class="rounded-circle bg-info-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px; background-color: rgba(23, 162, 184, 0.1);">
                        <i class="fas fa-calendar-day text-info fa-lg"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-info"><?php echo date('d/m/Y'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques par rôle -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Répartition par rôle (30 derniers jours)</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($roleStats as $roleStat): 
                        $role = $roleStat['role'] ?? 'Non attribué';
                        $count = $roleStat['log_count'];
                        $userCount = $roleStat['user_count'];
                        $roleColor = getRoleColor($role);
                        $percentage = $totalLogs > 0 ? round(($count / $totalLogs) * 100, 1) : 0;
                    ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card border-<?php echo $roleColor; ?> border-2 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small"><?php echo ucfirst($role); ?></div>
                                        <div class="h4 mb-1"><?php echo number_format($count); ?></div>
                                        <div class="small">
                                            <span class="badge bg-<?php echo $roleColor; ?>">
                                                <?php echo $userCount; ?> utilisateur(s)
                                            </span>
                                        </div>
                                    </div>
                                    <div class="rounded-circle bg-<?php echo $roleColor; ?>-light d-flex align-items-center justify-content-center" 
                                         style="width: 50px; height: 50px; background-color: <?php echo $roleColor === 'danger' ? 'rgba(220, 53, 69, 0.1)' : ($roleColor === 'primary' ? 'rgba(13, 110, 253, 0.1)' : ($roleColor === 'success' ? 'rgba(25, 135, 84, 0.1)' : 'rgba(255, 193, 7, 0.1)')); ?>">
                                        <i class="fas fa-user text-<?php echo $roleColor; ?> fa-lg"></i>
                                    </div>
                                </div>
                                <div class="progress mt-2" style="height: 5px;">
                                    <div class="progress-bar bg-<?php echo $roleColor; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $percentage; ?>%">
                                    </div>
                                </div>
                                <div class="mt-2 text-end">
                                    <small class="text-muted"><?php echo $percentage; ?>%</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0">
            <i class="fas fa-filter me-2"></i>Filtres de recherche
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" id="filterForm">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Type de log</label>
                    <select class="form-select" name="type">
                        <?php foreach ($logTypes as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $filters['type'] == $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Rôle</label>
                    <select class="form-select" name="role">
                        <option value="">Tous les rôles</option>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role; ?>" <?php echo $filters['role'] == $role ? 'selected' : ''; ?>>
                            <?php echo ucfirst($role); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Utilisateur</label>
                    <select class="form-select" name="user">
                        <option value="">Tous les utilisateurs</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $filters['user'] == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['name']); ?> (<?php echo $user['role']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Date début</label>
                    <input type="date" class="form-control" name="date_from" 
                           value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Date fin</label>
                    <input type="date" class="form-control" name="date_to" 
                           value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Recherche</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($filters['search']); ?>" 
                               placeholder="Rechercher...">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i>Appliquer
                            </button>
                            <a href="logs.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo me-1"></i>Réinitialiser
                            </a>
                        </div>
                        <div>
                            <small class="text-muted">
                                <?php echo number_format($totalLogs); ?> log(s) trouvé(s)
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste des logs -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="fas fa-list me-2"></i>Liste des journaux
            <?php if ($filters['type'] !== 'all'): ?>
            <span class="badge bg-primary ms-2"><?php echo $logTypes[$filters['type']]; ?></span>
            <?php endif; ?>
        </h6>
        <div>
            <div class="btn-group btn-group-sm">
                <a href="logs.php?<?php echo buildPaginationUrl($filters, $filters['page']); ?>" 
                   class="btn btn-outline-primary">
                    <i class="fas fa-sync-alt"></i>
                </a>
            </div>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="logsTable">
                <thead class="table-light">
                    <tr>
                        <th width="150">Date/Heure</th>
                        <th>Utilisateur/Rôle</th>
                        <th>Action</th>
                        <th>Table/Objet</th>
                        <th>IP</th>
                        <th>Détails</th>
                        <th width="100">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">Aucun log trouvé</h6>
                            <p class="text-muted small">Modifiez vos critères de recherche</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): 
                        $actionColor = getActionColor($log['action']);
                        $userRoleColor = getRoleColor($log['user_role']);
                    ?>
                    <tr class="log-row" data-log-id="<?php echo $log['id']; ?>">
                        <td>
                            <div class="small">
                                <div class="fw-semibold"><?php echo date('d/m/Y', strtotime($log['created_at'])); ?></div>
                                <div class="text-muted"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                            </div>
                        </td>
                        <td>
                            <?php if ($log['user_id']): ?>
                            <div class="d-flex align-items-center">
                                <div class="me-2">
                                    <span class="avatar-sm">
                                        <?php echo strtoupper(substr($log['user_prenom'], 0, 1) . substr($log['user_nom'], 0, 1)); ?>
                                    </span>
                                </div>
                                <div>
                                    <div class="fw-semibold small"><?php echo htmlspecialchars($log['user_prenom'] . ' ' . $log['user_nom']); ?></div>
                                    <small class="badge bg-<?php echo $userRoleColor; ?>">
                                        <?php echo $log['user_role']; ?>
                                    </small>
                                </div>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">Système</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $actionColor; ?>">
                                <?php echo formatAction($log['action']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="small">
                                <div class="fw-semibold"><?php echo htmlspecialchars($log['table_name'] ?? 'N/A'); ?></div>
                                <?php if ($log['record_id']): ?>
                                <div class="text-muted">ID: <?php echo $log['record_id']; ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="small">
                                <code><?php echo htmlspecialchars($log['ip_address']); ?></code>
                            </div>
                        </td>
                        <td>
                            <?php 
                            $details = $log['description'] ?? $log['details'] ?? '';
                            if (!empty($details)): ?>
                            <span class="small" title="<?php echo htmlspecialchars($details); ?>">
                                <?php echo truncate(htmlspecialchars($details), 50); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" 
                                        onclick="showLogDetails(<?php echo $log['id']; ?>)"
                                        title="Détails">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white border-top">
        <nav aria-label="Pagination">
            <ul class="pagination justify-content-center mb-0">
                <?php if ($filters['page'] > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?php echo buildPaginationUrl($filters, $filters['page'] - 1); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $filters['page'] - 2); $i <= min($totalPages, $filters['page'] + 2); $i++): ?>
                <li class="page-item <?php echo $i == $filters['page'] ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo buildPaginationUrl($filters, $i); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($filters['page'] < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?php echo buildPaginationUrl($filters, $filters['page'] + 1); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="text-center mt-2">
            <small class="text-muted">
                Page <?php echo $filters['page']; ?> sur <?php echo $totalPages; ?> 
                • <?php echo number_format($totalLogs); ?> log(s) au total
            </small>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Section actions critiques -->
<?php if (!empty($criticalActions)): ?>
<div class="card shadow-sm mt-4" id="critical-actions">
    <div class="card-header bg-danger text-white">
        <h6 class="mb-0">
            <i class="fas fa-shield-alt me-2"></i>Actions critiques récentes
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Utilisateur</th>
                        <th>Action</th>
                        <th>Table</th>
                        <th>IP</th>
                        <th>Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($criticalActions as $action): ?>
                    <tr>
                        <td class="small"><?php echo date('d/m H:i', strtotime($action['created_at'])); ?></td>
                        <td>
                            <span class="fw-semibold"><?php echo htmlspecialchars($action['prenom'] . ' ' . $action['nom']); ?></span>
                        </td>
                        <td>
                            <span class="badge bg-danger"><?php echo formatAction($action['action']); ?></span>
                        </td>
                        <td class="small"><?php echo htmlspecialchars($action['table_name']); ?></td>
                        <td class="small"><code><?php echo htmlspecialchars($action['ip_address']); ?></code></td>
                        <td class="small"><?php echo truncate(htmlspecialchars($action['description'] ?? $action['details'] ?? ''), 30); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal détails du log -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails du journal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="logDetailsContent">
                <!-- Chargé via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
function showLogDetails(logId) {
    fetch(`../ajax/get_log_details.php?id=${logId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.text();
        })
        .then(html => {
            document.getElementById('logDetailsContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('logDetailsModal')).show();
        })
        .catch(error => {
            document.getElementById('logDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Erreur lors du chargement des détails: ${error.message}
                </div>
            `;
            new bootstrap.Modal(document.getElementById('logDetailsModal')).show();
        });
}

function exportLogs() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = `export_logs.php?${params.toString()}`;
}

function clearOldLogs() {
    if (confirm('Supprimer les logs de plus de 90 jours ? Cette action est irréversible.')) {
        fetch('../ajax/clear_old_logs.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`${data.deleted} logs supprimés avec succès`);
                location.reload();
            } else {
                alert('Erreur lors du nettoyage: ' + (data.error || 'Erreur inconnue'));
            }
        })
        .catch(error => {
            alert('Erreur réseau: ' + error.message);
        });
    }
}

// Style pour les avatars
const style = document.createElement('style');
style.textContent = `
.avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #4361ee;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 12px;
}

.bg-primary-light { background-color: rgba(67, 97, 238, 0.1) !important; }
.bg-success-light { background-color: rgba(40, 167, 69, 0.1) !important; }
.bg-warning-light { background-color: rgba(255, 193, 7, 0.1) !important; }
.bg-info-light { background-color: rgba(23, 162, 184, 0.1) !important; }
.bg-danger-light { background-color: rgba(220, 53, 69, 0.1) !important; }
`;
document.head.appendChild(style);
</script>