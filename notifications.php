<?php
// notifications.php

require_once 'config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$title = 'Notifications';

// Récupérer les notifications selon le rôle
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // Construire la requête selon le rôle
    $sql = "
        SELECT n.*, 
               u.prenom as sender_prenom,
               u.nom as sender_nom,
               u.role as sender_role
        FROM notifications n
        LEFT JOIN utilisateurs u ON n.sender_id = u.id
        WHERE ";
    
    $countSql = "SELECT COUNT(*) as total FROM notifications WHERE ";
    $params = [];
    $countParams = [];
    
    if ($role === 'admin') {
        // Admin voit toutes les notifications système et celles qui lui sont destinées
        $sql .= "(n.user_id IS NULL OR n.user_id = ? OR n.role_target = ?)";
        $countSql .= "(user_id IS NULL OR user_id = ? OR role_target = ?)";
        $params = [$user_id, $role];
        $countParams = [$user_id, $role];
    } elseif ($role === 'docteur') {
        // Docteur voit ses notifications et celles de son rôle
        $sql .= "(n.user_id = ? OR n.role_target = ?)";
        $countSql .= "(user_id = ? OR role_target = ?)";
        $params = [$user_id, $role];
        $countParams = [$user_id, $role];
    } else {
        // Autres rôles voient seulement leurs notifications personnelles
        $sql .= "n.user_id = ?";
        $countSql .= "user_id = ?";
        $params = [$user_id];
        $countParams = [$user_id];
    }
    
    $sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Compter le total
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($countParams);
    $totalResult = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $totalNotifications = $totalResult['total'];
    $totalPages = ceil($totalNotifications / $limit);
    
    // Récupérer les notifications
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Marquer toutes les notifications comme lues quand on visite la page
    if ($role === 'admin') {
        $markReadSql = "UPDATE notifications SET lu = 1, read_at = NOW() WHERE (user_id IS NULL OR user_id = ? OR role_target = ?) AND lu = 0";
        $markReadStmt = $pdo->prepare($markReadSql);
        $markReadStmt->execute([$user_id, $role]);
    } elseif ($role === 'docteur') {
        $markReadSql = "UPDATE notifications SET lu = 1, read_at = NOW() WHERE (user_id = ? OR role_target = ?) AND lu = 0";
        $markReadStmt = $pdo->prepare($markReadSql);
        $markReadStmt->execute([$user_id, $role]);
    } else {
        $markReadSql = "UPDATE notifications SET lu = 1, read_at = NOW() WHERE user_id = ? AND lu = 0";
        $markReadStmt = $pdo->prepare($markReadSql);
        $markReadStmt->execute([$user_id]);
    }
    
} catch (PDOException $e) {
    error_log("Erreur notifications page: " . $e->getMessage());
    $notifications = [];
    $totalNotifications = 0;
    $totalPages = 1;
}

// Compter les notifications non lues pour l'en-tête
$unreadNotifications = 0;
try {
    if ($role === 'admin') {
        $countSql = "SELECT COUNT(*) as unread_count FROM notifications WHERE (user_id IS NULL OR user_id = ? OR role_target = ?) AND lu = 0";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$user_id, $role]);
    } elseif ($role === 'docteur') {
        $countSql = "SELECT COUNT(*) as unread_count FROM notifications WHERE (user_id = ? OR role_target = ?) AND lu = 0";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$user_id, $role]);
    } else {
        $countSql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND lu = 0";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$user_id]);
    }
    
    $result = $countStmt->fetch(PDO::FETCH_ASSOC);
    $unreadNotifications = $result['unread_count'] ?? 0;
} catch (PDOException $e) {
    error_log("Erreur comptage notifications: " . $e->getMessage());
}

