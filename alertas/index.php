<?php
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: /login/login.php'); exit(); }
require_once __DIR__ . '/../csrf.php';
validar_csrf();
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../config_helpers.php';
require_once __DIR__ . '/../permisos.php';
$db = conectar();
exigir_permiso_modulo($db, 'alertas');
$configuracion = cargar_configuracion($db);
$umbralStock = umbral_stock($configuracion);
$diasVencimiento = dias_alerta_vencimiento($configuracion);
$paginaSolicitada = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$registrosPorPagina = 15;
$filtro = $_GET['estado'] ?? 'Todas';
if (!in_array($filtro, ['Todas', 'Pendiente', 'Atendida'], true)) $filtro = 'Todas';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    $referencia = filter_input(INPUT_POST, 'referencia', FILTER_VALIDATE_INT);
    if (in_array($tipo, ['stock', 'vencimiento'], true) && $referencia) {
        $stmt = $db->prepare('INSERT INTO alertas_atendidas (tipo, referencia, atendida_por) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE atendida_por = VALUES(atendida_por), atendida_en = CURRENT_TIMESTAMP');
        $stmt->bind_param('sii', $tipo, $referencia, $_SESSION['usuario_id']);
        $stmt->execute();
    }
    $estadoRetorno = $_POST['estado'] ?? 'Todas';
    if (!in_array($estadoRetorno, ['Todas', 'Pendiente', 'Atendida'], true)) $estadoRetorno = 'Todas';
    $paginaRetorno = filter_var($_POST['pagina'] ?? 1, FILTER_VALIDATE_INT) ?: 1;
    header('Location: /alertas/?' . http_build_query(['estado' => $estadoRetorno, 'pagina' => $paginaRetorno]));
    exit();
}
$alertas = [];
$stmt = $db->prepare("SELECT s.id_producto AS referencia, 'stock' AS tipo_clave, 'Stock bajo' AS tipo, '⚠️' AS icono, CONCAT(s.nombre, ' tiene ', s.stock_actual, ' unidades; mínimo: ', GREATEST(s.stock_minimo, ?), '.') AS detalle, 'Alta' AS prioridad, COALESCE(a.atendida_por, 0) AS atendida_por, COALESCE(a.atendida_en, CURDATE()) AS fecha FROM vista_inventario s INNER JOIN productos p ON p.id_producto = s.id_producto LEFT JOIN alertas_atendidas a ON a.tipo = 'stock' AND a.referencia = s.id_producto WHERE p.estado = 1 AND s.stock_actual <= GREATEST(s.stock_minimo, ?) UNION ALL SELECT l.id_lote AS referencia, 'vencimiento' AS tipo_clave, 'Próximo a vencer' AS tipo, '📅' AS icono, CONCAT(p.nombre, ' · lote ', l.numero_lote, ' vence el ', DATE_FORMAT(l.fecha_vencimiento, '%d/%m/%Y'), '.') AS detalle, CASE WHEN DATEDIFF(l.fecha_vencimiento, CURDATE()) <= 7 THEN 'Alta' ELSE 'Media' END AS prioridad, COALESCE(a.atendida_por, 0) AS atendida_por, COALESCE(a.atendida_en, CURDATE()) AS fecha FROM lotes l INNER JOIN productos p ON p.id_producto = l.id_producto LEFT JOIN alertas_atendidas a ON a.tipo = 'vencimiento' AND a.referencia = l.id_lote WHERE l.estado = 1 AND l.cantidad > 0 AND l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) ORDER BY prioridad, tipo");
$stmt->bind_param('iii', $umbralStock, $umbralStock, $diasVencimiento);
$stmt->execute();
$resultado = $stmt->get_result();
while ($fila = $resultado->fetch_assoc()) {
    $fila['id'] = $fila['tipo_clave'] . '-' . $fila['referencia'];
    $fila['estado'] = (int)$fila['atendida_por'] > 0 ? 'Atendida' : 'Pendiente';
    $alertas[] = $fila;
}
$filtradas = array_filter($alertas, fn (array $alerta): bool => in_array($filtro, ['Todas', 'Pendiente', 'Atendida'], true) && ($filtro === 'Todas' || $alerta['estado'] === $filtro));
$pendientes = count(array_filter($alertas, fn (array $alerta): bool => $alerta['estado'] === 'Pendiente'));
$totalAlertas = count($filtradas);
$totalPaginas = max(1, (int)ceil($totalAlertas / $registrosPorPagina));
$paginaActual = min(max(1, $paginaSolicitada), $totalPaginas);
$offset = ($paginaActual - 1) * $registrosPorPagina;
$inicioPagina = max(1, $paginaActual - 2);
$finPagina = min($totalPaginas, $paginaActual + 2);
$alertasPorPagina = array_values(array_slice($filtradas, $offset, $registrosPorPagina));
$urlBasePaginacion = '/alertas/?' . http_build_query(['estado' => $filtro]);
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Alertas | Farmacia Fuente de Vida</title><link rel="stylesheet" href="../menu.css"><link rel="stylesheet" href="alertas.css"></head>
<body>
<?php include __DIR__ . '/../SideBar/menu.php'; ?>
<main class="main module-main"><header class="header"><div><h1>Alertas</h1><p>Productos y eventos que requieren atención</p></div><div class="user"><div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'U', 0, 1)), ENT_QUOTES, 'UTF-8') ?></div><div><strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($_SESSION['usuario_rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></div></div></header>
<section class="alert-summary"><div class="summary-icon">🔔</div><div><strong><?= $pendientes ?> alertas pendientes</strong><p>Revisa las novedades del sistema para mantener la farmacia al día.</p></div></section>
<section class="content-box"><div class="section-header"><div><h2>Centro de alertas</h2><p><?= $totalAlertas ?> alerta(s)<?= $totalAlertas ? ' · Página ' . $paginaActual . ' de ' . $totalPaginas : '' ?> · Stock bajo y lotes próximos a vencer</p></div><form class="filter" method="GET"><label for="estado">Mostrar</label><select id="estado" name="estado" onchange="this.form.submit()"><option <?= $filtro === 'Todas' ? 'selected' : '' ?>>Todas</option><option <?= $filtro === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option><option <?= $filtro === 'Atendida' ? 'selected' : '' ?>>Atendida</option></select></form></div><div class="alert-table"><table><thead><tr><th>Tipo</th><th>Detalle</th><th>Prioridad</th><th>Estado</th><th>Fecha</th><th>Acción</th></tr></thead><tbody><?php if (!$alertasPorPagina): ?><tr><td colspan="6" class="empty-state">No hay alertas para mostrar.</td></tr><?php endif; ?><?php foreach ($alertasPorPagina as $alerta): ?><tr><td><span class="alert-type"><span><?= $alerta['icono'] ?></span><?= htmlspecialchars($alerta['tipo'], ENT_QUOTES, 'UTF-8') ?></span></td><td><?= htmlspecialchars($alerta['detalle'], ENT_QUOTES, 'UTF-8') ?></td><td><span class="priority <?= strtolower($alerta['prioridad']) ?>"><?= $alerta['prioridad'] ?></span></td><td><span class="state <?= strtolower($alerta['estado']) ?>"><?= $alerta['estado'] ?></span></td><td><?= htmlspecialchars((string)$alerta['fecha'], ENT_QUOTES, 'UTF-8') ?></td><td><?php if ($alerta['estado'] === 'Pendiente'): ?><form method="POST"><input type="hidden" name="tipo" value="<?= htmlspecialchars($alerta['tipo_clave'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="referencia" value="<?= (int)$alerta['referencia'] ?>"><input type="hidden" name="estado" value="<?= htmlspecialchars($filtro, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="pagina" value="<?= $paginaActual ?>"><?= csrf_input() ?>
<button class="action-button" type="submit">Marcar atendida</button></form><?php else: ?>Resuelta<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php if ($totalPaginas > 1): ?><nav class="pagination" aria-label="Paginación de alertas"><?php if ($paginaActual > 1): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . ($paginaActual - 1), ENT_QUOTES, 'UTF-8') ?>">Anterior</a><?php endif; ?><?php if ($inicioPagina > 1): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=1', ENT_QUOTES, 'UTF-8') ?>">1</a><?php if ($inicioPagina > 2): ?><span aria-hidden="true">…</span><?php endif; ?><?php endif; ?><?php for ($pagina = $inicioPagina; $pagina <= $finPagina; $pagina++): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . $pagina, ENT_QUOTES, 'UTF-8') ?>" class="<?= $pagina === $paginaActual ? 'current' : '' ?>" <?= $pagina === $paginaActual ? 'aria-current="page"' : '' ?>><?= $pagina ?></a><?php endfor; ?><?php if ($finPagina < $totalPaginas): ?><?php if ($finPagina < $totalPaginas - 1): ?><span aria-hidden="true">…</span><?php endif; ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . $totalPaginas, ENT_QUOTES, 'UTF-8') ?>"><?= $totalPaginas ?></a><?php endif; ?><?php if ($paginaActual < $totalPaginas): ?><a href="<?= htmlspecialchars($urlBasePaginacion . '&pagina=' . ($paginaActual + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a><?php endif; ?></nav><?php endif; ?></section></main>
<script src="/dev-reload.js"></script>
</body></html>
