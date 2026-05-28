<?php
// docteur/ordonnances.php
require_once '../config/database.php';
checkRole('docteur');

$title = 'Gestion des Ordonnances';
$docteur_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

// Gérer les actions
if ($action === 'view' && $id) {
    viewOrdonnance($id);
    exit;
} elseif ($action === 'add') {
    addOrdonnance();
    exit;
} elseif ($action === 'edit' && $id) {
    editOrdonnance($id);
    exit;
}

// Fonction pour afficher une ordonnance
function viewOrdonnance($id) {
    global $pdo, $docteur_id;
    
    $stmt = $pdo->prepare("
        SELECT d.*, 
               p.nom as patient_nom, p.prenom as patient_prenom,
               p.code_patient, p.date_naissance, p.telephone,
               p.adresse, p.ville, p.code_postal,
               u.prenom as docteur_prenom, u.nom as docteur_nom,
               u.specialite, u.telephone as docteur_telephone
        FROM documents_medicaux d
        JOIN patients p ON d.patient_id = p.id
        JOIN utilisateurs u ON d.docteur_id = u.id
        WHERE d.id = ? AND d.docteur_id = ? AND d.type = 'ordonnance'
    ");
    $stmt->execute([$id, $docteur_id]);
    $ordonnance = $stmt->fetch();
    
    if (!$ordonnance) {
        header('Location: ordonnances.php');
        exit;
    }
    
    require_once '../includes/header.php';
    ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-prescription me-2"></i>Ordonnance
                        </h1>
                        <p class="text-muted mb-0"><?php echo $ordonnance['reference'] ?? ''; ?></p>
                    </div>
                    <div class="btn-group">
                        <a href="ordonnances.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Retour
                        </a>
                        <a href="ordonnances.php?action=edit&id=<?php echo $id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i>Modifier
                        </a>
                        <?php if ($ordonnance['fichier_path']): ?>
                        <a href="../uploads/<?php echo $ordonnance['fichier_path']; ?>" class="btn btn-success" target="_blank" download>
                            <i class="fas fa-download me-1"></i>Télécharger
                        </a>
                        <?php endif; ?>
                        <button onclick="window.print()" class="btn btn-outline-secondary">
                            <i class="fas fa-print me-1"></i>Imprimer
                        </button>
                    </div>
                </div>
                
                <!-- Carte d'ordonnance -->
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-prescription me-2"></i>Ordonnance Médicale
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- En-tête -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <h6 class="fw-bold">MÉDECIN PRESCRIPTEUR</h6>
                                    <p class="mb-1">Dr. <?php echo $ordonnance['docteur_prenom'] . ' ' . $ordonnance['docteur_nom']; ?></p>
                                    <p class="mb-1"><?php echo $ordonnance['specialite']; ?></p>
                                    <p class="mb-1"><?php echo $ordonnance['docteur_telephone']; ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3">
                                    <h6 class="fw-bold">PATIENT</h6>
                                    <p class="mb-1"><?php echo $ordonnance['patient_prenom'] . ' ' . $ordonnance['patient_nom']; ?></p>
                                    <p class="mb-1">Né(e) le : <?php echo date('d/m/Y', strtotime($ordonnance['date_naissance'])); ?></p>
                                    <p class="mb-1"><?php echo $ordonnance['telephone']; ?></p>
                                    <p class="mb-0"><?php echo $ordonnance['adresse'] . ', ' . $ordonnance['code_postal'] . ' ' . $ordonnance['ville']; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informations de l'ordonnance -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="border rounded p-3">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Date de prescription :</strong>
                                            <p><?php echo date('d/m/Y', strtotime($ordonnance['created_at'])); ?></p>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Valide jusqu'au :</strong>
                                            <p><?php echo $ordonnance['valide_jusqu'] ? date('d/m/Y', strtotime($ordonnance['valide_jusqu'])) : 'Non spécifié'; ?></p>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Référence :</strong>
                                            <p><?php echo $ordonnance['reference'] ?? 'N/A'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Image de l'ordonnance -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">Document scanné</h6>
                                <?php if ($ordonnance['fichier_path']): ?>
                                <div class="text-center">
                                    <?php 
                                    $extension = pathinfo($ordonnance['fichier_path'], PATHINFO_EXTENSION);
                                    if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])): 
                                    ?>
                                    <img src="../uploads/<?php echo $ordonnance['fichier_path']; ?>" 
                                         class="img-fluid rounded border" 
                                         alt="Ordonnance" 
                                         style="max-height: 600px;">
                                    <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-file-pdf fa-3x mb-3"></i>
                                        <p>Document PDF : <?php echo $ordonnance['fichier_path']; ?></p>
                                        <a href="../uploads/<?php echo $ordonnance['fichier_path']; ?>" 
                                           class="btn btn-primary" target="_blank">
                                            <i class="fas fa-eye me-1"></i>Voir le document
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning text-center">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                    <p class="mb-0">Aucun document scanné n'est disponible pour cette ordonnance.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <?php if ($ordonnance['description']): ?>
                        <div class="row">
                            <div class="col-12">
                                <h6 class="border-bottom pb-2 mb-3">Description</h6>
                                <div class="border rounded p-3">
                                    <?php echo nl2br(htmlspecialchars($ordonnance['description'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-light">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            Créé le : <?php echo date('d/m/Y H:i', strtotime($ordonnance['created_at'])); ?> |
                            <i class="fas fa-user-md me-1"></i>
                            Par : Dr. <?php echo $ordonnance['docteur_prenom'] . ' ' . $ordonnance['docteur_nom']; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    require_once '../includes/footer.php';
}

// Fonction pour ajouter une ordonnance
function addOrdonnance() {
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
                            <i class="fas fa-plus-circle me-2"></i>Nouvelle Ordonnance
                        </h4>
                    </div>
                    <div class="card-body">
                        <form id="ordonnanceForm" enctype="multipart/form-data">
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
                                        <label class="form-label">Date de prescription</label>
                                        <input type="date" class="form-control" name="date_prescription" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Titre de l'ordonnance *</label>
                                <input type="text" class="form-control" name="titre" 
                                       placeholder="Ex: Ordonnance pour traitement antibiotique" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" 
                                          placeholder="Description des médicaments et posologie..."></textarea>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Valide jusqu'au</label>
                                        <input type="date" class="form-control" name="valide_jusqu">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Niveau de confidentialité</label>
                                        <select class="form-select" name="confidentialite">
                                            <option value="normal">Normal</option>
                                            <option value="confidentiel">Confidentiel</option>
                                            <option value="tres_confidentiel">Très confidentiel</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section upload du document -->
                            <div class="border rounded p-4 mb-4">
                                <h5 class="mb-3">
                                    <i class="fas fa-file-upload me-2"></i>Document scanné
                                </h5>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Vous pouvez soit scanner directement l'ordonnance, soit téléverser un fichier PDF/Image.
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Document (PDF ou Image) *</label>
                                    <input type="file" class="form-control" name="document_file" 
                                           accept=".pdf,.jpg,.jpeg,.png,.gif" required>
                                    <small class="text-muted">
                                        Formats acceptés : PDF, JPG, PNG, GIF (Max 10MB)
                                    </small>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary" onclick="openScanner()">
                                        <i class="fas fa-scanner me-2"></i>Scanner le document
                                    </button>
                                </div>
                                
                                <!-- Aperçu du fichier -->
                                <div id="filePreview" class="mt-3" style="display: none;">
                                    <h6>Aperçu :</h6>
                                    <div class="border rounded p-3 text-center">
                                        <img id="previewImage" src="" class="img-fluid rounded" style="max-height: 200px;">
                                        <p id="previewFilename" class="mt-2 mb-0"></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="ordonnances.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Annuler
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer l'ordonnance
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal pour le scanner -->
    <div class="modal fade" id="scannerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-scanner me-2"></i>Scanner l'ordonnance
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="scannerContainer" class="text-center">
                        <div id="cameraView" style="width: 100%; height: 400px; background: #000;"></div>
                        <div class="mt-3">
                            <button id="startCamera" class="btn btn-success me-2">
                                <i class="fas fa-camera me-1"></i>Activer la caméra
                            </button>
                            <button id="capturePhoto" class="btn btn-primary me-2" disabled>
                                <i class="fas fa-camera-retro me-1"></i>Prendre une photo
                            </button>
                            <button id="uploadFile" class="btn btn-secondary">
                                <i class="fas fa-upload me-1"></i>Téléverser un fichier
                            </button>
                        </div>
                    </div>
                    <div id="scanResult" style="display: none;">
                        <!-- Résultat du scan -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Gestion de l'aperçu du fichier
    document.querySelector('input[name="document_file"]').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const preview = document.getElementById('filePreview');
        const previewImage = document.getElementById('previewImage');
        const previewFilename = document.getElementById('previewFilename');
        
        previewFilename.textContent = file.name;
        
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            previewImage.src = '../assets/images/pdf-icon.png';
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    });
    
    // Fonction pour ouvrir le scanner
    function openScanner() {
        const modal = new bootstrap.Modal(document.getElementById('scannerModal'));
        modal.show();
    }
    
    // Gestion du formulaire
    document.getElementById('ordonnanceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'add');
        formData.append('type', 'ordonnance');
        
        fetch('../ajax/save_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Ordonnance enregistrée avec succès!');
                window.location.href = 'ordonnances.php?action=view&id=' + result.id;
            } else {
                alert('Erreur: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue');
        });
    });
    
    // Gestion du scanner
    let cameraStream = null;
    
    document.getElementById('startCamera').addEventListener('click', async function() {
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: 'environment' } 
            });
            const cameraView = document.getElementById('cameraView');
            cameraView.innerHTML = '';
            const video = document.createElement('video');
            video.srcObject = cameraStream;
            video.autoplay = true;
            video.playsInline = true;
            video.style.width = '100%';
            video.style.height = '100%';
            cameraView.appendChild(video);
            
            document.getElementById('capturePhoto').disabled = false;
            this.disabled = true;
        } catch (error) {
            alert('Impossible d\'accéder à la caméra: ' + error.message);
        }
    });
    
    document.getElementById('capturePhoto').addEventListener('click', function() {
        const cameraView = document.getElementById('cameraView');
        const video = cameraView.querySelector('video');
        
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        // Arrêter la caméra
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
        }
        
        // Convertir en blob et l'ajouter au formulaire
        canvas.toBlob(function(blob) {
            const file = new File([blob], 'ordonnance_scan.jpg', { type: 'image/jpeg' });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            document.querySelector('input[name="document_file"]').files = dataTransfer.files;
            
            // Déclencher l'événement change pour l'aperçu
            document.querySelector('input[name="document_file"]').dispatchEvent(new Event('change'));
            
            // Fermer le modal
            bootstrap.Modal.getInstance(document.getElementById('scannerModal')).hide();
        }, 'image/jpeg', 0.9);
    });
    
    document.getElementById('uploadFile').addEventListener('click', function() {
        // Simuler un clic sur l'input file
        document.querySelector('input[name="document_file"]').click();
        bootstrap.Modal.getInstance(document.getElementById('scannerModal')).hide();
    });
    </script>
    
    <?php
    require_once '../includes/footer.php';
}

