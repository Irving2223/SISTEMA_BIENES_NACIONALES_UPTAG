<?php

include("conexion.php");
session_start();

// Mostrar errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Verificar si llegan datos
echo "<h2>🔍 Datos recibidos:</h2><pre>";
print_r($_POST);
echo "</pre>";

// Verificar tipo de solicitante
$tipo = $_POST['tipo_solicitante'] ?? 'NO RECIBIDO';
echo "<h3>Tipo de solicitante: $tipo</h3>";

if (empty($_POST)) {
    echo "<p style='color:red;'>❌ No se recibieron datos. El formulario no se envió.</p>";
    exit;
}

echo "<p style='color:green;'>✅ Los datos llegaron correctamente.</p>";

// Aquí iría la lógica de conexión y guardado

?>