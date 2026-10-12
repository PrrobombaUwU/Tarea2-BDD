<?php
session_start();

if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['paciente', 'medico'])) {
    header("Location: login.php");
    exit();
}

require_once 'conexion.php';

$rol = $_SESSION['rol'];
$rut_paciente='';
$paciente_info = null;
$error = '';
$historial = [];

if ($rol === 'paciente'){
    $rut_paciente = $_SESSION['rut'];
} elseif ($rol === 'medico'){
    $rut_paciente = trim($_GET['rut_paciente'] ?? '');
}

$lista_pacientes = [];
if ($rol === 'medico'){
    try {
        $sql_pac = "SELECT DISTINCT p.rut_paciente, p.nombre_completo 
            FROM paciente p
            INNER JOIN cita c ON p.rut_paciente = c.rut_paciente
            WHERE c.rut_medico = :med
            ORDER BY p.nombre_completo ASC"
    ;
    $stmt_pac = $pdo->prepare($sql_pac);
    $stmt_pac->execute([':med'=>$_SESSION['rut']]);
    $lista_pacientes = $stmt_pac->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_historial = "Error al cargar lista de pacientes:" . $e->getMessage(); 
    }
}

if (!empty($rut_paciente)){
    try{
        $stmt_p = $pdo->prepare("SELECT rut_paciente, nombre_completo, fecha_nacimiento, sexo FROM paciente WHERE rut_paciente = :rut LIMIT 1");
        $stmt_p->execute([':rut'=>$rut_paciente]);
        $paciente_info = $stmt_p->fetch(PDO::FETCH_ASSOC);

        if ($paciente_info){
            $sql_at = "SELECT 
                    a.id_atencion,
                    a.fecha,
                    a.motivo_consulta,
                    a.obs_clinicas,
                    m.nombre_completo AS medico,
                    esp.nombre AS especialidad,
                    cm.nombre AS centro_medico
                FROM atencion a
                INNER JOIN medico m ON a.rut_medico = m.rut_medico
                INNER JOIN cita c ON a.id_cita = c.id_cita
                INNER JOIN especialidad esp ON c.id_especialidad = esp.id_especialidad
                INNER JOIN centro_medico cm ON a.codigo_centro = cm.codigo_centro
                WHERE a.rut_paciente = :rut
                ORDER BY a.fecha DESC, a.id_atencion DESC
            ";
            $stmt_at = $pdo->prepare($sql_at);
            $stmt_at->execute([':rut'=> $rut_paciente]);
            $atenciones = $stmt_at->FetchAll(PDO::FETCH_ASSOC);
            foreach ($atenciones as $at) {
                $id_at = $at['id_atencion'];

                $sql_d = "
                    SELECT d.codigo_d, d.descripcion 
                    FROM atencion_diagnostico ad
                    INNER JOIN diagnostico d ON ad.codigo_d = d.codigo_d
                    WHERE ad.id_atencion = :id_at
                    ORDER BY d.codigo_d ASC
                ";
                $stmt_d = $pdo->prepare($sql_d);
                $stmt_d->execute([':id_at' => $id_at]);
                $diags = $stmt_d->fetchAll(PDO::FETCH_ASSOC);

                $sql_r = "
                    SELECT medicamento, dosis, Dias_tratamiento 
                    FROM receta 
                    WHERE id_atencion = :id_at
                    ORDER BY id_receta ASC
                ";
                $stmt_r = $pdo->prepare($sql_r);
                $stmt_r->execute([':id_at' => $id_at]);
                $recs = $stmt_r->fetchAll(PDO::FETCH_ASSOC);

                $historial[] = [
                    'datos'        => $at,
                    'diagnosticos' => $diags,
                    'recetas'      => $recs
                ];
            }
        } else {
            $error_historial = "No se encontró el paciente con RUT " . htmlspecialchars($rut_paciente);
        }
    } catch (PDOException $e) {
        $error_historial = "Error al consultar historial: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SaludUSM - Historial Clínico</title>
</head>
<body>

<p>
        <?php if ($rol === 'paciente'): ?>
            <a href="paginapaciente.php">&larr; Volver a mi Portal</a>
        <?php else: ?>
            <a href="paginamedico.php">&larr; Volver a mi Agenda</a>
        <?php endif; ?>
    </p>

    <?php if ($rol === 'medico'): ?>
        <form action="historialclinico.php" method="GET">
            <label for="rut_paciente">Seleccionar paciente:</label>
            <select id="rut_paciente" name="rut_paciente" required>
                <option value="" disabled <?php echo empty($rut_paciente) ? 'selected' : ''; ?>>-- Elija un paciente --</option>
                <?php foreach ($lista_pacientes as $p): ?>
                    <option value="<?php echo htmlspecialchars($p['rut_paciente']); ?>" <?php echo ($rut_paciente === $p['rut_paciente']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['nombre_completo'] . ' (' . $p['rut_paciente'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Ver Historial</button>
        </form>
        <br>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if ($paciente_info): ?>
        <h3>Datos del Paciente</h3>
        <table >
            <!-- border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%;" -->
            <tr>
                <th>Paciente:</th>
                <td><?php echo htmlspecialchars($paciente_info['nombre_completo']); ?></td>
                <th>RUT:</th>
                <td><?php echo htmlspecialchars($paciente_info['rut_paciente']); ?></td>
            </tr>
            <tr>
                <th>Fecha Nacimiento:</th>
                <td><?php echo htmlspecialchars($paciente_info['fecha_nacimiento']); ?></td>
                <th>Sexo:</th>
                <td><?php echo htmlspecialchars($paciente_info['sexo']); ?></td>
            </tr>
        </table>

        <h3>Atenciones Anteriores</h3>

        <?php if (count($historial) > 0): ?>
            <?php foreach ($historial as $item): ?>
                <?php $at = $item['datos']; ?>

                <table border="1">
                    <tr>
                        <th>Fecha:</th>
                        <td><?php echo htmlspecialchars($at['fecha']); ?></td>
                        <th>Especialidad:</th>
                        <td><?php echo htmlspecialchars($at['especialidad']); ?></td>
                    </tr>
                    <tr>
                        <th>Médico:</th>
                        <td><?php echo htmlspecialchars($at['medico']); ?></td>
                        <th>Centro Médico:</th>
                        <td><?php echo htmlspecialchars($at['centro_medico']); ?></td>
                    </tr>
                    <tr>
                        <th>Motivo:</th>
                        <td colspan="3"><?php echo nl2br(htmlspecialchars($at['motivo_consulta'] ?? 'Sin motivo registrado')); ?></td>
                    </tr>
                    <tr>
                        <th>Observaciones:</th>
                        <td colspan="3"><?php echo nl2br(htmlspecialchars($at['obs_clinicas'] ?? 'Sin observaciones')); ?></td>
                    </tr>
                    <tr>
                        <th>Diagnósticos:</th>
                        <td colspan="3">
                            <?php if (count($item['diagnosticos']) > 0): ?>
                                <ul>
                                    <?php foreach ($item['diagnosticos'] as $d): ?>
                                        <li><strong><?php echo htmlspecialchars($d['codigo_d']); ?>:</strong> <?php echo htmlspecialchars($d['descripcion']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <em>Sin diagnósticos registrados</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Recetas:</th>
                        <td colspan="3">
                            <?php if (count($item['recetas']) > 0): ?>
                                <table border="1">
                                    <tr>
                                        <th>Medicamento</th>
                                        <th>Dosis</th>
                                        <th>Días Tratamiento</th>
                                    </tr>
                                    <?php foreach ($item['recetas'] as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['medicamento']); ?></td>
                                            <td><?php echo htmlspecialchars($r['dosis'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($r['Dias_tratamiento'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            <?php else: ?>
                                <em>Sin recetas prescritas</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <br>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No se registran atenciones médicas anteriores para este paciente.</p>
        <?php endif; ?>

        <?php elseif ($rol === 'medico' && empty($rut_paciente)): ?>
        <p>Seleccione un paciente de la lista para ver su historial.</p>
    <?php endif; ?>

</body>
</html>

        