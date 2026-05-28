<?php
// docteur/certificats.php
require_once '../config/database.php';
checkRole('docteur');

$title = 'Gestion des Certificats';
$docteur_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

// Gérer les actions
if ($action === 'view' && $id) {
    viewCertificat($id);
    exit;
} elseif ($action === 'add') {
    addCertificat();
    exit;
} elseif ($action === 'edit' && $id) {
    editCertificat($id);
    exit;
}

// Fonction pour afficher un certificat
function viewCertificat($id) {
    global $pdo, $docteur_id;
    
    $stmt = $pdo->prepare("
        SELECT d.*, 
               p.nom as patient_nom, p.prenom as patient_prenom,
               p.code_patient, p.date_naissance, p.telephone,
               p.adresse, p.ville, p.code_postal,
               u.prenom as docteur_prenom, u.nom as docteur_nom,
               u.specialite, u.telephone as docteur_telephone,
               c.motif as motif_consultation,
               c.created_at as date_consultation
        FROM documents_medicaux d
        JOIN patients p ON d.patient_id = p.id
        JOIN utilisateurs u ON d.docteur_id = u.id
        LEFT JOIN consultations c ON d.consultation_id = c.id
        WHERE d.id = ? AND d.docteur_id = ? AND d.type = 'certificat'
    ");
    $stmt->execute([$id, $docteur_id]);
    $certificat = $stmt->fetch();
    
    if (!$certificat) {
        header('Location: certificats.php');
        exit;
    }
    
    // Décoder les métadonnées si elles existent
    $metadata = [];
    if (!empty($certificat['metadata'])) {
        $metadata = json_decode($certificat['metadata'], true);
    }
    
    require_once '../includes/header.php';
    ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-file-medical me-2"></i>Certificat Médical
                        </h1>
                        <p class="text-muted mb-0"><?php echo $certificat['reference'] ?? ''; ?></p>
                    </div>
                    <div class="btn-group">
                        <a href="certificats.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Retour
                        </a>
                        <a href="certificats.php?action=edit&id=<?php echo $id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i>Modifier
                        </a>
                        <?php if ($certificat['fichier_path']): ?>
                        <a href="../uploads/<?php echo $certificat['fichier_path']; ?>" class="btn btn-success" target="_blank" download>
                            <i class="fas fa-download me-1"></i>Télécharger
                        </a>
                        <?php endif; ?>
                        <button onclick="window.print()" class="btn btn-outline-secondary">
                            <i class="fas fa-print me-1"></i>Imprimer
                        </button>
                    </div>
                </div>
                
                <!-- Certificat médical -->
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-file-medical me-2"></i>Certificat Médical
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- En-tête -->
                        <div class="text-center mb-4 border-bottom pb-4">
                            <h3 class="fw-bold text-primary mb-1">CERTIFICAT MÉDICAL</h3>
                            <p class="text-muted mb-0">N° <?php echo $certificat['fichier_nom'] ?? ''; ?></p>
                        </div>
                        
                        <!-- Informations du médecin -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <h6 class="fw-bold">MÉDECIN</h6>
                                    <p class="mb-1">Dr. <?php echo $certificat['docteur_prenom'] . ' ' . $certificat['docteur_nom']; ?></p>
                                    <p class="mb-1"><?php echo $certificat['specialite']; ?></p>
                                    <p class="mb-1"><?php echo $certificat['docteur_telephone']; ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <h6 class="fw-bold">PATIENT</h6>
                                    <p class="mb-1"><strong>Nom :</strong> <?php echo $certificat['patient_prenom'] . ' ' . $certificat['patient_nom']; ?></p>
                                    <p class="mb-1"><strong>Né(e) le :</strong> <?php echo date('d/m/Y', strtotime($certificat['date_naissance'])); ?></p>
                                    <p class="mb-1"><strong>Téléphone :</strong> <?php echo $certificat['telephone']; ?></p>
                                    <p class="mb-0"><strong>Adresse :</strong> <?php echo $certificat['adresse'] . ', ' . $certificat['code_postal'] . ' ' . $certificat['ville']; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contenu du certificat -->
                        <div class="certificat-content border rounded p-4 mb-4">
                            <div class="mb-4">
                                <h6 class="fw-bold mb-3">OBJET DU CERTIFICAT</h6>
                                <p class="fs-5 fw-bold"><?php echo $certificat['titre']; ?></p>
                            </div>
                            
                            <?php if ($certificat['description']): ?>
                            <div class="mb-4">
                                <h6 class="fw-bold mb-3">OBSERVATIONS MÉDICALES</h6>
                                <div class="p-3 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($certificat['description'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Dates importantes -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="border rounded p-3">
                                        <h6 class="fw-bold mb-3">DATES</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <p class="mb-1"><strong>Date de consultation :</strong></p>
                                                <p class="mb-1"><strong>Date d'effet :</strong></p>
                                                <p class="mb-1"><strong>Date d'expiration :</strong></p>
                                            </div>
                                            <div class="col-6">
                                                <p class="mb-1"><?php echo $certificat['date_consultation'] ? date('d/m/Y', strtotime($certificat['date_consultation'])) : date('d/m/Y', strtotime($certificat['created_at'])); ?></p>
                                                <p class="mb-1"><?php echo !empty($metadata['date_debut']) ? date('d/m/Y', strtotime($metadata['date_debut'])) : 'Immédiat'; ?></p>
                                                <p class="mb-0"><?php echo $certificat['valide_jusqu'] && $certificat['valide_jusqu'] != '0000-00-00' ? date('d/m/Y', strtotime($certificat['valide_jusqu'])) : 'Non spécifié'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3">
                                        <h6 class="fw-bold mb-3">INFORMATIONS</h6>
                                        <?php if (!empty($metadata['type_certificat'])): ?>
                                        <p class="mb-1"><strong>Type :</strong> <?php echo ucfirst($metadata['type_certificat']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($metadata['duree'])): ?>
                                        <p class="mb-1"><strong>Durée :</strong> <?php echo $metadata['duree']; ?> jours</p>
                                        <?php endif; ?>
                                        <?php if (!empty($metadata['recommandations'])): ?>
                                        <div class="mt-2 p-2 border-top">
                                            <strong>Recommandations :</strong><br>
                                            <?php echo nl2br(htmlspecialchars($metadata['recommandations'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Signature -->
                            <div class="text-end mt-4 pt-4 border-top">
                                <div class="d-inline-block text-center">
                                    <p class="mb-2">Fait à <?php echo $certificat['ville'] ?? ''; ?>, le <?php echo date('d/m/Y', strtotime($certificat['created_at'])); ?></p>
                                    <p class="fw-bold mb-1">Dr. <?php echo $certificat['docteur_prenom'] . ' ' . $certificat['docteur_nom']; ?></p>
                                    <p class="text-muted small">Médecin</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Document scanné -->
                        <?php if ($certificat['fichier_path']): ?>
                        <div class="border rounded p-3 mb-4">
                            <h6 class="mb-3">Document scanné</h6>
                            <div class="text-center">
                                <?php 
                                $extension = pathinfo($certificat['fichier_path'], PATHINFO_EXTENSION);
                                if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])): 
                                ?>
                                <img src="../uploads/<?php echo $certificat['fichier_path']; ?>" 
                                     class="img-fluid rounded border" 
                                     alt="Certificat" 
                                     style="max-height: 500px;">
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-file-pdf fa-3x mb-3"></i>
                                    <p>Document PDF : <?php echo $certificat['fichier_path']; ?></p>
                                    <a href="../uploads/<?php echo $certificat['fichier_path']; ?>" 
                                       class="btn btn-primary" target="_blank">
                                        <i class="fas fa-eye me-1"></i>Voir le document
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-light">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            Établi le : <?php echo date('d/m/Y H:i', strtotime($certificat['created_at'])); ?> |
                            <i class="fas fa-user-md me-1"></i>
                            Médecin : Dr. <?php echo $certificat['docteur_prenom'] . ' ' . $certificat['docteur_nom']; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .certificat-content {
        background: white;
        font-family: 'Times New Roman', serif;
    }
    </style>
    
    <?php
    require_once '../includes/footer.php';
}

// Fonction pour ajouter un certificat
function addCertificat() {
    global $pdo, $docteur_id;
    
    // Récupérer les patients
    $patients_stmt = $pdo->prepare("
        SELECT p.id, p.nom, p.prenom, p.code_patient, p.date_naissance
        FROM patients p
        WHERE p.statut = 'actif'
        ORDER BY p.nom, p.prenom
    ");
    $patients_stmt->execute();
    $patients = $patients_stmt->fetchAll();
    
    require_once '../includes/header.php';
    ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>Nouveau Certificat
                        </h4>
                    </div>
                    <div class="card-body">
                        <form id="certificatForm" enctype="multipart/form-data">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Patient *</label>
                                        <select class="form-select" name="patient_id" required>
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
                                        <label class="form-label">Type de certificat *</label>
                                        <select class="form-select" name="type_certificat" required>
                                            <option value="">Sélectionner...</option>
                                            <option value="aptitude">Certificat d'aptitude</option>
                                            <option value="incapacite">Certificat d'incapacité</option>
                                            <option value="arrete_travail">Certificat d'arrêt de travail</option>
                                            <option value="hospitalisation">Certificat d'hospitalisation</option>
                                            <option value="visite">Certificat de visite</option>
                                            <option value="vaccination">Certificat de vaccination</option>
                                            <option value="guerison">Certificat de guérison</option>
                                            <option value="deces">Certificat de décès</option>
                                            <option value="autre">Autre</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Titre du certificat *</label>
                                <input type="text" class="form-control" name="titre" 
                                       placeholder="Ex: Certificat d'arrêt de travail" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Observations médicales *</label>
                                <textarea class="form-control" name="description" rows="4" 
                                          placeholder="Décrivez les observations médicales, le diagnostic, les constatations..." required></textarea>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Date d'effet</label>
                                        <input type="date" class="form-control" name="date_debut" 
                                               value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Date d'expiration</label>
                                        <input type="date" class="form-control" name="date_fin">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Durée (jours)</label>
                                        <input type="number" class="form-control" name="duree" 
                                               placeholder="Ex: 7">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Recommandations</label>
                                <textarea class="form-control" name="recommandations" rows="3" 
                                          placeholder="Traitement, repos, suivi médical..."></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Destinataire(s)</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="destinataire[]" value="patient" checked>
                                    <label class="form-check-label">Patient</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="destinataire[]" value="employeur">
                                    <label class="form-check-label">Employeur</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="destinataire[]" value="assurance">
                                    <label class="form-check-label">Assurance</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="destinataire[]" value="administration">
                                    <label class="form-check-label">Administration</label>
                                </div>
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
                                <a href="certificats.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Annuler
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer le certificat
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
    document.getElementById('certificatForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Créer l'objet metadata
        const metadata = {
            type_certificat: formData.get('type_certificat'),
            date_debut: formData.get('date_debut'),
            duree: formData.get('duree'),
            recommandations: formData.get('recommandations'),
            destinataires: formData.getAll('destinataire[]')
        };
        
        formData.append('metadata', JSON.stringify(metadata));
        formData.append('action', 'add');
        formData.append('type', 'certificat');
        formData.append('valide_jusqu', formData.get('date_fin'));
        
        // Supprimer les champs individuels qui seront dans metadata
        formData.delete('type_certificat');
        formData.delete('date_debut');
        formData.delete('date_fin');
        formData.delete('duree');
        formData.delete('recommandations');
        formData.getAll('destinataire[]').forEach(() => {
            formData.delete('destinataire[]');
        });
        
        fetch('../ajax/save_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Certificat enregistré avec succès!');
                window.location.href = 'certificats.php?action=view&id=' + result.id;
            } else {
                alert('Erreur: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue');
        });
    });
    
    // Calcul automatique de la date de fin si durée saisie
    document.querySelector('input[name="duree"]').addEventListener('change', function() {
        const duree = parseInt(this.value);
        const dateDebut = document.querySelector('input[name="date_debut"]').value;
        
        if (duree && dateDebut) {
            const date = new Date(dateDebut);
            date.setDate(date.getDate() + duree);
            document.querySelector('input[name="date_fin"]').value = date.toISOString().split('T')[0];
        }
    });
    </script>
    
    <?php
    require_once '../includes/footer.php';
}

