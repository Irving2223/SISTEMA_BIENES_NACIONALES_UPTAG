<?php

// este codigo busca la persona cuando queremos agregar un apoderado en registrar_solicitante.php

include("conexion.php");

header('Content-Type: application/json');

$cedula = $_GET['cedula'] ?? '';
$resultados = [];

if (!empty($cedula)) {
    $stmt = $conn->prepare("SELECT cedula, CONCAT(primer_nombre, ' ', IFNULL(segundo_nombre, ''), ' ', primer_apellido, ' ', IFNULL(segundo_apellido, '')) as nombre_completo, telefono, direccion_habitacion FROM personas_naturales WHERE cedula LIKE ? OR cedula = ?");
    $search = $cedula . '%';
    $stmt->bind_param("ss", $search, $cedula);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $resultados[] = $row;
    }
    $stmt->close();
}

echo json_encode([
    'encontrado' => count($resultados) > 0,
    'resultados' => $resultados
]);
?>