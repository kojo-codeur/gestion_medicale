<?php
// secretaire/agenda.php - Version sans AJAX avec correction collation
require_once '../config/database.php';
require_once '../includes/sidebar.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'secretaire') {
    header('Location: ../login.php');
    exit;
}

$pdo = Database::getInstance()->getConnection();

// Forcer la collation de la connexion pour éviter les erreurs de mix
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

$title = 'Agenda des rendez-vous';

// --- Traitement du formulaire (POST classique) ---
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_rdv'])) {
    $patient_id = (int)$_POST['patient_id'];
    $docteur_id = (int)$_POST['docteur_id'];
    $date_rdv = $_POST['date_rdv'];
    $duree = (int)($_POST['duree'] ?? 30);
    $type_rdv = $_POST['type_rdv'] ?? 'consultation';
    $motif = $_POST['motif'] ?? null;
    $notes = $_POST['notes'] ?? null;
    $statut = 'confirme';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO rendez_vous (patient_id, docteur_id, date_rdv, duree, type_rdv, motif, notes, statut, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$patient_id, $docteur_id, $date_rdv, $duree, $type_rdv, $motif, $notes, $statut, $_SESSION['user_id']]);
        $success = "Rendez-vous ajouté avec succès.";
    } catch (PDOException $e) {
        $error = "Erreur lors de l'ajout : " . $e->getMessage();
    }
}

// --- Récupération des données pour l'affichage ---
$view = $_GET['view'] ?? 'week';
$date = $_GET['date'] ?? date('Y-m-d');
$currentDate = new DateTime($date);
$startDate = clone $currentDate;

if ($view === 'week') {
    $dayOfWeek = $currentDate->format('N');
    $startDate->modify('-' . ($dayOfWeek - 1) . ' days');
    $endDate = clone $startDate;
    $endDate->modify('+6 days');
    $dates = [];
    for ($i = 0; $i < 7; $i++) {
        $dates[] = clone $startDate->modify('+' . ($i == 0 ? 0 : 1) . ' days');
    }
    $startDate = clone $currentDate;
    $startDate->modify('-' . ($dayOfWeek - 1) . ' days');
} else {
    $startDate = new DateTime($currentDate->format('Y-m-01'));
    $endDate = new DateTime($currentDate->format('Y-m-t'));
    $dates = [];
    $tmp = clone $startDate;
    while ($tmp <= $endDate) {
        $dates[] = clone $tmp;
        $tmp->modify('+1 day');
    }
}

// Requête avec gestion des collations (explicite si nécessaire)
$sql = "SELECT r.*, 
        CONCAT(p.nom, ' ', p.prenom) as patient_nom, p.code_patient, p.telephone,
        CONCAT(d.nom, ' ', d.prenom) as docteur_nom, d.specialite
        FROM rendez_vous r
        JOIN patients p ON r.patient_id = p.id
        JOIN utilisateurs d ON r.docteur_id = d.id
        WHERE r.date_rdv BETWEEN ? AND ?
        AND r.statut NOT IN ('annule')
        ORDER BY r.date_rdv ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$startDate->format('Y-m-d 00:00:00'), $endDate->format('Y-m-d 23:59:59')]);
$rdvs = $stmt->fetchAll();

$rdvsByDate = [];
foreach ($rdvs as $rdv) {
    $dateKey = date('Y-m-d', strtotime($rdv['date_rdv']));
    $rdvsByDate[$dateKey][] = $rdv;
}

$prevLink = ($view === 'week') 
    ? '?view=week&date=' . (clone $startDate)->modify('-7 days')->format('Y-m-d')
    : '?view=month&date=' . (clone $startDate)->modify('-1 month')->format('Y-m-d');
$nextLink = ($view === 'week')
    ? '?view=week&date=' . (clone $startDate)->modify('+7 days')->format('Y-m-d')
    : '?view=month&date=' . (clone $startDate)->modify('+1 month')->format('Y-m-d');

$patients = $pdo->query("SELECT id, nom, prenom, code_patient FROM patients WHERE statut = 'actif' ORDER BY nom, prenom")->fetchAll();
$docteurs = $pdo->query("SELECT id, nom, prenom, specialite FROM utilisateurs WHERE role = 'docteur' AND statut = 'actif' ORDER BY nom, prenom")->fetchAll();
?>

