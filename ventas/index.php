<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: /login/login.php'); exit(); }
require_once __DIR__ . '/../csrf.php';
validar_csrf();
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../config_helpers.php';
require_once __DIR__ . '/../permisos.php';
$db = conectar();
exigir_permiso_modulo($db, 'ventas');
$configuracion = cargar_configuracion($db);
$simboloMoneda = simbolo_moneda($configuracion);
$stmt = $db->query("SELECT p.id_producto, p.nombre, COALESCE(SUM(l.cantidad), 0) AS stock FROM productos p LEFT JOIN lotes l ON l.id_producto = p.id_producto AND l.estado = 1 AND l.fecha_vencimiento >= CURDATE() WHERE p.estado = 1 GROUP BY p.id_producto, p.nombre HAVING stock > 0 ORDER BY p.nombre");
$productosConStock = $stmt->fetch_all(MYSQLI_ASSOC);
$presentacionesPorProducto = [];
$stmt = $db->query('SELECT pp.id_producto, pp.id_producto_presentacion, cp.nombre, cp.nivel, pp.unidades_base, pp.precio_venta FROM producto_presentaciones pp INNER JOIN catalogo_presentaciones cp ON cp.id_catalogo_presentacion = pp.id_catalogo_presentacion WHERE pp.estado = 1 ORDER BY cp.nivel DESC, cp.nombre');
foreach ($stmt->fetch_all(MYSQLI_ASSOC) as $presentacion) {
    $presentacion['nombre'] = nombre_presentacion_visible($presentacion['nombre']);
    $presentacionesPorProducto[(int)$presentacion['id_producto']][] = $presentacion;
}
$productos = array_values(array_filter($productosConStock, static fn(array $item): bool => !empty($presentacionesPorProducto[(int)$item['id_producto']])));
foreach ($productos as &$productoDisponible) {
    $productoDisponible['precio_venta'] = (float)$presentacionesPorProducto[(int)$productoDisponible['id_producto']][0]['precio_venta'];
    foreach ($presentacionesPorProducto[(int)$productoDisponible['id_producto']] as &$presentacionVenta) {
        $presentacionVenta['stock_base'] = (int)$productoDisponible['stock'];
    }
    unset($presentacionVenta);
}
unset($productoDisponible);
$presentacionesVentaJson = htmlspecialchars(json_encode($presentacionesPorProducto, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8');
$clientes = $db->query('SELECT id_cliente, nombre FROM clientes WHERE estado = 1 ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$errores = [];
$mensaje = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $productoId = filter_input(INPUT_POST, 'producto', FILTER_VALIDATE_INT);
    $cantidad = filter_var($_POST['cantidad'] ?? '', FILTER_VALIDATE_INT);
    $presentacionId = filter_var($_POST['id_producto_presentacion'] ?? '', FILTER_VALIDATE_INT);
    $clienteId = filter_input(INPUT_POST, 'cliente', FILTER_VALIDATE_INT);
    if ($clienteId === false || $clienteId === 0) $clienteId = null;
    $metodoPago = 'Efectivo';
    if (!$productoId || !array_filter($productos, static fn(array $item): bool => (int)$item['id_producto'] === $productoId)) $errores[] = 'Seleccione un producto con existencias.';
    if (!$presentacionId || !in_array($presentacionId, array_map(static fn(array $item): int => (int)$item['id_producto_presentacion'], $presentacionesPorProducto[$productoId] ?? []), true)) $errores[] = 'Seleccione una presentación configurada para el producto.';
    if ($cantidad === false || $cantidad < 1) $errores[] = 'La cantidad debe ser un entero mayor que 0.';
    if ($clienteId && !in_array($clienteId, array_map('intval', array_column($clientes, 'id_cliente')), true)) $errores[] = 'Seleccione un cliente válido.';
    if (!$errores) {
        try {
            $db->begin_transaction();
            $stmt = $db->prepare('SELECT id_producto FROM productos WHERE id_producto = ? AND estado = 1 FOR UPDATE');
            $stmt->bind_param('i', $productoId);
            $stmt->execute();
            $producto = $stmt->get_result()->fetch_assoc();
            $stmt = $db->prepare('SELECT pp.unidades_base, pp.precio_venta FROM producto_presentaciones pp WHERE pp.id_producto_presentacion = ? AND pp.id_producto = ? AND pp.estado = 1 FOR UPDATE');
            $stmt->bind_param('ii', $presentacionId, $productoId);
            $stmt->execute();
            $presentacionSeleccionada = $stmt->get_result()->fetch_assoc();
            $stmt = $db->prepare('SELECT id_lote, cantidad FROM lotes WHERE id_producto = ? AND estado = 1 AND fecha_vencimiento >= CURDATE() AND cantidad > 0 ORDER BY fecha_vencimiento, id_lote FOR UPDATE');
            $stmt->bind_param('i', $productoId);
            $stmt->execute();
            $lotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $disponible = array_sum(array_column($lotes, 'cantidad'));
            $unidadesBase = (int)($presentacionSeleccionada['unidades_base'] ?? 0);
            if (!$producto || !$presentacionSeleccionada || (float)$presentacionSeleccionada['precio_venta'] <= 0 || $cantidad > intdiv(PHP_INT_MAX, max(1, $unidadesBase)) || $disponible < $cantidad * $unidadesBase) {
                $db->rollback();
                $errores[] = !$presentacionSeleccionada || (float)($presentacionSeleccionada['precio_venta'] ?? 0) <= 0
                    ? 'Configure un precio de venta válido para esa presentación antes de vender.'
                    : 'No hay existencias suficientes en lotes vigentes para la presentación seleccionada.';
            } else {
                $precio = (float)$presentacionSeleccionada['precio_venta'];
                $total = $precio * $cantidad;
                $stmt = $db->prepare('INSERT INTO ventas (id_usuario, id_cliente, total, metodo_pago) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('iids', $_SESSION['usuario_id'], $clienteId, $total, $metodoPago);
                $stmt->execute();
                $ventaId = $db->insert_id;
                $baseTotal = $cantidad * $unidadesBase;
                $baseRestante = $baseTotal;
                $asignaciones = [];
                foreach ($lotes as $lote) {
                    $cantidadLoteBase = min($baseRestante, (int)$lote['cantidad']);
                    if ($cantidadLoteBase > 0) {
                        $asignaciones[] = ['id_lote' => (int)$lote['id_lote'], 'cantidad_base' => $cantidadLoteBase];
                        $baseRestante -= $cantidadLoteBase;
                    }
                    if ($baseRestante === 0) break;
                }
                $primerLoteId = $asignaciones[0]['id_lote'];
                $stmt = $db->prepare('INSERT INTO detalle_ventas (id_venta, id_producto, id_lote, id_producto_presentacion, cantidad, cantidad_base, precio_unitario) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('iiiiiid', $ventaId, $productoId, $primerLoteId, $presentacionId, $cantidad, $baseTotal, $precio);
                $stmt->execute();
                $detalleVentaId = $db->insert_id;
                $stmtAsignacion = $db->prepare('INSERT INTO detalle_ventas_lotes (id_detalle_venta, id_lote, cantidad_base) VALUES (?, ?, ?)');
                $stmtActualizarLote = $db->prepare('UPDATE lotes SET cantidad = cantidad - ? WHERE id_lote = ?');
                foreach ($asignaciones as $asignacion) {
                    $loteId = $asignacion['id_lote'];
                    $cantidadLoteBase = $asignacion['cantidad_base'];
                    $stmtAsignacion->bind_param('iii', $detalleVentaId, $loteId, $cantidadLoteBase);
                    $stmtAsignacion->execute();
                    $stmtActualizarLote->bind_param('ii', $cantidadLoteBase, $loteId);
                    $stmtActualizarLote->execute();
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
    $stmt = $db->prepare("SELECT CONCAT(p.nombre, ' (', COALESCE(cp.nombre, 'Unidad'), ')') AS nombre, dv.cantidad, dv.precio_unitario, dv.subtotal FROM detalle_ventas dv INNER JOIN productos p ON p.id_producto = dv.id_producto LEFT JOIN producto_presentaciones pp ON pp.id_producto_presentacion = dv.id_producto_presentacion LEFT JOIN catalogo_presentaciones cp ON cp.id_catalogo_presentacion = pp.id_catalogo_presentacion WHERE dv.id_venta = ? ORDER BY p.nombre");
    $stmt->bind_param('i', $detalleVenta);
    $stmt->execute();
    $detalle = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($detalle as &$lineaDetalle) {
        $lineaDetalle['nombre'] = nombre_presentacion_visible($lineaDetalle['nombre']);
    }
    unset($lineaDetalle);
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
<script defer src="ventas.js"></script>
<main class="main module-main"><header class="header"><div><h1>Ventas</h1><p>Registro de ventas de la farmacia</p></div><div class="user"><div class="user-avatar">A</div><div><strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($_SESSION['usuario_rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></div></div></header>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php elseif ($mensaje): ?><div class="alert success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<section class="content-box sale-form"><div class="section-header"><div><h2>Nueva venta</h2><p>Selecciona el producto y su presentación, indica la cantidad y revisa el total antes de registrar.</p></div></div><form method="POST" class="inline-form" data-presentations="<?= $presentacionesVentaJson ?>" data-selected-presentation="<?= (int)($_POST['id_producto_presentacion'] ?? 0) ?>"><?= csrf_input() ?>
<label class="sale-product-field">Producto<select name="producto" id="sale-product" required><option value="">Seleccione un producto</option><?php foreach ($productos as $producto): ?><option value="<?= (int)$producto['id_producto'] ?>" <?= (int)($_POST['producto'] ?? 0) === (int)$producto['id_producto'] ? 'selected' : '' ?>><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?> · <?= (int)$producto['stock'] ?> unidades mínimas disponibles</option><?php endforeach; ?></select></label>
<label class="sale-presentation-field">Presentación de venta<select name="id_producto_presentacion" id="sale-presentation" required><option value="">Seleccione primero un producto</option></select></label>
<label>Cantidad<input type="number" name="cantidad" min="1" step="1" value="<?= htmlspecialchars($_POST['cantidad'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label>
<label>Precio unitario (<?= $simboloMoneda ?>)<input id="sale-unit-price" type="number" value="" readonly></label>
<div class="sale-total" aria-live="polite"><span>Total de la venta</span><strong><?= $simboloMoneda ?> <span id="sale-total">0.00</span></strong></div>
<label class="sale-customer-field">Cliente<select name="cliente"><option value="">Público en general</option><?php foreach ($clientes as $cliente): ?><option value="<?= (int)$cliente['id_cliente'] ?>" <?= (int)($_POST['cliente'] ?? 0) === (int)$cliente['id_cliente'] ? 'selected' : '' ?>><?= htmlspecialchars($cliente['nombre'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
<label class="sale-payment-field">Método de pago<input value="Efectivo" readonly><input type="hidden" name="metodo_pago" value="Efectivo"></label>
<button class="btn-primary sale-submit" type="submit" <?= !$productos ? 'disabled' : '' ?>>Registrar venta</button></form></section>
<?php if ($detalleVenta): ?><section class="content-box"><div class="section-header"><div><h2>Detalle de venta #<?= $detalleVenta ?></h2></div><a class="btn-secondary" href="/ventas/">Cerrar</a></div><div class="table-container"><table><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio unitario</th><th>Subtotal</th></tr></thead><tbody><?php foreach ($detalle as $linea): ?><tr><td><?= htmlspecialchars($linea['nombre'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int)$linea['cantidad'] ?></td><td><?= $simboloMoneda ?> <?= number_format((float)$linea['precio_unitario'], 2) ?></td><td><?= $simboloMoneda ?> <?= number_format((float)$linea['subtotal'], 2) ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
<section class="content-box"><div class="section-header"><div><h2>Historial de ventas</h2><p><?= $totalVentas ?> venta(s)<?= $totalVentas ? ' · Página ' . $paginaActual . ' de ' . $totalPaginas . ' · Máximo 15 por página' : '' ?></p></div></div>
<form class="sales-filters" method="GET" data-auto-filter><input type="search" name="buscar_venta" value="<?= htmlspecialchars($buscarVentas, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por cliente o número de venta"><label>Desde<input type="date" name="desde" value="<?= htmlspecialchars($fechaDesdeVentas, ENT_QUOTES, 'UTF-8') ?>"></label><label>Hasta<input type="date" name="hasta" value="<?= htmlspecialchars($fechaHastaVentas, ENT_QUOTES, 'UTF-8') ?>"></label><a class="btn-secondary" href="/ventas/">Limpiar</a></form>
<?php if ($errorFiltroVentas): ?><div class="alert error">La fecha inicial no puede ser posterior a la fecha final.</div><?php endif; ?>
<div class="table-container"><table><thead><tr><th>Fecha</th><th>Cliente</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead><tbody><?php if (!$ventas): ?><tr><td colspan="5" class="empty-state">No hay ventas que coincidan con esos filtros.</td></tr><?php endif; ?><?php foreach ($ventas as $venta): ?><tr><td><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></td><td><?= htmlspecialchars($venta['cliente'], ENT_QUOTES, 'UTF-8') ?></td><td><?= $simboloMoneda ?> <?= number_format((float)$venta['total'], 2) ?></td><td><span class="status completed"><?= htmlspecialchars($venta['estado'], ENT_QUOTES, 'UTF-8') ?></span></td><td><a class="link-button" href="/ventas/?detalle=<?= (int)$venta['id_venta'] ?>">Ver</a></td></tr><?php endforeach; ?></tbody></table></div><?php if ($totalPaginas > 1): ?><nav class="pagination" aria-label="Paginación de ventas"><?php if ($paginaActual > 1): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . ($paginaActual - 1), ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?><?php if ($inicioPagina > 1): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=1', ENT_QUOTES, 'UTF-8') ?>">1</a><?php if ($inicioPagina > 2): ?><span aria-hidden="true">…</span><?php endif; ?><?php endif; ?><?php for ($pagina = $inicioPagina; $pagina <= $finPagina; $pagina++): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . $pagina, ENT_QUOTES, 'UTF-8') ?>" class="<?= $pagina === $paginaActual ? 'current' : '' ?>" <?= $pagina === $paginaActual ? 'aria-current="page"' : '' ?>><?= $pagina ?></a><?php endfor; ?><?php if ($finPagina < $totalPaginas): ?><?php if ($finPagina < $totalPaginas - 1): ?><span aria-hidden="true">…</span><?php endif; ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . $totalPaginas, ENT_QUOTES, 'UTF-8') ?>"><?= $totalPaginas ?></a><?php endif; ?><?php if ($paginaActual < $totalPaginas): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . ($paginaActual + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?></nav><?php endif; ?></section></main><script src="/dev-reload.js"></script></body></html>
