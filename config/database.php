<?php
// config/database.php

session_start();

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                'mysql:host=localhost;dbname=gestion_medicale;charset=utf8mb4',
                'root',
                '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Erreur de connexion: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}


// Fonctions utilitaires
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function checkRole($requiredRole) {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
    
    if ($_SESSION['role'] !== $requiredRole) {
        $_SESSION['error'] = "Accès non autorisé";
        header('Location: ../index.php');
        exit();
    }
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function isAdmin() {
    return getUserRole() === 'admin';
}

function isDocteur() {
    return getUserRole() === 'docteur';
}

function isSecretaire() {
    return getUserRole() === 'secretaire';
}

function isAssistant() {
    return getUserRole() === 'assistant';
}

// function formatDate($date, $format = 'd/m/Y') {
//     return date($format, strtotime($date));
// }

if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'd/m/Y') {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '';
        }
        return date($format, strtotime($date));
    }
}

function generateReference($prefix) {
    return $prefix . '-' . date('Ymd') . '-' . uniqid();
}


// Fonction pour générer un token sécurisé
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Fonction pour valider un email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Fonction pour valider un mot de passe
function isValidPassword($password) {
    return strlen($password) >= 8 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/[0-9]/', $password);
}

// Fonction pour vérifier la force du mot de passe
function getPasswordStrength($password) {
    $strength = 0;
    
    if (strlen($password) >= 8) $strength++;
    if (preg_match('/[A-Z]/', $password)) $strength++;
    if (preg_match('/[a-z]/', $password)) $strength++;
    if (preg_match('/[0-9]/', $password)) $strength++;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $strength++;
    
    return $strength;
}

// Fonction pour envoyer un email
function sendEmail($to, $subject, $body, $from = 'no-reply@medsystem.fr') {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $from" . "\r\n";
    $headers .= "Reply-To: $from" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($to, $subject, $body, $headers);
}

// Fonction pour nettoyer l'input
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function calculateAge($date_naissance) {
    if (empty($date_naissance)) return 'Inconnu';
    $birth = new DateTime($date_naissance);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
    return $age;
}

// Fonction pour vérifier le taux de tentatives de connexion
function checkLoginAttempts($email, $limit = 5, $timeframe = 900) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempts 
        FROM login_logs 
        WHERE user_id IS NULL 
        AND ip_address = ? 
        AND login_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([$_SERVER['REMOTE_ADDR'], $timeframe]);
    $result = $stmt->fetch();
    
    return $result['attempts'] < $limit;
}

// Fonction pour générer un mot de passe aléatoire
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}

// Fonction pour formater une date relative
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return 'à l\'instant';
    } elseif ($time < 3600) {
        return floor($time / 60) . ' min';
    } elseif ($time < 86400) {
        return floor($time / 3600) . ' h';
    } elseif ($time < 604800) {
        return floor($time / 86400) . ' j';
    } else {
        return date('d/m/Y', strtotime($datetime));
    }
}

// Fonction pour journaliser les actions utilisateur
if (!function_exists('logAction')) {
    function logAction($action, $table, $record_id, $details = '') {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action, table_name, record_id, details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $action,
                $table,
                $record_id,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Erreur journalisation: " . $e->getMessage());
            return false;
        }
    }
}

function getActivityIcon($action) {
    $icons = [
        'CREATE' => 'plus',
        'UPDATE' => 'edit',
        'DELETE' => 'trash',
        'LOGIN' => 'sign-in-alt',
        'LOGOUT' => 'sign-out-alt',
        'VIEW' => 'eye',
        'EXPORT' => 'download',
        'IMPORT' => 'upload',
        'PRINT' => 'print',
        'RESTORE' => 'undo',
        'BACKUP' => 'save',
        'DOWNLOAD' => 'download'
    ];
    return $icons[$action] ?? 'circle';
}

function getActivityColor($action) {
    $colors = [
        'CREATE' => 'success',
        'UPDATE' => 'warning',
        'DELETE' => 'danger',
        'LOGIN' => 'info',
        'LOGOUT' => 'secondary',
        'VIEW' => 'primary',
        'RESTORE' => 'primary',
        'BACKUP' => 'info',
        'DOWNLOAD' => 'success'
    ];
    return $colors[$action] ?? 'secondary';
}

