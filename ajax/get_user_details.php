<?php
// ajax/get_user_details.php
require_once '../config/database.php';

// Vérifier l'authentification et les permissions
if (!isset($_SESSION['user_id'])) {
    die('Non autorisé');
}

// Vérifier les permissions (seul l'admin peut voir les détails)
$stmt = $pdo->prepare("SELECT role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'admin') {
    die('Accès non autorisé');
}

$userId = $_GET['id'] ?? null;

if (!$userId) {
    die('ID utilisateur manquant');
}

try {
    // Récupérer les informations de l'utilisateur
    $stmt = $pdo->prepare("
        SELECT u.*,
               (SELECT COUNT(*) FROM consultations WHERE docteur_id = u.id) as consultations_total,
               (SELECT COUNT(*) FROM consultations WHERE docteur_id = u.id AND DATE(date_consultation) = CURDATE()) as consultations_today,
               (SELECT COUNT(*) FROM patients WHERE created_by = u.id) as patients_total,
               (SELECT COUNT(*) FROM rendez_vous WHERE docteur_id = u.id AND statut = 'confirme') as rdv_total,
               (SELECT COUNT(*) FROM prescriptions WHERE docteur_id = u.id) as prescriptions_total
        FROM utilisateurs u 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        die('Utilisateur non trouvé');
    }

    // Récupérer l'historique de connexion
    $loginStmt = $pdo->prepare("
        SELECT * FROM login_logs 
        WHERE user_id = ? 
        ORDER BY login_time DESC 
        LIMIT 1
    ");
    $loginStmt->execute([$userId]);
    $loginHistory = $loginStmt->fetchAll();

    // Récupérer les dernières actions d'audit
    $auditStmt = $pdo->prepare("
        SELECT * FROM audit_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $auditStmt->execute([$userId]);
    $auditHistory = $auditStmt->fetchAll();

    // Déterminer les couleurs et badges
    $roleColor = $user['role'] == 'admin' ? 'danger' : 
                ($user['role'] == 'docteur' ? 'primary' : 
                ($user['role'] == 'secretaire' ? 'success' : 'warning'));
    
    $statusColor = $user['statut'] == 'actif' ? 'success' : 
                  ($user['statut'] == 'inactif' ? 'secondary' : 'danger');
    
    $lastLogin = !empty($user['derniere_connexion']) 
        ? date('d/m/Y H:i', strtotime($user['derniere_connexion'])) 
        : 'Jamais';

    // Calculer l'activité récente
    $activityStatus = 'Inactif';
    if (!empty($user['derniere_connexion'])) {
        $lastLoginTime = strtotime($user['derniere_connexion']);
        $hoursSinceLogin = floor((time() - $lastLoginTime) / 3600);
        
        if ($hoursSinceLogin < 1) {
            $activityStatus = '<span class="text-success"><i class="fas fa-circle me-1"></i>En ligne</span>';
        } elseif ($hoursSinceLogin < 24) {
            $activityStatus = '<span class="text-primary"><i class="fas fa-clock me-1"></i>Actif aujourd\'hui</span>';
        } elseif ($hoursSinceLogin < 168) {
            $activityStatus = '<span class="text-warning"><i class="fas fa-clock me-1"></i>Actif cette semaine</span>';
        } else {
            $activityStatus = '<span class="text-secondary"><i class="fas fa-clock me-1"></i>Inactif depuis longtemps</span>';
        }
    }

?>
<div class="row">
    <!-- Informations principales -->
    <div class="col-md-4">
        <div class="text-center mb-4">
            <div class="avatar-details mx-auto mb-3">
                <?php echo strtoupper(substr($user['prenom'] ?? '', 0, 1) . substr($user['nom'] ?? '', 0, 1)); ?>
            </div>
            <h4><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></h4>
            <p class="text-muted mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
            
            <div class="mt-3">
                <span class="badge bg-<?php echo $roleColor; ?> me-2">
                    <?php echo ucfirst($user['role']); ?>
                </span>
                <span class="badge bg-<?php echo $statusColor; ?>">
                    <?php echo ucfirst($user['statut']); ?>
                </span>
            </div>
            
            <?php if ($user['specialite']): ?>
            <div class="mt-2">
                <span class="badge bg-info">
                    <i class="fas fa-stethoscope me-1"></i>
                    <?php echo htmlspecialchars($user['specialite']); ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Statistiques rapides -->
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistiques</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <?php if ($user['role'] == 'docteur'): ?>
                    <div class="col-6 mb-3">
                        <div class="h4 text-primary mb-1"><?php echo $user['consultations_total']; ?></div>
                        <small class="text-muted">Consultations</small>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="h4 text-success mb-1"><?php echo $user['patients_total']; ?></div>
                        <small class="text-muted">Patients</small>
                    </div>
                    <div class="col-6">
                        <div class="h4 text-warning mb-1"><?php echo $user['rdv_total']; ?></div>
                        <small class="text-muted">Rendez-vous</small>
                    </div>
                    <div class="col-6">
                        <div class="h4 text-info mb-1"><?php echo $user['prescriptions_total']; ?></div>
                        <small class="text-muted">Ordonnances</small>
                    </div>
                    <?php elseif ($user['role'] == 'secretaire'): ?>
                    <div class="col-12 mb-3">
                        <div class="h4 text-primary mb-1"><?php echo $user['patients_total']; ?></div>
                        <small class="text-muted">Patients créés</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Informations détaillées -->
    <div class="col-md-8">
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th class="text-muted">Téléphone:</th>
                                <td><?php echo htmlspecialchars($user['telephone'] ?: 'Non renseigné'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Activité:</th>
                                <td><?php echo $activityStatus; ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Dernière connexion:</th>
                                <td><?php echo $lastLogin; ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Date de création:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($user['date_creation'])); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Dernière modification:</th>
                                <td><?php echo !empty($user['date_modification']) ? date('d/m/Y H:i', strtotime($user['date_modification'])) : 'Jamais'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Activité aujourd'hui</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($user['role'] == 'docteur'): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Consultations:</span>
                                <strong class="text-primary"><?php echo $user['consultations_today']; ?></strong>
                            </div>
                            <div class="progress mt-1" style="height: 8px;">
                                <div class="progress-bar bg-primary" style="width: <?php echo min($user['consultations_today'] * 20, 100); ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Statut du compte:</span>
                                <strong class="text-<?php echo $statusColor; ?>">
                                    <?php echo ucfirst($user['statut']); ?>
                                </strong>
                            </div>
                        </div>
                        
                        <div>
                            <div class="d-flex justify-content-between">
                                <span>Heures de travail estimées:</span>
                                <strong><?php echo ($user['consultations_total'] * 0.5); ?>h</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historique de connexion -->
            <?php if (!empty($loginHistory)): ?>
            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Dernières connexions</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date & Heure</th>
                                        <th>Adresse IP</th>
                                        <th>Résultat</th>
                                        <th>Navigateur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loginHistory as $login): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($login['login_time'])); ?></td>
                                        <td><code><?php echo htmlspecialchars($login['ip_address'] ?? 'N/A'); ?></code></td>
                                        <td>
                                            <?php if ($login['success']): ?>
                                            <span class="badge bg-success">Succès</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">Échec</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php 
                                                    $userAgent = $login['user_agent'] ?? '';
                                                    $browser = 'Inconnu';
                                                    
                                                    if (strpos($userAgent, 'Chrome') !== false) $browser = 'Chrome';
                                                    elseif (strpos($userAgent, 'Firefox') !== false) $browser = 'Firefox';
                                                    elseif (strpos($userAgent, 'Safari') !== false) $browser = 'Safari';
                                                    elseif (strpos($userAgent, 'Edge') !== false) $browser = 'Edge';
                                                    
                                                    echo htmlspecialchars($browser);
                                                ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Historique d'audit -->
            <?php if (!empty($auditHistory)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Dernières actions</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Action</th>
                                        <th>Table</th>
                                        <th>ID Enregistrement</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($auditHistory as $audit): ?>
                                    <tr>
                                        <td><?php echo date('H:i', strtotime($audit['created_at'])); ?></td>
                                        <td>
                                            <?php 
                                                $actionColor = 'secondary';
                                                if ($audit['action'] == 'CREATE') $actionColor = 'success';
                                                elseif ($audit['action'] == 'UPDATE') $actionColor = 'warning';
                                                elseif ($audit['action'] == 'DELETE') $actionColor = 'danger';
                                            ?>
                                            <span class="badge bg-<?php echo $actionColor; ?>">
                                                <?php echo htmlspecialchars($audit['action']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($audit['table_name'] ?? 'N/A'); ?></td>
                                        <td><code>#<?php echo htmlspecialchars($audit['record_id'] ?? 'N/A'); ?></code></td>
                                        <td>
                                            <small class="text-muted">
                                                <?php 
                                                    $description = $audit['description'] ?? $audit['details'] ?? 'N/A';
                                                    echo htmlspecialchars(substr($description, 0, 50));
                                                    if (strlen($description) > 50) echo '...';
                                                ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="mt-3 text-end">
    <a href="../admin/utilisateurs.php?action=edit&id=<?php echo $userId; ?>" 
       class="btn btn-primary btn-sm">
        <i class="fas fa-edit me-1"></i>Modifier
    </a>
</div>

<style>
.avatar-details {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: bold;
}

.table th {
    font-weight: 500;
    width: 140px;
}

.progress {
    border-radius: 4px;
}
</style>
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erreur: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>