<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: /login/login.php'); exit(); }
require_once __DIR__ . '/../csrf.php';
validar_csrf();
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../config_helpers.php';
require_once __DIR__ . '/../permisos.php';
$db = conectar();
exigir_permiso_modulo($db, 'clientes');
$configuracion = cargar_configuracion($db);
$clienteId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$cliente = ['nombre' => '', 'nit' => '', 'telefono' => '', 'correo' => '', 'direccion' => ''];
$errores = [];
$mensaje = '';
if ($clienteId) {
    $stmt = $db->prepare('SELECT nombre, nit, telefono, correo, direccion FROM clientes WHERE id_cliente = ? AND estado = 1');
    $stmt->bind_param('i', $clienteId);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    if (!$existente) { http_response_code(404); exit('Cliente no encontrado.'); }
    $cliente = $existente;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (($_POST['accion'] ?? '') === 'desactivar') {
        $clienteId = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
        if ($clienteId) {
            $stmt = $db->prepare('UPDATE clientes SET estado = 0 WHERE id_cliente = ?');
            $stmt->bind_param('i', $clienteId);
            $stmt->execute();
        }
        header('Location: /clientes/?desactivado=1');
        exit();
    }
    foreach (array_keys($cliente) as $campo) $cliente[$campo] = trim($_POST[$campo] ?? '');
    if (strlen($cliente['nombre']) < 3) $errores[] = 'El nombre debe tener al menos 3 caracteres.';
    if ($cliente['correo'] !== '' && !filter_var($cliente['correo'], FILTER_VALIDATE_EMAIL)) $errores[] = 'Ingrese un correo válido.';
    if ($cliente['telefono'] !== '' && !preg_match('/^[0-9+ ()-]{7,20}$/', $cliente['telefono'])) $errores[] = 'Ingrese un teléfono válido.';
    if (!$errores) {
        if ($clienteId) {
            $stmt = $db->prepare('UPDATE clientes SET nombre = ?, nit = ?, telefono = ?, correo = ?, direccion = ? WHERE id_cliente = ?');
            $stmt->bind_param('sssssi', $cliente['nombre'], $cliente['nit'], $cliente['telefono'], $cliente['correo'], $cliente['direccion'], $clienteId);
        } else {
            $stmt = $db->prepare('INSERT INTO clientes (nombre, nit, telefono, correo, direccion) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssss', $cliente['nombre'], $cliente['nit'], $cliente['telefono'], $cliente['correo'], $cliente['direccion']);
        }
        $stmt->execute();
        header('Location: /clientes/?guardado=1');
        exit();
    }
}
$buscarClientes = trim($_GET['buscar_cliente'] ?? '');
$paginaSolicitada = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$registrosPorPagina = 15;
$whereClientes = " FROM clientes WHERE estado = 1 AND (nombre LIKE CONCAT('%', ?, '%') OR COALESCE(nit, '') LIKE CONCAT('%', ?, '%') OR COALESCE(telefono, '') LIKE CONCAT('%', ?, '%') OR COALESCE(correo, '') LIKE CONCAT('%', ?, '%'))";
$stmt = $db->prepare('SELECT COUNT(*)' . $whereClientes);
$stmt->bind_param('ssss', $buscarClientes, $buscarClientes, $buscarClientes, $buscarClientes);
$stmt->execute();
$totalClientes = (int)$stmt->get_result()->fetch_row()[0];
$totalPaginas = max(1, (int)ceil($totalClientes / $registrosPorPagina));
$paginaActual = min(max(1, $paginaSolicitada), $totalPaginas);
$offset = ($paginaActual - 1) * $registrosPorPagina;
$inicioPagina = max(1, $paginaActual - 2);
$finPagina = min($totalPaginas, $paginaActual + 2);
$urlBasePaginacion = '/clientes/?' . http_build_query(['buscar_cliente' => $buscarClientes]);
$stmt = $db->prepare('SELECT id_cliente, nombre, nit, telefono, correo' . $whereClientes . ' ORDER BY nombre LIMIT ? OFFSET ?');
$stmt->bind_param('ssssii', $buscarClientes, $buscarClientes, $buscarClientes, $buscarClientes, $registrosPorPagina, $offset);
$stmt->execute();
$clientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if (isset($_GET['guardado'])) $mensaje = 'Cliente guardado correctamente.';
if (isset($_GET['desactivado'])) $mensaje = 'Cliente desactivado correctamente.';
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Clientes | Farmacia Fuente de Vida</title><link rel="stylesheet" href="../menu.css"><link rel="stylesheet" href="clientes.css"></head><body>
<?php include __DIR__ . '/../SideBar/menu.php'; ?>
<main class="main module-main"><header class="header"><div><h1>Clientes</h1><p>Registro de clientes</p></div><div class="user"><div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)), ENT_QUOTES, 'UTF-8') ?></div><div><strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($_SESSION['usuario_rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></div></div></header>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php elseif ($mensaje): ?><div class="alert success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<section class="content-box"><div class="section-header"><div><h2><?= $clienteId ? 'Editar cliente' : 'Nuevo cliente' ?></h2></div></div><form method="POST" class="provider-form"><?= csrf_input() ?>
<label>Nombre<input name="nombre" value="<?= htmlspecialchars($cliente['nombre'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>NIT<input name="nit" value="<?= htmlspecialchars($cliente['nit'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Teléfono<input name="telefono" value="<?= htmlspecialchars($cliente['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Correo<input type="email" name="correo" value="<?= htmlspecialchars($cliente['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><label>Dirección<input name="direccion" value="<?= htmlspecialchars($cliente['direccion'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label><button class="btn-primary" type="submit"><?= $clienteId ? 'Guardar cambios' : 'Registrar cliente' ?></button><?php if ($clienteId): ?><a class="btn-secondary" href="/clientes/">Cancelar</a><?php endif; ?></form></section>
<section class="content-box"><div class="section-header"><div><h2>Clientes registrados</h2><p><?= $totalClientes ?> resultado(s)<?= $totalClientes ? ' · Página ' . $paginaActual . ' de ' . $totalPaginas : '' ?></p></div></div><form class="client-filters" method="GET" data-auto-filter><input type="search" name="buscar_cliente" value="<?= htmlspecialchars($buscarClientes, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre, NIT, teléfono o correo"><a class="btn-secondary" href="/clientes/">Limpiar</a></form><div class="table-container"><table><thead><tr><th>Nombre</th><th>NIT</th><th>Teléfono</th><th>Correo</th><th>Acciones</th></tr></thead><tbody><?php if (!$clientes): ?><tr><td colspan="5">No hay clientes que coincidan con la búsqueda.</td></tr><?php endif; ?><?php foreach ($clientes as $fila): ?><tr><td><?= htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fila['nit'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fila['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fila['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><a class="edit" href="/clientes/?id=<?= (int)$fila['id_cliente'] ?>">Editar</a><form method="POST" onsubmit="<?= confirmar_eliminaciones($configuracion) ? "return confirm('¿Desactivar este cliente?');" : '' ?>">
<?= csrf_input() ?>
<input type="hidden" name="accion" value="desactivar"><input type="hidden" name="id_cliente" value="<?= (int)$fila['id_cliente'] ?>"><button class="delete" type="submit">Eliminar</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php if ($totalPaginas > 1): ?><nav class="pagination" aria-label="Paginación de clientes"><?php if ($paginaActual > 1): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . ($paginaActual - 1), ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?><?php if ($inicioPagina > 1): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=1', ENT_QUOTES, 'UTF-8') ?>">1</a><?php if ($inicioPagina > 2): ?><span aria-hidden="true">…</span><?php endif; ?><?php endif; ?><?php for ($pagina = $inicioPagina; $pagina <= $finPagina; $pagina++): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . $pagina, ENT_QUOTES, 'UTF-8') ?>" class="<?= $pagina === $paginaActual ? 'current' : '' ?>" <?= $pagina === $paginaActual ? 'aria-current="page"' : '' ?>><?= $pagina ?></a><?php endfor; ?><?php if ($finPagina < $totalPaginas): ?><?php if ($finPagina < $totalPaginas - 1): ?><span aria-hidden="true">…</span><?php endif; ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . $totalPaginas, ENT_QUOTES, 'UTF-8') ?>"><?= $totalPaginas ?></a><?php endif; ?><?php if ($paginaActual < $totalPaginas): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . ($paginaActual + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?></nav><?php endif; ?></section></main><script src="/dev-reload.js"></script></body></html>
