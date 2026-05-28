<?php
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$title = 'Recherche - MedSystem';
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];
$totalResults = 0;
$searchCategory = isset($_GET['category']) ? $_GET['category'] : 'all';

// Fonction pour échapper les caractères spéciaux HTML
function escapeHtml($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Fonction pour surligner les termes recherchés
function highlightSearchTerms($text, $query) {
    if (empty($query) || empty($text)) {
        return $text;
    }
    
    $words = preg_split('/\s+/', $query);
    $patterns = [];
    
    foreach ($words as $word) {
        if (strlen($word) > 2) { // Ignorer les mots trop courts
            $patterns[] = preg_quote($word, '/');
        }
    }
    
    if (empty($patterns)) {
        return $text;
    }
    
    $pattern = '/(' . implode('|', $patterns) . ')/i';
    return preg_replace($pattern, '<mark class="bg-warning">$1</mark>', $text);
}

// Fonction pour calculer la pertinence
function calculateRelevance($text, $query) {
    $score = 0;
    $words = preg_split('/\s+/', strtolower($query));
    $text = strtolower($text);
    
    foreach ($words as $word) {
        if (strlen($word) > 2) {
            // Plus de points si le mot est au début
            if (strpos($text, $word) === 0) {
                $score += 10;
            }
            // Points pour chaque occurrence
            $score += substr_count($text, $word) * 3;
            // Points si le mot correspond exactement (avec accents)
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $text)) {
                $score += 5;
            }
        }
    }
    
    return $score;
}

