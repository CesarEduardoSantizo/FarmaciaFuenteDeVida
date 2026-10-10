<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login/login.php');
    exit();
}
require_once __DIR__ . '/../csrf.php';
validar_csrf();
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../config_helpers.php';
require_once __DIR__ . '/../permisos.php';
$db = conectar();
exigir_permiso_modulo($db, 'inventario');
$configuracion = cargar_configuracion($db);
$umbralStock = umbral_stock($configuracion);
$simboloMoneda = simbolo_moneda($configuracion);
$mensaje = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['accion'] ?? '') === 'desactivar') {
    $productoId = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
    if ($productoId) {
        $db->begin_transaction();
        $stmt = $db->prepare('UPDATE productos SET estado = 0 WHERE id_producto = ?');
        $stmt->bind_param('i', $productoId);
        $stmt->execute();
        $stmt = $db->prepare('UPDATE lotes SET estado = 0 WHERE id_producto = ?');
        $stmt->bind_param('i', $productoId);
        $stmt->execute();
        $db->commit();
    }
    header('Location: /inventario/?vista=existencias&eliminado=1');
    exit();
}
$mensaje = isset($_GET['eliminado']) ? 'Producto desactivado; sus movimientos históricos se conservaron.' : '';
$vistaInventario = $_GET['vista'] ?? 'existencias';
if ($vistaInventario === 'entradas') $vistaInventario = 'movimientos';
if (!in_array($vistaInventario, ['existencias', 'movimientos'], true)) $vistaInventario = 'existencias';

