<?php
require_once 'config/database.php';

if (!isset($_SESSION['reset_email'])) {
    header('Location: forgot-password-otp.php');
    exit();
}

$error = '';
$success = '';
$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    
    if ($password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas';
    } elseif (strlen($password) < 8) {
        $error = 'Le mot de passe doit avoir au moins 8 caractères';
    } else {
        // Hacher et mettre à jour le mot de passe
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $pdo->prepare("
            UPDATE utilisateurs 
            SET password = ?, otp_code = NULL, otp_expires_at = NULL 
            WHERE email = ?
        ")->execute([$hashedPassword, $email]);
        
        // Journaliser
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action, table_name, record_id, ip_address) 
                VALUES (?, 'PASSWORD_RESET', 'utilisateurs', ?, ?)
            ")->execute([$user['id'], $user['id'], $_SERVER['REMOTE_ADDR']]);
        }
        
        // Nettoyer session
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_otp']);
        
        // Rediriger avec succès
        $_SESSION['reset_success'] = true;
        header('Location: login.php?reset=1');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fb 0%, #e4e7fb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(67, 97, 238, 0.15);
            max-width: 500px;
            width: 100%;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0 !important;
            padding: 30px;
            text-align: center;
        }
        
        .password-strength {
            height: 5px;
            background: #e5e7eb;
            border-radius: 3px;
            margin-top: 5px;
        }
        
        .strength-bar {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-lock"></i> Nouveau mot de passe</h3>
            <p class="mb-0">Pour : <?php echo htmlspecialchars($email); ?></p>
        </div>
        
        <div class="card-body p-4">
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" id="resetForm">
                <div class="mb-3">
                    <label class="form-label">Nouveau mot de passe</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                        <input type="password" 
                               name="password" 
                               id="password"
                               class="form-control" 
                               required 
                               minlength="8"
                               placeholder="Minimum 8 caractères">
                        <button type="button" class="input-group-text toggle-password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength mt-2">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <small class="text-muted">Force : <span id="strengthText">Faible</span></small>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Confirmer le mot de passe</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                        <input type="password" 
                               name="confirm_password" 
                               id="confirmPassword"
                               class="form-control" 
                               required 
                               placeholder="Retapez votre mot de passe">
                    </div>
                    <div class="mt-2" id="passwordMatch"></div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 btn-lg">
                    <i class="fas fa-save"></i> Enregistrer le nouveau mot de passe
                </button>
            </form>
        </div>
    </div>

    <script>
        // Afficher/masquer mot de passe
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
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
        
        // Vérifier force du mot de passe
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            const colors = ['#ef4444', '#f59e0b', '#10b981', '#10b981'];
            const texts = ['Très faible', 'Faible', 'Bon', 'Fort'];
            
            strengthBar.style.width = (strength * 25) + '%';
            strengthBar.style.backgroundColor = colors[strength] || colors[0];
            strengthText.textContent = texts[strength] || texts[0];
            strengthText.style.color = colors[strength] || colors[0];
            
            checkPasswordMatch();
        });
        
        // Vérifier correspondance
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirmPassword').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (!confirm) {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirm) {
                matchDiv.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> Les mots de passe correspondent</span>';
            } else {
                matchDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times"></i> Les mots de passe ne correspondent pas</span>';
            }
        }
        
        document.getElementById('confirmPassword').addEventListener('input', checkPasswordMatch);
        
        // Validation formulaire
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirmPassword').value;
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Le mot de passe doit avoir au moins 8 caractères');
                return false;
            }
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas');
                return false;
            }
        });
    </script>
</body>
</html>