<?php
// assistant/taches.php
require_once '../config/database.php';
checkRole('assistant');

$title = 'Gestion des Tâches';

// Variables pour les messages
$success_message = '';
$error_message = '';


// Gestion des actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        
        // Ajouter une tâche
        case 'add_task':
            $titre = cleanInput($_POST['titre']);
            $description = cleanInput($_POST['description'] ?? '');
            $priorite = cleanInput($_POST['priorite'] ?? 'moyenne');
            $date_echeance = $_POST['date_echeance'] ?? null;
            $assigned_to = $_POST['assigned_to'] ?? null;
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO taches 
                    (titre, description, priorite, date_echeance, assigned_to, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $titre, $description, $priorite, $date_echeance, $assigned_to, $_SESSION['user_id']
                ]);
                
                $success_message = "Tâche ajoutée avec succès !";
                
            } catch (PDOException $e) {
                error_log("Erreur ajout tâche: " . $e->getMessage());
                $error_message = "Erreur lors de l'ajout de la tâche.";
            }
            break;
            
        // Mettre à jour une tâche
        case 'update_task':
            $task_id = $_POST['task_id'];
            $statut = cleanInput($_POST['statut']);
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE taches 
                    SET statut = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                $stmt->execute([$statut, $task_id]);
                
                // Ajouter un commentaire si fourni
                if (!empty($_POST['commentaire'])) {
                    $comment = cleanInput($_POST['commentaire']);
                    $commentStmt = $pdo->prepare("
                        INSERT INTO tache_comments 
                        (tache_id, commentaire, created_by, created_at) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    $commentStmt->execute([$task_id, $comment, $_SESSION['user_id']]);
                }
                
                $success_message = "Tâche mise à jour avec succès !";
                
            } catch (PDOException $e) {
                error_log("Erreur mise à jour tâche: " . $e->getMessage());
                $error_message = "Erreur lors de la mise à jour de la tâche.";
            }
            break;
            
        // Supprimer une tâche
        case 'delete_task':
            $task_id = $_POST['task_id'];
            
            try {
                $stmt = $pdo->prepare("UPDATE taches SET statut = 'supprime', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$task_id]);
                
                $success_message = "Tâche supprimée avec succès !";
                
            } catch (PDOException $e) {
                error_log("Erreur suppression tâche: " . $e->getMessage());
                $error_message = "Erreur lors de la suppression de la tâche.";
            }
            break;
            
        // Ajouter un commentaire
        case 'add_comment':
            $task_id = $_POST['task_id'];
            $commentaire = cleanInput($_POST['commentaire']);
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO tache_comments 
                    (tache_id, commentaire, created_by, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                
                $stmt->execute([$task_id, $commentaire, $_SESSION['user_id']]);
                
                $success_message = "Commentaire ajouté avec succès !";
                
            } catch (PDOException $e) {
                error_log("Erreur ajout commentaire: " . $e->getMessage());
                $error_message = "Erreur lors de l'ajout du commentaire.";
            }
            break;
    }
}

// Récupérer les filtres
$filter_status = $_GET['status'] ?? 'tous';
$filter_priority = $_GET['priority'] ?? 'tous';
$filter_assigned = $_GET['assigned'] ?? 'tous';
$filter_date = $_GET['date'] ?? '';

// Construire la requête
$query = "
    SELECT t.*,
           CONCAT(u1.prenom, ' ', u1.nom) as created_by_name,
           CONCAT(u2.prenom, ' ', u2.nom) as assigned_to_name,
           (SELECT COUNT(*) FROM tache_comments WHERE tache_id = t.id) as comments_count
    FROM taches t
    LEFT JOIN utilisateurs u1 ON t.created_by = u1.id
    LEFT JOIN utilisateurs u2 ON t.assigned_to = u2.id
    WHERE t.statut != 'supprime'
";

$params = [];

// Appliquer les filtres
if ($filter_status != 'tous' && $filter_status != '') {
    $query .= " AND t.statut = ?";
    $params[] = $filter_status;
}

