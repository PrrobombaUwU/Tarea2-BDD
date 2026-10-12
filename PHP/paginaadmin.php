<?php
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'administrador') {
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

$estadisticas_centros = [];
$top_diagnosticos = [];

try {
    $sql_centros = "SELECT 
            cm.codigo_centro,
            cm.nombre AS centro_nombre,
            cm.comuna,
            cm.region,
            COUNT(c.id_cita) AS total_citas,
            SUM(CASE WHEN c.id_estado = 4 THEN 1 ELSE 0 END) AS total_inasistencias,
            porcentajeInasistencia(
                COUNT(c.id_cita),
                SUM(CASE WHEN c.id_estado = 4 THEN 1 ELSE 0 END)
        ) AS porcentaje_inasistencia
        FROM centro_medico cm
        LEFT JOIN cita c ON cm.codigo_centro = c.codigo_centro
        GROUP BY cm.codigo_centro, cm.nombre, cm.comuna, cm.region
        ORDER BY cm.nombre ASC
    ";
    $stmt_centros = $pdo->query($sql_centros);
    $estadisticas_centros = $stmt_centros->fetchAll(PDO::FETCH_ASSOC);

    $sql_diag = "SELECT 
            d.codigo_d,
            d.descripcion,
            COUNT(ad.codigo_d) AS total_veces
        FROM diagnostico d
        INNER JOIN atencion_diagnostico ad ON d.codigo_d = ad.codigo_d
        GROUP BY d.codigo_d, d.descripcion
        ORDER BY total_veces DESC, d.descripcion ASC
        LIMIT 5
    ";
    $stmt_diag = $pdo->query($sql_diag);
    $top_diagnosticos = $stmt_diag->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_panel = "Error al cargar las estadísticas del panel: " . $e->getMessage();
}


$f_fecha_desde  = trim($_GET['f_fecha_desde'] ?? '');
$f_fecha_hasta  = trim($_GET['f_fecha_hasta'] ?? '');
$f_centro       = trim($_GET['f_centro'] ?? '');
$f_region       = trim($_GET['f_region'] ?? '');
$f_especialidad = trim($_GET['f_especialidad'] ?? '');
$f_medico       = trim($_GET['f_medico'] ?? '');
$f_estado       = trim($_GET['f_estado'] ?? '');
$f_prevision    = trim($_GET['f_prevision'] ?? '');

$citas_avanzadas = [];
$error_filtro_citas = '';

$filtro_opts_centros        = [];
$filtro_opts_regiones       = [];
$filtro_opts_especialidades = [];
$filtro_opts_medicos        = [];
$filtro_opts_estados        = [];
$filtro_opts_previsiones    = [];

try {
    $filtro_opts_centros        = $pdo->query("SELECT codigo_centro, nombre FROM centro_medico ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    $filtro_opts_regiones       = $pdo->query("SELECT DISTINCT region FROM centro_medico WHERE region IS NOT NULL AND region != '' ORDER BY region ASC")->fetchAll(PDO::FETCH_COLUMN);
    $filtro_opts_especialidades = $pdo->query("SELECT id_especialidad, nombre FROM especialidad ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    $filtro_opts_medicos        = $pdo->query("SELECT rut_medico, nombre_completo FROM medico ORDER BY nombre_completo ASC")->fetchAll(PDO::FETCH_ASSOC);
    $filtro_opts_estados        = $pdo->query("SELECT Id_Estado, estado FROM estado_cita ORDER BY Id_Estado ASC")->fetchAll(PDO::FETCH_ASSOC);
    $filtro_opts_previsiones    = $pdo->query("SELECT Id_prevision, nombre FROM prevision ORDER BY Id_prevision ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_filtro_citas = "Error al cargar opciones de filtros: " . $e->getMessage();
}

$sql_avanzada = "SELECT fecha_hora, paciente, medico, especialidad, centro_medico, estado 
                 FROM vista_citas_resumen 
                 WHERE 1=1";

$params_avanzada = [];

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
if ($f_medico !== '') {
    $sql_avanzada .= " AND rut_medico = :f_medico";
    $params_avanzada[':f_medico'] = $f_medico;
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
    $error_filtro_citas = "Error al ejecutar la búsqueda de citas: " . $e->getMessage();
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

    <hr>
    <h2>Panel de Gestión</h2>

    <?php if (isset($error_panel)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error_panel); ?></p>
    <?php else: ?>

        <!-- Sección 1: Estadísticas por Centro Médico -->
        <h3>Rendimiento y Citas por Centro Médico</h3>
        <table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th>Código</th>
                    <th>Centro Médico</th>
                    <th>Ubicación</th>
                    <th>Total Citas</th>
                    <th>Inasistencias (No asistió)</th>
                    <th>% Inasistencia</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($estadisticas_centros) > 0): ?>
                    <?php foreach ($estadisticas_centros as $fila): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fila['codigo_centro']); ?></td>
                            <td><strong><?php echo htmlspecialchars($fila['centro_nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($fila['comuna'] . ', ' . $fila['region']); ?></td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($fila['total_citas']); ?></td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($fila['total_inasistencias']); ?></td>
                            <td style="text-align: center;">
                                <strong><?php echo htmlspecialchars($fila['porcentaje_inasistencia']); ?>%</strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No se encontraron centros médicos registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <br>

        <h3>Top 5 Diagnósticos Más Frecuentes de la Red</h3>
        <table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th style="width: 10%;">Ranking</th>
                    <th style="width: 15%;">Código CIE</th>
                    <th>Descripción del Diagnóstico</th>
                    <th style="width: 15%; text-align: center;">Frecuencia</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($top_diagnosticos) > 0): ?>
                    <?php $ranking = 1; ?>
                    <?php foreach ($top_diagnosticos as $diag): ?>
                        <tr>
                            <td style="text-align: center;"><strong>#<?php echo $ranking++; ?></strong></td>
                            <td><?php echo htmlspecialchars($diag['codigo_d']); ?></td>
                            <td><?php echo htmlspecialchars($diag['descripcion']); ?></td>
                            <td style="text-align: center;"><strong><?php echo htmlspecialchars($diag['total_veces']); ?></strong> atenciones</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">No hay atenciones ni diagnósticos registrados aún.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    <?php endif; ?>

    <br>

    <hr>
<h3>Búsqueda avanzada de citas de la red (Filtros combinables)</h3>

<?php if (!empty($error_filtro_citas)): ?>
    <p style="color: red;"><?php echo htmlspecialchars($error_filtro_citas); ?></p>
<?php endif; ?>

<form action="paginaadmin.php" method="GET" style="background-color: #f8f9fa; padding: 15px; border: 1px solid #ddd; margin-bottom: 20px;">
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
                    <option value="">-- Todos los centros --</option>
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

            <td><label for="f_medico">Médico:</label></td>
            <td>
                <select id="f_medico" name="f_medico">
                    <option value="">-- Todos los médicos --</option>
                    <?php foreach ($filtro_opts_medicos as $m): ?>
                        <option value="<?php echo htmlspecialchars($m['rut_medico']); ?>" <?php echo ($f_medico === $m['rut_medico']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['nombre_completo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
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

            <td><label for="f_prevision">Previsión Paciente:</label></td>
            <td>
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
    <button type="submit">Buscar citas</button>
    <a href="paginaadmin.php"><button type="button">Restablecer filtros</button></a>
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
                    <td><?php echo htmlspecialchars($fila['medico'] ?? 'Sin médico asignado'); ?></td>
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

<h1>Administración SaludUSM</h1>
<section class="card">
  <h2>Médicos</h2>
  <p><a href="admin_medicos.php">Administrar médicos, especialidades y centros</a></p>
</section>
<section class="card">
  <h2>Centros médicos</h2>
  <p><a href="admin_centros.php">Administrar centros médicos</a></p>
</section>



    <a href="logout.php">Cerrar Sesión</a>
</body>
</html>