/**************************************************
 * FONCTIONS DE SAUVEGARDE
 **************************************************/

/**
 * Créer une sauvegarde
 */
if (!function_exists('createBackup')) {
    function createBackup($type, $backup_name, $description = '') {
        global $pdo;
        
        try {
            $backup_dir = '../backups/';
            
            // Créer le répertoire si inexistant
            if (!file_exists($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
            
            $filename = generateBackupFilename($type, $backup_name);
            $filepath = $backup_dir . $filename;
            
            switch ($type) {
                case 'database':
                    $result = backupDatabase($filepath);
                    break;
                    
                case 'files':
                    $result = backupFiles($filepath);
                    break;
                    
                case 'complete':
                    $db_result = backupDatabase($filepath . '_db.sql');
                    $files_result = backupFiles($filepath . '_files.zip');
                    
                    // Créer une archive avec les deux
                    $result = createCompleteBackup($filepath, $filepath . '_db.sql', $filepath . '_files.zip');
                    
                    // Supprimer les fichiers temporaires
                    unlink($filepath . '_db.sql');
                    unlink($filepath . '_files.zip');
                    break;
                    
                case 'incremental':
                    $result = backupIncremental($filepath);
                    break;
                    
                default:
                    return ['success' => false, 'error' => 'Type de sauvegarde inconnu'];
            }
            
            if ($result['success']) {
                // Enregistrer dans la base de données
                $size_mb = filesize($filepath) / 1024 / 1024;
                
                $stmt = $pdo->prepare("
                    INSERT INTO backup_history 
                    (filename, backup_type, backup_name, description, size_mb, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $filename,
                    $type,
                    $backup_name,
                    $description,
                    round($size_mb, 2),
                    $_SESSION['user_id'] ?? null
                ]);
                
                $backup_id = $pdo->lastInsertId();
                
                // Journaliser l'action
                logAction('BACKUP', 'backup_history', $backup_id, "Création sauvegarde: {$filename}");
                
                return [
                    'success' => true,
                    'filename' => $filename,
                    'backup_id' => $backup_id,
                    'size' => $size_mb,
                    'path' => $filepath
                ];
            }
            
            return $result;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * Générer un nom de fichier pour la sauvegarde
 */
if (!function_exists('generateBackupFilename')) {
    function generateBackupFilename($type, $name) {
        $clean_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
        $timestamp = date('Y-m-d_H-i-s');
        $type_prefix = substr($type, 0, 3);
        
        return "backup_{$type_prefix}_{$clean_name}_{$timestamp}.sql";
    }
}

/**
 * Sauvegarder la base de données
 */
if (!function_exists('backupDatabase')) {
    function backupDatabase($filepath) {
        global $pdo;
        
        try {
            // Récupérer le nom de la base
            $db_name = $pdo->query("SELECT DATABASE()")->fetchColumn();
            
            // Récupérer toutes les tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($tables)) {
                return ['success' => false, 'error' => 'Aucune table trouvée'];
            }
            
            $sql = "-- Backup de la base de données\n";
            $sql .= "-- Généré le: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Base: " . $db_name . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                // Structure de la table
                $create_table = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
                $sql .= "--\n";
                $sql .= "-- Structure de la table `{$table}`\n";
                $sql .= "--\n\n";
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sql .= $create_table['Create Table'] . ";\n\n";
                
                // Données de la table
                $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $sql .= "--\n";
                    $sql .= "-- Données de la table `{$table}`\n";
                    $sql .= "--\n\n";
                    
                    foreach ($rows as $row) {
                        $columns = array_map(function($value) use ($pdo) {
                            if ($value === null) {
                                return 'NULL';
                            }
                            return $pdo->quote($value);
                        }, array_values($row));
                        
                        $sql .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $columns) . ");\n";
                    }
                    $sql .= "\n";
                }
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            // Écrire dans le fichier
            if (file_put_contents($filepath, $sql) === false) {
                return ['success' => false, 'error' => 'Erreur d\'écriture du fichier'];
            }
            
            // Compresser le fichier
            $compressed = compressFile($filepath);
            if ($compressed) {
                unlink($filepath); // Supprimer le fichier non compressé
                $filepath .= '.gz';
            }
            
            return [
                'success' => true,
                'path' => $filepath,
                'tables' => count($tables),
                'compressed' => $compressed
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * Sauvegarder les fichiers
 */
if (!function_exists('backupFiles')) {
    function backupFiles($filepath) {
        try {
            $root_dir = realpath('../');
            $exclude_patterns = [
                '/backups/',
                '/node_modules/',
                '/vendor/',
                '/\.git/',
                '/\.env',
                'logs/',
                'cache/'
            ];
            
            // Vérifier si l'extension ZipArchive est disponible
            if (!class_exists('ZipArchive')) {
                return ['success' => false, 'error' => 'Extension ZipArchive non disponible'];
            }
            
            // Créer une archive ZIP
            $zip = new ZipArchive();
            
            if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return ['success' => false, 'error' => 'Impossible de créer l\'archive ZIP'];
            }
            
            // Ajouter les fichiers
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root_dir),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            $file_count = 0;
            foreach ($iterator as $file) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($root_dir) + 1);
                
                // Exclure certains répertoires/fichiers
                $exclude = false;
                foreach ($exclude_patterns as $pattern) {
                    if (preg_match($pattern, $file_path)) {
                        $exclude = true;
                        break;
                    }
                }
                
                if ($exclude || $file->isDir()) {
                    continue;
                }
                
                if ($zip->addFile($file_path, $relative_path)) {
                    $file_count++;
                }
            }
            
            $zip->close();
            
            return [
                'success' => true,
                'path' => $filepath,
                'file_count' => $file_count,
                'size' => filesize($filepath)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * Créer une sauvegarde complète
 */
if (!function_exists('createCompleteBackup')) {
    function createCompleteBackup($output_path, $db_file, $files_archive) {
        try {
            if (!class_exists('ZipArchive')) {
                return ['success' => false, 'error' => 'Extension ZipArchive non disponible'];
            }
            
            $zip = new ZipArchive();
            
            if ($zip->open($output_path . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return ['success' => false, 'error' => 'Impossible de créer l\'archive complète'];
            }
            
            // Ajouter la base de données
            $zip->addFile($db_file, 'database.sql');
            
            // Ajouter les fichiers
            $zip->addFile($files_archive, 'files.zip');
            
            // Ajouter un fichier README
            $readme = "Backup complet\n";
            $readme .= "Date: " . date('Y-m-d H:i:s') . "\n";
            $readme .= "Base: " . basename($db_file) . "\n";
            $readme .= "Fichiers: " . basename($files_archive) . "\n";
            $zip->addFromString('README.txt', $readme);
            
            $zip->close();
            
            return [
                'success' => true,
                'path' => $output_path . '.zip',
                'size' => filesize($output_path . '.zip')
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * Sauvegarde incrémentielle
 */
if (!function_exists('backupIncremental')) {
    function backupIncremental($filepath) {
        global $pdo;
        
        try {
            // Trouver la dernière sauvegarde incrémentielle
            $stmt = $pdo->prepare("
                SELECT filename, created_at 
                FROM backup_history 
                WHERE backup_type = 'incremental' 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $last_backup = $stmt->fetch();
            
            if (!$last_backup) {
                // Première sauvegarde incrémentielle = sauvegarde complète
                return backupDatabase($filepath);
            }
            
            // Récupérer les modifications depuis la dernière sauvegarde
            $last_date = $last_backup['created_at'];
            
            $sql = "-- Sauvegarde incrémentielle\n";
            $sql .= "-- Depuis: {$last_date}\n";
            $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            // Pour chaque table, récupérer les modifications
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $changes_count = 0;
            
            foreach ($tables as $table) {
                // Vérifier si la table a des timestamps
                $columns_stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
                $columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $has_timestamp = in_array('created_at', $columns) || in_array('updated_at', $columns);
                
                if ($has_timestamp) {
                    $where = "created_at > '{$last_date}' OR updated_at > '{$last_date}'";
                    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE {$where}");
                } else {
                    // Si pas de timestamp, prendre tout (solution simplifiée)
                    $stmt = $pdo->prepare("SELECT * FROM `{$table}`");
                }
                
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $sql .= "-- Table: {$table}\n";
                    
                    foreach ($rows as $row) {
                        // Générer un REPLACE INTO pour mettre à jour les données existantes
                        $columns = array_map(function($value) use ($pdo) {
                            if ($value === null) {
                                return 'NULL';
                            }
                            return $pdo->quote($value);
                        }, array_values($row));
                        
                        $sql .= "REPLACE INTO `{$table}` VALUES (" . implode(', ', $columns) . ");\n";
                        $changes_count++;
                    }
                    $sql .= "\n";
                }
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            if ($changes_count === 0) {
                return ['success' => false, 'error' => 'Aucune modification depuis la dernière sauvegarde'];
            }
            
            // Écrire dans le fichier
            if (file_put_contents($filepath, $sql) === false) {
                return ['success' => false, 'error' => 'Erreur d\'écriture du fichier'];
            }
            
            return [
                'success' => true,
                'path' => $filepath,
                'changes' => $changes_count
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * Restaurer une sauvegarde
 */
if (!function_exists('restoreBackup')) {
    function restoreBackup($backup_id) {
        global $pdo;
        
        try {
            // Récupérer les infos de la sauvegarde
            $stmt = $pdo->prepare("SELECT * FROM backup_history WHERE id = ?");
            $stmt->execute([$backup_id]);
            $backup = $stmt->fetch();
            
            if (!$backup) {
                return ['success' => false, 'error' => 'Sauvegarde non trouvée'];
            }
            
            $backup_dir = '../backups/';
            $filepath = $backup_dir . $backup['filename'];
            
            if (!file_exists($filepath)) {
                return ['success' => false, 'error' => 'Fichier de sauvegarde introuvable'];
            }
            
            // Vérifier le type de fichier
            $extension = pathinfo($filepath, PATHINFO_EXTENSION);
            
            if ($extension === 'gz') {
                // Décompresser
                $uncompressed = decompressFile($filepath);
                if (!$uncompressed['success']) {
                    return $uncompressed;
                }
                $sql_file = $uncompressed['path'];
            } else {
                $sql_file = $filepath;
            }
            
            // Lire et exécuter le fichier SQL
            $sql = file_get_contents($sql_file);
            
            if ($sql === false) {
                return ['success' => false, 'error' => 'Impossible de lire le fichier de sauvegarde'];
            }
            
            // Désactiver les contraintes de clés étrangères temporairement
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            
            // Exécuter les requêtes une par une
            $queries = explode(';', $sql);
            $executed_queries = 0;
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    try {
                        $pdo->exec($query);
                        $executed_queries++;
                    } catch (Exception $e) {
                        // Continuer malgré les erreurs mineures
                        error_log("Erreur lors de la restauration: " . $e->getMessage());
                    }
                }
            }
            
            // Réactiver les contraintes
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            
            // Nettoyer le fichier temporaire si nécessaire
            if ($extension === 'gz') {
                unlink($sql_file);
            }
            
            // Mettre à jour le statut
            $stmt = $pdo->prepare("
                UPDATE backup_history 
                SET last_restored_at = NOW(), restored_by = ? 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'] ?? null, $backup_id]);
            
            // Journaliser l'action
            logAction('RESTORE', 'backup_history', $backup_id, "Restauration sauvegarde: {$backup['filename']}");
            
            return [
                'success' => true,
                'backup_id' => $backup_id,
                'queries_executed' => $executed_queries,
                'filename' => $backup['filename']
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * Compresser un fichier avec GZIP
 */
if (!function_exists('compressFile')) {
    function compressFile($filepath) {
        if (!function_exists('gzopen')) {
            return false;
        }
        
        $gz_file = $filepath . '.gz';
        
        $fp = gzopen($gz_file, 'w9');
        if (!$fp) {
            return false;
        }
        
        $content = file_get_contents($filepath);
        if ($content === false) {
            gzclose($fp);
            return false;
        }
        
        gzwrite($fp, $content);
        gzclose($fp);
        
        return true;
    }
}

/**
 * Décompresser un fichier GZIP
 */
if (!function_exists('decompressFile')) {
    function decompressFile($gz_file) {
        if (!function_exists('gzopen')) {
            return ['success' => false, 'error' => 'Extension GZIP non disponible'];
        }
        
        $output_file = str_replace('.gz', '', $gz_file);
        
        $gz = gzopen($gz_file, 'rb');
        if (!$gz) {
            return ['success' => false, 'error' => 'Impossible d\'ouvrir le fichier compressé'];
        }
        
        $fp = fopen($output_file, 'wb');
        if (!$fp) {
            gzclose($gz);
            return ['success' => false, 'error' => 'Impossible de créer le fichier de sortie'];
        }
        
        while (!gzeof($gz)) {
            fwrite($fp, gzread($gz, 4096));
        }
        
        fclose($fp);
        gzclose($gz);
        
        return ['success' => true, 'path' => $output_file];
    }
}

/**
 * Télécharger une sauvegarde
 */
if (!function_exists('downloadBackup')) {
    function downloadBackup($backup_id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT filename FROM backup_history WHERE id = ?");
            $stmt->execute([$backup_id]);
            $backup = $stmt->fetch();
            
            if (!$backup) {
                return ['success' => false, 'error' => 'Sauvegarde non trouvée'];
            }
            
            $backup_dir = '../backups/';
            $filepath = $backup_dir . $backup['filename'];
            
            if (!file_exists($filepath)) {
                return ['success' => false, 'error' => 'Fichier de sauvegarde introuvable'];
            }
            
            // Journaliser l'action
            logAction('DOWNLOAD', 'backup_history', $backup_id, "Téléchargement sauvegarde: {$backup['filename']}");
            
            return [
                'success' => true,
                'filepath' => $filepath,
                'filename' => $backup['filename']
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * Vérifier l'intégrité d'une sauvegarde
 */
if (!function_exists('verifyBackup')) {
    function verifyBackup($backup_id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM backup_history WHERE id = ?");
            $stmt->execute([$backup_id]);
            $backup = $stmt->fetch();
            
            if (!$backup) {
                return ['success' => false, 'error' => 'Sauvegarde non trouvée'];
            }
            
            $backup_dir = '../backups/';
            $filepath = $backup_dir . $backup['filename'];
            
            $file_exists = file_exists($filepath);
            $checks = [
                'file_exists' => $file_exists,
                'file_size' => $file_exists ? filesize($filepath) : 0,
                'file_readable' => $file_exists ? is_readable($filepath) : false,
                'backup_size_match' => $file_exists ? (filesize($filepath) / 1024 / 1024 >= $backup['size_mb'] * 0.9) : false
            ];
            
            $all_ok = !in_array(false, $checks, true);
            
            return [
                'success' => $all_ok,
                'checks' => $checks,
                'backup' => $backup
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

/**
 * Nettoyer les anciennes sauvegardes
 */
if (!function_exists('cleanupOldBackups')) {
    function cleanupOldBackups($retention_days = 30) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT id, filename, created_at 
                FROM backup_history 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$retention_days]);
            $old_backups = $stmt->fetchAll();
            
            $deleted = 0;
            $errors = [];
            $backup_dir = '../backups/';
            
            foreach ($old_backups as $backup) {
                $filepath = $backup_dir . $backup['filename'];
                
                // Supprimer le fichier physique
                if (file_exists($filepath) && !unlink($filepath)) {
                    $errors[] = "Impossible de supprimer: " . $backup['filename'];
                    continue;
                }
                
                // Supprimer l'entrée en base
                $del_stmt = $pdo->prepare("DELETE FROM backup_history WHERE id = ?");
                if ($del_stmt->execute([$backup['id']])) {
                    $deleted++;
                    
                    // Journaliser l'action
                    logAction('CLEANUP', 'backup_history', $backup['id'], 
                             "Nettoyage automatique sauvegarde: {$backup['filename']}");
                } else {
                    $errors[] = "Erreur base de données pour: " . $backup['filename'];
                }
            }
            
            return [
                'success' => empty($errors),
                'deleted' => $deleted,
                'errors' => $errors,
                'total_checked' => count($old_backups)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

// Fonction pour vérifier les permissions
if (!function_exists('checkPermission')) {
    function checkPermission($module, $action, $user_role = null) {
        global $pdo;
        
        if ($user_role === null) {
            $user_role = $_SESSION['role'] ?? null;
        }
        
        // L'admin a toujours tous les droits
        if ($user_role === 'admin') {
            return true;
        }
        
        try {
            // Chercher le rôle dans la base
            $stmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = ?");
            $stmt->execute([$user_role]);
            $role = $stmt->fetch();
            
            if ($role) {
                // Vérifier la permission dans la base
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM role_permissions 
                    WHERE role_id = ? AND module = ? AND action = ?
                ");
                $stmt->execute([$role['id'], $module, $action]);
                return $stmt->fetchColumn() > 0;
            } else {
                // Vérifier les permissions par défaut
                $default_permissions = [
                    'docteur' => [
                        'patients' => ['view', 'create', 'edit'],
                        'consultations' => ['view', 'create', 'edit'],
                        'prescriptions' => ['view', 'create', 'edit', 'print'],
                        'rendezvous' => ['view', 'create', 'edit'],
                        'documents' => ['view', 'create', 'edit']
                    ],
                    'secretaire' => [
                        'patients' => ['view', 'create'],
                        'rendezvous' => ['view', 'create', 'edit', 'delete'],
                        'documents' => ['view', 'create']
                    ],
                    'assistant' => [
                        'patients' => ['view'],
                        'consultations' => ['view'],
                        'rendezvous' => ['view'],
                        'documents' => ['view']
                    ]
                ];
                
                if (isset($default_permissions[$user_role][$module])) {
                    return in_array($action, $default_permissions[$user_role][$module]);
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Erreur vérification permission: " . $e->getMessage());
            return false;
        }
    }
}

// Fonction pour rediriger si pas de permission
if (!function_exists('requirePermission')) {
    function requirePermission($module, $action) {
        if (!checkPermission($module, $action)) {
            $_SESSION['error'] = "Vous n'avez pas la permission d'accéder à cette page";
            header('Location: ../index.php');
            exit();
        }
    }
}

/**
 * Obtenir les statistiques des sauvegardes
 */
if (!function_exists('getBackupStats')) {
    function getBackupStats() {
        global $pdo;
        
        try {
            $stats = $pdo->query("
                SELECT 
                    COUNT(*) as total_backups,
                    SUM(size_mb) as total_size_mb,
                    AVG(size_mb) as avg_size_mb,
                    MIN(created_at) as oldest_backup,
                    MAX(created_at) as latest_backup,
                    backup_type,
                    COUNT(*) as type_count
                FROM backup_history 
                GROUP BY backup_type
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            $disk_usage = 0;
            $backup_dir = '../backups/';
            if (is_dir($backup_dir)) {
                $files = glob($backup_dir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $disk_usage += filesize($file);
                    }
                }
            }
            
            $disk_free = disk_free_space($backup_dir) ?: 0;
            
            return [
                'success' => true,
                'stats' => $stats,
                'disk_usage_mb' => round($disk_usage / 1024 / 1024, 2),
                'disk_free_mb' => round($disk_free / 1024 / 1024, 2)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}


/**
 * Journaliser une action
 */
// function logAction($action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null, $message = null) {
//     global $pdo;
    
//     try {
//         $stmt = $pdo->prepare("
//             INSERT INTO audit_logs 
//             (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, message, created_at)
//             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
//         ");
        
//         $stmt->execute([
//             $_SESSION['user_id'] ?? null,
//             $action,
//             $tableName,
//             $recordId,
//             $oldValues ? json_encode($oldValues) : null,
//             $newValues ? json_encode($newValues) : null,
//             $_SERVER['REMOTE_ADDR'],
//             $_SERVER['HTTP_USER_AGENT'],
//             $message
//         ]);
        
//         return $pdo->lastInsertId();
        
//     } catch (Exception $e) {
//         error_log("Erreur de journalisation: " . $e->getMessage());
//         return false;
//     }
// }

/**
 * Journaliser une connexion
 */
function logLogin($userId, $success = true, $message = null) {
    global $pdo;
    
    $action = $success ? 'login_success' : 'login_failed';
    
    $stmt = $pdo->prepare("
        INSERT INTO login_logs 
        (user_id, action, ip_address, user_agent, success, message, login_time)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $userId,
        $action,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        $success ? 1 : 0,
        $message
    ]);
    
    // Mettre à jour la dernière connexion
    if ($success) {
        $pdo->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?")
            ->execute([$userId]);
    }
}

/**
 * Journaliser une erreur
 */
function logError($errorType, $message, $file = null, $line = null, $data = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO error_logs 
        (error_type, message, file, line, error_data, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $errorType,
        $message,
        $file,
        $line,
        $data ? json_encode($data) : null,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
}

/**
 * Purger les anciens logs
 */
function purgeOldLogs($days = 30) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Archiver les logs avant suppression
        $pdo->prepare("
            INSERT INTO log_archive 
            SELECT *, NOW() as archived_at 
            FROM audit_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ")->execute([$days]);
        
        // Supprimer les logs archivés
        $stmt = $pdo->prepare("
            DELETE FROM audit_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        $deleted = $stmt->rowCount();
        
        // Purger les logs de connexion
        $pdo->prepare("
            DELETE FROM login_logs 
            WHERE login_time < DATE_SUB(NOW(), INTERVAL ? DAY)
        ")->execute([$days]);
        
        // Purger les logs d'erreur
        $pdo->prepare("
            DELETE FROM error_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ")->execute([$days]);
        
        $pdo->commit();
        
        return $deleted;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logError('PURGE_ERROR', $e->getMessage(), __FILE__, __LINE__);
        return false;
    }
}

// Initialiser la connexion
$db = Database::getInstance();
$pdo = $db->getConnection();
?>









<?php
// config/database.php

// session_start();

// class Database {
//     private static $instance = null;
//     private $connection;
    
//     private function __construct() {
//         try {
//             $this->connection = new PDO(
//                 'mysql:host=localhost;dbname=gestion_medicale;charset=utf8mb4',
//                 'root',
//                 '',
//                 [
//                     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
//                     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
//                     PDO::ATTR_EMULATE_PREPARES => false
//                 ]
//             );
//         } catch (PDOException $e) {
//             die("Erreur de connexion: " . $e->getMessage());
//         }
//     }
    
//     public static function getInstance() {
//         if (self::$instance === null) {
//             self::$instance = new self();
//         }
//         return self::$instance;
//     }
    
//     public function getConnection() {
//         return $this->connection;
//     }
// }

// // Fonctions utilitaires
// function sanitize($data) {
//     if (is_array($data)) {
//         return array_map('sanitize', $data);
//     }
//     return htmlspecialchars(strip_tags(trim($data)));
// }

// function isLoggedIn() {
//     return isset($_SESSION['user_id']) && isset($_SESSION['role']);
// }

// function checkRole($requiredRole) {
//     if (!isLoggedIn()) {
//         header('Location: ../login.php');
//         exit();
//     }
    
//     if ($_SESSION['role'] !== $requiredRole) {
//         $_SESSION['error'] = "Accès non autorisé";
//         header('Location: ../index.php');
//         exit();
//     }
// }

// function getUserRole() {
//     return $_SESSION['role'] ?? null;
// }

// function isAdmin() {
//     return getUserRole() === 'admin';
// }

// function isDocteur() {
//     return getUserRole() === 'docteur';
// }

// function isSecretaire() {
//     return getUserRole() === 'secretaire';
// }

// function isAssistant() {
//     return getUserRole() === 'assistant';
// }

// function formatDate($date, $format = 'd/m/Y') {
//     return date($format, strtotime($date));
// }

// function generateReference($prefix) {
//     return $prefix . '-' . date('Ymd') . '-' . uniqid();
// }

// function calculateAge($birthdate) {
//     $birthDate = new DateTime($birthdate);
//     $today = new DateTime();
//     return $today->diff($birthDate)->y;
// }

// // config/database.php - Ajouts pour l'authentification

// // ... code existant ...

// // Fonction pour générer un token sécurisé
// function generateToken($length = 32) {
//     return bin2hex(random_bytes($length));
// }

// // Fonction pour valider un email
// function isValidEmail($email) {
//     return filter_var($email, FILTER_VALIDATE_EMAIL);
// }

// // Fonction pour valider un mot de passe
// function isValidPassword($password) {
//     return strlen($password) >= 8 &&
//            preg_match('/[A-Z]/', $password) &&
//            preg_match('/[a-z]/', $password) &&
//            preg_match('/[0-9]/', $password);
// }

// // Fonction pour vérifier la force du mot de passe
// function getPasswordStrength($password) {
//     $strength = 0;
    
//     if (strlen($password) >= 8) $strength++;
//     if (preg_match('/[A-Z]/', $password)) $strength++;
//     if (preg_match('/[a-z]/', $password)) $strength++;
//     if (preg_match('/[0-9]/', $password)) $strength++;
//     if (preg_match('/[^A-Za-z0-9]/', $password)) $strength++;
    
//     return $strength;
// }

// // Fonction pour envoyer un email
// function sendEmail($to, $subject, $body, $from = 'no-reply@medsystem.fr') {
//     $headers = "MIME-Version: 1.0" . "\r\n";
//     $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
//     $headers .= "From: $from" . "\r\n";
//     $headers .= "Reply-To: $from" . "\r\n";
//     $headers .= "X-Mailer: PHP/" . phpversion();
    
//     return mail($to, $subject, $body, $headers);
// }

// // Fonction pour nettoyer l'input
// function cleanInput($data) {
//     $data = trim($data);
//     $data = stripslashes($data);
//     $data = htmlspecialchars($data);
//     return $data;
// }

// // Fonction pour vérifier le taux de tentatives de connexion
// function checkLoginAttempts($email, $limit = 5, $timeframe = 900) {
//     global $pdo;
    
//     $stmt = $pdo->prepare("
//         SELECT COUNT(*) as attempts 
//         FROM login_logs 
//         WHERE user_id IS NULL 
//         AND ip_address = ? 
//         AND login_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
//     ");
//     $stmt->execute([$_SERVER['REMOTE_ADDR'], $timeframe]);
//     $result = $stmt->fetch();
    
//     return $result['attempts'] < $limit;
// }

// // Fonction pour générer un mot de passe aléatoire
// function generateRandomPassword($length = 12) {
//     $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
//     $password = '';
    
//     for ($i = 0; $i < $length; $i++) {
//         $password .= $chars[random_int(0, strlen($chars) - 1)];
//     }
    
//     return $password;
// }

// // Fonction pour formater une date relative
// function timeAgo($datetime) {
//     $time = time() - strtotime($datetime);
    
//     if ($time < 60) {
//         return 'à l\'instant';
//     } elseif ($time < 3600) {
//         return floor($time / 60) . ' min';
//     } elseif ($time < 86400) {
//         return floor($time / 3600) . ' h';
//     } elseif ($time < 604800) {
//         return floor($time / 86400) . ' j';
//     } else {
//         return date('d/m/Y', strtotime($datetime));
//     }
// }



// // Initialiser la connexion
// $db = Database::getInstance();
// $pdo = $db->getConnection();


define('SITE_NAME', 'Système de Gestion Médicale');
define('SITE_URL', 'http://localhost/medical_system/');
define('BASE_URL', 'http://localhost/medical_system/');

require_once 'database.php';

function redirect($url) {
    header("Location: $url");
    exit();
}


function generatePatientCode() {
    return 'PAT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Fonctions helpers
function getTypeName($type) {
    $types = [
        'rdv'          => 'Rendez-vous',
        'consultation' => 'Consultation',
        'patient'      => 'Patient',
        'system'       => 'Système',
        'urgence'      => 'Urgence',
        'message'      => 'Message',
        'info'         => 'Information'
    ];
    return $types[$type] ?? ucfirst($type);
}

function getTypeIcon($type) {
    $icons = [
        'rdv'          => 'fas fa-calendar-alt',
        'consultation' => 'fas fa-stethoscope',
        'patient'      => 'fas fa-user',
        'system'       => 'fas fa-cog',
        'urgence'      => 'fas fa-ambulance',
        'message'      => 'fas fa-envelope',
        'info'         => 'fas fa-info-circle'
    ];
    return $icons[$type] ?? 'fas fa-bell';
}

function getTimeAgo($timestamp) {
    $diff = time() - strtotime($timestamp);
    if ($diff < 60) return 'À l\'instant';
    if ($diff < 3600) return 'Il y a ' . floor($diff/60) . ' min';
    if ($diff < 86400) return 'Il y a ' . floor($diff/3600) . ' h';
    if ($diff < 604800) return 'Il y a ' . floor($diff/86400) . ' j';
    return date('d/m/Y', strtotime($timestamp));
}


?>