// Fonction pour modifier une ordonnance
function editOrdonnance($id) {
    global $pdo, $docteur_id;
    
    // Récupérer l'ordonnance
    $stmt = $pdo->prepare("
        SELECT d.*, p.nom, p.prenom
        FROM documents_medicaux d
        JOIN patients p ON d.patient_id = p.id
        WHERE d.id = ? AND d.docteur_id = ? AND d.type = 'ordonnance'
    ");
    $stmt->execute([$id, $docteur_id]);
    $ordonnance = $stmt->fetch();
    
    if (!$ordonnance) {
        header('Location: ordonnances.php');
        exit;
    }
    
    require_once '../includes/header.php';
    ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h4 class="mb-0">
                            <i class="fas fa-edit me-2"></i>Modifier l'Ordonnance
                        </h4>
                    </div>
                    <div class="card-body">
                        <form id="editOrdonnanceForm" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Titre de l'ordonnance *</label>
                                <input type="text" class="form-control" name="titre" 
                                       value="<?php echo htmlspecialchars($ordonnance['titre']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($ordonnance['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Valide jusqu'au</label>
                                        <input type="date" class="form-control" name="valide_jusqu" 
                                               value="<?php echo $ordonnance['valide_jusqu']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Niveau de confidentialité</label>
                                        <select class="form-select" name="confidentialite">
                                            <option value="normal" <?php echo $ordonnance['confidentialite'] == 'normal' ? 'selected' : ''; ?>>Normal</option>
                                            <option value="confidentiel" <?php echo $ordonnance['confidentialite'] == 'confidentiel' ? 'selected' : ''; ?>>Confidentiel</option>
                                            <option value="tres_confidentiel" <?php echo $ordonnance['confidentialite'] == 'tres_confidentiel' ? 'selected' : ''; ?>>Très confidentiel</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Document actuel -->
                            <div class="border rounded p-3 mb-3">
                                <h6>Document actuel</h6>
                                <?php if ($ordonnance['fichier_path']): ?>
                                <div class="d-flex align-items-center">
                                    <?php 
                                    $extension = pathinfo($ordonnance['fichier_path'], PATHINFO_EXTENSION);
                                    if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])):
                                    ?>
                                    <img src="../uploads/<?php echo $ordonnance['fichier_path']; ?>" 
                                         class="img-thumbnail me-3" style="width: 100px; height: 100px; object-fit: cover;">
                                    <?php else: ?>
                                    <div class="bg-light rounded p-3 me-3">
                                        <i class="fas fa-file-pdf fa-2x text-danger"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="mb-1"><?php echo $ordonnance['fichier_path']; ?></p>
                                        <a href="../uploads/<?php echo $ordonnance['fichier_path']; ?>" 
                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>Voir
                                        </a>
                                    </div>
                                </div>
                                <?php else: ?>
                                <p class="text-muted">Aucun document</p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Remplacer le document -->
                            <div class="border rounded p-3 mb-4">
                                <h6 class="mb-3">
                                    <i class="fas fa-sync-alt me-2"></i>Remplacer le document
                                </h6>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="replaceFile">
                                    <label class="form-check-label" for="replaceFile">
                                        Remplacer le fichier existant
                                    </label>
                                </div>
                                
                                <div id="newFileContainer" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label">Nouveau document (PDF ou Image)</label>
                                        <input type="file" class="form-control" name="new_document_file" 
                                               accept=".pdf,.jpg,.jpeg,.png,.gif">
                                        <small class="text-muted">
                                            Laissez vide pour conserver le document actuel
                                        </small>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-primary" onclick="openScanner()">
                                            <i class="fas fa-scanner me-2"></i>Scanner un nouveau document
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="ordonnances.php?action=view&id=<?php echo $id; ?>" class="btn btn-secondary">
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
    // Gestion du remplacement de fichier
    document.getElementById('replaceFile').addEventListener('change', function() {
        document.getElementById('newFileContainer').style.display = this.checked ? 'block' : 'none';
    });
    
    // Gestion du formulaire
    document.getElementById('editOrdonnanceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'edit');
        formData.append('type', 'ordonnance');
        
        fetch('../ajax/save_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Ordonnance modifiée avec succès!');
                window.location.href = 'ordonnances.php?action=view&id=' + result.id;
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

// Liste principale des ordonnances
require_once '../includes/header.php';

// Compter les ordonnances
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN confidentialite = 'confidentiel' THEN 1 ELSE 0 END) as confidentiel,
        SUM(CASE WHEN confidentialite = 'tres_confidentiel' THEN 1 ELSE 0 END) as tres_confidentiel
    FROM documents_medicaux
    WHERE docteur_id = ? AND type = 'ordonnance'
