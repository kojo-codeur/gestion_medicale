<?php
session_start();
require_once 'config/database.php';

// Journaliser la déconnexion
if (isset($_SESSION['user_id'])) {
    try {
        $pdo = Database::getInstance()->getConnection();
        
        // Supprimer le token "Se souvenir de moi"
        if (isset($_COOKIE['remember_token'])) {
            $hashedToken = hash('sha256', $_COOKIE['remember_token']);
            $pdo->prepare("DELETE FROM auth_tokens WHERE token = ?")->execute([$hashedToken]);
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        // Journaliser la déconnexion
        $pdo->prepare("
            INSERT INTO login_logs 
            (user_id, login_time, ip_address, user_agent, success) 
            VALUES (?, NOW(), ?, ?, 1)
        ")->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
    } catch (Exception $e) {
        // Ignorer les erreurs de journalisation
    }
}

// Détruire la session
session_unset();
session_destroy();
session_write_close();

// Supprimer le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Rediriger vers la page de connexion avec message
header('Location: login.php?logout=1');
exit();
?>