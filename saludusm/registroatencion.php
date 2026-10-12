<?php
session_start();

// 1. Verificación de sesión y rol
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'medico') {
    header("Location: login.php");
    exit();
}

require_once 'conexion.php';

$rut_medico = $_SESSION['rut'];
$id_cita = filter_input(INPUT_GET, 'id_cita', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'id_cita', FILTER_VALIDATE_INT);

if (!$id_cita) {
    header("Location: paginamedico.php");
    exit();
}

$mensaje_exito = '';
$mensaje_error = '';

// 2. Validar que la cita pertenezca al médico y obtener datos
try {
    $sql_cita = "
        SELECT 
            c.id_cita,
            c.fecha_hora,
            c.rut_paciente,
            c.codigo_centro,
            c.id_estado,
            p.nombre_completo AS paciente,
            cm.nombre AS centro_nombre,
            esp.nombre AS especialidad
        FROM cita c
        INNER JOIN paciente p ON c.rut_paciente = p.rut_paciente
        INNER JOIN centro_medico cm ON c.codigo_centro = cm.codigo_centro
        INNER JOIN especialidad esp ON c.id_especialidad = esp.id_especialidad
        WHERE c.id_cita = :id_cita AND c.rut_medico = :rut_medico
        LIMIT 1
    ";
    $stmt_cita = $pdo->prepare($sql_cita);
    $stmt_cita->execute([
        ':id_cita'    => $id_cita,
        ':rut_medico' => $rut_medico
    ]);
    $cita = $stmt_cita->fetch(PDO::FETCH_ASSOC);

    if (!$cita) {
        die("Acceso denegado: La cita no existe o no corresponde al médico autenticado.");
    }
} catch (PDOException $e) {
    die("Error al consultar la cita: " . $e->getMessage());
}

// 3. Buscar si ya existe la atención
$atencion = null;
try {
    $stmt_at = $pdo->prepare("SELECT * FROM atencion WHERE id_cita = :id_cita LIMIT 1");
    $stmt_at->execute([':id_cita' => $id_cita]);
    $atencion = $stmt_at->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje_error = "Error al verificar la atención: " . $e->getMessage();
}

