<?php
// docteur/dashboard.php
require_once '../config/database.php';
checkRole('docteur');

$title = 'Dashboard Docteur';
$docteur_id = $_SESSION['user_id'];

// CORRECTION : Utiliser execute() puis fetch()
$docteur_stmt = $pdo->prepare("
    SELECT u.*, ds.specialite_id, s.nom as specialite_nom, s.couleur as specialite_couleur
    FROM utilisateurs u
    LEFT JOIN docteur_specialite ds ON u.id = ds.docteur_id AND ds.principal = 1
    LEFT JOIN specialites s ON ds.specialite_id = s.id
    WHERE u.id = ?
");

// CORRECTION : Appeler execute() puis fetch()
$docteur_stmt->execute([$docteur_id]);
$docteur = $docteur_stmt->fetch();

$_SESSION['specialite'] = $docteur['specialite_nom'] ?? 'Médecin généraliste';

require_once '../includes/header.php';

// CORRECTION : Statistiques du docteur avec execute() puis fetch()
$stats_stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM consultations WHERE docteur_id = ? AND DATE(date_consultation) = CURDATE()) as consultations_today,
        (SELECT COUNT(*) FROM rendez_vous WHERE docteur_id = ? AND DATE(date_rdv) = CURDATE() AND statut = 'confirme') as rdv_today,
        (SELECT COUNT(*) FROM prescriptions WHERE docteur_id = ? AND statut = 'active') as prescriptions_active,
        (SELECT COUNT(DISTINCT patient_id) FROM consultations WHERE docteur_id = ?) as patients_total,
        (SELECT COUNT(*) FROM consultations WHERE docteur_id = ? AND statut IN ('planifie', 'en_cours')) as consultations_pending,
        (SELECT COUNT(*) FROM consultations WHERE docteur_id = ? AND urgence = 1 AND DATE(date_consultation) = CURDATE()) as urgent_today,
        (SELECT AVG(TIMESTAMPDIFF(MINUTE, date_consultation, NOW())) FROM consultations WHERE docteur_id = ? AND statut = 'termine' AND DATE(date_consultation) = CURDATE()) as avg_consult_duration,
        (SELECT COUNT(*) FROM patients WHERE statut = 'actif' AND created_by = ?) as patients_registered
");

// CORRECTION : Exécuter avec tous les paramètres
$stats_stmt->execute([
    $docteur_id, $docteur_id, $docteur_id, $docteur_id, 
    $docteur_id, $docteur_id, $docteur_id, $docteur_id
]);
$stats = $stats_stmt->fetch();

