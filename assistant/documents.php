<?php
// assistant/documents.php
require_once '../config/database.php';
checkRole('assistant');

$title = 'Documents Médicaux';
$docteur_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

// Fonction pour ajouter un document
function addDocument() {
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
    
    // Récupérer les consultations récentes
    $consultations_stmt = $pdo->prepare("
        SELECT c.id, c.reference, c.date_consultation, 
               c.motif, p.nom, p.prenom
        FROM consultations c
        JOIN patients p ON c.patient_id = p.id
        WHERE c.docteur_id = ? AND c.statut = 'termine'
        ORDER BY c.date_consultation DESC
        LIMIT 20
    ");
    $consultations_stmt->execute([$docteur_id]);
    $consultations = $consultations_stmt->fetchAll();
    
    require_once '../includes/header.php';
    ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>Nouveau Document
                        </h4>
                    </div>
                    <div class="card-body">
                        <form id="addDocumentForm" enctype="multipart/form-data">
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
                                        <label class="form-label">Type de document *</label>
                                        <select class="form-select" name="type" required>
                                            <option value="">Sélectionner un type...</option>
                                            <option value="ordonnance">Ordonnance</option>
                                            <option value="certificat">Certificat</option>
                                            <option value="compte_rendu">Compte rendu</option>
                                            <option value="resultat_analyse">Résultat d'analyse</option>
                                            <option value="imagerie">Imagerie médicale</option>
                                            <option value="autre">Autre document</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Titre du document *</label>
                                <input type="text" class="form-control" name="titre" 
                                       placeholder="Ex: Ordonnance pour traitement antibiotique" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" 
                                          placeholder="Description du document..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Consultation associée (optionnel)</label>
                                <select class="form-select" name="consultation_id" id="consultationSelect">
                                    <option value="">Sélectionner une consultation...</option>
                                    <?php foreach ($consultations as $consultation): ?>
                                    <option value="<?php echo $consultation['id']; ?>">
                                        <?php echo date('d/m/Y', strtotime($consultation['date_consultation'])); ?> - 
                                        <?php echo $consultation['prenom'] . ' ' . $consultation['nom']; ?> - 
                                        <?php echo $consultation['motif']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Section upload du document -->
                            <div class="border rounded p-4 mb-4">
                                <h5 class="mb-3">
                                    <i class="fas fa-file-upload me-2"></i>Document à téléverser *
                                </h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">Fichier (PDF, Image, Word)</label>
                                    <input type="file" class="form-control" name="document_file" 
                                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                                    <small class="text-muted">
                                        Formats acceptés : PDF, JPG, PNG, DOC, DOCX (Max 10MB)
                                    </small>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary" onclick="scanDocument()">
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
                                <a href="documents.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Annuler
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer le document
                                </button>
                            </div>
                        </form>
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
        } else if (file.type.includes('document') || file.name.endsWith('.doc') || file.name.endsWith('.docx')) {
            previewImage.src = '../assets/images/word-icon.png';
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    });
    
    // Gestion du formulaire
    document.getElementById('addDocumentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'add');
        formData.append('docteur_id', '<?php echo $docteur_id; ?>');
        
        fetch('../ajax/save_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Document enregistré avec succès!');
                window.location.href = 'documents.php';
            } else {
                alert('Erreur: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue');
        });
    });
    
    // Fonction pour scanner un document
    function scanDocument() {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            // Utiliser la caméra pour scanner
            alert('Fonction de scan à implémenter. Pour l\'instant, veuillez téléverser un fichier.');
        } else {
            alert('Votre navigateur ne supporte pas l\'accès à la caméra.');
        }
    }
    </script>
    
    <?php
    require_once '../includes/footer.php';
    exit;
}

// Si action = add, afficher le formulaire
if ($action === 'add') {
    addDocument();
}

// Gérer l'action d'édition
if ($action === 'edit' && $id) {
    // Code pour éditer un document existant
    // À implémenter si nécessaire
}

require_once '../includes/header.php';

