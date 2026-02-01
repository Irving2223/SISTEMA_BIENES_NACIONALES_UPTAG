<?php


// Actualizar actividad para esta sesión
$_SESSION['usuario']['ultimo_acceso'] = time();

$host = "localhost";
$user = "root";
$pass = "";
$db = "bienes_nacionales_uptag";

$conn = mysqli_connect($host, $user, $pass, $db);


/* 
Codigo para verificar la conexion a la BD:

if (isset($conn)) {
    echo "Conexión exitosa";
} else {
    echo "Conexión fallida";
}
*/

?>
