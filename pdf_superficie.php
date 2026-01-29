<?php
// Iniciar buffer de salida
ob_start();

// Conexión a la base de datos
include("conexion.php");

// Establecer zona horaria de Venezuela
date_default_timezone_set('America/Caracas');

// Obtener datos del POST
if (!isset($_POST['fecha_inicio'], $_POST['fecha_fin'], $_POST['resultados_json'])) {
    die("Error: Datos insuficientes para generar el reporte.");
}

$fecha_inicio = $_POST['fecha_inicio'];
$fecha_fin = $_POST['fecha_fin'];
$filtro_municipio = $_POST['filtro_municipio'] ?? 'todos';
$resultados = json_decode($_POST['resultados_json'], true);

// Validar fechas
if (empty($fecha_inicio) || empty($fecha_fin) || !is_array($resultados)) {
    die("Error: Fechas no válidas o datos inválidos.");
}

// Calcular la sumatoria total de la superficie
$total_superficie = 0;
foreach ($resultados as $solicitud) {
    $total_superficie += floatval($solicitud['superficie'] ?? 0);
}

// Contador para numeración
$contador = 1;

// Obtener nombre del usuario logueado desde la sesión y buscar en la BD
session_start();
$nombre_usuario = 'Usuario del Sistema';

if (isset($_SESSION['usuario']['cedula'])) {
    $cedula_usuario = $_SESSION['usuario']['cedula'];
    
    // Buscar en la tabla de usuarios primero
    $sql_usuario = "SELECT CONCAT(nombre, ' ', apellido) as nombre_completo FROM usuarios WHERE cedula = ?";
    $stmt_usuario = $conn->prepare($sql_usuario);
    $stmt_usuario->bind_param("s", $cedula_usuario);
    $stmt_usuario->execute();
    $result_usuario = $stmt_usuario->get_result();
    
    if ($result_usuario->num_rows > 0) {
        $row_usuario = $result_usuario->fetch_assoc();
        $nombre_usuario = htmlspecialchars($row_usuario['nombre_completo']);
    } else {
        // Si no está en usuarios, buscar en personas_naturales
        $sql_persona = "SELECT CONCAT(primer_nombre, ' ', primer_apellido) as nombre_completo FROM personas_naturales WHERE cedula = ?";
        $stmt_persona = $conn->prepare($sql_persona);
        $stmt_persona->bind_param("s", $cedula_usuario);
        $stmt_persona->execute();
        $result_persona = $stmt_persona->get_result();
        
        if ($result_persona->num_rows > 0) {
            $row_persona = $result_persona->fetch_assoc();
            $nombre_usuario = htmlspecialchars($row_persona['nombre_completo']);
        }
    }
    
    $stmt_usuario->close();
    if (isset($stmt_persona)) {
        $stmt_persona->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte por Superficie</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
         @page {
            margin: 20mm;
            size: A4 landscape;
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: transparent;
            font-size: 9px;
            color: #333;
        }

        .header {
            position: relative;
            background-image: url('http://localhost/SISTEMA INTI DAC/assets/img/sidebar/sidebar.webp');
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            /* background: #1E3A3A; */
            color: white;
            padding: 30px;
            border-radius:12px;
            margin-bottom: 20px;
        }

        .logo {
            position: absolute;
            top: 40px;
            right: 10px;
            text-align: right;
        }

        .title {
            font-size: 25px;
            font-weight: bold;
            margin: 0 0 5px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .subtitle {
            font-size: 11px;
            margin: 0;
            opacity: 0.9;
        }

        .report-info {
            background: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 2px;
            border-left: 4px solid #c5e0b3;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .info-row {
            display: inline-block;
            margin-right: 30px;
            margin-bottom: 5px;
        }

        .info-label {
            font-weight: bold;
            color: #555;
        }

        .table-container {
            background: white;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7px;
        }

        th {
            background: #c5e0b3;
            color: black;
            padding: 6px 2px;
            text-align: left;
            font-weight: bold;
            font-size: 6px;
            text-transform: uppercase;

        }

        td {
            padding: 10px 5px;
            border-bottom: 1px solid #e8f5e8;
            vertical-align: top;
        }

        tr:nth-child(even) {
            background-color: #f8fdf8;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 7px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .generated-info {
            background: #c5e0b3;
            padding: 8px;
            margin-top: 15px;
            border-radius: 4px;
            font-size: 7px;
            color: #333;
            text-align: left;
        }

        .generated-info {
            background: #c5e0b3;
            padding: 8px;
            margin-top: 15px;
            border-radius: 4px;
            font-size: 7px;
            color: #333;
            text-align: left;
        }
        
        .total-superficie {
            background: #e3f2fd;
            padding: 10px;
            margin: 15px 0;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
            font-size: 12px;
            color: #1976d2;
        }
    </style>
</head>
<body>



        <div class="header">
            <!-- Ruta local al logo -->
            <img src="http://localhost/SISTEMA INTI DAC/assets/img/LOGO INTI.png" style=" width:140px; height:140px; item-align: center;">

        <div class="logo">
            <h1 class="title" style="font-size: 40px;">REPORTE DE SUPERFICIE</h1>
            <p class="subtitle" style="font-size: 20px;">Departamento de atención al campesino</p>
        </div>

      </div>


    

    <div class="report-info">
        <div class="info-row">
            <span class="info-label">Rango de Fechas:</span> <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
        </div>

        <div class="info-row">
            <span class="info-label">Municipio:</span>
            <?php
            if ($filtro_municipio === 'todos') {
                echo 'Todos los Municipios';
            } else {
                // Obtener nombre del municipio
                $sql_municipio = "SELECT nombre_municipio FROM municipios WHERE id_municipio = ?";
                $stmt_municipio = $conn->prepare($sql_municipio);
                $stmt_municipio->bind_param("s", $filtro_municipio);
                $stmt_municipio->execute();
                $result_municipio = $stmt_municipio->get_result();
                if ($result_municipio->num_rows > 0) {
                    $row_municipio = $result_municipio->fetch_assoc();
                    echo htmlspecialchars($row_municipio['nombre_municipio']);
                } else {
                    echo 'Municipio no encontrado';
                }
                $stmt_municipio->close();
            }
            ?>
        </div>

        <div class="info-row">
            <span class="info-label">Generado el:</span> <?php echo date('d/m/Y H:i:s'); ?>
        </div>

        <div class="info-row">
            <span class="info-label">Generado por:</span> <?= $nombre_usuario ?>
        </div>

        <div class="info-row">
            <span class="info-label">Total de Registros:</span> <?php echo count($resultados); ?>
        </div>

        <div class="info-row">
            <span class="info-label">Superficie Total (Ha):</span> <?php echo number_format($total_superficie, 2); ?>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>FECHA</th>
                    <th>CÉDULA/RIF</th>
                    <th>NOMBRE</th>
                    <th>SOLICITUD TIPO</th>
                    <th>PREDIO</th>
                    <th>DIRECCIÓN</th>
                    <th>SUPERFICIE (Ha)</th>
                    <th>LINDEROS</th>
                    <th>MUNICIPIO</th>
                    <th>PARROQUIA</th>
                    <th>SECTOR</th>
                    <th>ESTATUS</th>
                    <th>OBSERVACIONES</th>
                </tr>
            </thead>
            
            <tbody>
                <?php $contador = 1; ?>
                <?php foreach ($resultados as $solicitud): ?>
                <tr>
                    <td><?php echo $contador++; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['identificacion']); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['tipo_solicitud']); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['predio']); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['direccion_predio'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['superficie'] ?? 'N/A'); ?></td>
                    <td>
                        N: <?php echo htmlspecialchars($solicitud['lindero_norte'] ?? 'N/A'); ?><br>
                        S: <?php echo htmlspecialchars($solicitud['lindero_sur'] ?? 'N/A'); ?><br>
                        E: <?php echo htmlspecialchars($solicitud['lindero_este'] ?? 'N/A'); ?><br>
                        O: <?php echo htmlspecialchars($solicitud['lindero_oeste'] ?? 'N/A'); ?>
                    </td>
                    <td><?php echo htmlspecialchars($solicitud['municipio'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['parroquia']); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['sector']); ?></td>
                    <td>
                        <span style="padding: 1px 4px; border-radius: 30px; font-size: 6px; font-weight: bold; background: #01722fff; color: white;">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $solicitud['estatus'])); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($solicitud['observaciones'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>


    <div class="footer">

 Sistema de Información para la Gestión Administrativa del Departamento de Atención al Campesino del Instituto Nacional de Tierras (INTI) © 2025 by Irving Coello, Richard Molina, Dixon Véliz y Brayan Pirona</a> is licensed under CC BY-NC-ND 4.0

    </div>  

</body>
</html>

<?php
// Obtener el HTML capturado
$html = ob_get_clean();

// Cargar domPDF
require_once "librerias/dompdf/autoload.inc.php";
use Dompdf\Dompdf;

$dompdf = new Dompdf();

// Habilitar carga de imágenes remotas
$options = $dompdf->getOptions();
$options->set('isRemoteEnabled', true);
$dompdf->setOptions($options);

// Cargar HTML
$dompdf->loadHtml($html);

// Configurar papel
$dompdf->setPaper('A4', 'landscape');

// Renderizar
$dompdf->render();

// Nombre del archivo
$filename = "reporte_superficie_" . date('Ymd_His') . ".pdf";

// Enviar al navegador
$dompdf->stream($filename, [
    "Attachment" => true  // Descarga el archivo
]);

exit();
?>

