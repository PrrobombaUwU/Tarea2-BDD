<?php
session_start();


if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'paciente') {
    header("Location: login.php");
    exit();
}

require_once 'conexion.php';

$rut_paciente = $_SESSION['rut'];
$datos_paciente = [];

$mensaje_exito = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
    $nombre    = trim($_POST['nombre'] ?? '');
    $nacimiento = trim($_POST['nacimiento'] ?? '');
    $sexo      = trim($_POST['sexo'] ?? '');
    $telefono  = trim($_POST['telefono'] ?? '');
    $comuna    = trim($_POST['comuna'] ?? '');
    $prevision = trim($_POST['prevision'] ?? '');
    $password  = trim($_POST['contraseña'] ?? '');

    try {
        if (!empty($password)) {
            $sql_upd = "UPDATE paciente 
                        SET nombre_completo = :nom, fecha_nacimiento = :nac, sexo = :sex,
                            telefono_contacto = :tel, comuna_residencia = :com, id_prevision = :prev, contraseña = :pass
                        WHERE rut_paciente = :rut";
            $stmt_upd = $pdo->prepare($sql_upd);
            $stmt_upd->execute([
                ':nom'  => $nombre,
                ':nac'  => $nacimiento,
                ':sex'  => $sexo,
                ':tel'  => $telefono,
                ':com'  => $comuna,
                ':prev' => $prevision,
                ':pass' => $password,
                ':rut'  => $rut_paciente
            ]);
        } else {
            $sql_upd = "UPDATE paciente 
                        SET nombre_completo = :nom, fecha_nacimiento = :nac, sexo = :sex,
                            telefono_contacto = :tel, comuna_residencia = :com, id_prevision = :prev
                        WHERE rut_paciente = :rut";
            $stmt_upd = $pdo->prepare($sql_upd);
            $stmt_upd->execute([
                ':nom'  => $nombre,
                ':nac'  => $nacimiento,
                ':sex'  => $sexo,
                ':tel'  => $telefono,
                ':com'  => $comuna,
                ':prev' => $prevision,
                ':rut'  => $rut_paciente
            ]);
        }

        $_SESSION['nombre'] = $nombre;
        $mensaje_exito = "¡Datos actualizados con éxito!";

    } catch (PDOException $e) {
        $mensaje_error = "Error al actualizar los datos: " . $e->getMessage();
    }
}

try {
    $sql_paciente = "SELECT rut_paciente, nombre_completo, fecha_nacimiento, sexo, telefono_contacto, comuna_residencia, id_prevision 
                     FROM paciente 
                     WHERE rut_paciente = :rut LIMIT 1";
    $stmt_paciente = $pdo->prepare($sql_paciente);
    $stmt_paciente->execute([':rut' => $rut_paciente]);
    $datos_paciente = $stmt_paciente->fetch(PDO::FETCH_ASSOC);

    if (!$datos_paciente) {
        $error_paciente = "No se encontraron los datos del paciente.";
    }
} catch (PDOException $e) {
    $error_paciente = "Error al cargar datos del paciente: " . $e->getMessage();
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

try {
        $sql = "
            SELECT c.fecha_hora, m.nombre_completo, esp.nombre as nombre_especialidad, cm.nombre as nombre_centromedico, e.estado
            FROM cita c
            INNER JOIN medico m ON m.rut_medico = c.rut_medico
            INNER JOIN especialidad esp ON esp.id_especialidad = c.id_especialidad
            INNER JOIN centro_medico cm ON cm.codigo_centro = c.codigo_centro
            INNER JOIN estado_cita e ON e.id_estado = c.id_estado
            WHERE c.rut_paciente = :rut
            AND c.fecha_hora > NOW()
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':rut' => $rut_paciente
        ]);

        $resultados_futuracita = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Error al realizar la búsqueda: " . $e->getMessage();
    }

