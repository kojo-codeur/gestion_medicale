<?php
// ajax/notifications.php
require_once '../../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$pdo = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'mark_read':
            $id = (int)$_POST['id'];
            // Vérifier que la notification appartient bien à l'utilisateur
            $check = $pdo->prepare("SELECT id FROM notifications WHERE id = ? AND (user_id = ? OR (user_id IS NULL AND ? = 'admin'))");
            $check->execute([$id, $user_id, $role]);
            if ($check->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE notifications SET lu = 1, read_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Accès interdit']);
            }
            break;

        case 'mark_all_read':
            if ($role === 'admin') {
                $stmt = $pdo->prepare("UPDATE notifications SET lu = 1, read_at = NOW() WHERE (user_id IS NULL OR user_id = ?) AND lu = 0");
                $stmt->execute([$user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE notifications SET lu = 1, read_at = NOW() WHERE user_id = ? AND lu = 0");
                $stmt->execute([$user_id]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = (int)$_POST['id'];
            $check = $pdo->prepare("SELECT id FROM notifications WHERE id = ? AND (user_id = ? OR (user_id IS NULL AND ? = 'admin'))");
            $check->execute([$id, $user_id, $role]);
            if ($check->rowCount() > 0) {
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Accès interdit']);
            }
            break;

        case 'delete_all_read':
            if ($role === 'admin') {
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND lu = 1");
                $stmt->execute([$user_id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND lu = 1");
                $stmt->execute([$user_id]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'get_count':
            if ($role === 'admin') {
                $stmt = $pdo->prepare("SELECT COUNT(*) as unread FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND lu = 0");
                $stmt->execute([$user_id]);
                $unread = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
                $stmt2 = $pdo->prepare("SELECT COUNT(*) as total FROM notifications WHERE (user_id IS NULL OR user_id = ?)");
                $stmt2->execute([$user_id]);
                $total = $stmt2->fetch(PDO::FETCH_ASSOC)['total'];
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND lu = 0");
                $stmt->execute([$user_id]);
                $unread = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
                $stmt2 = $pdo->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
                $stmt2->execute([$user_id]);
                $total = $stmt2->fetch(PDO::FETCH_ASSOC)['total'];
            }
            echo json_encode(['success' => true, 'unread_count' => (int)$unread, 'total_count' => (int)$total]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action invalide']);
    }
} catch (PDOException $e) {
    error_log("AJAX notifications error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}