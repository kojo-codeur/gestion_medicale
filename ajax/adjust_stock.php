<?php
// ajax/adjust_stock.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'docteur', 'assistant'])) {
    echo json_encode(['success' => false, 'error' => 'Permission refusée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Données JSON invalides']);
    exit;
}

$medicament_id = (int)($input['medicament_id'] ?? 0);
$operation = $input['operation'] ?? '';
$quantite = (int)($input['quantity'] ?? 0);
$raison = trim($input['reason'] ?? '');

if ($medicament_id <= 0 || !in_array($operation, ['add', 'remove', 'set']) || $quantite <= 0) {
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
    exit;
}

$pdo = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM medicaments WHERE id = ? FOR UPDATE");
    $stmt->execute([$medicament_id]);
    $med = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$med) {
        throw new Exception("Médicament introuvable");
    }

    $ancien = (int)$med['stock_actuel'];
    switch ($operation) {
        case 'add':   $nouveau = $ancien + $quantite; break;
        case 'remove': if ($ancien < $quantite) throw new Exception("Stock insuffisant"); $nouveau = $ancien - $quantite; break;
        case 'set':   $nouveau = $quantite; break;
        default: throw new Exception("Opération invalide");
    }

    $update = $pdo->prepare("UPDATE medicaments SET stock_actuel = ?, updated_at = NOW() WHERE id = ?");
    $update->execute([$nouveau, $medicament_id]);

    if ($nouveau <= 0 && $med['statut'] !== 'inactif') {
        $pdo->prepare("UPDATE medicaments SET statut = 'rupture' WHERE id = ?")->execute([$medicament_id]);
    } elseif ($nouveau > 0 && $med['statut'] === 'rupture') {
        $pdo->prepare("UPDATE medicaments SET statut = 'actif' WHERE id = ?")->execute([$medicament_id]);
    }

    $log = $pdo->prepare("
        INSERT INTO medicament_stock_log 
        (medicament_id, operation, quantite, ancien_stock, nouveau_stock, raison, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $log->execute([$medicament_id, $operation, $quantite, $ancien, $nouveau, $raison, $user_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'new_stock' => $nouveau]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}