<?php
require_once 'config/database.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Traitement de l'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nom' => trim($_POST['nom']),
        'prenom' => trim($_POST['prenom']),
        'email' => trim($_POST['email']),
        'telephone' => trim($_POST['telephone']),
        'password' => $_POST['password'],
        'confirm_password' => $_POST['confirm_password'],
        'role' => 'assistant', // Par défaut
        'specialite' => $_POST['specialite'] ?? null,
        'accept_terms' => isset($_POST['accept_terms'])
    ];
    
    try {
        // Validation
        if (empty($data['nom']) || empty($data['prenom']) || empty($data['email']) || empty($data['password'])) {
            $error = 'Tous les champs obligatoires doivent être remplis';
        } elseif ($data['password'] !== $data['confirm_password']) {
            $error = 'Les mots de passe ne correspondent pas';
        } elseif (strlen($data['password']) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères';
        } elseif (!$data['accept_terms']) {
            $error = 'Vous devez accepter les conditions d\'utilisation';
        } else {
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $stmt->execute([$data['email']]);
            
            if ($stmt->fetch()) {
                $error = 'Cette adresse email est déjà utilisée';
            } else {
                // Hacher le mot de passe
                $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                
                // Générer un code OTP à 6 chiffres
                $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes')); // OTP valide 10 minutes
                
                // Insérer l'utilisateur (statut "inactif" jusqu'à vérification OTP)
                $stmt = $pdo->prepare("
                    INSERT INTO utilisateurs 
                    (nom, prenom, email, password, telephone, role, specialite, statut, verification_code, otp_code, otp_expires_at, date_creation) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'inactif', NULL, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $data['nom'],
                    $data['prenom'],
                    $data['email'],
                    $hashedPassword,
                    $data['telephone'],
                    $data['role'],
                    $data['specialite'],
                    $otpCode,
                    $otpExpires
                ]);
                
                $userId = $pdo->lastInsertId();
                
                // Envoyer l'OTP par email
                // $this->sendOTPEmail($data['email'], $data['prenom'], $otpCode);
                
                // Pour le moment, on va stocker l'OTP en session pour le test
                session_start();
                $_SESSION['verify_email'] = $data['email'];
                $_SESSION['verify_user_id'] = $userId;
                $_SESSION['otp_code'] = $otpCode; // À supprimer en production
                
                // Journaliser l'inscription
                $pdo->prepare("
                    INSERT INTO audit_logs 
                    (user_id, action, table_name, record_id, ip_address) 
                    VALUES (?, 'REGISTER', 'utilisateurs', ?, ?)
                ")->execute([$userId, $userId, $_SERVER['REMOTE_ADDR']]);
                
                // Rediriger vers la page de vérification OTP
                header('Location: verify-otp.php');
                exit();
            }
        }
        
    } catch (Exception $e) {
        $error = 'Une erreur est survenue. Veuillez réessayer.';
    }
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - MedSystem</title>

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
        
        .register-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fb 0%, #e4e7fb 100%);
            padding: 40px 0;
        }
        
        .register-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(67, 97, 238, 0.15);
        }
        
        .register-header {
            background: var(--gradient);
            color: white;
            padding: 40px;
            border-radius: 20px 20px 0 0;
            text-align: center;
        }
        
        .register-logo {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin: 40px 0;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e5e7eb;
            z-index: 1;
        }
        
        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 50px;
        }
        
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e5e7eb;
            color: #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 600;
        }
        
        .step.active .step-circle {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .step-label {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .form-section {
            padding: 40px;
        }
        
        .password-strength {
            height: 5px;
            background: #e5e7eb;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s;
            border-radius: 3px;
        }
        
        .strength-weak { background: #ef4444; width: 25%; }
        .strength-fair { background: #f59e0b; width: 50%; }
        .strength-good { background: #10b981; width: 75%; }
        .strength-strong { background: #10b981; width: 100%; }
        
        .password-requirements {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 10px;
        }
        
        .requirement {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .requirement i {
            margin-right: 8px;
            font-size: 0.75rem;
        }
        
        .requirement.valid i {
            color: #10b981;
        }
        
        .requirement.invalid i {
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-8 col-lg-10">
                    <div class="card register-card">
                        <div class="register-header">
                            <div class="register-logo">
                                <i class="fas fa-hospital"></i>
                            </div>
                            <h1 class="fw-bold mb-3">Créer votre compte</h1>
                            <p class="opacity-75 mb-0">
                                Rejoignez notre communauté médicale en quelques étapes
                            </p>
                        </div>
                        
                        <div class="progress-steps">
                            <div class="step active">
                                <div class="step-circle">1</div>
                                <div class="step-label">Informations</div>
                            </div>
                            <div class="step">
                                <div class="step-circle">2</div>
                                <div class="step-label">Compte</div>
                            </div>
                            <div class="step">
                                <div class="step-circle">3</div>
                                <div class="step-label">Vérification</div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>
                                                
                            <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success text-center">
                                <i class="fas fa-check-circle fa-2x mb-3"></i>
                                <h4 class="alert-heading">Inscription réussie !</h4>
                                <p>
                                    Votre compte a été créé avec succès.<br>
                                    Un email de vérification a été envoyé à votre adresse.
                                </p>
                                <p class="mb-0">
                                    <a href="login.php" class="btn btn-primary mt-3">
                                        <i class="fas fa-sign-in-alt me-1"></i>Se connecter
                                    </a>
                                </p>
                            </div>
                            <?php else: ?>
                            
                            <form method="POST" id="registerForm">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Nom *</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-user text-muted"></i>
                                            </span>
                                            <input type="text" 
                                                   class="form-control" 
                                                   name="nom" 
                                                   placeholder="Votre nom"
                                                   required
                                                   value="<?php echo $_POST['nom'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Prénom *</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-user text-muted"></i>
                                            </span>
                                            <input type="text" 
                                                   class="form-control" 
                                                   name="prenom" 
                                                   placeholder="Votre prénom"
                                                   required
                                                   value="<?php echo $_POST['prenom'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Email *</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-envelope text-muted"></i>
                                            </span>
                                            <input type="email" 
                                                   class="form-control" 
                                                   name="email" 
                                                   placeholder="votre@email.com"
                                                   required
                                                   value="<?php echo $_POST['email'] ?? ''; ?>">
                                        </div>
                                        <small class="text-muted">Vous recevrez un email de vérification</small>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Téléphone</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-phone text-muted"></i>
                                            </span>
                                            <input type="tel" 
                                                   class="form-control" 
                                                   name="telephone" 
                                                   placeholder="+33 1 23 45 67 89"
                                                   value="<?php echo $_POST['telephone'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Rôle</label>
                                        <select class="form-select" name="role">
                                            <option value="assistant" selected>Assistant médical</option>
                                            <option value="secretaire">Secrétaire médicale</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Spécialité (si applicable)</label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="specialite" 
                                               placeholder="Votre spécialité"
                                               value="<?php echo $_POST['specialite'] ?? ''; ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Mot de passe *</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-lock text-muted"></i>
                                            </span>
                                            <input type="password" 
                                                   class="form-control password-input" 
                                                   name="password" 
                                                   id="password"
                                                   placeholder="8 caractères minimum"
                                                   required>
                                            <button type="button" class="input-group-text bg-light toggle-password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="password-strength mt-2">
                                            <div class="strength-bar" id="strengthBar"></div>
                                        </div>
                                        <div class="password-requirements" id="passwordRequirements">
                                            <div class="requirement invalid" data-rule="length">
                                                <i class="fas fa-circle"></i>
                                                <span>8 caractères minimum</span>
                                            </div>
                                            <div class="requirement invalid" data-rule="uppercase">
                                                <i class="fas fa-circle"></i>
                                                <span>1 lettre majuscule</span>
                                            </div>
                                            <div class="requirement invalid" data-rule="lowercase">
                                                <i class="fas fa-circle"></i>
                                                <span>1 lettre minuscule</span>
                                            </div>
                                            <div class="requirement invalid" data-rule="number">
                                                <i class="fas fa-circle"></i>
                                                <span>1 chiffre</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Confirmer le mot de passe *</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-lock text-muted"></i>
                                            </span>
                                            <input type="password" 
                                                   class="form-control" 
                                                   name="confirm_password" 
                                                   placeholder="Répétez le mot de passe"
                                                   required>
                                        </div>
                                        <div class="mt-2" id="passwordMatch"></div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div class="form-check mb-4">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="accept_terms" 
                                                   id="accept_terms"
                                                   required>
                                            <label class="form-check-label" for="accept_terms">
                                                J'accepte les 
                                                <a href="#" class="text-primary text-decoration-none">conditions d'utilisation</a>
                                                et la 
                                                <a href="#" class="text-primary text-decoration-none">politique de confidentialité</a>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary w-100 py-3">
                                            <i class="fas fa-user-plus me-2"></i>
                                            Créer mon compte
                                        </button>
                                    </div>
                                    
                                    <div class="col-12 text-center">
                                        <p class="text-muted mb-0">
                                            Vous avez déjà un compte ? 
                                            <a href="login.php" class="text-primary fw-semibold text-decoration-none">
                                                Se connecter
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </form>
                            
                            <?php endif; ?>
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
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        const requirements = document.querySelectorAll('.requirement');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Check requirements
            const checks = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password)
            };
            
            // Update requirement indicators
            requirements.forEach(req => {
                const rule = req.dataset.rule;
                if (checks[rule]) {
                    req.classList.remove('invalid');
                    req.classList.add('valid');
                    req.querySelector('i').className = 'fas fa-check';
                    strength++;
                } else {
                    req.classList.remove('valid');
                    req.classList.add('invalid');
                    req.querySelector('i').className = 'fas fa-circle';
                }
            });
            
            // Update strength bar
            const strengthClasses = ['strength-weak', 'strength-fair', 'strength-good', 'strength-strong'];
            strengthBar.className = 'strength-bar';
            
            if (strength > 0) {
                strengthBar.classList.add(strengthClasses[strength - 1]);
            }
            
            // Check password match
            checkPasswordMatch();
        });
        
        // Check password confirmation
        function checkPasswordMatch() {
            const password = document.querySelector('[name="password"]').value;
            const confirmPassword = document.querySelector('[name="confirm_password"]').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (!confirmPassword) {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchDiv.innerHTML = '<span class="text-success"><i class="fas fa-check me-1"></i>Les mots de passe correspondent</span>';
            } else {
                matchDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times me-1"></i>Les mots de passe ne correspondent pas</span>';
            }
        }
        
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
        document.getElementById('registerForm')?.addEventListener('submit', function(e) {
            const terms = document.getElementById('accept_terms');
            
            if (!terms.checked) {
                e.preventDefault();
                alert('Vous devez accepter les conditions d\'utilisation');
                terms.focus();
                return false;
            }
            
            // Check password strength
            const password = document.querySelector('[name="password"]').value;
            const confirmPassword = document.querySelector('[name="confirm_password"]').value;
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 8 caractères');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas');
                return false;
            }
            
            return true;
        });
        
        // Auto-focus on first field
        document.addEventListener('DOMContentLoaded', function() {
            const firstField = document.querySelector('[name="nom"]');
            if (firstField) firstField.focus();
        });
    </script>
</body>
</html>