// Compter les documents par type
$stats_stmt = $pdo->prepare("
    SELECT 
        type,
        COUNT(*) as count
    FROM documents_medicaux
    WHERE docteur_id = ?
    GROUP BY type
");
$stats_stmt->execute([$docteur_id]);
$stats = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Récupérer les documents récents - CORRIGÉ : utilisation de la colonne correcte 'created_at'
$documents_stmt = $pdo->prepare("
    SELECT d.*, 
           p.nom as patient_nom, 
           p.prenom as patient_prenom,
           p.date_naissance,
           p.code_patient,
           c.date_consultation,
           c.diagnostic
    FROM documents_medicaux d
    JOIN patients p ON d.patient_id = p.id
    LEFT JOIN consultations c ON d.consultation_id = c.id
    WHERE d.docteur_id = ?
    ORDER BY d.created_at DESC
    LIMIT 15
");
$documents_stmt->execute([$docteur_id]);
$documents = $documents_stmt->fetchAll();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-file-medical me-2"></i>Documents Médicaux
        </h1>
        <p class="text-muted mb-0">
            <span class="fw-semibold">Dr. <?php echo $_SESSION['prenom'] . ' ' . $_SESSION['nom']; ?></span>
        </p>
    </div>
    <div class="btn-toolbar">
        <a href="documents.php?action=add" class="btn btn-sm btn-primary">
            <i class="fas fa-plus-circle me-1"></i>Nouveau document
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
                        <div class="text-muted small">Ordonnances</div>
                        <div class="h4 mb-0"><?php echo $stats['ordonnance'] ?? 0; ?></div>
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
                        <div class="text-muted small">Certificats</div>
                        <div class="h4 mb-0"><?php echo $stats['certificat'] ?? 0; ?></div>
                    </div>
                    <div class="rounded-circle bg-success-light p-3">
                        <i class="fas fa-certificate text-success fa-lg"></i>
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
                        <div class="text-muted small">Comptes rendus</div>
                        <div class="h4 mb-0"><?php echo $stats['compte_rendu'] ?? 0; ?></div>
                    </div>
                    <div class="rounded-circle bg-warning-light p-3">
                        <i class="fas fa-file-alt text-warning fa-lg"></i>
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
                        <div class="text-muted small">Aujourd'hui</div>
                        <?php 
                        $today_stmt = $pdo->prepare("SELECT COUNT(*) FROM documents_medicaux WHERE docteur_id = ? AND DATE(created_at) = CURDATE()");
                        $today_stmt->execute([$docteur_id]);
                        $today_count = $today_stmt->fetchColumn();
                        ?>
                        <div class="h4 mb-0"><?php echo $today_count; ?></div>
                    </div>
                    <div class="rounded-circle bg-info-light p-3">
                        <i class="fas fa-calendar-day text-info fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Barre d'actions -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control" placeholder="Rechercher un document..." 
                           id="searchInput" onkeyup="searchDocuments()">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="typeFilter" onchange="filterByType()">
                    <option value="">Tous les types</option>
                    <option value="ordonnance">Ordonnance</option>
                    <option value="certificat">Certificat</option>
                    <option value="compte_rendu">Compte rendu</option>
                    <option value="resultat_analyse">Résultat d'analyse</option>
                    <option value="imagerie">Imagerie médicale</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control" id="dateFilter" onchange="filterByDate()">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                    <i class="fas fa-redo me-1"></i>Réinitialiser
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Liste des documents -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-folder me-2"></i>
            Derniers documents
        </h5>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                    data-bs-toggle="dropdown">
                <i class="fas fa-cloud-upload-alt me-1"></i>Actions
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="documents.php?action=add">
                    <i class="fas fa-plus me-2"></i>Nouveau document
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="exportDocuments()">
                    <i class="fas fa-download me-2"></i>Exporter la liste
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="printDocuments()">
                    <i class="fas fa-print me-2"></i>Imprimer la liste
                </a></li>
            </ul>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($documents)): ?>
        <div class="text-center py-5">
            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Aucun document trouvé</h5>
            <p class="text-muted small mb-3">Vous n'avez pas encore créé de documents médicaux</p>
            <a href="documents.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Créer un document
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="documentsTable">
                <thead class="table-light">
                    <tr>
                        <th>Type</th>
                        <th>Patient</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Document</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): 
                        $typeIcons = [
                            'ordonnance' => ['fas fa-prescription', 'primary'],
                            'certificat' => ['fas fa-certificate', 'success'],
                            'compte_rendu' => ['fas fa-file-alt', 'warning'],
                            'resultat_analyse' => ['fas fa-vial', 'info'],
                            'imagerie' => ['fas fa-x-ray', 'danger'],
                            'autre' => ['fas fa-file', 'secondary']
                        ];
                        $icon = $typeIcons[$doc['type']] ?? ['fas fa-file', 'secondary'];
                        $age = calculateAge($doc['date_naissance']);
                        $dateCreated = $doc['created_at'] ?? $doc['date_creation'] ?? '';
                    ?>
                    <tr class="document-row" data-type="<?php echo $doc['type']; ?>" 
                        data-date="<?php echo date('Y-m-d', strtotime($dateCreated)); ?>">
                        <td>
                            <span class="badge bg-<?php echo $icon[1]; ?>">
                                <i class="<?php echo $icon[0]; ?> me-1"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $doc['type'])); ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar me-3">
                                    <?php echo strtoupper(substr($doc['patient_prenom'], 0, 1) . substr($doc['patient_nom'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?php echo $doc['patient_prenom'] . ' ' . $doc['patient_nom']; ?></div>
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
                            <?php if ($doc['consultation_id'] && $doc['date_consultation']): ?>
                            <div class="small mt-1">
                                <i class="fas fa-stethoscope me-1"></i>
                                Consultation du <?php echo date('d/m/Y', strtotime($doc['date_consultation'])); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($dateCreated): ?>
                            <div><?php echo date('d/m/Y', strtotime($dateCreated)); ?></div>
                            <small class="text-muted"><?php echo date('H:i', strtotime($dateCreated)); ?></small>
                            <?php endif; ?>
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
                                <?php if ($doc['type'] === 'ordonnance'): ?>
                                <a href="ordonnances.php?action=view&id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-outline-primary" title="Voir">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php elseif ($doc['type'] === 'certificat'): ?>
                                <a href="certificats.php?action=view&id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-outline-primary" title="Voir">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php elseif ($doc['type'] === 'compte_rendu'): ?>
                                <a href="comptes-rendus.php?action=view&id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-outline-primary" title="Voir">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php else: ?>
                                <a href="#" class="btn btn-outline-primary" title="Voir" onclick="viewDocument(<?php echo $doc['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($doc['fichier_path']): ?>
                                <a href="../uploads/<?php echo $doc['fichier_path']; ?>" 
                                   class="btn btn-outline-success" title="Télécharger" download>
                                    <i class="fas fa-download"></i>
                                </a>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline-danger" 
                                        onclick="confirmDelete(<?php echo $doc['id']; ?>)" 
                                        title="Supprimer">
                                    <i class="fas fa-trash"></i>
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
                    Total: <?php echo array_sum($stats); ?> documents
                </small>
            </div>
            <div class="col-md-6 text-end">
                <a href="documents.php?view=all" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-archive me-1"></i>Voir tous les documents
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal de suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer ce document ? Cette action est irréversible.</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Le fichier associé sera également supprimé du serveur.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" onclick="deleteDocument()">Supprimer</button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
let documentToDelete = null;

function searchDocuments() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.document-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
    });
}