// Si une recherche a été effectuée
if (!empty($searchQuery)) {
    try {
    
        // Liste des tables à rechercher
        $searchTables = [
            'patients' => [
                'fields' => ['nom', 'prenom', 'email', 'telephone', 'adresse', 'ville', 'code_postal', 'mutuelle', 'numero_secu'],
                'display_fields' => ['nom', 'prenom', 'email', 'telephone', 'ville'],
                'title_field' => "CONCAT(prenom, ' ', nom)",
                'link' => 'patient.php?id=',
                'id_field' => 'id',
                'icon' => 'fas fa-user-injured',
                'color' => 'primary'
            ],
            'rendez_vous' => [
                'fields' => ['motif', 'notes'],
                'display_fields' => ['motif', 'notes'],
                'title_field' => 'motif',
                'link' => 'rendezvous.php?id=',
                'id_field' => 'id',
                'icon' => 'fas fa-calendar-check',
                'color' => 'success'
            ],
            'consultations' => [
                'fields' => ['diagnostic', 'traitement', 'observations', 'motif'],
                'display_fields' => ['motif', 'diagnostic'],
                'title_field' => 'motif',
                'link' => 'consultation.php?id=',
                'id_field' => 'id',
                'icon' => 'fas fa-stethoscope',
                'color' => 'info'
            ],
            'prescriptions' => [
                'fields' => ['medicaments', 'posologie', 'instructions'],
                'display_fields' => ['medicaments'],
                'title_field' => 'medicaments',
                'link' => 'prescription.php?id=',
                'id_field' => 'id',
                'icon' => 'fas fa-prescription',
                'color' => 'warning'
            ],
            'utilisateurs' => [
                'fields' => ['nom', 'prenom', 'email', 'telephone', 'role', 'specialite'],
                'display_fields' => ['nom', 'prenom', 'email', 'role'],
                'title_field' => "CONCAT(prenom, ' ', nom)",
                'link' => 'profile.php?id=',
                'id_field' => 'id',
                'icon' => 'fas fa-user-md',
                'color' => 'danger'
            ]
        ];
        
        // Préparer les termes de recherche pour la requête SQL
        $searchWords = preg_split('/\s+/', $searchQuery);
        $searchConditions = [];
        $params = [];
        
        foreach ($searchWords as $index => $word) {
            if (strlen($word) > 2) {
                $searchConditions[] = "?";
                $params[] = "%$word%";
            }
        }
        
        if (empty($searchConditions)) {
            // Si aucun mot significatif, chercher toute la phrase
            $searchConditions[] = "?";
            $params[] = "%$searchQuery%";
        }
        
        // Rechercher dans chaque table
        foreach ($searchTables as $tableName => $tableConfig) {
            // Si une catégorie spécifique est sélectionnée, ignorer les autres
            if ($searchCategory !== 'all' && $searchCategory !== $tableName) {
                continue;
            }
            
            $fields = $tableConfig['fields'];
            $fieldConditions = [];
            
            // Créer les conditions pour chaque champ
            foreach ($fields as $field) {
                foreach ($searchConditions as $condition) {
                    $fieldConditions[] = "$field LIKE $condition";
                }
            }
            
            if (empty($fieldConditions)) {
                continue;
            }
            
            // Construire la requête SQL
            $fieldConditionsStr = implode(' OR ', $fieldConditions);
            $idField = $tableConfig['id_field'];
            $titleField = $tableConfig['title_field'];
            $displayFields = $tableConfig['display_fields'];
            
            // Récupérer les données de jointure si nécessaire
            $joinClause = '';
            $selectFields = "t.*, $titleField as search_title";
            
            // Ajouter des jointures pour les relations
            switch ($tableName) {
                case 'rendez_vous':
                    $joinClause = "LEFT JOIN patients p ON t.patient_id = p.id";
                    $selectFields .= ", CONCAT(p.prenom, ' ', p.nom) as patient_nom";
                    break;
                case 'consultations':
                    $joinClause = "LEFT JOIN patients p ON t.patient_id = p.id";
                    $selectFields .= ", CONCAT(p.prenom, ' ', p.nom) as patient_nom";
                    break;
                case 'prescriptions':
                    $joinClause = "LEFT JOIN patients p ON t.patient_id = p.id 
                                  LEFT JOIN consultations c ON t.consultation_id = c.id";
                    $selectFields .= ", CONCAT(p.prenom, ' ', p.nom) as patient_nom";
                    break;
            }
            
            $sql = "SELECT $selectFields 
                    FROM $tableName t 
                    $joinClause 
                    WHERE ($fieldConditionsStr) 
                    ORDER BY t.created_at DESC 
                    LIMIT 50";
            
            $stmt = $pdo->prepare($sql);
            
            // Dupliquer les paramètres pour chaque champ
            $allParams = [];
            foreach ($fields as $field) {
                foreach ($params as $param) {
                    $allParams[] = $param;
                }
            }
            
            $stmt->execute($allParams);
            $tableResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ajouter les métadonnées et calculer la pertinence
            foreach ($tableResults as $result) {
                // Construire le texte complet pour la recherche
                $fullText = '';
                foreach ($displayFields as $field) {
                    if (isset($result[$field]) && !empty($result[$field])) {
                        $fullText .= ' ' . $result[$field];
                    }
                }
                
                // Ajouter les informations supplémentaires
                $result['_metadata'] = [
                    'table' => $tableName,
                    'table_config' => $tableConfig,
                    'relevance' => calculateRelevance($fullText, $searchQuery),
                    'full_text' => trim($fullText)
                ];
                
                $results[] = $result;
            }
        }
        
        // Trier par pertinence
        usort($results, function($a, $b) {
            return $b['_metadata']['relevance'] <=> $a['_metadata']['relevance'];
        });
        
        $totalResults = count($results);
        
    } catch (PDOException $e) {
        $error = "Erreur lors de la recherche : " . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- En-tête de recherche -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 mb-4">
                        <i class="fas fa-search me-2"></i>Recherche globale
                    </h1>
                    
                    <!-- Formulaire de recherche -->
                    <form action="search.php" method="get" class="mb-4">
                        <div class="input-group input-group-lg">
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   name="q" 
                                   value="<?php echo escapeHtml($searchQuery); ?>" 
                                   placeholder="Rechercher un patient, rendez-vous, consultation..." 
                                   aria-label="Termes de recherche"
                                   required>
                            <button class="btn btn-primary btn-lg" type="submit">
                                <i class="fas fa-search me-1"></i>Rechercher
                            </button>
                        </div>
                        
                        <!-- Filtres par catégorie -->
                        <div class="mt-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="category" id="catAll" value="all" <?php echo $searchCategory === 'all' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="catAll">
                                    <i class="fas fa-layer-group me-1"></i>Tout
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="category" id="catPatients" value="patients" <?php echo $searchCategory === 'patients' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="catPatients">
                                    <i class="fas fa-user-injured me-1"></i>Patients
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="category" id="catRdv" value="rendez_vous" <?php echo $searchCategory === 'rendez_vous' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="catRdv">
                                    <i class="fas fa-calendar-check me-1"></i>Rendez-vous
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="category" id="catConsult" value="consultations" <?php echo $searchCategory === 'consultations' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="catConsult">
                                    <i class="fas fa-stethoscope me-1"></i>Consultations
                                </label>
                            </div>
                        </div>
                    </form>
                    
                    <?php if (!empty($searchQuery)): ?>
                    <div class="alert alert-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-info-circle me-2"></i>
                                <strong><?php echo $totalResults; ?></strong> résultat<?php echo $totalResults !== 1 ? 's' : ''; ?> pour "<strong><?php echo escapeHtml($searchQuery); ?></strong>"
                            </div>
                            <?php if ($totalResults > 0): ?>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportResults()">
                                <i class="fas fa-download me-1"></i>Exporter
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Résultats de recherche -->
    <?php if (!empty($searchQuery)): ?>
        <?php if ($totalResults > 0): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($results as $result): 
                                    $metadata = $result['_metadata'];
                                    $config = $metadata['table_config'];
                                ?>
                                <a href="<?php echo $config['link'] . $result[$config['id_field']]; ?>" 
                                   class="list-group-item list-group-item-action border-0 py-3 px-4">
                                    <div class="d-flex align-items-start">
                                        <!-- Icône -->
                                        <div class="flex-shrink-0">
                                            <div class="rounded-circle bg-<?php echo $config['color']; ?>-subtle p-3">
                                                <i class="<?php echo $config['icon']; ?> fa-lg text-<?php echo $config['color']; ?>"></i>
                                            </div>
                                        </div>
                                        
                                        <!-- Contenu -->
                                        <div class="flex-grow-1 ms-3">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <h5 class="mb-0">
                                                    <?php 
                                                    $title = isset($result['search_title']) ? $result['search_title'] : 'Sans titre';
                                                    echo highlightSearchTerms(escapeHtml($title), $searchQuery);
                                                    ?>
                                                </h5>
                                                <div>
                                                    <span class="badge bg-<?php echo $config['color']; ?>-subtle text-<?php echo $config['color']; ?>">
                                                        <?php 
                                                        $tableLabels = [
                                                            'patients' => 'Patient',
                                                            'rendez_vous' => 'Rendez-vous',
                                                            'consultations' => 'Consultation',
                                                            'prescriptions' => 'Prescription',
                                                            'utilisateurs' => 'Personnel'
                                                        ];
                                                        echo $tableLabels[$metadata['table']] ?? $metadata['table'];
                                                        ?>
                                                    </span>
                                                    <?php if ($metadata['relevance'] > 20): ?>
                                                    <span class="badge bg-success ms-1">
                                                        <i class="fas fa-star me-1"></i>Pertinent
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Informations supplémentaires -->
                                            <div class="text-muted mb-2">
                                                <?php 
                                                $displayText = '';
                                                foreach ($config['display_fields'] as $field) {
                                                    if (isset($result[$field]) && !empty($result[$field])) {
                                                        $displayText .= escapeHtml($result[$field]) . ' • ';
                                                    }
                                                }
                                                
                                                // Afficher les informations de patient pour les jointures
                                                if (isset($result['patient_nom']) && !empty($result['patient_nom'])) {
                                                    $displayText = 'Patient : ' . escapeHtml($result['patient_nom']) . ' • ' . $displayText;
                                                }
                                                
                                                echo rtrim(highlightSearchTerms($displayText, $searchQuery), ' • ');
                                                ?>
                                            </div>
                                            
                                            <!-- Date et score de pertinence -->
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <?php if (isset($result['created_at'])): ?>
                                                    <i class="far fa-clock me-1"></i>
                                                    <?php 
                                                        $date = new DateTime($result['created_at']);
                                                        echo $date->format('d/m/Y H:i');
                                                    ?>
                                                    <?php endif; ?>
                                                </small>
                                                <small class="text-muted">
                                                    Score : <?php echo $metadata['relevance']; ?> pts
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalResults > 50): ?>
                    <div class="mt-4">
                        <nav aria-label="Pagination">
                            <ul class="pagination justify-content-center">
                                <li class="page-item disabled">
                                    <span class="page-link">Page 1</span>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="#" onclick="loadMoreResults()">
                                        <i class="fas fa-chevron-down me-1"></i>Charger plus de résultats
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Aucun résultat -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <div class="mb-4">
                                <i class="fas fa-search fa-4x text-muted mb-3"></i>
                                <h3 class="h4 text-muted">Aucun résultat trouvé</h3>
                                <p class="text-muted mb-4">
                                    Aucun élément ne correspond à vos critères de recherche.
                                </p>
                                <div class="row justify-content-center">
                                    <div class="col-md-8 col-lg-6">
                                        <div class="card border">
                                            <div class="card-body">
                                                <h5 class="card-title">Suggestions :</h5>
                                                <ul class="text-start">
                                                    <li>Vérifiez l'orthographe des termes recherchés</li>
                                                    <li>Utilisez des termes plus généraux</li>
                                                    <li>Essayez d'autres mots-clés</li>
                                                    <li>Recherchez dans une catégorie spécifique</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Aide à la recherche -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5 mb-3"><i class="fas fa-lightbulb me-2"></i>Conseils de recherche</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 border">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-users text-primary me-2"></i>Recherche de patients
                                        </h5>
                                        <p class="card-text">
                                            Tapez le nom, prénom, email, numéro de sécurité sociale ou numéro de téléphone d'un patient.
                                        </p>
                                        <small class="text-muted">
                                            Exemples : "Dupont", "jean.dupont@email.com", "1234567890123"
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 border">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-calendar-alt text-success me-2"></i>Recherche de rendez-vous
                                        </h5>
                                        <p class="card-text">
                                            Recherchez par motif de consultation, date ou notes associées au rendez-vous.
                                        </p>
                                        <small class="text-muted">
                                            Exemples : "contrôle annuel", "vaccin", "janvier 2024"
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 border">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-file-medical text-info me-2"></i>Recherche médicale
                                        </h5>
                                        <p class="card-text">
                                            Trouvez des consultations par diagnostic, traitement ou observations médicales.
                                        </p>
                                        <small class="text-muted">
                                            Exemples : "grippe", "antibiotique", "tension artérielle"
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 border">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-pills text-warning me-2"></i>Recherche de prescriptions
                                        </h5>
                                        <p class="card-text">
                                            Recherchez des médicaments, posologies ou instructions particulières.
                                        </p>
                                        <small class="text-muted">
                                            Exemples : "paracétamol", "2 fois par jour", "après repas"
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recherches récentes -->
                        <div class="mt-4">
                            <h4 class="h6 mb-3"><i class="fas fa-history me-2"></i>Recherches fréquentes</h4>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="search.php?q=vaccin&category=consultations" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-syringe me-1"></i>vaccin
                                </a>
                                <a href="search.php?q=contrôle&category=rendez_vous" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-calendar-check me-1"></i>contrôle
                                </a>
                                <a href="search.php?q=ordonnance&category=prescriptions" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-prescription me-1"></i>ordonnance
                                </a>
                                <a href="search.php?q=urgent&category=all" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-exclamation-triangle me-1"></i>urgent
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Scripts JavaScript -->
<script>
// Exporter les résultats
function exportResults() {
    const searchQuery = "<?php echo escapeHtml($searchQuery); ?>";
    const category = "<?php echo escapeHtml($searchCategory); ?>";
    
    // Créer un formulaire pour l'export
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_search.php';
    
    const queryInput = document.createElement('input');
    queryInput.type = 'hidden';
    queryInput.name = 'query';
    queryInput.value = searchQuery;
    
    const categoryInput = document.createElement('input');
    categoryInput.type = 'hidden';
    categoryInput.name = 'category';
    categoryInput.value = category;
    
    form.appendChild(queryInput);
    form.appendChild(categoryInput);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Charger plus de résultats (pagination AJAX)
function loadMoreResults() {
    const searchQuery = "<?php echo escapeHtml($searchQuery); ?>";
    const category = "<?php echo escapeHtml($searchCategory); ?>";
    const currentCount = <?php echo $totalResults; ?>;
    
    // Afficher un indicateur de chargement
    const loadButton = document.querySelector('[onclick="loadMoreResults()"]');
    if (loadButton) {
        loadButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Chargement...';
        loadButton.disabled = true;
    }
    
    // Envoyer une requête AJAX
    fetch(`ajax/search_more.php?q=${encodeURIComponent(searchQuery)}&category=${category}&offset=${currentCount}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.results.length > 0) {
                // Ajouter les nouveaux résultats
                appendResults(data.results);
                
                // Mettre à jour le bouton
                if (loadButton) {
                    if (data.has_more) {
                        loadButton.innerHTML = '<i class="fas fa-chevron-down me-1"></i>Charger plus de résultats';
                        loadButton.disabled = false;
                    } else {
                        loadButton.parentElement.parentElement.remove();
                    }
                }
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            if (loadButton) {
                loadButton.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Erreur, réessayer';
                loadButton.disabled = false;
                loadButton.onclick = loadMoreResults;
            }
        });
}

// Fonction pour ajouter des résultats (à implémenter selon votre structure)
function appendResults(results) {
    // Implémentez cette fonction pour ajouter dynamiquement des résultats
    console.log('Résultats à ajouter:', results);
}

// Recherche rapide avec suggestions
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="q"]');
    if (searchInput) {
        let debounceTimer;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                if (this.value.length >= 2) {
                    fetchSuggestions(this.value);
                }
            }, 300);
        });
    }
});

// Récupérer les suggestions
function fetchSuggestions(query) {
    fetch(`ajax/search_suggestions.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuggestions(data.suggestions, query);
            }
        });
}

// Afficher les suggestions (simple implémentation)
function showSuggestions(suggestions, query) {
    // Implémentez l'affichage des suggestions selon votre interface
    console.log('Suggestions:', suggestions);
}

// Raccourci clavier pour la recherche
document.addEventListener('keydown', function(e) {
    if (e.key === '/' && !e.target.matches('input, textarea')) {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput) {
            searchInput.focus();
        }
    }
    
    if (e.key === 'Escape') {
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput) {
            searchInput.blur();
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>