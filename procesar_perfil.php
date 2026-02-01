<?php
session_start();
include('conexion.php');

// Verificar sesión
if (!isset($_SESSION['usuario']) || !$_SESSION['usuario']['loggeado']) {
    header('Location: Loggin.php?error=Acceso+denegado');
    exit;
}

$cedula = $_SESSION['usuario']['cedula'];
$accion = $_POST['accion'] ?? '';

// Redirigir al perfil con mensaje
function redirigirConMensaje($mensaje, $tipo) {
    $url = 'perfil.php?mensaje=' . urlencode($mensaje) . '&tipo=' . $tipo;
    header("Location: $url");
    exit;
}

// Obtener usuario actual
$query = "SELECT password_hash, email FROM usuarios WHERE cedula = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $cedula);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    redirigirConMensaje('Usuario no encontrado.', 'error');
}

// === CAMBIAR CORREO ===
if ($accion === 'cambiar_correo') {
    $email_actual = trim($_POST['email_actual'] ?? '');
    $nuevo_email = trim($_POST['nuevo_email'] ?? '');

    if (empty($email_actual) || empty($nuevo_email)) {
        redirigirConMensaje('Todos los campos son obligatorios.', 'error');
    }

    if (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
        redirigirConMensaje('El nuevo correo no es válido.', 'error');
    }

    if ($email_actual !== $user['email']) {
        redirigirConMensaje('El correo actual no coincide.', 'error');
    }

    if ($nuevo_email === $user['email']) {
        redirigirConMensaje('El nuevo correo es igual al actual.', 'error');
    }

    // Verificar si ya existe otro usuario con ese correo
    $check = "SELECT cedula FROM usuarios WHERE email = ? AND cedula != ?";
    $stmt = $conn->prepare($check);
    $stmt->bind_param("ss", $nuevo_email, $cedula);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        redirigirConMensaje('Este correo ya está en uso.', 'error');
    }
    $stmt->close();

    // Actualizar
    $update = "UPDATE usuarios SET email = ? WHERE cedula = ?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("ss", $nuevo_email, $cedula);

    if ($stmt->execute()) {
        registrarAuditoria($conn, $cedula, 'UPDATE', 'usuarios', json_encode(['email' => $email_actual]), json_encode(['email' => $nuevo_email]));
        $_SESSION['usuario']['email'] = $nuevo_email;
        redirigirConMensaje('Correo actualizado correctamente.', 'exito');
    } else {
        redirigirConMensaje('Error al actualizar el correo.', 'error');
    }
    $stmt->close();
}

// === CAMBIAR CONTRASEÑA ===
elseif ($accion === 'cambiar_contrasena') {
    $actual = $_POST['password_actual'] ?? '';
    $nueva = $_POST['nueva_contrasena'] ?? '';
    $confirmar = $_POST['confirmar_contrasena'] ?? '';

    if (empty($actual) || empty($nueva) || empty($confirmar)) {
        redirigirConMensaje('Todos los campos son obligatorios.', 'error');
    }

    if ($nueva !== $confirmar) {
        redirigirConMensaje('Las contraseñas no coinciden.', 'error');
    }

    // Validar complejidad
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $nueva)) {
        redirigirConMensaje('La contraseña debe tener al menos 8 caracteres, una mayúscula, minúscula, número y carácter especial.', 'error');
    }

    // Verificar contraseña actual (usando password_verify)
    if (!password_verify($actual, $user['password_hash'])) {
        redirigirConMensaje('La contraseña actual es incorrecta.', 'error');
    }

    // Hashear y guardar nueva contraseña
    $hash = password_hash($nueva, PASSWORD_DEFAULT);
    $update = "UPDATE usuarios SET password_hash = ? WHERE cedula = ?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("ss", $hash, $cedula);

    if ($stmt->execute()) {
        registrarAuditoria($conn, $cedula, 'UPDATE', 'usuarios', null, json_encode(['password' => 'cambiado']));
        redirigirConMensaje('Contraseña actualizada correctamente.', 'exito');
    } else {
        redirigirConMensaje('Error al actualizar la contraseña.', 'error');
    }
    $stmt->close();
}

// Acción no válida
redirigirConMensaje('Acción no válida.', 'error');

// Función auxiliar para auditoria
function registrarAuditoria($conn, $cedula_usuario, $accion, $tabla, $datos_anteriores, $datos_nuevos) {
    $sql = "INSERT INTO auditoria (tabla_afectada, accion, usuario_cedula, datos_anteriores, datos_nuevos, ip_address, fecha_accion) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt->bind_param("ssssss", $tabla, $accion, $cedula_usuario, $datos_anteriores, $datos_nuevos, $ip);
    $stmt->execute();
    $stmt->close();
}
?>
