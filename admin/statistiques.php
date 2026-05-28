<?php
// admin/statistiques.php
require_once '../config/database.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$pdo = Database::getInstance()->getConnection();

$title = 'Statistiques médicales';
require_once '../includes/header.php';

$user_id = $_SESSION['user_id'];
$specialite = $_SESSION['specialite'] ?? 'Médecin généraliste';

// Dates pour les filtres
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01');
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-d');
$periode = isset($_GET['periode']) ? $_GET['periode'] : 'mois_courant';

// Récupérer les statistiques
try {
    // 1. Statistiques générales
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_consultations,
            COUNT(DISTINCT patient_id) as patients_uniques,
            AVG(duree) as duree_moyenne,
            SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) as terminees,
            SUM(CASE WHEN urgence = 1 THEN 1 ELSE 0 END) as urgences,
            SUM(CASE WHEN facturee = 1 THEN 1 ELSE 0 END) as facturees
        FROM consultations 
        WHERE docteur_id = ? 
        AND DATE(date_consultation) BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $date_debut, $date_fin]);
    $stats_generales = $stmt->fetch();
    
    // 2. Répartition par type de consultation
    $stmt = $pdo->prepare("
        SELECT 
            type_consultation,
            COUNT(*) as nombre,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM consultations WHERE docteur_id = ? AND DATE(date_consultation) BETWEEN ? AND ?), 1) as pourcentage
        FROM consultations 
        WHERE docteur_id = ? 
        AND DATE(date_consultation) BETWEEN ? AND ?
        GROUP BY type_consultation
        ORDER BY nombre DESC
    ");
    $stmt->execute([$user_id, $date_debut, $date_fin, $user_id, $date_debut, $date_fin]);
    $types_consultations = $stmt->fetchAll();
    
    // 3. Pathologies les plus fréquentes
    $stmt = $pdo->prepare("
        SELECT 
            p.nom as pathologie_nom,
            COUNT(pp.id) as nombre_cas,
            ROUND(COUNT(pp.id) * 100.0 / (SELECT COUNT(*) FROM patient_pathologie pp2 
                                         JOIN consultations c2 ON pp2.patient_id = c2.patient_id
                                         WHERE c2.docteur_id = ? AND DATE(c2.date_consultation) BETWEEN ? AND ?), 1) as pourcentage
        FROM patient_pathologie pp
        JOIN pathologies p ON pp.pathologie_id = p.id
        JOIN consultations c ON pp.patient_id = c.patient_id
        WHERE c.docteur_id = ? 
        AND DATE(c.date_consultation) BETWEEN ? AND ?
        AND pp.statut IN ('active', 'chronique', 'en_suivi')
        GROUP BY p.id
        ORDER BY nombre_cas DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $date_debut, $date_fin, $user_id, $date_debut, $date_fin]);
    $pathologies_frequentes = $stmt->fetchAll();
    
    // 4. Médicaments les plus prescrits
    $stmt = $pdo->prepare("
        SELECT 
            m.nom_commercial,
            COUNT(pd.id) as nombre_prescriptions,
            SUM(pd.quantite) as quantite_totale
        FROM prescription_details pd
        JOIN prescriptions p ON pd.prescription_id = p.id
        JOIN medicaments m ON pd.medicament_id = m.id
        WHERE p.docteur_id = ?
        AND DATE(p.date_prescription) BETWEEN ? AND ?
        GROUP BY m.id
        ORDER BY nombre_prescriptions DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $date_debut, $date_fin]);
    $medicaments_prescrits = $stmt->fetchAll();
    
    // 5. Statistiques mensuelles (pour graphique)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(date_consultation, '%Y-%m') as mois,
            COUNT(*) as consultations,
            COUNT(DISTINCT patient_id) as nouveaux_patients
        FROM consultations 
        WHERE docteur_id = ?
        AND date_consultation >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(date_consultation, '%Y-%m')
        ORDER BY mois
    ");
    $stmt->execute([$user_id]);
    $stats_mensuelles = $stmt->fetchAll();
    
    // 6. Répartition par jour de la semaine
    $stmt = $pdo->prepare("
        SELECT 
            DAYNAME(date_consultation) as jour,
            COUNT(*) as consultations,
            ROUND(AVG(duree), 0) as duree_moyenne
        FROM consultations 
        WHERE docteur_id = ? 
        AND DATE(date_consultation) BETWEEN ? AND ?
        GROUP BY DAYOFWEEK(date_consultation), DAYNAME(date_consultation)
        ORDER BY DAYOFWEEK(date_consultation)
    ");
    $stmt->execute([$user_id, $date_debut, $date_fin]);
    $repartition_jours = $stmt->fetchAll();
    
    // 7. Âge des patients
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) < 18 THEN 'Enfants (<18)'
                WHEN TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) BETWEEN 18 AND 40 THEN 'Jeunes adultes (18-40)'
                WHEN TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) BETWEEN 41 AND 65 THEN 'Adultes (41-65)'
                ELSE 'Seniors (>65)'
            END as tranche_age,
            COUNT(DISTINCT c.patient_id) as nombre_patients
        FROM consultations c
        JOIN patients p ON c.patient_id = p.id
        WHERE c.docteur_id = ?
        AND DATE(c.date_consultation) BETWEEN ? AND ?
        GROUP BY tranche_age
        ORDER BY FIELD(tranche_age, 'Enfants (<18)', 'Jeunes adultes (18-40)', 'Adultes (41-65)', 'Seniors (>65)')
    ");
    $stmt->execute([$user_id, $date_debut, $date_fin]);
    $tranches_age = $stmt->fetchAll();
    
    // 8. Taux d'absentéisme RDV
    $stmt = $pdo->prepare("
        SELECT 
            statut,
            COUNT(*) as nombre,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM rendez_vous WHERE docteur_id = ? AND DATE(date_rdv) BETWEEN ? AND ?), 1) as pourcentage
        FROM rendez_vous 
        WHERE docteur_id = ?
        AND DATE(date_rdv) BETWEEN ? AND ?
        GROUP BY statut
    ");
    $stmt->execute([$user_id, $date_debut, $date_fin, $user_id, $date_debut, $date_fin]);
    $stats_rdv = $stmt->fetchAll();
    
    // Calculer le taux d'absentéisme
    $absent_taux = 0;
    $total_rdv = 0;
    $absents = 0;
    foreach ($stats_rdv as $stat_rdv) {
        $total_rdv += $stat_rdv['nombre'];
        if ($stat_rdv['statut'] == 'absent') {
            $absents = $stat_rdv['nombre'];
        }
    }
    if ($total_rdv > 0) {
        $absent_taux = round(($absents / $total_rdv) * 100, 1);
    }
    
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des statistiques: " . $e->getMessage();
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-chart-bar me-2"></i>Statistiques médicales
        </h1>
        <p class="text-muted mb-0">Analyse de votre activité médicale</p>
    </div>
    <div class="btn-toolbar">
        <button type="button" class="btn btn-outline-primary me-2" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Imprimer
        </button>
        <button type="button" class="btn btn-primary" onclick="exportToExcel()">
            <i class="fas fa-file-excel me-1"></i>Exporter Excel
        </button>
    </div>
