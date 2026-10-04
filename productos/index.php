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
$productoId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$esEdicion = $productoId > 0;
$buscarCatalogo = trim($_GET['buscar_catalogo'] ?? '');
$categoriaCatalogo = filter_input(INPUT_GET, 'categoria_catalogo', FILTER_VALIDATE_INT) ?: 0;
$paginaSolicitada = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$productosPorPagina = 15;
$producto = ['codigo' => '', 'nombre' => '', 'categoria' => '', 'precio' => '', 'stock_minimo' => '5', 'descripcion' => '', 'principio_activo' => '', 'presentacion' => '', 'numero_lote' => '', 'stock' => '0', 'vencimiento' => '', 'costo' => ''];
$errores = [];
$mensaje = isset($_GET['guardado']) ? 'Los datos del producto se guardaron correctamente.' : '';
$mostrarModalAlta = false;
$categorias = $db->query('SELECT id_categoria, nombre FROM categorias WHERE estado = 1 ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);

if ($productoId) {
    $stmt = $db->prepare('SELECT codigo, nombre, id_categoria, precio_venta, stock_minimo, descripcion, principio_activo, presentacion FROM productos WHERE id_producto = ?');
    $stmt->bind_param('i', $productoId);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    if (!$existente) { http_response_code(404); exit('Producto no encontrado.'); }
    $producto = array_merge($producto, [
        'codigo' => $existente['codigo'], 'nombre' => $existente['nombre'], 'categoria' => $existente['id_categoria'],
        'precio' => $existente['precio_venta'], 'stock_minimo' => $existente['stock_minimo'],
        'descripcion' => $existente['descripcion'] ?? '', 'principio_activo' => $existente['principio_activo'] ?? '',
        'presentacion' => $existente['presentacion'] ?? ''
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['codigo', 'nombre', 'categoria', 'precio', 'stock_minimo', 'descripcion', 'principio_activo', 'presentacion', 'numero_lote', 'stock', 'vencimiento', 'costo'] as $campo) {
        $producto[$campo] = trim($_POST[$campo] ?? '');
    }
    if (strlen($producto['codigo']) < 2) $errores[] = 'Ingrese un código de al menos 2 caracteres.';
    if (strlen($producto['nombre']) < 3) $errores[] = 'El nombre debe tener al menos 3 caracteres.';
    if (!in_array((int)$producto['categoria'], array_map('intval', array_column($categorias, 'id_categoria')), true)) $errores[] = 'Seleccione una categoría válida.';
    if (!is_numeric($producto['precio']) || (float)$producto['precio'] <= 0) $errores[] = 'El precio debe ser mayor que 0.';
    if (filter_var($producto['stock_minimo'], FILTER_VALIDATE_INT) === false || (int)$producto['stock_minimo'] < 0) $errores[] = 'El stock mínimo debe ser un entero no negativo.';
    if (!$productoId) {
        if (strlen($producto['numero_lote']) < 1) $errores[] = 'Ingrese el número de lote.';
        if (filter_var($producto['stock'], FILTER_VALIDATE_INT) === false || (int)$producto['stock'] < 0) $errores[] = 'El stock debe ser un entero no negativo.';
        $fechaValida = DateTime::createFromFormat('Y-m-d', $producto['vencimiento']);
        if (!$fechaValida || $fechaValida->format('Y-m-d') !== $producto['vencimiento']) $errores[] = 'Ingrese una fecha de vencimiento válida.';
        if (!is_numeric($producto['costo']) || (float)$producto['costo'] <= 0) $errores[] = 'El costo del lote debe ser mayor que 0.';
    }
    if (!$errores) {
        try {
            $db->begin_transaction();
            if ($productoId) {
                $stmt = $db->prepare('UPDATE productos SET codigo = ?, nombre = ?, descripcion = ?, principio_activo = ?, presentacion = ?, id_categoria = ?, precio_venta = ?, stock_minimo = ? WHERE id_producto = ?');
                $stmt->bind_param('sssssidii', $producto['codigo'], $producto['nombre'], $producto['descripcion'], $producto['principio_activo'], $producto['presentacion'], $producto['categoria'], $producto['precio'], $producto['stock_minimo'], $productoId);
                $stmt->execute();
            } else {
                $stmt = $db->prepare('INSERT INTO productos (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sssssidi', $producto['codigo'], $producto['nombre'], $producto['descripcion'], $producto['principio_activo'], $producto['presentacion'], $producto['categoria'], $producto['precio'], $producto['stock_minimo']);
                $stmt->execute();
                $nuevoProductoId = $db->insert_id;
                $stmt = $db->prepare('INSERT INTO lotes (id_producto, numero_lote, fecha_vencimiento, cantidad, costo_unitario) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('issid', $nuevoProductoId, $producto['numero_lote'], $producto['vencimiento'], $producto['stock'], $producto['costo']);
                $stmt->execute();
            }
            $db->commit();
            header('Location: /productos/?guardado=1&buscar_catalogo=' . urlencode($buscarCatalogo) . '&categoria_catalogo=' . $categoriaCatalogo . '&pagina=' . $paginaSolicitada);
            exit();
        } catch (mysqli_sql_exception $e) {
            $db->rollback();
            $errores[] = $e->getCode() === 1062 ? 'El código o número de lote ya está registrado.' : 'No se pudo guardar el producto. Verifique los datos e inténtelo nuevamente.';
        }
    }
    $mostrarModalAlta = !$esEdicion && (bool)$errores;
}
$fromCatalogo = " FROM productos p INNER JOIN categorias c ON c.id_categoria = p.id_categoria WHERE p.estado = 1 AND (p.codigo LIKE CONCAT('%', ?, '%') OR p.nombre LIKE CONCAT('%', ?, '%') OR COALESCE(p.principio_activo, '') LIKE CONCAT('%', ?, '%') OR COALESCE(p.presentacion, '') LIKE CONCAT('%', ?, '%'))";
if ($categoriaCatalogo) $fromCatalogo .= ' AND p.id_categoria = ' . (int)$categoriaCatalogo;
$stmt = $db->prepare('SELECT COUNT(*)' . $fromCatalogo);
$stmt->bind_param('ssss', $buscarCatalogo, $buscarCatalogo, $buscarCatalogo, $buscarCatalogo);
$stmt->execute();
$totalProductos = (int)$stmt->get_result()->fetch_row()[0];
$totalPaginas = max(1, (int)ceil($totalProductos / $productosPorPagina));
$paginaActual = min(max(1, $paginaSolicitada), $totalPaginas);
$inicioPagina = max(1, $paginaActual - 2);
$finPagina = min($totalPaginas, $paginaActual + 2);
$offset = ($paginaActual - 1) * $productosPorPagina;
$sqlCatalogo = 'SELECT p.id_producto, p.codigo, p.nombre, p.principio_activo, p.presentacion, p.precio_venta, c.nombre AS categoria' . $fromCatalogo . ' ORDER BY p.nombre, p.presentacion LIMIT ? OFFSET ?';
$stmt = $db->prepare($sqlCatalogo);
$stmt->bind_param('ssssii', $buscarCatalogo, $buscarCatalogo, $buscarCatalogo, $buscarCatalogo, $productosPorPagina, $offset);
$stmt->execute();
$catalogoProductos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$urlCatalogo = '/productos/?buscar_catalogo=' . urlencode($buscarCatalogo) . '&categoria_catalogo=' . $categoriaCatalogo . '&pagina=' . $paginaActual;
$urlFiltrosCatalogo = '/productos/?buscar_catalogo=' . urlencode($buscarCatalogo) . '&categoria_catalogo=' . $categoriaCatalogo;
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Productos | Farmacia Fuente de Vida</title><link rel="stylesheet" href="../menu.css"><link rel="stylesheet" href="productos.css"></head>
<body>
<?php include __DIR__ . '/../SideBar/menu.php'; ?>
<main class="main module-main"><header class="header"><div><h1>Productos</h1><p><?= $productoId ? 'Editar producto' : 'Catálogo y registro de productos' ?></p></div><div class="user"><div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'U', 0, 1)), ENT_QUOTES, 'UTF-8') ?></div><div><strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($_SESSION['usuario_rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></div></div></header>
<?php if ($mensaje): ?><div class="alert success product-success" role="status"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($productoId): ?><div class="confirm-edit-backdrop" id="confirm-edit-cancel" hidden><section class="confirm-edit-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-edit-title"><h2 id="confirm-edit-title">¿Cancelar la edición?</h2><p>Los cambios que no hayas guardado se perderán.</p><div class="confirm-edit-actions"><button class="btn-secondary" type="button" data-keep-edit>Seguir editando</button><a class="btn-danger" href="<?= htmlspecialchars($urlCatalogo, ENT_QUOTES, 'UTF-8') ?>">Descartar cambios</a></div></section></div><?php endif; ?>
<section class="content-box product-catalog"><div class="section-header"><div><h2>Catálogo de productos</h2><p><?= $totalProductos ?> producto(s)<?= $totalProductos ? ' · Página ' . $paginaActual . ' de ' . $totalPaginas : '' ?></p></div><?php if (!$productoId): ?><button class="btn-primary" type="button" data-open-add>+ Agregar producto</button><?php endif; ?></div><form class="product-filters" method="GET" data-auto-filter><input type="search" name="buscar_catalogo" value="<?= htmlspecialchars($buscarCatalogo, ENT_QUOTES, 'UTF-8') ?>" placeholder="Código, nombre, principio activo o presentación"><select name="categoria_catalogo" aria-label="Filtrar por categoría"><option value="0">Todas las categorías</option><?php foreach ($categorias as $categoria): ?><option value="<?= (int)$categoria['id_categoria'] ?>" <?= $categoriaCatalogo === (int)$categoria['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><a class="btn-secondary" href="/productos/">Limpiar</a></form><div class="table-container"><table><thead><tr><th>Código</th><th>Producto</th><th>Principio activo</th><th>Presentación</th><th>Categoría</th><th>Precio</th><th>Acción</th></tr></thead><tbody><?php if (!$catalogoProductos): ?><tr><td colspan="7" class="empty-state">No hay productos que coincidan con esos filtros.</td></tr><?php endif; ?><?php foreach ($catalogoProductos as $fila): ?><tr><td><?= htmlspecialchars($fila['codigo'], ENT_QUOTES, 'UTF-8') ?></td><td><strong><?= htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td><td><?= htmlspecialchars($fila['principio_activo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fila['presentacion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fila['categoria'], ENT_QUOTES, 'UTF-8') ?></td><td><?= $simboloMoneda ?> <?= number_format((float)$fila['precio_venta'], 2) ?></td><td><a class="edit" href="/productos/?id=<?= (int)$fila['id_producto'] ?>&amp;buscar_catalogo=<?= urlencode($buscarCatalogo) ?>&amp;categoria_catalogo=<?= $categoriaCatalogo ?>&amp;pagina=<?= $paginaActual ?>">Editar</a></td></tr><?php endforeach; ?></tbody></table></div>
<?php if ($totalPaginas > 1): ?><nav class="product-pagination" aria-label="Paginación del catálogo"><?php if ($paginaActual > 1): ?><a href="<?= htmlspecialchars($urlFiltrosCatalogo . '&pagina=' . ($paginaActual - 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Página anterior">Anterior</a><?php endif; ?><?php if ($inicioPagina > 1): ?><a href="<?= htmlspecialchars($urlFiltrosCatalogo . '&pagina=1', ENT_QUOTES, 'UTF-8') ?>">1</a><?php if ($inicioPagina > 2): ?><span aria-hidden="true">…</span><?php endif; ?><?php endif; ?><?php for ($pagina = $inicioPagina; $pagina <= $finPagina; $pagina++): ?><a href="<?= htmlspecialchars($urlFiltrosCatalogo . '&pagina=' . $pagina, ENT_QUOTES, 'UTF-8') ?>" class="<?= $pagina === $paginaActual ? 'current' : '' ?>" <?= $pagina === $paginaActual ? 'aria-current="page"' : '' ?>><?= $pagina ?></a><?php endfor; ?><?php if ($finPagina < $totalPaginas): ?><?php if ($finPagina < $totalPaginas - 1): ?><span aria-hidden="true">…</span><?php endif; ?><a href="<?= htmlspecialchars($urlFiltrosCatalogo . '&pagina=' . $totalPaginas, ENT_QUOTES, 'UTF-8') ?>"><?= $totalPaginas ?></a><?php endif; ?><?php if ($paginaActual < $totalPaginas): ?><a href="<?= htmlspecialchars($urlFiltrosCatalogo . '&pagina=' . ($paginaActual + 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Página siguiente">Siguiente</a><?php endif; ?></nav><?php endif; ?></section>
<section class="content-box form-box <?= $productoId ? 'edit-modal' : 'add-modal' ?>" <?= $productoId ? 'role="dialog" aria-modal="true" aria-labelledby="edit-product-title"' : 'id="add-product-modal" role="dialog" aria-modal="true" aria-labelledby="add-product-title" ' . ($mostrarModalAlta ? '' : 'hidden') ?>>
<div class="section-header"><div><h2 <?= $productoId ? 'id="edit-product-title"' : 'id="add-product-title"' ?>><?= $productoId ? 'Editar producto' : 'Agregar producto' ?></h2><p><?= $productoId ? 'Actualiza los datos del producto.' : 'Complete los datos del producto' ?></p></div><?php if ($productoId): ?><button class="modal-close" type="button" data-cancel-edit aria-label="Cerrar edición">×</button><?php else: ?><button class="modal-close" type="button" data-cancel-add aria-label="Cerrar">×</button><?php endif; ?></div>
<?php if ($errores): ?><div class="alert error"><strong>Revise los datos:</strong><ul><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST"><?= csrf_input() ?>
<div class="form-grid"><label>Código<input name="codigo" value="<?= htmlspecialchars($producto['codigo'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Nombre del producto<input name="nombre" value="<?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Categoría<select name="categoria" required><option value="">Seleccione una categoría</option><?php foreach ($categorias as $categoria): ?><option value="<?= (int)$categoria['id_categoria'] ?>" <?= (string)$producto['categoria'] === (string)$categoria['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label>Precio de venta (<?= $simboloMoneda ?>)<input type="number" name="precio" min="0.01" step="0.01" value="<?= htmlspecialchars($producto['precio'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Stock mínimo<input type="number" name="stock_minimo" min="0" step="1" value="<?= htmlspecialchars($producto['stock_minimo'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Presentación<input name="presentacion" value="<?= htmlspecialchars($producto['presentacion'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Caja, frasco, unidad"></label><label>Principio activo<input name="principio_activo" value="<?= htmlspecialchars($producto['principio_activo'], ENT_QUOTES, 'UTF-8') ?>"></label><label class="wide">Descripción<textarea name="descripcion" maxlength="255"><?= htmlspecialchars($producto['descripcion'], ENT_QUOTES, 'UTF-8') ?></textarea></label><?php if (!$productoId): ?><label>Número de lote<input name="numero_lote" value="<?= htmlspecialchars($producto['numero_lote'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Stock inicial<input type="number" name="stock" min="0" step="1" value="<?= htmlspecialchars($producto['stock'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Fecha de vencimiento<input type="date" name="vencimiento" value="<?= htmlspecialchars($producto['vencimiento'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Costo unitario (<?= $simboloMoneda ?>)<input type="number" name="costo" min="0.01" step="0.01" value="<?= htmlspecialchars($producto['costo'], ENT_QUOTES, 'UTF-8') ?>" required></label><?php endif; ?></div><div class="form-actions"><?php if ($productoId): ?><button class="btn-secondary" type="button" data-cancel-edit>Cancelar</button><?php else: ?><button class="btn-secondary" type="button" data-cancel-add>Cancelar</button><?php endif; ?><button class="btn-primary" type="submit"><?= $productoId ? 'Guardar cambios' : 'Guardar producto' ?></button></div></form></section></main><script src="productos.js"></script><script src="/dev-reload.js"></script></body></html>
