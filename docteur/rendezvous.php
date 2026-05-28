<?php
// docteur/rendezvous.php
require_once '../config/database.php';
checkRole('docteur');

$title = 'Gestion des Rendez-vous';
$docteur_id = $_SESSION['user_id'];

// Récupérer la date spécifiée ou aujourd'hui
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$action = $_GET['action'] ?? '';

require_once '../includes/header.php';

// Si action = add, afficher le formulaire d'ajout
if ($action === 'add') {
    include 'forms/add_rendezvous.php';
    require_once '../includes/footer.php';
    exit;
}

// Compter les RDV par statut
$stats_stmt = $pdo->prepare("
    SELECT 
        statut,
        COUNT(*) as count
    FROM rendez_vous
    WHERE docteur_id = ?
    GROUP BY statut
");
$stats_stmt->execute([$docteur_id]);
$stats = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Récupérer les RDV pour la date sélectionnée
$rendezvous_stmt = $pdo->prepare("
    SELECT r.*, 
           p.nom as patient_nom, 
           p.prenom as patient_prenom, 
           p.telephone, 
           p.date_naissance,
           p.code_patient,
           TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as age
    FROM rendez_vous r
    JOIN patients p ON r.patient_id = p.id
    WHERE r.docteur_id = ?
    AND DATE(r.date_rdv) = ?
    ORDER BY r.date_rdv
");
$rendezvous_stmt->execute([$docteur_id, $selected_date]);
$rendezvous = $rendezvous_stmt->fetchAll();

// Récupérer les RDV à venir (7 prochains jours)
$upcoming_stmt = $pdo->prepare("
    SELECT r.*, p.nom, p.prenom, p.telephone
    FROM rendez_vous r
    JOIN patients p ON r.patient_id = p.id
    WHERE r.docteur_id = ?
    AND r.date_rdv >= CURDATE()
    AND r.statut = 'confirme'
    ORDER BY r.date_rdv
    LIMIT 10
");
$upcoming_stmt->execute([$docteur_id]);
$upcoming_rdv = $upcoming_stmt->fetchAll();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-calendar-check me-2"></i>Gestion des Rendez-vous
        </h1>
        <p class="text-muted mb-0">
            <span class="fw-semibold"><?php echo $_SESSION['prenom'] . ' ' . $_SESSION['nom']; ?></span> • 
            Affichage du <?php echo date('d/m/Y', strtotime($selected_date)); ?>
        </p>
    </div>
    <div class="btn-toolbar">
        <div class="btn-group me-2">
            <a href="rendezvous.php?date=<?php echo date('Y-m-d', strtotime('-1 day', strtotime($selected_date))); ?>" 
               class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-chevron-left"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="goToToday()">
                Aujourd'hui
            </button>
            <a href="rendezvous.php?date=<?php echo date('Y-m-d', strtotime('+1 day', strtotime($selected_date))); ?>" 
               class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="printSchedule()">
            <i class="fas fa-print me-1"></i>Imprimer
        </button>
        <a href="rendezvous.php?action=add" class="btn btn-sm btn-primary">
            <i class="fas fa-plus-circle me-1"></i>Nouveau RDV
        </a>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Confirmés</div>
                        <div class="h4 mb-0"><?php echo $stats['confirme'] ?? 0; ?></div>
                    </div>
                    <div class="rounded-circle bg-primary-light p-3">
                        <i class="fas fa-check-circle text-primary fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">En attente</div>
                        <div class="h4 mb-0"><?php echo $stats['attente'] ?? 0; ?></div>
                    </div>
                    <div class="rounded-circle bg-warning-light p-3">
                        <i class="fas fa-clock text-warning fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Aujourd'hui</div>
                        <div class="h4 mb-0"><?php echo count($rendezvous); ?></div>
                    </div>
                    <div class="rounded-circle bg-success-light p-3">
                        <i class="fas fa-calendar-day text-success fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start border-info border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">À venir (7j)</div>
                        <div class="h4 mb-0"><?php echo count($upcoming_rdv); ?></div>
                    </div>
                    <div class="rounded-circle bg-info-light p-3">
                        <i class="fas fa-calendar-week text-info fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sélection de date -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-auto">
                <label for="date" class="col-form-label">Sélectionner une date :</label>
            </div>
            <div class="col-auto">
                <input type="date" id="date" name="date" class="form-control" 
                       value="<?php echo $selected_date; ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>Afficher
                </button>
            </div>
            <div class="col-auto ms-auto">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="showPast" 
                           onchange="togglePastRendezVous()">
                    <label class="form-check-label" for="showPast">
                        Afficher les RDV passés
                    </label>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <!-- Liste des RDV du jour -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-day me-2"></i>
                    Rendez-vous du <?php echo date('d/m/Y', strtotime($selected_date)); ?>
                    <span class="badge bg-primary ms-2"><?php echo count($rendezvous); ?></span>
                </h5>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown">
                        <i class="fas fa-filter me-1"></i>Filtrer
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="filterByStatus('all')">Tous</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="filterByStatus('confirme')">Confirmés</a></li>
                        <li><a class="dropdown-item" href="#" onclick="filterByStatus('attente')">En attente</a></li>
                        <li><a class="dropdown-item" href="#" onclick="filterByStatus('annule')">Annulés</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($rendezvous)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucun rendez-vous programmé</h5>
                    <p class="text-muted small mb-3">Vous n'avez pas de rendez-vous pour cette date</p>
                    <a href="rendezvous.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Planifier un RDV
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Heure</th>
                                <th>Patient</th>
                                <th>Motif</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rendezvous as $rdv): 
                                $statusColors = [
                                    'confirme' => 'success',
                                    'attente' => 'warning',
                                    'annule' => 'danger',
                                    'reporte' => 'secondary'
                                ];
                            ?>
                            <tr class="rdv-row" data-status="<?php echo $rdv['statut']; ?>">
                                <td>
                                    <div class="fw-semibold"><?php echo date('H:i', strtotime($rdv['date_rdv'])); ?></div>
                                    <small class="text-muted"><?php echo $rdv['duree']; ?> min</small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-3">
                                            <?php echo strtoupper(substr($rdv['patient_prenom'], 0, 1) . substr($rdv['patient_nom'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?php echo $rdv['patient_prenom'] . ' ' . $rdv['patient_nom']; ?></div>
                                            <small class="text-muted">
                                                <?php echo $rdv['age']; ?> ans • <?php echo $rdv['telephone']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($rdv['motif']): ?>
                                    <span title="<?php echo htmlspecialchars($rdv['motif']); ?>">
                                        <?php echo substr($rdv['motif'], 0, 50); ?>
                                        <?php if (strlen($rdv['motif']) > 50): ?>...<?php endif; ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">Non spécifié</span>
                                    <?php endif; ?>
                                    <?php if ($rdv['notes']): ?>
                                    <small class="d-block text-muted mt-1">
                                        <i class="fas fa-comment me-1"></i>
                                        <?php echo substr($rdv['notes'], 0, 30); ?>
                                        <?php if (strlen($rdv['notes']) > 30): ?>...<?php endif; ?>
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $statusColors[$rdv['statut']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($rdv['statut']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="consultations.php?action=add&patient_id=<?php echo $rdv['patient_id']; ?>&rdv_id=<?php echo $rdv['id']; ?>" 
                                           class="btn btn-outline-primary" title="Démarrer consultation">
                                            <i class="fas fa-stethoscope"></i>
                                        </a>
                                        <a href="rendezvous.php?action=edit&id=<?php echo $rdv['id']; ?>" 
                                           class="btn btn-outline-secondary" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-outline-info" 
                                                onclick="showRendezVousDetails(<?php echo $rdv['id']; ?>)" 
                                                title="Détails">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white border-top">
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Cliquez sur <i class="fas fa-stethoscope text-primary"></i> pour démarrer une consultation
                        </small>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="rendezvous.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-calendar-alt me-1"></i>Vue mensuelle
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RDV à venir et actions rapides -->
    <div class="col-lg-4">
        <!-- RDV à venir -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Prochains rendez-vous
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($upcoming_rdv)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-plus fa-2x text-muted mb-3"></i>
                    <p class="text-muted small">Aucun rendez-vous à venir</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($upcoming_rdv as $rdv): ?>
                    <a href="rendezvous.php?date=<?php echo date('Y-m-d', strtotime($rdv['date_rdv'])); ?>" 
                       class="list-group-item list-group-item-action border-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?php echo $rdv['prenom'] . ' ' . $rdv['nom']; ?></h6>
                                <div class="small">
                                    <span class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('d/m', strtotime($rdv['date_rdv'])); ?>
                                    </span>
                                    <span class="ms-2 fw-semibold">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('H:i', strtotime($rdv['date_rdv'])); ?>
                                    </span>
                                </div>
                                <?php if ($rdv['motif']): ?>
                                <small class="text-muted d-block mt-1">
                                    <?php echo substr($rdv['motif'], 0, 40); ?>
                                    <?php if (strlen($rdv['motif']) > 40): ?>...<?php endif; ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <span class="badge bg-success"><?php echo $rdv['duree']; ?> min</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="rendezvous.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i>Nouveau RDV
                    </a>
                    <a href="consultations.php?action=add" class="btn btn-success">
                        <i class="fas fa-stethoscope me-2"></i>Nouvelle consultation
                    </a>
                    <a href="patients.php" class="btn btn-outline-primary">
                        <i class="fas fa-search me-2"></i>Rechercher un patient
                    </a>
                    <button class="btn btn-outline-secondary" onclick="exportRendezVous()">
                        <i class="fas fa-download me-2"></i>Exporter l'agenda
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour les détails du RDV -->
<div class="modal fade" id="rdvModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails du rendez-vous</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="rdvDetails">
                <!-- Les détails seront chargés ici -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" id="startConsultationBtn">
                    <i class="fas fa-stethoscope me-1"></i>Démarrer consultation
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// Fonctions JavaScript
function goToToday() {
    window.location.href = 'rendezvous.php?date=<?php echo date('Y-m-d'); ?>';
}

function printSchedule() {
    window.open('rendezvous.php?print=1&date=<?php echo $selected_date; ?>', '_blank');
}

function filterByStatus(status) {
    const rows = document.querySelectorAll('.rdv-row');
    rows.forEach(row => {
        if (status === 'all' || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function togglePastRendezVous() {
    const showPast = document.getElementById('showPast').checked;
    // Logique pour afficher/masquer les RDV passés
}

function showRendezVousDetails(rdvId) {
    // Charger les détails du RDV via AJAX
    fetch(`api/rendezvous_details.php?id=${rdvId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('rdvDetails').innerHTML = data;
            
            // Mettre à jour le bouton de consultation
            const startBtn = document.getElementById('startConsultationBtn');
            startBtn.onclick = function() {
                window.location.href = `consultations.php?action=add&rdv_id=${rdvId}`;
            };
            
            // Afficher la modal
            new bootstrap.Modal(document.getElementById('rdvModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur lors du chargement des détails');
        });
}

function exportRendezVous() {
    const date = document.getElementById('date').value;
    window.open(`api/export_rendezvous.php?date=${date}`, '_blank');
}

// Initialiser la sélection de date
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les tooltips
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
    
    // Gérer le changement de date
    document.getElementById('date').addEventListener('change', function() {
        this.form.submit();
    });
});
</script>

<style>
.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #4361ee;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.bg-primary-light { background-color: rgba(67, 97, 238, 0.1); }
.bg-success-light { background-color: rgba(16, 185, 129, 0.1); }
.bg-warning-light { background-color: rgba(245, 158, 11, 0.1); }
.bg-info-light { background-color: rgba(6, 182, 212, 0.1); }
.bg-danger-light { background-color: rgba(239, 68, 68, 0.1); }

.rdv-row:hover {
    background-color: #f8f9fa;
}

.table td {
    vertical-align: middle;
}
</style>