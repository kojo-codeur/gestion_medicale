<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// Vérifier droits (admin, docteur ou assistant)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'docteur', 'assistant'])) {
    echo json_encode(['success' => false, 'error' => 'Permission refusée']);
    exit;
}

// Lire les données JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Données invalides']);
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

    // Récupérer le médicament
    $stmt = $pdo->prepare("SELECT * FROM medicaments WHERE id = ? FOR UPDATE");
    $stmt->execute([$medicament_id]);
    $med = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$med) {
        throw new Exception("Médicament introuvable");
    }

    $ancien_stock = (int)$med['stock_actuel'];
    $nouveau_stock = $ancien_stock;

    switch ($operation) {
        case 'add':
            $nouveau_stock = $ancien_stock + $quantite;
            break;
        case 'remove':
            if ($ancien_stock < $quantite) {
                throw new Exception("Stock insuffisant (disponible: $ancien_stock)");
            }
            $nouveau_stock = $ancien_stock - $quantite;
            break;
        case 'set':
            $nouveau_stock = $quantite;
            break;
    }

    // Mise à jour du stock
    $update = $pdo->prepare("UPDATE medicaments SET stock_actuel = ?, updated_at = NOW() WHERE id = ?");
    $update->execute([$nouveau_stock, $medicament_id]);

    // Mettre à jour le statut si rupture
    if ($nouveau_stock <= 0 && $med['statut'] !== 'inactif') {
        $pdo->prepare("UPDATE medicaments SET statut = 'rupture' WHERE id = ?")->execute([$medicament_id]);
    } elseif ($nouveau_stock > 0 && $med['statut'] === 'rupture') {
        $pdo->prepare("UPDATE medicaments SET statut = 'actif' WHERE id = ?")->execute([$medicament_id]);
    }

    // Journalisation
    $logStmt = $pdo->prepare("
        INSERT INTO medicament_stock_log 
        (medicament_id, operation, quantite, ancien_stock, nouveau_stock, raison, user_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $logStmt->execute([$medicament_id, $operation, $quantite, $ancien_stock, $nouveau_stock, $raison, $user_id]);

    $pdo->commit();

    echo json_encode(['success' => true, 'new_stock' => $nouveau_stock]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}