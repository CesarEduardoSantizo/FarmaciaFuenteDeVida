<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$currentPath = '/' . trim($currentPath, '/') . '/';
$menuItems = [
    ['Inicio', '/index.php', '⌂'],
    ['Inventario', '/inventario/', '📦'],
    ['Productos', '/productos/', '💊'],
    ['Ventas', '/ventas/', '🛒'],
    ['Clientes', '/clientes/', '👥'],
    ['Compras', '/compras/', '🧾'],
    ['Proveedores', '/proveedores/', '🚚'],
    ['Reportes', '/reportes/', '📊'],
    ['Alertas', '/alertas/', '🔔'],
];
$menuIconImages = [
    '/index.php' => '/imagenes/inicio.png',
    '/inventario/' => '/imagenes/inventario.png',
    '/productos/' => '/imagenes/productos.png',
    '/ventas/' => '/imagenes/ventas.png',
    '/clientes/' => '/imagenes/clientes.png',
    '/compras/' => '/imagenes/compras.png',
    '/proveedores/' => '/imagenes/proveedores.png',
    '/reportes/' => '/imagenes/reporte.png',
    '/alertas/' => '/imagenes/alertas.png',
];
?>
<aside class="sidebar">
    <div class="logo">
        <img class="sidebar-brand-image" src="/imagenes/fuente.png" alt="Farmacia Fuente de Vida">
        <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Ocultar menú" aria-controls="sidebarNavigation" aria-expanded="true" title="Ocultar menú"><span class="sidebar-toggle-lines" aria-hidden="true"></span></button>
    </div>
    <nav id="sidebarNavigation">
        <?php foreach ($menuItems as [$label, $href, $icon]): ?>
            <?php $active = $href === '/index.php' ? $currentPath === '/index.php/' || $currentPath === '//' : $currentPath === '/' . trim($href, '/') . '/'; ?>
            <a class="<?= $active ? 'active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                <?php if (isset($menuIconImages[$href])): ?>
                    <img class="sidebar-option-image" src="<?= htmlspecialchars($menuIconImages[$href], ENT_QUOTES, 'UTF-8') ?>" alt="" width="24" height="24">
                <?php else: ?>
                    <span aria-hidden="true"><?= $icon ?></span>
                <?php endif; ?>
                <span class="sidebar-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>
        <?php if (($_SESSION['usuario_rol'] ?? '') === 'Administrador'): ?>
            <?php $active = $currentPath === '/usuarios/'; ?>
            <a class="<?= $active ? 'active' : '' ?>" href="/usuarios/" title="Usuarios" aria-label="Usuarios"><img class="sidebar-option-image" src="/imagenes/usuarios.png" alt="" width="24" height="24"><span class="sidebar-label">Usuarios</span></a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-bottom">
        <?php $active = $currentPath === '/configuracion/'; ?>
        <a class="<?= $active ? 'active' : '' ?>" href="/configuracion/" title="Configuración" aria-label="Configuración"><img class="sidebar-option-image" src="/imagenes/configuracion.png" alt="" width="24" height="24"><span class="sidebar-label">Configuración</span></a>
        <form method="POST" action="/logout/" class="logout-form">
            <button class="logout-btn" type="submit" title="Cerrar sesión" aria-label="Cerrar sesión"><img class="sidebar-option-image" src="/imagenes/cerrar%20sesion.png" alt="" width="24" height="24"><span class="sidebar-label">Cerrar sesión</span></button>
        </form>
    </div>
</aside>
<script>
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarPreferenceKey = 'farmacia-sidebar-collapsed';

    if (localStorage.getItem(sidebarPreferenceKey) === 'true') {
        document.documentElement.classList.add('sidebar-collapsed');
    }

    const updateSidebarToggle = () => {
        const collapsed = document.documentElement.classList.contains('sidebar-collapsed');
        sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
        sidebarToggle.setAttribute('aria-label', collapsed ? 'Mostrar menú' : 'Ocultar menú');
        sidebarToggle.title = collapsed ? 'Mostrar menú' : 'Ocultar menú';
    };

    updateSidebarToggle();
    sidebarToggle.addEventListener('click', () => {
        document.documentElement.classList.toggle('sidebar-collapsed');
        localStorage.setItem(sidebarPreferenceKey, String(document.documentElement.classList.contains('sidebar-collapsed')));
        updateSidebarToggle();
    });

    const updateCurrentDates = () => {
        const today = new Date();
        const dateText = new Intl.DateTimeFormat('es-GT', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        }).format(today);
        const dateTime = [
            today.getFullYear(),
            String(today.getMonth() + 1).padStart(2, '0'),
            String(today.getDate()).padStart(2, '0')
        ].join('-');

        document.querySelectorAll('.current-date').forEach((dateElement) => {
            dateElement.textContent = dateText;
            dateElement.dateTime = dateTime;
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('main .header').forEach((header) => {
            if (header.querySelector('.current-date')) {
                return;
            }

            const dateElement = document.createElement('time');
            dateElement.className = 'current-date';
            dateElement.setAttribute('aria-label', 'Fecha de hoy');
            header.insertBefore(dateElement, header.querySelector('.user'));
        });

        updateCurrentDates();
        window.setInterval(updateCurrentDates, 60000);

        document.querySelectorAll('form[data-auto-filter]').forEach((form) => {
            let searchTimer;
            const applyFilters = () => form.requestSubmit();

            form.querySelectorAll('select, input[type="date"]').forEach((field) => {
                field.addEventListener('change', applyFilters);
            });

            form.querySelectorAll('input[type="search"], input[type="text"]').forEach((field) => {
                field.addEventListener('input', () => {
                    window.clearTimeout(searchTimer);
                    searchTimer = window.setTimeout(applyFilters, 350);
                });
            });
        });
    });
</script>
