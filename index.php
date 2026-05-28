<?php

// Connexion à la base de données (seulement pour les stats)
require_once 'config/database.php';

// Redirection selon le rôle connecté
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            exit();
        case 'docteur':
            header('Location: docteur/dashboard.php');
            exit();
        case 'secretaire':
            header('Location: secretaire/dashboard.php');
            exit();
        case 'assistant':
            header('Location: assistant/dashboard.php');
            exit();
    }
}

$pdo = Database::getInstance()->getConnection();

// Récupérer les statistiques publiques
$stats = [
    'patients' => $pdo->query("SELECT COUNT(*) FROM patients WHERE statut = 'actif'")->fetchColumn(),
    'docteurs' => $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'docteur' AND statut = 'actif'")->fetchColumn(),
    'consultations' => $pdo->query("SELECT COUNT(*) FROM consultations WHERE YEAR(date_consultation) = YEAR(CURDATE())")->fetchColumn(),
    'satisfaction' => '98%'
];
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedSystem - Gestion Médicale Intelligente</title>

    <!-- icon -->
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/landing.css">
    
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .hero-gradient {
            background: var(--gradient);
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            color: white;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
        }
        
        .nav-link.active {
            color: var(--primary) !important;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="index.php">
                <i class="fas fa-hospital me-2"></i>MedSystem
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item mx-2">
                        <a class="nav-link active" href="#home">Accueil</a>
                    </li>
                    <li class="nav-item mx-2">
                        <a class="nav-link" href="#features">Fonctionnalités</a>
                    </li>
                    <li class="nav-item mx-2">
                        <a class="nav-link" href="#stats">Statistiques</a>
                    </li>
                    <li class="nav-item mx-2">
                        <a class="nav-link" href="#testimonials">Témoignages</a>
                    </li>
                    <li class="nav-item mx-2">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
                
                <div class="d-flex">
                    <a href="login.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-sign-in-alt me-1"></i>Connexion
                    </a>
                    <a href="register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-1"></i>Inscription
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-gradient text-white py-5 mt-5">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <h1 class="display-4 fw-bold mb-4">
                        Gestion Médicale <span class="text-warning">Intelligente</span>
                    </h1>
                    <p class="lead mb-4 opacity-75">
                        Solution complète pour la gestion des patients, consultations, rendez-vous et prescriptions médicales. 
                        Optimisez votre pratique médicale avec notre système moderne et intuitif.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="login.php" class="btn btn-light btn-lg px-4">
                            <i class="fas fa-play-circle me-2"></i>Commencer maintenant
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg px-4">
                            <i class="fas fa-info-circle me-2"></i>En savoir plus
                        </a>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="text-center mt-5 mt-lg-0">
                        <img src="assets/img/image.png" alt="Dashboard Médical" class="img-fluid" style="max-width: 80%;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section id="stats" class="py-5 bg-light">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="card stat-card text-center p-4 border-0 shadow-sm">
                        <div class="feature-icon bg-primary mx-auto">
                            <i class="fas fa-user-injured"></i>
                        </div>
                        <h3 class="display-5 fw-bold text-primary"><?php echo number_format($stats['patients']); ?></h3>
                        <p class="text-muted mb-0">Patients actifs</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-center p-4 border-0 shadow-sm">
                        <div class="feature-icon bg-success mx-auto">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <h3 class="display-5 fw-bold text-success"><?php echo $stats['docteurs']; ?></h3>
                        <p class="text-muted mb-0">Médecins actifs</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-center p-4 border-0 shadow-sm">
                        <div class="feature-icon bg-warning mx-auto">
                            <i class="fas fa-stethoscope"></i>
                        </div>
                        <h3 class="display-5 fw-bold text-warning"><?php echo number_format($stats['consultations']); ?></h3>
                        <p class="text-muted mb-0">Consultations cette année</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card text-center p-4 border-0 shadow-sm">
                        <div class="feature-icon bg-info mx-auto">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h3 class="display-5 fw-bold text-info"><?php echo $stats['satisfaction']; ?></h3>
                        <p class="text-muted mb-0">Taux de satisfaction</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">Fonctionnalités principales</h2>
                <p class="text-muted lead">Découvrez les fonctionnalités qui font de MedSystem la solution idéale</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card h-100 border-0 shadow-sm hover-lift">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary">
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <h4 class="mb-3">Gestion multi-rôles</h4>
                            <p class="text-muted">
                                Administration complète avec rôles distincts pour médecins, secrétaires, assistants et administrateurs.
                            </p>
                            <ul class="list-unstyled text-muted">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Permissions personnalisées</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Dashboard personnalisé</li>
                                <li><i class="fas fa-check text-success me-2"></i>Interface adaptée</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="card h-100 border-0 shadow-sm hover-lift">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-success">
                                <i class="fas fa-user-injured"></i>
                            </div>
                            <h4 class="mb-3">Dossier patient complet</h4>
                            <p class="text-muted">
                                Gestion centralisée des dossiers patients avec historique médical complet et suivi détaillé.
                            </p>
                            <ul class="list-unstyled text-muted">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Fiche médicale complète</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Historique des consultations</li>
                                <li><i class="fas fa-check text-success me-2"></i>Prescriptions numériques</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="card h-100 border-0 shadow-sm hover-lift">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-warning">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h4 class="mb-3">Gestion des rendez-vous</h4>
                            <p class="text-muted">
                                Système de prise de rendez-vous intelligent avec rappels automatiques et gestion des disponibilités.
                            </p>
                            <ul class="list-unstyled text-muted">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Planning en temps réel</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Rappels automatiques</li>
                                <li><i class="fas fa-check text-success me-2"></i>Gestion des annulations</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4 mt-3">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="card h-100 border-0 shadow-sm hover-lift">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-info">
                                <i class="fas fa-prescription"></i>
                            </div>
                            <h4 class="mb-3">Prescriptions électroniques</h4>
                            <p class="text-muted">
                                Création et gestion des prescriptions médicales avec base de données des médicaments intégrée.
                            </p>
                            <ul class="list-unstyled text-muted">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Base médicaments intégrée</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Génération automatique</li>
                                <li><i class="fas fa-check text-success me-2"></i>Historique des prescriptions</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="500">
                    <div class="card h-100 border-0 shadow-sm hover-lift">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-danger">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h4 class="mb-3">Tableaux de bord avancés</h4>
                            <p class="text-muted">
                                Visualisation des données avec graphiques interactifs et rapports détaillés pour chaque rôle.
                            </p>
                            <ul class="list-unstyled text-muted">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Statistiques en temps réel</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Rapports personnalisés</li>
                                <li><i class="fas fa-check text-success me-2"></i>Export des données</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="600">
                    <div class="card h-100 border-0 shadow-sm hover-lift">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-purple">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h4 class="mb-3">Sécurité et confidentialité</h4>
                            <p class="text-muted">
                                Protection des données médicales sensibles avec chiffrement et contrôle d'accès granulaire.
                            </p>
                            <ul class="list-unstyled text-muted">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Chiffrement des données</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Audit des accès</li>
                                <li><i class="fas fa-check text-success me-2"></i>Sauvegardes automatiques</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Role-Based Features -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">Adapté à tous les métiers</h2>
                <p class="text-muted lead">Une interface personnalisée pour chaque membre de l'équipe médicale</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="card text-center p-4 border-0 shadow-sm h-100" data-aos="flip-left">
                        <div class="mb-3">
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-user-md fa-2x"></i>
                            </div>
                        </div>
                        <h4>Docteurs</h4>
                        <p class="text-muted small">
                            Gestion des consultations, prescriptions et suivi des patients avec interface optimisée.
                        </p>
                        <div class="mt-3">
                            <span class="badge bg-primary-light text-primary">Consultations</span>
                            <span class="badge bg-primary-light text-primary">Prescriptions</span>
                            <span class="badge bg-primary-light text-primary">Diagnostics</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-center p-4 border-0 shadow-sm h-100" data-aos="flip-left" data-aos-delay="100">
                        <div class="mb-3">
                            <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-user-nurse fa-2x"></i>
                            </div>
                        </div>
                        <h4>Assistants</h4>
                        <p class="text-muted small">
                            Accueil des patients, préparation des consultations et aide aux procédures médicales.
                        </p>
                        <div class="mt-3">
                            <span class="badge bg-success-light text-success">Patients</span>
                            <span class="badge bg-success-light text-success">Préparation</span>
                            <span class="badge bg-success-light text-success">Assistance</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-center p-4 border-0 shadow-sm h-100" data-aos="flip-left" data-aos-delay="200">
                        <div class="mb-3">
                            <div class="rounded-circle bg-warning text-white d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-clipboard-list fa-2x"></i>
                            </div>
                        </div>
                        <h4>Secrétaires</h4>
                        <p class="text-muted small">
                            Gestion administrative, prise de rendez-vous et organisation du planning médical.
                        </p>
                        <div class="mt-3">
                            <span class="badge bg-warning-light text-warning">RDV</span>
                            <span class="badge bg-warning-light text-warning">Facturation</span>
                            <span class="badge bg-warning-light text-warning">Administratif</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-center p-4 border-0 shadow-sm h-100" data-aos="flip-left" data-aos-delay="300">
                        <div class="mb-3">
                            <div class="rounded-circle bg-danger text-white d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-cogs fa-2x"></i>
                            </div>
                        </div>
                        <h4>Administrateurs</h4>
                        <p class="text-muted small">
                            Configuration du système, gestion des utilisateurs et supervision de l'ensemble des activités.
                        </p>
                        <div class="mt-3">
                            <span class="badge bg-danger-light text-danger">Système</span>
                            <span class="badge bg-danger-light text-danger">Utilisateurs</span>
                            <span class="badge bg-danger-light text-danger">Sécurité</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section id="testimonials" class="py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">Ce qu'ils disent de nous</h2>
                <p class="text-muted lead">Découvrez les témoignages de nos utilisateurs satisfaits</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4" data-aos="zoom-in">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-4">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Dr. Sophie Martin</h6>
                                    <small class="text-muted">Cardiologue</small>
                                </div>
                            </div>
                            <p class="text-muted">
                                "MedSystem a révolutionné ma pratique. La gestion des patients est devenue tellement plus efficace."
                            </p>
                            <div class="text-warning">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="100">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-4">
                                <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="fas fa-user-nurse"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Marie Dubois</h6>
                                    <small class="text-muted">Assistante médicale</small>
                                </div>
                            </div>
                            <p class="text-muted">
                                "L'interface est intuitive et nous fait gagner un temps considérable dans la gestion quotidienne."
                            </p>
                            <div class="text-warning">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="200">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-4">
                                <div class="rounded-circle bg-warning text-white d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Thomas Leroy</h6>
                                    <small class="text-muted">Directeur de clinique</small>
                                </div>
                            </div>
                            <p class="text-muted">
                                "Une solution complète qui répond parfaitement aux besoins de notre établissement médical."
                            </p>
                            <div class="text-warning">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8" data-aos="fade-right">
                    <h2 class="display-5 fw-bold mb-3">Prêt à optimiser votre pratique médicale ?</h2>
                    <p class="lead mb-4 opacity-75">
                        Rejoignez des centaines de professionnels de santé qui font déjà confiance à MedSystem.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end" data-aos="fade-left">
                    <a href="register.php" class="btn btn-light btn-lg px-5">
                        <i class="fas fa-rocket me-2"></i>Commencer gratuitement
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h4 class="mb-4">
                        <i class="fas fa-hospital me-2"></i>MedSystem
                    </h4>
                    <p class="text-white-50">
                        Solution complète de gestion médicale pour les professionnels de santé.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white-50"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-linkedin fa-lg"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-instagram fa-lg"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 mb-4">
                    <h6 class="mb-4">Navigation</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#home" class="text-white-50 text-decoration-none">Accueil</a></li>
                        <li class="mb-2"><a href="#features" class="text-white-50 text-decoration-none">Fonctionnalités</a></li>
                        <li class="mb-2"><a href="#stats" class="text-white-50 text-decoration-none">Statistiques</a></li>
                        <li><a href="#testimonials" class="text-white-50 text-decoration-none">Témoignages</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-4 mb-4">
                    <h6 class="mb-4">Système</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="login.php" class="text-white-50 text-decoration-none">Connexion</a></li>
                        <li class="mb-2"><a href="register.php" class="text-white-50 text-decoration-none">Inscription</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">FAQ</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">Support</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-4 col-md-4 mb-4">
                    <h6 class="mb-4">Contact</h6>
                    <ul class="list-unstyled text-white-50">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2"></i>123 Rue de la Santé</li>
                        <li class="mb-2"><i class="fas fa-phone me-2"></i>+0 1 23 456 789</li>
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i>contact@medsystem.fr</li>
                        <li><i class="fas fa-clock me-2"></i>Lun-Ven: 9h-18h</li>
                    </ul>
                </div>
            </div>
            
            <hr class="text-white-50 my-4">
            
            <div class="row">
                <div class="col-md-6">
                    <p class="text-white-50 mb-0">
                        &copy; <?php echo date('Y'); ?> MedSystem. Tous droits réservés.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-white-50 mb-0">
                        <a href="#" class="text-white-50 text-decoration-none me-3">Mentions légales</a>
                        <a href="#" class="text-white-50 text-decoration-none">Politique de confidentialité</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
        // Initialiser AOS animations
        AOS.init({
            duration: 800,
            once: true
        });
        
        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Navigation active
        const sections = document.querySelectorAll('section');
        const navLinks = document.querySelectorAll('.nav-link');
        
        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (scrollY >= (sectionTop - 200)) {
                    current = section.getAttribute('id');
                }
            });
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${current}`) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>