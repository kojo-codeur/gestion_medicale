<?php
// assistant/salle-attente.php
require_once '../config/database.php';
checkRole('assistant');

$pdo = Database::getInstance()->getConnection();

$title = 'Salle d\'Attente';
require_once '../includes/header.php';

$success_message = '';
$error_message = '';

// --- Traitement des actions POST ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_to_waiting':
            $patient_id = intval($_POST['patient_id']);
            $docteur_id = !empty($_POST['docteur_id']) ? intval($_POST['docteur_id']) : null;
            $motif = cleanInput($_POST['motif'] ?? '');
            $urgence = isset($_POST['urgence']) ? 1 : 0;
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM salle_attente WHERE patient_id = ? AND statut = 'en_attente'");
                $checkStmt->execute([$patient_id]);
                if ($checkStmt->rowCount() > 0) {
                    $error_message = "Ce patient est déjà en salle d'attente.";
                    break;
                }
                $stmt = $pdo->prepare("INSERT INTO salle_attente (patient_id, docteur_id, motif, urgence, added_by, added_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$patient_id, $docteur_id, $motif, $urgence, $_SESSION['user_id']]);
                $success_message = "Patient ajouté à la salle d'attente !";
            } catch (PDOException $e) {
                $error_message = "Erreur lors de l'ajout.";
                error_log($e->getMessage());
            }
            break;

        case 'call_patient':
            $waiting_id = intval($_POST['waiting_id']);
            try {
                $infoStmt = $pdo->prepare("
                    SELECT sa.*, p.nom, p.prenom, p.code_patient
                    FROM salle_attente sa
                    JOIN patients p ON sa.patient_id = p.id
                    WHERE sa.id = ?
                ");
                $infoStmt->execute([$waiting_id]);
                $patient_info = $infoStmt->fetch();
                $stmt = $pdo->prepare("UPDATE salle_attente SET statut = 'appele', called_at = NOW(), called_by = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $waiting_id]);
                if ($patient_info['docteur_id']) {
                    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, titre, message, lien, created_at) VALUES (?, 'attente', 'Patient appelé', ?, ?, NOW())");
                    $message = "Patient {$patient_info['prenom']} {$patient_info['nom']} est prêt pour consultation.";
                    $lien = "consultations.php?action=add&patient_id={$patient_info['patient_id']}";
                    $notifStmt->execute([$patient_info['docteur_id'], $message, $lien]);
                }
                $success_message = "Patient appelé avec succès !";
            } catch (PDOException $e) {
                $error_message = "Erreur lors de l'appel.";
                error_log($e->getMessage());
            }
            break;

        case 'remove_patient':
            $waiting_id = intval($_POST['waiting_id']);
            $raison = cleanInput($_POST['raison'] ?? '');
            try {
                $stmt = $pdo->prepare("UPDATE salle_attente SET statut = 'retire', removed_at = NOW(), removed_by = ?, raison_retrait = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $raison, $waiting_id]);
                $success_message = "Patient retiré de la salle d'attente.";
            } catch (PDOException $e) {
                $error_message = "Erreur lors du retrait.";
                error_log($e->getMessage());
            }
            break;

        case 'update_waiting':
            $waiting_id = intval($_POST['waiting_id']);
            $docteur_id = !empty($_POST['docteur_id']) ? intval($_POST['docteur_id']) : null;
            $motif = cleanInput($_POST['motif'] ?? '');
            $urgence = isset($_POST['urgence']) ? 1 : 0;
            $notes = cleanInput($_POST['notes'] ?? '');
            try {
                $stmt = $pdo->prepare("UPDATE salle_attente SET docteur_id = ?, motif = ?, urgence = ?, notes = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$docteur_id, $motif, $urgence, $notes, $waiting_id]);
                $success_message = "Informations mises à jour !";
            } catch (PDOException $e) {
                $error_message = "Erreur lors de la mise à jour.";
                error_log($e->getMessage());
            }
            break;
    }
}

// --- Récupération des patients en attente (salle_attente + rendez-vous du jour non encore en salle) ---
$waiting_patients = [];
try {
    // 1. Patients déjà en salle d'attente (statut = 'en_attente')
    $waitingStmt = $pdo->prepare("
        SELECT sa.*,
               p.nom as patient_nom, p.prenom as patient_prenom, p.code_patient,
               p.telephone, p.date_naissance,
               TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as patient_age,
               d.nom as docteur_nom, d.prenom as docteur_prenom, d.specialite,
               TIMESTAMPDIFF(MINUTE, sa.added_at, NOW()) as waiting_minutes,
               CONCAT(u.prenom, ' ', u.nom) as added_by_name
        FROM salle_attente sa
        JOIN patients p ON sa.patient_id = p.id
        LEFT JOIN utilisateurs d ON sa.docteur_id = d.id
        LEFT JOIN utilisateurs u ON sa.added_by = u.id
        WHERE sa.statut = 'en_attente'
        ORDER BY sa.urgence DESC, sa.added_at ASC
    ");
    $waitingStmt->execute();
    $waiting_from_table = $waitingStmt->fetchAll();
    $waiting_ids = array_column($waiting_from_table, 'patient_id');

    // 2. Rendez-vous du jour (date_rdv = CURDATE(), statut = 'confirme') non encore en salle
    $rdvStmt = $pdo->prepare("
        SELECT r.id as rdv_id, r.patient_id, r.docteur_id, r.motif, r.notes,
               p.nom as patient_nom, p.prenom as patient_prenom, p.code_patient,
               p.telephone, p.date_naissance,
               TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as patient_age,
               d.nom as docteur_nom, d.prenom as docteur_prenom, d.specialite,
               TIMESTAMPDIFF(MINUTE, r.date_rdv, NOW()) as waiting_minutes
        FROM rendez_vous r
        JOIN patients p ON r.patient_id = p.id
        LEFT JOIN utilisateurs d ON r.docteur_id = d.id
        WHERE DATE(r.date_rdv) = CURDATE()
          AND r.statut = 'confirme'
          AND r.patient_id NOT IN (" . implode(',', array_map('intval', $waiting_ids)) . ")
        ORDER BY r.date_rdv ASC
    ");
    $rdvStmt->execute();
    $rdv_patients = $rdvStmt->fetchAll();

    // Fusionner les deux sources en simulant une structure identique
    $waiting_patients = $waiting_from_table;
    foreach ($rdv_patients as $rdv) {
        $waiting_patients[] = [
            'id' => null, // pas d'ID dans salle_attente car non encore ajouté
            'patient_id' => $rdv['patient_id'],
            'docteur_id' => $rdv['docteur_id'],
            'motif' => $rdv['motif'],
            'urgence' => 0,
            'notes' => $rdv['notes'],
            'added_at' => $rdv['date_rdv'], // pour ordre, on utilise l'heure du RDV
            'added_by' => null,
            'added_by_name' => 'Rendez-vous',
            'patient_nom' => $rdv['patient_nom'],
            'patient_prenom' => $rdv['patient_prenom'],
            'code_patient' => $rdv['code_patient'],
            'telephone' => $rdv['telephone'],
            'date_naissance' => $rdv['date_naissance'],
            'patient_age' => $rdv['patient_age'],
            'docteur_nom' => $rdv['docteur_nom'],
            'docteur_prenom' => $rdv['docteur_prenom'],
            'specialite' => $rdv['specialite'],
            'waiting_minutes' => $rdv['waiting_minutes'],
            'from_rdv' => true // indicateur
        ];
    }

    // Tri final: urgents d'abord, puis par added_at
    usort($waiting_patients, function($a, $b) {
        if ($a['urgence'] != $b['urgence']) return $b['urgence'] - $a['urgence'];
        return strtotime($a['added_at']) - strtotime($b['added_at']);
    });
} catch (Exception $e) {
    error_log("Erreur récupération salle attente: " . $e->getMessage());
}

// --- Statistiques ---
$stats = [];
try {
    $statsStmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM salle_attente WHERE statut = 'en_attente') as total_waiting,
            (SELECT COUNT(*) FROM salle_attente WHERE statut = 'en_attente' AND urgence = 1) as urgent_waiting,
            (SELECT AVG(TIMESTAMPDIFF(MINUTE, added_at, IFNULL(called_at, NOW()))) FROM salle_attente WHERE statut IN ('appele', 'en_attente') AND DATE(added_at) = CURDATE()) as avg_wait_time,
            (SELECT COUNT(*) FROM salle_attente WHERE statut = 'appele' AND DATE(called_at) = CURDATE()) as called_today,
            (SELECT COUNT(DISTINCT docteur_id) FROM salle_attente WHERE statut = 'en_attente' AND docteur_id IS NOT NULL) as doctors_with_waiting,
            (SELECT COUNT(*) FROM salle_attente WHERE statut = 'en_attente' AND TIMESTAMPDIFF(MINUTE, added_at, NOW()) > 60) as waiting_over_hour
    ");
    $statsStmt->execute();
    $stats = $statsStmt->fetch();
} catch (Exception $e) {
    $stats = [];
}

// --- Listes pour les modals ---
$doctors = $pdo->query("SELECT id, nom, prenom, specialite FROM utilisateurs WHERE role = 'docteur' AND statut = 'actif' ORDER BY nom, prenom")->fetchAll();
$patientsList = $pdo->query("SELECT id, nom, prenom, code_patient, telephone, date_naissance FROM patients WHERE statut = 'actif' ORDER BY nom, prenom")->fetchAll();

// Fonctions utilitaires
function getWaitingColor($minutes, $urgence) {
    if ($urgence) return 'danger';
    if ($minutes > 60) return 'warning';
    if ($minutes > 30) return 'info';
    return 'success';
}
function formatWaitingTime($minutes) {
    if ($minutes < 60) return $minutes . ' min';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours . 'h' . ($mins ? ' ' . $mins . 'min' : '');
}
?>

<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
        <div>
            <h1 class="h2 mb-0"><i class="fas fa-clock me-2"></i>Salle d'Attente</h1>
            <p class="text-muted mb-0">Patients en attente de consultation (y compris ceux ayant un rendez-vous aujourd'hui)</p>
        </div>
        <div class="btn-toolbar">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addToWaitingModal">
                <i class="fas fa-user-plus me-2"></i>Ajouter patient
            </button>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success_message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error_message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Cartes statistiques -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-6 mb-4"><div class="card border-start border-primary border-4 shadow-sm"><div class="card-body py-3 text-center"><div class="h2 mb-1"><?= $stats['total_waiting'] ?? 0 ?></div><div class="small text-muted">En attente</div></div></div></div>
        <div class="col-xl-2 col-md-4 col-6 mb-4"><div class="card border-start border-danger border-4 shadow-sm"><div class="card-body py-3 text-center"><div class="h2 mb-1"><?= $stats['urgent_waiting'] ?? 0 ?></div><div class="small text-muted">Urgences</div></div></div></div>
        <div class="col-xl-2 col-md-4 col-6 mb-4"><div class="card border-start border-warning border-4 shadow-sm"><div class="card-body py-3 text-center"><div class="h2 mb-1"><?= $stats['waiting_over_hour'] ?? 0 ?></div><div class="small text-muted">+ d'1 heure</div></div></div></div>
        <div class="col-xl-2 col-md-4 col-6 mb-4"><div class="card border-start border-info border-4 shadow-sm"><div class="card-body py-3 text-center"><div class="h2 mb-1"><?= round($stats['avg_wait_time'] ?? 0) ?></div><div class="small text-muted">Temps moyen (min)</div></div></div></div>
        <div class="col-xl-2 col-md-4 col-6 mb-4"><div class="card border-start border-success border-4 shadow-sm"><div class="card-body py-3 text-center"><div class="h2 mb-1"><?= $stats['called_today'] ?? 0 ?></div><div class="small text-muted">Appelés aujourd'hui</div></div></div></div>
        <div class="col-xl-2 col-md-4 col-6 mb-4"><div class="card border-start border-secondary border-4 shadow-sm"><div class="card-body py-3 text-center"><div class="h2 mb-1"><?= $stats['doctors_with_waiting'] ?? 0 ?></div><div class="small text-muted">Médecins concernés</div></div></div></div>
    </div>

    <div class="row">
        <!-- Liste des patients en attente -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>Patients en attente <span class="badge bg-secondary ms-2"><?= count($waiting_patients) ?></span></h6>
                    <button class="btn btn-sm btn-outline-primary" onclick="location.reload()"><i class="fas fa-sync"></i></button>
                </div>
                <div class="card-body">
                    <?php if (empty($waiting_patients)): ?>
                    <div class="text-center py-5"><i class="fas fa-user-clock fa-3x text-muted mb-3"></i><h5 class="text-muted">Aucun patient en attente</h5><p class="text-muted">La salle d'attente est vide.</p><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addToWaitingModal"><i class="fas fa-user-plus me-1"></i>Ajouter un patient</button></div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($waiting_patients as $patient): 
                            $waiting_color = getWaitingColor($patient['waiting_minutes'], $patient['urgence']);
                            $age = $patient['patient_age'];
                        ?>
                        <div class="col-md-6 mb-4">
                            <div class="card border h-100">
                                <div class="card-header bg-<?= $waiting_color ?> bg-opacity-10 border-bottom-0">
                                    <div class="d-flex justify-content-between">
                                        <div><h6 class="mb-0"><?= htmlspecialchars($patient['patient_prenom'] . ' ' . $patient['patient_nom']) ?></h6><small class="text-muted"><?= htmlspecialchars($patient['code_patient']) ?></small></div>
                                        <div><span class="badge bg-<?= $waiting_color ?>"><?= formatWaitingTime($patient['waiting_minutes']) ?></span><?= $patient['urgence'] ? '<span class="badge bg-danger ms-1">Urgent</span>' : '' ?></div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="small text-muted mb-2"><i class="fas fa-user me-1"></i><?= $age ?> ans &nbsp; <i class="fas fa-phone me-1"></i><?= htmlspecialchars($patient['telephone']) ?></div>
                                    <?php if ($patient['docteur_nom']): ?>
                                    <div class="mb-2"><i class="fas fa-user-md me-1"></i>Dr. <?= htmlspecialchars($patient['docteur_prenom'] . ' ' . $patient['docteur_nom']) ?> <small>(<?= htmlspecialchars($patient['specialite']) ?>)</small></div>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['motif'])): ?>
                                    <div class="mb-2"><i class="fas fa-comment-medical me-1"></i><?= htmlspecialchars(substr($patient['motif'], 0, 100)) ?><?= strlen($patient['motif']) > 100 ? '...' : '' ?></div>
                                    <?php endif; ?>
                                    <div class="small text-muted"><i class="fas fa-user-plus me-1"></i><?= htmlspecialchars($patient['added_by_name'] ?? ($patient['from_rdv'] ? 'Rendez-vous' : 'Salle')) ?> à <?= date('H:i', strtotime($patient['added_at'])) ?></div>
                                    <div class="mt-3 d-flex justify-content-between">
                                        <?php if ($patient['id']): // seulement pour ceux déjà dans salle_attente ?>
                                        <button class="btn btn-sm btn-primary" onclick="callPatient(<?= $patient['id'] ?>)"><i class="fas fa-bell me-1"></i>Appeler</button>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-danger" onclick="removePatient(<?= $patient['id'] ?>)"><i class="fas fa-times"></i></button>
                                        </div>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-success" onclick="addRdvToWaiting(<?= $patient['patient_id'] ?>, <?= json_encode($patient) ?>)"><i class="fas fa-plus me-1"></i>Ajouter en salle</button>
                                        <span class="text-muted">(depuis rendez-vous)</span>
                                        <?php endif; ?>
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

        <!-- Panneau latéral -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions rapides</h6></div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addToWaitingModal"><i class="fas fa-user-plus me-2"></i>Ajouter patient</button>
                        <button class="btn btn-success" onclick="callNextPatient()"><i class="fas fa-bell me-2"></i>Appeler suivant</button>
                        <button class="btn btn-warning" onclick="printWaitingList()"><i class="fas fa-print me-2"></i>Imprimer liste</button>
                    </div>
                    <hr>
                    <div class="small">
                        <div class="d-flex justify-content-between mb-2"><span>Heure actuelle:</span><strong id="currentTime"><?= date('H:i') ?></strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>En attente:</span><strong><?= count($waiting_patients) ?></strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>Temps d'attente moyen:</span><strong><?= round($stats['avg_wait_time'] ?? 0) ?> min</strong></div>
                        <div class="d-flex justify-content-between"><span>Appelés aujourd'hui:</span><strong><?= $stats['called_today'] ?? 0 ?></strong></div>
                    </div>
                </div>
            </div>

            <?php $urgent = array_filter($waiting_patients, function($p) { return $p['urgence'] == 1 && $p['id']; }); if (!empty($urgent)): ?>
            <div class="card border-danger shadow-sm">
                <div class="card-header bg-danger text-white"><h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Patients urgents</h6></div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($urgent as $p): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div><strong><?= htmlspecialchars($p['patient_prenom'] . ' ' . $p['patient_nom']) ?></strong><br><small><?= formatWaitingTime($p['waiting_minutes']) ?></small></div>
                            <button class="btn btn-sm btn-danger" onclick="callPatient(<?= $p['id'] ?>)"><i class="fas fa-bell"></i></button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Ajouter patient -->
<div class="modal fade" id="addToWaitingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Ajouter à la salle d'attente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_to_waiting">
                    <div class="mb-3">
                        <label class="form-label">Patient</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($patientsList as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom'] . ' (' . $p['code_patient'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Médecin (optionnel)</label>
                        <select name="docteur_id" class="form-select">
                            <option value="">-- Aucun --</option>
                            <?php foreach ($doctors as $doc): ?>
                            <option value="<?= $doc['id'] ?>">Dr. <?= htmlspecialchars($doc['prenom'] . ' ' . $doc['nom']) ?> (<?= htmlspecialchars($doc['specialite']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motif</label>
                        <textarea name="motif" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="urgence" value="1" class="form-check-input" id="urgenceCheck">
                        <label class="form-check-label text-danger" for="urgenceCheck">Urgence</label>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Ajouter</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Édition -->
<div class="modal fade" id="editWaitingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title">Modifier patient</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_waiting">
                    <input type="hidden" name="waiting_id" id="edit_waiting_id">
                    <div class="mb-3"><label class="form-label">Patient</label><input type="text" id="edit_patient_name" class="form-control" disabled></div>
                    <div class="mb-3"><label class="form-label">Médecin</label><select name="docteur_id" id="edit_docteur_id" class="form-select"><option value="">-- Aucun --</option><?php foreach ($doctors as $doc): ?><option value="<?= $doc['id'] ?>">Dr. <?= htmlspecialchars($doc['prenom'] . ' ' . $doc['nom']) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Motif</label><textarea name="motif" id="edit_motif" class="form-control" rows="2"></textarea></div>
                    <div class="form-check mb-3"><input type="checkbox" name="urgence" value="1" id="edit_urgence" class="form-check-input"><label class="form-check-label">Urgence</label></div>
                    <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Enregistrer</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Retrait -->
<div class="modal fade" id="removePatientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title">Retirer de la salle d'attente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="remove_patient">
                    <input type="hidden" name="waiting_id" id="remove_waiting_id">
                    <div class="mb-3"><label class="form-label">Raison du retrait</label><textarea name="raison" class="form-control" rows="2" placeholder="Parti sans consultation, examens externes, ..."></textarea></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-danger">Retirer</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function callPatient(waitingId) {
    var form = document.createElement('form');
    form.method = 'POST';
    var input1 = document.createElement('input'); input1.type = 'hidden'; input1.name = 'action'; input1.value = 'call_patient';
    var input2 = document.createElement('input'); input2.type = 'hidden'; input2.name = 'waiting_id'; input2.value = waitingId;
    form.appendChild(input1); form.appendChild(input2);
    document.body.appendChild(form); form.submit();
}
function callNextPatient() {
    <?php $first = !empty($waiting_patients) ? $waiting_patients[0] : null; if ($first && $first['id']): ?>
    callPatient(<?= $first['id'] ?>);
    <?php else: ?>
    alert('Aucun patient en attente à appeler');
    <?php endif; ?>
}
function editWaiting(waitingId) {
    fetch('ajax/get_waiting.php?id=' + waitingId).then(r=>r.json()).then(d=>{
        if(d.success){
            document.getElementById('edit_waiting_id').value = d.waiting.id;
            document.getElementById('edit_patient_name').value = d.waiting.patient_name;
            document.getElementById('edit_docteur_id').value = d.waiting.docteur_id || '';
            document.getElementById('edit_motif').value = d.waiting.motif || '';
            document.getElementById('edit_urgence').checked = d.waiting.urgence == 1;
            document.getElementById('edit_notes').value = d.waiting.notes || '';
            new bootstrap.Modal(document.getElementById('editWaitingModal')).show();
        }
    }).catch(e=>console.error(e));
}
function removePatient(waitingId) {
    document.getElementById('remove_waiting_id').value = waitingId;
    new bootstrap.Modal(document.getElementById('removePatientModal')).show();
}
function addRdvToWaiting(patientId, patientData) {
    var form = document.createElement('form');
    form.method = 'POST';
    var inpAction = document.createElement('input'); inpAction.type='hidden'; inpAction.name='action'; inpAction.value='add_to_waiting';
    var inpPatient = document.createElement('input'); inpPatient.type='hidden'; inpPatient.name='patient_id'; inpPatient.value=patientId;
    var inpMotif = document.createElement('input'); inpMotif.type='hidden'; inpMotif.name='motif'; inpMotif.value=patientData.motif || '';
    var inpUrgence = document.createElement('input'); inpUrgence.type='hidden'; inpUrgence.name='urgence'; inpUrgence.value='0';
    var inpDocteur = document.createElement('input'); inpDocteur.type='hidden'; inpDocteur.name='docteur_id'; inpDocteur.value=patientData.docteur_id || '';
    form.appendChild(inpAction); form.appendChild(inpPatient); form.appendChild(inpMotif); form.appendChild(inpUrgence); form.appendChild(inpDocteur);
    document.body.appendChild(form); form.submit();
}
    
const waitingPatients = <?php 
    // Prépare les données en ajoutant waiting_time_formatted
    $print_data = array_map(function($p) {
        return [
            'patient_prenom' => $p['patient_prenom'],
            'patient_nom' => $p['patient_nom'],
            'code_patient' => $p['code_patient'],
            'docteur_nom' => $p['docteur_nom'],
            'docteur_prenom' => $p['docteur_prenom'],
            'urgence' => $p['urgence'],
            'waiting_minutes' => $p['waiting_minutes'],
            'waiting_formatted' => formatWaitingTime($p['waiting_minutes'])
        ];
    }, $waiting_patients);
    echo json_encode($print_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>;

function printWaitingList() {
    const printWindow = window.open('', '_blank');
    const today = new Date();
    const dateStr = today.toLocaleDateString('fr-FR');
    const timeStr = today.toLocaleTimeString('fr-FR');
    
    let tableRows = '';
    waitingPatients.forEach(patient => {
        const urgentClass = patient.urgence ? 'urgent' : '';
        const doctorName = patient.docteur_nom ? `Dr. ${patient.docteur_prenom} ${patient.docteur_nom}` : '-';
        tableRows += `
            <tr class="${urgentClass}">
                <td>${escapeHtml(patient.patient_prenom)} ${escapeHtml(patient.patient_nom)}</td>
                <td>${escapeHtml(patient.code_patient)}</td>
                <td>${escapeHtml(doctorName)}</td>
                <td>${escapeHtml(patient.waiting_formatted)}</td>
                <td>${patient.urgence ? 'OUI' : 'non'}</td>
            </tr>
        `;
    });

    const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Liste d'attente - ${dateStr}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #333; font-size: 1.5rem; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .urgent { background-color: #ffe6e6; }
                .footer { margin-top: 30px; font-size: 0.8rem; color: #666; text-align: center; }
            </style>
        </head>
        <body>
            <h1>Liste d'attente - ${dateStr} ${timeStr}</h1>
            <table>
                <thead>
                    <tr><th>Patient</th><th>Code</th><th>Docteur</th><th>Temps attente</th><th>Urgent</th></tr>
                </thead>
                <tbody>
                    ${tableRows}
                </tbody>
            </table>
            <div class="footer">Document généré par le système de gestion médicale</div>
        </body>
        </html>
    `;
    
    printWindow.document.write(htmlContent);
    printWindow.document.close();
    printWindow.print();
}

// Petite fonction utilitaire pour échapper le HTML (sécurité)
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    }).replace(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g, function(c) {
        return c;
    });
}

setInterval(() => { document.getElementById('currentTime').textContent = new Date().toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'}); }, 60000);
</script>

<?php require_once '../includes/footer.php'; ?>
