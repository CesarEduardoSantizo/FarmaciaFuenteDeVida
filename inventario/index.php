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
$db = conectar();
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
    header('Location: /inventario/?eliminado=1');
    exit();
}
$mensaje = isset($_GET['eliminado']) ? 'Producto desactivado; sus movimientos históricos se conservaron.' : '';

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
            <div class="section-header"><div><h2>Inventario</h2><p><?= $totalProductos ?> producto(s)<?= $totalProductos ? ' · Página ' . $paginaActual . ' de ' . $totalPaginas : '' ?></p></div><a class="btn-primary" href="/productos/">+ Agregar producto</a></div>
            <form class="search inventory-filters" method="GET" data-auto-filter><input type="search" name="buscar" value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre o código"><select name="categoria" aria-label="Filtrar por categoría"><option value="0">Todas las categorías</option><?php foreach ($categorias as $categoria): ?><option value="<?= (int)$categoria['id_categoria'] ?>" <?= $categoriaFiltro === (int)$categoria['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><select name="stock" aria-label="Filtrar por estado de stock"><option value="todos" <?= $filtroStock === 'todos' ? 'selected' : '' ?>>Todo el stock</option><option value="bajo" <?= $filtroStock === 'bajo' ? 'selected' : '' ?>>Stock bajo</option><option value="disponible" <?= $filtroStock === 'disponible' ? 'selected' : '' ?>>Stock suficiente</option></select><a class="clear-filters" href="/inventario/">Limpiar</a></form>
            <?php if ($mensaje): ?><div class="alert success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <div class="table-container"><table><thead><tr><th>Producto</th><th>Categoría</th><th>Stock</th><th>Precio</th><th>Vencimiento</th><th>Acciones</th></tr></thead><tbody>
            <?php if (!$productos): ?><tr><td class="empty-state" colspan="6">No se encontraron productos.</td></tr><?php endif; ?>
            <?php foreach ($productos as $producto): ?><tr><td><strong><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td><td><?= htmlspecialchars($producto['categoria'], ENT_QUOTES, 'UTF-8') ?></td><td><span class="stock <?= (int)$producto['stock'] <= max((int)$producto['stock_minimo'], $umbralStock) ? 'low' : 'normal' ?>"><?= (int)$producto['stock'] ?></span></td><td><?= $simboloMoneda ?> <?= number_format((float)$producto['precio'], 2) ?></td><td><?= $producto['vencimiento'] ? date('d/m/Y', strtotime($producto['vencimiento'])) : 'Sin lote' ?></td><td class="actions"><a class="edit" href="/productos/?id=<?= (int)$producto['id'] ?>">Editar</a><form method="POST" onsubmit="<?= confirmar_eliminaciones($configuracion) ? "return confirm('¿Desactivar este producto? Se conservarán sus movimientos históricos.');" : '' ?>">
<?= csrf_input() ?>
<input type="hidden" name="accion" value="desactivar">
<input type="hidden" name="id_producto" value="<?= (int)$producto['id'] ?>">
<button class="delete" type="submit">Eliminar</button>
</form></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php if ($totalPaginas > 1): ?><nav class="pagination" aria-label="Paginación del inventario"><?php if ($paginaActual > 1): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . ($paginaActual - 1), ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?><?php if ($inicioPagina > 1): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=1', ENT_QUOTES, 'UTF-8') ?>">1</a><?php if ($inicioPagina > 2): ?><span aria-hidden="true">…</span><?php endif; ?><?php endif; ?><?php for ($pagina = $inicioPagina; $pagina <= $finPagina; $pagina++): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . $pagina, ENT_QUOTES, 'UTF-8') ?>" class="<?= $pagina === $paginaActual ? 'current' : '' ?>" <?= $pagina === $paginaActual ? 'aria-current="page"' : '' ?>><?= $pagina ?></a><?php endfor; ?><?php if ($finPagina < $totalPaginas): ?><?php if ($finPagina < $totalPaginas - 1): ?><span aria-hidden="true">…</span><?php endif; ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . $totalPaginas, ENT_QUOTES, 'UTF-8') ?>"><?= $totalPaginas ?></a><?php endif; ?><?php if ($paginaActual < $totalPaginas): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . ($paginaActual + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?></nav><?php endif; ?>
        </section>
    </main>
    <script src="/dev-reload.js"></script>
</body>
</html>
