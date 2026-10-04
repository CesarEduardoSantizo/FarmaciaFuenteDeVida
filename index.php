<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login/login.php');
    exit();
}
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/config_helpers.php';
$db = conectar();
$configuracion = cargar_configuracion($db);
$umbralStock = umbral_stock($configuracion);
$diasVencimiento = dias_alerta_vencimiento($configuracion);
$simboloMoneda = simbolo_moneda($configuracion);
$resumen = $db->query("SELECT (SELECT COUNT(*) FROM productos WHERE estado = 1) AS productos, (SELECT COUNT(*) FROM vista_inventario WHERE stock_actual <= GREATEST(stock_minimo, {$umbralStock})) AS stock_bajo, (SELECT COUNT(*) FROM ventas WHERE estado = 'Completada' AND DATE(fecha_venta) = CURDATE()) AS ventas_hoy, (SELECT COALESCE(SUM(total), 0) FROM ventas WHERE estado = 'Completada' AND DATE(fecha_venta) = CURDATE()) AS total_hoy")->fetch_assoc();
$inventario = $db->query("SELECT vi.*, (SELECT MIN(l.fecha_vencimiento) FROM lotes l WHERE l.id_producto = vi.id_producto AND l.estado = 1 AND l.cantidad > 0) AS vencimiento FROM vista_inventario vi INNER JOIN productos p ON p.id_producto = vi.id_producto WHERE p.estado = 1 ORDER BY vi.nombre LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$stmt = $db->prepare("SELECT 'Stock bajo' AS tipo, CONCAT(nombre, ' tiene ', stock_actual, ' unidades; mínimo: ', GREATEST(stock_minimo, ?), '.') AS detalle FROM vista_inventario WHERE stock_actual <= GREATEST(stock_minimo, ?) UNION ALL SELECT 'Próximo a vencer', CONCAT(p.nombre, ' · lote ', l.numero_lote, ' vence el ', DATE_FORMAT(l.fecha_vencimiento, '%d/%m/%Y'), '.') FROM lotes l INNER JOIN productos p ON p.id_producto = l.id_producto WHERE l.estado = 1 AND l.cantidad > 0 AND l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) ORDER BY tipo LIMIT 6");
$stmt->bind_param('iii', $umbralStock, $umbralStock, $diasVencimiento);
$stmt->execute();
$alertas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmacia Fuente de Vida</title>

    <link rel="stylesheet" href="menu.css">
</head>

<body>

    <?php include __DIR__ . '/SideBar/menu.php'; ?>


    <!-- Contenido principal -->
    <main class="main">

        <!-- Encabezado -->
        <header class="header">

            <button class="menu-btn" id="menuBtn">
                ☰
            </button>

            <div>
                <h1>Dashboard</h1>
                <p>Bienvenido al sistema de gestión</p>
            </div>

            <div class="user">
                <div class="user-avatar">
                    A
                </div>

                <div>
                    <strong><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= htmlspecialchars($_SESSION['usuario_rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                </div>
            </div>

        </header>


        <!-- Tarjetas -->
        <section class="cards">

            <div class="card">
                <div class="card-icon">
                    <img src="/imagenes/productos.png" alt="" width="24" height="24">
                </div>

                <div>
                    <span>Total de productos</span>
                    <h2><?= (int)$resumen['productos'] ?></h2>
                </div>
            </div>


            <div class="card">
                <div class="card-icon">
                    <img src="/imagenes/alertas.png" alt="" width="24" height="24">
                </div>

                <div>
                    <span>Stock bajo</span>
                    <h2><?= (int)$resumen['stock_bajo'] ?></h2>
                </div>
            </div>


            <div class="card">
                <div class="card-icon">
                    <img src="/imagenes/ventas.png" alt="" width="24" height="24">
                </div>

                <div>
                    <span>Ventas del día</span>
                    <h2><?= (int)$resumen['ventas_hoy'] ?></h2>
                </div>
            </div>


            <div class="card">
                <div class="card-icon">
                    <img src="/imagenes/ventas.png" alt="" width="24" height="24">
                </div>

                <div>
                    <span>Total vendido hoy</span>
                    <h2><?= $simboloMoneda ?> <?= number_format((float)$resumen['total_hoy'], 2) ?></h2>
                </div>
            </div>

        </section>


        <!-- Inventario -->
        <section class="content-box">

            <div class="section-header">

                <div>
                    <h2>Inventario</h2>
                    <p>Productos registrados en la farmacia</p>
                </div>

                <a class="btn-primary" href="/productos/">
                    + Agregar producto
                </a>

            </div>


            <!-- Buscador -->
            <form class="search dashboard-search" action="/inventario/" method="GET" data-auto-filter>
                <input type="search" name="buscar" placeholder="Buscar producto..." aria-label="Buscar producto por nombre o código">
                <a class="clear-filters" href="/inventario/">Limpiar</a>
            </form>


            <!-- Tabla -->
            <div class="table-container">

                <table>

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Stock</th>
                            <th>Precio</th>
                            <th>Vencimiento</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody><?php if (!$inventario): ?><tr><td colspan="7">No hay productos registrados.</td></tr><?php endif; ?>
                    <?php foreach ($inventario as $producto): ?><tr><td><?= (int)$producto['id_producto'] ?></td><td><strong><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td><td><?= htmlspecialchars($producto['categoria'], ENT_QUOTES, 'UTF-8') ?></td><td><span class="stock <?= (int)$producto['stock_actual'] <= max((int)$producto['stock_minimo'], $umbralStock) ? 'low' : 'normal' ?>"><?= (int)$producto['stock_actual'] ?></span></td><td><?= $simboloMoneda ?> <?= number_format((float)$producto['precio_venta'], 2) ?></td><td><?= $producto['vencimiento'] ? date('d/m/Y', strtotime($producto['vencimiento'])) : 'Sin lote' ?></td><td class="actions"><a class="edit" href="/productos/?id=<?= (int)$producto['id_producto'] ?>">Editar</a></td></tr><?php endforeach; ?></tbody>

                </table>

            </div>

        </section>


        <!-- Alertas -->
        <section class="content-box">

            <div class="section-header">

                <div>
                    <h2>Alertas</h2>
                    <p>Productos que requieren atención</p>
                </div>

            </div>

            <div class="alert-list"><?php if (!$alertas): ?><p>No hay alertas activas.</p><?php endif; ?><?php foreach ($alertas as $alerta): ?><div class="alert-item"><span><?= $alerta['tipo'] === 'Stock bajo' ? '⚠️' : '📅' ?></span><div><strong><?= htmlspecialchars($alerta['tipo'], ENT_QUOTES, 'UTF-8') ?></strong><p><?= htmlspecialchars($alerta['detalle'], ENT_QUOTES, 'UTF-8') ?></p></div></div><?php endforeach; ?></div>

        </section>

    </main>


    <script>

        const menuBtn = document.getElementById("menuBtn");
        const sidebar = document.querySelector(".sidebar");

        menuBtn.addEventListener("click", () => {
            sidebar.classList.toggle("show");
        });

    </script>
    <script src="/dev-reload.js"></script>

</body>
</html>