");
$stats_stmt->execute([$docteur_id]);
$stats = $stats_stmt->fetch();

// Récupérer les ordonnances
$ordonnances_stmt = $pdo->prepare("
    SELECT d.*, p.nom, p.prenom, p.code_patient, p.date_naissance,
           (CASE 
                WHEN d.fichier_path IS NOT NULL THEN 1 
                ELSE 0 
            END) as has_file
    FROM documents_medicaux d
    JOIN patients p ON d.patient_id = p.id
    WHERE d.docteur_id = ? AND d.type = 'ordonnance'
    ORDER BY d.created_at DESC
    LIMIT 20
");
$ordonnances_stmt->execute([$docteur_id]);
$ordonnances = $ordonnances_stmt->fetchAll();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-prescription me-2"></i>Gestion des Ordonnances
        </h1>
        <p class="text-muted mb-0">
            <span class="fw-semibold">Dr. <?php echo $_SESSION['prenom'] . ' ' . $_SESSION['nom']; ?></span>
        </p>
    </div>
    <div class="btn-toolbar">
        <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="exportOrdonnances()">
            <i class="fas fa-download me-1"></i>Exporter
        </button>
        <a href="ordonnances.php?action=add" class="btn btn-sm btn-primary">
            <i class="fas fa-plus-circle me-1"></i>Nouvelle ordonnance
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
                        <div class="text-muted small">Avec document</div>
                        <?php 
                        $with_file = array_sum(array_column($ordonnances, 'has_file'));
                        ?>
                        <div class="h4 mb-0"><?php echo $with_file; ?></div>
                    </div>
                    <div class="rounded-circle bg-success-light p-3">
                        <i class="fas fa-file-pdf text-success fa-lg"></i>
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
                        <div class="text-muted small">Confidentielles</div>
                        <div class="h4 mb-0"><?php echo $stats['confidentiel']; ?></div>
                    </div>
                    <div class="rounded-circle bg-warning-light p-3">
                        <i class="fas fa-lock text-warning fa-lg"></i>
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
                        <div class="text-muted small">Très confidentielles</div>
                        <div class="h4 mb-0"><?php echo $stats['tres_confidentiel']; ?></div>
                    </div>
                    <div class="rounded-circle bg-info-light p-3">
                        <i class="fas fa-shield-alt text-info fa-lg"></i>
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
            <div class="col-md-4">
                <input type="text" class="form-control" placeholder="Rechercher par patient..." 
                       name="search" value="<?php echo $_GET['search'] ?? ''; ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="confidentialite">
                    <option value="">Tous les niveaux</option>
                    <option value="normal" <?php echo ($_GET['confidentialite'] ?? '') == 'normal' ? 'selected' : ''; ?>>Normal</option>
                    <option value="confidentiel" <?php echo ($_GET['confidentialite'] ?? '') == 'confidentiel' ? 'selected' : ''; ?>>Confidentiel</option>
                    <option value="tres_confidentiel" <?php echo ($_GET['confidentialite'] ?? '') == 'tres_confidentiel' ? 'selected' : ''; ?>>Très confidentiel</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="has_file">
                    <option value="">Tous les documents</option>
                    <option value="1" <?php echo ($_GET['has_file'] ?? '') == '1' ? 'selected' : ''; ?>>Avec fichier</option>
                    <option value="0" <?php echo ($_GET['has_file'] ?? '') == '0' ? 'selected' : ''; ?>>Sans fichier</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des ordonnances -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Ordonnances récentes
        </h5>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                    data-bs-toggle="dropdown">
                <i class="fas fa-filter me-1"></i>Actions
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="printAll()">Imprimer toutes</a></li>
                <li><a class="dropdown-item" href="#" onclick="exportPDF()">Exporter en PDF</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="checkMissingFiles()">Vérifier fichiers manquants</a></li>
            </ul>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($ordonnances)): ?>
        <div class="text-center py-5">
            <i class="fas fa-prescription-bottle-alt fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Aucune ordonnance trouvée</h5>
            <p class="text-muted small mb-3">Vous n'avez pas encore créé d'ordonnances</p>
            <a href="ordonnances.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Créer votre première ordonnance
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Patient</th>
                        <th>Titre</th>
                        <th>Date</th>
                        <th>Document</th>
                        <th>Confidentialité</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordonnances as $doc): 
                        $age = calculateAge($doc['date_naissance']);
                        $confidentialiteColors = [
                            'normal' => 'secondary',
                            'confidentiel' => 'warning',
                            'tres_confidentiel' => 'danger'
                        ];
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
                            <div><?php echo date('d/m/Y', strtotime($doc['created_at'])); ?></div>
                            <small class="text-muted"><?php echo date('H:i', strtotime($doc['created_at'])); ?></small>
                        </td>
                        <td>
                            <?php if ($doc['has_file']): ?>
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
                            <span class="badge bg-<?php echo $confidentialiteColors[$doc['confidentialite']] ?? 'secondary'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $doc['confidentialite'])); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="ordonnances.php?action=view&id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-outline-primary" title="Voir">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="ordonnances.php?action=edit&id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-outline-secondary" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($doc['has_file']): ?>
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
                    Affichage des 20 dernières ordonnances
                </small>
            </div>
            <div class="col-md-6 text-end">
                <a href="ordonnances.php?view=all" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-archive me-1"></i>Voir toutes les ordonnances
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
function exportOrdonnances() {
    window.open('../ajax/export_ordonnances.php', '_blank');
}

function exportPDF() {
    const ids = Array.from(document.querySelectorAll('tr')).slice(1).map(tr => {
        const link = tr.querySelector('a[href*="action=view"]');
        return link ? link.href.split('id=')[1] : null;
    }).filter(id => id);
    
    window.open(`../ajax/export_pdf.php?ids=${ids.join(',')}`, '_blank');
}

function printAll() {
    window.open('ordonnances.php?print=all', '_blank');
}

function checkMissingFiles() {
    const missing = document.querySelectorAll('tr .badge.bg-danger');
    if (missing.length > 0) {
        alert(`Attention : ${missing.length} ordonnance(s) sans document scanné.`);
    } else {
        alert('Toutes les ordonnances ont un document associé.');
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

#cameraView {
    position: relative;
    overflow: hidden;
}

#cameraView video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>