<?php
// Script temporaire pour générer le hash du mot de passe "admin"
$password = 'admin';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Hash pour le mot de passe 'admin' : " . $hash . "\n";
echo "Vérification : " . (password_verify($password, $hash) ? 'OK' : 'ERREUR') . "\n";
?>