if ($filter_priority != 'tous' && $filter_priority != '') {
    $query .= " AND t.priorite = ?";
    $params[] = $filter_priority;
}

if ($filter_assigned != 'tous' && $filter_assigned != '') {
    if ($filter_assigned == 'moi') {
        $query .= " AND (t.assigned_to = ? OR t.created_by = ?)";
        $params[] = $_SESSION['user_id'];
        $params[] = $_SESSION['user_id'];
    } else {
        $query .= " AND t.assigned_to = ?";
        $params[] = $filter_assigned;
    }
}

if ($filter_date) {
    $query .= " AND DATE(t.date_echeance) = ?";
    $params[] = $filter_date;
}

$query .= " ORDER BY 
    CASE t.priorite 
        WHEN 'haute' THEN 1
        WHEN 'moyenne' THEN 2
        WHEN 'basse' THEN 3
        ELSE 4
    END,
    t.date_echeance ASC,
    t.created_at DESC";

// Exécuter la requête
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
} catch (Exception $e) {
    $tasks = [];
    error_log("Erreur récupération tâches: " . $e->getMessage());
}

// Récupérer la liste des utilisateurs pour l'assignation
$users_stmt = $pdo->query("
    SELECT id, nom, prenom, role 
    FROM utilisateurs 
    WHERE statut = 'actif' 
    AND role IN ('docteur', 'assistant', 'secretaire')
    ORDER BY nom, prenom
");
$users = $users_stmt->fetchAll();

// Récupérer les statistiques
try {
    $stats_stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM taches WHERE statut = 'a_faire' AND assigned_to = ?) as my_todo,
            (SELECT COUNT(*) FROM taches WHERE statut = 'en_cours' AND assigned_to = ?) as my_in_progress,
            (SELECT COUNT(*) FROM taches WHERE statut = 'termine' AND assigned_to = ?) as my_done,
            (SELECT COUNT(*) FROM taches WHERE statut != 'supprime') as total_tasks,
            (SELECT COUNT(*) FROM taches WHERE date_echeance = CURDATE() AND statut IN ('a_faire', 'en_cours')) as due_today,
            (SELECT COUNT(*) FROM taches WHERE priorite = 'haute' AND statut IN ('a_faire', 'en_cours')) as high_priority,
            (SELECT COUNT(*) FROM taches WHERE assigned_to IS NULL AND statut IN ('a_faire', 'en_cours')) as unassigned
    ");
    $stats_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $stats = $stats_stmt->fetch();
} catch (Exception $e) {
    $stats = [];
}

// Fonctions utilitaires
function getPriorityColor($priority) {
    switch($priority) {
        case 'haute': return 'danger';
        case 'moyenne': return 'warning';
        case 'basse': return 'success';
        default: return 'secondary';
    }
}

function getStatusColor($status) {
    switch($status) {
        case 'a_faire': return 'warning';
        case 'en_cours': return 'info';
        case 'termine': return 'success';
        case 'annule': return 'danger';
        default: return 'secondary';
    }
}

function formatDate($date, $show_time = false) {
    if (!$date) return '-';
    $format = $show_time ? 'd/m/Y H:i' : 'd/m/Y';
    return date($format, strtotime($date));
}

function isOverdue($date_echeance, $statut) {
    if (!$date_echeance || $statut == 'termine' || $statut == 'annule') {
        return false;
    }
    $due_date = strtotime($date_echeance);
    $today = strtotime(date('Y-m-d'));
    return $due_date < $today;
}

