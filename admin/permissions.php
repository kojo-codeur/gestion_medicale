<?php
// admin/permissions.php
require_once '../config/database.php';
checkRole('admin');

$title = 'Gestion des Permissions';
require_once '../includes/header.php';

// Définir les modules du système
$system_modules = [
    'dashboard' => [
        'name' => 'Tableau de bord',
        'description' => 'Accès au tableau de bord principal',
        'icon' => 'fas fa-tachometer-alt'
    ],
    'patients' => [
        'name' => 'Patients',
        'description' => 'Gestion des patients',
        'icon' => 'fas fa-user-injured'
    ],
    'consultations' => [
        'name' => 'Consultations',
        'description' => 'Gestion des consultations médicales',
        'icon' => 'fas fa-stethoscope'
    ],
    'rendezvous' => [
        'name' => 'Rendez-vous',
        'description' => 'Gestion des rendez-vous',
        'icon' => 'fas fa-calendar-check'
    ],
    'prescriptions' => [
        'name' => 'Prescriptions',
        'description' => 'Gestion des ordonnances',
        'icon' => 'fas fa-prescription-bottle-alt'
    ],
    'documents' => [
        'name' => 'Documents médicaux',
        'description' => 'Gestion des documents médicaux',
        'icon' => 'fas fa-file-medical'
    ],
    'utilisateurs' => [
        'name' => 'Utilisateurs',
        'description' => 'Gestion des utilisateurs du système',
        'icon' => 'fas fa-users'
    ],
    'roles' => [
        'name' => 'Rôles et Permissions',
        'description' => 'Gestion des rôles et permissions',
        'icon' => 'fas fa-user-shield'
    ],
    'parametres' => [
        'name' => 'Paramètres',
        'description' => 'Configuration du système',
        'icon' => 'fas fa-cog'
    ],
    'sauvegardes' => [
        'name' => 'Sauvegardes',
        'description' => 'Sauvegarde et restauration',
        'icon' => 'fas fa-database'
    ],
    'statistiques' => [
        'name' => 'Statistiques',
        'description' => 'Statistiques et rapports',
        'icon' => 'fas fa-chart-bar'
    ],
    'pathologies' => [
        'name' => 'Pathologies',
        'description' => 'Gestion des pathologies',
        'icon' => 'fas fa-disease'
    ],
    'specialites' => [
        'name' => 'Spécialités',
        'description' => 'Gestion des spécialités médicales',
        'icon' => 'fas fa-user-md'
    ],
    'notifications' => [
        'name' => 'Notifications',
        'description' => 'Gestion des notifications',
        'icon' => 'fas fa-bell'
    ],
    'audit' => [
        'name' => 'Audit Logs',
        'description' => 'Journal des activités',
        'icon' => 'fas fa-history'
    ]
];

// Définir les actions possibles
$system_actions = [
    'view' => [
        'name' => 'Voir',
        'description' => 'Voir les enregistrements',
        'color' => 'info'
    ],
    'create' => [
        'name' => 'Créer',
        'description' => 'Créer de nouveaux enregistrements',
        'color' => 'success'
    ],
    'edit' => [
        'name' => 'Modifier',
        'description' => 'Modifier les enregistrements',
        'color' => 'warning'
    ],
    'delete' => [
        'name' => 'Supprimer',
        'description' => 'Supprimer les enregistrements',
        'color' => 'danger'
    ],
    'export' => [
        'name' => 'Exporter',
        'description' => 'Exporter les données',
        'color' => 'secondary'
    ],
    'import' => [
        'name' => 'Importer',
        'description' => 'Importer des données',
        'color' => 'primary'
    ],
    'print' => [
        'name' => 'Imprimer',
        'description' => 'Imprimer les documents',
        'color' => 'dark'
    ]
];

// Définir les rôles par défaut
$default_roles = [
    'admin' => [
        'name' => 'Administrateur',
        'description' => 'Accès complet au système',
        'color' => 'danger',
        'icon' => 'fas fa-crown'
    ],
    'docteur' => [
        'name' => 'Docteur',
        'description' => 'Médecin avec accès aux patients et consultations',
        'color' => 'primary',
        'icon' => 'fas fa-user-md'
    ],
    'secretaire' => [
        'name' => 'Secrétaire',
        'description' => 'Personnel administratif',
        'color' => 'info',
        'icon' => 'fas fa-user-tie'
    ],
    'assistant' => [
        'name' => 'Assistant médical',
        'description' => 'Assistant aux médecins',
        'color' => 'success',
        'icon' => 'fas fa-user-nurse'
    ]
];

