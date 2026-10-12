<?php
session_start();


if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'paciente') {
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

try {
        $sql = "SELECT c.id_cita, c.fecha_hora, m.nombre_completo, esp.nombre as nombre_especialidad, cm.nombre as nombre_centromedico, e.estado
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
        $sql = "SELECT c.fecha_hora, m.nombre_completo, esp.nombre as nombre_especialidad, cm.nombre as nombre_centromedico, e.estado
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



$sel_esp    = $_GET['id_especialidad'] ?? '';
$sel_centro = $_GET['codigo_centro'] ?? '';
$sel_medico = $_GET['rut_medico'] ?? '';
$sel_fecha  = $_GET['fecha'] ?? '';

$lista_especialidades = [];
$lista_centros = [];
$lista_medicos = [];
$bloques_disponibles = [];
$mensaje_agendar_error = '';
$mensaje_agendar_exito = '';


$duracion_bloque_minutos = 30;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agendar_cita') {
    $esp_post    = $_POST['id_especialidad'] ?? '';
    $centro_post = $_POST['codigo_centro'] ?? '';
    $medico_post = $_POST['rut_medico'] ?? '';
    $hora_post   = $_POST['hora_bloque'] ?? ''; 

    if (!empty($esp_post) && !empty($centro_post) && !empty($medico_post) && !empty($hora_post)) {
        try {
            $fin_post = date('Y-m-d H:i:s', strtotime($hora_post . " +$duracion_bloque_minutos minutes"));

            $sql_verif = "SELECT COUNT(*) FROM cita 
                          WHERE rut_medico = :med 
                            AND id_estado != 5
                            AND fecha_hora < :fin_bloque 
                            AND DATE_ADD(fecha_hora, INTERVAL :dur MINUTE) > :inicio_bloque";

            $stmt_verif = $pdo->prepare($sql_verif);
            $stmt_verif->execute([
                ':med'           => $medico_post,
                ':fin_bloque'    => $fin_post,
                ':dur'           => $duracion_bloque_minutos,
                ':inicio_bloque' => $hora_post
            ]);

            if ($stmt_verif->fetchColumn() == 0) {
                $sql_ins = "INSERT INTO cita (fecha_hora, rut_paciente, rut_medico, codigo_centro, id_especialidad, id_estado) 
                            VALUES (:fh, :pac, :med, :cen, :esp, 1)";
                $stmt_ins = $pdo->prepare($sql_ins);
                $stmt_ins->execute([
                    ':fh'  => $hora_post,
                    ':pac' => $rut_paciente,
                    ':med' => $medico_post,
                    ':cen' => $centro_post,
                    ':esp' => $esp_post
                ]);
                $mensaje_agendar_exito = "¡Cita agendada exitosamente para el " . htmlspecialchars($hora_post) . "!";
                $sel_esp = $sel_centro = $sel_medico = $sel_fecha = '';
            } else {
                $mensaje_agendar_error = "El bloque horario seleccionado ya se encuentra ocupado. Por favor elija otro.";
            }
        } catch (PDOException $e) {
            $mensaje_agendar_error = "Error al agendar cita: " . $e->getMessage();
        }
    } else {
        $mensaje_agendar_error = "Debe completar todos los datos para agendar.";
    }
}

try {
    $stmt_e = $pdo->query("SELECT id_especialidad, nombre FROM especialidad ORDER BY nombre ASC");
    $lista_especialidades = $stmt_e->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje_agendar_error = "Error al cargar especialidades: " . $e->getMessage();
}


if (!empty($sel_esp)) {
    try {
        $sql_c = "SELECT DISTINCT c.codigo_centro, c.nombre 
                  FROM centro_medico c
                  INNER JOIN medico_centro mc ON c.codigo_centro = mc.codigo_centro
                  INNER JOIN medico_especialidad me ON mc.rut_medico = me.rut_medico
                  WHERE me.id_especialidad = :esp
                  ORDER BY c.nombre ASC";
        $stmt_c = $pdo->prepare($sql_c);
        $stmt_c->execute([':esp' => $sel_esp]);
        $lista_centros = $stmt_c->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensaje_agendar_error = "Error al cargar centros: " . $e->getMessage();
    }
}


if (!empty($sel_esp) && !empty($sel_centro)) {
    try {
        $sql_m = "SELECT DISTINCT m.rut_medico, m.nombre_completo 
                  FROM medico m
                  INNER JOIN medico_especialidad me ON m.rut_medico = me.rut_medico
                  INNER JOIN medico_centro mc ON m.rut_medico = mc.rut_medico
                  WHERE me.id_especialidad = :esp AND mc.codigo_centro = :cen
                  ORDER BY m.nombre_completo ASC";
        $stmt_m = $pdo->prepare($sql_m);
        $stmt_m->execute([':esp' => $sel_esp, ':cen' => $sel_centro]);
        $lista_medicos = $stmt_m->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensaje_agendar_error = "Error al cargar médicos: " . $e->getMessage();
    }
}


if (!empty($sel_esp) && !empty($sel_centro) && !empty($sel_medico) && !empty($sel_fecha)) {
    try {
        $sql_citas = "SELECT fecha_hora FROM cita 
                      WHERE rut_medico = :med 
                        AND DATE(fecha_hora) = :fecha
                        AND id_estado != 5";
        $stmt_citas = $pdo->prepare($sql_citas);
        $stmt_citas->execute([':med' => $sel_medico, ':fecha' => $sel_fecha]);
        $citas_ocupadas = $stmt_citas->fetchAll(PDO::FETCH_ASSOC);

        $hora_inicio = new DateTime($sel_fecha . ' 09:00:00');
        $hora_cierre = new DateTime($sel_fecha . ' 18:00:00');
        $ahora = new DateTime();

        while ($hora_inicio < $hora_cierre) {
            $bloque_ini = clone $hora_inicio;
            $bloque_fin = (clone $hora_inicio)->modify("+{$duracion_bloque_minutos} minutes");

            if ($bloque_fin > $hora_cierre) {
                break;
            }

            $esta_ocupado = false;
            foreach ($citas_ocupadas as $c) {
                $cita_ini = new DateTime($c['fecha_hora']);
                $cita_fin = (clone $cita_ini)->modify("+{$duracion_bloque_minutos} minutes");

                if ($bloque_ini < $cita_fin && $bloque_fin > $cita_ini) {
                    $esta_ocupado = true;
                    break;
                }
            }

            if (!$esta_ocupado && $bloque_ini > $ahora) {
                $bloques_disponibles[] = [
                    'hora'     => $bloque_ini->format('H:i'),
                    'datetime' => $bloque_ini->format('Y-m-d H:i:s')
                ];
            }

            $hora_inicio->modify("+{$duracion_bloque_minutos} minutes");
        }
    } catch (PDOException $e) {
        $mensaje_agendar_error = "Error al calcular horarios: " . $e->getMessage();
    }
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
    $error_filtro_citas = "Error al cargar listas de filtros: " . $e->getMessage();
}


$sql_avanzada = "SELECT fecha_hora, paciente, medico, especialidad, centro_medico, estado 
                 FROM vista_citas_resumen 
                 WHERE rut_paciente = :rut_paciente";

$params_avanzada = [':rut_paciente' => $rut_paciente];

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
    $error_filtro_citas = "Error al ejecutar la búsqueda avanzada: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cancelar_cita') {
    $id_cita_cancelar = filter_input(INPUT_POST, 'id_cita', FILTER_VALIDATE_INT);

    if ($id_cita_cancelar) {
        try {
            $sql_val = "SELECT id_cita FROM cita 
                        WHERE id_cita = :id 
                          AND rut_paciente = :rut 
                          AND fecha_hora > NOW() 
                          AND id_estado IN (1, 2)";
            $stmt_val = $pdo->prepare($sql_val);
            $stmt_val->execute([':id' => $id_cita_cancelar, ':rut' => $rut_paciente]);

            if ($stmt_val->fetch()) {
                $sql_can = "UPDATE cita SET id_estado = 5 WHERE id_cita = :id";
                $stmt_can = $pdo->prepare($sql_can);
                $stmt_can->execute([':id' => $id_cita_cancelar]);
                $mensaje_exito = "La cita ha sido cancelada exitosamente.";
            } else {
                $mensaje_error = "No se puede cancelar esta cita: ya ha pasado o su estado actual no lo permite.";
            }
        } catch (PDOException $e) {
            $mensaje_error = "Error al cancelar la cita: " . $e->getMessage();
        }
    }
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
    <?php if (!empty($mensaje_exito)): ?>
        <p style="color: green; font-weight: bold; background-color: #e6ffe6; padding: 8px; border: 1px solid green;">
            <?php echo htmlspecialchars($mensaje_exito); ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($mensaje_error)): ?>
        <p style="color: red; font-weight: bold; background-color: #ffe6e6; padding: 8px; border: 1px solid red;">
            <?php echo htmlspecialchars($mensaje_error); ?>
        </p>
    <?php endif; ?>

    <?php if (count($resultados_futuracita) > 0): ?>
            <table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th>Fecha y hora</th>
                        <th>Medico</th>
                        <th>Especialidad</th>
                        <th>Centro medico</th>
                        <th>Estado</th>
                        <th>Modificar</th>
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
                            <td>
                                <?php if (($row['estado']) != 'Cancelada'): ?>
                                <a href="reprogramar.php?id_cita=<?php echo htmlspecialchars($row['id_cita']); ?>">Reprogramar</a>&nbsp;&nbsp;&nbsp;<form action="paginapaciente.php" method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de que desea cancelar esta cita?');">
                                <input type="hidden" name="id_cita" value="<?php echo htmlspecialchars($row['id_cita']); ?>">
                                <button type="submit" name="accion" value="cancelar_cita" 
                                        style="color: #dc3545; background: none; border: none; padding: 0; font: inherit; cursor: pointer; text-decoration: underline;">
                                    Cancelar Cita
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
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

        <hr>
        <h3>Búsqueda avanzada de citas (Filtros combinables)</h3>

        <?php if (!empty($error_filtro_citas)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error_filtro_citas); ?></p>
        <?php endif; ?>

        <form action="paginapaciente.php" method="GET" style="background-color: #f8f9fa; padding: 15px; border: 1px solid #ddd; margin-bottom: 20px;">
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

                    <td><label for="f_prevision">Previsión:</label></td>
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
            <a href="paginapaciente.php"><button type="button">Restablecer filtros</button></a>
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


        <hr>
        <h3>Agendar cita</h3>

        <?php if (!empty($mensaje_agendar_exito)): ?>
            <p style="color: green; font-weight: bold; background-color: #e6ffe6; padding: 8px; border: 1px solid green;">
            <?php echo htmlspecialchars($mensaje_agendar_exito); ?>
        </p>
        <?php endif; ?>

        <?php if (!empty($mensaje_agendar_error)): ?>
            <p style="color: red; font-weight: bold; background-color: #ffe6e6; padding: 8px; border: 1px solid red;">
            <?php echo htmlspecialchars($mensaje_agendar_error); ?>
            </p>
            <?php endif; ?>

