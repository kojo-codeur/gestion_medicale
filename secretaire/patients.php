<?php
// secretaire/patients.php
require_once '../config/database.php';
require_once '../includes/header.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'secretaire') {
    header('Location: ../login.php');
    exit;
}

$pdo = Database::getInstance()->getConnection();
$title = 'Gestion des patients';
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// --- Traitement CRUD ---
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    
    try {
        if ($action === 'add') {
            // Insertion
            $stmt = $pdo->prepare("
                INSERT INTO patients 
                (nom, prenom, date_naissance, sexe, lieu_naissance, adresse, ville, code_postal, pays,
                 telephone, telephone_urgence, email, profession, situation_familiale, nombre_enfants,
                 groupe_sanguin, rhésus, poids, taille, antecedents_familiaux, antecedents_personnels,
                 allergies, medicaments_habituels, habitudes, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['nom'], $data['prenom'], $data['date_naissance'], $data['sexe'],
                $data['lieu_naissance'] ?? null, $data['adresse'] ?? null, $data['ville'] ?? null,
                $data['code_postal'] ?? null, $data['pays'] ?? 'France', $data['telephone'] ?? null,
                $data['telephone_urgence'] ?? null, $data['email'] ?? null, $data['profession'] ?? null,
                $data['situation_familiale'] ?? 'celibataire', (int)($data['nombre_enfants'] ?? 0),
                $data['groupe_sanguin'] ?? null, $data['rhésus'] ?? null,
                !empty($data['poids']) ? (float)$data['poids'] : null,
                !empty($data['taille']) ? (float)$data['taille'] : null,
                $data['antecedents_familiaux'] ?? null, $data['antecedents_personnels'] ?? null,
                $data['allergies'] ?? null, $data['medicaments_habituels'] ?? null,
                $data['habitudes'] ?? null, $data['notes'] ?? null, $_SESSION['user_id']
            ]);
            header('Location: patients.php?success=Patient ajouté avec succès');
            exit;
        } elseif ($action === 'edit' && $id) {
            $stmt = $pdo->prepare("
                UPDATE patients SET
                nom = ?, prenom = ?, date_naissance = ?, sexe = ?, lieu_naissance = ?, adresse = ?,
                ville = ?, code_postal = ?, pays = ?, telephone = ?, telephone_urgence = ?, email = ?,
                profession = ?, situation_familiale = ?, nombre_enfants = ?, groupe_sanguin = ?,
                rhésus = ?, poids = ?, taille = ?, antecedents_familiaux = ?, antecedents_personnels = ?,
                allergies = ?, medicaments_habituels = ?, habitudes = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['nom'], $data['prenom'], $data['date_naissance'], $data['sexe'],
                $data['lieu_naissance'] ?? null, $data['adresse'] ?? null, $data['ville'] ?? null,
                $data['code_postal'] ?? null, $data['pays'] ?? 'France', $data['telephone'] ?? null,
                $data['telephone_urgence'] ?? null, $data['email'] ?? null, $data['profession'] ?? null,
                $data['situation_familiale'] ?? 'celibataire', (int)($data['nombre_enfants'] ?? 0),
                $data['groupe_sanguin'] ?? null, $data['rhésus'] ?? null,
                !empty($data['poids']) ? (float)$data['poids'] : null,
                !empty($data['taille']) ? (float)$data['taille'] : null,
                $data['antecedents_familiaux'] ?? null, $data['antecedents_personnels'] ?? null,
                $data['allergies'] ?? null, $data['medicaments_habituels'] ?? null,
                $data['habitudes'] ?? null, $data['notes'] ?? null, $id
            ]);
            header('Location: patients.php?success=Patient modifié avec succès');
            exit;
        } elseif ($action === 'delete' && $id) {
            // Archivage (soft delete)
            $stmt = $pdo->prepare("UPDATE patients SET statut = 'archive' WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: patients.php?success=Patient archivé');
            exit;
        }
    } catch (PDOException $e) {
        header("Location: patients.php?action=$action&id=$id&error=" . urlencode($e->getMessage()));
        exit;
    }
}

