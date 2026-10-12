<?php
require __DIR__ . '/config.php';

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: login.php");
    exit();
}

require_once 'conexion.php';

$errores = [];
$editar = trim(filter_input(INPUT_GET, 'editar', FILTER_DEFAULT) ?? '');
$centro_actual = null;
$form = ['codigo_centro' => '', 'nombre' => '', 'comuna' => '', 'region' => ''];

if ($editar !== '') {
    $st = $pdo->prepare('SELECT codigo_centro, nombre, comuna, region FROM centro_medico WHERE codigo_centro = ?');
    $st->execute([$editar]);
    $centro_actual = $st->fetch();
    if (!$centro_actual) {
        http_response_code(404);
        exit('Centro médico no encontrado.');
    }
    $form = $centro_actual;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    $codigo_post = trim($_POST['codigo_centro'] ?? '');

    if ($accion === 'eliminar') {
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM cita WHERE codigo_centro = ? AND fecha_hora > NOW() AND id_estado IN (1, 2)");
            $st->execute([$codigo_post]);
            if (!$codigo_post || (int)$st->fetchColumn() > 0) {
                throw new DomainException('No se puede eliminar el centro: tiene citas futuras asociadas.');
            }

            $st = $pdo->prepare('SELECT COUNT(*) FROM medico_centro mc WHERE mc.codigo_centro = ? AND
                                 (SELECT COUNT(*) FROM medico_centro mc2 WHERE mc2.rut_medico = mc.rut_medico) <= 1');
            $st->execute([$codigo_post]);
            if ((int)$st->fetchColumn() > 0) {
                throw new DomainException('No se puede eliminar el centro porque dejaría a uno o más médicos sin centro asignado.');
            }

            $pdo->prepare('DELETE FROM centro_medico WHERE codigo_centro = ?')->execute([$codigo_post]);
            $pdo->commit();
            flash('Centro médico eliminado correctamente.');
            header('Location: admin_centros.php');
            exit;
        } catch (DomainException $ex) {
            $pdo->rollBack();
            $errores[] = $ex->getMessage();
        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log('Error al eliminar centro médico: ' . $ex->getMessage());
            $errores[] = 'No se pudo eliminar el centro médico.';
        }
    }

    if ($accion === 'crear' || $accion === 'actualizar') {
        $form = [
            'codigo_centro' => trim($_POST['codigo_centro'] ?? ''),
            'nombre'        => trim($_POST['nombre'] ?? ''),
            'comuna'        => trim($_POST['comuna'] ?? ''),
            'region'        => trim($_POST['region'] ?? ''),
        ];

        if (in_array('', $form, true)) {
            $errores[] = 'Código, nombre, comuna y región son obligatorios.';
        }

        if ($accion === 'actualizar' && (!$centro_actual || $codigo_post !== $centro_actual['codigo_centro'])) {
            $errores[] = 'El centro seleccionado no es válido.';
        }

        if (!$errores) {
            try {
                if ($accion === 'crear') {
                    $pdo->prepare('INSERT INTO centro_medico (codigo_centro, nombre, comuna, region) VALUES (?, ?, ?, ?)')
                        ->execute([$form['codigo_centro'], $form['nombre'], $form['comuna'], $form['region']]);
                } else {
                    $pdo->prepare('UPDATE centro_medico SET nombre = ?, comuna = ?, region = ? WHERE codigo_centro = ?')
                        ->execute([$form['nombre'], $form['comuna'], $form['region'], $form['codigo_centro']]);
                }
                flash($accion === 'crear' ? 'Centro médico creado correctamente.' : 'Centro médico actualizado correctamente.');
                header('Location: admin_centros.php');
                exit;
            } catch (PDOException $ex) {
                error_log('Error al guardar centro médico: ' . $ex->getMessage());
                $errores[] = $ex->getCode() === '23000' ? 'El código ya está registrado.' : 'No se pudo guardar el centro médico.';
            }
        }
    }
}

$centros = $pdo->query('SELECT c.codigo_centro, c.nombre, c.comuna, c.region,
                               COUNT(DISTINCT mc.rut_medico) AS medicos
                        FROM centro_medico c 
                        LEFT JOIN medico_centro mc ON mc.codigo_centro = c.codigo_centro
                        GROUP BY c.codigo_centro, c.nombre, c.comuna, c.region 
                        ORDER BY c.nombre')->fetchAll();

foreach ($errores as $er) echo '<div class="alert err">' . e($er) . '</div>';
?>
<h1>Administrar centros médicos</h1>
<p><a href="paginaadmin.php">Volver a administración</a> · <a href="admin_medicos.php">Administrar médicos</a></p>
<section class="card">
  <h2><?= $centro_actual ? 'Editar centro' : 'Crear centro' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="<?= $centro_actual ? 'actualizar' : 'crear' ?>">
    <div class="field">
      <label for="codigo_centro">Código</label>
      <input id="codigo_centro" name="codigo_centro" value="<?= e($form['codigo_centro']) ?>" <?= $centro_actual ? 'readonly' : 'required' ?>>
    </div>
    <div class="field">
      <label for="nombre">Nombre</label>
      <input id="nombre" name="nombre" value="<?= e($form['nombre']) ?>" required>
    </div>
    <div class="field">
      <label for="comuna">Comuna</label>
      <input id="comuna" name="comuna" value="<?= e($form['comuna']) ?>" required>
    </div>
    <div class="field">
      <label for="region">Región</label>
      <input id="region" name="region" value="<?= e($form['region']) ?>" required>
    </div>
    <button class="submit-button" type="submit"><?= $centro_actual ? 'Guardar cambios' : 'Crear centro' ?></button>
  </form>
</section>
<section class="card">
  <h2>Centros registrados</h2>
  <table>
    <tr><th>Código</th><th>Nombre</th><th>Comuna</th><th>Región</th><th>Médicos</th><th>Acciones</th></tr>
    <?php foreach ($centros as $centro): ?>
      <tr>
        <td><?= e($centro['codigo_centro']) ?></td>
        <td><?= e($centro['nombre']) ?></td>
        <td><?= e($centro['comuna']) ?></td>
        <td><?= e($centro['region']) ?></td>
        <td><?= (int)$centro['medicos'] ?></td>
        <td>
          <a href="admin_centros.php?editar=<?= urlencode($centro['codigo_centro']) ?>">Editar</a>
          <form method="post" onsubmit="return confirm('¿Eliminar este centro?');" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="codigo_centro" value="<?= e($centro['codigo_centro']) ?>">
            <button type="submit">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php pie();