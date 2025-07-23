<?php
/**
 * Script pour réinitialiser le mot de passe admin
 * Exécutez ce script pour remettre le mot de passe à "admin"
 */

// Configuration (adaptez selon votre configuration MAMP)
$config = [
    'db_host' => 'localhost',
    'db_port' => '8889',
    'db_name' => 'lydia_cms',
    'db_user' => 'root',
    'db_pass' => 'root'
];

try {
    // Connexion à la base de données
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Génération du nouveau hash
    $newPassword = 'admin';
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Mise à jour en base
    $stmt = $pdo->prepare("UPDATE admin_settings SET setting_value = ? WHERE setting_key = 'admin_password'");
    $success = $stmt->execute([$hashedPassword]);

    if ($success) {
        echo "✅ Mot de passe mis à jour avec succès !<br>";
        echo "🔑 Mot de passe : <strong>admin</strong><br>";
        echo "🔗 URL admin : <a href='admin/'>admin/</a><br><br>";
        echo "⚠️ Supprimez ce fichier après utilisation pour des raisons de sécurité.";
    } else {
        echo "❌ Erreur lors de la mise à jour du mot de passe.";
    }

} catch (PDOException $e) {
    echo "❌ Erreur de connexion : " . $e->getMessage();
    echo "<br><br>Vérifiez que :";
    echo "<ul>";
    echo "<li>MAMP est démarré</li>";
    echo "<li>La base de données 'lydia_cms' existe</li>";
    echo "<li>Les paramètres de connexion sont corrects</li>";
    echo "</ul>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation mot de passe</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>🔐 Réinitialisation du mot de passe administrateur</h1>
</body>
</html>
