<?php
// notifications.php
require_once '../config/database.php';
require_once '../includes/header.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = Database::getInstance()->getConnection();
$title = 'Notifications';
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];


// --- Gestion des requêtes AJAX (CRUD intégré) ---
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'mark_read':
                $id = (int)$_POST['id'];
                // Vérifier l'appartenance
                if ($role === 'admin') {
                    $check = $pdo->prepare("SELECT id FROM notifications WHERE id = ? AND (user_id IS NULL OR user_id = ?)");
                    $check->execute([$id, $user_id]);
                } else {
                    $check = $pdo->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
                    $check->execute([$id, $user_id]);
                }
                if ($check->rowCount()) {
                    $stmt = $pdo->prepare("UPDATE notifications SET lu = 1 WHERE id = ?");
                    $stmt->execute([$id]);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Accès interdit']);
                }
                break;
                
            case 'mark_all_read':
                if ($role === 'admin') {
                    $stmt = $pdo->prepare("UPDATE notifications SET lu = 1 WHERE (user_id IS NULL OR user_id = ?) AND lu = 0");
                    $stmt->execute([$user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE notifications SET lu = 1 WHERE user_id = ? AND lu = 0");
                    $stmt->execute([$user_id]);
                }
                echo json_encode(['success' => true]);
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                if ($role === 'admin') {
                    $check = $pdo->prepare("SELECT id FROM notifications WHERE id = ? AND (user_id IS NULL OR user_id = ?)");
                    $check->execute([$id, $user_id]);
                } else {
                    $check = $pdo->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
                    $check->execute([$id, $user_id]);
                }
                if ($check->rowCount()) {
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
                    $stmt->execute([$id]);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Accès interdit']);
                }
                break;
                
            case 'delete_all_read':
                if ($role === 'admin') {
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND lu = 1");
                    $stmt->execute([$user_id]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND lu = 1");
                    $stmt->execute([$user_id]);
                }
                echo json_encode(['success' => true]);
                break;
                
            case 'get_count':
                if ($role === 'admin') {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as unread FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND lu = 0");
                    $stmt->execute([$user_id]);
                    $unread = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
                    $stmt2 = $pdo->prepare("SELECT COUNT(*) as total FROM notifications WHERE (user_id IS NULL OR user_id = ?)");
                    $stmt2->execute([$user_id]);
                    $total = $stmt2->fetch(PDO::FETCH_ASSOC)['total'];
                } else {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND lu = 0");
                    $stmt->execute([$user_id]);
                    $unread = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
                    $stmt2 = $pdo->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
                    $stmt2->execute([$user_id]);
                    $total = $stmt2->fetch(PDO::FETCH_ASSOC)['total'];
                }
                echo json_encode(['success' => true, 'unread_count' => (int)$unread, 'total_count' => (int)$total]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Action invalide']);
        }
    } catch (PDOException $e) {
        error_log("AJAX notifications error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
    exit;
}

// --- Récupération des notifications ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    if ($role === 'admin') {
        $sql = "SELECT * FROM notifications WHERE (user_id IS NULL OR user_id = ?)";
        $countSql = "SELECT COUNT(*) as total FROM notifications WHERE (user_id IS NULL OR user_id = ?)";
        $params = [$user_id];
        $countParams = [$user_id];
    } else {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $countSql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
        $params = [$user_id];
        $countParams = [$user_id];
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($countParams);
    $totalResult = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $totalNotifications = $totalResult['total'];
    $totalPages = ceil($totalNotifications / $limit);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Marquer toutes comme lues lors de la visite (on ne met pas read_at)
    if ($role === 'admin') {
        $markReadSql = "UPDATE notifications SET lu = 1 WHERE (user_id IS NULL OR user_id = ?) AND lu = 0";
        $markReadStmt = $pdo->prepare($markReadSql);
        $markReadStmt->execute([$user_id]);
    } else {
        $markReadSql = "UPDATE notifications SET lu = 1 WHERE user_id = ? AND lu = 0";
        $markReadStmt = $pdo->prepare($markReadSql);
        $markReadStmt->execute([$user_id]);
    }
    
} catch (PDOException $e) {
    error_log("Erreur notifications page: " . $e->getMessage());
    $notifications = [];
    $totalNotifications = 0;
    $totalPages = 1;
}

// Compter non lues pour l'en-tête
$unreadNotifications = 0;
try {
    if ($role === 'admin') {
        $countSql = "SELECT COUNT(*) as unread_count FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND lu = 0";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$user_id]);
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

// Statistiques par type
$stats = [];
try {
    if ($role === 'admin') {
        $statsSql = "SELECT type, COUNT(*) as count, SUM(CASE WHEN lu = 0 THEN 1 ELSE 0 END) as unread 
                     FROM notifications WHERE (user_id IS NULL OR user_id = ?) 
                     GROUP BY type ORDER BY count DESC";
        $statsParams = [$user_id];
    } else {
        $statsSql = "SELECT type, COUNT(*) as count, SUM(CASE WHEN lu = 0 THEN 1 ELSE 0 END) as unread 
                     FROM notifications WHERE user_id = ? 
                     GROUP BY type ORDER BY count DESC";
        $statsParams = [$user_id];
    }
    $statsStmt = $pdo->prepare($statsSql);
    $statsStmt->execute($statsParams);
    $stats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur statistiques notifications: " . $e->getMessage());
}
?>

<style>
    .notification-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .badge-type { font-size: 0.7rem; padding: 4px 8px; border-radius: 20px; }
    .notification-time { font-size: 0.8rem; color: #6c757d; }
    .filter-btn.active { background-color: var(--primary); color: white; border-color: var(--primary); }
    .stats-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .empty-state { padding: 80px 20px; text-align: center; color: #6c757d; }
    .empty-state i { font-size: 4rem; margin-bottom: 20px; opacity: 0.3; }
    .pagination .page-link { color: var(--primary); }
    .pagination .page-item.active .page-link { background-color: var(--primary); border-color: var(--primary); }
    .type-rdv { background-color: #e3f2fd; color: #1565c0; }
    .type-consultation { background-color: #e8f5e9; color: #2e7d32; }
    .type-patient { background-color: #e0f2f1; color: #00695c; }
    .type-system { background-color: #f3e5f5; color: #7b1fa2; }
    .type-urgence { background-color: #ffebee; color: #c62828; }
    .type-message { background-color: #fff3e0; color: #ef6c00; }
    .type-info { background-color: #e1f5fe; color: #0277bd; }
    .badge-rdv { background-color: #1565c0; }
    .badge-consultation { background-color: #2e7d32; }
    .badge-patient { background-color: #00695c; }
    .badge-system { background-color: #7b1fa2; }
    .badge-urgence { background-color: #c62828; }
    .badge-message { background-color: #ef6c00; }
    .badge-info { background-color: #0277bd; }
    @media (max-width: 768px) {
        .notification-card { margin-bottom: 10px; }
        .notification-icon { width: 40px; height: 40px; font-size: 1.2rem; }
        .stats-card { margin-bottom: 15px; }
    }
</style>
<?php require_once '../includes/header.php'; ?>

<div class="row mb-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold">Notifications</h1>
            <p class="text-muted mb-0">
                <?php echo $totalNotifications . ' notification' . ($totalNotifications > 1 ? 's' : ''); ?>
                <?php if ($unreadNotifications > 0): ?>
                    . <span class="text-primary"><?php echo $unreadNotifications; ?> non lu<?php echo ($unreadNotifications > 1 ? 's' : ''); ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="markAllAsRead()"><i class="fas fa-check-double me-1"></i> Tout marquer comme lu</button>
            <button class="btn btn-outline-danger" onclick="deleteAllRead()"><i class="fas fa-trash me-1"></i> Supprimer les lues</button>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-3 mb-4">
            <div class="stats-card mb-4">
                <h6 class="fw-bold mb-3">Filtres</h6>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button class="btn btn-sm btn-outline-secondary filter-btn active" data-filter="all">Toutes</button>
                    <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="unread">Non lues</button>
                    <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="read">Lues</button>
                </div>
                <hr>
                <h6 class="fw-bold mb-3">Types</h6>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($stats as $stat): 
                        $typeName = getTypeName($stat['type']);
                        $badgeClass = 'badge-' . $stat['type'];
                    ?>
                    <button class="btn btn-sm btn-outline-secondary filter-btn text-start d-flex justify-content-between align-items-center" data-type="<?php echo $stat['type']; ?>">
                        <span><i class="fas fa-circle small me-2 <?php echo $badgeClass; ?>"></i> <?php echo $typeName; ?></span>
                        <span class="badge bg-light text-dark"><?php echo $stat['count']; ?>
                            <?php if ($stat['unread'] > 0): ?>
                                <span class="ms-1 badge bg-danger"><?php echo $stat['unread']; ?></span>
                            <?php endif; ?>
                        </span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            
        </div>

        <div class="col-lg-9">
            <div class="stats-card">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state"><i class="fas fa-bell-slash"></i><h4 class="h5 mb-2">Aucune notification</h4><p class="text-muted">Vous n'avez pas encore de notifications.</p></div>
                <?php else: ?>
                    <div class="mb-4">
                        <input type="text" class="form-control" placeholder="Rechercher dans les notifications..." id="searchNotifications">
                    </div>
                    <div id="notificationsList">
                        <?php foreach ($notifications as $notification): 
                            $unreadClass = $notification['lu'] == 0 ? 'unread' : '';
                            $typeClass = 'type-' . $notification['type'];
                            $badgeClass = 'badge-' . $notification['type'];
                            $typeName = getTypeName($notification['type']);
                            $timeAgo = getTimeAgo($notification['created_at']);
                        ?>
                        <div class="notification-card p-3 bg-white <?php echo $unreadClass; ?>" data-id="<?php echo $notification['id']; ?>" data-type="<?php echo $notification['type']; ?>" data-read="<?php echo $notification['lu']; ?>">
                            <div class="d-flex align-items-start">
                                <div class="notification-icon me-3 <?php echo $typeClass; ?>">
                                    <i class="<?php echo getTypeIcon($notification['type']); ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <h6 class="mb-0 fw-semibold"><?php echo htmlspecialchars($notification['titre']); ?></h6>
                                            <small class="text-muted">De : Système</small>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge badge-type <?php echo $badgeClass; ?>"><?php echo $typeName; ?></span>
                                            <?php if ($notification['lu'] == 0): ?><span class="badge bg-danger">Nouveau</span><?php endif; ?>
                                        </div>
                                    </div>
                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="notification-time"><i class="far fa-clock me-1"></i> <?php echo $timeAgo; ?></small>
                                        <div class="d-flex gap-2">
                                            <?php if ($notification['lu'] == 0): ?>
                                                <button class="btn btn-sm btn-outline-success mark-read-btn" onclick="markAsRead(<?php echo $notification['id']; ?>, this)"><i class="fas fa-check me-1"></i> Marquer comme lu</button>
                                            <?php endif; ?>
                                            <?php if ($notification['lien']): ?>
                                                <!-- <a href="<?php echo htmlspecialchars($notification['lien']); ?>" class="btn btn-sm btn-primary"><i class="fas fa-external-link-alt me-1"></i> Voir</a> -->
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-danger delete-btn" onclick="deleteNotification(<?php echo $notification['id']; ?>, this)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <nav class="mt-4"><ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>"><i class="fas fa-chevron-left"></i></a></li><?php endif; ?>
                        <?php for ($i=1; $i<=$totalPages; $i++): ?>
                            <?php if ($i==$page): ?><li class="page-item active"><span class="page-link"><?php echo $i; ?></span></li>
                            <?php elseif ($i==1 || $i==$totalPages || ($i>=$page-2 && $i<=$page+2)): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                            <?php elseif ($i==$page-3 || $i==$page+3): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>"><i class="fas fa-chevron-right"></i></a></li><?php endif; ?>
                    </ul></nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('.filter-btn').on('click', function() {
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        const filter = $(this).data('filter');
        const type = $(this).data('type');
        if (filter === 'all') $('.notification-card').show();
        else if (filter === 'unread') { $('.notification-card').hide(); $('.notification-card[data-read="0"]').show(); }
        else if (filter === 'read') { $('.notification-card').hide(); $('.notification-card[data-read="1"]').show(); }
        else if (type) { $('.notification-card').hide(); $('.notification-card[data-type="' + type + '"]').show(); }
    });
    $('#searchNotifications').on('keyup', function() {
        const term = $(this).val().toLowerCase();
        $('.notification-card').each(function() {
            const text = $(this).find('h6').text().toLowerCase() + ' ' + $(this).find('p').text().toLowerCase();
            $(this).toggle(text.includes(term));
        });
    });
});

function markAsRead(id, btn) {
    $.post(window.location.href, { action: 'mark_read', id: id }, function(res) {
        if (res.success) {
            const card = $(btn).closest('.notification-card');
            card.removeClass('unread').attr('data-read', '1');
            card.find('.mark-read-btn').remove();
            card.find('.badge.bg-danger').remove();
            updateNotificationCount();
        }
    }, 'json');
}

function markAllAsRead() {
    if (!confirm('Marquer toutes les notifications comme lues ?')) return;
    $.post(window.location.href, { action: 'mark_all_read' }, function(res) {
        if (res.success) {
            $('.notification-card').each(function() {
                $(this).removeClass('unread').attr('data-read', '1');
                $(this).find('.mark-read-btn').remove();
                $(this).find('.badge.bg-danger').remove();
            });
            updateNotificationCount();
            alert('Toutes les notifications ont été marquées comme lues.');
        }
    }, 'json');
}

function deleteNotification(id, btn) {
    if (!confirm('Supprimer cette notification ?')) return;
    $.post(window.location.href, { action: 'delete', id: id }, function(res) {
        if (res.success) {
            $(btn).closest('.notification-card').fadeOut(300, function() { $(this).remove(); updateNotificationCount(); });
        }
    }, 'json');
}

function deleteAllRead() {
    if (!confirm('Supprimer toutes les notifications lues ?')) return;
    $.post(window.location.href, { action: 'delete_all_read' }, function(res) {
        if (res.success) {
            $('.notification-card[data-read="1"]').fadeOut(300, function() { $(this).remove(); updateNotificationCount(); });
            alert('Toutes les notifications lues ont été supprimées.');
        }
    }, 'json');
}

function updateNotificationCount() {
    $.get(window.location.href, { action: 'get_count' }, function(res) {
        if (res.success) {
            const badge = $('.notification-badge');
            if (res.unread_count > 0) {
                const count = res.unread_count > 9 ? '9+' : res.unread_count;
                if (badge.length) badge.text(count);
                else $('#notificationsDropdown').append(`<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">${count}</span>`);
            } else badge.remove();
            const total = res.total_count || 0;
            const unread = res.unread_count || 0;
            $('.main-content h1 + p').html(total + ' notification' + (total>1?'s':'') + (unread>0 ? ' • <span class="text-primary">' + unread + ' non lu' + (unread>1?'s':'') + '</span>' : ''));
        }
    }, 'json');
}
setInterval(updateNotificationCount, 30000);
</script>