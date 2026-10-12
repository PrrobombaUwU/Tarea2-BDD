<?php
// PHP/index.php
session_start();

// Si no hay sesión iniciada, enviar al login
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SaludUSM - Inicio</title>
</head>
<body>

    <h1>Bienvenido a SaludUSM</h1>
    <p><strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['nombre'] ?? $_SESSION['usuario']); ?></p>
    <p><strong>Rol:</strong> <?php echo htmlspecialchars($_SESSION['rol']); ?></p>

    <hr>

    <!-- Botón para cerrar sesión y volver al login -->
    <a href="logout.php">Cerrar Sesión</a>

</body>
</html>