</div>

<!-- Filtres de période -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Filtres de période</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Période prédéfinie</label>
                <select class="form-select" name="periode" id="periodeSelect">
                    <option value="aujourdhui" <?php echo $periode == 'aujourdhui' ? 'selected' : ''; ?>>Aujourd'hui</option>
                    <option value="semaine_courante" <?php echo $periode == 'semaine_courante' ? 'selected' : ''; ?>>Semaine courante</option>
                    <option value="mois_courant" <?php echo $periode == 'mois_courant' ? 'selected' : ''; ?>>Mois courant</option>
                    <option value="trimestre" <?php echo $periode == 'trimestre' ? 'selected' : ''; ?>>Trimestre</option>
                    <option value="annee_courante" <?php echo $periode == 'annee_courante' ? 'selected' : ''; ?>>Année courante</option>
                    <option value="personnalise" <?php echo $periode == 'personnalise' ? 'selected' : ''; ?>>Personnalisé</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date de début</label>
                <input type="date" class="form-control" name="date_debut" id="date_debut" 
                       value="<?php echo $date_debut; ?>" <?php echo $periode != 'personnalise' ? 'readonly' : ''; ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date de fin</label>
                <input type="date" class="form-control" name="date_fin" id="date_fin" 
                       value="<?php echo $date_fin; ?>" <?php echo $periode != 'personnalise' ? 'readonly' : ''; ?>>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i>Filtrer
                </button>
            </div>
        </form>
        <div class="mt-3">
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Période : du <?php echo formatDate($date_debut); ?> au <?php echo formatDate($date_fin); ?>
                <?php if ($specialite): ?> | Spécialité : <?php echo $specialite; ?><?php endif; ?>
            </small>
        </div>
    </div>
