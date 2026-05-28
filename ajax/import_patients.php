<?php
// ajax/import_patients.php
require_once '../config/database.php';

// Vérifier l'authentification et les permissions
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier les permissions (admin ou secrétaire)
$stmt = $pdo->prepare("SELECT role FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!in_array($user['role'], ['admin', 'secretaire'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

// Vérifier si un fichier a été uploadé
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Aucun fichier uploadé ou erreur d\'upload']);
    exit();
}

$file = $_FILES['csv_file']['tmp_name'];
$fileName = $_FILES['csv_file']['name'];

// Vérifier l'extension
$ext = pathinfo($fileName, PATHINFO_EXTENSION);
if (strtolower($ext) !== 'csv') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Seuls les fichiers CSV sont autorisés']);
    exit();
}

try {
    // Ouvrir le fichier CSV
    if (($handle = fopen($file, "r")) === FALSE) {
        throw new Exception("Impossible d'ouvrir le fichier CSV");
    }
    
    $pdo->beginTransaction();
    $importedCount = 0;
    $skippedCount = 0;
    $errors = [];
    
    // Lire l'en-tête
    $header = fgetcsv($handle, 1000, ",");
    
    // Vérifier les colonnes requises
    $requiredColumns = ['nom', 'prenom', 'date_naissance', 'sexe', 'telephone'];
    foreach ($requiredColumns as $col) {
        if (!in_array($col, array_map('strtolower', $header))) {
            throw new Exception("Colonne manquante: $col");
        }
    }
    
    // Lire les données
    $row = 1;
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $row++;
        
        // Associer les données aux colonnes
        $patientData = array_combine(array_map('strtolower', $header), $data);
        
        // Valider les données
        if (empty($patientData['nom']) || empty($patientData['prenom']) || 
            empty($patientData['date_naissance']) || empty($patientData['telephone'])) {
            $errors[] = "Ligne $row: Données manquantes";
            $skippedCount++;
            continue;
        }
        
        // Valider le sexe
        $sexe = strtoupper($patientData['sexe']);
        if (!in_array($sexe, ['M', 'F'])) {
            $errors[] = "Ligne $row: Sexe invalide (M ou F requis)";
            $skippedCount++;
            continue;
        }
        
        // Valider la date de naissance
        $dateNaissance = DateTime::createFromFormat('Y-m-d', $patientData['date_naissance']);
        if (!$dateNaissance) {
            $dateNaissance = DateTime::createFromFormat('d/m/Y', $patientData['date_naissance']);
        }
        
        if (!$dateNaissance) {
            $errors[] = "Ligne $row: Format de date invalide (utiliser YYYY-MM-DD ou DD/MM/YYYY)";
            $skippedCount++;
            continue;
        }
        
        // Vérifier si le patient existe déjà (par nom, prénom et date de naissance)
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM patients 
            WHERE nom = ? 
            AND prenom = ? 
            AND date_naissance = ?
        ");
        $checkStmt->execute([
            trim($patientData['nom']),
            trim($patientData['prenom']),
            $dateNaissance->format('Y-m-d')
        ]);
        
        if ($checkStmt->fetchColumn() > 0) {
            $skippedCount++;
            continue; // Patient déjà existant, on ignore
        }
        
        // Préparer les données pour l'insertion
        $insertData = [
            'nom' => trim($patientData['nom']),
            'prenom' => trim($patientData['prenom']),
            'date_naissance' => $dateNaissance->format('Y-m-d'),
            'sexe' => $sexe,
            'telephone' => trim($patientData['telephone']),
            'email' => trim($patientData['email'] ?? ''),
            'adresse' => trim($patientData['adresse'] ?? ''),
            'ville' => trim($patientData['ville'] ?? ''),
            'code_postal' => trim($patientData['code_postal'] ?? ''),
            'pays' => trim($patientData['pays'] ?? 'France'),
            'groupe_sanguin' => trim($patientData['groupe_sanguin'] ?? ''),
            'profession' => trim($patientData['profession'] ?? ''),
            'antecedents_familiaux' => trim($patientData['antecedents_familiaux'] ?? ''),
            'antecedents_personnels' => trim($patientData['antecedents_personnels'] ?? ''),
            'allergies' => trim($patientData['allergies'] ?? ''),
            'medicaments_habituels' => trim($patientData['medicaments_habituels'] ?? ''),
            'created_by' => $_SESSION['user_id']
        ];
        
        // Insérer le patient
        $stmt = $pdo->prepare("
            INSERT INTO patients 
            (nom, prenom, date_naissance, sexe, telephone, email, adresse, 
             ville, code_postal, pays, groupe_sanguin, profession, 
             antecedents_familiaux, antecedents_personnels, allergies, 
             medicaments_habituels, created_by, date_enregistrement) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute(array_values($insertData));
        
        // Journaliser l'action
        $patientId = $pdo->lastInsertId();
        $auditStmt = $pdo->prepare("
            INSERT INTO audit_logs 
            (user_id, action, table_name, record_id, ip_address, details) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $auditStmt->execute([
            $_SESSION['user_id'],
            'CREATE',
            'patients',
            $patientId,
            $_SERVER['REMOTE_ADDR'],
            "Import CSV: " . $insertData['prenom'] . " " . $insertData['nom']
        ]);
        
        $importedCount++;
    }
    
    fclose($handle);
    $pdo->commit();
    
    // Préparer la réponse
    $response = [
        'success' => true,
        'count' => $importedCount,
        'skipped' => $skippedCount,
        'message' => "Import terminé: $importedCount patients importés, $skippedCount ignorés"
    ];
    
    if (!empty($errors)) {
        $response['errors'] = array_slice($errors, 0, 10); // Limiter à 10 erreurs
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    if (isset($handle) && $handle) {
        fclose($handle);
    }
    
    $pdo->rollBack();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>