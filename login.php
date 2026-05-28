<?php
require_once 'config/database.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Traitement de la connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    try {
        // Vérifier l'utilisateur
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? AND statut = 'actif'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Mettre à jour la dernière connexion
            $pdo->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?")
                ->execute([$user['id']]);
            
            // Journaliser la connexion
            $pdo->prepare("
                INSERT INTO login_logs 
                (user_id, login_time, ip_address, user_agent, success) 
                VALUES (?, NOW(), ?, ?, 1)
            ")->execute([
                $user['id'],
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
            
            // Créer la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['prenom'] = $user['prenom'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['specialite'] = $user['specialite'];
            $_SESSION['last_login'] = date('d/m/Y H:i');
            
            // Cookie "Se souvenir de moi"
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (30 * 24 * 60 * 60);
                
                setcookie('remember_token', $token, $expires, '/', '', true, true);
                
                // Stocker le token en base
                $pdo->prepare("
                    INSERT INTO auth_tokens 
                    (user_id, token, expires_at, created_at) 
                    VALUES (?, ?, ?, NOW())
                ")->execute([
                    $user['id'],
                    hash('sha256', $token),
                    date('Y-m-d H:i:s', $expires)
                ]);
            }
            
            $redirect = '';
            switch($user['role']) {
                case 'admin':
                    $redirect = 'admin/dashboard.php';
                    break;
                case 'docteur':
                    $redirect = 'docteur/dashboard.php';
                    break;
                case 'secretaire':
                    $redirect = 'secretaire/dashboard.php';
                    break;
                case 'assistant':
                    $redirect = 'assistant/dashboard.php';
                    break;
                default:
                    $redirect = 'index.php';
                    break;
            }
            
            header("Location: $redirect");
            exit();
            
        } else {
            $pdo->prepare("
                INSERT INTO login_logs 
                (user_id, login_time, ip_address, user_agent, success) 
                VALUES (NULL, NOW(), ?, ?, 0)
            ")->execute([
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
            
            $error = 'Identifiants incorrects ou compte inactif';
        }
        
    } catch (Exception $e) {
        $error = 'Une erreur est survenue. Veuillez réessayer.';
    }
}

// Vérifier le cookie "Se souvenir de moi"
if (empty($_SESSION) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $hashedToken = hash('sha256', $token);
    
    $stmt = $pdo->prepare("
        SELECT u.* FROM utilisateurs u
        JOIN auth_tokens t ON u.id = t.user_id
        WHERE t.token = ? 
        AND t.expires_at > NOW() 
        AND u.statut = 'actif'
    ");
    $stmt->execute([$hashedToken]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['nom'] = $user['nom'];
        $_SESSION['prenom'] = $user['prenom'];
        $_SESSION['email'] = $user['email'];
        
        header('Location: index.php');
        exit();
    }
}

// Afficher le message de succès de réinitialisation
if (isset($_GET['reset']) && $_GET['reset'] == 1) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
}

// Afficher le message de succès de vérification
if (isset($_GET['verified']) && $_GET['verified'] == 1) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            Votre compte a été vérifié avec succès. Vous pouvez maintenant vous connecter.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
}