</div>

<!-- KPI Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Consultations</h6>
                        <h2 class="mb-0"><?php echo $stats_generales['total_consultations'] ?? 0; ?></h2>
                        <small class="text-white-75">
                            <?php echo $stats_generales['terminees'] ?? 0; ?> terminées
                        </small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-stethoscope fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Patients uniques</h6>
                        <h2 class="mb-0"><?php echo $stats_generales['patients_uniques'] ?? 0; ?></h2>
                        <small class="text-white-75">
                            Suivi actif
                        </small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-injured fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Urgences</h6>
                        <h2 class="mb-0"><?php echo $stats_generales['urgences'] ?? 0; ?></h2>
                        <small class="text-white-75">
                            <?php echo $stats_generales['total_consultations'] > 0 ? 
                                round(($stats_generales['urgences'] / $stats_generales['total_consultations']) * 100, 1) : 0; ?>% du total
                        </small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-ambulance fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Durée moyenne</h6>
                        <h2 class="mb-0"><?php echo round($stats_generales['duree_moyenne'] ?? 0); ?>min</h2>
                        <small class="text-white-75">
                            Par consultation
                        </small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques et détails -->
<div class="row">
    <!-- Graphique mensuel -->
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Évolution mensuelle</h6>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleChartType()">
                        <i class="fas fa-exchange-alt"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="250"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Répartition par type -->
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Types de consultations</h6>
            </div>
            <div class="card-body">
                <canvas id="typeChart" height="250"></canvas>
                <div class="mt-3">
                    <table class="table table-sm table-borderless">
                        <?php foreach ($types_consultations as $type): 
                            $type_nom = $type['type_consultation'] ? ucfirst($type['type_consultation']) : 'Non spécifié';
                        ?>
                        <tr>
                            <td><?php echo $type_nom; ?></td>
                            <td class="text-end"><?php echo $type['nombre']; ?></td>
                            <td class="text-end text-muted"><?php echo $type['pourcentage']; ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tableaux détaillés -->