require_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
        <div>
            <h1 class="h2 mb-0">
                <i class="fas fa-tasks me-2"></i>Gestion des Tâches
            </h1>
            <p class="text-muted mb-0">
                Organisation et suivi des activités de l'équipe
            </p>
        </div>
        <div class="btn-toolbar">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                <i class="fas fa-plus me-1"></i>Nouvelle tâche
            </button>
        </div>
    </div>

    <!-- Messages d'alerte -->
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-6 mb-4">
            <div class="card border-start border-primary border-4 shadow-sm">
                <div class="card-body py-3">
                    <div class="text-center">
                        <div class="h2 mb-1"><?php echo $stats['my_todo'] ?? 0; ?></div>
                        <div class="small text-muted">À faire</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-4">
            <div class="card border-start border-info border-4 shadow-sm">
                <div class="card-body py-3">
                    <div class="text-center">
                        <div class="h2 mb-1"><?php echo $stats['my_in_progress'] ?? 0; ?></div>
                        <div class="small text-muted">En cours</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-4">
            <div class="card border-start border-success border-4 shadow-sm">
                <div class="card-body py-3">
                    <div class="text-center">
                        <div class="h2 mb-1"><?php echo $stats['my_done'] ?? 0; ?></div>
                        <div class="small text-muted">Terminées</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-4">
            <div class="card border-start border-danger border-4 shadow-sm">
                <div class="card-body py-3">
                    <div class="text-center">
                        <div class="h2 mb-1"><?php echo $stats['due_today'] ?? 0; ?></div>
                        <div class="small text-muted">Échéance aujourd'hui</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-4">
            <div class="card border-start border-warning border-4 shadow-sm">
                <div class="card-body py-3">
                    <div class="text-center">
                        <div class="h2 mb-1"><?php echo $stats['high_priority'] ?? 0; ?></div>
                        <div class="small text-muted">Haute priorité</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-6 mb-4">
            <div class="card border-start border-secondary border-4 shadow-sm">
                <div class="card-body py-3">
                    <div class="text-center">
                        <div class="h2 mb-1"><?php echo $stats['unassigned'] ?? 0; ?></div>
                        <div class="small text-muted">Non assignées</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0">
                <i class="fas fa-filter me-2"></i>Filtres
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Statut</label>
                    <select class="form-select" name="status">
                        <option value="tous" <?php echo $filter_status == 'tous' ? 'selected' : ''; ?>>Tous les statuts</option>
                        <option value="a_faire" <?php echo $filter_status == 'a_faire' ? 'selected' : ''; ?>>À faire</option>
                        <option value="en_cours" <?php echo $filter_status == 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                        <option value="termine" <?php echo $filter_status == 'termine' ? 'selected' : ''; ?>>Terminé</option>
                        <option value="annule" <?php echo $filter_status == 'annule' ? 'selected' : ''; ?>>Annulé</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Priorité</label>
                    <select class="form-select" name="priority">
                        <option value="tous" <?php echo $filter_priority == 'tous' ? 'selected' : ''; ?>>Toutes</option>
                        <option value="haute" <?php echo $filter_priority == 'haute' ? 'selected' : ''; ?>>Haute</option>
                        <option value="moyenne" <?php echo $filter_priority == 'moyenne' ? 'selected' : ''; ?>>Moyenne</option>
                        <option value="basse" <?php echo $filter_priority == 'basse' ? 'selected' : ''; ?>>Basse</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Assignée à</label>
                    <select class="form-select" name="assigned">
                        <option value="tous" <?php echo $filter_assigned == 'tous' ? 'selected' : ''; ?>>Tous</option>
                        <option value="moi" <?php echo $filter_assigned == 'moi' ? 'selected' : ''; ?>>À moi</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $filter_assigned == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?> (<?php echo $user['role']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Date échéance</label>
                    <input type="date" class="form-control" name="date" value="<?php echo $filter_date; ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i>Filtrer
                    </button>
                    <a href="taches.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des tâches -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>Tâches
                        <span class="badge bg-secondary ms-2"><?php echo count($tasks); ?></span>
                    </h6>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshTasks()">
                            <i class="fas fa-sync"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($tasks)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-muted">Aucune tâche trouvée</h5>
                        <p class="text-muted">Toutes les tâches sont terminées ou aucun filtre ne correspond.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                            <i class="fas fa-plus me-1"></i>Créer une nouvelle tâche
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($tasks as $task): 
                            $overdue = isOverdue($task['date_echeance'], $task['statut']);
                        ?>
                        <div class="list-group-item border-0 <?php echo $overdue ? 'bg-danger-light' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <input type="checkbox" 
                                               class="form-check-input me-3 task-checkbox" 
                                               data-task-id="<?php echo $task['id']; ?>"
                                               <?php echo $task['statut'] == 'termine' ? 'checked' : ''; ?>>
                                        <h6 class="mb-0 flex-grow-1">
                                            <?php echo htmlspecialchars($task['titre']); ?>
                                            <?php if ($overdue): ?>
                                            <span class="badge bg-danger ms-2">En retard</span>
                                            <?php endif; ?>
                                        </h6>
                                        <div class="ms-3">
                                            <span class="badge bg-<?php echo getPriorityColor($task['priorite']); ?> me-2">
                                                <?php echo ucfirst($task['priorite']); ?>
                                            </span>
                                            <span class="badge bg-<?php echo getStatusColor($task['statut']); ?>">
                                                <?php echo ucfirst($task['statut']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($task['description']): ?>
                                    <p class="small text-muted mb-2">
                                        <?php echo htmlspecialchars($task['description']); ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="small text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?php if ($task['assigned_to_name']): ?>
                                            Assignée à: <?php echo htmlspecialchars($task['assigned_to_name']); ?>
                                            <?php else: ?>
                                            Non assignée
                                            <?php endif; ?>
                                            <span class="mx-2">•</span>
                                            <i class="fas fa-calendar me-1"></i>
                                            Échéance: <?php echo formatDate($task['date_echeance']); ?>
                                            <span class="mx-2">•</span>
                                            <i class="fas fa-comment me-1"></i>
                                            <?php echo $task['comments_count']; ?> commentaire(s)
                                        </div>
                                        
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="viewTask(<?php echo $task['id']; ?>)"
                                                    data-bs-toggle="tooltip" title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-warning" 
                                                    onclick="editTask(<?php echo $task['id']; ?>)"
                                                    data-bs-toggle="tooltip" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($task['statut'] != 'termine' && $task['statut'] != 'annule'): ?>
                                            <button type="button" class="btn btn-outline-success" 
                                                    onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'termine')"
                                                    data-bs-toggle="tooltip" title="Marquer comme terminé">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteTask(<?php echo $task['id']; ?>)"
                                                    data-bs-toggle="tooltip" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
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
        
        <div class="col-lg-4">
            <!-- Mes tâches -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0">
                        <i class="fas fa-user-circle me-2"></i>Mes tâches
                    </h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php 
                        try {
                            $myTasksStmt = $pdo->prepare("
                                SELECT * FROM taches 
                                WHERE assigned_to = ? 
                                AND statut IN ('a_faire', 'en_cours')
                                ORDER BY 
                                    CASE priorite 
                                        WHEN 'haute' THEN 1
                                        WHEN 'moyenne' THEN 2
                                        WHEN 'basse' THEN 3
                                        ELSE 4
                                    END,
                                    date_echeance ASC
                                LIMIT 5
                            ");
                            $myTasksStmt->execute([$_SESSION['user_id']]);
                            $my_tasks = $myTasksStmt->fetchAll();
                        } catch (Exception $e) {
                            $my_tasks = [];
                        }
                        ?>
                        
                        <?php if (empty($my_tasks)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted small">Aucune tâche en cours</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($my_tasks as $task): ?>
                        <div class="list-group-item border-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold small"><?php echo htmlspecialchars($task['titre']); ?></div>
                                    <div class="small text-muted">
                                        <?php echo formatDate($task['date_echeance']); ?>
                                        <span class="badge bg-<?php echo getPriorityColor($task['priorite']); ?> ms-2">
                                            <?php echo $task['priorite']; ?>
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-success"
                                            onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'termine')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-3">
                        <a href="taches.php?assigned=moi" class="btn btn-outline-primary w-100">
                            <i class="fas fa-list me-1"></i>Voir toutes mes tâches
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Échéances proches -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>Échéances proches
                    </h6>
                </div>
                <div class="card-body">
                    <?php 
                    try {
                        $upcomingStmt = $pdo->prepare("
                            SELECT t.*, 
                                   CONCAT(u.prenom, ' ', u.nom) as assigned_to_name
                            FROM taches t
                            LEFT JOIN utilisateurs u ON t.assigned_to = u.id
                            WHERE t.date_echeance >= CURDATE()
                            AND t.statut IN ('a_faire', 'en_cours')
                            ORDER BY t.date_echeance ASC
                            LIMIT 5
                        ");
                        $upcomingStmt->execute();
                        $upcoming_tasks = $upcomingStmt->fetchAll();
                    } catch (Exception $e) {
                        $upcoming_tasks = [];
                    }
                    ?>
                    
                    <?php if (empty($upcoming_tasks)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                        <p class="text-muted small">Aucune échéance proche</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcoming_tasks as $task): ?>
                        <div class="list-group-item border-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold small"><?php echo htmlspecialchars($task['titre']); ?></div>
                                    <div class="small text-muted">
                                        <div>
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo $task['assigned_to_name'] ? htmlspecialchars($task['assigned_to_name']) : 'Non assignée'; ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo formatDate($task['date_echeance']); ?>
                                            <?php if (date('Y-m-d', strtotime($task['date_echeance'])) == date('Y-m-d')): ?>
                                            <span class="badge bg-warning ms-2">Aujourd'hui</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="badge bg-<?php echo getPriorityColor($task['priorite']); ?>">
                                    <?php echo $task['priorite']; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajout tâche -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouvelle tâche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Titre *</label>
                        <input type="text" class="form-control" name="titre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priorité</label>
                            <select class="form-select" name="priorite">
                                <option value="haute">Haute</option>
                                <option value="moyenne" selected>Moyenne</option>
                                <option value="basse">Basse</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date d'échéance</label>
                            <input type="date" class="form-control" name="date_echeance">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Assigner à</label>
                        <select class="form-select" name="assigned_to">
                            <option value="">Non assignée</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?> (<?php echo $user['role']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="add_task">
                        Créer la tâche
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Voir tâche -->
<div class="modal fade" id="viewTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de la tâche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="taskDetails">
                <!-- Rempli par JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Mettre à jour statut -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mettre à jour la tâche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="task_id" id="update_task_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Statut *</label>
                        <select class="form-select" name="statut" id="update_status" required>
                            <option value="a_faire">À faire</option>
                            <option value="en_cours">En cours</option>
                            <option value="termine">Terminé</option>
                            <option value="annule">Annulé</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" name="commentaire" rows="3" placeholder="Ajouter un commentaire..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="update_task">
                        Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function refreshTasks() {
    location.reload();
}

function viewTask(taskId) {
    fetch('ajax/get_task.php?id=' + taskId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const task = data.task;
                const details = `
                    <div class="mb-4">
                        <h4 class="mb-2">${task.titre}</h4>
                        <div class="d-flex gap-2 mb-3">
                            <span class="badge bg-${getPriorityColor(task.priorite)}">
                                ${task.priorite}
                            </span>
                            <span class="badge bg-${getStatusColor(task.statut)}">
                                ${task.statut}
                            </span>
                        </div>
                        
                        ${task.description ? `<p class="text-muted">${task.description}</p>` : ''}
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong><i class="fas fa-user me-2"></i>Créée par:</strong>
                                    <span class="ms-2">${task.created_by_name || 'Inconnu'}</span>
                                </div>
                                <div class="mb-2">
                                    <strong><i class="fas fa-calendar-plus me-2"></i>Date création:</strong>
                                    <span class="ms-2">${formatDate(task.created_at, true)}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong><i class="fas fa-user-tag me-2"></i>Assignée à:</strong>
                                    <span class="ms-2">${task.assigned_to_name || 'Non assignée'}</span>
                                </div>
                                <div class="mb-2">
                                    <strong><i class="fas fa-calendar-day me-2"></i>Date échéance:</strong>
                                    <span class="ms-2">${formatDate(task.date_echeance)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="comments-section">
                        <h6 class="mb-3"><i class="fas fa-comments me-2"></i>Commentaires</h6>
                        <div id="commentsList">
                            ${data.comments && data.comments.length > 0 ? 
                                data.comments.map(comment => `
                                    <div class="comment mb-3 p-3 bg-light rounded">
                                        <div class="d-flex justify-content-between mb-2">
                                            <strong>${comment.created_by_name}</strong>
                                            <small class="text-muted">${formatDate(comment.created_at, true)}</small>
                                        </div>
                                        <p class="mb-0">${comment.commentaire}</p>
                                    </div>
                                `).join('') : 
                                '<p class="text-muted">Aucun commentaire</p>'
                            }
                        </div>
                        
                        <div class="mt-4">
                            <h6 class="mb-3">Ajouter un commentaire</h6>
                            <form id="addCommentForm">
                                <input type="hidden" name="task_id" value="${taskId}">
                                <div class="mb-3">
                                    <textarea class="form-control" name="commentaire" rows="3" required></textarea>
                                </div>
                                <button type="button" class="btn btn-primary" onclick="submitComment(${taskId})">
                                    <i class="fas fa-paper-plane me-1"></i>Envoyer
                                </button>
                            </form>
                        </div>
                    </div>
                `;
                
                document.getElementById('taskDetails').innerHTML = details;
                const modal = new bootstrap.Modal(document.getElementById('viewTaskModal'));
                modal.show();
            } else {
                alert('Erreur lors du chargement de la tâche');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors du chargement de la tâche');
        });
}

function updateTaskStatus(taskId, status = null) {
    document.getElementById('update_task_id').value = taskId;
    if (status) {
        document.getElementById('update_status').value = status;
    }
    const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
    modal.show();
}

function deleteTask(taskId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette tâche ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'task_id';
        inputId.value = taskId;
        
        const inputAction = document.createElement('input');
        inputAction.type = 'hidden';
        inputAction.name = 'action';
        inputAction.value = 'delete_task';
        
        form.appendChild(inputId);
        form.appendChild(inputAction);
        document.body.appendChild(form);
        form.submit();
    }
}

function submitComment(taskId) {
    const form = document.getElementById('addCommentForm');
    const formData = new FormData(form);
    formData.append('action', 'add_comment');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        location.reload();
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de l\'ajout du commentaire');
    });
}

