<?php
session_start(); // Iniciar la sesión para manejar el estado del usuario

require_once '../conexion.php'; // Ruta al archivo de configuración
require_once '../csrf.php';
$db = conectar();

// Verificar la conexión
if ($db->connect_error) {
    die("Error de conexión: " . $db->connect_error);
} 

// Obtener los datos del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validar_csrf();
    if (($_POST['accion'] ?? '') === 'crear_administrador') {
        $nombre = trim($_POST['nombre'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $password = $_POST['password'] ?? '';
        if (strlen($nombre) < 3 || strlen($usuario) < 3 || !filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
            header('Location: login.php?error=' . urlencode('Revise los datos: nombre y usuario de 3 caracteres, correo válido y contraseña de al menos 10 caracteres.'));
            exit();
        }
        try {
            $db->begin_transaction();
            $usuarios = $db->query('SELECT id_usuario FROM usuarios FOR UPDATE');
            if ($usuarios->num_rows !== 0) {
                $db->rollback();
                header('Location: login.php?error=' . urlencode('La configuración inicial ya fue completada. Inicie sesión.'));
                exit();
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO usuarios (nombre, usuario, password, correo, rol) VALUES (?, ?, ?, ?, 'Administrador')");
            $stmt->bind_param('ssss', $nombre, $usuario, $hash, $correo);
            $stmt->execute();
            $usuarioId = $db->insert_id;
            $db->commit();
            session_regenerate_id(true);
            $_SESSION['usuario'] = $usuario;
            $_SESSION['usuario_nombre'] = $nombre;
            $_SESSION['usuario_rol'] = 'Administrador';
            $_SESSION['usuario_id'] = $usuarioId;
            header('Location: ../index.php');
            exit();
        } catch (mysqli_sql_exception $e) {
            $db->rollback();
            $error = $e->getCode() === 1062 ? 'Ese nombre de usuario ya existe.' : 'No se pudo crear el administrador.';
            header('Location: login.php?error=' . urlencode($error));
            exit();
        }
    }

    $usuarioNombre = trim($_POST['usuario'] ?? '');
    $contraseña = $_POST['password'] ?? '';

    if ($usuarioNombre === '' || $contraseña === '') {
        header('Location: login.php?error=' . urlencode('Ingrese usuario y contraseña'));
        exit();
    }

    // Preparar la consulta para obtener el usuario y la contraseña almacenada
    $stmt = $db->prepare("SELECT id_usuario, nombre, usuario, password, rol FROM usuarios WHERE usuario = ? AND estado = 1 LIMIT 1");
    if (!$stmt) {
        header('Location: login.php?error=' . urlencode('Error interno (consulta)'));
        exit();
    }

    $stmt->bind_param("s", $usuarioNombre);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado && $resultado->num_rows > 0) {
        $user = $resultado->fetch_assoc();
        $passwordOk = password_verify($contraseña, $user['password']);
        if (!$passwordOk && hash_equals($user['password'], $contraseña)) {
            $passwordOk = true;
            $hash = password_hash($contraseña, PASSWORD_DEFAULT);
            $actualizar = $db->prepare('UPDATE usuarios SET password = ? WHERE id_usuario = ?');
            $actualizar->bind_param('si', $hash, $user['id_usuario']);
            $actualizar->execute();
        }

        if ($passwordOk) {
            session_regenerate_id(true);
            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['usuario_nombre'] = $user['nombre'];
            $_SESSION['usuario_rol'] = $user['rol'];
            $_SESSION['usuario_id'] = $user['id_usuario'];
            header("Location: ../index.php");
            exit();
        }
    }

    // Si llegamos aquí, credenciales inválidas
    header('Location: login.php?error=' . urlencode('Usuario o contraseña incorrectos'));
    exit();
}

$db->close();
?>