-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le :  Dim 24 mai 2026 à 15:06
-- Version du serveur :  10.1.28-MariaDB
-- Version de PHP :  7.1.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `gestion_medicale`
--

DELIMITER $$
--
-- Procédures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_rapport_patient` (IN `patient_id_param` INT)  BEGIN
    -- Informations patient
    SELECT * FROM patients WHERE id = patient_id_param;
    
    -- Consultations
    SELECT c.*, 
           CONCAT(u.prenom, ' ', u.nom) as docteur,
           u.specialite
    FROM consultations c
    JOIN utilisateurs u ON c.docteur_id = u.id
    WHERE c.patient_id = patient_id_param
    ORDER BY c.date_consultation DESC;
    
    -- Pathologies
    SELECT pp.*, 
           pat.nom as pathologie_nom,
           pat.gravite as pathologie_gravite
    FROM patient_pathologie pp
    JOIN pathologies pat ON pp.pathologie_id = pat.id
    WHERE pp.patient_id = patient_id_param;
    
    -- Prescriptions actives
    SELECT * FROM prescriptions 
    WHERE patient_id = patient_id_param 
    AND statut = 'active'
    ORDER BY date_prescription DESC;
    
    -- Rendez-vous à venir
    SELECT r.*,
           CONCAT(d.prenom, ' ', d.nom) as docteur
    FROM rendez_vous r
    JOIN utilisateurs d ON r.docteur_id = d.id
    WHERE r.patient_id = patient_id_param
    AND r.date_rdv >= CURDATE()
    AND r.statut = 'confirme'
    ORDER BY r.date_rdv ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_recherche_patients` (IN `search_term` VARCHAR(255), IN `ville_param` VARCHAR(100), IN `age_min` INT, IN `age_max` INT, IN `sexe_param` VARCHAR(1))  BEGIN
    SELECT 
        p.*,
        TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) as age,
        (SELECT COUNT(*) FROM consultations WHERE patient_id = p.id) as nb_consultations,
        (SELECT MAX(date_consultation) FROM consultations WHERE patient_id = p.id) as derniere_consultation
    FROM patients p
    WHERE p.statut = 'actif'
    AND (
        p.nom LIKE CONCAT('%', search_term, '%')
        OR p.prenom LIKE CONCAT('%', search_term, '%')
        OR p.code_patient LIKE CONCAT('%', search_term, '%')
        OR p.telephone LIKE CONCAT('%', search_term, '%')
        OR p.email LIKE CONCAT('%', search_term, '%')
    )
    AND (ville_param IS NULL OR p.ville = ville_param)
    AND (sexe_param IS NULL OR p.sexe = sexe_param)
    AND (age_min IS NULL OR TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) >= age_min)
    AND (age_max IS NULL OR TIMESTAMPDIFF(YEAR, p.date_naissance, CURDATE()) <= age_max)
    ORDER BY p.nom, p.prenom;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_statistiques_mensuelles` (IN `annee` INT, IN `mois` INT)  BEGIN
    SELECT 
        'Consultations' as type,
        COUNT(*) as total
    FROM consultations
    WHERE YEAR(date_consultation) = annee AND MONTH(date_consultation) = mois
    
    UNION ALL
    
    SELECT 
        'Nouveaux patients',
        COUNT(*)
    FROM patients
    WHERE YEAR(date_enregistrement) = annee AND MONTH(date_enregistrement) = mois
    
    UNION ALL
    
    SELECT 
        'Rendez-vous',
        COUNT(*)
    FROM rendez_vous
    WHERE YEAR(date_rdv) = annee AND MONTH(date_rdv) = mois
    
    UNION ALL
    
    SELECT 
        'Prescriptions',
        COUNT(*)
    FROM prescriptions
    WHERE YEAR(date_prescription) = annee AND MONTH(date_prescription) = mois;
END$$

--
-- Fonctions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_age_patient` (`patient_id` INT) RETURNS INT(11) READS SQL DATA
    DETERMINISTIC
BEGIN
    DECLARE age INT;
    
    SELECT TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()) INTO age
    FROM patients 
    WHERE id = patient_id;
    
    RETURN age;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_count_upcoming_appointments` (`docteur_id` INT) RETURNS INT(11) READS SQL DATA
    DETERMINISTIC
BEGIN
    DECLARE appointment_count INT;
    
    SELECT COUNT(*) INTO appointment_count
    FROM rendez_vous
    WHERE docteur_id = docteur_id
    AND date_rdv >= CURDATE()
    AND statut = 'confirme';
    
    RETURN appointment_count;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_has_pending_consultations` (`patient_id` INT) RETURNS TINYINT(1) READS SQL DATA
    DETERMINISTIC
BEGIN
    DECLARE pending_count INT;
    
    SELECT COUNT(*) INTO pending_count
    FROM consultations
    WHERE patient_id = patient_id
    AND statut IN ('planifie', 'en_cours');
    
    RETURN pending_count > 0;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text,
  `new_values` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 2, 'UPDATE_PROFILE', 'utilisateurs', 2, NULL, NULL, '::1', NULL, '2025-12-22 19:11:24'),
(2, 6, 'UPDATE_PROFILE', 'utilisateurs', 6, NULL, NULL, '::1', NULL, '2025-12-23 12:51:22'),
(3, 1, 'DOWNLOAD_BACKUP', 'backup_history', 1, NULL, NULL, NULL, NULL, '2025-12-23 22:58:03'),
(4, 10, 'REGISTER', 'utilisateurs', 10, NULL, NULL, '::1', NULL, '2025-12-24 22:09:46'),
(5, 1, 'UPDATE', 'utilisateurs', 10, NULL, NULL, '::1', NULL, '2025-12-24 22:48:07'),
(6, 1, 'UPDATE', 'utilisateurs', 9, NULL, NULL, '::1', NULL, '2025-12-24 22:50:17'),
(7, 1, 'UPDATE', 'utilisateurs', 8, NULL, NULL, '::1', NULL, '2025-12-24 22:50:22'),
(8, 1, 'UPDATE', 'utilisateurs', 8, NULL, NULL, '::1', NULL, '2025-12-24 22:50:57'),
(9, 1, 'UPDATE', 'utilisateurs', 9, NULL, NULL, '::1', NULL, '2025-12-24 22:51:33'),
(10, 1, 'CREATE', 'utilisateurs', 14, NULL, NULL, '::1', NULL, '2025-12-24 22:52:18'),
(11, 1, 'UPDATE', 'utilisateurs', 14, NULL, NULL, '::1', NULL, '2025-12-24 22:52:50'),
(12, 14, 'PASSWORD_RESET', 'utilisateurs', 14, NULL, NULL, '::1', NULL, '2025-12-25 15:06:43'),
(13, 1, 'UPDATE', 'utilisateurs', 8, NULL, NULL, '::1', NULL, '2025-12-25 22:12:50'),
(14, 1, 'UPDATE', 'utilisateurs', 8, NULL, NULL, '::1', NULL, '2025-12-25 22:12:56'),
(15, 1, 'UPDATE', 'utilisateurs', 1, NULL, NULL, '::1', NULL, '2025-12-26 11:38:59'),
(16, 1, 'UPDATE', 'utilisateurs', 4, NULL, NULL, '::1', NULL, '2025-12-26 11:39:08'),
(17, 1, 'UPDATE', 'utilisateurs', 4, NULL, NULL, '::1', NULL, '2025-12-26 11:39:12'),
(18, 1, 'PASSWORD_RESET', 'utilisateurs', 1, NULL, NULL, '::1', NULL, '2026-05-20 15:12:03'),
(19, 4, 'PASSWORD_RESET', 'utilisateurs', 4, NULL, NULL, '::1', NULL, '2026-05-20 15:18:57'),
(20, 1, 'PASSWORD_RESET', 'utilisateurs', 1, NULL, NULL, '::1', NULL, '2026-05-21 22:51:18'),
(21, 15, 'REGISTER', 'utilisateurs', 15, NULL, NULL, '::1', NULL, '2026-05-22 11:14:43'),
(22, 1, 'UPDATE', 'utilisateurs', 15, NULL, NULL, '::1', NULL, '2026-05-22 11:15:49'),
(23, 4, 'UPDATE_PROFILE', 'utilisateurs', 4, NULL, NULL, '::1', NULL, '2026-05-24 13:03:09');