// 4. ACCIONES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    // -----------------------------------------------------------
    // A) GUARDAR O ACTUALIZAR ATENCIÓN PRINCIPAL
    // -----------------------------------------------------------
    if ($accion === 'guardar_atencion') {
        $motivo = trim($_POST['motivo_consulta'] ?? '');
        $obs    = trim($_POST['obs_clinicas'] ?? '');

        // Regla: la cita debe estar Atendida (id_estado = 3)
        if ((int)$cita['id_estado'] !== 3) {
            try {
                $stmt_upd_estado = $pdo->prepare("UPDATE cita SET id_estado = 3 WHERE id_cita = :id_cita");
                $stmt_upd_estado->execute([':id_cita' => $id_cita]);
                $cita['id_estado'] = 3;
            } catch (PDOException $e) {
                $mensaje_error = "Error al cambiar estado a Atendida: " . $e->getMessage();
            }
        }

        if (empty($mensaje_error)) {
            try {
                if ($atencion) {
                    $sql_upd = "UPDATE atencion SET motivo_consulta = :motivo, obs_clinicas = :obs WHERE id_atencion = :id_atencion";
                    $stmt_upd = $pdo->prepare($sql_upd);
                    $stmt_upd->execute([':motivo' => $motivo, ':obs' => $obs, ':id_atencion' => $atencion['id_atencion']]);
                    $mensaje_exito = "¡Atención actualizada con éxito!";
                } else {
                    $fecha_at = date('Y-m-d', strtotime($cita['fecha_hora']));
                    $sql_ins = "INSERT INTO atencion (motivo_consulta, obs_clinicas, id_cita, fecha, codigo_centro, rut_paciente, rut_medico) 
                                VALUES (:motivo, :obs, :id_cita, :fecha, :cen, :pac, :med)";
                    $stmt_ins = $pdo->prepare($sql_ins);
                    $stmt_ins->execute([
                        ':motivo'   => $motivo,
                        ':obs'      => $obs,
                        ':id_cita'  => $id_cita,
                        ':fecha'    => $fecha_at,
                        ':cen'      => $cita['codigo_centro'],
                        ':pac'      => $cita['rut_paciente'],
                        ':med'      => $rut_medico
                    ]);
                    $mensaje_exito = "¡Atención registrada correctamente!";
                }
                // Refrescar datos de la atención
                $stmt_at = $pdo->prepare("SELECT * FROM atencion WHERE id_cita = :id_cita LIMIT 1");
                $stmt_at->execute([':id_cita' => $id_cita]);
                $atencion = $stmt_at->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $mensaje_error = "Error al procesar la atención: " . $e->getMessage();
            }
        }
    }

    // -----------------------------------------------------------
    // B) SUBCOMPONENTE: DIAGNÓSTICOS
    // -----------------------------------------------------------
    elseif ($accion === 'agregar_diagnostico' && $atencion) {
        $codigo_d = trim($_POST['codigo_d'] ?? '');
        if (!empty($codigo_d)) {
            try {
                $stmt_add_diag = $pdo->prepare("INSERT INTO atencion_diagnostico (id_atencion, codigo_d) VALUES (:id_at, :cod)");
                $stmt_add_diag->execute([':id_at' => $atencion['id_atencion'], ':cod' => $codigo_d]);
                $mensaje_exito = "Diagnóstico agregado exitosamente.";
            } catch (PDOException $e) {
                $mensaje_error = "El diagnóstico ya está asociado a esta atención o no es válido.";
            }
        }
    }
    elseif ($accion === 'eliminar_diagnostico' && $atencion) {
        $codigo_d = trim($_POST['codigo_d'] ?? '');
        try {
            $stmt_del_diag = $pdo->prepare("DELETE FROM atencion_diagnostico WHERE id_atencion = :id_at AND codigo_d = :cod");
            $stmt_del_diag->execute([':id_at' => $atencion['id_atencion'], ':cod' => $codigo_d]);
            $mensaje_exito = "Diagnóstico eliminado de la atención.";
        } catch (PDOException $e) {
            $mensaje_error = "Error al quitar diagnóstico: " . $e->getMessage();
        }
    }

    // -----------------------------------------------------------
    // C) SUBCOMPONENTE: RECETAS
    // -----------------------------------------------------------
    elseif ($accion === 'agregar_receta' && $atencion) {
        $medicamento = trim($_POST['medicamento'] ?? '');
        $dosis       = trim($_POST['dosis'] ?? '');
        $dias        = filter_input(INPUT_POST, 'dias_tratamiento', FILTER_VALIDATE_INT);

        if (!empty($medicamento)) {
            try {
                $sql_rec = "INSERT INTO receta (id_atencion, medicamento, dosis, Dias_tratamiento) 
                            VALUES (:id_at, :med, :dos, :dias)";
                $stmt_rec = $pdo->prepare($sql_rec);
                $stmt_rec->execute([
                    ':id_at' => $atencion['id_atencion'],
                    ':med'   => $medicamento,
                    ':dos'   => $dosis,
                    ':dias'  => $dias
                ]);
                $mensaje_exito = "Medicamento recetado con éxito.";
            } catch (PDOException $e) {
                $mensaje_error = "Error al registrar receta: " . $e->getMessage();
            }
        }
    }
    elseif ($accion === 'editar_receta' && $atencion) {
        $id_receta   = filter_input(INPUT_POST, 'id_receta', FILTER_VALIDATE_INT);
        $medicamento = trim($_POST['medicamento'] ?? '');
        $dosis       = trim($_POST['dosis'] ?? '');
        $dias        = filter_input(INPUT_POST, 'dias_tratamiento', FILTER_VALIDATE_INT);

        if ($id_receta && !empty($medicamento)) {
            try {
                $sql_upd_rec = "UPDATE receta 
                                SET medicamento = :med, dosis = :dos, Dias_tratamiento = :dias 
                                WHERE id_receta = :id_rec AND id_atencion = :id_at";
                $stmt_upd_rec = $pdo->prepare($sql_upd_rec);
                $stmt_upd_rec->execute([
                    ':med'    => $medicamento,
                    ':dos'    => $dosis,
                    ':dias'   => $dias,
                    ':id_rec' => $id_receta,
                    ':id_at'  => $atencion['id_atencion']
                ]);
                $mensaje_exito = "Receta actualizada.";
            } catch (PDOException $e) {
                $mensaje_error = "Error al modificar la receta: " . $e->getMessage();
            }
        }
    }
    elseif ($accion === 'eliminar_receta' && $atencion) {
        $id_receta = filter_input(INPUT_POST, 'id_receta', FILTER_VALIDATE_INT);
        try {
            $stmt_del_rec = $pdo->prepare("DELETE FROM receta WHERE id_receta = :id_rec AND id_atencion = :id_at");
            $stmt_del_rec->execute([':id_rec' => $id_receta, ':id_at' => $atencion['id_atencion']]);
            $mensaje_exito = "Medicamento eliminado de la receta.";
        } catch (PDOException $e) {
            $mensaje_error = "Error al eliminar la receta: " . $e->getMessage();
        }
    }
}

