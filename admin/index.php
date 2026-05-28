<?php
require_once '../config/database.php';
checkRole(['accueil']);

$userInfo = getUserInfo($pdo);
$patients = getAllPatients($pdo);
$consultations = getConsultationsByRole($pdo, $_SESSION['user_id'], 'accueil');

// Statistiques
$newPatientsToday = $pdo->query("SELECT COUNT(*) as count FROM patients WHERE DATE(date_enregistrement) = CURDATE()")->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Accueil</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="dashboard-sidebar">
        <div class="sidebar-header">
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo substr($userInfo['prenom'], 0, 1) . substr($userInfo['nom'], 0, 1); ?>
                </div>
                <div class="user-info">
                    <h3><?php echo $userInfo['prenom'] . ' ' . $userInfo['nom']; ?></h3>
                    <p>Service d'Accueil</p>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <ul>
                <li><a href="index.php" class="active"><i class="fas fa-home"></i> Tableau de Bord</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="nouveau-patient.php"><i class="fas fa-user-plus"></i> Nouveau Patient</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-main">
        <header class="dashboard-header">
            <div class="dashboard-title">
                <h2><i class="fas fa-headset"></i> Tableau de Bord Accueil</h2>
                <button class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </header>

        <div class="dashboard-content">
            <!-- Stats -->
            <div class="stats-container">
                <div class="stat-card">
                    <i class="fas fa-users" style="color: #3498db;"></i>
                    <h3><?php echo count($patients); ?></h3>
                    <p>Patients Totaux</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-plus" style="color: #2ecc71;"></i>
                    <h3><?php echo $newPatientsToday; ?></h3>
                    <p>Nouveaux Aujourd'hui</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-phone-volume" style="color: #e74c3c;"></i>
                    <h3>25</h3>
                    <p>Appels Reçus</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-door-open" style="color: #9b59b6;"></i>
                    <h3>48</h3>
                    <p>Visites Aujourd'hui</p>
                </div>
            </div>

            <!-- Nouveaux patients -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-user-plus"></i> Nouveaux Patients (Aujourd'hui)</h3>
                    <a href="nouveau-patient.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Ajouter
                    </a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Heure</th>
                                <th>Motif</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $todayPatients = array_filter($patients, function($patient) {
                                return date('Y-m-d', strtotime($patient['date_enregistrement'])) == date('Y-m-d');
                            });
                            
                            if(empty($todayPatients)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <div class="empty-state">
                                            <i class="fas fa-users-slash"></i>
                                            <p>Aucun nouveau patient aujourd'hui</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach(array_slice($todayPatients, 0, 5) as $patient): ?>
                                <tr>
                                    <td><?php echo $patient['code_patient']; ?></td>
                                    <td><?php echo $patient['nom']; ?></td>
                                    <td><?php echo $patient['prenom']; ?></td>
                                    <td><?php echo date('H:i', strtotime($patient['date_enregistrement'])); ?></td>
                                    <td>
                                        <?php if($patient['pathologies']): ?>
                                            <span class="badge badge-info"><?php echo $patient['pathologies']; ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Première visite</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="patients.php?view=<?php echo $patient['id']; ?>" class="btn-icon" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Formulaire rapide nouveau patient -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-user-injured"></i> Enregistrement Rapide</h3>
                </div>
                <form method="POST" action="nouveau-patient.php" class="quick-form">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="text" name="nom" placeholder="Nom" required class="form-control">
                        </div>
                        <div class="form-group">
                            <input type="text" name="prenom" placeholder="Prénom" required class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <select name="sexe" class="form-control" required>
                                <option value="">Sexe</option>
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <input type="date" name="date_naissance" placeholder="Date de naissance" required class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <input type="text" name="telephone" placeholder="Téléphone" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Enregistrer le patient
                    </button>
                </form>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>