// Gérer les cases à cocher
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.task-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const taskId = this.dataset.taskId;
            const status = this.checked ? 'termine' : 'a_faire';
            
            const formData = new FormData();
            formData.append('action', 'update_task');
            formData.append('task_id', taskId);
            formData.append('statut', status);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                // Mettre à jour l'interface
                const badge = this.closest('.list-group-item').querySelector('.badge.bg-warning, .badge.bg-info');
                if (badge) {
                    badge.textContent = status === 'termine' ? 'Terminé' : 'À faire';
                    badge.className = `badge bg-${status === 'termine' ? 'success' : 'warning'}`;
                }
            })
            .catch(error => console.error('Erreur:', error));
        });
    });
    
    // Initialiser les tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
});

// Fonctions utilitaires
function getPriorityColor(priority) {
    switch(priority) {
        case 'haute': return 'danger';
        case 'moyenne': return 'warning';
        case 'basse': return 'success';
        default: return 'secondary';
    }
}

function getStatusColor(status) {
    switch(status) {
        case 'a_faire': return 'warning';
        case 'en_cours': return 'info';
        case 'termine': return 'success';
        case 'annule': return 'danger';
        default: return 'secondary';
    }
}

function formatDate(dateString, showTime = false) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const options = { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit',
        hour: showTime ? '2-digit' : undefined,
        minute: showTime ? '2-digit' : undefined
    };
    return date.toLocaleDateString('fr-FR', options);
}
</script>

<style>
.bg-danger-light {
    background-color: rgba(220, 53, 69, 0.1);
}

.task-checkbox {
    transform: scale(1.2);
}

.task-checkbox:checked {
    background-color: #28a745;
    border-color: #28a745;
}

.list-group-item {
    transition: all 0.3s ease;
}

.list-group-item:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.comment {
    border-left: 3px solid #4361ee;
}
</style>

<?php require_once '../includes/footer.php'; ?>