$busqueda = trim($_GET['buscar'] ?? '');
$paginaSolicitada = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$registrosPorPagina = 15;
$categoriaFiltro = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT) ?: 0;
$filtroStock = $_GET['stock'] ?? 'todos';
if (!in_array($filtroStock, ['todos', 'bajo', 'disponible'], true)) $filtroStock = 'todos';
$categorias = $db->query('SELECT id_categoria, nombre FROM categorias WHERE estado = 1 ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$filtroCategoriaSql = $categoriaFiltro ? ' AND p.id_categoria = ' . (int)$categoriaFiltro : '';
$filtroStockSql = match ($filtroStock) {
    'bajo' => ' HAVING stock <= GREATEST(stock_minimo, ' . $umbralStock . ')',
    'disponible' => ' HAVING stock > GREATEST(stock_minimo, ' . $umbralStock . ')',
    default => '',
};
$fromInventario = " FROM productos p INNER JOIN categorias c ON c.id_categoria = p.id_categoria LEFT JOIN lotes l ON l.id_producto = p.id_producto AND l.estado = 1 WHERE p.estado = 1 AND (p.nombre LIKE CONCAT('%', ?, '%') OR p.codigo LIKE CONCAT('%', ?, '%') OR c.nombre LIKE CONCAT('%', ?, '%')){$filtroCategoriaSql} GROUP BY p.id_producto, p.nombre, c.nombre, p.precio_venta, p.stock_minimo{$filtroStockSql}";
$stmt = $db->prepare('SELECT COUNT(*) FROM (SELECT p.id_producto, COALESCE(SUM(l.cantidad), 0) AS stock' . $fromInventario . ') AS inventario_filtrado');
$stmt->bind_param('sss', $busqueda, $busqueda, $busqueda);
$stmt->execute();
$totalProductos = (int)$stmt->get_result()->fetch_row()[0];
$totalPaginas = max(1, (int)ceil($totalProductos / $registrosPorPagina));
$paginaActual = min(max(1, $paginaSolicitada), $totalPaginas);
$offset = ($paginaActual - 1) * $registrosPorPagina;
$inicioPagina = max(1, $paginaActual - 2);
$finPagina = min($totalPaginas, $paginaActual + 2);
$parametrosPaginacion = ['buscar' => $busqueda, 'categoria' => $categoriaFiltro, 'stock' => $filtroStock];
$urlBasePaginacion = '/inventario/?' . http_build_query($parametrosPaginacion);
$sqlInventario = 'SELECT p.id_producto AS id, p.nombre, c.nombre AS categoria, p.precio_venta AS precio, p.stock_minimo, COALESCE(SUM(l.cantidad), 0) AS stock, MIN(CASE WHEN l.cantidad > 0 THEN l.fecha_vencimiento END) AS vencimiento' . $fromInventario . ' ORDER BY p.nombre LIMIT ? OFFSET ?';
$stmt = $db->prepare($sqlInventario);
$stmt->bind_param('sssii', $busqueda, $busqueda, $busqueda, $registrosPorPagina, $offset);
$stmt->execute();
$productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$presentacionesInventario = [];
$stmt = $db->query('SELECT pp.id_producto, cp.nombre, cp.nivel, pp.unidades_base FROM producto_presentaciones pp INNER JOIN catalogo_presentaciones cp ON cp.id_catalogo_presentacion = pp.id_catalogo_presentacion WHERE pp.estado = 1 ORDER BY pp.id_producto, cp.nivel DESC');
foreach ($stmt->fetch_all(MYSQLI_ASSOC) as $presentacionInventario) {
    $presentacionInventario['nombre'] = nombre_presentacion_visible($presentacionInventario['nombre']);
    $presentacionesInventario[(int)$presentacionInventario['id_producto']][] = $presentacionInventario;
}
$desgloseInventario = [];
$equivalenciasInventario = [];
foreach ($productos as $productoInventario) {
    $restante = (int)$productoInventario['stock'];
    if ($restante === 0) {
        $desgloseInventario[(int)$productoInventario['id']] = 'Sin existencias';
        continue;
    }
    $partes = [];
    $equivalencias = [];
    foreach ($presentacionesInventario[(int)$productoInventario['id']] ?? [] as $presentacionInventario) {
        $unidadesBase = max(1, (int)$presentacionInventario['unidades_base']);
        $cantidadPresentacion = intdiv($restante, $unidadesBase);
        $restante %= $unidadesBase;
        $nombrePresentacion = $presentacionInventario['nombre'];
        $pluralPresentacion = preg_match('/[aeiouáéíóú]$/iu', $nombrePresentacion) ? $nombrePresentacion . 's' : $nombrePresentacion . 'es';
        if ($cantidadPresentacion > 0) {
            $partes[] = number_format($cantidadPresentacion, 0, '.', ',') . ' ' . ($cantidadPresentacion === 1 ? $nombrePresentacion : $pluralPresentacion);
        }
        $totalEnPresentacion = intdiv((int)$productoInventario['stock'], $unidadesBase);
        $equivalencias[] = number_format($totalEnPresentacion, 0, '.', ',') . ' ' . ($totalEnPresentacion === 1 ? $nombrePresentacion : $pluralPresentacion);
    }
    $idProductoInventario = (int)$productoInventario['id'];
    $desgloseInventario[$idProductoInventario] = $partes ? implode(' · ', $partes) : number_format((int)$productoInventario['stock'], 0, '.', ',') . ' unidades';
    $equivalenciasInventario[$idProductoInventario] = $equivalencias;
}

$buscarMovimiento = trim($_GET['buscar_movimiento'] ?? $_GET['buscar_entrada'] ?? '');
$fechaDesdeMovimiento = trim($_GET['desde_movimiento'] ?? $_GET['desde_entrada'] ?? '');
$fechaHastaMovimiento = trim($_GET['hasta_movimiento'] ?? $_GET['hasta_entrada'] ?? '');
foreach (['fechaDesdeMovimiento', 'fechaHastaMovimiento'] as $campoFecha) {
    if ($$campoFecha !== '') {
        $fechaValidada = DateTime::createFromFormat('Y-m-d', $$campoFecha);
        if (!$fechaValidada || $fechaValidada->format('Y-m-d') !== $$campoFecha) $$campoFecha = '';
    }
}
$tipoMovimiento = $_GET['tipo_movimiento'] ?? 'todos';
if (!in_array($tipoMovimiento, ['todos', 'entrada', 'salida'], true)) $tipoMovimiento = 'todos';
$paginaMovimientosSolicitada = filter_input(INPUT_GET, 'pagina_movimientos', FILTER_VALIDATE_INT) ?: 1;
$consultaMovimientos = "
    SELECT 'entrada' AS tipo, c.fecha_compra AS fecha, p.nombre AS producto,
           COALESCE(cp.nombre, p.presentacion, 'Unidad') AS presentacion,
           dc.cantidad, COALESCE(dc.cantidad_base, dc.cantidad) AS cantidad_base,
           pr.nombre AS contraparte,
           CONCAT('Factura: ', COALESCE(NULLIF(c.numero_factura, ''), 'Sin número')) AS referencia,
           l.numero_lote AS lote, dc.subtotal AS importe
    FROM detalle_compras dc
    INNER JOIN compras c ON c.id_compra = dc.id_compra
    INNER JOIN productos p ON p.id_producto = dc.id_producto
    INNER JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
    INNER JOIN lotes l ON l.id_lote = dc.id_lote
    LEFT JOIN producto_presentaciones pp ON pp.id_producto_presentacion = dc.id_producto_presentacion
    LEFT JOIN catalogo_presentaciones cp ON cp.id_catalogo_presentacion = pp.id_catalogo_presentacion
    WHERE c.estado = 'Completada'
    UNION ALL
    SELECT 'salida' AS tipo, v.fecha_venta AS fecha, p.nombre AS producto,
           COALESCE(cp.nombre, p.presentacion, 'Unidad') AS presentacion,
           dv.cantidad, COALESCE(dv.cantidad_base, dv.cantidad) AS cantidad_base,
           COALESCE(cl.nombre, 'Venta mostrador') AS contraparte,
           CONCAT('Venta #', v.id_venta) AS referencia,
           COALESCE(
               (SELECT GROUP_CONCAT(DISTINCT lote_mov.numero_lote ORDER BY lote_mov.numero_lote SEPARATOR ', ')
                FROM detalle_ventas_lotes dvl
                INNER JOIN lotes lote_mov ON lote_mov.id_lote = dvl.id_lote
                WHERE dvl.id_detalle_venta = dv.id_detalle_venta),
               lote_principal.numero_lote
           ) AS lote,
           dv.subtotal AS importe
    FROM detalle_ventas dv
    INNER JOIN ventas v ON v.id_venta = dv.id_venta
    INNER JOIN productos p ON p.id_producto = dv.id_producto
    INNER JOIN lotes lote_principal ON lote_principal.id_lote = dv.id_lote
    LEFT JOIN clientes cl ON cl.id_cliente = v.id_cliente
    LEFT JOIN producto_presentaciones pp ON pp.id_producto_presentacion = dv.id_producto_presentacion
    LEFT JOIN catalogo_presentaciones cp ON cp.id_catalogo_presentacion = pp.id_catalogo_presentacion
    WHERE v.estado = 'Completada'
";
$filtroMovimientos = " WHERE (? = 'todos' OR tipo = ?) AND
    (producto LIKE CONCAT('%', ?, '%') OR presentacion LIKE CONCAT('%', ?, '%')
     OR contraparte LIKE CONCAT('%', ?, '%') OR referencia LIKE CONCAT('%', ?, '%')
     OR lote LIKE CONCAT('%', ?, '%'))
    AND (? = '' OR fecha >= CONCAT(?, ' 00:00:00'))
    AND (? = '' OR fecha < DATE_ADD(?, INTERVAL 1 DAY))";
$parametrosMovimientos = [
    $tipoMovimiento, $tipoMovimiento,
    $buscarMovimiento, $buscarMovimiento, $buscarMovimiento, $buscarMovimiento, $buscarMovimiento,
    $fechaDesdeMovimiento, $fechaDesdeMovimiento, $fechaHastaMovimiento, $fechaHastaMovimiento,
];
$stmt = $db->prepare('SELECT COUNT(*) FROM (' . $consultaMovimientos . ') movimientos' . $filtroMovimientos);
$stmt->bind_param('sssssssssss', ...$parametrosMovimientos);
$stmt->execute();
$totalMovimientos = (int)$stmt->get_result()->fetch_row()[0];
$totalPaginasMovimientos = max(1, (int)ceil($totalMovimientos / $registrosPorPagina));
$paginaMovimientos = min(max(1, $paginaMovimientosSolicitada), $totalPaginasMovimientos);
$offsetMovimientos = ($paginaMovimientos - 1) * $registrosPorPagina;
$inicioPaginaMovimientos = max(1, $paginaMovimientos - 2);
$finPaginaMovimientos = min($totalPaginasMovimientos, $paginaMovimientos + 2);
$stmt = $db->prepare('SELECT tipo, fecha, producto, presentacion, cantidad, cantidad_base, contraparte, referencia, lote, importe FROM (' . $consultaMovimientos . ') movimientos' . $filtroMovimientos . ' ORDER BY fecha DESC LIMIT ? OFFSET ?');
$parametrosMovimientosPaginado = [...$parametrosMovimientos, $registrosPorPagina, $offsetMovimientos];
$stmt->bind_param('sssssssssssii', ...$parametrosMovimientosPaginado);
$stmt->execute();
$movimientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($movimientos as &$movimiento) {
    $movimiento['presentacion'] = nombre_presentacion_visible($movimiento['presentacion']);
}
unset($movimiento);
$urlBaseMovimientos = '/inventario/?' . http_build_query([
    'vista' => 'movimientos',
    'tipo_movimiento' => $tipoMovimiento,
    'buscar_movimiento' => $buscarMovimiento,
    'desde_movimiento' => $fechaDesdeMovimiento,
    'hasta_movimiento' => $fechaHastaMovimiento,
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario | Farmacia Fuente de Vida</title>
    <link rel="stylesheet" href="../menu.css">
    <link rel="stylesheet" href="inventario.css">
</head>
<body>
    <?php include __DIR__ . '/../SideBar/menu.php'; ?>

    <main class="main module-main">
        <header class="header"><div><h1>Inventario</h1><p>Productos registrados en existencia</p></div><div class="user"><div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'U', 0, 1)), ENT_QUOTES, 'UTF-8') ?></div><div><strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($_SESSION['usuario_rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></div></div></header>
        <section class="content-box">
            <nav class="inventory-tabs" aria-label="Secciones del inventario">
                <a href="/inventario/?vista=existencias" class="<?= $vistaInventario === 'existencias' ? 'current' : '' ?>" <?= $vistaInventario === 'existencias' ? 'aria-current="page"' : '' ?>>Existencias</a>
                <a href="/inventario/?vista=movimientos" class="<?= $vistaInventario === 'movimientos' ? 'current' : '' ?>" <?= $vistaInventario === 'movimientos' ? 'aria-current="page"' : '' ?>>Movimientos</a>
            </nav>
            <?php if ($vistaInventario === 'existencias'): ?>
            <div class="section-header"><div><h2>Inventario</h2><p><?= $totalProductos ?> producto(s)<?= $totalProductos ? ' · Página ' . $paginaActual . ' de ' . $totalPaginas : '' ?></p></div><a class="btn-primary" href="/productos/">+ Agregar producto</a></div>
            <form class="search inventory-filters" method="GET" data-auto-filter><input type="hidden" name="vista" value="existencias"><input type="search" name="buscar" value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre o código"><select name="categoria" aria-label="Filtrar por categoría"><option value="0">Todas las categorías</option><?php foreach ($categorias as $categoria): ?><option value="<?= (int)$categoria['id_categoria'] ?>" <?= $categoriaFiltro === (int)$categoria['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><select name="stock" aria-label="Filtrar por estado de stock"><option value="todos" <?= $filtroStock === 'todos' ? 'selected' : '' ?>>Todo el stock</option><option value="bajo" <?= $filtroStock === 'bajo' ? 'selected' : '' ?>>Stock bajo</option><option value="disponible" <?= $filtroStock === 'disponible' ? 'selected' : '' ?>>Stock suficiente</option></select><a class="clear-filters" href="/inventario/?vista=existencias">Limpiar</a></form>
            <?php if ($mensaje): ?><div class="alert success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <div class="table-container"><table><thead><tr><th>Producto</th><th>Categoría</th><th>Existencias (desglose)</th><th>Precio</th><th>Vencimiento</th><th>Acciones</th></tr></thead><tbody>
            <?php if (!$productos): ?><tr><td class="empty-state" colspan="6">No se encontraron productos.</td></tr><?php endif; ?>
            <?php foreach ($productos as $producto): ?><tr><td><strong><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td><td><?= htmlspecialchars($producto['categoria'], ENT_QUOTES, 'UTF-8') ?></td><td><div class="inventory-stock"><span class="stock <?= (int)$producto['stock'] <= max((int)$producto['stock_minimo'], $umbralStock) ? 'low' : 'normal' ?>"><?= htmlspecialchars($desgloseInventario[(int)$producto['id']] ?? ((int)$producto['stock'] . ' unidades'), ENT_QUOTES, 'UTF-8') ?></span><?php if (!empty($equivalenciasInventario[(int)$producto['id']])): ?><small>Equivale a: <?= htmlspecialchars(implode(' · ', $equivalenciasInventario[(int)$producto['id']]), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></div></td><td><?= $simboloMoneda ?> <?= number_format((float)$producto['precio'], 2) ?></td><td><?= $producto['vencimiento'] ? date('d/m/Y', strtotime($producto['vencimiento'])) : 'Sin lote' ?></td><td class="actions"><a class="edit" href="/productos/?id=<?= (int)$producto['id'] ?>">Editar</a><form method="POST" onsubmit="<?= confirmar_eliminaciones($configuracion) ? "return confirm('¿Desactivar este producto? Se conservarán sus movimientos históricos.');" : '' ?>">
<?= csrf_input() ?>
<input type="hidden" name="accion" value="desactivar">
<input type="hidden" name="id_producto" value="<?= (int)$producto['id'] ?>">
<button class="delete" type="submit">Eliminar</button>
</form></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php if ($totalPaginas > 1): ?><nav class="pagination" aria-label="Paginación del inventario"><?php if ($paginaActual > 1): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . ($paginaActual - 1), ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?><?php if ($inicioPagina > 1): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=1', ENT_QUOTES, 'UTF-8') ?>">1</a><?php if ($inicioPagina > 2): ?><span aria-hidden="true">…</span><?php endif; ?><?php endif; ?><?php for ($pagina = $inicioPagina; $pagina <= $finPagina; $pagina++): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . $pagina, ENT_QUOTES, 'UTF-8') ?>" class="<?= $pagina === $paginaActual ? 'current' : '' ?>" <?= $pagina === $paginaActual ? 'aria-current="page"' : '' ?>><?= $pagina ?></a><?php endfor; ?><?php if ($finPagina < $totalPaginas): ?><?php if ($finPagina < $totalPaginas - 1): ?><span aria-hidden="true">…</span><?php endif; ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . $totalPaginas, ENT_QUOTES, 'UTF-8') ?>"><?= $totalPaginas ?></a><?php endif; ?><?php if ($paginaActual < $totalPaginas): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . ($paginaActual + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?></nav><?php endif; ?>
            <?php else: ?>
            <div class="section-header"><div><h2>Movimientos de inventario</h2><p><?= $totalMovimientos ?> movimiento(s)<?= $totalMovimientos ? ' · Página ' . $paginaMovimientos . ' de ' . $totalPaginasMovimientos : '' ?>. Las compras se muestran como entradas y las ventas como salidas.</p></div><a class="btn-primary" href="/compras/">Registrar compra</a></div>
            <form class="search inventory-entry-filters" method="GET" data-auto-filter><input type="hidden" name="vista" value="movimientos"><input type="search" name="buscar_movimiento" value="<?= htmlspecialchars($buscarMovimiento, ENT_QUOTES, 'UTF-8') ?>" placeholder="Producto, proveedor, cliente, venta o lote"><select name="tipo_movimiento" aria-label="Filtrar por tipo de movimiento"><option value="todos" <?= $tipoMovimiento === 'todos' ? 'selected' : '' ?>>Entradas y salidas</option><option value="entrada" <?= $tipoMovimiento === 'entrada' ? 'selected' : '' ?>>Solo entradas</option><option value="salida" <?= $tipoMovimiento === 'salida' ? 'selected' : '' ?>>Solo salidas</option></select><label>Desde<input type="date" name="desde_movimiento" value="<?= htmlspecialchars($fechaDesdeMovimiento, ENT_QUOTES, 'UTF-8') ?>"></label><label>Hasta<input type="date" name="hasta_movimiento" value="<?= htmlspecialchars($fechaHastaMovimiento, ENT_QUOTES, 'UTF-8') ?>"></label><a class="clear-filters" href="/inventario/?vista=movimientos">Limpiar</a></form>
            <div class="table-container"><table class="entries-table"><thead><tr><th>Fecha</th><th>Movimiento</th><th>Producto</th><th>Cantidad</th><th>Equivalencia mínima</th><th>Proveedor / cliente</th><th>Referencia / lote</th><th><?= $tipoMovimiento === 'salida' ? 'Total de venta' : ($tipoMovimiento === 'entrada' ? 'Costo de entrada' : 'Importe') ?></th></tr></thead><tbody>
            <?php if (!$movimientos): ?><tr><td class="empty-state" colspan="8">No hay movimientos registrados que coincidan con esos filtros.</td></tr><?php endif; ?>
            <?php foreach ($movimientos as $movimiento): ?><tr><td><?= date('d/m/Y H:i', strtotime($movimiento['fecha'])) ?></td><td><span class="movement-badge <?= $movimiento['tipo'] === 'entrada' ? 'movement-entry' : 'movement-exit' ?>"><?= $movimiento['tipo'] === 'entrada' ? 'Entrada · Compra' : 'Salida · Venta' ?></span></td><td><strong><?= htmlspecialchars($movimiento['producto'], ENT_QUOTES, 'UTF-8') ?></strong><small class="entry-secondary"><?= htmlspecialchars($movimiento['presentacion'], ENT_QUOTES, 'UTF-8') ?></small></td><td><?= number_format((int)$movimiento['cantidad'], 0, '.', ',') ?> <?= htmlspecialchars($movimiento['presentacion'], ENT_QUOTES, 'UTF-8') ?></td><td><?= number_format((int)$movimiento['cantidad_base'], 0, '.', ',') ?> unidades</td><td><?= htmlspecialchars($movimiento['contraparte'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($movimiento['referencia'], ENT_QUOTES, 'UTF-8') ?><small class="entry-secondary">Lote(s): <?= htmlspecialchars($movimiento['lote'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?></small></td><td><?= $simboloMoneda ?> <?= number_format((float)$movimiento['importe'], 2) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php if ($totalPaginasMovimientos > 1): ?><nav class="pagination" aria-label="Paginación de movimientos"><?php if ($paginaMovimientos > 1): ?><a href="<?= htmlspecialchars($urlBaseMovimientos . '&pagina_movimientos=' . ($paginaMovimientos - 1), ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?><?php if ($inicioPaginaMovimientos > 1): ?><a href="<?= htmlspecialchars($urlBaseMovimientos . '&pagina_movimientos=1', ENT_QUOTES, 'UTF-8') ?>">1</a><?php if ($inicioPaginaMovimientos > 2): ?><span aria-hidden="true">…</span><?php endif; ?><?php endif; ?><?php for ($pagina = $inicioPaginaMovimientos; $pagina <= $finPaginaMovimientos; $pagina++): ?><a href="<?= htmlspecialchars($urlBaseMovimientos . '&pagina_movimientos=' . $pagina, ENT_QUOTES, 'UTF-8') ?>" class="<?= $pagina === $paginaMovimientos ? 'current' : '' ?>" <?= $pagina === $paginaMovimientos ? 'aria-current="page"' : '' ?>><?= $pagina ?></a><?php endfor; ?><?php if ($finPaginaMovimientos < $totalPaginasMovimientos): ?><?php if ($finPaginaMovimientos < $totalPaginasMovimientos - 1): ?><span aria-hidden="true">…</span><?php endif; ?><a href="<?= htmlspecialchars($urlBaseMovimientos . '&pagina_movimientos=' . $totalPaginasMovimientos, ENT_QUOTES, 'UTF-8') ?>"><?= $totalPaginasMovimientos ?></a><?php endif; ?><?php if ($paginaMovimientos < $totalPaginasMovimientos): ?><a href="<?= htmlspecialchars($urlBaseMovimientos . '&pagina_movimientos=' . ($paginaMovimientos + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?></nav><?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
    <script src="/dev-reload.js"></script>
</body>
</html>
