<?php
// admin/rapports.php
require_once '../config/database.php';
checkRole('admin');


$title = 'Rapports et Statistiques';
require_once '../includes/header.php';

// Paramètres de date par défaut
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$type_rapport = $_GET['type'] ?? 'general';


// Fonction pour formater les dates
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date) || $date == '0000-00-00') {
        return 'N/A';
    }
    return date($format, strtotime($date));
}

// Fonctions de statistiques
function getGeneralStats($pdo, $date_debut, $date_fin) {
    $stats = [];
    
    // Patients
    $stmt = $pdo->query("SELECT COUNT(*) FROM patients");
    $stats['patients']['total'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE date_enregistrement BETWEEN ? AND ?");
    $stmt->execute([$date_debut, $date_fin]);
    $stats['patients']['nouveaux'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM patients WHERE statut = 'actif'");
    $stats['patients']['actifs'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM patients WHERE sexe = 'M'");
    $stats['patients']['par_sexe']['M'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM patients WHERE sexe = 'F'");
    $stats['patients']['par_sexe']['F'] = $stmt->fetchColumn();
    
    // Consultations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM consultations WHERE date_consultation BETWEEN ? AND ?");
    $stmt->execute([$date_debut, $date_fin]);
    $stats['consultations']['total'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT type_consultation, COUNT(*) as count FROM consultations WHERE date_consultation BETWEEN ? AND ? GROUP BY type_consultation");
    $stmt->execute([$date_debut, $date_fin]);
    $consultations_par_type = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $consultations_par_type[$row['type_consultation']] = $row['count'];
    }
    $stats['consultations']['par_type'] = $consultations_par_type;
    
    $stmt = $pdo->prepare("SELECT statut, COUNT(*) as count FROM consultations WHERE date_consultation BETWEEN ? AND ? GROUP BY statut");
    $stmt->execute([$date_debut, $date_fin]);
    $consultations_par_statut = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $consultations_par_statut[$row['statut']] = $row['count'];
    }
    $stats['consultations']['par_statut'] = $consultations_par_statut;
    
    // Rendez-vous
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE date_rdv BETWEEN ? AND ?");
    $stmt->execute([$date_debut, $date_fin]);
    $stats['rendezvous']['total'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT statut, COUNT(*) as count FROM rendez_vous WHERE date_rdv BETWEEN ? AND ? GROUP BY statut");
    $stmt->execute([$date_debut, $date_fin]);
    $rdv_par_statut = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rdv_par_statut[$row['statut']] = $row['count'];
    }
    $stats['rendezvous']['par_statut'] = $rdv_par_statut;
    
    // Médicaments
    $stmt = $pdo->query("SELECT COUNT(*) FROM medicaments");
    $stats['medicaments']['total'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM medicaments WHERE stock_actuel = 0");
    $stats['medicaments']['en_rupture'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM medicaments WHERE statut = 'actif' AND stock_actuel <= 10 AND stock_actuel > 0");
    $stats['medicaments']['stock_faible'] = $stmt->fetchColumn();
    
    return $stats;
}

function getDoctorsStats($pdo, $date_debut, $date_fin) {
    $sql = "
        SELECT 
            u.id,
            CONCAT(u.prenom, ' ', u.nom) as docteur,
            u.specialite,
            COUNT(c.id) as consultations,
            COUNT(DISTINCT c.patient_id) as patients_uniques,
            AVG(CASE WHEN c.statut = 'termine' THEN 1 ELSE 0 END) * 100 as taux_completion,
            MIN(c.date_consultation) as premiere_consultation,
            MAX(c.date_consultation) as derniere_consultation
        FROM utilisateurs u
        LEFT JOIN consultations c ON u.id = c.docteur_id 
            AND c.date_consultation BETWEEN ? AND ?
        WHERE u.role = 'docteur' AND u.statut = 'actif'
        GROUP BY u.id
        ORDER BY consultations DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date_debut, $date_fin]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFinancialStats($pdo, $date_debut, $date_fin) {
    $stats = [];
    
    try {
        // Statistiques financières - vérifier si la table paiements existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'paiements'");
        $paiements_exists = $stmt->fetch();
        
        if ($paiements_exists) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM paiements WHERE date_paiement BETWEEN ? AND ?");
            $stmt->execute([$date_debut, $date_fin]);
            $stats['paiements']['total'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(montant), 0) as total_revenus 
                FROM paiements 
                WHERE date_paiement BETWEEN ? AND ? 
                AND statut = 'complete'
            ");
            $stmt->execute([$date_debut, $date_fin]);
            $stats['revenus']['total'] = $stmt->fetchColumn();
        } else {
            // Table paiements n'existe pas, utiliser des estimations basées sur les prescriptions
            $stats['paiements']['total'] = 0;
            $stats['revenus']['total'] = 0;
        }
        
        // Prescriptions
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE date_prescription BETWEEN ? AND ?");
        $stmt->execute([$date_debut, $date_fin]);
        $stats['prescriptions']['total'] = $stmt->fetchColumn();
        
        // Valeur du stock des médicaments
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(stock_actuel * prix_unitaire), 0) as valeur_stock 
            FROM medicaments 
            WHERE statut = 'actif'
        ");
        $stats['valeur_stock'] = $stmt->fetchColumn();
        
        // Médicaments en rupture
        $stmt = $pdo->query("SELECT COUNT(*) FROM medicaments WHERE stock_actuel = 0");
        $stats['medicaments_rupture'] = $stmt->fetchColumn();
        
    } catch (Exception $e) {
        // En cas d'erreur, retourner des statistiques vides
        error_log("Erreur getFinancialStats: " . $e->getMessage());
        $stats = [
            'paiements' => ['total' => 0],
            'revenus' => ['total' => 0],
            'prescriptions' => ['total' => 0],
            'valeur_stock' => 0,
            'medicaments_rupture' => 0
        ];
    }
    
    return $stats;
}

function getMonthlyActivity($pdo, $date_debut, $date_fin) {
    $sql = "
        SELECT 
            DATE_FORMAT(date_consultation, '%Y-%m') as mois,
            COUNT(*) as consultations
        FROM consultations 
        WHERE date_consultation BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(date_consultation, '%Y-%m')
        ORDER BY mois DESC
        LIMIT 6
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date_debut, $date_fin]);
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les rendez-vous
    $sql_rdv = "
        SELECT 
            DATE_FORMAT(date_rdv, '%Y-%m') as mois,
            COUNT(*) as rendezvous
        FROM rendez_vous 
        WHERE date_rdv BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(date_rdv, '%Y-%m')
        ORDER BY mois DESC
        LIMIT 6
    ";
    
    $stmt = $pdo->prepare($sql_rdv);
    $stmt->execute([$date_debut, $date_fin]);
    $rendezvous = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les nouveaux patients
    $sql_patients = "
        SELECT 
            DATE_FORMAT(date_enregistrement, '%Y-%m') as mois,
            COUNT(*) as nouveaux_patients
        FROM patients 
        WHERE date_enregistrement BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(date_enregistrement, '%Y-%m')
        ORDER BY mois DESC
        LIMIT 6
    ";
    
    $stmt = $pdo->prepare($sql_patients);
    $stmt->execute([$date_debut, $date_fin]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fusionner les données
    $months = [];
    foreach ($consultations as $cons) {
        $month = $cons['mois'];
        $months[$month] = [
            'mois' => $month,
            'consultations' => $cons['consultations'],
            'rendezvous' => 0,
            'nouveaux_patients' => 0
        ];
    }
    
    foreach ($rendezvous as $rdv) {
        $month = $rdv['mois'];
        if (!isset($months[$month])) {
            $months[$month] = [
                'mois' => $month,
                'consultations' => 0,
                'rendezvous' => 0,
                'nouveaux_patients' => 0
            ];
        }
        $months[$month]['rendezvous'] = $rdv['rendezvous'];
    }
    
    foreach ($patients as $patient) {
        $month = $patient['mois'];
        if (!isset($months[$month])) {
            $months[$month] = [
                'mois' => $month,
                'consultations' => 0,
                'rendezvous' => 0,
                'nouveaux_patients' => 0
            ];
        }
        $months[$month]['nouveaux_patients'] = $patient['nouveaux_patients'];
    }
    
    // Trier par mois
    ksort($months);
    return array_values($months);
}

// Vérifier et récupérer les statistiques
try {
    $general_stats = getGeneralStats($pdo, $date_debut, $date_fin);
    $doctors_stats = getDoctorsStats($pdo, $date_debut, $date_fin);
    $financial_stats = getFinancialStats($pdo, $date_debut, $date_fin);
    $monthly_stats = getMonthlyActivity($pdo, $date_debut, $date_fin);
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erreur lors de la récupération des statistiques: ' . $e->getMessage() . '</div>';
    require_once '../includes/footer.php';
    exit();
}
?>

<!-- Content Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-chart-bar me-2"></i>Rapports et Statistiques
        </h1>
        <p class="text-muted mb-0">Analyse des données du système médical</p>
    </div>
    <div class="btn-toolbar">
        <button class="btn btn-primary me-2" onclick="printReport()">
            <i class="fas fa-print me-1"></i>Imprimer
        </button>
        <div class="dropdown">
            <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="exportReport('pdf')">
                    <i class="fas fa-file-pdf me-2"></i>PDF
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="exportReport('excel')">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="exportReport('csv')">
                    <i class="fas fa-file-csv me-2"></i>CSV
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Période du</label>
                <input type="date" class="form-control" name="date_debut" 
                       value="<?php echo htmlspecialchars($date_debut); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">au</label>
                <input type="date" class="form-control" name="date_fin" 
                       value="<?php echo htmlspecialchars($date_fin); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Type de rapport</label>
                <select class="form-select" name="type" onchange="this.form.submit()">
                    <option value="general" <?php echo $type_rapport === 'general' ? 'selected' : ''; ?>>Général</option>
                    <option value="doctors" <?php echo $type_rapport === 'doctors' ? 'selected' : ''; ?>>Médecins</option>
                    <option value="financial" <?php echo $type_rapport === 'financial' ? 'selected' : ''; ?>>Financier</option>
                    <option value="patients" <?php echo $type_rapport === 'patients' ? 'selected' : ''; ?>>Patients</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i>Générer
                </button>
            </div>
        </form>
        <div class="row mt-3">
            <div class="col-12">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary" onclick="updatePeriod(7)">7 derniers jours</button>
                    <button type="button" class="btn btn-outline-primary" onclick="updatePeriod(30)">30 derniers jours</button>
                    <button type="button" class="btn btn-outline-primary" onclick="updatePeriod(90)">3 derniers mois</button>
                    <button type="button" class="btn btn-outline-primary" onclick="updatePeriod(365)">Année en cours</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques principales -->
<?php if ($type_rapport === 'general'): ?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start border-primary border-4 shadow-sm stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Patients</div>
                        <div class="h3 mb-0"><?php echo htmlspecialchars($general_stats['patients']['total']); ?></div>
                        <div class="small text-success">
                            <i class="fas fa-arrow-up me-1"></i>
                            +<?php echo htmlspecialchars($general_stats['patients']['nouveaux']); ?> nouveaux
                        </div>
                    </div>
                    <div class="rounded-circle bg-primary-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-user-injured text-primary fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-success border-4 shadow-sm stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Consultations</div>
                        <div class="h3 mb-0"><?php echo htmlspecialchars($general_stats['consultations']['total']); ?></div>
                        <div class="small">
                            <?php 
                            $terminees = $general_stats['consultations']['par_statut']['termine'] ?? 0;
                            $pourcentage = $general_stats['consultations']['total'] > 0 ? 
                                round(($terminees / $general_stats['consultations']['total']) * 100) : 0;
                            ?>
                            <span class="text-success"><?php echo $pourcentage; ?>% terminées</span>
                        </div>
                    </div>
                    <div class="rounded-circle bg-success-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-stethoscope text-success fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-warning border-4 shadow-sm stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Rendez-vous</div>
                        <div class="h3 mb-0"><?php echo htmlspecialchars($general_stats['rendezvous']['total']); ?></div>
                        <div class="small">
                            <?php 
                            $confirmes = $general_stats['rendezvous']['par_statut']['confirme'] ?? 0;
                            $taux_confirmation = $general_stats['rendezvous']['total'] > 0 ? 
                                round(($confirmes / $general_stats['rendezvous']['total']) * 100) : 0;
                            ?>
                            <span class="text-warning"><?php echo $taux_confirmation; ?>% confirmés</span>
                        </div>
                    </div>
                    <div class="rounded-circle bg-warning-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-calendar-check text-warning fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-info border-4 shadow-sm stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Médicaments</div>
                        <div class="h3 mb-0"><?php echo htmlspecialchars($general_stats['medicaments']['total']); ?></div>
                        <div class="small text-danger">
                            <?php echo htmlspecialchars($general_stats['medicaments']['en_rupture']); ?> en rupture
                        </div>
                    </div>
                    <div class="rounded-circle bg-info-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-pills text-info fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques et tableaux -->
<div class="row">
    <!-- Répartition par sexe -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-venus-mars me-2"></i>
                    Répartition par sexe
                </h6>
            </div>
            <div class="card-body">
                <div class="text-center">
                    <div class="d-flex justify-content-center align-items-center mb-3">
                        <div class="me-4">
                            <div class="fw-bold text-primary"><?php echo htmlspecialchars($general_stats['patients']['par_sexe']['M']); ?></div>
                            <div class="small text-muted">Hommes</div>
                        </div>
                        <div class="position-relative" style="width: 100px; height: 100px;">
                            <canvas id="genderChart"></canvas>
                        </div>
                        <div class="ms-4">
                            <div class="fw-bold text-danger"><?php echo htmlspecialchars($general_stats['patients']['par_sexe']['F']); ?></div>
                            <div class="small text-muted">Femmes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Types de consultations -->
    <div class="col-md-8 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Types de consultations
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="position-relative" style="height: 200px;">
                            <canvas id="consultationTypeChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th class="text-end">Nombre</th>
                                        <th class="text-end">Pourcentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_consultations = $general_stats['consultations']['total'];
                                    if (!empty($general_stats['consultations']['par_type'])) {
                                        foreach ($general_stats['consultations']['par_type'] as $type => $count): 
                                            $percentage = $total_consultations > 0 ? round(($count / $total_consultations) * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(ucfirst($type)); ?></td>
                                        <td class="text-end"><?php echo htmlspecialchars($count); ?></td>
                                        <td class="text-end"><?php echo $percentage; ?>%</td>
                                    </tr>
                                    <?php endforeach;
                                    } else { ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Aucune donnée disponible</td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Activité mensuelle -->
    <div class="col-12 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Activité mensuelle
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 300px;">
                    <canvas id="monthlyActivityChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($type_rapport === 'doctors'): ?>
<!-- Rapport des médecins -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="fas fa-user-md me-2"></i>
            Performance des médecins
        </h6>
        <span class="badge bg-primary"><?php echo count($doctors_stats); ?> médecin(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($doctors_stats)): ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Médecin</th>
                        <th>Spécialité</th>
                        <th>Consultations</th>
                        <th>Patients uniques</th>
                        <th>Taux de complétion</th>
                        <th>Période d'activité</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doctors_stats as $doctor): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($doctor['docteur']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($doctor['specialite']); ?></td>
                        <td>
                            <span class="badge bg-info"><?php echo htmlspecialchars($doctor['consultations']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($doctor['patients_uniques']); ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                    <div class="progress-bar bg-success" 
                                         style="width: <?php echo min(100, $doctor['taux_completion']); ?>%"></div>
                                </div>
                                <span class="small"><?php echo round($doctor['taux_completion'], 1); ?>%</span>
                            </div>
                        </td>
                        <td>
                            <?php if ($doctor['premiere_consultation']): ?>
                            <div class="small">
                                <div>Début: <?php echo formatDate($doctor['premiere_consultation'], 'd/m/Y'); ?></div>
                                <div>Fin: <?php echo formatDate($doctor['derniere_consultation'], 'd/m/Y'); ?></div>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">Aucune consultation</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $performance = 'Moyenne';
                            $color = 'warning';
                            
                            if ($doctor['consultations'] >= 50) {
                                $performance = 'Excellente';
                                $color = 'success';
                            } elseif ($doctor['consultations'] >= 20) {
                                $performance = 'Bonne';
                                $color = 'info';
                            } elseif ($doctor['consultations'] === 0) {
                                $performance = 'Aucune';
                                $color = 'secondary';
                            }
                            ?>
                            <span class="badge bg-<?php echo $color; ?>"><?php echo $performance; ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
            <h6 class="text-muted">Aucun médecin trouvé</h6>
            <p class="text-muted small">Aucun médecin n'a d'activité dans la période sélectionnée</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($type_rapport === 'financial'): ?>
<!-- Rapport financier -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-file-invoice-dollar me-2"></i>
                    Synthèse financière
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <td class="fw-semibold">Période analysée</td>
                                <td><?php echo formatDate($date_debut, 'd/m/Y'); ?> au <?php echo formatDate($date_fin, 'd/m/Y'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Nombre de prescriptions</td>
                                <td><?php echo htmlspecialchars($financial_stats['prescriptions']['total']); ?></td>
                            </tr>
                            <?php if ($financial_stats['paiements']['total'] > 0): ?>
                            <tr>
                                <td class="fw-semibold">Paiements enregistrés</td>
                                <td><?php echo htmlspecialchars($financial_stats['paiements']['total']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Revenus totaux</td>
                                <td><?php echo number_format($financial_stats['revenus']['total'], 2); ?> €</td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td class="fw-semibold">Valeur du stock</td>
                                <td><?php echo number_format($financial_stats['valeur_stock'], 2); ?> €</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Médicaments en rupture</td>
                                <td>
                                    <?php echo htmlspecialchars($financial_stats['medicaments_rupture']); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Aperçu financier
                </h6>
            </div>
            <div class="card-body">
                <?php if ($financial_stats['revenus']['total'] > 0): ?>
                <div style="height: 250px;">
                    <canvas id="financialChart"></canvas>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Module financier en développement</p>
                    <p class="small text-muted">Les données financières complètes seront disponibles après la mise en place du module de facturation.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($type_rapport === 'patients'): ?>
<!-- Rapport patients -->
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h6 class="mb-0">
            <i class="fas fa-users me-2"></i>
            Statistiques patients
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Démographie</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <td>Patients total</td>
                                <td class="text-end"><?php echo htmlspecialchars($general_stats['patients']['total']); ?></td>
                            </tr>
                            <tr>
                                <td>Patients actifs</td>
                                <td class="text-end"><?php echo htmlspecialchars($general_stats['patients']['actifs']); ?></td>
                            </tr>
                            <tr>
                                <td>Nouveaux patients (période)</td>
                                <td class="text-end"><?php echo htmlspecialchars($general_stats['patients']['nouveaux']); ?></td>
                            </tr>
                            <tr>
                                <td>Hommes</td>
                                <td class="text-end"><?php echo htmlspecialchars($general_stats['patients']['par_sexe']['M']); ?></td>
                            </tr>
                            <tr>
                                <td>Femmes</td>
                                <td class="text-end"><?php echo htmlspecialchars($general_stats['patients']['par_sexe']['F']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <h6>Distribution par âge</h6>
                <?php 
                try {
                    $stmt = $pdo->query("
                        SELECT 
                            CASE 
                                WHEN TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()) < 18 THEN '0-17'
                                WHEN TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
                                WHEN TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()) BETWEEN 31 AND 50 THEN '31-50'
                                WHEN TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()) BETWEEN 51 AND 65 THEN '51-65'
                                ELSE '65+'
                            END as tranche_age,
                            COUNT(*) as nombre
                        FROM patients 
                        WHERE date_naissance IS NOT NULL
                        GROUP BY tranche_age
                        ORDER BY FIELD(tranche_age, '0-17', '18-30', '31-50', '51-65', '65+')
                    ");
                    $age_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($age_stats)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tranche d'âge</th>
                                    <th class="text-end">Nombre</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($age_stats as $stat): 
                                    $percentage = $general_stats['patients']['total'] > 0 ? 
                                        round(($stat['nombre'] / $general_stats['patients']['total']) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($stat['tranche_age']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($stat['nombre']); ?></td>
                                    <td class="text-end"><?php echo $percentage; ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted small">Données d'âge non disponibles</p>
                    <?php endif;
                } catch (Exception $e){ ?>
                <p class="text-muted small">Impossible de calculer les tranches d'âge</p>
                <?php endtry; }?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Rapport détaillé -->
<div class="card shadow-sm mt-4">
    <div class="card-header bg-white">
        <h6 class="mb-0">
            <i class="fas fa-file-alt me-2"></i>
            Rapport détaillé
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Synthèse</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="fas fa-calendar-check text-primary me-2"></i>
                        Période: <?php echo formatDate($date_debut, 'd/m/Y'); ?> - <?php echo formatDate($date_fin, 'd/m/Y'); ?>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-users text-success me-2"></i>
                        Patients: <?php echo $general_stats['patients']['total']; ?> total, 
                        <?php echo $general_stats['patients']['nouveaux']; ?> nouveaux
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-stethoscope text-warning me-2"></i>
                        Consultations: <?php echo $general_stats['consultations']['total']; ?> 
                        (<?php echo $general_stats['patients']['total'] > 0 ? round(($general_stats['consultations']['total'] / $general_stats['patients']['total']) * 100) : 0; ?>% des patients)
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-calendar-alt text-info me-2"></i>
                        Rendez-vous: <?php echo $general_stats['rendezvous']['total']; ?> 
                        (<?php echo $general_stats['rendezvous']['total'] > 0 ? round((($general_stats['rendezvous']['par_statut']['confirme'] ?? 0) / $general_stats['rendezvous']['total']) * 100) : 0; ?>% confirmés)
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Recommandations</h6>
                <div class="alert alert-info">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Suggestions d'amélioration:</strong>
                    <ul class="mb-0 mt-2">
                        <?php if ($general_stats['consultations']['total'] < 10): ?>
                        <li>Augmenter le nombre de consultations</li>
                        <?php endif; ?>
                        <?php if ($general_stats['medicaments']['en_rupture'] > 0): ?>
                        <li>Réapprovisionner les médicaments en rupture de stock</li>
                        <?php endif; ?>
                        <?php if (($general_stats['rendezvous']['par_statut']['annule'] ?? 0) > 5): ?>
                        <li>Analyser les taux d'annulation des rendez-vous</li>
                        <?php endif; ?>
                        <li>Optimiser les plannings des médecins</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white text-end">
        <small class="text-muted">
            Rapport généré le <?php echo date('d/m/Y à H:i'); ?> par <?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?>
        </small>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Fonction d'impression améliorée
function printReport() {
    const originalContent = document.body.innerHTML;
    const printContent = document.querySelector('.container-fluid').innerHTML;
    
    const printWindow = window.open('', '_blank', 'width=900,height=600');
    
    const styles = `
        <style>
            @media print {
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 15px; 
                    background: white !important; 
                    color: #000 !important;
                }
                .btn-toolbar, 
                .dropdown, 
                .card-header button, 
                form, 
                .alert-info,
                .stat-card:hover,
                .btn-group { 
                    display: none !important; 
                }
                .card { 
                    border: 1px solid #ddd !important; 
                    box-shadow: none !important; 
                    margin-bottom: 15px; 
                    page-break-inside: avoid;
                }
                .card-header { 
                    background-color: #f5f5f5 !important; 
                    border-bottom: 1px solid #ddd !important;
                    color: #000 !important;
                }
                .table { 
                    border-collapse: collapse; 
                    width: 100%;
                }
                .table th { 
                    background-color: #f8f9fa !important; 
                    color: #000 !important;
                    border: 1px solid #dee2e6;
                    padding: 8px;
                }
                .table td {
                    border: 1px solid #dee2e6;
                    padding: 8px;
                }
                .badge { 
                    background-color: #6c757d !important;
                    color: white !important;
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-size: 12px;
                }
                h1, h2, h3, h4, h5, h6 { 
                    color: #000 !important; 
                }
                .text-primary { color: #000 !important; }
                .text-success { color: #000 !important; }
                .text-warning { color: #000 !important; }
                .text-danger { color: #000 !important; }
                .border-start { border-left: 4px solid !important; }
                .progress-bar { 
                    background-color: #6c757d !important;
                    -webkit-print-color-adjust: exact;
                }
                .progress {
                    background-color: #e9ecef !important;
                    -webkit-print-color-adjust: exact;
                }
                .small { font-size: 11px; }
                .text-muted { color: #666 !important; }
                .alert-info { 
                    background-color: #f8f9fa !important; 
                    border: 1px solid #dee2e6 !important;
                    color: #000 !important;
                }
                ul { padding-left: 20px; }
                .table-responsive { overflow: visible !important; }
                canvas {
                    max-width: 100% !important;
                    height: auto !important;
                }
            }
            
            .report-header { 
                text-align: center; 
                margin-bottom: 30px; 
                padding-bottom: 15px; 
                border-bottom: 2px solid #4361ee;
            }
            .report-title { 
                color: #4361ee; 
                font-size: 24px; 
                margin-bottom: 5px;
                font-weight: bold;
            }
            .report-subtitle { 
                color: #666; 
                font-size: 14px;
            }
            .report-meta { 
                margin: 20px 0; 
                color: #666; 
                font-size: 12px;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 5px;
            }
            .footer-note { 
                margin-top: 30px; 
                padding-top: 10px; 
                border-top: 1px solid #ddd; 
                font-size: 11px; 
                color: #666; 
                text-align: center;
            }
        </style>
    `;
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Rapport Médical - <?php echo formatDate($date_debut, 'd/m/Y'); ?> au <?php echo formatDate($date_fin, 'd/m/Y'); ?></title>
            ${styles}
        </head>
        <body>
            <div class="report-header">
                <div class="report-title">Rapport Médical</div>
                <div class="report-subtitle">Système de Gestion Médicale</div>
            </div>
            
            <div class="report-meta">
                <strong>Période:</strong> <?php echo formatDate($date_debut, 'd/m/Y'); ?> au <?php echo formatDate($date_fin, 'd/m/Y'); ?><br>
                <strong>Type de rapport:</strong> <?php echo $type_rapport === 'general' ? 'Général' : 
                                                    ($type_rapport === 'doctors' ? 'Médecins' : 
                                                    ($type_rapport === 'financial' ? 'Financier' : 'Patients')); ?><br>
                <strong>Généré par:</strong> <?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?><br>
                <strong>Date de génération:</strong> ${new Date().toLocaleDateString('fr-FR')} ${new Date().toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'})}
            </div>
            
            ${printContent}
            
            <div class="footer-note">
                Ce rapport a été généré automatiquement par le système de gestion médicale.<br>
                Les données sont basées sur les enregistrements de la base de données.
            </div>
            
            <script>
                window.onload = function() {
                    setTimeout(() => {
                        window.print();
                        window.close();
                    }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

// Exporter le rapport
function exportReport(format) {
    const params = new URLSearchParams(window.location.search);
    params.append('format', format);
    
    alert('L\'exportation sera disponible après l\'implémentation du backend.');
    // window.open(`ajax/export_report.php?${params.toString()}`, '_blank');
}

// Initialiser les graphiques
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($type_rapport === 'general'): ?>
    // Graphique genre
    const genderCtx = document.getElementById('genderChart');
    if (genderCtx) {
        new Chart(genderCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Hommes', 'Femmes'],
                datasets: [{
                    data: [
                        <?php echo $general_stats['patients']['par_sexe']['M']; ?>,
                        <?php echo $general_stats['patients']['par_sexe']['F']; ?>
                    ],
                    backgroundColor: ['#4361ee', '#f72585'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = <?php echo $general_stats['patients']['total']; ?>;
                                const value = context.raw;
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Graphique types de consultations
    const consultationTypeCtx = document.getElementById('consultationTypeChart');
    if (consultationTypeCtx && <?php echo !empty($general_stats['consultations']['par_type']) ? 'true' : 'false'; ?>) {
        const labels = <?php echo json_encode(array_map('ucfirst', array_keys($general_stats['consultations']['par_type']))); ?>;
        const data = <?php echo json_encode(array_values($general_stats['consultations']['par_type'])); ?>;
        
        new Chart(consultationTypeCtx.getContext('2d'), {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: ['#4361ee', '#4cc9f0', '#f72585', '#7209b7', '#3a0ca3', '#4895ef', '#560bad']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
    }
    
    // Graphique activité mensuelle
    const monthlyCtx = document.getElementById('monthlyActivityChart');
    if (monthlyCtx) {
        const monthlyStats = <?php echo json_encode($monthly_stats); ?>;
        const months = monthlyStats.map(stat => {
            const date = new Date(stat.mois + '-01');
            return date.toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
        });
        const consultations = monthlyStats.map(stat => stat.consultations || 0);
        const rendezvous = monthlyStats.map(stat => stat.rendezvous || 0);
        const nouveauxPatients = monthlyStats.map(stat => stat.nouveaux_patients || 0);
        
        new Chart(monthlyCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Consultations',
                        data: consultations,
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Rendez-vous',
                        data: rendezvous,
                        borderColor: '#4cc9f0',
                        backgroundColor: 'rgba(76, 201, 240, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Nouveaux patients',
                        data: nouveauxPatients,
                        borderColor: '#f72585',
                        backgroundColor: 'rgba(247, 37, 133, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Nombre'
                        },
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    }
    <?php elseif ($type_rapport === 'financial' && $financial_stats['revenus']['total'] > 0): ?>
    // Graphique financier
    const financialCtx = document.getElementById('financialChart');
    if (financialCtx) {
        new Chart(financialCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Prescriptions', 'Revenus', 'Valeur stock'],
                datasets: [{
                    data: [
                        <?php echo $financial_stats['prescriptions']['total']; ?>,
                        <?php echo $financial_stats['revenus']['total']; ?>,
                        <?php echo $financial_stats['valeur_stock']; ?>
                    ],
                    backgroundColor: [
                        'rgba(76, 201, 240, 0.7)',
                        'rgba(67, 97, 238, 0.7)',
                        'rgba(72, 149, 239, 0.7)'
                    ],
                    borderColor: [
                        '#4cc9f0',
                        '#4361ee',
                        '#4895ef'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Montant (€)'
                        },
                        ticks: {
                            callback: function(value) {
                                if (this.data.datasets[0].data[0] > 100) {
                                    return value.toLocaleString('fr-FR', {minimumFractionDigits: 0}) + '€';
                                }
                                return value.toLocaleString('fr-FR', {minimumFractionDigits: 2}) + '€';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw.toLocaleString('fr-FR', {minimumFractionDigits: 2}) + '€';
                            }
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>
});

// Mettre à jour la période
function updatePeriod(days) {
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - days);
    
    document.querySelector('input[name="date_debut"]').value = formatDateForInput(startDate);
    document.querySelector('input[name="date_fin"]').value = formatDateForInput(endDate);
    document.querySelector('form').submit();
}

// Formater la date pour l'input date
function formatDateForInput(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Afficher un toast (notification)
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type} border-0 position-fixed bottom-0 end-0 m-3`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>

<style>
@media print {
    .btn-toolbar,
    .card-header button,
    form,
    .btn-group {
        display: none !important;
    }
    
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
    
    .table th {
        background-color: #f8f9fa !important;
        color: #000 !important;
    }
}

.stat-card {
    transition: transform 0.2s;
    cursor: pointer;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
}

.chart-container {
    position: relative;
    height: 300px;
}

.report-summary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

.bg-primary-light { background-color: rgba(67, 97, 238, 0.1); }
.bg-success-light { background-color: rgba(76, 201, 240, 0.1); }
.bg-warning-light { background-color: rgba(247, 37, 133, 0.1); }
.bg-info-light { background-color: rgba(67, 97, 238, 0.1); }

.text-primary-light { color: rgba(67, 97, 238, 0.8); }
.text-success-light { color: rgba(76, 201, 240, 0.8); }
.text-warning-light { color: rgba(247, 37, 133, 0.8); }
.text-info-light { color: rgba(67, 97, 238, 0.8); }

.toast {
    z-index: 1060;
    min-width: 250px;
}
</style>