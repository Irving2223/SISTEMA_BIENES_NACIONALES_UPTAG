<?php
session_start();
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
$filtro_tipo = $_POST['filtro_tipo'] ?? 'todos';
$resultados = json_decode($_POST['resultados_json'], true);

// Validar fechas
if (empty($fecha_inicio) || empty($fecha_fin) || !is_array($resultados)) {
    die("Error: Fechas no válidas o datos inválidos.");
}

// Contador para numeración
$contador = 1;

// Obtener nombre del usuario logueado desde la sesión
$nombre_usuario = 'Usuario del Sistema';
if (isset($_SESSION['usuario']['nombre']) && isset($_SESSION['usuario']['apellido'])) {
    $nombre_usuario = htmlspecialchars($_SESSION['usuario']['nombre'] . ' ' . $_SESSION['usuario']['apellido']);
} elseif (isset($_SESSION['usuario']['nombre'])) {
    $nombre_usuario = htmlspecialchars($_SESSION['usuario']['nombre']);
}

// ✅ ¡AQUÍ ESTÁ LA CORRECCIÓN CLAVE!
// Recorremos cada solicitud y si es colectivo, buscamos su referente principal
foreach ($resultados as &$solicitud) {
    if (isset($solicitud['tipo_solicitante']) && $solicitud['tipo_solicitante'] == 'C') {
        // Buscar el referente principal del colectivo usando su RIF (identificacion)
        $rif_colectivo = $conn->real_escape_string($solicitud['identificacion']);
        
        $sql_referente = "
            SELECT 
                CONCAT(pn.primer_nombre, ' ', IFNULL(pn.segundo_nombre, ''), ' ', pn.primer_apellido, ' ', IFNULL(pn.segundo_apellido, '')) as nombre_completo
            FROM colectivo_integrantes ci
            JOIN personas_naturales pn ON ci.cedula = pn.cedula
            WHERE ci.rif_o_ci_colectivo = '$rif_colectivo' AND ci.es_referente = 1
            LIMIT 1
        ";
        
        $result_referente = $conn->query($sql_referente);
        
        if ($result_referente && $result_referente->num_rows > 0) {
            $row_referente = $result_referente->fetch_assoc();
            // ✅ SOBREESCRIBIMOS EL CAMPO nombre_representante CON EL NOMBRE DEL REFERENTE
            $solicitud['nombre_representante'] = $row_referente['nombre_completo'];
        } else {
            // Si no hay referente, dejamos como está (N/A)
            $solicitud['nombre_representante'] = 'N/A';
        }
    }
    // Si no es colectivo, no tocamos nada: ya viene bien desde el reporte_solicitudes.php
}

// Cerrar conexión
$conn->close();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Solicitudes</title>
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
    </style>
</head>
<body>

    <div class="header">
            <!-- Ruta local al logo -->
            <img src="http://localhost/SISTEMA INTI DAC/assets/img/LOGO INTI.png" style="width:140px; height:140px; item-align: center;">

        <div class="logo">
            <h1 class="title" style="font-size: 40px;">REPORTE DE SOLICITUDES</h1>
            <p class="subtitle" style="font-size: 20px;">Departamento de atención al campesino</p>
      </div>

    </div>

    <div class="report-info">
        <div class="info-row">
            <span class="info-label">Rango de Fechas:</span> <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
        </div>

        <div class="info-row">
            <span class="info-label">Filtro Tipo:</span> <?php
                $filtro_texto = [
                    'todos' => 'Todos',
                    'naturales' => 'Personas Naturales',
                    'juridicos' => 'Personas Jurídicas',
                    'colectivos' => 'Colectivos'
                ];
                echo $filtro_texto[$filtro_tipo] ?? 'Todos';
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
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>FECHA</th>
                    <th>BENEFICIARIO</th>
                    <th>CÉDULA DE IDENTIDAD / RIF</th>
                    <th>TIPO SOLICITANTE</th>
                    <th>SEXO</th>
                    <th>EDAD</th>
                    <th>TELÉFONO</th>
                    <th>PREDIO</th>
                    <th>SUPERFICIE</th>
                    <th>ESTADO DE ATENCIÓN</th>
                    <th>ESTADO DEL PREDIO</th>
                    <th>MUNICIPIO</th>
                    <th>PARROQUIA</th>
                    <th>SECTOR</th>
                    <th>REQUERIMIENTO</th>
                    <th>OBSERVACIONES</th>
                </tr>
            </thead>
            
            <tbody>
                <?php $contador = 1; ?>
                <?php foreach ($resultados as $solicitud): ?>
                <tr>
                    <td><?php echo $contador++; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['beneficiario']); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['identificacion']); ?></td>
                    <td><?php
                        $tipo_descripcion = '';
                        switch ($solicitud['tipo_solicitante']) {
                            case 'N':
                                $tipo_descripcion = 'Natural';
                                break;
                            case 'J':
                                $tipo_descripcion = 'Jurídico';
                                break;
                            case 'C':
                                $tipo_descripcion = 'Colectivo';
                                break;
                            default:
                                $tipo_descripcion = 'Desconocido';
                        }
                        echo htmlspecialchars($tipo_descripcion);
                    ?></td>
                    <td><?php echo htmlspecialchars($solicitud['sexo'] ?? 'N/A'); ?></td>
                    <td><?php
                        if ($solicitud['edad'] !== null && $solicitud['edad'] > 0) {
                            echo htmlspecialchars($solicitud['edad']) . ' años';
                        } else {
                            echo 'N/A';
                        }
                    ?></td>
                    <td><?php echo htmlspecialchars($solicitud['telefono'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['predio'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['superficie'] ?? 'N/A'); ?> ha</td>
                    <td><?php echo htmlspecialchars($solicitud['estado_atencion']); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['estado_predio']); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['municipio'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['parroquia'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['sector'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['requerimiento'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($solicitud['observaciones'] ?? 'N/A'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="footer">

<div class="footer-inti">

Sistema de Información para la Gestión Administrativa del Departamento de Atención al Campesino del Instituto Nacional de Tierras (INTI) © 2025 by Irving Coello, Richard Molina, Dixon Véliz y Brayan Pirona</a> is licensed under CC BY-NC-ND 4.0

    </div>
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
$filename = "reporte_solicitudes_" . date('Ymd_His') . ".pdf";

// Enviar al navegador
$dompdf->stream($filename, [
    "Attachment" => true  // Descarga el archivo
]);

exit();
?>


