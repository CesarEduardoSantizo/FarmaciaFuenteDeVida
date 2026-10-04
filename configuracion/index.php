<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: /login/login.php'); exit(); }
require_once __DIR__ . '/../csrf.php';
validar_csrf();
require_once __DIR__ . '/../conexion.php';
$db = conectar();
$tab = $_GET['tab'] ?? 'perfil';
if (!in_array($tab, ['perfil', 'usuarios', 'sistema'], true)) { $tab = 'perfil'; }
if ($tab === 'usuarios' && ($_SESSION['usuario_rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    exit('No tiene permisos para administrar usuarios.');
}
$errores = [];
$mensaje = '';
$stmt = $db->prepare('SELECT nombre, correo, rol FROM usuarios WHERE id_usuario = ?');
$stmt->bind_param('i', $_SESSION['usuario_id']);
$stmt->execute();
$perfil = $stmt->get_result()->fetch_assoc() ?: ['nombre' => '', 'correo' => '', 'rol' => 'Vendedor'];
$nombre = trim($_POST['nombre'] ?? $perfil['nombre']);
$correo = trim($_POST['correo'] ?? ($perfil['correo'] ?? ''));
$rol = $perfil['rol'];
$valoresSistema = ['nombre_farmacia' => 'Farmacia Fuente de Vida', 'moneda' => 'GTQ', 'alertas_stock_minimo' => '5', 'alertas_vencimiento_dias' => '30', 'confirmar_eliminaciones' => '1'];
$configRows = $db->query('SELECT clave, valor FROM configuracion')->fetch_all(MYSQLI_ASSOC);
foreach ($configRows as $configRow) if (array_key_exists($configRow['clave'], $valoresSistema)) $valoresSistema[$configRow['clave']] = $configRow['valor'];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($tab === 'perfil') {
        if (strlen($nombre) < 3) $errores[] = 'El nombre debe tener al menos 3 caracteres.';
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = 'Ingrese un correo electrónico válido.';
        if (!$errores) {
            $stmt = $db->prepare('UPDATE usuarios SET nombre = ?, correo = ? WHERE id_usuario = ?');
            $stmt->bind_param('ssi', $nombre, $correo, $_SESSION['usuario_id']);
            $stmt->execute();
            $_SESSION['usuario_nombre'] = $nombre;
            $mensaje = 'Perfil actualizado correctamente.';
        }
    } elseif ($tab === 'usuarios') {
        $usuario = trim($_POST['usuario'] ?? '');
        $nombreUsuario = trim($_POST['nombre_usuario'] ?? '');
        $correoUsuario = trim($_POST['correo_usuario'] ?? '');
        $passwordUsuario = $_POST['password_usuario'] ?? '';
        $rolUsuario = trim($_POST['rol_usuario'] ?? 'Vendedor');
        if (strlen($nombreUsuario) < 3) $errores[] = 'El nombre debe tener al menos 3 caracteres.';
        if (strlen($usuario) < 3) $errores[] = 'El usuario debe tener al menos 3 caracteres.';
        if (!filter_var($correoUsuario, FILTER_VALIDATE_EMAIL)) $errores[] = 'Ingrese un correo válido para el usuario.';
        if (strlen($passwordUsuario) < 8) $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        if (!in_array($rolUsuario, ['Administrador', 'Vendedor'], true)) $errores[] = 'Seleccione un rol válido.';
        if (!$errores) {
            try {
                $hash = password_hash($passwordUsuario, PASSWORD_DEFAULT);
                $stmt = $db->prepare('INSERT INTO usuarios (nombre, usuario, password, correo, rol) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('sssss', $nombreUsuario, $usuario, $hash, $correoUsuario, $rolUsuario);
                $stmt->execute();
                header('Location: /configuracion/?tab=usuarios&creado=1');
                exit();
            } catch (mysqli_sql_exception $e) {
                $errores[] = $e->getCode() === 1062 ? 'Ese nombre de usuario ya existe.' : 'No se pudo crear el usuario.';
            }
        }
    } else {
        $valoresSistema['nombre_farmacia'] = trim($_POST['nombre_farmacia'] ?? '');
        $valoresSistema['moneda'] = trim($_POST['moneda'] ?? 'GTQ');
        $valoresSistema['alertas_stock_minimo'] = trim($_POST['alertas_stock_minimo'] ?? '5');
        $valoresSistema['alertas_vencimiento_dias'] = trim($_POST['alertas_vencimiento_dias'] ?? '30');
        $valoresSistema['confirmar_eliminaciones'] = isset($_POST['confirmar_eliminaciones']) ? '1' : '0';
        if (strlen($valoresSistema['nombre_farmacia']) < 3) $errores[] = 'El nombre de la farmacia debe tener al menos 3 caracteres.';
        if (!in_array($valoresSistema['moneda'], ['GTQ', 'USD'], true)) $errores[] = 'Seleccione una moneda válida.';
        if (filter_var($valoresSistema['alertas_stock_minimo'], FILTER_VALIDATE_INT) === false || (int)$valoresSistema['alertas_stock_minimo'] < 0) $errores[] = 'El mínimo de alertas debe ser un entero no negativo.';
        if (filter_var($valoresSistema['alertas_vencimiento_dias'], FILTER_VALIDATE_INT) === false || (int)$valoresSistema['alertas_vencimiento_dias'] < 1) $errores[] = 'Los días de vencimiento deben ser un entero mayor que cero.';
        if (!$errores) {
            $stmt = $db->prepare('INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
            foreach ($valoresSistema as $clave => $valor) {
                $stmt->bind_param('ss', $clave, $valor);
                $stmt->execute();
            }
            $mensaje = 'Configuración guardada correctamente.';
        }
    }
}
$usuarios = $db->query('SELECT nombre, correo, rol, estado FROM usuarios ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
if (isset($_GET['creado'])) $mensaje = 'Usuario creado correctamente.';
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Configuración | Farmacia Fuente de Vida</title><link rel="stylesheet" href="../menu.css"><link rel="stylesheet" href="configuracion.css"></head>
<body>
<?php include __DIR__ . '/../SideBar/menu.php'; ?>
<main class="main module-main"><header class="header"><div><h1>Configuración</h1><p>Gestión del sistema</p></div><div class="user"><div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'U', 0, 1)), ENT_QUOTES, 'UTF-8') ?></div><div><strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($_SESSION['usuario_rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></div></div></header>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php elseif ($mensaje): ?><div class="alert success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<section class="content-box config-box"><nav class="tabs"><a class="tab <?= $tab === 'perfil' ? 'active' : '' ?>" href="?tab=perfil">Perfil</a><a class="tab <?= $tab === 'usuarios' ? 'active' : '' ?>" href="?tab=usuarios">Usuarios y permisos</a><a class="tab <?= $tab === 'sistema' ? 'active' : '' ?>" href="?tab=sistema">Sistema</a></nav>
<?php if ($tab === 'perfil'): ?><form method="POST" class="config-form"><?= csrf_input() ?>
<div class="avatar-large"><?= htmlspecialchars(strtoupper(substr($nombre, 0, 1)), ENT_QUOTES, 'UTF-8') ?></div><div class="fields"><label>Nombre<input name="nombre" value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>" required></label><label>Correo<input type="email" name="correo" value="<?= htmlspecialchars($correo, ENT_QUOTES, 'UTF-8') ?>" required></label><label>Rol<input value="<?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?>" readonly></label></div><div class="form-actions"><button class="btn-primary" type="submit">Guardar cambios</button></div></form>
<?php elseif ($tab === 'usuarios'): ?><div class="tab-heading"><div><h2>Usuarios y permisos</h2><p>Usuarios registrados en el sistema.</p></div><button class="btn-primary" type="button" onclick="document.getElementById('user-form').classList.toggle('hidden')">+ Nuevo usuario</button></div><form id="user-form" class="user-form <?= $errores ? '' : 'hidden' ?>" method="POST"><?= csrf_input() ?>
<label>Nombre<input name="nombre_usuario" value="<?= htmlspecialchars($_POST['nombre_usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label><label>Usuario<input name="usuario" value="<?= htmlspecialchars($_POST['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label><label>Correo<input type="email" name="correo_usuario" value="<?= htmlspecialchars($_POST['correo_usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label><label>Contraseña<input type="password" name="password_usuario" minlength="8" required></label><label>Rol<select name="rol_usuario"><option <?= ($_POST['rol_usuario'] ?? 'Vendedor') === 'Vendedor' ? 'selected' : '' ?>>Vendedor</option><option <?= ($_POST['rol_usuario'] ?? '') === 'Administrador' ? 'selected' : '' ?>>Administrador</option></select></label><button class="btn-primary" type="submit">Crear usuario</button></form><div class="table-container"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th></tr></thead><tbody><?php if (!$usuarios): ?><tr><td colspan="4">No hay usuarios registrados.</td></tr><?php endif; ?><?php foreach ($usuarios as $usuario): ?><tr><td><strong><?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td><td><?= htmlspecialchars($usuario['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><span class="role"><?= htmlspecialchars($usuario['rol'], ENT_QUOTES, 'UTF-8') ?></span></td><td><span class="user-state <?= (int)$usuario['estado'] ? 'activo' : 'inactivo' ?>"><?= (int)$usuario['estado'] ? 'Activo' : 'Inactivo' ?></span></td></tr><?php endforeach; ?></tbody></table></div>
<?php else: ?><div class="tab-heading"><div><h2>Configuración del sistema</h2><p>Preferencias generales para la operación de la farmacia.</p></div></div><form class="system-form" method="POST"><?= csrf_input() ?>
<label>Nombre de la farmacia<input name="nombre_farmacia" value="<?= htmlspecialchars($valoresSistema['nombre_farmacia'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Moneda<select name="moneda"><option value="GTQ" <?= $valoresSistema['moneda'] === 'GTQ' ? 'selected' : '' ?>>Quetzal guatemalteco (Q)</option><option value="USD" <?= $valoresSistema['moneda'] === 'USD' ? 'selected' : '' ?>>Dólar estadounidense ($)</option></select></label><label>Umbral de stock bajo<input type="number" min="0" name="alertas_stock_minimo" value="<?= htmlspecialchars($valoresSistema['alertas_stock_minimo'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Días para alertar vencimientos<input type="number" min="1" name="alertas_vencimiento_dias" value="<?= htmlspecialchars($valoresSistema['alertas_vencimiento_dias'], ENT_QUOTES, 'UTF-8') ?>" required></label><label class="switch-row"><span>Confirmar eliminaciones<small>Solicitar confirmación antes de desactivar registros.</small></span><input type="checkbox" name="confirmar_eliminaciones" value="1" <?= $valoresSistema['confirmar_eliminaciones'] === '1' ? 'checked' : '' ?>></label><button class="btn-primary" type="submit">Guardar configuración</button></form><?php endif; ?></section></main><script src="/dev-reload.js"></script></body></html>
