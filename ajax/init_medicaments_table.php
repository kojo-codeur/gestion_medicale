<?php
require_once '../config/database.php';

// Seul l'admin peut initialiser
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Permission refusée']);
    exit;
}

header('Content-Type: application/json');
$pdo = Database::getInstance()->getConnection();

try {
    // Vérifier si la table existe déjà
    $check = $pdo->query("SHOW TABLES LIKE 'medicaments'");
    if ($check->rowCount() > 0) {
        echo json_encode(['success' => false, 'error' => 'La table medicaments existe déjà. Supprimez-la d\'abord si vous voulez la réinitialiser.']);
        exit;
    }

    // Création de la table
    $sql = "CREATE TABLE `medicaments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `code_cip` varchar(50) DEFAULT NULL,
        `nom_commercial` varchar(200) NOT NULL,
        `nom_generique` varchar(200) DEFAULT NULL,
        `laboratoire` varchar(100) DEFAULT NULL,
        `forme` enum('comprime','gelule','sirop','injectable','pommade','creme','suppositoire','collyre','spray','poudre','autre') NOT NULL,
        `dosage` varchar(100) DEFAULT NULL,
        `classe_therapeutique` varchar(100) DEFAULT NULL,
        `indications` text,
        `contre_indications` text,
        `effets_secondaires` text,
        `posologie` text,
        `precautions` text,
        `interactions` text,
        `conditionnement` varchar(100) DEFAULT NULL,
        `stock_actuel` int(11) NOT NULL DEFAULT '0',
        `stock_minimum` int(11) NOT NULL DEFAULT '10',
        `prix_unitaire` decimal(10,2) NOT NULL DEFAULT '0.00',
        `remboursement` decimal(5,2) DEFAULT '0.00',
        `statut` enum('actif','inactif','rupture','retire') DEFAULT 'actif',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code_cip` (`code_cip`),
        KEY `idx_nom_commercial` (`nom_commercial`(191)),
        KEY `idx_statut` (`statut`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);

    // Insertion de données de démonstration
    $inserts = [
        [
            'code_cip' => '3400933596033',
            'nom_commercial' => 'Doliprane',
            'nom_generique' => 'Paracétamol',
            'laboratoire' => 'Sanofi',
            'forme' => 'comprime',
            'dosage' => '1000mg',
            'classe_therapeutique' => 'Analgésique',
            'indications' => 'Douleurs et fièvre',
            'contre_indications' => 'Insuffisance hépatique sévère',
            'effets_secondaires' => 'Rares réactions allergiques',
            'posologie' => '1 comprimé toutes les 6h, max 4 par jour',
            'precautions' => 'Ne pas dépasser la dose prescrite',
            'interactions' => 'Potentialisation des anticoagulants',
            'conditionnement' => 'Boîte de 8 comprimés',
            'stock_actuel' => 150,
            'stock_minimum' => 20,
            'prix_unitaire' => 2.50,
            'remboursement' => 65.00,
            'statut' => 'actif'
        ],
        [
            'code_cip' => '3400931254876',
            'nom_commercial' => 'Ibuprofène',
            'nom_generique' => 'Ibuprofène',
            'laboratoire' => 'Bayer',
            'forme' => 'comprime',
            'dosage' => '400mg',
            'classe_therapeutique' => 'Anti-inflammatoire',
            'indications' => 'Douleurs et inflammations',
            'contre_indications' => 'Allergie à l\'ibuprofène, grossesse',
            'effets_secondaires' => 'Troubles digestifs',
            'posologie' => '1 comprimé 3 fois par jour',
            'stock_actuel' => 80,
            'stock_minimum' => 15,
            'prix_unitaire' => 3.20,
            'remboursement' => 35.00,
            'statut' => 'actif'
        ],
        [
            'code_cip' => '3400934875129',
            'nom_commercial' => 'Amoxicilline',
            'nom_generique' => 'Amoxicilline',
            'laboratoire' => 'GSK',
            'forme' => 'gelule',
            'dosage' => '500mg',
            'classe_therapeutique' => 'Antibiotique',
            'indications' => 'Infections bactériennes',
            'contre_indications' => 'Allergie aux pénicillines',
            'effets_secondaires' => 'Diarrhée, éruption cutanée',
            'posologie' => '1 gélule 3 fois par jour pendant 7 jours',
            'stock_actuel' => 45,
            'stock_minimum' => 25,
            'prix_unitaire' => 5.80,
            'remboursement' => 100.00,
            'statut' => 'actif'
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO medicaments 
        (code_cip, nom_commercial, nom_generique, laboratoire, forme, dosage, classe_therapeutique,
         indications, contre_indications, effets_secondaires, posologie, precautions, interactions,
         conditionnement, stock_actuel, stock_minimum, prix_unitaire, remboursement, statut)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($inserts as $med) {
        $stmt->execute([
            $med['code_cip'], $med['nom_commercial'], $med['nom_generique'], $med['laboratoire'],
            $med['forme'], $med['dosage'], $med['classe_therapeutique'],
            $med['indications'], $med['contre_indications'], $med['effets_secondaires'],
            $med['posologie'], $med['precautions'] ?? null, $med['interactions'] ?? null,
            $med['conditionnement'], $med['stock_actuel'], $med['stock_minimum'],
            $med['prix_unitaire'], $med['remboursement'], $med['statut']
        ]);
    }

    // Création de la table des mouvements de stock (optionnelle mais utile)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `medicament_stock_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `medicament_id` int(11) NOT NULL,
            `operation` varchar(20) NOT NULL,
            `quantite` int(11) NOT NULL,
            `ancien_stock` int(11) NOT NULL,
            `nouveau_stock` int(11) NOT NULL,
            `raison` text,
            `user_id` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `medicament_id` (`medicament_id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo json_encode(['success' => true, 'message' => 'Table medicaments créée avec données de démonstration']);

} catch (PDOException $e) {
    error_log("Erreur init_medicaments_table: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}