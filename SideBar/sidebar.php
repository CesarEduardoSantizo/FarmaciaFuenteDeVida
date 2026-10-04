<?php
if (!isset($_SESSION)) {
    session_start();
}
?>
<aside class="sidebar">
  <div class="sidebar-header">
    <h2>Menú</h2>
  </div>
  <nav class="sidebar-nav">
    <a href="/index.php">Inicio</a>
    <a href="/inventario/index.php">Inventario</a>
    <a href="/ventas/ventas.php">Ventas</a>
    <a href="/compras/compras.php">Compras</a>
    <a href="/clientes/clientes.php">Clientes</a>
    <a href="/proveedores/proveedores.php">Proveedores</a>
    <a href="/usuarios/usuarios.php">Usuarios</a>
    <form method="POST" action="/login/login.php">
      <input type="hidden" name="logout" value="1">
      <button type="submit" class="logout-btn">Cerrar sesión</button>
    </form>
  </nav>
</aside>
