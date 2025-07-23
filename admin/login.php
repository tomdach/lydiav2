<?php
require_once 'config.php';

$error = '';
$success = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = getClientIP();
    
    // Vérification des tentatives de connexion
    if (!checkLoginAttempts($ip)) {
        $error = 'Trop de tentatives de connexion. Veuillez attendre 15 minutes.';
    } else {
        // Vérification CSRF
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Token de sécurité invalide.';
        } else {
            $password = $_POST['password'] ?? '';
            $storedPassword = getSetting('admin_password');
            
            if (password_verify($password, $storedPassword)) {
                // Connexion réussie
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_login_time'] = time();
                
                logLoginAttempt($ip, true);
                
                // Redirection vers le dashboard
                header('Location: index.php');
                exit;
            } else {
                logLoginAttempt($ip, false);
                $error = 'Mot de passe incorrect.';
            }
        }
    }
}

// Redirection si déjà connecté
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Connexion</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-2xl shadow-2xl w-full max-w-md">
        <div class="text-center mb-8">
            <div class="mx-auto w-20 h-20 bg-gradient-to-r from-purple-600 to-blue-600 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-lock text-white text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Administration</h1>
            <p class="text-gray-600">Connectez-vous pour accéder au dashboard</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                    <span class="text-red-700"><?= sanitize($error) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                    <span class="text-green-700"><?= sanitize($success) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-key mr-2"></i>Mot de passe
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                    placeholder="Entrez votre mot de passe"
                    autocomplete="current-password"
                >
            </div>

            <button 
                type="submit" 
                class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-4 rounded-lg font-semibold hover:from-blue-700 hover:to-purple-700 transition duration-200 transform hover:scale-105"
            >
                <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
            </button>
        </form>

        <div class="mt-8 text-center">
            <p class="text-sm text-gray-500">
                <i class="fas fa-info-circle mr-1"></i>
                Mot de passe par défaut : <code class="bg-gray-100 px-2 py-1 rounded">admin</code>
            </p>
        </div>

        <div class="mt-6 text-center">
            <a href="../index.php" class="text-sm text-blue-600 hover:text-blue-800 transition duration-200">
                <i class="fas fa-arrow-left mr-1"></i>Retour au site
            </a>
        </div>
    </div>

    <script>
        // Focus automatique sur le champ mot de passe
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('password').focus();
        });

        // Effet de parallax subtil sur le background
        document.addEventListener('mousemove', function(e) {
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            document.body.style.background = `linear-gradient(${135 + x * 10}deg, #667eea 0%, #764ba2 100%)`;
        });
    </script>
</body>
</html>