<form action="paginapaciente.php" method="GET">
    <div>
        <label for="id_especialidad"><strong>1. Especialidad:</strong></label><br>
        <select id="id_especialidad" name="id_especialidad" onchange="this.form.submit()" required>
            <option value="" disabled <?php echo empty($sel_esp) ? 'selected' : ''; ?>>Seleccione una especialidad</option>
            <?php foreach ($lista_especialidades as $e): ?>
                <option value="<?php echo $e['id_especialidad']; ?>" <?php echo ($sel_esp == $e['id_especialidad']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($e['nombre']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <br>

    <?php if (!empty($sel_esp)): ?>
        <div>
            <label for="codigo_centro"><strong>2. Centro Médico:</strong></label><br>
            <select id="codigo_centro" name="codigo_centro" onchange="this.form.submit()" required>
                <option value="" disabled <?php echo empty($sel_centro) ? 'selected' : ''; ?>>Seleccione un centro médico</option>
                <?php foreach ($lista_centros as $c): ?>
                    <option value="<?php echo $c['codigo_centro']; ?>" <?php echo ($sel_centro == $c['codigo_centro']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <br>
    <?php endif; ?>

    <?php if (!empty($sel_centro)): ?>
        <div>
            <label for="rut_medico"><strong>3. Médico:</strong></label><br>
            <select id="rut_medico" name="rut_medico" onchange="this.form.submit()" required>
                <option value="" disabled <?php echo empty($sel_medico) ? 'selected' : ''; ?>>Seleccione un médico</option>
                <?php foreach ($lista_medicos as $m): ?>
                    <option value="<?php echo $m['rut_medico']; ?>" <?php echo ($sel_medico === $m['rut_medico']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($m['nombre_completo']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <br>
    <?php endif; ?>

    <?php if (!empty($sel_medico)): ?>
        <div>
            <label for="fecha"><strong>4. Fecha:</strong></label><br>
            <input type="date" id="fecha" name="fecha" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($sel_fecha); ?>" onchange="this.form.submit()" required>
        </div>
        <br>
    <?php endif; ?>
</form>

<?php if (!empty($sel_fecha) && !empty($sel_medico)): ?>
    <form action="paginapaciente.php" method="POST">
        <input type="hidden" name="id_especialidad" value="<?php echo htmlspecialchars($sel_esp); ?>">
        <input type="hidden" name="codigo_centro" value="<?php echo htmlspecialchars($sel_centro); ?>">
        <input type="hidden" name="rut_medico" value="<?php echo htmlspecialchars($sel_medico); ?>">

        <label><strong>5. Bloques horarios disponibles:</strong></label><br>

        <?php if (count($bloques_disponibles) > 0): ?>
            <div style="margin: 10px 0;">
                <?php foreach ($bloques_disponibles as $bloque): ?>
                    <label style="display: inline-block; margin: 4px; padding: 6px 12px; border: 1px solid #007bff; border-radius: 4px; cursor: pointer;">
                        <input type="radio" name="hora_bloque" value="<?php echo htmlspecialchars($bloque['datetime']); ?>" required>
                        <?php echo htmlspecialchars($bloque['hora']); ?> hrs
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" name="accion" value="agendar_cita" style="padding: 8px 16px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
                Agendar Cita
            </button>
        <?php else: ?>
            <p style="color: #c00;">No hay bloques disponibles para el médico seleccionado en esta fecha.</p>
        <?php endif; ?>
    </form>
    <br>
<?php endif; ?>

<p><a href="historialclinico.php"><button type="button">Ver mi Historial Clínico</button></a></p>


    <a href="logout.php">Cerrar Sesión</a>
</body>
</html>

