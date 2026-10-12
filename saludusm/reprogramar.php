<?php
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'paciente') {
    header("Location: login.php");
    exit();
}

require_once 'conexion.php';

$rut_paciente = $_SESSION['rut'];
$cita_actual = null;
$error = null;
$mensaje_exito = '';
$mensaje_error = '';

$duracion_bloque_minutos = 30;
$bloques_disponibles = [];

$id_cita_modificar = filter_input(INPUT_GET, 'id_cita', FILTER_VALIDATE_INT) 
                  ?? filter_input(INPUT_POST, 'id_cita', FILTER_VALIDATE_INT);
$sel_fecha = $_GET['fecha'] ?? $_POST['fecha'] ?? '';

if ($id_cita_modificar) {
    try {
        $sql = "SELECT c.id_cita, c.fecha_hora, c.rut_medico, c.codigo_centro, c.id_especialidad,
                       m.nombre_completo, esp.nombre AS nombre_especialidad, 
                       cm.nombre AS nombre_centromedico, e.estado, c.id_estado
                FROM cita c
                INNER JOIN medico m ON m.rut_medico = c.rut_medico
                INNER JOIN especialidad esp ON esp.id_especialidad = c.id_especialidad
                INNER JOIN centro_medico cm ON cm.codigo_centro = c.codigo_centro
                INNER JOIN estado_cita e ON e.id_estado = c.id_estado
                WHERE c.id_cita = :id_cita_modificar";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_cita_modificar' => $id_cita_modificar
        ]);

$cita_actual = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cita_actual) {
            $error = "No se encontró la cita o no tiene permisos para reprogramarla.";
        }
    } catch (PDOException $e) {
        $error = "Error al cargar la cita: " . $e->getMessage();
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'reprogramar_cita') {
    $hora_post = $_POST['hora_bloque'] ?? '';

    if (!empty($cita_actual) && !empty($hora_post)) {
        try {
            $fin_post = date('Y-m-d H:i:s', strtotime($hora_post . " +$duracion_bloque_minutos minutes"));

            $sql_verif = "SELECT COUNT(*) FROM cita 
                          WHERE rut_medico = :med 
                            AND id_cita != :id_actual
                            AND id_estado != 5
                            AND fecha_hora < :fin_bloque 
                            AND DATE_ADD(fecha_hora, INTERVAL :dur MINUTE) > :inicio_bloque";

            $stmt_verif = $pdo->prepare($sql_verif);
            $stmt_verif->execute([
                ':med'           => $cita_actual['rut_medico'],
                ':id_actual'     => $cita_actual['id_cita'],
                ':fin_bloque'    => $fin_post,
                ':dur'           => $duracion_bloque_minutos,
                ':inicio_bloque' => $hora_post
            ]);

            if ($stmt_verif->fetchColumn() == 0) {
                $sql_upd = "UPDATE cita SET fecha_hora = :nueva_fh WHERE id_cita = :id";
                $stmt_upd = $pdo->prepare($sql_upd);
                $stmt_upd->execute([
                    ':nueva_fh' => $hora_post,
                    ':id'       => $cita_actual['id_cita']
                ]);

                $mensaje_exito = "¡Cita reprogramada exitosamente para el " . htmlspecialchars($hora_post) . "!";
                $cita_actual['fecha_hora'] = $hora_post;
                $sel_fecha = '';
            } else {
                $mensaje_error = "El bloque horario seleccionado ya fue tomado. Por favor elija otro.";
            }
        } catch (PDOException $e) {
            $mensaje_error = "Error al reprogramar la cita: " . $e->getMessage();
        }
    } else {
        $mensaje_error = "Debe seleccionar un bloque horario válido.";
    }
}

if (!empty($cita_actual) && !empty($sel_fecha)) {
    try {
        $sql_citas = "SELECT fecha_hora FROM cita 
                      WHERE rut_medico = :med 
                        AND id_cita != :id_actual
                        AND DATE(fecha_hora) = :fecha
                        AND id_estado != 5";
        $stmt_citas = $pdo->prepare($sql_citas);
        $stmt_citas->execute([
            ':med'       => $cita_actual['rut_medico'],
            ':id_actual' => $cita_actual['id_cita'],
            ':fecha'     => $sel_fecha
        ]);
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
        $mensaje_error = "Error al calcular horarios: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SaludUSM - Reprogramar Cita</title>
</head>
<body>
    <h1>Reprogramar Cita</h1>
    <hr>
    
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

    <?php if ($error): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
        <p><a href="paginapaciente.php">Volver a mis citas</a></p>
    <?php elseif ($cita_actual): ?>
        <h3>Datos de la Cita Actual:</h3>
        <table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th>Fecha y hora actual</th>
                    <th>Médico</th>
                    <th>Especialidad</th>
                    <th>Centro médico</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php echo htmlspecialchars($cita_actual['fecha_hora']); ?></strong></td>
                    <td><?php echo htmlspecialchars($cita_actual['nombre_completo']); ?></td>
                    <td><?php echo htmlspecialchars($cita_actual['nombre_especialidad']); ?></td>
                    <td><?php echo htmlspecialchars($cita_actual['nombre_centromedico']); ?></td>
                    <td><?php echo htmlspecialchars($cita_actual['estado']); ?></td>
                </tr>
            </tbody>
        </table>

        <br>
        <hr>

        <form action="reprogramar.php" method="GET">
            <input type="hidden" name="id_cita" value="<?php echo htmlspecialchars($cita_actual['id_cita']); ?>">
            <div>
                <label for="fecha"><strong>Seleccione nueva fecha:</strong></label><br>
                <input type="date" id="fecha" name="fecha" min="<?php echo date('Y-m-d'); ?>" 
                       value="<?php echo htmlspecialchars($sel_fecha); ?>" onchange="this.form.submit()" required>
            </div>
        </form>

        <?php if (!empty($sel_fecha)): ?>
            <br>
            <form action="reprogramar.php" method="POST">
                <input type="hidden" name="id_cita" value="<?php echo htmlspecialchars($cita_actual['id_cita']); ?>">
                <input type="hidden" name="fecha" value="<?php echo htmlspecialchars($sel_fecha); ?>">

                <label><strong>Bloques horarios disponibles:</strong></label><br>

                <?php if (count($bloques_disponibles) > 0): ?>
                    <div style="margin: 10px 0;">
                        <?php foreach ($bloques_disponibles as $bloque): ?>
                            <label style="display: inline-block; margin: 4px; padding: 6px 12px; border: 1px solid #007bff; border-radius: 4px; cursor: pointer;">
                                <input type="radio" name="hora_bloque" value="<?php echo htmlspecialchars($bloque['datetime']); ?>" required>
                                <?php echo htmlspecialchars($bloque['hora']); ?> hrs
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="accion" value="reprogramar_cita" style="padding: 8px 16px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Confirmar Reprogramación
                    </button>
                <?php else: ?>
                    <p style="color: #c00;">No hay bloques disponibles para el médico en esta fecha.</p>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <br><br>
        <a href="paginapaciente.php">&larr; Volver a mi Portal</a>
    <?php endif; ?>

</body>
</html>