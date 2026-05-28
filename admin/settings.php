<?php
// settings.php
require_once '../config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$pdo = Database::getInstance()->getConnection();

$title = 'Paramètres du compte';
require_once '../includes/header.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Récupérer l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: ../logout.php');
    exit();
}

// Créer le dossier pour les avatars s'il n'existe pas
define('UPLOAD_DIR', '../uploads/avatars/');
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Fonctions utilitaires
if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'd/m/Y H:i') {
        if (empty($date)) return '—';
        return (new DateTime($date))->format($format);
    }
}

// Préférences par défaut
$preferences = [];
if (!empty($user['preferences'])) {
    $preferences = json_decode($user['preferences'], true);
}
$preferences = array_merge([
    'theme' => 'light',
    'notifications_email' => 1,
    'notifications_sms' => 0,
    'language' => 'fr',
    'density' => 'comfortable',
    'notify_new_message' => 1,
    'notify_appointment' => 1,
    'notify_system' => 0,
    'notify_security' => 1,
    'profile_public' => 0,
    'share_activity' => 0,
    'allow_data_collection' => 1,
], $preferences);

// --- Traitement POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = sanitize($_POST);
    
    try {
        $pdo->beginTransaction();
        
        // 1. Mise à jour du profil
        if (isset($_POST['update_profile'])) {
            $stmt = $pdo->prepare("
                UPDATE utilisateurs SET 
                nom = ?, prenom = ?, email = ?, telephone = ?, 
                adresse = ?, date_naissance = ?, sexe = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['nom'], $data['prenom'], $data['email'],
                $data['telephone'] ?? null, $data['adresse'] ?? null,
                $data['date_naissance'] ?? null, $data['sexe'] ?? null,
                $user_id
            ]);
            $_SESSION['nom'] = $data['nom'];
            $_SESSION['prenom'] = $data['prenom'];
            logAction('UPDATE', 'utilisateurs', $user_id, "Mise à jour du profil");
            $_SESSION['success'] = "Profil mis à jour avec succès";
            
        // 2. Changement de mot de passe
        } elseif (isset($_POST['change_password'])) {
            if (!password_verify($data['current_password'], $user['password'])) {
                throw new Exception("Mot de passe actuel incorrect");
            }
            if ($data['new_password'] !== $data['confirm_password']) {
                throw new Exception("Les nouveaux mots de passe ne correspondent pas");
            }
            if (strlen($data['new_password']) < 8) {
                throw new Exception("Le mot de passe doit contenir au moins 8 caractères");
            }
            $hashed = password_hash($data['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE utilisateurs SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $user_id]);
            logAction('UPDATE', 'utilisateurs', $user_id, "Changement de mot de passe");
            $_SESSION['success'] = "Mot de passe modifié avec succès";
            
        // 3. Upload de la photo de profil
        } elseif (isset($_POST['upload_avatar'])) {
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['avatar'];
                $allowed = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($file['type'], $allowed)) {
                    throw new Exception("Format non autorisé. Utilisez JPG, PNG ou GIF.");
                }
                if ($file['size'] > 2 * 1024 * 1024) {
                    throw new Exception("Le fichier ne doit pas dépasser 2 Mo.");
                }
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                $destination = UPLOAD_DIR . $filename;
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Supprimer l'ancienne photo
                    if (!empty($user['photo']) && file_exists('../' . $user['photo'])) {
                        unlink('../' . $user['photo']);
                    }
                    $photo_path = 'uploads/avatars/' . $filename;
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET photo = ? WHERE id = ?");
                    $stmt->execute([$photo_path, $user_id]);
                    $_SESSION['success'] = "Photo de profil mise à jour.";
                } else {
                    throw new Exception("Erreur lors de l'enregistrement.");
                }
            } else {
                throw new Exception("Aucun fichier reçu ou erreur d'upload.");
            }
            
        // 4. Suppression de la photo
        } elseif (isset($_POST['delete_avatar'])) {
            if (!empty($user['photo']) && file_exists('../' . $user['photo'])) {
                unlink('../' . $user['photo']);
            }
            $stmt = $pdo->prepare("UPDATE utilisateurs SET photo = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['success'] = "Photo de profil supprimée.";
            
        // 5. Mise à jour des préférences
        } elseif (isset($_POST['update_preferences'])) {
            // Vérifier/créer la colonne preferences
            try {
                $pdo->query("SELECT preferences FROM utilisateurs LIMIT 1");
            } catch (PDOException $e) {
                $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN preferences TEXT NULL");
            }
            $new_prefs = [
                'theme' => $data['theme'] ?? 'light',
                'notifications_email' => isset($data['notifications_email']) ? 1 : 0,
                'notifications_sms' => isset($data['notifications_sms']) ? 1 : 0,
                'language' => $data['language'] ?? 'fr',
                'density' => $data['density'] ?? 'comfortable',
                'notify_new_message' => isset($data['notify_new_message']) ? 1 : 0,
                'notify_appointment' => isset($data['notify_appointment']) ? 1 : 0,
                'notify_system' => isset($data['notify_system']) ? 1 : 0,
                'notify_security' => isset($data['notify_security']) ? 1 : 0,
                'profile_public' => isset($data['profile_public']) ? 1 : 0,
                'share_activity' => isset($data['share_activity']) ? 1 : 0,
                'allow_data_collection' => isset($data['allow_data_collection']) ? 1 : 0
            ];
            $stmt = $pdo->prepare("UPDATE utilisateurs SET preferences = ? WHERE id = ?");
            $stmt->execute([json_encode($new_prefs), $user_id]);
            $_SESSION['theme'] = $new_prefs['theme'];
            $_SESSION['success'] = "Préférences mises à jour.";
            
        // 6. Mise à jour des infos professionnelles (docteur)
        } elseif (isset($_POST['update_professional']) && $role === 'docteur') {
            // Mettre à jour les champs professionnels (ils doivent exister dans la table)
            $fields = ['rpps', 'specialite', 'diplome', 'annee_diplome', 'universite', 'experience', 'competences', 'certifications', 'heure_debut', 'heure_fin', 'duree_consultation'];
            $setParts = [];
            $params = [];
            foreach ($fields as $field) {
                // Vérifier si la colonne existe, sinon ignorer
                try {
                    $pdo->query("SELECT $field FROM utilisateurs LIMIT 1");
                    $setParts[] = "$field = ?";
                    $params[] = $data[$field] ?? null;
                } catch (PDOException $e) {}
            }
            // Jours de consultation (encodé en JSON)
            if (isset($data['jours_consultation'])) {
                $jours = json_encode($data['jours_consultation']);
                $setParts[] = "jours_consultation = ?";
                $params[] = $jours;
            }
            if (!empty($setParts)) {
                $params[] = $user_id;
                $sql = "UPDATE utilisateurs SET " . implode(', ', $setParts) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
            $_SESSION['success'] = "Informations professionnelles mises à jour.";
        }
        
        $pdo->commit();
        // Recharger les données de l'utilisateur
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        
    }
}

