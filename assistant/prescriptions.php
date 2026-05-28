<?php
// assistant/prescriptions.php
require_once '../config/database.php';
checkRole('assistant');

$title = 'Gestion des Prescriptions';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

require_once '../includes/header.php';

// Traitement CRUD
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = sanitize($_POST);
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'add') {
            // Vérifier si le patient existe
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
            $stmt->execute([$data['patient_id']]);
            $patient = $stmt->fetch();
            
            if (!$patient) {
                throw new Exception("Patient non trouvé.");
            }
            
            // Vérifier si le médecin existe
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE id = ? AND role = 'docteur'");
            $stmt->execute([$data['docteur_id']]);
            $docteur = $stmt->fetch();
            
            if (!$docteur) {
                throw new Exception("Médecin non trouvé.");
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO prescriptions 
                (patient_id, docteur_id, date_prescription, medicaments, posologie, 
                 duree_traitement, instructions, statut, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['patient_id'],
                $data['docteur_id'],
                date('Y-m-d H:i:s'),
                $data['medicaments'],
                $data['posologie'],
                $data['duree_traitement'] ?? null,
                $data['instructions'] ?? null,
                'active',
                $user_id
            ]);
            
            $prescriptionId = $pdo->lastInsertId();
            
            // Journaliser l'action
            logAction('CREATE', 'prescriptions', $prescriptionId, "Création prescription pour patient ID: {$data['patient_id']}");
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Prescription créée avec succès";
            header("Location: prescriptions.php");
            exit();
            
        } elseif ($action === 'edit' && $id) {
            // Vérifier si la prescription existe
            $stmt = $pdo->prepare("SELECT * FROM prescriptions WHERE id = ?");
            $stmt->execute([$id]);
            $prescription = $stmt->fetch();
            
            if (!$prescription) {
                throw new Exception("Prescription non trouvée.");
            }
            
            // Vérifier les permissions
            if ($user_role === 'assistant' && $prescription['created_by'] !== $user_id) {
                throw new Exception("Vous n'avez pas la permission de modifier cette prescription.");
            }
            
            // Vérifier si le patient existe
            if (!empty($data['patient_id'])) {
                $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
                $stmt->execute([$data['patient_id']]);
                $patient = $stmt->fetch();
                
                if (!$patient) {
                    throw new Exception("Patient non trouvé.");
                }
            }
            
            // Vérifier si le médecin existe
            if (!empty($data['docteur_id'])) {
                $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE id = ? AND role = 'docteur'");
                $stmt->execute([$data['docteur_id']]);
                $docteur = $stmt->fetch();
                
                if (!$docteur) {
                    throw new Exception("Médecin non trouvé.");
                }
            }
            
            $stmt = $pdo->prepare("
                UPDATE prescriptions SET 
                patient_id = ?, docteur_id = ?, medicaments = ?, posologie = ?, 
                duree_traitement = ?, instructions = ?, statut = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['patient_id'] ?? $prescription['patient_id'],
                $data['docteur_id'] ?? $prescription['docteur_id'],
                $data['medicaments'],
                $data['posologie'],
                $data['duree_traitement'] ?? null,
                $data['instructions'] ?? null,
                $data['statut'],
                $id
            ]);
            
            // Journaliser l'action
            logAction('UPDATE', 'prescriptions', $id, "Modification prescription ID: $id");
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Prescription modifiée avec succès";
            header("Location: prescriptions.php");
            exit();
            
        } elseif ($action === 'delete' && $id) {
            // Vérifier si la prescription existe
            $stmt = $pdo->prepare("SELECT * FROM prescriptions WHERE id = ?");
            $stmt->execute([$id]);
            $prescription = $stmt->fetch();
            
            if (!$prescription) {
                throw new Exception("Prescription non trouvée.");
            }
            
            // Vérifier les permissions
            if ($user_role === 'assistant' && $prescription['created_by'] !== $user_id) {
                throw new Exception("Vous n'avez pas la permission de supprimer cette prescription.");
            }
            
            $stmt = $pdo->prepare("DELETE FROM prescriptions WHERE id = ?");
            $stmt->execute([$id]);
            
            // Journaliser l'action
            logAction('DELETE', 'prescriptions', $id, "Suppression prescription ID: $id");
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Prescription supprimée avec succès";
            header("Location: prescriptions.php");
            exit();
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: prescriptions.php?action=$action" . ($id ? "&id=$id" : ""));
        exit();
    }
}

