<?php
// config/notifications.php

/**
 * Fonction pour obtenir le nombre de notifications non lues
 */
function getUnreadNotificationsCount($user_id) {
    global $pdo; // Assurez-vous que $pdo est disponible
    
    try {
        $role = $_SESSION['role'] ?? 'user';
        
        $sql = "SELECT COUNT(*) as count FROM notifications WHERE lu = 0 AND (";
        if ($role === 'admin') {
            $sql .= "user_id IS NULL OR user_id = :user_id OR role_target = :role OR role_target = 'tous'";
        } else {
            $sql .= "user_id = :user_id OR role_target = :role OR role_target = 'tous'";
        }
        $sql .= ")";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':role', $role, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Erreur comptage notifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Fonction pour créer une nouvelle notification
 */
function createNotification($data) {
    global $pdo;
    
    $defaults = [
        'user_id' => null,
        'sender_id' => null,
        'role_target' => null,
        'type' => 'info',
        'titre' => 'Nouvelle notification',
        'message' => '',
        'lien' => null,
        'lu' => 0
    ];
    
    $data = array_merge($defaults, $data);
    
    try {
        $sql = "INSERT INTO notifications 
                (user_id, sender_id, role_target, type, titre, message, lien, lu, created_at) 
                VALUES (:user_id, :sender_id, :role_target, :type, :titre, :message, :lien, :lu, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        
        return $pdo->lastInsertId();
        
    } catch (PDOException $e) {
        error_log("Erreur création notification: " . $e->getMessage());
        return false;
    }
}