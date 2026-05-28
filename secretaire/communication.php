<?php
// secretaire/communication.php
require_once '../config/database.php';
require_once '../includes/sidebar.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'secretaire') {
    header('Location: ../login.php');
    exit;
}

$pdo = Database::getInstance()->getConnection();
$title = 'Communication';

$tab = $_GET['tab'] ?? 'rappels';
$message = '';
$error = '';

if ($tab === 'rappels' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_rappel'])) {
    $rdv_id = (int)$_POST['rdv_id'];
    $type = $_POST['type']; 
    $destinataires = $_POST['destinataires'] ?? [];

    $stmt = $pdo->prepare("
        SELECT r.*, 
               p.nom as patient_nom, p.prenom as patient_prenom, p.telephone, p.email,
               d.nom as docteur_nom, d.prenom as docteur_prenom, d.id as docteur_id
        FROM rendez_vous r
        JOIN patients p ON r.patient_id = p.id
        JOIN utilisateurs d ON r.docteur_id = d.id
        WHERE r.id = ?
    ");
    $stmt->execute([$rdv_id]);
    $rdv = $stmt->fetch();

    if (!$rdv) {
        $error = "Rendez-vous introuvable.";
    } elseif (empty($destinataires)) {
        $error = "Veuillez sélectionner au moins un destinataire.";
    } else {
        $date_rdv = date('d/m/Y à H:i', strtotime($rdv['date_rdv']));
        $contenu_patient = "Rappel: Rendez-vous le $date_rdv avec Dr " . $rdv['docteur_prenom'] . " " . $rdv['docteur_nom'];
        $contenu_docteur = "Rappel: Rendez-vous avec " . $rdv['patient_prenom'] . " " . $rdv['patient_nom'] . " le $date_rdv";
        $contenu_assistant = "Rappel: Rendez-vous patient: " . $rdv['patient_prenom'] . " " . $rdv['patient_nom'] . " avec Dr " . $rdv['docteur_prenom'] . " " . $rdv['docteur_nom'] . " le $date_rdv";

        $envoyes = [];

        // Envoi au patient
        if (in_array('patient', $destinataires)) {
            if ($type == 'notification') {
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, type, titre, message, lu, created_at) VALUES (?, 'message', 'Rappel RDV', ?, 0, NOW())");
                $notif->execute([$rdv['patient_id'], $contenu_patient]);
                $envoyes[] = "patient";
            } else {
                // simulation SMS/email
                $envoyes[] = "patient (simulation $type)";
            }
        }

        // Envoi au docteur
        if (in_array('docteur', $destinataires)) {
            if ($type == 'notification') {
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, type, titre, message, lu, created_at) VALUES (?, 'message', 'Rappel RDV', ?, 0, NOW())");
                $notif->execute([$rdv['docteur_id'], $contenu_docteur]);
                $envoyes[] = "médecin";
            } else {
                $envoyes[] = "médecin (simulation $type)";
            }
        }

        // Envoi aux assistants (tous les assistants actifs)
        if (in_array('assistant', $destinataires)) {
            $assistants = $pdo->query("SELECT id FROM utilisateurs WHERE role = 'assistant' AND statut = 'actif'");
            $countAssistants = 0;
            if ($type == 'notification') {
                $notifAss = $pdo->prepare("INSERT INTO notifications (user_id, type, titre, message, lu, created_at) VALUES (?, 'message', 'Rappel RDV', ?, 0, NOW())");
                while ($ass = $assistants->fetch()) {
                    $notifAss->execute([$ass['id'], $contenu_assistant]);
                    $countAssistants++;
                }
                if ($countAssistants > 0) $envoyes[] = "$countAssistants assistant(s)";
            } else {
                // simulation
                $envoyes[] = "assistant(s) (simulation $type)";
            }
        }

        if (!empty($envoyes)) {
            // Journalisation
            $log = $pdo->prepare("INSERT INTO rappel_logs (rdv_id, type, destinataire_type, contenu, envoye_par) VALUES (?, ?, ?, ?, ?)");
            $log->execute([$rdv_id, $type, implode(',', $destinataires), "$contenu_patient | $contenu_docteur | $contenu_assistant", $_SESSION['user_id']]);
            
            // Marquer le rappel comme envoyé dans rendez_vous
            $pdo->prepare("UPDATE rendez_vous SET rappel_envoye = 1, rappel_date = NOW() WHERE id = ?")->execute([$rdv_id]);
            $message = "Rappel(s) envoyé(s) à : " . implode(', ', $envoyes);
        } else {
            $error = "Aucun destinataire valide.";
        }
    }
}

