<?php
// admin/profile.php
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

checkRole($role);

$pdo = Database::getInstance()->getConnection();

$title = 'Mon Profil';
require_once '../includes/header.php';

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM consultations WHERE docteur_id = u.id) as consultations_count,
           (SELECT COUNT(*) FROM patients WHERE created_by = u.id) as patients_count,
           (SELECT COUNT(*) FROM prescriptions WHERE docteur_id = u.id) as prescriptions_count
    FROM utilisateurs u 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../logout.php');
    exit();
}

// Traitement de la mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = sanitize($_POST);
    $errors = [];
    
    // Validation
    if (empty($data['nom'])) {
        $errors[] = 'Le nom est obligatoire';
    }
    if (empty($data['prenom'])) {
        $errors[] = 'Le prénom est obligatoire';
    }
    if (empty($data['email'])) {
        $errors[] = 'L\'email est obligatoire';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide';
    }
    
    // Vérifier si l'email est déjà utilisé (sauf par l'utilisateur actuel)
    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
    $stmt->execute([$data['email'], $user_id]);
    if ($stmt->fetch()) {
        $errors[] = 'Cet email est déjà utilisé par un autre utilisateur';
    }
    
    // Traitement du changement de mot de passe
    if (!empty($data['current_password']) || !empty($data['new_password'])) {
        if (empty($data['current_password'])) {
            $errors[] = 'Le mot de passe actuel est requis pour le changer';
        } elseif (empty($data['new_password'])) {
            $errors[] = 'Le nouveau mot de passe est requis';
        } elseif (strlen($data['new_password']) < 8) {
            $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères';
        } elseif ($data['new_password'] !== $data['confirm_password']) {
            $errors[] = 'Les mots de passe ne correspondent pas';
        } elseif (!password_verify($data['current_password'], $user['password'])) {
            $errors[] = 'Le mot de passe actuel est incorrect';
        }
    }
    
    // Si pas d'erreurs, mettre à jour
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Préparer les données de mise à jour
            $updateData = [
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'email' => $data['email'],
                'telephone' => $data['telephone'] ?? null,
                'adresse' => $data['adresse'] ?? null,
                'date_modification' => date('Y-m-d H:i:s')
            ];
            
            // Ajouter le nouveau mot de passe si fourni
            if (!empty($data['new_password'])) {
                $updateData['password'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
            }
            
            // Construire la requête dynamiquement
            $setClause = [];
            $params = [];
            foreach ($updateData as $key => $value) {
                $setClause[] = "$key = ?";
                $params[] = $value;
            }
            $params[] = $user_id;
            
            $sql = "UPDATE utilisateurs SET " . implode(', ', $setClause) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Mettre à jour la session
            $_SESSION['nom'] = $data['nom'];
            $_SESSION['prenom'] = $data['prenom'];
            $_SESSION['email'] = $data['email'];
            
            // Journaliser l'action
            $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action, table_name, record_id, ip_address) 
                VALUES (?, ?, 'utilisateurs', ?, ?)
            ")->execute([$user_id, 'UPDATE_PROFILE', $user_id, $_SERVER['REMOTE_ADDR']]);
            
            $pdo->commit();
            
            $success = 'Profil mis à jour avec succès';
            
            // Recharger les données utilisateur
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Erreur lors de la mise à jour: ' . $e->getMessage();
        }
    }
}

