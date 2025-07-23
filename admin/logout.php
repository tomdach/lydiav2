<?php
require_once 'config.php';

// Destruction de la session
session_destroy();

// Suppression du cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirection vers la page de connexion
header('Location: login.php');
exit;
?>
