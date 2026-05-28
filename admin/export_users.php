<?php
// admin/export_users.php
require_once '../config/database.php';
session_start();
checkRole('admin');

// Récupérer les filtres
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$statut = $_GET['statut'] ?? '';

// Construire la requête
$sql = "SELECT u.* FROM utilisateurs u WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%" . trim($search) . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($role)) {
    $sql .= " AND u.role = ?";
    $params[] = $role;
}

if (!empty($statut)) {
    $sql .= " AND u.statut = ?";
    $params[] = $statut;
}

$sql .= " ORDER BY u.nom ASC, u.prenom ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Définir les en-têtes pour le téléchargement CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=utilisateurs_' . date('Y-m-d_H-i') . '.csv');

// Créer le fichier CSV
$output = fopen('php://output', 'w');

// En-têtes CSV
fputcsv($output, [
    'ID',
    'Nom',
    'Prénom',
    'Email',
    'Rôle',
    'Spécialité',
    'Téléphone',
    'Statut',
    'Date création',
    'Dernière connexion',
    'Dernière modification'
], ';');

// Données
foreach ($users as $user) {
    fputcsv($output, [
        $user['id'],
        $user['nom'],
        $user['prenom'],
        $user['email'],
        $user['role'],
        $user['specialite'] ?? '',
        $user['telephone'] ?? '',
        $user['statut'],
        date('d/m/Y H:i', strtotime($user['date_creation'])),
        !empty($user['derniere_connexion']) ? date('d/m/Y H:i', strtotime($user['derniere_connexion'])) : '',
        !empty($user['date_modification']) ? date('d/m/Y H:i', strtotime($user['date_modification'])) : ''
    ], ';');
}

fclose($output);
exit();