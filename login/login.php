<?php
session_start();

if (isset($_GET['logout']) || isset($_POST['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_unset();
    session_destroy();
    header('Location: /login/login.php');
    exit();
}

if (isset($_SESSION['usuario_id']) || isset($_SESSION['usuario'])) {
    header('Location: /index.php');
    exit();
}

$error = $_GET['error'] ?? '';
require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../conexion.php';
$db = conectar();
$sinUsuarios = (int)$db->query('SELECT COUNT(*) FROM usuarios')->fetch_row()[0] === 0;
$productosInventario = $db->query(
    'SELECT vi.nombre, vi.stock_actual, vi.stock_minimo
     FROM vista_inventario vi
     INNER JOIN productos p ON p.id_producto = vi.id_producto
     WHERE p.estado = 1
     ORDER BY vi.nombre
     LIMIT 3'
)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Farmacia Fuente de Vida</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>

    <main class="login-container">

        <section class="login-info">
            <div class="logo">
                <img class="login-brand-image" src="/imagenes/fuente.png" alt="Farmacia Fuente de Vida">
            </div>

            <div class="info-text">
                <h2>Bienvenido de vuelta</h2>
                <p>Ingrese sus credenciales para acceder al sistema de inventario y control de ventas.</p>
            </div>

            <div class="illustration">
                <div class="screen">
                    <h3>Inventario</h3>
                    <?php if (!$productosInventario): ?>
                        <p class="inventory-empty">No hay productos registrados.</p>
                    <?php else: ?>
                        <?php foreach ($productosInventario as $producto): ?>
                            <?php
                            $stockActual = (int)$producto['stock_actual'];
                            $stockMinimo = (int)$producto['stock_minimo'];
                            $stockBajo = $stockActual <= $stockMinimo;
                            $estadoStock = $stockActual === 0 ? 'Agotado' : ($stockBajo ? 'Stock bajo' : 'Disponible');
                            $claseStock = $stockActual === 0 ? 'out' : ($stockBajo ? 'low' : 'available');
                            ?>
                            <div class="row">
                                <span><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                <b class="<?= $claseStock ?>"><?= $estadoStock ?></b>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="login-card">
            <div class="lock-icon"><img class="login-access-icon" src="/imagenes/iniciar-sesion.png" alt="" width="50" height="50"></div>

            <h2><?= $sinUsuarios ? 'Crear administrador' : 'Iniciar sesión' ?></h2>
            <p class="subtitle"><?= $sinUsuarios ? 'Configure la primera cuenta para comenzar.' : 'Ingrese sus datos para continuar' ?></p>

            <?php if ($error): ?>
                <div class="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($sinUsuarios): ?>
            <form action="ingreso.php" method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="accion" value="crear_administrador">
                <div class="form-group"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre" minlength="3" required></div>
                <div class="form-group"><label for="usuario">Usuario</label><input type="text" id="usuario" name="usuario" minlength="3" required></div>
                <div class="form-group"><label for="correo">Correo</label><input type="email" id="correo" name="correo" required></div>
                <div class="form-group"><label for="password">Contraseña (mínimo 10 caracteres)</label><div class="password-field"><input type="password" id="password" name="password" minlength="10" required><button class="toggle-password" type="button" aria-controls="password" aria-pressed="false">Mostrar</button></div></div>
                <button type="submit">Crear administrador</button>
            </form>
            <?php else: ?>
            <form action="ingreso.php" method="POST">
                <?= csrf_input() ?>
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" placeholder="Ingrese su usuario" required>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="password-field">
                        <input type="password" id="password" name="password" placeholder="Ingrese su contraseña" required>
                        <button class="toggle-password" type="button" aria-controls="password" aria-pressed="false">Mostrar</button>
                    </div>
                </div>

                <button type="submit">Iniciar sesión</button>
            </form>
            <?php endif; ?>

            <p class="footer-text">Sistema seguro para la gestión de la farmacia</p>
        </section>

    </main>

    <script src="login.js"></script>
    <script src="/dev-reload.js"></script>

</body>
</html>