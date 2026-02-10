<?php
require_once 'conexion.php';

try {
    // Verificar si la columna ya existe
    $result = $conn->query("SHOW COLUMNS FROM bienes LIKE 'ubicacion_id'");
    
    if ($result && $result->num_rows > 0) {
        echo "La columna ubicacion_id ya existe.";
    } else {
        // Agregar la columna sin foreign key primero
        $sql = "ALTER TABLE bienes ADD COLUMN ubicacion_id INT DEFAULT NULL AFTER categoria_id";
        if ($conn->query($sql)) {
            echo "Columna ubicacion_id agregada correctamente.";
        } else {
            echo "Error al agregar columna: " . $conn->error;
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
