<?php
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    try {
        $stmt = $pdo->prepare("SELECT id, prenom FROM utilisateurs WHERE email = ? AND statut = 'actif'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Générer OTP
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Stocker en session
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_otp'] = $otp;
            
            // Mettre à jour dans la base
            $pdo->prepare("
                UPDATE utilisateurs 
                SET otp_code = ?, otp_expires_at = ? 
                WHERE email = ?
            ")->execute([$otp, $otpExpires, $email]);
            
            // Rediriger vers vérification
            header('Location: reset-verify-otp.php');
            exit();
            
        } else {
            $success = 'Si votre email existe, vous recevrez un code OTP';
        }
        
    } catch (Exception $e) {
        $error = 'Une erreur est survenue';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié</title>
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
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-key"></i> Mot de passe oublié</h3>
            <p class="mb-0">Recevez un code OTP pour réinitialiser</p>
        </div>
        
        <div class="card-body p-4">
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Votre email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" 
                               name="email" 
                               class="form-control" 
                               required 
                               placeholder="votre@email.com">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 btn-lg">
                    <i class="fas fa-paper-plane"></i> Recevoir le code OTP
                </button>
            </form>
            
            <div class="text-center mt-3">
                <a href="login.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left"></i> Retour à la connexion
                </a>
            </div>
        </div>
    </div>
</body>
</html>