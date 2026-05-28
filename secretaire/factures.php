<?php
// secretaire/factures.php
require_once '../config/database.php';
require_once '../includes/sidebar.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'secretaire') {
    header('Location: ../login.php');
    exit;
}

$pdo = Database::getInstance()->getConnection();
$title = 'Gestion des factures';

// Vérifier et ajouter la colonne montant_ttc si elle n'existe pas
try {
    $pdo->query("SELECT montant_ttc FROM factures LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE factures ADD COLUMN montant_ttc DECIMAL(10,2) DEFAULT 0 AFTER montant_ht");
}

// Récupérer les patients actifs
$patients = $pdo->query("SELECT id, nom, prenom, code_patient FROM patients WHERE statut = 'actif' ORDER BY nom, prenom")->fetchAll();

// Fonction pour envoyer les notifications de paiement
function sendPaymentNotifications($pdo, $facture_numero, $patient_id, $montant_ttc) {
    // Récupérer infos patient
    $stmt = $pdo->prepare("SELECT nom, prenom, code_patient FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    if (!$patient) return;
    
    $patient_nom = $patient['prenom'] . ' ' . $patient['nom'];
    $message = "Le patient $patient_nom ({$patient['code_patient']}) a réglé sa facture n°$facture_numero d'un montant de " . number_format($montant_ttc, 2) . " €.";
    
    // Trouver le docteur concerné (dernière consultation ou dernier RDV)
    $doctor = $pdo->prepare("
        (SELECT docteur_id FROM consultations WHERE patient_id = ? ORDER BY date_consultation DESC LIMIT 1)
        UNION
        (SELECT docteur_id FROM rendez_vous WHERE patient_id = ? ORDER BY date_rdv DESC LIMIT 1)
        LIMIT 1
    ");
    $doctor->execute([$patient_id, $patient_id]);
    $doc = $doctor->fetch();
    if ($doc && $doc['docteur_id']) {
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, type, titre, message, lu, created_at) VALUES (?, 'info', 'Paiement reçu', ?, 0, NOW())");
        $notif->execute([$doc['docteur_id'], $message]);
    }
    
    // Notifier tous les assistants actifs
    $assistants = $pdo->query("SELECT id FROM utilisateurs WHERE role = 'assistant' AND statut = 'actif'");
    while ($ass = $assistants->fetch()) {
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, type, titre, message, lu, created_at) VALUES (?, 'info', 'Paiement patient', ?, 0, NOW())");
        $notif->execute([$ass['id'], $message]);
    }
}

// Traitement CRUD
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$statutFilter = $_GET['statut'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'add') {
            $numero = 'FACT-' . date('Ymd') . '-' . rand(1000, 9999);
            $montant_ht = (float)$_POST['montant_ht'];
            $tva = (float)$_POST['tva'];
            $montant_ttc = $montant_ht * (1 + $tva / 100);
            
            $stmt = $pdo->prepare("
                INSERT INTO factures (numero, patient_id, date_emission, montant_ht, tva, montant_ttc, description, statut, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'attente', ?)
            ");
            $stmt->execute([
                $numero, $_POST['patient_id'], $_POST['date_emission'], $montant_ht, $tva, $montant_ttc,
                $_POST['description'] ?? null, $_SESSION['user_id']
            ]);
            
            
        } elseif ($action === 'edit' && $id) {
            // Récupérer l'ancien statut
            $old = $pdo->prepare("SELECT statut, patient_id, numero, montant_ttc FROM factures WHERE id = ?");
            $old->execute([$id]);
            $oldFacture = $old->fetch();
            if (!$oldFacture) throw new Exception("Facture introuvable");
            
            $montant_ht = (float)$_POST['montant_ht'];
            $tva = (float)$_POST['tva'];
            $montant_ttc = $montant_ht * (1 + $tva / 100);
            $newStatut = $_POST['statut'];
            
            $stmt = $pdo->prepare("
                UPDATE factures 
                SET patient_id = ?, date_emission = ?, montant_ht = ?, tva = ?, montant_ttc = ?, description = ?, statut = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['patient_id'], $_POST['date_emission'], $montant_ht, $tva, $montant_ttc,
                $_POST['description'] ?? null, $newStatut, $id
            ]);
            
            // Si le statut devient 'paye' et ne l'était pas avant
            if ($newStatut === 'paye' && $oldFacture['statut'] !== 'paye') {
                sendPaymentNotifications($pdo, $oldFacture['numero'], $oldFacture['patient_id'], $oldFacture['montant_ttc']);
            }
            
           
        }
    } catch (Exception $e) {
        
    }
}

// Action "pay" : marquer directement comme payée
if ($action === 'pay' && $id) {
    $stmt = $pdo->prepare("SELECT patient_id, montant_ttc, numero, statut FROM factures WHERE id = ?");
    $stmt->execute([$id]);
    $fact = $stmt->fetch();
    if ($fact && $fact['statut'] !== 'paye') {
        $pdo->prepare("UPDATE factures SET statut = 'paye' WHERE id = ?")->execute([$id]);
        sendPaymentNotifications($pdo, $fact['numero'], $fact['patient_id'], $fact['montant_ttc']);
        
    } else {
    }
    exit;
}