try {
        $sql = "
            SELECT c.fecha_hora, m.nombre_completo, esp.nombre as nombre_especialidad, cm.nombre as nombre_centromedico, e.estado
            FROM cita c
            INNER JOIN medico m ON m.rut_medico = c.rut_medico
            INNER JOIN especialidad esp ON esp.id_especialidad = c.id_especialidad
            INNER JOIN centro_medico cm ON cm.codigo_centro = c.codigo_centro
            INNER JOIN estado_cita e ON e.id_estado = c.id_estado
            WHERE c.rut_paciente = :rut
            AND c.fecha_hora < NOW()
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':rut' => $rut_paciente
        ]);

        $resultados_citaspasadas = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Error al realizar la búsqueda: " . $e->getMessage();
    }

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SaludUSM - Portal Paciente</title>
</head>
<body>
    <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></h1>
    <p>RUT: <?php echo htmlspecialchars($_SESSION['rut']); ?></p>

    <hr>
    <form action="paginapaciente.php" method="GET">
    <label>Buscar medico:</label><input type="text" id="buscar" name="buscar" placeholder="Ingrese nombre o especialidad" value="<?php echo htmlspecialchars($busqueda); ?>" style="width:500px;"><button id="botonbuscar" type="submit">Buscar</button><br>
    <?php if ($busqueda !== ''): ?>
            <a href="paginapaciente.php"><button type="button">Limpiar</button></a>
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
        <h3>Datos del paciente:</h3>
        <?php if (!empty($mensaje_exito)): ?>
            <p style="color: green; font-weight: bold; background-color: #e6ffe6; padding: 8px; border: 1px solid green;"><?php echo htmlspecialchars($mensaje_exito); ?></p>
        <?php endif; ?>

        <?php if (!empty($mensaje_error)): ?>
            <p style="color: red; font-weight: bold; background-color: #ffe6e6; padding: 8px; border: 1px solid red;"><?php echo htmlspecialchars($mensaje_error); ?></p>
        <?php endif; ?>
        <?php if (isset($error_paciente)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error_paciente); ?></p>
        <?php endif; ?>
        <form action="paginapaciente.php" method="POST">
            <div>
                <label for="rut">RUT:</label><br>
                <input type="text" id="rut" name="rut" readonly value="<?php echo htmlspecialchars($datos_paciente['rut_paciente'] ?? $rut_paciente); ?>">
            </div>

            <div>
                <label for="nombre">Nombre y apellido:</label><br>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($datos_paciente['nombre_completo'] ?? ''); ?>" required>
            </div>

            <div>
                <label for="nacimiento">Fecha de nacimiento:</label><br>
                <input type="date" id="nacimiento" name="nacimiento" value="<?php echo htmlspecialchars($datos_paciente['fecha_nacimiento'] ?? ''); ?>" required>
            </div>

            <div>
                <label for="sexo">Sexo:</label><br>
                <?php $sexo = $datos_paciente['sexo'] ?? ''; ?>
                <select id="sexo" name="sexo" required>
                    <option value="" disabled <?php echo empty($sexo) ? 'selected' : ''; ?>>Seleccione su sexo</option>
                    <option value="M" <?php echo ($sexo === 'M') ? 'selected' : ''; ?>>Masculino</option>
                    <option value="F" <?php echo ($sexo === 'F') ? 'selected' : ''; ?>>Femenino</option>
                </select>
            </div>

            <div>
                <label for="telefono">Telefono de contacto:</label><br>
                <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($datos_paciente['telefono_contacto'] ?? ''); ?>">
            </div>

            <div>
                <label for="comuna">Comuna de residencia:</label><br>
                <input type="text" id="comuna" name="comuna" value="<?php echo htmlspecialchars($datos_paciente['comuna_residencia'] ?? ''); ?>" required>
            </div>

            <div>
                <label for="prevision">Prevision de salud:</label><br>
                <?php $prev = (string)($datos_paciente['id_prevision'] ?? ''); ?>
                <select id="prevision" name="prevision" required>
                    <option value="" disabled <?php echo empty($prev) ? 'selected' : ''; ?>>Seleccione su previsión</option>
                    <option value="1" <?php echo ($prev === '1') ? 'selected' : ''; ?>>Fonasa</option>
                    <option value="2" <?php echo ($prev === '2') ? 'selected' : ''; ?>>Isapre</option>
                    <option value="3" <?php echo ($prev === '3') ? 'selected' : ''; ?>>Particular</option>
                </select>
            </div>

            <div>
                <label for="contraseña">Contraseña:</label><br>
                <input type="password" id="contraseña" name="contraseña" pattern="[0-9]{4}[a-z]{4}" maxlength="8" title="Debe contener exactamente 4 numeros seguidos de 4 letras minusculas (ej: 1234abcd)" placeholder="Dejar en blanco para mantener actual">
            </div>

            <button type="submit" name="accion" value="actualizar">Actualizar datos</button>
        </form>
        <form action="cuentaeliminada.php" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas eliminar tu cuenta?');">
            <input type="hidden" name="rut" value="<?php echo htmlspecialchars($rut_paciente); ?>">
            <button type="submit" style="background-color: #ffcccc; color: #900;">Eliminar cuenta</button>
        </form>

        <br>

    <hr>
    <h3>Futuras citas:</h3>
    <?php if (count($resultados_futuracita) > 0): ?>
            <table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th>Fecha y hora</th>
                        <th>Medico</th>
                        <th>Especialidad</th>
                        <th>Centro medico</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados_futuracita as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['fecha_hora']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['nombre_completo']); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_especialidad']); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_centromedico']); ?></td>
                            <td><?php echo htmlspecialchars($row['estado']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No se encontraron futuras citas</p>
        <?php endif; ?>

        <hr>
        <h3>Citas pasadas:</h3>
    <?php if (count($resultados_citaspasadas) > 0): ?>
            <table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th>Fecha y hora</th>
                        <th>Medico</th>
                        <th>Especialidad</th>
                        <th>Centro medico</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados_citaspasadas as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['fecha_hora']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['nombre_completo']); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_especialidad']); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_centromedico']); ?></td>
                            <td><?php echo htmlspecialchars($row['estado']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No se encontraron citas pasadas</p>
        <?php endif; ?>


    <a href="logout.php">Cerrar Sesión</a>
</body>
</html>