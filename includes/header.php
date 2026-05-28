<?php
// includes/header.php

if (!isset($title)) {
    $title = 'MedSystem - Gestion Médicale';
}

// --- Traitement du marquage d'une notification comme lue (sans AJAX) ---
if (isset($_GET['mark_notification_id']) && is_numeric($_GET['mark_notification_id'])) {
    $notif_id = (int)$_GET['mark_notification_id'];
    if (isLoggedIn()) {
        require_once '../config/database.php';
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("UPDATE notifications SET lu = 1 WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
        $stmt->execute([$notif_id, $_SESSION['user_id']]);
    }
    // Supprimer le paramètre de l'URL pour ne pas le garder
    $request_uri = $_SERVER['REQUEST_URI'];
    $clean_uri = preg_replace('/([?&])mark_notification_id=[^&]*&?/', '$1', $request_uri);
    $clean_uri = rtrim($clean_uri, '?&');
    header("Location: $clean_uri");
    exit;
}

// Fonctions helpers pour les notifications
if (!function_exists('getTimeAgo')) {
    function getTimeAgo($datetime) {
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return 'À l\'instant';
        if ($diff < 3600) return floor($diff / 60) . ' min';
        if ($diff < 86400) return floor($diff / 3600) . ' h';
        if ($diff < 604800) return floor($diff / 86400) . ' j';
        return date('d/m/Y', $time);
    }
}

if (!function_exists('getNotificationIcon')) {
    function getNotificationIcon($type) {
        $icons = [
            'rdv' => ['icon' => 'fas fa-calendar-check', 'color' => 'text-primary'],
            'consultation' => ['icon' => 'fas fa-stethoscope', 'color' => 'text-success'],
            'patient' => ['icon' => 'fas fa-user-injured', 'color' => 'text-info'],
            'system' => ['icon' => 'fas fa-cog', 'color' => 'text-secondary'],
            'urgence' => ['icon' => 'fas fa-exclamation-triangle', 'color' => 'text-danger'],
            'message' => ['icon' => 'fas fa-comment', 'color' => 'text-warning'],
            'info' => ['icon' => 'fas fa-info-circle', 'color' => 'text-primary']
        ];
        return $icons[$type] ?? ['icon' => 'fas fa-bell', 'color' => 'text-secondary'];
    }
}

// Récupérer les notifications
$unreadNotifications = 0;
$notifications = [];
if (isLoggedIn()) {
    require_once '../config/database.php';
    $pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
    
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE lu = 0 AND (user_id = ? OR user_id IS NULL)");
    $countStmt->execute([$_SESSION['user_id']]);
    $unreadNotifications = (int)$countStmt->fetchColumn();
}

// --- Récupération de la photo de profil ---
$user_photo_url = '';
if (isLoggedIn()) {
    if (!class_exists('Database')) {
        require_once '../config/database.php';
    }
    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT photo FROM utilisateurs WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $photo_db = $stmt->fetchColumn();
        if (!empty($photo_db)) {
            $user_photo_url = '../' . $photo_db;
            if (!file_exists($user_photo_url)) {
                $user_photo_url = '';
            }
        }
    } catch (Exception $e) {
        $user_photo_url = '';
    }
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <meta name="description" content="Système de gestion médicale complet pour les professionnels de santé">
    <meta name="keywords" content="médical, gestion, patients, consultations, rendez-vous, prescriptions">
    <meta name="author" content="MedSystem">
    <link rel="icon" type="image/x-icon" href="../assets/img/logo.png">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --primary-light: #edf2ff;
            --sidebar-width: 280px;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fb;
            overflow-x: hidden;
        }

        html{
            scrollbar-color: var(--primary);
            -webkit-backdrop-filter: blur();
        }

        .modal {
            z-index: 99999 !important;
            position: fixed !important;
        }
        .modal-backdrop {
            z-index: 99998 !important;
            position: fixed !important;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: white;
            border-right: 1px solid #e5e7eb;
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 2px 0 15px rgba(0,0,0,0.08);
            transform: translateX(0);
        }
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            backdrop-filter: blur(2px);
        }
        @media (max-width: 991.98px) {
            .sidebar-overlay.show {
                display: block;
            }
        }
        .top-navbar {
            height: 70px;
            z-index: 1010;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background: white;
        }
        @media (min-width: 992px) {
            .top-navbar {
                left: var(--sidebar-width);
                width: calc(100% - var(--sidebar-width));
            }
        }
        .main-content {
            min-height: calc(100vh - 70px);
            transition: all 0.3s ease;
            padding-top: 85px;
            padding-left: 0;
            padding-right: 0;
            position: relative;
        }
        @media (min-width: 992px) {
            .main-content {
                margin-left: var(--sidebar-width);
                padding-left: 20px;
                padding-right: 20px;
            }
        }
        #mobileMenuToggle {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1011;
        }
        @media (min-width: 992px) {
            #mobileMenuToggle {
                display: none !important;
            }
        }
        .dropdown-menu {
            z-index: 1020 !important;
        }
        /* NOTIFICATION DROPDOWN AGRANDI */
        .notification-dropdown {
            width: 420px !important;
            max-width: calc(100vw - 20px);
            min-width: 320px;
            z-index: 1021 !important;
            font-size: 0.9rem;
        }
        .notification-item {
            transition: background-color 0.2s;
            border-left: 3px solid transparent;
            cursor: pointer;
            padding: 1rem 1rem !important;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        .notification-item.unread {
            border-left-color: var(--primary);
            background-color: #f0f7ff;
        }
        .notification-item .time {
            font-size: 0.75rem;
            color: #6c757d;
            white-space: nowrap;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .notification-badge {
            animation: pulse 2s infinite;
            font-size: 0.7rem;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }
        @media (max-width: 576px) {
            .notification-dropdown {
                position: fixed !important;
                top: 70px !important;
                left: 10px !important;
                right: 10px !important;
                width: auto !important;
                max-width: none;
            }
        }
        .search-input {
            min-width: 250px;
        }
        @media (max-width: 1200px) {
            .search-input {
                min-width: 200px;
            }
        }
        @media (max-width: 1100px) {
            .search-input {
                display: none !important;
            }
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            overflow: hidden;
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        body.sidebar-open {
            overflow: hidden;
        }
        .modal.show {
            display: flex !important;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }
        .modal-dialog {
            margin: 1rem;
            max-width: 90%;
        }
        body.modal-open .sidebar {
            opacity: 0.3;
            pointer-events: none;
        }
        @media (max-width: 768px) {
            .navbar-brand.mx-auto {
                margin-left: 0 !important;
                margin-right: auto !important;
            }
            .main-content {
                padding: 15px;
                padding-top: 85px;
            }
            .modal-dialog {
                margin: 0.5rem;
                max-width: 95%;
            }
        }
        @media (max-width: 576px) {
            .top-navbar .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }
            .main-content {
                padding: 10px;
                padding-top: 80px;
            }
        }
    </style>
</head>
<body class="<?php echo isLoggedIn() ? 'has-sidebar' : ''; ?>">
    <?php if (isLoggedIn()): ?>
    
    <!-- Inclure la sidebar dynamique -->
    <?php include 'sidebar.php'; ?>
    
    <!-- Overlay pour mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm top-navbar">
        <div class="container-fluid px-3">
            <button class="btn btn-sm btn-outline-primary me-2 d-lg-none" id="mobileMenuToggle" type="button">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="d-none d-lg-block">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="#" class="text-decoration-none">MedSystem</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($title); ?></li>
                </ol>
            </nav>
            
            <div class="navbar-brand d-lg-none mx-auto">
                <span class="fw-bold"><?php echo htmlspecialchars($title); ?></span>
            </div>
            
            <div class="d-flex align-items-center ms-auto">
                <!-- Search -->
                <div class="input-group me-3 d-none d-xl-flex search-input">
                    <input type="text" class="form-control form-control-sm" placeholder="Rechercher..." id="globalSearch">
                    <button class="btn btn-primary btn-sm" type="button" id="searchButton">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                
                
                <!-- Notifications -->
                <div class="dropdown me-3">
                    <button class="btn btn-link text-dark position-relative p-0 border-0 bg-transparent" 
                            type="button" 
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            id="notificationsDropdown"
                            title="Notifications">
                        <i class="fas fa-bell fa-lg"></i>
                        <?php if ($unreadNotifications > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                            <?php echo $unreadNotifications > 9 ? '9+' : $unreadNotifications; ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0 notification-dropdown" style="z-index: 9999;">
                        <div class="p-3 border-bottom bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">Notifications</h6>
                                <?php if ($unreadNotifications > 0): ?>
                                <span class="badge bg-primary">
                                    <?php echo $unreadNotifications; ?> non lu<?php echo $unreadNotifications > 1 ? 's' : ''; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="p-2" style="max-height: 450px; overflow-y: auto;">
                            <?php if (empty($notifications)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                    <p class="text-muted mb-0">Aucune notification</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <?php
                                    $timeAgo = getTimeAgo($notif['created_at']);
                                    $unreadClass = $notif['lu'] ? '' : 'unread';
                                    $icon = getNotificationIcon($notif['type']);
                                    // Lien absolu (relatif à la racine) vers la page des notifications avec le paramètre de marquage
                                    $target = "../admin/notifications.php?mark_notification_id=" . $notif['id'];
                                    ?>
                                    <a href="<?= htmlspecialchars($target) ?>" class="text-decoration-none" style="color: inherit;">
                                        <div class="notification-item p-3 border-bottom <?= $unreadClass ?>">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <i class="<?= $icon['icon'] ?> <?= $icon['color'] ?> fa-fw"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <h6 class="mb-1 fw-semibold"><?= htmlspecialchars($notif['titre']) ?></h6>
                                                        <small class="time ms-2"><?= $timeAgo ?></small>
                                                    </div>
                                                    <p class="mb-1 small text-muted"><?= htmlspecialchars($notif['message']) ?></p>
                                                    <span class="small text-primary">Voir plus →</span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-3 border-top">
                            <a class="dropdown-item text-center text-primary fw-semibold" href="../admin/notifications.php">
                                <i class="fas fa-bell me-1"></i>Voir toutes les notifications
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- User Profile -->
                <div class="dropdown">
                    <button class="btn btn-link text-dark d-flex align-items-center p-0 border-0 bg-transparent" 
                            type="button" 
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            title="Mon compte">
                        <div class="me-2 text-end d-none d-lg-block">
                            <div class="fw-semibold text-truncate" style="max-width: 150px;">
                                <?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?>
                            </div>
                            <small class="text-muted"><?php echo ucfirst($_SESSION['role']); ?></small>
                        </div>
                        <div class="user-avatar">
                            <?php if (!empty($user_photo_url)): ?>
                                <img src="<?= htmlspecialchars($user_photo_url) ?>" alt="Photo de profil" class="rounded-circle">
                            <?php else: ?>
                                <?php 
                                $initials = strtoupper(substr($_SESSION['prenom'], 0, 1) . substr($_SESSION['nom'], 0, 1));
                                echo $initials;
                                ?>
                            <?php endif; ?>
                        </div>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow border-0" style="z-index: 1047;">
                        <div class="px-4 py-3">
                            <div class="fw-semibold"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="../admin/profile.php"><i class="fas fa-user me-2"></i>Mon profil</a>
                        <a class="dropdown-item" href="../admin/settings.php"><i class="fas fa-cog me-2"></i>Paramètres</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <main class="main-content">
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Gestion du menu mobile
        const mobileMenuToggle = $('#mobileMenuToggle');
        const sidebar = $('#sidebar');
        const sidebarOverlay = $('#sidebarOverlay');
        
        if (mobileMenuToggle.length && sidebar.length) {
            mobileMenuToggle.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                sidebar.toggleClass('show');
                sidebarOverlay.toggleClass('show');
                $('body').toggleClass('sidebar-open');
            });
            sidebarOverlay.on('click', function() {
                sidebar.removeClass('show');
                sidebarOverlay.removeClass('show');
                $('body').removeClass('sidebar-open');
            });
            $(document).on('click', '.sidebar .nav-link', function() {
                if ($(window).width() < 992) {
                    sidebar.removeClass('show');
                    sidebarOverlay.removeClass('show');
                    $('body').removeClass('sidebar-open');
                }
            });
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.hasClass('show')) {
                    sidebar.removeClass('show');
                    sidebarOverlay.removeClass('show');
                    $('body').removeClass('sidebar-open');
                }
            });
            $(window).on('resize', function() {
                if ($(window).width() >= 992) {
                    sidebar.removeClass('show');
                    sidebarOverlay.removeClass('show');
                    $('body').removeClass('sidebar-open');
                }
            });
        }
        
        // Gestion des modales (z-index)
        function ensureModalZIndex() {
            $('.modal').each(function() {
                if (parseInt($(this).css('z-index')) < 1080) $(this).css('z-index', '1080');
            });
            $('.modal-backdrop').each(function() {
                if (parseInt($(this).css('z-index')) < 1079) $(this).css('z-index', '1079');
            });
        }
        ensureModalZIndex();
        $(document).on('show.bs.modal', '.modal', function() {
            ensureModalZIndex();
            if ($(window).width() < 992) {
                sidebar.removeClass('show');
                sidebarOverlay.removeClass('show');
                $('body').removeClass('sidebar-open');
            }
        });
        $(document).on('shown.bs.modal', '.modal', function() { ensureModalZIndex(); });
        $(document).on('click', '[data-toggle="modal"], [data-bs-toggle="modal"]', function() { setTimeout(ensureModalZIndex, 50); });
        
        // Recherche globale
        $('#searchButton').on('click', function() { performSearch(); });
        $('#globalSearch').on('keypress', function(e) { if (e.which === 13) performSearch(); });
        function performSearch() {
            const term = $('#globalSearch').val().trim();
            if (term) window.location.href = `search.php?q=${encodeURIComponent(term)}`;
        }
        
        // Gestion des dropdowns
        $(document).on('click', '[data-bs-toggle="dropdown"]', function(e) { e.stopPropagation(); });
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.dropdown').length) $('.dropdown-menu').removeClass('show');
        });
        $('#notificationsDropdown').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('.dropdown-menu').not($(this).next('.dropdown-menu')).removeClass('show');
            $(this).next('.dropdown-menu').toggleClass('show');
        });
        $('.dropdown:has(.user-avatar) > button').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('.dropdown-menu').not($(this).next('.dropdown-menu')).removeClass('show');
            $(this).next('.dropdown-menu').toggleClass('show');
        });
    });
    </script>
    
    <?php endif; ?>