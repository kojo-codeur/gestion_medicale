<?php
// docteur/prescriptions.php
require_once '../config/database.php';
checkRole('docteur');

$title = 'Gestion des Prescriptions';
$docteur_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$prescription_id = $_GET['id'] ?? 0;

require_once '../includes/header.php';


// Si action = add, afficher le formulaire
if ($action === 'add') {
    // Récupérer les consultations pour les prescriptions
    $consultations_stmt = $pdo->prepare("
        SELECT c.id, c.reference, c.date_consultation, 
               p.nom as patient_nom, p.prenom as patient_prenom,
               p.code_patient, p.date_naissance
        FROM consultations c
        JOIN patients p ON c.patient_id = p.id
        WHERE c.docteur_id = ?
        AND c.statut = 'termine'
        ORDER BY c.date_consultation DESC
        LIMIT 10
    ");
    $consultations_stmt->execute([$docteur_id]);
    $consultations = $consultations_stmt->fetchAll();
    
    // Récupérer les médicaments
    $medicaments_stmt = $pdo->query("
        SELECT * FROM medicaments 
        WHERE statut = 'disponible'
        ORDER BY nom_commercial
    ");
    $medicaments = $medicaments_stmt->fetchAll();
    ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-prescription me-2"></i>Nouvelle Prescription
                        </h4>
                    </div>
                    <div class="card-body">
                        <form id="prescriptionForm" method="POST" action="api/save_prescription.php">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Sélectionner une consultation</label>
                                        <select class="form-select" name="consultation_id" id="consultation_id" required>
                                            <option value="">Sélectionner une consultation...</option>
                                            <?php foreach ($consultations as $consult): ?>
                                            <option value="<?php echo $consult['id']; ?>">
                                                <?php echo $consult['reference']; ?> - 
                                                <?php echo $consult['patient_prenom'] . ' ' . $consult['patient_nom']; ?> - 
                                                <?php echo date('d/m/Y', strtotime($consult['date_consultation'])); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Date de prescription</label>
                                        <input type="date" class="form-control" name="date_prescription" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <h5 class="border-bottom pb-2 mb-3">Médicaments</h5>
                                    <div id="medicamentsContainer">
                                        <div class="medicament-row border rounded p-3 mb-3">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">Médicament</label>
                                                    <select class="form-select medicament-select" name="medicaments[0][nom]" required>
                                                        <option value="">Sélectionner un médicament...</option>
                                                        <?php foreach ($medicaments as $med): ?>
                                                        <option value="<?php echo htmlspecialchars($med['nom_commercial']); ?>">
                                                            <?php echo htmlspecialchars($med['nom_commercial']); ?> - <?php echo $med['dosage']; ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Dosage</label>
                                                    <input type="text" class="form-control" name="medicaments[0][dosage]" 
                                                           placeholder="Ex: 500mg" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Forme</label>
                                                    <input type="text" class="form-control" name="medicaments[0][forme]" 
                                                           placeholder="Ex: comprimé">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Quantité</label>
                                                    <input type="number" class="form-control" name="medicaments[0][quantite]" 
                                                           min="1" value="1" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Durée</label>
                                                    <input type="text" class="form-control" name="medicaments[0][duree]" 
                                                           placeholder="Ex: 7 jours" required>
                                                </div>
                                                <div class="col-md-12 mt-2">
                                                    <label class="form-label">Posologie</label>
                                                    <textarea class="form-control" name="medicaments[0][posologie]" 
                                                              rows="2" placeholder="Ex: 1 comprimé matin et soir" required></textarea>
                                                </div>
                                                <div class="col-md-12 mt-2">
                                                    <label class="form-label">Instructions</label>
                                                    <textarea class="form-control" name="medicaments[0][instructions]" 
                                                              rows="2" placeholder="Instructions spécifiques..."></textarea>
                                                </div>
                                                <div class="col-md-2 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-medicament" onclick="removeMedicament(this)">
                                                        <i class="fas fa-times"></i> Supprimer
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="button" class="btn btn-outline-primary mb-3" onclick="addMedicament()">
                                        <i class="fas fa-plus me-1"></i>Ajouter un médicament
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Renouvelable</label>
                                        <select class="form-select" name="renouvelable" id="renouvelable">
                                            <option value="0">Non</option>
                                            <option value="1">Oui</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nombre de renouvellements</label>
                                        <input type="number" class="form-control" name="nombre_renouvellements" 
                                               id="nombre_renouvellements"
                                               min="0" value="0" disabled>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Notes complémentaires</label>
                                <textarea class="form-control" name="notes" rows="3" 
                                          placeholder="Notes ou instructions supplémentaires..."></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="prescriptions.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Retour
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer la prescription
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    let medicamentCount = 1;
    
    function addMedicament() {
        const container = document.getElementById('medicamentsContainer');
        const template = `
            <div class="medicament-row border rounded p-3 mb-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Médicament</label>
                        <select class="form-select medicament-select" name="medicaments[${medicamentCount}][nom]" required>
                            <option value="">Sélectionner un médicament...</option>
                            <?php foreach ($medicaments as $med): ?>
                            <option value="<?php echo htmlspecialchars($med['nom_commercial']); ?>">
                                <?php echo htmlspecialchars($med['nom_commercial']); ?> - <?php echo $med['dosage']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Dosage</label>
                        <input type="text" class="form-control" name="medicaments[${medicamentCount}][dosage]" 
                               placeholder="Ex: 500mg" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Forme</label>
                        <input type="text" class="form-control" name="medicaments[${medicamentCount}][forme]" 
                               placeholder="Ex: comprimé">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Quantité</label>
                        <input type="number" class="form-control" name="medicaments[${medicamentCount}][quantite]" 
                               min="1" value="1" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Durée</label>
                        <input type="text" class="form-control" name="medicaments[${medicamentCount}][duree]" 
                               placeholder="Ex: 7 jours" required>
                    </div>
                    <div class="col-md-12 mt-2">
                        <label class="form-label">Posologie</label>
                        <textarea class="form-control" name="medicaments[${medicamentCount}][posologie]" 
                                  rows="2" placeholder="Ex: 1 comprimé matin et soir" required></textarea>
                    </div>
                    <div class="col-md-12 mt-2">
                        <label class="form-label">Instructions</label>
                        <textarea class="form-control" name="medicaments[${medicamentCount}][instructions]" 
                                  rows="2" placeholder="Instructions spécifiques..."></textarea>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-sm remove-medicament" onclick="removeMedicament(this)">
                            <i class="fas fa-times"></i> Supprimer
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', template);
        medicamentCount++;
    }
    
    function removeMedicament(button) {
        const rows = document.querySelectorAll('.medicament-row');
        if (rows.length > 1) {
            button.closest('.medicament-row').remove();
        }
    }
    
    // Gérer le champ renouvelable
    document.getElementById('renouvelable').addEventListener('change', function() {
        const renewField = document.getElementById('nombre_renouvellements');
        renewField.disabled = this.value !== '1';
        if (this.value !== '1') {
            renewField.value = '0';
        }
    });
    </script>
    
    <?php
    require_once '../includes/footer.php';
    exit;
}

// Si action = view, afficher les détails d'une prescription
elseif ($action === 'view' && $prescription_id > 0) {
    // Récupérer la prescription
    $stmt = $pdo->prepare("
        SELECT p.*, 
               pat.nom as patient_nom, 
               pat.prenom as patient_prenom,
               pat.date_naissance,
               pat.code_patient,
               pat.adresse,
               pat.ville,
               pat.code_postal,
               pat.telephone,
               u.nom as docteur_nom,
               u.prenom as docteur_prenom,
               u.specialite,
               c.reference as consultation_ref,
               c.date_consultation
        FROM prescriptions p
        JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN utilisateurs u ON p.docteur_id = u.id
        LEFT JOIN consultations c ON p.consultation_id = c.id
        WHERE p.id = ? AND p.docteur_id = ?
    ");
    $stmt->execute([$prescription_id, $docteur_id]);
    $prescription = $stmt->fetch();
    
    if (!$prescription) {
        echo '<div class="container-fluid py-4">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Prescription non trouvée ou accès non autorisé
                </div>
                <a href="prescriptions.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-1"></i>Retour à la liste
                </a>
              </div>';
        require_once '../includes/footer.php';
        exit;
    }
    
    // Récupérer les médicaments de cette prescription
    $meds_stmt = $pdo->prepare("
        SELECT * FROM prescription_details 
        WHERE prescription_id = ?
        ORDER BY id
    ");
    $meds_stmt->execute([$prescription_id]);
    $medicaments = $meds_stmt->fetchAll();
    ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-prescription me-2"></i>Détails de la Prescription
                            <small class="text-muted"><?php echo $prescription['reference']; ?></small>
                        </h4>
                        <div class="btn-group">
                            <a href="prescriptions.php?action=print&id=<?php echo $prescription_id; ?>" 
                               class="btn btn-sm btn-success" target="_blank">
                                <i class="fas fa-print me-1"></i>Imprimer
                            </a>
                            <?php if ($prescription['statut'] === 'active'): ?>
                            <a href="prescriptions.php?action=edit&id=<?php echo $prescription_id; ?>" 
                               class="btn btn-sm btn-primary">
                                <i class="fas fa-edit me-1"></i>Modifier
                            </a>
                            <?php endif; ?>
                            <a href="prescriptions.php" class="btn btn-sm btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Retour
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2">Informations générales</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <th width="40%">Référence:</th>
                                        <td><?php echo $prescription['reference']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Date de prescription:</th>
                                        <td><?php echo date('d/m/Y', strtotime($prescription['date_prescription'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Patient:</th>
                                        <td>
                                            <strong><?php echo $prescription['patient_prenom'] . ' ' . $prescription['patient_nom']; ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo $prescription['code_patient']; ?> • 
                                                <?php echo calculateAge($prescription['date_naissance']); ?> ans
                                            </small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Docteur:</th>
                                        <td><?php echo 'Dr. ' . $prescription['docteur_prenom'] . ' ' . $prescription['docteur_nom']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Statut:</th>
                                        <td>
                                            <?php 
                                            $statusColors = [
                                                'active' => 'success',
                                                'terminee' => 'secondary',
                                                'annulee' => 'danger'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $statusColors[$prescription['statut']] ?? 'secondary'; ?>">
                                                <?php echo ucfirst($prescription['statut']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2">Informations complémentaires</h5>
                                <table class="table table-sm">
                                    <?php if ($prescription['consultation_ref']): ?>
                                    <tr>
                                        <th width="40%">Consultation:</th>
                                        <td>
                                            <?php echo $prescription['consultation_ref']; ?><br>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y', strtotime($prescription['date_consultation'])); ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>Renouvelable:</th>
                                        <td>
                                            <?php echo $prescription['renouvelable'] ? 'Oui' : 'Non'; ?>
                                            <?php if ($prescription['renouvelable'] && $prescription['nombre_renouvellements'] > 0): ?>
                                            <br><small class="text-muted">Nombre de renouvellements: <?php echo $prescription['nombre_renouvellements']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if (isset($prescription['date_fin']) && !empty($prescription['date_fin'])): ?>
                                    <tr>
                                        <th>Valide jusqu'au:</th>
                                        <td><?php echo date('d/m/Y', strtotime($prescription['date_fin'])); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <h5 class="border-bottom pb-2 mb-3">Médicaments prescrits</h5>
                                <?php if (empty($medicaments)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Aucun médicament dans cette prescription
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Médicament</th>
                                                <th>Dosage</th>
                                                <th>Forme</th>
                                                <th>Quantité</th>
                                                <th>Durée</th>
                                                <th>Posologie</th>
                                                <th>Instructions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($medicaments as $med): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($med['nom_medicament']); ?></strong></td>
                                                <td><?php echo $med['dosage']; ?></td>
                                                <td><?php echo $med['forme']; ?></td>
                                                <td><?php echo $med['quantite']; ?></td>
                                                <td><?php echo $med['duree']; ?></td>
                                                <td><?php echo nl2br(htmlspecialchars($med['posologie'])); ?></td>
                                                <td><?php echo nl2br(htmlspecialchars($med['instructions'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($prescription['notes'])): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <h5 class="border-bottom pb-2 mb-3">Notes complémentaires</h5>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($prescription['notes'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card border-warning">
                                    <div class="card-header bg-warning bg-opacity-10">
                                        <h6 class="mb-0"><i class="fas fa-user-md me-2"></i>Information médicale importante</h6>
                                    </div>
                                    <div class="card-body">
                                        <small class="text-muted">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Cette prescription est un document médical confidentiel. 
                                            Elle doit être présentée à un pharmacien pour être dispensée.
                                            En cas d'effets secondaires ou d'inquiétudes, consultez votre médecin.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                Créée le <?php echo date('d/m/Y H:i', strtotime($prescription['created_at'])); ?>
                                <?php if ($prescription['updated_at'] != $prescription['created_at']): ?>
                                • Modifiée le <?php echo date('d/m/Y H:i', strtotime($prescription['updated_at'])); ?>
                                <?php endif; ?>
                            </small>
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                                    <i class="fas fa-print me-1"></i>Imprimer cette page
                                </button>
                                <a href="api/download_prescription.php?id=<?php echo $prescription_id; ?>" 
                                   class="btn btn-outline-primary btn-sm" target="_blank">
                                    <i class="fas fa-download me-1"></i>Télécharger PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    require_once '../includes/footer.php';
    exit;
}

// Si action = edit, modifier une prescription
elseif ($action === 'edit' && $prescription_id > 0) {
    // Récupérer la prescription à modifier
    $stmt = $pdo->prepare("
        SELECT p.*, 
               pat.nom as patient_nom, 
               pat.prenom as patient_prenom,
               pat.code_patient,
               pat.date_naissance
        FROM prescriptions p
        JOIN patients pat ON p.patient_id = pat.id
        WHERE p.id = ? AND p.docteur_id = ? AND p.statut = 'active'
    ");
    $stmt->execute([$prescription_id, $docteur_id]);
    $prescription = $stmt->fetch();
    
    if (!$prescription) {
        echo '<div class="container-fluid py-4">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Prescription non trouvée, non autorisée ou non modifiable
                </div>
                <a href="prescriptions.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-1"></i>Retour à la liste
                </a>
              </div>';
        require_once '../includes/footer.php';
        exit;
    }
    
    // Récupérer les médicaments de cette prescription
    $meds_stmt = $pdo->prepare("
        SELECT * FROM prescription_details 
        WHERE prescription_id = ?
        ORDER BY id
    ");
    $meds_stmt->execute([$prescription_id]);
    $existing_medicaments = $meds_stmt->fetchAll();
    
    // Récupérer tous les médicaments disponibles
    $all_meds_stmt = $pdo->query("
        SELECT * FROM medicaments 
        WHERE statut = 'disponible'
        ORDER BY nom_commercial
    ");
    $all_medicaments = $all_meds_stmt->fetchAll();
    ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-edit me-2"></i>Modifier la Prescription
                            <small class="text-muted"><?php echo $prescription['reference']; ?></small>
                        </h4>
                    </div>
                    <div class="card-body">
                        <form id="editPrescriptionForm" method="POST" action="api/update_prescription.php">
                            <input type="hidden" name="prescription_id" value="<?php echo $prescription_id; ?>">
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="mb-3">Informations patient</h6>
                                            <p class="mb-1">
                                                <strong>Patient:</strong> 
                                                <?php echo $prescription['patient_prenom'] . ' ' . $prescription['patient_nom']; ?>
                                            </p>
                                            <p class="mb-0">
                                                <strong>Code patient:</strong> 
                                                <?php echo $prescription['code_patient']; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="mb-3">Dates</h6>
                                            <div class="mb-3">
                                                <label class="form-label">Date de prescription</label>
                                                <input type="date" class="form-control" name="date_prescription" 
                                                       value="<?php echo $prescription['date_prescription']; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <h5 class="border-bottom pb-2 mb-3">Médicaments</h5>
                                    <div id="medicamentsContainer">
                                        <?php foreach ($existing_medicaments as $index => $med): ?>
                                        <div class="medicament-row border rounded p-3 mb-3">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">Médicament</label>
                                                    <select class="form-select medicament-select" name="medicaments[<?php echo $index; ?>][nom]" required>
                                                        <option value="">Sélectionner un médicament...</option>
                                                        <?php foreach ($all_medicaments as $all_med): ?>
                                                        <option value="<?php echo htmlspecialchars($all_med['nom_commercial']); ?>" 
                                                            <?php echo ($med['nom_medicament'] == $all_med['nom_commercial']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($all_med['nom_commercial']); ?> - <?php echo $all_med['dosage']; ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Dosage</label>
                                                    <input type="text" class="form-control" name="medicaments[<?php echo $index; ?>][dosage]" 
                                                           value="<?php echo htmlspecialchars($med['dosage']); ?>" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Forme</label>
                                                    <input type="text" class="form-control" name="medicaments[<?php echo $index; ?>][forme]" 
                                                           value="<?php echo htmlspecialchars($med['forme']); ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Quantité</label>
                                                    <input type="number" class="form-control" name="medicaments[<?php echo $index; ?>][quantite]" 
                                                           min="1" value="<?php echo $med['quantite']; ?>" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Durée</label>
                                                    <input type="text" class="form-control" name="medicaments[<?php echo $index; ?>][duree]" 
                                                           value="<?php echo htmlspecialchars($med['duree']); ?>">
                                                </div>
                                                <div class="col-md-12 mt-2">
                                                    <label class="form-label">Posologie</label>
                                                    <textarea class="form-control" name="medicaments[<?php echo $index; ?>][posologie]" 
                                                              rows="2"><?php echo htmlspecialchars($med['posologie']); ?></textarea>
                                                </div>
                                                <div class="col-md-12 mt-2">
                                                    <label class="form-label">Instructions</label>
                                                    <textarea class="form-control" name="medicaments[<?php echo $index; ?>][instructions]" 
                                                              rows="2"><?php echo htmlspecialchars($med['instructions']); ?></textarea>
                                                </div>
                                                <div class="col-md-2 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-medicament" onclick="removeMedicament(this)">
                                                        <i class="fas fa-times"></i> Supprimer
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <button type="button" class="btn btn-outline-primary mb-3" onclick="addMedicament()">
                                        <i class="fas fa-plus me-1"></i>Ajouter un médicament
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Renouvelable</label>
                                        <select class="form-select" name="renouvelable" id="renouvelable">
                                            <option value="0" <?php echo !$prescription['renouvelable'] ? 'selected' : ''; ?>>Non</option>
                                            <option value="1" <?php echo $prescription['renouvelable'] ? 'selected' : ''; ?>>Oui</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nombre de renouvellements</label>
                                        <input type="number" class="form-control" name="nombre_renouvellements" 
                                               id="nombre_renouvellements"
                                               min="0" value="<?php echo $prescription['nombre_renouvellements']; ?>"
                                               <?php echo !$prescription['renouvelable'] ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Notes complémentaires</label>
                                <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($prescription['notes']); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Statut</label>
                                <select class="form-select" name="statut">
                                    <option value="active" <?php echo $prescription['statut'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="terminee" <?php echo $prescription['statut'] === 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                                    <option value="annulee" <?php echo $prescription['statut'] === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                                </select>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="prescriptions.php?action=view&id=<?php echo $prescription_id; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Annuler
                                </a>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-warning" onclick="confirmCancel()">
                                        <i class="fas fa-ban me-1"></i>Annuler la prescription
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Enregistrer les modifications
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    let medicamentCount = <?php echo count($existing_medicaments); ?>;
    
    function addMedicament() {
        const container = document.getElementById('medicamentsContainer');
        const template = `
            <div class="medicament-row border rounded p-3 mb-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Médicament</label>
                        <select class="form-select medicament-select" name="medicaments[${medicamentCount}][nom]" required>
                            <option value="">Sélectionner un médicament...</option>
                            <?php foreach ($all_medicaments as $med): ?>
                            <option value="<?php echo htmlspecialchars($med['nom_commercial']); ?>">
                                <?php echo htmlspecialchars($med['nom_commercial']); ?> - <?php echo $med['dosage']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Dosage</label>
                        <input type="text" class="form-control" name="medicaments[${medicamentCount}][dosage]" 
                               placeholder="Ex: 500mg" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Forme</label>
                        <input type="text" class="form-control" name="medicaments[${medicamentCount}][forme]" 
                               placeholder="Ex: comprimé">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Quantité</label>
                        <input type="number" class="form-control" name="medicaments[${medicamentCount}][quantite]" 
                               min="1" value="1" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Durée</label>
                        <input type="text" class="form-control" name="medicaments[${medicamentCount}][duree]" 
                               placeholder="Ex: 7 jours">
                    </div>
                    <div class="col-md-12 mt-2">
                        <label class="form-label">Posologie</label>
                        <textarea class="form-control" name="medicaments[${medicamentCount}][posologie]" 
                                  rows="2" placeholder="Ex: 1 comprimé matin et soir"></textarea>
                    </div>
                    <div class="col-md-12 mt-2">
                        <label class="form-label">Instructions</label>
                        <textarea class="form-control" name="medicaments[${medicamentCount}][instructions]" 
                                  rows="2" placeholder="Instructions spécifiques..."></textarea>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-sm remove-medicament" onclick="removeMedicament(this)">
                            <i class="fas fa-times"></i> Supprimer
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', template);
        medicamentCount++;
    }
    
    function removeMedicament(button) {
        const rows = document.querySelectorAll('.medicament-row');
        if (rows.length > 1) {
            button.closest('.medicament-row').remove();
        }
    }
    
    // Gérer le champ renouvelable
    document.getElementById('renouvelable').addEventListener('change', function() {
        const renewField = document.getElementById('nombre_renouvellements');
        renewField.disabled = this.value !== '1';
        if (this.value !== '1') {
            renewField.value = '0';
        }
    });
    
    function confirmCancel() {
        if (confirm('Voulez-vous vraiment annuler cette prescription ? Cette action est irréversible.')) {
            fetch('api/cancel_prescription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    prescription_id: <?php echo $prescription_id; ?>,
                    action: 'cancel'
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Prescription annulée avec succès!');
                    window.location.href = 'prescriptions.php?action=view&id=<?php echo $prescription_id; ?>';
                } else {
                    alert('Erreur: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Une erreur est survenue');
            });
        }
    }
    </script>
    
    <?php
    require_once '../includes/footer.php';
    exit;
}

// Si action = print, afficher une version imprimable
elseif ($action === 'print' && $prescription_id > 0) {
    // Récupérer la prescription
    $stmt = $pdo->prepare("
        SELECT p.*, 
               pat.nom as patient_nom, 
               pat.prenom as patient_prenom,
               pat.date_naissance,
               pat.code_patient,
               pat.adresse,
               pat.ville,
               pat.code_postal,
               pat.telephone,
               u.nom as docteur_nom,
               u.prenom as docteur_prenom,
               u.specialite,
               u.adresse as docteur_adresse,
               u.telephone as docteur_telephone
        FROM prescriptions p
        JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN utilisateurs u ON p.docteur_id = u.id
        WHERE p.id = ? AND p.docteur_id = ?
    ");
    $stmt->execute([$prescription_id, $docteur_id]);
    $prescription = $stmt->fetch();
    
    if (!$prescription) {
        echo '<div class="container-fluid py-4">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Prescription non trouvée ou accès non autorisé
                </div>
                <a href="prescriptions.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-1"></i>Retour à la liste
                </a>
              </div>';
        require_once '../includes/footer.php';
        exit;
    }
    
    // Récupérer les médicaments
    $meds_stmt = $pdo->prepare("
        SELECT * FROM prescription_details 
        WHERE prescription_id = ?
        ORDER BY id
    ");
    $meds_stmt->execute([$prescription_id]);
    $medicaments = $meds_stmt->fetchAll();
    ?>
    
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Prescription <?php echo $prescription['reference']; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            @media print {
                .no-print { display: none !important; }
                body { font-size: 12pt; }
                .prescription-header { border-bottom: 3px solid #000; }
                .prescription-footer { border-top: 2px solid #000; margin-top: 20px; }
                .medicament-table { page-break-inside: avoid; }
            }
            .prescription-container {
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
                border: 1px solid #ddd;
                background: white;
            }
            .prescription-header {
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            .prescription-footer {
                padding-top: 15px;
                margin-top: 30px;
                font-size: 0.9em;
            }
            .signature-line {
                border-top: 1px solid #000;
                width: 300px;
                margin: 40px auto 0;
                text-align: center;
                padding-top: 5px;
            }
        </style>
    </head>
    <body>
        <div class="container-fluid py-4 no-print">
            <div class="row mb-3">
                <div class="col-12">
                    <div class="btn-group">
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="fas fa-print me-1"></i>Imprimer
                        </button>
                        <a href="prescriptions.php?action=view&id=<?php echo $prescription_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Retour
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="prescription-container">
            <!-- En-tête -->
            <div class="prescription-header">
                <div class="row">
                    <div class="col-6">
                        <h4 class="mb-1"><?php echo 'Dr. ' . $prescription['docteur_prenom'] . ' ' . $prescription['docteur_nom']; ?></h4>
                        <p class="mb-0"><?php echo $prescription['specialite']; ?></p>
                        <p class="mb-0"><?php echo $prescription['docteur_adresse']; ?></p>
                        <p class="mb-0">Tél: <?php echo $prescription['docteur_telephone']; ?></p>
                    </div>
                    <div class="col-6 text-end">
                        <h4 class="mb-1">PRESCRIPTION MÉDICALE</h4>
                        <p class="mb-0">Référence: <?php echo $prescription['reference']; ?></p>
                        <p class="mb-0">Date: <?php echo date('d/m/Y', strtotime($prescription['date_prescription'])); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Informations patient -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5>Patient</h5>
                    <table class="table table-sm table-bordered">
                        <tr>
                            <th width="20%">Nom:</th>
                            <td><?php echo $prescription['patient_prenom'] . ' ' . $prescription['patient_nom']; ?></td>
                        </tr>
                        <tr>
                            <th>Date de naissance:</th>
                            <td><?php echo date('d/m/Y', strtotime($prescription['date_naissance'])); ?> 
                                (<?php echo calculateAge($prescription['date_naissance']); ?> ans)</td>
                        </tr>
                        <tr>
                            <th>Adresse:</th>
                            <td><?php echo $prescription['adresse'] . ', ' . $prescription['code_postal'] . ' ' . $prescription['ville']; ?></td>
                        </tr>
                        <tr>
                            <th>Téléphone:</th>
                            <td><?php echo $prescription['telephone']; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Médicaments -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5>Médicaments prescrits</h5>
                    <?php if (!empty($medicaments)): ?>
                    <table class="table table-bordered medicament-table">
                        <thead>
                            <tr class="table-light">
                                <th>Médicament</th>
                                <th>Dosage</th>
                                <th>Forme</th>
                                <th>Quantité</th>
                                <th>Posologie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medicaments as $med): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($med['nom_medicament']); ?></strong></td>
                                <td><?php echo $med['dosage']; ?></td>
                                <td><?php echo $med['forme']; ?></td>
                                <td><?php echo $med['quantite']; ?></td>
                                <td><?php echo nl2br(htmlspecialchars($med['posologie'])); ?></td>
                            </tr>
                            <?php if (!empty($med['instructions'])): ?>
                            <tr>
                                <td colspan="5" class="small">
                                    <strong>Instructions:</strong> <?php echo htmlspecialchars($med['instructions']); ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Informations supplémentaires -->
            <div class="row mb-4">
                <div class="col-12">
                    <h5>Informations complémentaires</h5>
                    <table class="table table-sm table-bordered">
                        <tr>
                            <th width="30%">Renouvelable:</th>
                            <td><?php echo $prescription['renouvelable'] ? 'Oui' : 'Non'; ?></td>
                        </tr>
                        <?php if ($prescription['renouvelable']): ?>
                        <tr>
                            <th>Nombre de renouvellements:</th>
                            <td><?php echo $prescription['nombre_renouvellements']; ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($prescription['date_fin']) && !empty($prescription['date_fin'])): ?>
                        <tr>
                            <th>Valide jusqu'au:</th>
                            <td><?php echo date('d/m/Y', strtotime($prescription['date_fin'])); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <?php if (!empty($prescription['notes'])): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h5>Notes du médecin</h5>
                    <div class="border p-3">
                        <?php echo nl2br(htmlspecialchars($prescription['notes'])); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Pied de page -->
            <div class="prescription-footer">
                <div class="row">
                    <div class="col-12 text-center">
                        <div class="signature-line">
                            Signature et cachet du médecin
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12">
                        <small class="text-muted">
                            <i class="fas fa-exclamation-circle me-1"></i>
                            Cette prescription est valable pour une durée de 3 mois à compter de sa date d'établissement.
                            Elle doit être présentée à un pharmacien pour être exécutée.
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        window.onload = function() {
            // Auto-print si demandé
            if (window.location.search.includes('autoprint')) {
                window.print();
            }
        }
        </script>
    </body>
    </html>
    
    <?php
    exit;
}

// Si action = delete, supprimer une prescription
elseif ($action === 'delete' && $prescription_id > 0) {
    // Vérifier que la prescription appartient au docteur
    $stmt = $pdo->prepare("SELECT id FROM prescriptions WHERE id = ? AND docteur_id = ?");
    $stmt->execute([$prescription_id, $docteur_id]);
    
    if ($stmt->fetch()) {
        // Marquer comme annulée plutôt que supprimer
        $update_stmt = $pdo->prepare("UPDATE prescriptions SET statut = 'annulee' WHERE id = ?");
        $update_stmt->execute([$prescription_id]);
        
        // Journaliser l'action
        $audit_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, created_at) VALUES (?, 'DELETE', 'prescriptions', ?, NOW())");
        $audit_stmt->execute([$docteur_id, $prescription_id]);
        
        $_SESSION['success_message'] = 'Prescription annulée avec succès.';
    } else {
        $_SESSION['error_message'] = 'Prescription non trouvée ou accès non autorisé.';
    }
    
    header('Location: prescriptions.php');
    exit;
}

// --- AFFICHAGE DE LA LISTE DES PRESCRIPTIONS ---

// Compter les prescriptions
$stats_stmt = $pdo->prepare("
    SELECT 
        statut,
        COUNT(*) as count
    FROM prescriptions
    WHERE docteur_id = ?
    GROUP BY statut
");
$stats_stmt->execute([$docteur_id]);
$stats = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Récupérer les prescriptions récentes
$prescriptions_stmt = $pdo->prepare("
    SELECT p.*, 
           pat.nom as patient_nom, 
           pat.prenom as patient_prenom,
           pat.date_naissance,
           pat.code_patient,
           u.nom as docteur_nom,
           u.prenom as docteur_prenom,
           (SELECT COUNT(*) FROM prescription_details WHERE prescription_id = p.id) as medicaments_count
    FROM prescriptions p
    JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN utilisateurs u ON p.docteur_id = u.id
    WHERE p.docteur_id = ?
    ORDER BY p.date_prescription DESC
    LIMIT 20
");
$prescriptions_stmt->execute([$docteur_id]);
$prescriptions = $prescriptions_stmt->fetchAll();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-prescription me-2"></i>Gestion des Prescriptions
        </h1>
        <p class="text-muted mb-0">
            <span class="fw-semibold">Dr. <?php echo $_SESSION['prenom'] . ' ' . $_SESSION['nom']; ?></span>
        </p>
    </div>
    <div class="btn-toolbar">
        <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="printPrescriptions()">
            <i class="fas fa-print me-1"></i>Imprimer
        </button>
        <a href="prescriptions.php?action=add" class="btn btn-sm btn-primary">
            <i class="fas fa-plus-circle me-1"></i>Nouvelle prescription
        </a>
    </div>
</div>

<!-- Messages d'alerte -->
<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo $_SESSION['success_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <?php echo $_SESSION['error_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Actives</div>
                        <div class="h4 mb-0"><?php echo $stats['active'] ?? 0; ?></div>
                    </div>
                    <div class="rounded-circle bg-primary-light p-3">
                        <i class="fas fa-prescription text-primary fa-lg"></i>
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
                        <div class="text-muted small">Terminées</div>
                        <div class="h4 mb-0"><?php echo $stats['terminee'] ?? 0; ?></div>
                    </div>
                    <div class="rounded-circle bg-success-light p-3">
                        <i class="fas fa-check-circle text-success fa-lg"></i>
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
                        <div class="text-muted small">Aujourd'hui</div>
                        <?php 
                        $today_stmt = $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE docteur_id = ? AND DATE(date_prescription) = CURDATE()");
                        $today_stmt->execute([$docteur_id]);
                        $today_count = $today_stmt->fetchColumn();
                        ?>
                        <div class="h4 mb-0"><?php echo $today_count; ?></div>
                    </div>
                    <div class="rounded-circle bg-warning-light p-3">
                        <i class="fas fa-calendar-day text-warning fa-lg"></i>
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
                        <div class="text-muted small">Médicaments prescrits</div>
                        <?php 
                        $meds_stmt = $pdo->prepare("
                            SELECT COUNT(*) 
                            FROM prescription_details pd
                            JOIN prescriptions p ON pd.prescription_id = p.id
                            WHERE p.docteur_id = ?
                        ");
                        $meds_stmt->execute([$docteur_id]);
                        $meds_count = $meds_stmt->fetchColumn();
                        ?>
                        <div class="h4 mb-0"><?php echo $meds_count; ?></div>
                    </div>
                    <div class="rounded-circle bg-info-light p-3">
                        <i class="fas fa-pills text-info fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres et recherche -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="patient" class="form-label">Patient</label>
                <?php 
                $patients_stmt = $pdo->prepare("
                    SELECT DISTINCT p.id, p.nom, p.prenom 
                    FROM patients p
                    JOIN prescriptions pr ON p.id = pr.patient_id
                    WHERE pr.docteur_id = ?
                    ORDER BY p.nom, p.prenom
                ");
                $patients_stmt->execute([$docteur_id]);
                $patients = $patients_stmt->fetchAll();
                ?>
                <select class="form-select" id="patient" name="patient_id">
                    <option value="">Tous les patients</option>
                    <?php foreach ($patients as $patient): ?>
                    <option value="<?php echo $patient['id']; ?>" <?php echo isset($_GET['patient_id']) && $_GET['patient_id'] == $patient['id'] ? 'selected' : ''; ?>>
                        <?php echo $patient['prenom'] . ' ' . $patient['nom']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="statut" class="form-label">Statut</label>
                <select class="form-select" id="statut" name="statut">
                    <option value="">Tous les statuts</option>
                    <option value="active" <?php echo isset($_GET['statut']) && $_GET['statut'] == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="terminee" <?php echo isset($_GET['statut']) && $_GET['statut'] == 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                    <option value="annulee" <?php echo isset($_GET['statut']) && $_GET['statut'] == 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="date_debut" class="form-label">Date début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" 
                       value="<?php echo $_GET['date_debut'] ?? ''; ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des prescriptions -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Prescriptions récentes
        </h5>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                    data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="api/export_prescriptions.php?format=pdf" target="_blank">PDF</a></li>
                <li><a class="dropdown-item" href="api/export_prescriptions.php?format=excel" target="_blank">Excel</a></li>
                <li><a class="dropdown-item" href="api/export_prescriptions.php?format=csv" target="_blank">CSV</a></li>
            </ul>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($prescriptions)): ?>
        <div class="text-center py-5">
            <i class="fas fa-prescription-bottle-alt fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Aucune prescription trouvée</h5>
            <p class="text-muted small mb-3">Vous n'avez pas encore créé de prescriptions</p>
            <a href="prescriptions.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Créer votre première prescription
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Médicaments</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prescriptions as $pres): 
                        $statusColors = [
                            'active' => 'success',
                            'terminee' => 'secondary',
                            'annulee' => 'danger',
                            'expire' => 'warning'
                        ];
                        $age = calculateAge($pres['date_naissance']);
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo date('d/m/Y', strtotime($pres['date_prescription'])); ?></div>
                            <small class="text-muted"><?php echo date('H:i', strtotime($pres['date_prescription'])); ?></small>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar me-3">
                                    <?php echo strtoupper(substr($pres['patient_prenom'], 0, 1) . substr($pres['patient_nom'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?php echo $pres['patient_prenom'] . ' ' . $pres['patient_nom']; ?></div>
                                    <small class="text-muted">
                                        <?php echo $pres['code_patient']; ?> • <?php echo $age; ?> ans
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo $pres['medicaments_count']; ?> médicament(s)</div>
                            <?php if ($pres['renouvelable']): ?>
                            <small class="text-success">
                                <i class="fas fa-redo me-1"></i>Renouvelable
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $statusColors[$pres['statut']] ?? 'secondary'; ?>">
                                <?php echo ucfirst($pres['statut']); ?>
                            </span>
                            <?php if (isset($pres['date_fin']) && !empty($pres['date_fin'])): ?>
                            <div class="small text-muted">
                                Valide jusqu'au <?php echo date('d/m/Y', strtotime($pres['date_fin'])); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="prescriptions.php?action=view&id=<?php echo $pres['id']; ?>" 
                                   class="btn btn-outline-primary" title="Voir">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="prescriptions.php?action=print&id=<?php echo $pres['id']; ?>" 
                                   class="btn btn-outline-success" title="Imprimer" target="_blank">
                                    <i class="fas fa-print"></i>
                                </a>
                                <?php if ($pres['statut'] === 'active'): ?>
                                <a href="prescriptions.php?action=edit&id=<?php echo $pres['id']; ?>" 
                                   class="btn btn-outline-secondary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="prescriptions.php?action=delete&id=<?php echo $pres['id']; ?>" 
                                   class="btn btn-outline-danger" title="Annuler"
                                   onclick="return confirm('Voulez-vous vraiment annuler cette prescription ?');">
                                    <i class="fas fa-ban"></i>
                                </a>
                                <?php endif; ?>
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
                    Affichage des 20 dernières prescriptions
                </small>
            </div>
            <div class="col-md-6 text-end">
                <a href="prescriptions.php?export=all" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-list me-1"></i>Voir toutes les prescriptions
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'impression -->
<div class="modal fade" id="printModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Imprimer les prescriptions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="printForm">
                    <div class="mb-3">
                        <label class="form-label">Période</label>
                        <select class="form-select" id="printPeriod">
                            <option value="today">Aujourd'hui</option>
                            <option value="week">Cette semaine</option>
                            <option value="month">Ce mois</option>
                            <option value="custom">Personnalisée</option>
                        </select>
                    </div>
                    <div class="row mb-3" id="customDates" style="display: none;">
                        <div class="col-md-6">
                            <label class="form-label">Date début</label>
                            <input type="date" class="form-control" id="printStartDate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date fin</label>
                            <input type="date" class="form-control" id="printEndDate">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Format</label>
                        <select class="form-select" id="printFormat">
                            <option value="pdf">PDF</option>
                            <option value="word">Word</option>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="includeDetails" checked>
                        <label class="form-check-label" for="includeDetails">
                            Inclure les détails des médicaments
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="generatePrint()">
                    <i class="fas fa-print me-1"></i>Générer
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
function printPrescriptions() {
    const printModal = new bootstrap.Modal(document.getElementById('printModal'));
    printModal.show();
}

function generatePrint() {
    const period = document.getElementById('printPeriod').value;
    const format = document.getElementById('printFormat').value;
    const includeDetails = document.getElementById('includeDetails').checked;
    
    let url = `api/print_prescriptions.php?format=${format}&details=${includeDetails ? 1 : 0}`;
    
    if (period === 'custom') {
        const startDate = document.getElementById('printStartDate').value;
        const endDate = document.getElementById('printEndDate').value;
        if (startDate && endDate) {
            url += `&start=${startDate}&end=${endDate}`;
        }
    } else {
        url += `&period=${period}`;
    }
    
    window.open(url, '_blank');
    bootstrap.Modal.getInstance(document.getElementById('printModal')).hide();
}

// Gérer l'affichage des dates personnalisées
document.getElementById('printPeriod').addEventListener('change', function() {
    const customDates = document.getElementById('customDates');
    customDates.style.display = this.value === 'custom' ? 'block' : 'none';
});

// Initialiser
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les tooltips
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
    
    // Mettre à jour les dates par défaut
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('printStartDate').value = today;
    document.getElementById('printEndDate').value = today;
    
    // Filtrer par patient si paramètre dans l'URL
    const urlParams = new URLSearchParams(window.location.search);
    const patientId = urlParams.get('patient_id');
    if (patientId) {
        document.getElementById('patient').value = patientId;
    }
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

.medicament-row {
    background-color: #f8f9fa;
    transition: background-color 0.2s;
}

.medicament-row:hover {
    background-color: #e9ecef;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>