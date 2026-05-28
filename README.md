# 🏥 MedSystem - Application Web de Gestion Médicale

![Version](https://img.shields.io/github/v/release/kojo-codeur/gestion_medicale?filename=README.md?style=for-the-badge&logo=github&logoColor=white&label=Version)
![License](https://img.shields.io/badge/License-MIT-yellow?style=for-the-badge&logo=opensourceinitiative&logoColor=white)
![PHP Version](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL Version](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)
![Chart.js](https://img.shields.io/badge/Chart.js-4.4-FF6384?style=for-the-badge&logo=chart.js&logoColor=white)
![DataTables](https://img.shields.io/badge/DataTables-1.13-0769AD?style=for-the-badge&logo=jquery&logoColor=white)
![FullCalendar](https://img.shields.io/badge/FullCalendar-6.1-4285F4?style=for-the-badge&logo=fullcalendar&logoColor=white)

## 📌 Description

MedSystem est une application web complète de gestion médicale développée en PHP et MySQL.
Le système permet aux établissements de santé, cliniques et hôpitaux de gérer efficacement :

* Les patients
* Les consultations
* Les rendez-vous
* Les prescriptions médicales
* Les documents médicaux
* Les équipements médicaux
* Les notifications
* Les sauvegardes automatiques
* Les utilisateurs et rôles
* et autres chose aussi plus important

L'application possède une interface moderne et responsive utilisant Bootstrap 5.

---

# 🚀 Fonctionnalités principales

## 👨‍⚕️ Gestion des Patients

* Ajout des patients
* Modification des informations
* Recherche avancée
* Historique médical
* Gestion des pathologies

## 📅 Gestion des Rendez-vous

* Création de rendez-vous
* Validation et confirmation
* Gestion des urgences
* Calendrier médical

## 🩺 Gestion des Consultations

* Création automatique des références
* Diagnostic médical
* Traitements
* Ordonnances
* Historique des consultations

## 💊 Gestion des Médicaments

* Stock des médicaments
* Prix et remboursement
* Alertes de rupture
* Informations thérapeutiques

## 📄 Documents Médicaux

* Upload des fichiers médicaux
* Ordonnances
* Résultats d’analyses
* Comptes rendus
* Certificats médicaux

## 🔐 Authentification & Sécurité

* Connexion sécurisée
* Gestion des rôles
* Historique des connexions
* Logs d’audit
* Tokens d’authentification
* Vérification email

## 🔔 Notifications

* Notifications en temps réel
* Alertes système
* Notifications des rendez-vous
* Notifications des consultations

## 💾 Sauvegarde & Maintenance

* Sauvegarde automatique
* Historique des backups
* Planification des sauvegardes
* Archivage des logs

---

# 🛠️ Technologies utilisées

## Backend

* PHP 7+
* MySQL / MariaDB
* PDO

## Frontend

* HTML5
* CSS3
* Bootstrap 5
* JavaScript
* jQuery
* Font Awesome

## Base de données

* phpMyAdmin
* MariaDB 10+

---

# 🗄️ Structure de la Base de Données

La base de données `gestion_medicale` contient plusieurs tables importantes :

* utilisateurs
* patients
* consultations
* rendez_vous
* prescriptions
* documents_medicaux
* notifications
* medicaments
* equipment
* audit_logs
* login_logs
* backup_history

Le système utilise également :

* Procédures stockées
* Fonctions SQL
* Triggers MySQL

---

# ⚙️ Installation du Projet

## 1️⃣ Cloner le projet

```bash
git clone https://github.com/votre-compte/medsystem.git
```

## 2️⃣ Copier le projet dans le serveur local

Exemple :

* htdocs pour XAMPP
* www pour WAMP

---

## 3️⃣ Importer la base de données

1. Ouvrir phpMyAdmin
2. Créer une base de données :

```sql
gestion_medicale
```

3. Importer le fichier SQL fourni

---

## 4️⃣ Configurer la connexion

Modifier le fichier :

```php
config/database.php
```

Exemple :

```php
private $host = "localhost";
private $db_name = "gestion_medicale";
private $username = "root";
private $password = "";
```

---

## 5️⃣ Lancer le projet

Démarrer :

* Apache
* MySQL

Puis accéder à :

```bash
http://localhost/medsystem
```

---

# 👥 Rôles Utilisateurs

Le système prend en charge plusieurs rôles :

* Administrateur
* Docteur
* Assistant médical
* Réceptionniste ou secretaire

---

# 📷 Interface Moderne

L’application inclut :

✅ Sidebar dynamique
✅ Notifications interactives
✅ Responsive Design
✅ Dashboard professionnel
✅ Gestion des modales
✅ Recherche globale
✅ Profil utilisateur avec photo

---

# 🔒 Sécurité

* Protection des sessions
* Validation des formulaires
* Utilisation de PDO
* Prévention SQL Injection
* Logs des connexions
* Gestion des permissions

---

# 📌 Auteur

Projet développé par :
**kojo-codeur**

---

# 📄 Licence

Ce projet est destiné à des fins éducatives et académiques.

---

