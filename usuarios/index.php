<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: /login/login.php'); exit(); }
if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') { http_response_code(403); exit('No tiene permisos para administrar usuarios.'); }
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../config_helpers.php';
$db = conectar();
$configuracion = cargar_configuracion($db);
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$errores = [];
$mensaje = '';
$usuarioId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$usuario = ['nombre' => '', 'usuario' => '', 'correo' => '', 'rol' => 'Vendedor'];
if ($usuarioId) {
    $stmt = $db->prepare('SELECT nombre, usuario, correo, rol, estado FROM usuarios WHERE id_usuario = ?');
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    if (!$existente) { http_response_code(404); exit('Usuario no encontrado.'); }
    $usuario = $existente;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit('Solicitud no válida. Recargue la página e inténtelo nuevamente.');
    }
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'cambiar_estado') {
        $objetivoId = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
        $stmt = $db->prepare('SELECT rol, estado FROM usuarios WHERE id_usuario = ?');
        $stmt->bind_param('i', $objetivoId);
        $stmt->execute();
        $objetivo = $stmt->get_result()->fetch_assoc();
        if (!$objetivo) {
            $errores[] = 'No se encontró el usuario seleccionado.';
        } elseif ($objetivoId === (int)$_SESSION['usuario_id'] && (int)$objetivo['estado'] === 1) {
            $errores[] = 'No puede desactivar su propia cuenta.';
        } elseif ((int)$objetivo['estado'] === 1 && $objetivo['rol'] === 'Administrador') {
            $adminsActivos = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE estado = 1 AND rol = 'Administrador'")->fetch_row()[0];
            if ($adminsActivos <= 1) $errores[] = 'Debe quedar al menos un administrador activo.';
        }
        if (!$errores) {
            $stmt = $db->prepare('UPDATE usuarios SET estado = 1 - estado WHERE id_usuario = ?');
            $stmt->bind_param('i', $objetivoId);
            $stmt->execute();
            header('Location: /usuarios/?actualizado=1');
            exit();
        }
    } else {
        $usuarioId = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT) ?: 0;
        foreach (['nombre', 'usuario', 'correo', 'rol'] as $campo) $usuario[$campo] = trim($_POST[$campo] ?? '');
        $password = $_POST['password'] ?? '';
        if (strlen($usuario['nombre']) < 3) $errores[] = 'El nombre debe tener al menos 3 caracteres.';
        if (strlen($usuario['usuario']) < 3) $errores[] = 'El nombre de usuario debe tener al menos 3 caracteres.';
        if (!filter_var($usuario['correo'], FILTER_VALIDATE_EMAIL)) $errores[] = 'Ingrese un correo válido.';
        if (!in_array($usuario['rol'], ['Administrador', 'Vendedor'], true)) $errores[] = 'Seleccione un rol válido.';
        if ((!$usuarioId && strlen($password) < 8) || ($password !== '' && strlen($password) < 8)) $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($usuarioId === (int)$_SESSION['usuario_id'] && $usuario['rol'] !== 'Administrador') $errores[] = 'No puede cambiar su propio rol de administrador.';
        if (!$errores) {
            try {
                if ($usuarioId) {
                    if ($password !== '') {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare('UPDATE usuarios SET nombre = ?, usuario = ?, correo = ?, rol = ?, password = ? WHERE id_usuario = ?');
                        $stmt->bind_param('sssssi', $usuario['nombre'], $usuario['usuario'], $usuario['correo'], $usuario['rol'], $hash, $usuarioId);
                    } else {
                        $stmt = $db->prepare('UPDATE usuarios SET nombre = ?, usuario = ?, correo = ?, rol = ? WHERE id_usuario = ?');
                        $stmt->bind_param('ssssi', $usuario['nombre'], $usuario['usuario'], $usuario['correo'], $usuario['rol'], $usuarioId);
                    }
                    $stmt->execute();
                    if ($usuarioId === (int)$_SESSION['usuario_id']) {
                        $_SESSION['usuario'] = $usuario['usuario'];
                        $_SESSION['usuario_nombre'] = $usuario['nombre'];
                    }
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare('INSERT INTO usuarios (nombre, usuario, correo, rol, password) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('sssss', $usuario['nombre'], $usuario['usuario'], $usuario['correo'], $usuario['rol'], $hash);
                    $stmt->execute();
                }
                header('Location: /usuarios/?guardado=1');
                exit();
            } catch (mysqli_sql_exception $e) {
                $errores[] = $e->getCode() === 1062 ? 'Ese nombre de usuario ya está registrado.' : 'No se pudo guardar el usuario.';
            }
        }
    }
}
$usuarios = $db->query('SELECT id_usuario, nombre, usuario, correo, rol, estado FROM usuarios ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
if (isset($_GET['guardado'])) $mensaje = 'Usuario guardado correctamente.';
if (isset($_GET['actualizado'])) $mensaje = 'Estado del usuario actualizado.';
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Usuarios | Farmacia Fuente de Vida</title><link rel="stylesheet" href="../menu.css"><link rel="stylesheet" href="../configuracion/configuracion.css"></head><body>
<?php include __DIR__ . '/../SideBar/menu.php'; ?>
<main class="main module-main"><header class="header"><div><h1>Usuarios</h1><p>Cuentas y acceso al sistema</p></div><div class="user"><div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['usuario_nombre'] ?? 'A', 0, 1)), ENT_QUOTES, 'UTF-8') ?></div><div><strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'], ENT_QUOTES, 'UTF-8') ?></strong><small>Administrador</small></div></div></header>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php elseif ($mensaje): ?><div class="alert success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<section class="content-box"><div class="section-header"><div><h2><?= $usuarioId ? 'Editar usuario' : 'Nuevo usuario' ?></h2><p><?= $usuarioId ? 'Deje la contraseña vacía para conservar la actual.' : 'La contraseña se guarda protegida.' ?></p></div></div><form method="POST" class="user-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id_usuario" value="<?= $usuarioId ?>"><label>Nombre<input name="nombre" value="<?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Usuario<input name="usuario" value="<?= htmlspecialchars($usuario['usuario'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Correo<input type="email" name="correo" value="<?= htmlspecialchars($usuario['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label><label>Contraseña<input type="password" name="password" minlength="8" <?= $usuarioId ? '' : 'required' ?>></label><label>Rol<select name="rol"><option <?= $usuario['rol'] === 'Vendedor' ? 'selected' : '' ?>>Vendedor</option><option <?= $usuario['rol'] === 'Administrador' ? 'selected' : '' ?>>Administrador</option></select></label><button class="btn-primary" type="submit"><?= $usuarioId ? 'Guardar cambios' : 'Crear usuario' ?></button><?php if ($usuarioId): ?><a class="btn-secondary" href="/usuarios/">Cancelar</a><?php endif; ?></form></section>
<section class="content-box"><div class="section-header"><div><h2>Usuarios registrados</h2><p><?= count($usuarios) ?> cuenta(s)</p></div></div><div class="table-container"><table><thead><tr><th>Nombre</th><th>Usuario</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody><?php if (!$usuarios): ?><tr><td colspan="6">No hay usuarios registrados.</td></tr><?php endif; ?><?php foreach ($usuarios as $fila): ?><tr><td><strong><?= htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td><td><?= htmlspecialchars($fila['usuario'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fila['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fila['rol'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int)$fila['estado'] ? 'Activo' : 'Inactivo' ?></td><td><a class="edit" href="/usuarios/?id=<?= (int)$fila['id_usuario'] ?>">Editar</a><?php if ((int)$fila['id_usuario'] !== (int)$_SESSION['usuario_id']): ?><form method="POST" onsubmit="<?= confirmar_eliminaciones($configuracion) ? "return confirm('¿Cambiar el estado de esta cuenta?');" : '' ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="id_usuario" value="<?= (int)$fila['id_usuario'] ?>"><button class="delete" type="submit"><?= (int)$fila['estado'] ? 'Desactivar' : 'Activar' ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section></main><script src="/dev-reload.js"></script></body></html>
