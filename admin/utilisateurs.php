<?php
// admin/utilisateurs.php
require_once '../config/database.php';
checkRole('admin');

$title = 'Gestion des Utilisateurs';
$admin_id = $_SESSION['user_id'];

// Traitement CRUD
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';


// Récupérer les rôles pour le filtre
$roles = ['admin', 'docteur', 'secretaire', 'assistant'];

// Traitement des actions GET (activation/désactivation)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    try {
        $pdo->beginTransaction();
        
        // Récupérer le statut actuel
        $stmt = $pdo->prepare("SELECT statut FROM utilisateurs WHERE id = ?");
        $stmt->execute([$id]);
        $currentStatus = $stmt->fetchColumn();
        
        if ($currentStatus === false) {
            throw new Exception("Utilisateur non trouvé.");
        }
        
        $newStatus = ($currentStatus === 'actif') ? 'inactif' : 'actif';
        
        $stmt = $pdo->prepare("UPDATE utilisateurs SET statut = ?, date_modification = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        
        $statusText = ($newStatus === 'actif') ? 'activé' : 'désactivé';
        
        // Journaliser l'action
        logAction('UPDATE', 'utilisateurs', $id, "Changement statut: $currentStatus -> $newStatus");
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "Utilisateur $statusText avec succès";
        header("Location: utilisateurs.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: utilisateurs.php");
        exit();
    }
}

// Traitement POST pour ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $data = sanitize($_POST);
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'add') {
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
            $stmt->execute([$data['email']]);
            $exists = $stmt->fetchColumn();
            
            if ($exists > 0) {
                throw new Exception("Cet email est déjà utilisé.");
            }
            
            // Générer mot de passe par défaut
            $defaultPassword = strtolower($data['nom'] . $data['prenom']) . '123';
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs 
                (nom, prenom, email, password, role, telephone, specialite, statut) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['nom'],
                $data['prenom'],
                $data['email'],
                $hashedPassword,
                $data['role'],
                $data['telephone'] ?? null,
                $data['specialite'] ?? null,
                'actif'
            ]);
            
            $userId = $pdo->lastInsertId();
            
            // Journaliser l'action
            logAction('CREATE', 'utilisateurs', $userId, "Création utilisateur: {$data['email']}");
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Utilisateur créé. Mot de passe par défaut: $defaultPassword";
            header("Location: utilisateurs.php");
            exit();
            
        } elseif ($action === 'edit' && isset($data['id'])) {
            $id = $data['id'];
            
            // Vérifier si l'email est modifié et existe déjà
            if (!empty($data['email'])) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ? AND id != ?");
                $stmt->execute([$data['email'], $id]);
                $exists = $stmt->fetchColumn();
                
                if ($exists > 0) {
                    throw new Exception("Cet email est déjà utilisé par un autre utilisateur.");
                }
            }
            
            $stmt = $pdo->prepare("
                UPDATE utilisateurs SET 
                nom = ?, prenom = ?, email = ?, role = ?, telephone = ?, 
                specialite = ?, statut = ?, date_modification = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['nom'],
                $data['prenom'],
                $data['email'],
                $data['role'],
                $data['telephone'] ?? null,
                $data['specialite'] ?? null,
                $data['statut'],
                $id
            ]);
            
            // Journaliser l'action
            logAction('UPDATE', 'utilisateurs', $id, "Modification utilisateur: {$data['email']}");
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Utilisateur modifié avec succès";
            header("Location: utilisateurs.php");
            exit();
            
        } elseif ($action === 'delete' && isset($_GET['id'])) {
            $id = $_GET['id'];
            
            // Vérifier s'il y a des données associées
            $hasData = false;
            $tables = ['consultations', 'patients', 'prescriptions', 'rendez_vous'];
            
            foreach ($tables as $table) {
                $field = ($table === 'consultations' || $table === 'prescriptions' || $table === 'rendez_vous') ? 'docteur_id' : 'created_by';
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
                $stmt->execute([$id]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $hasData = true;
                    break;
                }
            }
            
            if ($hasData) {
                // Désactiver au lieu de supprimer
                $stmt = $pdo->prepare("UPDATE utilisateurs SET statut = 'inactif' WHERE id = ?");
                $stmt->execute([$id]);
                $message = "L'utilisateur a été désactivé (données associées préservées)";
            } else {
                $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Utilisateur supprimé avec succès";
            }
            
            // Journaliser l'action
            logAction('DELETE', 'utilisateurs', $id, "Suppression utilisateur ID: $id");
            
            $pdo->commit();
            
            $_SESSION['success_message'] = $message;
            header("Location: utilisateurs.php");
            exit();
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: utilisateurs.php?action=" . $action . (isset($id) ? "&id=$id" : ""));
        exit();
    }
}

