<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: /login/login.php'); exit(); }
require_once __DIR__ . '/../csrf.php';
validar_csrf();
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../config_helpers.php';
$db = conectar();
$configuracion = cargar_configuracion($db);
$simboloMoneda = simbolo_moneda($configuracion);
$stmt = $db->query("SELECT p.id_producto, p.nombre, p.precio_venta, COALESCE(SUM(l.cantidad), 0) AS stock FROM productos p LEFT JOIN lotes l ON l.id_producto = p.id_producto AND l.estado = 1 AND l.fecha_vencimiento >= CURDATE() WHERE p.estado = 1 GROUP BY p.id_producto, p.nombre, p.precio_venta HAVING stock > 0 ORDER BY p.nombre");
$productos = $stmt->fetch_all(MYSQLI_ASSOC);
$clientes = $db->query('SELECT id_cliente, nombre FROM clientes WHERE estado = 1 ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$errores = [];
$mensaje = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $productoId = filter_input(INPUT_POST, 'producto', FILTER_VALIDATE_INT);
    $cantidad = filter_var($_POST['cantidad'] ?? '', FILTER_VALIDATE_INT);
    $clienteId = filter_input(INPUT_POST, 'cliente', FILTER_VALIDATE_INT);
    if ($clienteId === false || $clienteId === 0) $clienteId = null;
    $metodoPago = trim($_POST['metodo_pago'] ?? 'Efectivo');
    if (!$productoId || !array_filter($productos, static fn(array $item): bool => (int)$item['id_producto'] === $productoId)) $errores[] = 'Seleccione un producto con existencias.';
    if ($cantidad === false || $cantidad < 1) $errores[] = 'La cantidad debe ser un entero mayor que 0.';
    if ($clienteId && !in_array($clienteId, array_map('intval', array_column($clientes, 'id_cliente')), true)) $errores[] = 'Seleccione un cliente válido.';
    if (!in_array($metodoPago, ['Efectivo', 'Tarjeta', 'Transferencia'], true)) $errores[] = 'Seleccione un método de pago válido.';
    if (!$errores) {
        try {
            $db->begin_transaction();
            $stmt = $db->prepare('SELECT precio_venta FROM productos WHERE id_producto = ? AND estado = 1 FOR UPDATE');
            $stmt->bind_param('i', $productoId);
            $stmt->execute();
            $producto = $stmt->get_result()->fetch_assoc();
            $stmt = $db->prepare('SELECT id_lote, cantidad FROM lotes WHERE id_producto = ? AND estado = 1 AND fecha_vencimiento >= CURDATE() AND cantidad > 0 ORDER BY fecha_vencimiento, id_lote FOR UPDATE');
            $stmt->bind_param('i', $productoId);
            $stmt->execute();
            $lotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $disponible = array_sum(array_column($lotes, 'cantidad'));
            if (!$producto || $disponible < $cantidad) {
                $db->rollback();
                $errores[] = 'No hay existencias suficientes en lotes vigentes.';
            } else {
                $precio = (float)$producto['precio_venta'];
                $total = $precio * $cantidad;
                $stmt = $db->prepare('INSERT INTO ventas (id_usuario, id_cliente, total, metodo_pago) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('iids', $_SESSION['usuario_id'], $clienteId, $total, $metodoPago);
                $stmt->execute();
                $ventaId = $db->insert_id;
                $stmt = $db->prepare('INSERT INTO detalle_ventas (id_venta, id_producto, id_lote, cantidad, precio_unitario) VALUES (?, ?, ?, ?, ?)');
                $restante = $cantidad;
                foreach ($lotes as $lote) {
                    $cantidadLote = min($restante, (int)$lote['cantidad']);
                    if ($cantidadLote < 1) continue;
                    $loteId = (int)$lote['id_lote'];
                    $stmt->bind_param('iiiid', $ventaId, $productoId, $loteId, $cantidadLote, $precio);
                    $stmt->execute();
                    $restante -= $cantidadLote;
                    if ($restante === 0) break;
                }
                $db->commit();
                header('Location: /ventas/?registrada=' . $ventaId);
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            $db->rollback();
            $errores[] = 'No se pudo registrar la venta. Verifique los datos e inténtelo nuevamente.';
        }
    }
}
$detalleVenta = filter_input(INPUT_GET, 'detalle', FILTER_VALIDATE_INT) ?: 0;
$detalle = [];
if ($detalleVenta) {
    $stmt = $db->prepare('SELECT p.nombre, dv.cantidad, dv.precio_unitario, dv.subtotal FROM detalle_ventas dv INNER JOIN productos p ON p.id_producto = dv.id_producto WHERE dv.id_venta = ? ORDER BY p.nombre');
    $stmt->bind_param('i', $detalleVenta);
    $stmt->execute();
    $detalle = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
if (isset($_GET['registrada'])) $mensaje = 'Venta registrada correctamente.';
$buscarVentas = trim($_GET['buscar_venta'] ?? '');
$paginaSolicitada = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$registrosPorPagina = 15;
$fechaDesdeVentas = trim($_GET['desde'] ?? '');
$fechaHastaVentas = trim($_GET['hasta'] ?? '');
$validarFechaFiltro = static function (string $fecha): string {
    if ($fecha === '') return '';
    $fechaParseada = DateTime::createFromFormat('!Y-m-d', $fecha);
    return $fechaParseada && $fechaParseada->format('Y-m-d') === $fecha ? $fecha : '';
};
$fechaDesdeVentas = $validarFechaFiltro($fechaDesdeVentas);
$fechaHastaVentas = $validarFechaFiltro($fechaHastaVentas);
$errorFiltroVentas = $fechaDesdeVentas !== '' && $fechaHastaVentas !== '' && $fechaDesdeVentas > $fechaHastaVentas;
$whereVentas = " FROM ventas v LEFT JOIN clientes c ON c.id_cliente = v.id_cliente WHERE (? = '' OR CAST(v.id_venta AS CHAR) LIKE CONCAT('%', ?, '%') OR COALESCE(c.nombre, 'Público en general') LIKE CONCAT('%', ?, '%')) AND (? = '' OR v.fecha_venta >= ?) AND (? = '' OR v.fecha_venta < DATE_ADD(?, INTERVAL 1 DAY))";
$stmt = $db->prepare('SELECT COUNT(*)' . $whereVentas);
$stmt->bind_param('sssssss', $buscarVentas, $buscarVentas, $buscarVentas, $fechaDesdeVentas, $fechaDesdeVentas, $fechaHastaVentas, $fechaHastaVentas);
$stmt->execute();
$totalVentas = (int)$stmt->get_result()->fetch_row()[0];
$totalPaginas = max(1, (int)ceil($totalVentas / $registrosPorPagina));
$paginaActual = min(max(1, $paginaSolicitada), $totalPaginas);
$offset = ($paginaActual - 1) * $registrosPorPagina;
$inicioPagina = max(1, $paginaActual - 2);
$finPagina = min($totalPaginas, $paginaActual + 2);
$urlBasePaginacion = '/ventas/?' . http_build_query(['buscar_venta' => $buscarVentas, 'desde' => $fechaDesdeVentas, 'hasta' => $fechaHastaVentas]);
$sqlVentas = "SELECT v.id_venta, v.fecha_venta, COALESCE(c.nombre, 'Público en general') AS cliente, v.total, v.estado" . $whereVentas . ' ORDER BY v.fecha_venta DESC, v.id_venta DESC LIMIT ? OFFSET ?';
$stmt = $db->prepare($sqlVentas);
$stmt->bind_param('sssssssii', $buscarVentas, $buscarVentas, $buscarVentas, $fechaDesdeVentas, $fechaDesdeVentas, $fechaHastaVentas, $fechaHastaVentas, $registrosPorPagina, $offset);
$stmt->execute();
$ventas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Ventas | Farmacia Fuente de Vida</title><link rel="stylesheet" href="../menu.css"><link rel="stylesheet" href="ventas.css"></head><body>
<?php include __DIR__ . '/../SideBar/menu.php'; ?>
<main class="main module-main"><header class="header"><div><h1>Ventas</h1><p>Registro de ventas de la farmacia</p></div><div class="user"><div class="user-avatar">A</div><div><strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($_SESSION['usuario_rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></div></div></header>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php elseif ($mensaje): ?><div class="alert success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<section class="content-box sale-form"><div class="section-header"><div><h2>Nueva venta</h2><p>El precio y las existencias se verifican al guardar</p></div></div><form method="POST" class="inline-form"><?= csrf_input() ?>
<label>Producto<select name="producto" required><option value="">Seleccione un producto</option><?php foreach ($productos as $producto): ?><option value="<?= (int)$producto['id_producto'] ?>" <?= (int)($_POST['producto'] ?? 0) === (int)$producto['id_producto'] ? 'selected' : '' ?>><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?> · <?= $simboloMoneda ?> <?= number_format((float)$producto['precio_venta'], 2) ?> (<?= (int)$producto['stock'] ?> disp.)</option><?php endforeach; ?></select></label>
<label>Cantidad<input type="number" name="cantidad" min="1" step="1" value="<?= htmlspecialchars($_POST['cantidad'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label>
<label>Cliente<select name="cliente"><option value="">Público en general</option><?php foreach ($clientes as $cliente): ?><option value="<?= (int)$cliente['id_cliente'] ?>" <?= (int)($_POST['cliente'] ?? 0) === (int)$cliente['id_cliente'] ? 'selected' : '' ?>><?= htmlspecialchars($cliente['nombre'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
<label>Método de pago<select name="metodo_pago"><option>Efectivo</option><option>Tarjeta</option><option>Transferencia</option></select></label>
<button class="btn-primary" type="submit" <?= !$productos ? 'disabled' : '' ?>>Registrar venta</button></form></section>
<?php if ($detalleVenta): ?><section class="content-box"><div class="section-header"><div><h2>Detalle de venta #<?= $detalleVenta ?></h2></div><a class="btn-secondary" href="/ventas/">Cerrar</a></div><div class="table-container"><table><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio unitario</th><th>Subtotal</th></tr></thead><tbody><?php foreach ($detalle as $linea): ?><tr><td><?= htmlspecialchars($linea['nombre'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int)$linea['cantidad'] ?></td><td><?= $simboloMoneda ?> <?= number_format((float)$linea['precio_unitario'], 2) ?></td><td><?= $simboloMoneda ?> <?= number_format((float)$linea['subtotal'], 2) ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
<section class="content-box"><div class="section-header"><div><h2>Historial de ventas</h2><p><?= $totalVentas ?> venta(s)<?= $totalVentas ? ' · Página ' . $paginaActual . ' de ' . $totalPaginas : '' ?></p></div><button class="btn-secondary" type="button" onclick="window.print()">Imprimir</button></div>
<form class="sales-filters" method="GET" data-auto-filter><input type="search" name="buscar_venta" value="<?= htmlspecialchars($buscarVentas, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por cliente o número de venta"><label>Desde<input type="date" name="desde" value="<?= htmlspecialchars($fechaDesdeVentas, ENT_QUOTES, 'UTF-8') ?>"></label><label>Hasta<input type="date" name="hasta" value="<?= htmlspecialchars($fechaHastaVentas, ENT_QUOTES, 'UTF-8') ?>"></label><a class="btn-secondary" href="/ventas/">Limpiar</a></form>
<?php if ($errorFiltroVentas): ?><div class="alert error">La fecha inicial no puede ser posterior a la fecha final.</div><?php endif; ?>
<div class="table-container"><table><thead><tr><th>Fecha</th><th>Cliente</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead><tbody><?php if (!$ventas): ?><tr><td colspan="5" class="empty-state">No hay ventas que coincidan con esos filtros.</td></tr><?php endif; ?><?php foreach ($ventas as $venta): ?><tr><td><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></td><td><?= htmlspecialchars($venta['cliente'], ENT_QUOTES, 'UTF-8') ?></td><td><?= $simboloMoneda ?> <?= number_format((float)$venta['total'], 2) ?></td><td><span class="status completed"><?= htmlspecialchars($venta['estado'], ENT_QUOTES, 'UTF-8') ?></span></td><td><a class="link-button" href="/ventas/?detalle=<?= (int)$venta['id_venta'] ?>">Ver</a></td></tr><?php endforeach; ?></tbody></table></div><?php if ($totalPaginas > 1): ?><nav class="pagination" aria-label="Paginación de ventas"><?php if ($paginaActual > 1): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . ($paginaActual - 1), ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?><?php if ($inicioPagina > 1): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=1', ENT_QUOTES, 'UTF-8') ?>">1</a><?php if ($inicioPagina > 2): ?><span aria-hidden="true">…</span><?php endif; ?><?php endif; ?><?php for ($pagina = $inicioPagina; $pagina <= $finPagina; $pagina++): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . $pagina, ENT_QUOTES, 'UTF-8') ?>" class="<?= $pagina === $paginaActual ? 'current' : '' ?>" <?= $pagina === $paginaActual ? 'aria-current="page"' : '' ?>><?= $pagina ?></a><?php endfor; ?><?php if ($finPagina < $totalPaginas): ?><?php if ($finPagina < $totalPaginas - 1): ?><span aria-hidden="true">…</span><?php endif; ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . $totalPaginas, ENT_QUOTES, 'UTF-8') ?>"><?= $totalPaginas ?></a><?php endif; ?><?php if ($paginaActual < $totalPaginas): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . ($paginaActual + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?></nav><?php endif; ?></section></main><script src="/dev-reload.js"></script></body></html>