--
-- Déclencheurs `audit_logs`
--
DELIMITER $$
CREATE TRIGGER `before_audit_log_delete` BEFORE DELETE ON `audit_logs` FOR EACH ROW BEGIN
    INSERT INTO log_archive (
        original_id, user_id, action, table_name, record_id,
        old_values, new_values, ip_address, user_agent, message,
        created_at, archived_at
    ) VALUES (
        OLD.id, OLD.user_id, OLD.action, OLD.table_name, OLD.record_id,
        OLD.old_values, OLD.new_values, OLD.ip_address, OLD.user_agent,
        OLD.created_at, NOW()
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `backup_history`
--

CREATE TABLE `backup_history` (
  `id` int(11) NOT NULL,
  `backup_type` varchar(50) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `size_mb` decimal(10,2) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `backup_history`
--

INSERT INTO `backup_history` (`id`, `backup_type`, `filename`, `size_mb`, `created_by`, `created_at`) VALUES
(1, 'manual', 'backup_2025-12-22_17-19-06.sql', '0.03', 1, '2025-12-22 16:19:06');

-- --------------------------------------------------------

--
-- Structure de la table `backup_logs`
--

CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `backup_id` int(11) DEFAULT NULL,
  `execution_time` datetime NOT NULL,
  `status` enum('success','failed','partial') NOT NULL,
  `error_message` text,
  `duration_seconds` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `backup_schedule`
--

CREATE TABLE `backup_schedule` (
  `id` int(11) NOT NULL,
  `schedule_name` varchar(100) NOT NULL,
  `backup_type` enum('complete','database','files','incremental') NOT NULL,
  `frequency` enum('daily','weekly','monthly','custom') NOT NULL,
  `execution_time` time NOT NULL,
  `enabled` tinyint(1) DEFAULT '1',
  `retention_days` int(11) DEFAULT '30',
  `last_execution` datetime DEFAULT NULL,
  `next_execution` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `backup_settings`
--

CREATE TABLE `backup_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `backup_settings`
--

INSERT INTO `backup_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
(1, 'backup_directory', '../backups/', 'string', 'Répertoire de stockage des sauvegardes', NULL),
(2, 'max_storage_mb', '1024', 'integer', 'Espace maximum en MB', NULL),
(3, 'compression', 'gzip', 'string', 'Méthode de compression (gzip, zip, none)', NULL),
(4, 'encryption_enabled', 'false', 'boolean', 'Activer le chiffrement des sauvegardes', NULL),
(5, 'auto_cleanup_enabled', 'true', 'boolean', 'Nettoyage automatique des anciennes sauvegardes', NULL),
(6, 'retention_days', '30', 'integer', 'Nombre de jours de rétention', NULL),
(7, 'notify_on_error', 'true', 'boolean', 'Notification en cas d\'erreur', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `consultations`
--

CREATE TABLE `consultations` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `docteur_id` int(11) NOT NULL,
  `assistant_id` int(11) DEFAULT NULL,
  `date_consultation` datetime NOT NULL,
  `duree` int(11) DEFAULT '30' COMMENT 'Durée en minutes',
  `type_consultation` enum('premiere','suivi','urgence','controle') DEFAULT 'suivi',
  `motif` text,
  `histoire_maladie` text,
  `examen_clinique` text,
  `examen_complementaire` text,
  `diagnostic` text,
  `diagnostic_detail` text COMMENT 'Structure JSON du diagnostic',
  `traitement` text,
  `ordonnance` text COMMENT 'Structure JSON de l''ordonnance',
  `recommandations` text,
  `notes` text,
  `statut` enum('planifie','en_cours','termine','annule','reporte') DEFAULT 'planifie',
  `facturee` tinyint(1) DEFAULT '0',
  `urgence` tinyint(1) DEFAULT '0',
  `confidentialite` enum('normal','confidentiel','tres_confidentiel') DEFAULT 'normal',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `consultations`
--

INSERT INTO `consultations` (`id`, `reference`, `patient_id`, `docteur_id`, `assistant_id`, `date_consultation`, `duree`, `type_consultation`, `motif`, `histoire_maladie`, `examen_clinique`, `examen_complementaire`, `diagnostic`, `diagnostic_detail`, `traitement`, `ordonnance`, `recommandations`, `notes`, `statut`, `facturee`, `urgence`, `confidentialite`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'CONS-202401-0001', 1, 2, NULL, '2024-01-15 09:00:00', 30, 'controle', 'Contrôle tension artérielle', NULL, NULL, NULL, 'Hypertension stable sous traitement', NULL, 'Continuer Amlodipine 5mg, surveillance mensuelle', NULL, NULL, NULL, 'termine', 0, 0, 'normal', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 4),
(2, 'CONS-202401-0002', 2, 2, NULL, '2024-01-16 10:30:00', 30, 'urgence', 'Douleurs thoraciques', NULL, NULL, NULL, 'Angor stable, pas d\'urgence', NULL, 'Repos, ECG de contrôle, consultation cardiologue', NULL, NULL, NULL, 'termine', 0, 0, 'normal', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 4),
(3, 'CONS-202401-0003', 3, 5, NULL, '2024-01-17 14:00:00', 30, 'premiere', 'Éruption cutanée', NULL, NULL, NULL, 'Eczéma atopique', NULL, 'Crème corticoïde, émollients, éviction allergènes', NULL, NULL, NULL, 'termine', 0, 0, 'normal', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 6),
(4, 'CONS-202401-0004', 4, 2, NULL, '2024-01-18 11:00:00', 30, 'suivi', 'Suivi diabète', '', '', '', 'Diabète équilibré', NULL, 'Continuer Metformine 1000mg, régime contrôlé', NULL, '', '', 'termine', 0, 1, 'normal', '2025-12-19 22:41:53', '2025-12-23 10:47:25', 4),
(5, 'CONS-202401-0005', 5, 5, NULL, '2024-01-19 15:30:00', 30, '', 'Migraines récurrentes', NULL, NULL, NULL, 'Migraines avec aura', NULL, 'Sumatriptan 50mg si crise, propranolol 40mg/j en prévention', NULL, NULL, NULL, 'termine', 0, 0, 'normal', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 6),
(6, 'CONS-202401-0006', 6, 7, NULL, '2024-01-20 10:00:00', 30, 'premiere', 'Examen médical général', NULL, NULL, NULL, 'Bon état de santé général', NULL, 'Aucun traitement nécessaire', NULL, NULL, NULL, 'termine', 0, 0, 'normal', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 3),
(7, 'CONS-202401-0007', 7, 7, NULL, '2024-01-21 14:30:00', 30, 'controle', 'Contrôle allergies', NULL, NULL, NULL, 'Allergies saisonnières confirmées', NULL, 'Antihistaminiques, éviction des allergènes', NULL, NULL, NULL, 'termine', 0, 0, 'normal', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 3);

--
-- Déclencheurs `consultations`
--
DELIMITER $$
CREATE TRIGGER `before_consultation_insert` BEFORE INSERT ON `consultations` FOR EACH ROW BEGIN
    DECLARE annee_mois VARCHAR(6);
    DECLARE prochain_num INT;
    
    SET annee_mois = DATE_FORMAT(NOW(), '%Y%m');
    
    SELECT COALESCE(MAX(SUBSTRING(reference, 13)), 0) + 1 INTO prochain_num
    FROM consultations 
    WHERE reference LIKE CONCAT('CONS-', annee_mois, '-%');
    
    IF prochain_num IS NULL THEN
        SET prochain_num = 1;
    END IF;
    
    SET NEW.reference = CONCAT('CONS-', annee_mois, '-', LPAD(prochain_num, 4, '0'));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `docteur_specialite`
--

CREATE TABLE `docteur_specialite` (
  `id` int(11) NOT NULL,
  `docteur_id` int(11) NOT NULL,
  `specialite_id` int(11) NOT NULL,
  `principal` tinyint(1) DEFAULT '0',
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `docteur_specialite`
--

INSERT INTO `docteur_specialite` (`id`, `docteur_id`, `specialite_id`, `principal`, `date_debut`, `date_fin`, `notes`, `created_at`) VALUES
(1, 2, 1, 1, NULL, NULL, NULL, '2025-12-19 22:41:53'),
(2, 5, 2, 1, NULL, NULL, NULL, '2025-12-19 22:41:53'),
(3, 7, 10, 1, NULL, NULL, NULL, '2025-12-19 22:41:53'),
(4, 5, 10, 0, NULL, NULL, NULL, '2025-12-19 22:41:53');

-- --------------------------------------------------------

--
-- Structure de la table `documents_medicaux`
--

CREATE TABLE `documents_medicaux` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `docteur_id` int(11) DEFAULT NULL,
  `consultation_id` int(11) DEFAULT NULL,
  `type` enum('ordonnance','certificat','resultat_analyse','compte_rendu','imagerie','autre') NOT NULL,
  `titre` varchar(200) NOT NULL,
  `description` text,
  `fichier_path` varchar(255) DEFAULT NULL,
  `fichier_nom` varchar(255) DEFAULT NULL,
  `taille` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `confidentialite` enum('normal','confidentiel','tres_confidentiel') DEFAULT 'normal',
  `valide_jusqu` date DEFAULT NULL,
  `metadata` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `documents_medicaux`
--

INSERT INTO `documents_medicaux` (`id`, `patient_id`, `docteur_id`, `consultation_id`, `type`, `titre`, `description`, `fichier_path`, `fichier_nom`, `taille`, `mime_type`, `confidentialite`, `valide_jusqu`, `metadata`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 1, 'compte_rendu', 'Compte rendu consultation cardiologie', 'Consultation de contrôle tension artérielle', 'documents/6949e5288d904_Handwritten-Notes-53-1024x576.png', 'cr_20240115_001.pdf', NULL, NULL, 'normal', NULL, NULL, 2, '2025-12-19 22:41:53', '2025-12-22 23:41:12'),
(2, 2, 2, 2, 'ordonnance', 'Ordonnance médicale', 'Médicaments pour douleurs thoraciques', 'documents/6949d4504cef8_base python.png', 'ordo_20240116_001.pdf', NULL, NULL, 'normal', '0000-00-00', NULL, 2, '2025-12-19 22:41:53', '2025-12-22 22:29:47'),
(3, 3, 5, 3, 'resultat_analyse', 'Résultats tests allergiques', 'Tests cutanés allergologiques', NULL, 'tests_allergie_001.pdf', NULL, NULL, 'confidentiel', NULL, NULL, 5, '2025-12-19 22:41:53', '2025-12-22 22:45:49'),
(4, 5, 5, 5, 'certificat', 'Certificat médical', 'Certificat pour arrêt de travail', NULL, 'certif_20240119_001.pdf', NULL, NULL, 'confidentiel', NULL, NULL, 5, '2025-12-19 22:41:53', '2025-12-22 22:45:49');

-- --------------------------------------------------------

--
-- Structure de la table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(10) NOT NULL,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `nom` varchar(200) NOT NULL,
  `categorie` varchar(100) NOT NULL,
  `marque` varchar(100) DEFAULT NULL,
  `modele` varchar(100) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `date_acquisition` date DEFAULT NULL,
  `valeur` decimal(10,2) DEFAULT '0.00',
  `localisation` varchar(100) DEFAULT NULL,
  `statut` enum('actif','maintenance','hors_service','reserve','supprime') DEFAULT 'actif',
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `equipment`
--

INSERT INTO `equipment` (`id`, `nom`, `categorie`, `marque`, `modele`, `numero_serie`, `date_acquisition`, `valeur`, `localisation`, `statut`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'diagnostic', 'ddd', '43d', '3333', '2026-01-04', '100.00', 'bujumbura', 'supprime', 'operation', 14, '2026-01-04 16:03:41', '2026-01-04 16:17:04');

-- --------------------------------------------------------

--
-- Structure de la table `equipment_history`
--

CREATE TABLE `equipment_history` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text,
  `performed_by` int(11) DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `equipment_history`
--

INSERT INTO `equipment_history` (`id`, `equipment_id`, `action`, `details`, `performed_by`, `performed_at`) VALUES
(1, 1, 'Ajout', 'Équipement ajouté: Admin (diagnostic)', 14, '2026-01-04 16:03:41'),
(2, 1, 'Suppression', 'Équipement supprimé: Admin (3333)', 14, '2026-01-04 16:11:11'),
(3, 1, 'Suppression', 'Équipement supprimé: Admin (3333)', 14, '2026-01-04 16:12:13'),
(4, 1, 'Suppression', 'Équipement supprimé: Admin (3333)', 14, '2026-01-04 16:13:14'),
(5, 1, 'Suppression', 'Équipement supprimé: Admin (3333)', 14, '2026-01-04 16:14:15'),
(6, 1, 'Suppression', 'Équipement supprimé: Admin (3333)', 14, '2026-01-04 16:15:16'),
(7, 1, 'Suppression', 'Équipement supprimé: Admin (3333)', 14, '2026-01-04 16:16:17'),
(8, 1, 'Suppression', 'Équipement supprimé: Admin (3333)', 14, '2026-01-04 16:17:04');

-- --------------------------------------------------------

--
-- Structure de la table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `success` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_id`, `login_time`, `ip_address`, `user_agent`, `success`) VALUES
(1, 1, '2025-12-21 23:09:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(2, 1, '2025-12-22 15:58:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(3, 1, '2025-12-22 16:41:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(4, 1, '2025-12-22 16:42:32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(5, 2, '2025-12-22 16:42:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(6, 1, '2025-12-22 16:45:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(7, 1, '2025-12-22 16:46:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(8, 2, '2025-12-23 00:07:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(9, 1, '2025-12-23 00:35:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(10, 2, '2025-12-23 11:47:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(11, 1, '2025-12-23 11:52:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(12, 6, '2025-12-23 11:55:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(13, 1, '2025-12-23 23:39:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(14, 1, '2025-12-24 10:30:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(15, 1, '2025-12-24 14:16:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(16, 1, '2025-12-24 14:18:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(17, 2, '2025-12-24 20:53:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(18, 1, '2025-12-24 20:54:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(19, 8, '2025-12-24 20:55:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(20, 6, '2025-12-24 20:58:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(21, 1, '2025-12-25 00:11:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(22, 10, '2025-12-25 00:20:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(23, 10, '2025-12-25 00:53:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(24, 10, '2025-12-25 00:56:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(25, 14, '2025-12-25 17:07:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(26, 2, '2025-12-25 17:41:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(27, 1, '2025-12-25 23:32:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(28, 1, '2025-12-26 12:05:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(29, 14, '2025-12-26 13:02:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(30, 2, '2025-12-26 13:10:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(31, 14, '2025-12-26 23:31:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(32, 2, '2025-12-26 23:33:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(33, 1, '2025-12-26 23:35:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(34, 6, '2025-12-27 20:51:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(35, 2, '2025-12-27 20:51:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(36, 1, '2025-12-27 20:51:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(37, 1, '2026-01-01 14:59:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(38, 1, '2026-01-04 17:08:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(39, 1, '2026-01-04 17:10:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(40, 14, '2026-01-04 17:14:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', 1),
(41, 1, '2026-05-20 17:12:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1),
(42, 4, '2026-05-20 17:19:06', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1),
(43, 4, '2026-05-20 17:19:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1),
(44, 1, '2026-05-22 00:51:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1),
(45, 1, '2026-05-22 13:04:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1),
(46, 1, '2026-05-22 13:07:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1),
(47, 1, '2026-05-22 13:10:56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1),
(48, 1, '2026-05-22 13:11:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1),
(49, 1, '2026-05-22 13:15:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1),
(50, 1, '2026-05-23 00:18:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1),
(51, 1, '2026-05-23 00:23:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1),
(52, 1, '2026-05-23 11:48:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1),
(53, 1, '2026-05-24 14:30:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1),
(54, 4, '2026-05-24 14:32:01', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 1);

--
-- Déclencheurs `login_logs`
--
DELIMITER $$
CREATE TRIGGER `after_user_login_insert` AFTER INSERT ON `login_logs` FOR EACH ROW BEGIN
    IF NEW.success = TRUE THEN
        UPDATE utilisateurs 
        SET derniere_connexion = NEW.login_time 
        WHERE id = NEW.user_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `log_archive`
--

CREATE TABLE `log_archive` (
  `id` int(11) NOT NULL,
  `original_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text COLLATE utf8mb4_unicode_ci,
  `new_values` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `message` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `archived_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `medicaments`
--

CREATE TABLE `medicaments` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `medicaments`
--

INSERT INTO `medicaments` (`id`, `code_cip`, `nom_commercial`, `nom_generique`, `laboratoire`, `forme`, `dosage`, `classe_therapeutique`, `indications`, `contre_indications`, `effets_secondaires`, `posologie`, `precautions`, `interactions`, `conditionnement`, `stock_actuel`, `stock_minimum`, `prix_unitaire`, `remboursement`, `statut`, `created_at`, `updated_at`) VALUES
(1, '3400933596033', 'Doliprane', 'Paracétamol', 'Sanofi', 'comprime', '1000mg', 'Antalgique', 'Douleurs et fièvre', NULL, NULL, NULL, NULL, NULL, NULL, 150, 20, '2.50', '65.00', 'actif', '2025-12-22 16:15:40', '2025-12-22 16:15:40'),
(2, '3400931254876', 'Ibuprofène', 'Ibuprofène', 'Bayer', 'comprime', '400mg', 'Anti-inflammatoire', 'Douleurs et inflammations', NULL, NULL, NULL, NULL, NULL, NULL, 80, 15, '3.20', '35.00', 'actif', '2025-12-22 16:15:40', '2025-12-22 16:15:40'),
(3, '3400934875129', 'Amoxicilline', 'Amoxicilline', 'GSK', 'gelule', '500mg', 'Antibiotique', 'Infections bactériennes', '', '', '', '', '', '', 46, 25, '5.80', '100.00', 'actif', '2025-12-22 16:15:40', '2025-12-22 17:32:50'),
(4, '3400936548721', 'Ventoline', 'Salbutamol', 'GSK', 'spray', '100mcg/dose', 'Bronchodilatateur', 'Asthme, bronchite', NULL, NULL, NULL, NULL, NULL, NULL, 120, 30, '12.50', '65.00', 'actif', '2025-12-22 16:15:40', '2025-12-22 16:15:40'),
(5, '3400932154873', 'Levothyrox', 'Lévothyroxine', 'Merck', 'comprime', '75mcg', 'Hormone thyroïdienne', 'Hypothyroïdie', NULL, NULL, NULL, NULL, NULL, NULL, 95, 40, '4.30', '100.00', 'actif', '2025-12-22 16:15:40', '2025-12-22 16:15:40'),
(6, '3400936541234', 'Atorvastatine', 'Atorvastatine', 'Pfizer', 'comprime', '20mg', 'Hypolipémiant', 'Cholestérol élevé', NULL, NULL, NULL, NULL, NULL, NULL, 60, 20, '6.75', '65.00', 'actif', '2025-12-22 16:15:40', '2025-12-22 16:15:40'),
(7, '3400939874561', 'Lantus', 'Insuline glargine', 'Sanofi', 'injectable', '100UI/ml', 'Antidiabétique', 'Diabète type 1 et 2', NULL, NULL, NULL, NULL, NULL, NULL, 35, 15, '45.00', '100.00', 'actif', '2025-12-22 16:15:40', '2025-12-22 16:15:40'),
(8, '3400933216547', 'Xanax', 'Alprazolam', 'Pfizer', 'comprime', '0.25mg', 'Anxiolytique', 'Anxiété, crises de panique', NULL, NULL, NULL, NULL, NULL, NULL, 25, 10, '8.90', '65.00', 'actif', '2025-12-22 16:15:40', '2025-12-22 16:15:40'),
(9, '3400936547890', 'Zyrtec', 'Cétirizine', 'UCB', 'comprime', '10mg', 'Antihistaminique', 'Allergies', NULL, NULL, NULL, NULL, NULL, NULL, 110, 25, '3.45', '35.00', 'actif', '2025-12-22 16:15:40', '2025-12-22 16:15:40'),
(10, '3400931234567', 'Lasilix', 'Furosémide', 'Sanofi', 'comprime', '40mg', 'Diurétique', 'Hypertension, œdèmes', NULL, NULL, NULL, NULL, NULL, NULL, 70, 20, '2.80', '100.00', 'actif', '2025-12-22 16:15:40', '2025-12-22 16:15:40');

-- --------------------------------------------------------

--
-- Structure de la table `medicament_distribution`
--

CREATE TABLE `medicament_distribution` (
  `id` int(11) NOT NULL,
  `medicament_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `consultation_id` int(11) DEFAULT NULL,
  `quantite` int(11) NOT NULL,
  `date_distribution` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `distributed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `medicament_stock_log`
--

CREATE TABLE `medicament_stock_log` (
  `id` int(11) NOT NULL,
  `medicament_id` int(11) NOT NULL,
  `operation` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantite` int(11) NOT NULL,
  `ancien_stock` int(11) NOT NULL,
  `nouveau_stock` int(11) NOT NULL,
  `raison` text COLLATE utf8mb4_unicode_ci,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `mouvements_stock`
--

CREATE TABLE `mouvements_stock` (
  `id` int(11) NOT NULL,
  `medicament_id` int(11) NOT NULL,
  `type_mouvement` enum('entree','sortie','ajustement','inventaire') NOT NULL,
  `quantite` int(11) NOT NULL,
  `quantite_avant` int(11) NOT NULL,
  `quantite_apres` int(11) NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `titre` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `lien` varchar(255) DEFAULT NULL,
  `lu` tinyint(1) DEFAULT '0',
  `important` tinyint(1) DEFAULT '0',
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `titre`, `message`, `lien`, `lu`, `important`, `expires_at`, `created_at`) VALUES
(1, 2, 'rdv', 'Nouveau rendez-vous', 'Vous avez un nouveau rendez-vous avec M. Durand demain à 9h', '/docteur/rendezvous.php', 1, 0, NULL, '2025-12-19 22:41:53'),
(2, 3, 'patient', 'Nouveau patient enregistré', 'Un nouveau patient a été enregistré : M. Martin', '/secretaire/patients.php', 0, 0, NULL, '2025-12-19 22:41:53'),
(3, 5, 'consultation', 'Consultation à venir', 'Consultation avec Mme Petit dans 2 heures', '/docteur/consultations.php', 0, 1, NULL, '2025-12-19 22:41:53'),
(4, 1, 'system', 'Maintenance planifiée', 'Maintenance système prévue ce soir à 22h', '/admin/gestion.php', 0, 1, NULL, '2025-12-19 22:41:53');

-- --------------------------------------------------------

--
-- Structure de la table `parametres_systeme`
--

CREATE TABLE `parametres_systeme` (
  `id` int(11) NOT NULL,
  `cle` varchar(100) NOT NULL,
  `valeur` text,
  `type` varchar(50) DEFAULT 'texte',
  `categorie` varchar(50) DEFAULT 'general',
  `description` text,
  `modifiable` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `parametres_systeme`
--

INSERT INTO `parametres_systeme` (`id`, `cle`, `valeur`, `type`, `categorie`, `description`, `modifiable`, `created_at`, `updated_at`) VALUES
(1, 'app_nom', 'Gestion Médicale', 'texte', 'general', 'Nom de l\'application', 1, '2025-12-19 22:41:53', '2025-12-22 14:02:37'),
(2, 'app_version', '2.0.0', 'texte', 'general', 'Version de l\'application', 1, '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(3, 'app_timezone', 'afrique', 'texte', 'general', 'Fuseau horaire', 1, '2025-12-19 22:41:53', '2025-12-22 14:02:06'),
(4, 'session_timeout', '3600', 'nombre', 'securite', 'Timeout session en secondes', 1, '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(5, 'password_min_length', '8', 'nombre', 'securite', 'Longueur minimale mot de passe', 1, '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(6, 'consultation_duree_defaut', '30', 'nombre', 'consultation', 'Durée par défaut des consultations', 1, '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(7, 'rdv_rappel_heures', '24', 'nombre', 'rendezvous', 'Heures avant rappel RDV', 1, '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(8, 'email_notification', '1', 'booleen', 'notification', 'Activer les notifications email', 1, '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(9, 'logo_url', '/assets/images/logo.png', 'texte', 'interface', 'URL du logo', 1, '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(10, 'theme_couleur', '#44e60a', 'couleur', 'interface', 'Couleur principale du thème', 1, '2025-12-19 22:41:53', '2025-12-21 21:42:14');

-- --------------------------------------------------------

--
-- Structure de la table `pathologies`
--

CREATE TABLE `pathologies` (
  `id` int(11) NOT NULL,
  `code_cim` varchar(20) DEFAULT NULL,
  `nom` varchar(200) NOT NULL,
  `specialite_id` int(11) DEFAULT NULL,
  `description` text,
  `symptomes` text,
  `causes` text,
  `traitement` text,
  `prevention` text,
  `gravite` enum('faible','moderee','grave','tres_grave') DEFAULT 'moderee',
  `contagieux` tinyint(1) DEFAULT '0',
  `chronique` tinyint(1) DEFAULT '0',
  `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `pathologies`
--

INSERT INTO `pathologies` (`id`, `code_cim`, `nom`, `specialite_id`, `description`, `symptomes`, `causes`, `traitement`, `prevention`, `gravite`, `contagieux`, `chronique`, `date_creation`, `date_modification`) VALUES
(1, 'I10', 'Hypertension artérielle', 1, 'Pression artérielle élevée de façon chronique', 'Maux de tête, vertiges, saignements de nez', NULL, NULL, NULL, 'moderee', 0, 0, '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(2, 'L20', 'Eczéma atopique', 2, 'Maladie inflammatoire chronique de la peau', 'Démangeaisons, rougeurs, sécheresse cutanée', NULL, NULL, NULL, '', 0, 0, '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(3, 'G43', 'Migraine', 3, 'Maladie caractérisée par des maux de tête sévères', 'Céphalées, nausées, sensibilité à la lumière', NULL, NULL, NULL, 'moderee', 0, 0, '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(4, 'J45', 'Asthme', 4, 'Maladie inflammatoire des bronches', 'Essoufflement, sifflements, toux', NULL, NULL, NULL, 'grave', 0, 0, '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(5, 'E11', 'Diabète de type 2', 1, 'Trouble métabolique caractérisé par une hyperglycémie', 'Soif intense, fatigue, besoin fréquent d\'uriner', NULL, NULL, NULL, 'grave', 0, 0, '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(6, 'F41', 'Trouble anxieux généralisé', 7, 'Anxiété excessive et persistante', 'Inquiétude constante, tension musculaire, fatigue', NULL, NULL, NULL, 'moderee', 0, 0, '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(7, 'M17', 'Gonarthrose', 8, 'Arthrose du genou', 'Douleurs articulaires, raideur, difficulté à marcher', NULL, NULL, NULL, 'moderee', 0, 0, '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(8, 'H10', 'Conjonctivite', 9, 'Inflammation de la conjonctive oculaire', 'Rougeur, démangeaisons, écoulement oculaire', NULL, NULL, NULL, '', 0, 0, '2025-12-19 22:41:53', '2025-12-19 22:41:53');

-- --------------------------------------------------------

--
-- Structure de la table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `code_patient` varchar(20) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `date_naissance` date NOT NULL,
  `sexe` enum('M','F') NOT NULL,
  `lieu_naissance` varchar(100) DEFAULT NULL,
  `adresse` text,
  `ville` varchar(100) DEFAULT NULL,
  `code_postal` varchar(10) DEFAULT NULL,
  `pays` varchar(50) DEFAULT 'France',
  `telephone` varchar(20) DEFAULT NULL,
  `telephone_urgence` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `profession` varchar(100) DEFAULT NULL,
  `situation_familiale` enum('celibataire','marie','divorce','veuf') DEFAULT 'celibataire',
  `nombre_enfants` int(11) DEFAULT '0',
  `groupe_sanguin` varchar(5) DEFAULT NULL,
  `rhésus` enum('+','-') DEFAULT NULL,
  `poids` decimal(5,2) DEFAULT NULL,
  `taille` decimal(5,2) DEFAULT NULL,
  `imc` decimal(5,2) DEFAULT NULL,
  `antecedents_familiaux` text,
  `antecedents_personnels` text,
  `allergies` text,
  `medicaments_habituels` text,
  `habitudes` text,
  `notes` text,
  `statut` enum('actif','archive','decede') DEFAULT 'actif',
  `date_enregistrement` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `patients`
--

INSERT INTO `patients` (`id`, `code_patient`, `nom`, `prenom`, `date_naissance`, `sexe`, `lieu_naissance`, `adresse`, `ville`, `code_postal`, `pays`, `telephone`, `telephone_urgence`, `email`, `profession`, `situation_familiale`, `nombre_enfants`, `groupe_sanguin`, `rhésus`, `poids`, `taille`, `imc`, `antecedents_familiaux`, `antecedents_personnels`, `allergies`, `medicaments_habituels`, `habitudes`, `notes`, `statut`, `date_enregistrement`, `date_modification`, `created_by`) VALUES
(1, 'PAT-202401-0001', 'Durand', 'Marie', '1985-06-15', 'F', NULL, '123 Rue de Paris', 'Paris', '75001', 'France', '+33612345678', NULL, 'marie.durand@email.com', NULL, 'celibataire', 0, 'A+', NULL, NULL, NULL, NULL, NULL, 'Hypertension familiale, Cholestérol', 'Pénicilline, Arachides', NULL, NULL, NULL, 'actif', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 3),
(2, 'PAT-202401-0002', 'Leroy', 'Paul', '1978-03-22', 'M', NULL, '456 Avenue Victor Hugo', 'Lyon', '69002', 'France', '+33687654321', NULL, 'paul.leroy@email.com', NULL, 'celibataire', 0, 'O+', NULL, NULL, NULL, NULL, NULL, 'Diabète type 2, Cholestérol', 'Aucune', NULL, NULL, NULL, 'actif', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 3),
(3, 'PAT-202401-0003', 'Petit', 'Julie', '1992-11-30', 'F', NULL, '789 Boulevard Saint-Germain', 'Marseille', '13001', 'France', '+33611223344', NULL, 'julie.petit@email.com', NULL, 'celibataire', 0, 'B+', NULL, NULL, NULL, NULL, NULL, 'Asthme depuis l\'enfance, Eczéma', 'Acariens, Pollen, Poils de chat', NULL, NULL, NULL, 'actif', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 8),
(4, 'PAT-202401-0004', 'Moreau', 'Thomas', '1965-08-12', 'M', NULL, '321 Rue de la République', 'Toulouse', '31000', 'France', '+33699887766', NULL, 'thomas.moreau@email.com', NULL, 'celibataire', 0, 'AB+', NULL, NULL, NULL, NULL, NULL, 'Opération genou 2018, Hypertension', 'Iode, Crustacés', NULL, NULL, NULL, 'actif', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 3),
(5, 'PAT-202401-0005', 'Simon', 'Claire', '1972-12-05', 'F', NULL, '654 Rue du Commerce', 'Lille', '59000', 'France', '+33655443322', NULL, 'claire.simon@email.com', NULL, 'celibataire', 0, 'A-', NULL, NULL, NULL, NULL, NULL, 'Migraines chroniques, Dépression', 'Aspirine, Ibuprofène', NULL, NULL, NULL, 'actif', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 8),
(6, 'PAT-202401-0006', 'Dubois', 'Marc', '1988-07-19', 'M', NULL, '987 Avenue des Champs-Élysées', 'Paris', '75008', 'France', '+33666778899', NULL, 'marc.dubois@email.com', NULL, 'celibataire', 0, 'O-', NULL, NULL, NULL, NULL, NULL, 'Sportif, Aucun antécédent', 'Aucune', NULL, NULL, NULL, 'actif', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 3),
(7, 'PAT-202401-0007', 'Laurent', 'Sophie', '1995-04-25', 'F', NULL, '159 Rue de la Liberté', 'Bordeaux', '33000', 'France', '+33622334455', NULL, 'sophie.laurent@email.com', NULL, 'celibataire', 0, 'B-', NULL, NULL, NULL, NULL, NULL, 'Allergies saisonnières', 'Pollens, Moisissures', NULL, NULL, NULL, 'actif', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 8);

--
-- Déclencheurs `patients`
--
DELIMITER $$
CREATE TRIGGER `before_patient_insert` BEFORE INSERT ON `patients` FOR EACH ROW BEGIN
    DECLARE annee_mois VARCHAR(6);
    DECLARE prochain_num INT;
    
    SET annee_mois = DATE_FORMAT(NOW(), '%Y%m');
    
    SELECT COALESCE(MAX(SUBSTRING(code_patient, 13)), 0) + 1 INTO prochain_num
    FROM patients 
    WHERE code_patient LIKE CONCAT('PAT-', annee_mois, '-%');
    
    IF prochain_num IS NULL THEN
        SET prochain_num = 1;
    END IF;
    
    SET NEW.code_patient = CONCAT('PAT-', annee_mois, '-', LPAD(prochain_num, 4, '0'));
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_patient_update_imc` BEFORE UPDATE ON `patients` FOR EACH ROW BEGIN
    IF NEW.poids IS NOT NULL AND NEW.taille IS NOT NULL AND NEW.taille > 0 THEN
        SET NEW.imc = NEW.poids / POW(NEW.taille / 100, 2);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `patient_pathologie`
--

CREATE TABLE `patient_pathologie` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `pathologie_id` int(11) NOT NULL,
  `date_diagnostic` date NOT NULL,
  `diagnostic_par` int(11) DEFAULT NULL,
  `gravite` enum('legere','moderee','grave') DEFAULT 'moderee',
  `stade` varchar(50) DEFAULT NULL,
  `traitement_actuel` text,
  `evolution` text,
  `notes` text,
  `statut` enum('active','guerie','chronique','en_suivi') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `patient_pathologie`
--

INSERT INTO `patient_pathologie` (`id`, `patient_id`, `pathologie_id`, `date_diagnostic`, `diagnostic_par`, `gravite`, `stade`, `traitement_actuel`, `evolution`, `notes`, `statut`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2023-05-10', 2, 'moderee', NULL, NULL, NULL, NULL, 'chronique', '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(2, 2, 5, '2022-11-15', 2, 'grave', NULL, NULL, NULL, NULL, 'en_suivi', '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(3, 3, 2, '2023-08-22', 5, 'legere', NULL, NULL, NULL, NULL, 'active', '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(4, 3, 4, '2015-03-10', NULL, 'moderee', NULL, NULL, NULL, NULL, 'chronique', '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(5, 5, 3, '2020-06-30', 5, 'moderee', NULL, NULL, NULL, NULL, 'chronique', '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(6, 4, 1, '2021-09-05', 2, 'legere', NULL, NULL, NULL, NULL, 'active', '2025-12-19 22:41:53', '2025-12-19 22:41:53'),
(7, 7, 8, '2023-04-15', 7, 'legere', NULL, NULL, NULL, NULL, 'guerie', '2025-12-19 22:41:53', '2025-12-19 22:41:53');

-- --------------------------------------------------------

--
-- Structure de la table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `consultation_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `docteur_id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `date_prescription` date NOT NULL,
  `medicaments` text COMMENT 'Structure JSON des médicaments',
  `posologie` text,
  `duree_traitement` varchar(50) DEFAULT NULL,
  `renouvelable` tinyint(1) DEFAULT '0',
  `nombre_renouvellements` int(11) DEFAULT '0',
  `notes` text,
  `statut` enum('active','terminee','annulee') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `consultation_id`, `patient_id`, `docteur_id`, `reference`, `date_prescription`, `medicaments`, `posologie`, `duree_traitement`, `renouvelable`, `nombre_renouvellements`, `notes`, `statut`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 1, 1, 2, 'PRES-202401-0001', '2024-01-15', '[{\"nom\":\"Amlodipine\",\"dosage\":\"5mg\",\"forme\":\"comprime\",\"quantite\":30,\"posologie\":\"1 comprimé par jour\",\"repas\":\"indifferent\"}]', 'Prendre 1 comprimé par jour, de préférence le matin', '30 jours', 0, 0, NULL, 'active', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 0),
(2, 3, 3, 5, 'PRES-202401-0002', '2024-01-17', '[{\"nom\":\"Corticoide topique\",\"dosage\":\"0.1%\",\"forme\":\"creme\",\"quantite\":1,\"posologie\":\"appliquer 2 fois par jour\",\"zone\":\"zones atteintes\"},{\"nom\":\"Emollient\",\"dosage\":\"\",\"forme\":\"creme\",\"quantite\":1,\"posologie\":\"appliquer quotidiennement\",\"zone\":\"peau seche\"}]', 'Appliquer la crème corticoïde sur les lésions 2 fois par jour. Utiliser l\'émollient quotidiennement sur toute la peau.', '15 jours', 0, 0, NULL, 'active', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 0),
(3, 5, 5, 5, 'PRES-202401-0003', '2024-01-19', '[{\"nom\":\"Sumatriptan\",\"dosage\":\"50mg\",\"forme\":\"comprime\",\"quantite\":6,\"posologie\":\"1 comprimé au début de la crise\",\"max\":\"2 comprimés par 24h\"},{\"nom\":\"Propranolol\",\"dosage\":\"40mg\",\"forme\":\"comprime\",\"quantite\":30,\"posologie\":\"1 comprimé par jour\",\"repas\":\"pendant les repas\"}]', 'Sumatriptan : prendre au début de la crise (max 2/24h). Propranolol : 1 comprimé par jour pendant les repas.', '30 jours', 0, 0, NULL, 'active', '2025-12-19 22:41:53', '2025-12-19 22:41:53', 0);

--
-- Déclencheurs `prescriptions`
--
DELIMITER $$
CREATE TRIGGER `before_prescription_insert` BEFORE INSERT ON `prescriptions` FOR EACH ROW BEGIN
    DECLARE annee_mois VARCHAR(6);
    DECLARE prochain_num INT;
    
    SET annee_mois = DATE_FORMAT(NOW(), '%Y%m');
    
    SELECT COALESCE(MAX(SUBSTRING(reference, 13)), 0) + 1 INTO prochain_num
    FROM prescriptions 
    WHERE reference LIKE CONCAT('PRES-', annee_mois, '-%');
    
    IF prochain_num IS NULL THEN
        SET prochain_num = 1;
    END IF;
    
    SET NEW.reference = CONCAT('PRES-', annee_mois, '-', LPAD(prochain_num, 4, '0'));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `prescription_details`
--

CREATE TABLE `prescription_details` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `medicament_id` int(11) DEFAULT NULL,
  `nom_medicament` varchar(200) NOT NULL,
  `dosage` varchar(50) NOT NULL,
  `forme` varchar(50) DEFAULT NULL,
  `quantite` int(11) NOT NULL,
  `posologie` text NOT NULL,
  `duree` varchar(50) DEFAULT NULL,
  `instructions` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `prescription_details`
--

INSERT INTO `prescription_details` (`id`, `prescription_id`, `medicament_id`, `nom_medicament`, `dosage`, `forme`, `quantite`, `posologie`, `duree`, `instructions`, `created_at`, `updated_at`) VALUES
(13, 2147483647, 0, 'Amlodipine', '5 mg', 'comprimé', 0, 'Antihypertenseur', '65%', NULL, '2025-12-22 22:43:31', '2025-12-22 22:43:31'),
(14, 2147483647, 0, 'Acide acétylsalicylique', '160 mg', 'comprimé gastro-résistant', 0, 'Antiagrégant plaquettaire', '65%', NULL, '2025-12-22 22:43:31', '2025-12-22 22:43:31'),
(15, 2147483647, 0, 'Amlodipine', '5 mg', 'comprimé', 0, 'Antihypertenseur', '65%', NULL, '2025-12-22 22:43:53', '2025-12-22 22:43:53'),
(16, 2147483647, 0, 'Acide acétylsalicylique', '160 mg', 'comprimé gastro-résistant', 0, 'Antiagrégant plaquettaire', '65%', NULL, '2025-12-22 22:43:53', '2025-12-22 22:43:53'),
(17, 2147483647, 0, 'Azathioprine', '50 mg', 'comprimé', 0, 'Immunosuppresseur', '100%', NULL, '2025-12-22 22:43:53', '2025-12-22 22:43:53'),
(18, 2147483647, 0, 'Paracétamol', '500 mg', 'comprimé', 0, 'Antalgique', '65%', NULL, '2025-12-22 22:43:53', '2025-12-22 22:43:53'),
(19, 2147483647, 0, 'Atorvastatine', '10 mg', 'comprimé', 0, 'Hypolipémiant', '65%', NULL, '2025-12-22 22:43:53', '2025-12-22 22:43:53');

-- --------------------------------------------------------

--
-- Structure de la table `rendez_vous`
--

CREATE TABLE `rendez_vous` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `docteur_id` int(11) NOT NULL,
  `date_rdv` datetime NOT NULL,
  `duree` int(11) DEFAULT '30' COMMENT 'Durée en minutes',
  `type_rdv` enum('consultation','controle','urgence','autre') DEFAULT 'consultation',
  `motif` text,
  `notes` text,
  `statut` enum('confirme','annule','reporte','present','absent') DEFAULT 'confirme',
  `rappel_envoye` tinyint(1) DEFAULT '0',
  `rappel_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `rendez_vous`
--

INSERT INTO `rendez_vous` (`id`, `reference`, `patient_id`, `docteur_id`, `date_rdv`, `duree`, `type_rdv`, `motif`, `notes`, `statut`, `rappel_envoye`, `rappel_date`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'RDV-202402-0001', 1, 2, '2024-02-01 09:00:00', 30, 'consultation', 'Contrôle tension mensuel', NULL, 'confirme', 0, NULL, '2025-12-19 22:41:53', '2025-12-19 22:41:53', 3),
(2, 'RDV-202402-0002', 2, 2, '2024-02-02 10:30:00', 30, 'consultation', 'ECG de contrôle', NULL, 'confirme', 0, NULL, '2025-12-19 22:41:53', '2025-12-19 22:41:53', 3),
(3, 'RDV-202402-0003', 3, 5, '2024-02-03 14:00:00', 30, 'consultation', 'Suivi eczéma', NULL, 'confirme', 0, NULL, '2025-12-19 22:41:53', '2025-12-19 22:41:53', 8),
(4, 'RDV-202402-0004', 4, 2, '2024-02-04 11:00:00', 30, 'consultation', 'Contrôle glycémie', NULL, 'confirme', 0, NULL, '2025-12-19 22:41:53', '2025-12-19 22:41:53', 3),
(5, 'RDV-202402-0005', 5, 5, '2024-02-05 15:30:00', 30, 'consultation', 'Évaluation traitement migraine', NULL, 'confirme', 0, NULL, '2025-12-19 22:41:53', '2025-12-19 22:41:53', 8),
(6, 'RDV-202402-0006', 6, 7, '2024-02-06 10:00:00', 30, 'consultation', 'Examen de routine', NULL, 'confirme', 0, NULL, '2025-12-19 22:41:53', '2025-12-19 22:41:53', 3),
(7, 'RDV-202402-0007', 7, 7, '2024-02-07 14:30:00', 30, 'consultation', 'Suivi allergies', NULL, 'confirme', 0, NULL, '2025-12-19 22:41:53', '2025-12-19 22:41:53', 3);

--
-- Déclencheurs `rendez_vous`
--
DELIMITER $$
CREATE TRIGGER `before_rendezvous_insert` BEFORE INSERT ON `rendez_vous` FOR EACH ROW BEGIN
    DECLARE annee_mois VARCHAR(6);
    DECLARE prochain_num INT;
    
    SET annee_mois = DATE_FORMAT(NOW(), '%Y%m');
    
    SELECT COALESCE(MAX(SUBSTRING(reference, 13)), 0) + 1 INTO prochain_num
    FROM rendez_vous 
    WHERE reference LIKE CONCAT('RDV-', annee_mois, '-%');
    
    IF prochain_num IS NULL THEN
        SET prochain_num = 1;
    END IF;
    
    SET NEW.reference = CONCAT('RDV-', annee_mois, '-', LPAD(prochain_num, 4, '0'));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `description`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Accès complet au système', 1, '2025-12-22 01:13:36', NULL),
(2, 'docteur', 'Médecin avec accès aux patients et consultations', 1, '2025-12-22 01:13:37', NULL),
(3, 'secretaire', 'Personnel administratif', 1, '2025-12-22 01:13:37', NULL),
(4, 'assistant', 'Assistant aux médecins', 1, '2025-12-22 01:13:37', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `module`, `action`, `created_at`) VALUES
(1, 1, 'dashboard', 'view', '2025-12-22 01:13:36'),
(2, 1, 'dashboard', 'create', '2025-12-22 01:13:36'),
(3, 1, 'dashboard', 'edit', '2025-12-22 01:13:36'),
(4, 1, 'dashboard', 'delete', '2025-12-22 01:13:36'),
(5, 1, 'dashboard', 'export', '2025-12-22 01:13:36'),
(6, 1, 'dashboard', 'import', '2025-12-22 01:13:36'),
(7, 1, 'dashboard', 'print', '2025-12-22 01:13:36'),
(8, 1, 'patients', 'view', '2025-12-22 01:13:36'),
(9, 1, 'patients', 'create', '2025-12-22 01:13:36'),
(10, 1, 'patients', 'edit', '2025-12-22 01:13:36'),
(11, 1, 'patients', 'delete', '2025-12-22 01:13:36'),
(12, 1, 'patients', 'export', '2025-12-22 01:13:36'),
(13, 1, 'patients', 'import', '2025-12-22 01:13:36'),
(14, 1, 'patients', 'print', '2025-12-22 01:13:36'),
(15, 1, 'consultations', 'view', '2025-12-22 01:13:36'),
(16, 1, 'consultations', 'create', '2025-12-22 01:13:36'),
(17, 1, 'consultations', 'edit', '2025-12-22 01:13:36'),
(18, 1, 'consultations', 'delete', '2025-12-22 01:13:36'),
(19, 1, 'consultations', 'export', '2025-12-22 01:13:36'),
(20, 1, 'consultations', 'import', '2025-12-22 01:13:36'),
(21, 1, 'consultations', 'print', '2025-12-22 01:13:36'),
(22, 1, 'rendezvous', 'view', '2025-12-22 01:13:36'),
(23, 1, 'rendezvous', 'create', '2025-12-22 01:13:36'),
(24, 1, 'rendezvous', 'edit', '2025-12-22 01:13:36'),
(25, 1, 'rendezvous', 'delete', '2025-12-22 01:13:36'),
(26, 1, 'rendezvous', 'export', '2025-12-22 01:13:36'),
(27, 1, 'rendezvous', 'import', '2025-12-22 01:13:36'),
(28, 1, 'rendezvous', 'print', '2025-12-22 01:13:36'),
(29, 1, 'prescriptions', 'view', '2025-12-22 01:13:36'),
(30, 1, 'prescriptions', 'create', '2025-12-22 01:13:36'),
(31, 1, 'prescriptions', 'edit', '2025-12-22 01:13:36'),
(32, 1, 'prescriptions', 'delete', '2025-12-22 01:13:36'),
(33, 1, 'prescriptions', 'export', '2025-12-22 01:13:36'),
(34, 1, 'prescriptions', 'import', '2025-12-22 01:13:36'),
(35, 1, 'prescriptions', 'print', '2025-12-22 01:13:36'),
(36, 1, 'documents', 'view', '2025-12-22 01:13:36'),
(37, 1, 'documents', 'create', '2025-12-22 01:13:36'),
(38, 1, 'documents', 'edit', '2025-12-22 01:13:36'),
(39, 1, 'documents', 'delete', '2025-12-22 01:13:36'),
(40, 1, 'documents', 'export', '2025-12-22 01:13:36'),
(41, 1, 'documents', 'import', '2025-12-22 01:13:36'),
(42, 1, 'documents', 'print', '2025-12-22 01:13:36'),
(43, 1, 'utilisateurs', 'view', '2025-12-22 01:13:36'),
(44, 1, 'utilisateurs', 'create', '2025-12-22 01:13:36'),
(45, 1, 'utilisateurs', 'edit', '2025-12-22 01:13:36'),
(46, 1, 'utilisateurs', 'delete', '2025-12-22 01:13:36'),
(47, 1, 'utilisateurs', 'export', '2025-12-22 01:13:36'),
(48, 1, 'utilisateurs', 'import', '2025-12-22 01:13:36'),
(49, 1, 'utilisateurs', 'print', '2025-12-22 01:13:36'),
(50, 1, 'roles', 'view', '2025-12-22 01:13:36'),
(51, 1, 'roles', 'create', '2025-12-22 01:13:36'),
(52, 1, 'roles', 'edit', '2025-12-22 01:13:36'),
(53, 1, 'roles', 'delete', '2025-12-22 01:13:36'),
(54, 1, 'roles', 'export', '2025-12-22 01:13:36'),
(55, 1, 'roles', 'import', '2025-12-22 01:13:36'),
(56, 1, 'roles', 'print', '2025-12-22 01:13:36'),
(57, 1, 'parametres', 'view', '2025-12-22 01:13:36'),
(58, 1, 'parametres', 'create', '2025-12-22 01:13:36'),
(59, 1, 'parametres', 'edit', '2025-12-22 01:13:36'),
(60, 1, 'parametres', 'delete', '2025-12-22 01:13:36'),
(61, 1, 'parametres', 'export', '2025-12-22 01:13:36'),
(62, 1, 'parametres', 'import', '2025-12-22 01:13:36'),
(63, 1, 'parametres', 'print', '2025-12-22 01:13:36'),
(64, 1, 'sauvegardes', 'view', '2025-12-22 01:13:36'),
(65, 1, 'sauvegardes', 'create', '2025-12-22 01:13:36'),
(66, 1, 'sauvegardes', 'edit', '2025-12-22 01:13:36'),
(67, 1, 'sauvegardes', 'delete', '2025-12-22 01:13:36'),
(68, 1, 'sauvegardes', 'export', '2025-12-22 01:13:36'),
(69, 1, 'sauvegardes', 'import', '2025-12-22 01:13:36'),
(70, 1, 'sauvegardes', 'print', '2025-12-22 01:13:36'),
(71, 1, 'statistiques', 'view', '2025-12-22 01:13:36'),
(72, 1, 'statistiques', 'create', '2025-12-22 01:13:36'),
(73, 1, 'statistiques', 'edit', '2025-12-22 01:13:36'),
(74, 1, 'statistiques', 'delete', '2025-12-22 01:13:36'),
(75, 1, 'statistiques', 'export', '2025-12-22 01:13:36'),
(76, 1, 'statistiques', 'import', '2025-12-22 01:13:36'),
(77, 1, 'statistiques', 'print', '2025-12-22 01:13:36'),
(78, 1, 'pathologies', 'view', '2025-12-22 01:13:36'),
(79, 1, 'pathologies', 'create', '2025-12-22 01:13:37'),
(80, 1, 'pathologies', 'edit', '2025-12-22 01:13:37'),
(81, 1, 'pathologies', 'delete', '2025-12-22 01:13:37'),
(82, 1, 'pathologies', 'export', '2025-12-22 01:13:37'),
(83, 1, 'pathologies', 'import', '2025-12-22 01:13:37'),
(84, 1, 'pathologies', 'print', '2025-12-22 01:13:37'),
(85, 1, 'specialites', 'view', '2025-12-22 01:13:37'),
(86, 1, 'specialites', 'create', '2025-12-22 01:13:37'),
(87, 1, 'specialites', 'edit', '2025-12-22 01:13:37'),
(88, 1, 'specialites', 'delete', '2025-12-22 01:13:37'),
(89, 1, 'specialites', 'export', '2025-12-22 01:13:37'),
(90, 1, 'specialites', 'import', '2025-12-22 01:13:37'),
(91, 1, 'specialites', 'print', '2025-12-22 01:13:37'),
(92, 1, 'notifications', 'view', '2025-12-22 01:13:37'),
(93, 1, 'notifications', 'create', '2025-12-22 01:13:37'),
(94, 1, 'notifications', 'edit', '2025-12-22 01:13:37'),
(95, 1, 'notifications', 'delete', '2025-12-22 01:13:37'),
(96, 1, 'notifications', 'export', '2025-12-22 01:13:37'),
(97, 1, 'notifications', 'import', '2025-12-22 01:13:37'),
(98, 1, 'notifications', 'print', '2025-12-22 01:13:37'),
(99, 1, 'audit', 'view', '2025-12-22 01:13:37'),
(100, 1, 'audit', 'create', '2025-12-22 01:13:37'),
(101, 1, 'audit', 'edit', '2025-12-22 01:13:37'),
(102, 1, 'audit', 'delete', '2025-12-22 01:13:37'),
(103, 1, 'audit', 'export', '2025-12-22 01:13:37'),
(104, 1, 'audit', 'import', '2025-12-22 01:13:37'),
(105, 1, 'audit', 'print', '2025-12-22 01:13:37'),
(107, 2, 'dashboard', 'view', '2025-12-22 01:16:23'),
(108, 2, 'patients', 'view', '2025-12-22 01:16:23'),
(109, 2, 'patients', 'create', '2025-12-22 01:16:23'),
(110, 2, 'patients', 'edit', '2025-12-22 01:16:23'),
(111, 2, 'consultations', 'view', '2025-12-22 01:16:23'),
(112, 2, 'consultations', 'create', '2025-12-22 01:16:23'),
(113, 2, 'consultations', 'edit', '2025-12-22 01:16:23'),
(114, 2, 'rendezvous', 'view', '2025-12-22 01:16:23'),
(115, 2, 'rendezvous', 'create', '2025-12-22 01:16:23'),
(116, 2, 'rendezvous', 'edit', '2025-12-22 01:16:23'),
(117, 2, 'prescriptions', 'view', '2025-12-22 01:16:23'),
(118, 2, 'prescriptions', 'create', '2025-12-22 01:16:23'),
(119, 2, 'prescriptions', 'edit', '2025-12-22 01:16:23'),
(120, 2, 'prescriptions', 'print', '2025-12-22 01:16:23'),
(121, 2, 'documents', 'view', '2025-12-22 01:16:23'),
(122, 2, 'documents', 'create', '2025-12-22 01:16:23'),
(123, 2, 'documents', 'edit', '2025-12-22 01:16:23'),
(124, 2, 'statistiques', 'view', '2025-12-22 01:16:23'),
(125, 2, 'pathologies', 'view', '2025-12-22 01:16:23'),
(126, 2, 'specialites', 'view', '2025-12-22 01:16:23'),
(127, 2, 'notifications', 'view', '2025-12-22 01:16:23'),
(128, 3, 'dashboard', 'view', '2025-12-22 01:16:23'),
(129, 3, 'patients', 'view', '2025-12-22 01:16:23'),
(130, 3, 'patients', 'create', '2025-12-22 01:16:23'),
(131, 3, 'rendezvous', 'view', '2025-12-22 01:16:23'),
(132, 3, 'rendezvous', 'create', '2025-12-22 01:16:23'),
(133, 3, 'rendezvous', 'edit', '2025-12-22 01:16:23'),
(134, 3, 'rendezvous', 'delete', '2025-12-22 01:16:23'),
(135, 3, 'documents', 'view', '2025-12-22 01:16:23'),
(136, 3, 'documents', 'create', '2025-12-22 01:16:23'),
(137, 3, 'notifications', 'view', '2025-12-22 01:16:23'),
(138, 4, 'dashboard', 'view', '2025-12-22 01:16:23'),
(139, 4, 'patients', 'view', '2025-12-22 01:16:23'),
(140, 4, 'consultations', 'view', '2025-12-22 01:16:23'),
(141, 4, 'rendezvous', 'view', '2025-12-22 01:16:23'),
(142, 4, 'documents', 'view', '2025-12-22 01:16:23'),
(143, 4, 'notifications', 'view', '2025-12-22 01:16:23');

-- --------------------------------------------------------

--
-- Structure de la table `salle_attente`
--

CREATE TABLE `salle_attente` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `docteur_id` int(11) DEFAULT NULL,
  `motif` text,
  `notes` text,
  `urgence` tinyint(1) DEFAULT '0',
  `statut` enum('en_attente','appele','retire') DEFAULT 'en_attente',
  `added_by` int(11) DEFAULT NULL,
  `called_by` int(11) DEFAULT NULL,
  `removed_by` int(11) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `called_at` timestamp NULL DEFAULT NULL,
  `removed_at` timestamp NULL DEFAULT NULL,
  `raison_retrait` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `specialites`
--

CREATE TABLE `specialites` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text,
  `couleur` varchar(7) DEFAULT '#3b82f6',
  `icon` varchar(50) DEFAULT 'fas fa-stethoscope',
  `statut` enum('active','inactive') DEFAULT 'active',
  `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `specialites`
--

INSERT INTO `specialites` (`id`, `code`, `nom`, `description`, `couleur`, `icon`, `statut`, `date_creation`) VALUES
(1, 'CARDIO', 'Cardiologie', 'Spécialité médicale traitant les troubles cardiaques et vasculaires', '#ef4444', 'fas fa-heartbeat', 'active', '2025-12-19 22:41:53'),
(2, 'DERMA', 'Dermatologie', 'Spécialité médicale traitant les maladies de la peau', '#8b5cf6', 'fas fa-allergies', 'active', '2025-12-19 22:41:53'),
(3, 'NEURO', 'Neurologie', 'Spécialité médicale traitant les troubles neurologiques', '#3b82f6', 'fas fa-brain', 'active', '2025-12-19 22:41:53'),
(4, 'PEDIA', 'Pédiatrie', 'Spécialité médicale traitant les enfants et adolescents', '#10b981', 'fas fa-baby', 'active', '2025-12-19 22:41:53'),
(5, 'GYNECO', 'Gynécologie', 'Spécialité médicale traitant la santé féminine', '#ec4899', 'fas fa-female', 'active', '2025-12-19 22:41:53'),
(6, 'RADIO', 'Radiologie', 'Spécialité médicale utilisant l\'imagerie médicale', '#f59e0b', 'fas fa-x-ray', 'active', '2025-12-19 22:41:53'),
(7, 'PSY', 'Psychiatrie', 'Spécialité médicale traitant les troubles mentaux', '#6366f1', 'fas fa-brain', 'active', '2025-12-19 22:41:53'),
(8, 'ORTHO', 'Orthopédie', 'Spécialité chirurgicale traitant le système musculo-squelettique', '#84cc16', 'fas fa-bone', 'active', '2025-12-19 22:41:53'),
(9, 'OPHTAL', 'Ophtalmologie', 'Spécialité médicale traitant les yeux', '#06b6d4', 'fas fa-eye', 'active', '2025-12-19 22:41:53'),
(10, 'GENERAL', 'Médecine Générale', 'Médecine générale et soins primaires', '#64748b', 'fas fa-user-md', 'active', '2025-12-19 22:41:53');

-- --------------------------------------------------------

--
-- Structure de la table `stock_peremption`
--

CREATE TABLE `stock_peremption` (
  `id` int(11) NOT NULL,
  `medicament_id` int(11) NOT NULL,
  `lot` varchar(100) DEFAULT NULL,
  `date_peremption` date DEFAULT NULL,
  `quantite` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `taches`
--

CREATE TABLE `taches` (
  `id` int(11) NOT NULL,
  `titre` varchar(200) NOT NULL,
  `description` text,
  `priorite` enum('haute','moyenne','basse') DEFAULT 'moyenne',
  `statut` enum('a_faire','en_cours','termine','annule','supprime') DEFAULT 'a_faire',
  `date_echeance` date DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `tache_comments`
--

CREATE TABLE `tache_comments` (
  `id` int(11) NOT NULL,
  `tache_id` int(11) NOT NULL,
  `commentaire` text NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('docteur','secretaire','assistant','admin') NOT NULL,
  `specialite` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `adresse` text,
  `date_naissance` date DEFAULT NULL,
  `sexe` enum('M','F') DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `statut` enum('actif','inactif','suspendu') DEFAULT 'inactif',
  `jours_consultation` varchar(190) DEFAULT NULL,
  `derniere_connexion` datetime DEFAULT NULL,
  `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `signature` varchar(250) DEFAULT NULL,
  `date_modification` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `verification_code` varchar(32) DEFAULT NULL,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `otp_verified` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `specialite`, `telephone`, `adresse`, `date_naissance`, `sexe`, `photo`, `statut`, `jours_consultation`, `derniere_connexion`, `date_creation`, `signature`, `date_modification`, `verification_code`, `otp_code`, `otp_expires_at`, `email_verified_at`, `otp_verified`) VALUES
(1, 'Admin', 'System', 'admin@medical.com', '$2y$10$AvIgI.kfNCa4RT9uBh4fPedH3T.G.92ZHk5EAtIT3QI5cSK7K/u4m', 'admin', NULL, '+12345678910', '', '1994-01-22', 'M', NULL, 'actif', NULL, '2026-05-24 14:30:50', '2025-12-19 22:41:53', NULL, '2026-05-24 12:30:50', NULL, NULL, NULL, NULL, 0),
(2, 'Dupont', 'Jean', 'jean.dupont@medical.com', '$2y$10$ikCSUHureSSiYliQ8mgK/uReygTVfUEtcB5zii3lSjct4YnaLNyCq', 'docteur', 'Cardiologie', '+1234567890', '', NULL, NULL, NULL, 'actif', NULL, '2025-12-27 20:51:22', '2025-12-19 22:41:53', NULL, '2025-12-27 18:51:22', NULL, NULL, NULL, NULL, 0),
(3, 'Martin', 'Sophie', 'sophie.martin@medical.com', '$2y$10$RQCo24mb2HSwT8iho.yIeOybb19006XYdssNYzpCHgED7Hw57rHym', 'secretaire', NULL, '+33123456791', NULL, NULL, NULL, NULL, 'actif', NULL, NULL, '2025-12-19 22:41:53', NULL, '2025-12-19 22:49:18', NULL, NULL, NULL, NULL, 0),
(4, 'Bernard', 'Pierre', 'pierre.bernard@medical.com', '$2y$10$D2P9CXwxfHSGKZGBGrIT3u0W90s5z80N.RwEPxOPIdF4QkKO16j9C', 'assistant', 'Cardiologie', '+33123456792', '', NULL, NULL, NULL, 'actif', NULL, '2026-05-24 14:32:01', '2025-12-19 22:41:53', NULL, '2026-05-24 13:03:09', NULL, NULL, NULL, NULL, 0),
(5, 'Leroy', 'Marie', 'marie.leroy@medical.com', '$2y$10$5Ftwbu3tdpgWhfcZvUal.eyEKwEPCDJpPZ31QAWrytfK8JPwCpshe', 'docteur', 'Dermatologie', '+33123456793', NULL, NULL, NULL, NULL, 'actif', NULL, NULL, '2025-12-19 22:41:53', NULL, '2025-12-19 22:49:18', NULL, NULL, NULL, NULL, 0),
(6, 'Petit', 'Luc', 'luc.petit@medical.com', '$2y$10$hJrmvR7PBP3agdiWsn9PjuI7k4jl/OQwCS9IJsJ/LZE/yzTwk0jZa', 'assistant', 'Dermatologie', '+12345678910', 'mon adresse', '0000-00-00', '', NULL, 'actif', NULL, '2025-12-27 20:51:10', '2025-12-19 22:41:53', NULL, '2025-12-27 18:51:10', NULL, NULL, NULL, NULL, 0),
(7, 'Robert', 'Alice', 'alice.robert@medical.com', '$2y$10$lXJ14ACv1cptL/FWZ0b91OPY3HUDZU3bxlGUtgp6L.ZitrCiwjO8q', 'docteur', 'Médecine Générale', '+33123456795', NULL, NULL, NULL, NULL, 'actif', NULL, NULL, '2025-12-19 22:41:53', NULL, '2025-12-19 22:49:19', NULL, NULL, NULL, NULL, 0),
(8, 'Simon', 'Thomas', 'thomas.simon@medical.com', '$2y$10$z6PFf/.nTG8F1t4hncFvvuZKAhhdUqJD8ByS7dIJFoK8Tb4nLjAAi', 'secretaire', NULL, '+012345678910', '', '0000-00-00', '', NULL, 'actif', NULL, '2025-12-24 20:55:55', '2025-12-19 22:41:53', NULL, '2025-12-25 22:12:56', NULL, NULL, NULL, NULL, 0),
(9, 'daniel', 'kojo', 'kojo.don@gmail.com', '$2y$10$HQrfu0nl5j10anjpO0ido.kycHTL8xTKmZAaMFb4/YOqAkoJULrre', 'assistant', NULL, '+1234567890', NULL, NULL, NULL, NULL, 'actif', NULL, NULL, '2025-12-24 22:08:13', NULL, '2025-12-24 22:51:33', '0e6f81c14defc10a4f8b8c81e1a6d6b9', NULL, NULL, NULL, 0),
(10, 'daniel', 'kojo', 'kojo@gmail.com', '$2y$10$CUDWY/nXP9ZlzgCP0xf/EuvVuBZms.iSUXZ0mSSCidtft4fd0mfdy', 'assistant', 'medicine general', '1234567890', NULL, NULL, NULL, NULL, 'actif', NULL, '2025-12-25 00:56:00', '2025-12-24 22:09:46', NULL, '2025-12-24 22:56:00', 'd6acf648d96c5d358ae6112e64222e92', NULL, NULL, NULL, 0),
(14, 'karume', 'Kojocampany', 'chalij68@gmail.com', '$2y$10$FDVlsL0PrlA5wVcumEt3cezaMSMgEkF3/KK8oN0N8a8KB4t4ZJPoK', 'assistant', NULL, '+12369227132', '', '0000-00-00', 'M', NULL, 'actif', NULL, '2026-01-04 17:14:10', '2025-12-24 22:52:18', NULL, '2026-01-04 16:44:45', NULL, NULL, NULL, NULL, 0),
(15, 'karume', 'daniel', 'karume@medical.com', '$2y$10$Rt31thX1P7GtKsG9/RfY7uhv1NPaf736xtYiq2HjShgm1GOlEx00G', 'assistant', 'infirmiere', '+33 868578', NULL, NULL, NULL, NULL, 'actif', NULL, NULL, '2026-05-22 11:14:43', NULL, '2026-05-22 11:15:49', NULL, '872185', '2026-05-22 13:24:43', NULL, 0);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `view_log_statistics`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `view_log_statistics` (
`log_date` date
,`total_logs` bigint(21)
,`unique_users` bigint(21)
,`unique_ips` bigint(21)
,`error_logs` decimal(23,0)
,`auth_logs` decimal(23,0)
,`crud_logs` decimal(23,0)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_consultations_mensuelles`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_consultations_mensuelles` (
`mois` varchar(7)
,`total_consultations` bigint(21)
,`patients_uniques` bigint(21)
,`docteurs` bigint(21)
,`terminees` decimal(23,0)
,`annulees` decimal(23,0)
,`planifiees` decimal(23,0)
,`urgences` decimal(23,0)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_docteurs_activite`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_docteurs_activite` (
`id` int(11)
,`docteur` varchar(101)
,`specialite` varchar(100)
,`total_consultations` bigint(21)
,`premiere_consultation` datetime
,`derniere_consultation` datetime
,`patients_suivis` bigint(21)
,`taux_completion` decimal(8,4)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_patients_derniere_consultation`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_patients_derniere_consultation` (
`id` int(11)
,`code_patient` varchar(20)
,`nom` varchar(50)
,`prenom` varchar(50)
,`date_naissance` date
,`sexe` enum('M','F')
,`lieu_naissance` varchar(100)
,`adresse` text
,`ville` varchar(100)
,`code_postal` varchar(10)
,`pays` varchar(50)
,`telephone` varchar(20)
,`telephone_urgence` varchar(20)
,`email` varchar(100)
,`profession` varchar(100)
,`situation_familiale` enum('celibataire','marie','divorce','veuf')
,`nombre_enfants` int(11)
,`groupe_sanguin` varchar(5)
,`rhésus` enum('+','-')
,`poids` decimal(5,2)
,`taille` decimal(5,2)
,`imc` decimal(5,2)
,`antecedents_familiaux` text
,`antecedents_personnels` text
,`allergies` text
,`medicaments_habituels` text
,`habitudes` text
,`notes` text
,`statut` enum('actif','archive','decede')
,`date_enregistrement` timestamp
,`date_modification` timestamp
,`created_by` int(11)
,`derniere_consultation` datetime
,`dernier_diagnostic` text
,`docteur_prenom` varchar(50)
,`docteur_nom` varchar(50)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_rdv_a_venir`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_rdv_a_venir` (
`id` int(11)
,`reference` varchar(50)
,`patient_id` int(11)
,`docteur_id` int(11)
,`date_rdv` datetime
,`duree` int(11)
,`type_rdv` enum('consultation','controle','urgence','autre')
,`motif` text
,`notes` text
,`statut` enum('confirme','annule','reporte','present','absent')
,`rappel_envoye` tinyint(1)
,`rappel_date` datetime
,`created_at` timestamp
,`updated_at` timestamp
,`created_by` int(11)
,`patient_nom` varchar(50)
,`patient_prenom` varchar(50)
,`patient_telephone` varchar(20)
,`docteur_prenom` varchar(50)
,`docteur_nom` varchar(50)
,`specialite` varchar(100)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_statistiques_patients`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_statistiques_patients` (
`total_patients` bigint(21)
,`hommes` decimal(23,0)
,`femmes` decimal(23,0)
,`age_moyen` decimal(24,4)
,`mineurs` bigint(21)
,`adultes` bigint(21)
,`seniors` bigint(21)
,`archives` bigint(21)
,`decedes` bigint(21)
);

-- --------------------------------------------------------

--
-- Structure de la vue `view_log_statistics`
--
DROP TABLE IF EXISTS `view_log_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_log_statistics`  AS  select cast(`audit_logs`.`created_at` as date) AS `log_date`,count(0) AS `total_logs`,count(distinct `audit_logs`.`user_id`) AS `unique_users`,count(distinct `audit_logs`.`ip_address`) AS `unique_ips`,sum((case when (`audit_logs`.`action` like '%error%') then 1 else 0 end)) AS `error_logs`,sum((case when (`audit_logs`.`action` like '%login%') then 1 else 0 end)) AS `auth_logs`,sum((case when (`audit_logs`.`action` in ('create','update','delete')) then 1 else 0 end)) AS `crud_logs` from `audit_logs` where (`audit_logs`.`created_at` >= (curdate() - interval 30 day)) group by cast(`audit_logs`.`created_at` as date) order by cast(`audit_logs`.`created_at` as date) desc ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_consultations_mensuelles`
--
DROP TABLE IF EXISTS `vue_consultations_mensuelles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_consultations_mensuelles`  AS  select date_format(`consultations`.`date_consultation`,'%Y-%m') AS `mois`,count(0) AS `total_consultations`,count(distinct `consultations`.`patient_id`) AS `patients_uniques`,count(distinct `consultations`.`docteur_id`) AS `docteurs`,sum((case when (`consultations`.`statut` = 'termine') then 1 else 0 end)) AS `terminees`,sum((case when (`consultations`.`statut` = 'annule') then 1 else 0 end)) AS `annulees`,sum((case when (`consultations`.`statut` = 'planifie') then 1 else 0 end)) AS `planifiees`,sum((case when (`consultations`.`urgence` = 1) then 1 else 0 end)) AS `urgences` from `consultations` group by date_format(`consultations`.`date_consultation`,'%Y-%m') order by date_format(`consultations`.`date_consultation`,'%Y-%m') desc ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_docteurs_activite`
--
DROP TABLE IF EXISTS `vue_docteurs_activite`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_docteurs_activite`  AS  select `u`.`id` AS `id`,concat(`u`.`prenom`,' ',`u`.`nom`) AS `docteur`,`u`.`specialite` AS `specialite`,count(`c`.`id`) AS `total_consultations`,min(`c`.`date_consultation`) AS `premiere_consultation`,max(`c`.`date_consultation`) AS `derniere_consultation`,count(distinct `c`.`patient_id`) AS `patients_suivis`,(avg((case when (`c`.`statut` = 'termine') then 1 else 0 end)) * 100) AS `taux_completion` from (`utilisateurs` `u` left join `consultations` `c` on((`u`.`id` = `c`.`docteur_id`))) where ((`u`.`role` = 'docteur') and (`u`.`statut` = 'actif')) group by `u`.`id` order by count(`c`.`id`) desc ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_patients_derniere_consultation`
--
DROP TABLE IF EXISTS `vue_patients_derniere_consultation`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_patients_derniere_consultation`  AS  select `p`.`id` AS `id`,`p`.`code_patient` AS `code_patient`,`p`.`nom` AS `nom`,`p`.`prenom` AS `prenom`,`p`.`date_naissance` AS `date_naissance`,`p`.`sexe` AS `sexe`,`p`.`lieu_naissance` AS `lieu_naissance`,`p`.`adresse` AS `adresse`,`p`.`ville` AS `ville`,`p`.`code_postal` AS `code_postal`,`p`.`pays` AS `pays`,`p`.`telephone` AS `telephone`,`p`.`telephone_urgence` AS `telephone_urgence`,`p`.`email` AS `email`,`p`.`profession` AS `profession`,`p`.`situation_familiale` AS `situation_familiale`,`p`.`nombre_enfants` AS `nombre_enfants`,`p`.`groupe_sanguin` AS `groupe_sanguin`,`p`.`rhésus` AS `rhésus`,`p`.`poids` AS `poids`,`p`.`taille` AS `taille`,`p`.`imc` AS `imc`,`p`.`antecedents_familiaux` AS `antecedents_familiaux`,`p`.`antecedents_personnels` AS `antecedents_personnels`,`p`.`allergies` AS `allergies`,`p`.`medicaments_habituels` AS `medicaments_habituels`,`p`.`habitudes` AS `habitudes`,`p`.`notes` AS `notes`,`p`.`statut` AS `statut`,`p`.`date_enregistrement` AS `date_enregistrement`,`p`.`date_modification` AS `date_modification`,`p`.`created_by` AS `created_by`,`c`.`date_consultation` AS `derniere_consultation`,`c`.`diagnostic` AS `dernier_diagnostic`,`u`.`prenom` AS `docteur_prenom`,`u`.`nom` AS `docteur_nom` from ((`patients` `p` left join `consultations` `c` on(((`p`.`id` = `c`.`patient_id`) and (`c`.`date_consultation` = (select max(`consultations`.`date_consultation`) from `consultations` where (`consultations`.`patient_id` = `p`.`id`)))))) left join `utilisateurs` `u` on((`c`.`docteur_id` = `u`.`id`))) where (`p`.`statut` = 'actif') ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_rdv_a_venir`
--
DROP TABLE IF EXISTS `vue_rdv_a_venir`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_rdv_a_venir`  AS  select `r`.`id` AS `id`,`r`.`reference` AS `reference`,`r`.`patient_id` AS `patient_id`,`r`.`docteur_id` AS `docteur_id`,`r`.`date_rdv` AS `date_rdv`,`r`.`duree` AS `duree`,`r`.`type_rdv` AS `type_rdv`,`r`.`motif` AS `motif`,`r`.`notes` AS `notes`,`r`.`statut` AS `statut`,`r`.`rappel_envoye` AS `rappel_envoye`,`r`.`rappel_date` AS `rappel_date`,`r`.`created_at` AS `created_at`,`r`.`updated_at` AS `updated_at`,`r`.`created_by` AS `created_by`,`p`.`nom` AS `patient_nom`,`p`.`prenom` AS `patient_prenom`,`p`.`telephone` AS `patient_telephone`,`d`.`prenom` AS `docteur_prenom`,`d`.`nom` AS `docteur_nom`,`d`.`specialite` AS `specialite` from ((`rendez_vous` `r` join `patients` `p` on((`r`.`patient_id` = `p`.`id`))) join `utilisateurs` `d` on((`r`.`docteur_id` = `d`.`id`))) where ((`r`.`date_rdv` >= curdate()) and (`r`.`statut` = 'confirme')) order by `r`.`date_rdv` ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_statistiques_patients`
--
DROP TABLE IF EXISTS `vue_statistiques_patients`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_statistiques_patients`  AS  select count(0) AS `total_patients`,sum((case when (`patients`.`sexe` = 'M') then 1 else 0 end)) AS `hommes`,sum((case when (`patients`.`sexe` = 'F') then 1 else 0 end)) AS `femmes`,avg(timestampdiff(YEAR,`patients`.`date_naissance`,curdate())) AS `age_moyen`,count((case when (timestampdiff(YEAR,`patients`.`date_naissance`,curdate()) < 18) then 1 end)) AS `mineurs`,count((case when (timestampdiff(YEAR,`patients`.`date_naissance`,curdate()) between 18 and 65) then 1 end)) AS `adultes`,count((case when (timestampdiff(YEAR,`patients`.`date_naissance`,curdate()) > 65) then 1 end)) AS `seniors`,count((case when (`patients`.`statut` = 'archive') then 1 end)) AS `archives`,count((case when (`patients`.`statut` = 'decede') then 1 end)) AS `decedes` from `patients` ;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_table` (`table_name`);

--
-- Index pour la table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `backup_history`
--
ALTER TABLE `backup_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_backup_type` (`backup_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Index pour la table `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `backup_id` (`backup_id`),
  ADD KEY `idx_execution_time` (`execution_time`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `backup_schedule`
--
ALTER TABLE `backup_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_enabled` (`enabled`),
  ADD KEY `idx_next_execution` (`next_execution`);

--
-- Index pour la table `backup_settings`
--
ALTER TABLE `backup_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Index pour la table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `assistant_id` (`assistant_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_docteur` (`docteur_id`),
  ADD KEY `idx_date` (`date_consultation`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_type` (`type_consultation`),
  ADD KEY `idx_reference` (`reference`),
  ADD KEY `idx_urgence` (`urgence`),
  ADD KEY `idx_consultations_date` (`date_consultation`),
  ADD KEY `idx_consultations_patient_docteur` (`patient_id`,`docteur_id`),
  ADD KEY `idx_consultations_statut_date` (`statut`,`date_consultation`);

--
-- Index pour la table `docteur_specialite`
--
ALTER TABLE `docteur_specialite`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_docteur_specialite` (`docteur_id`,`specialite_id`),
  ADD KEY `idx_docteur` (`docteur_id`),
  ADD KEY `idx_specialite` (`specialite_id`),
  ADD KEY `idx_principal` (`principal`);

--
-- Index pour la table `documents_medicaux`
--
ALTER TABLE `documents_medicaux`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consultation_id` (`consultation_id`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `documents_medicaux_ibfk_4` (`docteur_id`);

--
-- Index pour la table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_serie` (`numero_serie`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_equipment_statut` (`statut`),
  ADD KEY `idx_equipment_categorie` (`categorie`),
  ADD KEY `idx_equipment_localisation` (`localisation`);

--
-- Index pour la table `equipment_history`
--
ALTER TABLE `equipment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `idx_equipment_history_date` (`performed_at`);

--
-- Index pour la table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_time` (`user_id`,`login_time`),
  ADD KEY `idx_login_time` (`login_time`);

--
-- Index pour la table `log_archive`
--
ALTER TABLE `log_archive`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_archived_at` (`archived_at`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_user` (`user_id`);

--
-- Index pour la table `medicaments`
--
ALTER TABLE `medicaments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_cip` (`code_cip`),
  ADD KEY `idx_nom_commercial` (`nom_commercial`(191)),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_stock` (`stock_actuel`),
  ADD KEY `idx_classe` (`classe_therapeutique`),
  ADD KEY `idx_laboratoire` (`laboratoire`);

--
-- Index pour la table `medicament_distribution`
--
ALTER TABLE `medicament_distribution`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicament_id` (`medicament_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `consultation_id` (`consultation_id`),
  ADD KEY `distributed_by` (`distributed_by`),
  ADD KEY `idx_medicament_distribution_date` (`date_distribution`);

--
-- Index pour la table `medicament_stock_log`
--
ALTER TABLE `medicament_stock_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicament_id` (`medicament_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `mouvements_stock`
--
ALTER TABLE `mouvements_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medicament` (`medicament_id`),
  ADD KEY `idx_date` (`created_at`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_lu` (`lu`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_important` (`important`);

--
-- Index pour la table `parametres_systeme`
--
ALTER TABLE `parametres_systeme`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cle` (`cle`),
  ADD KEY `idx_cle` (`cle`),
  ADD KEY `idx_categorie` (`categorie`);

--
-- Index pour la table `pathologies`
--
ALTER TABLE `pathologies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nom` (`nom`(191)),
  ADD KEY `idx_code_cim` (`code_cim`),
  ADD KEY `idx_specialite` (`specialite_id`),
  ADD KEY `idx_gravite` (`gravite`);

--
-- Index pour la table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_patient` (`code_patient`),
  ADD KEY `idx_nom_prenom` (`nom`,`prenom`),
  ADD KEY `idx_code_patient` (`code_patient`),
  ADD KEY `idx_date_naissance` (`date_naissance`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_ville` (`ville`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_patients_date_naissance` (`date_naissance`),
  ADD KEY `idx_patients_ville` (`ville`),
  ADD KEY `idx_patients_sexe` (`sexe`),
  ADD KEY `idx_patients_statut` (`statut`);

--
-- Index pour la table `patient_pathologie`
--
ALTER TABLE `patient_pathologie`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_patient_pathologie` (`patient_id`,`pathologie_id`),
  ADD KEY `diagnostic_par` (`diagnostic_par`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_pathologie` (`pathologie_id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date_diagnostic` (`date_diagnostic`);

--
-- Index pour la table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_docteur` (`docteur_id`),
  ADD KEY `idx_date` (`date_prescription`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_reference` (`reference`),
  ADD KEY `idx_consultation` (`consultation_id`),
  ADD KEY `idx_prescriptions_patient_date` (`patient_id`,`date_prescription`);

--
-- Index pour la table `prescription_details`
--
ALTER TABLE `prescription_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prescription` (`prescription_id`),
  ADD KEY `idx_medicament` (`medicament_id`);

--
-- Index pour la table `rendez_vous`
--
ALTER TABLE `rendez_vous`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_docteur` (`docteur_id`),
  ADD KEY `idx_date` (`date_rdv`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_reference` (`reference`),
  ADD KEY `idx_rappel` (`rappel_envoye`,`rappel_date`),
  ADD KEY `idx_rdv_date` (`date_rdv`);

--
-- Index pour la table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_role_name` (`role_name`);

--
-- Index pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_permission` (`role_id`,`module`,`action`),
  ADD KEY `idx_module_action` (`module`,`action`);

--
-- Index pour la table `salle_attente`
--
ALTER TABLE `salle_attente`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `docteur_id` (`docteur_id`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `called_by` (`called_by`),
  ADD KEY `removed_by` (`removed_by`),
  ADD KEY `idx_salle_attente_statut` (`statut`),
  ADD KEY `idx_salle_attente_urgence` (`urgence`),
  ADD KEY `idx_salle_attente_added_at` (`added_at`);

--
-- Index pour la table `specialites`
--
ALTER TABLE `specialites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `nom` (`nom`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_statut` (`statut`);

--
-- Index pour la table `stock_peremption`
--
ALTER TABLE `stock_peremption`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicament_id` (`medicament_id`),
  ADD KEY `idx_stock_peremption_date` (`date_peremption`);

--
-- Index pour la table `taches`
--
ALTER TABLE `taches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_taches_statut` (`statut`),
  ADD KEY `idx_taches_priorite` (`priorite`),
  ADD KEY `idx_taches_assigned` (`assigned_to`),
  ADD KEY `idx_taches_date_echeance` (`date_echeance`);

--
-- Index pour la table `tache_comments`
--
ALTER TABLE `tache_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tache_id` (`tache_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_nom_prenom` (`nom`,`prenom`),
  ADD KEY `verification_code` (`verification_code`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `backup_history`
--
ALTER TABLE `backup_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `backup_logs`
--
ALTER TABLE `backup_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `backup_schedule`
--
ALTER TABLE `backup_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `backup_settings`
--
ALTER TABLE `backup_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `docteur_specialite`
--
ALTER TABLE `docteur_specialite`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `documents_medicaux`
--
ALTER TABLE `documents_medicaux`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `equipment_history`
--
ALTER TABLE `equipment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT pour la table `log_archive`
--
ALTER TABLE `log_archive`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `medicaments`
--
ALTER TABLE `medicaments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `medicament_distribution`
--
ALTER TABLE `medicament_distribution`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `medicament_stock_log`
--
ALTER TABLE `medicament_stock_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `mouvements_stock`
--
ALTER TABLE `mouvements_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `parametres_systeme`
--
ALTER TABLE `parametres_systeme`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `pathologies`
--
ALTER TABLE `pathologies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `patient_pathologie`
--
ALTER TABLE `patient_pathologie`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `prescription_details`
--
ALTER TABLE `prescription_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT pour la table `rendez_vous`
--
ALTER TABLE `rendez_vous`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=144;

--
-- AUTO_INCREMENT pour la table `salle_attente`
--
ALTER TABLE `salle_attente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `specialites`
--
ALTER TABLE `specialites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `stock_peremption`
--
ALTER TABLE `stock_peremption`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `taches`
--
ALTER TABLE `taches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tache_comments`
--
ALTER TABLE `tache_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD CONSTRAINT `auth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `backup_history`
--
ALTER TABLE `backup_history`
  ADD CONSTRAINT `backup_history_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD CONSTRAINT `backup_logs_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `backup_schedule` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `backup_logs_ibfk_2` FOREIGN KEY (`backup_id`) REFERENCES `backup_history` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `consultations_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consultations_ibfk_2` FOREIGN KEY (`docteur_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `consultations_ibfk_3` FOREIGN KEY (`assistant_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `consultations_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `docteur_specialite`
--
ALTER TABLE `docteur_specialite`
  ADD CONSTRAINT `docteur_specialite_ibfk_1` FOREIGN KEY (`docteur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `docteur_specialite_ibfk_2` FOREIGN KEY (`specialite_id`) REFERENCES `specialites` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `documents_medicaux`
--
ALTER TABLE `documents_medicaux`
  ADD CONSTRAINT `documents_medicaux_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_medicaux_ibfk_2` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `documents_medicaux_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `documents_medicaux_ibfk_4` FOREIGN KEY (`docteur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `equipment`
--
ALTER TABLE `equipment`
  ADD CONSTRAINT `equipment_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `equipment_history`
--
ALTER TABLE `equipment_history`
  ADD CONSTRAINT `equipment_history_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `equipment_history_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `login_logs`
--
ALTER TABLE `login_logs`
  ADD CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `medicament_distribution`
--
ALTER TABLE `medicament_distribution`
  ADD CONSTRAINT `medicament_distribution_ibfk_1` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicament_distribution_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `medicament_distribution_ibfk_3` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `medicament_distribution_ibfk_4` FOREIGN KEY (`distributed_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `medicament_stock_log`
--
ALTER TABLE `medicament_stock_log`
  ADD CONSTRAINT `medicament_stock_log_ibfk_1` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicament_stock_log_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `mouvements_stock`
--
ALTER TABLE `mouvements_stock`
  ADD CONSTRAINT `fk_mouvement_medicament` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `pathologies`
--
ALTER TABLE `pathologies`
  ADD CONSTRAINT `pathologies_ibfk_1` FOREIGN KEY (`specialite_id`) REFERENCES `specialites` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `patient_pathologie`
--
ALTER TABLE `patient_pathologie`
  ADD CONSTRAINT `patient_pathologie_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `patient_pathologie_ibfk_2` FOREIGN KEY (`pathologie_id`) REFERENCES `pathologies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `patient_pathologie_ibfk_3` FOREIGN KEY (`diagnostic_par`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`docteur_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `prescription_details`
--
ALTER TABLE `prescription_details`
  ADD CONSTRAINT `prescription_details_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `rendez_vous`
--
ALTER TABLE `rendez_vous`
  ADD CONSTRAINT `rendez_vous_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rendez_vous_ibfk_2` FOREIGN KEY (`docteur_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `rendez_vous_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `salle_attente`
--
ALTER TABLE `salle_attente`
  ADD CONSTRAINT `salle_attente_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salle_attente_ibfk_2` FOREIGN KEY (`docteur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `salle_attente_ibfk_3` FOREIGN KEY (`added_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `salle_attente_ibfk_4` FOREIGN KEY (`called_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `salle_attente_ibfk_5` FOREIGN KEY (`removed_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `stock_peremption`
--
ALTER TABLE `stock_peremption`
  ADD CONSTRAINT `stock_peremption_ibfk_1` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `taches`
--
ALTER TABLE `taches`
  ADD CONSTRAINT `taches_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `taches_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `tache_comments`
--
ALTER TABLE `tache_comments`
  ADD CONSTRAINT `tache_comments_ibfk_1` FOREIGN KEY (`tache_id`) REFERENCES `taches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tache_comments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