<div class="row">
    <!-- Pathologies fréquentes -->
    <div class="col-lg-6">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-disease me-2"></i>Pathologies les plus fréquentes</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Pathologie</th>
                                <th class="text-end">Cas</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pathologies_frequentes as $pathologie): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pathologie['pathologie_nom']); ?></td>
                                <td class="text-end"><?php echo $pathologie['nombre_cas']; ?></td>
                                <td class="text-end"><?php echo $pathologie['pourcentage']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($pathologies_frequentes)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle me-2"></i>Aucune pathologie enregistrée
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Médicaments prescrits -->
    <div class="col-lg-6">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-pills me-2"></i>Médicaments les plus prescrits</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Médicament</th>
                                <th class="text-end">Prescriptions</th>
                                <th class="text-end">Quantité</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medicaments_prescrits as $medicament): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($medicament['nom_commercial']); ?></td>
                                <td class="text-end"><?php echo $medicament['nombre_prescriptions']; ?></td>
                                <td class="text-end"><?php echo $medicament['quantite_totale']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($medicaments_prescrits)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle me-2"></i>Aucune prescription enregistrée
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Répartition détaillée -->
<div class="row">
    <!-- Répartition par jour -->
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Activité par jour</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Jour</th>
                                <th class="text-end">Consultations</th>
                                <th class="text-end">Durée moyenne</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($repartition_jours as $jour): ?>
                            <tr>
                                <td><?php echo $jour['jour']; ?></td>
                                <td class="text-end"><?php echo $jour['consultations']; ?></td>
                                <td class="text-end"><?php echo $jour['duree_moyenne']; ?> min</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tranches d'âge -->
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-users me-2"></i>Répartition par âge</h6>
            </div>
            <div class="card-body">
                <canvas id="ageChart" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Statistiques RDV -->
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Statistiques RDV</h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="display-6 fw-bold mb-1">
                        <?php echo $absent_taux; ?>%
                    </div>
                    <div class="text-muted small">Taux d'absentéisme</div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <?php foreach ($stats_rdv as $stat): 
                                $statut_nom = ucfirst($stat['statut']);
                                $color = 'secondary';
                                switch ($stat['statut']) {
                                    case 'confirme':
                                        $color = 'success';
                                        break;
                                    case 'annule':
                                        $color = 'warning';
                                        break;
                                    case 'absent':
                                        $color = 'danger';
                                        break;
                                    case 'present':
                                        $color = 'primary';
                                        break;
                                    default:
                                        $color = 'secondary';
                                }
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo $statut_nom; ?>
                                    </span>
                                </td>
                                <td class="text-end"><?php echo $stat['nombre']; ?></td>
                                <td class="text-end text-muted"><?php echo $stat['pourcentage']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rapport détaillé -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Rapport d'activité détaillé</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="generateReport()">
            <i class="fas fa-download me-1"></i>Générer rapport
        </button>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Synthèse</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="fas fa-chart-line text-primary me-2"></i>
                        <strong>Productivité :</strong> 
                        <?php echo $stats_generales['total_consultations'] ?? 0; ?> consultations sur la période
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-user-md text-success me-2"></i>
                        <strong>Patientèle :</strong> 
                        <?php echo $stats_generales['patients_uniques'] ?? 0; ?> patients suivis
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-clock text-warning me-2"></i>
                        <strong>Temps moyen :</strong> 
                        <?php echo round($stats_generales['duree_moyenne'] ?? 0); ?> minutes par consultation
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-ambulance text-danger me-2"></i>
                        <strong>Urgences :</strong> 
                        <?php echo $stats_generales['urgences'] ?? 0; ?> cas traités
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Recommandations</h6>
                <div class="alert alert-info">
                    <small>
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Optimisation des consultations :</strong> Considérez ajuster la durée des consultations selon le type.
                    </small>
                </div>
                <div class="alert alert-warning">
                    <small>
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Absentéisme RDV :</strong> Un taux de <?php echo $absent_taux; ?>% peut être optimisé par des rappels systématiques.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Données pour les graphiques
const monthlyData = {
    labels: [<?php 
        $labels = [];
        foreach ($stats_mensuelles as $stat) {
            $labels[] = "'" . date('M Y', strtotime($stat['mois'] . '-01')) . "'";
        }
        echo implode(', ', $labels);
    ?>],
    datasets: [{
        label: 'Consultations',
        data: [<?php echo implode(', ', array_column($stats_mensuelles, 'consultations')); ?>],
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59, 130, 246, 0.1)',
        tension: 0.4
    }, {
        label: 'Nouveaux patients',
        data: [<?php echo implode(', ', array_column($stats_mensuelles, 'nouveaux_patients')); ?>],
        borderColor: '#10b981',
        backgroundColor: 'rgba(16, 185, 129, 0.1)',
        tension: 0.4
    }]
};

