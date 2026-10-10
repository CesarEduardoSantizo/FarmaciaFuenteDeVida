<?php

function catalogo_modulos_sistema(): array
{
    return [
        'inicio' => ['nombre' => 'Inicio', 'ruta' => '/index.php'],
        'inventario' => ['nombre' => 'Inventario', 'ruta' => '/inventario/'],
        'productos' => ['nombre' => 'Productos', 'ruta' => '/productos/'],
        'catalogo_presentaciones' => ['nombre' => 'Presentaciones y categorías', 'ruta' => '/catalogo_presentaciones/'],
        'ventas' => ['nombre' => 'Ventas', 'ruta' => '/ventas/'],
        'clientes' => ['nombre' => 'Clientes', 'ruta' => '/clientes/'],
        'compras' => ['nombre' => 'Compras', 'ruta' => '/compras/'],
        'proveedores' => ['nombre' => 'Proveedores', 'ruta' => '/proveedores/'],
        'reportes' => ['nombre' => 'Reportes', 'ruta' => '/reportes/'],
        'alertas' => ['nombre' => 'Alertas', 'ruta' => '/alertas/'],
        'configuracion' => ['nombre' => 'Configuración', 'ruta' => '/configuracion/'],
        'usuarios' => ['nombre' => 'Usuarios y permisos', 'ruta' => '/usuarios/'],
    ];
}

function usuario_puede_acceder(mysqli $db, string $rol, string $modulo): bool
{
    if (!isset(catalogo_modulos_sistema()[$modulo])) {
        return false;
    }

    $permisos = permisos_de_rol($db, $rol);

    return ($permisos[$modulo] ?? false) === true;
}

function permisos_de_rol(mysqli $db, string $rol): array
{
    $stmt = $db->prepare('SELECT modulo, puede_acceder FROM permisos_modulos WHERE rol = ?');
    $stmt->bind_param('s', $rol);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $permisos = [];
    foreach ($filas as $fila) {
        $permisos[$fila['modulo']] = (int)$fila['puede_acceder'] === 1;
    }

    return $permisos;
}

function roles_disponibles(mysqli $db): array
{
    $filas = $db->query("SELECT nombre FROM roles ORDER BY CASE nombre WHEN 'Administrador' THEN 0 WHEN 'Vendedor' THEN 1 ELSE 2 END, nombre")->fetch_all(MYSQLI_ASSOC);

    return array_column($filas, 'nombre');
}

function exigir_permiso_modulo(mysqli $db, string $modulo): void
{
    $rol = (string)($_SESSION['usuario_rol'] ?? '');
    if (!usuario_puede_acceder($db, $rol, $modulo)) {
        http_response_code(403);
        exit('No tiene permiso para acceder a este módulo. Solicite acceso al administrador.');
    }
}
