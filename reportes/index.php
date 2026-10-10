<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: /login/login.php'); exit(); }
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../config_helpers.php';
require_once __DIR__ . '/../permisos.php';
$db = conectar();
exigir_permiso_modulo($db, 'reportes');
$configuracion = cargar_configuracion($db);
$umbralStock = umbral_stock($configuracion);
$diasVencimiento = dias_alerta_vencimiento($configuracion);

$tipos = ['Inventario general', 'Productos con stock bajo', 'Productos próximos a vencer', 'Ventas por fecha', 'Ventas por producto', 'Compras a proveedores', 'Compras por día', 'Compras por semana', 'Compras por mes'];
$tiposConFechas = ['Ventas por fecha', 'Ventas por producto', 'Compras a proveedores', 'Compras por día', 'Compras por semana', 'Compras por mes'];
$gruposReportes = [
    'Inventario' => [
        'Inventario general' => 'Consulta las existencias actuales de todos los productos.',
        'Productos con stock bajo' => 'Identifica productos que necesitan reposición.',
        'Productos próximos a vencer' => 'Revisa los lotes cercanos a su fecha de vencimiento.',
    ],
    'Ventas' => [
        'Ventas por fecha' => 'Resume cuántas ventas y cuánto se vendió cada día.',
        'Ventas por producto' => 'Consulta las cantidades y los ingresos de cada producto vendido.',
    ],
    'Compras' => [
        'Compras a proveedores' => 'Consulta las compras registradas y sus proveedores.',
        'Compras por día' => 'Compara compras e inversión por día.',
        'Compras por semana' => 'Compara compras e inversión por semana.',
        'Compras por mes' => 'Compara compras e inversión por mes.',
    ],
];
$tipo = trim($_POST['tipo'] ?? $_GET['tipo'] ?? '');
if (!in_array($tipo, $tipos, true)) { $tipo = ''; }
$descripcionTipo = '';
foreach ($gruposReportes as $opcionesReporte) {
    if (isset($opcionesReporte[$tipo])) {
        $descripcionTipo = $opcionesReporte[$tipo];
        break;
    }
}
$desde = trim($_POST['desde'] ?? date('Y-m-d', strtotime('-30 days')));
$hasta = trim($_POST['hasta'] ?? date('Y-m-d'));
$errores = [];
$generado = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($tipo === '') $errores[] = 'Seleccione un tipo de reporte.';
    if (in_array($tipo, $tiposConFechas, true)) {
        $fechaDesde = DateTime::createFromFormat('Y-m-d', $desde);
        $fechaHasta = DateTime::createFromFormat('Y-m-d', $hasta);
        if (!$fechaDesde || $fechaDesde->format('Y-m-d') !== $desde) $errores[] = 'Ingrese una fecha inicial válida.';
        if (!$fechaHasta || $fechaHasta->format('Y-m-d') !== $hasta) $errores[] = 'Ingrese una fecha final válida.';
        if (!$errores && $fechaDesde > $fechaHasta) $errores[] = 'La fecha inicial no puede ser posterior a la fecha final.';
    }
    $generado = !$errores;
}