// Messages flash
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show">' . htmlspecialchars($_SESSION['success']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show">' . htmlspecialchars($_SESSION['error']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['error']);
}

$photo_url = '';
if (!empty($user['photo'])) {
    $photo_url = '../' . $user['photo'];
}

$initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
$roleColor = $role === 'admin' ? 'danger' : ($role === 'docteur' ? 'primary' : ($role === 'secretaire' ? 'success' : 'warning'));
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0"><i class="fas fa-user-cog me-2"></i>Paramètres du compte</h1>
        <p class="text-muted mb-0">Gérez vos préférences et informations personnelles</p>
    </div>
</div>

<!-- Navigation onglets -->
<ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile"><i class="fas fa-user me-2"></i>Profil</button></li>
    <li class="nav-item"><button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security"><i class="fas fa-shield-alt me-2"></i>Sécurité</button></li>
    <li class="nav-item"><button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences"><i class="fas fa-sliders-h me-2"></i>Préférences</button></li>
    <li class="nav-item"><button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity"><i class="fas fa-history me-2"></i>Activité</button></li>
    <?php if ($role === 'docteur'): ?>
    <li class="nav-item"><button class="nav-link" id="professional-tab" data-bs-toggle="tab" data-bs-target="#professional"><i class="fas fa-briefcase me-2"></i>Professionnel</button></li>
    <?php endif; ?>
</ul>