const typeData = {
    labels: [<?php 
        $typeLabels = [];
        foreach ($types_consultations as $type) {
            $nom = $type['type_consultation'] ? ucfirst($type['type_consultation']) : 'Non spécifié';
            $typeLabels[] = "'" . $nom . "'";
        }
        echo implode(', ', $typeLabels);
    ?>],
    datasets: [{
        data: [<?php echo implode(', ', array_column($types_consultations, 'nombre')); ?>],
        backgroundColor: [
            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', 
            '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1'
        ]
    }]
};

const ageData = {
    labels: [<?php 
        $ageLabels = [];
        foreach ($tranches_age as $tranche) {
            $ageLabels[] = "'" . $tranche['tranche_age'] . "'";
        }
        echo implode(', ', $ageLabels);
    ?>],
    datasets: [{
        data: [<?php echo implode(', ', array_column($tranches_age, 'nombre_patients')); ?>],
        backgroundColor: [
            '#3b82f6', '#10b981', '#f59e0b', '#ef4444'
        ],
        borderWidth: 1
    }]
};

// Initialisation des graphiques
let monthlyChart, typeChart, ageChart;
let chartType = 'line'; // 'line' ou 'bar'

document.addEventListener('DOMContentLoaded', function() {
    initCharts();
    initFilters();
});

