<?php
session_start();

require_once 'conexion.php';
$rut_paciente = $_SESSION['rut'];

$sql_del = "DELETE FROM paciente WHERE rut_paciente = :rut";
$stmt_del = $pdo->prepare($sql_del);
$stmt_del->execute([':rut'  => $rut_paciente]);

$_SESSION['usuario'] = '';
$_SESSION['rol'] = '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SaludUSM</title>
</head>
<body>

    <h2>Su cuenta ha sido eliminada</h2>

    <a href="login.php">
        Volver al inicio 

    </a>


    </body>
</html>