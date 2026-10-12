<?php
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'medico') {
    header("Location: login.php");
    exit();
}

require_once 'conexion.php';

try {
    $sql_vencidas = "UPDATE cita 
                     SET id_estado = 5 
                     WHERE fecha_hora < NOW() 
                       AND id_estado IN (1, 2)";
    $pdo->query($sql_vencidas);
} catch (PDOException $e) {
}

$rut_medico = $_SESSION['rut'];

$f_fecha_desde  = trim($_GET['f_fecha_desde'] ?? '');
$f_fecha_hasta  = trim($_GET['f_fecha_hasta'] ?? '');
$f_centro       = trim($_GET['f_centro'] ?? '');
$f_region       = trim($_GET['f_region'] ?? '');
$f_especialidad = trim($_GET['f_especialidad'] ?? '');
$f_estado       = trim($_GET['f_estado'] ?? '');
$f_prevision    = trim($_GET['f_prevision'] ?? '');

$citas_avanzadas = [];
$error_filtro_citas = '';


$filtro_opts_centros        = [];
$filtro_opts_regiones       = [];
$filtro_opts_especialidades = [];
$filtro_opts_estados        = [];
$filtro_opts_previsiones    = [];

try {
    $filtro_opts_centros        = $pdo->query("SELECT codigo_centro, nombre FROM centro_medico ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    $filtro_opts_regiones       = $pdo->query("SELECT DISTINCT region FROM centro_medico WHERE region IS NOT NULL AND region != '' ORDER BY region ASC")->fetchAll(PDO::FETCH_COLUMN);
    $filtro_opts_especialidades = $pdo->query("SELECT id_especialidad, nombre FROM especialidad ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    $filtro_opts_estados        = $pdo->query("SELECT Id_Estado, estado FROM estado_cita ORDER BY Id_Estado ASC")->fetchAll(PDO::FETCH_ASSOC);
    $filtro_opts_previsiones    = $pdo->query("SELECT Id_prevision, nombre FROM prevision ORDER BY Id_prevision ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_filtro_citas = "Error al cargar listas: " . $e->getMessage();
}

$sql_avanzada = "SELECT fecha_hora, paciente, medico, especialidad, centro_medico, estado 
                 FROM vista_citas_resumen 
                 WHERE rut_medico = :rut_medico";

$params_avanzada = [':rut_medico' => $rut_medico];

if ($f_fecha_desde !== '') {
    $sql_avanzada .= " AND fecha_hora >= :f_desde";
    $params_avanzada[':f_desde'] = $f_fecha_desde . " 00:00:00";
}
if ($f_fecha_hasta !== '') {
    $sql_avanzada .= " AND fecha_hora <= :f_hasta";
    $params_avanzada[':f_hasta'] = $f_fecha_hasta . " 23:59:59";
}
if ($f_centro !== '') {
    $sql_avanzada .= " AND codigo_centro = :f_centro";
    $params_avanzada[':f_centro'] = $f_centro;
}
if ($f_region !== '') {
    $sql_avanzada .= " AND region = :f_region";
    $params_avanzada[':f_region'] = $f_region;
}
if ($f_especialidad !== '') {
    $sql_avanzada .= " AND id_especialidad = :f_especialidad";
    $params_avanzada[':f_especialidad'] = $f_especialidad;
}
if ($f_estado !== '') {
    $sql_avanzada .= " AND id_estado = :f_estado";
    $params_avanzada[':f_estado'] = $f_estado;
}
if ($f_prevision !== '') {
    $sql_avanzada .= " AND id_prevision = :f_prevision";
    $params_avanzada[':f_prevision'] = $f_prevision;
}

$sql_avanzada .= " ORDER BY fecha_hora DESC";

try {
    $stmt_avanzada = $pdo->prepare($sql_avanzada);
    $stmt_avanzada->execute($params_avanzada);
    $citas_avanzadas = $stmt_avanzada->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_filtro_citas = "Error al ejecutar la búsqueda: " . $e->getMessage();
}


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
        $sql = "SELECT 
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
        $sql = "SELECT TIME_FORMAT(c.fecha_hora, '%H:%i') AS hora, p.nombre_completo, c.rut_paciente, pre.nombre as nombre_prevision, cm.nombre as centro_medico, e.estado as estado_cita, c.id_cita
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
                            <td><a href="registroatencion.php?id_cita=<?= urlencode($row['id_cita']) ?>">Registrar atencion</a>
                                <a href="historialclinico.php?rut_paciente=<?= urlencode($row['rut_paciente']) ?>">Historial</a>
                            </td>
                            
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No se encontraron citas para la fecha seleccionada</p>
        <?php endif; ?>

        <hr>
<h3>Búsqueda avanzada de mis citas (Filtros combinables)</h3>

<?php if (!empty($error_filtro_citas)): ?>
    <p style="color: red;"><?php echo htmlspecialchars($error_filtro_citas); ?></p>
<?php endif; ?>

<form action="paginamedico.php" method="GET" style="background-color: #f8f9fa; padding: 15px; border: 1px solid #ddd; margin-bottom: 20px;">
    <table cellpadding="4" cellspacing="6">
        <tr>
            <td><label for="f_fecha_desde">Fecha Desde:</label></td>
            <td><input type="date" id="f_fecha_desde" name="f_fecha_desde" value="<?php echo htmlspecialchars($f_fecha_desde); ?>"></td>
            
            <td><label for="f_fecha_hasta">Fecha Hasta:</label></td>
            <td><input type="date" id="f_fecha_hasta" name="f_fecha_hasta" value="<?php echo htmlspecialchars($f_fecha_hasta); ?>"></td>
        </tr>
        <tr>
            <td><label for="f_centro">Centro médico:</label></td>
            <td>
                <select id="f_centro" name="f_centro">
                    <option value="">-- Todos mis centros --</option>
                    <?php foreach ($filtro_opts_centros as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['codigo_centro']); ?>" <?php echo ($f_centro === $c['codigo_centro']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>

            <td><label for="f_region">Región:</label></td>
            <td>
                <select id="f_region" name="f_region">
                    <option value="">-- Todas las regiones --</option>
                    <?php foreach ($filtro_opts_regiones as $r): ?>
                        <option value="<?php echo htmlspecialchars($r); ?>" <?php echo ($f_region === $r) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><label for="f_especialidad">Especialidad:</label></td>
            <td>
                <select id="f_especialidad" name="f_especialidad">
                    <option value="">-- Todas las especialidades --</option>
                    <?php foreach ($filtro_opts_especialidades as $esp): ?>
                        <option value="<?php echo $esp['id_especialidad']; ?>" <?php echo ($f_especialidad == $esp['id_especialidad']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($esp['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>

            <td><label for="f_estado">Estado:</label></td>
            <td>
                <select id="f_estado" name="f_estado">
                    <option value="">-- Todos los estados --</option>
                    <?php foreach ($filtro_opts_estados as $est): ?>
                        <option value="<?php echo $est['Id_Estado']; ?>" <?php echo ($f_estado == $est['Id_Estado']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($est['estado']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><label for="f_prevision">Previsión Paciente:</label></td>
            <td colspan="3">
                <select id="f_prevision" name="f_prevision">
                    <option value="">-- Todas las previsiones --</option>
                    <?php foreach ($filtro_opts_previsiones as $prev): ?>
                        <option value="<?php echo $prev['Id_prevision']; ?>" <?php echo ($f_prevision == $prev['Id_prevision']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($prev['nombre'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>
    <br>
    <button type="submit">Filtrar citas</button>
    <a href="paginamedico.php"><button type="button">Limpiar</button></a>
</form>

<?php if (count($citas_avanzadas) > 0): ?>
    <table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th>Fecha y hora</th>
                <th>Paciente</th>
                <th>Médico</th>
                <th>Especialidad</th>
                <th>Centro</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($citas_avanzadas as $fila): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($fila['fecha_hora']); ?></strong></td>
                    <td><?php echo htmlspecialchars($fila['paciente']); ?></td>
                    <td><?php echo htmlspecialchars($fila['medico']); ?></td>
                    <td><?php echo htmlspecialchars($fila['especialidad']); ?></td>
                    <td><?php echo htmlspecialchars($fila['centro_medico']); ?></td>
                    <td><?php echo htmlspecialchars($fila['estado'] ?? 'No especificado'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No se encontraron citas con los criterios seleccionados.</p>
<?php endif; ?>

    <a href="logout.php">Cerrar Sesión</a>
</body>
</html>