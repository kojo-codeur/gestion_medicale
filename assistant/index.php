<?php
require_once '../config/database.php';
requireAuth();
checkRole(['assistant']);

$title = "Tableau de bord - Assistant";
$user_id = $_SESSION['user_id'];

// Récupérer les statistiques
try {
    // Total patients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM patients");
    $totalPatients = $stmt->fetch()['total'];
    
    // Consultations aujourd'hui
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM consultations WHERE DATE(date_consultation) = ?");
    $stmt->execute([$today]);
    $todayConsultations = $stmt->fetch()['total'];
    
    // Rendez-vous à venir
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM rendez_vous 
        WHERE DATE(date_rdv) >= ? 
        AND statut = 'confirme'
    ");
    $stmt->execute([$today]);
    $upcomingAppointments = $stmt->fetch()['total'];
    
    // Derniers patients
    $stmt = $pdo->query("
        SELECT * FROM patients 
        ORDER BY date_enregistrement DESC 
        LIMIT 5
    ");
    $recentPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Consultations à venir
    $stmt = $pdo->prepare("
        SELECT c.*, p.nom as patient_nom, p.prenom as patient_prenom
        FROM consultations c
        JOIN patients p ON c.patient_id = p.id
        WHERE DATE(c.date_consultation) >= ?
        ORDER BY c.date_consultation ASC
        LIMIT 5
    ");
    $stmt->execute([$today]);
    $upcomingConsultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erreur statistiques assistant: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Assistant | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-hospital-alt"></i>
                <h2><?php echo APP_NAME; ?></h2>
            </div>
            <div class="user-info">
                <p><strong><?php echo getUserFullName(); ?></strong></p>
                <p class="role-badge"><?php echo getRoleWithIcon($_SESSION['role']); ?></p>
            </div>
        </div>
        
        <nav class="sidebar-menu">
            <a href="index.php" class="sidebar-item active">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a>
            <a href="patients.php" class="sidebar-item">
                <i class="fas fa-user-injured"></i> Patients
            </a>
            <a href="consultations.php" class="sidebar-item">
                <i class="fas fa-file-medical"></i> Consultations
            </a>
            <div class="sidebar-divider"></div>
            <a href="../logout.php" class="sidebar-item">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <small>Version <?php echo APP_VERSION; ?></small>
        </div>
    </div>
    
    <div class="main-content-with-sidebar">
        <header class="navbar">
            <button class="sidebar-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="header-actions">
                <span class="welcome-msg">Bonjour, <?php echo htmlspecialchars($_SESSION['user_prenom']); ?>!</span>
                <div class="notifications">
                    <i class="fas fa-bell"></i>
                    <span class="badge"><?php echo $todayConsultations; ?></span>
                </div>
            </div>
        </header>
        
        <main class="dashboard">
            <div class="welcome-section">
                <h1><i class="fas fa-user-nurse"></i> Tableau de bord Assistant</h1>
                <p>Gérez les patients et les consultations médicales</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon patients">
                        <i class="fas fa-user-injured"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $totalPatients; ?></h3>
                        <p>Patients enregistrés</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon consultations">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $todayConsultations; ?></h3>
                        <p>Consultations aujourd'hui</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon appointments">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $upcomingAppointments; ?></h3>
                        <p>Rendez-vous à venir</p>
                    </div>
                </div>
            </div>
            
            <div class="quick-actions">
                <h2>Actions rapides</h2>
                <div class="actions-grid">
                    <a href="patients.php?action=add" class="action-btn">
                        <i class="fas fa-user-plus"></i>
                        <span>Nouveau patient</span>
                    </a>
                    <a href="consultations.php?action=add" class="action-btn">
                        <i class="fas fa-notes-medical"></i>
                        <span>Nouvelle consultation</span>
                    </a>
                    <a href="patients.php" class="action-btn">
                        <i class="fas fa-search"></i>
                        <span>Rechercher patient</span>
                    </a>
                    <a href="#" class="action-btn">
                        <i class="fas fa-print"></i>
                        <span>Imprimer rapports</span>
                    </a>
                </div>
            </div>
            
            <div class="content-grid">
                <div class="table-container">
                    <div class="table-header">
                        <h2>Derniers patients</h2>
                        <a href="patients.php" class="btn btn-primary btn-sm">
                            Voir tous <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Date naissance</th>
                                <th>Sexe</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPatients as $patient): ?>
                            <tr>
                                <td><strong><?php echo escape($patient['code_patient']); ?></strong></td>
                                <td><?php echo escape($patient['nom']); ?></td>
                                <td><?php echo escape($patient['prenom']); ?></td>
                                <td><?php echo formatDate($patient['date_naissance']); ?></td>
                                <td>
                                    <span class="badge <?php echo $patient['sexe'] === 'M' ? 'badge-info' : 'badge-warning'; ?>">
                                        <?php echo $patient['sexe'] === 'M' ? 'Homme' : 'Femme'; ?>
                                    </span>
                                </td>
                                <td class="table-actions">
                                    <a href="patients.php?action=view&id=<?php echo $patient['id']; ?>" 
                                       class="btn btn-primary btn-sm" title="Voir">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="patients.php?action=edit&id=<?php echo $patient['id']; ?>" 
                                       class="btn btn-success btn-sm" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="table-container">
                    <div class="table-header">
                        <h2>Consultations à venir</h2>
                        <a href="consultations.php" class="btn btn-primary btn-sm">
                            Voir toutes <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingConsultations as $consultation): ?>
                            <tr>
                                <td><?php echo formatDateTime($consultation['date_consultation']); ?></td>
                                <td><?php echo escape($consultation['patient_prenom'] . ' ' . $consultation['patient_nom']); ?></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $consultation['statut'] === 'termine' ? 'success' : 
                                             ($consultation['statut'] === 'annule' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo ucfirst($consultation['statut']); ?>
                                    </span>
                                </td>
                                <td class="table-actions">
                                    <a href="consultations.php?action=view&id=<?php echo $consultation['id']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>