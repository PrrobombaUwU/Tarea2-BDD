<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

const VALIDAR_DV = true;

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

function db(): PDO {
    global $pdo;
    return $pdo;
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Solicitud inválida (CSRF).');
    }
}

function usuario_actual(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $st = db()->prepare('SELECT id, rut, nombre, apellido, email, telefono, rol FROM usuarios WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    if (!$u) { session_destroy(); return null; }
    return $u;
}

function ruta_inicio(string $rol): string {
    return ['paciente' => 'paciente.php', 'medico' => 'medico.php', 'admin' => 'admin.php'][$rol] ?? 'index.php';
}

function requerir_rol(string ...$roles): array {
    $u = usuario_actual();
    if (!$u) { header('Location: index.php'); exit; }
    if ($roles && !in_array($u['rol'], $roles, true)) {
        http_response_code(403);
        exit('Acceso denegado.');
    }
    return $u;
}

function flash(?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

// valida
function normalizar_rut(string $rut): string {
    return strtoupper(str_replace('.', '', trim($rut)));
}

function validar_rut(string $rut): bool {
    if (!preg_match('/^(\d{7,8})-([\dK])$/', $rut, $m)) return false; return true;
}

function errores_password(string $p): array {
    $e = [];
    if (!preg_match('/^\d{4}[a-z]{4}$/', $p)) {
        $e[] = 'La contraseña debe tener exactamente 4 números seguidos de 4 letras minúsculas (ej: 0000aaaa).';
    }
    return $e;
}

function telefono_valido(string $t): bool {
    return $t === '' || preg_match('/^\+?\d{8,15}$/', $t) === 1;
}

function existe_duplicado(string $rut, string $email, int $excluir_id = 0): ?string {
    $st = db()->prepare('SELECT rut, email FROM usuarios WHERE (rut = ? OR email = ?) AND id <> ?');
    $st->execute([$rut, $email, $excluir_id]);
    foreach ($st->fetchAll() as $r) {
        if ($r['rut'] === $rut)     return 'Ya existe un usuario con ese RUT.';
        if ($r['email'] === $email) return 'Ya existe un usuario con ese correo.';
    }
    return null;
}

function cabecera(string $titulo, ?array $u = null): void { ?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> · SaludUSM</title>
<link rel="stylesheet" href="style.css">
</head><body>
<?php if ($u): ?>
<nav class="topbar">
  <strong>SaludUSM</strong>
  <span><?= e($u['nombre'] . ' ' . $u['apellido']) ?> (<?= e($u['rol']) ?>)</span>
  <span><a href="<?= e(ruta_inicio($u['rol'])) ?>">Inicio</a> · <a href="perfil.php">Mi perfil</a> · <a href="logout.php">Salir</a></span>
</nav>
<?php endif; ?>
<main class="page">
<?php if ($m = flash()): ?><div class="alert ok"><?= e($m) ?></div><?php endif;
}
function pie(): void { echo "</main></body></html>"; }
