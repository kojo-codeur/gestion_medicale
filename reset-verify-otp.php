<?php
require_once 'config/database.php';

if (!isset($_SESSION['reset_email'])) {
    header('Location: forgot-password-otp.php');
    exit();
}

$error = '';
$success = '';
$email = $_SESSION['reset_email'];

// Générer OTP si pas déjà fait
if (!isset($_SESSION['reset_otp'])) {
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $_SESSION['reset_otp'] = $otp;
    
    $pdo->prepare("
        UPDATE utilisateurs 
        SET otp_code = ?, otp_expires_at = ? 
        WHERE email = ?
    ")->execute([$otp, $otpExpires, $email]);
}

// Vérification OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userOtp = $_POST['otp'] ?? '';
    
    if ($userOtp === $_SESSION['reset_otp']) {
        // OTP correct - rediriger vers réinitialisation
        header('Location: reset-password.php');
        exit();
    } else {
        $error = 'Code OTP incorrect';
    }
}

// Regénérer OTP
if (isset($_POST['regenerate'])) {
    $newOtp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $_SESSION['reset_otp'] = $newOtp;
    
    $pdo->prepare("
        UPDATE utilisateurs 
        SET otp_code = ?, otp_expires_at = ? 
        WHERE email = ?
    ")->execute([$newOtp, $otpExpires, $email]);
    
    $success = 'Nouveau code OTP généré';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification OTP</title>
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
    </style>
</head>
<body>
    <div class="otp-card">
        <h2 class="text-center mb-4">Vérification OTP</h2>
        
        <div class="alert alert-info">
            <strong>Email :</strong> <?php echo htmlspecialchars($email); ?>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Afficher l'OTP -->
        <div class="otp-display">
            <?php echo $_SESSION['reset_otp']; ?>
        </div>
        
        <p class="text-center text-muted mb-4">
            <small>Copiez ce code dans le champ ci-dessous</small>
        </p>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Entrez le code OTP</label>
                <input type="text" 
                       name="otp" 
                       class="form-control" 
                       style="font-size: 1.5rem; letter-spacing: 10px; text-align: center;"
                       maxlength="6"
                       required
                       autofocus
                       placeholder="000000">
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-check"></i> Vérifier
                </button>
                
                <button type="submit" name="regenerate" class="btn btn-outline-primary">
                    <i class="fas fa-redo"></i> Nouveau code
                </button>
            </div>
        </form>
    </div>

    <script>
        // Auto-remplir pour le test
        document.querySelector('input[name="otp"]').addEventListener('click', function() {
            if (this.value === '') {
                this.value = '<?php echo $_SESSION["reset_otp"]; ?>';
            }
        });
        
        // Limiter aux chiffres
        document.querySelector('input[name="otp"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>