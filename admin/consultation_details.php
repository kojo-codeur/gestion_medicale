<?php
// admin/consultation_details.php
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

checkRole($role);

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: ../docteur/consultations.php?error=ID consultation manquant");
    exit();
}

// Récupérer les informations de la consultation
$stmt = $pdo->prepare("
    SELECT c.*,
           p.nom as patient_nom, p.prenom as patient_prenom, p.code_patient, 
           p.date_naissance, p.sexe, p.telephone, p.email,
           p.adresse, p.ville, p.code_postal,
           p.groupe_sanguin, p.rhésus, p.poids, p.taille, p.imc,
           p.antecedents_familiaux, p.antecedents_personnels, p.allergies,
           p.medicaments_habituels, p.habitudes,
           d.nom as docteur_nom, d.prenom as docteur_prenom, d.specialite as docteur_specialite,
           a.nom as assistant_nom, a.prenom as assistant_prenom,
           u.nom as created_by_nom, u.prenom as created_by_prenom
    FROM consultations c
    LEFT JOIN patients p ON c.patient_id = p.id
    LEFT JOIN utilisateurs d ON c.docteur_id = d.id
    LEFT JOIN utilisateurs a ON c.assistant_id = a.id
    LEFT JOIN utilisateurs u ON c.created_by = u.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$consultation = $stmt->fetch();

if (!$consultation) {
    header("Location: ../docteur/consultations.php?error=Consultation non trouvée");
    exit();
}

// Calculer l'âge du patient
$age = calculateAge($consultation['date_naissance']);

// Récupérer la prescription associée
$prescriptionStmt = $pdo->prepare("
    SELECT p.*, 
           d.nom as docteur_nom, d.prenom as docteur_prenom
    FROM prescriptions p
    LEFT JOIN utilisateurs d ON p.docteur_id = d.id
    WHERE p.consultation_id = ?
    ORDER BY p.date_prescription DESC
    LIMIT 1
");
$prescriptionStmt->execute([$id]);
$prescription = $prescriptionStmt->fetch();

// Récupérer les documents associés
$documentsStmt = $pdo->prepare("
    SELECT dm.*, 
           u.nom as docteur_nom, u.prenom as docteur_prenom
    FROM documents_medicaux dm
    LEFT JOIN utilisateurs u ON dm.docteur_id = u.id
    WHERE dm.consultation_id = ?
    ORDER BY dm.created_at DESC
");
$documentsStmt->execute([$id]);
$documents = $documentsStmt->fetchAll();

$title = 'Détails Consultation - ' . $consultation['patient_prenom'] . ' ' . $consultation['patient_nom'];
require_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-stethoscope me-2"></i>Détails Consultation
        </h1>
        <p class="text-muted mb-0">Fiche complète de consultation médicale</p>
    </div>
    <div class="btn-toolbar">
        <div class="btn-group me-2">
            <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'docteur'): ?>
            <a href="../docteur/consultations.php?action=edit&id=<?php echo $id; ?>" 
               class="btn btn-primary">
                <i class="fas fa-edit me-1"></i>Modifier
            </a>
            <?php endif; ?>
            
            <button type="button" class="btn btn-success" onclick="printConsultation()">
                <i class="fas fa-print me-1"></i>Imprimer
            </button>
            
            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" 
                    data-bs-toggle="dropdown">
                <span class="visually-hidden">Options</span>
            </button>
            <div class="dropdown-menu">
                <?php if (!$prescription && in_array($_SESSION['role'], ['admin', 'docteur'])): ?>
                <a class="dropdown-item" href="../docteur/prescriptions.php?action=add&consultation_id=<?php echo $id; ?>">
                    <i class="fas fa-prescription me-2"></i>Créer ordonnance
                </a>
                <?php endif; ?>
                <a class="dropdown-item" href="../docteur/documents.php?action=add&consultation_id=<?php echo $id; ?>">
                    <i class="fas fa-file-upload me-2"></i>Ajouter document
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="patient_details.php?id=<?php echo $consultation['patient_id']; ?>">
                    <i class="fas fa-user-injured me-2"></i>Voir fiche patient
                </a>
                <a class="dropdown-item" href="../docteur/consultations.php?action=add&patient_id=<?php echo $consultation['patient_id']; ?>">
                    <i class="fas fa-plus-circle me-2"></i>Nouvelle consultation
                </a>
            </div>
        </div>
        <a href="../docteur/consultations.php<?php echo isset($_GET['from']) && $_GET['from'] == 'patient' ? '?patient_id=' . $consultation['patient_id'] : ''; ?>" 
           class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Retour
        </a>
    </div>
</div>

<div class="row">
    <!-- Colonne gauche - Informations consultation -->
    <div class="col-lg-4">
        <!-- Carte identification -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-id-card me-2"></i>Identification
                </h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="avatar-details mx-auto mb-3">
                        <?php echo strtoupper(substr($consultation['patient_prenom'], 0, 1) . substr($consultation['patient_nom'], 0, 1)); ?>
                    </div>
                    <h4><?php echo $consultation['patient_prenom'] . ' ' . $consultation['patient_nom']; ?></h4>
                    <p class="text-muted mb-1">
                        Code: <span class="badge bg-info"><?php echo $consultation['code_patient']; ?></span>
                    </p>
                    <p class="mb-0">
                        <span class="badge bg-light text-dark me-1">
                            <?php echo $age; ?> ans
                        </span>
                        <span class="badge bg-light text-dark">
                            <?php echo $consultation['sexe'] == 'M' ? 'Masculin' : 'Féminin'; ?>
                        </span>
                    </p>
                </div>
                
                <div class="row small">
                    <div class="col-6">
                        <p class="mb-1"><strong>Date:</strong><br>
                            <?php echo date('d/m/Y', strtotime($consultation['date_consultation'])); ?>
                        </p>
                        <p class="mb-1"><strong>Heure:</strong><br>
                            <?php echo date('H:i', strtotime($consultation['date_consultation'])); ?>
                        </p>
                    </div>
                    <div class="col-6">
                        <?php if ($consultation['poids'] && $consultation['taille']): 
                            $bmi = $consultation['imc'] ?? ($consultation['poids'] / pow($consultation['taille'] / 100, 2));
                            $bmiClass = $bmi < 18.5 ? 'info' : ($bmi < 25 ? 'success' : ($bmi < 30 ? 'warning' : 'danger'));
                        ?>
                        <p class="mb-1"><strong>Poids/Taille:</strong><br>
                            <?php echo $consultation['poids']; ?> kg / <?php echo $consultation['taille']; ?> cm
                        </p>
                        <p class="mb-0">
                            <strong>IMC:</strong> 
                            <span class="badge bg-<?php echo $bmiClass; ?>">
                                <?php echo number_format($bmi, 1); ?>
                            </span>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($consultation['groupe_sanguin']): ?>
                <div class="mt-3 pt-3 border-top">
                    <p class="mb-0">
                        <strong>Groupe sanguin:</strong> 
                        <span class="badge bg-danger"><?php echo $consultation['groupe_sanguin']; ?><?php echo $consultation['rhésus']; ?></span>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Informations contact -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-address-card me-2"></i>Contact</h6>
            </div>
            <div class="card-body">
                <div class="small">
                    <?php if ($consultation['telephone']): ?>
                    <p class="mb-2">
                        <i class="fas fa-phone text-primary me-2"></i>
                        <a href="tel:<?php echo $consultation['telephone']; ?>">
                            <?php echo $consultation['telephone']; ?>
                        </a>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($consultation['email']): ?>
                    <p class="mb-2">
                        <i class="fas fa-envelope text-primary me-2"></i>
                        <a href="mailto:<?php echo $consultation['email']; ?>">
                            <?php echo $consultation['email']; ?>
                        </a>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($consultation['adresse'] || $consultation['ville']): ?>
                    <p class="mb-0">
                        <i class="fas fa-home text-primary me-2"></i>
                        <?php if ($consultation['adresse']): ?>
                        <?php echo $consultation['adresse']; ?><br>
                        <?php endif; ?>
                        <?php if ($consultation['code_postal'] || $consultation['ville']): ?>
                        <?php echo $consultation['code_postal']; ?> <?php echo $consultation['ville']; ?>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Informations médicales rapides -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Antécédents rapides</h6>
            </div>
            <div class="card-body">
                <?php if ($consultation['allergies']): ?>
                <div class="alert alert-danger py-2 mb-3">
                    <strong><i class="fas fa-exclamation-triangle me-1"></i>Allergies:</strong><br>
                    <?php echo nl2br(htmlspecialchars(substr($consultation['allergies'], 0, 100))); ?>
                    <?php if (strlen($consultation['allergies']) > 100): ?>...<?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($consultation['medicaments_habituels']): ?>
                <div class="mb-3">
                    <strong>Traitements habituels:</strong><br>
                    <small class="text-muted">
                        <?php echo nl2br(htmlspecialchars(substr($consultation['medicaments_habituels'], 0, 100))); ?>
                        <?php if (strlen($consultation['medicaments_habituels']) > 100): ?>...<?php endif; ?>
                    </small>
                </div>
                <?php endif; ?>
                
                <?php if ($consultation['antecedents_personnels']): ?>
                <div>
                    <strong>Antécédents personnels:</strong><br>
                    <small class="text-muted">
                        <?php echo nl2br(htmlspecialchars(substr($consultation['antecedents_personnels'], 0, 100))); ?>
                        <?php if (strlen($consultation['antecedents_personnels']) > 100): ?>...<?php endif; ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Information consultation -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Information consultation</h6>
            </div>
            <div class="card-body">
                <div class="small">
                    <p class="mb-1">
                        <strong>Référence:</strong><br>
                        <code><?php echo $consultation['reference']; ?></code>
                    </p>
                    
                    <p class="mb-1">
                        <strong>Médecin:</strong><br>
                        Dr. <?php echo $consultation['docteur_prenom'] . ' ' . $consultation['docteur_nom']; ?>
                        <?php if ($consultation['docteur_specialite']): ?>
                        <br><small class="text-muted">(<?php echo $consultation['docteur_specialite']; ?>)</small>
                        <?php endif; ?>
                    </p>
                    
                    <?php if ($consultation['assistant_prenom']): ?>
                    <p class="mb-1">
                        <strong>Assistant:</strong><br>
                        <?php echo $consultation['assistant_prenom'] . ' ' . $consultation['assistant_nom']; ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php 
                        $typeLabels = [
                            'premiere' => 'Première consultation',
                            'suivi' => 'Consultation de suivi',
                            'urgence' => 'Consultation urgente',
                            'controle' => 'Consultation de contrôle'
                        ];
                        $type = $typeLabels[$consultation['type_consultation']] ?? $consultation['type_consultation'];
                    ?>
                    <p class="mb-1">
                        <strong>Type:</strong> <?php echo $type; ?>
                    </p>
                    
                    <p class="mb-1">
                        <strong>Statut:</strong> 
                        <span class="badge bg-<?php echo $consultation['statut'] == 'termine' ? 'success' : ($consultation['statut'] == 'annule' ? 'danger' : 'warning'); ?>">
                            <?php echo ucfirst($consultation['statut']); ?>
                        </span>
                        <?php if ($consultation['urgence']): ?>
                        <span class="badge bg-danger ms-1">Urgent</span>
                        <?php endif; ?>
                    </p>
                    
                    <p class="mb-1">
                        <strong>Durée:</strong> <?php echo $consultation['duree'] ?? 30; ?> minutes
                    </p>
                    
                    <p class="mb-1">
                        <strong>Créé par:</strong><br>
                        <?php echo $consultation['created_by_prenom'] . ' ' . $consultation['created_by_nom']; ?>
                    </p>
                    
                    <p class="mb-0">
                        <strong>Date création:</strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($consultation['created_at'])); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Colonne droite - Contenu consultation -->
    <div class="col-lg-8">
        <!-- Onglets -->
        <ul class="nav nav-tabs mb-4" id="consultationTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="motif-tab" data-bs-toggle="tab" 
                        data-bs-target="#motif" type="button">
                    <i class="fas fa-clipboard-list me-1"></i>Motif & Diagnostic
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="traitement-tab" data-bs-toggle="tab" 
                        data-bs-target="#traitement" type="button">
                    <i class="fas fa-pills me-1"></i>Traitement
                    <?php if ($prescription): ?>
                    <span class="badge bg-success ms-1">Ordonnance</span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="examen-tab" data-bs-toggle="tab" 
                        data-bs-target="#examen" type="button">
                    <i class="fas fa-microscope me-1"></i>Examens
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="documents-tab" data-bs-toggle="tab" 
                        data-bs-target="#documents" type="button">
                    <i class="fas fa-file-medical me-1"></i>Documents
                    <?php if ($documents): ?>
                    <span class="badge bg-info ms-1"><?php echo count($documents); ?></span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="consultationTabsContent">
            <!-- Onglet Motif & Diagnostic -->
            <div class="tab-pane fade show active" id="motif" role="tabpanel">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-comment-medical me-2"></i>Motif de consultation</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($consultation['motif']): ?>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['motif'])); ?></p>
                        <?php else: ?>
                        <p class="text-muted mb-0">Non renseigné</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Histoire de la maladie</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($consultation['histoire_maladie']): ?>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['histoire_maladie'])); ?></p>
                        <?php else: ?>
                        <p class="text-muted mb-0">Non renseigné</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-stethoscope me-2"></i>Examen clinique</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($consultation['examen_clinique']): ?>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['examen_clinique'])); ?></p>
                        <?php else: ?>
                        <p class="text-muted mb-0">Non renseigné</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-diagnoses me-2"></i>Diagnostic</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($consultation['diagnostic']): ?>
                        <div class="alert alert-info">
                            <strong>Diagnostic principal:</strong><br>
                            <?php echo nl2br(htmlspecialchars($consultation['diagnostic'])); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($consultation['diagnostic_detail']): 
                            $diagnosticDetail = json_decode($consultation['diagnostic_detail'], true);
                            if (is_array($diagnosticDetail)):
                        ?>
                        <div class="mt-3">
                            <strong>Détails du diagnostic:</strong>
                            <ul class="mb-0">
                                <?php foreach ($diagnosticDetail as $key => $value): 
                                    if (is_string($value) && !empty(trim($value))):
                                ?>
                                <li><strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars($value); ?></li>
                                <?php endif; endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; endif; ?>
                        
                        <?php if (!$consultation['diagnostic'] && !$consultation['diagnostic_detail']): ?>
                        <p class="text-muted mb-0">Aucun diagnostic renseigné</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Traitement -->
            <div class="tab-pane fade" id="traitement" role="tabpanel">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-prescription me-2"></i>Traitement prescrit</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($consultation['traitement']): ?>
                        <div class="mb-4">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['traitement'])); ?></p>
                        </div>
                        <?php else: ?>
                        <p class="text-muted mb-4">Aucun traitement renseigné</p>
                        <?php endif; ?>
                        
                        <!-- Ordonnance associée -->
                        <?php if ($prescription): ?>
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-file-prescription me-2"></i>Ordonnance <?php echo $prescription['reference']; ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <strong>Date:</strong> <?php echo date('d/m/Y', strtotime($prescription['date_prescription'])); ?>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Prescrit par:</strong> Dr. <?php echo $prescription['docteur_prenom'] . ' ' . $prescription['docteur_nom']; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($prescription['duree_traitement']): ?>
                                        <p class="mb-1">
                                            <strong>Durée traitement:</strong> <?php echo $prescription['duree_traitement']; ?>
                                        </p>
                                        <?php endif; ?>
                                        <p class="mb-0">
                                            <strong>Statut:</strong> 
                                            <span class="badge bg-<?php echo $prescription['statut'] == 'active' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($prescription['statut']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                
                                <?php if ($prescription['medicaments']): 
                                    $medicaments = json_decode($prescription['medicaments'], true);
                                    if (is_array($medicaments) && !empty($medicaments)):
                                ?>
                                <div class="mb-3">
                                    <strong>Médicaments prescrits:</strong>
                                    <div class="table-responsive mt-2">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Médicament</th>
                                                    <th>Dosage</th>
                                                    <th>Posologie</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($medicaments as $med): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($med['nom'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($med['dosage'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($med['posologie'] ?? ''); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php endif; endif; ?>
                                
                                <?php if ($prescription['posologie']): ?>
                                <div class="mb-3">
                                    <strong>Posologie:</strong>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($prescription['posologie'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($prescription['notes']): ?>
                                <div class="mb-3">
                                    <strong>Notes:</strong>
                                    <p class="mb-0 small text-muted"><?php echo nl2br(htmlspecialchars($prescription['notes'])); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mt-4">
                                    <a href="../docteur/prescription_details.php?id=<?php echo $prescription['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i>Voir détails ordonnance
                                    </a>
                                    <a href="../docteur/prescriptions.php?action=print&id=<?php echo $prescription['id']; ?>" 
                                       class="btn btn-outline-secondary btn-sm ms-2" target="_blank">
                                        <i class="fas fa-print me-1"></i>Imprimer
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-prescription fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Aucune ordonnance associée</p>
                            <?php if (in_array($_SESSION['role'], ['admin', 'docteur'])): ?>
                            <a href="../docteur/prescriptions.php?action=add&consultation_id=<?php echo $id; ?>" 
                               class="btn btn-success">
                                <i class="fas fa-plus me-1"></i>Créer une ordonnance
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-comments me-2"></i>Recommandations</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($consultation['recommandations']): ?>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['recommandations'])); ?></p>
                        <?php else: ?>
                        <p class="text-muted mb-0">Aucune recommandation</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Examens -->
            <div class="tab-pane fade" id="examen" role="tabpanel">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-microscope me-2"></i>Examens complémentaires</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($consultation['examen_complementaire']): ?>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['examen_complementaire'])); ?></p>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-microscope fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Aucun examen complémentaire demandé</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-notes-medical me-2"></i>Notes médicales</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($consultation['notes']): ?>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['notes'])); ?></p>
                        <?php else: ?>
                        <p class="text-muted mb-0">Aucune note médicale</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Documents -->
            <div class="tab-pane fade" id="documents" role="tabpanel">
                <?php if ($documents): ?>
                <div class="row">
                    <?php foreach ($documents as $doc): 
                        $typeIcons = [
                            'ordonnance' => 'fas fa-prescription',
                            'certificat' => 'fas fa-certificate',
                            'resultat_analyse' => 'fas fa-flask',
                            'compte_rendu' => 'fas fa-file-medical',
                            'imagerie' => 'fas fa-x-ray',
                            'autre' => 'fas fa-file'
                        ];
                        $icon = $typeIcons[$doc['type']] ?? 'fas fa-file';
                        
                        $typeLabels = [
                            'ordonnance' => 'Ordonnance',
                            'certificat' => 'Certificat',
                            'resultat_analyse' => 'Résultat d\'analyse',
                            'compte_rendu' => 'Compte-rendu',
                            'imagerie' => 'Imagerie',
                            'autre' => 'Document'
                        ];
                        $typeLabel = $typeLabels[$doc['type']] ?? $doc['type'];
                    ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="<?php echo $icon; ?> me-2"></i>
                                    <strong><?php echo $typeLabel; ?></strong>
                                </div>
                                <span class="badge bg-light text-dark">
                                    <?php echo date('d/m/Y', strtotime($doc['created_at'])); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title"><?php echo htmlspecialchars($doc['titre']); ?></h6>
                                
                                <?php if ($doc['description']): ?>
                                <p class="card-text small"><?php echo htmlspecialchars(substr($doc['description'], 0, 100)); ?>...</p>
                                <?php endif; ?>
                                
                                <?php if ($doc['docteur_prenom']): ?>
                                <p class="card-text small text-muted mb-2">
                                    <i class="fas fa-user-md me-1"></i>
                                    Dr. <?php echo $doc['docteur_prenom'] . ' ' . $doc['docteur_nom']; ?>
                                </p>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <?php if ($doc['fichier_path'] && file_exists($doc['fichier_path'])): ?>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo $doc['fichier_path']; ?>" 
                                           class="btn btn-outline-primary" target="_blank">
                                            <i class="fas fa-eye me-1"></i>Voir
                                        </a>
                                        <a href="<?php echo $doc['fichier_path']; ?>" 
                                           class="btn btn-outline-secondary" download>
                                            <i class="fas fa-download me-1"></i>Télécharger
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <span class="badge bg-warning">Fichier non disponible</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-center mt-4">
                    <a href="../docteur/documents.php?action=add&consultation_id=<?php echo $id; ?>" 
                       class="btn btn-success">
                        <i class="fas fa-plus me-1"></i>Ajouter un document
                    </a>
                </div>
                
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Aucun document associé</p>
                    <a href="../docteur/documents.php?action=add&consultation_id=<?php echo $id; ?>" 
                       class="btn btn-success">
                        <i class="fas fa-plus me-1"></i>Ajouter un document
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// Fonction d'impression
function printConsultation() {
    const printWindow = window.open('', '_blank');
    
    // Construire le contenu HTML pour l'impression
    const content = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Compte-rendu Consultation - ${"<?php echo $consultation['reference']; ?>"}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; font-size: 12px; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { color: #333; margin-bottom: 10px; }
                .patient-info { display: flex; margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; }
                .patient-left { flex: 1; }
                .patient-right { flex: 1; }
                .section { margin-bottom: 25px; }
                .section-title { background-color: #f5f5f5; padding: 8px; font-weight: bold; border-left: 4px solid #4361ee; margin-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f5f5f5; }
                .diagnostic { background-color: #e8f4fd; padding: 10px; border-left: 4px solid #2196F3; }
                .traitement { background-color: #f0f9f0; padding: 10px; border-left: 4px solid #4CAF50; }
                .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 11px; color: #666; }
                .signature { margin-top: 40px; }
                .signature-line { border-top: 1px solid #333; width: 200px; margin: 20px 0 5px; }
                @media print {
                    body { padding: 0; }
                    .no-print { display: none; }
                    .page-break { page-break-after: always; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>COMPTE-RENDU DE CONSULTATION</h1>
                <p><strong>Référence:</strong> ${"<?php echo $consultation['reference']; ?>"}</p>
                <p>Date: ${"<?php echo date('d/m/Y H:i', strtotime($consultation['date_consultation'])); ?>"}</p>
            </div>
            
            <div class="patient-info">
                <div class="patient-left">
                    <p><strong>Patient:</strong> ${"<?php echo $consultation['patient_prenom'] . ' ' . $consultation['patient_nom']; ?>"}</p>
                    <p><strong>Code patient:</strong> ${"<?php echo $consultation['code_patient']; ?>"}</p>
                    <p><strong>Âge:</strong> ${"<?php echo $age; ?>"} ans | <strong>Sexe:</strong> ${"<?php echo $consultation['sexe'] == 'M' ? 'Masculin' : 'Féminin'; ?>"}</p>
                    ${"<?php if ($consultation['telephone']): ?>"}
                    <p><strong>Téléphone:</strong> ${"<?php echo $consultation['telephone']; ?>"}</p>
                    ${"<?php endif; ?>"}
                </div>
                <div class="patient-right">
                    <p><strong>Médecin:</strong> Dr. ${"<?php echo $consultation['docteur_prenom'] . ' ' . $consultation['docteur_nom']; ?>"}</p>
                    <p><strong>Spécialité:</strong> ${"<?php echo $consultation['docteur_specialite'] ?? 'Non spécifiée'; ?>"}</p>
                    <p><strong>Durée:</strong> ${"<?php echo $consultation['duree'] ?? 30; ?>"} minutes</p>
                    <p><strong>Type:</strong> ${"<?php 
                        $typeLabels = [
                            'premiere' => 'Première consultation',
                            'suivi' => 'Consultation de suivi', 
                            'urgence' => 'Consultation urgente',
                            'controle' => 'Consultation de contrôle'
                        ];
                        echo $typeLabels[$consultation['type_consultation']] ?? $consultation['type_consultation'];
                    ?>"}</p>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">MOTIF DE CONSULTATION</div>
                ${"<?php echo nl2br(htmlspecialchars($consultation['motif'] ?? 'Non renseigné')); ?>"}
            </div>
            
            ${"<?php if ($consultation['histoire_maladie']): ?>"}
            <div class="section">
                <div class="section-title">HISTOIRE DE LA MALADIE</div>
                ${"<?php echo nl2br(htmlspecialchars($consultation['histoire_maladie'])); ?>"}
            </div>
            ${"<?php endif; ?>"}
            
            ${"<?php if ($consultation['examen_clinique']): ?>"}
            <div class="section">
                <div class="section-title">EXAMEN CLINIQUE</div>
                ${"<?php echo nl2br(htmlspecialchars($consultation['examen_clinique'])); ?>"}
            </div>
            ${"<?php endif; ?>"}
            
            ${"<?php if ($consultation['diagnostic']): ?>"}
            <div class="section">
                <div class="section-title">DIAGNOSTIC</div>
                <div class="diagnostic">
                    ${"<?php echo nl2br(htmlspecialchars($consultation['diagnostic'])); ?>"}
                </div>
            </div>
            ${"<?php endif; ?>"}
            
            ${"<?php if ($consultation['traitement']): ?>"}
            <div class="section">
                <div class="section-title">TRAITEMENT PRESCRIT</div>
                <div class="traitement">
                    ${"<?php echo nl2br(htmlspecialchars($consultation['traitement'])); ?>"}
                </div>
            </div>
            ${"<?php endif; ?>"}
            
            ${"<?php if ($prescription): ?>"}
            <div class="page-break"></div>
            <div class="section">
                <div class="section-title">ORDONNANCE N° ${"<?php echo $prescription['reference']; ?>"}</div>
                <p><strong>Date:</strong> ${"<?php echo date('d/m/Y', strtotime($prescription['date_prescription'])); ?>"}</p>
                <p><strong>Durée du traitement:</strong> ${"<?php echo $prescription['duree_traitement'] ?? 'Non spécifiée'; ?>"}</p>
                
                ${"<?php 
                    if ($prescription['medicaments']):
                        $medicaments = json_decode($prescription['medicaments'], true);
                        if (is_array($medicaments) && !empty($medicaments)):
                ?>"}
                <table>
                    <thead>
                        <tr>
                            <th>Médicament</th>
                            <th>Dosage</th>
                            <th>Posologie</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${"<?php foreach ($medicaments as $med): ?>"}
                        <tr>
                            <td>${"<?php echo htmlspecialchars($med['nom'] ?? ''); ?>"}</td>
                            <td>${"<?php echo htmlspecialchars($med['dosage'] ?? ''); ?>"}</td>
                            <td>${"<?php echo htmlspecialchars($med['posologie'] ?? ''); ?>"}</td>
                        </tr>
                        ${"<?php endforeach; ?>"}
                    </tbody>
                </table>
                ${"<?php endif; endif; ?>"}
                
                ${"<?php if ($prescription['posologie']): ?>"}
                <div style="margin-top: 15px;">
                    <strong>Posologie:</strong><br>
                    ${"<?php echo nl2br(htmlspecialchars($prescription['posologie'])); ?>"}
                </div>
                ${"<?php endif; ?>"}
            </div>
            ${"<?php endif; ?>"}
            
            ${"<?php if ($consultation['recommandations']): ?>"}
            <div class="section">
                <div class="section-title">RECOMMANDATIONS</div>
                ${"<?php echo nl2br(htmlspecialchars($consultation['recommandations'])); ?>"}
            </div>
            ${"<?php endif; ?>"}
            
            ${"<?php if ($consultation['examen_complementaire']): ?>"}
            <div class="section">
                <div class="section-title">EXAMENS COMPLÉMENTAIRES DEMANDÉS</div>
                ${"<?php echo nl2br(htmlspecialchars($consultation['examen_complementaire'])); ?>"}
            </div>
            ${"<?php endif; ?>"}
            
            <div class="signature">
                <p>Fait à ${"<?php echo $consultation['ville'] ?? 'Non spécifié'; ?>"}, le ${"<?php echo date('d/m/Y'); ?>"}</p>
                <div class="signature-line"></div>
                <p>Signature et cachet du médecin</p>
            </div>
            
            <div class="footer">
                <p>Document généré le ${new Date().toLocaleDateString('fr-FR')} à ${new Date().toLocaleTimeString('fr-FR')}</p>
                <p><strong>${"<?php echo $consultation['reference']; ?>"}</strong> - Confidentiel médical</p>
            </div>
            
            <div class="no-print" style="margin-top: 30px; text-align: center;">
                <button onclick="window.print()" class="btn btn-primary">Imprimer</button>
                <button onclick="window.close()" class="btn btn-secondary">Fermer</button>
            </div>
            
            <script>
                window.onload = function() {
                    window.print();
                };
            <\/script>
        </body>
        </html>
    `;
    
    printWindow.document.write(content);
    printWindow.document.close();
}

// Initialiser les tooltips et onglets
document.addEventListener('DOMContentLoaded', function() {
    // Tooltips
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
    
    // Onglets
    const triggerTabList = [].slice.call(document.querySelectorAll('#consultationTabs button'));
    triggerTabList.forEach(function (triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
        });
    });
});
</script>

<style>
.avatar-details {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    margin: 0 auto;
}

.nav-tabs .nav-link {
    color: #6b7280;
    font-weight: 500;
    border: none;
    padding: 0.75rem 1.5rem;
}

.nav-tabs .nav-link.active {
    color: #4361ee;
    border-bottom: 2px solid #4361ee;
    background-color: transparent;
}

.tab-content {
    padding-top: 1rem;
}

.card-header {
    border-bottom: 1px solid rgba(0,0,0,.125);
}

.badge {
    font-size: 0.75em;
    font-weight: 500;
}

.table th {
    font-weight: 600;
    color: #6b7280;
    background-color: #f9fafb;
    border-bottom: 2px solid #e5e7eb;
    padding: 0.75rem;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

.table td {
    padding: 0.75rem;
    vertical-align: middle;
    border-bottom: 1px solid #e5e7eb;
}

.alert {
    border-radius: 0.5rem;
    border: none;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>