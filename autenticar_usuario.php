<?php
session_start();

// Si ya está logueado, redirigir al home
if (isset($_SESSION['usuario']) && $_SESSION['usuario']['loggeado'] === true) {
    header('Location: home.php');
    exit;
}

// Incluir conexión MySQLi
include("conexion.php");

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Loggin.php?error=Acceso+no+autorizado');
    exit;
}

// Obtener y sanitizar datos
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validar campos vacíos
if (empty($username) || empty($password)) {
    header('Location: Loggin.php?error=Debe+llenar+ambos+campos');
    exit;
}

// Consulta segura con MySQLi
$stmt = $conn->prepare("SELECT cedula, nombre, apellido, clave_usuario, rol, activo FROM usuarios WHERE cedula = ? AND activo = 1");
if (!$stmt) {
    error_log("Error en preparación de consulta: " . $conn->error);
    header('Location: Loggin.php?error=Error+interno+del+sistema');
    exit;
}

$stmt->bind_param("s", $username);
if (!$stmt->execute()) {
    error_log("Error en ejecución de consulta: " . $stmt->error);
    header('Location: Loggin.php?error=Error+interno+del+sistema');
    exit;
}

$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Verificar si el usuario existe
if (!$user) {
    error_log("Intento de login fallido: cédula " . $username);
    // Registrar intento fallido en bitácora
    if ($conn) {
        $stmt_bitacora = $conn->prepare("INSERT INTO bitacora (cedula_usuario, accion, tabla_afectada, registro_afectado, detalle) VALUES (?, 'Inicio de sesión', 'usuarios', ?, 'Intento de inicio de sesión fallido - usuario no encontrado')");
        if ($stmt_bitacora) {
            $stmt_bitacora->bind_param("ss", $username, $username);
            $stmt_bitacora->execute();
            $stmt_bitacora->close();
        } else {
            error_log("Error preparando consulta de bitácora: " . $conn->error);
        }
    }
    header('Location: Loggin.php?error=C%C3%A9dula+o+contrase%C3%B1a+incorrecta');
    exit;
}

// Verificar contraseña (usando MD5)
if (md5($password) !== $user['clave_usuario']) {
    error_log("Intento de login fallido (contraseña): cédula " . $username);
    // Registrar intento fallido en bitácora
    if ($conn) {
        $stmt_bitacora = $conn->prepare("INSERT INTO bitacora (cedula_usuario, accion, tabla_afectada, registro_afectado, detalle) VALUES (?, 'Inicio de sesión', 'usuarios', ?, 'Intento de inicio de sesión fallido - contraseña incorrecta')");
        if ($stmt_bitacora) {
            $stmt_bitacora->bind_param("ss", $username, $username);
            $stmt_bitacora->execute();
            $stmt_bitacora->close();
        }
    }
    header('Location: Loggin.php?error=C%C3%A9dula+o+contrase%C3%B1a+incorrecta');
    exit;
}

// Verificar si la cuenta está activa
if ($user['activo'] != 1) {
    // Registrar intento fallido en bitácora
    if ($conn) {
        $stmt_bitacora = $conn->prepare("INSERT INTO bitacora (cedula_usuario, accion, tabla_afectada, registro_afectado, detalle) VALUES (?, 'Inicio de sesión', 'usuarios', ?, 'Intento de inicio de sesión fallido - usuario inactivo')");
        if ($stmt_bitacora) {
            $stmt_bitacora->bind_param("ss", $username, $username);
            $stmt_bitacora->execute();
            $stmt_bitacora->close();
        }
    }
    header('Location: Loggin.php?error=Su+usuario+est%C3%A1+inactivo.+Contacte+al+administrador.');
    exit;
}

// ✅ TODO CORRECTO: Iniciar sesión
session_destroy(); // Limpia cualquier sesión anterior
session_start();   // Reinicia sesión

$_SESSION['usuario'] = [
    'cedula' => $user['cedula'],
    'nombre' => $user['nombre'],
    'apellido' => $user['apellido'],
    'rol' => $user['rol'],
    'loggeado' => true,
    'ultimo_acceso' => time()
];

// Registrar en bitácora el inicio de sesión EXITOSO
$stmt_bit = $conn->prepare("
    INSERT INTO bitacora (cedula_usuario, accion, tabla_afectada, registro_afectado, detalle) 
    VALUES (?, 'Inicio de sesión', 'Sistema', 'Inicio', 'Inicio de sesión exitoso')
");
if (!$stmt_bit) {
    error_log("Error preparando consulta de bitácora exitosa: " . $conn->error);
    // Continuar igualmente, pero registrar error
} else {
    $stmt_bit->bind_param("s", $user['cedula']);
    if (!$stmt_bit->execute()) {
        error_log("Error ejecutando consulta de bitácora: " . $stmt_bit->error);
    }
    $stmt_bit->close();
}

// Redirigir al home
header('Location: home.php');
exit;
?>