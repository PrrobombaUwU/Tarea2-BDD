<?php
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'medico') {
    header("Location: login.php");
    exit();
}

require_once 'conexion.php';

$rut_medico = $_SESSION['rut'];
$datos_medico = [];

$mensaje_exito = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
    $nombre    = trim($_POST['nombre'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $password  = trim($_POST['contraseña'] ?? '');

    try {
        if (!empty($password)) {
            $sql_upd = "UPDATE medico
                        SET nombre_completo = :nom, email_institucional = :ema, contraseña = :pass
                        WHERE rut_medico = :rut";
            $stmt_upd = $pdo->prepare($sql_upd);
            $stmt_upd->execute([
                ':nom'  => $nombre,
                ':ema'  => $email,
                ':pass' => $password,
                ':rut'  => $rut_medico
            ]);
        } else {
            $sql_upd = "UPDATE medico
                        SET nombre_completo = :nom, email_institucional = :ema
                        WHERE rut_medico = :rut";
            $stmt_upd = $pdo->prepare($sql_upd);
            $stmt_upd->execute([
                ':nom'  => $nombre,
                ':ema'  => $email,
                ':rut'  => $rut_medico
            ]);
        }

        $_SESSION['nombre'] = $nombre;
        $mensaje_exito = "¡Datos actualizados con éxito!";

    } catch (PDOException $e) {
        $mensaje_error = "Error al actualizar los datos: " . $e->getMessage();
    }
}

try {
    $sql_medico = "SELECT rut_medico, nombre_completo, email_institucional 
                     FROM medico
                     WHERE rut_medico = :rut LIMIT 1";
    $stmt_medico = $pdo->prepare($sql_medico);
    $stmt_medico->execute([':rut' => $rut_medico]);
    $datos_medico = $stmt_medico->fetch(PDO::FETCH_ASSOC);

    if (!$datos_medico) {
        $error_medico = "No se encontraron los datos del medico.";
    }
} catch (PDOException $e) {
    $error_medico = "Error al cargar datos del medico: " . $e->getMessage();
}

$busqueda = trim($_GET['buscar'] ?? '');
$resultados = [];

if ($busqueda !== '') {
    try {
        $sql = "
            SELECT 
                m.rut_medico,
                m.nombre_completo AS medico,
                GROUP_CONCAT(DISTINCT e.nombre ORDER BY e.nombre SEPARATOR ', ') AS especialidades,
                GROUP_CONCAT(DISTINCT c.nombre ORDER BY c.nombre SEPARATOR ', ') AS centros
            FROM medico m
            LEFT JOIN medico_especialidad me ON m.rut_medico = me.rut_medico
            LEFT JOIN especialidad e ON me.id_especialidad = e.id_especialidad
            LEFT JOIN medico_centro mc ON m.rut_medico = mc.rut_medico
            LEFT JOIN centro_medico c ON mc.codigo_centro = c.codigo_centro
            WHERE m.nombre_completo LIKE :termino_nombre 
               OR e.nombre LIKE :termino_esp
            GROUP BY m.rut_medico, m.nombre_completo
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

$fecha_seleccionada = $_GET['fechacita'] ?? date('Y-m-d');
$resultados_citas = [];
$error_citas = null;

try {
        $sql = "
            SELECT TIME_FORMAT(c.fecha_hora, '%H:%i') AS hora, p.nombre_completo, pre.nombre as nombre_prevision, cm.nombre as centro_medico, e.estado as estado_cita, c.id_cita
            FROM cita c
            INNER JOIN paciente p ON p.rut_paciente = c.rut_paciente
            INNER JOIN prevision pre ON pre.id_prevision = p.id_prevision
            INNER JOIN centro_medico cm ON cm.codigo_centro = c.codigo_centro
            INNER JOIN estado_cita e ON e.id_estado = c.id_estado
            WHERE c.rut_medico = :rut
            AND DATE(c.fecha_hora) = :fecha
            ORDER BY c.fecha_hora ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':rut' => $rut_medico,
            ':fecha' => $fecha_seleccionada
        ]);

        $resultados_citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error al realizar la búsqueda: " . $e->getMessage();
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SaludUSM - Portal Médico</title>
</head>
<body>
    <h1>Bienvenido, Dr(a). <?php echo htmlspecialchars($_SESSION['nombre']); ?></h1>
    <p>RUT: <?php echo htmlspecialchars($_SESSION['rut']); ?></p>

    <hr>
    <form action="paginamedico.php" method="GET">
    <label>Buscar medico:</label><input type="text" id="buscar" name="buscar" placeholder="Ingrese nombre o especialidad" value="<?php echo htmlspecialchars($busqueda); ?>" style="width:500px;"><button id="botonbuscar" type="submit">Buscar</button><br>
    <?php if ($busqueda !== ''): ?>
            <a href="paginamedico.php"><button type="button">Limpiar</button></a>
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
                            <td><strong><?php echo htmlspecialchars($row['medico']); ?></strong></td>
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
        <h3>Datos del medico:</h3>
        <?php if (!empty($mensaje_exito)): ?>
            <p style="color: green; font-weight: bold; background-color: #e6ffe6; padding: 8px; border: 1px solid green;"><?php echo htmlspecialchars($mensaje_exito); ?></p>
        <?php endif; ?>

        <?php if (!empty($mensaje_error)): ?>
            <p style="color: red; font-weight: bold; background-color: #ffe6e6; padding: 8px; border: 1px solid red;"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>
        <?php if (isset($error_medico)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error_medico); ?></p>
        <?php endif; ?>
        <form action="paginamedico.php" method="POST">
            <div>
                <label for="rut">RUT:</label><br>
                <input type="text" id="rut" name="rut" readonly value="<?php echo htmlspecialchars($datos_medico['rut_medico'] ?? $rut_medico); ?>">
            </div>

            <div>
                <label for="nombre">Nombre y apellido:</label><br>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($datos_medico['nombre_completo'] ?? ''); ?>" required>
            </div>

            <div>
                <label for="email">Email institucional:</label><br>
                <input type="text" id="email" name="email" value="<?php echo htmlspecialchars($datos_medico['email_institucional'] ?? ''); ?>">
            </div>

            <div>
                <label for="contraseña">Contraseña:</label><br>
                <input type="password" id="contraseña" name="contraseña" pattern="[0-9]{4}[a-z]{4}" maxlength="8" title="Debe contener exactamente 4 numeros seguidos de 4 letras minusculas (ej: 1234abcd)" placeholder="Dejar en blanco para mantener actual">
            </div>

            <button type="submit" name="accion" value="actualizar">Actualizar datos</button>
        </form>


        <hr>
        <h3>Citas:</h3>
        <form action="paginamedico.php" method="GET">
            <label for="fechacita">Seleccione fecha:</label>
            <input type="date" id="fechacita" name="fechacita" value="<?php echo htmlspecialchars($fecha_seleccionada); ?>" required>
            <button type="submit">Buscar cita</button>
            <?php if ($fecha_seleccionada !== date('Y-m-d')): ?>
                <a href="paginamedico.php"><button type="button">Hoy</button></a>
            <?php endif; ?>
        </form>
        <br>
        <?php if (isset($error_citas)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error_citas); ?></p>
        <?php elseif (count($resultados_citas) > 0): ?>
            <table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th>Hora</th>
                        <th>Paciente</th>
                        <th>Prevision</th>
                        <th>Centro medico</th>
                        <th>Estado</th>
                        <th>Registrar atencion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados_citas as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['hora']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['nombre_completo']); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_prevision']); ?></td>
                            <td><?php echo htmlspecialchars($row['centro_medico']); ?></td>
                            <td><?php echo htmlspecialchars($row['estado_cita']); ?></td>
                            <td><a href="registroatencion.php?id_cita=<?= urlencode($row['id_cita']) ?>">Registrar atencion</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No se encontraron citas para la fecha seleccionada</p>
        <?php endif; ?>

    <a href="logout.php">Cerrar Sesión</a>
</body>
</html>