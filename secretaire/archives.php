<?php
// secretaire/archives.php
require_once '../config/database.php';
require_once '../includes/sidebar.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'secretaire') {
    header('Location: ../login.php');
    exit;
}

$pdo = Database::getInstance()->getConnection();
$title = 'Archives patients';

$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM patients WHERE statut = 'archive'";
$params = [];
if ($search) {
    $sql .= " AND (nom LIKE ? OR prenom LIKE ? OR code_patient LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY nom, prenom";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

// Réactiver un patient
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    $pdo->prepare("UPDATE patients SET statut = 'actif' WHERE id = ?")->execute([$id]);
    header('Location: archives.php?success=Patient restauré');
    exit;
}

$success = $_GET['success'] ?? '';
?>

<?php include '../includes/header.php'; ?>
    
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="fas fa-archive text-primary me-2"></i>Patients archivés</h1>
        <div class="input-group w-50">
            <input type="text" class="form-control" placeholder="Rechercher" id="searchInput" value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-primary" onclick="window.location.href='?search='+document.getElementById('searchInput').value"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Code</th><th>Nom complet</th><th>Date naissance</th><th>Téléphone</th><th>Email</th><th>Date archivage</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($patients as $p): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($p['code_patient']) ?></span></td>
                            <td><strong><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($p['date_naissance'])) ?></td>
                            <td><?= htmlspecialchars($p['telephone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['email'] ?? '') ?></td>
                            <td><?= date('d/m/Y', strtotime($p['date_modification'])) ?></td>
                            <td><a href="?restore=<?= $p['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Restaurer ce patient ?')"><i class="fas fa-undo"></i> Restaurer</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($patients)): ?><tr><td colspan="7" class="text-center py-4">Aucun patient archivé</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php require_once '../includes/footer.php'; ?>