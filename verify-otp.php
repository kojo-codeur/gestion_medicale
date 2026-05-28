<?php
session_start();
require_once 'config/database.php';

// Vérifier si l'utilisateur vient de l'inscription
if (!isset($_SESSION['register_email'])) {
    header('Location: register.php');
    exit();
}

$error = '';
$success = '';
$email = $_SESSION['register_email'];

// Générer et afficher OTP au chargement de la page
if (!isset($_SESSION['otp_displayed'])) {
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Stocker OTP en session
    $_SESSION['register_otp'] = $otp;
    $_SESSION['otp_displayed'] = true;
    
    // Mettre à jour dans la base de données
    $stmt = $pdo->prepare("
        UPDATE utilisateurs 
        SET otp_code = ?, otp_expires_at = ? 
        WHERE email = ? AND statut = 'inactif'
    ");
    $stmt->execute([$otp, $otpExpires, $email]);
}

// Vérification OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userOtp = $_POST['otp'] ?? '';
    
    if (empty($userOtp)) {
        $error = 'Veuillez entrer le code OTP';
    } else {
        // Vérifier OTP depuis la session (en développement)
        if (isset($_SESSION['register_otp']) && $_SESSION['register_otp'] === $userOtp) {
            
            // Activer le compte
            $pdo->beginTransaction();
            
            $pdo->prepare("
                UPDATE utilisateurs 
                SET statut = 'actif', 
                    otp_code = NULL,
                    otp_expires_at = NULL,
                    otp_verified = 1,
                    email_verified_at = NOW()
                WHERE email = ?
            ")->execute([$email]);
            
            // Récupérer l'ID utilisateur
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Journaliser la vérification
                $pdo->prepare("
                    INSERT INTO audit_logs 
                    (user_id, action, table_name, record_id, ip_address) 
                    VALUES (?, 'OTP_VERIFIED', 'utilisateurs', ?, ?)
                ")->execute([$user['id'], $user['id'], $_SERVER['REMOTE_ADDR']]);
            }
            
            $pdo->commit();
            
            // Nettoyer la session
            unset($_SESSION['register_email']);
            unset($_SESSION['register_otp']);
            unset($_SESSION['otp_displayed']);
            
            // Rediriger vers login
            $_SESSION['verification_success'] = true;
            header('Location: login.php?verified=1');
            exit();
            
        } else {
            $error = 'Code OTP incorrect';
        }
    }
}

// Regénérer OTP
if (isset($_POST['regenerate'])) {
    $newOtp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $_SESSION['register_otp'] = $newOtp;
    
    // Mettre à jour dans la base
    $stmt = $pdo->prepare("
        UPDATE utilisateurs 
        SET otp_code = ?, otp_expires_at = ? 
        WHERE email = ?
    ");
    $stmt->execute([$newOtp, $otpExpires, $email]);
    
    $success = 'Nouveau code OTP généré';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification OTP - MedSystem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .otp-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .otp-display {
            background: #f8f9fa;
            border: 2px dashed #4361ee;
            padding: 20px;
            text-align: center;
            font-size: 2.5rem;
            font-weight: bold;
            letter-spacing: 10px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .otp-input {
            letter-spacing: 10px;
            font-size: 1.5rem;
            text-align: center;
            padding: 15px;
        }
        
        .email-info {
            background: #e8f4fd;
            border-left: 4px solid #4361ee;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="otp-card">
        <h2 class="text-center mb-4">Vérification OTP</h2>
        
        <div class="email-info">
            <strong>Email :</strong> <?php echo htmlspecialchars($email); ?>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Afficher l'OTP pour le développement -->
        <div class="otp-display" id="otpDisplay">
            <?php echo $_SESSION['register_otp']; ?>
        </div>
        
        <p class="text-muted text-center mb-4">
            <small>Copiez ce code dans le champ ci-dessous (Mode développement)</small>
        </p>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Entrez le code OTP</label>
                <input type="text" 
                       name="otp" 
                       class="form-control otp-input" 
                       maxlength="6" 
                       pattern="\d{6}"
                       required 
                       autofocus
                       placeholder="000000">
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-check"></i> Vérifier
                </button>
                
                <button type="submit" name="regenerate" class="btn btn-outline-primary">
                    <i class="fas fa-redo"></i> Nouveau code OTP
                </button>
            </div>
        </form>
        
        <div class="text-center mt-4">
            <a href="register.php" class="text-decoration-none">
                <i class="fas fa-arrow-left"></i> Retour à l'inscription
            </a>
        </div>
    </div>

    <script>
        // Auto-focus et formatage
        document.querySelector('input[name="otp"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 6) {
                this.value = this.value.substring(0, 6);
            }
        });
        
        // Auto-remplir pour le test
        document.querySelector('input[name="otp"]').addEventListener('click', function() {
            if (this.value === '') {
                this.value = document.getElementById('otpDisplay').textContent.trim();
            }
        });
    </script>
</body>
</html>