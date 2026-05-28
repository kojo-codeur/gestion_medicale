<?php
// admin/dashboard.php
require_once '../config/database.php';
checkRole('admin');

$pdo = Database::getInstance()->getConnection();

$title = 'Dashboard Administrateur';
$admin_id = $_SESSION['user_id'];

require_once '../includes/header.php';

// Statistiques globales
$stats = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM utilisateurs WHERE statut = 'actif') as total_users,
        (SELECT COUNT(*) FROM patients WHERE statut = 'actif') as total_patients,
        (SELECT COUNT(*) FROM consultations WHERE DATE(date_consultation) = CURDATE()) as consultations_today,
        (SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_rdv) = CURDATE() AND statut = 'confirme') as rdv_today,
        (SELECT COUNT(*) FROM prescriptions WHERE statut = 'active') as active_prescriptions,
        (SELECT COUNT(*) FROM utilisateurs WHERE role = 'docteur' AND statut = 'actif') as active_doctors,
        (SELECT COUNT(*) FROM utilisateurs WHERE derniere_connexion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as active_today,
        (SELECT ROUND(AVG(TIMESTAMPDIFF(YEAR, date_naissance, CURDATE())), 1) FROM patients WHERE statut = 'actif') as avg_patient_age
")->fetch();

// Statistiques par spécialité
$specialties_stats = $pdo->query("
    SELECT 
        COALESCE(u.specialite, 'Non spécifié') as specialite,
        COUNT(*) as count,
        COUNT(DISTINCT c.id) as consultations,
        COUNT(DISTINCT r.id) as rendez_vous
    FROM utilisateurs u
    LEFT JOIN consultations c ON u.id = c.docteur_id AND DATE(c.date_consultation) = CURDATE()
    LEFT JOIN rendez_vous r ON u.id = r.docteur_id AND DATE(r.date_rdv) = CURDATE()
    WHERE u.role = 'docteur' AND u.statut = 'actif'
    GROUP BY u.specialite
    ORDER BY COUNT(*) DESC
    LIMIT 5
")->fetchAll();

// Utilisateurs récents
$recent_users = $pdo->query("
    SELECT u.*, 
           TIMESTAMPDIFF(HOUR, u.derniere_connexion, NOW()) as hours_since_login,
           CASE 
               WHEN u.derniere_connexion IS NULL THEN 'Jamais'
               WHEN TIMESTAMPDIFF(HOUR, u.derniere_connexion, NOW()) < 1 THEN 'Moins d\'1h'
               WHEN TIMESTAMPDIFF(HOUR, u.derniere_connexion, NOW()) < 24 THEN CONCAT(TIMESTAMPDIFF(HOUR, u.derniere_connexion, NOW()), 'h')
               ELSE CONCAT(FLOOR(TIMESTAMPDIFF(DAY, u.derniere_connexion, NOW())), 'j')
           END as last_activity
    FROM utilisateurs u 
    WHERE u.statut = 'actif'
    ORDER BY u.date_creation DESC 
    LIMIT 8
")->fetchAll();

// Consultations récentes
$recent_consultations = $pdo->query("
    SELECT c.*, p.nom as patient_nom, p.prenom as patient_prenom, 
           d.nom as docteur_nom, d.prenom as docteur_prenom,
           d.specialite
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    JOIN utilisateurs d ON c.docteur_id = d.id
    WHERE c.date_consultation >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY c.date_consultation DESC 
    LIMIT 8
")->fetchAll();

// Statistiques mensuelles
$monthly_stats = $pdo->query("
    SELECT 
        DATE_FORMAT(date_consultation, '%Y-%m') as month,
        COUNT(*) as consultations,
        COUNT(DISTINCT patient_id) as patients,
        COUNT(DISTINCT docteur_id) as doctors,
        SUM(CASE WHEN urgence = 1 THEN 1 ELSE 0 END) as urgences
    FROM consultations
    WHERE date_consultation >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date_consultation, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
")->fetchAll();

// Récupérer les données pour le graphique d'activité (7 derniers jours)
$activity_data = $pdo->query("
    SELECT 
        DATE(date_consultation) as date,
        COUNT(*) as consultations,
        COUNT(DISTINCT patient_id) as new_patients,
        (SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_rdv) = DATE(consultations.date_consultation) AND statut = 'confirme') as rdv
    FROM consultations
    WHERE date_consultation >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(date_consultation)
    ORDER BY date ASC
")->fetchAll();

// Préparer les données pour le graphique
$activity_labels = [];
$consultations_data = [];
$patients_data = [];
$rdv_data = [];

foreach ($activity_data as $row) {
    $activity_labels[] = date('d/m', strtotime($row['date']));
    $consultations_data[] = $row['consultations'];
    $patients_data[] = $row['new_patients'];
    $rdv_data[] = $row['rdv'];
}

// Alertes système
$system_alerts = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM consultations WHERE urgence = 1 AND DATE(date_consultation) = CURDATE()) as urgent_consultations,
        (SELECT COUNT(*) FROM patients WHERE statut = 'archive' AND DATE(date_modification) = CURDATE()) as archived_today,
        (SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE() AND action LIKE '%error%') as system_errors,
        (SELECT COUNT(*) FROM backup_history WHERE DATE(created_at) = CURDATE()) as backups_today,
        (SELECT COUNT(*) FROM medicaments WHERE stock_actuel <= stock_minimum) as low_stock,
        (SELECT COUNT(*) FROM notifications WHERE lu = 0 AND important = 1) as important_notifications
")->fetch();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard Administrateur
        </h1>
        <p class="text-muted mb-0">
            Vue d'ensemble du système médical - <?php echo date('d/m/Y H:i'); ?>
        </p>
    </div>
    <div class="btn-toolbar">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshDashboard()">
                <i class="fas fa-sync-alt me-1"></i>Actualiser
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Imprimer
            </button>
        </div>
        <a href="rapports.php" class="btn btn-sm btn-primary">
            <i class="fas fa-chart-bar me-1"></i>Générer rapport
        </a>
    </div>
</div>

<!-- Alertes système -->
<?php 
$hasAlerts = $system_alerts['urgent_consultations'] > 0 || 
             $system_alerts['system_errors'] > 0 || 
             $system_alerts['low_stock'] > 0 ||
             $system_alerts['important_notifications'] > 0;
?>
<?php if ($hasAlerts): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-warning d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Alertes système :</strong>
                <?php 
                $alerts = [];
                if ($system_alerts['urgent_consultations'] > 0) {
                    $alerts[] = $system_alerts['urgent_consultations'] . " consultation(s) urgente(s)";
                }
                if ($system_alerts['system_errors'] > 0) {
                    $alerts[] = $system_alerts['system_errors'] . " erreur(s) système";
                }
                if ($system_alerts['low_stock'] > 0) {
                    $alerts[] = $system_alerts['low_stock'] . " médicament(s) en stock faible";
                }
                if ($system_alerts['important_notifications'] > 0) {
                    $alerts[] = $system_alerts['important_notifications'] . " notification(s) importante(s)";
                }
                echo implode(', ', $alerts);
                ?>
            </div>
            <a href="gestion.php" class="btn btn-sm btn-outline-warning">Voir détails</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="row mb-4">
    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
        <div class="card border-start border-primary border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Utilisateurs</div>
                        <div class="h2 mb-0"><?php echo $stats['total_users']; ?></div>
                        <small class="text-success">
                            <i class="fas fa-user-check me-1"></i><?php echo $stats['active_today']; ?> actifs
                        </small>
                    </div>
                    <div class="rounded-circle bg-primary-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-users text-primary fa-lg"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="utilisateurs.php" class="text-decoration-none small">
                        <i class="fas fa-eye me-1"></i>Voir détails
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
        <div class="card border-start border-success border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Patients</div>
                        <div class="h2 mb-0"><?php echo number_format($stats['total_patients']); ?></div>
                        <small class="text-muted">
                            <i class="fas fa-calendar-alt me-1"></i><?php echo $stats['avg_patient_age']; ?> ans
                        </small>
                    </div>
                    <div class="rounded-circle bg-success-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-user-injured text-success fa-lg"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="patients.php" class="text-decoration-none small">
                        <i class="fas fa-chart-pie me-1"></i>Statistiques
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
        <div class="card border-start border-info border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Consultations</div>
                        <div class="h2 mb-0"><?php echo $stats['consultations_today']; ?></div>
                        <small class="<?php echo $system_alerts['urgent_consultations'] > 0 ? 'text-danger' : 'text-muted'; ?>">
                            <i class="fas fa-exclamation-triangle me-1"></i><?php echo $system_alerts['urgent_consultations']; ?> urgentes
                        </small>
                    </div>
                    <div class="rounded-circle bg-info-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-stethoscope text-info fa-lg"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="consultations.php" class="text-decoration-none small">
                        <i class="fas fa-calendar-day me-1"></i>Voir l'agenda
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
        <div class="card border-start border-warning border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Médecins</div>
                        <div class="h2 mb-0"><?php echo $stats['active_doctors']; ?></div>
                        <small class="text-muted">
                            <i class="fas fa-prescription me-1"></i><?php echo $stats['active_prescriptions']; ?> prescriptions
                        </small>
                    </div>
                    <div class="rounded-circle bg-warning-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-user-md text-warning fa-lg"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="utilisateurs.php?role=docteur" class="text-decoration-none small">
                        <i class="fas fa-list me-1"></i>Liste
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
        <div class="card border-start border-danger border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">RDV</div>
                        <div class="h2 mb-0"><?php echo $stats['rdv_today']; ?></div>
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>Aujourd'hui
                        </small>
                    </div>
                    <div class="rounded-circle bg-danger-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-calendar-check text-danger fa-lg"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="rendezvous.php" class="text-decoration-none small">
                        <i class="fas fa-calendar me-1"></i>Voir agenda
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
        <div class="card border-start border-purple border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Stock</div>
                        <?php 
                        $stock_stmt = $pdo->query("SELECT COUNT(*) as low_stock FROM medicaments WHERE stock_actuel <= stock_minimum");
                        $low_stock = $stock_stmt->fetch()['low_stock'];
                        ?>
                        <div class="h2 mb-0 <?php echo $low_stock > 0 ? 'text-danger' : ''; ?>"><?php echo $low_stock; ?></div>
                        <small class="<?php echo $low_stock > 0 ? 'text-danger' : 'text-muted'; ?>">
                            <i class="fas fa-exclamation-circle me-1"></i>Faible stock
                        </small>
                    </div>
                    <div class="rounded-circle bg-purple-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-pills text-purple fa-lg"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="medicaments.php" class="text-decoration-none small">
                        <i class="fas fa-capsules me-1"></i>Gérer
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Activité des 7 derniers jours
                </h6>
                <select class="form-select form-select-sm w-auto" id="activityPeriod" onchange="updateActivityChart(this.value)">
                    <option value="7">7 jours</option>
                    <option value="30">30 jours</option>
                    <option value="90">3 mois</option>
                </select>
            </div>
            <div class="card-body position-relative" style="height: 300px;">
                <canvas id="activityChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Répartition des rôles
                </h6>
            </div>
            <div class="card-body position-relative" style="height: 300px;">
                <canvas id="rolesChart"></canvas>
                <div class="mt-3" id="rolesLegend"></div>
            </div>
        </div>
    </div>
</div>

<!-- Specialties and Monthly Stats -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-star me-2"></i>
                    Top spécialités (Aujourd'hui)
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Spécialité</th>
                                <th>Médecins</th>
                                <th>Consultations</th>
                                <th>RDV</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($specialties_stats as $specialty): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="specialty-icon me-2">
                                            <i class="fas fa-user-md"></i>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?php echo $specialty['specialite']; ?></div>
                                            <small class="text-muted"><?php echo $specialty['count']; ?> médecins</small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $specialty['count']; ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $specialty['consultations']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $specialty['rendez_vous']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-top">
                    <a href="specialites.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-list me-1"></i>Toutes les spécialités
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques mensuelles (6 derniers mois)
                </h6>
            </div>
            <div class="card-body position-relative" style="height: 300px;">
                <canvas id="monthlyChart"></canvas>
                <div class="mt-3 row text-center">
                    <div class="col-4">
                        <small class="text-muted d-block">Consultations totales</small>
                        <?php 
                        $total_consultations = array_sum(array_column($monthly_stats, 'consultations'));
                        ?>
                        <span class="fw-bold"><?php echo $total_consultations; ?></span>
                    </div>
                    <div class="col-4">
                        <small class="text-muted d-block">Patients uniques</small>
                        <?php 
                        $total_patients = array_sum(array_column($monthly_stats, 'patients'));
                        ?>
                        <span class="fw-bold"><?php echo $total_patients; ?></span>
                    </div>
                    <div class="col-4">
                        <small class="text-muted d-block">Urgences</small>
                        <?php 
                        $total_urgences = array_sum(array_column($monthly_stats, 'urgences'));
                        ?>
                        <span class="fw-bold text-danger"><?php echo $total_urgences; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Data Tables Row -->
<div class="row">
    <!-- Utilisateurs récents -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-user-clock me-2"></i>
                    Utilisateurs récents
                </h6>
                <a href="utilisateurs.php?action=add" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus me-1"></i>Ajouter
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Utilisateur</th>
                                <th>Rôle</th>
                                <th>Dernière activité</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): 
                                $roleColor = $user['role'] == 'admin' ? 'danger' : 
                                          ($user['role'] == 'docteur' ? 'primary' : 
                                          ($user['role'] == 'secretaire' ? 'success' : 'warning'));
                                $statusColor = $user['statut'] == 'actif' ? 'success' : 
                                             ($user['statut'] == 'inactif' ? 'secondary' : 'danger');
                                $activityColor = $user['hours_since_login'] <= 24 ? 'success' : 
                                               ($user['hours_since_login'] <= 168 ? 'warning' : 'danger');
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-3">
                                            <?php echo strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?php echo $user['prenom'] . ' ' . $user['nom']; ?></div>
                                            <small class="text-muted"><?php echo $user['specialite'] ?? 'Non spécifié'; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $roleColor; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $activityColor; ?>">
                                        <?php echo $user['last_activity']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $statusColor; ?>">
                                        <?php echo ucfirst($user['statut']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-top">
                    <a href="utilisateurs.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-users me-1"></i>Tous les utilisateurs
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Consultations récentes -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Consultations récentes (7 jours)
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
                                <th>Médecin</th>
                                <th>Date</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_consultations as $consult): 
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
                                        <div class="avatar-sm me-2">
                                            <?php echo strtoupper(substr($consult['patient_prenom'], 0, 1) . substr($consult['patient_nom'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?php echo $consult['patient_prenom'] . ' ' . $consult['patient_nom']; ?></div>
                                            <small class="text-muted"><?php echo $consult['reference']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-2">
                                            <?php echo strtoupper(substr($consult['docteur_prenom'], 0, 1) . substr($consult['docteur_nom'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="small">Dr. <?php echo $consult['docteur_prenom']; ?></div>
                                            <small class="text-muted"><?php echo $consult['specialite']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small"><?php echo date('d/m', strtotime($consult['date_consultation'])); ?></div>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($consult['date_consultation'])); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $statusColors[$consult['statut']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($consult['statut']); ?>
                                    </span>
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
</div>

<!-- Quick Actions -->
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
                    <div class="col-md-3 col-sm-6">
                        <a href="utilisateurs.php?action=add" class="card action-card text-center p-4 text-decoration-none">
                            <div class="icon-wrapper mb-3 bg-primary-light">
                                <i class="fas fa-user-plus fa-2x text-primary"></i>
                            </div>
                            <h6 class="mb-1">Ajouter utilisateur</h6>
                            <small class="text-muted">Créer un nouveau compte</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="gestion.php" class="card action-card text-center p-4 text-decoration-none">
                            <div class="icon-wrapper mb-3 bg-success-light">
                                <i class="fas fa-cogs fa-2x text-success"></i>
                            </div>
                            <h6 class="mb-1">Configuration</h6>
                            <small class="text-muted">Paramètres système</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="sauvegardes.php" class="card action-card text-center p-4 text-decoration-none">
                            <div class="icon-wrapper mb-3 bg-warning-light">
                                <i class="fas fa-database fa-2x text-warning"></i>
                            </div>
                            <h6 class="mb-1">Sauvegarde</h6>
                            <small class="text-muted">Sauvegarder les données</small>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="rapports.php" class="card action-card text-center p-4 text-decoration-none">
                            <div class="icon-wrapper mb-3 bg-info-light">
                                <i class="fas fa-chart-pie fa-2x text-info"></i>
                            </div>
                            <h6 class="mb-1">Rapports</h6>
                            <small class="text-muted">Générer des rapports</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Déclaration des variables pour les graphiques
let activityChart, rolesChart, monthlyChart;

// Initialiser les graphiques
document.addEventListener('DOMContentLoaded', function() {
    // Données pour le graphique d'activité
    const activityData = {
        labels: <?php echo json_encode($activity_labels); ?>,
        datasets: [{
            label: 'Consultations',
            data: <?php echo json_encode($consultations_data); ?>,
            borderColor: '#4361ee',
            backgroundColor: 'rgba(67, 97, 238, 0.1)',
            borderWidth: 2,
            tension: 0.4,
            fill: true
        }, {
            label: 'Nouveaux patients',
            data: <?php echo json_encode($patients_data); ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            borderWidth: 2,
            tension: 0.4,
            fill: true
        }, {
            label: 'RDV',
            data: <?php echo json_encode($rdv_data); ?>,
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            borderWidth: 2,
            tension: 0.4,
            fill: true
        }]
    };

    // Configuration du graphique d'activité
    const activityCtx = document.getElementById('activityChart');
    activityChart = new Chart(activityCtx, {
        type: 'line',
        data: activityData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    },
                    ticks: {
                        stepSize: 5
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    // Données pour le graphique des rôles
    const rolesData = {
        labels: ['Docteurs', 'Secrétaires', 'Assistants', 'Administrateurs'],
        datasets: [{
            data: [
                <?php echo $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'docteur' AND statut = 'actif'")->fetchColumn(); ?>,
                <?php echo $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'secretaire' AND statut = 'actif'")->fetchColumn(); ?>,
                <?php echo $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'assistant' AND statut = 'actif'")->fetchColumn(); ?>,
                <?php echo $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'admin' AND statut = 'actif'")->fetchColumn(); ?>
            ],
            backgroundColor: ['#4361ee', '#10b981', '#f59e0b', '#ef4444'],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    };

    // Configuration du graphique des rôles
    const rolesCtx = document.getElementById('rolesChart');
    rolesChart = new Chart(rolesCtx, {
        type: 'doughnut',
        data: rolesData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            cutout: '65%'
        }
    });

    // Créer la légende pour le graphique des rôles
    createRolesLegend(rolesData);

    // Données pour le graphique mensuel
    const monthlyData = {
        labels: <?php echo json_encode(array_reverse(array_column($monthly_stats, 'month'))); ?>,
        datasets: [{
            label: 'Consultations',
            data: <?php echo json_encode(array_reverse(array_column($monthly_stats, 'consultations'))); ?>,
            backgroundColor: 'rgba(67, 97, 238, 0.7)',
            borderColor: '#4361ee',
            borderWidth: 2
        }, {
            label: 'Patients uniques',
            data: <?php echo json_encode(array_reverse(array_column($monthly_stats, 'patients'))); ?>,
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderColor: '#10b981',
            borderWidth: 2
        }]
    };

    // Configuration du graphique mensuel
    const monthlyCtx = document.getElementById('monthlyChart');
    monthlyChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: monthlyData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Redimensionner les graphiques lors du redimensionnement de la fenêtre
    window.addEventListener('resize', function() {
        if (activityChart) activityChart.resize();
        if (rolesChart) rolesChart.resize();
        if (monthlyChart) monthlyChart.resize();
    });
});

// Fonction pour créer la légende du graphique des rôles
function createRolesLegend(data) {
    const legend = document.getElementById('rolesLegend');
    const colors = data.datasets[0].backgroundColor;
    const labels = data.labels;
    const values = data.datasets[0].data;
    
    let html = '';
    labels.forEach((label, index) => {
        const total = values.reduce((a, b) => a + b, 0);
        const percentage = total > 0 ? Math.round((values[index] / total) * 100) : 0;
        html += `
            <div class="d-flex align-items-center mb-2">
                <span class="legend-color me-2" style="background-color: ${colors[index]}; width: 12px; height: 12px; border-radius: 2px;"></span>
                <span class="small flex-grow-1">${label}</span>
                <span class="fw-semibold">${values[index]} (${percentage}%)</span>
            </div>
        `;
    });
    
    legend.innerHTML = html;
}

// Fonction pour mettre à jour le graphique d'activité
async function updateActivityChart(days) {
    try {
        const response = await fetch(`../api/get_activity_data.php?days=${days}`);
        const data = await response.json();
        
        if (data) {
            activityChart.data = data;
            activityChart.update();
            showToast(`Données mises à jour pour ${days} jours`, 'success');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showToast('Erreur lors du chargement des données', 'danger');
    }
}

// Fonction pour rafraîchir le dashboard
function refreshDashboard() {
    const refreshBtn = document.querySelector('button[onclick="refreshDashboard()"]');
    const originalHtml = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Actualisation...';
    refreshBtn.disabled = true;
    
    setTimeout(() => {
        location.reload();
    }, 500);
}

// Fonction pour afficher les notifications
function showToast(message, type = 'success') {
    // Créer le conteneur de toast s'il n'existe pas
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(container);
    }
    
    // Créer le toast
    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    container.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
    
    // Nettoyer après la fermeture
    toastEl.addEventListener('hidden.bs.toast', () => {
        toastEl.remove();
    });
}
</script>

<style>
/* Styles pour les avatars */
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
    font-size: 0.9rem;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #e9ecef;
    color: #495057;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.8rem;
}

/* Styles pour les icônes de spécialité */
.specialty-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background-color: rgba(67, 97, 238, 0.1);
    color: #4361ee;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Styles pour les cartes d'action */
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
    height: 100%;
}

.action-card:hover {
    border-color: #4361ee;
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(67, 97, 238, 0.1);
    text-decoration: none;
}

/* Couleurs de fond */
.bg-primary-light { background-color: rgba(67, 97, 238, 0.1); }
.bg-success-light { background-color: rgba(16, 185, 129, 0.1); }
.bg-info-light { background-color: rgba(6, 182, 212, 0.1); }
.bg-warning-light { background-color: rgba(245, 158, 11, 0.1); }
.bg-danger-light { background-color: rgba(239, 68, 68, 0.1); }
.bg-purple-light { background-color: rgba(139, 92, 246, 0.1); }
.text-purple { color: #8b5cf6; }

/* Styles pour les graphiques */
.card-body canvas {
    width: 100% !important;
    height: 100% !important;
}

/* Animation pour les cartes */
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1) !important;
}

/* Ajustement des graphiques pour éviter le débordement */
.chart-container {
    position: relative;
    width: 100%;
    height: 300px;
}
</style>