// CORRECTION : RDV du jour
$rdv_stmt = $pdo->prepare("
    SELECT r.*, p.nom as patient_nom, p.prenom as patient_prenom, p.telephone, 
           p.date_naissance, p.code_patient, p.groupe_sanguin,
           TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as age
    FROM rendez_vous r
    JOIN patients p ON r.patient_id = p.id
    WHERE r.docteur_id = ?
    AND DATE(r.date_rdv) = CURDATE()
    AND r.statut = 'confirme'
    ORDER BY r.date_rdv
");
$rdv_stmt->execute([$docteur_id]);
$rdv_today = $rdv_stmt->fetchAll();

// CORRECTION : Consultations récentes
$consultations_stmt = $pdo->prepare("
    SELECT c.*, p.nom as patient_nom, p.prenom as patient_prenom, 
           p.code_patient, p.telephone, p.date_naissance,
           (SELECT COUNT(*) FROM prescriptions WHERE consultation_id = c.id) as prescriptions_count,
           (SELECT COUNT(*) FROM documents_medicaux WHERE consultation_id = c.id) as documents_count
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    WHERE c.docteur_id = ?
    ORDER BY c.date_consultation DESC
    LIMIT 6
");
$consultations_stmt->execute([$docteur_id]);
$recent_consultations = $consultations_stmt->fetchAll();

// CORRECTION : Patients avec suivi urgent
$patients_urgents_stmt = $pdo->prepare("
    SELECT p.*, 
           TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as age,
           (SELECT COUNT(*) FROM consultations WHERE patient_id = p.id AND urgence = 1 AND DATE(date_consultation) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as urgent_count,
           (SELECT MAX(date_consultation) FROM consultations WHERE patient_id = p.id) as last_consultation
    FROM patients p
    WHERE p.id IN (
        SELECT DISTINCT patient_id FROM consultations 
        WHERE docteur_id = ? AND urgence = 1 
        AND DATE(date_consultation) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    )
    AND p.statut = 'actif'
    ORDER BY last_consultation DESC
    LIMIT 4
");
$patients_urgents_stmt->execute([$docteur_id]);
$patients_urgents = $patients_urgents_stmt->fetchAll();

// CORRECTION : Récupérer les prochains RDV
$next_rdv_stmt = $pdo->prepare("
    SELECT r.*, p.nom, p.prenom, p.telephone
    FROM rendez_vous r
    JOIN patients p ON r.patient_id = p.id
    WHERE r.docteur_id = ?
    AND r.date_rdv > NOW()
    AND r.statut = 'confirme'
    ORDER BY r.date_rdv ASC
    LIMIT 3
");
$next_rdv_stmt->execute([$docteur_id]);
$next_rdv = $next_rdv_stmt->fetchAll();
?>

<!-- Le reste du code HTML/JavaScript reste inchangé -->
<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard Docteur
        </h1>
        <p class="text-muted mb-0">
            Dr. <?php echo $_SESSION['prenom'] . ' ' . $_SESSION['nom']; ?> • 
            <span class="fw-semibold" style="color: <?php echo $docteur['specialite_couleur'] ?? '#4361ee'; ?>;">
                <?php echo $docteur['specialite_nom'] ?? 'Médecin généraliste'; ?>
            </span> • 
            <?php echo date('d/m/Y H:i'); ?>
        </p>
    </div>
    <div class="btn-toolbar">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshDashboard()">
                <i class="fas fa-sync-alt me-1"></i>Actualiser
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printSchedule()">
                <i class="fas fa-print me-1"></i>Emploi du temps
            </button>
        </div>
        <a href="consultations.php?action=add" class="btn btn-sm btn-primary">
            <i class="fas fa-plus-circle me-1"></i>Nouvelle consultation
        </a>
    </div>
</div>

<!-- Alertes urgentes -->
<?php if ($stats['urgent_today'] > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-danger d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Consultations urgentes aujourd'hui :</strong>
                <?php echo $stats['urgent_today']; ?> patient(s) nécessite(nt) une attention immédiate
            </div>
            <a href="consultations.php?urgence=1" class="btn btn-sm btn-outline-light">
                Voir les urgences
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-primary border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Consultations aujourd'hui</div>
                        <div class="h2 mb-0"><?php echo $stats['consultations_today']; ?></div>
                        <?php if ($stats['avg_consult_duration']): ?>
                        <small class="text-muted">
                            Durée moyenne: <?php echo round($stats['avg_consult_duration']); ?> min
                        </small>
                        <?php endif; ?>
                    </div>
                    <div class="rounded-circle bg-primary-light d-flex align-items-center justify-content-center" 
                         style="width: 60px; height: 60px;">
                        <i class="fas fa-stethoscope text-primary fa-2x"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="consultations.php?date=<?php echo date('Y-m-d'); ?>" class="text-decoration-none small">
                        <i class="fas fa-calendar-day me-1"></i>Voir l'agenda du jour
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-success border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">RDV aujourd'hui</div>
                        <div class="h2 mb-0"><?php echo $stats['rdv_today']; ?></div>
                        <?php if ($stats['consultations_pending'] > 0): ?>
                        <small class="text-warning">
                            <?php echo $stats['consultations_pending']; ?> en attente
                        </small>
                        <?php endif; ?>
                    </div>
                    <div class="rounded-circle bg-success-light d-flex align-items-center justify-content-center" 
                         style="width: 60px; height: 60px;">
                        <i class="fas fa-calendar-check text-success fa-2x"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="rendezvous.php?date=<?php echo date('Y-m-d'); ?>" class="text-decoration-none small">
                        <i class="fas fa-list me-1"></i>Voir tous les RDV
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-warning border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Patients suivis</div>
                        <div class="h2 mb-0"><?php echo $stats['patients_total']; ?></div>
                        <small class="text-muted">
                            <?php echo $stats['patients_registered']; ?> enregistré(s) par vous
                        </small>
                    </div>
                    <div class="rounded-circle bg-warning-light d-flex align-items-center justify-content-center" 
                         style="width: 60px; height: 60px;">
                        <i class="fas fa-user-injured text-warning fa-2x"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="patients.php" class="text-decoration-none small">
                        <i class="fas fa-users me-1"></i>Gérer mes patients
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-info border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Prescriptions actives</div>
                        <div class="h2 mb-0"><?php echo $stats['prescriptions_active']; ?></div>
                        <small class="text-muted">
                            <?php 
                            $prescriptions_today_stmt = $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE docteur_id = ? AND DATE(date_prescription) = CURDATE()");
                            $prescriptions_today_stmt->execute([$docteur_id]);
                            echo $prescriptions_today_stmt->fetchColumn(); 
                            ?> aujourd'hui
                        </small>
                    </div>
                    <div class="rounded-circle bg-info-light d-flex align-items-center justify-content-center" 
                         style="width: 60px; height: 60px;">
                        <i class="fas fa-prescription text-info fa-2x"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="prescriptions.php" class="text-decoration-none small">
                        <i class="fas fa-file-prescription me-1"></i>Voir toutes les prescriptions
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Planning du jour et prochains RDV -->
<div class="row mb-4">
    <!-- RDV du jour -->
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-calendar-day me-2"></i>
                    Planning du jour (<?php echo date('d/m/Y'); ?>)
                </h6>
                <div>
                    <span class="badge bg-primary"><?php echo count($rdv_today); ?> RDV</span>
                    <a href="rendezvous.php?action=add" class="btn btn-sm btn-outline-primary ms-2">
                        <i class="fas fa-plus me-1"></i>Ajouter RDV
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($rdv_today)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucun rendez-vous aujourd'hui</h5>
                    <p class="text-muted small">Profitez de cette journée pour vos tâches administratives</p>
                    <a href="rendezvous.php?action=add" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i>Planifier un RDV
                    </a>
                </div>
                <?php else: ?>
                <div class="timeline-container">
                    <?php 
                    $currentTime = strtotime(date('H:i'));
                    foreach ($rdv_today as $index => $rdv): 
                        $rdvTime = strtotime(date('H:i', strtotime($rdv['date_rdv'])));
                        $isPast = $rdvTime < $currentTime;
                        $isNow = $rdvTime <= $currentTime && ($currentTime - $rdvTime) < 3600; // Dans l'heure
                    ?>
                    <div class="timeline-item <?php echo $isNow ? 'current' : ($isPast ? 'past' : 'upcoming'); ?>">
                        <div class="timeline-time">
                            <?php echo date('H:i', strtotime($rdv['date_rdv'])); ?>
                            <small class="d-block text-muted"><?php echo $rdv['duree']; ?> min</small>
                        </div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo $rdv['patient_prenom'] . ' ' . $rdv['patient_nom']; ?></h6>
                                    <div class="small text-muted">
                                        <span class="me-3">
                                            <i class="fas fa-user me-1"></i><?php echo $rdv['age']; ?> ans
                                        </span>
                                        <span class="me-3">
                                            <i class="fas fa-phone me-1"></i><?php echo $rdv['telephone']; ?>
                                        </span>
                                        <?php if ($rdv['groupe_sanguin']): ?>
                                        <span>
                                            <i class="fas fa-tint me-1"></i><?php echo $rdv['groupe_sanguin']; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($rdv['motif']): ?>
                                    <p class="small mb-0 mt-2">
                                        <i class="fas fa-comment-medical me-1"></i>
                                        <?php echo substr($rdv['motif'], 0, 100); ?>
                                        <?php if (strlen($rdv['motif']) > 100): ?>...<?php endif; ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group">
                                    <a href="consultations.php?action=add&patient_id=<?php echo $rdv['patient_id']; ?>&rdv_id=<?php echo $rdv['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-stethoscope"></i>
                                    </a>
                                    <a href="rendezvous.php?action=edit&id=<?php echo $rdv['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Prochains RDV -->
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Prochains rendez-vous
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($next_rdv)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-plus fa-2x text-muted mb-3"></i>
                    <p class="text-muted small">Aucun rendez-vous à venir</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($next_rdv as $rdv): ?>
                    <div class="list-group-item border-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?php echo $rdv['prenom'] . ' ' . $rdv['nom']; ?></h6>
                                <div class="small">
                                    <span class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('d/m', strtotime($rdv['date_rdv'])); ?>
                                    </span>
                                    <span class="ms-2 fw-semibold">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('H:i', strtotime($rdv['date_rdv'])); ?>
                                    </span>
                                </div>
                                <?php if ($rdv['motif']): ?>
                                <small class="text-muted d-block mt-1">
                                    <?php echo substr($rdv['motif'], 0, 50); ?>
                                    <?php if (strlen($rdv['motif']) > 50): ?>...<?php endif; ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <a href="rendezvous.php?action=edit&id=<?php echo $rdv['id']; ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="card-footer bg-white border-top">
                    <a href="rendezvous.php" class="btn btn-sm btn-outline-primary w-100">
                        <i class="fas fa-calendar-alt me-1"></i>Voir l'agenda complet
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Consultations récentes et patients urgents -->
<div class="row">
    <!-- Consultations récentes -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Consultations récentes
                </h6>
                <a href="consultations.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-list me-1"></i>Toutes
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Patient</th>
                                <th>Date</th>
                                <th>Diagnostic</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_consultations as $consult): 
                                $age = calculateAge($consult['date_naissance']);
                                $statusColors = [
                                    'planifie' => 'warning',
                                    'en_cours' => 'info',
                                    'termine' => 'success',
                                    'annule' => 'danger',
                                    'reporte' => 'secondary'
                                ];
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-3">
                                            <?php echo strtoupper(substr($consult['patient_prenom'], 0, 1) . substr($consult['patient_nom'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?php echo $consult['patient_prenom'] . ' ' . $consult['patient_nom']; ?></div>
                                            <small class="text-muted"><?php echo $consult['code_patient']; ?> • <?php echo $age; ?> ans</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo date('d/m H:i', strtotime($consult['date_consultation'])); ?></div>
                                    <small class="badge bg-<?php echo $statusColors[$consult['statut']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($consult['statut']); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($consult['diagnostic']): ?>
                                    <span class="small" title="<?php echo htmlspecialchars($consult['diagnostic']); ?>">
                                        <?php echo substr($consult['diagnostic'], 0, 50); ?>
                                        <?php if (strlen($consult['diagnostic']) > 50): ?>...<?php endif; ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted small">Non spécifié</span>
                                    <?php endif; ?>
                                    <div class="mt-1">
                                        <?php if ($consult['prescriptions_count'] > 0): ?>
                                        <span class="badge bg-info-light text-info me-1">
                                            <i class="fas fa-prescription me-1"></i><?php echo $consult['prescriptions_count']; ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($consult['documents_count'] > 0): ?>
                                        <span class="badge bg-warning-light text-warning">
                                            <i class="fas fa-file me-1"></i><?php echo $consult['documents_count']; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="consultations.php?action=view&id=<?php echo $consult['id']; ?>" 
                                           class="btn btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="prescriptions.php?action=add&consultation_id=<?php echo $consult['id']; ?>" 
                                           class="btn btn-outline-success">
                                            <i class="fas fa-prescription"></i>
                                        </a>
                                        <?php if ($consult['statut'] == 'termine'): ?>
                                        <a href="documents.php?action=add&consultation_id=<?php echo $consult['id']; ?>" 
                                           class="btn btn-outline-info">
                                            <i class="fas fa-file-medical"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-top">
                    <a href="consultations.php?action=add" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>Nouvelle consultation
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Patients avec suivi urgent -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2 text-danger"></i>
                    Patients nécessitant attention
                </h6>
                <span class="badge bg-danger"><?php echo count($patients_urgents); ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($patients_urgents)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
                    <p class="text-muted small">Aucun patient nécessitant une attention urgente</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($patients_urgents as $patient): ?>
                    <div class="list-group-item border-0">
                        <div class="d-flex align-items-start">
                            <div class="me-3">
                                <div class="avatar bg-danger text-white">
                                    <?php echo strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1)); ?>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo $patient['prenom'] . ' ' . $patient['nom']; ?></h6>
                                <div class="small text-muted">
                                    <div class="mb-1">
                                        <i class="fas fa-user me-1"></i><?php echo $patient['age']; ?> ans
                                        <span class="ms-2">
                                            <i class="fas fa-phone me-1"></i><?php echo $patient['telephone']; ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="badge bg-danger me-1">
                                            <?php echo $patient['urgent_count']; ?> urgence(s) cette semaine
                                        </span>
                                        <?php if ($patient['last_consultation']): ?>
                                        <small class="text-muted">
                                            Dernière consultation: <?php echo date('d/m', strtotime($patient['last_consultation'])); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <a href="consultations.php?action=add&patient_id=<?php echo $patient['id']; ?>" 
                                   class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-stethoscope"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="card-footer bg-white border-top">
                    <a href="patients.php?urgence=1" class="btn btn-sm btn-outline-danger w-100">
                        <i class="fas fa-list me-1"></i>Voir tous les patients urgents
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="consultations.php?action=add" class="card action-card text-center p-4 text-decoration-none">
                            <div class="icon-wrapper mb-3 bg-primary-light">
                                <i class="fas fa-plus-circle fa-2x text-primary"></i>
                            </div>
                            <h6>Nouvelle consultation</h6>
                            <small class="text-muted">Créer une consultation</small>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="prescriptions.php?action=add" class="card action-card text-center p-4 text-decoration-none">
                            <div class="icon-wrapper mb-3 bg-success-light">
                                <i class="fas fa-prescription-bottle-alt fa-2x text-success"></i>
                            </div>
                            <h6>Nouvelle prescription</h6>
                            <small class="text-muted">Rédiger une ordonnance</small>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="patients.php" class="card action-card text-center p-4 text-decoration-none">
                            <div class="icon-wrapper mb-3 bg-warning-light">
                                <i class="fas fa-search fa-2x text-warning"></i>
                            </div>
                            <h6>Rechercher patient</h6>
                            <small class="text-muted">Gérer les dossiers</small>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="documents.php?action=add" class="card action-card text-center p-4 text-decoration-none">
                            <div class="icon-wrapper mb-3 bg-info-light">
                                <i class="fas fa-file-medical fa-2x text-info"></i>
                            </div>
                            <h6>Documents médicaux</h6>
                            <small class="text-muted">Gérer les documents</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// Fonction pour rafraîchir le dashboard
function refreshDashboard() {
    const btn = event.target;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Actualisation...';
    btn.disabled = true;
    
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// Fonction pour imprimer l'emploi du temps
function printSchedule() {
    window.open('rendezvous.php?print=1', '_blank');
}

// Afficher une notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    const container = document.getElementById('toastContainer') || createToastContainer();
    container.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}

// Initialiser les tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
    
    // Mettre à jour l'heure toutes les minutes
    updateCurrentTime();
    setInterval(updateCurrentTime, 60000);
});

function updateCurrentTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    document.querySelectorAll('.current-time').forEach(el => {
        el.textContent = timeString;
    });
}
</script>

<style>
.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #4361ee;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

.icon-wrapper {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.action-card {
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.action-card:hover {
    border-color: #4361ee;
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(67, 97, 238, 0.1);
}

.bg-primary-light { background-color: rgba(67, 97, 238, 0.1); }
.bg-success-light { background-color: rgba(16, 185, 129, 0.1); }
.bg-warning-light { background-color: rgba(245, 158, 11, 0.1); }
.bg-info-light { background-color: rgba(6, 182, 212, 0.1); }
.bg-danger-light { background-color: rgba(239, 68, 68, 0.1); }

/* Timeline styles */
.timeline-container {
    padding: 20px;
}

.timeline-item {
    display: flex;
    margin-bottom: 20px;
    padding: 15px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    background: white;
    transition: all 0.3s ease;
}

.timeline-item.current {
    border-color: #4361ee;
    background-color: rgba(67, 97, 238, 0.05);
    box-shadow: 0 0 0 1px #4361ee;
}

.timeline-item.past {
    opacity: 0.7;
    background-color: #f9fafb;
}

.timeline-item.upcoming:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.timeline-time {
    min-width: 80px;
    padding-right: 20px;
    text-align: center;
    font-weight: 600;
    color: #374151;
    border-right: 2px solid #e5e7eb;
}

.timeline-item.current .timeline-time {
    color: #4361ee;
    border-right-color: #4361ee;
}

.timeline-content {
    flex: 1;
    padding-left: 20px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .timeline-item {
        flex-direction: column;
    }
    
    .timeline-time {
        border-right: none;
        border-bottom: 2px solid #e5e7eb;
        padding-right: 0;
        padding-bottom: 10px;
        margin-bottom: 10px;
        text-align: left;
    }
    
    .timeline-content {
        padding-left: 0;
    }
    
    .display-4 {
        font-size: 2rem;
    }
}
</style>