<?php
// settings.php
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$title = 'Paramètres du compte docteur';
require_once '../includes/header.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../logout.php');
    exit();
}

// Variables pour les messages
$success = '';
$error = '';

// Traitement du formulaire de mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = sanitize($_POST);
    
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['update_profile'])) {
            // Mettre à jour les informations de base
            $stmt = $pdo->prepare("
                UPDATE utilisateurs SET 
                nom = ?, prenom = ?, email = ?, telephone = ?, 
                adresse = ?, date_naissance = ?, sexe = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['nom'],
                $data['prenom'],
                $data['email'],
                $data['telephone'] ?? null,
                $data['adresse'] ?? null,
                $data['date_naissance'] ?? null,
                $data['sexe'] ?? null,
                $user_id
            ]);
            
            // Mettre à jour la session
            $_SESSION['nom'] = $data['nom'];
            $_SESSION['prenom'] = $data['prenom'];
            
            // Journaliser l'action
            logAction('UPDATE', 'utilisateurs', $user_id, "Mise à jour du profil");
            
            $success = "Profil mis à jour avec succès";
            
        } elseif (isset($_POST['change_password'])) {
            // Vérifier l'ancien mot de passe
            if (!password_verify($data['current_password'], $user['password'])) {
                $error = "Mot de passe actuel incorrect";
            } elseif ($data['new_password'] !== $data['confirm_password']) {
                $error = "Les nouveaux mots de passe ne correspondent pas";
            } elseif (strlen($data['new_password']) < 8) {
                $error = "Le mot de passe doit contenir au moins 8 caractères";
            } else {
                // Hasher le nouveau mot de passe
                $hashed_password = password_hash($data['new_password'], PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE utilisateurs SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                // Journaliser l'action
                logAction('UPDATE', 'utilisateurs', $user_id, "Changement de mot de passe");
                
                $success = "Mot de passe modifié avec succès";
            }
            
        } elseif (isset($_POST['update_preferences'])) {
            // Mettre à jour les préférences
            $preferences = [
                'theme' => $data['theme'] ?? 'light',
                'notifications_email' => isset($data['notifications_email']) ? 1 : 0,
                'notifications_sms' => isset($data['notifications_sms']) ? 1 : 0,
                'language' => $data['language'] ?? 'fr'
            ];
            
            // Dans une vraie application, vous auriez une table de préférences
            // Pour cet exemple, on stocke en JSON dans un champ
            $stmt = $pdo->prepare("UPDATE utilisateurs SET preferences = ? WHERE id = ?");
            $stmt->execute([json_encode($preferences), $user_id]);
            
            // Mettre à jour le thème de la session
            $_SESSION['theme'] = $preferences['theme'];
            
            $success = "Préférences mises à jour avec succès";
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur: " . $e->getMessage();
    }
    
    // Recharger les données utilisateur
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

// Décoder les préférences
// $preferences = $user['preferences'] ? json_decode($user['preferences'], true) : [
//     'theme' => 'light',
//     'notifications_email' => 1,
//     'notifications_sms' => 0,
//     'language' => 'fr'
// ];
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-user-cog me-2"></i>Paramètres du compte
        </h1>
        <p class="text-muted mb-0">Gérez vos préférences et informations personnelles</p>
    </div>
    <div class="btn-toolbar">
        <button type="button" class="btn btn-outline-primary" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Imprimer
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

<!-- Navigation entre les onglets -->
<ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" 
                data-bs-target="#profile" type="button">
            <i class="fas fa-user me-2"></i>Profil
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="security-tab" data-bs-toggle="tab" 
                data-bs-target="#security" type="button">
            <i class="fas fa-shield-alt me-2"></i>Sécurité
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" 
                data-bs-target="#preferences" type="button">
            <i class="fas fa-sliders-h me-2"></i>Préférences
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="activity-tab" data-bs-toggle="tab" 
                data-bs-target="#activity" type="button">
            <i class="fas fa-history me-2"></i>Activité
        </button>
    </li>
    <?php if ($role === 'docteur'): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="professional-tab" data-bs-toggle="tab" 
                data-bs-target="#professional" type="button">
            <i class="fas fa-briefcase me-2"></i>Professionnel
        </button>
    </li>
    <?php endif; ?>
</ul>

<!-- Contenu des onglets -->
<div class="tab-content" id="settingsTabsContent">
    <!-- Onglet Profil -->
    <div class="tab-pane fade show active" id="profile" role="tabpanel">
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-user-edit me-2"></i>Informations personnelles</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label required">Nom</label>
                                    <input type="text" class="form-control" name="nom" 
                                           value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Prénom</label>
                                    <input type="text" class="form-control" name="prenom" 
                                           value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label required">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" name="telephone" 
                                           value="<?php echo htmlspecialchars($user['telephone'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Date de naissance</label>
                                    <input type="date" class="form-control" name="date_naissance" 
                                           value="<?php echo $user['date_naissance'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Sexe</label>
                                    <select class="form-select" name="sexe">
                                        <option value="">Sélectionner</option>
                                        <option value="M" <?php echo ($user['sexe'] ?? '') == 'M' ? 'selected' : ''; ?>>Masculin</option>
                                        <option value="F" <?php echo ($user['sexe'] ?? '') == 'F' ? 'selected' : ''; ?>>Féminin</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Adresse</label>
                                    <textarea class="form-control" name="adresse" rows="2"><?php echo htmlspecialchars($user['adresse'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Rôle</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Spécialité</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['specialite'] ?? 'Non spécifiée'); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="mt-4 border-top pt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Photo de profil -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-camera me-2"></i>Photo de profil</h6>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <div class="avatar-xl mx-auto">
                                <?php 
                                $initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
                                $roleColor = $role === 'admin' ? 'danger' : 
                                            ($role === 'docteur' ? 'primary' : 
                                            ($role === 'secretaire' ? 'success' : 'warning'));
                                ?>
                                <span class="avatar-initials bg-<?php echo $roleColor; ?>">
                                    <?php echo $initials; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h5 class="mb-1"><?php echo $user['prenom'] . ' ' . $user['nom']; ?></h5>
                            <p class="text-muted mb-2"><?php echo ucfirst($role); ?></p>
                            <?php if ($user['specialite']): ?>
                            <span class="badge bg-primary"><?php echo $user['specialite']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary" onclick="changeAvatar()">
                                <i class="fas fa-upload me-1"></i>Changer la photo
                            </button>
                            <button type="button" class="btn btn-outline-danger" onclick="removeAvatar()">
                                <i class="fas fa-trash me-1"></i>Supprimer la photo
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Statut du compte -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Statut du compte</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Date de création</span>
                                <span class="fw-semibold"><?php echo formatDate($user['date_creation']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Dernière connexion</span>
                                <span class="fw-semibold"><?php echo $user['derniere_connexion'] ? formatDate($user['derniere_connexion']) : 'Jamais'; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Statut</span>
                                <span class="badge bg-<?php echo $user['statut'] == 'actif' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($user['statut']); ?>
                                </span>
                            </li>
                        </ul>
                        
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-warning w-100 mb-2" 
                                    onclick="requestAccountDeletion()">
                                <i class="fas fa-user-times me-1"></i>Demander la suppression
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Onglet Sécurité -->
    <div class="tab-pane fade" id="security" role="tabpanel">
        <div class="row">
            <div class="col-lg-6">
                <!-- Changement de mot de passe -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-key me-2"></i>Changer le mot de passe</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="passwordForm">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label required">Mot de passe actuel</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="current_password" 
                                           id="current_password" required>
                                    <button class="btn btn-outline-secondary" type="button" 
                                            onclick="togglePassword('current_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required">Nouveau mot de passe</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="new_password" 
                                           id="new_password" required minlength="8">
                                    <button class="btn btn-outline-secondary" type="button" 
                                            onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    <ul class="small text-muted mb-0">
                                        <li>Minimum 8 caractères</li>
                                        <li>Lettres majuscules et minuscules</li>
                                        <li>Au moins un chiffre</li>
                                        <li>Au moins un caractère spécial</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label required">Confirmer le nouveau mot de passe</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="confirm_password" 
                                           id="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" 
                                            onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key me-1"></i>Changer le mot de passe
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <!-- Authentification à deux facteurs -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-mobile-alt me-2"></i>Authentification à deux facteurs</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="2faToggle" 
                                       <?php echo $preferences['2fa_enabled'] ?? false ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="2faToggle">
                                    Activer l'authentification à deux facteurs
                                </label>
                            </div>
                            <p class="text-muted small mt-2">
                                Ajoutez une couche de sécurité supplémentaire à votre compte
                            </p>
                        </div>
                        
                        <div id="2faSetup" class="<?php echo $preferences['2fa_enabled'] ?? false ? '' : 'd-none'; ?>">
                            <hr>
                            
                            <div class="text-center mb-3">
                                <div id="qrcode" class="mb-3"></div>
                                <p class="small text-muted">
                                    Scannez ce code QR avec Google Authenticator ou une application similaire
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Code de vérification</label>
                                <input type="text" class="form-control" placeholder="Entrez le code à 6 chiffres" 
                                       maxlength="6" id="2faCode">
                            </div>
                            
                            <button type="button" class="btn btn-success w-100" onclick="verify2FACode()">
                                <i class="fas fa-check me-1"></i>Vérifier et activer
                            </button>
                        </div>
                        
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-secondary w-100" onclick="setup2FA()">
                                <i class="fas fa-qrcode me-1"></i>Configurer l'authentification
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Sessions actives -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-laptop me-2"></i>Sessions actives</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT * FROM login_logs 
                            WHERE user_id = ? 
                            ORDER BY login_time DESC 
                            LIMIT 5
                        ");
                        $stmt->execute([$user_id]);
                        $sessions = $stmt->fetchAll();
                        ?>
                        
                        <div class="list-group list-group-flush">
                            <?php foreach ($sessions as $session): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fw-semibold">
                                            <i class="fas fa-<?php echo $session['success'] ? 'check text-success' : 'times text-danger'; ?> me-2"></i>
                                            <?php echo formatDate($session['login_time'], 'd/m/Y H:i'); ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $session['ip_address']; ?> • 
                                            <?php echo $session['user_agent']; ?>
                                        </small>
                                    </div>
                                    <div>
                                        <?php if ($session['success']): ?>
                                        <span class="badge bg-success">Actif</span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">Échoué</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-danger w-100" onclick="terminateAllSessions()">
                                <i class="fas fa-sign-out-alt me-1"></i>Terminer toutes les sessions
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Onglet Préférences -->
    <div class="tab-pane fade" id="preferences" role="tabpanel">
        <div class="row">
            <div class="col-lg-6">
                <!-- Préférences d'affichage -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-palette me-2"></i>Apparence</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_preferences" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label">Thème</label>
                                <select class="form-select" name="theme">
                                    <option value="light" <?php echo ($preferences['theme'] ?? 'light') == 'light' ? 'selected' : ''; ?>>Clair</option>
                                    <option value="dark" <?php echo ($preferences['theme'] ?? 'light') == 'dark' ? 'selected' : ''; ?>>Sombre</option>
                                    <option value="auto" <?php echo ($preferences['theme'] ?? 'light') == 'auto' ? 'selected' : ''; ?>>Auto</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Langue</label>
                                <select class="form-select" name="language">
                                    <option value="fr" <?php echo ($preferences['language'] ?? 'fr') == 'fr' ? 'selected' : ''; ?>>Français</option>
                                    <option value="en" <?php echo ($preferences['language'] ?? 'fr') == 'en' ? 'selected' : ''; ?>>Anglais</option>
                                    <option value="es" <?php echo ($preferences['language'] ?? 'fr') == 'es' ? 'selected' : ''; ?>>Espagnol</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Densité d'affichage</label>
                                <select class="form-select" name="density">
                                    <option value="comfortable" <?php echo ($preferences['density'] ?? 'comfortable') == 'comfortable' ? 'selected' : ''; ?>>Confortable</option>
                                    <option value="compact" <?php echo ($preferences['density'] ?? 'comfortable') == 'compact' ? 'selected' : ''; ?>>Compact</option>
                                </select>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer les préférences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <!-- Notifications -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_preferences" value="1">
                            
                            <div class="mb-3">
                                <h6>Types de notifications</h6>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="notifications_email" 
                                           id="notifications_email" value="1" 
                                           <?php echo ($preferences['notifications_email'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notifications_email">
                                        Notifications par email
                                    </label>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="notifications_sms" 
                                           id="notifications_sms" value="1" 
                                           <?php echo ($preferences['notifications_sms'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notifications_sms">
                                        Notifications par SMS
                                    </label>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="notifications_push" 
                                           id="notifications_push" value="1" 
                                           <?php echo ($preferences['notifications_push'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notifications_push">
                                        Notifications push (navigateur)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6>Événements à notifier</h6>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="notify_new_message" 
                                           id="notify_new_message" value="1" 
                                           <?php echo ($preferences['notify_new_message'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_new_message">
                                        Nouveaux messages
                                    </label>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="notify_appointment" 
                                           id="notify_appointment" value="1" 
                                           <?php echo ($preferences['notify_appointment'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_appointment">
                                        Rendez-vous et rappels
                                    </label>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="notify_system" 
                                           id="notify_system" value="1" 
                                           <?php echo ($preferences['notify_system'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_system">
                                        Mises à jour système
                                    </label>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="notify_security" 
                                           id="notify_security" value="1" 
                                           <?php echo ($preferences['notify_security'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_security">
                                        Alertes de sécurité
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer les préférences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Privacy -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-user-shield me-2"></i>Confidentialité</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="profileVisibility" 
                                       <?php echo ($preferences['profile_public'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="profileVisibility">
                                    Profil visible par les autres utilisateurs
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="activitySharing" 
                                       <?php echo ($preferences['share_activity'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="activitySharing">
                                    Partager mon activité
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="dataCollection" 
                                       <?php echo ($preferences['allow_data_collection'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="dataCollection">
                                    Autoriser la collecte de données anonymes
                                </label>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="button" class="btn btn-outline-primary w-100 mb-2" 
                                    onclick="downloadPersonalData()">
                                <i class="fas fa-download me-1"></i>Télécharger mes données
                            </button>
                            <button type="button" class="btn btn-outline-danger w-100" 
                                    onclick="deletePersonalData()">
                                <i class="fas fa-trash me-1"></i>Supprimer mes données
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Onglet Activité -->
    <div class="tab-pane fade" id="activity" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Historique des activités</h6>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearActivityHistory()">
                    <i class="fas fa-trash me-1"></i>Effacer l'historique
                </button>
            </div>
            <div class="card-body p-0">
                <div class="timeline p-4">
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT * FROM audit_logs 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 20
                    ");
                    $stmt->execute([$user_id]);
                    $activities = $stmt->fetchAll();
                    
                    if (empty($activities)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune activité récente</p>
                    </div>
                    <?php else: 
                        foreach ($activities as $activity): 
                            $icon = getActivityIcon($activity['action']);
                            $color = getActivityColor($activity['action']);
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-<?php echo $color; ?>">
                            <i class="fas fa-<?php echo $icon; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-1"><?php echo $activity['action']; ?></h6>
                                <small class="text-muted"><?php echo formatDate($activity['created_at'], 'd/m H:i'); ?></small>
                            </div>
                            <p class="mb-1 small"><?php echo $activity['table_name']; ?> • ID: <?php echo $activity['record_id']; ?></p>
                            <?php if ($activity['new_values']): ?>
                            <small class="text-muted"><?php echo substr($activity['new_values'], 0, 100); ?>...</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Statistiques d'activité -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-white-50">Actions ce mois</h6>
                                <?php
                                $stmt = $pdo->prepare("
                                    SELECT COUNT(*) FROM audit_logs 
                                    WHERE user_id = ? 
                                    AND MONTH(created_at) = MONTH(CURDATE())
                                    AND YEAR(created_at) = YEAR(CURDATE())
                                ");
                                $stmt->execute([$user_id]);
                                $month_actions = $stmt->fetchColumn();
                                ?>
                                <h2 class="mb-0"><?php echo $month_actions; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-bolt fa-2x opacity-50"></i>
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
                                <h6 class="text-white-50">Patients traités</h6>
                                <?php
                                $stmt = $pdo->prepare("
                                    SELECT COUNT(DISTINCT patient_id) FROM consultations 
                                    WHERE docteur_id = ?
                                ");
                                $stmt->execute([$user_id]);
                                $patients_treated = $stmt->fetchColumn();
                                ?>
                                <h2 class="mb-0"><?php echo $patients_treated; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user-injured fa-2x opacity-50"></i>
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
                                <h6 class="text-white-50">Consultations</h6>
                                <?php
                                $stmt = $pdo->prepare("
                                    SELECT COUNT(*) FROM consultations 
                                    WHERE docteur_id = ?
                                ");
                                $stmt->execute([$user_id]);
                                $consultations_count = $stmt->fetchColumn();
                                ?>
                                <h2 class="mb-0"><?php echo $consultations_count; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-stethoscope fa-2x opacity-50"></i>
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
                                <h6 class="text-white-50">Jours actifs</h6>
                                <?php
                                $stmt = $pdo->prepare("
                                    SELECT COUNT(DISTINCT DATE(created_at)) FROM audit_logs 
                                    WHERE user_id = ?
                                ");
                                $stmt->execute([$user_id]);
                                $active_days = $stmt->fetchColumn();
                                ?>
                                <h2 class="mb-0"><?php echo $active_days; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-alt fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($role === 'docteur'): ?>
    <!-- Onglet Professionnel -->
    <div class="tab-pane fade" id="professional" role="tabpanel">
        <div class="row">
            <div class="col-lg-8">
                <!-- Informations professionnelles -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-briefcase me-2"></i>Informations professionnelles</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="professionalForm">
                            <input type="hidden" name="update_professional" value="1">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Numéro RPPS</label>
                                    <input type="text" class="form-control" name="rpps" 
                                           value="<?php echo htmlspecialchars($user['rpps'] ?? ''); ?>"
                                           placeholder="Numéro d'identification professionnel">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Spécialité principale</label>
                                    <input type="text" class="form-control" name="specialite" 
                                           value="<?php echo htmlspecialchars($user['specialite'] ?? ''); ?>"
                                           placeholder="Ex: Cardiologie, Dermatologie...">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Diplôme</label>
                                    <input type="text" class="form-control" name="diplome" 
                                           value="<?php echo htmlspecialchars($user['diplome'] ?? ''); ?>"
                                           placeholder="Ex: Doctorat en médecine">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Année d'obtention</label>
                                    <input type="number" class="form-control" name="annee_diplome" 
                                           value="<?php echo htmlspecialchars($user['annee_diplome'] ?? ''); ?>"
                                           min="1900" max="<?php echo date('Y'); ?>">
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Université/École</label>
                                    <input type="text" class="form-control" name="universite" 
                                           value="<?php echo htmlspecialchars($user['universite'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Expérience professionnelle</label>
                                    <textarea class="form-control" name="experience" rows="4"><?php echo htmlspecialchars($user['experience'] ?? ''); ?></textarea>
                                    <small class="text-muted">Parcours professionnel et expériences</small>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Compétences spécialisées</label>
                                    <textarea class="form-control" name="competences" rows="3"><?php echo htmlspecialchars($user['competences'] ?? ''); ?></textarea>
                                    <small class="text-muted">Compétences, techniques maîtrisées</small>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Certifications</label>
                                    <textarea class="form-control" name="certifications" rows="3"><?php echo htmlspecialchars($user['certifications'] ?? ''); ?></textarea>
                                    <small class="text-muted">Certifications et formations continues</small>
                                </div>
                            </div>
                            
                            <div class="mt-4 border-top pt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer les informations
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Disponibilités -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Disponibilités</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>Jours de consultation</h6>
                            <?php
                            $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
                            $jours_actifs = $user['jours_consultation'] ? json_decode($user['jours_consultation'], true) : [];
                            ?>
                            <div class="row">
                                <?php foreach ($jours as $index => $jour): ?>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="jours_consultation[]" value="<?php echo $index; ?>"
                                               id="jour_<?php echo $index; ?>"
                                               <?php echo in_array($index, $jours_actifs) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="jour_<?php echo $index; ?>">
                                            <?php echo $jour; ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Heures de début</label>
                            <input type="time" class="form-control" name="heure_debut" 
                                   value="<?php echo $user['heure_debut'] ?? '08:00'; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Heures de fin</label>
                            <input type="time" class="form-control" name="heure_fin" 
                                   value="<?php echo $user['heure_fin'] ?? '18:00'; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Durée des consultations (minutes)</label>
                            <input type="number" class="form-control" name="duree_consultation" 
                                   value="<?php echo $user['duree_consultation'] ?? 30; ?>" min="15" step="5">
                        </div>
                        
                        <button type="button" class="btn btn-outline-primary w-100" onclick="saveAvailability()">
                            <i class="fas fa-save me-1"></i>Enregistrer les disponibilités
                        </button>
                    </div>
                </div>
                
                <!-- Signature électronique -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-signature me-2"></i>Signature électronique</h6>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <div id="signatureCanvas" class="border rounded bg-white mb-3" 
                                 style="height: 150px; cursor: crosshair;"></div>
                            
                            <div class="btn-group w-100 mb-2">
                                <button type="button" class="btn btn-outline-primary" onclick="clearSignature()">
                                    <i class="fas fa-eraser me-1"></i>Effacer
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="saveSignature()">
                                    <i class="fas fa-save me-1"></i>Sauvegarder
                                </button>
                            </div>
                            
                            <?php if ($user['signature']): ?>
                            <div class="mt-3">
                                <h6>Signature actuelle</h6>
                                <img src="<?php echo $user['signature']; ?>" alt="Signature" class="img-fluid border rounded p-2">
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="deleteSignature()">
                                    <i class="fas fa-trash me-1"></i>Supprimer
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                Cette signature sera utilisée pour signer vos prescriptions et documents médicaux
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Upload Photo -->
<div class="modal fade" id="avatarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Changer la photo de profil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div id="avatarPreview" class="avatar-xl mx-auto mb-3">
                        <span class="avatar-initials bg-primary" id="avatarInitials">
                            <?php echo $initials; ?>
                        </span>
                    </div>
                    <input type="file" class="form-control" id="avatarInput" accept="image/*">
                </div>
                <div class="alert alert-info">
                    <small>
                        <i class="fas fa-info-circle me-2"></i>
                        Taille maximale : 2MB. Formats acceptés : JPG, PNG, GIF
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="uploadAvatar()">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// Variables globales
let signaturePad = null;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser la signature
    initSignaturePad();
    
    // Sauvegarder l'onglet actif
    document.querySelectorAll('#settingsTabs button').forEach(tab => {
        tab.addEventListener('click', function() {
            localStorage.setItem('activeSettingsTab', this.id);
        });
    });
    
    // Restaurer l'onglet actif
    const activeTab = localStorage.getItem('activeSettingsTab');
    if (activeTab) {
        const tab = document.querySelector(`#${activeTab}`);
        if (tab) {
            new bootstrap.Tab(tab).show();
        }
    }
    
    // Initialiser le 2FA
    init2FA();
});

// Fonctions pour la photo de profil
function changeAvatar() {
    const modal = new bootstrap.Modal(document.getElementById('avatarModal'));
    modal.show();
    
    // Prévisualisation de l'image
    document.getElementById('avatarInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('avatarPreview');
                preview.innerHTML = `<img src="${e.target.result}" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">`;
            };
            reader.readAsDataURL(file);
        }
    });
}

function uploadAvatar() {
    const fileInput = document.getElementById('avatarInput');
    const file = fileInput.files[0];
    
    if (!file) {
        showToast('Veuillez sélectionner une image', 'warning');
        return;
    }
    
    // Vérifier la taille du fichier
    if (file.size > 2 * 1024 * 1024) {
        showToast('L\'image est trop volumineuse (max 2MB)', 'danger');
        return;
    }
    
    const formData = new FormData();
    formData.append('avatar', file);
    
    fetch('ajax/upload_avatar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Photo de profil mise à jour', 'success');
            bootstrap.Modal.getInstance(document.getElementById('avatarModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Erreur: ' + data.error, 'danger');
        }
    });
}

function removeAvatar() {
    if (confirm('Supprimer la photo de profil ?')) {
        fetch('ajax/remove_avatar.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Photo de profil supprimée', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Erreur: ' + data.error, 'danger');
            }
        });
    }
}

// Fonctions pour le mot de passe
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    field.type = field.type === 'password' ? 'text' : 'password';
}

// Fonctions pour le 2FA
function init2FA() {
    document.getElementById('2faToggle').addEventListener('change', function() {
        const setupDiv = document.getElementById('2faSetup');
        if (this.checked) {
            setupDiv.classList.remove('d-none');
            generateQRCode();
        } else {
            setupDiv.classList.add('d-none');
            disable2FA();
        }
    });
}

function generateQRCode() {
    // Générer un QR code pour l'authentification 2FA
    const qrcodeDiv = document.getElementById('qrcode');
    qrcodeDiv.innerHTML = '<div class="spinner-border text-primary"></div>';
    
    fetch('ajax/generate_2fa_qr.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                qrcodeDiv.innerHTML = `<img src="${data.qr_code}" alt="QR Code 2FA" class="img-fluid">`;
            }
        });
}

function verify2FACode() {
    const code = document.getElementById('2faCode').value;
    
    if (!code || code.length !== 6) {
        showToast('Veuillez entrer un code valide à 6 chiffres', 'warning');
        return;
    }
    
    fetch('ajax/verify_2fa_code.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code: code })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Authentification à deux facteurs activée', 'success');
        } else {
            showToast('Code incorrect', 'danger');
        }
    });
}

function disable2FA() {
    fetch('ajax/disable_2fa.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Authentification à deux facteurs désactivée', 'success');
            }
        });
}

function setup2FA() {
    document.getElementById('2faToggle').checked = true;
    document.getElementById('2faSetup').classList.remove('d-none');
    generateQRCode();
}

// Fonctions pour les sessions
function terminateAllSessions() {
    if (confirm('Terminer toutes les sessions actives ? Vous serez déconnecté de tous les appareils.')) {
        fetch('ajax/terminate_sessions.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Toutes les sessions ont été terminées', 'success');
                    setTimeout(() => {
                        window.location.href = 'logout.php';
                    }, 2000);
                }
            });
    }
}

// Fonctions pour la confidentialité
function downloadPersonalData() {
    if (confirm('Télécharger toutes vos données personnelles ?')) {
        fetch('ajax/download_personal_data.php')
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'mes-donnees-personnelles.zip';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            });
    }
}

function deletePersonalData() {
    if (confirm('Supprimer toutes vos données personnelles ? Cette action est irréversible.')) {
        fetch('ajax/delete_personal_data.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Vos données ont été supprimées', 'success');
                    setTimeout(() => {
                        window.location.href = 'logout.php';
                    }, 2000);
                }
            });
    }
}

function requestAccountDeletion() {
    if (confirm('Demander la suppression de votre compte ? Cette demande sera traitée par un administrateur.')) {
        fetch('ajax/request_account_deletion.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Demande de suppression envoyée', 'success');
                }
            });
    }
}

