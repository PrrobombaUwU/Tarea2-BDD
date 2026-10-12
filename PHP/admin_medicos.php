<?php
require __DIR__ . '/config.php';

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: login.php");
    exit();
}

require_once 'conexion.php';

$errores = [];
$editar = trim(filter_input(INPUT_GET, 'editar', FILTER_DEFAULT) ?? '');
$form = ['rut' => '', 'nombre_completo' => '', 'email' => ''];
$seleccion_esp = [];
$seleccion_centros = [];
$medico_actual = null;

if ($editar !== '') {
    $st = $pdo->prepare('SELECT rut_medico, nombre_completo, email_institucional AS email FROM medico WHERE rut_medico = ?');
    $st->execute([$editar]);
    $medico_actual = $st->fetch();
    if (!$medico_actual) {
        http_response_code(404);
        exit('Médico no encontrado.');
    }
    $form = [
        'rut' => $medico_actual['rut_medico'],
        'nombre_completo' => $medico_actual['nombre_completo'],
        'email' => $medico_actual['email']
    ];
    $st = $pdo->prepare('SELECT id_especialidad FROM medico_especialidad WHERE rut_medico = ?');
    $st->execute([$editar]);
    $seleccion_esp = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

    $st = $pdo->prepare('SELECT codigo_centro FROM medico_centro WHERE rut_medico = ?');
    $st->execute([$editar]);
    $seleccion_centros = $st->fetchAll(PDO::FETCH_COLUMN);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    $rut_post = trim($_POST['rut'] ?? '');

    if ($accion === 'eliminar') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM cita WHERE rut_medico = ?');
        $st->execute([$rut_post]);
        if (!$rut_post || (int)$st->fetchColumn() > 0) {
            $errores[] = 'No se puede eliminar al médico: no existe o tiene citas asociadas.';
        } else {
            $pdo->prepare('DELETE FROM medico WHERE rut_medico = ?')->execute([$rut_post]);
            flash('Médico eliminado correctamente.');
            header('Location: admin_medicos.php');
            exit;
        }
    }

    if ($accion === 'crear' || $accion === 'actualizar') {
        $form = [
            'rut' => normalizar_rut(trim($_POST['rut'] ?? '')),
            'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
            'email' => strtolower(trim($_POST['email'] ?? '')),
        ];
        $pass = $_POST['password'] ?? '';
        $ids_esp_raw = $_POST['especialidades'] ?? [];
        $ids_centros_raw = $_POST['centros'] ?? [];

        $seleccion_esp = is_array($ids_esp_raw) ? array_values(array_unique(array_filter(array_map('intval', $ids_esp_raw)))) : [];
        $seleccion_centros = is_array($ids_centros_raw) ? array_values(array_unique(array_filter(array_map('trim', $ids_centros_raw), fn($v) => $v !== ''))) : [];

        if ($form['rut'] === '' || $form['nombre_completo'] === '' || $form['email'] === '') {
            $errores[] = 'RUT, nombre completo y correo son obligatorios.';
        }
        if ($form['rut'] !== '' && !validar_rut($form['rut'])) {
            $errores[] = 'RUT inválido.';
        }
        if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Correo inválido.';
        }
        if (count($seleccion_esp) < 1 || count($seleccion_esp) > 3) {
            $errores[] = 'Cada médico debe tener entre 1 y 3 especialidades.';
        }
        if (count($seleccion_centros) < 1) {
            $errores[] = 'Cada médico debe atender en al menos un centro.';
        }

        if ($accion === 'crear') {
            if ($pass === '') {
                $errores[] = 'La contraseña es obligatoria.';
            } else {
                $errores = array_merge($errores, errores_password($pass));
            }
            $st = $pdo->prepare('SELECT COUNT(*) FROM medico WHERE rut_medico = ? OR email_institucional = ?');
            $st->execute([$form['rut'], $form['email']]);
            if ((int)$st->fetchColumn() > 0) {
                $errores[] = 'Ya existe un médico registrado con ese RUT o correo institucional.';
            }
        } elseif ($accion === 'actualizar') {
            if (!$medico_actual || $form['rut'] !== $medico_actual['rut_medico']) {
                $errores[] = 'El médico seleccionado no es válido.';
            }
            $st = $pdo->prepare('SELECT COUNT(*) FROM medico WHERE email_institucional = ? AND rut_medico != ?');
            $st->execute([$form['email'], $form['rut']]);
            if ((int)$st->fetchColumn() > 0) {
                $errores[] = 'Ya existe otro médico con ese correo institucional.';
            }
        }

        if (!$errores) {
            $pdo->beginTransaction();
            try {
                if ($accion === 'crear') {
                    $pdo->prepare('INSERT INTO medico (rut_medico, nombre_completo, email_institucional, contraseña) VALUES (?, ?, ?, ?)')
                        ->execute([$form['rut'], $form['nombre_completo'], $form['email'], $pass]);
                } else {
                    $pdo->prepare('UPDATE medico SET nombre_completo = ?, email_institucional = ? WHERE rut_medico = ?')
                        ->execute([$form['nombre_completo'], $form['email'], $form['rut']]);

                    if ($pass !== '') {
                        $password_errors = errores_password($pass);
                        if ($password_errors) {
                            throw new DomainException(implode(' ', $password_errors));
                        }
                        $pdo->prepare('UPDATE medico SET contraseña = ? WHERE rut_medico = ?')
                            ->execute([$pass, $form['rut']]);
                    }

                    $st = $pdo->prepare('SELECT id_especialidad FROM medico_especialidad WHERE rut_medico = ?');
                    $st->execute([$form['rut']]);
                    $esp_previas = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

                    $st = $pdo->prepare('SELECT codigo_centro FROM medico_centro WHERE rut_medico = ?');
                    $st->execute([$form['rut']]);
                    $centros_previos = $st->fetchAll(PDO::FETCH_COLUMN);

                    $quita_esp = array_diff($esp_previas, $seleccion_esp);
                    $quita_centros = array_diff($centros_previos, $seleccion_centros);

                    if ($quita_esp) {
                        $in_esp = implode(',', array_fill(0, count($quita_esp), '?'));
                        $st = $pdo->prepare("SELECT COUNT(*) FROM cita WHERE rut_medico = ? AND id_especialidad IN ($in_esp) AND fecha_hora > NOW() AND id_estado IN (1, 2)");
                        $st->execute(array_merge([$form['rut']], array_values($quita_esp)));
                        if ((int)$st->fetchColumn() > 0) {
                            throw new DomainException('No se pueden quitar especialidades mientras el médico tenga citas futuras asignadas.');
                        }
                    }

                    foreach ($quita_centros as $cod_c) {
                        $st = $pdo->prepare("SELECT COUNT(*) FROM cita WHERE rut_medico = ? AND codigo_centro = ? AND fecha_hora > NOW() AND id_estado IN (1, 2)");
                        $st->execute([$form['rut'], $cod_c]);
                        if ((int)$st->fetchColumn() > 0) {
                            throw new DomainException('No se puede quitar un centro asociado a citas futuras.');
                        }
                    }

                    $pdo->prepare('DELETE FROM medico_especialidad WHERE rut_medico = ?')->execute([$form['rut']]);
                    $pdo->prepare('DELETE FROM medico_centro WHERE rut_medico = ?')->execute([$form['rut']]);
                }

                $ins_esp = $pdo->prepare('INSERT INTO medico_especialidad (rut_medico, id_especialidad) VALUES (?, ?)');
                foreach ($seleccion_esp as $especialidad_id) {
                    $ins_esp->execute([$form['rut'], $especialidad_id]);
                }

                $ins_centro = $pdo->prepare('INSERT INTO medico_centro (rut_medico, codigo_centro) VALUES (?, ?)');
                foreach ($seleccion_centros as $codigo_centro) {
                    $ins_centro->execute([$form['rut'], $codigo_centro]);
                }

                $pdo->commit();
                flash($accion === 'crear' ? 'Médico creado correctamente.' : 'Médico actualizado correctamente.');
                header('Location: admin_medicos.php');
                exit;
            } catch (DomainException $ex) {
                $pdo->rollBack();
                $errores[] = $ex->getMessage();
            } catch (PDOException $ex) {
                $pdo->rollBack();
                error_log('Error al guardar médico: ' . $ex->getMessage());
                $errores[] = 'No se pudo guardar el médico. Verifique los datos ingresados.';
            }
        }
    }
}

