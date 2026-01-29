<?php

// obtener_datos.php FUNCIONA EN CONJUNTO CON REGISTRAR_SOLICITUD.PHP PARA CARGAR DINÁMICAMENTE LAS PARROQUIAS Y SECTORES


header('Content-Type: application/json');

include("conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';
    $response = [];

    if ($tipo === 'parroquias' && isset($_POST['id_municipio'])) {
        $id_municipio = intval($_POST['id_municipio']);
        $sql = "SELECT id_parroquia, nombre_parroquia FROM parroquias WHERE id_municipio = $id_municipio ORDER BY nombre_parroquia";
        $result = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $response[] = $row;
        }
    } elseif ($tipo === 'sectores' && isset($_POST['id_parroquia'])) {
        $id_parroquia = intval($_POST['id_parroquia']);
        $sql = "SELECT id_sector, nombre_sector FROM sectores WHERE id_parroquia = $id_parroquia ORDER BY nombre_sector";
        $result = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $response[] = $row;
        }
    }

    echo json_encode($response);
}
?>