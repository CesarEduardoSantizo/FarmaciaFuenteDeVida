<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: /login/login.php'); exit(); }
require_once __DIR__ . '/../csrf.php';
validar_csrf();
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../permisos.php';
$db = conectar();
exigir_permiso_modulo($db, 'configuracion');
$rolesSistema = roles_disponibles($db);
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
        if (($_POST['accion'] ?? '') === 'cambiar_contrasena') {
            $usuarioObjetivo = filter_var($_POST['id_usuario'] ?? null, FILTER_VALIDATE_INT);
            $nuevaContrasena = $_POST['nueva_contrasena'] ?? '';
            $confirmarContrasena = $_POST['confirmar_contrasena'] ?? '';
            if ($usuarioObjetivo === false || $usuarioObjetivo < 1) $errores[] = 'Seleccione un usuario válido.';
            if (strlen($nuevaContrasena) < 8) $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
            if ($nuevaContrasena !== $confirmarContrasena) $errores[] = 'Las contraseñas no coinciden.';
            if (!$errores) {
                try {
                    $hash = password_hash($nuevaContrasena, PASSWORD_DEFAULT);
                    $stmt = $db->prepare('UPDATE usuarios SET password = ? WHERE id_usuario = ?');
                    $stmt->bind_param('si', $hash, $usuarioObjetivo);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) {
                        $stmt = $db->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ?');
                        $stmt->bind_param('i', $usuarioObjetivo);
                        $stmt->execute();
                        if (!$stmt->get_result()->fetch_assoc()) $errores[] = 'No se encontró el usuario seleccionado.';
                    }
                    if (!$errores) {
                        header('Location: /configuracion/?tab=usuarios&contrasena_actualizada=1');
                        exit();
                    }
                } catch (mysqli_sql_exception $e) {
                    error_log('No se pudo cambiar la contraseña de usuario: ' . $e->getMessage());
                    $errores[] = 'No se pudo cambiar la contraseña. Inténtelo nuevamente.';
                }
            }
        } else {
            $usuario = trim($_POST['usuario'] ?? '');
            $nombreUsuario = trim($_POST['nombre_usuario'] ?? '');
            $correoUsuario = trim($_POST['correo_usuario'] ?? '');
            $passwordUsuario = $_POST['password_usuario'] ?? '';
            $rolUsuario = trim($_POST['rol_usuario'] ?? (in_array('Vendedor', $rolesSistema, true) ? 'Vendedor' : 'Administrador'));
            if (strlen($nombreUsuario) < 3) $errores[] = 'El nombre debe tener al menos 3 caracteres.';
            if (strlen($usuario) < 3) $errores[] = 'El usuario debe tener al menos 3 caracteres.';
            if (!filter_var($correoUsuario, FILTER_VALIDATE_EMAIL)) $errores[] = 'Ingrese un correo válido para el usuario.';
            if (strlen($passwordUsuario) < 8) $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
            if (!in_array($rolUsuario, $rolesSistema, true)) $errores[] = 'Seleccione un rol válido.';
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
$usuarios = $db->query('SELECT id_usuario, nombre, correo, rol, estado FROM usuarios ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
if (isset($_GET['creado'])) $mensaje = 'Usuario creado correctamente.';
if (isset($_GET['contrasena_actualizada'])) $mensaje = 'La contraseña del usuario se cambió correctamente.';
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
<?php elseif ($tab === 'usuarios'): ?>
<div class="tab-heading"><div><h2>Usuarios y permisos</h2><p>Administra las cuentas y restablece sus contraseñas.</p></div><button class="btn-primary" type="button" onclick="document.getElementById('user-form').classList.toggle('hidden')">+ Nuevo usuario</button></div>
<form id="user-form" class="user-form <?= $errores && ($_POST['accion'] ?? '') !== 'cambiar_contrasena' ? '' : 'hidden' ?>" method="POST"><?= csrf_input() ?>
<label>Nombre<input name="nombre_usuario" value="<?= htmlspecialchars($_POST['nombre_usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label><label>Usuario<input name="usuario" value="<?= htmlspecialchars($_POST['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label><label>Correo<input type="email" name="correo_usuario" value="<?= htmlspecialchars($_POST['correo_usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label><label>Contraseña<input type="password" name="password_usuario" minlength="8" required></label><label>Rol<select name="rol_usuario"><?php foreach ($rolesSistema as $rolDisponible): ?><option value="<?= htmlspecialchars($rolDisponible, ENT_QUOTES, 'UTF-8') ?>" <?= ($_POST['rol_usuario'] ?? (in_array('Vendedor', $rolesSistema, true) ? 'Vendedor' : 'Administrador')) === $rolDisponible ? 'selected' : '' ?>><?= htmlspecialchars($rolDisponible, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><button class="btn-primary" type="submit">Crear usuario</button></form>
<div class="table-container"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody><?php if (!$usuarios): ?><tr><td colspan="5">No hay usuarios registrados.</td></tr><?php endif; ?><?php foreach ($usuarios as $usuario): ?><tr><td><strong><?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td><td><?= htmlspecialchars($usuario['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><span class="role"><?= htmlspecialchars($usuario['rol'], ENT_QUOTES, 'UTF-8') ?></span></td><td><span class="user-state <?= (int)$usuario['estado'] ? 'activo' : 'inactivo' ?>"><?= (int)$usuario['estado'] ? 'Activo' : 'Inactivo' ?></span></td><td><button class="edit password-change-trigger" type="button" data-user-id="<?= (int)$usuario['id_usuario'] ?>" data-user-name="<?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?>">Cambiar contraseña</button></td></tr><?php endforeach; ?></tbody></table></div>
<?php else: ?><div class="tab-heading"><div><h2>Configuración del sistema</h2><p>Preferencias generales para la operación de la farmacia.</p></div></div><form class="system-form" method="POST"><?= csrf_input() ?>
<label>Nombre de la farmacia<input name="nombre_farmacia" value="<?= htmlspecialchars($valoresSistema['nombre_farmacia'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Moneda<select name="moneda"><option value="GTQ" <?= $valoresSistema['moneda'] === 'GTQ' ? 'selected' : '' ?>>Quetzal guatemalteco (Q)</option><option value="USD" <?= $valoresSistema['moneda'] === 'USD' ? 'selected' : '' ?>>Dólar estadounidense ($)</option></select></label><label>Umbral de stock bajo<input type="number" min="0" name="alertas_stock_minimo" value="<?= htmlspecialchars($valoresSistema['alertas_stock_minimo'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Días para alertar vencimientos<input type="number" min="1" name="alertas_vencimiento_dias" value="<?= htmlspecialchars($valoresSistema['alertas_vencimiento_dias'], ENT_QUOTES, 'UTF-8') ?>" required></label><label class="switch-row"><span>Confirmar eliminaciones<small>Solicitar confirmación antes de desactivar registros.</small></span><input type="checkbox" name="confirmar_eliminaciones" value="1" <?= $valoresSistema['confirmar_eliminaciones'] === '1' ? 'checked' : '' ?>></label><button class="btn-primary" type="submit">Guardar configuración</button></form><?php endif; ?></section>
<dialog class="action-confirm-dialog password-change-dialog" id="passwordChangeDialog" aria-labelledby="passwordChangeTitle">
    <div class="action-confirm-icon" aria-hidden="true">•</div>
    <h2 id="passwordChangeTitle">Cambiar contraseña</h2>
    <p class="password-change-user" id="passwordChangeUser"></p>
    <form method="POST" id="passwordChangeForm" class="password-change-form">
        <?= csrf_input() ?>
        <input type="hidden" name="accion" value="cambiar_contrasena">
        <input type="hidden" name="id_usuario" id="passwordChangeUserId">
        <label>Nueva contraseña<input type="password" name="nueva_contrasena" minlength="8" autocomplete="new-password" required></label>
        <label>Confirmar contraseña<input type="password" name="confirmar_contrasena" minlength="8" autocomplete="new-password" required></label>
        <small>Usa al menos 8 caracteres. La contraseña actual no se mostrará.</small>
        <div class="action-confirm-actions"><button class="btn-secondary" id="passwordChangeCancel" type="button">Cancelar</button><button class="action-confirm-submit" type="submit">Guardar contraseña</button></div>
    </form>
</dialog>
<script>
    const passwordChangeDialog = document.getElementById('passwordChangeDialog');
    const passwordChangeForm = document.getElementById('passwordChangeForm');
    document.querySelectorAll('.password-change-trigger').forEach((button) => {
        button.addEventListener('click', () => {
            passwordChangeForm.reset();
            document.getElementById('passwordChangeUserId').value = button.dataset.userId;
            document.getElementById('passwordChangeUser').textContent = `Cuenta: ${button.dataset.userName}`;
            passwordChangeDialog.showModal();
            passwordChangeForm.querySelector('input[name="nueva_contrasena"]').focus();
        });
    });
    document.getElementById('passwordChangeCancel').addEventListener('click', () => passwordChangeDialog.close());
    passwordChangeDialog.addEventListener('click', (event) => {
        if (event.target === passwordChangeDialog) passwordChangeDialog.close();
    });
    passwordChangeForm.addEventListener('submit', (event) => {
        const password = passwordChangeForm.querySelector('input[name="nueva_contrasena"]');
        const confirmation = passwordChangeForm.querySelector('input[name="confirmar_contrasena"]');
        confirmation.setCustomValidity(password.value === confirmation.value ? '' : 'Las contraseñas no coinciden.');
        if (!passwordChangeForm.reportValidity()) event.preventDefault();
    });
</script>
<script src="/dev-reload.js"></script></body></html>
