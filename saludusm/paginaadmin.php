<?php
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: login.php");
    exit();
}

require_once 'conexion.php';

$nombre = $_SESSION['nombre'];
$datos_admin = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
    $nuevo_nombre    = trim($_POST['nombre'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $password  = trim($_POST['contraseña'] ?? '');
    $antiguo_nombre = $_SESSION['nombre'];

    try {
        if (!empty($password)) {
            $sql_upd = "UPDATE administrador
                        SET nombre = :nuevo_nom, email = :ema, contraseña = :pass
                        WHERE nombre = :antiguo_nom";
            $stmt_upd = $pdo->prepare($sql_upd);
            $stmt_upd->execute([
                ':nuevo_nom'   => $nuevo_nombre,
                ':ema'  => $email,
                ':pass' => $password,
                ':antiguo_nom' => $antiguo_nombre
            ]);
        } else {
            $sql_upd = "UPDATE administrador
                        SET nombre = :nuevo_nom, email = :ema
                        WHERE nombre = :antiguo_nom";
            $stmt_upd = $pdo->prepare($sql_upd);
            $stmt_upd->execute([
                ':nuevo_nom'   => $nuevo_nombre,
                ':ema'  => $email,
                ':antiguo_nom' => $antiguo_nombre
            ]);
        }

        $_SESSION['nombre'] = $nuevo_nombre;
        $nombre = $nuevo_nombre;
        $mensaje_exito = "¡Datos actualizados con éxito!";

    } catch (PDOException $e) {
        $mensaje_error = "Error al actualizar los datos: " . $e->getMessage();
    }
}

try {
    $sql_admin = "SELECT nombre, email
                     FROM administrador
                     WHERE nombre = :nom LIMIT 1";
    $stmt_admin = $pdo->prepare($sql_admin);
    $stmt_admin->execute([':nom' => $nombre]);
    $datos_admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);

    if (!$datos_admin) {
        $error_admin = "No se encontraron los datos del admin.";
    }
} catch (PDOException $e) {
    $error_admin = "Error al cargar datos del admin: " . $e->getMessage();
}

$busqueda = trim($_GET['buscar'] ?? '');
$resultados = [];

if ($busqueda !== '') {
    try {
        $sql = "
            SELECT 
                m.rut_admin,
                m.nombre_completo AS admin,
                GROUP_CONCAT(DISTINCT e.nombre ORDER BY e.nombre SEPARATOR ', ') AS especialidades,
                GROUP_CONCAT(DISTINCT c.nombre ORDER BY c.nombre SEPARATOR ', ') AS centros
            FROM admin m
            LEFT JOIN admin_especialidad me ON m.rut_admin = me.rut_admin
            LEFT JOIN especialidad e ON me.id_especialidad = e.id_especialidad
            LEFT JOIN admin_centro mc ON m.rut_admin = mc.rut_admin
            LEFT JOIN centro_admin c ON mc.codigo_centro = c.codigo_centro
            WHERE m.nombre_completo LIKE :termino_nombre 
               OR e.nombre LIKE :termino_esp
            GROUP BY m.rut_admin, m.nombre_completo
            ORDER BY m.nombre_completo ASC
        ";

        $stmt = $pdo->prepare($sql);
        $param = '%' . $busqueda . '%';
        $stmt->execute([
            ':termino_nombre' => $param,
            ':termino_esp'    => $param
        ]);

        $resultados = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Error al realizar la búsqueda: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SaludUSM - Panel de Administración</title>
</head>
<body>
    <h1>Panel Administrador: <?php echo htmlspecialchars($_SESSION['nombre']); ?></h1>

    <hr>
    <form action="paginaadmin.php" method="GET">
   <label>Buscar admin:</label><input type="text" id="buscar" name="buscar" placeholder="Ingrese nombre o especialidad" value="<?php echo htmlspecialchars($busqueda); ?>" style="width:500px;"><button id="botonbuscar" type="submit">Buscar</button><br>
    <?php if ($busqueda !== ''): ?>
            <a href="paginaadmin.php"><button type="button">Limpiar</button></a>
        <?php endif; ?>
    </form>


    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php elseif ($busqueda !== ''): ?>
        <h3>Resultados de la búsqueda para: "<?php echo htmlspecialchars($busqueda); ?>"</h3>

        <?php if (count($resultados) > 0): ?>
            <table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th>Médico</th>
                        <th>Especialidad(es)</th>
                        <th>Centro(s) donde atiende</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['admin']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['especialidades'] ?? 'Sin especialidad registrada'); ?></td>
                            <td><?php echo htmlspecialchars($row['centros'] ?? 'Sin centros asignados'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No se encontraron médicos ni especialidades que coincidan con la búsqueda.</p>
        <?php endif; ?>

        <br>
    <?php endif; ?>

     <hr>
        <h3>Datos del admin:</h3>
        <?php if (!empty($mensaje_exito)): ?>
            <p style="color: green; font-weight: bold; background-color: #e6ffe6; padding: 8px; border: 1px solid green;"><?php echo htmlspecialchars($mensaje_exito); ?></p>
        <?php endif; ?>

        <?php if (!empty($mensaje_error)): ?>
            <p style="color: red; font-weight: bold; background-color: #ffe6e6; padding: 8px; border: 1px solid red;"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>
        <?php if (isset($error_admin)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error_admin); ?></p>
        <?php endif; ?>
        <form action="paginaadmin.php" method="POST">
            <div>
                <label for="nombre">Nombre:</label><br>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($datos_admin['nombre'] ?? ''); ?>" required>
            </div>

            <div>
                <label for="email">Email:</label><br>
                <input type="text" id="email" name="email" value="<?php echo htmlspecialchars($datos_admin['email'] ?? ''); ?>">
            </div>

            <div>
                <label for="contraseña">Contraseña:</label><br>
                <input type="password" id="contraseña" name="contraseña" pattern="[0-9]{4}[a-z]{4}" maxlength="8" title="Debe contener exactamente 4 numeros seguidos de 4 letras minusculas (ej: 1234abcd)" placeholder="Dejar en blanco para mantener actual">
            </div>

            <button type="submit" name="accion" value="actualizar">Actualizar datos</button>
        </form>

    <a href="logout.php">Cerrar Sesión</a>
</body>
</html>