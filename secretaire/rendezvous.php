<?php
// secretaire/rendezvous.php
require_once '../config/database.php';
require_once '../includes/sidebar.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'secretaire') {
    header('Location: ../login.php');
    exit;
}

$pdo = Database::getInstance()->getConnection();
$title = 'Gestion des rendez-vous';
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// --- Traitement CRUD ---
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Récupération des listes pour les formulaires
$patients = $pdo->query("SELECT id, nom, prenom, code_patient FROM patients WHERE statut = 'actif' ORDER BY nom, prenom")->fetchAll();
$docteurs = $pdo->query("SELECT id, nom, prenom, specialite FROM utilisateurs WHERE role = 'docteur' AND statut = 'actif' ORDER BY nom, prenom")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    
    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare("
                INSERT INTO rendez_vous (patient_id, docteur_id, date_rdv, duree, type_rdv, motif, notes, statut, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'confirme', ?)
            ");
            $stmt->execute([
                $data['patient_id'], $data['docteur_id'], $data['date_rdv'], 
                $data['duree'] ?? 30, $data['type_rdv'] ?? 'consultation',
                $data['motif'] ?? null, $data['notes'] ?? null, $_SESSION['user_id']
            ]);
            
            
        } elseif ($action === 'edit' && $id) {
            $stmt = $pdo->prepare("
                UPDATE rendez_vous SET patient_id = ?, docteur_id = ?, date_rdv = ?, duree = ?, 
                type_rdv = ?, motif = ?, notes = ?, statut = ? WHERE id = ?
            ");
            $stmt->execute([
                $data['patient_id'], $data['docteur_id'], $data['date_rdv'],
                $data['duree'] ?? 30, $data['type_rdv'] ?? 'consultation',
                $data['motif'] ?? null, $data['notes'] ?? null, $data['statut'], $id
            ]);
            
        } elseif ($action === 'delete' && $id) {
            $stmt = $pdo->prepare("UPDATE rendez_vous SET statut = 'annule' WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: rendezvous.php?success=Rendez-vous annulé');
            exit;
        }
    } catch (PDOException $e) {
        
    }
}

