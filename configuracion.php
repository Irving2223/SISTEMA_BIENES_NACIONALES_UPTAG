<?php
// Incluir el header ANTES de cualquier salida, pero preparar la exportación primero
ob_start();

include("header.php");

// Determinar si el usuario es superusuario (Administrador)
$es_superusuario = ($_SESSION['usuario']['rol'] === 'Administrador');
$clase_contenedor = $es_superusuario ? '' : 'normal-user';

// Manejar la exportación de la base de datos ANTES de que se genere cualquier contenido visible
if (isset($_POST['exportar_datos'])) {
    // Limpiar el buffer de salida antes de enviar los headers
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Verificar nuevamente que el usuario sea administrador
    if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'Administrador') {
        http_response_code(403);
        echo "Acceso denegado.";
        exit();
    }

    include("conexion.php");
    
    $nombre_db = 'bienes_nacionales_uptag'; // Nombre de tu base de datos
    $fecha = date('Y-m-d_H-i-s');
    $nombre_archivo = "Copia {$nombre_db}_{$fecha}.sql";
    
    // Obtener todas las tablas
    $tablas = [];
    $resultado = mysqli_query($conn, "SHOW TABLES");
    while ($fila = mysqli_fetch_row($resultado)) {
        $tablas[] = $fila[0];
    }
    
    // Iniciar el contenido del archivo SQL
    $salida_sql = "-- phpMyAdmin SQL Dump\n-- version 5.2.1\n-- https://www.phpmyadmin.net/\n--\n-- Servidor: localhost\n-- Tiempo de generación: " . date('d-m-Y H:i:s') . "\n-- Versión del servidor: " . mysqli_get_server_info($conn) . "\n-- Versión de PHP: " . PHP_VERSION . "\n\nSET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\nSTART TRANSACTION;\nSET time_zone = \"+00:00\";\n\n\n";
    
    // Recorrer cada tabla
    foreach ($tablas as $tabla) {
        // Estructura de la tabla
        $resultado2 = mysqli_query($conn, "SHOW CREATE TABLE `$tabla`");
        $fila2 = mysqli_fetch_row($resultado2);
        $salida_sql .= "\n-- Estructura de tabla para la tabla `$tabla`\n--\n" . $fila2[1] . ";\n\n";
        
        // Volcar datos de la tabla
        $resultado3 = mysqli_query($conn, "SELECT * FROM `$tabla`");
        $num_campos = mysqli_num_fields($resultado3);
        
        while ($fila3 = mysqli_fetch_row($resultado3)) {
            $salida_sql .= "INSERT INTO `$tabla` VALUES(";
            for ($j=0; $j<$num_campos; $j++) {
                $fila3[$j] = addslashes($fila3[$j]);
                if (isset($fila3[$j])) {
                    $salida_sql .= "\"" . $fila3[$j] . "\"";
                } else {
                    $salida_sql .= "NULL";
                }
                if ($j < ($num_campos-1)) {
                    $salida_sql .= ", ";
                }
            }
            $salida_sql .= ");\n";
        }
        $salida_sql .= "\n";
    }
    
    $salida_sql .= "COMMIT;\n";
    
    // Forzar descarga
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . basename($nombre_archivo));
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($salida_sql));
    
    echo $salida_sql;
    exit();
}
?>

<!-- Contenido de la página -->
<div class="container <?php echo $clase_contenedor; ?>">

    <!-- Título Principal -->
    <h1 style="font-weight:900; font-family:montserrat; color:#ff6600; padding:20px; text-align:left; font-size:40px;"><i class="zmdi zmdi-settings"></i> Configuración  <span style="font-weight:700; color:black;">del sistema</span></h1>

    <!-- 1. Gestión de Usuarios (solo superusuario) -->
    <div class="section-container superuser-only">
        <h3 class="section-title">
            <i class="fas fa-users"></i> Gestión de Usuarios
        </h3>
        <div class="field-row">
            <div class="field-col">
                <label class="field-label">Gestionar usuarios existentes</label>
                <p class="text-muted" style="margin: 4px 0 16px 0; font-size: 0.85rem;">Crear, editar, desactivar o eliminar usuarios</p>
                <button class="btn btn-primary" onclick="location.href='gestion_usuarios.php'">Gestionar</button>
            </div>
          
        </div>
    </div>

    <!-- 7. Auditoría del Sistema (solo superusuario) -->
    <div class="section-container superuser-only">
        <h3 class="section-title">
            <i class="fas fa-clipboard-list"></i> Auditoría del Sistema
        </h3>
        <div class="field-row">
            <div class="field-col">
                <label class="field-label">Registro de actividades</label>
                <p class="text-muted" style="margin: 4px 0 16px 0; font-size: 0.85rem;">Ver quién hizo qué y cuándo</p>
                <button class="btn btn-primary" onclick="location.href='auditoria_sistema.php'">Ver Registro</button>
            </div>
            
        </div>
    </div>

    <!-- Copias de Seguridad -->
    <div class="section-container">
        <h3 class="section-title">
            <i class="fas fa-database"></i> Copias de Seguridad
        </h3>
        <div class="field-row">
            <div class="field-col">
                <label class="field-label">Exportar datos</label>
                <p class="text-muted" style="margin: 4px 0 16px 0; font-size: 0.85rem;">Generar copia de seguridad manual</p>
                <form method="POST" action="">
                    <button type="submit" name="exportar_datos" class="btn btn-primary">Exportar Datos de la BD</button>
                </form>
            </div>
        </div>
    </div>

        <!-- Copias de Seguridad -->
    <div class="section-container">
        <h3 class="section-title">
            <i class="fas fa-database"></i> Soporte Técnico
        </h3>
        <div class="field-row">
            <div class="field-col">
                <label class="field-label">Contacto a soporte Técnico del sistema</label>
                <form method="POST" action="">
                    <button  class="btn btn-primary"><a href="https://wa.me/584164646323">Soporte Técnico</a></button>
                </form>
            </div>
        </div>
    </div>

</div>
<!-- /Contenido de la página -->

<?php
include("footer.php");
?>

<!-- Scripts -->
<script src="./js/jquery-3.1.1.min.js"></script>
<script src="./js/sweetalert2.min.js"></script>
<script src="./js/bootstrap.min.js"></script>
<script src="./js/material.min.js"></script>
<script src="./js/ripples.min.js"></script>
<script src="./js/jquery.mCustomScrollbar.concat.min.js"></script>
<script src="./js/main.js"></script>
<script>
    $.material.init();

    // Confirmar antes de exportar
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (this.querySelector('[name="exportar_datos"]')) {
                if (!confirm('¿Desea generar una copia de seguridad manual de la base de datos? Esta operación puede tardar varios segundos.')) {
                    e.preventDefault();
                }
            }
        });
    });
</script>