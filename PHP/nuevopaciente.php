<?php
$error = "";
$exito = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'conexion.php';

    $rut  = trim($_POST['rut'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $nacimiento = trim($_POST['nacimiento'] ?? '');
    $sexo = trim($_POST['sexo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $comuna = trim($_POST['comuna'] ?? '');
    $prevision = trim($_POST['prevision'] ?? '');
    $contraseña      = trim($_POST['contraseña'] ?? '');
    
        try {
            $telParam = !empty($telefono) ? $telefono : null;

            $stmt = $pdo->prepare("CALL ingresarUsuario(?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $rut,
                $nombre,
                $nacimiento,
                $sexo,
                $telParam,
                $comuna,
                (int)$prevision,
                $contraseña
            ]);

            $exito = "¡Paciente registrado exitosamente! Ya puedes iniciar sesión.";

            header("Location: login.php?registro=exito");
            exit();

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "El RUT ingresado ya se encuentra registrado en el sistema.";
            } else {
                $error = "Error al insertar en la base de datos: " . $e->getMessage();
            }
        }
    }


?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SaludUSM</title>
</head>
<body>

    <h2>Ingreso nuevo paciente - SaludUSM</h2>

    <?php if (!empty($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form action="nuevopaciente.php" method="POST">
        <!-- Campo Usuario -->
        <div>
            <label for="rut">RUT:</label><br>
            <input type="text" id="rut" name="rut" pattern="[0-9]{7,8}-[0-9kK]{1}" maxlength="10" title="Debe ser del formato: XXXXXXXX-X (ej: 12345678-9)" placeholder="12345678-9" value="<?php echo htmlspecialchars($_POST['rut'] ?? ''); ?>" required>
        </div>
        <br>

        <div>
            <label for="nombre">Nombre y apellido:</label><br>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
        </div>
        <br>

        <div>
            <label for="nacimiento">Fecha de nacimiento:</label><br>
            <input type="date" id="nacimiento" name="nacimiento" value="<?php echo htmlspecialchars($_POST['nacimiento'] ?? ''); ?>" required>
        </div>
        <br>

        <div>
            <label for="sexo">Sexo:</label><br>
            <select id="sexo" name="sexo" required>
                <option value="" disabled selected>Seleccione su sexo</option>
                <option value="M" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] === 'M') ? 'selected' : ''; ?>>Masculino</option>
                <option value="F" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] === 'F') ? 'selected' : ''; ?>>Femenino</option>
            </select>
        </div>
        <br>

        <div>
            <label for="telefono">Telefono de contacto:</label><br>
            <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
        </div>
        <br>

        <div>
            <label for="comuna">Comuna de residencia:</label><br>
            <input type="text" id="comuna" name="comuna" value="<?php echo htmlspecialchars($_POST['comuna'] ?? ''); ?>" required>
        </div>
        <br>

        <div>
            <label for="prevision">Prevision de salud:</label><br>
            <select id="prevision" name="prevision" required>
                <option value="" disabled selected>Seleccione su prevision</option>
                <option value="1" <?php echo (isset($_POST['prevision']) && $_POST['prevision'] === '1') ? 'selected' : ''; ?>>Fonasa</option>
                <option value="2" <?php echo (isset($_POST['prevision']) && $_POST['prevision'] === '2') ? 'selected' : ''; ?>>Isapre</option>
                <option value="3" <?php echo (isset($_POST['prevision']) && $_POST['prevision'] === '3') ? 'selected' : ''; ?>>Particular</option>
            </select>
        </div>
        <br>

        <!-- Campo Contraseña -->
        <div>
            <label for="contraseña">Contraseña:</label><br>
            <input type="password" id="contraseña" pattern="[0-9]{4}[a-z]{4}" maxlength="8" title="Debe contener exactamente 4 numeros seguidos de 4 letras minusculas (ej: 1234abcd)" placeholder="1234abcd" name="contraseña" required>
        </div>
        <br>

    
        <br>

        <button type="submit">Crear usuario</button>
        <a href="login.php" style="margin-left: 10px;">Volver al inicio</a>
    </form>

</body>
</html>