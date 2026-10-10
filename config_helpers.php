<?php

function cargar_configuracion(mysqli $db): array
{
    $rows = $db->query('SELECT clave, valor FROM configuracion')->fetch_all(MYSQLI_ASSOC);
    return array_column($rows, 'valor', 'clave');
}

function umbral_stock(array $configuracion): int
{
    return max(0, (int)($configuracion['alertas_stock_minimo'] ?? 5));
}

function dias_alerta_vencimiento(array $configuracion): int
{
    return max(1, (int)($configuracion['alertas_vencimiento_dias'] ?? 30));
}

function simbolo_moneda(array $configuracion): string
{
    return ($configuracion['moneda'] ?? 'GTQ') === 'USD' ? '$' : 'Q';
}

function confirmar_eliminaciones(array $configuracion): bool
{
    return ($configuracion['confirmar_eliminaciones'] ?? '1') === '1';
}

function nombre_presentacion_visible(string $nombre): string
{
    $nombre = preg_replace('/(?<!\p{L})bl(?:\x{251C}\x{00A1}|\x{00C3}\x{00AD})ster(?!\p{L})/iu', 'Blíster', $nombre) ?? $nombre;
    return preg_replace('/(?<!\p{L})bl[ií]ster(?!\p{L})/iu', 'Blíster', $nombre) ?? $nombre;
}