// Fonctions pour les préférences
function clearActivityHistory() {
    if (confirm('Effacer tout l'historique d'activité ?')) {
        fetch('ajax/clear_activity_history.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Historique d'activité effacé', 'success');
                    setTimeout(() => location.reload(), 1000);
                }
            });
    }
}

// Fonctions pour la signature électronique
function initSignaturePad() {
    const canvas = document.getElementById('signatureCanvas');
    if (canvas) {
        signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)'
        });
        
        // Ajuster la taille du canvas
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            signaturePad.clear();
        }
        
        window.addEventListener("resize", resizeCanvas);
        resizeCanvas();
    }
}

function clearSignature() {
    if (signaturePad) {
        signaturePad.clear();
    }
}

function saveSignature() {
    if (signaturePad && !signaturePad.isEmpty()) {
        const dataURL = signaturePad.toDataURL();
        
        fetch('ajax/save_signature.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ signature: dataURL })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Signature enregistrée avec succès', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Erreur: ' + data.error, 'danger');
            }
        });
    } else {
        showToast('Veuillez signer dans le champ prévu', 'warning');
    }
}

function deleteSignature() {
    if (confirm('Supprimer votre signature électronique ?')) {
        fetch('ajax/delete_signature.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Signature supprimée', 'success');
                    setTimeout(() => location.reload(), 1000);
                }
            });
    }
}