// Traitement des formulaires
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['create_role'])) {
            $role_name = sanitize($_POST['role_name']);
            $description = sanitize($_POST['description'] ?? '');
            
            // Vérifier si le rôle existe déjà
            $check = $pdo->prepare("SELECT id FROM roles WHERE role_name = ?");
            $check->execute([$role_name]);
            
            if ($check->fetch()) {
                $error = "Ce rôle existe déjà";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO roles (role_name, description, created_by, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$role_name, $description, $_SESSION['user_id']]);
                $role_id = $pdo->lastInsertId();
                
                // Ajouter les permissions par défaut
                if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                    foreach ($_POST['permissions'] as $permission) {
                        list($module, $action) = explode('_', $permission);
                        
                        $perm_stmt = $pdo->prepare("
                            INSERT INTO role_permissions (role_id, module, action, created_at) 
                            VALUES (?, ?, ?, NOW())
                        ");
                        $perm_stmt->execute([$role_id, $module, $action]);
                    }
                }
                
                logAction('CREATE', 'roles', $role_id, "Création rôle: {$role_name}");
                $success = "Rôle créé avec succès";
            }
            
        } elseif (isset($_POST['update_role']) && isset($_POST['role_id'])) {
            $role_id = (int)$_POST['role_id'];
            $description = sanitize($_POST['description'] ?? '');
            
            $stmt = $pdo->prepare("
                UPDATE roles 
                SET description = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$description, $role_id]);
            
            // Supprimer les anciennes permissions
            $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$role_id]);
            
            // Ajouter les nouvelles permissions
            if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                foreach ($_POST['permissions'] as $permission) {
                    list($module, $action) = explode('_', $permission);
                    
                    $perm_stmt = $pdo->prepare("
                        INSERT INTO role_permissions (role_id, module, action, created_at) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    $perm_stmt->execute([$role_id, $module, $action]);
                }
            }
            
            logAction('UPDATE', 'roles', $role_id, "Mise à jour permissions");
            $success = "Permissions mises à jour";
            
        } elseif (isset($_POST['delete_role']) && isset($_POST['role_id'])) {
            $role_id = (int)$_POST['role_id'];
            
            // Vérifier si des utilisateurs utilisent ce rôle
            $check = $pdo->prepare("
                SELECT COUNT(*) FROM utilisateurs 
                WHERE role = (SELECT role_name FROM roles WHERE id = ?)
            ");
            $check->execute([$role_id]);
            $user_count = $check->fetchColumn();
            
            if ($user_count > 0) {
                $error = "Impossible de supprimer : {$user_count} utilisateur(s) utilisent ce rôle";
            } else {
                // Supprimer les permissions
                $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$role_id]);
                
                // Supprimer le rôle
                $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
                $stmt->execute([$role_id]);
                
                logAction('DELETE', 'roles', $role_id, "Suppression rôle");
                $success = "Rôle supprimé avec succès";
            }
            
        } elseif (isset($_POST['update_user_role']) && isset($_POST['user_id'])) {
            $user_id = (int)$_POST['user_id'];
            $new_role = sanitize($_POST['new_role']);
            
            $stmt = $pdo->prepare("UPDATE utilisateurs SET role = ? WHERE id = ?");
            $stmt->execute([$new_role, $user_id]);
            
            logAction('UPDATE', 'utilisateurs', $user_id, "Changement rôle vers: {$new_role}");
            $success = "Rôle utilisateur mis à jour";
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupérer les rôles existants
$roles = $pdo->query("SELECT * FROM roles ORDER BY role_name")->fetchAll();

// Récupérer les permissions pour chaque rôle
$role_permissions = [];
foreach ($roles as $role) {
    $stmt = $pdo->prepare("
        SELECT CONCAT(module, '_', action) as permission 
        FROM role_permissions 
        WHERE role_id = ?
    ");
    $stmt->execute([$role['id']]);
    $perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $role_permissions[$role['id']] = $perms;
}

// Récupérer les utilisateurs
$users = $pdo->query("
    SELECT id, nom, prenom, email, role, statut, derniere_connexion 
    FROM utilisateurs 
    ORDER BY nom, prenom
")->fetchAll();

// Vérifier les données par défaut et les insérer si nécessaire
checkDefaultData();

function checkDefaultData() {
    global $pdo, $default_roles;
    
    // Vérifier et insérer les rôles par défaut
    foreach ($default_roles as $role_key => $role_data) {
        $check = $pdo->prepare("SELECT id FROM roles WHERE role_name = ?");
        $check->execute([$role_key]);
        
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO roles (role_name, description, created_by, created_at) 
                VALUES (?, ?, 1, NOW())
            ");
            $stmt->execute([$role_key, $role_data['description']]);
            
            // Pour l'admin, donner toutes les permissions
            if ($role_key === 'admin') {
                $role_id = $pdo->lastInsertId();
                insertAdminPermissions($role_id);
            }
        }
    }
}

function insertAdminPermissions($role_id) {
    global $pdo, $system_modules, $system_actions;
    
    foreach ($system_modules as $module_key => $module) {
        foreach ($system_actions as $action_key => $action) {
            $stmt = $pdo->prepare("
                INSERT INTO role_permissions (role_id, module, action, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$role_id, $module_key, $action_key]);
        }
    }
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-user-shield me-2"></i>Gestion des Permissions
        </h1>
        <p class="text-muted mb-0">Gestion des rôles et des accès au système</p>
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

<!-- Onglets -->
<ul class="nav nav-tabs mb-4" id="permissionsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="roles-tab" data-bs-toggle="tab" 
                data-bs-target="#roles" type="button">
            <i class="fas fa-users-cog me-2"></i>Rôles
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="users-tab" data-bs-toggle="tab" 
                data-bs-target="#users" type="button">
            <i class="fas fa-user me-2"></i>Utilisateurs
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="permissions-tab" data-bs-toggle="tab" 
                data-bs-target="#permissions" type="button">
            <i class="fas fa-key me-2"></i>Permissions
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="audit-tab" data-bs-toggle="tab" 
                data-bs-target="#audit" type="button">
            <i class="fas fa-history me-2"></i>Logs
        </button>
    </li>
</ul>

<!-- Contenu des onglets -->
<div class="tab-content" id="permissionsTabsContent">
    <!-- Onglet Rôles -->
    <div class="tab-pane fade show active" id="roles" role="tabpanel">
        <div class="row">
            <div class="col-lg-8">
                <!-- Liste des rôles -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-users-cog me-2"></i>Rôles définis</h6>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" 
                                data-bs-target="#createRoleModal">
                            <i class="fas fa-plus me-1"></i>Nouveau rôle
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($roles)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users-cog fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">Aucun rôle défini</h6>
                            <p class="text-muted small">Créez votre premier rôle pour commencer</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Description</th>
                                        <th>Permissions</th>
                                        <th>Créé le</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roles as $role): 
                                        $role_info = $default_roles[$role['role_name']] ?? [
                                            'name' => $role['role_name'],
                                            'color' => 'secondary',
                                            'icon' => 'fas fa-user'
                                        ];
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-<?php echo $role_info['color']; ?> me-2">
                                                    <i class="<?php echo $role_info['icon']; ?> me-1"></i>
                                                    <?php echo $role_info['name']; ?>
                                                </span>
                                                <small class="text-muted"><?php echo $role['role_name']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small text-muted"><?php echo $role['description']; ?></div>
                                        </td>
                                        <td>
                                            <?php 
                                            $permissions = $role_permissions[$role['id']] ?? [];
                                            $count = count($permissions);
                                            ?>
                                            <span class="badge bg-info"><?php echo $count; ?> permissions</span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo formatDate($role['created_at'], 'd/m/Y'); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="editRole(<?php echo $role['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if (!in_array($role['role_name'], array_keys($default_roles))): ?>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="deleteRole(<?php echo $role['id']; ?>, '<?php echo $role['role_name']; ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
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
                
                <!-- Rôles par défaut -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-star me-2"></i>Rôles système par défaut</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($default_roles as $key => $role): ?>
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge bg-<?php echo $role['color']; ?> me-2">
                                            <i class="<?php echo $role['icon']; ?>"></i>
                                        </span>
                                        <h6 class="mb-0"><?php echo $role['name']; ?></h6>
                                        <span class="badge bg-light text-dark ms-2">Système</span>
                                    </div>
                                    <p class="small text-muted mb-2"><?php echo $role['description']; ?></p>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">ID: <?php echo $key; ?></small>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="viewRolePermissions('<?php echo $key; ?>')">
                                            <i class="fas fa-eye me-1"></i>Voir permissions
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Statistiques -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Statistiques</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="stat-number"><?php echo count($roles); ?></div>
                                <div class="stat-label">Rôles</div>
                            </div>
                            <div class="col-6 mb-3">
                                <?php
                                $total_perms = 0;
                                foreach ($role_permissions as $perms) {
                                    $total_perms += count($perms);
                                }
                                ?>
                                <div class="stat-number"><?php echo $total_perms; ?></div>
                                <div class="stat-label">Permissions</div>
                            </div>
                            <div class="col-6">
                                <div class="stat-number"><?php echo count($system_modules); ?></div>
                                <div class="stat-label">Modules</div>
                            </div>
                            <div class="col-6">
                                <div class="stat-number"><?php echo count($system_actions); ?></div>
                                <div class="stat-label">Actions</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Guide des permissions -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Guide des permissions</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>Actions disponibles:</h6>
                            <?php foreach ($system_actions as $key => $action): ?>
                            <span class="badge bg-<?php echo $action['color']; ?> me-1 mb-1">
                                <?php echo $action['name']; ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <div class="mb-3">
                            <h6>Règles:</h6>
                            <ul class="small mb-0">
                                <li>Admin: Toutes les permissions</li>
                                <li>Docteur: Patients, Consultations, Prescriptions</li>
                                <li>Secrétaire: Rendez-vous, Patients (view/create)</li>
                                <li>Assistant: Consultations (view), Patients (view)</li>
                            </ul>
                        </div>
                        <div class="alert alert-warning small">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> Les rôles système ne peuvent pas être modifiés ou supprimés.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Onglet Utilisateurs -->
    <div class="tab-pane fade" id="users" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-user me-2"></i>Utilisateurs et rôles</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Rôle actuel</th>
                                <th>Statut</th>
                                <th>Dernière connexion</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): 
                                $role_info = $default_roles[$user['role']] ?? [
                                    'name' => $user['role'],
                                    'color' => 'secondary',
                                    'icon' => 'fas fa-user'
                                ];
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo $user['prenom'] . ' ' . $user['nom']; ?></div>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo $user['email']; ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $role_info['color']; ?>">
                                        <i class="<?php echo $role_info['icon']; ?> me-1"></i>
                                        <?php echo $role_info['name']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['statut'] == 'actif' ? 'success' : 'secondary'; ?>">
                                        <?php echo $user['statut']; ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo $user['derniere_connexion'] ? formatDate($user['derniere_connexion'], 'd/m/Y H:i') : 'Jamais'; ?>
                                    </small>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="changeUserRole(<?php echo $user['id']; ?>, '<?php echo $user['prenom'] . ' ' . $user['nom']; ?>', '<?php echo $user['role']; ?>')">
                                        <i class="fas fa-exchange-alt"></i> Changer rôle
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Le changement de rôle prend effet immédiatement. 
                        L'utilisateur devra peut-être se reconnecter pour voir les nouvelles permissions.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Onglet Permissions -->
    <div class="tab-pane fade" id="permissions" role="tabpanel">
        <div class="row">
            <div class="col-lg-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-key me-2"></i>Matrice des permissions</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Module</th>
                                        <?php foreach ($system_actions as $action_key => $action): ?>
                                        <th class="text-center" title="<?php echo $action['description']; ?>">
                                            <span class="badge bg-<?php echo $action['color']; ?>">
                                                <?php echo $action['name']; ?>
                                            </span>
                                        </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($system_modules as $module_key => $module): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="<?php echo $module['icon']; ?> me-2 text-primary"></i>
                                                <div>
                                                    <div class="fw-semibold"><?php echo $module['name']; ?></div>
                                                    <small class="text-muted"><?php echo $module['description']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <?php foreach ($system_actions as $action_key => $action): ?>
                                        <td class="text-center">
                                            <?php 
                                            // Vérifier quelle permission est accordée à quel rôle
                                            $permission_key = $module_key . '_' . $action_key;
                                            $roles_with_permission = [];
                                            
                                            foreach ($roles as $role) {
                                                $perms = $role_permissions[$role['id']] ?? [];
                                                if (in_array($permission_key, $perms)) {
                                                    $roles_with_permission[] = $role['role_name'];
                                                }
                                            }
                                            ?>
                                            <?php if (!empty($roles_with_permission)): ?>
                                            <span class="badge bg-success" title="<?php echo implode(', ', $roles_with_permission); ?>">
                                                <i class="fas fa-check"></i>
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-light text-muted" title="Aucun rôle">
                                                <i class="fas fa-times"></i>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Cette matrice montre quelles permissions sont accordées à quels rôles. 
                                Survolez les cases pour voir la liste des rôles ayant cette permission.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Onglet Logs -->
    <div class="tab-pane fade" id="audit" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Logs des activités permissions</h6>
            </div>
            <div class="card-body">
                <?php
                // Récupérer les logs liés aux permissions
                $logs = $pdo->query("
                    SELECT al.*, u.nom, u.prenom 
                    FROM audit_logs al
                    LEFT JOIN utilisateurs u ON al.user_id = u.id
                    WHERE al.table_name IN ('roles', 'role_permissions', 'utilisateurs')
                    ORDER BY al.created_at DESC
                    LIMIT 100
                ")->fetchAll();
                ?>
                
                <?php if (empty($logs)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">Aucune activité enregistrée</h6>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Utilisateur</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>Détails</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small class="text-muted">
                                        <?php echo formatDate($log['created_at'], 'd/m/Y H:i:s'); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($log['nom']): ?>
                                    <div><?php echo $log['prenom'] . ' ' . $log['nom']; ?></div>
                                    <?php else: ?>
                                    <span class="text-muted">Système</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getActivityColor($log['action']); ?>">
                                        <i class="fas fa-<?php echo getActivityIcon($log['action']); ?> me-1"></i>
                                        <?php echo $log['action']; ?>
                                    </span>
                                </td>
                                <td>
                                    <code><?php echo $log['table_name']; ?></code>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo $log['details'] ?? ''; ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3 text-center">
                    <a href="../admin/logs.php" class="btn btn-outline-primary">
                        <i class="fas fa-external-link-alt me-1"></i>Voir tous les logs
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->

<!-- Modal Création Rôle -->
<div class="modal fade" id="createRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="createRoleForm">
                <div class="modal-header">
                    <h5 class="modal-title">Créer un nouveau rôle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom du rôle</label>
                            <input type="text" class="form-control" name="role_name" required
                                   placeholder="ex: gestionnaire_patients">
                            <small class="text-muted">Utilisez des underscores (_) sans espaces</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="1"></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Permissions</label>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Sélectionnez les permissions à attribuer à ce rôle
                        </div>
                        
                        <?php foreach ($system_modules as $module_key => $module): ?>
                        <div class="card mb-3">
                            <div class="card-header py-2">
                                <div class="form-check">
                                    <input class="form-check-input module-checkbox" 
                                           type="checkbox" 
                                           id="module_<?php echo $module_key; ?>"
                                           data-module="<?php echo $module_key; ?>">
                                    <label class="form-check-label fw-semibold" for="module_<?php echo $module_key; ?>">
                                        <i class="<?php echo $module['icon']; ?> me-2"></i>
                                        <?php echo $module['name']; ?>
                                    </label>
                                    <small class="text-muted ms-2">- <?php echo $module['description']; ?></small>
                                </div>
                            </div>
                            <div class="card-body py-2">
                                <div class="row">
                                    <?php foreach ($system_actions as $action_key => $action): ?>
                                    <div class="col-md-3 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input action-checkbox" 
                                                   type="checkbox" 
                                                   name="permissions[]" 
                                                   value="<?php echo $module_key . '_' . $action_key; ?>"
                                                   id="perm_<?php echo $module_key . '_' . $action_key; ?>">
                                            <label class="form-check-label" for="perm_<?php echo $module_key . '_' . $action_key; ?>">
                                                <span class="badge bg-<?php echo $action['color']; ?>">
                                                    <?php echo $action['name']; ?>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="create_role" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Créer le rôle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Édition Rôle -->
<div class="modal fade" id="editRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editRoleForm">
                <input type="hidden" name="role_id" id="editRoleId">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier les permissions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editRoleDescription" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Permissions</label>
                        
                        <?php foreach ($system_modules as $module_key => $module): ?>
                        <div class="card mb-3">
                            <div class="card-header py-2">
                                <div class="form-check">
                                    <input class="form-check-input edit-module-checkbox" 
                                           type="checkbox" 
                                           id="edit_module_<?php echo $module_key; ?>"
                                           data-module="<?php echo $module_key; ?>">
                                    <label class="form-check-label fw-semibold" for="edit_module_<?php echo $module_key; ?>">
                                        <i class="<?php echo $module['icon']; ?> me-2"></i>
                                        <?php echo $module['name']; ?>
                                    </label>
                                </div>
                            </div>
                            <div class="card-body py-2">
                                <div class="row">
                                    <?php foreach ($system_actions as $action_key => $action): ?>
                                    <div class="col-md-3 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input edit-action-checkbox" 
                                                   type="checkbox" 
                                                   name="permissions[]" 
                                                   value="<?php echo $module_key . '_' . $action_key; ?>"
                                                   id="edit_perm_<?php echo $module_key . '_' . $action_key; ?>">
                                            <label class="form-check-label" for="edit_perm_<?php echo $module_key . '_' . $action_key; ?>">
                                                <span class="badge bg-<?php echo $action['color']; ?>">
                                                    <?php echo $action['name']; ?>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="update_role" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Suppression Rôle -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="deleteRoleForm">
                <input type="hidden" name="role_id" id="deleteRoleId">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer le rôle <strong id="deleteRoleName"></strong> ?</p>
                    <p class="text-danger">Cette action est irréversible.</p>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmDeleteRole" required>
                        <label class="form-check-label" for="confirmDeleteRole">
                            Je confirme vouloir supprimer ce rôle
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="delete_role" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Changer Rôle Utilisateur -->
<div class="modal fade" id="changeUserRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="changeUserRoleForm">
                <input type="hidden" name="user_id" id="changeUserId">
                <div class="modal-header">
                    <h5 class="modal-title">Changer le rôle utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Utilisateur</label>
                        <input type="text" class="form-control" id="changeUserName" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nouveau rôle</label>
                        <select class="form-select" name="new_role" id="newUserRole" required>
                            <option value="">Sélectionner un rôle</option>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_name']; ?>">
                                <?php echo $default_roles[$role['role_name']]['name'] ?? $role['role_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Le changement de rôle prend effet immédiatement. 
                        L'utilisateur devra peut-être se reconnecter.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="update_user_role" class="btn btn-primary">
                        <i class="fas fa-exchange-alt me-1"></i>Changer rôle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Voir Permissions Rôle -->
<div class="modal fade" id="viewRolePermissionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Permissions du rôle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="rolePermissionsContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
// Gestion des cases à cocher (sélectionner tout un module)
document.addEventListener('DOMContentLoaded', function() {
    // Pour le modal de création
    document.querySelectorAll('.module-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const module = this.getAttribute('data-module');
            const actionCheckboxes = document.querySelectorAll(
                `.action-checkbox[value^="${module}_"]`
            );
            actionCheckboxes.forEach(function(actionCheckbox) {
                actionCheckbox.checked = checkbox.checked;
            });
        });
    });
    
    // Pour le modal d'édition
    document.querySelectorAll('.edit-module-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const module = this.getAttribute('data-module');
            const actionCheckboxes = document.querySelectorAll(
                `.edit-action-checkbox[value^="${module}_"]`
            );
            actionCheckboxes.forEach(function(actionCheckbox) {
                actionCheckbox.checked = checkbox.checked;
            });
        });
    });
});