function filterByType() {
    const type = document.getElementById('typeFilter').value;
    const rows = document.querySelectorAll('.document-row');
    
    rows.forEach(row => {
        if (!type || row.dataset.type === type) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filterByDate() {
    const date = document.getElementById('dateFilter').value;
    const rows = document.querySelectorAll('.document-row');
    
    rows.forEach(row => {
        if (!date || row.dataset.date === date) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('typeFilter').value = '';
    document.getElementById('dateFilter').value = '';
    
    const rows = document.querySelectorAll('.document-row');
    rows.forEach(row => {
        row.style.display = '';
    });
}

function confirmDelete(docId) {
    documentToDelete = docId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function deleteDocument() {
    if (!documentToDelete) return;
    
    fetch(`../ajax/delete_document.php?id=${documentToDelete}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur lors de la suppression: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erreur lors de la suppression');
    });
}

function viewDocument(docId) {
    // Ouvrir le document dans un nouvel onglet ou modal
    window.open(`view_document.php?id=${docId}`, '_blank');
}

function exportDocuments() {
    window.open('../ajax/export_documents.php', '_blank');
}

function printDocuments() {
    window.print();
}

// Initialiser
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les tooltips
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
    
    // Mettre à jour la date par défaut du filtre
    document.getElementById('dateFilter').value = new Date().toISOString().split('T')[0];
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

.document-row:hover {
    background-color: #f8f9fa;
}

.badge i {
    font-size: 0.8em;
}
</style>