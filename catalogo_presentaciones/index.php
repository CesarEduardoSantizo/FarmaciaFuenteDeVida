<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: /login/login.php'); exit(); }
require_once __DIR__ . '/../csrf.php';
validar_csrf();
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../config_helpers.php';
require_once __DIR__ . '/../permisos.php';
$db = conectar();
exigir_permiso_modulo($db, 'catalogo_presentaciones');
$errores = [];
$seccion = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['seccion'] ?? 'presentaciones')
    : ($_GET['seccion'] ?? 'presentaciones');
if (!in_array($seccion, ['presentaciones', 'categorias'], true)) {
    $seccion = 'presentaciones';
}
$mensaje = '';
if (isset($_GET['eliminada'])) {
    $mensaje = 'La presentación se eliminó del catálogo.';
} elseif (isset($_GET['categoria_eliminada'])) {
    $mensaje = 'La categoría se eliminó correctamente.';
} elseif (isset($_GET['categoria_guardada'])) {
    $mensaje = 'La categoría se guardó correctamente.';
} elseif (isset($_GET['guardado'])) {
    $mensaje = 'La presentación se guardó correctamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'eliminar') {
        $presentacionId = filter_input(INPUT_POST, 'id_catalogo_presentacion', FILTER_VALIDATE_INT);
        if (!$presentacionId) {
            $errores[] = 'La presentación seleccionada no es válida.';
        } else {
            $stmt = $db->prepare('DELETE FROM catalogo_presentaciones WHERE id_catalogo_presentacion = ? AND NOT EXISTS (SELECT 1 FROM producto_presentaciones WHERE id_catalogo_presentacion = ?)');
            $stmt->bind_param('ii', $presentacionId, $presentacionId);
            $stmt->execute();
            if ($stmt->affected_rows === 1) {
                header('Location: /catalogo_presentaciones/?seccion=presentaciones&eliminada=1');
                exit();
            }
            $stmt = $db->prepare('SELECT COUNT(*) FROM producto_presentaciones WHERE id_catalogo_presentacion = ?');
            $stmt->bind_param('i', $presentacionId);
            $stmt->execute();
            $enUso = (int)$stmt->get_result()->fetch_row()[0] > 0;
            $errores[] = $enUso
                ? 'No se puede eliminar: esta presentación está configurada en uno o más productos. Desactívala si ya no quieres ofrecerla.'
                : 'No se encontró la presentación seleccionada.';
        }
    } elseif ($accion === 'estado') {
        $presentacionId = filter_input(INPUT_POST, 'id_catalogo_presentacion', FILTER_VALIDATE_INT);
        $estado = filter_input(INPUT_POST, 'estado', FILTER_VALIDATE_INT);
        if (!$presentacionId || !in_array($estado, [0, 1], true)) {
            $errores[] = 'La presentación seleccionada no es válida.';
        } else {
            $stmt = $db->prepare('UPDATE catalogo_presentaciones SET estado = ? WHERE id_catalogo_presentacion = ?');
            $stmt->bind_param('ii', $estado, $presentacionId);
            $stmt->execute();
            header('Location: /catalogo_presentaciones/?seccion=presentaciones&guardado=1');
            exit();
        }
    } elseif ($accion === 'crear') {
        $nombre = nombre_presentacion_visible(trim($_POST['nombre'] ?? ''));
        $nivel = filter_var($_POST['nivel'] ?? '', FILTER_VALIDATE_INT);
        if ($nombre === '' || mb_strlen($nombre) > 100) $errores[] = 'El nombre es obligatorio y debe tener 100 caracteres o menos.';
        if ($nivel === false || $nivel < 1 || $nivel > 50) $errores[] = 'El nivel debe ser un número entre 1 y 50.';
        if (!$errores) {
            try {
                $stmt = $db->prepare('INSERT INTO catalogo_presentaciones (nombre, nivel) VALUES (?, ?)');
                $stmt->bind_param('si', $nombre, $nivel);
                $stmt->execute();
                header('Location: /catalogo_presentaciones/?seccion=presentaciones&guardado=1');
                exit();
            } catch (mysqli_sql_exception $e) {
                $errores[] = $e->getCode() === 1062
                    ? 'Ya existe una presentación con ese nombre.'
                    : 'No se pudo guardar la presentación. Verifique los datos e inténtelo nuevamente.';
            }
        }
    } elseif ($accion === 'crear_categoria') {
        $nombreCategoria = trim($_POST['nombre_categoria'] ?? '');
        $descripcionCategoria = trim($_POST['descripcion_categoria'] ?? '');
        if ($nombreCategoria === '' || mb_strlen($nombreCategoria) > 100) $errores[] = 'El nombre de la categoría es obligatorio y debe tener 100 caracteres o menos.';
        if (mb_strlen($descripcionCategoria) > 255) $errores[] = 'La descripción debe tener 255 caracteres o menos.';
        if (!$errores) {
            try {
                $stmt = $db->prepare('INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)');
                $stmt->bind_param('ss', $nombreCategoria, $descripcionCategoria);
                $stmt->execute();
                header('Location: /catalogo_presentaciones/?seccion=categorias&categoria_guardada=1');
                exit();
            } catch (mysqli_sql_exception $e) {
                $errores[] = $e->getCode() === 1062
                    ? 'Ya existe una categoría con ese nombre.'
                    : 'No se pudo guardar la categoría. Verifique los datos e inténtelo nuevamente.';
            }
        }
    } elseif ($accion === 'estado_categoria') {
        $categoriaId = filter_var($_POST['id_categoria'] ?? '', FILTER_VALIDATE_INT);
        $estadoCategoria = filter_var($_POST['estado'] ?? '', FILTER_VALIDATE_INT);
        if (!$categoriaId || !in_array($estadoCategoria, [0, 1], true)) {
            $errores[] = 'La categoría seleccionada no es válida.';
        } else {
            $stmt = $db->prepare('UPDATE categorias SET estado = ? WHERE id_categoria = ?');
            $stmt->bind_param('ii', $estadoCategoria, $categoriaId);
            $stmt->execute();
            header('Location: /catalogo_presentaciones/?seccion=categorias&categoria_guardada=1');
            exit();
        }
    } elseif ($accion === 'eliminar_categoria') {
        $categoriaId = filter_var($_POST['id_categoria'] ?? '', FILTER_VALIDATE_INT);
        if (!$categoriaId) {
            $errores[] = 'La categoría seleccionada no es válida.';
        } else {
            $stmt = $db->prepare('DELETE FROM categorias WHERE id_categoria = ? AND NOT EXISTS (SELECT 1 FROM productos WHERE id_categoria = ?)');
            $stmt->bind_param('ii', $categoriaId, $categoriaId);
            $stmt->execute();
            if ($stmt->affected_rows === 1) {
                header('Location: /catalogo_presentaciones/?seccion=categorias&categoria_eliminada=1');
                exit();
            }
            $stmt = $db->prepare('SELECT COUNT(*) FROM productos WHERE id_categoria = ?');
            $stmt->bind_param('i', $categoriaId);
            $stmt->execute();
            $enUso = (int)$stmt->get_result()->fetch_row()[0] > 0;
            $errores[] = $enUso
                ? 'No se puede eliminar: hay productos asignados a esta categoría. Desactívala si ya no quieres utilizarla.'
                : 'No se encontró la categoría seleccionada.';
        }
    } else {
        $errores[] = 'La acción solicitada no es válida.';
    }
}