?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - MedSystem</title>

    <!-- icon -->
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fb 0%, #e4e7fb 100%);
        }
        
        .login-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(67, 97, 238, 0.15);
            overflow: hidden;
        }
        
        .login-left {
            background: var(--gradient);
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-logo {
            font-size: 2.5rem;
            margin-bottom: 30px;
        }
        
        .feature-list li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .feature-list i {
            margin-right: 10px;
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .form-control-lg {
            padding: 15px 20px;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s;
        }
        
        .form-control-lg:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .btn-login {
            background: var(--gradient);
            border: none;
            padding: 15px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #6b7280;
            margin: 30px 0;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .divider span {
            padding: 0 15px;
        }
        
        .social-login .btn {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s;
        }
        
        .social-login .btn:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center align-items-center min-vh-100">
                <div class="col-xl-10 col-lg-12">
                    <div class="card login-card">
                        <div class="row g-0">
                            <!-- Left Side - Welcome -->
                            <div class="col-lg-6 d-none d-lg-block">
                                <div class="login-left">
                                    <div class="text-center mb-5">
                                        <div class="login-logo">
                                            <i class="fas fa-hospital"></i>
                                        </div>
                                        <h1 class="fw-bold mb-3">Bienvenue sur MedSystem</h1>
                                        <p class="opacity-75">
                                            Connectez-vous pour accéder à votre espace de travail médical
                                        </p>
                                    </div>
                                    
                                    <ul class="feature-list list-unstyled">
                                        <li>
                                            <i class="fas fa-check-circle"></i>
                                            <span>Gestion complète des patients</span>
                                        </li>
                                        <li>
                                            <i class="fas fa-check-circle"></i>
                                            <span>Consultations et prescriptions</span>
                                        </li>
                                        <li>
                                            <i class="fas fa-check-circle"></i>
                                            <span>Rendez-vous intelligents</span>
                                        </li>
                                        <li>
                                            <i class="fas fa-check-circle"></i>
                                            <span>Rapports détaillés</span>
                                        </li>
                                        <li>
                                            <i class="fas fa-check-circle"></i>
                                            <span>Sécurité maximale</span>
                                        </li>
                                    </ul>
                                    
                                    <div class="mt-5 text-center">
                                        <small class="opacity-75">
                                            <i class="fas fa-shield-alt me-1"></i>
                                            Vos données médicales sont sécurisées
                                        </small>
                                    </div>

                                    <div class="text-center bg-white rounded-pill px-3 py-2 mt-4 d-inline-block">
                                        <a href="index.php" class="back-link">
                                            <i class="fas fa-arrow-left me-2"></i>
                                            Retour à la connexion
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Side - Login Form -->
                            <div class="col-lg-6">
                                <div class="p-5 p-md-5 p-lg-5">
                                    <div class="text-center mb-5">
                                        <h2 class="fw-bold">Connexion</h2>
                                        <p class="text-muted">Accédez à votre compte</p>
                                    </div>
                                    
                                    <?php if ($error): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        <?php echo $error; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($_GET['success'])): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <?php echo htmlspecialchars($_GET['success']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($_GET['reset'])): ?>
                                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Mot de passe réinitialisé avec succès. Veuillez vous connecter.
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" id="loginForm">
                                        <div class="mb-4">
                                            <label class="form-label fw-semibold">Adresse email</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-end-0">
                                                    <i class="fas fa-envelope text-muted"></i>
                                                </span>
                                                <input type="email" 
                                                       class="form-control form-control-lg border-start-0" 
                                                       name="email" 
                                                       placeholder="votre@email.com"
                                                       required
                                                       value="<?php echo $_POST['email'] ?? ''; ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="form-label fw-semibold">Mot de passe</label>
                                                <a href="forgot_password.php" class="text-decoration-none small text-primary">
                                                    Mot de passe oublié ?
                                                </a>
                                            </div>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-end-0">
                                                    <i class="fas fa-lock text-muted"></i>
                                                </span>
                                                <input type="password" 
                                                       class="form-control form-control-lg border-start-0 password-input" 
                                                       name="password" 
                                                       placeholder="Votre mot de passe"
                                                       required>
                                                <button type="button" class="input-group-text bg-light border-start-0 toggle-password">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                                <label class="form-check-label" for="remember">
                                                    Se souvenir de moi
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-login text-white w-100 mb-4">
                                            <i class="fas fa-sign-in-alt me-2"></i>
                                            Se connecter
                                        </button>
                                        
                                        <div class="text-center">
                                            <p class="text-muted mb-0">
                                                Nouveau sur MedSystem ? 
                                                <a href="register.php" class="text-primary fw-semibold text-decoration-none">
                                                    Créer un compte
                                                </a>
                                            </p>
                                        </div>
                                    </form>
                                    
                                    <div class="divider">
                                        <span>Ou continuer avec</span>
                                    </div>
                                    
                                    <div class="social-login row g-2">
                                        <div class="col-6">
                                            <button type="button" class="btn btn-outline-light text-dark">
                                                <i class="fab fa-google text-danger me-2"></i>
                                                Google
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button type="button" class="btn btn-outline-light text-dark">
                                                <i class="fab fa-microsoft text-primary me-2"></i>
                                                Microsoft
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-5 text-center">
                                        <small class="text-muted">
                                            <a href="#" class="text-decoration-none me-3">Conditions d'utilisation</a>
                                            <a href="#" class="text-decoration-none">Politique de confidentialité</a>
                                        </small>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('.password-input');
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = this.querySelector('[name="email"]');
            const password = this.querySelector('[name="password"]');
            
            if (!email.value || !password.value) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs');
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email.value)) {
                e.preventDefault();
                alert('Veuillez entrer une adresse email valide');
                email.focus();
                return false;
            }
        });
        
        // Auto-focus on email field
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.querySelector('[name="email"]');
            if (emailField && !emailField.value) {
                emailField.focus();
            }
        });
    </script>
</body>
</html>