// Fonction pour modifier un certificat
function editCertificat($id) {
    global $pdo, $docteur_id;
    
    // Récupérer le certificat
    $stmt = $pdo->prepare("
        SELECT d.*, p.nom, p.prenom
        FROM documents_medicaux d
        JOIN patients p ON d.patient_id = p.id
        WHERE d.id = ? AND d.docteur_id = ? AND d.type = 'certificat'
    ");
    $stmt->execute([$id, $docteur_id]);
    $certificat = $stmt->fetch();
    
    if (!$certificat) {
        header('Location: certificats.php');
        exit;
    }
    
    // Récupérer les métadonnées
    $metadata = [];
    if (!empty($certificat['metadata'])) {
        $metadata = json_decode($certificat['metadata'], true);
    }
    
    require_once '../includes/header.php';
    ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h4 class="mb-0">
                            <i class="fas fa-edit me-2"></i>Modifier le Certificat
                        </h4>
                    </div>
                    <div class="card-body">
                        <form id="editCertificatForm" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Titre du certificat *</label>
                                <input type="text" class="form-control" name="titre" 
                                       value="<?php echo htmlspecialchars($certificat['titre']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Observations médicales *</label>
                                <textarea class="form-control" name="description" rows="4" required><?php echo htmlspecialchars($certificat['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Date d'effet</label>
                                        <input type="date" class="form-control" name="date_debut" 
                                               value="<?php echo !empty($metadata['date_debut']) ? $metadata['date_debut'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Date d'expiration</label>
                                        <input type="date" class="form-control" name="date_fin" 
                                               value="<?php echo $certificat['valide_jusqu'] && $certificat['valide_jusqu'] != '0000-00-00' ? $certificat['valide_jusqu'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Durée (jours)</label>
                                        <input type="number" class="form-control" name="duree" 
                                               value="<?php echo $metadata['duree'] ?? ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Recommandations</label>
                                <textarea class="form-control" name="recommandations" rows="3"><?php echo htmlspecialchars($metadata['recommandations'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Destinataire(s)</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="destinataire[]" value="patient" <?php echo in_array('patient', $metadata['destinataires'] ?? []) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Patient</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="destinataire[]" value="employeur" <?php echo in_array('employeur', $metadata['destinataires'] ?? []) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Employeur</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="destinataire[]" value="assurance" <?php echo in_array('assurance', $metadata['destinataires'] ?? []) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Assurance</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="destinataire[]" value="administration" <?php echo in_array('administration', $metadata['destinataires'] ?? []) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Administration</label>
                                </div>
                            </div>
                            
                            <!-- Document actuel -->
                            <?php if ($certificat['fichier_path']): ?>
                            <div class="border rounded p-3 mb-3">
                                <h6>Document actuel</h6>
                                <div class="d-flex align-items-center">
                                    <?php 
                                    $extension = pathinfo($certificat['fichier_path'], PATHINFO_EXTENSION);
                                    if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])):
                                    ?>
                                    <img src="../uploads/<?php echo $certificat['fichier_path']; ?>" 
                                         class="img-thumbnail me-3" style="width: 100px; height: 100px; object-fit: cover;">
                                    <?php else: ?>
                                    <div class="bg-light rounded p-3 me-3">
                                        <i class="fas fa-file-pdf fa-2x text-danger"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="mb-1"><?php echo $certificat['fichier_path']; ?></p>
                                        <a href="../uploads/<?php echo $certificat['fichier_path']; ?>" 
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
                                <a href="certificats.php?action=view&id=<?php echo $id; ?>" class="btn btn-secondary">
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
    document.getElementById('editCertificatForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Créer l'objet metadata
        const metadata = {
            type_certificat: '<?php echo $metadata['type_certificat'] ?? ''; ?>',
            date_debut: formData.get('date_debut'),
            duree: formData.get('duree'),
            recommandations: formData.get('recommandations'),
            destinataires: formData.getAll('destinataire[]')
        };
        
        formData.append('metadata', JSON.stringify(metadata));
        formData.append('action', 'edit');
        formData.append('type', 'certificat');
        formData.append('valide_jusqu', formData.get('date_fin'));
        
        // Supprimer les champs individuels
        formData.delete('date_debut');
        formData.delete('date_fin');
        formData.delete('duree');
        formData.delete('recommandations');
        formData.getAll('destinataire[]').forEach(() => {
            formData.delete('destinataire[]');
        });
        
        fetch('../ajax/save_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Certificat modifié avec succès!');
                window.location.href = 'certificats.php?action=view&id=' + result.id;
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

// Liste principale des certificats
require_once '../includes/header.php';

// Compter les certificats
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN valide_jusqu >= CURDATE() OR valide_jusqu IS NULL OR valide_jusqu = '0000-00-00' THEN 1 ELSE 0 END) as valide,
        SUM(CASE WHEN valide_jusqu < CURDATE() AND valide_jusqu != '0000-00-00' THEN 1 ELSE 0 END) as expire,
        SUM(CASE WHEN fichier_path IS NOT NULL THEN 1 ELSE 0 END) as avec_document
    FROM documents_medicaux
    WHERE docteur_id = ? AND type = 'certificat'
");
$stats_stmt->execute([$docteur_id]);
$stats = $stats_stmt->fetch();

// Récupérer les certificats
$certificats_stmt = $pdo->prepare("
    SELECT d.*, p.nom, p.prenom, p.code_patient, p.date_naissance,
           (CASE 
                WHEN d.valide_jusqu >= CURDATE() OR d.valide_jusqu IS NULL OR d.valide_jusqu = '0000-00-00' THEN 'valide'
                ELSE 'expire'
            END) as statut_valide
    FROM documents_medicaux d
    JOIN patients p ON d.patient_id = p.id
    WHERE d.docteur_id = ? AND d.type = 'certificat'
    ORDER BY d.created_at DESC
    LIMIT 20
");
$certificats_stmt->execute([$docteur_id]);
$certificats = $certificats_stmt->fetchAll();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-file-medical me-2"></i>Gestion des Certificats
        </h1>
        <p class="text-muted mb-0">
            <span class="fw-semibold">Dr. <?php echo $_SESSION['prenom'] . ' ' . $_SESSION['nom']; ?></span>
        </p>
    </div>
    <div class="btn-toolbar">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportCertificats()">
                <i class="fas fa-download me-1"></i>Exporter
            </button>
        </div>
        <a href="certificats.php?action=add" class="btn btn-sm btn-primary">
            <i class="fas fa-plus-circle me-1"></i>Nouveau certificat
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
                        <i class="fas fa-file-medical text-primary fa-lg"></i>
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
                        <div class="text-muted small">Certificats valides</div>
                        <div class="h4 mb-0"><?php echo $stats['valide']; ?></div>
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
                        <div class="text-muted small">Certificats expirés</div>
                        <div class="h4 mb-0"><?php echo $stats['expire']; ?></div>
                    </div>
                    <div class="rounded-circle bg-warning-light p-3">
                        <i class="fas fa-exclamation-triangle text-warning fa-lg"></i>
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
                <select class="form-select" name="statut">
                    <option value="">Tous les statuts</option>
                    <option value="valide" <?php echo ($_GET['statut'] ?? '') == 'valide' ? 'selected' : ''; ?>>Valides</option>
                    <option value="expire" <?php echo ($_GET['statut'] ?? '') == 'expire' ? 'selected' : ''; ?>>Expirés</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="has_document">
                    <option value="">Tous</option>
                    <option value="1" <?php echo ($_GET['has_document'] ?? '') == '1' ? 'selected' : ''; ?>>Avec document</option>
                    <option value="0" <?php echo ($_GET['has_document'] ?? '') == '0' ? 'selected' : ''; ?>>Sans document</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des certificats -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Certificats récents
        </h5>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                    data-bs-toggle="dropdown">
                <i class="fas fa-filter me-1"></i>Actions
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="printCertificats()">Imprimer tous</a></li>
                <li><a class="dropdown-item" href="#" onclick="checkExpiring()">Vérifier les expirations</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="generateReport()">Générer un rapport</a></li>
            </ul>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($certificats)): ?>
        <div class="text-center py-5">
            <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Aucun certificat trouvé</h5>
            <p class="text-muted small mb-3">Vous n'avez pas encore créé de certificats médicaux</p>
            <a href="certificats.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Créer votre premier certificat
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Titre</th>
                        <th>Dates</th>
                        <th>Statut</th>
                        <th>Document</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($certificats as $doc): 
                        $age = calculateAge($doc['date_naissance']);
                        $statutColors = [
                            'valide' => 'success',
                            'expire' => 'danger'
                        ];
                        $statutText = [
                            'valide' => 'Valide',
                            'expire' => 'Expiré'
                        ];
                        
                        // Décoder les métadonnées pour obtenir le type
                        $type = 'autre';
                        if (!empty($doc['metadata'])) {
                            $docMetadata = json_decode($doc['metadata'], true);
                            $type = $docMetadata['type_certificat'] ?? 'autre';
                        }
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
                            <small class="text-muted">
                                <?php echo ucfirst($type); ?>
                            </small>
                        </td>
                        <td>
                            <div><?php echo date('d/m/Y', strtotime($doc['created_at'])); ?></div>
                            <?php if ($doc['valide_jusqu'] && $doc['valide_jusqu'] != '0000-00-00'): ?>
                            <small class="text-muted">
                                Jusqu'au : <?php echo date('d/m/Y', strtotime($doc['valide_jusqu'])); ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $statutColors[$doc['statut_valide']] ?? 'secondary'; ?>">
                                <?php echo $statutText[$doc['statut_valide']] ?? 'Inconnu'; ?>
                            </span>
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
                                <a href="certificats.php?action=view&id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-outline-primary" title="Voir">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="certificats.php?action=edit&id=<?php echo $doc['id']; ?>" 
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
                    Affichage des 20 derniers certificats
                </small>
            </div>
            <div class="col-md-6 text-end">
                <a href="certificats.php?view=all" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-archive me-1"></i>Voir tous les certificats
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
function exportCertificats() {
    window.open('../ajax/export_certificats.php', '_blank');
}

function printCertificats() {
    const ids = Array.from(document.querySelectorAll('tr')).slice(1).map(tr => {
        const link = tr.querySelector('a[href*="action=view"]');
        return link ? link.href.split('id=')[1] : null;
    }).filter(id => id);
    
    window.open(`../ajax/print_multiple.php?type=certificat&ids=${ids.join(',')}`, '_blank');
}

function checkExpiring() {
    const expiring = document.querySelectorAll('tr .badge.bg-warning, tr .badge.bg-danger');
    if (expiring.length > 0) {
        alert(`Attention : ${expiring.length} certificat(s) expirés ou sur le point d'expirer.`);
    } else {
        alert('Tous les certificats sont valides.');
    }
}

function generateReport() {
    window.open('../ajax/rapport_certificats.php', '_blank');
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