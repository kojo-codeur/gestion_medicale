<?php
// ajax/check_disponibility.php
require_once '../config/database.php';

header('Content-Type: application/json');

$docteur_id = $_POST['docteur_id'] ?? null;
$date_rdv = $_POST['date_rdv'] ?? null;
$rdv_id = $_POST['rdv_id'] ?? 0;

if (!$docteur_id || !$date_rdv) {
    echo json_encode(['available' => false, 'message' => 'Données manquantes']);
    exit();
}

try {
    // Convertir la date en format MySQL
    $dateTime = date('Y-m-d H:i:s', strtotime($date_rdv));
    $dateOnly = date('Y-m-d', strtotime($date_rdv));
    
    // Récupérer les informations du médecin
    $stmt = $pdo->prepare("SELECT prenom, nom, specialite FROM utilisateurs WHERE id = ?");
    $stmt->execute([$docteur_id]);
    $docteur = $stmt->fetch();
    
    if (!$docteur) {
        echo json_encode(['available' => false, 'message' => 'Médecin non trouvé']);
        exit();
    }
    
    // Vérifier si c'est un jour de travail (lundi-vendredi)
    $dayOfWeek = date('N', strtotime($dateTime));
    if ($dayOfWeek >= 6) { // 6 = samedi, 7 = dimanche
        echo json_encode([
            'available' => false,
            'message' => 'Le cabinet est fermé le week-end'
        ]);
        exit();
    }
    
    // Vérifier les heures de travail (8h-18h)
    $hour = date('H', strtotime($dateTime));
    if ($hour < 8 || $hour > 17) {
        echo json_encode([
            'available' => false,
            'message' => 'Heures de travail: 8h-18h'
        ]);
        exit();
    }
    
    // Vérifier la durée (par défaut 30 minutes)
    $duree = $_POST['duree'] ?? 30;
    
    // Vérifier les conflits de rendez-vous
    $checkStmt = $pdo->prepare("
        SELECT r.id, r.date_rdv, r.duree,
               p.nom as patient_nom, p.prenom as patient_prenom,
               TIME(r.date_rdv) as heure_rdv
        FROM rendez_vous r
        JOIN patients p ON r.patient_id = p.id
        WHERE r.docteur_id = ? 
        AND DATE(r.date_rdv) = DATE(?)
        AND r.statut IN ('confirme', 'present')
        AND r.id != ?
        AND (
            (? BETWEEN r.date_rdv AND DATE_ADD(r.date_rdv, INTERVAL r.duree MINUTE))
            OR 
            (DATE_ADD(?, INTERVAL ? MINUTE) BETWEEN r.date_rdv AND DATE_ADD(r.date_rdv, INTERVAL r.duree MINUTE))
            OR
            (r.date_rdv BETWEEN ? AND DATE_ADD(?, INTERVAL ? MINUTE))
        )
        ORDER BY r.date_rdv
    ");
    
    $checkStmt->execute([
        $docteur_id, $dateTime, $rdv_id,
        $dateTime, $dateTime, $duree,
        $dateTime, $dateTime, $duree
    ]);
    $conflicts = $checkStmt->fetchAll();
    
    if (empty($conflicts)) {
        // Vérifier le nombre de rendez-vous ce jour-là
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as total_rdv
            FROM rendez_vous 
            WHERE docteur_id = ? 
            AND DATE(date_rdv) = DATE(?)
            AND statut IN ('confirme', 'present')
        ");
        $countStmt->execute([$docteur_id, $dateTime]);
        $count = $countStmt->fetch();
        
        if ($count['total_rdv'] >= 15) {
            echo json_encode([
                'available' => false,
                'message' => 'Le médecin a déjà 15 rendez-vous ce jour-là'
            ]);
            exit();
        }
        
        echo json_encode([
            'available' => true,
            'message' => 'Disponible',
            'docteur' => $docteur['prenom'] . ' ' . $docteur['nom'],
            'specialite' => $docteur['specialite']
        ]);
    } else {
        $suggestions = '<div class="small">';
        $suggestions .= '<strong>Conflits détectés:</strong><br>';
        
        foreach ($conflicts as $conflict) {
            $suggestions .= "• " . $conflict['patient_prenom'] . " " . $conflict['patient_nom'] . 
                           " à " . substr($conflict['heure_rdv'], 0, 5) . 
                           " (" . $conflict['duree'] . " min)<br>";
        }
        
        // Proposer des créneaux disponibles
        $suggestions .= '<br><strong>Suggestions:</strong><br>';
        
        // Chercher des créneaux libres dans les 2 prochaines heures
        $suggestedTime = strtotime($dateTime);
        for ($i = 1; $i <= 4; $i++) {
            $checkTime = date('Y-m-d H:i:s', $suggestedTime + ($i * 15 * 60));
            
            $checkStmt->execute([
                $docteur_id, $checkTime, $rdv_id,
                $checkTime, $checkTime, $duree,
                $checkTime, $checkTime, $duree
            ]);
            
            if (!$checkStmt->fetch()) {
                $suggestions .= "• " . date('H:i', strtotime($checkTime)) . " (disponible)<br>";
                break;
            }
        }
        
        $suggestions .= '</div>';
        
        echo json_encode([
            'available' => false,
            'message' => 'Le médecin a déjà un rendez-vous à cette heure',
            'suggestions' => $suggestions,
            'conflicts' => $conflicts
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['available' => false, 'message' => 'Erreur de vérification: ' . $e->getMessage()]);
}
?>