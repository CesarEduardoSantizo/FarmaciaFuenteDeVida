<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: /login/login.php'); exit(); }
if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') { http_response_code(403); exit('No tiene permisos para administrar usuarios.'); }
require_once __DIR__ . '/../permisos.php';
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../config_helpers.php';
$db = conectar();
exigir_permiso_modulo($db, 'usuarios');
$configuracion = cargar_configuracion($db);
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$errores = [];
$mensaje = '';
$tab = $_GET['tab'] ?? 'usuarios';
if (!in_array($tab, ['usuarios', 'permisos'], true)) $tab = 'usuarios';
$modulosSistema = catalogo_modulos_sistema();
$rolesSistema = roles_disponibles($db);
$usuarioId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$usuario = ['nombre' => '', 'usuario' => '', 'correo' => '', 'rol' => in_array('Vendedor', $rolesSistema, true) ? 'Vendedor' : 'Administrador'];
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
    if ($accion === 'crear_rol') {
        $nuevoRol = trim($_POST['nombre_rol'] ?? '');
        if (!preg_match('/^[\p{L}][\p{L}\p{N} _-]{1,29}$/u', $nuevoRol)) {
            $errores[] = 'El nombre del rol debe tener entre 2 y 30 caracteres y comenzar con una letra.';
            $tab = 'permisos';
        } else {
            try {
                $db->begin_transaction();
                $stmtRol = $db->prepare('INSERT INTO roles (nombre) VALUES (?)');
                $stmtRol->bind_param('s', $nuevoRol);
                $stmtRol->execute();
                $stmtPermiso = $db->prepare('INSERT INTO permisos_modulos (rol, modulo, puede_acceder) VALUES (?, ?, 0)');
                foreach ($modulosSistema as $claveModulo => $datosModulo) {
                    $stmtPermiso->bind_param('ss', $nuevoRol, $claveModulo);
                    $stmtPermiso->execute();
                }
                $db->commit();
                header('Location: /usuarios/?tab=permisos&rol_creado=1');
                exit();
            } catch (mysqli_sql_exception $e) {
                $db->rollback();
                $errores[] = $e->getCode() === 1062 ? 'Ya existe un rol con ese nombre.' : 'No se pudo crear el rol. Verifique la base de datos e inténtelo nuevamente.';
                $tab = 'permisos';
            }
        }
    } elseif ($accion === 'eliminar_rol') {
        $rolEliminar = trim($_POST['rol'] ?? '');
        if (!in_array($rolEliminar, $rolesSistema, true)) {
            $errores[] = 'No se encontró el rol seleccionado.';
            $tab = 'permisos';
        } elseif (in_array($rolEliminar, ['Administrador', 'Vendedor'], true)) {
            $errores[] = 'Los roles principales del sistema no se pueden eliminar.';
            $tab = 'permisos';
        } else {
            $stmt = $db->prepare('SELECT COUNT(*) FROM usuarios WHERE rol = ?');
            $stmt->bind_param('s', $rolEliminar);
            $stmt->execute();
            $usuariosAsignados = (int)$stmt->get_result()->fetch_row()[0];
            if ($usuariosAsignados > 0) {
                $errores[] = 'No se puede eliminar este rol porque tiene usuarios asignados. Cambie primero esos usuarios a otro rol.';
                $tab = 'permisos';
            } else {
                try {
                    $db->begin_transaction();
                    $stmtPermiso = $db->prepare('DELETE FROM permisos_modulos WHERE rol = ?');
                    $stmtPermiso->bind_param('s', $rolEliminar);
                    $stmtPermiso->execute();
                    $stmtRol = $db->prepare('DELETE FROM roles WHERE nombre = ?');
                    $stmtRol->bind_param('s', $rolEliminar);
                    $stmtRol->execute();
                    if ($stmtRol->affected_rows !== 1) {
                        throw new RuntimeException('No se encontró el rol seleccionado.');
                    }
                    $db->commit();
                    header('Location: /usuarios/?tab=permisos&rol_eliminado=1');
                    exit();
                } catch (Throwable $e) {
                    $db->rollback();
                    $errores[] = $e instanceof mysqli_sql_exception
                        ? 'No se pudo eliminar el rol. Inténtelo nuevamente.'
                        : $e->getMessage();
                    $tab = 'permisos';
                }
            }
        }
    } elseif ($accion === 'guardar_permisos') {
        $permisosEnviados = is_array($_POST['permisos'] ?? null) ? $_POST['permisos'] : [];
        try {
            $db->begin_transaction();
            $stmtPermiso = $db->prepare('INSERT INTO permisos_modulos (rol, modulo, puede_acceder) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE puede_acceder = VALUES(puede_acceder)');
            foreach ($rolesSistema as $rolPermisos) {
                foreach ($modulosSistema as $claveModulo => $datosModulo) {
                    $puedeAcceder = isset($permisosEnviados[$rolPermisos][$claveModulo]) ? 1 : 0;
                    if ($rolPermisos === 'Administrador' && in_array($claveModulo, ['inicio', 'usuarios'], true)) {
                        $puedeAcceder = 1;
                    } elseif ($rolPermisos !== 'Administrador' && $claveModulo === 'usuarios') {
                        $puedeAcceder = 0;
                    }
                    $stmtPermiso->bind_param('ssi', $rolPermisos, $claveModulo, $puedeAcceder);
                    $stmtPermiso->execute();
                }
            }
            $db->commit();
            header('Location: /usuarios/?tab=permisos&permisos_guardados=1');
            exit();
        } catch (mysqli_sql_exception $e) {
            $db->rollback();
            $errores[] = 'No se pudieron guardar los permisos. Verifique la base de datos e inténtelo nuevamente.';
            $tab = 'permisos';
        }
    } elseif ($accion === 'eliminar_usuario') {
        $objetivoId = filter_var($_POST['id_usuario'] ?? null, FILTER_VALIDATE_INT);
        if ($objetivoId === false || $objetivoId < 1) {
            $errores[] = 'Seleccione un usuario válido.';
        } else {
            $stmt = $db->prepare('SELECT rol, estado FROM usuarios WHERE id_usuario = ?');
            $stmt->bind_param('i', $objetivoId);
            $stmt->execute();
            $objetivo = $stmt->get_result()->fetch_assoc();
        }
        if ($objetivoId !== false && $objetivoId > 0 && !$objetivo) {
            $errores[] = 'No se encontró el usuario seleccionado.';
        } elseif ($objetivoId !== false && $objetivoId > 0 && $objetivoId === (int)$_SESSION['usuario_id']) {
            $errores[] = 'No puede eliminar la cuenta con la que inició sesión.';
        } elseif ($objetivoId !== false && $objetivoId > 0 && $objetivo) {
            $stmt = $db->prepare('SELECT (SELECT COUNT(*) FROM ventas WHERE id_usuario = ?) + (SELECT COUNT(*) FROM compras WHERE id_usuario = ?) + (SELECT COUNT(*) FROM alertas_atendidas WHERE atendida_por = ?)');
            $stmt->bind_param('iii', $objetivoId, $objetivoId, $objetivoId);
            $stmt->execute();
            $registrosAsociados = (int)$stmt->get_result()->fetch_row()[0];
            $adminsActivos = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE estado = 1 AND rol = 'Administrador'")->fetch_row()[0];
            if ($registrosAsociados > 0) {
                $errores[] = 'No se puede eliminar este usuario porque tiene actividad registrada. Puede desactivarlo para conservar el historial.';
            } elseif ($objetivo['rol'] === 'Administrador' && (int)$objetivo['estado'] === 1 && $adminsActivos <= 1) {
                $errores[] = 'Debe quedar al menos un administrador activo.';
            } else {
                try {
                    $stmt = $db->prepare('DELETE FROM usuarios WHERE id_usuario = ?');
                    $stmt->bind_param('i', $objetivoId);
                    $stmt->execute();
                    header('Location: /usuarios/?usuario_eliminado=1');
                    exit();
                } catch (mysqli_sql_exception $e) {
                    $errores[] = $e->getCode() === 1451
                        ? 'Este usuario tiene actividad registrada y no se puede eliminar. Puede desactivarlo para conservar el historial.'
                        : 'No se pudo eliminar el usuario.';
                }
            }
        }
    } elseif ($accion === 'cambiar_estado') {
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
        if (!in_array($usuario['rol'], $rolesSistema, true)) $errores[] = 'Seleccione un rol válido.';
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
$usuarios = $db->query('SELECT u.id_usuario, u.nombre, u.usuario, u.correo, u.rol, u.estado, (SELECT COUNT(*) FROM ventas v WHERE v.id_usuario = u.id_usuario) + (SELECT COUNT(*) FROM compras c WHERE c.id_usuario = u.id_usuario) + (SELECT COUNT(*) FROM alertas_atendidas a WHERE a.atendida_por = u.id_usuario) AS registros_asociados FROM usuarios u ORDER BY u.nombre')->fetch_all(MYSQLI_ASSOC);
$administradoresActivos = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE estado = 1 AND rol = 'Administrador'")->fetch_row()[0];
$rolesConUsuarios = $db->query('SELECT r.nombre, COUNT(u.id_usuario) AS usuarios_asignados FROM roles r LEFT JOIN usuarios u ON u.rol = r.nombre GROUP BY r.nombre ORDER BY CASE r.nombre WHEN \'Administrador\' THEN 0 WHEN \'Vendedor\' THEN 1 ELSE 2 END, r.nombre')->fetch_all(MYSQLI_ASSOC);
$permisosPorRol = [];
foreach ($rolesSistema as $rolPermisos) {
    $permisosPorRol[$rolPermisos] = permisos_de_rol($db, $rolPermisos);
}
if (isset($_GET['guardado'])) $mensaje = 'Usuario guardado correctamente.';
if (isset($_GET['actualizado'])) $mensaje = 'Estado del usuario actualizado.';
if (isset($_GET['permisos_guardados'])) $mensaje = 'Los permisos por rol se actualizaron correctamente.';
if (isset($_GET['rol_creado'])) $mensaje = 'Rol creado sin permisos. Asígnele los módulos correspondientes.';
if (isset($_GET['rol_eliminado'])) $mensaje = 'El rol se eliminó correctamente.';
if (isset($_GET['usuario_eliminado'])) $mensaje = 'El usuario se eliminó correctamente.';
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Usuarios | Farmacia Fuente de Vida</title><link rel="stylesheet" href="../menu.css"><link rel="stylesheet" href="../configuracion/configuracion.css"></head><body>
<?php include __DIR__ . '/../SideBar/menu.php'; ?>
<main class="main module-main"><header class="header"><div><h1>Usuarios</h1><p>Cuentas y acceso al sistema</p></div><div class="user"><div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['usuario_nombre'] ?? 'A', 0, 1)), ENT_QUOTES, 'UTF-8') ?></div><div><strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'], ENT_QUOTES, 'UTF-8') ?></strong><small>Administrador</small></div></div></header>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php elseif ($mensaje): ?><div class="alert success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<nav class="users-tabs" aria-label="Administración de usuarios"><a href="/usuarios/?tab=usuarios" class="<?= $tab === 'usuarios' ? 'active' : '' ?>" <?= $tab === 'usuarios' ? 'aria-current="page"' : '' ?>>Usuarios</a><a href="/usuarios/?tab=permisos" class="<?= $tab === 'permisos' ? 'active' : '' ?>" <?= $tab === 'permisos' ? 'aria-current="page"' : '' ?>>Permisos por rol</a></nav>
<?php if ($tab === 'usuarios'): ?>
<section class="content-box"><div class="section-header"><div><h2><?= $usuarioId ? 'Editar usuario' : 'Nuevo usuario' ?></h2><p><?= $usuarioId ? 'Deje la contraseña vacía para conservar la actual.' : 'La contraseña se guarda protegida.' ?></p><a class="btn-secondary role-manager-link" href="/usuarios/?tab=permisos">Agregar o configurar roles</a></div></div><form method="POST" class="user-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id_usuario" value="<?= $usuarioId ?>"><label>Nombre<input name="nombre" value="<?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Usuario<input name="usuario" value="<?= htmlspecialchars($usuario['usuario'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Correo<input type="email" name="correo" value="<?= htmlspecialchars($usuario['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label><label>Contraseña<input type="password" name="password" minlength="8" <?= $usuarioId ? '' : 'required' ?>></label><label>Rol<select name="rol"><?php foreach ($rolesSistema as $rolDisponible): ?><option value="<?= htmlspecialchars($rolDisponible, ENT_QUOTES, 'UTF-8') ?>" <?= $usuario['rol'] === $rolDisponible ? 'selected' : '' ?>><?= htmlspecialchars($rolDisponible, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><button class="btn-primary" type="submit"><?= $usuarioId ? 'Guardar cambios' : 'Crear usuario' ?></button><?php if ($usuarioId): ?><a class="btn-secondary" href="/usuarios/?tab=usuarios">Cancelar</a><?php endif; ?></form></section>
<section class="content-box"><div class="section-header"><div><h2>Usuarios registrados</h2><p><?= count($usuarios) ?> cuenta(s). Elimina cuentas sin actividad; las que tienen historial se pueden desactivar.</p></div></div><div class="table-container"><table><thead><tr><th>Nombre</th><th>Usuario</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody><?php if (!$usuarios): ?><tr><td colspan="6">No hay usuarios registrados.</td></tr><?php endif; ?><?php foreach ($usuarios as $fila): ?><?php $esActual = (int)$fila['id_usuario'] === (int)$_SESSION['usuario_id']; $tieneHistorial = (int)$fila['registros_asociados'] > 0; $esUltimoAdminActivo = $fila['rol'] === 'Administrador' && (int)$fila['estado'] === 1 && $administradoresActivos <= 1; $puedeEliminarUsuario = !$esActual && !$tieneHistorial && !$esUltimoAdminActivo; ?><tr><td><strong><?= htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td><td><?= htmlspecialchars($fila['usuario'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fila['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fila['rol'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int)$fila['estado'] ? 'Activo' : 'Inactivo' ?></td><td class="user-actions"><?php if (!$esActual): ?><a class="edit" href="/usuarios/?id=<?= (int)$fila['id_usuario'] ?>&amp;tab=usuarios">Editar</a><form method="POST" <?= confirmar_eliminaciones($configuracion) ? 'data-confirm-title="' . ((int)$fila['estado'] ? 'Desactivar usuario' : 'Activar usuario') . '" data-confirm-message="' . ((int)$fila['estado'] ? 'El usuario ya no podrá iniciar sesión. Puedes volver a activarlo después.' : 'El usuario podrá volver a iniciar sesión con su cuenta.') . '" data-confirm-label="' . ((int)$fila['estado'] ? 'Desactivar' : 'Activar') . '" data-confirm-kind="warning"' : '' ?>><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="id_usuario" value="<?= (int)$fila['id_usuario'] ?>"><button class="delete" type="submit"><?= (int)$fila['estado'] ? 'Desactivar' : 'Activar' ?></button></form><?php if ($puedeEliminarUsuario): ?><form method="POST" data-confirm-title="Eliminar usuario" data-confirm-message="La cuenta se eliminará permanentemente. Esta acción no se puede deshacer." data-confirm-label="Eliminar" data-confirm-kind="danger"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="eliminar_usuario"><input type="hidden" name="id_usuario" value="<?= (int)$fila['id_usuario'] ?>"><button class="delete permanent-delete" type="submit">Eliminar</button></form><?php else: ?><span class="action-hint" title="<?= $esUltimoAdminActivo ? 'Debe quedar al menos un administrador activo.' : ($tieneHistorial ? 'Conserva el historial de actividad; puedes desactivarlo.' : 'No se puede eliminar la cuenta actual.') ?>"><?= $esUltimoAdminActivo ? 'Último administrador' : ($tieneHistorial ? 'Con historial' : 'Sesión actual') ?></span><?php endif; ?><?php else: ?><span class="action-hint">Sesión actual</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php else: ?>
<section class="content-box permission-box"><div class="section-header"><div><h2>Permisos por rol</h2><p>Elige a qué módulos puede entrar cada rol. Los cambios se aplican al menú y también al intentar abrir la dirección del módulo.</p></div></div>
<div class="permission-notice"><strong>Acceso protegido:</strong> el rol Administrador siempre conserva acceso a Inicio y a esta sección de Usuarios para poder administrar la farmacia y recuperar permisos.</div>
<form method="POST" class="role-create-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="crear_rol"><label for="nombre-rol">Agregar un rol</label><div><input id="nombre-rol" name="nombre_rol" maxlength="30" placeholder="Ej. Encargado" required><button class="btn-secondary" type="submit">Agregar rol</button></div><small>El rol nuevo empezará sin acceso a módulos; luego podrás configurarlo aquí.</small></form>
<div class="role-list" aria-label="Roles existentes"><?php foreach ($rolesConUsuarios as $rolInfo): ?><?php $rolProtegido = in_array($rolInfo['nombre'], ['Administrador', 'Vendedor'], true); $rolEnUso = (int)$rolInfo['usuarios_asignados'] > 0; ?><article class="role-card"><div><strong><?= htmlspecialchars($rolInfo['nombre'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= (int)$rolInfo['usuarios_asignados'] ?> usuario(s) asignado(s)</small></div><?php if ($rolProtegido): ?><span class="role-protected">Rol principal</span><?php elseif ($rolEnUso): ?><button class="delete" type="button" disabled title="Cambia primero esos usuarios a otro rol.">En uso</button><?php else: ?><form method="POST" data-confirm-title="Eliminar rol" data-confirm-message="Se eliminarán también los permisos configurados para este rol. Esta acción no se puede deshacer." data-confirm-label="Eliminar rol" data-confirm-kind="danger"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="eliminar_rol"><input type="hidden" name="rol" value="<?= htmlspecialchars($rolInfo['nombre'], ENT_QUOTES, 'UTF-8') ?>"><button class="delete" type="submit">Eliminar rol</button></form><?php endif; ?></article><?php endforeach; ?></div>
<form method="POST" class="permission-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="guardar_permisos"><input type="hidden" name="tab" value="permisos"><div class="table-container"><table class="permission-table"><thead><tr><th>Módulo</th><?php foreach ($rolesSistema as $rolPermisos): ?><th><?= htmlspecialchars($rolPermisos, ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($modulosSistema as $claveModulo => $datosModulo): ?><tr><th scope="row"><?= htmlspecialchars($datosModulo['nombre'], ENT_QUOTES, 'UTF-8') ?></th><?php foreach ($rolesSistema as $rolPermisos): ?><?php $forzado = ($rolPermisos === 'Administrador' && in_array($claveModulo, ['inicio', 'usuarios'], true)) || ($rolPermisos !== 'Administrador' && $claveModulo === 'usuarios'); $habilitado = $forzado ? $rolPermisos === 'Administrador' : !empty($permisosPorRol[$rolPermisos][$claveModulo]); ?><td><label class="permission-toggle"><input type="checkbox" name="permisos[<?= htmlspecialchars($rolPermisos, ENT_QUOTES, 'UTF-8') ?>][<?= htmlspecialchars($claveModulo, ENT_QUOTES, 'UTF-8') ?>]" value="1" <?= $habilitado ? 'checked' : '' ?> <?= $forzado ? 'disabled' : '' ?>><span><?= $forzado ? ($rolPermisos === 'Administrador' ? 'Siempre permitido' : 'Solo Administrador') : ($habilitado ? 'Permitido' : 'Bloqueado') ?></span></label></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><div class="permission-actions"><p>Los roles nuevos se crean sin accesos. Usuarios queda reservado para Administrador.</p><button class="btn-primary" type="submit">Guardar permisos</button></div></form></section>
<?php endif; ?>
</main>
<dialog class="action-confirm-dialog" id="actionConfirmDialog" aria-labelledby="actionConfirmTitle" aria-describedby="actionConfirmMessage">
    <div class="action-confirm-icon" aria-hidden="true">!</div>
    <h2 id="actionConfirmTitle">Confirmar acción</h2>
    <p id="actionConfirmMessage"></p>
    <div class="action-confirm-actions">
        <button class="btn-secondary" id="actionConfirmCancel" type="button">Cancelar</button>
        <button class="action-confirm-submit" id="actionConfirmSubmit" type="button">Confirmar</button>
    </div>
</dialog>
<script>
    const actionConfirmDialog = document.getElementById('actionConfirmDialog');
    const actionConfirmTitle = document.getElementById('actionConfirmTitle');
    const actionConfirmMessage = document.getElementById('actionConfirmMessage');
    const actionConfirmCancel = document.getElementById('actionConfirmCancel');
    const actionConfirmSubmit = document.getElementById('actionConfirmSubmit');
    let pendingConfirmedForm = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirmMessage) return;
        if (pendingConfirmedForm === form) {
            pendingConfirmedForm = null;
            return;
        }

        event.preventDefault();
        actionConfirmTitle.textContent = form.dataset.confirmTitle || 'Confirmar acción';
        actionConfirmMessage.textContent = form.dataset.confirmMessage;
        actionConfirmSubmit.textContent = form.dataset.confirmLabel || 'Confirmar';
        actionConfirmSubmit.classList.toggle('danger', form.dataset.confirmKind === 'danger');
        actionConfirmSubmit.classList.toggle('warning', form.dataset.confirmKind === 'warning');
        actionConfirmDialog.returnValue = '';
        actionConfirmDialog.showModal();
        actionConfirmCancel.focus();

        actionConfirmSubmit.onclick = () => {
            actionConfirmDialog.close('confirm');
            pendingConfirmedForm = form;
            form.requestSubmit();
        };
    });

    actionConfirmCancel.addEventListener('click', () => actionConfirmDialog.close('cancel'));
    actionConfirmDialog.addEventListener('click', (event) => {
        if (event.target === actionConfirmDialog) actionConfirmDialog.close('cancel');
    });
</script>
<script src="/dev-reload.js"></script></body></html>