// Fonctions pour les disponibilités
function saveAvailability() {
    const jours = [];
    document.querySelectorAll('input[name="jours_consultation[]"]:checked').forEach(checkbox => {
        jours.push(checkbox.value);
    });
    
    const data = {
        jours: jours,
        heure_debut: document.querySelector('input[name="heure_debut"]').value,
        heure_fin: document.querySelector('input[name="heure_fin"]').value,
        duree_consultation: document.querySelector('input[name="duree_consultation"]').value
    };
    
    fetch('ajax/save_availability.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Disponibilités enregistrées', 'success');
        } else {
            showToast('Erreur: ' + data.error, 'danger');
        }
    });
}

// Fonction utilitaire pour afficher des toasts
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type}`;
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
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
</script>

<style>
/* Styles pour les paramètres */
.avatar-xl {
    width: 100px;
    height: 100px;
    margin: 0 auto;
}

.avatar-initials {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    font-weight: bold;
    color: white;
}

/* Timeline pour l'activité */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
    border-left: 2px solid #e5e7eb;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -12px;
    top: 0;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background-color: #4361ee;
    color: white;
    border: 3px solid white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

.timeline-content {
    padding-left: 20px;
}

/* Canvas pour la signature */
#signatureCanvas {
    background-color: white;
}

/* Responsive */
@media (max-width: 768px) {
    .avatar-xl {
        width: 80px;
        height: 80px;
    }
    
    .avatar-initials {
        width: 80px;
        height: 80px;
        font-size: 28px;
    }
}
</style>