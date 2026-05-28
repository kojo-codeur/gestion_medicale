<?php
// ajax/get_medicament_details.php
require_once '../config/database.php';

// Vérifier authentification
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'docteur', 'assistant'])) {
    http_response_code(403);
    exit('Accès non autorisé');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo '<div class="alert alert-danger">ID de médicament invalide.</div>';
    exit;
}

$pdo = Database::getInstance()->getConnection();

try {
    $stmt = $pdo->prepare("SELECT * FROM medicaments WHERE id = ?");
    $stmt->execute([$id]);
    $med = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$med) {
        echo '<div class="alert alert-warning">Médicament introuvable.</div>';
        exit;
    }

    // Types et classes
    $types_medicament = [
        'comprime' => 'Comprimé', 'gelule' => 'Gélule', 'sirop' => 'Sirop',
        'injectable' => 'Injectable', 'pommade' => 'Pommade', 'creme' => 'Crème',
        'suppositoire' => 'Suppositoire', 'collyre' => 'Collyre', 'spray' => 'Spray',
        'poudre' => 'Poudre', 'autre' => 'Autre'
    ];

    $classe_label = $med['classe_therapeutique'] ?: 'Non spécifiée';
    $forme_label = $types_medicament[$med['forme']] ?? ucfirst($med['forme']);

    // Déterminer couleur statut stock
    if ($med['stock_actuel'] <= 0) {
        $stock_badge = 'danger';
        $stock_text = 'Rupture';
    } elseif ($med['stock_actuel'] <= $med['stock_minimum']) {
        $stock_badge = 'warning';
        $stock_text = 'Stock faible';
    } else {
        $stock_badge = 'success';
        $stock_text = 'Stock suffisant';
    }

    // Statut général
    $status_badge = [
        'actif' => 'success',
        'inactif' => 'secondary',
        'rupture' => 'danger',
        'retire' => 'dark'
    ][$med['statut']] ?? 'secondary';
?>
<div class="row">
    <div class="col-md-6">
        <table class="table table-bordered">
            <tr><th style="width:40%">Code CIP</th><td><?= htmlspecialchars($med['code_cip'] ?? 'N/A') ?></td></tr>
            <tr><th>Nom commercial</th><td><strong><?= htmlspecialchars($med['nom_commercial']) ?></strong></td></tr>
            <tr><th>Nom générique</th><td><?= htmlspecialchars($med['nom_generique'] ?? 'N/A') ?></td></tr>
            <tr><th>Laboratoire</th><td><?= htmlspecialchars($med['laboratoire'] ?? 'N/A') ?></td></tr>
            <tr><th>Forme / Dosage</th><td><?= $forme_label ?> - <?= htmlspecialchars($med['dosage'] ?? 'N/A') ?></td></tr>
            <tr><th>Conditionnement</th><td><?= htmlspecialchars($med['conditionnement'] ?? 'N/A') ?></td></tr>
            <tr><th>Classe thérapeutique</th><td><?= htmlspecialchars($classe_label) ?></td></tr>
        </table>
    </div>
    <div class="col-md-6">
        <table class="table table-bordered">
            <tr><th>Stock actuel</th><td><span class="badge bg-<?= $stock_badge ?>"><?= $med['stock_actuel'] ?></span> (min. <?= $med['stock_minimum'] ?>)</td></tr>
            <tr><th>Prix unitaire</th><td><?= number_format($med['prix_unitaire'], 2) ?> €</td></tr>
            <tr><th>Remboursement</th><td><?= $med['remboursement'] ? $med['remboursement'] . '%' : 'Non remboursé' ?></td></tr>
            <tr><th>Statut</th><td><span class="badge bg-<?= $status_badge ?>"><?= ucfirst($med['statut']) ?></span></td></tr>
            <tr><th>Dernière modification</th><td><?= date('d/m/Y H:i', strtotime($med['updated_at'] ?? $med['created_at'])) ?></td></tr>
        </table>
    </div>
    <div class="col-12 mt-3">
        <div class="card">
            <div class="card-header bg-light">Informations médicales</div>
            <div class="card-body">
                <?php if ($med['indications']): ?>
                    <div class="mb-3"><strong>Indications :</strong><br><?= nl2br(htmlspecialchars($med['indications'])) ?></div>
                <?php endif; ?>
                <?php if ($med['contre_indications']): ?>
                    <div class="mb-3"><strong>Contre-indications :</strong><br><?= nl2br(htmlspecialchars($med['contre_indications'])) ?></div>
                <?php endif; ?>
                <?php if ($med['effets_secondaires']): ?>
                    <div class="mb-3"><strong>Effets secondaires :</strong><br><?= nl2br(htmlspecialchars($med['effets_secondaires'])) ?></div>
                <?php endif; ?>
                <?php if ($med['posologie']): ?>
                    <div class="mb-3"><strong>Posologie :</strong><br><?= nl2br(htmlspecialchars($med['posologie'])) ?></div>
                <?php endif; ?>
                <?php if ($med['precautions']): ?>
                    <div class="mb-3"><strong>Précautions :</strong><br><?= nl2br(htmlspecialchars($med['precautions'])) ?></div>
                <?php endif; ?>
                <?php if ($med['interactions']): ?>
                    <div class="mb-3"><strong>Interactions :</strong><br><?= nl2br(htmlspecialchars($med['interactions'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
} catch (PDOException $e) {
    error_log("Erreur get_medicament_details: " . $e->getMessage());
    echo '<div class="alert alert-danger">Erreur lors du chargement des détails.</div>';
}