<style>
    .agenda-day { background: white; border: 1px solid #dee2e6; min-height: 500px; padding: 10px; position: relative; }
    .agenda-day-header { background: #f8f9fa; padding: 10px; text-align: center; font-weight: bold; border-bottom: 1px solid #dee2e6; }
    .rdv-item { background: #e3f2fd; border-left: 3px solid #0d6efd; padding: 8px; margin-bottom: 8px; border-radius: 5px; font-size: 0.85rem; }
    .rdv-item .time { font-weight: bold; color: #0d6efd; }
    .today { background-color: #fff3cd; }
    .btn-add-rdv { position: absolute; bottom: 10px; right: 10px; background: #0d6efd; color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .btn-add-rdv:hover { background: #0b5ed7; transform: scale(1.05); }
    .floating-add { position: fixed; bottom: 30px; right: 30px; background: #0d6efd; color: white; border-radius: 50%; width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); cursor: pointer; z-index: 1000; }
    .floating-add:hover { background: #0b5ed7; transform: scale(1.05); }
    @media (max-width: 768px) { .agenda-day { min-height: auto; } }
</style>


<?php require_once '../includes/header.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="fas fa-calendar-alt text-primary me-2"></i>Agenda</h1>
        <div>
            <a href="<?= $prevLink ?>" class="btn btn-outline-secondary"><i class="fas fa-chevron-left"></i></a>
            <a href="?view=week&date=<?= date('Y-m-d') ?>" class="btn btn-outline-primary">Aujourd'hui</a>
            <a href="<?= $nextLink ?>" class="btn btn-outline-secondary"><i class="fas fa-chevron-right"></i></a>
            <div class="btn-group ms-2">
                <a href="?view=week&date=<?= $currentDate->format('Y-m-d') ?>" class="btn btn-<?= $view === 'week' ? 'primary' : 'outline-secondary' ?>">Semaine</a>
                <a href="?view=month&date=<?= $currentDate->format('Y-m-d') ?>" class="btn btn-<?= $view === 'month' ? 'primary' : 'outline-secondary' ?>">Mois</a>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if ($view === 'week'): ?>
        <div class="row">
            <?php foreach ($dates as $day): 
                $isToday = ($day->format('Y-m-d') == date('Y-m-d'));
                $dateKey = $day->format('Y-m-d');
            ?>
                <div class="col-md">
                    <div class="agenda-day <?= $isToday ? 'today' : '' ?>">
                        <div class="agenda-day-header"><?= $day->format('D d/m') ?></div>
                        <div class="p-2">
                            <?php if (isset($rdvsByDate[$dateKey])): ?>
                                <?php foreach ($rdvsByDate[$dateKey] as $rdv): ?>
                                    <div class="rdv-item">
                                        <div class="time"><?= date('H:i', strtotime($rdv['date_rdv'])) ?></div>
                                        <strong><?= htmlspecialchars($rdv['patient_nom']) ?></strong><br>
                                        <small><?= htmlspecialchars($rdv['docteur_nom']) ?></small><br>
                                        <small class="text-muted"><?= htmlspecialchars($rdv['type_rdv']) ?></small>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-muted text-center small">Aucun RDV</div>
                            <?php endif; ?>
                        </div>
                        <div class="btn-add-rdv" data-date="<?= $dateKey ?>" title="Ajouter un rendez-vous ce jour"><i class="fas fa-plus"></i></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h5 class="mb-0"><?= $currentDate->format('F Y') ?></h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light"><tr><th>Lundi</th><th>Mardi</th><th>Mercredi</th><th>Jeudi</th><th>Vendredi</th><th>Samedi</th><th>Dimanche</th></tr></thead>
                        <tbody>
                            <?php
                            $firstDayOfMonth = new DateTime($currentDate->format('Y-m-01'));
                            $startWeek = clone $firstDayOfMonth;
                            $dayOfWeek = $firstDayOfMonth->format('N');
                            $startWeek->modify('-' . ($dayOfWeek - 1) . ' days');
                            for ($row = 0; $row < 6; $row++):
                                echo '<tr>';
                                for ($col = 0; $col < 7; $col++):
                                    $cellDate = clone $startWeek;
                                    $cellDate->modify('+' . ($row * 7 + $col) . ' days');
                                    $dateKey = $cellDate->format('Y-m-d');
                                    $isCurrentMonth = ($cellDate->format('m') == $currentDate->format('m'));
                                    $isToday = ($dateKey == date('Y-m-d'));
                            ?>
                                <td style="vertical-align: top; height: 120px; background: <?= $isToday ? '#fff3cd' : ($isCurrentMonth ? 'white' : '#f8f9fa') ?>">
                                    <div class="fw-bold mb-1"><?= $cellDate->format('j') ?></div>
                                    <?php if (isset($rdvsByDate[$dateKey])): ?>
                                        <?php foreach ($rdvsByDate[$dateKey] as $rdv): ?>
                                            <div class="small" style="background:#e3f2fd; margin-bottom:3px; padding:2px 5px; border-radius:3px;">
                                                <?= date('H:i', strtotime($rdv['date_rdv'])) ?> - <?= htmlspecialchars($rdv['patient_nom']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <div class="text-center mt-2"><i class="fas fa-plus-circle text-primary add-day" data-date="<?= $dateKey ?>" style="cursor:pointer;"></i></div>
                                </td>
                            <?php endfor; echo '</tr>'; endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Bouton flottant -->
<div class="floating-add" id="floatingAddBtn"><i class="fas fa-plus"></i></div>

<!-- Modal (formulaire classique) -->
<div class="modal fade" id="rdvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Nouveau rendez-vous</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form method="POST" id="rdvForm">
                    <input type="hidden" name="add_rdv" value="1">
                    <div class="mb-3">
                        <label class="form-label">Patient *</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['code_patient'] . ' - ' . $p['prenom'] . ' ' . $p['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Médecin *</label>
                        <select name="docteur_id" class="form-select" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($docteurs as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom'] . ' (' . $d['specialite'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date et heure *</label>
                        <input type="datetime-local" name="date_rdv" class="form-control" required id="dateRdvInput">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Durée (minutes)</label>
                        <input type="number" name="duree" class="form-control" value="30">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type_rdv" class="form-select">
                            <option value="consultation">Consultation</option><option value="controle">Contrôle</option>
                            <option value="urgence">Urgence</option><option value="autre">Autre</option>
                        </select>
                    </div>
                    <div class="mb-3"><label>Motif</label><textarea name="motif" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label>Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary" form="rdvForm">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    function openModalWithDate(date) {
        $('#dateRdvInput').val(date + 'T10:00');
        $('#rdvModal').modal('show');
    }
    $('.btn-add-rdv').click(function() { openModalWithDate($(this).data('date')); });
    $('.add-day').click(function() { openModalWithDate($(this).data('date')); });
    $('#floatingAddBtn').click(function() {
        $('#rdvForm')[0].reset();
        $('#dateRdvInput').val('');
        $('#rdvModal').modal('show');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>