<?php
session_start();
// Iniciar buffer de salida
ob_start();

// Conexión a la base de datos
include("conexion.php");

// Establecer zona horaria de Venezuela
date_default_timezone_set('America/Caracas');

// Obtener datos del POST
$busqueda = $_POST['buscar'] ?? '';
$resultados = isset($_POST['resultados_json']) ? json_decode($_POST['resultados_json'], true) : array();

if (!is_array($resultados)) {
    $resultados = array();
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

// Cerrar conexión
$conn->close();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Categorías - Bienes Nacionales UPTAG</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @page {
            margin: 10mm;
            size: A4 portrait;
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
            background-color: #333;
            color: white;
            padding: 15px;
            margin-bottom: 15px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
        }

        .header p {
            margin: 5px 0 0 0;
            font-size: 11px;
        }

        .report-info {
            background-color: #f5f5f5;
            padding: 10px 15px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
        }

        .info-row {
            margin-bottom: 5px;
        }

        .info-label {
            font-weight: bold;
        }

        .table-container {
            margin-top: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }

        thead th {
            background-color: #333;
            color: white;
            padding: 6px 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #333;
        }

        tbody td {
            padding: 5px 8px;
            border: 1px solid #999;
            vertical-align: top;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            padding: 8px;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #ddd;
            background-color: white;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
        }

        .status-activo {
            background-color: #28a745;
            color: white;
        }

        .status-inactivo {
            background-color: #dc3545;
            color: white;
        }

        .total-row {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>REPORTE DE CATEGORÍAS</h1>
        <p>BIENES NACIONALES UPTAG</p>
    </div>

    <div class="report-info">
        <?php if (!empty($busqueda)): ?>
        <div class="info-row">
            <span class="info-label">Búsqueda:</span> 
            "<?php echo htmlspecialchars($busqueda); ?>"
        </div>
        <?php endif; ?>
        <div class="info-row">
            <span class="info-label">Exportado por:</span> 
            <?php echo $nombre_usuario; ?>
        </div>
        <div class="info-row">
            <span class="info-label">Fecha y Hora:</span> 
            <?php echo date('d/m/Y H:i:s'); ?>
        </div>
        <div class="info-row">
            <span class="info-label">Total Categorías:</span> 
            <?php echo count($resultados); ?>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">Nº</th>
                    <th style="width: 50px; text-align: center;">ID</th>
                    <th style="width: 60px; text-align: center;">Código</th>
                    <th style="width: 100px;">Nombre</th>
                    <th style="width: 120px;">Descripción</th>
                    <th style="width: 80px;">Cuenta Pptaria.</th>
                    <th style="width: 50px; text-align: center;">Estatus</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultados as $cat): ?>
                <tr>
                    <td class="text-center"><?php echo $contador++; ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($cat['id'] ?? 'N/A'); ?></td>
                    <td class="text-center"><strong><?php echo htmlspecialchars($cat['codigo'] ?? $cat['id'] ?? 'N/A'); ?></strong></td>
                    <td><?php echo htmlspecialchars($cat['nombre'] ?? $cat['denominacion'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($cat['descripcion'] ?? 'Sin descripción'); ?></td>
                    <td><?php echo htmlspecialchars($cat['cuenta_presupuestaria'] ?? 'N/A'); ?></td>
                    <td class="text-center">
                        <?php 
                            $estatus = isset($cat['activo']) ? ($cat['activo'] == 1 ? 'Activo' : 'Inactivo') : 'Activo';
                            $badge_class = $estatus == 'Activo' ? 'status-activo' : 'status-inactivo';
                        ?>
                        <span class="status-badge <?php echo $badge_class; ?>">
                            <?php echo htmlspecialchars($estatus); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="6" class="text-right" style="padding: 8px;">TOTAL CATEGORÍAS:</td>
                    <td class="text-center" style="padding: 8px;"><?php echo count($resultados); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="footer">
        Sistema de Gestión de Bienes Nacionales UPTAG - Generado por: <?php echo $nombre_usuario; ?> - <?php echo date('d/m/Y H:i:s'); ?>
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

// Configurar papel - A4 vertical (portrait)
$dompdf->setPaper('A4', 'portrait');

// Renderizar PDF
$dompdf->render();

// Generar nombre del archivo
$nombre_archivo = 'reporte_categorias_' . date('Y-m-d_H-i-s') . '.pdf';

// Enviar al navegador
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

$dompdf->stream($nombre_archivo, ['attachment' => true]);
exit;
?>
