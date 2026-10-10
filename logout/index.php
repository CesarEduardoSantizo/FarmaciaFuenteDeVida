<?php
session_start();
require_once __DIR__ . '/../csrf.php';
validar_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: /login/login.php');
    exit();
}
if (!isset($_SESSION['usuario_id'])) { header('Location: /login/login.php'); exit(); }
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Cerrar sesión | Farmacia Fuente de Vida</title><link rel="stylesheet" href="../menu.css"><link rel="stylesheet" href="logout.css"></head><body><main class="logout-page"><section class="logout-card"><div class="logout-icon">↪</div><h1>Cerrar sesión</h1><p>¿Está seguro que desea cerrar sesión?</p><form method="POST"><?= csrf_input() ?><a href="/index.php">Cancelar</a><button type="submit">Cerrar sesión</button></form></section></main><script src="/dev-reload.js"></script></body></html>
