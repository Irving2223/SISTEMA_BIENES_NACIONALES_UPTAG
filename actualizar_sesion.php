<?php
// este codigo actualiza la variable de sesión 'ultimo_acceso' para mantener la sesión activa que funciona como una peticion AJAX del codigo JS en header.php y home.php
session_start();

// Protección básica
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['loggeado'] !== true) {
    http_response_code(403);
    exit;
}

// Actualizar solo si la acción es correcta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
    $_SESSION['usuario']['ultimo_acceso'] = time();
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
}
?>