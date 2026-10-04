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
$errores = [];
$mensaje = isset($_GET['registrada']) ? 'Compra registrada y existencias actualizadas.' : '';
$proveedores = $db->query('SELECT id_proveedor, nombre FROM proveedores WHERE estado = 1 ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$productos = $db->query('SELECT id_producto, nombre FROM productos WHERE estado = 1 ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $proveedorId = filter_input(INPUT_POST, 'id_proveedor', FILTER_VALIDATE_INT);
    $productoId = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
    $cantidad = filter_var($_POST['cantidad'] ?? '', FILTER_VALIDATE_INT);
    $numeroLote = trim($_POST['numero_lote'] ?? '');
    $vencimiento = trim($_POST['fecha_vencimiento'] ?? '');
    $costo = trim($_POST['costo_unitario'] ?? '');
    $factura = trim($_POST['numero_factura'] ?? '');
    if (!$proveedorId || !in_array($proveedorId, array_map('intval', array_column($proveedores, 'id_proveedor')), true)) $errores[] = 'Seleccione un proveedor válido.';
    if (!$productoId || !in_array($productoId, array_map('intval', array_column($productos, 'id_producto')), true)) $errores[] = 'Seleccione un producto válido.';
    if ($cantidad === false || $cantidad < 1) $errores[] = 'La cantidad debe ser un entero mayor que cero.';
    if ($numeroLote === '') $errores[] = 'Ingrese el número de lote.';
    $fecha = DateTime::createFromFormat('Y-m-d', $vencimiento);
    if (!$fecha || $fecha->format('Y-m-d') !== $vencimiento) $errores[] = 'Ingrese una fecha de vencimiento válida.';
    if (!is_numeric($costo) || (float)$costo <= 0) $errores[] = 'El costo unitario debe ser mayor que cero.';
    if (!$errores) {
        try {
            $db->begin_transaction();
            $stmt = $db->prepare('SELECT id_lote FROM lotes WHERE id_producto = ? AND numero_lote = ? FOR UPDATE');
            $stmt->bind_param('is', $productoId, $numeroLote);
            $stmt->execute();
            $loteExistente = $stmt->get_result()->fetch_assoc();
            if ($loteExistente) {
                $loteId = (int)$loteExistente['id_lote'];
            } else {
                $stmt = $db->prepare('INSERT INTO lotes (id_producto, numero_lote, fecha_vencimiento, cantidad, costo_unitario) VALUES (?, ?, ?, 0, ?)');
                $stmt->bind_param('issd', $productoId, $numeroLote, $vencimiento, $costo);
                $stmt->execute();
                $loteId = $db->insert_id;
            }
            $stmt = $db->prepare('INSERT INTO compras (id_proveedor, id_usuario, numero_factura, total) VALUES (?, ?, ?, 0)');
            $stmt->bind_param('iis', $proveedorId, $_SESSION['usuario_id'], $factura);
            $stmt->execute();
            $compraId = $db->insert_id;
            $stmt = $db->prepare('INSERT INTO detalle_compras (id_compra, id_producto, id_lote, cantidad, costo_unitario) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('iiiid', $compraId, $productoId, $loteId, $cantidad, $costo);
            $stmt->execute();
            $total = $cantidad * (float)$costo;
            $stmt = $db->prepare('UPDATE compras SET total = ? WHERE id_compra = ?');
            $stmt->bind_param('di', $total, $compraId);
            $stmt->execute();
            $stmt = $db->prepare('UPDATE lotes SET costo_unitario = ?, fecha_vencimiento = ?, estado = 1 WHERE id_lote = ?');
            $stmt->bind_param('dsi', $costo, $vencimiento, $loteId);
            $stmt->execute();
            $db->commit();
            header('Location: /compras/?registrada=' . $compraId);
            exit();
        } catch (mysqli_sql_exception $e) {
            $db->rollback();
            $errores[] = $e->getCode() === 1062 ? 'Ese número de lote ya existe para el producto seleccionado.' : 'No se pudo registrar la compra. Verifique los datos.';
        }
    }
}
$paginaSolicitada = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$registrosPorPagina = 15;
$totalCompras = (int)$db->query('SELECT COUNT(*) FROM compras')->fetch_row()[0];
$totalPaginas = max(1, (int)ceil($totalCompras / $registrosPorPagina));
$paginaActual = min(max(1, $paginaSolicitada), $totalPaginas);
$offset = ($paginaActual - 1) * $registrosPorPagina;
$inicioPagina = max(1, $paginaActual - 2);
$finPagina = min($totalPaginas, $paginaActual + 2);
$stmt = $db->prepare('SELECT c.id_compra, c.fecha_compra, c.numero_factura, c.total, p.nombre AS proveedor, COUNT(dc.id_detalle_compra) AS lineas FROM compras c INNER JOIN proveedores p ON p.id_proveedor = c.id_proveedor LEFT JOIN detalle_compras dc ON dc.id_compra = c.id_compra GROUP BY c.id_compra, c.fecha_compra, c.numero_factura, c.total, p.nombre ORDER BY c.fecha_compra DESC LIMIT ? OFFSET ?');
$stmt->bind_param('ii', $registrosPorPagina, $offset);
$stmt->execute();
$compras = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$resumenCompras = $db->query("SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS gasto_total FROM compras WHERE estado = 'Completada'")->fetch_assoc();
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Compras | Farmacia Fuente de Vida</title><link rel="stylesheet" href="../menu.css"><link rel="stylesheet" href="compras.css"></head><body>
<?php include __DIR__ . '/../SideBar/menu.php'; ?>
<main class="main module-main"><header class="header"><div><h1>Compras de medicamentos</h1><p>Registra el gasto de productos comprados a proveedores y su entrada al inventario.</p></div><div class="user"><div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['usuario_nombre'] ?? 'U', 0, 1)), ENT_QUOTES, 'UTF-8') ?></div><div><strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($_SESSION['usuario_rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></div></div></header>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php elseif ($mensaje): ?><div class="alert success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<section class="content-box purchase-form-box"><div class="section-header"><div><h2>Registrar compra</h2><p>El total se calcula con la cantidad y el costo unitario. Al guardar, se agrega al inventario.</p></div></div><form method="POST" class="provider-form"><?= csrf_input() ?>
<label>Proveedor<select name="id_proveedor" required><option value="">Seleccione</option><?php foreach ($proveedores as $proveedor): ?><option value="<?= (int)$proveedor['id_proveedor'] ?>"><?= htmlspecialchars($proveedor['nombre'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
<label>Producto<select name="id_producto" required><option value="">Seleccione</option><?php foreach ($productos as $producto): ?><option value="<?= (int)$producto['id_producto'] ?>"><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
<label>Número de factura<input name="numero_factura"></label>
<label>Número de lote<input name="numero_lote" required></label>
<label>Vencimiento<input type="date" name="fecha_vencimiento" required></label>
<label>Cantidad<input id="cantidad-compra" type="number" name="cantidad" min="1" step="1" required></label>
<label>Costo unitario (<?= $simboloMoneda ?>)<input id="costo-compra" type="number" name="costo_unitario" min="0.01" step="0.01" required></label>
<div class="purchase-total" aria-live="polite"><span>Gasto de esta compra</span><strong><?= $simboloMoneda ?> <span id="total-compra">0.00</span></strong></div>
<button class="btn-primary" type="submit" <?= !$proveedores || !$productos ? 'disabled' : '' ?>>Registrar compra</button>
</form></section>
<section class="content-box"><div class="section-header"><div><h2>Historial de compras</h2><p><?= $totalCompras ?> compra(s)<?= $totalCompras ? ' · Página ' . $paginaActual . ' de ' . $totalPaginas : '' ?> · <?= (int)$resumenCompras['cantidad'] ?> completadas</p></div><div class="purchase-summary"><span>Gasto total registrado</span><strong><?= $simboloMoneda ?> <?= number_format((float)$resumenCompras['gasto_total'], 2) ?></strong></div></div><div class="table-container"><table><thead><tr><th>Fecha</th><th>Factura</th><th>Proveedor</th><th>Líneas</th><th>Total</th></tr></thead><tbody><?php if (!$compras): ?><tr><td colspan="5">No hay compras registradas.</td></tr><?php endif; ?><?php foreach ($compras as $compra): ?><tr><td><?= date('d/m/Y H:i', strtotime($compra['fecha_compra'])) ?></td><td><?= htmlspecialchars($compra['numero_factura'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($compra['proveedor'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int)$compra['lineas'] ?></td><td><?= $simboloMoneda ?> <?= number_format((float)$compra['total'], 2) ?></td></tr><?php endforeach; ?></tbody></table></div><?php if ($totalPaginas > 1): ?><nav class="pagination" aria-label="Paginación de compras"><?php if ($paginaActual > 1): ?><a href="/compras/?pagina=<?= $paginaActual - 1 ?>">Anterior</a><?php endif; ?><?php if ($inicioPagina > 1): ?><a href="/compras/?pagina=1">1</a><?php if ($inicioPagina > 2): ?><span aria-hidden="true">…</span><?php endif; ?><?php endif; ?><?php for ($pagina = $inicioPagina; $pagina <= $finPagina; $pagina++): ?><a href="/compras/?pagina=<?= $pagina ?>" class="<?= $pagina === $paginaActual ? 'current' : '' ?>" <?= $pagina === $paginaActual ? 'aria-current="page"' : '' ?>><?= $pagina ?></a><?php endfor; ?><?php if ($finPagina < $totalPaginas): ?><?php if ($finPagina < $totalPaginas - 1): ?><span aria-hidden="true">…</span><?php endif; ?><a href="/compras/?pagina=<?= $totalPaginas ?>"><?= $totalPaginas ?></a><?php endif; ?><?php if ($paginaActual < $totalPaginas): ?><a href="/compras/?pagina=<?= $paginaActual + 1 ?>">Siguiente</a><?php endif; ?></nav><?php endif; ?></section></main><script src="/dev-reload.js"></script><script>
const cantidadCompra = document.getElementById('cantidad-compra');
const costoCompra = document.getElementById('costo-compra');
const totalCompra = document.getElementById('total-compra');
const actualizarTotalCompra = () => {
    const total = (Number(cantidadCompra.value) || 0) * (Number(costoCompra.value) || 0);
    totalCompra.textContent = total.toFixed(2);
};
cantidadCompra.addEventListener('input', actualizarTotalCompra);
costoCompra.addEventListener('input', actualizarTotalCompra);
</script></body></html>
