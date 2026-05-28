<?php
// admin/inventaire.php
require_once '../config/database.php';
checkRole('admin');

$title = 'Inventaire des Médicaments';
require_once '../includes/header.php';

// Vérifier si la table existe
try {
    $pdo->query("SELECT 1 FROM medicaments LIMIT 1");
    $table_exists = true;
} catch (PDOException $e) {
    $table_exists = false;
}

// Récupérer les statistiques d'inventaire
$stats = [];
if ($table_exists) {
    try {
        // Total valeur inventaire
        $stmt = $pdo->query("
            SELECT 
                SUM(stock_actuel * prix_unitaire) as valeur_totale,
                SUM(stock_actuel) as total_unites,
                COUNT(*) as total_medicaments,
                SUM(CASE WHEN stock_actuel <= stock_minimum AND stock_actuel > 0 THEN 1 ELSE 0 END) as alertes_stock,
                SUM(CASE WHEN stock_actuel = 0 THEN 1 ELSE 0 END) as ruptures_stock,
                SUM(CASE WHEN DATEDIFF(NOW(), updated_at) > 30 THEN 1 ELSE 0 END) as medicaments_inactifs
            FROM medicaments 
            WHERE statut IN ('actif', 'rupture')
        ");
        $stats = $stmt->fetch();
    } catch (Exception $e) {
        $stats = [];
    }
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 mb-0">
            <i class="fas fa-boxes me-2"></i>Inventaire des Médicaments
        </h1>
        <p class="text-muted mb-0">Gestion complète du stock pharmaceutique</p>
    </div>
    <div class="btn-toolbar">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary" onclick="printInventory()">
                <i class="fas fa-print me-1"></i>Imprimer
            </button>
            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" 
                    data-bs-toggle="dropdown">
                <span class="visually-hidden">Options</span>
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="#" onclick="exportInventory('csv')">
                    <i class="fas fa-file-csv me-2"></i>Exporter en CSV
                </a>
                <a class="dropdown-item" href="#" onclick="exportInventory('pdf')">
                    <i class="fas fa-file-pdf me-2"></i>Exporter en PDF
                </a>
                <a class="dropdown-item" href="#" onclick="exportInventory('excel')">
                    <i class="fas fa-file-excel me-2"></i>Exporter en Excel
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="medicaments.php">
                    <i class="fas fa-pills me-2"></i>Gestion des médicaments
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!$table_exists): ?>
<div class="alert alert-warning">
    <h6><i class="fas fa-exclamation-triangle me-2"></i>Table non initialisée</h6>
    <p>La table des médicaments n'existe pas encore.</p>
    <a href="medicaments.php" class="btn btn-warning btn-sm">
        <i class="fas fa-database me-1"></i>Initialiser la table
    </a>
</div>
<?php endif; ?>

<!-- Statistiques d'inventaire -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-primary border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Valeur totale</div>
                        <div class="h3 mb-0">
                            <?php echo isset($stats['valeur_totale']) ? number_format($stats['valeur_totale'], 2) : '0'; ?>€
                        </div>
                    </div>
                    <div class="rounded-circle bg-primary-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-euro-sign text-primary fa-lg"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-success">
                        <i class="fas fa-chart-line me-1"></i>
                        Total inventaire
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-success border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Unités en stock</div>
                        <div class="h3 mb-0">
                            <?php echo isset($stats['total_unites']) ? number_format($stats['total_unites']) : '0'; ?>
                        </div>
                    </div>
                    <div class="rounded-circle bg-success-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-box text-success fa-lg"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="fas fa-cube me-1"></i>
                        Médicaments: <?php echo $stats['total_medicaments'] ?? 0; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-warning border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Alertes stock</div>
                        <div class="h3 mb-0">
                            <?php echo $stats['alertes_stock'] ?? 0; ?>
                        </div>
                    </div>
                    <div class="rounded-circle bg-warning-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-exclamation-triangle text-warning fa-lg"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-warning">
                        <i class="fas fa-clock me-1"></i>
                        Niveau critique
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-danger border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Ruptures de stock</div>
                        <div class="h3 mb-0">
                            <?php echo $stats['ruptures_stock'] ?? 0; ?>
                        </div>
                    </div>
                    <div class="rounded-circle bg-danger-light d-flex align-items-center justify-content-center" 
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-times-circle text-danger fa-lg"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-danger">
                        <i class="fas fa-ban me-1"></i>
                        Stock épuisé
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres et actions -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Rechercher</label>
                <input type="text" class="form-control" name="search" 
                       placeholder="Nom, code CIP..." 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Statut stock</label>
                <select class="form-select" name="stock_status">
                    <option value="">Tous</option>
                    <option value="normal" <?php echo ($_GET['stock_status'] ?? '') == 'normal' ? 'selected' : ''; ?>>
                        Stock normal
                    </option>
                    <option value="low" <?php echo ($_GET['stock_status'] ?? '') == 'low' ? 'selected' : ''; ?>>
                        Stock faible
                    </option>
                    <option value="critical" <?php echo ($_GET['stock_status'] ?? '') == 'critical' ? 'selected' : ''; ?>>
                        Stock critique
                    </option>
                    <option value="out" <?php echo ($_GET['stock_status'] ?? '') == 'out' ? 'selected' : ''; ?>>
                        Rupture
                    </option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Tri par</label>
                <select class="form-select" name="sort">
                    <option value="nom" <?php echo ($_GET['sort'] ?? '') == 'nom' ? 'selected' : ''; ?>>Nom</option>
                    <option value="stock" <?php echo ($_GET['sort'] ?? '') == 'stock' ? 'selected' : ''; ?>>Stock</option>
                    <option value="valeur" <?php echo ($_GET['sort'] ?? '') == 'valeur' ? 'selected' : ''; ?>>Valeur</option>
                    <option value="date" <?php echo ($_GET['sort'] ?? '') == 'date' ? 'selected' : ''; ?>>Dernière modif</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Ordre</label>
                <select class="form-select" name="order">
                    <option value="asc" <?php echo ($_GET['order'] ?? '') == 'asc' ? 'selected' : ''; ?>>Croissant</option>
                    <option value="desc" <?php echo ($_GET['order'] ?? '') == 'desc' ? 'selected' : ''; ?>>Décroissant</option>
                </select>
            </div>
            
            <div class="col-md-12">
                <div class="d-flex justify-content-between">
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i>Filtrer
                        </button>
                        <a href="inventaire.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-times me-1"></i>Réinitialiser
                        </a>
                    </div>
                    <div>
                        <button type="button" class="btn btn-success" onclick="runInventoryCheck()">
                            <i class="fas fa-sync-alt me-1"></i>Vérifier inventaire
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tableau d'inventaire -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Inventaire détaillé</h6>
        <div>
            <button class="btn btn-sm btn-outline-primary me-2" onclick="exportInventoryReport()">
                <i class="fas fa-download me-1"></i>Rapport
            </button>
            <span class="badge bg-info" id="inventoryCount">0 médicaments</span>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="inventoryTable">
                <thead class="table-light">
                    <tr>
                        <th>Code CIP</th>
                        <th>Médicament</th>
                        <th>Forme</th>
                        <th>Stock</th>
                        <th>Niveau</th>
                        <th>Valeur unitaire</th>
                        <th>Valeur totale</th>
                        <th>Dernière modif</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Initialiser les variables
                    $medicaments = [];
                    $total_valeur = 0;
                    $total_stock = 0;
                    
                    if ($table_exists):
                        // Construire la requête
                        $sql = "SELECT 
                            m.*,
                            (m.stock_actuel * m.prix_unitaire) as valeur_totale,
                            CASE 
                                WHEN m.stock_actuel = 0 THEN 'rupture'
                                WHEN m.stock_actuel <= m.stock_minimum * 0.3 THEN 'critique'
                                WHEN m.stock_actuel <= m.stock_minimum THEN 'faible'
                                ELSE 'normal'
                            END as niveau_stock
                        FROM medicaments m 
                        WHERE m.statut IN ('actif', 'rupture')";
                        
                        $params = [];
                        
                        $search = $_GET['search'] ?? '';
                        $stock_status = $_GET['stock_status'] ?? '';
                        $sort = $_GET['sort'] ?? 'nom';
                        $order = $_GET['order'] ?? 'asc';
                        
                        if ($search) {
                            $sql .= " AND (m.nom_commercial LIKE ? OR m.nom_generique LIKE ? OR m.code_cip LIKE ?)";
                            $search_term = "%$search%";
                            $params = array_merge($params, [$search_term, $search_term, $search_term]);
                        }
                        
                        if ($stock_status) {
                            switch ($stock_status) {
                                case 'normal':
                                    $sql .= " AND m.stock_actuel > m.stock_minimum";
                                    break;
                                case 'low':
                                    $sql .= " AND m.stock_actuel <= m.stock_minimum AND m.stock_actuel > 0";
                                    break;
                                case 'critical':
                                    $sql .= " AND m.stock_actuel <= m.stock_minimum * 0.3 AND m.stock_actuel > 0";
                                    break;
                                case 'out':
                                    $sql .= " AND m.stock_actuel = 0";
                                    break;
                            }
                        }
                        
                        // Tri
                        $sort_fields = [
                            'nom' => 'm.nom_commercial',
                            'stock' => 'm.stock_actuel',
                            'valeur' => 'valeur_totale',
                            'date' => 'm.updated_at'
                        ];
                        
                        $sort_field = $sort_fields[$sort] ?? 'm.nom_commercial';
                        $sql .= " ORDER BY $sort_field " . ($order === 'desc' ? 'DESC' : 'ASC');
                        
                        try {
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);
                            $medicaments = $stmt->fetchAll();
                            
                            // Réinitialiser les totaux
                            $total_valeur = 0;
                            $total_stock = 0;
                            
                            // Afficher les médicaments
                            if (!empty($medicaments)):
                                foreach ($medicaments as $med):
                                    $valeur_totale = $med['stock_actuel'] * $med['prix_unitaire'];
                                    $total_valeur += $valeur_totale;
                                    $total_stock += $med['stock_actuel'];
                                    
                                    // Déterminer le niveau de stock
                                    $niveau_class = 'success';
                                    $niveau_text = 'Normal';
                                    
                                    if ($med['stock_actuel'] == 0) {
                                        $niveau_class = 'danger';
                                        $niveau_text = 'Rupture';
                                    } elseif ($med['stock_actuel'] <= $med['stock_minimum'] * 0.3) {
                                        $niveau_class = 'danger';
                                        $niveau_text = 'Critique';
                                    } elseif ($med['stock_actuel'] <= $med['stock_minimum']) {
                                        $niveau_class = 'warning';
                                        $niveau_text = 'Faible';
                                    }
                                    
                                    // Calculer le pourcentage
                                    $pourcentage = $med['stock_minimum'] > 0 ? 
                                        min(100, ($med['stock_actuel'] / $med['stock_minimum']) * 100) : 100;
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($med['code_cip'] ?? 'N/A'); ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($med['nom_commercial']); ?></div>
                                <?php if ($med['nom_generique']): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($med['nom_generique']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($med['forme']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($med['dosage'] ?? ''); ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo $med['stock_actuel']; ?></div>
                                <small class="text-muted">Min: <?php echo $med['stock_minimum']; ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $niveau_class; ?>">
                                    <?php echo $niveau_text; ?>
                                </span>
                                <div class="progress mt-1" style="height: 4px;">
                                    <div class="progress-bar bg-<?php echo $niveau_class; ?>" 
                                         style="width: <?php echo $pourcentage; ?>%"></div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo number_format($med['prix_unitaire'], 2); ?>€</div>
                            </td>
                            <td>
                                <div class="fw-semibold text-primary"><?php echo number_format($valeur_totale, 2); ?>€</div>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y', strtotime($med['updated_at'])); ?>
                                </small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="medicaments.php?action=edit&id=<?php echo $med['id']; ?>" 
                                       class="btn btn-outline-primary" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-warning" 
                                            onclick="adjustStock(<?php echo $med['id']; ?>)" title="Ajuster stock">
                                        <i class="fas fa-box"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-info" 
                                            onclick="viewStockHistory(<?php echo $med['id']; ?>)" title="Historique">
                                        <i class="fas fa-history"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php 
                                endforeach; 
                            endif;
                        } catch (Exception $e) {
                            echo '<tr><td colspan="9" class="text-center text-danger">Erreur: ' . $e->getMessage() . '</td></tr>';
                        }
                    endif; // Fin du if ($table_exists)
                    
                    // Afficher le message si pas de médicaments
                    if (empty($medicaments)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-boxes fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">Aucun médicament en inventaire</h6>
                                <p class="text-muted small">Commencez par ajouter des médicaments</p>
                                <a href="medicaments.php?action=add" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i>Ajouter un médicament
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Total (uniquement si on a des médicaments) -->
                    <?php if (!empty($medicaments)): ?>
                    <tr class="table-light">
                        <td colspan="3" class="text-end fw-bold">TOTAL INVENTAIRE:</td>
                        <td class="fw-bold"><?php echo number_format($total_stock); ?></td>
                        <td></td>
                        <td></td>
                        <td class="fw-bold text-primary"><?php echo number_format($total_valeur, 2); ?>€</td>
                        <td></td>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if (!empty($medicaments)): ?>
    <div class="card-footer bg-white border-top">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <small class="text-muted">
                    <?php echo count($medicaments); ?> médicament(s) | 
                    Valeur totale: <?php echo number_format($total_valeur, 2); ?>€
                </small>
            </div>
            <div>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="prevPage()">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="btn btn-outline-secondary active">1</button>
                    <button class="btn btn-outline-secondary" onclick="nextPage()">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Rapports et graphiques -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Répartition par niveau de stock
                </h6>
            </div>
            <div class="card-body">
                <canvas id="stockDistributionChart" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Top 10 médicaments par valeur
                </h6>
            </div>
            <div class="card-body">
                <div id="topMedicamentsChart"></div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Médicament</th>
                                <th>Valeur totale</th>
                            </tr>
                        </thead>
                        <tbody id="topMedicamentsList">
                            <!-- Chargé via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Historique Stock -->
<div class="modal fade" id="stockHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Historique des mouvements de stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="stockHistoryContent">
                <!-- Chargé via AJAX -->
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<style>
.progress {
    background-color: #e9ecef;
    border-radius: 2px;
}

.progress-bar {
    border-radius: 2px;
}

.inventory-total {
    background-color: #f8f9fa;
    border-top: 2px solid #dee2e6;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.card {
    border: 1px solid rgba(0,0,0,.125);
    border-radius: 10px;
}

.table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.05em;
    color: #6b7280;
}

.bg-primary-light {
    background-color: rgba(67, 97, 238, 0.1);
}

.bg-success-light {
    background-color: rgba(40, 167, 69, 0.1);
}

.bg-warning-light {
    background-color: rgba(255, 193, 7, 0.1);
}

.bg-danger-light {
    background-color: rgba(220, 53, 69, 0.1);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Mettre à jour le compteur
document.addEventListener('DOMContentLoaded', function() {
    const medicamentCount = <?php echo isset($medicaments) ? count($medicaments) : 0; ?>;
    document.getElementById('inventoryCount').textContent = medicamentCount + ' médicament(s)';
    
    // Initialiser les graphiques
    initStockDistributionChart();
    loadTopMedicaments();
});

// Graphique de répartition du stock
function initStockDistributionChart() {
    const ctx = document.getElementById('stockDistributionChart').getContext('2d');
    
    // Données statiques (à remplacer par des données réelles)
    const data = {
        labels: ['Normal', 'Faible', 'Critique', 'Rupture'],
        datasets: [{
            data: [65, 20, 10, 5],
            backgroundColor: [
                '#28a745',
                '#ffc107',
                '#fd7e14',
                '#dc3545'
            ],
            borderWidth: 1
        }]
    };
    
    new Chart(ctx, {
        type: 'doughnut',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed + '%';
                        }
                    }
                }
            }
        }
    });
}