// Récupérer les logs de connexion (CORRECTION ICI)
$stmt = $pdo->prepare("
    SELECT * FROM login_logs 
    WHERE user_id = ? 
    ORDER BY login_time DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$logs = $stmt->fetchAll();

// Récupérer l'activité récente
$stmt = $pdo->prepare("
    SELECT * FROM audit_logs 
    WHERE user_id = ? 
    AND table_name != 'login_logs'
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$activities = $stmt->fetchAll();
?>

<!-- Content Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <h1 class="h2 mb-0">
        <i class="fas fa-user-circle me-2"></i>Mon Profil
    </h1>
    <div class="btn-toolbar">
        <button type="button" class="btn btn-outline-primary" onclick="printProfile()">
            <i class="fas fa-print me-1"></i>Imprimer
        </button>
    </div>
</div>

<!-- Messages d'erreur/succès -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <h6><i class="fas fa-exclamation-triangle me-2"></i>Erreurs</h6>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Colonne gauche : Informations -->
    <div class="col-lg-4 mb-4">
        <!-- Carte profil -->
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <div class="mb-4">
                    <div class="avatar-profile mx-auto">
                        <div class="mb-3">
                            <?php if (!empty($photo_url)): ?>
                                <img src="<?= htmlspecialchars($photo_url) ?>" class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                            <?php else: ?>
                                <?php 
                                $initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
                                $roleColor = $user['role'] == 'admin' ? 'danger' : 
                                            ($user['role'] == 'docteur' ? 'primary' : 
                                            ($user['role'] == 'secretaire' ? 'success' : 'warning'));
                                ?>
                                <span class="avatar-initials bg-<?php echo $roleColor; ?>">
                                    <?php echo $initials; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <h4 class="mb-1"><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></h4>
                <span class="badge bg-<?php echo $roleColor; ?> mb-3"><?php echo ucfirst($user['role']); ?></span>
                
                <div class="list-group list-group-flush text-start">
                    <div class="list-group-item">
                        <i class="fas fa-envelope me-2 text-muted"></i>
                        <?php echo htmlspecialchars($user['email']); ?>
                    </div>
                    <?php if ($user['telephone']): ?>
                    <div class="list-group-item">
                        <i class="fas fa-phone me-2 text-muted"></i>
                        <?php echo htmlspecialchars($user['telephone']); ?>
                    </div>
                    <?php endif; ?>
                    <div class="list-group-item">
                        <i class="fas fa-calendar-alt me-2 text-muted"></i>
                        Membre depuis: <?php echo date('d/m/Y', strtotime($user['date_creation'])); ?>
                    </div>
                    <?php if ($user['derniere_connexion']): ?>
                    <div class="list-group-item">
                        <i class="fas fa-sign-in-alt me-2 text-muted"></i>
                        Dernière connexion: <?php echo date('d/m/Y H:i', strtotime($user['derniere_connexion'])); ?>
                    </div>
                    <?php endif; ?>
                    <div class="list-group-item">
                        <i class="fas fa-shield-alt me-2 text-muted"></i>
                        Statut: 
                        <span class="badge bg-<?php echo $user['statut'] == 'actif' ? 'success' : 'danger'; ?>">
                            <?php echo ucfirst($user['statut']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistiques -->
        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistiques</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <?php if ($user['role'] == 'docteur'): ?>
                    <div class="col-4">
                        <div class="h4 text-primary"><?php echo $user['consultations_count'] ?? 0; ?></div>
                        <small class="text-muted">Consultations</small>
                    </div>
                    <div class="col-4">
                        <div class="h4 text-success"><?php echo $user['patients_count'] ?? 0; ?></div>
                        <small class="text-muted">Patients</small>
                    </div>
                    <div class="col-4">
                        <div class="h4 text-warning"><?php echo $user['prescriptions_count'] ?? 0; ?></div>
                        <small class="text-muted">Prescriptions</small>
                    </div>
                    <?php elseif ($user['role'] == 'secretaire' || $user['role'] == 'assistant'): ?>
                    <div class="col-6">
                        <div class="h4 text-primary"><?php echo $user['patients_count'] ?? 0; ?></div>
                        <small class="text-muted">Patients enregistrés</small>
                    </div>
                    <div class="col-6">
                        <?php
                        $rdvCount = $pdo->prepare("
                            SELECT COUNT(*) FROM rendez_vous 
                            WHERE created_by = ?
                        ")->execute([$user_id]);
                        ?>
                        <div class="h4 text-success"><?php echo $rdvCount; ?></div>
                        <small class="text-muted">RDV créés</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Colonne droite : Formulaire et historique -->
    <div class="col-lg-8">
        <!-- Formulaire de mise à jour -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-edit me-2"></i>Modifier mon profil</h6>
            </div>
            <div class="card-body">
                <form method="POST" id="profileForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nom *</label>
                            <input type="text" class="form-control" name="nom" 
                                   value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prénom *</label>
                            <input type="text" class="form-control" name="prenom" 
                                   value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" name="telephone" 
                                   value="<?php echo htmlspecialchars($user['telephone'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Adresse</label>
                            <textarea class="form-control" name="adresse" rows="2"><?php echo htmlspecialchars($user['adresse'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Section changement de mot de passe -->
                        <div class="col-12 mt-4">
                            <h6 class="border-bottom pb-2">
                                <i class="fas fa-key me-2"></i>Changer le mot de passe
                            </h6>
                            <p class="text-muted small">
                                Laissez ces champs vides si vous ne souhaitez pas changer votre mot de passe.
                            </p>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Mot de passe actuel</label>
                            <input type="password" class="form-control" name="current_password">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" name="new_password" 
                                   minlength="8">
                            <small class="text-muted">Minimum 8 caractères</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Confirmer le nouveau</label>
                            <input type="password" class="form-control" name="confirm_password">
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i>Enregistrer les modifications
                        </button>
                        <button type="reset" class="btn btn-secondary ms-2">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Historique des connexions -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Historique des connexions récentes
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date/Heure</th>
                                <th>Adresse IP</th>
                                <th>Appareil</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-3 text-muted">
                                    Aucun historique de connexion
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($log['login_time'])); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($log['ip_address']); ?></span>
                                    </td>
                                    <td>
                                        <small class="text-truncate d-block" style="max-width: 200px;">
                                            <?php echo htmlspecialchars($log['user_agent']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($log['success']): ?>
                                        <span class="badge bg-success">Succès</span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">Échec</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-top">
                <a href="logs.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-list me-1"></i>Voir tout l'historique
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<style>
.avatar-profile {
    width: 120px;
    height: 120px;
    margin: 0 auto;
}

.avatar-initials {
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    font-weight: bold;
    color: white;
}

.list-group-item {
    border: none;
    padding: 10px 0;
}
</style>

<script>
// Validation du formulaire
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const newPassword = this.querySelector('input[name="new_password"]');
    const confirmPassword = this.querySelector('input[name="confirm_password"]');
    const currentPassword = this.querySelector('input[name="current_password"]');
    
    // Si un nouveau mot de passe est saisi, vérifier les validations
    if (newPassword.value) {
        // Vérifier la longueur
        if (newPassword.value.length < 8) {
            e.preventDefault();
            alert('Le nouveau mot de passe doit contenir au moins 8 caractères');
            newPassword.focus();
            return false;
        }
        
        // Vérifier la correspondance
        if (newPassword.value !== confirmPassword.value) {
            e.preventDefault();
            alert('Les mots de passe ne correspondent pas');
            confirmPassword.focus();
            return false;
        }
        
        // Vérifier le mot de passe actuel
        if (!currentPassword.value) {
            e.preventDefault();
            alert('Veuillez saisir votre mot de passe actuel');
            currentPassword.focus();
            return false;
        }
    }
    
    // Vérifier la confirmation si le mot de passe actuel est saisi
    if (currentPassword.value && !newPassword.value) {
        e.preventDefault();
        alert('Veuillez saisir un nouveau mot de passe');
        newPassword.focus();
        return false;
    }
});

// Afficher/cacher le formulaire de changement de mot de passe
function togglePasswordChange() {
    const passwordSection = document.getElementById('passwordChangeSection');
    passwordSection.classList.toggle('d-none');
}

// Imprimer le profil
function printProfile() {
    const printContent = document.querySelector('.card:first-child').outerHTML;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Profil Utilisateur</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .avatar-initials { 
                    width: 100px; height: 100px; border-radius: 50%; 
                    display: flex; align-items: center; justify-content: center;
                    font-size: 30px; font-weight: bold; color: white; margin: 0 auto 20px;
                }
                .list-group-item { padding: 8px 0; border-bottom: 1px solid #eee; }
                .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
                h4 { color: #4361ee; margin-bottom: 10px; }
                .text-muted { color: #6c757d; }
            </style>
        </head>
        <body>
            <h2 style="color: #4361ee; border-bottom: 2px solid #4361ee; padding-bottom: 10px;">
                Profil Utilisateur
            </h2>
            <div style="margin-bottom: 20px; color: #6c757d;">
                Date d'impression: ${new Date().toLocaleDateString('fr-FR')}
            </div>
            ${printContent}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

// Toggle pour voir le mot de passe
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>