// Action "delete"
if ($action === 'delete' && $id) {
    $pdo->prepare("DELETE FROM factures WHERE id = ?")->execute([$id]);
    
}

// Récupération de la facture pour édition
$editFacture = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM factures WHERE id = ?");
    $stmt->execute([$id]);
    $editFacture = $stmt->fetch();
    if (!$editFacture) {
        
    }
}

// Liste des factures
$sql = "SELECT f.*, CONCAT(p.nom, ' ', p.prenom) as patient_nom, p.code_patient 
        FROM factures f 
        JOIN patients p ON f.patient_id = p.id 
        WHERE 1=1";
$params = [];
if ($statutFilter && in_array($statutFilter, ['attente', 'paye', 'annule'])) {
    $sql .= " AND f.statut = ?";
    $params[] = $statutFilter;
}
$sql .= " ORDER BY f.date_emission DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$factures = $stmt->fetchAll();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="fas fa-file-invoice-dollar text-primary me-2"></i>Factures</h1>
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Nouvelle facture</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($action === 'add' || ($action === 'edit' && $editFacture)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h5 class="mb-0"><?= $action === 'add' ? 'Nouvelle facture' : 'Modifier la facture' ?></h5></div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Patient</label>
                            <select name="patient_id" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($patients as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= ($editFacture['patient_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['code_patient'] . ' - ' . $p['prenom'] . ' ' . $p['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Date d'émission</label>
                            <input type="date" name="date_emission" class="form-control" value="<?= $editFacture['date_emission'] ?? date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label>Montant HT (€)</label>
                            <input type="number" step="0.01" name="montant_ht" class="form-control" value="<?= $editFacture['montant_ht'] ?? '' ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label>TVA (%)</label>
                            <input type="number" step="0.01" name="tva" class="form-control" value="<?= $editFacture['tva'] ?? 20 ?>">
                        </div>
                        <div class="col-12">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($editFacture['description'] ?? '') ?></textarea>
                        </div>
                        <?php if ($action === 'edit'): ?>
                        <div class="col-md-3">
                            <label>Statut</label>
                            <select name="statut" class="form-select">
                                <option value="attente" <?= ($editFacture['statut']??'')=='attente'?'selected':'' ?>>En attente</option>
                                <option value="paye" <?= ($editFacture['statut']??'')=='paye'?'selected':'' ?>>Payée</option>
                                <option value="annule" <?= ($editFacture['statut']??'')=='annule'?'selected':'' ?>>Annulée</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                        <a href="factures.php" class="btn btn-secondary ms-2">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item"><a class="nav-link <?= !$statutFilter ? 'active' : '' ?>" href="factures.php">Toutes</a></li>
            <li class="nav-item"><a class="nav-link <?= $statutFilter == 'attente' ? 'active' : '' ?>" href="?statut=attente">En attente</a></li>
            <li class="nav-item"><a class="nav-link <?= $statutFilter == 'paye' ? 'active' : '' ?>" href="?statut=paye">Payées</a></li>
        </ul>

        <div class="card shadow-sm">
            <div class="card-header bg-white"><i class="fas fa-list me-2"></i>Liste des factures</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>N° facture</th><th>Patient</th><th>Date</th><th>Montant HT</th><th>TVA</th><th>Montant TTC</th><th>Statut</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($factures as $f): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($f['numero']) ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($f['patient_nom']) ?></strong><br>
                                    <small><?= htmlspecialchars($f['code_patient']) ?></small>
                                </td>
                                <td><?= date('d/m/Y', strtotime($f['date_emission'])) ?></td>
                                <td><?= number_format($f['montant_ht'], 2) ?> €</td>
                                <td><?= $f['tva'] ?>%</td>
                                <td><strong><?= number_format($f['montant_ttc'], 2) ?> €</strong></td>
                                <td>
                                    <span class="badge bg-<?= $f['statut']=='paye'?'success':($f['statut']=='attente'?'warning':'danger') ?>">
                                        <?= ucfirst($f['statut']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?action=edit&id=<?= $f['id'] ?>" class="btn btn-outline-primary" title="Modifier"><i class="fas fa-edit"></i></a>
                                        <a href="?action=delete&id=<?= $f['id'] ?>" class="btn btn-outline-danger" title="Supprimer" onclick="return confirm('Supprimer cette facture ?')"><i class="fas fa-trash"></i></a>
                                        <?php if ($f['statut'] != 'paye'): ?>
                                        <a href="?action=pay&id=<?= $f['id'] ?>" class="btn btn-outline-success" title="Marquer comme payée" onclick="return confirm('Marquer cette facture comme payée ?')"><i class="fas fa-check"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($factures)): ?>
                            <tr><td colspan="8" class="text-center py-4">Aucune facture</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php require_once '../includes/footer.php'; ?>