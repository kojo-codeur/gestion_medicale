<?php
// ajax/reset_password.php
require_once '../config/database.php';
checkRole('admin');

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID utilisateur manquant']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Récupérer les informations de l'utilisateur
    $stmt = $pdo->prepare("SELECT nom, prenom FROM utilisateurs WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("Utilisateur non trouvé");
    }
    
    // Générer un nouveau mot de passe
    $defaultPassword = strtolower($user['nom'] . $user['prenom']) . '123';
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
    
    // Mettre à jour le mot de passe
    $stmt = $pdo->prepare("UPDATE utilisateurs SET password = ?, date_modification = NOW() WHERE id = ?");
    $stmt->execute([$hashedPassword, $id]);
    
    // Journaliser l'action
    $admin_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs 
        (user_id, action, table_name, record_id, details, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $admin_id, 
        'UPDATE', 
        'utilisateurs', 
        $id, 
        "Réinitialisation mot de passe utilisateur ID: $id",
        $_SERVER['REMOTE_ADDR']
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'password' => $defaultPassword,
        'message' => 'Mot de passe réinitialisé avec succès'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

<?php
// ajax/reset_password.php
require_once '../config/database.php';

// Vérifier l'authentification et les permissions
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit();
}

// Vérifier les permissions (seul l'admin peut réinitialiser les mots de passe)
$stmt = $pdo->prepare("SELECT role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
    exit();
}

$userId = $_GET['id'] ?? null;

if (!$userId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID utilisateur manquant']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Récupérer les informations de l'utilisateur
    $stmt = $pdo->prepare("SELECT nom, prenom FROM utilisateurs WHERE id = ?");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();
    
    if (!$targetUser) {
        throw new Exception("Utilisateur non trouvé");
    }
    
    // Générer un nouveau mot de passe (nom+prenom+123 en minuscules)
    $newPassword = strtolower(str_replace(' ', '', $targetUser['nom'] . $targetUser['prenom'])) . '123';
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Mettre à jour le mot de passe
    $stmt = $pdo->prepare("UPDATE utilisateurs SET password = ?, date_modification = NOW() WHERE id = ?");
    $stmt->execute([$hashedPassword, $userId]);
    
    // Journaliser l'action
    $auditStmt = $pdo->prepare("
        INSERT INTO audit_logs 
        (user_id, action, table_name, record_id, ip_address, details) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $auditStmt->execute([
        $_SESSION['user_id'],
        'UPDATE',
        'utilisateurs',
        $userId,
        $_SERVER['REMOTE_ADDR'],
        "Réinitialisation mot de passe pour utilisateur ID: $userId"
    ]);
    
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'password' => $newPassword,
        'message' => 'Mot de passe réinitialisé avec succès'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>