// Récupérer les statistiques par type
$stats = [];
try {
    $statsSql = "
        SELECT 
            type,
            COUNT(*) as count,
            SUM(CASE WHEN lu = 0 THEN 1 ELSE 0 END) as unread
        FROM notifications 
        WHERE ";
    
    if ($role === 'admin') {
        $statsSql .= "(user_id IS NULL OR user_id = ? OR role_target = ?)";
        $statsParams = [$user_id, $role];
    } elseif ($role === 'docteur') {
        $statsSql .= "(user_id = ? OR role_target = ?)";
        $statsParams = [$user_id, $role];
    } else {
        $statsSql .= "user_id = ?";
        $statsParams = [$user_id];
    }
    
    $statsSql .= " GROUP BY type ORDER BY count DESC";
    
    $statsStmt = $pdo->prepare($statsSql);
    $statsStmt->execute($statsParams);
    $stats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur statistiques notifications: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - MedSystem</title>
    
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
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        .notification-card {
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .notification-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .notification-card.unread {
            border-left: 4px solid var(--primary);
            background-color: #f8fbff;
        }
        
        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .badge-type {
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 20px;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .filter-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .empty-state {
            padding: 80px 20px;
            text-align: center;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .pagination .page-link {
            color: var(--primary);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        /* Types de notifications */
        .type-rdv { background-color: #e3f2fd; color: #1565c0; }
        .type-consultation { background-color: #e8f5e9; color: #2e7d32; }
        .type-patient { background-color: #e0f2f1; color: #00695c; }
        .type-system { background-color: #f3e5f5; color: #7b1fa2; }
        .type-urgence { background-color: #ffebee; color: #c62828; }
        .type-message { background-color: #fff3e0; color: #ef6c00; }
        .type-info { background-color: #e1f5fe; color: #0277bd; }
        
        /* Badges colorés */
        .badge-rdv { background-color: #1565c0; }
        .badge-consultation { background-color: #2e7d32; }
        .badge-patient { background-color: #00695c; }
        .badge-system { background-color: #7b1fa2; }
        .badge-urgence { background-color: #c62828; }
        .badge-message { background-color: #ef6c00; }
        .badge-info { background-color: #0277bd; }
        
        @media (max-width: 768px) {
            .notification-card {
                margin-bottom: 10px;
            }
            
            .notification-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
            
            .stats-card {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body class="has-sidebar">
    <!-- Header -->
    <?php 
    $title = 'Notifications';
    include 'includes/header.php'; 
    ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="container-fluid py-4">
            <!-- En-tête de page -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 fw-bold">Notifications</h1>
                    <p class="text-muted mb-0">
                        <?php 
                        echo $totalNotifications . ' notification' . ($totalNotifications > 1 ? 's' : '');
                        if ($unreadNotifications > 0) {
                            echo ' • <span class="text-primary">' . $unreadNotifications . ' non lu' . ($unreadNotifications > 1 ? 's' : '') . '</span>';
                        }
                        ?>
                    </p>
                </div>
                
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="markAllAsRead()">
                        <i class="fas fa-check-double me-1"></i> Tout marquer comme lu
                    </button>
                    <button class="btn btn-outline-danger" onclick="deleteAllRead()">
                        <i class="fas fa-trash me-1"></i> Supprimer les lues
                    </button>
                </div>
            </div>
            
            <div class="row">
                <!-- Sidebar avec filtres et statistiques -->
                <div class="col-lg-3 mb-4">
                    <!-- Filtres -->
                    <div class="stats-card mb-4">
                        <h6 class="fw-bold mb-3">Filtres</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-sm btn-outline-secondary filter-btn active" data-filter="all">
                                Toutes
                            </button>
                            <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="unread">
                                Non lues
                            </button>
                            <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="read">
                                Lues
                            </button>
                        </div>
                        
                        <hr class="my-3">
                        
                        <h6 class="fw-bold mb-3">Types</h6>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($stats as $stat): 
                                $typeName = getTypeName($stat['type']);
                                $badgeClass = 'badge-' . $stat['type'];
                            ?>
                            <button class="btn btn-sm btn-outline-secondary filter-btn text-start d-flex justify-content-between align-items-center" 
                                    data-type="<?php echo $stat['type']; ?>">
                                <span>
                                    <i class="fas fa-circle small me-2 <?php echo $badgeClass; ?>"></i>
                                    <?php echo $typeName; ?>
                                </span>
                                <span class="badge bg-light text-dark">
                                    <?php echo $stat['count']; ?>
                                    <?php if ($stat['unread'] > 0): ?>
                                    <span class="ms-1 badge bg-danger"><?php echo $stat['unread']; ?></span>
                                    <?php endif; ?>
                                </span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Statistiques -->
                    <div class="stats-card">
                        <h6 class="fw-bold mb-3">Statistiques</h6>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Notifications totales</span>
                                <strong><?php echo $totalNotifications; ?></strong>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-primary" style="width: 100%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Non lues</span>
                                <strong class="text-primary"><?php echo $unreadNotifications; ?></strong>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <?php if ($totalNotifications > 0): ?>
                                <div class="progress-bar bg-warning" 
                                     style="width: <?php echo ($unreadNotifications / $totalNotifications) * 100; ?>%"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Taux de lecture</span>
                                <strong>
                                    <?php 
                                    if ($totalNotifications > 0) {
                                        $readRate = (($totalNotifications - $unreadNotifications) / $totalNotifications) * 100;
                                        echo number_format($readRate, 1) . '%';
                                    } else {
                                        echo '0%';
                                    }
                                    ?>
                                </strong>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <?php if ($totalNotifications > 0): ?>
                                <div class="progress-bar bg-success" 
                                     style="width: <?php echo (($totalNotifications - $unreadNotifications) / $totalNotifications) * 100; ?>%"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Liste des notifications -->
                <div class="col-lg-9">
                    <div class="stats-card">
                        <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h4 class="h5 mb-2">Aucune notification</h4>
                            <p class="text-muted">Vous n'avez pas encore de notifications.</p>
                        </div>
                        <?php else: ?>
                        
                        <!-- Barre de recherche -->
                        <div class="mb-4">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Rechercher dans les notifications..." id="searchNotifications">
                                <button class="btn btn-primary" type="button">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Liste -->
                        <div id="notificationsList">
                            <?php foreach ($notifications as $notification): 
                                $unreadClass = $notification['lu'] == 0 ? 'unread' : '';
                                $typeClass = 'type-' . $notification['type'];
                                $badgeClass = 'badge-' . $notification['type'];
                                $typeName = getTypeName($notification['type']);
                                $timeAgo = getTimeAgo($notification['created_at']);
                                $senderName = $notification['sender_prenom'] ? 
                                    $notification['sender_prenom'] . ' ' . $notification['sender_nom'] . ' (' . $notification['sender_role'] . ')' : 
                                    'Système';
                            ?>
                            <div class="notification-card p-3 bg-white <?php echo $unreadClass; ?>" 
                                 data-id="<?php echo $notification['id']; ?>"
                                 data-type="<?php echo $notification['type']; ?>"
                                 data-read="<?php echo $notification['lu']; ?>">
                                <div class="d-flex align-items-start">
                                    <div class="notification-icon me-3 <?php echo $typeClass; ?>">
                                        <i class="<?php echo getTypeIcon($notification['type']); ?>"></i>
                                    </div>
                                    
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <div>
                                                <h6 class="mb-0 fw-semibold"><?php echo htmlspecialchars($notification['titre']); ?></h6>
                                                <small class="text-muted">
                                                    De: <?php echo htmlspecialchars($senderName); ?>
                                                </small>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge badge-type <?php echo $badgeClass; ?>">
                                                    <?php echo $typeName; ?>
                                                </span>
                                                <?php if ($notification['lu'] == 0): ?>
                                                <span class="badge bg-danger">Nouveau</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <p class="mb-2"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="notification-time">
                                                <i class="far fa-clock me-1"></i>
                                                <?php echo $timeAgo; ?>
                                                <?php if ($notification['read_at']): ?>
                                                • Lu: <?php echo date('d/m/Y H:i', strtotime($notification['read_at'])); ?>
                                                <?php endif; ?>
                                            </small>
                                            
                                            <div class="d-flex gap-2">
                                                <?php if ($notification['lu'] == 0): ?>
                                                <button class="btn btn-sm btn-outline-success mark-read-btn" 
                                                        onclick="markAsRead(<?php echo $notification['id']; ?>, this)">
                                                    <i class="fas fa-check me-1"></i> Marquer comme lu
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($notification['lien']): ?>
                                                <a href="<?php echo htmlspecialchars($notification['lien']); ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-external-link-alt me-1"></i> Voir
                                                </a>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-sm btn-outline-danger delete-btn" 
                                                        onclick="deleteNotification(<?php echo $notification['id']; ?>, this)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == $page): ?>
                                    <li class="page-item active">
                                        <span class="page-link"><?php echo $i; ?></span>
                                    </li>
                                    <?php elseif ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Filtrage des notifications
        $('.filter-btn').on('click', function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            
            const filter = $(this).data('filter');
            const type = $(this).data('type');
            
            if (filter === 'all') {
                $('.notification-card').show();
            } else if (filter === 'unread') {
                $('.notification-card').hide();
                $('.notification-card[data-read="0"]').show();
            } else if (filter === 'read') {
                $('.notification-card').hide();
                $('.notification-card[data-read="1"]').show();
            } else if (type) {
                $('.notification-card').hide();
                $('.notification-card[data-type="' + type + '"]').show();
            }
        });
        
        // Recherche
        $('#searchNotifications').on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            
            $('.notification-card').each(function() {
                const title = $(this).find('h6').text().toLowerCase();
                const message = $(this).find('p').text().toLowerCase();
                const sender = $(this).find('small.text-muted').text().toLowerCase();
                
                if (title.includes(searchTerm) || message.includes(searchTerm) || sender.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
        
        // Marquer une notification comme lue
        window.markAsRead = function(notificationId, button) {
            $.ajax({
                url: 'ajax/notifications.php',
                type: 'POST',
                data: {
                    action: 'mark_read',
                    id: notificationId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const card = $(button).closest('.notification-card');
                        card.removeClass('unread');
                        card.attr('data-read', '1');
                        card.find('.mark-read-btn').remove();
                        card.find('.badge.bg-danger').remove();
                        
                        // Mettre à jour le compteur dans l'en-tête
                        updateNotificationCount();
                    }
                }
            });
        };
        
        // Marquer toutes comme lues
        window.markAllAsRead = function() {
            if (!confirm('Marquer toutes les notifications comme lues ?')) return;
            
            $.ajax({
                url: 'ajax/notifications.php',
                type: 'POST',
                data: {
                    action: 'mark_all_read'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('.notification-card').each(function() {
                            $(this).removeClass('unread');
                            $(this).attr('data-read', '1');
                            $(this).find('.mark-read-btn').remove();
                            $(this).find('.badge.bg-danger').remove();
                        });
                        
                        // Mettre à jour le compteur dans l'en-tête
                        updateNotificationCount();
                        
                        alert('Toutes les notifications ont été marquées comme lues.');
                    }
                }
            });
        };
        
        // Supprimer une notification
        window.deleteNotification = function(notificationId, button) {
            if (!confirm('Supprimer cette notification ?')) return;
            
            $.ajax({
                url: 'ajax/notifications.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    id: notificationId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $(button).closest('.notification-card').fadeOut(300, function() {
                            $(this).remove();
                            // Mettre à jour le compteur
                            updateNotificationCount();
                        });
                    }
                }
            });
        };
        
        // Supprimer toutes les notifications lues
        window.deleteAllRead = function() {
            if (!confirm('Supprimer toutes les notifications lues ?')) return;
            
            $.ajax({
                url: 'ajax/notifications.php',
                type: 'POST',
                data: {
                    action: 'delete_all_read'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('.notification-card[data-read="1"]').fadeOut(300, function() {
                            $(this).remove();
                            // Mettre à jour le compteur
                            updateNotificationCount();
                        });
                        
                        alert('Toutes les notifications lues ont été supprimées.');
                    }
                }
            });
        };
        
        // Mettre à jour le compteur de notifications
        function updateNotificationCount() {
            $.ajax({
                url: 'ajax/notifications.php?action=get_count',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Mettre à jour le badge dans l'en-tête
                        const badge = $('.notification-badge');
                        if (response.unread_count > 0) {
                            const count = response.unread_count > 9 ? '9+' : response.unread_count;
                            if (badge.length) {
                                badge.text(count);
                            } else {
                                $('#notificationsDropdown').append(`
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                                        ${count}
                                    </span>
                                `);
                            }
                        } else {
                            badge.remove();
                        }
                        
                        // Mettre à jour le texte dans la page
                        const total = response.total_count || 0;
                        const unread = response.unread_count || 0;
                        const text = total + ' notification' + (total > 1 ? 's' : '');
                        const unreadText = unread > 0 ? 
                            ' • <span class="text-primary">' + unread + ' non lu' + (unread > 1 ? 's' : '') + '</span>' : '';
                        
                        $('.main-content h1 + p').html(text + unreadText);
                    }
                }
            });
        }
        
        // Auto-refresh toutes les 30 secondes
        setInterval(updateNotificationCount, 30000);
    });
    
    // Fonction helper pour le temps écoulé
    function getTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);
        
        if (diffMins < 1) return 'À l\'instant';
        if (diffMins < 60) return `Il y a ${diffMins} min`;
        if (diffHours < 24) return `Il y a ${diffHours} h`;
        if (diffDays < 7) return `Il y a ${diffDays} j`;
        return date.toLocaleDateString('fr-FR');
    }
    </script>
</body>
</html>
