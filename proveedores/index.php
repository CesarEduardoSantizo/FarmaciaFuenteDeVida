<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: /login/login.php'); exit(); }
require_once __DIR__ . '/../csrf.php';
validar_csrf();
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../config_helpers.php';
$db = conectar();
$configuracion = cargar_configuracion($db);
$proveedorId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$proveedor = ['nombre' => '', 'contacto' => '', 'nit' => '', 'telefono' => '', 'correo' => '', 'direccion' => ''];
$errores = [];
$mensaje = '';
if ($proveedorId) {
    $stmt = $db->prepare('SELECT nombre, contacto, nit, telefono, correo, direccion FROM proveedores WHERE id_proveedor = ? AND estado = 1');
    $stmt->bind_param('i', $proveedorId);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    if (!$existente) { http_response_code(404); exit('Proveedor no encontrado.'); }
    $proveedor = $existente;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (($_POST['accion'] ?? '') === 'desactivar') {
        $proveedorId = filter_input(INPUT_POST, 'id_proveedor', FILTER_VALIDATE_INT);
        if ($proveedorId) {
            $stmt = $db->prepare('UPDATE proveedores SET estado = 0 WHERE id_proveedor = ?');
            $stmt->bind_param('i', $proveedorId);
            $stmt->execute();
        }
        header('Location: /proveedores/?desactivado=1');
        exit();
    }
    foreach (array_keys($proveedor) as $campo) $proveedor[$campo] = trim($_POST[$campo] ?? '');
    if (strlen($proveedor['nombre']) < 3) $errores[] = 'El nombre debe tener al menos 3 caracteres.';
    if ($proveedor['contacto'] !== '' && strlen($proveedor['contacto']) < 3) $errores[] = 'El contacto debe tener al menos 3 caracteres.';
    if ($proveedor['telefono'] !== '' && !preg_match('/^[0-9+ ()-]{7,20}$/', $proveedor['telefono'])) $errores[] = 'Ingrese un teléfono válido.';
    if ($proveedor['correo'] !== '' && !filter_var($proveedor['correo'], FILTER_VALIDATE_EMAIL)) $errores[] = 'Ingrese un correo válido.';
    if (!$errores) {
        if ($proveedorId) {
            $stmt = $db->prepare('UPDATE proveedores SET nombre = ?, contacto = ?, nit = ?, telefono = ?, correo = ?, direccion = ? WHERE id_proveedor = ?');
            $stmt->bind_param('ssssssi', $proveedor['nombre'], $proveedor['contacto'], $proveedor['nit'], $proveedor['telefono'], $proveedor['correo'], $proveedor['direccion'], $proveedorId);
        } else {
            $stmt = $db->prepare('INSERT INTO proveedores (nombre, contacto, nit, telefono, correo, direccion) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssss', $proveedor['nombre'], $proveedor['contacto'], $proveedor['nit'], $proveedor['telefono'], $proveedor['correo'], $proveedor['direccion']);
        }
        $stmt->execute();
        header('Location: /proveedores/?guardado=1');
        exit();
    }
}
$paginaSolicitada = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$registrosPorPagina = 15;
$totalProveedores = (int)$db->query('SELECT COUNT(*) FROM proveedores WHERE estado = 1')->fetch_row()[0];
$totalPaginas = max(1, (int)ceil($totalProveedores / $registrosPorPagina));
$paginaActual = min(max(1, $paginaSolicitada), $totalPaginas);
$offset = ($paginaActual - 1) * $registrosPorPagina;
$inicioPagina = max(1, $paginaActual - 2);
$finPagina = min($totalPaginas, $paginaActual + 2);
$urlBasePaginacion = '/proveedores/';
$stmt = $db->prepare('SELECT id_proveedor, nombre, contacto, telefono, correo FROM proveedores WHERE estado = 1 ORDER BY nombre LIMIT ? OFFSET ?');
$stmt->bind_param('ii', $registrosPorPagina, $offset);
$stmt->execute();
$proveedores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if (isset($_GET['guardado'])) $mensaje = 'Proveedor guardado correctamente.';
if (isset($_GET['desactivado'])) $mensaje = 'Proveedor desactivado correctamente.';
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Proveedores | Farmacia Fuente de Vida</title><link rel="stylesheet" href="../menu.css"><link rel="stylesheet" href="proveedores.css"></head><body>
<?php include __DIR__ . '/../SideBar/menu.php'; ?>
<main class="main module-main"><header class="header"><div><h1>Proveedores</h1><p>Gestión de proveedores</p></div><div class="user"><div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'U', 0, 1)), ENT_QUOTES, 'UTF-8') ?></div><div><strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($_SESSION['usuario_rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></div></div></header>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php elseif ($mensaje): ?><div class="alert success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<section class="content-box"><div class="section-header"><div><h2><?= $proveedorId ? 'Editar proveedor' : 'Nuevo proveedor' ?></h2><p>Datos de contacto del proveedor</p></div></div><form method="POST" class="provider-form"><?= csrf_input() ?>
<label>Nombre<input name="nombre" value="<?= htmlspecialchars($proveedor['nombre'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Contacto<input name="contacto" value="<?= htmlspecialchars($proveedor['contacto'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>NIT<input name="nit" value="<?= htmlspecialchars($proveedor['nit'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Teléfono<input name="telefono" value="<?= htmlspecialchars($proveedor['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Correo<input type="email" name="correo" value="<?= htmlspecialchars($proveedor['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Dirección<input name="direccion" value="<?= htmlspecialchars($proveedor['direccion'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><button class="btn-primary" type="submit"><?= $proveedorId ? 'Guardar cambios' : '+ Nuevo proveedor' ?></button><?php if ($proveedorId): ?><a class="btn-secondary" href="/proveedores/">Cancelar</a><?php endif; ?></form></section>
<section class="content-box"><div class="section-header"><div><h2>Proveedores registrados</h2><p><?= $totalProveedores ?> proveedor(es)<?= $totalProveedores ? ' · Página ' . $paginaActual . ' de ' . $totalPaginas : '' ?></p></div></div><div class="table-container"><table><thead><tr><th>Nombre</th><th>Contacto</th><th>Teléfono</th><th>Correo</th><th>Acciones</th></tr></thead><tbody><?php if (!$proveedores): ?><tr><td colspan="5" class="empty-state">No hay proveedores registrados.</td></tr><?php endif; ?><?php foreach ($proveedores as $fila): ?><tr><td><strong><?= htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td><td><?= htmlspecialchars($fila['contacto'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fila['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fila['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><a class="edit" href="/proveedores/?id=<?= (int)$fila['id_proveedor'] ?>">Editar</a><form method="POST" onsubmit="<?= confirmar_eliminaciones($configuracion) ? "return confirm('¿Desactivar este proveedor?');" : '' ?>">
<?= csrf_input() ?>
<input type="hidden" name="accion" value="desactivar">
<input type="hidden" name="id_proveedor" value="<?= (int)$fila['id_proveedor'] ?>">
<button class="delete" type="submit">Eliminar</button>
</form></td></tr><?php endforeach; ?></tbody></table></div><?php if ($totalPaginas > 1): ?><nav class="pagination" aria-label="Paginación de proveedores"><?php if ($paginaActual > 1): ?><a href="<?= $urlBasePaginacion . '?pagina=' . ($paginaActual - 1) ?>">Anterior</a><?php endif; ?><?php if ($inicioPagina > 1): ?><a href="<?= $urlBasePaginacion ?>?pagina=1">1</a><?php if ($inicioPagina > 2): ?><span aria-hidden="true">…</span><?php endif; ?><?php endif; ?><?php for ($pagina = $inicioPagina; $pagina <= $finPagina; $pagina++): ?><a href="<?= $urlBasePaginacion ?>?pagina=<?= $pagina ?>" class="<?= $pagina === $paginaActual ? 'current' : '' ?>" <?= $pagina === $paginaActual ? 'aria-current="page"' : '' ?>><?= $pagina ?></a><?php endfor; ?><?php if ($finPagina < $totalPaginas): ?><?php if ($finPagina < $totalPaginas - 1): ?><span aria-hidden="true">…</span><?php endif; ?><a href="<?= $urlBasePaginacion ?>?pagina=<?= $totalPaginas ?>"><?= $totalPaginas ?></a><?php endif; ?><?php if ($paginaActual < $totalPaginas): ?><a href="<?= $urlBasePaginacion ?>?pagina=<?= $paginaActual + 1 ?>">Siguiente</a><?php endif; ?></nav><?php endif; ?></section></main><script src="/dev-reload.js"></script></body></html>