$presentaciones = $db->query('SELECT cp.id_catalogo_presentacion, cp.nombre, cp.nivel, cp.estado, COUNT(pp.id_producto_presentacion) AS productos FROM catalogo_presentaciones cp LEFT JOIN producto_presentaciones pp ON pp.id_catalogo_presentacion = cp.id_catalogo_presentacion GROUP BY cp.id_catalogo_presentacion, cp.nombre, cp.nivel, cp.estado ORDER BY cp.nivel DESC, cp.nombre')->fetch_all(MYSQLI_ASSOC);
foreach ($presentaciones as &$presentacion) {
    $presentacion['nombre'] = nombre_presentacion_visible($presentacion['nombre']);
}
unset($presentacion);
$categorias = $db->query('SELECT c.id_categoria, c.nombre, c.descripcion, c.estado, COUNT(p.id_producto) AS productos FROM categorias c LEFT JOIN productos p ON p.id_categoria = c.id_categoria GROUP BY c.id_categoria, c.nombre, c.descripcion, c.estado ORDER BY c.nombre')->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Presentaciones y categorías | Farmacia Fuente de Vida</title><link rel="stylesheet" href="../menu.css"><link rel="stylesheet" href="presentaciones.css"></head><body>
<?php include __DIR__ . '/../SideBar/menu.php'; ?>
<main class="main module-main">
    <header class="header"><div><h1>Presentaciones y categorías</h1><p>Administra los empaques y las categorías que usarás al registrar productos.</p></div><div class="user"><div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'U', 0, 1)), ENT_QUOTES, 'UTF-8') ?></div><div><strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($_SESSION['usuario_rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></div></div></header>
    <?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php elseif ($mensaje): ?><div class="alert success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <nav class="catalog-sections" aria-label="Secciones del catálogo"><a href="/catalogo_presentaciones/?seccion=presentaciones" class="<?= $seccion === 'presentaciones' ? 'active' : '' ?>" <?= $seccion === 'presentaciones' ? 'aria-current="page"' : '' ?>>Presentaciones</a><a href="/catalogo_presentaciones/?seccion=categorias" class="<?= $seccion === 'categorias' ? 'active' : '' ?>" <?= $seccion === 'categorias' ? 'aria-current="page"' : '' ?>>Categorías</a></nav>
    <?php if ($seccion === 'categorias'): ?>
    <section class="content-box" id="categorias">
        <div class="section-header"><div><h2>Categorías de productos</h2><p>Crea categorías como Vitaminas o Alergia para clasificarlas en el formulario de productos.</p></div></div>
        <form method="POST" class="presentation-form category-form"><?= csrf_input() ?><input type="hidden" name="seccion" value="categorias"><input type="hidden" name="accion" value="crear_categoria"><label>Nombre de la categoría<input name="nombre_categoria" maxlength="100" placeholder="Ej. Alergia" required><small>El nombre aparecerá como opción al registrar o editar productos.</small></label><label>Descripción<input name="descripcion_categoria" maxlength="255" placeholder="Ej. Productos para aliviar síntomas de alergia"><small>Opcional; sirve para identificar qué productos pertenecen aquí.</small></label><button class="btn-primary" type="submit">Agregar categoría</button></form>
        <div class="table-container category-table"><table><thead><tr><th>Categoría</th><th>Descripción</th><th>Productos</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
            <?php if (!$categorias): ?><tr><td colspan="5" class="empty-state">Aún no hay categorías. Agrega la primera desde el formulario.</td></tr><?php endif; ?>
            <?php foreach ($categorias as $categoria): ?><tr><td><strong><?= htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td><td><?= htmlspecialchars($categoria['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?: 'Sin descripción' ?></td><td><?= (int)$categoria['productos'] ?></td><td><span class="status-badge <?= (int)$categoria['estado'] ? 'active' : 'inactive' ?>"><?= (int)$categoria['estado'] ? 'Activa' : 'Inactiva' ?></span></td><td><div class="presentation-actions"><form method="POST"><?= csrf_input() ?><input type="hidden" name="seccion" value="categorias"><input type="hidden" name="accion" value="estado_categoria"><input type="hidden" name="id_categoria" value="<?= (int)$categoria['id_categoria'] ?>"><input type="hidden" name="estado" value="<?= (int)$categoria['estado'] ? 0 : 1 ?>"><button class="btn-secondary" type="submit"><?= (int)$categoria['estado'] ? 'Desactivar' : 'Activar' ?></button></form><form method="POST" onsubmit="return confirm('¿Eliminar esta categoría? Esta acción no se puede deshacer.');"><?= csrf_input() ?><input type="hidden" name="seccion" value="categorias"><input type="hidden" name="accion" value="eliminar_categoria"><input type="hidden" name="id_categoria" value="<?= (int)$categoria['id_categoria'] ?>"><button class="btn-danger" type="submit" <?= (int)$categoria['productos'] > 0 ? 'disabled title="No se puede eliminar porque tiene productos asignados."' : '' ?>>Eliminar</button></form></div><?php if ((int)$categoria['productos'] > 0): ?><small class="delete-hint">En uso: desactívala para conservar sus productos.</small><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
    <?php else: ?>
    <section class="content-box" id="presentaciones">
        <div class="section-header"><div><h2>Agregar una presentación</h2><p>Las presentaciones describen los empaques disponibles para configurar cada producto.</p></div></div>
        <ol class="presentation-guide"><li><strong>Unidad mínima</strong><span>Lo que se cuenta individualmente, por ejemplo una tableta.</span></li><li><strong>Empaque intermedio</strong><span>Un nivel superior que contiene varias unidades o empaques menores.</span></li><li><strong>Empaque mayor</strong><span>El nivel más alto, como una caja que contiene cajitas.</span></li></ol>
        <form method="POST" class="presentation-form"><?= csrf_input() ?><input type="hidden" name="seccion" value="presentaciones"><input type="hidden" name="accion" value="crear"><label>Nombre de la presentación<input name="nombre" maxlength="100" placeholder="Ej. Blíster" required><small>Usa un nombre corto y claro, por ejemplo: Unidad, Blíster, Cajita o Caja.</small></label><label>Nivel jerárquico<input type="number" name="nivel" min="1" max="50" value="1" required><small>1 = unidad mínima. Cada nivel superior representa un empaque mayor.</small></label><button class="btn-primary" type="submit">Agregar presentación</button></form>
        <p class="catalog-note"><strong>Importante:</strong> si una presentación ya está asignada a productos, no se podrá eliminar. En ese caso puedes desactivarla para evitar usarla en nuevas configuraciones.</p>
    </section>
    <section class="content-box"><div class="section-header"><div><h2>Presentaciones del catálogo</h2><p><?= count($presentaciones) ?> opción(es). Las opciones activas aparecen disponibles al registrar productos.</p></div></div><div class="table-container"><table><thead><tr><th>Presentación</th><th>Nivel</th><th>Productos configurados</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
        <?php if (!$presentaciones): ?><tr><td colspan="5" class="empty-state">Aún no hay presentaciones registradas.</td></tr><?php endif; ?>
        <?php foreach ($presentaciones as $presentacion): ?><tr><td><strong><?= htmlspecialchars($presentacion['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td><td><span class="level-badge">Nivel <?= (int)$presentacion['nivel'] ?></span></td><td><?= (int)$presentacion['productos'] ?></td><td><span class="status-badge <?= (int)$presentacion['estado'] ? 'active' : 'inactive' ?>"><?= (int)$presentacion['estado'] ? 'Activa' : 'Inactiva' ?></span></td><td><div class="presentation-actions"><form method="POST"><?= csrf_input() ?><input type="hidden" name="seccion" value="presentaciones"><input type="hidden" name="accion" value="estado"><input type="hidden" name="id_catalogo_presentacion" value="<?= (int)$presentacion['id_catalogo_presentacion'] ?>"><input type="hidden" name="estado" value="<?= (int)$presentacion['estado'] ? 0 : 1 ?>"><button class="btn-secondary" type="submit"><?= (int)$presentacion['estado'] ? 'Desactivar' : 'Activar' ?></button></form><form method="POST" onsubmit="return confirm('¿Eliminar esta presentación del catálogo? Esta acción no se puede deshacer.');"><?= csrf_input() ?><input type="hidden" name="seccion" value="presentaciones"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_catalogo_presentacion" value="<?= (int)$presentacion['id_catalogo_presentacion'] ?>"><button class="btn-danger" type="submit" <?= (int)$presentacion['productos'] > 0 ? 'disabled title="No se puede eliminar porque está asignada a productos."' : '' ?>>Eliminar</button></form></div><?php if ((int)$presentacion['productos'] > 0): ?><small class="delete-hint">En uso: desactívala en lugar de eliminarla.</small><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>
    <?php endif; ?>
</main><script src="/dev-reload.js"></script></body></html>