// Récupérer les messages de session
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-prescription me-2"></i>Gestion des Prescriptions
        </h1>
        <p class="text-muted mb-0">Prescriptions médicales des patients</p>
    </div>
    <div class="btn-toolbar">
        <?php if ($action === 'list' && in_array($user_role, ['assistant', 'secretaire', 'docteur', 'admin'])): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus-circle me-1"></i>Nouvelle prescription
        </a>
        <?php elseif ($action !== 'list'): ?>
        <a href="prescriptions.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Retour à la liste
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Messages -->
<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Formulaire Ajout/Modification -->
<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-prescription me-2"></i>
                    <?php echo $action === 'add' ? 'Nouvelle prescription' : 'Modifier la prescription'; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php
                $prescription = null;
                if ($action === 'edit' && $id) {
                    $stmt = $pdo->prepare("SELECT * FROM prescriptions WHERE id = ?");
                    $stmt->execute([$id]);
                    $prescription = $stmt->fetch();
                    if (!$prescription) {
                        echo '<div class="alert alert-danger">Prescription non trouvée</div>';
                        require_once '../includes/footer.php';
                        exit();
                    }
                }
                ?>
                
                <form method="POST" id="prescriptionForm" novalidate>
                    <input type="hidden" name="action" value="<?php echo $action; ?>">
                    <?php if ($action === 'edit' && $id): ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <?php endif; ?>
                    
                    <div class="row g-3">
                        <!-- Informations patient et médecin -->
                        <div class="col-md-6">
                            <label class="form-label required">Patient</label>
                            <select class="form-select" name="patient_id" required 
                                <?php echo ($action === 'edit' && $user_role === 'assistant') ? 'disabled' : ''; ?>>
                                <option value="">Sélectionner un patient</option>
                                <?php
                                $patientsQuery = "SELECT id, nom, prenom, date_naissance FROM patients WHERE statut = 'actif' ORDER BY nom, prenom";
                                $patients = $pdo->query($patientsQuery)->fetchAll();
                                foreach ($patients as $patient): 
                                    $selected = ($prescription['patient_id'] ?? '') == $patient['id'];
                                ?>
                                <option value="<?php echo $patient['id']; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['nom'] . ' ' . $patient['prenom']); ?> 
                                    (<?php echo date('d/m/Y', strtotime($patient['date_naissance'])); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($action === 'edit' && $user_role === 'assistant'): ?>
                            <input type="hidden" name="patient_id" value="<?php echo $prescription['patient_id']; ?>">
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label required">Médecin prescripteur</label>
                            <select class="form-select" name="docteur_id" required
                                <?php echo ($action === 'edit' && $user_role === 'assistant') ? 'disabled' : ''; ?>>
                                <option value="">Sélectionner un médecin</option>
                                <?php
                                $docteursQuery = "SELECT id, nom, prenom, specialite FROM utilisateurs 
                                                 WHERE role = 'docteur' AND statut = 'actif' 
                                                 ORDER BY nom, prenom";
                                $docteurs = $pdo->query($docteursQuery)->fetchAll();
                                foreach ($docteurs as $docteur): 
                                    $selected = ($prescription['docteur_id'] ?? '') == $docteur['id'];
                                ?>
                                <option value="<?php echo $docteur['id']; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                    Dr. <?php echo htmlspecialchars($docteur['prenom'] . ' ' . $docteur['nom']); ?>
                                    <?php if ($docteur['specialite']): ?>
                                    - <?php echo htmlspecialchars($docteur['specialite']); ?>
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($action === 'edit' && $user_role === 'assistant'): ?>
                            <input type="hidden" name="docteur_id" value="<?php echo $prescription['docteur_id']; ?>">
                            <?php endif; ?>
                        </div>
                        
                        <!-- Médicaments -->
                        <div class="col-12">
                            <label class="form-label required">Médicaments</label>
                            <textarea class="form-control" name="medicaments" rows="4" required 
                                      placeholder="Liste des médicaments prescrits..."><?php echo htmlspecialchars($prescription['medicaments'] ?? ''); ?></textarea>
                            <div class="form-text">
                                Séparez les médicaments par des points-virgules (;) ou des retours à la ligne
                            </div>
                        </div>
                        
                        <!-- Posologie et durée -->
                        <div class="col-md-6">
                            <label class="form-label required">Posologie</label>
                            <textarea class="form-control" name="posologie" rows="3" required 
                                      placeholder="Posologie détaillée (dose, fréquence, heure...)"><?php echo htmlspecialchars($prescription['posologie'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Durée du traitement</label>
                            <input type="text" class="form-control" name="duree_traitement" 
                                   value="<?php echo htmlspecialchars($prescription['duree_traitement'] ?? ''); ?>"
                                   placeholder="Ex: 7 jours, 1 mois, etc.">
                        </div>
                        
                        <!-- Instructions -->
                        <div class="col-12">
                            <label class="form-label">Instructions spéciales</label>
                            <textarea class="form-control" name="instructions" rows="3" 
                                      placeholder="Instructions particulières, contre-indications, précautions..."><?php echo htmlspecialchars($prescription['instructions'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Statut (pour l'édition) -->
                        <?php if ($action === 'edit'): ?>
                        <div class="col-md-6">
                            <label class="form-label required">Statut</label>
                            <select class="form-select" name="statut" required>
                                <option value="active" <?php echo (isset($prescription['statut']) && $prescription['statut'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="completed" <?php echo (isset($prescription['statut']) && $prescription['statut'] === 'completed') ? 'selected' : ''; ?>>Terminée</option>
                                <option value="cancelled" <?php echo (isset($prescription['statut']) && $prescription['statut'] === 'cancelled') ? 'selected' : ''; ?>>Annulée</option>
                                <option value="suspended" <?php echo (isset($prescription['statut']) && $prescription['statut'] === 'suspended') ? 'selected' : ''; ?>>Suspendue</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Informations complémentaires (pour l'édition) -->
                        <?php if ($action === 'edit' && $prescription): ?>
                        <div class="col-12">
                            <div class="card bg-light border">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-info-circle me-2"></i>Informations système
                                    </h6>
                                    <div class="row small">
                                        <div class="col-md-4">
                                            <strong>Créée le:</strong><br>
                                            <?php echo date('d/m/Y H:i', strtotime($prescription['created_at'])); ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Par:</strong><br>
                                            <?php 
                                            $creatorStmt = $pdo->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
                                            $creatorStmt->execute([$prescription['created_by']]);
                                            $creator = $creatorStmt->fetch();
                                            echo $creator ? htmlspecialchars($creator['prenom'] . ' ' . $creator['nom']) : 'Inconnu';
                                            ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Dernière modification:</strong><br>
                                            <?php echo !empty($prescription['updated_at']) ? date('d/m/Y H:i', strtotime($prescription['updated_at'])) : 'Jamais'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i>
                            <?php echo $action === 'add' ? 'Créer la prescription' : 'Enregistrer les modifications'; ?>
                        </button>
                        <a href="prescriptions.php" class="btn btn-secondary ms-2">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Liste des prescriptions -->
<div class="card shadow-sm">
    <div class="card-header bg-white border-bottom">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Liste des prescriptions
                </h6>
            </div>
            <div class="col-md-6">
                <form method="GET" class="row g-2">
                    <div class="col">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Rechercher patient, médecin ou médicament..." 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    </div>
                    <div class="col-auto">
                        <select class="form-select" name="statut">
                            <option value="">Tous les statuts</option>
                            <option value="active" <?php echo ($_GET['statut'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo ($_GET['statut'] ?? '') === 'completed' ? 'selected' : ''; ?>>Terminée</option>
                            <option value="cancelled" <?php echo ($_GET['statut'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> Filtrer
                        </button>
                        <?php if (!empty($_GET['search']) || !empty($_GET['statut'])): ?>
                        <a href="prescriptions.php" class="btn btn-outline-secondary ms-1">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Médecin</th>
                        <th>Médicaments</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Construire la requête selon le rôle
                    if ($user_role === 'admin' || $user_role === 'secretaire') {
                        $sql = "SELECT p.*, 
                                       pat.nom as patient_nom, pat.prenom as patient_prenom,
                                       doc.nom as docteur_nom, doc.prenom as docteur_prenom,
                                       doc.specialite as docteur_specialite
                                FROM prescriptions p
                                LEFT JOIN patients pat ON p.patient_id = pat.id
                                LEFT JOIN utilisateurs doc ON p.docteur_id = doc.id
                                WHERE 1=1";
                    } elseif ($user_role === 'docteur') {
                        $sql = "SELECT p.*, 
                                       pat.nom as patient_nom, pat.prenom as patient_prenom,
                                       doc.nom as docteur_nom, doc.prenom as docteur_prenom,
                                       doc.specialite as docteur_specialite
                                FROM prescriptions p
                                LEFT JOIN patients pat ON p.patient_id = pat.id
                                LEFT JOIN utilisateurs doc ON p.docteur_id = doc.id
                                WHERE p.docteur_id = ?";
                    } else { // assistant
                        $sql = "SELECT p.*, 
                                       pat.nom as patient_nom, pat.prenom as patient_prenom,
                                       doc.nom as docteur_nom, doc.prenom as docteur_prenom,
                                       doc.specialite as docteur_specialite
                                FROM prescriptions p
                                LEFT JOIN patients pat ON p.patient_id = pat.id
                                LEFT JOIN utilisateurs doc ON p.docteur_id = doc.id
                                WHERE p.created_by = ?";
                    }
                    
                    $params = [];
                    if ($user_role === 'docteur' || $user_role === 'assistant') {
                        $params[] = $user_id;
                    }
                    
                    // Filtre recherche
                    if (!empty($_GET['search'])) {
                        $sql .= " AND (
                            pat.nom LIKE ? OR 
                            pat.prenom LIKE ? OR 
                            doc.nom LIKE ? OR 
                            doc.prenom LIKE ? OR 
                            p.medicaments LIKE ?
                        )";
                        $searchTerm = "%" . trim($_GET['search']) . "%";
                        array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
                    }
                    
                    // Filtre statut
                    if (!empty($_GET['statut'])) {
                        $sql .= " AND p.statut = ?";
                        $params[] = $_GET['statut'];
                    }
                    
                    $sql .= " ORDER BY p.date_prescription DESC, p.created_at DESC";
                    
                    try {
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $prescriptions = $stmt->fetchAll();
                        
                        if (empty($prescriptions)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-prescription fa-2x text-muted mb-3"></i>
                            <p class="text-muted">Aucune prescription trouvée</p>
                            <?php if (in_array($user_role, ['assistant', 'secretaire', 'docteur', 'admin'])): ?>
                            <a href="?action=add" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus-circle me-1"></i>Créer une prescription
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                        else:
                        foreach ($prescriptions as $prescription): 
                            $statusColor = $prescription['statut'] == 'active' ? 'success' : 
                                         ($prescription['statut'] == 'completed' ? 'primary' : 
                                         ($prescription['statut'] == 'cancelled' ? 'danger' : 'warning'));
                    ?>
                    <tr>
                        <td><strong>#<?php echo str_pad($prescription['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($prescription['patient_prenom'] . ' ' . $prescription['patient_nom']); ?></div>
                            <small class="text-muted">ID: <?php echo $prescription['patient_id']; ?></small>
                        </td>
                        <td>
                            <div class="small">
                                <div>Dr. <?php echo htmlspecialchars($prescription['docteur_prenom'] . ' ' . $prescription['docteur_nom']); ?></div>
                                <?php if ($prescription['docteur_specialite']): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($prescription['docteur_specialite']); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="small" style="max-width: 200px;">
                                <?php 
                                $medicaments = explode(';', $prescription['medicaments']);
                                $firstMed = trim($medicaments[0]);
                                echo htmlspecialchars($firstMed);
                                if (count($medicaments) > 1) {
                                    echo ' <span class="text-muted">+ ' . (count($medicaments) - 1) . ' autre(s)</span>';
                                }
                                ?>
                            </div>
                        </td>
                        <td>
                            <div class="small">
                                <div><?php echo date('d/m/Y', strtotime($prescription['date_prescription'])); ?></div>
                                <div class="text-muted"><?php echo date('H:i', strtotime($prescription['date_prescription'])); ?></div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $statusColor; ?>">
                                <?php 
                                $statusText = [
                                    'active' => 'Active',
                                    'completed' => 'Terminée',
                                    'cancelled' => 'Annulée',
                                    'suspended' => 'Suspendue'
                                ];
                                echo $statusText[$prescription['statut']] ?? ucfirst($prescription['statut']);
                                ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-info" 
                                        onclick="showPrescriptionDetails(<?php echo $prescription['id']; ?>)" 
                                        title="Détails">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if (in_array($user_role, ['admin', 'secretaire', 'docteur']) || 
                                         ($user_role === 'assistant' && $prescription['created_by'] == $user_id)): ?>
                                <a href="?action=edit&id=<?php echo $prescription['id']; ?>" 
                                   class="btn btn-outline-primary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (in_array($user_role, ['admin', 'secretaire']) || 
                                         ($user_role === 'assistant' && $prescription['created_by'] == $user_id)): ?>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $prescription['id']; ?>)" 
                                        title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php 
                        endforeach;
                        endif;
                    } catch (Exception $e) {
                        echo '<tr><td colspan="7" class="text-center text-danger py-4">Erreur: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card-footer bg-white border-top">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <small class="text-muted">
                    Total: <?php echo count($prescriptions); ?> prescription(s)
                </small>
            </div>
            <div>
                <?php if (in_array($user_role, ['assistant', 'secretaire', 'docteur', 'admin'])): ?>
                <a href="?action=add" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus-circle me-1"></i>Nouvelle prescription
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Détails Prescription -->
<div class="modal fade" id="prescriptionDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de la prescription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="prescriptionDetailsContent">
                <!-- Contenu chargé via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

<script>
// Validation du formulaire
document.getElementById('prescriptionForm')?.addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.add('was-validated');
    }
});

// Afficher les détails d'une prescription
function showPrescriptionDetails(prescriptionId) {
    fetch(`../ajax/get_prescription_details.php?id=${prescriptionId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.text();
        })
        .then(html => {
            document.getElementById('prescriptionDetailsContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('prescriptionDetailsModal')).show();
        })
        .catch(error => {
            document.getElementById('prescriptionDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Erreur lors du chargement des détails
                </div>
            `;
            new bootstrap.Modal(document.getElementById('prescriptionDetailsModal')).show();
        });
}

// Confirmer la suppression
function confirmDelete(prescriptionId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette prescription ? Cette action est irréversible.')) {
        window.location.href = `?action=delete&id=${prescriptionId}`;
    }
}

// Initialiser les tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
});
</script>

<style>
.required::after {
    content: " *";
    color: #dc3545;
}

.table th {
    font-weight: 600;
    color: #6b7280;
    background-color: #f9fafb;
    border-bottom: 2px solid #e5e7eb;
    padding: 1rem;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

.table td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #e5e7eb;
}

.table-hover tbody tr:hover {
    background-color: #f8fafc;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.25rem;
}

.btn-group-sm {
    border-radius: 0.25rem;
    overflow: hidden;
}

.badge {
    font-size: 0.75em;
    padding: 0.35em 0.65em;
}

.card {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
}

.card-header {
    padding: 1rem 1.5rem;
}

.card-body {
    padding: 1.5rem;
}

.alert {
    border-radius: 0.5rem;
    border: none;
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}
</style>