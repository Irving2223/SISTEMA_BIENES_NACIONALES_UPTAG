<?php
session_start();
// Iniciar buffer de salida
ob_start();

// Conexión a la base de datos
include("conexion.php");

// Establecer zona horaria de Venezuela
date_default_timezone_set('America/Caracas');

// Obtener datos del POST
if (!isset($_POST['resultados_json'])) {
    die("Error: Datos insuficientes para generar el reporte.");
}

$filtro_tipo = $_POST['filtro_tipo'] ?? 'todos';
$resultados = json_decode($_POST['resultados_json'], true);

if (!is_array($resultados)) {
    die("Error: Datos inválidos.");
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

// Calcular totales
$total_bienes = 0;
$total_valor = 0;
foreach ($resultados as $ubic) {
    $total_bienes += $ubic['cantidad_bienes'];
    $total_valor += $ubic['valor_total'];
}

// Cerrar conexión
$conn->close();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ubicaciones - Bienes Nacionales</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @page {
            margin: 15mm;
            size: A4 landscape;
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: transparent;
            font-size: 8px;
            color: #333;
        }

        .header {
            position: relative;
            background-color: #ff6600;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 20px;
        }

        .header img {
            width: 80px;
            height: 80px;
        }

        .header-content {
            color: white;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }

        .header p {
            margin: 5px 0 0 0;
            font-size: 14px;
        }

        .report-info {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            border-left: 4px solid #ff6600;
        }

        .info-row {
            flex: 1;
            min-width: 200px;
        }

        .info-label {
            font-weight: bold;
            color: #ff6600;
        }

        .stats-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-box {
            flex: 1;
            background-color: #fff3e0;
            border: 2px solid #ff6600;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }

        .stat-box h3 {
            margin: 0;
            font-size: 24px;
            color: #e65100;
        }

        .stat-box p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: #666;
        }

        .table-container {
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7px;
        }

        thead th {
            background-color: #ff6600;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #e65100;
        }

        tbody td {
            padding: 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tbody tr:hover {
            background-color: #fff3e0;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            padding: 10px;
            font-size: 8px;
            color: #666;
        }

        .type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 7px;
            font-weight: bold;
        }

        .type-pnf {
            background-color: #2196f3;
            color: white;
        }

        .type-sede {
            background-color: #4caf50;
            color: white;
        }

        .type-edificio {
            background-color: #ff9800;
            color: white;
        }

        .type-oficina {
            background-color: #9c27b0;
            color: white;
        }

        .type-aula {
            background-color: #00bcd4;
            color: white;
        }

        .type-laboratorio {
            background-color: #e91e63;
            color: white;
        }

        .type-otro {
            background-color: #607d8b;
            color: white;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="header-content">
            <h1>REPORTE DE UBICACIONES</h1>
            <p>Oficina de Bienes Nacionales - Universidad</p>
        </div>
    </div>

    <div class="stats-container">
        <div class="stat-box">
            <h3><?php echo count($resultados); ?></h3>
            <p>Total Ubicaciones</p>
        </div>
        <div class="stat-box">
            <h3><?php echo number_format($total_bienes, 0, ',', '.'); ?></h3>
            <p>Bienes en Ubicaciones</p>
        </div>
        <div class="stat-box">
            <h3><?php echo number_format($total_valor, 2, ',', '.'); ?> Bs.</h3>
            <p>Valor Total de Bienes</p>
        </div>
    </div>

    <div class="report-info">
        <div class="info-row">
            <span class="info-label">Tipo de Ubicación:</span> <?php
                $tipo_texto = [
                    'todos' => 'Todos',
                    'pnf' => 'Programas Nacionales de Formación (PNF)',
                    'sede' => 'Sedes',
                    'edificio' => 'Edificios',
                    'piso' => 'Pisos/Niveles',
                    'oficina' => 'Oficinas',
                    'aula' => 'Aulas',
                    'laboratorio' => 'Laboratorios',
                    'sala_reunion' => 'Salas de Reuniones',
                    'area_comun' => 'Áreas Comunes',
                    'otro' => 'Otros'
                ];
                echo $tipo_texto[$filtro_tipo] ?? 'Todos';
            ?>
        </div>

        <div class="info-row">
            <span class="info-label">Generado el:</span> <?php echo date('d/m/Y H:i:s'); ?>
        </div>

        <div class="info-row">
            <span class="info-label">Generado por:</span> <?= $nombre_usuario ?>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">Nº</th>
                    <th style="width: 150px;">Nombre de Ubicación</th>
                    <th style="width: 100px;">Tipo</th>
                    <th style="width: 120px;">Dependencia</th>
                    <th style="width: 150px;">Dirección/Referencia</th>
                    <th style="width: 100px;">Responsable</th>
                    <th style="width: 80px;">Teléfono</th>
                    <th style="width: 60px;">Bienes</th>
                    <th style="width: 100px;">Valor Bs.</th>
                    <th style="width: 100px;">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultados as $ubic): ?>
                <tr>
                    <td><?php echo $contador++; ?></td>
                    <td><?php echo htmlspecialchars($ubic['nombre'] ?? 'N/A'); ?></td>
                    <td>
                        <?php 
                            $tipo = strtolower($ubic['tipo'] ?? '');
                            $badge_class = 'type-otro';
                            if (strpos($tipo, 'pnf') !== false) {
                                $badge_class = 'type-pnf';
                            } elseif (strpos($tipo, 'sede') !== false) {
                                $badge_class = 'type-sede';
                            } elseif (strpos($tipo, 'edificio') !== false) {
                                $badge_class = 'type-edificio';
                            } elseif (strpos($tipo, 'oficina') !== false) {
                                $badge_class = 'type-oficina';
                            } elseif (strpos($tipo, 'aula') !== false) {
                                $badge_class = 'type-aula';
                            } elseif (strpos($tipo, 'laboratorio') !== false) {
                                $badge_class = 'type-laboratorio';
                            }
                        ?>
                        <span class="type-badge <?php echo $badge_class; ?>">
                            <?php echo htmlspecialchars(ucfirst($ubic['tipo'] ?? 'N/A')); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($ubic['dependencia'] ?? 'Sin asignar'); ?></td>
                    <td><?php echo htmlspecialchars($ubic['direccion'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($ubic['responsable'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($ubic['telefono'] ?? 'N/A'); ?></td>
                    <td style="text-align: center; font-weight: bold;"><?php echo number_format($ubic['cantidad_bienes'] ?? 0, 0, ',', '.'); ?></td>
                    <td style="text-align: right;"><?php echo number_format($ubic['valor_total'] ?? 0, 2, ',', '.'); ?></td>
                    <td>
                        <?php if (isset($ubic['activo']) && $ubic['activo'] == 1): ?>
                            <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; background-color: #4caf50; color: white; font-size: 7px;">Activo</span>
                        <?php else: ?>
                            <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; background-color: #f44336; color: white; font-size: 7px;">Inactivo</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #fff3e0; font-weight: bold;">
                    <td colspan="7" style="text-align: right;">TOTALES:</td>
                    <td style="text-align: center;"><?php echo number_format($total_bienes, 0, ',', '.'); ?></td>
                    <td style="text-align: right;"><?php echo number_format($total_valor, 2, ',', '.'); ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="footer">
        Sistema de Gestión de Bienes Nacionales - Oficina de Bienes Nacionales - <?php echo date('Y'); ?>
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

// Renderizar PDF
$dompdf->render();

// Generar nombre del archivo
$nombre_archivo = 'reporte_ubicaciones_' . date('Y-m-d_H-i-s') . '.pdf';

// Enviar al navegador
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

$dompdf->stream($nombre_archivo, ['attachment' => true]);
exit;
?>
