<?php
// assistant/materiel.php
require_once '../config/database.php';
checkRole('assistant');

// Déterminer la page active
$page = $_GET['page'] ?? 'materiel';
$valid_pages = ['materiel', 'stock', 'equipement'];
if (!in_array($page, $valid_pages)) {
    $page = 'materiel';
}

$title = getPageTitle($page);

// Fonction pour obtenir le titre de la page
function getPageTitle($page) {
    $titles = [
        'materiel' => 'Matériel Stérile',
        'stock' => 'Gestion des Médicaments',
        'equipement' => 'Gestion des Équipements'
    ];
    return $titles[$page] ?? 'Gestion du Matériel';
}

// Variables pour les messages
$success_message = '';
$error_message = '';

// ========== TRAITEMENT DES FORMULAIRES ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        
        // ========== GESTION MATÉRIEL STÉRILE ==========
        case 'add_materiel':
            $nom = cleanInput($_POST['nom']);
            $type = cleanInput($_POST['type']);
            $reference = cleanInput($_POST['reference'] ?? '');
            $quantite = $_POST['quantite'] ?? 1;
            $quantite_min = $_POST['quantite_min'] ?? 10;
            $date_reception = $_POST['date_reception'] ?? date('Y-m-d');
            $date_peremption = $_POST['date_peremption'] ?? null;
            $fournisseur = cleanInput($_POST['fournisseur'] ?? '');
            $localisation = cleanInput($_POST['localisation'] ?? 'Stock principal');
            $notes = cleanInput($_POST['notes'] ?? '');
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO materiel_sterile 
                    (nom, type, reference, quantite, quantite_min, date_reception, date_peremption, 
                     fournisseur, localisation, notes, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $nom, $type, $reference, $quantite, $quantite_min, $date_reception, $date_peremption,
                    $fournisseur, $localisation, $notes, $_SESSION['user_id']
                ]);
                
                $success_message = "Matériel stérile ajouté avec succès !";
                $page = 'materiel';
                
            } catch (PDOException $e) {
                error_log("Erreur ajout matériel: " . $e->getMessage());
                $error_message = "Erreur lors de l'ajout du matériel.";
            }
            break;
            
        case 'edit_materiel':
            $id = $_POST['materiel_id'];
            $nom = cleanInput($_POST['nom']);
            $type = cleanInput($_POST['type']);
            $reference = cleanInput($_POST['reference'] ?? '');
            $quantite = $_POST['quantite'] ?? 1;
            $quantite_min = $_POST['quantite_min'] ?? 10;
            $date_reception = $_POST['date_reception'] ?? date('Y-m-d');
            $date_peremption = $_POST['date_peremption'] ?? null;
            $fournisseur = cleanInput($_POST['fournisseur'] ?? '');
            $localisation = cleanInput($_POST['localisation'] ?? 'Stock principal');
            $notes = cleanInput($_POST['notes'] ?? '');
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE materiel_sterile 
                    SET nom = ?, type = ?, reference = ?, quantite = ?, quantite_min = ?, 
                        date_reception = ?, date_peremption = ?, fournisseur = ?, 
                        localisation = ?, notes = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $nom, $type, $reference, $quantite, $quantite_min, $date_reception, $date_peremption,
                    $fournisseur, $localisation, $notes, $id
                ]);
                
                $success_message = "Matériel stérile modifié avec succès !";
                $page = 'materiel';
                
            } catch (PDOException $e) {
                error_log("Erreur modification matériel: " . $e->getMessage());
                $error_message = "Erreur lors de la modification du matériel.";
            }
            break;
            
        case 'delete_materiel':
            $id = $_POST['materiel_id'];
            
            try {
                $stmt = $pdo->prepare("DELETE FROM materiel_sterile WHERE id = ?");
                $stmt->execute([$id]);
                
                $success_message = "Matériel stérile supprimé avec succès !";
                $page = 'materiel';
                
            } catch (PDOException $e) {
                error_log("Erreur suppression matériel: " . $e->getMessage());
                $error_message = "Erreur lors de la suppression du matériel.";
            }
            break;
            
        case 'use_materiel':
            $materiel_id = $_POST['materiel_id'];
            $quantite_utilisee = $_POST['quantite_utilisee'];
            $patient_id = $_POST['patient_id'] ?? null;
            $consultation_id = $_POST['consultation_id'] ?? null;
            $motif = cleanInput($_POST['motif'] ?? 'Utilisation normale');
            
            try {
                // Récupérer la quantité actuelle
                $stockStmt = $pdo->prepare("SELECT quantite FROM materiel_sterile WHERE id = ?");
                $stockStmt->execute([$materiel_id]);
                $current_quantite = $stockStmt->fetchColumn();
                
                // Vérifier si quantité suffisante
                if ($current_quantite < $quantite_utilisee) {
                    $error_message = "Quantité insuffisante. Stock actuel: $current_quantite";
                    break;
                }
                
                // Mettre à jour le stock
                $new_quantite = $current_quantite - $quantite_utilisee;
                $updateStmt = $pdo->prepare("UPDATE materiel_sterile SET quantite = ? WHERE id = ?");
                $updateStmt->execute([$new_quantite, $materiel_id]);
                
                // Enregistrer l'utilisation
                $usageStmt = $pdo->prepare("
                    INSERT INTO materiel_usage 
                    (materiel_id, patient_id, consultation_id, quantite, motif, used_by, used_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $usageStmt->execute([
                    $materiel_id, $patient_id, $consultation_id, $quantite_utilisee, 
                    $motif, $_SESSION['user_id']
                ]);
                
                $success_message = "Utilisation enregistrée avec succès !";
                $page = 'materiel';
                
            } catch (PDOException $e) {
                error_log("Erreur utilisation matériel: " . $e->getMessage());
                $error_message = "Erreur lors de l'enregistrement de l'utilisation.";
            }
            break;
            
        // ========== GESTION DES MÉDICAMENTS ==========
        case 'stock_in':
            $medicament_id = $_POST['medicament_id'];
            $quantite = $_POST['quantite'];
            $lot = cleanInput($_POST['lot'] ?? '');
            $date_peremption = $_POST['date_peremption'] ?? null;
            $fournisseur = cleanInput($_POST['fournisseur'] ?? '');
            $numero_facture = cleanInput($_POST['numero_facture'] ?? '');
            $notes = cleanInput($_POST['notes'] ?? '');
            
            try {
                // Récupérer le stock actuel
                $stockStmt = $pdo->prepare("SELECT stock_actuel FROM medicaments WHERE id = ?");
                $stockStmt->execute([$medicament_id]);
                $current_stock = $stockStmt->fetchColumn();
                
                // Calculer le nouveau stock
                $new_stock = $current_stock + $quantite;
                
                // Mettre à jour le stock
                $updateStmt = $pdo->prepare("UPDATE medicaments SET stock_actuel = ? WHERE id = ?");
                $updateStmt->execute([$new_stock, $medicament_id]);
                
                // Enregistrer le mouvement
                $movementStmt = $pdo->prepare("
                    INSERT INTO mouvements_stock 
                    (medicament_id, type_mouvement, quantite, quantite_avant, quantite_apres, motif, created_by, created_at) 
                    VALUES (?, 'entree', ?, ?, ?, ?, ?, NOW())
                ");
                
                $motif = "Entrée de stock" . ($lot ? " (Lot: $lot)" : "") . ($fournisseur ? " - Fournisseur: $fournisseur" : "");
                $movementStmt->execute([
                    $medicament_id, $quantite, $current_stock, $new_stock, $motif, $_SESSION['user_id']
                ]);
                
                // Si date de péremption fournie, l'enregistrer
                if ($date_peremption) {
                    $peremptionStmt = $pdo->prepare("
                        INSERT INTO stock_peremption 
                        (medicament_id, lot, date_peremption, quantite, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $peremptionStmt->execute([$medicament_id, $lot, $date_peremption, $quantite]);
                }
                
                $success_message = "Entrée de stock enregistrée avec succès !";
                $page = 'stock';
                
            } catch (PDOException $e) {
                error_log("Erreur entrée stock: " . $e->getMessage());
                $error_message = "Erreur lors de l'entrée de stock.";
            }
            break;
            
        case 'stock_out':
            $medicament_id = $_POST['medicament_id'];
            $quantite = $_POST['quantite'];
            $motif = cleanInput($_POST['motif'] ?? 'Sortie de stock');
            $patient_id = $_POST['patient_id'] ?? null;
            $consultation_id = $_POST['consultation_id'] ?? null;
            
            try {
                // Récupérer le stock actuel
                $stockStmt = $pdo->prepare("SELECT stock_actuel FROM medicaments WHERE id = ?");
                $stockStmt->execute([$medicament_id]);
                $current_stock = $stockStmt->fetchColumn();
                
                // Vérifier si stock suffisant
                if ($current_stock < $quantite) {
                    $error_message = "Stock insuffisant. Stock actuel: $current_stock";
                    break;
                }
                
                // Calculer le nouveau stock
                $new_stock = $current_stock - $quantite;
                
                // Mettre à jour le stock
                $updateStmt = $pdo->prepare("UPDATE medicaments SET stock_actuel = ? WHERE id = ?");
                $updateStmt->execute([$new_stock, $medicament_id]);
                
                // Enregistrer le mouvement
                $movementStmt = $pdo->prepare("
                    INSERT INTO mouvements_stock 
                    (medicament_id, type_mouvement, quantite, quantite_avant, quantite_apres, motif, created_by, created_at) 
                    VALUES (?, 'sortie', ?, ?, ?, ?, ?, NOW())
                ");
                
                $movementStmt->execute([
                    $medicament_id, $quantite, $current_stock, $new_stock, $motif, $_SESSION['user_id']
                ]);
                
                // Si lié à un patient, enregistrer dans la distribution
                if ($patient_id) {
                    $distributionStmt = $pdo->prepare("
                        INSERT INTO medicament_distribution 
                        (medicament_id, patient_id, consultation_id, quantite, date_distribution, distributed_by) 
                        VALUES (?, ?, ?, ?, NOW(), ?)
                    ");
                    $distributionStmt->execute([
                        $medicament_id, $patient_id, $consultation_id, $quantite, $_SESSION['user_id']
                    ]);
                }
                
                $success_message = "Sortie de stock enregistrée avec succès !";
                $page = 'stock';
                
            } catch (PDOException $e) {
                error_log("Erreur sortie stock: " . $e->getMessage());
                $error_message = "Erreur lors de la sortie de stock.";
            }
            break;
            
        case 'stock_adjust':
            $medicament_id = $_POST['medicament_id'];
            $new_quantity = $_POST['new_quantity'];
            $reason = cleanInput($_POST['reason'] ?? 'Ajustement de stock');
            
            try {
                // Récupérer le stock actuel
                $stockStmt = $pdo->prepare("SELECT stock_actuel FROM medicaments WHERE id = ?");
                $stockStmt->execute([$medicament_id]);
                $current_stock = $stockStmt->fetchColumn();
                
                // Mettre à jour le stock
                $updateStmt = $pdo->prepare("UPDATE medicaments SET stock_actuel = ? WHERE id = ?");
                $updateStmt->execute([$new_quantity, $medicament_id]);
                
                // Enregistrer le mouvement
                $movementStmt = $pdo->prepare("
                    INSERT INTO mouvements_stock 
                    (medicament_id, type_mouvement, quantite, quantite_avant, quantite_apres, motif, created_by, created_at) 
                    VALUES (?, 'ajustement', ?, ?, ?, ?, ?, NOW())
                ");
                
                $difference = $new_quantity - $current_stock;
                $movementStmt->execute([
                    $medicament_id, abs($difference), $current_stock, $new_quantity, $reason, $_SESSION['user_id']
                ]);
                
                $success_message = "Ajustement de stock enregistré avec succès !";
                $page = 'stock';
                
            } catch (PDOException $e) {
                error_log("Erreur ajustement stock: " . $e->getMessage());
                $error_message = "Erreur lors de l'ajustement de stock.";
            }
            break;
            
        // ========== GESTION DES ÉQUIPEMENTS ==========
        case 'add_equipment':
            $nom = cleanInput($_POST['nom']);
            $categorie = cleanInput($_POST['categorie']);
            $marque = cleanInput($_POST['marque'] ?? '');
            $modele = cleanInput($_POST['modele'] ?? '');
            $numero_serie = cleanInput($_POST['numero_serie'] ?? '');
            $date_acquisition = $_POST['date_acquisition'] ?? date('Y-m-d');
            $valeur = $_POST['valeur'] ?? 0;
            $localisation = cleanInput($_POST['localisation'] ?? '');
            $statut = cleanInput($_POST['statut'] ?? 'actif');
            $notes = cleanInput($_POST['notes'] ?? '');
            
            try {
                // Vérifier si le numéro de série existe déjà
                if ($numero_serie) {
                    $checkStmt = $pdo->prepare("SELECT id FROM equipment WHERE numero_serie = ?");
                    $checkStmt->execute([$numero_serie]);
                    if ($checkStmt->rowCount() > 0) {
                        $error_message = "Un équipement avec ce numéro de série existe déjà.";
                        break;
                    }
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO equipment 
                    (nom, categorie, marque, modele, numero_serie, date_acquisition, valeur, localisation, statut, notes, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $nom, $categorie, $marque, $modele, $numero_serie, 
                    $date_acquisition, $valeur, $localisation, $statut, $notes,
                    $_SESSION['user_id']
                ]);
                
                $equipment_id = $pdo->lastInsertId();
                
                // Créer une entrée dans l'historique
                $historyStmt = $pdo->prepare("
                    INSERT INTO equipment_history 
                    (equipment_id, action, details, performed_by, performed_at) 
                    VALUES (?, 'Ajout', ?, ?, NOW())
                ");
                $historyStmt->execute([
                    $equipment_id,
                    "Équipement ajouté: $nom ($categorie)",
                    $_SESSION['user_id']
                ]);
                
                $success_message = "Équipement ajouté avec succès !";
                $page = 'equipement';
                
            } catch (PDOException $e) {
                error_log("Erreur ajout équipement: " . $e->getMessage());
                $error_message = "Erreur lors de l'ajout de l'équipement.";
            }
            break;
            
        case 'edit_equipment':
            $equipment_id = $_POST['equipment_id'];
            $nom = cleanInput($_POST['nom']);
            $categorie = cleanInput($_POST['categorie']);
            $marque = cleanInput($_POST['marque'] ?? '');
            $modele = cleanInput($_POST['modele'] ?? '');
            $numero_serie = cleanInput($_POST['numero_serie'] ?? '');
            $date_acquisition = $_POST['date_acquisition'] ?? date('Y-m-d');
            $valeur = $_POST['valeur'] ?? 0;
            $localisation = cleanInput($_POST['localisation'] ?? '');
            $statut = cleanInput($_POST['statut'] ?? 'actif');
            $notes = cleanInput($_POST['notes'] ?? '');
            
            try {
                // Récupérer les anciennes valeurs
                $oldStmt = $pdo->prepare("SELECT * FROM equipment WHERE id = ?");
                $oldStmt->execute([$equipment_id]);
                $oldData = $oldStmt->fetch();
                
                $stmt = $pdo->prepare("
                    UPDATE equipment 
                    SET nom = ?, categorie = ?, marque = ?, modele = ?, numero_serie = ?, 
                        date_acquisition = ?, valeur = ?, localisation = ?, statut = ?, 
                        notes = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $nom, $categorie, $marque, $modele, $numero_serie, 
                    $date_acquisition, $valeur, $localisation, $statut, $notes,
                    $equipment_id
                ]);
                
                // Créer une entrée dans l'historique
                $historyStmt = $pdo->prepare("
                    INSERT INTO equipment_history 
                    (equipment_id, action, details, performed_by, performed_at) 
                    VALUES (?, 'Modification', ?, ?, NOW())
                ");
                $historyStmt->execute([
                    $equipment_id,
                    "Modification de l'équipement: $nom",
                    $_SESSION['user_id']
                ]);
                
                $success_message = "Équipement modifié avec succès !";
                $page = 'equipement';
                
            } catch (PDOException $e) {
                error_log("Erreur modification équipement: " . $e->getMessage());
                $error_message = "Erreur lors de la modification de l'équipement.";
            }
            break;
            
        case 'delete_equipment':
            $equipment_id = $_POST['equipment_id'];
            
            try {
                // Marquer comme supprimé plutôt que supprimer physiquement
                $stmt = $pdo->prepare("UPDATE equipment SET statut = 'supprime', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$equipment_id]);
                
                $success_message = "Équipement supprimé avec succès !";
                $page = 'equipement';
                
            } catch (PDOException $e) {
                error_log("Erreur suppression équipement: " . $e->getMessage());
                $error_message = "Erreur lors de la suppression de l'équipement.";
            }
            break;
    }
}

// ========== RÉCUPÉRATION DES DONNÉES ==========

// Matériel stérile
try {
    $materielStmt = $pdo->query("
        SELECT ms.*, 
               CONCAT(u.prenom, ' ', u.nom) as created_by_name,
               DATEDIFF(ms.date_peremption, CURDATE()) as jours_peremption
        FROM materiel_sterile ms
        LEFT JOIN utilisateurs u ON ms.created_by = u.id
        WHERE ms.quantite > 0
        ORDER BY ms.date_peremption ASC, ms.quantite ASC
    ");
    $materiel_sterile = $materielStmt->fetchAll();
} catch (Exception $e) {
    $materiel_sterile = [];
    error_log("Erreur récupération matériel: " . $e->getMessage());
}

// Médicaments avec stock bas
try {
    $lowStockStmt = $pdo->query("
        SELECT m.*, 
               (m.stock_actuel / m.stock_minimum * 100) as stock_percentage
        FROM medicaments m
        WHERE m.statut = 'actif'
        AND m.stock_actuel <= m.stock_minimum
        ORDER BY stock_percentage ASC
        LIMIT 10
    ");
    $low_stock_medicaments = $lowStockStmt->fetchAll();
} catch (Exception $e) {
    $low_stock_medicaments = [];
    error_log("Erreur récupération stock bas: " . $e->getMessage());
}

// Tous les médicaments
try {
    $medicamentsStmt = $pdo->query("
        SELECT m.*, 
               (m.stock_actuel / m.stock_minimum * 100) as stock_percentage
        FROM medicaments m
        WHERE m.statut = 'actif'
        ORDER BY m.nom_commercial
    ");
    $all_medicaments = $medicamentsStmt->fetchAll();
} catch (Exception $e) {
    $all_medicaments = [];
    error_log("Erreur récupération médicaments: " . $e->getMessage());
}

// Équipements
try {
    $equipmentStmt = $pdo->query("
        SELECT e.*, 
               CONCAT(u.prenom, ' ', u.nom) as created_by_name
        FROM equipment e
        LEFT JOIN utilisateurs u ON e.created_by = u.id
        WHERE e.statut != 'supprime'
        ORDER BY e.created_at DESC
    ");
    $equipments = $equipmentStmt->fetchAll();
} catch (Exception $e) {
    $equipments = [];
    error_log("Erreur récupération équipements: " . $e->getMessage());
}

// Mouvements de stock récents
try {
    $movementsStmt = $pdo->query("
        SELECT ms.*, m.nom_commercial, m.nom_generique,
               CONCAT(u.prenom, ' ', u.nom) as user_name
        FROM mouvements_stock ms
        JOIN medicaments m ON ms.medicament_id = m.id
        LEFT JOIN utilisateurs u ON ms.created_by = u.id
        ORDER BY ms.created_at DESC
        LIMIT 10
    ");
    $recent_movements = $movementsStmt->fetchAll();
} catch (Exception $e) {
    $recent_movements = [];
    error_log("Erreur récupération mouvements: " . $e->getMessage());
}

// Patients pour les modals
try {
    $patientsStmt = $pdo->query("
        SELECT id, nom, prenom, code_patient 
        FROM patients 
        WHERE statut = 'actif' 
        ORDER BY nom 
        LIMIT 50
    ");
    $patients = $patientsStmt->fetchAll();
} catch (Exception $e) {
    $patients = [];
    error_log("Erreur récupération patients: " . $e->getMessage());
}

// Statistiques
try {
    $statsStmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM materiel_sterile WHERE quantite > 0) as total_materiel,
            (SELECT COUNT(*) FROM medicaments WHERE statut = 'actif') as total_medicaments,
            (SELECT COUNT(*) FROM equipment WHERE statut = 'actif') as total_equipments,
            (SELECT COUNT(*) FROM medicaments WHERE stock_actuel <= stock_minimum AND statut = 'actif') as low_stock_count,
            (SELECT SUM(valeur) FROM equipment WHERE statut = 'actif') as equipment_value,
            (SELECT COUNT(*) FROM materiel_sterile WHERE date_peremption < CURDATE() + INTERVAL 30 DAY AND date_peremption > CURDATE()) as peremption_soon
    ");
    $stats = $statsStmt->fetch();
} catch (Exception $e) {
    $stats = [
        'total_materiel' => 0, 
        'total_medicaments' => 0, 
        'total_equipments' => 0, 
        'low_stock_count' => 0, 
        'equipment_value' => 0,
        'peremption_soon' => 0
    ];
    error_log("Erreur récupération statistiques: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
        <div>
            <h1 class="h2 mb-0">
                <i class="fas fa-medkit me-2"></i><?php echo $title; ?>
            </h1>
            <p class="text-muted mb-0">
                <?php echo getPageDescription($page); ?>
            </p>
        </div>
        <div class="btn-toolbar">
            <?php if ($page == 'materiel'): ?>
            <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addMaterielModal">
                <i class="fas fa-plus me-1"></i>Nouveau matériel
            </button>
            <?php elseif ($page == 'stock'): ?>
            <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#stockInModal">
                <i class="fas fa-arrow-down me-1"></i>Entrée de stock
            </button>
            <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#stockOutModal">
                <i class="fas fa-arrow-up me-1"></i>Sortie de stock
            </button>
            <?php elseif ($page == 'equipement'): ?>
            <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                <i class="fas fa-plus me-1"></i>Nouvel équipement
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Messages d'alerte -->
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Navigation par onglets -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $page == 'materiel' ? 'active' : ''; ?>" 
               href="materiel.php?page=materiel">
                <i class="fas fa-boxes me-1"></i>Matériel Stérile
                <?php if ($stats['peremption_soon'] > 0): ?>
                <span class="badge bg-warning ms-1"><?php echo $stats['peremption_soon']; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $page == 'stock' ? 'active' : ''; ?>" 
               href="materiel.php?page=stock">
                <i class="fas fa-pills me-1"></i>Médicaments
                <?php if ($stats['low_stock_count'] > 0): ?>
                <span class="badge bg-danger ms-1"><?php echo $stats['low_stock_count']; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $page == 'equipement' ? 'active' : ''; ?>" 
               href="materiel.php?page=equipement">
                <i class="fas fa-cogs me-1"></i>Équipements
            </a>
        </li>
    </ul>

    <!-- Statistiques rapides -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-start border-primary border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">Matériel Stérile</div>
                            <div class="h4 mb-0"><?php echo $stats['total_materiel']; ?></div>
                            <small class="text-muted">Articles disponibles</small>
                        </div>
                        <i class="fas fa-boxes text-primary fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-start border-success border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">Médicaments</div>
                            <div class="h4 mb-0"><?php echo $stats['total_medicaments']; ?></div>
                            <small class="text-danger">
                                <?php echo $stats['low_stock_count']; ?> stock bas
                            </small>
                        </div>
                        <i class="fas fa-pills text-success fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-start border-warning border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">Équipements</div>
                            <div class="h4 mb-0"><?php echo $stats['total_equipments']; ?></div>
                            <small class="text-muted">
                                <?php echo formatCurrency($stats['equipment_value']); ?>
                            </small>
                        </div>
                        <i class="fas fa-cogs text-warning fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-start border-info border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">Péremption</div>
                            <div class="h4 mb-0"><?php echo $stats['peremption_soon']; ?></div>
                            <small class="text-warning">Dans 30 jours</small>
                        </div>
                        <i class="fas fa-clock text-info fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenu de la page -->
    <?php if ($page == 'materiel'): ?>
    <!-- Page Matériel Stérile -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>Inventaire du matériel stérile
                    </h6>
                    <div>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMaterielModal">
                            <i class="fas fa-plus me-1"></i>Ajouter
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Type</th>
                                    <th>Référence</th>
                                    <th>Quantité</th>
                                    <th>Minimum</th>
                                    <th>Péremption</th>
                                    <th>Localisation</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($materiel_sterile)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-boxes fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">Aucun matériel stérile enregistré</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($materiel_sterile as $item): 
                                    $peremption_color = '';
                                    if ($item['date_peremption']) {
                                        $jours = $item['jours_peremption'];
                                        if ($jours < 0) {
                                            $peremption_color = 'danger';
                                            $peremption_text = 'Expiré';
                                        } elseif ($jours < 30) {
                                            $peremption_color = 'warning';
                                            $peremption_text = "$jours jours";
                                        } else {
                                            $peremption_color = 'success';
                                            $peremption_text = date('d/m/Y', strtotime($item['date_peremption']));
                                        }
                                    } else {
                                        $peremption_color = 'secondary';
                                        $peremption_text = 'Non défini';
                                    }
                                    
                                    $stock_color = $item['quantite'] > $item['quantite_min'] ? 'success' : 
                                                  ($item['quantite'] > 0 ? 'warning' : 'danger');
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['nom']); ?></strong>
                                        <?php if ($item['fournisseur']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['fournisseur']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['type']); ?></td>
                                    <td><?php echo htmlspecialchars($item['reference']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $stock_color; ?>">
                                            <?php echo $item['quantite']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $item['quantite_min']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $peremption_color; ?>">
                                            <?php echo $peremption_text; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['localisation']); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="editMateriel(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-success" 
                                                    onclick="useMateriel(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-syringe"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteMateriel(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($page == 'stock'): ?>
    <!-- Page Médicaments -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>Inventaire des médicaments
                    </h6>
                    <div>
                        <button type="button" class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#stockInModal">
                            <i class="fas fa-arrow-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#stockOutModal">
                            <i class="fas fa-arrow-up"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nom commercial</th>
                                    <th>Nom générique</th>
                                    <th>Forme/Dosage</th>
                                    <th>Stock actuel</th>
                                    <th>Stock minimum</th>
                                    <th>Niveau</th>
                                    <th>Prix unitaire</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_medicaments)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-pills fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">Aucun médicament enregistré</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($all_medicaments as $med): 
                                    $percentage = ($med['stock_actuel'] / $med['stock_minimum']) * 100;
                                    $stock_color = $percentage >= 100 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger');
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($med['nom_commercial']); ?></strong>
                                        <?php if ($med['laboratoire']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($med['laboratoire']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($med['nom_generique']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($med['forme']); ?>
                                        <?php if ($med['dosage']): ?>
                                        <br><small><?php echo htmlspecialchars($med['dosage']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo $med['stock_actuel']; ?></strong>
                                    </td>
                                    <td><?php echo $med['stock_minimum']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                <div class="progress-bar bg-<?php echo $stock_color; ?>" 
                                                     style="width: <?php echo min(100, $percentage); ?>%">
                                                </div>
                                            </div>
                                            <small><?php echo round($percentage, 0); ?>%</small>
                                        </div>
                                    </td>
                                    <td><?php echo formatCurrency($med['prix_unitaire']); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-success"
                                                    onclick="stockIn(<?php echo $med['id']; ?>)">
                                                <i class="fas fa-arrow-down"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger"
                                                    onclick="stockOut(<?php echo $med['id']; ?>)">
                                                <i class="fas fa-arrow-up"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-warning"
                                                    onclick="adjustStock(<?php echo $med['id']; ?>)">
                                                <i class="fas fa-adjust"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">
                        <i class="fas fa-history me-2"></i>Derniers mouvements
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_movements)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-exchange-alt fa-2x text-muted mb-3"></i>
                        <p class="text-muted">Aucun mouvement récent</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_movements as $movement): 
                            $movement_color = $movement['type_mouvement'] == 'entree' ? 'success' : 
                                            ($movement['type_mouvement'] == 'sortie' ? 'danger' : 'warning');
                        ?>
                        <div class="list-group-item border-0 px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($movement['nom_commercial']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo date('d/m H:i', strtotime($movement['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo $movement_color; ?>">
                                        <?php echo ($movement['type_mouvement'] == 'entree' ? '+' : '-') . $movement['quantite']; ?>
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo $movement['quantite_apres']; ?> unités
                                    </small>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <?php echo htmlspecialchars($movement['motif']); ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($page == 'equipement'): ?>
    <!-- Page Équipements -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-list me-2"></i>Inventaire des équipements
                    </h6>
                    <div>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                            <i class="fas fa-plus me-1"></i>Ajouter
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Catégorie</th>
                                    <th>Numéro série</th>
                                    <th>Localisation</th>
                                    <th>Statut</th>
                                    <th>Valeur</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($equipments)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-cogs fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">Aucun équipement enregistré</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($equipments as $equipment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($equipment['nom']); ?></strong>
                                        <?php if ($equipment['marque']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($equipment['marque']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($equipment['categorie']); ?></td>
                                    <td>
                                        <?php if ($equipment['numero_serie']): ?>
                                        <code><?php echo htmlspecialchars($equipment['numero_serie']); ?></code>
                                        <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($equipment['localisation']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getEquipmentStatusColor($equipment['statut']); ?>">
                                            <?php echo ucfirst($equipment['statut']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatCurrency($equipment['valeur']); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="editEquipment(<?php echo $equipment['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteEquipment(<?php echo $equipment['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ========== MODALS MATÉRIEL STÉRILE ========== -->

<!-- Modal Ajout matériel -->
<div class="modal fade" id="addMaterielModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter du matériel stérile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom *</label>
                            <input type="text" class="form-control" name="nom" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type *</label>
                            <select class="form-select" name="type" required>
                                <option value="">Sélectionner...</option>
                                <option value="compresses">Compresses</option>
                                <option value="seringues">Seringues</option>
                                <option value="aiguilles">Aiguilles</option>
                                <option value="gants">Gants stériles</option>
                                <option value="champs">Champs opératoires</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Référence</label>
                            <input type="text" class="form-control" name="reference">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantité *</label>
                            <input type="number" class="form-control" name="quantite" min="1" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantité minimum</label>
                            <input type="number" class="form-control" name="quantite_min" min="1" value="10">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date de réception</label>
                            <input type="date" class="form-control" name="date_reception" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date de péremption</label>
                            <input type="date" class="form-control" name="date_peremption">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fournisseur</label>
                            <input type="text" class="form-control" name="fournisseur">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Localisation</label>
                            <select class="form-select" name="localisation">
                                <option value="Stock principal">Stock principal</option>
                                <option value="Bloc opératoire">Bloc opératoire</option>
                                <option value="Urgences">Urgences</option>
                                <option value="Pharmacie">Pharmacie</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="add_materiel">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modification matériel -->
<div class="modal fade" id="editMaterielModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le matériel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="materiel_id" id="edit_materiel_id">
                <div class="modal-body">
                    <!-- Contenu rempli par JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="edit_materiel">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Utilisation matériel -->
<div class="modal fade" id="useMaterielModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Utiliser du matériel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="materiel_id" id="use_materiel_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Quantité utilisée *</label>
                        <input type="number" class="form-control" name="quantite_utilisee" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Motif *</label>
                        <select class="form-select" name="motif" required>
                            <option value="consultation">Consultation</option>
                            <option value="soins">Soins infirmiers</option>
                            <option value="intervention">Intervention chirurgicale</option>
                            <option value="urgence">Urgence</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Patient (optionnel)</label>
                        <select class="form-select" name="patient_id">
                            <option value="">Non spécifié</option>
                            <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['nom'] . ' ' . $patient['prenom']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Consultation (optionnel)</label>
                        <input type="number" class="form-control" name="consultation_id" placeholder="ID consultation">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="use_materiel">
                        Enregistrer l'utilisation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== MODALS MÉDICAMENTS ========== -->

<!-- Modal Entrée de stock -->
<div class="modal fade" id="stockInModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Entrée de stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="medicament_id" id="stock_medicament_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Médicament *</label>
                        <select class="form-select" name="medicament_id" id="stock_in_medicament" required>
                            <option value="">Sélectionner...</option>
                            <?php foreach ($all_medicaments as $med): ?>
                            <option value="<?php echo $med['id']; ?>">
                                <?php echo htmlspecialchars($med['nom_commercial']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantité *</label>
                            <input type="number" class="form-control" name="quantite" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Numéro de lot</label>
                            <input type="text" class="form-control" name="lot">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date de péremption</label>
                            <input type="date" class="form-control" name="date_peremption">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fournisseur</label>
                            <input type="text" class="form-control" name="fournisseur">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Numéro de facture</label>
                        <input type="text" class="form-control" name="numero_facture">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="stock_in">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Sortie de stock -->
<div class="modal fade" id="stockOutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sortie de stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="medicament_id" id="out_medicament_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Médicament *</label>
                        <select class="form-select" name="medicament_id" id="stock_out_medicament" required>
                            <option value="">Sélectionner...</option>
                            <?php foreach ($all_medicaments as $med): ?>
                            <option value="<?php echo $med['id']; ?>">
                                <?php echo htmlspecialchars($med['nom_commercial']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantité *</label>
                        <input type="number" class="form-control" name="quantite" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Motif *</label>
                        <select class="form-select" name="motif" required>
                            <option value="distribution_patient">Distribution patient</option>
                            <option value="usage_interne">Usage interne</option>
                            <option value="transfert">Transfert vers autre service</option>
                            <option value="perte">Perte/Vol</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Patient (optionnel)</label>
                        <select class="form-select" name="patient_id">
                            <option value="">Non spécifié</option>
                            <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['nom'] . ' ' . $patient['prenom']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Consultation (optionnel)</label>
                        <input type="number" class="form-control" name="consultation_id" placeholder="ID consultation">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="stock_out">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ajustement de stock -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajustement de stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="medicament_id" id="adjust_medicament_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Stock actuel</label>
                        <input type="number" class="form-control" id="current_stock" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nouvelle quantité *</label>
                        <input type="number" class="form-control" name="new_quantity" id="new_quantity" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Raison *</label>
                        <select class="form-select" name="reason" required>
                            <option value="">Sélectionner...</option>
                            <option value="inventaire">Correction d'inventaire</option>
                            <option value="erreur_saisie">Erreur de saisie</option>
                            <option value="perte">Perte constatée</option>
                            <option value="expiration">Produit expiré</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="stock_adjust">
                        Appliquer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== MODALS ÉQUIPEMENTS ========== -->

<!-- Modal Ajout équipement -->
<div class="modal fade" id="addEquipmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un équipement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom *</label>
                            <input type="text" class="form-control" name="nom" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Catégorie *</label>
                            <select class="form-select" name="categorie" required>
                                <option value="">Sélectionner...</option>
                                <option value="diagnostic">Diagnostic</option>
                                <option value="monitoring">Monitoring</option>
                                <option value="chirurgical">Chirurgical</option>
                                <option value="mobilier">Mobilier</option>
                                <option value="informatique">Informatique</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Marque</label>
                            <input type="text" class="form-control" name="marque">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Modèle</label>
                            <input type="text" class="form-control" name="modele">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Numéro de série</label>
                            <input type="text" class="form-control" name="numero_serie">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date d'acquisition</label>
                            <input type="date" class="form-control" name="date_acquisition" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Valeur (€)</label>
                            <input type="number" class="form-control" name="valeur" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Localisation</label>
                            <input type="text" class="form-control" name="localisation">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Statut</label>
                            <select class="form-select" name="statut">
                                <option value="actif">Actif</option>
                                <option value="maintenance">En maintenance</option>
                                <option value="reserve">En réserve</option>
                                <option value="hors_service">Hors service</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="add_equipment">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modification équipement -->
<div class="modal fade" id="editEquipmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier l'équipement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="equipment_id" id="edit_equipment_id">
                <div class="modal-body">
                    <!-- Contenu rempli par JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="edit_equipment">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<!-- Fonctions utilitaires -->
<?php
function getPageDescription($page) {
    $descriptions = [
        'materiel' => 'Gestion du matériel stérile et consommables médicaux',
        'stock' => 'Gestion des stocks de médicaments et consommables',
        'equipement' => 'Gestion des équipements médicaux et matériel durable'
    ];
    return $descriptions[$page] ?? '';
}

function getEquipmentStatusColor($status) {
    switch($status) {
        case 'actif': return 'success';
        case 'maintenance': return 'warning';
        case 'hors_service': return 'danger';
        case 'reserve': return 'info';
        default: return 'secondary';
    }
}

function formatCurrency($amount) {
    return number_format($amount, 2, ',', ' ') . ' €';
}
?>

<script>
// Fonctions pour le matériel stérile
function editMateriel(id) {
    fetch('ajax/get_materiel.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_materiel_id').value = data.materiel.id;
                document.getElementById('edit_nom').value = data.materiel.nom;
                document.getElementById('edit_type').value = data.materiel.type;
                document.getElementById('edit_reference').value = data.materiel.reference;
                document.getElementById('edit_quantite').value = data.materiel.quantite;
                document.getElementById('edit_quantite_min').value = data.materiel.quantite_min;
                document.getElementById('edit_date_reception').value = data.materiel.date_reception;
                document.getElementById('edit_date_peremption').value = data.materiel.date_peremption;
                document.getElementById('edit_fournisseur').value = data.materiel.fournisseur;
                document.getElementById('edit_localisation').value = data.materiel.localisation;
                document.getElementById('edit_notes').value = data.materiel.notes;
                
                const modal = new bootstrap.Modal(document.getElementById('editMaterielModal'));
                modal.show();
            }
        });
}

function useMateriel(id) {
    document.getElementById('use_materiel_id').value = id;
    const modal = new bootstrap.Modal(document.getElementById('useMaterielModal'));
    modal.show();
}

function deleteMateriel(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce matériel ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'materiel_id';
        inputId.value = id;
        
        const inputAction = document.createElement('input');
        inputAction.type = 'hidden';
        inputAction.name = 'action';
        inputAction.value = 'delete_materiel';
        
        form.appendChild(inputId);
        form.appendChild(inputAction);
        document.body.appendChild(form);
        form.submit();
    }
}

// Fonctions pour les médicaments
function stockIn(medicamentId) {
    document.getElementById('stock_medicament_id').value = medicamentId;
    const modal = new bootstrap.Modal(document.getElementById('stockInModal'));
    modal.show();
}

function stockOut(medicamentId) {
    document.getElementById('out_medicament_id').value = medicamentId;
    const modal = new bootstrap.Modal(document.getElementById('stockOutModal'));
    modal.show();
}

function adjustStock(medicamentId) {
    fetch('ajax/get_stock.php?id=' + medicamentId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('adjust_medicament_id').value = medicamentId;
                document.getElementById('current_stock').value = data.stock_actuel;
                document.getElementById('new_quantity').value = data.stock_actuel;
                document.getElementById('new_quantity').min = 0;
                
                const modal = new bootstrap.Modal(document.getElementById('adjustStockModal'));
                modal.show();
            }
        });
}

// Fonctions pour les équipements
function editEquipment(id) {
    fetch('ajax/get_equipment.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_equipment_id').value = data.equipment.id;
                document.getElementById('edit_nom').value = data.equipment.nom;
                document.getElementById('edit_categorie').value = data.equipment.categorie;
                document.getElementById('edit_marque').value = data.equipment.marque;
                document.getElementById('edit_modele').value = data.equipment.modele;
                document.getElementById('edit_numero_serie').value = data.equipment.numero_serie;
                document.getElementById('edit_date_acquisition').value = data.equipment.date_acquisition;
                document.getElementById('edit_valeur').value = data.equipment.valeur;
                document.getElementById('edit_localisation').value = data.equipment.localisation;
                document.getElementById('edit_statut').value = data.equipment.statut;
                document.getElementById('edit_notes').value = data.equipment.notes;
                
                const modal = new bootstrap.Modal(document.getElementById('editEquipmentModal'));
                modal.show();
            }
        });
}

function deleteEquipment(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet équipement ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'equipment_id';
        inputId.value = id;
        
        const inputAction = document.createElement('input');
        inputAction.type = 'hidden';
        inputAction.name = 'action';
        inputAction.value = 'delete_equipment';
        
        form.appendChild(inputId);
        form.appendChild(inputAction);
        document.body.appendChild(form);
        form.submit();
    }
}

// Initialiser les tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
});
</script>