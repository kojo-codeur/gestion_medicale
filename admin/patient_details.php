<?php
// admin/patient_details.php
require_once '../config/database.php';
checkRole('admin');

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: patients.php?error=ID patient manquant");
    exit();
}
$pdo = Database::getInstance()->getConnection();

// Récupérer les informations du patient
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    header("Location: patients.php?error=Patient non trouvé");
    exit();
}

// Calculer l'âge
$age = calculateAge($patient['date_naissance']);

// Récupérer les statistiques du patient
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT c.id) as total_consultations,
        MIN(c.date_consultation) as premiere_consultation,
        MAX(c.date_consultation) as derniere_consultation,
        COUNT(DISTINCT r.id) as total_rdv,
        COUNT(DISTINCT p.id) as total_prescriptions,
        COUNT(DISTINCT dm.id) as total_documents
    FROM patients pat
    LEFT JOIN consultations c ON pat.id = c.patient_id
    LEFT JOIN rendez_vous r ON pat.id = r.patient_id
    LEFT JOIN prescriptions p ON pat.id = p.patient_id
    LEFT JOIN documents_medicaux dm ON pat.id = dm.patient_id
    WHERE pat.id = ?
");
$statsStmt->execute([$id]);
$stats = $statsStmt->fetch();

// Récupérer les consultations récentes
$consultationsStmt = $pdo->prepare("
    SELECT c.*, 
           u.prenom as docteur_prenom, 
           u.nom as docteur_nom,
           u.specialite
    FROM consultations c
    LEFT JOIN utilisateurs u ON c.docteur_id = u.id
    WHERE c.patient_id = ?
    ORDER BY c.date_consultation DESC
    LIMIT 5
");
$consultationsStmt->execute([$id]);
$consultations = $consultationsStmt->fetchAll();

// Récupérer les rendez-vous à venir
$rdvStmt = $pdo->prepare("
    SELECT r.*, 
           u.prenom as docteur_prenom, 
           u.nom as docteur_nom,
           u.specialite
    FROM rendez_vous r
    LEFT JOIN utilisateurs u ON r.docteur_id = u.id
    WHERE r.patient_id = ? 
    AND r.date_rdv >= CURDATE()
    AND r.statut = 'confirme'
    ORDER BY r.date_rdv ASC
");
$rdvStmt->execute([$id]);
$rendezvous = $rdvStmt->fetchAll();

// Récupérer les documents médicaux
$documentsStmt = $pdo->prepare("
    SELECT dm.*,
           u.prenom as docteur_prenom,
           u.nom as docteur_nom
    FROM documents_medicaux dm
    LEFT JOIN utilisateurs u ON dm.docteur_id = u.id
    WHERE dm.patient_id = ?
    ORDER BY dm.created_at DESC
");
$documentsStmt->execute([$id]);
$documents = $documentsStmt->fetchAll();

// Récupérer les prescriptions
$prescriptionsStmt = $pdo->prepare("
    SELECT p.*,
           u.prenom as docteur_prenom,
           u.nom as docteur_nom
    FROM prescriptions p
    LEFT JOIN utilisateurs u ON p.docteur_id = u.id
    WHERE p.patient_id = ?
    AND p.statut = 'active'
    ORDER BY p.date_prescription DESC
");
$prescriptionsStmt->execute([$id]);
$prescriptions = $prescriptionsStmt->fetchAll();

// Récupérer les pathologies
$pathologiesStmt = $pdo->prepare("
    SELECT pp.*,
           pat.nom as pathologie_nom,
           pat.code_cim,
           pat.gravite
    FROM patient_pathologie pp
    LEFT JOIN pathologies pat ON pp.pathologie_id = pat.id
    WHERE pp.patient_id = ?
    ORDER BY pp.date_diagnostic DESC
");
$pathologiesStmt->execute([$id]);
$pathologies = $pathologiesStmt->fetchAll();

$title = 'Détails Patient - ' . $patient['prenom'] . ' ' . $patient['nom'];
require_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-user-injured me-2"></i>Détails du Patient
        </h1>
        <p class="text-muted mb-0">Fiche complète et historique médical</p>
    </div>
    <div class="btn-toolbar">
        <div class="btn-group me-2">
            <a href="patients.php?action=edit&id=<?php echo $id; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i>Modifier
            </a>
            <a href="../docteur/consultations.php?action=add&patient_id=<?php echo $id; ?>" 
               class="btn btn-success">
                <i class="fas fa-stethoscope me-1"></i>Nouvelle consultation
            </a>
            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" 
                    data-bs-toggle="dropdown">
                <span class="visually-hidden">Options</span>
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="rendezvous.php?action=add&patient_id=<?php echo $id; ?>">
                    <i class="fas fa-calendar-plus me-2"></i>Nouveau rendez-vous
                </a>
                <a class="dropdown-item" href="#" onclick="printPatientCard()">
                    <i class="fas fa-print me-2"></i>Imprimer fiche
                </a>
                <div class="dropdown-divider"></div>
                <?php if ($patient['statut'] == 'actif'): ?>
                <a class="dropdown-item text-warning" href="#" 
                   onclick="confirmArchive(<?php echo $id; ?>)">
                    <i class="fas fa-archive me-2"></i>Archiver le patient
                </a>
                <?php else: ?>
                <a class="dropdown-item text-success" href="#" 
                   onclick="confirmActivate(<?php echo $id; ?>)">
                    <i class="fas fa-check-circle me-2"></i>Réactiver le patient
                </a>
                <?php endif; ?>
                <a class="dropdown-item text-danger" href="#" 
                   onclick="confirmDelete(<?php echo $id; ?>)">
                    <i class="fas fa-trash me-2"></i>Supprimer définitivement
                </a>
            </div>
        </div>
        <a href="patients.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Retour à la liste
        </a>
    </div>
</div>

<div class="row">
    <!-- Colonne gauche - Informations patient -->
    <div class="col-lg-4">
        <!-- Carte d'identité patient -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-id-card me-2"></i>Carte d'identité
                </h5>
            </div>
            <div class="card-body text-center">
                <!-- Avatar patient -->
                <div class="patient-avatar mb-3">
                    <div class="avatar-lg mx-auto">
                        <?php echo strtoupper(substr($patient['prenom'], 0, 1) . substr($patient['nom'], 0, 1)); ?>
                    </div>
                </div>
                
                <!-- Nom et code patient -->
                <h4 class="mb-1"><?php echo $patient['prenom'] . ' ' . $patient['nom']; ?></h4>
                <p class="text-muted mb-2">
                    Code: <span class="badge bg-info"><?php echo $patient['code_patient']; ?></span>
                </p>
                
                <!-- Informations démographiques -->
                <div class="row text-start small">
                    <div class="col-6">
                        <p class="mb-1"><strong>Âge:</strong> <?php echo $age; ?> ans</p>
                        <p class="mb-1"><strong>Sexe:</strong> 
                            <?php echo $patient['sexe'] == 'M' ? 'Masculin' : 'Féminin'; ?>
                        </p>
                        <p class="mb-1"><strong>Naissance:</strong> 
                            <?php echo date('d/m/Y', strtotime($patient['date_naissance'])); ?>
                        </p>
                        <?php if ($patient['lieu_naissance']): ?>
                        <p class="mb-1"><strong>Lieu:</strong> <?php echo $patient['lieu_naissance']; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-6">
                        <?php if ($patient['groupe_sanguin']): ?>
                        <p class="mb-1">
                            <strong>Groupe sanguin:</strong> 
                            <span class="badge bg-danger"><?php echo $patient['groupe_sanguin']; ?><?php echo $patient['rhésus']; ?></span>
                        </p>
                        <?php endif; ?>
                        
                        <?php if ($patient['poids'] && $patient['taille']): 
                            $bmi = $patient['imc'] ?? ($patient['poids'] / pow($patient['taille'] / 100, 2));
                            $bmiClass = $bmi < 18.5 ? 'info' : ($bmi < 25 ? 'success' : ($bmi < 30 ? 'warning' : 'danger'));
                        ?>
                        <p class="mb-1">
                            <strong>IMC:</strong> 
                            <span class="badge bg-<?php echo $bmiClass; ?>">
                                <?php echo number_format($bmi, 1); ?>
                            </span>
                        </p>
                        <p class="mb-1"><strong>Taille:</strong> <?php echo $patient['taille']; ?> cm</p>
                        <p class="mb-1"><strong>Poids:</strong> <?php echo $patient['poids']; ?> kg</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Profession et situation familiale -->
                <div class="mt-3 pt-3 border-top">
                    <div class="row small">
                        <?php if ($patient['profession']): ?>
                        <div class="col-12 mb-1">
                            <i class="fas fa-briefcase text-primary me-1"></i>
                            <?php echo $patient['profession']; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-6">
                            <i class="fas fa-users text-primary me-1"></i>
                            <?php 
                                $situations = [
                                    'celibataire' => 'Célibataire',
                                    'marie' => 'Marié(e)',
                                    'divorce' => 'Divorcé(e)',
                                    'veuf' => 'Veuf/Veuve'
                                ];
                                echo $situations[$patient['situation_familiale']] ?? 'Non spécifié';
                            ?>
                        </div>
                        <?php if ($patient['nombre_enfants'] > 0): ?>
                        <div class="col-6">
                            <i class="fas fa-child text-primary me-1"></i>
                            <?php echo $patient['nombre_enfants']; ?> enfant(s)
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Coordonnées -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-address-card me-2"></i>Coordonnées</h6>
            </div>
            <div class="card-body">
                <div class="row small">
                    <div class="col-12 mb-2">
                        <i class="fas fa-phone text-primary me-2"></i>
                        <a href="tel:<?php echo $patient['telephone']; ?>">
                            <?php echo $patient['telephone']; ?>
                        </a>
                    </div>
                    
                    <?php if ($patient['telephone_urgence']): ?>
                    <div class="col-12 mb-2">
                        <i class="fas fa-phone-alt text-danger me-2"></i>
                        <a href="tel:<?php echo $patient['telephone_urgence']; ?>">
                            <?php echo $patient['telephone_urgence']; ?>
                        </a>
                        <small class="text-muted d-block">Contact d'urgence</small>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($patient['email']): ?>
                    <div class="col-12 mb-2">
                        <i class="fas fa-envelope text-primary me-2"></i>
                        <a href="mailto:<?php echo $patient['email']; ?>">
                            <?php echo $patient['email']; ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <hr class="my-2">
                
                <!-- Adresse -->
                <?php if ($patient['adresse'] || $patient['ville']): ?>
                <div class="mt-2">
                    <strong>Adresse:</strong>
                    <address class="mb-0 mt-1 small">
                        <?php if ($patient['adresse']): ?>
                        <p class="mb-1"><?php echo $patient['adresse']; ?></p>
                        <?php endif; ?>
                        
                        <?php if ($patient['code_postal'] || $patient['ville']): ?>
                        <p class="mb-1">
                            <?php echo $patient['code_postal']; ?> <?php echo $patient['ville']; ?>
                        </p>
                        <?php endif; ?>
                        
                        <?php if ($patient['pays'] && $patient['pays'] != 'France'): ?>
                        <p class="mb-0"><?php echo $patient['pays']; ?></p>
                        <?php endif; ?>
                    </address>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistiques rapides -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistiques</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4 mb-3">
                        <div class="display-6 fw-bold text-primary"><?php echo $stats['total_consultations'] ?? 0; ?></div>
                        <small class="text-muted">Consultations</small>
                    </div>
                    <div class="col-4 mb-3">
                        <div class="display-6 fw-bold text-success"><?php echo $stats['total_rdv'] ?? 0; ?></div>
                        <small class="text-muted">Rendez-vous</small>
                    </div>
                    <div class="col-4 mb-3">
                        <div class="display-6 fw-bold text-warning"><?php echo $stats['total_prescriptions'] ?? 0; ?></div>
                        <small class="text-muted">Ordonnances</small>
                    </div>
                </div>
                
                <?php if ($stats['premiere_consultation']): ?>
                <div class="small mt-2">
                    <p class="mb-1">
                        <strong>Première consultation:</strong><br>
                        <?php echo date('d/m/Y', strtotime($stats['premiere_consultation'])); ?>
                    </p>
                    
                    <?php if ($stats['derniere_consultation']): ?>
                    <p class="mb-0">
                        <strong>Dernière consultation:</strong><br>
                        <?php echo date('d/m/Y', strtotime($stats['derniere_consultation'])); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statut patient -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations système</h6>
            </div>
            <div class="card-body">
                <?php
                $statusColor = $patient['statut'] == 'actif' ? 'success' : 
                             ($patient['statut'] == 'archive' ? 'secondary' : 'danger');
                ?>
                <span class="badge bg-<?php echo $statusColor; ?> mb-3">
                    <?php echo ucfirst($patient['statut']); ?>
                </span>
                
                <div class="small">
                    <p class="mb-1">
                        <strong>Enregistré le:</strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($patient['date_enregistrement'])); ?>
                    </p>
                    
                    <?php if ($patient['date_modification']): ?>
                    <p class="mb-1">
                        <strong>Dernière modification:</strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($patient['date_modification'])); ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($patient['created_by']): 
                        $creatorStmt = $pdo->prepare("SELECT prenom, nom FROM utilisateurs WHERE id = ?");
                        $creatorStmt->execute([$patient['created_by']]);
                        $creator = $creatorStmt->fetch();
                    ?>
                    <p class="mb-0">
                        <strong>Créé par:</strong><br>
                        <?php echo $creator ? $creator['prenom'] . ' ' . $creator['nom'] : 'Inconnu'; ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Colonne droite - Historique médical -->
    <div class="col-lg-8">
        <!-- Onglets -->
        <ul class="nav nav-tabs mb-4" id="medicalTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="consult-tab" data-bs-toggle="tab" 
                        data-bs-target="#consult" type="button">
                    <i class="fas fa-stethoscope me-1"></i>Consultations
                    <?php if ($consultations): ?>
                    <span class="badge bg-primary ms-1"><?php echo count($consultations); ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="rdv-tab" data-bs-toggle="tab" 
                        data-bs-target="#rdv" type="button">
                    <i class="fas fa-calendar-alt me-1"></i>Rendez-vous
                    <?php if ($rendezvous): ?>
                    <span class="badge bg-success ms-1"><?php echo count($rendezvous); ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="prescriptions-tab" data-bs-toggle="tab" 
                        data-bs-target="#prescriptions" type="button">
                    <i class="fas fa-pills me-1"></i>Prescriptions
                    <?php if ($prescriptions): ?>
                    <span class="badge bg-warning ms-1"><?php echo count($prescriptions); ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" 
                        data-bs-target="#history" type="button">
                    <i class="fas fa-history me-1"></i>Antécédents
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
        
        <div class="tab-content" id="medicalTabsContent">
            <!-- Onglet Consultations -->
            <div class="tab-pane fade show active" id="consult" role="tabpanel">
                <?php if ($consultations): ?>
                <div class="list-group">
                    <?php foreach ($consultations as $consult): ?>
                    <div class="list-group-item list-group-item-action mb-2">
                        <div class="d-flex w-100 justify-content-between">
                            <div class="w-100">
                                <div class="d-flex justify-content-between mb-2">
                                    <h6 class="mb-0">
                                        <?php echo date('d/m/Y H:i', strtotime($consult['date_consultation'])); ?>
                                        <?php if ($consult['type_consultation']): ?>
                                        <span class="badge bg-secondary ms-2">
                                            <?php 
                                                $types = [
                                                    'premiere' => 'Première',
                                                    'suivi' => 'Suivi',
                                                    'urgence' => 'Urgence',
                                                    'controle' => 'Contrôle'
                                                ];
                                                echo $types[$consult['type_consultation']] ?? $consult['type_consultation'];
                                            ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($consult['urgence']): ?>
                                        <span class="badge bg-danger ms-1">Urgent</span>
                                        <?php endif; ?>
                                    </h6>
                                    <div>
                                        <?php if ($consult['docteur_prenom']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-user-md me-1"></i>
                                            Dr. <?php echo $consult['docteur_prenom'] . ' ' . $consult['docteur_nom']; ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($consult['motif']): ?>
                                <p class="mb-1"><strong>Motif:</strong> <?php echo $consult['motif']; ?></p>
                                <?php endif; ?>
                                
                                <?php if ($consult['diagnostic']): ?>
                                <p class="mb-1"><strong>Diagnostic:</strong> <?php echo $consult['diagnostic']; ?></p>
                                <?php endif; ?>
                                
                                <?php if ($consult['traitement']): ?>
                                <div class="mb-2">
                                    <strong>Traitement:</strong>
                                    <p class="mb-0 small"><?php echo nl2br($consult['traitement']); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($consult['notes']): ?>
                                <div class="mb-2">
                                    <strong>Notes:</strong>
                                    <p class="mb-0 small text-muted"><?php echo nl2br($consult['notes']); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <!-- <div class="mt-2 pt-2 border-top">
                                    <div class="btn-group btn-group-sm">
                                        <a href="../docteur/consultation.php?id=<?php echo $consult['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>Voir détails
                                        </a>
                                        <a href="../docteur/consultations.php?action=edit&id=<?php echo $consult['id']; ?>" 
                                           class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-edit me-1"></i>Modifier
                                        </a>
                                    </div>
                                </div> -->
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($stats['total_consultations'] > 5): ?>
                <div class="text-center mt-3">
                    <a href="../docteur/consultations.php?patient_id=<?php echo $id; ?>" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-list me-1"></i>Voir toutes les consultations (<?php echo $stats['total_consultations']; ?>)
                    </a>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-stethoscope fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Aucune consultation enregistrée</p>
                    <a href="../docteur/consultations.php?action=add&patient_id=<?php echo $id; ?>" 
                       class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Créer première consultation
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Onglet Rendez-vous -->
            <div class="tab-pane fade" id="rdv" role="tabpanel">
                <?php if ($rendezvous): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Heure</th>
                                <th>Type</th>
                                <th>Médecin</th>
                                <th>Motif</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rendezvous as $rdv): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($rdv['date_rdv'])); ?></td>
                                <td><?php echo date('H:i', strtotime($rdv['date_rdv'])); ?></td>
                                <td>
                                    <?php 
                                        $typesRdv = [
                                            'consultation' => 'Consultation',
                                            'controle' => 'Contrôle',
                                            'urgence' => 'Urgence',
                                            'autre' => 'Autre'
                                        ];
                                        echo $typesRdv[$rdv['type_rdv']] ?? $rdv['type_rdv'];
                                    ?>
                                </td>
                                <td>
                                    <?php if ($rdv['docteur_prenom']): ?>
                                    Dr. <?php echo $rdv['docteur_prenom'] . ' ' . $rdv['docteur_nom']; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $rdv['motif'] ? substr($rdv['motif'], 0, 50) . '...' : '-'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $rdv['statut'] == 'confirme' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($rdv['statut']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="rendezvous.php?action=edit&id=<?php echo $rdv['id']; ?>" 
                                           class="btn btn-outline-primary" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="rendezvous.php?action=annuler&id=<?php echo $rdv['id']; ?>" 
                                           class="btn btn-outline-danger" title="Annuler"
                                           onclick="return confirm('Annuler ce rendez-vous ?')">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-3">
                    <a href="rendezvous.php?action=add&patient_id=<?php echo $id; ?>" 
                       class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Nouveau rendez-vous
                    </a>
                    <a href="rendezvous.php?patient_id=<?php echo $id; ?>" 
                       class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-list me-1"></i>Voir tous les rendez-vous
                    </a>
                </div>
                
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Aucun rendez-vous à venir</p>
                    <a href="rendezvous.php?action=add&patient_id=<?php echo $id; ?>" 
                       class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Planifier un rendez-vous
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Onglet Prescriptions -->
            <div class="tab-pane fade" id="prescriptions" role="tabpanel">
                <?php if ($prescriptions): ?>
                <div class="list-group">
                    <?php foreach ($prescriptions as $pres): 
                        $medicaments = json_decode($pres['medicaments'], true);
                    ?>
                    <div class="list-group-item list-group-item-action mb-3">
                        <div class="d-flex w-100 justify-content-between mb-2">
                            <div>
                                <h6 class="mb-0">
                                    Ordonnance <?php echo $pres['reference']; ?>
                                    <span class="badge bg-success ms-2">Active</span>
                                </h6>
                                <small class="text-muted">
                                    Prescrite le <?php echo date('d/m/Y', strtotime($pres['date_prescription'])); ?>
                                    <?php if ($pres['docteur_prenom']): ?>
                                    par Dr. <?php echo $pres['docteur_prenom'] . ' ' . $pres['docteur_nom']; ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div>
                                <?php if ($pres['renouvelable']): ?>
                                <span class="badge bg-info">
                                    Renouvelable (<?php echo $pres['nombre_renouvellements']; ?> fois)
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($medicaments && is_array($medicaments)): ?>
                        <div class="mb-3">
                            <strong>Médicaments:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($medicaments as $med): ?>
                                <li class="mb-1">
                                    <strong><?php echo htmlspecialchars($med['nom'] ?? ''); ?></strong>
                                    <?php if ($med['dosage']): ?> - <?php echo htmlspecialchars($med['dosage']); ?><?php endif; ?>
                                    <?php if ($med['posologie']): ?> (<?php echo htmlspecialchars($med['posologie']); ?>)<?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($pres['posologie']): ?>
                        <div class="mb-2">
                            <strong>Posologie:</strong>
                            <p class="mb-0 small"><?php echo nl2br($pres['posologie']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($pres['duree_traitement']): ?>
                        <div class="mb-2">
                            <strong>Durée:</strong> <?php echo $pres['duree_traitement']; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($pres['notes']): ?>
                        <div class="mt-2 pt-2 border-top">
                            <strong>Notes:</strong>
                            <p class="mb-0 small text-muted"><?php echo nl2br($pres['notes']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- <div class="mt-3 pt-2 border-top">
                            <div class="btn-group btn-group-sm">
                                <a href="../docteur/prescription_details.php?id=<?php echo $pres['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>Voir détails
                                </a>
                                <a href="../docteur/prescriptions.php?action=print&id=<?php echo $pres['id']; ?>" 
                                   class="btn btn-outline-secondary btn-sm" target="_blank">
                                    <i class="fas fa-print me-1"></i>Imprimer
                                </a>
                            </div>
                        </div> -->
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- <div class="text-center mt-3">
                    <a href="../docteur/prescriptions.php?patient_id=<?php echo $id; ?>" 
                       class="btn btn-outline-primary">
                        <i class="fas fa-list me-1"></i>Voir toutes les prescriptions
                    </a>
                </div> -->
                
                <?php else: ?>
                <!-- <div class="text-center py-5">
                    <i class="fas fa-pills fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Aucune prescription active</p>
                    <a href="../docteur/consultations.php?action=add&patient_id=<?php echo $id; ?>" 
                       class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Créer une consultation
                    </a>
                </div> -->
                <?php endif; ?>
            </div>
            
            <!-- Onglet Antécédents -->
            <div class="tab-pane fade" id="history" role="tabpanel">
                <div class="row g-3">
                    <!-- Antécédents familiaux -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-users me-2"></i>Antécédents familiaux</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($patient['antecedents_familiaux']): ?>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($patient['antecedents_familiaux'])); ?></p>
                                <?php else: ?>
                                <p class="text-muted mb-0">Non renseigné</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Antécédents personnels -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-user me-2"></i>Antécédents personnels</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($patient['antecedents_personnels']): ?>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($patient['antecedents_personnels'])); ?></p>
                                <?php else: ?>
                                <p class="text-muted mb-0">Non renseigné</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Allergies -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-allergies me-2"></i>Allergies</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($patient['allergies']): ?>
                                <p class="mb-0 text-danger">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <?php echo nl2br(htmlspecialchars($patient['allergies'])); ?>
                                </p>
                                <?php else: ?>
                                <p class="text-muted mb-0">Aucune allergie déclarée</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Médicaments habituels -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-pills me-2"></i>Médicaments habituels</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($patient['medicaments_habituels']): ?>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($patient['medicaments_habituels'])); ?></p>
                                <?php else: ?>
                                <p class="text-muted mb-0">Aucun traitement habituel</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Habitudes de vie -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-running me-2"></i>Habitudes de vie</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($patient['habitudes']): ?>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($patient['habitudes'])); ?></p>
                                <?php else: ?>
                                <p class="text-muted mb-0">Non renseigné</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pathologies diagnostiquées -->
                    <?php if ($pathologies): ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Pathologies diagnostiquées</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Pathologie</th>
                                                <th>Code CIM</th>
                                                <th>Date diagnostic</th>
                                                <th>Gravité</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pathologies as $patho): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($patho['pathologie_nom']); ?></td>
                                                <td><code><?php echo htmlspecialchars($patho['code_cim']); ?></code></td>
                                                <td><?php echo date('d/m/Y', strtotime($patho['date_diagnostic'])); ?></td>
                                                <td>
                                                    <?php 
                                                        $graviteColors = [
                                                            'legere' => 'success',
                                                            'moderee' => 'warning', 
                                                            'grave' => 'danger'
                                                        ];
                                                        $color = $graviteColors[$patho['gravite']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst($patho['gravite']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $statutColors = [
                                                            'active' => 'info',
                                                            'guerie' => 'success',
                                                            'chronique' => 'warning',
                                                            'en_suivi' => 'primary'
                                                        ];
                                                        $color = $statutColors[$patho['statut']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst($patho['statut']); ?>
                                                    </span>
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
                    
                    <!-- Notes médicales -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-notes-medical me-2"></i>Notes médicales</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($patient['notes']): ?>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($patient['notes'])); ?></p>
                                <?php else: ?>
                                <p class="text-muted mb-0">Aucune note</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Documents -->
            <div class="tab-pane fade" id="documents" role="tabpanel">
                <?php if ($documents): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Titre</th>
                                <th>Description</th>
                                <th>Créé par</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
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
                            ?>
                            <tr>
                                <td>
                                    <i class="<?php echo $icon; ?> me-1"></i>
                                    <?php 
                                        $typeLabels = [
                                            'ordonnance' => 'Ordonnance',
                                            'certificat' => 'Certificat',
                                            'resultat_analyse' => 'Analyse',
                                            'compte_rendu' => 'Compte-rendu',
                                            'imagerie' => 'Imagerie',
                                            'autre' => 'Autre'
                                        ];
                                        echo $typeLabels[$doc['type']] ?? $doc['type'];
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($doc['titre']); ?></td>
                                <td><?php echo $doc['description'] ? substr(htmlspecialchars($doc['description']), 0, 50) . '...' : '-'; ?></td>
                                <td>
                                    <?php if ($doc['docteur_prenom']): ?>
                                    Dr. <?php echo $doc['docteur_prenom'] . ' ' . $doc['docteur_nom']; ?>
                                    <?php else: ?>
                                    Non spécifié
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($doc['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($doc['fichier_path'] && file_exists($doc['fichier_path'])): ?>
                                        <a href="<?php echo $doc['fichier_path']; ?>" 
                                           class="btn btn-outline-primary" target="_blank" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo $doc['fichier_path']; ?>" 
                                           class="btn btn-outline-secondary" download title="Télécharger">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <?php else: ?>
                                        <button class="btn btn-outline-secondary" disabled title="Fichier non disponible">
                                            <i class="fas fa-file"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- <div class="text-center mt-3">
                    <a href="../docteur/documents.php?action=add&patient_id=<?php echo $id; ?>" 
                       class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i>Ajouter document
                    </a>
                </div> -->
                
                <?php else: ?>
                <!-- <div class="text-center py-5">
                    <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Aucun document médical</p>
                    <a href="../docteur/documents.php?action=add&patient_id=<?php echo $id; ?>" 
                       class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i>Ajouter document
                    </a>
                </div> -->
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// Fonction d'impression
function printPatientCard() {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Fiche Patient - <?php echo $patient['prenom'] . ' ' . $patient['nom']; ?></title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
                .patient-info { display: flex; margin-bottom: 20px; }
                .patient-details { flex: 1; }
                .patient-stats { flex: 1; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f5f5f5; }
                .section { margin: 25px 0; }
                .section-title { background-color: #f0f0f0; padding: 8px; font-weight: bold; }
                .warning { color: #dc3545; font-weight: bold; }
                @media print {
                    .no-print { display: none; }
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Fiche Patient</h1>
                <h2><?php echo $patient['prenom'] . ' ' . $patient['nom']; ?></h2>
                <p>Code: <?php echo $patient['code_patient']; ?> | 
                   Né(e) le: <?php echo date('d/m/Y', strtotime($patient['date_naissance'])); ?> | 
                   Âge: <?php echo $age; ?> ans | 
                   Sexe: <?php echo $patient['sexe'] == 'M' ? 'Masculin' : 'Féminin'; ?></p>
            </div>
            
            <div class="patient-info">
                <div class="patient-details">
                    <h3>Informations personnelles</h3>
                    <p><strong>Téléphone:</strong> <?php echo $patient['telephone']; ?></p>
                    <?php if ($patient['telephone_urgence']): ?>
                    <p><strong>Contact urgence:</strong> <?php echo $patient['telephone_urgence']; ?></p>
                    <?php endif; ?>
                    <?php if ($patient['email']): ?>
                    <p><strong>Email:</strong> <?php echo $patient['email']; ?></p>
                    <?php endif; ?>
                    <?php if ($patient['profession']): ?>
                    <p><strong>Profession:</strong> <?php echo $patient['profession']; ?></p>
                    <?php endif; ?>
                    <p><strong>Situation familiale:</strong> 
                        <?php 
                            $situations = [
                                'celibataire' => 'Célibataire',
                                'marie' => 'Marié(e)',
                                'divorce' => 'Divorcé(e)',
                                'veuf' => 'Veuf/Veuve'
                            ];
                            echo $situations[$patient['situation_familiale']] ?? 'Non spécifié';
                        ?>
                    </p>
                    <?php if ($patient['nombre_enfants'] > 0): ?>
                    <p><strong>Enfants:</strong> <?php echo $patient['nombre_enfants']; ?></p>
                    <?php endif; ?>
                </div>
                <div class="patient-stats">
                    <h3>Statistiques médicales</h3>
                    <p><strong>Consultations:</strong> <?php echo $stats['total_consultations'] ?? 0; ?></p>
                    <p><strong>Rendez-vous:</strong> <?php echo $stats['total_rdv'] ?? 0; ?></p>
                    <p><strong>Prescriptions:</strong> <?php echo $stats['total_prescriptions'] ?? 0; ?></p>
                    <?php if ($stats['premiere_consultation']): ?>
                    <p><strong>Première consultation:</strong> <?php echo date('d/m/Y', strtotime($stats['premiere_consultation'])); ?></p>
                    <p><strong>Dernière consultation:</strong> <?php echo date('d/m/Y', strtotime($stats['derniere_consultation'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($patient['adresse'] || $patient['ville']): ?>
            <div class="section">
                <div class="section-title">Adresse</div>
                <p>
                    <?php echo $patient['adresse']; ?><br>
                    <?php echo $patient['code_postal']; ?> <?php echo $patient['ville']; ?><br>
                    <?php echo $patient['pays']; ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if ($patient['allergies']): ?>
            <div class="section">
                <div class="section-title">⚠️ Allergies connues</div>
                <p class="warning"><?php echo nl2br(htmlspecialchars($patient['allergies'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($patient['medicaments_habituels']): ?>
            <div class="section">
                <div class="section-title">Médicaments habituels</div>
                <p><?php echo nl2br(htmlspecialchars($patient['medicaments_habituels'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($patient['antecedents_personnels']): ?>
            <div class="section">
                <div class="section-title">Antécédents personnels</div>
                <p><?php echo nl2br(htmlspecialchars($patient['antecedents_personnels'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($consultations): ?>
            <div class="section">
                <div class="section-title">Dernières consultations</div>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Médecin</th>
                            <th>Motif</th>
                            <th>Diagnostic</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($consultations, 0, 5) as $consult): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($consult['date_consultation'])); ?></td>
                            <td><?php echo $consult['docteur_prenom'] . ' ' . $consult['docteur_nom']; ?></td>
                            <td><?php echo htmlspecialchars($consult['motif'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($consult['diagnostic'] ?: 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
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
    `);
    printWindow.document.close();
}

// Confirmer l'archivage
function confirmArchive(patientId) {
    if (confirm('Êtes-vous sûr de vouloir archiver ce patient ?\n\nIl ne sera plus visible dans la liste principale mais restera accessible dans les archives.')) {
        window.location.href = `patients.php?action=archive&id=${patientId}`;
    }
}

// Confirmer la réactivation
function confirmActivate(patientId) {
    if (confirm('Réactiver ce patient ?')) {
        window.location.href = `patients.php?action=activate&id=${patientId}`;
    }
}

// Confirmer la suppression définitive
function confirmDelete(patientId) {
    if (confirm('⚠️ ATTENTION : Suppression définitive !\n\nCette action supprimera toutes les données associées à ce patient (consultations, rendez-vous, prescriptions, etc.).\n\nCette action est irréversible. Confirmez-vous la suppression ?')) {
        window.location.href = `patients.php?action=delete&id=${patientId}`;
    }
}

// Initialiser les tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
    
    // Activer les onglets
    const triggerTabList = [].slice.call(document.querySelectorAll('#medicalTabs button'));
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
.avatar-lg {
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

.patient-avatar {
    position: relative;
}

.patient-avatar::after {
    content: '';
    position: absolute;
    width: 90px;
    height: 90px;
    border-radius: 50%;
    border: 2px dashed #4361ee;
    top: -5px;
    left: 50%;
    transform: translateX(-50%);
    z-index: -1;
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

.list-group-item {
    border-left: 3px solid #4361ee;
    transition: all 0.3s ease;
}

.list-group-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.display-6 {
    font-size: 2.5rem;
    font-weight: 300;
    line-height: 1.2;
}

address {
    line-height: 1.6;
}

.text-small {
    font-size: 0.875rem;
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

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.badge {
    font-size: 0.75rem;
    font-weight: 500;
}
</style>