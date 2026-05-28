<?php
// docteur/comptes-rendus.php
require_once '../config/database.php';
checkRole('docteur');

$title = 'Comptes Rendus Médicaux';
$docteur_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

// Gérer les actions
if ($action === 'view' && $id) {
    viewCompteRendu($id);
    exit;
} elseif ($action === 'add') {
    addCompteRendu();
    exit;
} elseif ($action === 'edit' && $id) {
    editCompteRendu($id);
    exit;
}

// Fonction pour afficher un compte-rendu
function viewCompteRendu($id) {
    global $pdo, $docteur_id;
    
    $stmt = $pdo->prepare("
        SELECT d.*, 
               p.nom as patient_nom, p.prenom as patient_prenom,
               p.code_patient, p.date_naissance, p.telephone,
               p.adresse, p.ville, p.code_postal,
               u.prenom as docteur_prenom, u.nom as docteur_nom,
               u.specialite, u.telephone as docteur_telephone,
               c.motif as motif_consultation,
               c.date_consultation
        FROM documents_medicaux d
        JOIN patients p ON d.patient_id = p.id
        JOIN utilisateurs u ON d.docteur_id = u.id
        LEFT JOIN consultations c ON d.consultation_id = c.id
        WHERE d.id = ? AND d.docteur_id = ? AND d.type = 'compte_rendu'
    ");
    $stmt->execute([$id, $docteur_id]);
    $compte_rendu = $stmt->fetch();
    
    if (!$compte_rendu) {
        header('Location: comptes-rendus.php');
        exit;
    }
    
    // Récupérer les métadonnées
    $metadata = [];
    if (!empty($compte_rendu['metadata'])) {
        $metadata = json_decode($compte_rendu['metadata'], true);
    }
    
    require_once '../includes/header.php';
    ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-file-medical-alt me-2"></i>Compte Rendu Médical
                        </h1>
                        <p class="text-muted mb-0"><?php echo $compte_rendu['fichier_nom'] ?? ''; ?></p>
                    </div>
                    <div class="btn-group">
                        <a href="comptes-rendus.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Retour
                        </a>
                        <a href="comptes-rendus.php?action=edit&id=<?php echo $id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i>Modifier
                        </a>
                        <?php if ($compte_rendu['fichier_path']): ?>
                        <a href="../uploads/<?php echo $compte_rendu['fichier_path']; ?>" class="btn btn-success" target="_blank" download>
                            <i class="fas fa-download me-1"></i>Télécharger
                        </a>
                        <?php endif; ?>
                        <button onclick="window.print()" class="btn btn-outline-secondary">
                            <i class="fas fa-print me-1"></i>Imprimer
                        </button>
                    </div>
                </div>
                
                <!-- Compte rendu médical -->
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-file-medical-alt me-2"></i>Compte Rendu de Consultation
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- En-tête -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <h6 class="fw-bold">MÉDECIN</h6>
                                    <p class="mb-1">Dr. <?php echo $compte_rendu['docteur_prenom'] . ' ' . $compte_rendu['docteur_nom']; ?></p>
                                    <p class="mb-1"><?php echo $compte_rendu['specialite']; ?></p>
                                    <p class="mb-1"><?php echo $compte_rendu['docteur_telephone']; ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <h6 class="fw-bold">PATIENT</h6>
                                    <p class="mb-1"><?php echo $compte_rendu['patient_prenom'] . ' ' . $compte_rendu['patient_nom']; ?></p>
                                    <p class="mb-1">Né(e) le : <?php echo date('d/m/Y', strtotime($compte_rendu['date_naissance'])); ?></p>
                                    <p class="mb-1"><?php echo $compte_rendu['telephone']; ?></p>
                                    <p class="mb-0"><?php echo $compte_rendu['adresse'] . ', ' . $compte_rendu['code_postal'] . ' ' . $compte_rendu['ville']; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informations de la consultation -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="border rounded p-3">
                                    <h6 class="fw-bold mb-3">INFORMATIONS DE LA CONSULTATION</h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <p class="mb-1"><strong>Date :</strong></p>
                                            <p><?php echo $compte_rendu['date_consultation'] ? date('d/m/Y', strtotime($compte_rendu['date_consultation'])) : date('d/m/Y', strtotime($compte_rendu['created_at'])); ?></p>
                                        </div>
                                        <div class="col-md-3">
                                            <p class="mb-1"><strong>Motif :</strong></p>
                                            <p><?php echo $compte_rendu['motif_consultation'] ?? 'Non spécifié'; ?></p>
                                        </div>
                                        <div class="col-md-3">
                                            <p class="mb-1"><strong>Type :</strong></p>
                                            <p><?php echo $metadata['type_consultation'] ?? 'Consultation standard'; ?></p>
                                        </div>
                                        <div class="col-md-3">
                                            <p class="mb-1"><strong>Durée :</strong></p>
                                            <p><?php echo $metadata['duree_consultation'] ?? 'Non spécifié'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sections du compte rendu -->
                        <div class="compte-rendu-content">
                            
                            <!-- Anamnèse -->
                            <?php if (!empty($metadata['anamnese'])): ?>
                            <div class="section mb-4">
                                <h6 class="fw-bold border-bottom pb-2 mb-3">ANAMNÈSE</h6>
                                <div class="border rounded p-3 bg-light">
                                    <?php 
                                    if (is_array($metadata['anamnese'])) {
                                        foreach ($metadata['anamnese'] as $key => $value) {
                                            if (!empty($value)) {
                                                echo '<p><strong>' . ucfirst($key) . ' :</strong> ' . nl2br(htmlspecialchars($value)) . '</p>';
                                            }
                                        }
                                    } else {
                                        echo nl2br(htmlspecialchars($metadata['anamnese']));
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Examen clinique -->
                            <?php if (!empty($metadata['examen_clinique'])): ?>
                            <div class="section mb-4">
                                <h6 class="fw-bold border-bottom pb-2 mb-3">EXAMEN CLINIQUE</h6>
                                <div class="border rounded p-3 bg-light">
                                    <?php echo nl2br(htmlspecialchars($metadata['examen_clinique'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Examens complémentaires -->
                            <?php if (!empty($metadata['examens_complementaires'])): ?>
                            <div class="section mb-4">
                                <h6 class="fw-bold border-bottom pb-2 mb-3">EXAMENS COMPLÉMENTAIRES</h6>
                                <div class="border rounded p-3 bg-light">
                                    <?php echo nl2br(htmlspecialchars($metadata['examens_complementaires'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Diagnostic -->
                            <?php if (!empty($metadata['diagnostic'])): ?>
                            <div class="section mb-4">
                                <h6 class="fw-bold border-bottom pb-2 mb-3">DIAGNOSTIC</h6>
                                <div class="border rounded p-3 bg-light">
                                    <?php 
                                    if (is_array($metadata['diagnostic'])) {
                                        foreach ($metadata['diagnostic'] as $key => $value) {
                                            if (!empty($value)) {
                                                echo '<p><strong>' . ucfirst($key) . ' :</strong> ' . nl2br(htmlspecialchars($value)) . '</p>';
                                            }
                                        }
                                    } else {
                                        echo nl2br(htmlspecialchars($metadata['diagnostic']));
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Traitement -->
                            <?php if (!empty($metadata['traitement'])): ?>
                            <div class="section mb-4">
                                <h6 class="fw-bold border-bottom pb-2 mb-3">TRAITEMENT PRESCRIT</h6>
                                <div class="border rounded p-3 bg-light">
                                    <?php echo nl2br(htmlspecialchars($metadata['traitement'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Recommandations -->
                            <?php if (!empty($metadata['recommandations'])): ?>
                            <div class="section mb-4">
                                <h6 class="fw-bold border-bottom pb-2 mb-3">RECOMMANDATIONS</h6>
                                <div class="border rounded p-3 bg-light">
                                    <?php echo nl2br(htmlspecialchars($metadata['recommandations'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Suivi -->
                            <?php if (!empty($metadata['suivi'])): ?>
                            <div class="section mb-4">
                                <h6 class="fw-bold border-bottom pb-2 mb-3">PROPOSITION DE SUIVI</h6>
                                <div class="border rounded p-3 bg-light">
                                    <?php 
                                    if (is_array($metadata['suivi'])) {
                                        foreach ($metadata['suivi'] as $key => $value) {
                                            if (!empty($value)) {
                                                echo '<p><strong>' . ucfirst($key) . ' :</strong> ' . nl2br(htmlspecialchars($value)) . '</p>';
                                            }
                                        }
                                    } else {
                                        echo nl2br(htmlspecialchars($metadata['suivi']));
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Observations générales -->
                            <?php if ($compte_rendu['description']): ?>
                            <div class="section mb-4">
                                <h6 class="fw-bold border-bottom pb-2 mb-3">OBSERVATIONS GÉNÉRALES</h6>
                                <div class="border rounded p-3 bg-light">
                                    <?php echo nl2br(htmlspecialchars($compte_rendu['description'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                        </div>
                        
                        <!-- Document scanné -->
                        <?php if ($compte_rendu['fichier_path']): ?>
                        <div class="border rounded p-3 mb-4">
                            <h6 class="mb-3">Document scanné</h6>
                            <div class="text-center">
                                <?php 
                                $extension = pathinfo($compte_rendu['fichier_path'], PATHINFO_EXTENSION);
                                if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])): 
                                ?>
                                <img src="../uploads/<?php echo $compte_rendu['fichier_path']; ?>" 
                                     class="img-fluid rounded border" 
                                     alt="Compte rendu" 
                                     style="max-height: 500px;">
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-file-pdf fa-3x mb-3"></i>
                                    <p>Document PDF : <?php echo $compte_rendu['fichier_path']; ?></p>
                                    <a href="../uploads/<?php echo $compte_rendu['fichier_path']; ?>" 
                                       class="btn btn-primary" target="_blank">
                                        <i class="fas fa-eye me-1"></i>Voir le document
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Signature -->
                        <div class="text-end mt-4 pt-4 border-top">
                            <div class="d-inline-block text-center">
                                <p class="mb-2">Fait à <?php echo $compte_rendu['ville'] ?? ''; ?>, le <?php echo date('d/m/Y', strtotime($compte_rendu['created_at'])); ?></p>
                                <p class="fw-bold mb-1">Dr. <?php echo $compte_rendu['docteur_prenom'] . ' ' . $compte_rendu['docteur_nom']; ?></p>
                                <p class="text-muted small">Médecin traitant</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            Rédigé le : <?php echo date('d/m/Y H:i', strtotime($compte_rendu['created_at'])); ?> |
                            <i class="fas fa-user-md me-1"></i>
                            Médecin : Dr. <?php echo $compte_rendu['docteur_prenom'] . ' ' . $compte_rendu['docteur_nom']; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .compte-rendu-content {
        font-family: 'Times New Roman', serif;
    }
    .section {
        page-break-inside: avoid;
    }
    </style>
    
    <?php
    require_once '../includes/footer.php';
}

// Fonction pour ajouter un compte-rendu
function addCompteRendu() {
    global $pdo, $docteur_id;
    
    // Récupérer les patients avec consultations récentes
    $patients_stmt = $pdo->prepare("
        SELECT DISTINCT p.id, p.nom, p.prenom, p.code_patient, p.date_naissance
        FROM patients p
        LEFT JOIN consultations c ON p.id = c.patient_id AND c.docteur_id = ?
        WHERE p.statut = 'actif'
        ORDER BY p.nom, p.prenom
    ");
    $patients_stmt->execute([$docteur_id]);
    $patients = $patients_stmt->fetchAll();
    
    require_once '../includes/header.php';
    ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>Nouveau Compte Rendu
                        </h4>
                    </div>
                    <div class="card-body">
                        <form id="compteRenduForm" enctype="multipart/form-data">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Patient *</label>
                                        <select class="form-select" name="patient_id" id="patientSelect" required>
                                            <option value="">Sélectionner un patient...</option>
                                            <?php foreach ($patients as $patient): 
                                                $age = calculateAge($patient['date_naissance']);
                                            ?>
                                            <option value="<?php echo $patient['id']; ?>">
                                                <?php echo $patient['prenom'] . ' ' . $patient['nom']; ?> 
                                                (<?php echo $patient['code_patient']; ?>, <?php echo $age; ?> ans)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Consultation associée</label>
                                        <select class="form-select" name="consultation_id" id="consultationSelect">
                                            <option value="">Sélectionner une consultation...</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Titre du compte rendu *</label>
                                <input type="text" class="form-control" name="titre" 
                                       placeholder="Ex: Compte rendu de consultation du [date]" required>
                            </div>
                            
                            <!-- Sections du compte rendu -->
                            <div class="accordion mb-4" id="compteRenduAccordion">
                                
                                <!-- Anamnèse -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#anamneseSection">
                                            <i class="fas fa-clipboard-list me-2"></i>Anamnèse
                                        </button>
                                    </h2>
                                    <div id="anamneseSection" class="accordion-collapse collapse show">
                                        <div class="accordion-body">
                                            <div class="mb-3">
                                                <label class="form-label">Motif de consultation</label>
                                                <textarea class="form-control" name="anamnese[motif]" rows="2" 
                                                          placeholder="Motif principal de la consultation..."></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Antécédents médicaux</label>
                                                <textarea class="form-control" name="anamnese[antecedents]" rows="3" 
                                                          placeholder="Antécédents personnels et familiaux..."></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Traitements en cours</label>
                                                <textarea class="form-control" name="anamnese[traitements]" rows="2" 
                                                          placeholder="Médicaments, posologie..."></textarea>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label">Allergies</label>
                                                <textarea class="form-control" name="anamnese[allergies]" rows="2" 
                                                          placeholder="Allergies connues..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Examen clinique -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#examenSection">
                                            <i class="fas fa-stethoscope me-2"></i>Examen Clinique
                                        </button>
                                    </h2>
                                    <div id="examenSection" class="accordion-collapse collapse">
                                        <div class="accordion-body">
                                            <div class="mb-3">
                                                <label class="form-label">Signes cliniques</label>
                                                <textarea class="form-control" name="examen_clinique" rows="4" 
                                                          placeholder="Constantes vitales, examen physique, observations..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Examens complémentaires -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#examensComplementairesSection">
                                            <i class="fas fa-vial me-2"></i>Examens Complémentaires
                                        </button>
                                    </h2>
                                    <div id="examensComplementairesSection" class="accordion-collapse collapse">
                                        <div class="accordion-body">
                                            <div class="mb-3">
                                                <label class="form-label">Examens demandés/réalisés</label>
                                                <textarea class="form-control" name="examens_complementaires" rows="4" 
                                                          placeholder="Analyses biologiques, imagerie, autres examens..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Diagnostic -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#diagnosticSection">
                                            <i class="fas fa-diagnoses me-2"></i>Diagnostic
                                        </button>
                                    </h2>
                                    <div id="diagnosticSection" class="accordion-collapse collapse">
                                        <div class="accordion-body">
                                            <div class="mb-3">
                                                <label class="form-label">Diagnostic principal</label>
                                                <input type="text" class="form-control" name="diagnostic[principal]" 
                                                       placeholder="Diagnostic principal...">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Diagnostics secondaires</label>
                                                <textarea class="form-control" name="diagnostic[secondaires]" rows="2" 
                                                          placeholder="Diagnostics associés..."></textarea>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label">CIM-10 / Codes</label>
                                                <input type="text" class="form-control" name="diagnostic[codes]" 
                                                       placeholder="Codes CIM-10...">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Traitement -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#traitementSection">
                                            <i class="fas fa-prescription me-2"></i>Traitement
                                        </button>
                                    </h2>
                                    <div id="traitementSection" class="accordion-collapse collapse">
                                        <div class="accordion-body">
                                            <div class="mb-3">
                                                <label class="form-label">Traitement prescrit</label>
                                                <textarea class="form-control" name="traitement" rows="4" 
                                                          placeholder="Médicaments, posologie, durée..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Recommandations -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#recommandationsSection">
                                            <i class="fas fa-comment-medical me-2"></i>Recommandations
                                        </button>
                                    </h2>
                                    <div id="recommandationsSection" class="accordion-collapse collapse">
                                        <div class="accordion-body">
                                            <div class="mb-3">
                                                <label class="form-label">Conseils et recommandations</label>
                                                <textarea class="form-control" name="recommandations" rows="3" 
                                                          placeholder="Conseils hygiéno-diététiques, précautions..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Suivi -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#suiviSection">
                                            <i class="fas fa-calendar-check me-2"></i>Suivi
                                        </button>
                                    </h2>
                                    <div id="suiviSection" class="accordion-collapse collapse">
                                        <div class="accordion-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Date de suivi</label>
                                                        <input type="date" class="form-control" name="suivi[date]">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Type de suivi</label>
                                                        <select class="form-select" name="suivi[type]">
                                                            <option value="">Sélectionner...</option>
                                                            <option value="consultation">Consultation</option>
                                                            <option value="teleconsultation">Téléconsultation</option>
                                                            <option value="bilan">Bilan</option>
                                                            <option value="controle">Contrôle</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label">Instructions de suivi</label>
                                                <textarea class="form-control" name="suivi[instructions]" rows="3" 
                                                          placeholder="Instructions spécifiques pour le suivi..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                            
                            <!-- Observations générales -->
                            <div class="mb-3">
                                <label class="form-label">Observations générales</label>
                                <textarea class="form-control" name="description" rows="3" 
                                          placeholder="Observations, commentaires supplémentaires..."></textarea>
                            </div>
                            
                            <!-- Section upload du document -->
                            <div class="border rounded p-4 mb-4">
                                <h5 class="mb-3">
                                    <i class="fas fa-file-upload me-2"></i>Document scanné (optionnel)
                                </h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">Document (PDF ou Image)</label>
                                    <input type="file" class="form-control" name="document_file" 
                                           accept=".pdf,.jpg,.jpeg,.png,.gif">
                                    <small class="text-muted">
                                        Formats acceptés : PDF, JPG, PNG, GIF (Max 10MB)
                                    </small>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="comptes-rendus.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Annuler
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer le compte rendu
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Charger les consultations du patient
    document.getElementById('patientSelect').addEventListener('change', function() {
        const patientId = this.value;
        const consultationSelect = document.getElementById('consultationSelect');
        
        if (!patientId) {
            consultationSelect.innerHTML = '<option value="">Sélectionner une consultation...</option>';
            return;
        }
        
        fetch(`../ajax/get_consultations.php?patient_id=${patientId}&docteur_id=<?php echo $docteur_id; ?>`)
            .then(response => response.json())
            .then(data => {
                let options = '<option value="">Sélectionner une consultation...</option>';
                data.forEach(consultation => {
                    const date = new Date(consultation.date_consultation).toLocaleDateString('fr-FR');
                    options += `<option value="${consultation.id}">${date} - ${consultation.motif}</option>`;
                });
                consultationSelect.innerHTML = options;
            })
            .catch(error => {
                console.error('Error:', error);
            });
    });
    
    // Gestion du formulaire
    document.getElementById('compteRenduForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Collecter les données des sections
        const formData = new FormData(this);
        
        // Structurer les données
        const sections = {};
        
        // Anamnèse
        sections.anamnese = {
            motif: formData.get('anamnese[motif]'),
            antecedents: formData.get('anamnese[antecedents]'),
            traitements: formData.get('anamnese[traitements]'),
            allergies: formData.get('anamnese[allergies]')
        };
        
        // Diagnostic
        sections.diagnostic = {
            principal: formData.get('diagnostic[principal]'),
            secondaires: formData.get('diagnostic[secondaires]'),
            codes: formData.get('diagnostic[codes]')
        };
        
        // Suivi
        sections.suivi = {
            date: formData.get('suivi[date]'),
            type: formData.get('suivi[type]'),
            instructions: formData.get('suivi[instructions]')
        };
        
        // Ajouter les métadonnées
        formData.append('metadata', JSON.stringify({
            ...sections,
            examen_clinique: formData.get('examen_clinique'),
            examens_complementaires: formData.get('examens_complementaires'),
            traitement: formData.get('traitement'),
            recommandations: formData.get('recommandations'),
            type_consultation: 'standard',
            duree_consultation: '30 minutes'
        }));
        
        // Ajouter l'action et le type
        formData.append('action', 'add');
        formData.append('type', 'compte_rendu');
        
        // Nettoyer les données des sections individuelles
        ['anamnese[motif]', 'anamnese[antecedents]', 'anamnese[traitements]', 'anamnese[allergies]',
         'diagnostic[principal]', 'diagnostic[secondaires]', 'diagnostic[codes]',
         'suivi[date]', 'suivi[type]', 'suivi[instructions]',
         'examen_clinique', 'examens_complementaires', 'traitement', 'recommandations'].forEach(field => {
            formData.delete(field);
        });
        
        fetch('../ajax/save_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Compte rendu enregistré avec succès!');
                window.location.href = 'comptes-rendus.php?action=view&id=' + result.id;
            } else {
                alert('Erreur: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue');
        });
    });
    </script>
    
    <?php
    require_once '../includes/footer.php';
}

// Fonction pour modifier un compte-rendu
function editCompteRendu($id) {
    global $pdo, $docteur_id;
    
    // Récupérer le compte rendu
    $stmt = $pdo->prepare("
        SELECT d.*, p.nom, p.prenom
        FROM documents_medicaux d
        JOIN patients p ON d.patient_id = p.id
        WHERE d.id = ? AND d.docteur_id = ? AND d.type = 'compte_rendu'
    ");
    $stmt->execute([$id, $docteur_id]);
    $compte_rendu = $stmt->fetch();
    
    if (!$compte_rendu) {
        header('Location: comptes-rendus.php');
        exit;
    }
    
    // Récupérer les métadonnées
    $metadata = [];
    if (!empty($compte_rendu['metadata'])) {
        $metadata = json_decode($compte_rendu['metadata'], true);
    }
    
    require_once '../includes/header.php';
    ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h4 class="mb-0">
                            <i class="fas fa-edit me-2"></i>Modifier le Compte Rendu
                        </h4>
                    </div>
                    <div class="card-body">
                        <form id="editCompteRenduForm" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Titre du compte rendu *</label>
                                <input type="text" class="form-control" name="titre" 
                                       value="<?php echo htmlspecialchars($compte_rendu['titre']); ?>" required>
                            </div>
                            
                            <!-- Observations générales -->
                            <div class="mb-3">
                                <label class="form-label">Observations générales</label>
                                <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($compte_rendu['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Document actuel -->
                            <?php if ($compte_rendu['fichier_path']): ?>
                            <div class="border rounded p-3 mb-3">
                                <h6>Document actuel</h6>
                                <div class="d-flex align-items-center">
                                    <?php 
                                    $extension = pathinfo($compte_rendu['fichier_path'], PATHINFO_EXTENSION);
                                    if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])):
                                    ?>
                                    <img src="../uploads/<?php echo $compte_rendu['fichier_path']; ?>" 
                                         class="img-thumbnail me-3" style="width: 100px; height: 100px; object-fit: cover;">
                                    <?php else: ?>
                                    <div class="bg-light rounded p-3 me-3">
                                        <i class="fas fa-file-pdf fa-2x text-danger"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="mb-1"><?php echo $compte_rendu['fichier_path']; ?></p>
                                        <a href="../uploads/<?php echo $compte_rendu['fichier_path']; ?>" 
                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>Voir
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Nouveau document -->
                            <div class="border rounded p-3 mb-4">
                                <h6 class="mb-3">Remplacer le document</h6>
                                <div class="mb-3">
                                    <label class="form-label">Nouveau document (PDF ou Image)</label>
                                    <input type="file" class="form-control" name="new_document_file" 
                                           accept=".pdf,.jpg,.jpeg,.png,.gif">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="comptes-rendus.php?action=view&id=<?php echo $id; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Annuler
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Gestion du formulaire
    document.getElementById('editCompteRenduForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'edit');
        formData.append('type', 'compte_rendu');
        
        fetch('../ajax/save_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Compte rendu modifié avec succès!');
                window.location.href = 'comptes-rendus.php?action=view&id=' + result.id;
            } else {
                alert('Erreur: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue');
        });
    });
    </script>
    
    <?php
    require_once '../includes/footer.php';
}

// Liste principale des comptes rendus
require_once '../includes/header.php';

// Compter les comptes rendus
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN consultation_id IS NOT NULL THEN 1 ELSE 0 END) as avec_consultation,
        SUM(CASE WHEN fichier_path IS NOT NULL THEN 1 ELSE 0 END) as avec_document,
        COUNT(DISTINCT DATE(created_at)) as jours_avec_cr
    FROM documents_medicaux
    WHERE docteur_id = ? AND type = 'compte_rendu'
");
$stats_stmt->execute([$docteur_id]);
$stats = $stats_stmt->fetch();

// Récupérer les comptes rendus
$comptes_rendus_stmt = $pdo->prepare("
    SELECT d.*, p.nom, p.prenom, p.code_patient, p.date_naissance,
           c.date_consultation, c.motif as motif_consultation
    FROM documents_medicaux d
    JOIN patients p ON d.patient_id = p.id
    LEFT JOIN consultations c ON d.consultation_id = c.id
    WHERE d.docteur_id = ? AND d.type = 'compte_rendu'
    ORDER BY d.created_at DESC
    LIMIT 20
");
$comptes_rendus_stmt->execute([$docteur_id]);
$comptes_rendus = $comptes_rendus_stmt->fetchAll();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-file-medical-alt me-2"></i>Comptes Rendus Médicaux
        </h1>
        <p class="text-muted mb-0">
            <span class="fw-semibold">Dr. <?php echo $_SESSION['prenom'] . ' ' . $_SESSION['nom']; ?></span>
        </p>
    </div>
    <div class="btn-toolbar">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportComptesRendus()">
                <i class="fas fa-download me-1"></i>Exporter
            </button>
        </div>
        <a href="comptes-rendus.php?action=add" class="btn btn-sm btn-primary">
            <i class="fas fa-plus-circle me-1"></i>Nouveau compte rendu
        </a>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total</div>
                        <div class="h4 mb-0"><?php echo $stats['total']; ?></div>
                    </div>
                    <div class="rounded-circle bg-primary-light p-3">
                        <i class="fas fa-file-medical-alt text-primary fa-lg"></i>
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
                        <div class="text-muted small">Avec consultation</div>
                        <div class="h4 mb-0"><?php echo $stats['avec_consultation']; ?></div>
                    </div>
                    <div class="rounded-circle bg-success-light p-3">
                        <i class="fas fa-stethoscope text-success fa-lg"></i>
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
                        <div class="text-muted small">Avec document</div>
                        <div class="h4 mb-0"><?php echo $stats['avec_document']; ?></div>
                    </div>
                    <div class="rounded-circle bg-info-light p-3">
                        <i class="fas fa-file-pdf text-info fa-lg"></i>
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
                        <div class="text-muted small">Jours avec CR</div>
                        <div class="h4 mb-0"><?php echo $stats['jours_avec_cr']; ?></div>
                    </div>
                    <div class="rounded-circle bg-warning-light p-3">
                        <i class="fas fa-calendar-alt text-warning fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" class="form-control" placeholder="Rechercher par patient..." 
                       name="search" value="<?php echo $_GET['search'] ?? ''; ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="has_consultation">
                    <option value="">Tous les CR</option>
                    <option value="1" <?php echo ($_GET['has_consultation'] ?? '') == '1' ? 'selected' : ''; ?>>Avec consultation</option>
                    <option value="0" <?php echo ($_GET['has_consultation'] ?? '') == '0' ? 'selected' : ''; ?>>Sans consultation</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control" name="date_from" 
                       value="<?php echo $_GET['date_from'] ?? ''; ?>" placeholder="Date de début">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des comptes rendus -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Comptes rendus récents
        </h5>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                    data-bs-toggle="dropdown">
                <i class="fas fa-filter me-1"></i>Actions
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="printComptesRendus()">Imprimer tous</a></li>
                <li><a class="dropdown-item" href="#" onclick="generateStats()">Statistiques</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="checkIncomplete()">Vérifier les incomplets</a></li>
            </ul>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($comptes_rendus)): ?>
        <div class="text-center py-5">
            <i class="fas fa-file-medical-alt fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Aucun compte rendu trouvé</h5>
            <p class="text-muted small mb-3">Vous n'avez pas encore rédigé de comptes rendus</p>
            <a href="comptes-rendus.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Rédiger votre premier compte rendu
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Titre</th>
                        <th>Consultation</th>
                        <th>Date</th>
                        <th>Document</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comptes_rendus as $doc): 
                        $age = calculateAge($doc['date_naissance']);
                        $hasConsultation = !empty($doc['consultation_id']);
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar me-3">
                                    <?php echo strtoupper(substr($doc['prenom'], 0, 1) . substr($doc['nom'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?php echo $doc['prenom'] . ' ' . $doc['nom']; ?></div>
                                    <small class="text-muted">
                                        <?php echo $doc['code_patient']; ?> • <?php echo $age; ?> ans
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo $doc['titre']; ?></div>
                            <?php if ($doc['description']): ?>
                            <small class="text-muted">
                                <?php echo substr($doc['description'], 0, 60); ?>
                                <?php if (strlen($doc['description']) > 60): ?>...<?php endif; ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($hasConsultation): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check me-1"></i>Associée
                            </span>
                            <?php if ($doc['date_consultation']): ?>
                            <small class="text-muted d-block"><?php echo date('d/m/Y', strtotime($doc['date_consultation'])); ?></small>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="badge bg-secondary">
                                <i class="fas fa-times me-1"></i>Non associée
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?php echo date('d/m/Y', strtotime($doc['created_at'])); ?></div>
                            <small class="text-muted"><?php echo date('H:i', strtotime($doc['created_at'])); ?></small>
                        </td>
                        <td>
                            <?php if ($doc['fichier_path']): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check me-1"></i>Disponible
                            </span>
                            <?php else: ?>
                            <span class="badge bg-danger">
                                <i class="fas fa-times me-1"></i>Manquant
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="comptes-rendus.php?action=view&id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-outline-primary" title="Voir">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="comptes-rendus.php?action=edit&id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-outline-secondary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($doc['fichier_path']): ?>
                                <a href="../uploads/<?php echo $doc['fichier_path']; ?>" 
                                   class="btn btn-outline-success" title="Télécharger" download>
                                    <i class="fas fa-download"></i>
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
                    Affichage des 20 derniers comptes rendus
                </small>
            </div>
            <div class="col-md-6 text-end">
                <a href="comptes-rendus.php?view=all" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-archive me-1"></i>Voir tous les comptes rendus
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
function exportComptesRendus() {
    window.open('../ajax/export_comptes_rendus.php', '_blank');
}

function printComptesRendus() {
    const ids = Array.from(document.querySelectorAll('tr')).slice(1).map(tr => {
        const link = tr.querySelector('a[href*="action=view"]');
        return link ? link.href.split('id=')[1] : null;
    }).filter(id => id);
    
    window.open(`../ajax/print_multiple.php?type=compte_rendu&ids=${ids.join(',')}`, '_blank');
}

function generateStats() {
    window.open('../ajax/stats_comptes_rendus.php', '_blank');
}

function checkIncomplete() {
    const incomplete = document.querySelectorAll('tr .badge.bg-danger, tr .badge.bg-secondary');
    if (incomplete.length > 0) {
        alert(`Attention : ${incomplete.length} compte(s) rendu(s) incomplet(s) (sans document ou sans consultation).`);
    } else {
        alert('Tous les comptes rendus sont complets.');
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
</style>