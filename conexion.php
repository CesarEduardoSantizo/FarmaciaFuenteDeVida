<?php

function conectar() {
  $db = @new mysqli("localhost", "root", "", "farmacia_fuente_vida", 3307);
  if ($db->connect_errno) {
    error_log('Error de conexión a farmacia_fuente_vida: ' . $db->connect_error);
    http_response_code(503);
    exit('No hay conexión con MySQL. Verifique que MySQL de XAMPP esté iniciado en el puerto 3307.');
  }
  if (!$db->set_charset("utf8mb4")) {
    error_log('No se pudo configurar utf8mb4 para farmacia_fuente_vida.');
    http_response_code(503);
    exit('No se pudo configurar la conexión con MySQL.');
  }
  return $db;
}

function ir($pagina) {
    print "<meta http-equiv='refresh' content='3;url=$pagina'>";
}

/* 
conexion a DW via ODBC

class ConexionDW {
  private $conn;

  public function conectar() {
    try {
      $this->conn = new PDO("odbc:DSN=DW");
      $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      echo "✅ Conexión ODBC establecida correctamente.";
      return $this->conn;
    } catch (PDOException $e) {
      echo "❌ Error al conectar: " . $e->getMessage();
      return null;
    }
  }

  public function desconectar() {
    $this->conn = null;
    echo "Conexión cerrada correctamente.";
  }
}
*/
?>