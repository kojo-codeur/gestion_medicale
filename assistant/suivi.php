<?php
// assistant/suivi.php
require_once '../config/database.php';
checkRole('assistant');

$pdo = Database::getInstance()->getConnection();

$title = 'Suivi des Patients';

// Variables pour les messages
$success_message = '';
$error_message = '';

// Variables de filtrage
$filter_patient = $_GET['patient'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_date_start = $_GET['date_start'] ?? date('Y-m-01');
$filter_date_end = $_GET['date_end'] ?? date('Y-m-d');
$filter_doctor = $_GET['doctor'] ?? '';

// Récupérer la liste des docteurs
$doctors_stmt = $pdo->query("
    SELECT id, nom, prenom, specialite 
    FROM utilisateurs 
    WHERE role = 'docteur' AND statut = 'actif' 
    ORDER BY nom, prenom
");
$doctors = $doctors_stmt->fetchAll();

// Récupérer la liste des patients
$patients_stmt = $pdo->query("
    SELECT id, nom, prenom, code_patient 
    FROM patients 
    WHERE statut = 'actif' 
    ORDER BY nom, prenom
");
$patients = $patients_stmt->fetchAll();

// --- Récupération des patients en salle d'attente (hébergés) ---
$waiting_stmt = $pdo->prepare("
    SELECT sa.*,
           p.nom as patient_nom,
           p.prenom as patient_prenom,
           p.code_patient,
           p.telephone,
           p.date_naissance,
           TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as patient_age,
           TIMESTAMPDIFF(MINUTE, sa.added_at, NOW()) as waiting_minutes,
           d.nom as docteur_nom,
           d.prenom as docteur_prenom,
           d.specialite,
           CONCAT(u.prenom, ' ', u.nom) as added_by_name
    FROM salle_attente sa
    JOIN patients p ON sa.patient_id = p.id
    LEFT JOIN utilisateurs d ON sa.docteur_id = d.id
    LEFT JOIN utilisateurs u ON sa.added_by = u.id
    WHERE sa.statut = 'en_attente'
    ORDER BY sa.urgence DESC, sa.added_at ASC
");
$waiting_stmt->execute();
$waiting_patients = $waiting_stmt->fetchAll();

// --- Récupération des consultations avec filtres ---
$query = "
    SELECT 
        c.*,
        p.nom as patient_nom,
        p.prenom as patient_prenom,
        p.code_patient,
        p.telephone,
        p.date_naissance,
        TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as patient_age,
        d.nom as docteur_nom,
        d.prenom as docteur_prenom,
        d.specialite,
        (SELECT COUNT(*) FROM prescriptions WHERE consultation_id = c.id) as prescriptions_count,
        (SELECT COUNT(*) FROM documents_medicaux WHERE consultation_id = c.id) as documents_count,
        (SELECT COUNT(*) FROM patient_pathologie WHERE patient_id = c.patient_id) as pathologies_count
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    JOIN utilisateurs d ON c.docteur_id = d.id
    WHERE 1=1
";

$params = [];

if ($filter_patient) {
    $query .= " AND c.patient_id = ?";
    $params[] = $filter_patient;
}
if ($filter_status && $filter_status != 'all') {
    $query .= " AND c.statut = ?";
    $params[] = $filter_status;
}
if ($filter_date_start) {
    $query .= " AND DATE(c.date_consultation) >= ?";
    $params[] = $filter_date_start;
}
if ($filter_date_end) {
    $query .= " AND DATE(c.date_consultation) <= ?";
    $params[] = $filter_date_end;
}
if ($filter_doctor) {
    $query .= " AND c.docteur_id = ?";
    $params[] = $filter_doctor;
}

$query .= " ORDER BY c.date_consultation DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$consultations = $stmt->fetchAll();

// --- Statistiques générales ---
$stats_stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM consultations WHERE DATE(date_consultation) = CURDATE()) as consultations_today,
        (SELECT COUNT(*) FROM consultations WHERE statut IN ('planifie', 'en_cours')) as consultations_pending,
        (SELECT COUNT(DISTINCT patient_id) FROM consultations WHERE DATE(date_consultation) = CURDATE()) as patients_today,
        (SELECT COUNT(*) FROM prescriptions WHERE DATE(date_prescription) = CURDATE()) as prescriptions_today,
        (SELECT COUNT(*) FROM patients WHERE statut = 'actif') as total_patients,
        (SELECT COUNT(*) FROM consultations WHERE urgence = 1 AND DATE(date_consultation) = CURDATE()) as urgent_today,
        (SELECT AVG(TIMESTAMPDIFF(HOUR, date_consultation, NOW())) FROM consultations WHERE statut = 'planifie') as avg_wait_hours,
        (SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_rdv) = CURDATE() AND statut = 'confirme') as rdv_today,
        (SELECT COUNT(*) FROM salle_attente WHERE statut = 'en_attente') as waiting_count,
        (SELECT COUNT(*) FROM salle_attente WHERE statut = 'en_attente' AND urgence = 1) as urgent_waiting
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// --- Patients récents (5 derniers) ---
$recent_patients_stmt = $pdo->prepare("
    SELECT p.*, 
           MAX(c.date_consultation) as last_consultation,
           COUNT(c.id) as consultation_count
    FROM patients p
    LEFT JOIN consultations c ON p.id = c.patient_id
    WHERE p.statut = 'actif'
    GROUP BY p.id
    ORDER BY last_consultation DESC
    LIMIT 5
");
$recent_patients_stmt->execute();
$recent_patients = $recent_patients_stmt->fetchAll();

// Fonctions utilitaires
function getStatusColor($status) {
    switch($status) {
        case 'termine': return 'success';
        case 'en_cours': return 'info';
        case 'planifie': return 'warning';
        case 'annule': return 'danger';
        case 'reporte': return 'secondary';
        default: return 'secondary';
    }
}
function formatDuration($minutes) {
    if ($minutes < 60) return $minutes . ' min';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours . 'h' . ($mins > 0 ? ' ' . $mins . 'min' : '');
}
function formatWaitingTime($minutes) {
    if ($minutes < 60) return $minutes . ' min';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours . 'h' . ($mins > 0 ? ' ' . $mins . 'min' : '');
}
function getWaitingColor($minutes, $urgence) {
    if ($urgence) return 'danger';
    if ($minutes > 60) return 'warning';
    if ($minutes > 30) return 'info';
    return 'success';
}

require_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
        <div>
            <h1 class="h2 mb-0"><i class="fas fa-user-md me-2"></i>Suivi des Patients</h1>
            <p class="text-muted mb-0">Consultations et patients en attente</p>
        </div>
        <div class="btn-toolbar">
            <button type="button" class="btn btn-primary me-2" onclick="exportToExcel()"><i class="fas fa-file-excel me-1"></i>Exporter</button>
            <a href="consultations.php?action=add" class="btn btn-success"><i class="fas fa-plus me-1"></i>Nouvelle consultation</a>
        </div>
    </div>

    <!-- Cartes statistiques -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-6 mb-4"><div class="card border-start border-primary border-4 shadow-sm"><div class="card-body py-3 text-center"><div class="h2 mb-1"><?= $stats['consultations_today'] ?? 0 ?></div><div class="small text-muted">Consultations aujourd'hui</div></div></div></div>
        <div class="col-xl-2 col-md-4 col-6 mb-4"><div class="card border-start border-warning border-4 shadow-sm"><div class="card-body py-3 text-center"><div class="h2 mb-1"><?= $stats['consultations_pending'] ?? 0 ?></div><div class="small text-muted">En attente</div></div></div></div>
        <div class="col-xl-2 col-md-4 col-6 mb-4"><div class="card border-start border-info border-4 shadow-sm"><div class="card-body py-3 text-center"><div class="h2 mb-1"><?= $stats['patients_today'] ?? 0 ?></div><div class="small text-muted">Patients aujourd'hui</div></div></div></div>
        <div class="col-xl-2 col-md-4 col-6 mb-4"><div class="card border-start border-success border-4 shadow-sm"><div class="card-body py-3 text-center"><div class="h2 mb-1"><?= $stats['prescriptions_today'] ?? 0 ?></div><div class="small text-muted">Prescriptions</div></div></div></div>
        <div class="col-xl-2 col-md-4 col-6 mb-4"><div class="card border-start border-danger border-4 shadow-sm"><div class="card-body py-3 text-center"><div class="h2 mb-1"><?= $stats['urgent_today'] ?? 0 ?></div><div class="small text-muted">Urgences</div></div></div></div>
        <div class="col-xl-2 col-md-4 col-6 mb-4"><div class="card border-start border-secondary border-4 shadow-sm"><div class="card-body py-3 text-center"><div class="h2 mb-1"><?= $stats['rdv_today'] ?? 0 ?></div><div class="small text-muted">RDV aujourd'hui</div></div></div></div>
    </div>

    <!-- Patients en attente (hébergés) -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Patients en attente <span class="badge bg-secondary ms-2"><?= $stats['waiting_count'] ?? 0 ?></span></h6>
            <a href="salle-attente.php" class="btn btn-sm btn-outline-primary">Gérer la salle d'attente <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
        <div class="card-body">
            <?php if (empty($waiting_patients)): ?>
                <div class="text-center py-4 text-muted"><i class="fas fa-user-clock fa-2x mb-3"></i><p>Aucun patient en attente</p></div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($waiting_patients as $patient): 
                        $waiting_color = getWaitingColor($patient['waiting_minutes'], $patient['urgence']);
                    ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card border h-100">
                            <div class="card-header bg-<?= $waiting_color ?> bg-opacity-10">
                                <div class="d-flex justify-content-between">
                                    <div><strong><?= htmlspecialchars($patient['patient_prenom'] . ' ' . $patient['patient_nom']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($patient['code_patient']) ?></small></div>
                                    <div><span class="badge bg-<?= $waiting_color ?>"><?= formatWaitingTime($patient['waiting_minutes']) ?></span><?= $patient['urgence'] ? '<span class="badge bg-danger ms-1">Urgent</span>' : '' ?></div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($patient['telephone']) ?></div>
                                <div><i class="fas fa-user me-1"></i> <?= $patient['patient_age'] ?> ans</div>
                                <?php if ($patient['docteur_nom']): ?>
                                <div><i class="fas fa-user-md me-1"></i> Dr. <?= htmlspecialchars($patient['docteur_prenom'] . ' ' . $patient['docteur_nom']) ?></div>
                                <?php endif; ?>
                                <?php if ($patient['motif']): ?>
                                <div class="mt-2 small text-muted"><i class="fas fa-comment-medical me-1"></i> <?= nl2br(htmlspecialchars(substr($patient['motif'], 0, 80))) ?></div>
                                <?php endif; ?>
                                <div class="mt-3 d-flex justify-content-between">
                                    <button class="btn btn-sm btn-primary" onclick="callPatient(<?= $patient['id'] ?>)"><i class="fas fa-bell"></i> Appeler</button>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-danger" onclick="removePatient(<?= $patient['id'] ?>)"><i class="fas fa-times"></i> Retirer</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filtres consultations</h6></div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3"><label class="form-label">Patient</label><select class="form-select" name="patient"><option value="">Tous les patients</option><?php foreach ($patients as $patient): ?><option value="<?= $patient['id'] ?>" <?= $filter_patient == $patient['id'] ? 'selected' : '' ?>><?= htmlspecialchars($patient['nom'] . ' ' . $patient['prenom']) ?> (<?= htmlspecialchars($patient['code_patient']) ?>)</option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Statut</label><select class="form-select" name="status"><option value="all" <?= $filter_status == 'all' || !$filter_status ? 'selected' : '' ?>>Tous</option><option value="planifie" <?= $filter_status == 'planifie' ? 'selected' : '' ?>>Planifié</option><option value="en_cours" <?= $filter_status == 'en_cours' ? 'selected' : '' ?>>En cours</option><option value="termine" <?= $filter_status == 'termine' ? 'selected' : '' ?>>Terminé</option><option value="annule" <?= $filter_status == 'annule' ? 'selected' : '' ?>>Annulé</option></select></div>
                <div class="col-md-2"><label class="form-label">Docteur</label><select class="form-select" name="doctor"><option value="">Tous</option><?php foreach ($doctors as $doctor): ?><option value="<?= $doctor['id'] ?>" <?= $filter_doctor == $doctor['id'] ? 'selected' : '' ?>>Dr. <?= htmlspecialchars($doctor['prenom'] . ' ' . $doctor['nom']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Date début</label><input type="date" class="form-control" name="date_start" value="<?= $filter_date_start ?>"></div>
                <div class="col-md-2"><label class="form-label">Date fin</label><input type="date" class="form-control" name="date_end" value="<?= $filter_date_end ?>"></div>
                <div class="col-md-1 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button></div>
            </form>
        </div>
    </div>

    <!-- Tableau des consultations -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-list me-2"></i>Consultations <span class="badge bg-secondary ms-2"><?= count($consultations) ?></span></h6>
            <div><a href="suivi.php" class="btn btn-sm btn-outline-secondary me-2"><i class="fas fa-sync"></i></a><a href="consultations.php?action=add" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>Ajouter</a></div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="suiviTable">
                    <thead><tr><th>Patient</th><th>Docteur</th><th>Date/Heure</th><th>Motif</th><th>Statut</th><th>Durée</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($consultations)): ?>
                        <tr><td colspan="7" class="text-center py-4"><i class="fas fa-search fa-2x text-muted mb-3"></i><p class="text-muted">Aucune consultation trouvée</p></td></tr>
                        <?php else: foreach ($consultations as $consult): ?>
                        <tr>
                            <td><div class="d-flex align-items-center"><div class="avatar me-3"><?= strtoupper(substr($consult['patient_prenom'],0,1).substr($consult['patient_nom'],0,1)) ?></div><div><div class="fw-semibold"><?= htmlspecialchars($consult['patient_prenom'].' '.$consult['patient_nom']) ?></div><small class="text-muted"><?= htmlspecialchars($consult['code_patient']) ?> • <?= $consult['patient_age'] ?> ans</small></div></div></td>
                            <td><div class="fw-semibold">Dr. <?= htmlspecialchars($consult['docteur_prenom'].' '.$consult['docteur_nom']) ?></div><small class="text-muted"><?= htmlspecialchars($consult['specialite']) ?></small></td>
                            <td><div><?= date('d/m/Y', strtotime($consult['date_consultation'])) ?></div><small class="text-muted"><?= date('H:i', strtotime($consult['date_consultation'])) ?></small></td>
                            <td><?php if ($consult['motif']): ?><span title="<?= htmlspecialchars($consult['motif']) ?>"><?= htmlspecialchars(substr($consult['motif'],0,50)) ?><?= strlen($consult['motif'])>50 ? '...' : '' ?></span><?php else: ?><span class="text-muted">Non spécifié</span><?php endif; ?></td>
                            <td><span class="badge bg-<?= getStatusColor($consult['statut']) ?>"><?= ucfirst($consult['statut']) ?></span><?= $consult['urgence'] ? '<span class="badge bg-danger ms-1">Urgent</span>' : '' ?></td>
                            <td><?= formatDuration($consult['duree'] ?? 30) ?></td>
                            <td><div class="btn-group btn-group-sm"><a href="consultation_details.php?id=<?= $consult['id'] ?>" class="btn btn-outline-primary" title="Détails"><i class="fas fa-eye"></i></a><?php if ($consult['statut'] != 'termine' && $consult['statut'] != 'annule'): ?><a href="consultations.php?action=edit&id=<?= $consult['id'] ?>" class="btn btn-outline-warning" title="Modifier"><i class="fas fa-edit"></i></a><?php endif; ?><?php if ($consult['statut'] == 'termine'): ?><a href="prescription_create.php?consultation_id=<?= $consult['id'] ?>" class="btn btn-outline-success" title="Prescrire"><i class="fas fa-prescription"></i></a><?php endif; ?></div></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Patients récents -->
    <div class="row mt-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-history me-2"></i>Patients récemment vus</h6></div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($recent_patients as $patient): $age = calculateAge($patient['date_naissance']); ?>
                        <div class="col-md-6 mb-3"><div class="card border h-100"><div class="card-body"><div class="d-flex"><div class="avatar me-3"><?= strtoupper(substr($patient['prenom'],0,1).substr($patient['nom'],0,1)) ?></div><div><h6 class="mb-1"><?= htmlspecialchars($patient['prenom'].' '.$patient['nom']) ?></h6><div class="small text-muted"><div><i class="fas fa-user me-1"></i><?= $age ?> ans <span class="ms-2"><i class="fas fa-id-card me-1"></i><?= htmlspecialchars($patient['code_patient']) ?></span></div><?php if ($patient['telephone']): ?><div><i class="fas fa-phone me-1"></i><?= htmlspecialchars($patient['telephone']) ?></div><?php endif; ?><?php if ($patient['last_consultation']): ?><div><i class="fas fa-calendar-check me-1"></i> Dernière consult: <?= date('d/m/Y', strtotime($patient['last_consultation'])) ?> <span class="badge bg-info ms-2"><?= $patient['consultation_count'] ?> consult(s)</span></div><?php endif; ?></div></div></div><div class="mt-3"><a href="patients.php?action=view&id=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-primary me-2"><i class="fas fa-eye me-1"></i>Voir dossier</a><a href="consultations.php?action=add&patient_id=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-stethoscope me-1"></i>Nouvelle consult</a></div></div></div></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Statistiques rapides</h6></div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item border-0 d-flex justify-content-between"><span><i class="fas fa-users text-primary me-2"></i>Patients actifs</span><span class="badge bg-primary"><?= $stats['total_patients'] ?? 0 ?></span></div>
                        <div class="list-group-item border-0 d-flex justify-content-between"><span><i class="fas fa-clock text-warning me-2"></i>Temps d'attente moyen</span><span class="badge bg-warning"><?= round($stats['avg_wait_hours'] ?? 0, 1) ?>h</span></div>
                        <div class="list-group-item border-0 d-flex justify-content-between"><span><i class="fas fa-prescription text-success me-2"></i>Prescriptions aujourd'hui</span><span class="badge bg-success"><?= $stats['prescriptions_today'] ?? 0 ?></span></div>
                        <div class="list-group-item border-0 d-flex justify-content-between"><span><i class="fas fa-exclamation-triangle text-danger me-2"></i>Urgences aujourd'hui</span><span class="badge bg-danger"><?= $stats['urgent_today'] ?? 0 ?></span></div>
                        <div class="list-group-item border-0 d-flex justify-content-between"><span><i class="fas fa-calendar-check text-info me-2"></i>RDV confirmés</span><span class="badge bg-info"><?= $stats['rdv_today'] ?? 0 ?></span></div>
                        <div class="list-group-item border-0 d-flex justify-content-between"><span><i class="fas fa-hourglass-half text-secondary me-2"></i>En attente</span><span class="badge bg-secondary"><?= $stats['waiting_count'] ?? 0 ?> (dont <?= $stats['urgent_waiting'] ?? 0 ?> urgent)</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    const table = document.getElementById('suiviTable');
    const html = table.outerHTML;
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'suivi_patients_' + new Date().toISOString().slice(0,10) + '.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function callPatient(waitingId) {
    if (confirm('Appeler ce patient ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'salle-attente.php';
        const inputAction = document.createElement('input'); inputAction.type = 'hidden'; inputAction.name = 'action'; inputAction.value = 'call_patient';
        const inputId = document.createElement('input'); inputId.type = 'hidden'; inputId.name = 'waiting_id'; inputId.value = waitingId;
        form.appendChild(inputAction); form.appendChild(inputId);
        document.body.appendChild(form); form.submit();
    }
}
function removePatient(waitingId) {
    if (confirm('Retirer ce patient de la salle d\'attente ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'salle-attente.php';
        const inputAction = document.createElement('input'); inputAction.type = 'hidden'; inputAction.name = 'action'; inputAction.value = 'remove_patient';
        const inputId = document.createElement('input'); inputId.type = 'hidden'; inputId.name = 'waiting_id'; inputId.value = waitingId;
        const inputRaison = document.createElement('input'); inputRaison.type = 'hidden'; inputRaison.name = 'raison'; inputRaison.value = 'Retiré depuis suivi';
        form.appendChild(inputAction); form.appendChild(inputId); form.appendChild(inputRaison);
        document.body.appendChild(form); form.submit();
    }
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[title]').forEach(el => new bootstrap.Tooltip(el));
});
</script>

<style>
.avatar { width: 40px; height: 40px; border-radius: 50%; background-color: #4361ee; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }
</style>

<?php require_once '../includes/footer.php'; ?>