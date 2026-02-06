<?php
session_start();
// Iniciar buffer de salida
ob_start();

// Conexión a la base de datos
include("conexion.php");

// Establecer zona horaria de Venezuela
date_default_timezone_set('America/Caracas');

// Obtener datos del movimiento desde la sesión
if (!isset($_SESSION['ultimo_movimiento'])) {
    die("Error: No hay datos de movimiento disponibles.");
}

$movimiento = $_SESSION['ultimo_movimiento'];

// Obtener nombre del usuario logueado desde la sesión
$nombre_usuario = 'Usuario del Sistema';
if (isset($_SESSION['usuario']['nombre']) && isset($_SESSION['usuario']['apellido'])) {
    $nombre_usuario = htmlspecialchars($_SESSION['usuario']['nombre'] . ' ' . $_SESSION['usuario']['apellido']);
} elseif (isset($_SESSION['usuario']['nombre'])) {
    $nombre_usuario = htmlspecialchars($_SESSION['usuario']['nombre']);
}

$cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'N/A';

// Cerrar conexión
$conn->close();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado de Movimiento - Bienes Nacionales UPTAG</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @page {
            margin: 15mm;
            size: A4 portrait;
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: transparent;
            font-size: 10px;
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
            font-size: 18px;
            font-weight: bold;
        }

        .header p {
            margin: 5px 0 0 0;
            font-size: 12px;
        }

        .certificado {
            border: 2px solid #333;
            padding: 20px;
            margin: 15px;
        }

        .certificado h2 {
            text-align: center;
            margin: 0 0 20px 0;
            font-size: 16px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }

        .info-section {
            margin-bottom: 15px;
        }

        .info-section h3 {
            font-size: 12px;
            margin: 0 0 10px 0;
            background-color: #333;
            color: white;
            padding: 5px 10px;
        }

        .info-row {
            display: flex;
            margin-bottom: 5px;
        }

        .info-label {
            font-weight: bold;
            width: 150px;
        }

        .info-value {
            flex: 1;
        }

        .firma {
            margin-top: 40px;
            border-top: 1px solid #333;
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
        }

        .firma-box {
            text-align: center;
            width: 200px;
        }

        .firma-line {
            border-bottom: 1px solid #333;
            margin-bottom: 5px;
            height: 40px;
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

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60px;
            color: rgba(0, 0, 0, 0.1);
            z-index: -1;
        }
    </style>
</head>
<body>

    <div class="watermark">UPTAG</div>

    <div class="header">
        <h1>UNIVERSIDAD POLITÉCNICA DEL TÁCHIRA</h1>
        <p>OFICINA DE BIENES NACIONALES</p>
    </div>

    <div class="certificado">
        <h2>CERTIFICADO DE REGISTRO DE MOVIMIENTO</h2>

        <div class="info-section">
            <h3>DATOS DEL BIEN</h3>
            <div class="info-row">
                <span class="info-label">Código del Bien:</span>
                <span class="info-value"><?php echo htmlspecialchars($movimiento['codigo_bien']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Tipo de Movimiento:</span>
                <span class="info-value"><?php echo htmlspecialchars($movimiento['tipo_movimiento']); ?></span>
            </div>
        </div>

        <div class="info-section">
            <h3>FECHA Y RESPONSABLE</h3>
            <div class="info-row">
                <span class="info-label">Fecha del Movimiento:</span>
                <span class="info-value"><?php echo date('d/m/Y', strtotime($movimiento['fecha_movimiento'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Responsable:</span>
                <span class="info-value"><?php echo htmlspecialchars($movimiento['responsable']); ?></span>
            </div>
        </div>

        <div class="info-section">
            <h3>ORIGEN</h3>
            <div class="info-row">
                <span class="info-label">Ubicación Actual:</span>
                <span class="info-value"><?php echo htmlspecialchars($movimiento['ubicacion_origen']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Estatus Actual:</span>
                <span class="info-value"><?php echo htmlspecialchars($movimiento['estatus_origen']); ?></span>
            </div>
        </div>

        <?php if (!empty($movimiento['motivo'])): ?>
        <div class="info-section">
            <h3>MOTIVO</h3>
            <div class="info-row">
                <span class="info-value"><?php echo htmlspecialchars($movimiento['motivo']); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($movimiento['observaciones'])): ?>
        <div class="info-section">
            <h3>OBSERVACIONES</h3>
            <div class="info-row">
                <span class="info-value"><?php echo htmlspecialchars($movimiento['observaciones']); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="info-section">
            <h3>INFORMACIÓN DEL REGISTRO</h3>
            <div class="info-row">
                <span class="info-label">Registrado por:</span>
                <span class="info-value"><?php echo $nombre_usuario; ?> (C.I: <?php echo $cedula_usuario; ?>)</span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha de Registro:</span>
                <span class="info-value"><?php echo date('d/m/Y H:i:s'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Número de Control:</span>
                <span class="info-value"><?php echo $movimiento['id']; ?></span>
            </div>
        </div>

        <div class="firma">
            <div class="firma-box">
                <div class="firma-line"></div>
                <p>Responsable del Movimiento</p>
            </div>
            <div class="firma-box">
                <div class="firma-line"></div>
                <p>Jefe de la Oficina de Bienes Nacionales</p>
            </div>
        </div>
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
$nombre_archivo = 'certificado_movimiento_' . $movimiento['codigo_bien'] . '_' . date('Y-m-d_H-i-s') . '.pdf';

// Enviar al navegador
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

$dompdf->stream($nombre_archivo, ['attachment' => true]);

// Limpiar la sesión del último movimiento
unset($_SESSION['ultimo_movimiento']);

exit;
?>
