<?php
// admin/download_backup.php
require_once '../config/database.php';
checkRole('admin');

$backup_id = $_GET['id'] ?? 0;

// Vérifier si l'ID de sauvegarde est valide
if (!$backup_id) {
    header('Location: sauvegardes.php');
    exit;
}

$pdo = Database::getInstance()->getConnection();

// Récupérer les informations de la sauvegarde
$stmt = $pdo->prepare("
    SELECT bh.*, u.prenom, u.nom 
    FROM backup_history bh
    LEFT JOIN utilisateurs u ON bh.created_by = u.id
    WHERE bh.id = ?
");
$stmt->execute([$backup_id]);
$backup = $stmt->fetch();

if (!$backup) {
    $_SESSION['error_message'] = 'Sauvegarde non trouvée.';
    header('Location: sauvegardes.php');
    exit;
}

// Vérifier si le fichier existe
$backup_dir = '../backups/';
$file_path = $backup_dir . $backup['filename'];

if (!file_exists($file_path)) {
    $_SESSION['error_message'] = 'Le fichier de sauvegarde n\'existe plus.';
    header('Location: sauvegardes.php');
    exit;
}

// Journaliser le téléchargement
$audit_stmt = $pdo->prepare("
    INSERT INTO audit_logs (user_id, action, table_name, record_id, created_at) 
    VALUES (?, 'DOWNLOAD_BACKUP', 'backup_history', ?, NOW())
");
$audit_stmt->execute([$_SESSION['user_id'], $backup_id]);

// Télécharger le fichier
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

// Nettoyer le buffer de sortie
flush();

// Lire et envoyer le fichier
readfile($file_path);
exit;