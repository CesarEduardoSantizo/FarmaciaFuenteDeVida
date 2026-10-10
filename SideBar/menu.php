<?php
require_once __DIR__ . '/../permisos.php';
require_once __DIR__ . '/../csrf.php';
$permisosMenu = permisos_de_rol($db, (string)($_SESSION['usuario_rol'] ?? ''));
$modulosPorRuta = [];
foreach (catalogo_modulos_sistema() as $claveModulo => $datosModulo) {
    $modulosPorRuta[$datosModulo['ruta']] = $claveModulo;
}
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$currentPath = '/' . trim($currentPath, '/') . '/';
$menuItems = [
    ['Inicio', '/index.php', '⌂'],
    ['Inventario', '/inventario/', '📦'],
    ['Productos', '/productos/', '💊'],
    ['Presentaciones y categorías', '/catalogo_presentaciones/', '🧴'],
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
    '/catalogo_presentaciones/' => '/imagenes/Presentacion.png',
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
            <?php if (empty($permisosMenu[$modulosPorRuta[$href] ?? ''])) continue; ?>
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
        <?php if (($_SESSION['usuario_rol'] ?? '') === 'Administrador' && !empty($permisosMenu['usuarios'])): ?>
            <?php $active = $currentPath === '/usuarios/'; ?>
            <a class="<?= $active ? 'active' : '' ?>" href="/usuarios/" title="Usuarios y permisos" aria-label="Usuarios y permisos"><img class="sidebar-option-image" src="/imagenes/usuarios.png" alt="" width="24" height="24"><span class="sidebar-label">Usuarios y permisos</span></a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-bottom">
        <?php if (!empty($permisosMenu['configuracion'])): ?>
            <?php $active = $currentPath === '/configuracion/'; ?>
            <a class="<?= $active ? 'active' : '' ?>" href="/configuracion/" title="Configuración" aria-label="Configuración"><img class="sidebar-option-image" src="/imagenes/configuracion.png" alt="" width="24" height="24"><span class="sidebar-label">Configuración</span></a>
        <?php endif; ?>
        <form method="POST" action="/logout/" class="logout-form" data-logout-form>
            <?= csrf_input() ?>
            <button class="logout-btn" type="submit" title="Cerrar sesión" aria-label="Cerrar sesión"><img class="sidebar-option-image" src="/imagenes/cerrar%20sesion.png" alt="" width="24" height="24"><span class="sidebar-label">Cerrar sesión</span></button>
        </form>
    </div>
</aside>
<dialog class="logout-confirm-dialog" id="logoutConfirmDialog" aria-labelledby="logoutConfirmTitle" aria-describedby="logoutConfirmMessage">
    <div class="logout-confirm-icon" aria-hidden="true">↪</div>
    <h2 id="logoutConfirmTitle">Cerrar sesión</h2>
    <p id="logoutConfirmMessage">¿Estás seguro de que deseas cerrar tu sesión?</p>
    <div class="logout-confirm-actions">
        <button class="logout-cancel-button" id="logoutConfirmCancel" type="button">Cancelar</button>
        <button class="logout-submit-button" id="logoutConfirmSubmit" type="button">Cerrar sesión</button>
    </div>
</dialog>
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
        const logoutDialog = document.getElementById('logoutConfirmDialog');
        const logoutCancelButton = document.getElementById('logoutConfirmCancel');
        const logoutSubmitButton = document.getElementById('logoutConfirmSubmit');
        let logoutFormToSubmit = null;

        document.querySelectorAll('[data-logout-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (logoutFormToSubmit === form) {
                    logoutFormToSubmit = null;
                    return;
                }

                event.preventDefault();
                logoutFormToSubmit = form;
                logoutDialog.returnValue = '';
                logoutDialog.showModal();
                logoutCancelButton.focus();
            });
        });

        logoutCancelButton.addEventListener('click', () => {
            logoutFormToSubmit = null;
            logoutDialog.close();
        });
        logoutSubmitButton.addEventListener('click', () => {
            if (!logoutFormToSubmit) {
                return;
            }

            const form = logoutFormToSubmit;
            logoutDialog.close('confirm');
            form.requestSubmit();
        });
        logoutDialog.addEventListener('close', () => {
            if (logoutDialog.returnValue !== 'confirm') {
                logoutFormToSubmit = null;
            }
        });
        logoutDialog.addEventListener('click', (event) => {
            if (event.target === logoutDialog) {
                logoutFormToSubmit = null;
                logoutDialog.close();
            }
        });

        document.querySelectorAll('.table-container').forEach((container, index) => {
            const table = container.querySelector('table');
            if (!table) {
                return;
            }

            if (!table.id) {
                table.id = `horizontal-table-${index + 1}`;
            }

            const scrollbar = document.createElement('div');
            scrollbar.className = 'table-scrollbar';
            scrollbar.setAttribute('role', 'scrollbar');
            scrollbar.setAttribute('aria-label', 'Desplazamiento horizontal de la tabla');
            scrollbar.setAttribute('aria-controls', table.id);
            scrollbar.setAttribute('aria-orientation', 'horizontal');
            scrollbar.setAttribute('aria-valuemin', '0');
            scrollbar.setAttribute('aria-valuemax', '1000');
            scrollbar.tabIndex = 0;
            scrollbar.title = 'Arrastra la barra para ver más columnas';

            const thumb = document.createElement('span');
            thumb.className = 'table-scrollbar-thumb';
            scrollbar.appendChild(thumb);
            container.insertAdjacentElement('afterend', scrollbar);

            let dragStartX = 0;
            let dragStartScroll = 0;

            const updateScrollbar = () => {
                const maxScroll = container.scrollWidth - container.clientWidth;
                const trackWidth = scrollbar.clientWidth;
                const thumbWidth = maxScroll > 0
                    ? Math.max(36, (container.clientWidth / container.scrollWidth) * trackWidth)
                    : trackWidth;
                const maxThumbOffset = Math.max(0, trackWidth - thumbWidth);
                const thumbOffset = maxScroll > 0
                    ? (container.scrollLeft / maxScroll) * maxThumbOffset
                    : 0;
                const value = maxScroll > 0
                    ? Math.round((container.scrollLeft / maxScroll) * 1000)
                    : 0;

                scrollbar.hidden = maxScroll <= 0;
                thumb.style.width = `${thumbWidth}px`;
                thumb.style.transform = `translateX(${thumbOffset}px)`;
                scrollbar.setAttribute('aria-valuenow', String(value));
                scrollbar.setAttribute('aria-valuetext', `${Math.round(value / 10)}% desplazado`);
            };

            const scrollToPointer = (clientX) => {
                const bounds = scrollbar.getBoundingClientRect();
                const trackWidth = scrollbar.clientWidth;
                const thumbWidth = thumb.getBoundingClientRect().width;
                const maxThumbOffset = Math.max(0, trackWidth - thumbWidth);
                const pointerOffset = Math.min(
                    maxThumbOffset,
                    Math.max(0, clientX - bounds.left - thumbWidth / 2)
                );
                const maxScroll = container.scrollWidth - container.clientWidth;
                container.scrollLeft = maxThumbOffset > 0
                    ? (pointerOffset / maxThumbOffset) * maxScroll
                    : 0;
            };

            scrollbar.addEventListener('pointerdown', (event) => {
                if (container.scrollWidth <= container.clientWidth) {
                    return;
                }

                event.preventDefault();
                scrollbar.setPointerCapture(event.pointerId);
                dragStartX = event.clientX;
                dragStartScroll = container.scrollLeft;
                if (event.target !== thumb) {
                    scrollToPointer(event.clientX);
                    dragStartScroll = container.scrollLeft;
                }
                scrollbar.classList.add('dragging');
            });

            scrollbar.addEventListener('pointermove', (event) => {
                if (!scrollbar.hasPointerCapture(event.pointerId)) {
                    return;
                }

                const trackWidth = scrollbar.clientWidth;
                const thumbWidth = thumb.getBoundingClientRect().width;
                const maxThumbOffset = Math.max(1, trackWidth - thumbWidth);
                const maxScroll = container.scrollWidth - container.clientWidth;
                container.scrollLeft = dragStartScroll
                    + ((event.clientX - dragStartX) / maxThumbOffset) * maxScroll;
            });

            const endDragging = (event) => {
                if (scrollbar.hasPointerCapture(event.pointerId)) {
                    scrollbar.releasePointerCapture(event.pointerId);
                }
                scrollbar.classList.remove('dragging');
            };
            scrollbar.addEventListener('pointerup', endDragging);
            scrollbar.addEventListener('pointercancel', endDragging);

            scrollbar.addEventListener('keydown', (event) => {
                const step = Math.max(40, container.clientWidth * 0.2);
                if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                    event.preventDefault();
                    container.scrollLeft += event.key === 'ArrowRight' ? step : -step;
                } else if (event.key === 'Home' || event.key === 'End') {
                    event.preventDefault();
                    container.scrollLeft = event.key === 'Home' ? 0 : container.scrollWidth;
                }
            });

            container.addEventListener('scroll', updateScrollbar, { passive: true });
            if (typeof ResizeObserver !== 'undefined') {
                const resizeObserver = new ResizeObserver(updateScrollbar);
                resizeObserver.observe(container);
                resizeObserver.observe(table);
            } else {
                window.addEventListener('resize', updateScrollbar);
            }
            updateScrollbar();
        });

        document.querySelectorAll('main .header').forEach((header) => {
            let menuButton = header.querySelector('.menu-btn');
            if (!menuButton) {
                menuButton = document.createElement('button');
                menuButton.className = 'menu-btn';
                menuButton.type = 'button';
                menuButton.textContent = '☰';
                header.insertBefore(menuButton, header.firstChild);
            }

            const isMenuOpen = document.querySelector('.sidebar').classList.contains('show');
            menuButton.setAttribute('aria-label', isMenuOpen ? 'Cerrar menú' : 'Abrir menú');
            menuButton.setAttribute('aria-controls', 'sidebarNavigation');
            menuButton.setAttribute('aria-expanded', String(isMenuOpen));
            menuButton.addEventListener('click', () => {
                const isOpen = document.querySelector('.sidebar').classList.toggle('show');
                menuButton.setAttribute('aria-expanded', String(isOpen));
                menuButton.setAttribute('aria-label', isOpen ? 'Cerrar menú' : 'Abrir menú');
            });

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
