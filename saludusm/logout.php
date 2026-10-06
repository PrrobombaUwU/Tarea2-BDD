<?php
// PHP/logout.php
session_start();

// Destruir todas las variables de sesión
$_SESSION = [];

// Si se usan cookies de sesión, eliminarlas también
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir la sesión por completo
session_destroy();

// Redirigir de regreso al login
header("Location: login.php");
exit();
?>