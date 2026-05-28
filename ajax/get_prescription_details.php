<?php
// ajax/get_prescription_details.php
require_once '../config/database.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT p.*,
           pat.nom as patient_nom, pat.prenom as patient_prenom, 
           pat.date_naissance as patient_date_naissance, pat.telephone as patient_telephone,
           doc.nom as docteur_nom, doc.prenom as docteur_prenom, doc.specialite as docteur_specialite,
           creator.prenom as creator_prenom, creator.nom as creator_nom
    FROM prescriptions p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN utilisateurs doc ON p.docteur_id = doc.id
    LEFT JOIN utilisateurs creator ON p.created_by = creator.id
    WHERE p.id = ?
");

$stmt->execute([$id]);
$prescription = $stmt->fetch();

if (!$prescription) {
    echo '<div class="alert alert-danger">Prescription non trouvée</div>';
    exit();
}

$statusColor = $prescription['statut'] == 'active' ? 'success' : 
             ($prescription['statut'] == 'completed' ? 'primary' : 
             ($prescription['statut'] == 'cancelled' ? 'danger' : 'warning'));
?>

<div class="prescription-details">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h5 class="mb-1">Prescription #<?php echo str_pad($prescription['id'], 4, '0', STR_PAD_LEFT); ?></h5>
            <p class="text-muted mb-0">
                <?php echo date('d/m/Y à H:i', strtotime($prescription['date_prescription'])); ?>
            </p>
        </div>
        <div class="col-md-4 text-end">
            <span class="badge bg-<?php echo $statusColor; ?> fs-6">
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
        </div>
    </div>

    <!-- Informations patient et médecin -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-user-injured me-2"></i>Patient</h6>
                </div>
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($prescription['patient_prenom'] . ' ' . $prescription['patient_nom']); ?></h5>
                    <div class="mb-2">
                        <strong>Date de naissance:</strong><br>
                        <?php echo date('d/m/Y', strtotime($prescription['patient_date_naissance'])); ?>
                        (<?php echo date_diff(date_create($prescription['patient_date_naissance']), date_create('today'))->y; ?> ans)
                    </div>
                    <?php if ($prescription['patient_telephone']): ?>
                    <div class="mb-2">
                        <strong>Téléphone:</strong><br>
                        <?php echo htmlspecialchars($prescription['patient_telephone']); ?>
                    </div>
                    <?php endif; ?>
                    <div class="mb-0">
                        <strong>ID Patient:</strong> <?php echo $prescription['patient_id']; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-user-md me-2"></i>Médecin prescripteur</h6>
                </div>
                <div class="card-body">
                    <h5 class="card-title">Dr. <?php echo htmlspecialchars($prescription['docteur_prenom'] . ' ' . $prescription['docteur_nom']); ?></h5>
                    <?php if ($prescription['docteur_specialite']): ?>
                    <div class="mb-3">
                        <span class="badge bg-primary"><?php echo htmlspecialchars($prescription['docteur_specialite']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="mb-0">
                        <strong>ID Médecin:</strong> <?php echo $prescription['docteur_id']; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Détails de la prescription -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-capsules me-2"></i>Médicaments prescrits</h6>
                </div>
                <div class="card-body">
                    <div class="prescription-content">
                        <?php 
                        $medicaments = array_filter(array_map('trim', explode(';', $prescription['medicaments'])));
                        if (!empty($medicaments)):
                        ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($medicaments as $medicament): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($medicament); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p class="text-muted mb-0">Aucun médicament spécifié</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Posologie et instructions -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Posologie</h6>
                </div>
                <div class="card-body">
                    <?php if ($prescription['posologie']): ?>
                    <div class="posologie-content">
                        <?php echo nl2br(htmlspecialchars($prescription['posologie'])); ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted mb-0">Aucune posologie spécifiée</p>
                    <?php endif; ?>
                    
                    <?php if ($prescription['duree_traitement']): ?>
                    <div class="mt-3 pt-3 border-top">
                        <strong>Durée du traitement:</strong><br>
                        <?php echo htmlspecialchars($prescription['duree_traitement']); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Instructions spéciales</h6>
                </div>
                <div class="card-body">
                    <?php if ($prescription['instructions']): ?>
                    <div class="instructions-content">
                        <?php echo nl2br(htmlspecialchars($prescription['instructions'])); ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted mb-0">Aucune instruction spécifique</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Informations système -->
    <div class="row">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-history me-2"></i>Historique
                    </h6>
                    <div class="row small">
                        <div class="col-md-4">
                            <strong>Créée le:</strong><br>
                            <?php echo date('d/m/Y à H:i', strtotime($prescription['created_at'])); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Créée par:</strong><br>
                            <?php echo htmlspecialchars($prescription['creator_prenom'] . ' ' . $prescription['creator_nom']); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Dernière modification:</strong><br>
                            <?php echo !empty($prescription['updated_at']) ? date('d/m/Y à H:i', strtotime($prescription['updated_at'])) : 'Jamais'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.prescription-details .card {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
}

.prescription-details .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e5e7eb;
    padding: 0.75rem 1rem;
}

.prescription-details .card-body {
    padding: 1rem;
}

.prescription-content ul {
    margin-bottom: 0;
}

.list-group-item {
    border-left: none;
    border-right: none;
    padding: 0.75rem 0;
}

.list-group-item:first-child {
    border-top: none;
}

.list-group-item:last-child {
    border-bottom: none;
}

.badge {
    font-size: 0.875em;
    padding: 0.5em 0.75em;
}

.posologie-content, .instructions-content {
    white-space: pre-line;
}
</style>