$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? '';
$filter_doctor = $_GET['doctor'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$sql = "SELECT r.*, 
        CONCAT(p.nom, ' ', p.prenom) as patient_nom, p.code_patient, p.telephone as patient_tel,
        CONCAT(d.nom, ' ', d.prenom) as docteur_nom, d.specialite
        FROM rendez_vous r
        JOIN patients p ON r.patient_id = p.id
        JOIN utilisateurs d ON r.docteur_id = d.id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (p.nom LIKE ? OR p.prenom LIKE ? OR p.code_patient LIKE ? OR CONCAT(p.nom, ' ', p.prenom) LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($filter_status) {
    $sql .= " AND r.statut = ?";
    $params[] = $filter_status;
}
if ($filter_doctor) {
    $sql .= " AND r.docteur_id = ?";
    $params[] = $filter_doctor;
}
if ($date_from) {
    $sql .= " AND DATE(r.date_rdv) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $sql .= " AND DATE(r.date_rdv) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY r.date_rdv DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rdvs = $stmt->fetchAll();

// Compter le total
$countSql = "SELECT COUNT(*) FROM rendez_vous r JOIN patients p ON r.patient_id = p.id WHERE 1=1";
$countParams = [];
if ($search) { $countSql .= " AND (p.nom LIKE ? OR p.prenom LIKE ? OR p.code_patient LIKE ?)"; $countParams = array_fill(0,3,$like); }
if ($filter_status) { $countSql .= " AND r.statut = ?"; $countParams[] = $filter_status; }
if ($filter_doctor) { $countSql .= " AND r.docteur_id = ?"; $countParams[] = $filter_doctor; }
if ($date_from) { $countSql .= " AND DATE(r.date_rdv) >= ?"; $countParams[] = $date_from; }
if ($date_to) { $countSql .= " AND DATE(r.date_rdv) <= ?"; $countParams[] = $date_to; }

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalRdvs = $countStmt->fetchColumn();
$totalPages = ceil($totalRdvs / $limit);

// Récupérer un rendez-vous pour l'édition
$editRdv = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM rendez_vous WHERE id = ?");
    $stmt->execute([$id]);
    $editRdv = $stmt->fetch();
    if (!$editRdv) {
        header('Location: rendezvous.php?error=Rendez-vous introuvable');
        exit;
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between flex-wrap align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-calendar-alt text-primary me-2"></i>Gestion des rendez-vous</h1>
            <p class="text-muted">Planification et suivi</p>
        </div>
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Nouveau rendez-vous</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <?php if ($action === 'add' || ($action === 'edit' && $editRdv)): ?>
        <!-- Formulaire -->
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h5 class="mb-0"><?= $action === 'add' ? 'Nouveau rendez-vous' : 'Modifier le rendez-vous' ?></h5></div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label required">Patient</label>
                            <select name="patient_id" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($patients as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= (($editRdv['patient_id'] ?? $_GET['patient_id'] ?? 0) == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['code_patient'] . ' - ' . $p['prenom'] . ' ' . $p['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label required">Médecin</label>
                            <select name="docteur_id" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($docteurs as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= ($editRdv['docteur_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom'] . ' (' . $d['specialite'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label required">Date et heure</label><input type="datetime-local" name="date_rdv" class="form-control" value="<?= $editRdv ? date('Y-m-d\TH:i', strtotime($editRdv['date_rdv'])) : '' ?>" required></div>
                        <div class="col-md-2"><label>Durée (min)</label><input type="number" name="duree" class="form-control" value="<?= $editRdv['duree'] ?? 30 ?>"></div>
                        <div class="col-md-3"><label>Type</label><select name="type_rdv" class="form-select"><option value="consultation">Consultation</option><option value="controle">Contrôle</option><option value="urgence">Urgence</option><option value="autre">Autre</option></select></div>
                        <div class="col-md-3"><label>Statut</label><select name="statut" class="form-select"><option value="confirme" <?= ($editRdv['statut'] ?? '') == 'confirme' ? 'selected' : '' ?>>Confirmé</option><option value="annule">Annulé</option><option value="reporte">Reporté</option><option value="present">Présent</option><option value="absent">Absent</option></select></div>
                        <div class="col-12"><label>Motif</label><textarea name="motif" class="form-control" rows="2"><?= htmlspecialchars($editRdv['motif'] ?? '') ?></textarea></div>
                        <div class="col-12"><label>Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($editRdv['notes'] ?? '') ?></textarea></div>
                    </div>
                    <div class="mt-4"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button><a href="rendezvous.php" class="btn btn-secondary ms-2">Annuler</a></div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Filtres -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="Patient (nom, code, tél.)" value="<?= htmlspecialchars($search) ?>"></div>
                    <div class="col-md-2"><select name="status" class="form-select"><option value="">Tous statuts</option><option value="confirme" <?= $filter_status=='confirme'?'selected':'' ?>>Confirmé</option><option value="annule">Annulé</option><option value="reporte">Reporté</option><option value="present">Présent</option><option value="absent">Absent</option></select></div>
                    <div class="col-md-2"><select name="doctor" class="form-select"><option value="">Tous médecins</option><?php foreach ($docteurs as $d): ?><option value="<?= $d['id'] ?>" <?= $filter_doctor==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['prenom'].' '.$d['nom']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2"><input type="date" name="date_from" class="form-control" placeholder="Du" value="<?= htmlspecialchars($date_from) ?>"></div>
                    <div class="col-md-2"><input type="date" name="date_to" class="form-control" placeholder="Au" value="<?= htmlspecialchars($date_to) ?>"></div>
                    <div class="col-md-1"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i></button></div>
                </form>
            </div>
        </div>

        <!-- Liste -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between"><span><i class="fas fa-list me-2"></i>Rendez-vous (<?= $totalRdvs ?>)</span></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Référence</th><th>Patient</th><th>Médecin</th><th>Date & heure</th><th>Type</th><th>Statut</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($rdvs as $r): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($r['reference']) ?></span></td>
                                <td><strong><?= htmlspecialchars($r['patient_nom']) ?></strong><br><small><?= htmlspecialchars($r['code_patient']) ?> | <?= htmlspecialchars($r['patient_tel']) ?></small></td>
                                <td><?= htmlspecialchars($r['docteur_nom']) ?><br><small><?= htmlspecialchars($r['specialite']) ?></small></td>
                                <td><?= date('d/m/Y H:i', strtotime($r['date_rdv'])) ?></td>
                                <td><?= ucfirst($r['type_rdv']) ?></td>
                                <td><span class="badge bg-<?= $r['statut']=='confirme'?'success':($r['statut']=='annule'?'danger':($r['statut']=='reporte'?'warning':'secondary')) ?>"><?= ucfirst($r['statut']) ?></span></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline-primary"><i class="fas fa-edit"></i></a>
                                        <a href="?action=delete&id=<?= $r['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Annuler ce rendez-vous ?')"><i class="fas fa-times-circle"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($rdvs)): ?><tr><td colspan="7" class="text-center py-4">Aucun rendez-vous trouvé</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer"><nav><ul class="pagination mb-0"><?php for($i=1;$i<=$totalPages;$i++): ?><li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>&doctor=<?= urlencode($filter_doctor) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><?= $i ?></a></li><?php endfor; ?></ul></nav></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include '../includes/footer.php'; ?>