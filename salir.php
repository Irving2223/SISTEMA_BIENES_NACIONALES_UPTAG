<?php
session_start();

// Si había sesión, registrar cierre
if (isset($_SESSION['cedula'])) {
    include("conexion.php");
    $stmt = $conn->prepare("INSERT INTO Bitacora (cedula_usuario, accion, tabla_afectada, detalle) VALUES (?, 'Consulta', 'Sistema', 'Cierre de sesión')");
    $stmt->bind_param("s", $_SESSION['cedula']);
    $stmt->execute();
}

// Destruir sesión
session_destroy();

// Volver al login
header("Location: Loggin.php");
exit;
?>