// Récupérer les messages de session
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Fonction de log
function logAction($action, $table, $recordId, $details) {
    global $pdo, $admin_id;
    
    try {
        // Vérifier la structure de la table audit_logs
        $stmt = $pdo->prepare("DESCRIBE audit_logs");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('description', $columns)) {
            // Ancienne structure avec 'description'
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action, table_name, record_id, description, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$admin_id, $action, $table, $recordId, $details, $_SERVER['REMOTE_ADDR']]);
        } elseif (in_array('details', $columns)) {
            // Nouvelle structure avec 'details'
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action, table_name, record_id, details, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$admin_id, $action, $table, $recordId, $details, $_SERVER['REMOTE_ADDR']]);
        } else {
            // Structure alternative
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action, table_name, record_id, ip_address) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$admin_id, $action, $table, $recordId, $_SERVER['REMOTE_ADDR']]);
        }
    } catch (Exception $e) {
        // En cas d'erreur, on continue sans journaliser
        error_log("Erreur de journalisation: " . $e->getMessage());
    }
}

require_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-users-cog me-2"></i>Gestion des Utilisateurs
        </h1>
        <p class="text-muted mb-0">Administration des comptes utilisateurs du système</p>
    </div>
    <div class="btn-toolbar">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-user-plus me-1"></i>Nouvel utilisateur
        </a>
        <?php else: ?>
        <a href="utilisateurs.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Retour à la liste
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Messages -->
<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
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
                    <i class="fas fa-user-edit me-2"></i>
                    <?php echo $action === 'add' ? 'Ajouter un utilisateur' : 'Modifier l\'utilisateur'; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php
                $user = null;
                if ($action === 'edit' && $id) {
                    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
                    $stmt->execute([$id]);
                    $user = $stmt->fetch();
                    if (!$user) {
                        echo '<div class="alert alert-danger">Utilisateur non trouvé</div>';
                        require_once '../includes/footer.php';
                        exit();
                    }
                }
                ?>
                
                <form method="POST" id="userForm" novalidate>
                    <input type="hidden" name="action" value="<?php echo $action; ?>">
                    <?php if ($action === 'edit' && $id): ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <?php endif; ?>
                    
                    <div class="row g-3">
                        <!-- Informations personnelles -->
                        <div class="col-md-6">
                            <label class="form-label required">Nom</label>
                            <input type="text" class="form-control" name="nom" 
                                   value="<?php echo htmlspecialchars($user['nom'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Prénom</label>
                            <input type="text" class="form-control" name="prenom" 
                                   value="<?php echo htmlspecialchars($user['prenom'] ?? ''); ?>" required>
                        </div>
                        
                        <!-- Contact -->
                        <div class="col-md-6">
                            <label class="form-label required">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" name="telephone" 
                                   value="<?php echo htmlspecialchars($user['telephone'] ?? ''); ?>">
                        </div>
                        
                        <!-- Rôle et spécialité -->
                        <div class="col-md-6">
                            <label class="form-label required">Rôle</label>
                            <select class="form-select" name="role" id="roleSelect" required>
                                <option value="">Sélectionner un rôle</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo htmlspecialchars($role); ?>" 
                                    <?php echo (isset($user['role']) && $user['role'] === $role) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($role); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Spécialité</label>
                            <select class="form-select" name="specialite" id="specialiteSelect" 
                                    <?php echo (isset($user['role']) && $user['role'] === 'docteur') ? '' : 'disabled'; ?>>
                                <option value="">Sélectionner une spécialité</option>
                                <?php
                                $stmt = $pdo->query("SELECT * FROM specialites WHERE statut = 'active' ORDER BY nom");
                                $specialites = $stmt->fetchAll();
                                foreach ($specialites as $spec): ?>
                                <option value="<?php echo htmlspecialchars($spec['nom']); ?>" 
                                    <?php echo (isset($user['specialite']) && $user['specialite'] === $spec['nom']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec['nom']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Information mot de passe (pour l'ajout uniquement) -->
                        <?php if ($action === 'add'): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Mot de passe par défaut:</strong> Le mot de passe sera généré automatiquement 
                                à partir du nom et prénom (ex: nomprenom123). Il sera affiché après la création.
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Statut (pour l'édition uniquement) -->
                        <?php if ($action === 'edit'): ?>
                        <div class="col-md-6">
                            <label class="form-label required">Statut</label>
                            <select class="form-select" name="statut" required>
                                <option value="actif" <?php echo (isset($user['statut']) && $user['statut'] === 'actif') ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactif" <?php echo (isset($user['statut']) && $user['statut'] === 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                                <option value="suspendu" <?php echo (isset($user['statut']) && $user['statut'] === 'suspendu') ? 'selected' : ''; ?>>Suspendu</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Actions supplémentaires -->
                        <?php if ($action === 'edit' && isset($user)): ?>
                        <div class="col-md-6">
                            <label class="form-label">Actions rapides</label>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-warning" 
                                        onclick="resetPassword(<?php echo $user['id']; ?>)">
                                    <i class="fas fa-key me-1"></i>Réinitialiser mot de passe
                                </button>
                                <a href="?toggle_status=1&id=<?php echo $user['id']; ?>" 
                                   class="btn btn-outline-<?php echo ($user['statut'] ?? '') === 'actif' ? 'danger' : 'success'; ?>"
                                   onclick="return confirm('<?php echo ($user['statut'] ?? '') === 'actif' ? 'Désactiver' : 'Activer'; ?> cet utilisateur ?')">
                                    <i class="fas fa-power-off me-1"></i>
                                    <?php echo ($user['statut'] ?? '') === 'actif' ? 'Désactiver' : 'Activer'; ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Informations de connexion -->
                        <?php if ($action === 'edit' && isset($user)): ?>
                        <div class="col-12">
                            <div class="card bg-light border">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-info-circle me-2"></i>Informations de connexion
                                    </h6>
                                    <div class="row small">
                                        <div class="col-md-4">
                                            <strong>Créé le:</strong><br>
                                            <?php echo date('d/m/Y H:i', strtotime($user['date_creation'])); ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Dernière connexion:</strong><br>
                                            <?php echo !empty($user['derniere_connexion']) ? date('d/m/Y H:i', strtotime($user['derniere_connexion'])) : 'Jamais'; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Dernière modification:</strong><br>
                                            <?php echo !empty($user['date_modification']) ? date('d/m/Y H:i', strtotime($user['date_modification'])) : 'Jamais'; ?>
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
                            <?php echo $action === 'add' ? 'Créer l\'utilisateur' : 'Enregistrer les modifications'; ?>
                        </button>
                        <a href="utilisateurs.php" class="btn btn-secondary ms-2">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Liste des utilisateurs -->
<div class="card shadow-sm">
    <div class="card-header bg-white border-bottom">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Liste des utilisateurs
                </h6>
            </div>
            <div class="col-md-6">
                <form method="GET" class="row g-2">
                    <div class="col">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Rechercher..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    </div>
                    <div class="col-auto">
                        <select class="form-select" name="role">
                            <option value="">Tous les rôles</option>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role); ?>" <?php echo ($_GET['role'] ?? '') === $role ? 'selected' : ''; ?>>
                                <?php echo ucfirst($role); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <select class="form-select" name="statut">
                            <option value="">Tous les statuts</option>
                            <option value="actif" <?php echo ($_GET['statut'] ?? '') === 'actif' ? 'selected' : ''; ?>>Actif</option>
                            <option value="inactif" <?php echo ($_GET['statut'] ?? '') === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                            <option value="suspendu" <?php echo ($_GET['statut'] ?? '') === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> Filtrer
                        </button>
                        <?php if (!empty($_GET['search']) || !empty($_GET['role']) || !empty($_GET['statut'])): ?>
                        <a href="utilisateurs.php" class="btn btn-outline-secondary ms-1">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
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
                        <th>ID</th>
                        <th>Utilisateur</th>
                        <th>Contact</th>
                        <th>Rôle</th>
                        <th>Activité</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Construire la requête avec filtres
                    $sql = "SELECT u.*, 
                                   (SELECT COUNT(*) FROM consultations WHERE docteur_id = u.id) as consultations_count,
                                   (SELECT COUNT(*) FROM patients WHERE created_by = u.id) as patients_count,
                                   TIMESTAMPDIFF(HOUR, u.derniere_connexion, NOW()) as hours_since_login
                            FROM utilisateurs u 
                            WHERE 1=1";
                    
                    $params = [];
                    
                    // Filtre recherche
                    if (!empty($_GET['search'])) {
                        $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
                        $searchTerm = "%" . trim($_GET['search']) . "%";
                        $params[] = $searchTerm;
                        $params[] = $searchTerm;
                        $params[] = $searchTerm;
                    }
                    
                    // Filtre rôle
                    if (!empty($_GET['role'])) {
                        $sql .= " AND u.role = ?";
                        $params[] = $_GET['role'];
                    }
                    
                    // Filtre statut
                    if (!empty($_GET['statut'])) {
                        $sql .= " AND u.statut = ?";
                        $params[] = $_GET['statut'];
                    }
                    
                    $sql .= " ORDER BY 
                        CASE WHEN u.statut = 'actif' THEN 1 
                             WHEN u.statut = 'suspendu' THEN 2 
                             ELSE 3 END,
                        u.nom ASC, u.prenom ASC";
                    
                    try {
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $users = $stmt->fetchAll();
                        
                        if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-users fa-2x text-muted mb-3"></i>
                            <p class="text-muted">Aucun utilisateur trouvé</p>
                            <a href="?action=add" class="btn btn-primary btn-sm">
                                <i class="fas fa-user-plus me-1"></i>Ajouter un utilisateur
                            </a>
                        </td>
                    </tr>
                    <?php
                        else:
                        foreach ($users as $user): 
                            $roleColor = $user['role'] == 'admin' ? 'danger' : 
                                        ($user['role'] == 'docteur' ? 'primary' : 
                                        ($user['role'] == 'secretaire' ? 'success' : 'warning'));
                            $statusColor = $user['statut'] == 'actif' ? 'success' : 
                                         ($user['statut'] == 'inactif' ? 'secondary' : 'danger');
                            $activity = $user['hours_since_login'] <= 1 ? 'En ligne' : 
                                       ($user['hours_since_login'] <= 24 ? 'Aujourd\'hui' : 
                                       ($user['hours_since_login'] <= 168 ? 'Cette semaine' : 'Ancien'));
                    ?>
                    <tr>
                        <td><strong>#<?php echo str_pad($user['id'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar me-3">
                                    <?php echo strtoupper(substr($user['prenom'] ?? '', 0, 1) . substr($user['nom'] ?? '', 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['specialite'] ?? 'Aucune spécialité'); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($user['email']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($user['telephone'] ?? 'Non renseigné'); ?></small>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $roleColor; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="small">
                                <div>Consultations: <?php echo $user['consultations_count'] ?? 0; ?></div>
                                <div>Patients: <?php echo $user['patients_count'] ?? 0; ?></div>
                                <div>Activité: <?php echo $activity; ?></div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $statusColor; ?>">
                                <?php echo ucfirst($user['statut']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?action=edit&id=<?php echo $user['id']; ?>" 
                                   class="btn btn-outline-primary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?toggle_status=1&id=<?php echo $user['id']; ?>" 
                                   class="btn btn-outline-<?php echo $user['statut'] === 'actif' ? 'danger' : 'success'; ?>" 
                                   title="<?php echo $user['statut'] === 'actif' ? 'Désactiver' : 'Activer'; ?>"
                                   onclick="return confirm('<?php echo $user['statut'] === 'actif' ? 'Désactiver' : 'Activer'; ?> cet utilisateur ?')">
                                    <i class="fas fa-power-off"></i>
                                </a>
                                <button type="button" class="btn btn-outline-info" 
                                        onclick="viewUserDetails(<?php echo $user['id']; ?>)" title="Détails">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="?action=delete&id=<?php echo $user['id']; ?>" 
                                   class="btn btn-outline-danger" 
                                   title="Supprimer"
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php 
                        endforeach;
                        endif;
                    } catch (Exception $e) {
                        echo '<tr><td colspan="7" class="text-center text-danger py-4">Erreur: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card-footer bg-white border-top">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <small class="text-muted">
                    Total: <?php echo count($users); ?> utilisateur(s)
                </small>
            </div>
            <div>
                <button class="btn btn-sm btn-outline-secondary" onclick="exportUsers()">
                    <i class="fas fa-file-export me-1"></i>Exporter
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Détails Utilisateur -->
<div class="modal fade" id="userDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de l'utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <!-- Contenu chargé via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

<script>
// Activer/désactiver le champ spécialité selon le rôle
document.getElementById('roleSelect')?.addEventListener('change', function() {
    const specialiteSelect = document.getElementById('specialiteSelect');
    if (this.value === 'docteur') {
        specialiteSelect.disabled = false;
        specialiteSelect.required = true;
    } else {
        specialiteSelect.disabled = true;
        specialiteSelect.required = false;
        specialiteSelect.value = '';
    }
});

// Validation du formulaire
document.getElementById('userForm')?.addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.add('was-validated');
    }
});

// Afficher les détails d'un utilisateur
function viewUserDetails(userId) {
    // Créer un contenu simple pour les détails
    const modalContent = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Chargement des détails...</p>
        </div>
    `;
    
    document.getElementById('userDetailsContent').innerHTML = modalContent;
    const modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
    modal.show();
    
    // Charger les détails via AJAX
    fetch(`../ajax/get_user_details.php?id=${userId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('userDetailsContent').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('userDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Erreur lors du chargement des détails
                </div>
            `;
        });
}

// Réinitialiser le mot de passe
function resetPassword(userId) {
    if (confirm('Générer un nouveau mot de passe par défaut (nom+prenom+123) ?')) {
        fetch(`../ajax/reset_password.php?id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Nouveau mot de passe: ' + data.password);
                } else {
                    alert('Erreur: ' + data.error);
                }
            })
            .catch(error => {
                alert('Erreur lors de la réinitialisation');
            });
    }
}

// Exporter les utilisateurs
function exportUsers() {
    // Récupérer les filtres actuels
    const search = document.querySelector('input[name="search"]')?.value || '';
    const role = document.querySelector('select[name="role"]')?.value || '';
    const statut = document.querySelector('select[name="statut"]')?.value || '';
    
    // Rediriger vers la page d'export avec les filtres
    window.location.href = `export_users.php?search=${encodeURIComponent(search)}&role=${encodeURIComponent(role)}&statut=${encodeURIComponent(statut)}`;
}

// Initialiser les tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
});
</script>

<style>
.required::after {
    content: " *";
    color: #dc3545;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #4361ee;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
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

.table-hover tbody tr:hover {
    background-color: #f8fafc;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.25rem;
}

.btn-group-sm {
    border-radius: 0.25rem;
    overflow: hidden;
}

.badge {
    font-size: 0.75em;
    padding: 0.35em 0.65em;
}

.card {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
}

.card-header {
    padding: 1rem 1.5rem;
}

.card-body {
    padding: 1.5rem;
}

.alert {
    border-radius: 0.5rem;
    border: none;
}
</style>