$especialidades = $pdo->query('SELECT id_especialidad, nombre FROM especialidad ORDER BY nombre')->fetchAll();
$centros = $pdo->query('SELECT codigo_centro, nombre, comuna, region FROM centro_medico ORDER BY nombre')->fetchAll();
$medicos = $pdo->query("SELECT m.rut_medico, m.nombre_completo, m.email_institucional,
                        GROUP_CONCAT(DISTINCT e.nombre ORDER BY e.nombre SEPARATOR ', ') AS especialidades,
                        GROUP_CONCAT(DISTINCT c.nombre ORDER BY c.nombre SEPARATOR ', ') AS centros
                        FROM medico m
                        LEFT JOIN medico_especialidad me ON me.rut_medico = m.rut_medico
                        LEFT JOIN especialidad e ON e.id_especialidad = me.id_especialidad
                        LEFT JOIN medico_centro mc ON mc.rut_medico = m.rut_medico
                        LEFT JOIN centro_medico c ON c.codigo_centro = mc.codigo_centro
                        GROUP BY m.rut_medico, m.nombre_completo, m.email_institucional
                        ORDER BY m.nombre_completo")->fetchAll();

foreach ($errores as $er) echo '<div class="alert err">' . e($er) . '</div>';
?>
<h1>Administrar médicos</h1>
<p><a href="paginaadmin.php">Volver a administración</a> · <a href="admin_centros.php">Administrar centros</a></p>
<section class="card">
  <h2><?= $medico_actual ? 'Editar médico' : 'Crear médico' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="<?= $medico_actual ? 'actualizar' : 'crear' ?>">
    <div class="field">
      <label for="rut">RUT</label>
      <input id="rut" name="rut" value="<?= e($form['rut']) ?>" <?= $medico_actual ? 'readonly' : 'required' ?>>
    </div>
    <div class="field">
      <label for="nombre_completo">Nombre Completo</label>
      <input id="nombre_completo" name="nombre_completo" value="<?= e($form['nombre_completo']) ?>" required>
    </div>
    <div class="field">
      <label for="email">Correo Institucional</label>
      <input id="email" name="email" type="email" value="<?= e($form['email']) ?>" required>
    </div>
    <div class="field">
      <label for="password"><?= $medico_actual ? 'Nueva contraseña (opcional)' : 'Contraseña inicial' ?></label>
      <input id="password" name="password" type="password" autocomplete="new-password" <?= $medico_actual ? '' : 'required' ?>>
    </div>
    <fieldset class="field">
      <legend>Especialidades (selecciona entre 1 y 3)</legend>
      <?php foreach ($especialidades as $esp): ?>
        <label>
          <input style="width:auto" type="checkbox" name="especialidades[]" value="<?= (int)$esp['id_especialidad'] ?>" <?= in_array((int)$esp['id_especialidad'], $seleccion_esp, true) ? 'checked' : '' ?>>
          <?= e($esp['nombre']) ?>
        </label>
      <?php endforeach; ?>
    </fieldset>
    <fieldset class="field">
      <legend>Centros donde atiende (al menos 1)</legend>
      <?php foreach ($centros as $centro): ?>
        <label>
          <input style="width:auto" type="checkbox" name="centros[]" value="<?= e($centro['codigo_centro']) ?>" <?= in_array($centro['codigo_centro'], $seleccion_centros, true) ? 'checked' : '' ?>>
          <?= e($centro['nombre'] . ' (' . $centro['comuna'] . ')') ?>
        </label>
      <?php endforeach; ?>
      <?php if (!$centros): ?><p>Primero <a href="admin_centros.php">crea un centro médico</a>.</p><?php endif; ?>
    </fieldset>
    <button class="submit-button" type="submit"><?= $medico_actual ? 'Guardar cambios' : 'Crear médico' ?></button>
  </form>
</section>
<section class="card">
  <h2>Médicos registrados</h2>
  <table>
    <tr><th>Médico</th><th>RUT</th><th>Correo</th><th>Especialidades</th><th>Centros</th><th>Acciones</th></tr>
    <?php foreach ($medicos as $m): ?>
      <tr>
        <td><?= e($m['nombre_completo']) ?></td>
        <td><?= e($m['rut_medico']) ?></td>
        <td><?= e($m['email_institucional']) ?></td>
        <td><?= e($m['especialidades'] ?? '') ?></td>
        <td><?= e($m['centros'] ?? '') ?></td>
        <td>
          <a href="admin_medicos.php?editar=<?= urlencode($m['rut_medico']) ?>">Editar</a>
          <form method="post" onsubmit="return confirm('¿Eliminar este médico?');" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="rut" value="<?= e($m['rut_medico']) ?>">
            <button type="submit">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p><small>No se permite eliminar médicos con citas asociadas para preservar el historial clínico.</small></p>
</section>
<?php pie();