$filas = [];
$encabezados = [];
$consultas = [
    'Inventario general' => ["SELECT codigo AS Código, nombre AS Producto, categoria AS Categoría, stock_actual AS Stock, precio_venta AS Precio, stock_minimo AS `Stock mínimo` FROM vista_inventario ORDER BY nombre", ['Código', 'Producto', 'Categoría', 'Stock', 'Precio', 'Stock mínimo'], false],
    'Productos con stock bajo' => ["SELECT codigo AS Código, nombre AS Producto, categoria AS Categoría, stock_actual AS Stock, GREATEST(stock_minimo, {$umbralStock}) AS `Stock mínimo` FROM vista_inventario WHERE stock_actual <= GREATEST(stock_minimo, {$umbralStock}) ORDER BY stock_actual", ['Código', 'Producto', 'Categoría', 'Stock', 'Stock mínimo'], false],
    'Productos próximos a vencer' => ["SELECT p.codigo AS Código, p.nombre AS Producto, l.numero_lote AS Lote, l.fecha_vencimiento AS Vencimiento, l.cantidad AS Stock, DATEDIFF(l.fecha_vencimiento, CURDATE()) AS `Días restantes` FROM lotes l INNER JOIN productos p ON p.id_producto = l.id_producto WHERE l.estado = 1 AND l.cantidad > 0 AND l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL {$diasVencimiento} DAY) ORDER BY l.fecha_vencimiento", ['Código', 'Producto', 'Lote', 'Vencimiento', 'Stock', 'Días restantes'], false],
    'Ventas por fecha' => ["SELECT DATE_FORMAT(fecha_venta, '%d/%m/%Y') AS Fecha, COUNT(*) AS `Cantidad de ventas`, SUM(total) AS `Total vendido` FROM ventas WHERE estado = 'Completada' AND fecha_venta >= ? AND fecha_venta < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY DATE(fecha_venta) ORDER BY DATE(fecha_venta) DESC", ['Fecha', 'Cantidad de ventas', 'Total vendido'], true],
    'Ventas por producto' => ["SELECT DATE_FORMAT(fecha_venta, '%d/%m/%Y') AS Fecha, producto AS Producto, COALESCE(presentacion, 'Unidad') AS Presentación, SUM(cantidad) AS Paquetes, SUM(cantidad_base) AS `Unidades mínimas`, SUM(subtotal) AS Total FROM vista_reporte_ventas WHERE fecha_venta >= ? AND fecha_venta < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY id_venta, DATE(fecha_venta), producto, presentacion ORDER BY fecha_venta DESC, producto", ['Fecha', 'Producto', 'Presentación', 'Paquetes', 'Unidades mínimas', 'Total'], true],
    'Compras a proveedores' => ["SELECT DATE_FORMAT(c.fecha_compra, '%d/%m/%Y') AS Fecha, p.nombre AS Proveedor, COUNT(dc.id_detalle_compra) AS Productos, c.total AS Total FROM compras c INNER JOIN proveedores p ON p.id_proveedor = c.id_proveedor LEFT JOIN detalle_compras dc ON dc.id_compra = c.id_compra WHERE c.fecha_compra >= ? AND c.fecha_compra < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY c.id_compra, c.fecha_compra, p.nombre, c.total ORDER BY c.fecha_compra DESC", ['Fecha', 'Proveedor', 'Productos', 'Total'], true],
    'Compras por día' => ["SELECT DATE_FORMAT(fecha_compra, '%d/%m/%Y') AS Periodo, COUNT(*) AS Compras, SUM(total) AS `Gasto total` FROM compras WHERE estado = 'Completada' AND fecha_compra >= ? AND fecha_compra < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY DATE(fecha_compra) ORDER BY DATE(fecha_compra) DESC", ['Día', 'Compras', 'Gasto total'], true],
    'Compras por semana' => ["SELECT CONCAT(YEAR(fecha_compra), '-S', LPAD(WEEK(fecha_compra, 1), 2, '0')) AS Periodo, COUNT(*) AS Compras, SUM(total) AS `Gasto total` FROM compras WHERE estado = 'Completada' AND fecha_compra >= ? AND fecha_compra < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY YEAR(fecha_compra), WEEK(fecha_compra, 1) ORDER BY YEAR(fecha_compra) DESC, WEEK(fecha_compra, 1) DESC", ['Semana', 'Compras', 'Gasto total'], true],
    'Compras por mes' => ["SELECT DATE_FORMAT(fecha_compra, '%m/%Y') AS Periodo, COUNT(*) AS Compras, SUM(total) AS `Gasto total` FROM compras WHERE estado = 'Completada' AND fecha_compra >= ? AND fecha_compra < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY YEAR(fecha_compra), MONTH(fecha_compra) ORDER BY YEAR(fecha_compra) DESC, MONTH(fecha_compra) DESC", ['Mes', 'Compras', 'Gasto total'], true],
];
if ($tipo !== '' && isset($consultas[$tipo]) && (!$errores || !in_array(($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['POST'], true))) {
    [$sql, $encabezados, $usaFechas] = $consultas[$tipo];
    if ($usaFechas) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ss', $desde, $hasta);
        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $filas = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
    foreach ($filas as &$filaReporte) {
        foreach ($filaReporte as $columna => &$valorReporte) {
            if (mb_strtolower((string)$columna, 'UTF-8') === 'presentación') {
                $valorReporte = nombre_presentacion_visible((string)$valorReporte);
            }
        }
        unset($valorReporte);
    }
    unset($filaReporte);
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Reportes | Farmacia Fuente de Vida</title><link rel="stylesheet" href="../menu.css"><link rel="stylesheet" href="reportes.css"></head>
<body>
<?php include __DIR__ . '/../SideBar/menu.php'; ?>
<main class="main module-main"><header class="header no-print"><div><h1>Reportes</h1><p>Generación e impresión de reportes</p></div><div class="user"><div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'U', 0, 1)), ENT_QUOTES, 'UTF-8') ?></div><div><strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($_SESSION['usuario_rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></div></div></header>
<?php if ($errores): ?><div class="alert error no-print"><ul><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php elseif ($generado): ?><div class="alert success no-print">Reporte generado desde la base de datos: <?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>.</div><?php endif; ?>
<section class="content-box report-generator no-print">
    <div class="section-header">
        <div>
            <h2>Generar reporte</h2>
            <p>Elige el área y el tipo de información que quieres consultar.</p>
        </div>
    </div>
    <form method="POST" class="report-form" id="reportForm" novalidate>
        <input type="hidden" name="tipo" id="reportType" value="<?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>">
        <fieldset class="report-type-fieldset" tabindex="-1" aria-describedby="reportSelectionError">
            <legend><span class="report-step">1</span> Elige el tipo de reporte</legend>
            <p class="report-selection-error" id="reportSelectionError" role="alert" hidden>Seleccione un tipo de reporte para continuar.</p>
            <p class="report-selection-hint" id="reportSelectionHint" aria-live="polite"><?= htmlspecialchars($descripcionTipo ?: 'Selecciona una opción para ver qué información incluirá.', ENT_QUOTES, 'UTF-8') ?></p>
            <div class="report-categories">
                <?php foreach ($gruposReportes as $grupo => $opciones): ?>
                    <section class="report-category" aria-label="<?= htmlspecialchars($grupo, ENT_QUOTES, 'UTF-8') ?>">
                        <h3><?= htmlspecialchars($grupo, ENT_QUOTES, 'UTF-8') ?></h3>
                        <div class="report-type-buttons">
                            <?php foreach ($opciones as $opcion => $descripcion): ?>
                                <button class="report-type-button <?= $tipo === $opcion ? 'selected' : '' ?>" type="button" data-report="<?= htmlspecialchars($opcion, ENT_QUOTES, 'UTF-8') ?>" data-description="<?= htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8') ?>" data-uses-dates="<?= in_array($opcion, $tiposConFechas, true) ? 'true' : 'false' ?>" aria-pressed="<?= $tipo === $opcion ? 'true' : 'false' ?>">
                                    <strong><?= htmlspecialchars($opcion, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8') ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <section class="report-date-section" id="dateSection" <?= in_array($tipo, $tiposConFechas, true) ? '' : 'hidden' ?>>
            <h3><span class="report-step">2</span> Define el periodo</h3>
            <p>Selecciona las fechas que quieres incluir en el reporte.</p>
            <div class="date-presets" aria-label="Periodos rápidos">
                <button type="button" data-date-preset="today">Hoy</button>
                <button type="button" data-date-preset="7">Últimos 7 días</button>
                <button type="button" data-date-preset="30">Últimos 30 días</button>
                <button type="button" data-date-preset="month">Este mes</button>
            </div>
            <div class="date-grid" id="dateFilters">
                <label>Desde<input type="date" name="desde" value="<?= htmlspecialchars($desde, ENT_QUOTES, 'UTF-8') ?>" required></label>
                <label>Hasta<input type="date" name="hasta" value="<?= htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8') ?>" required></label>
            </div>
            <p class="report-date-error" id="reportDateError" role="alert" hidden>La fecha inicial no puede ser posterior a la fecha final.</p>
        </section>
        <div class="report-submit-row">
            <p id="reportSubmitHint"><?= $tipo && !in_array($tipo, $tiposConFechas, true) ? 'Este reporte utiliza los datos actuales y no necesita fechas.' : 'El reporte aparecerá debajo y podrás imprimirlo o guardarlo como PDF.' ?></p>
            <button class="btn-primary" type="submit">Generar reporte</button>
        </div>
    </form>
</section>
<section class="content-box report-preview <?= $tipo === '' ? 'empty-preview' : '' ?>"><div class="preview-header"><div><span class="preview-kicker">Vista previa</span><h2><?= $tipo ? htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') : 'Seleccione un reporte' ?></h2><?php if (in_array($tipo, $tiposConFechas, true)): ?><p>Periodo: <?= htmlspecialchars($desde, ENT_QUOTES, 'UTF-8') ?> al <?= htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?></div><button class="btn-primary no-print" type="button" onclick="window.print()" <?= $tipo === '' ? 'disabled' : '' ?>>Imprimir / Guardar PDF</button></div><?php if ($tipo): ?><div class="table-container"><table><thead><tr><?php foreach ($encabezados as $encabezado): ?><th><?= htmlspecialchars($encabezado, ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($filas as $fila): ?><tr><?php foreach ($fila as $valor): ?><td><?= htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8') ?></td><?php endforeach; ?></tr><?php endforeach; ?><?php if (!$filas): ?><tr><td colspan="<?= max(1, count($encabezados)) ?>" class="empty-state">No hay datos para este reporte.</td></tr><?php endif; ?></tbody></table></div><p class="report-note">Datos consultados de la base de datos.</p><?php else: ?><div class="empty-state">Elige una opción de arriba para ver sus datos.</div><?php endif; ?></section>
</main>
<script>
    const reportForm = document.getElementById('reportForm');
    const reportType = document.getElementById('reportType');
    const reportButtons = document.querySelectorAll('.report-type-button');
    const reportSelectionError = document.getElementById('reportSelectionError');
    const reportSelectionHint = document.getElementById('reportSelectionHint');
    const reportSubmitHint = document.getElementById('reportSubmitHint');
    const dateSection = document.getElementById('dateSection');
    const dateInputs = [...dateSection.querySelectorAll('input[type="date"]')];
    const reportDateError = document.getElementById('reportDateError');
    const [dateFrom, dateTo] = dateInputs;
    const dateToInput = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const selectReport = (type) => {
        reportType.value = type;
        reportSelectionError.hidden = true;
        reportButtons.forEach((button) => {
            const selected = button.dataset.report === type;
            button.classList.toggle('selected', selected);
            button.setAttribute('aria-pressed', String(selected));
            if (selected) reportSelectionHint.textContent = button.dataset.description;
        });
        const selectedButton = [...reportButtons].find((button) => button.dataset.report === type);
        const usesDates = selectedButton?.dataset.usesDates === 'true';
        dateSection.hidden = !usesDates;
        dateInputs.forEach((input) => {
            input.disabled = !usesDates;
        });
        reportDateError.hidden = true;
        reportSubmitHint.textContent = usesDates
            ? 'El reporte incluirá los movimientos dentro del periodo seleccionado.'
            : 'Este reporte utiliza los datos actuales y no necesita fechas.';
    };

    reportButtons.forEach((button) => {
        button.addEventListener('click', () => {
            selectReport(button.dataset.report);
        });
    });

    document.querySelectorAll('[data-date-preset]').forEach((button) => {
        button.addEventListener('click', () => {
            const today = new Date();
            const start = new Date(today);
            const preset = button.dataset.datePreset;
            if (preset === 'today') {
                start.setTime(today.getTime());
            } else if (preset === 'month') {
                start.setDate(1);
            } else {
                start.setDate(today.getDate() - Number(preset) + 1);
            }
            dateFrom.value = dateToInput(start);
            dateTo.value = dateToInput(today);
            reportDateError.hidden = true;
        });
    });

    reportForm.addEventListener('submit', (event) => {
        if (!reportType.value) {
            event.preventDefault();
            reportSelectionError.hidden = false;
            document.querySelector('.report-type-fieldset').focus();
            return;
        }
        const selectedButton = [...reportButtons].find((button) => button.dataset.report === reportType.value);
        if (selectedButton?.dataset.usesDates === 'true') {
            const invalidDate = dateInputs.find((input) => !input.value || !input.checkValidity());
            if (invalidDate) {
                event.preventDefault();
                invalidDate.reportValidity();
                return;
            }
            if (dateFrom.value > dateTo.value) {
                event.preventDefault();
                reportDateError.hidden = false;
                dateFrom.focus();
            }
        }
    });

    if (reportType.value) selectReport(reportType.value);
</script>
<script src="/dev-reload.js"></script></body></html>
