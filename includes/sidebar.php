<?php
// includes/sidebar.php
include_once '../config/database.php';

if (!isLoggedIn()) {
    return;
}

$pdo = Database::getInstance()->getConnection();


$role = $_SESSION['role'];
$currentPage = basename($_SERVER['PHP_SELF']);

// Définir les menus selon les rôles
$menus = [];

if ($role === 'admin') {
    $menus = [
        'dashboard' => [
            'icon' => 'fas fa-tachometer-alt',
            'title' => 'Dashboard',
            'url' => 'admin/dashboard.php',
            'badge' => null
        ],
        'utilisateurs' => [
            'icon' => 'fas fa-users-cog',
            'title' => 'Utilisateurs',
            'url' => 'admin/utilisateurs.php',
            'badge' => null,
            'submenu' => [
                ['title' => 'Liste des utilisateurs', 'url' => 'admin/utilisateurs.php'],
                ['title' => 'Ajouter un utilisateur', 'url' => 'admin/utilisateurs.php?action=add'],
                ['title' => 'Rôles et permissions', 'url' => 'admin/permissions.php']
            ]
        ],
        
        'gestion' => [
            'icon' => 'fas fa-cogs',
            'title' => 'Gestion',
            'url' => 'admin/gestion.php',
            'badge' => null,
            'submenu' => [
                ['title' => 'Configuration', 'url' => 'admin/gestion.php'],
                ['title' => 'Maladies', 'url' => 'admin/maladies.php'],
                ['title' => 'Médicaments', 'url' => 'admin/medicaments.php'],
                ['title' => 'Spécialités', 'url' => 'admin/specialites.php']
            ]
        ],
        'rapports' => [
            'icon' => 'fas fa-chart-bar',
            'title' => 'Rapports',
            'url' => 'admin/rapports.php',
            'badge' => null
        ],
        'systeme' => [
            'icon' => 'fas fa-server',
            'title' => 'Système',
            'url' => 'admin/systeme.php',
            'badge' => null,
            'submenu' => [
                ['title' => 'Sauvegardes', 'url' => 'admin/sauvegardes.php'],
                ['title' => 'Logs système', 'url' => 'admin/logs.php'],
                ['title' => 'Maintenance', 'url' => 'admin/maintenance.php']
            ]
        ],
        'statistiques' => [
            'icon' => 'fas fa-chart-line',
            'title' => 'Mes Statistiques',
            'url' => 'admin/statistiques.php',
            'badge' => null
        ]
    ];
} elseif ($role === 'docteur') {
    $docteur_id = $_SESSION['user_id'];
    
    // Initialiser les compteurs
    $rdv_today = 0;
    $consultations_pending = 0;
    
    try {
        require_once '../config/database.php';
        
        // Compter les RDV aujourd'hui
        $stmt_rdv = $pdo->prepare("
            SELECT COUNT(*) FROM rendez_vous 
            WHERE docteur_id = ? AND DATE(date_rdv) = CURDATE() AND statut = 'confirme'
        ");
        $stmt_rdv->execute([$docteur_id]);
        $rdv_today = $stmt_rdv->fetchColumn();
        
        // Compter les consultations en attente
        $stmt_consult = $pdo->prepare("
            SELECT COUNT(*) FROM consultations 
            WHERE docteur_id = ? AND statut IN ('planifie', 'en_cours')
        ");
        $stmt_consult->execute([$docteur_id]);
        $consultations_pending = $stmt_consult->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur sidebar docteur: " . $e->getMessage());
    }
    
    $menus = [
        'dashboard' => [
            'icon' => 'fas fa-tachometer-alt',
            'title' => 'Dashboard',
            'url' => 'docteur/dashboard.php',
            'badge' => null
        ],
        'mes_patients' => [
            'icon' => 'fas fa-user-injured',
            'title' => 'Mes Patients',
            'url' => 'docteur/patients.php',
            'badge' => null
        ],
        'consultations' => [
            'icon' => 'fas fa-stethoscope',
            'title' => 'Consultations',
            'url' => 'docteur/consultations.php',
            'badge' => $consultations_pending > 0 ? $consultations_pending : null
        ],
        'prescriptions' => [
            'icon' => 'fas fa-prescription',
            'title' => 'Prescriptions',
            'url' => 'docteur/prescriptions.php',
            'badge' => null
        ],
        'rendezvous' => [
            'icon' => 'fas fa-calendar-check',
            'title' => 'Rendez-vous',
            'url' => 'docteur/rendezvous.php',
            'badge' => $rdv_today > 0 ? $rdv_today : null
        ],
        'documents' => [
            'icon' => 'fas fa-file-medical',
            'title' => 'Documents',
            'url' => 'docteur/documents.php',
            'badge' => null,
            'submenu' => [
                ['title' => 'Ordonnances', 'url' => 'docteur/ordonnances.php'],
                ['title' => 'Certificats', 'url' => 'docteur/certificats.php'],
                ['title' => 'Comptes rendus', 'url' => 'docteur/comptes-rendus.php']
            ]
        ]
    ];
} elseif ($role === 'secretaire') {
    // Initialiser les compteurs
    $rdv_today = 0;
    $new_patients = 0;
    
    try {
        require_once '../config/database.php';
        
        // Compter les RDV aujourd'hui
        $stmt_rdv = $pdo->query("
            SELECT COUNT(*) FROM rendez_vous 
            WHERE DATE(date_rdv) = CURDATE() AND statut = 'confirme'
        ");
        $rdv_today = $stmt_rdv->fetchColumn();
        
        // Compter les nouveaux patients ce mois
        $stmt_patients = $pdo->query("
            SELECT COUNT(*) FROM patients 
            WHERE MONTH(date_enregistrement) = MONTH(CURDATE()) 
            AND YEAR(date_enregistrement) = YEAR(CURDATE())
        ");
        $new_patients = $stmt_patients->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur sidebar secretaire: " . $e->getMessage());
    }
    
    $menus = [
        'dashboard' => [
            'icon' => 'fas fa-tachometer-alt',
            'title' => 'Dashboard',
            'url' => 'secretaire/dashboard.php',
            'badge' => null
        ],
        'patients' => [
            'icon' => 'fas fa-users',
            'title' => 'Patients',
            'url' => 'secretaire/patients.php',
            'badge' => $new_patients > 0 ? $new_patients : null
        ],
        'rendezvous' => [
            'icon' => 'fas fa-calendar-alt',
            'title' => 'Rendez-vous',
            'url' => 'secretaire/rendezvous.php',
            'badge' => $rdv_today > 0 ? $rdv_today : null
        ],
        'facturation' => [
            'icon' => 'fas fa-file-invoice-dollar',
            'title' => 'Facturation',
            'url' => 'secretaire/facturation.php',
            'badge' => null,
            'submenu' => [
                ['title' => 'Nouvelle facture', 'url' => 'secretaire/factures.php?action=add'],
                ['title' => 'Factures en attente', 'url' => 'secretaire/factures.php?statut=attente'],
                ['title' => 'Factures payées', 'url' => 'secretaire/factures.php?statut=paye']
            ]
        ],
        'agenda' => [
            'icon' => 'fas fa-calendar-day',
            'title' => 'Agenda',
            'url' => 'secretaire/agenda.php',
            'badge' => null
        ],
        'archives' => [
            'icon' => 'fas fa-archive',
            'title' => 'Archives',
            'url' => 'secretaire/archives.php',
            'badge' => null
        ],
        'communication' => [
            'icon' => 'fas fa-comments',
            'title' => 'Communication',
            'url' => 'secretaire/communication.php',
            'badge' => null,
            'submenu' => [
                ['title' => 'Rappels RDV', 'url' => 'secretaire/communication.php?action=rappels'],
                ['title' => 'SMS', 'url' => 'secretaire/communication.php?action=sms'],
                ['title' => 'Emails', 'url' => 'secretaire/communication.php?action=emails']
            ]
        ]
    ];
} elseif ($role === 'assistant') {
    // Initialiser les compteurs
    $patients_en_attente = 0;
    
    try {
        require_once '../config/database.php';
        
        // Compter les patients en attente
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT c.patient_id) 
            FROM consultations c 
            WHERE c.statut = 'planifie' 
            AND DATE(c.date_consultation) = CURDATE()
        ");
        $patients_en_attente = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur sidebar assistant: " . $e->getMessage());
    }
    
    $menus = [
        'dashboard' => [
            'icon' => 'fas fa-tachometer-alt',
            'title' => 'Dashboard',
            'url' => 'assistant/dashboard.php',
            'badge' => null
        ],
        'patients' => [
            'icon' => 'fas fa-user-plus',
            'title' => 'Patients',
            'url' => 'assistant/patients.php',
            'badge' => null
        ],
        'consultations' => [
            'icon' => 'fas fa-clipboard-list',
            'title' => 'Consultations',
            'url' => 'assistant/consultations.php',
            'badge' => $patients_en_attente > 0 ? $patients_en_attente : null
        ],
        'salle_attente' => [
            'icon' => 'fas fa-chair',
            'title' => 'Salle d\'attente',
            'url' => 'assistant/salle-attente.php',
            'badge' => null
        ],
        'suivi' => [
            'icon' => 'fas fa-heartbeat',
            'title' => 'Suivi médical',
            'url' => 'assistant/suivi.php',
            'badge' => null
        ],
        'preparations' => [
            'icon' => 'fas fa-syringe',
            'title' => 'Préparations',
            'url' => 'assistant/preparations.php',
            'badge' => null,
            'submenu' => [
                ['title' => 'Matériel stérile', 'url' => 'assistant/materiel.php?page=materiel'],
                ['title' => 'Médicaments', 'url' => 'assistant/materiel.php?page=stock'],
                ['title' => 'Équipement', 'url' => 'assistant/materiel.php?page=equipement']
            ]
        ],
        'taches' => [
            'icon' => 'fas fa-tasks',
            'title' => 'Tâches',
            'url' => 'assistant/taches.php',
            'badge' => null
        ]
    ];
}


$photo_url = '';
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT photo FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_photo = $stmt->fetchColumn();
    if (!empty($user_photo)) {
        $photo_url = '../' . $user_photo;
        if (!file_exists($photo_url)) {
            $photo_url = ''; 
        }
    }
}

?>

<!-- Sidebar -->
<aside id="sidebar" class="sidebar">
    <!-- Profile -->
    <div class="sidebar-profile p-4 text-center border-bottom">
        <div class="mb-3">
            <div class="avatar-lg mx-auto">
                <?php if (!empty($photo_url)): ?>
                    <img src="<?= htmlspecialchars($photo_url) ?>" class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                <?php else: ?>
                    <?php 
                    $initials = strtoupper(substr($_SESSION['prenom'], 0, 1) . substr($_SESSION['nom'], 0, 1));
                    $roleColor = $role === 'admin' ? 'danger' : 
                                ($role === 'docteur' ? 'primary' : 
                                ($role === 'secretaire' ? 'success' : 'warning'));
                    ?>
                    <span class="avatar-initials bg-<?php echo $roleColor; ?>">
                        <?php echo $initials; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <h6 class="mb-1 fw-semibold"><?php echo $_SESSION['prenom'] . ' ' . $_SESSION['nom']; ?></h6>
        <span class="badge bg-<?php echo $roleColor; ?>"><?php echo ucfirst($role); ?></span>
        <div class="mt-2 small text-muted">
            <i class="fas fa-clock me-1"></i>
            Dernière connexion: <?php echo $_SESSION['last_login'] ?? 'Aujourd\'hui'; ?>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="sidebar-nav py-3">
        <ul class="nav flex-column">
            <?php foreach ($menus as $key => $menu): 
                $isActive = ($currentPage === basename($menu['url'])) || 
                           (isset($menu['submenu']) && in_array($currentPage, array_map('basename', array_column($menu['submenu'], 'url'))));
                $hasSubmenu = isset($menu['submenu']);
            ?>
            <li class="nav-item">
                <?php if ($hasSubmenu): ?>
                <a class="nav-link <?php echo $isActive ? 'active' : ''; ?> collapsed" 
                   data-bs-toggle="collapse" 
                   href="#submenu-<?php echo $key; ?>"
                   role="button"
                   aria-expanded="<?php echo $isActive ? 'true' : 'false'; ?>"
                   aria-controls="submenu-<?php echo $key; ?>">
                    <i class="<?php echo $menu['icon']; ?> me-3"></i>
                    <span><?php echo $menu['title']; ?></span>
                    <?php if ($menu['badge']): ?>
                    <span class="badge bg-danger ms-auto"><?php echo $menu['badge']; ?></span>
                    <?php endif; ?>
                    <i class="fas fa-chevron-down ms-auto toggle-icon"></i>
                </a>
                <div class="collapse <?php echo $isActive ? 'show' : ''; ?>" id="submenu-<?php echo $key; ?>">
                    <ul class="nav flex-column submenu">
                        <?php foreach ($menu['submenu'] as $subitem): 
                            $isSubActive = ($currentPage === basename($subitem['url']));
                        ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $isSubActive ? 'active' : ''; ?>" 
                               href="../<?php echo $subitem['url']; ?>">
                                <i class="fas fa-circle small me-2"></i>
                                <?php echo $subitem['title']; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php else: ?>
                <a class="nav-link <?php echo $isActive ? 'active' : ''; ?>" 
                   href="../<?php echo $menu['url']; ?>">
                    <i class="<?php echo $menu['icon']; ?> me-3"></i>
                    <span><?php echo $menu['title']; ?></span>
                    <?php if ($menu['badge']): ?>
                    <span class="badge bg-danger ms-auto"><?php echo $menu['badge']; ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
            
            <!-- Quick Actions -->
            <li class="nav-item mt-4">
                <div class="px-3 mb-2">
                    <small class="text-uppercase text-muted fw-bold">Actions rapides</small>
                </div>
            </li>
            
            <?php if ($role === 'docteur'): ?>
            <li class="nav-item">
                <a class="nav-link" href="../docteur/consultations.php?action=add">
                    <i class="fas fa-plus-circle me-3 text-success"></i>
                    <span>Nouvelle consultation</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../docteur/prescriptions.php?action=add">
                    <i class="fas fa-prescription-bottle-alt me-3 text-primary"></i>
                    <span>Nouvelle prescription</span>
                </a>
            </li>
            <?php elseif ($role === 'secretaire'): ?>
            <li class="nav-item">
                <a class="nav-link" href="../secretaire/rendezvous.php?action=add">
                    <i class="fas fa-calendar-plus me-3 text-primary"></i>
                    <span>Nouveau RDV</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../secretaire/patients.php?action=add">
                    <i class="fas fa-user-plus me-3 text-success"></i>
                    <span>Nouveau patient</span>
                </a>
            </li>
            <?php elseif ($role === 'assistant'): ?>
            <li class="nav-item">
                <a class="nav-link" href="../assistant/patients.php?action=add">
                    <i class="fas fa-user-plus me-3 text-primary"></i>
                    <span>Enregistrer patient</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../assistant/consultations.php">
                    <i class="fas fa-clipboard-check me-3 text-success"></i>
                    <span>Vérifier consultations</span>
                </a>
            </li>
            <?php elseif ($role === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="../admin/utilisateurs.php?action=add">
                    <i class="fas fa-user-plus me-3 text-primary"></i>
                    <span>Nouvel utilisateur</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../admin/sauvegardes.php">
                    <i class="fas fa-database me-3 text-warning"></i>
                    <span>Sauvegarde système</span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Settings & Logout -->
            <li class="nav-item mt-4">
                <hr class="mx-3">
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../admin/settings.php">
                    <i class="fas fa-cog me-3 text-muted"></i>
                    <span>Paramètres</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-3"></i>
                    <span>Déconnexion</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer p-3 border-top">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <i class="fas fa-circle text-success small"></i>
            </div>
            <div class="flex-grow-1">
                <small class="text-muted">Système actif</small>
                <div class="progress" style="height: 3px;">
                    <div class="progress-bar bg-success" style="width: 85%"></div>
                </div>
            </div>
        </div>
    </div>
</aside>

<style>
/* Sidebar Styles spécifiques - IMPORTANT : styles uniques ici, pas de doublons */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 280px;
    background: white;
    border-right: 1px solid #e5e7eb;
    z-index: 1000 !important; /* BAS pour être sous les modales */
    overflow-y: auto;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 2px 0 15px rgba(0,0,0,0.08);
    transform: translateX(-100%);
}

/* Sur desktop, la sidebar est toujours visible */
@media (min-width: 992px) {
    .sidebar {
        transform: translateX(0) !important;
    }
}

.sidebar.show {
    transform: translateX(0);
}

/* Profile */
.avatar-lg {
    width: 80px;
    height: 80px;
    margin: 0 auto;
}

.avatar-initials {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    color: white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Navigation */
.sidebar-nav .nav-link {
    color: #4b5563;
    padding: 12px 20px;
    margin: 4px 12px;
    border-radius: 8px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    font-weight: 500;
}

.sidebar-nav .nav-link:hover {
    background-color: #f3f4f6;
    color: #111827;
    transform: translateX(2px);
}

.sidebar-nav .nav-link.active {
    background-color: #4361ee;
    color: white;
    box-shadow: 0 2px 8px rgba(67, 97, 238, 0.3);
}

.sidebar-nav .nav-link.active i {
    color: white;
}

.sidebar-nav .nav-link .toggle-icon {
    transition: transform 0.3s;
}

.sidebar-nav .nav-link[aria-expanded="true"] .toggle-icon {
    transform: rotate(180deg);
}

.sidebar-nav .nav-link i {
    width: 20px;
    text-align: center;
    transition: color 0.2s;
}

.submenu {
    padding-left: 40px;
    background: rgba(243, 244, 246, 0.5);
    border-radius: 8px;
    margin: 0 12px 8px;
}

.submenu .nav-link {
    padding: 10px 12px;
    margin: 2px 0;
    font-size: 0.9rem;
}

.submenu .nav-link.active {
    background-color: rgba(67, 97, 238, 0.1);
    color: #4361ee;
}

.submenu .nav-link i {
    font-size: 6px;
}

/* Badges */
.badge {
    font-size: 0.7rem;
    padding: 0.25em 0.6em;
}

/* Scrollbar */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.sidebar::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .sidebar {
        width: 250px;
    }
}

/* Empêcher le scroll du body quand la sidebar est ouverte sur mobile */
body.sidebar-open {
    overflow: hidden;
}

/* Quand une modale est ouverte, rendre la sidebar transparente */
body.modal-open .sidebar {
    opacity: 0.3;
    pointer-events: none;
}

/* Ajustement du contenu principal */
.main-content {
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@media (min-width: 992px) {
    .main-content {
        margin-left: 280px;
    }
}

@media (max-width: 991px) {
    .main-content {
        margin-left: 0 !important;
    }
}
</style>

<script>
// Sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    // Fonction pour ouvrir/fermer le sidebar
    function toggleSidebar() {
        sidebar.classList.toggle('show');
        if (sidebarOverlay) {
            sidebarOverlay.classList.toggle('show');
        }
        document.body.classList.toggle('sidebar-open');
    }
    
    // Exposer la fonction globalement pour le bouton mobile
    window.toggleSidebar = toggleSidebar;
    
    // Fermer le sidebar quand on clique sur un lien (sur mobile)
    const sidebarLinks = document.querySelectorAll('.sidebar .nav-link:not([data-bs-toggle="collapse"])');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                sidebar.classList.remove('show');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('show');
                }
                document.body.classList.remove('sidebar-open');
            }
        });
    });
    
    // Fermer le sidebar avec la touche Escape
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('show');
            }
            document.body.classList.remove('sidebar-open');
        }
    });
    
    // Ajuster sur le redimensionnement de la fenêtre
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            sidebar.classList.remove('show');
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('show');
            }
            document.body.classList.remove('sidebar-open');
        }
    });
});
</script>