function initCharts() {
    // Graphique mensuel
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    monthlyChart = new Chart(monthlyCtx, {
        type: chartType,
        data: monthlyData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Nombre'
                    }
                }
            }
        }
    });
    
    // Graphique par type
    const typeCtx = document.getElementById('typeChart').getContext('2d');
    typeChart = new Chart(typeCtx, {
        type: 'doughnut',
        data: typeData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
    
    // Graphique par âge
    const ageCtx = document.getElementById('ageChart').getContext('2d');
    ageChart = new Chart(ageCtx, {
        type: 'pie',
        data: ageData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
}

function initFilters() {
    const periodeSelect = document.getElementById('periodeSelect');
    const dateDebut = document.getElementById('date_debut');
    const dateFin = document.getElementById('date_fin');
    
    periodeSelect.addEventListener('change', function() {
        const aujourdhui = new Date().toISOString().split('T')[0];
        const date = new Date();
        
        switch(this.value) {
            case 'aujourdhui':
                dateDebut.value = aujourdhui;
                dateFin.value = aujourdhui;
                dateDebut.readOnly = true;
                dateFin.readOnly = true;
                break;
                
            case 'semaine_courante':
                const debutSemaine = new Date(date.setDate(date.getDate() - date.getDay() + 1));
                dateDebut.value = debutSemaine.toISOString().split('T')[0];
                dateFin.value = aujourdhui;
                dateDebut.readOnly = true;
                dateFin.readOnly = true;
                break;
                
            case 'mois_courant':
                dateDebut.value = aujourdhui.substr(0, 8) + '01';
                dateFin.value = aujourdhui;
                dateDebut.readOnly = true;
                dateFin.readOnly = true;
                break;
                
            case 'trimestre':
                const mois = date.getMonth();
                const debutTrimestre = new Date(date.getFullYear(), Math.floor(mois / 3) * 3, 1);
                dateDebut.value = debutTrimestre.toISOString().split('T')[0];
                dateFin.value = aujourdhui;
                dateDebut.readOnly = true;
                dateFin.readOnly = true;
                break;
                
            case 'annee_courante':
                dateDebut.value = date.getFullYear() + '-01-01';
                dateFin.value = aujourdhui;
                dateDebut.readOnly = true;
                dateFin.readOnly = true;
                break;
                
            case 'personnalise':
                dateDebut.readOnly = false;
                dateFin.readOnly = false;
                break;
        }
    });
}

function toggleChartType() {
    chartType = chartType === 'line' ? 'bar' : 'line';
    monthlyChart.destroy();
    
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    monthlyChart = new Chart(monthlyCtx, {
        type: chartType,
        data: monthlyData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function exportToExcel() {
    // Simuler un téléchargement Excel
    const data = {
        periode: 'du <?php echo $date_debut; ?> au <?php echo $date_fin; ?>',
        specialite: '<?php echo $specialite; ?>',
        consultations_total: <?php echo $stats_generales['total_consultations'] ?? 0; ?>,
        patients_uniques: <?php echo $stats_generales['patients_uniques'] ?? 0; ?>,
        duree_moyenne: <?php echo round($stats_generales['duree_moyenne'] ?? 0); ?>,
        urgences: <?php echo $stats_generales['urgences'] ?? 0; ?>,
        absent_taux: <?php echo $absent_taux; ?>
    };
    
    // Créer un fichier CSV/Excel simple
    let csv = 'Rapport statistiques médicales\n\n';
    csv += 'Période,' + data.periode + '\n';
    csv += 'Spécialité,' + data.specialite + '\n\n';
    
    csv += 'Statistique,Valeur\n';
    csv += 'Total consultations,' + data.consultations_total + '\n';
    csv += 'Patients uniques,' + data.patients_uniques + '\n';
    csv += 'Durée moyenne (min),' + data.duree_moyenne + '\n';
    csv += 'Urgences,' + data.urgences + '\n';
    csv += 'Taux absentéisme (%),' + data.absent_taux + '\n\n';
    
    csv += 'Types de consultations\n';
    csv += 'Type,Nombre,Pourcentage\n';
    <?php foreach ($types_consultations as $type): 
        $type_nom = $type['type_consultation'] ? ucfirst($type['type_consultation']) : 'Non spécifié';
    ?>
    csv += '<?php echo $type_nom; ?>,<?php echo $type['nombre']; ?>,<?php echo $type['pourcentage']; ?>%\n';
    <?php endforeach; ?>
    
    // Télécharger le fichier
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'statistiques_<?php echo date('Ymd'); ?>.csv';
    link.click();
    
    showToast('Exportation Excel lancée', 'success');
}

function generateReport() {
    showToast('Génération du rapport en cours...', 'info');
    
    // Simuler un traitement
    setTimeout(() => {
        const reportContent = `
            Rapport d'activité médicale
            =============================
            
            Période : ${document.getElementById('date_debut').value} à ${document.getElementById('date_fin').value}
            Médecin : Dr. <?php echo $_SESSION['prenom'] . ' ' . $_SESSION['nom']; ?>
            Spécialité : <?php echo $specialite; ?>
            
            SYNTHÈSE
            ---------
            • Consultations totales : <?php echo $stats_generales['total_consultations'] ?? 0; ?>
            • Patients uniques : <?php echo $stats_generales['patients_uniques'] ?? 0; ?>
            • Durée moyenne : <?php echo round($stats_generales['duree_moyenne'] ?? 0); ?> minutes
            • Urgences : <?php echo $stats_generales['urgences'] ?? 0; ?>
            • Taux d'absentéisme : <?php echo $absent_taux; ?>%
            
            RECOMMANDATIONS
            ----------------
            1. Optimiser les plannings selon les jours de forte activité
            2. Mettre en place des rappels automatiques pour réduire l'absentéisme
            3. Adapter la durée des consultations selon le type
            
            Généré le : ${new Date().toLocaleDateString()}
        `;
        
        const blob = new Blob([reportContent], { type: 'text/plain' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'rapport_activite_<?php echo date('Ymd_His'); ?>.txt';
        link.click();
        
        showToast('Rapport généré avec succès', 'success');
    }, 1000);
}

function showToast(message, type = 'info') {
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
    
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}
</script>

<style>
.stat-card {
    border-radius: 10px;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.table th {
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table td {
    vertical-align: middle;
}

.canvas-container {
    position: relative;
    height: 300px;
    width: 100%;
}

@media (max-width: 768px) {
    .stat-card .display-6 {
        font-size: 1.5rem;
    }
    
    .stat-card i {
        font-size: 1.5rem;
    }
}

/* Impression */
@media print {
    .btn, .form-control, .form-select, .card-header .btn-group {
        display: none !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
    
    .stat-card {
        break-inside: avoid;
    }
}
</style>