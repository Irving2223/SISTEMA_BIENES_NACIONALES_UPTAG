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
$filtro_estatus = $_POST['filtro_estatus'] ?? 'todos';
$filtro_ubicacion = $_POST['filtro_ubicacion'] ?? 'todos';
$filtro_categoria = $_POST['filtro_categoria'] ?? 'todos';
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

// Cerrar conexión
$conn->close();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario - Bienes Nacionales</title>
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

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 7px;
            font-weight: bold;
        }

        .status-activo {
            background-color: #4caf50;
            color: white;
        }

        .status-inactivo {
            background-color: #f44336;
            color: white;
        }

        .status-mantenimiento {
            background-color: #ff9800;
            color: white;
        }

        .status-desincorporado {
            background-color: #9e9e9e;
            color: white;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="header-content">
            <h1>REPORTE DE INVENTARIO</h1>
            <p>Oficina de Bienes Nacionales - Universidad</p>
        </div>
    </div>

    <div class="report-info">
        <div class="info-row">
            <span class="info-label">Rango de Fechas:</span> <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
        </div>

        <div class="info-row">
            <span class="info-label">Filtro Estatus:</span> <?php
                $estatus_texto = [
                    'todos' => 'Todos',
                    'activo' => 'Activo',
                    'inactivo' => 'Inactivo',
                    'mantenimiento' => 'En Mantenimiento',
                    'desincorporado' => 'Desincorporado'
                ];
                echo $estatus_texto[$filtro_estatus] ?? 'Todos';
            ?>
        </div>

        <div class="info-row">
            <span class="info-label">Filtro Categoría:</span> <?php echo htmlspecialchars($filtro_categoria === 'todos' ? 'Todas' : $filtro_categoria); ?>
        </div>

        <div class="info-row">
            <span class="info-label">Generado el:</span> <?php echo date('d/m/Y H:i:s'); ?>
        </div>

        <div class="info-row">
            <span class="info-label">Generado por:</span> <?= $nombre_usuario ?>
        </div>

        <div class="info-row">
            <span class="info-label">Total de Bienes:</span> <?php echo count($resultados); ?>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">Nº</th>
                    <th style="width: 100px;">Código BN</th>
                    <th style="width: 120px;">Descripción</th>
                    <th style="width: 80px;">Marca</th>
                    <th style="width: 80px;">Modelo</th>
                    <th style="width: 90px;">Serial</th>
                    <th style="width: 100px;">Ubicación</th>
                    <th style="width: 100px;">Dependencia</th>
                    <th style="width: 90px;">Categoría</th>
                    <th style="width: 70px;">Estatus</th>
                    <th style="width: 80px;">Tipo Adquisición</th>
                    <th style="width: 70px;">Fecha Inc.</th>
                    <th style="width: 80px;">Valor (Bs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultados as $bien): ?>
                <tr>
                    <td><?php echo $contador++; ?></td>
                    <td><?php echo htmlspecialchars($bien['codigo_bien_nacional'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($bien['descripcion'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($bien['marca'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($bien['modelo'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($bien['serial'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($bien['ubicacion'] ?? 'No asignada'); ?></td>
                    <td><?php echo htmlspecialchars($bien['dependencia'] ?? 'No asignada'); ?></td>
                    <td><?php echo htmlspecialchars($bien['categoria'] ?? 'N/A'); ?></td>
                    <td>
                        <?php 
                            $estatus = strtolower($bien['estatus'] ?? 'desconocido');
                            $badge_class = 'status-activo';
                            if (strpos($estatus, 'inactiv') !== false || strpos($estatus, 'baja') !== false) {
                                $badge_class = 'status-inactivo';
                            } elseif (strpos($estatus, 'mantenimiento') !== false || strpos($estatus, 'reparacion') !== false) {
                                $badge_class = 'status-mantenimiento';
                            } elseif (strpos($estatus, 'desincorpor') !== false) {
                                $badge_class = 'status-desincorporado';
                            }
                        ?>
                        <span class="status-badge <?php echo $badge_class; ?>">
                            <?php echo htmlspecialchars($bien['estatus'] ?? 'N/A'); ?>
                        </span>
                    </td>
                    <td><?php 
                        $tipo = $bien['tipo_adquisicion'] ?? '';
                        $tipo_texto = [
                            'compra' => 'Compra',
                            'donacion' => 'Donación',
                            'ingreso_propio' => 'Ingreso Propio'
                        ];
                        echo $tipo_texto[$tipo] ?? htmlspecialchars($tipo ?: 'N/A');
                    ?></td>
                    <td><?php echo isset($bien['fecha_incorporacion']) ? date('d/m/Y', strtotime($bien['fecha_incorporacion'])) : 'N/A'; ?></td>
                    <td><?php echo isset($bien['valor_original']) ? number_format($bien['valor_original'], 2, ',', '.') : '0,00'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
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
$nombre_archivo = 'reporte_inventario_' . date('Y-m-d_H-i-s') . '.pdf';

// Enviar al navegador
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

$dompdf->stream($nombre_archivo, ['attachment' => true]);
exit;
?>