<div class="tab-content">
    <!-- Onglet Profil -->
    <div class="tab-pane fade show active" id="profile">
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-user-edit me-2"></i>Informations personnelles</h6></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label required">Nom</label><input type="text" class="form-control" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required></div>
                                <div class="col-md-6"><label class="form-label required">Prénom</label><input type="text" class="form-control" name="prenom" value="<?= htmlspecialchars($user['prenom']) ?>" required></div>
                                <div class="col-md-6"><label class="form-label required">Email</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required></div>
                                <div class="col-md-6"><label class="form-label">Téléphone</label><input type="tel" class="form-control" name="telephone" value="<?= htmlspecialchars($user['telephone'] ?? '') ?>"></div>
                                <div class="col-md-6"><label class="form-label">Date de naissance</label><input type="date" class="form-control" name="date_naissance" value="<?= $user['date_naissance'] ?? '' ?>"></div>
                                <div class="col-md-6"><label class="form-label">Sexe</label><select class="form-select" name="sexe"><option value="">Sélectionner</option><option value="M" <?= ($user['sexe'] ?? '') == 'M' ? 'selected' : '' ?>>Masculin</option><option value="F" <?= ($user['sexe'] ?? '') == 'F' ? 'selected' : '' ?>>Féminin</option></select></div>
                                <div class="col-12"><label class="form-label">Adresse</label><textarea class="form-control" name="adresse" rows="2"><?= htmlspecialchars($user['adresse'] ?? '') ?></textarea></div>
                                <div class="col-md-6"><label class="form-label">Rôle</label><input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" readonly></div>
                                <div class="col-md-6"><label class="form-label">Spécialité</label><input type="text" class="form-control" value="<?= htmlspecialchars($user['specialite'] ?? 'Non spécifiée') ?>" readonly></div>
                            </div>
                            <div class="mt-4 border-top pt-4"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-camera me-2"></i>Photo de profil</h6></div>
                    <div class="card-body text-center">
                        <?php if (!empty($user['photo']) && file_exists($photo_url)): ?>
                            <img src="<?= htmlspecialchars($photo_url) ?>" class="rounded-circle mb-3" style="width: 100px; height: 100px; object-fit: cover;">
                        <?php else: ?>
                            <div class="avatar-xl mx-auto mb-3"><span class="avatar-initials bg-<?= $roleColor ?>"><?= $initials ?></span></div>
                        <?php endif; ?>
                        <form method="POST" enctype="multipart/form-data" class="mt-3">
                            <div class="mb-3">
                                <input type="file" class="form-control" name="avatar" accept="image/jpeg,image/png,image/gif" required>
                            </div>
                            <button type="submit" name="upload_avatar" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-upload me-1"></i>Changer la photo
                            </button>
                        </form>
                        <?php if (!empty($user['photo'])): ?>
                        <form method="POST" onsubmit="return confirm('Supprimer définitivement la photo ?')">
                            <button type="submit" name="delete_avatar" class="btn btn-outline-danger w-100">
                                <i class="fas fa-trash me-1"></i>Supprimer
                            </button>
                        </form>
                        <?php endif; ?>
                        <div class="small text-muted mt-2">JPG, PNG, GIF (max 2 Mo)</div>
                    </div>
                </div>
                <!-- Statut du compte -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Statut du compte</h6></div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between"><span>Date de création</span><span class="fw-semibold"><?= formatDate($user['date_creation']) ?></span></li>
                            <li class="list-group-item d-flex justify-content-between"><span>Dernière connexion</span><span class="fw-semibold"><?= $user['derniere_connexion'] ? formatDate($user['derniere_connexion']) : 'Jamais' ?></span></li>
                            <li class="list-group-item d-flex justify-content-between"><span>Statut</span><span class="badge bg-<?= $user['statut'] == 'actif' ? 'success' : 'danger' ?>"><?= ucfirst($user['statut']) ?></span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Onglet Sécurité -->
    <div class="tab-pane fade" id="security">
        <div class="row">
            <div class="col-lg-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-key me-2"></i>Changer le mot de passe</h6></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="change_password" value="1">
                            <div class="mb-3"><label class="form-label required">Mot de passe actuel</label><input type="password" class="form-control" name="current_password" required></div>
                            <div class="mb-3"><label class="form-label required">Nouveau mot de passe</label><input type="password" class="form-control" name="new_password" required minlength="8"></div>
                            <div class="mb-3"><label class="form-label required">Confirmer</label><input type="password" class="form-control" name="confirm_password" required></div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-key me-1"></i>Changer le mot de passe</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-laptop me-2"></i>Sessions actives</h6></div>
                    <div class="card-body">
                        <?php $sessions = $pdo->prepare("SELECT * FROM login_logs WHERE user_id = ? ORDER BY login_time DESC LIMIT 5"); $sessions->execute([$user_id]); $sessions = $sessions->fetchAll(); ?>
                        <div class="list-group list-group-flush"><?php foreach ($sessions as $s): ?><div class="list-group-item"><div class="d-flex justify-content-between"><div><div class="fw-semibold"><i class="fas fa-<?= $s['success'] ? 'check text-success' : 'times text-danger' ?> me-2"></i><?= formatDate($s['login_time'], 'd/m/Y H:i') ?></div><small class="text-muted"><?= htmlspecialchars($s['ip_address']) ?> • <?= substr(htmlspecialchars($s['user_agent']), 0, 50) ?>...</small></div><div><span class="badge bg-<?= $s['success'] ? 'success' : 'danger' ?>"><?= $s['success'] ? 'Actif' : 'Échoué' ?></span></div></div></div><?php endforeach; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Onglet Préférences -->
    <div class="tab-pane fade" id="preferences">
        <div class="row">
            <div class="col-lg-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-palette me-2"></i>Apparence</h6></div>
                    <div class="card-body">
                        <form method="POST"><input type="hidden" name="update_preferences" value="1">
                            <div class="mb-3"><label class="form-label">Thème</label><select class="form-select" name="theme"><option value="light" <?= ($preferences['theme'] == 'light') ? 'selected' : '' ?>>Clair</option><option value="dark" <?= ($preferences['theme'] == 'dark') ? 'selected' : '' ?>>Sombre</option><option value="auto" <?= ($preferences['theme'] == 'auto') ? 'selected' : '' ?>>Auto</option></select></div>
                            <div class="mb-3"><label class="form-label">Langue</label><select class="form-select" name="language"><option value="fr" <?= ($preferences['language'] == 'fr') ? 'selected' : '' ?>>Français</option><option value="en" <?= ($preferences['language'] == 'en') ? 'selected' : '' ?>>Anglais</option></select></div>
                            <div class="mb-3"><label class="form-label">Densité</label><select class="form-select" name="density"><option value="comfortable" <?= ($preferences['density'] == 'comfortable') ? 'selected' : '' ?>>Confortable</option><option value="compact" <?= ($preferences['density'] == 'compact') ? 'selected' : '' ?>>Compact</option></select></div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h6></div>
                    <div class="card-body">
                        <form method="POST"><input type="hidden" name="update_preferences" value="1">
                            <div class="mb-3"><h6>Types</h6><div class="form-check"><input class="form-check-input" type="checkbox" name="notifications_email" value="1" <?= $preferences['notifications_email'] ? 'checked' : '' ?>><label class="form-check-label">Email</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="notifications_sms" value="1" <?= $preferences['notifications_sms'] ? 'checked' : '' ?>><label class="form-check-label">SMS</label></div></div>
                            <div class="mb-3"><h6>Événements</h6><div class="form-check"><input class="form-check-input" type="checkbox" name="notify_new_message" value="1" <?= $preferences['notify_new_message'] ? 'checked' : '' ?>><label class="form-check-label">Nouveaux messages</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="notify_appointment" value="1" <?= $preferences['notify_appointment'] ? 'checked' : '' ?>><label class="form-check-label">Rendez-vous</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="notify_system" value="1" <?= $preferences['notify_system'] ? 'checked' : '' ?>><label class="form-check-label">Mises à jour système</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="notify_security" value="1" <?= $preferences['notify_security'] ? 'checked' : '' ?>><label class="form-check-label">Alertes sécurité</label></div></div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button>
                        </form>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-user-shield me-2"></i>Confidentialité</h6></div>
                    <div class="card-body">
                        <form method="POST"><input type="hidden" name="update_preferences" value="1">
                            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="profile_public" value="1" id="profileVis" <?= ($preferences['profile_public'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="profileVis">Profil visible</label></div>
                            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="share_activity" value="1" id="shareAct" <?= ($preferences['share_activity'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="shareAct">Partager activité</label></div>
                            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="allow_data_collection" value="1" id="dataColl" <?= ($preferences['allow_data_collection'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="dataColl">Collecte données anonymes</label></div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Onglet Activité -->
    <div class="tab-pane fade" id="activity">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-history me-2"></i>Historique des activités</h6></div>
            <div class="card-body p-0">
                <div class="timeline p-4">
                    <?php $activities = $pdo->prepare("SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20"); $activities->execute([$user_id]); $activities = $activities->fetchAll(); ?>
                    <?php if (empty($activities)): ?><div class="text-center py-5"><i class="fas fa-history fa-3x text-muted mb-3"></i><p class="text-muted">Aucune activité récente</p></div>
                    <?php else: foreach ($activities as $act): ?>
                    <div class="timeline-item"><div class="timeline-marker bg-secondary"><i class="fas fa-circle"></i></div><div class="timeline-content"><div class="d-flex justify-content-between"><h6 class="mb-1"><?= htmlspecialchars($act['action']) ?></h6><small class="text-muted"><?= formatDate($act['created_at'], 'd/m H:i') ?></small></div><p class="mb-1 small"><?= htmlspecialchars($act['table_name']) ?> : <?= $user['prenom'] . ' ' . $user['nom'] ?></p></div></div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($role === 'docteur'): ?>
    <!-- Onglet Professionnel -->
    <div class="tab-pane fade" id="professional">
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-briefcase me-2"></i>Informations professionnelles</h6></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_professional" value="1">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Numéro RPPS</label><input type="text" class="form-control" name="rpps" value="<?= htmlspecialchars($user['rpps'] ?? '') ?>"></div>
                                <div class="col-md-6"><label class="form-label">Spécialité principale</label><input type="text" class="form-control" name="specialite" value="<?= htmlspecialchars($user['specialite'] ?? '') ?>"></div>
                                <div class="col-md-6"><label class="form-label">Diplôme</label><input type="text" class="form-control" name="diplome" value="<?= htmlspecialchars($user['diplome'] ?? '') ?>"></div>
                                <div class="col-md-6"><label class="form-label">Année d'obtention</label><input type="number" class="form-control" name="annee_diplome" value="<?= htmlspecialchars($user['annee_diplome'] ?? '') ?>"></div>
                                <div class="col-12"><label class="form-label">Université</label><input type="text" class="form-control" name="universite" value="<?= htmlspecialchars($user['universite'] ?? '') ?>"></div>
                                <div class="col-12"><label class="form-label">Expérience</label><textarea class="form-control" name="experience" rows="4"><?= htmlspecialchars($user['experience'] ?? '') ?></textarea></div>
                                <div class="col-12"><label class="form-label">Compétences</label><textarea class="form-control" name="competences" rows="3"><?= htmlspecialchars($user['competences'] ?? '') ?></textarea></div>
                                <div class="col-12"><label class="form-label">Certifications</label><textarea class="form-control" name="certifications" rows="3"><?= htmlspecialchars($user['certifications'] ?? '') ?></textarea></div>
                                <!-- Disponibilités -->
                                <div class="col-12 mt-3"><hr><h6>Disponibilités</h6></div>
                                <div class="col-12">
                                    <label class="form-label">Jours de consultation</label>
                                    <div class="row">
                                        <?php $jours = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche']; 
                                              $jours_actifs = !empty($user['jours_consultation']) ? json_decode($user['jours_consultation'], true) : []; ?>
                                        <?php foreach ($jours as $i => $jour): ?>
                                        <div class="col-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="jours_consultation[]" value="<?= $i ?>" <?= in_array($i, $jours_actifs) ? 'checked' : '' ?>><label class="form-check-label"><?= $jour ?></label></div></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6"><label class="form-label">Heure début</label><input type="time" class="form-control" name="heure_debut" value="<?= $user['heure_debut'] ?? '08:00' ?>"></div>
                                <div class="col-md-6"><label class="form-label">Heure fin</label><input type="time" class="form-control" name="heure_fin" value="<?= $user['heure_fin'] ?? '18:00' ?>"></div>
                                <div class="col-md-6"><label class="form-label">Durée consultation (min)</label><input type="number" class="form-control" name="duree_consultation" value="<?= $user['duree_consultation'] ?? 30 ?>" min="15" step="5"></div>
                            </div>
                            <div class="mt-4 border-top pt-4"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-signature me-2"></i>Signature électronique</h6></div>
                    <div class="card-body text-center">
                        <div class="alert alert-info small">Fonctionnalité à venir</div>
                        <?php if (!empty($user['signature'])): ?>
                        <img src="<?= htmlspecialchars($user['signature']) ?>" class="img-fluid border rounded p-2" style="max-height:100px">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.avatar-xl { width: 100px; height: 100px; margin: 0 auto; }
.avatar-initials { width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: bold; color: white; }
.timeline { position: relative; padding-left: 30px; }
.timeline-item { position: relative; padding-bottom: 20px; border-left: 2px solid #e5e7eb; }
.timeline-item:last-child { padding-bottom: 0; }
.timeline-marker { position: absolute; left: -12px; top: 0; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; color: white; border: 3px solid white; background-color: #6c757d; }
.timeline-content { padding-left: 20px; }
</style>

<?php require_once '../includes/footer.php'; ?>