// --- Récupération des RDV à venir (non encore rappelés) ---
$rdvsAVenir = $pdo->query("
    SELECT r.*, 
        CONCAT(p.nom, ' ', p.prenom) as patient_nom, p.telephone, p.email,
        CONCAT(d.nom, ' ', d.prenom) as docteur_nom
    FROM rendez_vous r
    JOIN patients p ON r.patient_id = p.id
    JOIN utilisateurs d ON r.docteur_id = d.id
    WHERE DATE(r.date_rdv) >= CURDATE() AND r.statut = 'confirme' AND r.rappel_envoye = 0
    ORDER BY r.date_rdv ASC
    LIMIT 50
")->fetchAll();

// --- Historique des rappels envoyés ---
$historique = $pdo->query("
    SELECT l.*, 
           CONCAT(p.nom, ' ', p.prenom) as patient_nom,
           CONCAT(d.nom, ' ', d.prenom) as docteur_nom,
           u.prenom as secretaire_prenom, u.nom as secretaire_nom
    FROM rappel_logs l
    JOIN rendez_vous r ON l.rdv_id = r.id
    JOIN patients p ON r.patient_id = p.id
    JOIN utilisateurs d ON r.docteur_id = d.id
    JOIN utilisateurs u ON l.envoye_par = u.id
    ORDER BY l.created_at DESC
    LIMIT 100
")->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <h1 class="h3 mb-4"><i class="fas fa-comments text-primary me-2"></i>Communication</h1>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item"><a class="nav-link <?= $tab == 'rappels' ? 'active' : '' ?>" href="?tab=rappels">Envoyer un rappel</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab == 'historique' ? 'active' : '' ?>" href="?tab=historique">Historique des rappels</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab == 'sms' ? 'active' : '' ?>" href="?tab=sms">SMS (simulation)</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab == 'emails' ? 'active' : '' ?>" href="?tab=emails">Emails (simulation)</a></li>
    </ul>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($tab == 'rappels'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h5 class="mb-0">Envoyer un rappel de rendez-vous</h5></div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Sélectionner un rendez-vous à venir</label>
                        <select name="rdv_id" class="form-select" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($rdvsAVenir as $rdv): ?>
                                <option value="<?= $rdv['id'] ?>"><?= htmlspecialchars($rdv['patient_nom']) ?> - le <?= date('d/m/Y H:i', strtotime($rdv['date_rdv'])) ?> (Dr <?= htmlspecialchars($rdv['docteur_nom']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($rdvsAVenir)): ?>
                            <div class="text-muted small mt-1">Aucun rendez-vous à venir sans rappel.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type de rappel</label>
                        <select name="type" class="form-select" required>
                            <option value="notification">Notification interne (base de données)</option>
                            <option value="sms">SMS (simulation)</option>
                            <option value="email">Email (simulation)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Destinataires</label>
                        <div class="border rounded p-3 bg-light">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="destinataires[]" value="patient" id="chkPatient">
                                <label class="form-check-label" for="chkPatient">Patient</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="destinataires[]" value="docteur" id="chkDocteur">
                                <label class="form-check-label" for="chkDocteur">Médecin traitant</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="destinataires[]" value="assistant" id="chkAssistant">
                                <label class="form-check-label" for="chkAssistant">Tous les assistants</label>
                            </div>
                        </div>
                        <small class="text-muted">Cochez au moins un destinataire.</small>
                    </div>
                    <button type="submit" name="send_rappel" class="btn btn-primary"><i class="fas fa-bell me-1"></i>Envoyer le rappel</button>
                </form>
            </div>
        </div>

    <?php elseif ($tab == 'historique'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h5 class="mb-0">Historique des rappels envoyés</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th><th>Patient</th><th>Médecin</th><th>Type</th><th>Destinataires</th><th>Envoyé par</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historique as $log): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                                <td><?= htmlspecialchars($log['patient_nom']) ?></td>
                                <td><?= htmlspecialchars($log['docteur_nom']) ?></td>
                                <td><span class="badge bg-secondary"><?= $log['type'] ?></span></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($log['destinataire_type']) ?></span></td>
                                <td><?= htmlspecialchars($log['secretaire_prenom'] . ' ' . $log['secretaire_nom']) ?></td>
                                <td>
                                    <a href="?tab=historique&action=delete_log&log_id=<?= $log['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ce log ?')"><i class="fas fa-trash"></i></a>
                                 </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($historique)): ?>
                            <tr><td colspan="7" class="text-center py-4">Aucun rappel envoyé pour le moment.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($tab == 'sms'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h5 class="mb-0">Envoyer un SMS (simulation)</h5></div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3"><label class="form-label">Numéro destinataire</label><input type="tel" name="numero" class="form-control" placeholder="+33600000000" required></div>
                    <div class="mb-3"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="3" required></textarea></div>
                    <button type="submit" name="send_sms" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Envoyer (simulation)</button>
                </form>
            </div>
        </div>

    <?php elseif ($tab == 'emails'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h5 class="mb-0">Envoyer un email (simulation)</h5></div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3"><label class="form-label">Destinataire</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Sujet</label><input type="text" name="sujet" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Message</label><textarea name="corps" class="form-control" rows="5" required></textarea></div>
                    <button type="submit" name="send_email" class="btn btn-primary"><i class="fas fa-envelope me-1"></i>Envoyer (simulation)</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php require_once '../includes/footer.php'; ?>