// Charger les top médicaments
function loadTopMedicaments() {
    fetch('ajax/get_top_medicaments.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tbody = document.getElementById('topMedicamentsList');
                tbody.innerHTML = '';
                
                data.medicaments.forEach((med, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${med.nom_commercial}</td>
                        <td class="fw-semibold text-primary">${parseFloat(med.valeur_totale).toFixed(2)}€</td>
                    `;
                    tbody.appendChild(row);
                });
            }
        })
        .catch(error => console.error('Erreur:', error));
}

// Vérifier l'inventaire
function runInventoryCheck() {
    showToast('Vérification de l\'inventaire en cours...', 'info');
    
    fetch('ajax/run_inventory_check.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Erreur: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            showToast('Erreur de connexion: ' + error.message, 'danger');
        });
}

// Ajuster le stock
function adjustStock(medicamentId) {
    // Rediriger vers la page d'ajustement
    window.location.href = `medicaments.php?action=edit&id=${medicamentId}`;
}

// Voir l'historique du stock
function viewStockHistory(medicamentId) {
    fetch(`ajax/get_stock_history.php?id=${medicamentId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('stockHistoryContent').innerHTML = data;
            new bootstrap.Modal(document.getElementById('stockHistoryModal')).show();
        })
        .catch(error => {
            document.getElementById('stockHistoryContent').innerHTML = `
                <div class="alert alert-danger">
                    <p>Erreur lors du chargement de l'historique: ${error.message}</p>
                </div>
            `;
            new bootstrap.Modal(document.getElementById('stockHistoryModal')).show();
        });
}

// Imprimer l'inventaire
function printInventory() {
    const printContent = document.getElementById('inventoryTable').outerHTML;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Inventaire des Médicaments</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f8f9fa; font-weight: bold; }
                .total-row { background-color: #f8f9fa; font-weight: bold; }
                .badge { padding: 2px 6px; border-radius: 3px; font-size: 12px; }
                .badge-success { background-color: #28a745; color: white; }
                .badge-warning { background-color: #ffc107; color: #212529; }
                .badge-danger { background-color: #dc3545; color: white; }
                h1 { color: #4361ee; margin-bottom: 20px; }
                .header { display: flex; justify-content: space-between; margin-bottom: 20px; }
                .date { color: #6c757d; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Inventaire des Médicaments</h1>
                <div class="date">${new Date().toLocaleDateString('fr-FR')}</div>
            </div>
            ${printContent}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

// Exporter l'inventaire
function exportInventory(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('format', format);
    
    showToast('Génération du fichier...', 'info');
    window.open(`export_inventory.php?${params.toString()}`, '_blank');
}

// Exporter le rapport
function exportInventoryReport() {
    showToast('Génération du rapport...', 'info');
    window.open('export_inventory_report.php', '_blank');
}

// Pagination
function prevPage() {
    showToast('Page précédente', 'info');
    // Implémenter la logique de pagination
}

function nextPage() {
    showToast('Page suivante', 'info');
    // Implémenter la logique de pagination
}

// Afficher un toast
function showToast(message, type = 'info') {
    // Supprimer les toasts existants
    const existingToasts = document.querySelectorAll('.toast');
    existingToasts.forEach(toast => toast.remove());
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    const container = document.getElementById('toastContainer') || createToastContainer();
    container.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}
</script>