// 5. Cargar datos relacionados de la atención
$diagnosticos_atencion = [];
$recetas_atencion = [];
$catalogo_diagnosticos = [];

if ($atencion) {
    try {
        // Diagnósticos de esta atención
        $sql_diag = "
            SELECT d.codigo_d, d.descripcion 
            FROM atencion_diagnostico ad
            INNER JOIN diagnostico d ON ad.codigo_d = d.codigo_d
            WHERE ad.id_atencion = :id_at
        ";
        $stmt_diag = $pdo->prepare($sql_diag);
        $stmt_diag->execute([':id_at' => $atencion['id_atencion']]);
        $diagnosticos_atencion = $stmt_diag->fetchAll(PDO::FETCH_ASSOC);

        // Recetas de esta atención
        $stmt_r = $pdo->prepare("SELECT * FROM receta WHERE id_atencion = :id_at ORDER BY id_receta ASC");
        $stmt_r->execute([':id_at' => $atencion['id_atencion']]);
        $recetas_atencion = $stmt_r->fetchAll(PDO::FETCH_ASSOC);

        // Catálogo global de diagnósticos para el selector
        $catalogo_diagnosticos = $pdo->query("SELECT codigo_d, descripcion FROM diagnostico ORDER BY descripcion ASC")->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $mensaje_error = "Error al cargar detalles de la atención: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SaludUSM - Ficha de Atención</title>
</head>
<body>
    <p><a href="paginamedico.php">&larr; Volver a mi Agenda</a></p>
    <h2>Ficha Clínica: Registro de Atención</h2>

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

    <!-- Resumen Cita (Vista 2 del Anexo del PDF) -->
    <table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 750px; background-color: #fafafa;">
        <tr>
            <th style="width: 25%; text-align: left;">N° de Cita:</th>
            <td><?php echo htmlspecialchars($cita['id_cita']); ?></td>
            <th style="width: 25%; text-align: left;">Estado:</th>
            <td><strong><?php echo ((int)$cita['id_estado'] === 3) ? 'Atendida' : 'Pendiente'; ?></strong></td>
        </tr>
        <tr>
            <th style="text-align: left;">Paciente:</th>
            <td><?php echo htmlspecialchars($cita['paciente']); ?></td>
            <th style="text-align: left;">RUT Paciente:</th>
            <td><?php echo htmlspecialchars($cita['rut_paciente']); ?></td>
        </tr>
        <tr>
            <th style="text-align: left;">Centro Médico:</th>
            <td><?php echo htmlspecialchars($cita['centro_nombre']); ?></td>
            <th style="text-align: left;">Fecha y Hora:</th>
            <td><?php echo htmlspecialchars($cita['fecha_hora']); ?></td>
        </tr>
    </table>

    <br>

    <!-- Formulario 1: Motivo y Observaciones -->
    <form action="registroatencion.php?id_cita=<?php echo urlencode($id_cita); ?>" method="POST" style="max-width: 750px;">
        <input type="hidden" name="id_cita" value="<?php echo htmlspecialchars($id_cita); ?>">

        <div>
            <label for="motivo_consulta"><strong>Motivo de Consulta:</strong></label><br>
            <textarea id="motivo_consulta" name="motivo_consulta" rows="2" style="width: 100%;" required><?php echo htmlspecialchars($atencion['motivo_consulta'] ?? ''); ?></textarea>
        </div>
        <br>

        <div>
            <label for="obs_clinicas"><strong>Observaciones Clínicas:</strong></label><br>
            <textarea id="obs_clinicas" name="obs_clinicas" rows="4" style="width: 100%;"><?php echo htmlspecialchars($atencion['obs_clinicas'] ?? ''); ?></textarea>
        </div>
        <br>

        <button type="submit" name="accion" value="guardar_atencion" style="padding: 6px 14px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
            <?php echo $atencion ? 'Actualizar Ficha Principal' : 'Guardar y Abrir Diagnósticos / Recetas'; ?>
        </button>
    </form>

    <?php if ($atencion): ?>
        <hr>

        <!-- SECCIÓN DIAGNÓSTICOS (CIE-10) -->
        <h3>Diagnósticos Asociados</h3>

        <form action="registroatencion.php?id_cita=<?php echo urlencode($id_cita); ?>" method="POST" style="margin-bottom: 15px;">
            <input type="hidden" name="id_cita" value="<?php echo htmlspecialchars($id_cita); ?>">
            <label for="codigo_d">Seleccionar diagnóstico (CIE-10):</label>
            <select id="codigo_d" name="codigo_d" required>
                <option value="" disabled selected>-- Elija un diagnóstico --</option>
                <?php foreach ($catalogo_diagnosticos as $d): ?>
                    <option value="<?php echo htmlspecialchars($d['codigo_d']); ?>">
                        <?php echo htmlspecialchars($d['codigo_d'] . ' - ' . $d['descripcion']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="accion" value="agregar_diagnostico">Asignar a la atención</button>
        </form>

        <table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 750px;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th style="width: 20%;">Código CIE-10</th>
                    <th>Descripción</th>
                    <th style="width: 15%;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($diagnosticos_atencion) > 0): ?>
                    <?php foreach ($diagnosticos_atencion as $diag): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($diag['codigo_d']); ?></strong></td>
                            <td><?php echo htmlspecialchars($diag['descripcion']); ?></td>
                            <td style="text-align: center;">
                                <form action="registroatencion.php?id_cita=<?php echo urlencode($id_cita); ?>" method="POST" onsubmit="return confirm('¿Quitar este diagnóstico?');">
                                    <input type="hidden" name="id_cita" value="<?php echo htmlspecialchars($id_cita); ?>">
                                    <input type="hidden" name="codigo_d" value="<?php echo htmlspecialchars($diag['codigo_d']); ?>">
                                    <button type="submit" name="accion" value="eliminar_diagnostico" style="color: red; cursor: pointer;">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No hay diagnósticos asociados a esta atención.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <hr>

        <!-- SECCIÓN RECETAS -->
        <h3>Prescripciones / Recetas Médicas</h3>

        <!-- Formulario agregar nueva receta -->
        <form action="registroatencion.php?id_cita=<?php echo urlencode($id_cita); ?>" method="POST" style="margin-bottom: 15px; background-color: #f8f9fa; padding: 10px; border: 1px solid #ddd; max-width: 730px;">
            <input type="hidden" name="id_cita" value="<?php echo htmlspecialchars($id_cita); ?>">
            <strong>Agregar medicamento:</strong><br>
            <label>Medicamento:</label>
            <input type="text" name="medicamento" placeholder="Ej: Paracetamol 500mg" required style="width: 220px;">
            
            <label>Dosis:</label>
            <input type="text" name="dosis" placeholder="Ej: 1 cada 8 hrs" style="width: 180px;">
            
            <label>Días:</label>
            <input type="number" name="dias_tratamiento" min="1" placeholder="Ej: 5" style="width: 60px;">

            <button type="submit" name="accion" value="agregar_receta" style="margin-left: 10px;">Prescribir</button>
        </form>

        <table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 750px;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th>Medicamento</th>
                    <th>Dosis</th>
                    <th>Días</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($recetas_atencion) > 0): ?>
                    <?php foreach ($recetas_atencion as $rec): ?>
                        <tr>
                            <!-- Formulario inline para editar o eliminar -->
                            <form action="registroatencion.php?id_cita=<?php echo urlencode($id_cita); ?>" method="POST">
                                <input type="hidden" name="id_cita" value="<?php echo htmlspecialchars($id_cita); ?>">
                                <input type="hidden" name="id_receta" value="<?php echo htmlspecialchars($rec['id_receta']); ?>">
                                <td>
                                    <input type="text" name="medicamento" value="<?php echo htmlspecialchars($rec['medicamento']); ?>" required style="width: 95%;">
                                </td>
                                <td>
                                    <input type="text" name="dosis" value="<?php echo htmlspecialchars($rec['dosis'] ?? ''); ?>" style="width: 95%;">
                                </td>
                                <td>
                                    <input type="number" name="dias_tratamiento" value="<?php echo htmlspecialchars($rec['Dias_tratamiento'] ?? ''); ?>" min="1" style="width: 50px;">
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <button type="submit" name="accion" value="editar_receta" style="background-color: #ffc107; border: 1px solid #cca000; cursor: pointer;">Guardar</button>
                                    <button type="submit" name="accion" value="eliminar_receta" onclick="return confirm('¿Eliminar medicamento de la receta?');" style="color: red; cursor: pointer;">Quitar</button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">No se han emitido recetas para esta atención.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <br>
</body>
</html>