// --- Récupération des données pour l'affichage ---
$search = trim($_GET['search'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$sql = "SELECT p.*, 
        (SELECT MAX(date_consultation) FROM consultations WHERE patient_id = p.id) as derniere_consultation
        FROM patients p WHERE p.statut = 'actif'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.nom LIKE ? OR p.prenom LIKE ? OR p.code_patient LIKE ? OR p.telephone LIKE ? OR p.email LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

$sql .= " ORDER BY p.nom, p.prenom LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

// Compter le total
$countSql = "SELECT COUNT(*) FROM patients WHERE statut = 'actif'";
$countParams = [];
if (!empty($search)) {
    $countSql .= " AND (nom LIKE ? OR prenom LIKE ? OR code_patient LIKE ? OR telephone LIKE ? OR email LIKE ?)";
    $countParams = array_fill(0, 5, $like);
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalPatients = $countStmt->fetchColumn();
$totalPages = ceil($totalPatients / $limit);

// Récupérer un patient pour l'édition si demandé
$editPatient = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$id]);
    $editPatient = $stmt->fetch();
    if (!$editPatient) {
        header('Location: patients.php?error=Patient introuvable');
        exit;
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between flex-wrap align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-users text-primary me-2"></i>Gestion des patients</h1>
            <p class="text-muted">Liste des patients actifs</p>
        </div>
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Nouveau patient</a>
    </div>

    <!-- Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if ($action === 'add' || ($action === 'edit' && $editPatient)): ?>
        <!-- Formulaire -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><?= $action === 'add' ? 'Ajouter un patient' : 'Modifier le patient' ?></h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label required">Nom</label><input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($editPatient['nom'] ?? '') ?>" required></div>
                        <div class="col-md-6"><label class="form-label required">Prénom</label><input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($editPatient['prenom'] ?? '') ?>" required></div>
                        <div class="col-md-4"><label class="form-label required">Date naissance</label><input type="date" name="date_naissance" class="form-control" value="<?= $editPatient['date_naissance'] ?? '' ?>" required></div>
                        <div class="col-md-2"><label class="form-label required">Sexe</label><select name="sexe" class="form-select"><option value="M" <?= ($editPatient['sexe'] ?? '') == 'M' ? 'selected' : '' ?>>Masculin</option><option value="F" <?= ($editPatient['sexe'] ?? '') == 'F' ? 'selected' : '' ?>>Féminin</option></select></div>
                        <div class="col-md-6"><label>Lieu naissance</label><input type="text" name="lieu_naissance" class="form-control" value="<?= htmlspecialchars($editPatient['lieu_naissance'] ?? '') ?>"></div>
                        <div class="col-md-12"><label>Adresse</label><textarea name="adresse" class="form-control" rows="2"><?= htmlspecialchars($editPatient['adresse'] ?? '') ?></textarea></div>
                        <div class="col-md-4"><label>Ville</label><input type="text" name="ville" class="form-control" value="<?= htmlspecialchars($editPatient['ville'] ?? '') ?>"></div>
                        <div class="col-md-4"><label>Code postal</label><input type="text" name="code_postal" class="form-control" value="<?= htmlspecialchars($editPatient['code_postal'] ?? '') ?>"></div>
                        <div class="col-md-4"><label>Pays</label><input type="text" name="pays" class="form-control" value="<?= htmlspecialchars($editPatient['pays'] ?? 'France') ?>"></div>
                        <div class="col-md-4"><label>Téléphone</label><input type="tel" name="telephone" class="form-control" value="<?= htmlspecialchars($editPatient['telephone'] ?? '') ?>"></div>
                        <div class="col-md-4"><label>Tél. urgence</label><input type="tel" name="telephone_urgence" class="form-control" value="<?= htmlspecialchars($editPatient['telephone_urgence'] ?? '') ?>"></div>
                        <div class="col-md-4"><label>Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editPatient['email'] ?? '') ?>"></div>
                        <div class="col-md-4"><label>Profession</label><input type="text" name="profession" class="form-control" value="<?= htmlspecialchars($editPatient['profession'] ?? '') ?>"></div>
                        <div class="col-md-4"><label>Situation familiale</label><select name="situation_familiale" class="form-select"><option value="celibataire">Célibataire</option><option value="marie" <?= ($editPatient['situation_familiale'] ?? '') == 'marie' ? 'selected' : '' ?>>Marié(e)</option><option value="divorce" <?= ($editPatient['situation_familiale'] ?? '') == 'divorce' ? 'selected' : '' ?>>Divorcé(e)</option><option value="veuf" <?= ($editPatient['situation_familiale'] ?? '') == 'veuf' ? 'selected' : '' ?>>Veuf/Veuve</option></select></div>
                        <div class="col-md-4"><label>Nb enfants</label><input type="number" name="nombre_enfants" class="form-control" value="<?= $editPatient['nombre_enfants'] ?? 0 ?>"></div>
                        <div class="col-md-3"><label>Groupe sanguin</label><input type="text" name="groupe_sanguin" class="form-control" value="<?= htmlspecialchars($editPatient['groupe_sanguin'] ?? '') ?>"></div>
                        <div class="col-md-3"><label>Rhésus</label><select name="rhésus" class="form-select"><option value="">-</option><option value="+" <?= ($editPatient['rhésus'] ?? '') == '+' ? 'selected' : '' ?>>+</option><option value="-" <?= ($editPatient['rhésus'] ?? '') == '-' ? 'selected' : '' ?>>-</option></select></div>
                        <div class="col-md-3"><label>Poids (kg)</label><input type="number" step="0.1" name="poids" class="form-control" value="<?= $editPatient['poids'] ?? '' ?>"></div>
                        <div class="col-md-3"><label>Taille (cm)</label><input type="number" step="0.1" name="taille" class="form-control" value="<?= $editPatient['taille'] ?? '' ?>"></div>
                        <div class="col-12"><label>Antécédents familiaux</label><textarea name="antecedents_familiaux" class="form-control" rows="2"><?= htmlspecialchars($editPatient['antecedents_familiaux'] ?? '') ?></textarea></div>
                        <div class="col-12"><label>Antécédents personnels</label><textarea name="antecedents_personnels" class="form-control" rows="2"><?= htmlspecialchars($editPatient['antecedents_personnels'] ?? '') ?></textarea></div>
                        <div class="col-12"><label>Allergies</label><textarea name="allergies" class="form-control" rows="2"><?= htmlspecialchars($editPatient['allergies'] ?? '') ?></textarea></div>
                        <div class="col-12"><label>Médicaments habituels</label><textarea name="medicaments_habituels" class="form-control" rows="2"><?= htmlspecialchars($editPatient['medicaments_habituels'] ?? '') ?></textarea></div>
                        <div class="col-12"><label>Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($editPatient['notes'] ?? '') ?></textarea></div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button>
                        <a href="patients.php" class="btn btn-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Barre de recherche -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-8"><input type="text" name="search" class="form-control" placeholder="Rechercher par nom, prénom, code patient, téléphone, email..." value="<?= htmlspecialchars($search) ?>"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Rechercher</button></div>
                    <div class="col-md-2"><a href="patients.php" class="btn btn-outline-secondary w-100">Réinitialiser</a></div>
                </form>
            </div>
        </div>

        <!-- Liste des patients -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between">
                <span><i class="fas fa-list me-2"></i>Patients (<?= $totalPatients ?>)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Code</th><th>Nom complet</th><th>Âge</th><th>Sexe</th><th>Téléphone</th><th>Ville</th><th>Dernière consultation</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($patients as $p): 
                                $age = floor((time() - strtotime($p['date_naissance'])) / (365.25 * 86400));
                            ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($p['code_patient']) ?></span></td>
                                <td><strong><?= htmlspecialchars($p['prenom'].' '.$p['nom']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($p['email'] ?? '') ?></small></td>
                                <td><?= $age ?> ans</td>
                                <td><?= $p['sexe'] == 'M' ? 'Homme' : 'Femme' ?></td>
                                <td><?= htmlspecialchars($p['telephone'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['ville'] ?? '') ?></td>
                                <td><?= $p['derniere_consultation'] ? date('d/m/Y', strtotime($p['derniere_consultation'])) : '<span class="badge bg-warning">Jamais</span>' ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="patients.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Modifier"><i class="fas fa-edit"></i></a>
                                        <a href="rendezvous.php?action=add&patient_id=<?= $p['id'] ?>" class="btn btn-outline-success" title="Prendre RDV"><i class="fas fa-calendar-plus"></i></a>
                                        <button class="btn btn-outline-danger" onclick="if(confirm('Archiver ce patient ?')) location.href='patients.php?action=delete&id=<?= $p['id'] ?>'" title="Archiver"><i class="fas fa-archive"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($patients)): ?>
                                <tr><td colspan="8" class="text-center py-4">Aucun patient trouvé</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer"><nav><ul class="pagination mb-0"><?php for($i=1;$i<=$totalPages;$i++): ?><li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a></li><?php endfor; ?></ul></nav></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php require_once '../includes/footer.php'; ?>