// Fonctions pour les modals
function editRole(roleId) {
    fetch(`../api/get_role.php?id=${roleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('editRoleId').value = roleId;
                document.getElementById('editRoleDescription').value = data.role.description;
                
                // Décocher toutes les cases
                document.querySelectorAll('.edit-action-checkbox').forEach(cb => {
                    cb.checked = false;
                });
                document.querySelectorAll('.edit-module-checkbox').forEach(cb => {
                    cb.checked = false;
                });
                
                // Cocher les permissions existantes
                if (data.permissions) {
                    data.permissions.forEach(permission => {
                        const checkbox = document.getElementById(`edit_perm_${permission}`);
                        if (checkbox) checkbox.checked = true;
                    });
                    
                    // Cocher les cases de module si toutes les actions sont cochées
                    document.querySelectorAll('.edit-module-checkbox').forEach(moduleCb => {
                        const module = moduleCb.getAttribute('data-module');
                        const moduleCheckboxes = document.querySelectorAll(
                            `.edit-action-checkbox[value^="${module}_"]`
                        );
                        const checkedCount = Array.from(moduleCheckboxes).filter(cb => cb.checked).length;
                        moduleCb.checked = checkedCount === moduleCheckboxes.length;
                        moduleCb.indeterminate = checkedCount > 0 && checkedCount < moduleCheckboxes.length;
                    });
                }
                
                new bootstrap.Modal(document.getElementById('editRoleModal')).show();
            } else {
                alert('Erreur: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur lors du chargement des données');
        });
}

function deleteRole(roleId, roleName) {
    document.getElementById('deleteRoleId').value = roleId;
    document.getElementById('deleteRoleName').textContent = roleName;
    new bootstrap.Modal(document.getElementById('deleteRoleModal')).show();
}

function changeUserRole(userId, userName, currentRole) {
    document.getElementById('changeUserId').value = userId;
    document.getElementById('changeUserName').value = userName;
    document.getElementById('newUserRole').value = currentRole;
    new bootstrap.Modal(document.getElementById('changeUserRoleModal')).show();
}

function viewRolePermissions(roleName) {
    fetch(`../api/get_role_permissions.php?name=${roleName}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = `
                    <div class="mb-3">
                        <h5>${data.role.name}</h5>
                        <p class="text-muted">${data.role.description}</p>
                    </div>
                `;
                
                if (data.permissions.length > 0) {
                    html += '<h6>Permissions accordées:</h6><div class="row">';
                    
                    // Grouper par module
                    const grouped = {};
                    data.permissions.forEach(perm => {
                        const [module, action] = perm.split('_');
                        if (!grouped[module]) grouped[module] = [];
                        grouped[module].push(action);
                    });
                    
                    for (const module in grouped) {
                        const moduleInfo = <?php echo json_encode($system_modules); ?>[module] || {name: module};
                        html += `
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3">
                                    <h6><i class="${moduleInfo.icon} me-2"></i>${moduleInfo.name}</h6>
                                    <div>`;
                        
                        grouped[module].forEach(action => {
                            const actionInfo = <?php echo json_encode($system_actions); ?>[action] || {name: action};
                            html += `<span class="badge bg-${actionInfo.color} me-1 mb-1">${actionInfo.name}</span>`;
                        });
                        
                        html += `</div></div></div>`;
                    }
                    
                    html += '</div>';
                } else {
                    html += '<div class="alert alert-info">Aucune permission spécifique</div>';
                }
                
                document.getElementById('rolePermissionsContent').innerHTML = html;
                new bootstrap.Modal(document.getElementById('viewRolePermissionsModal')).show();
            } else {
                alert('Erreur: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur lors du chargement des permissions');
        });
}

// Vérifier si un module est complètement sélectionné
function updateModuleCheckbox(module) {
    const checkboxes = document.querySelectorAll(`.action-checkbox[value^="${module}_"]`);
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    const someChecked = Array.from(checkboxes).some(cb => cb.checked);
    
    const moduleCheckbox = document.querySelector(`.module-checkbox[data-module="${module}"]`);
    if (moduleCheckbox) {
        moduleCheckbox.checked = allChecked;
        moduleCheckbox.indeterminate = !allChecked && someChecked;
    }
}

// Ajouter des écouteurs aux cases à cocher d'action
document.querySelectorAll('.action-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const value = this.value;
        const module = value.split('_')[0];
        updateModuleCheckbox(module);
    });
});

// Initialiser l'état des cases de module
document.querySelectorAll('.module-checkbox').forEach(moduleCb => {
    const module = moduleCb.getAttribute('data-module');
    updateModuleCheckbox(module);
});
</script>

<?php
require_once '../includes/footer.php';
requirePermission('permissions', 'view');
?>