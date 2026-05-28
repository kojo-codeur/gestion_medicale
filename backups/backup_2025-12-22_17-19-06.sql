-- Backup created: 2025-12-22 17:19:06
-- Database: gestion_medicale

-- Table: utilisateurs
CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `derniere_connexion` datetime DEFAULT NULL,
  `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `verification_code` varchar(32) DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_statut` (`statut`),
  KEY `idx_email` (`email`),
  KEY `idx_nom_prenom` (`nom`,`prenom`),
  KEY `verification_code` (`verification_code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;

INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `specialite`, `telephone`, `adresse`, `date_naissance`, `sexe`, `photo`, `statut`, `derniere_connexion`, `date_creation`, `date_modification`, `verification_code`, `email_verified_at`) VALUES ('1', 'Admin', 'System', 'admin@medical.com', '$2y$10$y0sEP4m5BT5jVwie8XEeBu7c8ZgUJau7acZ94HZb7GT29k3yHDfE.', 'admin', NULL, '+1234567890', '', '1994-01-22', 'M', NULL, 'actif', '2025-12-22 16:46:54', '2025-12-20 00:41:53', '2025-12-22 16:46:54', NULL, NULL);
INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `specialite`, `telephone`, `adresse`, `date_naissance`, `sexe`, `photo`, `statut`, `derniere_connexion`, `date_creation`, `date_modification`, `verification_code`, `email_verified_at`) VALUES ('2', 'Dupont', 'Jean', 'jean.dupont@medical.com', '$2y$10$ikCSUHureSSiYliQ8mgK/uReygTVfUEtcB5zii3lSjct4YnaLNyCq', 'docteur', 'Cardiologie', '+33123456790', NULL, NULL, NULL, NULL, 'actif', '2025-12-22 16:42:43', '2025-12-20 00:41:53', '2025-12-22 16:42:43', NULL, NULL);
INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `specialite`, `telephone`, `adresse`, `date_naissance`, `sexe`, `photo`, `statut`, `derniere_connexion`, `date_creation`, `date_modification`, `verification_code`, `email_verified_at`) VALUES ('3', 'Martin', 'Sophie', 'sophie.martin@medical.com', '$2y$10$RQCo24mb2HSwT8iho.yIeOybb19006XYdssNYzpCHgED7Hw57rHym', 'secretaire', NULL, '+33123456791', NULL, NULL, NULL, NULL, 'actif', NULL, '2025-12-20 00:41:53', '2025-12-20 00:49:18', NULL, NULL);
INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `specialite`, `telephone`, `adresse`, `date_naissance`, `sexe`, `photo`, `statut`, `derniere_connexion`, `date_creation`, `date_modification`, `verification_code`, `email_verified_at`) VALUES ('4', 'Bernard', 'Pierre', 'pierre.bernard@medical.com', '$2y$10$Fx4mSP8bEYaekp6G.T6v/.YA.MEhWHncAYYsqlKeBzLuAKsegLrE.', 'assistant', 'Cardiologie', '+33123456792', NULL, NULL, NULL, NULL, 'actif', NULL, '2025-12-20 00:41:53', '2025-12-20 00:49:18', NULL, NULL);
INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `specialite`, `telephone`, `adresse`, `date_naissance`, `sexe`, `photo`, `statut`, `derniere_connexion`, `date_creation`, `date_modification`, `verification_code`, `email_verified_at`) VALUES ('5', 'Leroy', 'Marie', 'marie.leroy@medical.com', '$2y$10$5Ftwbu3tdpgWhfcZvUal.eyEKwEPCDJpPZ31QAWrytfK8JPwCpshe', 'docteur', 'Dermatologie', '+33123456793', NULL, NULL, NULL, NULL, 'actif', NULL, '2025-12-20 00:41:53', '2025-12-20 00:49:18', NULL, NULL);
INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `specialite`, `telephone`, `adresse`, `date_naissance`, `sexe`, `photo`, `statut`, `derniere_connexion`, `date_creation`, `date_modification`, `verification_code`, `email_verified_at`) VALUES ('6', 'Petit', 'Luc', 'luc.petit@medical.com', '$2y$10$hJrmvR7PBP3agdiWsn9PjuI7k4jl/OQwCS9IJsJ/LZE/yzTwk0jZa', 'assistant', 'Dermatologie', '+33123456794', NULL, NULL, NULL, NULL, 'actif', NULL, '2025-12-20 00:41:53', '2025-12-22 16:34:29', NULL, NULL);
INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `specialite`, `telephone`, `adresse`, `date_naissance`, `sexe`, `photo`, `statut`, `derniere_connexion`, `date_creation`, `date_modification`, `verification_code`, `email_verified_at`) VALUES ('7', 'Robert', 'Alice', 'alice.robert@medical.com', '$2y$10$lXJ14ACv1cptL/FWZ0b91OPY3HUDZU3bxlGUtgp6L.ZitrCiwjO8q', 'docteur', 'Médecine Générale', '+33123456795', NULL, NULL, NULL, NULL, 'actif', NULL, '2025-12-20 00:41:53', '2025-12-20 00:49:19', NULL, NULL);
INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `password`, `role`, `specialite`, `telephone`, `adresse`, `date_naissance`, `sexe`, `photo`, `statut`, `derniere_connexion`, `date_creation`, `date_modification`, `verification_code`, `email_verified_at`) VALUES ('8', 'Simon', 'Thomas', 'thomas.simon@medical.com', '$2y$10$z6PFf/.nTG8F1t4hncFvvuZKAhhdUqJD8ByS7dIJFoK8Tb4nLjAAi', 'secretaire', NULL, '+33123456796', NULL, NULL, NULL, NULL, 'actif', NULL, '2025-12-20 00:41:53', '2025-12-20 00:49:19', NULL, NULL);

-- Table: patients
CREATE TABLE `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_patient` (`code_patient`),
  KEY `idx_nom_prenom` (`nom`,`prenom`),
  KEY `idx_code_patient` (`code_patient`),
  KEY `idx_date_naissance` (`date_naissance`),
  KEY `idx_statut` (`statut`),
  KEY `idx_ville` (`ville`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_patients_date_naissance` (`date_naissance`),
  KEY `idx_patients_ville` (`ville`),
  KEY `idx_patients_sexe` (`sexe`),
  KEY `idx_patients_statut` (`statut`),
  CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4;

INSERT INTO `patients` (`id`, `code_patient`, `nom`, `prenom`, `date_naissance`, `sexe`, `lieu_naissance`, `adresse`, `ville`, `code_postal`, `pays`, `telephone`, `telephone_urgence`, `email`, `profession`, `situation_familiale`, `nombre_enfants`, `groupe_sanguin`, `rhésus`, `poids`, `taille`, `imc`, `antecedents_familiaux`, `antecedents_personnels`, `allergies`, `medicaments_habituels`, `habitudes`, `notes`, `statut`, `date_enregistrement`, `date_modification`, `created_by`) VALUES ('1', 'PAT-202401-0001', 'Durand', 'Marie', '1985-06-15', 'F', NULL, '123 Rue de Paris', 'Paris', '75001', 'France', '+33612345678', NULL, 'marie.durand@email.com', NULL, 'celibataire', '0', 'A+', NULL, NULL, NULL, NULL, NULL, 'Hypertension familiale, Cholestérol', 'Pénicilline, Arachides', NULL, NULL, NULL, 'actif', '2025-12-20 00:41:53', '2025-12-20 00:41:53', '3');
INSERT INTO `patients` (`id`, `code_patient`, `nom`, `prenom`, `date_naissance`, `sexe`, `lieu_naissance`, `adresse`, `ville`, `code_postal`, `pays`, `telephone`, `telephone_urgence`, `email`, `profession`, `situation_familiale`, `nombre_enfants`, `groupe_sanguin`, `rhésus`, `poids`, `taille`, `imc`, `antecedents_familiaux`, `antecedents_personnels`, `allergies`, `medicaments_habituels`, `habitudes`, `notes`, `statut`, `date_enregistrement`, `date_modification`, `created_by`) VALUES ('2', 'PAT-202401-0002', 'Leroy', 'Paul', '1978-03-22', 'M', NULL, '456 Avenue Victor Hugo', 'Lyon', '69002', 'France', '+33687654321', NULL, 'paul.leroy@email.com', NULL, 'celibataire', '0', 'O+', NULL, NULL, NULL, NULL, NULL, 'Diabète type 2, Cholestérol', 'Aucune', NULL, NULL, NULL, 'actif', '2025-12-20 00:41:53', '2025-12-20 00:41:53', '3');
INSERT INTO `patients` (`id`, `code_patient`, `nom`, `prenom`, `date_naissance`, `sexe`, `lieu_naissance`, `adresse`, `ville`, `code_postal`, `pays`, `telephone`, `telephone_urgence`, `email`, `profession`, `situation_familiale`, `nombre_enfants`, `groupe_sanguin`, `rhésus`, `poids`, `taille`, `imc`, `antecedents_familiaux`, `antecedents_personnels`, `allergies`, `medicaments_habituels`, `habitudes`, `notes`, `statut`, `date_enregistrement`, `date_modification`, `created_by`) VALUES ('3', 'PAT-202401-0003', 'Petit', 'Julie', '1992-11-30', 'F', NULL, '789 Boulevard Saint-Germain', 'Marseille', '13001', 'France', '+33611223344', NULL, 'julie.petit@email.com', NULL, 'celibataire', '0', 'B+', NULL, NULL, NULL, NULL, NULL, 'Asthme depuis l\'enfance, Eczéma', 'Acariens, Pollen, Poils de chat', NULL, NULL, NULL, 'actif', '2025-12-20 00:41:53', '2025-12-20 00:41:53', '8');
INSERT INTO `patients` (`id`, `code_patient`, `nom`, `prenom`, `date_naissance`, `sexe`, `lieu_naissance`, `adresse`, `ville`, `code_postal`, `pays`, `telephone`, `telephone_urgence`, `email`, `profession`, `situation_familiale`, `nombre_enfants`, `groupe_sanguin`, `rhésus`, `poids`, `taille`, `imc`, `antecedents_familiaux`, `antecedents_personnels`, `allergies`, `medicaments_habituels`, `habitudes`, `notes`, `statut`, `date_enregistrement`, `date_modification`, `created_by`) VALUES ('4', 'PAT-202401-0004', 'Moreau', 'Thomas', '1965-08-12', 'M', NULL, '321 Rue de la République', 'Toulouse', '31000', 'France', '+33699887766', NULL, 'thomas.moreau@email.com', NULL, 'celibataire', '0', 'AB+', NULL, NULL, NULL, NULL, NULL, 'Opération genou 2018, Hypertension', 'Iode, Crustacés', NULL, NULL, NULL, 'actif', '2025-12-20 00:41:53', '2025-12-20 00:41:53', '3');
INSERT INTO `patients` (`id`, `code_patient`, `nom`, `prenom`, `date_naissance`, `sexe`, `lieu_naissance`, `adresse`, `ville`, `code_postal`, `pays`, `telephone`, `telephone_urgence`, `email`, `profession`, `situation_familiale`, `nombre_enfants`, `groupe_sanguin`, `rhésus`, `poids`, `taille`, `imc`, `antecedents_familiaux`, `antecedents_personnels`, `allergies`, `medicaments_habituels`, `habitudes`, `notes`, `statut`, `date_enregistrement`, `date_modification`, `created_by`) VALUES ('5', 'PAT-202401-0005', 'Simon', 'Claire', '1972-12-05', 'F', NULL, '654 Rue du Commerce', 'Lille', '59000', 'France', '+33655443322', NULL, 'claire.simon@email.com', NULL, 'celibataire', '0', 'A-', NULL, NULL, NULL, NULL, NULL, 'Migraines chroniques, Dépression', 'Aspirine, Ibuprofène', NULL, NULL, NULL, 'actif', '2025-12-20 00:41:53', '2025-12-20 00:41:53', '8');
INSERT INTO `patients` (`id`, `code_patient`, `nom`, `prenom`, `date_naissance`, `sexe`, `lieu_naissance`, `adresse`, `ville`, `code_postal`, `pays`, `telephone`, `telephone_urgence`, `email`, `profession`, `situation_familiale`, `nombre_enfants`, `groupe_sanguin`, `rhésus`, `poids`, `taille`, `imc`, `antecedents_familiaux`, `antecedents_personnels`, `allergies`, `medicaments_habituels`, `habitudes`, `notes`, `statut`, `date_enregistrement`, `date_modification`, `created_by`) VALUES ('6', 'PAT-202401-0006', 'Dubois', 'Marc', '1988-07-19', 'M', NULL, '987 Avenue des Champs-Élysées', 'Paris', '75008', 'France', '+33666778899', NULL, 'marc.dubois@email.com', NULL, 'celibataire', '0', 'O-', NULL, NULL, NULL, NULL, NULL, 'Sportif, Aucun antécédent', 'Aucune', NULL, NULL, NULL, 'actif', '2025-12-20 00:41:53', '2025-12-20 00:41:53', '3');
INSERT INTO `patients` (`id`, `code_patient`, `nom`, `prenom`, `date_naissance`, `sexe`, `lieu_naissance`, `adresse`, `ville`, `code_postal`, `pays`, `telephone`, `telephone_urgence`, `email`, `profession`, `situation_familiale`, `nombre_enfants`, `groupe_sanguin`, `rhésus`, `poids`, `taille`, `imc`, `antecedents_familiaux`, `antecedents_personnels`, `allergies`, `medicaments_habituels`, `habitudes`, `notes`, `statut`, `date_enregistrement`, `date_modification`, `created_by`) VALUES ('7', 'PAT-202401-0007', 'Laurent', 'Sophie', '1995-04-25', 'F', NULL, '159 Rue de la Liberté', 'Bordeaux', '33000', 'France', '+33622334455', NULL, 'sophie.laurent@email.com', NULL, 'celibataire', '0', 'B-', NULL, NULL, NULL, NULL, NULL, 'Allergies saisonnières', 'Pollens, Moisissures', NULL, NULL, NULL, 'actif', '2025-12-20 00:41:53', '2025-12-20 00:41:53', '8');

-- Table: consultations
CREATE TABLE `consultations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `assistant_id` (`assistant_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_docteur` (`docteur_id`),
  KEY `idx_date` (`date_consultation`),
  KEY `idx_statut` (`statut`),
  KEY `idx_type` (`type_consultation`),
  KEY `idx_reference` (`reference`),
  KEY `idx_urgence` (`urgence`),
  KEY `idx_consultations_date` (`date_consultation`),
  KEY `idx_consultations_patient_docteur` (`patient_id`,`docteur_id`),
  KEY `idx_consultations_statut_date` (`statut`,`date_consultation`),
  CONSTRAINT `consultations_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consultations_ibfk_2` FOREIGN KEY (`docteur_id`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `consultations_ibfk_3` FOREIGN KEY (`assistant_id`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `consultations_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4;

INSERT INTO `consultations` (`id`, `reference`, `patient_id`, `docteur_id`, `assistant_id`, `date_consultation`, `duree`, `type_consultation`, `motif`, `histoire_maladie`, `examen_clinique`, `examen_complementaire`, `diagnostic`, `diagnostic_detail`, `traitement`, `ordonnance`, `recommandations`, `notes`, `statut`, `facturee`, `urgence`, `confidentialite`, `created_at`, `updated_at`, `created_by`) VALUES ('1', 'CONS-202401-0001', '1', '2', NULL, '2024-01-15 09:00:00', '30', 'controle', 'Contrôle tension artérielle', NULL, NULL, NULL, 'Hypertension stable sous traitement', NULL, 'Continuer Amlodipine 5mg, surveillance mensuelle', NULL, NULL, NULL, 'termine', '0', '0', 'normal', '2025-12-20 00:41:53', '2025-12-20 00:41:53', '4');
INSERT INTO `consultations` (`id`, `reference`, `patient_id`, `docteur_id`, `assistant_id`, `date_consultation`, `duree`, `type_consultation`, `motif`, `histoire_maladie`, `examen_clinique`, `examen_complementaire`, `diagnostic`, `diagnostic_detail`, `traitement`, `ordonnance`, `recommandations`, `notes`, `statut`, `facturee`, `urgence`, `confidentialite`, `created_at`, `updated_at`, `created_by`) VALUES ('2', 'CONS-202401-0002', '2', '2', NULL, '2024-01-16 10:30:00', '30', 'urgence', 'Douleurs thoraciques', NULL, NULL, NULL, 'Angor stable, pas d\'urgence', NULL, 'Repos, ECG de contrôle, consultation cardiologue', NULL, NULL, NULL, 'termine', '0', '0', 'normal', '2025-12-20 00:41:53', '2025-12-20 00:41:53', '4');
INSERT INTO `consultations` (`id`, `reference`, `patient_id`, `docteur_id`, `assistant_id`, `date_consultation`, `duree`, `type_consultation`, `motif`, `histoire_maladie`, `examen_clinique`, `examen_complementaire`, `diagnostic`, `diagnostic_detail`, `traitement`, `ordonnance`, `recommandations`, `notes`, `statut`, `facturee`, `urgence`, `confidentialite`, `created_at`, `updated_at`, `created_by`) VALUES ('3', 'CONS-202401-0003', '3', '5', NULL, '2024-01-17 14:00:00', '30', 'premiere', 'Éruption cutanée', NULL, NULL, NULL, 'Eczéma atopique', NULL, 'Crème corticoïde, émollients, éviction allergènes', NULL, NULL, NULL, 'termine', '0', '0', 'normal', '2025-12-20 00:41:53', '2025-12-20 00:41:53', '6');
INSERT INTO `consultations` (`id`, `reference`, `patient_id`, `docteur_id`, `assistant_id`, `date_consultation`, `duree`, `type_consultation`, `motif`, `histoire_maladie`, `examen_clinique`, `examen_complementaire`, `diagnostic`, `diagnostic_detail`, `traitement`, `ordonnance`, `recommandations`, `notes`, `statut`, `facturee`, `urgence`, `confidentialite`, `created_at`, `updated_at`, `created_by`) VALUES ('4', 'CONS-202401-0004', '4', '2', NULL, '2024-01-18 11:00:00', '30', 'suivi', 'Suivi diabète', NULL, NULL, NULL, 'Diabète équilibré', NULL, 'Continuer Metformine 1000mg, régime contrôlé', NULL, NULL, NULL, 'termine', '0', '0', 'normal', '2025-12-20 00:41:53', '2025-12-20 00:41:53', '4');
INSERT INTO `consultations` (`id`, `reference`, `patient_id`, `docteur_id`, `assistant_id`, `date_consultation`, `duree`, `type_consultation`, `motif`, `histoire_maladie`, `examen_clinique`, `examen_complementaire`, `diagnostic`, `diagnostic_detail`, `traitement`, `ordonnance`, `recommandations`, `notes`, `statut`, `facturee`, `urgence`, `confidentialite`, `created_at`, `updated_at`, `created_by`) VALUES ('5', 'CONS-202401-0005', '5', '5', NULL, '2024-01-19 15:30:00', '30', '', 'Migraines récurrentes', NULL, NULL, NULL, 'Migraines avec aura', NULL, 'Sumatriptan 50mg si crise, propranolol 40mg/j en prévention', NULL, NULL, NULL, 'termine', '0', '0', 'normal', '2025-12-20 00:41:53', '2025-12-20 00:41:53', '6');
INSERT INTO `consultations` (`id`, `reference`, `patient_id`, `docteur_id`, `assistant_id`, `date_consultation`, `duree`, `type_consultation`, `motif`, `histoire_maladie`, `examen_clinique`, `examen_complementaire`, `diagnostic`, `diagnostic_detail`, `traitement`, `ordonnance`, `recommandations`, `notes`, `statut`, `facturee`, `urgence`, `confidentialite`, `created_at`, `updated_at`, `created_by`) VALUES ('6', 'CONS-202401-0006', '6', '7', NULL, '2024-01-20 10:00:00', '30', 'premiere', 'Examen médical général', NULL, NULL, NULL, 'Bon état de santé général', NULL, 'Aucun traitement nécessaire', NULL, NULL, NULL, 'termine', '0', '0', 'normal', '2025-12-20 00:41:53', '2025-12-20 00:41:53', '3');
INSERT INTO `consultations` (`id`, `reference`, `patient_id`, `docteur_id`, `assistant_id`, `date_consultation`, `duree`, `type_consultation`, `motif`, `histoire_maladie`, `examen_clinique`, `examen_complementaire`, `diagnostic`, `diagnostic_detail`, `traitement`, `ordonnance`, `recommandations`, `notes`, `statut`, `facturee`, `urgence`, `confidentialite`, `created_at`, `updated_at`, `created_by`) VALUES ('7', 'CONS-202401-0007', '7', '7', NULL, '2024-01-21 14:30:00', '30', 'controle', 'Contrôle allergies', NULL, NULL, NULL, 'Allergies saisonnières confirmées', NULL, 'Antihistaminiques, éviction des allergènes', NULL, NULL, NULL, 'termine', '0', '0', 'normal', '2025-12-20 00:41:53', '2025-12-20 00:41:53', '3');

-- Table: rendez_vous
CREATE TABLE `rendez_vous` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `created_by` (`created_by`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_docteur` (`docteur_id`),
  KEY `idx_date` (`date_rdv`),
  KEY `idx_statut` (`statut`),
  KEY `idx_reference` (`reference`),
  KEY `idx_rappel` (`rappel_envoye`,`rappel_date`),
  KEY `idx_rdv_date` (`date_rdv`),
  CONSTRAINT `rendez_vous_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rendez_vous_ibfk_2` FOREIGN KEY (`docteur_id`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `rendez_vous_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4;

INSERT INTO `rendez_vous` (`id`, `reference`, `patient_id`, `docteur_id`, `date_rdv`, `duree`, `type_rdv`, `motif`, `notes`, `statut`, `rappel_envoye`, `rappel_date`, `created_at`, `updated_at`, `created_by`) VALUES ('1', 'RDV-202402-0001', '1', '2', '2024-02-01 09:00:00', '30', 'consultation', 'Contrôle tension mensuel', NULL, 'confirme', '0', NULL, '2025-12-20 00:41:53', '2025-12-20 00:41:53', '3');
INSERT INTO `rendez_vous` (`id`, `reference`, `patient_id`, `docteur_id`, `date_rdv`, `duree`, `type_rdv`, `motif`, `notes`, `statut`, `rappel_envoye`, `rappel_date`, `created_at`, `updated_at`, `created_by`) VALUES ('2', 'RDV-202402-0002', '2', '2', '2024-02-02 10:30:00', '30', 'consultation', 'ECG de contrôle', NULL, 'confirme', '0', NULL, '2025-12-20 00:41:53', '2025-12-20 00:41:53', '3');
INSERT INTO `rendez_vous` (`id`, `reference`, `patient_id`, `docteur_id`, `date_rdv`, `duree`, `type_rdv`, `motif`, `notes`, `statut`, `rappel_envoye`, `rappel_date`, `created_at`, `updated_at`, `created_by`) VALUES ('3', 'RDV-202402-0003', '3', '5', '2024-02-03 14:00:00', '30', 'consultation', 'Suivi eczéma', NULL, 'confirme', '0', NULL, '2025-12-20 00:41:53', '2025-12-20 00:41:53', '8');
INSERT INTO `rendez_vous` (`id`, `reference`, `patient_id`, `docteur_id`, `date_rdv`, `duree`, `type_rdv`, `motif`, `notes`, `statut`, `rappel_envoye`, `rappel_date`, `created_at`, `updated_at`, `created_by`) VALUES ('4', 'RDV-202402-0004', '4', '2', '2024-02-04 11:00:00', '30', 'consultation', 'Contrôle glycémie', NULL, 'confirme', '0', NULL, '2025-12-20 00:41:53', '2025-12-20 00:41:53', '3');
INSERT INTO `rendez_vous` (`id`, `reference`, `patient_id`, `docteur_id`, `date_rdv`, `duree`, `type_rdv`, `motif`, `notes`, `statut`, `rappel_envoye`, `rappel_date`, `created_at`, `updated_at`, `created_by`) VALUES ('5', 'RDV-202402-0005', '5', '5', '2024-02-05 15:30:00', '30', 'consultation', 'Évaluation traitement migraine', NULL, 'confirme', '0', NULL, '2025-12-20 00:41:53', '2025-12-20 00:41:53', '8');
INSERT INTO `rendez_vous` (`id`, `reference`, `patient_id`, `docteur_id`, `date_rdv`, `duree`, `type_rdv`, `motif`, `notes`, `statut`, `rappel_envoye`, `rappel_date`, `created_at`, `updated_at`, `created_by`) VALUES ('6', 'RDV-202402-0006', '6', '7', '2024-02-06 10:00:00', '30', 'consultation', 'Examen de routine', NULL, 'confirme', '0', NULL, '2025-12-20 00:41:53', '2025-12-20 00:41:53', '3');
INSERT INTO `rendez_vous` (`id`, `reference`, `patient_id`, `docteur_id`, `date_rdv`, `duree`, `type_rdv`, `motif`, `notes`, `statut`, `rappel_envoye`, `rappel_date`, `created_at`, `updated_at`, `created_by`) VALUES ('7', 'RDV-202402-0007', '7', '7', '2024-02-07 14:30:00', '30', 'consultation', 'Suivi allergies', NULL, 'confirme', '0', NULL, '2025-12-20 00:41:53', '2025-12-20 00:41:53', '3');

-- Table: medicaments
CREATE TABLE `medicaments` (
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
  KEY `idx_statut` (`statut`),
  KEY `idx_stock` (`stock_actuel`),
  KEY `idx_classe` (`classe_therapeutique`),
  KEY `idx_laboratoire` (`laboratoire`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4;

INSERT INTO `medicaments` (`id`, `code_cip`, `nom_commercial`, `nom_generique`, `laboratoire`, `forme`, `dosage`, `classe_therapeutique`, `indications`, `contre_indications`, `effets_secondaires`, `posologie`, `precautions`, `interactions`, `conditionnement`, `stock_actuel`, `stock_minimum`, `prix_unitaire`, `remboursement`, `statut`, `created_at`, `updated_at`) VALUES ('1', '3400933596033', 'Doliprane', 'Paracétamol', 'Sanofi', 'comprime', '1000mg', 'Antalgique', 'Douleurs et fièvre', NULL, NULL, NULL, NULL, NULL, NULL, '150', '20', '2.50', '65.00', 'actif', '2025-12-22 18:15:40', '2025-12-22 18:15:40');
INSERT INTO `medicaments` (`id`, `code_cip`, `nom_commercial`, `nom_generique`, `laboratoire`, `forme`, `dosage`, `classe_therapeutique`, `indications`, `contre_indications`, `effets_secondaires`, `posologie`, `precautions`, `interactions`, `conditionnement`, `stock_actuel`, `stock_minimum`, `prix_unitaire`, `remboursement`, `statut`, `created_at`, `updated_at`) VALUES ('2', '3400931254876', 'Ibuprofène', 'Ibuprofène', 'Bayer', 'comprime', '400mg', 'Anti-inflammatoire', 'Douleurs et inflammations', NULL, NULL, NULL, NULL, NULL, NULL, '80', '15', '3.20', '35.00', 'actif', '2025-12-22 18:15:40', '2025-12-22 18:15:40');
INSERT INTO `medicaments` (`id`, `code_cip`, `nom_commercial`, `nom_generique`, `laboratoire`, `forme`, `dosage`, `classe_therapeutique`, `indications`, `contre_indications`, `effets_secondaires`, `posologie`, `precautions`, `interactions`, `conditionnement`, `stock_actuel`, `stock_minimum`, `prix_unitaire`, `remboursement`, `statut`, `created_at`, `updated_at`) VALUES ('3', '3400934875129', 'Amoxicilline', 'Amoxicilline', 'GSK', 'gelule', '500mg', 'Antibiotique', 'Infections bactériennes', NULL, NULL, NULL, NULL, NULL, NULL, '45', '25', '5.80', '100.00', 'actif', '2025-12-22 18:15:40', '2025-12-22 18:15:40');
INSERT INTO `medicaments` (`id`, `code_cip`, `nom_commercial`, `nom_generique`, `laboratoire`, `forme`, `dosage`, `classe_therapeutique`, `indications`, `contre_indications`, `effets_secondaires`, `posologie`, `precautions`, `interactions`, `conditionnement`, `stock_actuel`, `stock_minimum`, `prix_unitaire`, `remboursement`, `statut`, `created_at`, `updated_at`) VALUES ('4', '3400936548721', 'Ventoline', 'Salbutamol', 'GSK', 'spray', '100mcg/dose', 'Bronchodilatateur', 'Asthme, bronchite', NULL, NULL, NULL, NULL, NULL, NULL, '120', '30', '12.50', '65.00', 'actif', '2025-12-22 18:15:40', '2025-12-22 18:15:40');
INSERT INTO `medicaments` (`id`, `code_cip`, `nom_commercial`, `nom_generique`, `laboratoire`, `forme`, `dosage`, `classe_therapeutique`, `indications`, `contre_indications`, `effets_secondaires`, `posologie`, `precautions`, `interactions`, `conditionnement`, `stock_actuel`, `stock_minimum`, `prix_unitaire`, `remboursement`, `statut`, `created_at`, `updated_at`) VALUES ('5', '3400932154873', 'Levothyrox', 'Lévothyroxine', 'Merck', 'comprime', '75mcg', 'Hormone thyroïdienne', 'Hypothyroïdie', NULL, NULL, NULL, NULL, NULL, NULL, '95', '40', '4.30', '100.00', 'actif', '2025-12-22 18:15:40', '2025-12-22 18:15:40');
INSERT INTO `medicaments` (`id`, `code_cip`, `nom_commercial`, `nom_generique`, `laboratoire`, `forme`, `dosage`, `classe_therapeutique`, `indications`, `contre_indications`, `effets_secondaires`, `posologie`, `precautions`, `interactions`, `conditionnement`, `stock_actuel`, `stock_minimum`, `prix_unitaire`, `remboursement`, `statut`, `created_at`, `updated_at`) VALUES ('6', '3400936541234', 'Atorvastatine', 'Atorvastatine', 'Pfizer', 'comprime', '20mg', 'Hypolipémiant', 'Cholestérol élevé', NULL, NULL, NULL, NULL, NULL, NULL, '60', '20', '6.75', '65.00', 'actif', '2025-12-22 18:15:40', '2025-12-22 18:15:40');
INSERT INTO `medicaments` (`id`, `code_cip`, `nom_commercial`, `nom_generique`, `laboratoire`, `forme`, `dosage`, `classe_therapeutique`, `indications`, `contre_indications`, `effets_secondaires`, `posologie`, `precautions`, `interactions`, `conditionnement`, `stock_actuel`, `stock_minimum`, `prix_unitaire`, `remboursement`, `statut`, `created_at`, `updated_at`) VALUES ('7', '3400939874561', 'Lantus', 'Insuline glargine', 'Sanofi', 'injectable', '100UI/ml', 'Antidiabétique', 'Diabète type 1 et 2', NULL, NULL, NULL, NULL, NULL, NULL, '35', '15', '45.00', '100.00', 'actif', '2025-12-22 18:15:40', '2025-12-22 18:15:40');
INSERT INTO `medicaments` (`id`, `code_cip`, `nom_commercial`, `nom_generique`, `laboratoire`, `forme`, `dosage`, `classe_therapeutique`, `indications`, `contre_indications`, `effets_secondaires`, `posologie`, `precautions`, `interactions`, `conditionnement`, `stock_actuel`, `stock_minimum`, `prix_unitaire`, `remboursement`, `statut`, `created_at`, `updated_at`) VALUES ('8', '3400933216547', 'Xanax', 'Alprazolam', 'Pfizer', 'comprime', '0.25mg', 'Anxiolytique', 'Anxiété, crises de panique', NULL, NULL, NULL, NULL, NULL, NULL, '25', '10', '8.90', '65.00', 'actif', '2025-12-22 18:15:40', '2025-12-22 18:15:40');
INSERT INTO `medicaments` (`id`, `code_cip`, `nom_commercial`, `nom_generique`, `laboratoire`, `forme`, `dosage`, `classe_therapeutique`, `indications`, `contre_indications`, `effets_secondaires`, `posologie`, `precautions`, `interactions`, `conditionnement`, `stock_actuel`, `stock_minimum`, `prix_unitaire`, `remboursement`, `statut`, `created_at`, `updated_at`) VALUES ('9', '3400936547890', 'Zyrtec', 'Cétirizine', 'UCB', 'comprime', '10mg', 'Antihistaminique', 'Allergies', NULL, NULL, NULL, NULL, NULL, NULL, '110', '25', '3.45', '35.00', 'actif', '2025-12-22 18:15:40', '2025-12-22 18:15:40');
INSERT INTO `medicaments` (`id`, `code_cip`, `nom_commercial`, `nom_generique`, `laboratoire`, `forme`, `dosage`, `classe_therapeutique`, `indications`, `contre_indications`, `effets_secondaires`, `posologie`, `precautions`, `interactions`, `conditionnement`, `stock_actuel`, `stock_minimum`, `prix_unitaire`, `remboursement`, `statut`, `created_at`, `updated_at`) VALUES ('10', '3400931234567', 'Lasilix', 'Furosémide', 'Sanofi', 'comprime', '40mg', 'Diurétique', 'Hypertension, œdèmes', NULL, NULL, NULL, NULL, NULL, NULL, '70', '20', '2.80', '100.00', 'actif', '2025-12-22 18:15:40', '2025-12-22 18:15:40');

-- Table: prescriptions
CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_docteur` (`docteur_id`),
  KEY `idx_date` (`date_prescription`),
  KEY `idx_statut` (`statut`),
  KEY `idx_reference` (`reference`),
  KEY `idx_consultation` (`consultation_id`),
  KEY `idx_prescriptions_patient_date` (`patient_id`,`date_prescription`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`docteur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

INSERT INTO `prescriptions` (`id`, `consultation_id`, `patient_id`, `docteur_id`, `reference`, `date_prescription`, `medicaments`, `posologie`, `duree_traitement`, `renouvelable`, `nombre_renouvellements`, `notes`, `statut`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '2', 'PRES-202401-0001', '2024-01-15', '[{\"nom\":\"Amlodipine\",\"dosage\":\"5mg\",\"forme\":\"comprime\",\"quantite\":30,\"posologie\":\"1 comprimé par jour\",\"repas\":\"indifferent\"}]', 'Prendre 1 comprimé par jour, de préférence le matin', '30 jours', '0', '0', NULL, 'active', '2025-12-20 00:41:53', '2025-12-20 00:41:53');
INSERT INTO `prescriptions` (`id`, `consultation_id`, `patient_id`, `docteur_id`, `reference`, `date_prescription`, `medicaments`, `posologie`, `duree_traitement`, `renouvelable`, `nombre_renouvellements`, `notes`, `statut`, `created_at`, `updated_at`) VALUES ('2', '3', '3', '5', 'PRES-202401-0002', '2024-01-17', '[{\"nom\":\"Corticoide topique\",\"dosage\":\"0.1%\",\"forme\":\"creme\",\"quantite\":1,\"posologie\":\"appliquer 2 fois par jour\",\"zone\":\"zones atteintes\"},{\"nom\":\"Emollient\",\"dosage\":\"\",\"forme\":\"creme\",\"quantite\":1,\"posologie\":\"appliquer quotidiennement\",\"zone\":\"peau seche\"}]', 'Appliquer la crème corticoïde sur les lésions 2 fois par jour. Utiliser l\'émollient quotidiennement sur toute la peau.', '15 jours', '0', '0', NULL, 'active', '2025-12-20 00:41:53', '2025-12-20 00:41:53');
INSERT INTO `prescriptions` (`id`, `consultation_id`, `patient_id`, `docteur_id`, `reference`, `date_prescription`, `medicaments`, `posologie`, `duree_traitement`, `renouvelable`, `nombre_renouvellements`, `notes`, `statut`, `created_at`, `updated_at`) VALUES ('3', '5', '5', '5', 'PRES-202401-0003', '2024-01-19', '[{\"nom\":\"Sumatriptan\",\"dosage\":\"50mg\",\"forme\":\"comprime\",\"quantite\":6,\"posologie\":\"1 comprimé au début de la crise\",\"max\":\"2 comprimés par 24h\"},{\"nom\":\"Propranolol\",\"dosage\":\"40mg\",\"forme\":\"comprime\",\"quantite\":30,\"posologie\":\"1 comprimé par jour\",\"repas\":\"pendant les repas\"}]', 'Sumatriptan : prendre au début de la crise (max 2/24h). Propranolol : 1 comprimé par jour pendant les repas.', '30 jours', '0', '0', NULL, 'active', '2025-12-20 00:41:53', '2025-12-20 00:41:53');

