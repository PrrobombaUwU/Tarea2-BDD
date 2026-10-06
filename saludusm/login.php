<?php
session_start();

if (isset($_SESSION['usuario']) && isset($_SESSION['rol'])) {
    if ($_SESSION['rol'] === 'paciente') {
        header("Location: paginapaciente.php");
        exit();
    } elseif ($_SESSION['rol'] === 'medico') {
        header("Location: paginamedico.php");
        exit();
    } elseif ($_SESSION['rol'] === 'administrador') {
        header("Location: paginaadmin.php");
        exit();
    }
}

$mensaje_exito = "";
if (isset($_GET['registro']) && $_GET['registro'] === 'exito') {
    $mensaje_exito = "¡Paciente registrado exitosamente! Ya puedes iniciar sesión con tu RUT y contraseña.";
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'conexion.php';

    $usuario  = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $rol      = trim($_POST['rol'] ?? '');

    if (empty($usuario) || empty($password) || empty($rol)) {
        $error = "Por favor, completa todos los campos.";
    } else {
        try {
            $cuenta = null;

            if ($rol === 'paciente') {
                $stmt = $pdo->prepare("SELECT rut_paciente AS rut, nombre_completo, contraseña FROM paciente WHERE rut_paciente = :usuario LIMIT 1");
                $stmt->execute([':usuario' => $usuario]);
                $cuenta = $stmt->fetch();

            } elseif ($rol === 'medico') {
                $stmt = $pdo->prepare("SELECT rut_medico AS rut, nombre_completo, contraseña FROM medico WHERE rut_medico = :usuario LIMIT 1");
                $stmt->execute([':usuario' => $usuario]);
                $cuenta = $stmt->fetch();

            } elseif ($rol === 'administrador') {
                $stmt = $pdo->prepare("SELECT nombre, email, contraseña FROM administrador WHERE nombre = :nombre OR email = :email LIMIT 1");
                $stmt->execute([
                    ':nombre' => $usuario,
                    ':email'  => $usuario
                    ]);
                    $cuenta = $stmt->fetch();
            } else {
                $error = "Rol seleccionado no válido.";
            }

            
            if ($cuenta) {
                if ($password === $cuenta['contraseña'] || password_verify($password, $cuenta['contraseña'])) {
                    
                    $_SESSION['rol']    = $rol;
                    $_SESSION['nombre'] = $cuenta['nombre_completo'] ?? $cuenta['nombre'];
                    $_SESSION['rut']    = $cuenta['rut'] ?? null;
                    $_SESSION['usuario'] = $usuario;

                    if ($rol === 'paciente') {
                        header("Location: paginapaciente.php");
                    } elseif ($rol === 'medico') {
                        header("Location: paginamedico.php");
                    } elseif ($rol === 'administrador') {
                        header("Location: paginaadmin.php");
                    }
                    exit();
                } else {
                    $error = "Contraseña incorrecta.";
                }
            } else {
                $error = "No se encontró ningún usuario con esos datos para el rol seleccionado.";
            }

        } catch (PDOException $e) {
            $error = "Error al consultar la base de datos: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SaludUSM - Iniciar Sesión</title>
</head>
<body>

    <h2>Iniciar Sesión - SaludUSM</h2>

    <?php if (!empty($mensaje_exito)): ?>
        <p style="color: limegreen; font-weight: bold;"><?php echo htmlspecialchars($mensaje_exito); ?></p>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <div>
            <label for="usuario">Usuario (RUT para Paciente/Médico, Nombre o Email para Administrador):</label><br>
            <input type="text" id="usuario" name="usuario" value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>" required>
        </div>
        <br>

        <div>
            <label for="password">Contraseña:</label><br>
            <input type="password" id="password" name="password" required>
        </div>
        <br>

        <div>
            <label for="rol">Tipo de usuario:</label><br>
            <select id="rol" onChange="mostrarOcultarBoton()" name="rol" required>
                <option value="" disabled selected>Seleccione un rol</option>
                <option value="paciente" <?php echo (isset($_POST['rol']) && $_POST['rol'] === 'paciente') ? 'selected' : ''; ?>>Paciente</option>
                <option value="medico" <?php echo (isset($_POST['rol']) && $_POST['rol'] === 'medico') ? 'selected' : ''; ?>>Médico</option>
                <option value="administrador" <?php echo (isset($_POST['rol']) && $_POST['rol'] === 'administrador') ? 'selected' : ''; ?>>Administrador</option>
            </select>
        </div>
        <br>

        <button type="submit">Ingresar</button>
        
    </form>

    <button style="display:inline" id="nuevo" type="button" onclick="location.href='nuevopaciente.php';">Nuevo paciente</button>
        <script>
            function mostrarOcultarBoton() {
                const selectRol = document.getElementById('rol');
                const botonNuevo = document.getElementById('nuevo');

                if (selectRol.value === 'paciente') {
                    botonNuevo.style.display = 'inline';
                } else {
                    botonNuevo.style.display = 'none';
                }
            }

            window.onload = function() {
              mostrarOcultarBoton();
            };
        </script>

</body>
</html>