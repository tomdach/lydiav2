<?php
/**
 * Script d'installation automatique du CMS Lydia
 * À exécuter une seule fois après avoir copié les fichiers
 */

// Configuration
$config = [
    'db_host' => 'localhost',
    'db_port' => '8889', // Port MAMP par défaut
    'db_name' => 'lydia_cms',
    'db_user' => 'root',
    'db_pass' => 'root',
    'admin_password' => 'admin'
];

$errors = [];
$success = [];

// Vérification des prérequis
if (version_compare(PHP_VERSION, '7.4.0') < 0) {
    $errors[] = "PHP 7.4 ou supérieur requis. Version actuelle : " . PHP_VERSION;
}

if (!extension_loaded('pdo_mysql')) {
    $errors[] = "Extension PDO MySQL non installée";
}

if (!extension_loaded('json')) {
    $errors[] = "Extension JSON non installée";
}

// Tentative de connexion MySQL
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $success[] = "Connexion MySQL réussie";
} catch (PDOException $e) {
    $errors[] = "Erreur de connexion MySQL : " . $e->getMessage();
}

// Si pas d'erreurs, on continue l'installation
if (empty($errors) && isset($pdo)) {
    try {
        // Création de la base de données
        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$config['db_name']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE {$config['db_name']}");
        $success[] = "Base de données créée/sélectionnée";

        // Lecture et exécution du fichier SQL
        $sqlFile = __DIR__ . '/database.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            
            // Remplacement des valeurs de configuration
            $sql = str_replace('lydia_cms', $config['db_name'], $sql);
            
            // Exécution des requêtes SQL
            $statements = explode(';', $sql);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }
            $success[] = "Structure de base de données installée";
        } else {
            $errors[] = "Fichier database.sql introuvable";
        }

        // Mise à jour du mot de passe admin si différent de 'admin'
        if ($config['admin_password'] !== 'admin') {
            $hashedPassword = password_hash($config['admin_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin_settings SET setting_value = ? WHERE setting_key = 'admin_password'");
            $stmt->execute([$hashedPassword]);
            $success[] = "Mot de passe admin configuré";
        }

        // Création du fichier de configuration si nécessaire
        $configFile = __DIR__ . '/admin/config.php';
        if (file_exists($configFile)) {
            $configContent = file_get_contents($configFile);
            
            // Vérification si la configuration correspond
            if (strpos($configContent, "'{$config['db_host']}'") === false) {
                $success[] = "⚠️ Veuillez vérifier la configuration dans admin/config.php";
            }
        }

        // Vérification du fichier index.php
        $indexFile = __DIR__ . '/index.php';
        $dynamicIndexFile = __DIR__ . '/index_dynamic.php';
        
        if (file_exists($dynamicIndexFile) && !file_exists($indexFile . '.backup')) {
            // Sauvegarde de l'ancien index.php
            if (file_exists($indexFile)) {
                rename($indexFile, $indexFile . '.backup');
                $success[] = "Ancien index.php sauvegardé en index.php.backup";
            }
            
            // Copie du nouveau fichier
            copy($dynamicIndexFile, $indexFile);
            $success[] = "Nouveau index.php dynamique installé";
        }

    } catch (PDOException $e) {
        $errors[] = "Erreur lors de l'installation : " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation CMS Lydia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-lg max-w-2xl w-full">
        <div class="text-center mb-8">
            <div class="mx-auto w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-cogs text-white text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800">Installation CMS Lydia</h1>
            <p class="text-gray-600 mt-2">Configuration automatique de votre système de gestion de contenu</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <h3 class="text-lg font-semibold text-red-800 mb-2">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Erreurs détectées
                </h3>
                <ul class="space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <li class="text-red-700">• <?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <h3 class="text-lg font-semibold text-green-800 mb-2">
                    <i class="fas fa-check-circle mr-2"></i>Installation réussie
                </h3>
                <ul class="space-y-1">
                    <?php foreach ($success as $item): ?>
                        <li class="text-green-700">• <?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="border-t pt-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Informations de configuration</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-3 bg-gray-50 rounded">
                    <strong>Base de données :</strong><br>
                    <?= htmlspecialchars($config['db_name']) ?>
                </div>
                <div class="p-3 bg-gray-50 rounded">
                    <strong>Serveur :</strong><br>
                    <?= htmlspecialchars($config['db_host']) ?>:<?= htmlspecialchars($config['db_port']) ?>
                </div>
                <div class="p-3 bg-gray-50 rounded">
                    <strong>Utilisateur :</strong><br>
                    <?= htmlspecialchars($config['db_user']) ?>
                </div>
                <div class="p-3 bg-gray-50 rounded">
                    <strong>Mot de passe admin :</strong><br>
                    <?= htmlspecialchars($config['admin_password']) ?>
                </div>
            </div>
        </div>

        <?php if (empty($errors)): ?>
            <div class="mt-8 text-center space-y-4">
                <p class="text-green-600 font-semibold">
                    <i class="fas fa-rocket mr-2"></i>Installation terminée avec succès !
                </p>
                
                <div class="flex justify-center space-x-4">
                    <a href="index.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-eye mr-2"></i>Voir le site
                    </a>
                    <a href="admin/" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition duration-200">
                        <i class="fas fa-cog mr-2"></i>Administration
                    </a>
                </div>

                <p class="text-sm text-gray-500 mt-4">
                    <i class="fas fa-info-circle mr-1"></i>
                    Vous pouvez supprimer ce fichier install.php après l'installation
                </p>
            </div>
        <?php else: ?>
            <div class="mt-8 text-center">
                <p class="text-red-600 font-semibold mb-4">
                    <i class="fas fa-exclamation-triangle mr-2"></i>L'installation n'a pas pu être complétée
                </p>
                <p class="text-sm text-gray-600">
                    Veuillez corriger les erreurs ci-dessus et actualiser cette page.
                </p>
            </div>
        <?php endif; ?>

        <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <h4 class="font-semibold text-blue-800 mb-2">Prochaines étapes :</h4>
            <ol class="text-blue-700 text-sm space-y-1">
                <li>1. Connectez-vous à l'administration avec le mot de passe "<?= htmlspecialchars($config['admin_password']) ?>"</li>
                <li>2. Changez le mot de passe dans les paramètres</li>
                <li>3. Personnalisez le contenu de votre site</li>
                <li>4. Supprimez ce fichier install.php</li>
            </ol>
        </div>
    </div>
</body>
</html>
