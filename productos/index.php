<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: /login/login.php'); exit(); }
require_once __DIR__ . '/../csrf.php';
validar_csrf();
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../config_helpers.php';
require_once __DIR__ . '/../permisos.php';
$db = conectar();
exigir_permiso_modulo($db, 'productos');
$configuracion = cargar_configuracion($db);
$simboloMoneda = simbolo_moneda($configuracion);
$productoId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$esEdicion = $productoId > 0;
$buscarCatalogo = trim($_GET['buscar_catalogo'] ?? '');
$categoriaCatalogo = filter_input(INPUT_GET, 'categoria_catalogo', FILTER_VALIDATE_INT) ?: 0;
$paginaSolicitada = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$productosPorPagina = 15;
$producto = ['codigo' => '', 'nombre' => '', 'categoria' => '', 'precio' => '', 'stock_minimo' => '5', 'descripcion' => '', 'principio_activo' => '', 'presentacion' => ''];
$errores = [];
$mensaje = isset($_GET['guardado']) ? 'Los datos y las presentaciones del producto se guardaron correctamente.' : '';
$productoNuevoId = filter_input(INPUT_GET, 'producto_nuevo', FILTER_VALIDATE_INT) ?: 0;
$mostrarModalAlta = false;
$categorias = $db->query('SELECT id_categoria, nombre FROM categorias WHERE estado = 1 ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$catalogoPresentaciones = $db->query('SELECT id_catalogo_presentacion, nombre, nivel, estado FROM catalogo_presentaciones WHERE estado = 1 ORDER BY nivel DESC, nombre')->fetch_all(MYSQLI_ASSOC);
foreach ($catalogoPresentaciones as &$catalogoPresentacion) {
    $catalogoPresentacion['nombre'] = nombre_presentacion_visible($catalogoPresentacion['nombre']);
}
unset($catalogoPresentacion);
$presentacionesProducto = [];

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
        'presentacion' => nombre_presentacion_visible($existente['presentacion'] ?? '')
    ]);
    $stmt = $db->prepare('SELECT pp.id_catalogo_presentacion, cp.nombre, cp.nivel, pp.unidades_base, pp.precio_venta FROM producto_presentaciones pp INNER JOIN catalogo_presentaciones cp ON cp.id_catalogo_presentacion = pp.id_catalogo_presentacion WHERE pp.id_producto = ? AND pp.estado = 1 ORDER BY cp.nivel DESC');
    $stmt->bind_param('i', $productoId);
    $stmt->execute();
    $presentacionesProducto = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($presentacionesProducto as &$presentacionProducto) {
        $presentacionProducto['nombre'] = nombre_presentacion_visible($presentacionProducto['nombre']);
    }
    unset($presentacionProducto);
    foreach ($presentacionesProducto as $presentacionExistente) {
        if (!in_array((int)$presentacionExistente['id_catalogo_presentacion'], array_map('intval', array_column($catalogoPresentaciones, 'id_catalogo_presentacion')), true)) {
            $catalogoPresentaciones[] = [
                'id_catalogo_presentacion' => $presentacionExistente['id_catalogo_presentacion'],
                'nombre' => $presentacionExistente['nombre'],
                'nivel' => $presentacionExistente['nivel'],
                'estado' => 0,
            ];
        }
    }
    usort($catalogoPresentaciones, static fn(array $a, array $b): int => (int)$b['nivel'] <=> (int)$a['nivel'] ?: strcmp($a['nombre'], $b['nombre']));
    for ($i = 0; $i < count($presentacionesProducto) - 1; $i++) {
        $presentacionesProducto[$i]['unidades_siguiente'] = (int)$presentacionesProducto[$i]['unidades_base'] / (int)$presentacionesProducto[$i + 1]['unidades_base'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['codigo', 'nombre', 'categoria', 'precio', 'stock_minimo', 'descripcion', 'principio_activo', 'presentacion'] as $campo) {
        $producto[$campo] = trim($_POST[$campo] ?? '');
    }
    $producto['presentacion'] = nombre_presentacion_visible($producto['presentacion']);
    if (strlen($producto['codigo']) < 2) $errores[] = 'Ingrese un código de al menos 2 caracteres.';
    if (strlen($producto['nombre']) < 3) $errores[] = 'El nombre debe tener al menos 3 caracteres.';
    if (!in_array((int)$producto['categoria'], array_map('intval', array_column($categorias, 'id_categoria')), true)) $errores[] = 'Seleccione una categoría válida.';
    if (filter_var($producto['stock_minimo'], FILTER_VALIDATE_INT) === false || (int)$producto['stock_minimo'] < 0) $errores[] = 'El stock mínimo debe ser un entero no negativo.';
    $presentacionIds = is_array($_POST['presentacion_ids'] ?? null) ? $_POST['presentacion_ids'] : [];
    $presentacionCantidades = is_array($_POST['presentacion_cantidades'] ?? null) ? $_POST['presentacion_cantidades'] : [];
    $presentacionPrecios = is_array($_POST['presentacion_precios'] ?? null) ? $_POST['presentacion_precios'] : [];
    $idsCatalogoValidos = array_map('intval', array_column($catalogoPresentaciones, 'id_catalogo_presentacion'));
    $nivelesPresentacion = [];
    $factoresPresentacion = [];
    $preciosPresentacion = [];
    if (!$presentacionIds || count($presentacionIds) !== count($presentacionPrecios) || count($presentacionCantidades) !== count($presentacionIds) - 1) {
        $errores[] = 'Configure las presentaciones desde el nivel más alto hasta la unidad mínima.';
    } else {
        foreach ($presentacionIds as $indice => $idCatalogoRaw) {
            $idCatalogo = filter_var($idCatalogoRaw, FILTER_VALIDATE_INT);
            $catalogoEncontrado = null;
            foreach ($catalogoPresentaciones as $opcion) {
                if ((int)$opcion['id_catalogo_presentacion'] === $idCatalogo) {
                    $catalogoEncontrado = $opcion;
                    break;
                }
            }
            $precioNivel = trim((string)($presentacionPrecios[$indice] ?? ''));
            if (!$idCatalogo || !in_array($idCatalogo, $idsCatalogoValidos, true) || !$catalogoEncontrado) {
                $errores[] = 'Seleccione presentaciones activas del catálogo.';
                break;
            }
            if (!is_numeric($precioNivel) || (float)$precioNivel <= 0 || (float)$precioNivel > 99999999.99) {
                $errores[] = 'Cada presentación debe tener un precio de venta válido.';
                break;
            }
            $nivelesPresentacion[] = (int)$catalogoEncontrado['nivel'];
            $preciosPresentacion[] = (float)$precioNivel;
        }
        if (!$errores && (end($nivelesPresentacion) !== 1 || count(array_unique($presentacionIds)) !== count($presentacionIds))) {
            $errores[] = 'La jerarquía debe terminar en una unidad de nivel 1 y no puede repetir presentaciones.';
        }
        if (!$errores) {
            for ($i = 0; $i < count($nivelesPresentacion) - 1; $i++) {
                if ($nivelesPresentacion[$i] <= $nivelesPresentacion[$i + 1]) {
                    $errores[] = 'Ordene las presentaciones desde el empaque mayor hasta la unidad, sin repetir niveles.';
                    break;
                }
            }
        }
        if (!$errores) {
            $factoresPresentacion = array_fill(0, count($presentacionIds), 1);
            for ($i = count($presentacionIds) - 2; $i >= 0; $i--) {
                $cantidadSiguiente = filter_var($presentacionCantidades[$i] ?? '', FILTER_VALIDATE_INT);
                if ($cantidadSiguiente === false || $cantidadSiguiente < 1 || $cantidadSiguiente > 1000000 || $factoresPresentacion[$i + 1] > intdiv(PHP_INT_MAX, $cantidadSiguiente)) {
                    $errores[] = 'La cantidad contenida por cada empaque debe ser un entero positivo válido.';
                    break;
                }
                $factoresPresentacion[$i] = $factoresPresentacion[$i + 1] * $cantidadSiguiente;
            }
        }
    }
    if (!$errores) $producto['precio'] = (string)$preciosPresentacion[0];
    if (!$errores) {
        try {
            $db->begin_transaction();
            if ($productoId) {
                $stmt = $db->prepare('UPDATE productos SET codigo = ?, nombre = ?, descripcion = ?, principio_activo = ?, presentacion = ?, id_categoria = ?, precio_venta = ?, stock_minimo = ? WHERE id_producto = ?');
                $stmt->bind_param('sssssidii', $producto['codigo'], $producto['nombre'], $producto['descripcion'], $producto['principio_activo'], $producto['presentacion'], $producto['categoria'], $producto['precio'], $producto['stock_minimo'], $productoId);
                $stmt->execute();
                $productoGuardadoId = $productoId;
            } else {
                $stmt = $db->prepare('INSERT INTO productos (codigo, nombre, descripcion, principio_activo, presentacion, id_categoria, precio_venta, stock_minimo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sssssidi', $producto['codigo'], $producto['nombre'], $producto['descripcion'], $producto['principio_activo'], $producto['presentacion'], $producto['categoria'], $producto['precio'], $producto['stock_minimo']);
                $stmt->execute();
                $productoGuardadoId = $db->insert_id;
            }
            $stmt = $db->prepare('UPDATE producto_presentaciones SET estado = 0 WHERE id_producto = ?');
            $stmt->bind_param('i', $productoGuardadoId);
            $stmt->execute();
            $stmt = $db->prepare('INSERT INTO producto_presentaciones (id_producto, id_catalogo_presentacion, unidades_base, precio_venta, estado) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE unidades_base = VALUES(unidades_base), precio_venta = VALUES(precio_venta), estado = 1');
            foreach ($presentacionIds as $indice => $idCatalogoRaw) {
                $idCatalogo = (int)$idCatalogoRaw;
                $unidadesBase = $factoresPresentacion[$indice];
                $precioNivel = $preciosPresentacion[$indice];
                $stmt->bind_param('iiid', $productoGuardadoId, $idCatalogo, $unidadesBase, $precioNivel);
                $stmt->execute();
            }
            $db->commit();
            $parametrosGuardado = ['guardado' => 1, 'buscar_catalogo' => $buscarCatalogo, 'categoria_catalogo' => $categoriaCatalogo, 'pagina' => $paginaSolicitada];
            if (!$productoId) $parametrosGuardado['producto_nuevo'] = $productoGuardadoId;
            header('Location: /productos/?' . http_build_query($parametrosGuardado));
            exit();
        } catch (mysqli_sql_exception $e) {
            $db->rollback();
            $errores[] = $e->getCode() === 1062 ? 'El código o número de lote ya está registrado.' : 'No se pudo guardar el producto. Verifique los datos e inténtelo nuevamente.';
        }
    }
    $mostrarModalAlta = !$esEdicion && (bool)$errores;
    if ($presentacionIds) {
        $nivelesPorId = array_column($catalogoPresentaciones, 'nivel', 'id_catalogo_presentacion');
        $nombresPorId = array_column($catalogoPresentaciones, 'nombre', 'id_catalogo_presentacion');
        $presentacionesProducto = [];
        foreach ($presentacionIds as $indice => $idCatalogoRaw) {
            $idCatalogo = (int)$idCatalogoRaw;
            $presentacionesProducto[] = [
                'id_catalogo_presentacion' => $idCatalogo,
                'nombre' => $nombresPorId[$idCatalogo] ?? '',
                'nivel' => $nivelesPorId[$idCatalogo] ?? 0,
                'unidades_siguiente' => $presentacionCantidades[$indice] ?? '',
                'precio_venta' => $presentacionPrecios[$indice] ?? '',
            ];
        }
    }
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
foreach ($catalogoProductos as &$filaCatalogoProducto) {
    $filaCatalogoProducto['presentacion'] = nombre_presentacion_visible($filaCatalogoProducto['presentacion'] ?? '');
}
unset($filaCatalogoProducto);
$catalogoPresentacionesJson = htmlspecialchars(json_encode($catalogoPresentaciones, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8');
$presentacionesProductoJson = htmlspecialchars(json_encode($presentacionesProducto, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8');
$urlCatalogo = '/productos/?buscar_catalogo=' . urlencode($buscarCatalogo) . '&categoria_catalogo=' . $categoriaCatalogo . '&pagina=' . $paginaActual;
$urlFiltrosCatalogo = '/productos/?buscar_catalogo=' . urlencode($buscarCatalogo) . '&categoria_catalogo=' . $categoriaCatalogo;
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Productos | Farmacia Fuente de Vida</title><link rel="stylesheet" href="../menu.css"><link rel="stylesheet" href="productos.css"></head>
<body>
<?php include __DIR__ . '/../SideBar/menu.php'; ?>
<main class="main module-main"><header class="header"><div><h1>Productos</h1><p><?= $productoId ? 'Editar producto' : 'Catálogo y registro de productos' ?></p></div><div class="user"><div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'U', 0, 1)), ENT_QUOTES, 'UTF-8') ?></div><div><strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($_SESSION['usuario_rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></div></div></header>
<?php if ($mensaje): ?><div class="alert success product-success" role="status"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?><?php if ($productoNuevoId): ?> <a href="/compras/?producto=<?= $productoNuevoId ?>">Registrar su primera compra y agregar existencia</a>.<?php endif; ?></div><?php endif; ?>
<?php if ($productoId): ?><div class="confirm-edit-backdrop" id="confirm-edit-cancel" hidden><section class="confirm-edit-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-edit-title"><h2 id="confirm-edit-title">¿Cancelar la edición?</h2><p>Los cambios que no hayas guardado se perderán.</p><div class="confirm-edit-actions"><button class="btn-secondary" type="button" data-keep-edit>Seguir editando</button><a class="btn-danger" href="<?= htmlspecialchars($urlCatalogo, ENT_QUOTES, 'UTF-8') ?>">Descartar cambios</a></div></section></div><?php endif; ?>
<section class="content-box product-catalog"><div class="section-header"><div><h2>Catálogo de productos</h2><p><?= $totalProductos ?> producto(s)<?= $totalProductos ? ' · Página ' . $paginaActual . ' de ' . $totalPaginas : '' ?></p></div><?php if (!$productoId): ?><button class="btn-primary" type="button" data-open-add>+ Agregar producto</button><?php endif; ?></div><form class="product-filters" method="GET" data-auto-filter><input type="search" name="buscar_catalogo" value="<?= htmlspecialchars($buscarCatalogo, ENT_QUOTES, 'UTF-8') ?>" placeholder="Código, nombre, principio activo o presentación"><select name="categoria_catalogo" aria-label="Filtrar por categoría"><option value="0">Todas las categorías</option><?php foreach ($categorias as $categoria): ?><option value="<?= (int)$categoria['id_categoria'] ?>" <?= $categoriaCatalogo === (int)$categoria['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><a class="btn-secondary" href="/productos/">Limpiar</a></form><div class="table-container"><table><thead><tr><th>Código</th><th>Producto</th><th>Principio activo</th><th>Presentación</th><th>Categoría</th><th>Precio</th><th>Acción</th></tr></thead><tbody><?php if (!$catalogoProductos): ?><tr><td colspan="7" class="empty-state">No hay productos que coincidan con esos filtros.</td></tr><?php endif; ?><?php foreach ($catalogoProductos as $fila): ?><tr><td><?= htmlspecialchars($fila['codigo'], ENT_QUOTES, 'UTF-8') ?></td><td><strong><?= htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td><td><?= htmlspecialchars($fila['principio_activo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fila['presentacion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fila['categoria'], ENT_QUOTES, 'UTF-8') ?></td><td><?= $simboloMoneda ?> <?= number_format((float)$fila['precio_venta'], 2) ?></td><td><a class="edit" href="/productos/?id=<?= (int)$fila['id_producto'] ?>&amp;buscar_catalogo=<?= urlencode($buscarCatalogo) ?>&amp;categoria_catalogo=<?= $categoriaCatalogo ?>&amp;pagina=<?= $paginaActual ?>">Editar</a></td></tr><?php endforeach; ?></tbody></table></div>
<?php if ($totalPaginas > 1): ?><nav class="product-pagination" aria-label="Paginación del catálogo"><?php if ($paginaActual > 1): ?><a href="<?= htmlspecialchars($urlFiltrosCatalogo . '&pagina=' . ($paginaActual - 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Página anterior">Anterior</a><?php endif; ?><?php if ($inicioPagina > 1): ?><a href="<?= htmlspecialchars($urlFiltrosCatalogo . '&pagina=1', ENT_QUOTES, 'UTF-8') ?>">1</a><?php if ($inicioPagina > 2): ?><span aria-hidden="true">…</span><?php endif; ?><?php endif; ?><?php for ($pagina = $inicioPagina; $pagina <= $finPagina; $pagina++): ?><a href="<?= htmlspecialchars($urlFiltrosCatalogo . '&pagina=' . $pagina, ENT_QUOTES, 'UTF-8') ?>" class="<?= $pagina === $paginaActual ? 'current' : '' ?>" <?= $pagina === $paginaActual ? 'aria-current="page"' : '' ?>><?= $pagina ?></a><?php endfor; ?><?php if ($finPagina < $totalPaginas): ?><?php if ($finPagina < $totalPaginas - 1): ?><span aria-hidden="true">…</span><?php endif; ?><a href="<?= htmlspecialchars($urlFiltrosCatalogo . '&pagina=' . $totalPaginas, ENT_QUOTES, 'UTF-8') ?>"><?= $totalPaginas ?></a><?php endif; ?><?php if ($paginaActual < $totalPaginas): ?><a href="<?= htmlspecialchars($urlFiltrosCatalogo . '&pagina=' . ($paginaActual + 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Página siguiente">Siguiente</a><?php endif; ?></nav><?php endif; ?></section>
<section class="content-box form-box <?= $productoId ? 'edit-modal' : 'add-modal' ?>" <?= $productoId ? 'role="dialog" aria-modal="true" aria-labelledby="edit-product-title"' : 'id="add-product-modal" role="dialog" aria-modal="true" aria-labelledby="add-product-title" ' . ($mostrarModalAlta ? '' : 'hidden') ?>>
<div class="section-header"><div><h2 <?= $productoId ? 'id="edit-product-title"' : 'id="add-product-title"' ?>><?= $productoId ? 'Editar producto' : 'Agregar producto' ?></h2><p><?= $productoId ? 'Actualiza los datos del producto.' : 'Complete los datos del producto' ?></p></div><?php if ($productoId): ?><button class="modal-close" type="button" data-cancel-edit aria-label="Cerrar edición">×</button><?php else: ?><button class="modal-close" type="button" data-cancel-add aria-label="Cerrar">×</button><?php endif; ?></div>
<?php if ($errores): ?><div class="alert error"><strong>Revise los datos:</strong><ul><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST"><?= csrf_input() ?>
<div class="product-wizard" data-product-wizard data-step-count="2">
<ol class="wizard-steps" aria-label="Pasos para registrar el producto">
<li class="wizard-step is-current" data-step-indicator="0"><span>1</span><div><strong>Datos</strong><small>Información general</small></div></li>
<li class="wizard-step" data-step-indicator="1"><span>2</span><div><strong>Presentaciones</strong><small>Empaques y precios</small></div></li>
</ol>
<section class="wizard-panel" data-wizard-panel="0" aria-labelledby="wizard-data-title">
<div class="wizard-intro"><h3 id="wizard-data-title">Datos del producto</h3><p>Comienza con la información básica para identificarlo en el catálogo.</p></div>
<div class="form-grid"><label>Código<input name="codigo" value="<?= htmlspecialchars($producto['codigo'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Nombre del producto<input name="nombre" value="<?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>" required></label><label>Categoría<select name="categoria" required><option value="">Seleccione una categoría</option><?php foreach ($categorias as $categoria): ?><option value="<?= (int)$categoria['id_categoria'] ?>" <?= (string)$producto['categoria'] === (string)$categoria['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label>Principio activo<input name="principio_activo" value="<?= htmlspecialchars($producto['principio_activo'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. Ibuprofeno"></label><label>Presentación descriptiva<input name="presentacion" value="<?= htmlspecialchars($producto['presentacion'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. 400 mg, sabor u otra descripción"></label><label>Stock mínimo<input type="number" name="stock_minimo" min="0" step="1" value="<?= htmlspecialchars($producto['stock_minimo'], ENT_QUOTES, 'UTF-8') ?>" required></label><label class="wide">Descripción<textarea name="descripcion" maxlength="255" placeholder="¿Para qué sirve o qué características tiene?"><?= htmlspecialchars($producto['descripcion'], ENT_QUOTES, 'UTF-8') ?></textarea></label></div>
</section>
<section class="wizard-panel" data-wizard-panel="1" aria-labelledby="wizard-presentations-title" hidden>
<div class="wizard-intro"><h3 id="wizard-presentations-title">Presentaciones y precios</h3><p>Configura solo los empaques que utilizas y ordénalos del mayor a la unidad. Puedes quitar los que no necesites; indica cuántas unidades del siguiente empaque caben en cada uno.</p></div>
<label class="wizard-price-field">Precio de venta del empaque mayor (<?= $simboloMoneda ?>)<input type="number" name="precio" min="0.01" step="0.01" value="<?= htmlspecialchars($producto['precio'], ENT_QUOTES, 'UTF-8') ?>" required></label>
<section class="presentation-builder" id="presentation-builder" data-edit="<?= $productoId ? '1' : '0' ?>" data-catalog="<?= $catalogoPresentacionesJson ?>" data-selected="<?= $presentacionesProductoJson ?>">
<input type="hidden" name="presentacion_precios[]" id="precio-presentacion-mayor" value="<?= htmlspecialchars($producto['precio'], ENT_QUOTES, 'UTF-8') ?>">
<div id="presentation-rows"></div><button class="btn-secondary" type="button" id="add-presentation-level">Agregar nivel inferior</button>
</section>
</section>
<div class="wizard-footer"><div class="wizard-status" aria-live="polite">Paso 1 de 2</div><div class="form-actions"><button class="btn-secondary" type="button" data-wizard-previous hidden>Anterior</button><?php if ($productoId): ?><button class="btn-secondary" type="button" data-cancel-edit>Cancelar</button><?php else: ?><button class="btn-secondary" type="button" data-cancel-add>Cancelar</button><?php endif; ?><button class="btn-primary" type="button" data-wizard-next>Continuar</button><button class="btn-primary" type="submit" data-wizard-submit hidden><?= $productoId ? 'Guardar cambios' : 'Registrar producto' ?></button></div></div>
</div>
</form></section></main><script src="productos.js"></script><script src="/dev-reload.js"></script></body></html>
