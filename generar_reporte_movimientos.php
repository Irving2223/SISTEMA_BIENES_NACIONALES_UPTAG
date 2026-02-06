<?php
require_once 'conexion.php';

// Verificar autenticación
if (!isset($_SESSION['usuario'])) {
    header("Location: Loggin.php");
    exit;
}

$mensaje = '';
$tipo_mensaje = '';
$resultados = [];
$mostrar_tabla = false;

// Verificar si la tabla movimientos existe
function tablaExiste($conn, $nombre_tabla) {
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($nombre_tabla) . "'");
    return $result && $result->num_rows > 0;
}

// Procesar búsqueda si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'buscar') {
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $filtro_tipo = $_POST['filtro_tipo'] ?? 'todos';
    $filtro_bien = trim($_POST['filtro_bien'] ?? '');
    
    // Validar fechas
    if (empty($fecha_inicio) || empty($fecha_fin)) {
        $mensaje = "Debe seleccionar un rango de fechas.";
        $tipo_mensaje = "error";
    } elseif (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
        $mensaje = "La fecha de inicio no puede ser mayor a la fecha fin.";
        $tipo_mensaje = "error";
    } elseif (!tablaExiste($conn, 'movimientos')) {
        $mensaje = "La tabla de movimientos no existe aún. Debe registrar movimientos primero.";
        $tipo_mensaje = "error";
    } else {
        // Construir consulta
        $sql = "SELECT m.*, b.codigo_bien_nacional FROM movimientos m
                JOIN bienes b ON m.bien_id = b.id
                WHERE m.fecha_movimiento BETWEEN ? AND ?";
        $params = [];
        $types = 'ss';
        
        $params[] = $fecha_inicio;
        $params[] = $fecha_fin;
        
        // Filtro por tipo de movimiento
        if ($filtro_tipo !== 'todos') {
            $sql .= " AND m.tipo_movimiento = ?";
            $params[] = $filtro_tipo;
            $types .= 's';
        }
        
        // Filtro por código de bien
        if (!empty($filtro_bien)) {
            $sql .= " AND b.codigo_bien_nacional LIKE ?";
            $params[] = '%' . $filtro_bien . '%';
            $types .= 's';
        }
        
        $sql .= " ORDER BY m.fecha_movimiento DESC";
        
        // Ejecutar consulta
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Obtener nombres de ubicaciones
            if (!empty($row['ubicacion_origen_id'])) {
                $stmt_ubic = $conn->prepare("SELECT nombre FROM ubicaciones WHERE id = ?");
                $stmt_ubic->bind_param("i", $row['ubicacion_origen_id']);
                $stmt_ubic->execute();
                $res_ubic = $stmt_ubic->get_result();
                if ($res_ubic->num_rows > 0) {
                    $ubic = $res_ubic->fetch_assoc();
                    $row['ubicacion_origen'] = $ubic['nombre'];
                }
                $stmt_ubic->close();
            }
            
            if (!empty($row['ubicacion_destino_id'])) {
                $stmt_ubic = $conn->prepare("SELECT nombre FROM ubicaciones WHERE id = ?");
                $stmt_ubic->bind_param("i", $row['ubicacion_destino_id']);
                $stmt_ubic->execute();
                $res_ubic = $stmt_ubic->get_result();
                if ($res_ubic->num_rows > 0) {
                    $ubic = $res_ubic->fetch_assoc();
                    $row['ubicacion_destino'] = $ubic['nombre'];
                }
                $stmt_ubic->close();
            }
            
            // Obtener nombres de estatus
            if (!empty($row['estatus_origen_id'])) {
                $stmt_est = $conn->prepare("SELECT nombre FROM estatus_bienes WHERE id = ?");
                $stmt_est->bind_param("i", $row['estatus_origen_id']);
                $stmt_est->execute();
                $res_est = $stmt_est->get_result();
                if ($res_est->num_rows > 0) {
                    $est = $res_est->fetch_assoc();
                    $row['estatus_origen'] = $est['nombre'];
                }
                $stmt_est->close();
            }
            
            if (!empty($row['estatus_destino_id'])) {
                $stmt_est = $conn->prepare("SELECT nombre FROM estatus_bienes WHERE id = ?");
                $stmt_est->bind_param("i", $row['estatus_destino_id']);
                $stmt_est->execute();
                $res_est = $stmt_est->get_result();
                if ($res_est->num_rows > 0) {
                    $est = $res_est->fetch_assoc();
                    $row['estatus_destino'] = $est['nombre'];
                }
                $stmt_est->close();
            }
            
            $resultados[] = $row;
        }
        $stmt->close();
        
        $mostrar_tabla = true;
        
        if (empty($resultados)) {
            $mensaje = "No se encontraron movimientos en el rango de fechas especificado.";
            $tipo_mensaje = "info";
        } else {
            $mensaje = "Se encontraron " . count($resultados) . " movimientos.";
            $tipo_mensaje = "success";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Reporte de Movimientos - Sistema INTI</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/estilos_sistema.css">
    <link rel="stylesheet" href="css/material-design-iconic-font.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1 style="font-weight:900; font-family:montserrat; color:#ff6600; font-size:40px; padding:20px; text-align:left; font-size:50px;">
            <i class="zmdi zmdi-swap"></i> 
            Generar Reporte <span style="font-weight:700; color:black;">de Movimientos</span>
        </h1>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $tipo_mensaje == 'error' ? 'danger' : ($tipo_mensaje == 'info' ? 'warning' : 'success'); ?>" style="margin: 20px;">
                <?= htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario de filtros -->
        <form id="form-reporte" method="POST" action="" class="section-container">
            <input type="hidden" name="accion" value="buscar">
            <h4 class="section-title">Parámetros del Reporte</h4>
            
            <!-- Rango de fechas -->
            <div class="field-row">
                <div class="field-col">
                    <label for="fecha_inicio" class="field-label required">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" 
                           value="<?= $_POST['fecha_inicio'] ?? date('Y-m-01'); ?>" required />
                </div>
                <div class="field-col">
                    <label for="fecha_fin" class="field-label required">Fecha Fin</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" 
                           value="<?= $_POST['fecha_fin'] ?? date('Y-m-d'); ?>" required />
                </div>
            </div>
            
            <!-- Filtros adicionales -->
            <div class="field-row">
                <div class="field-col">
                    <label for="filtro_tipo" class="field-label">Tipo de Movimiento</label>
                    <select name="filtro_tipo" id="filtro_tipo" class="form-control">
                        <option value="todos">Todos los tipos</option>
                        <option value="traslado">Traslados</option>
                        <option value="cambio_estatus">Cambios de Estatus</option>
                        <option value="mantenimiento">Mantenimiento</option>
                        <option value="reparacion">Reparación</option>
                        <option value="desincorporacion">Desincorporaciones</option>
                        <option value="prestamo">Préstamos</option>
                        <option value="devolucion">Devoluciones</option>
                        <option value="otro">Otros</option>
                    </select>
                </div>
                <div class="field-col">
                    <label for="filtro_bien" class="field-label">Código de Bien</label>
                    <input type="text" name="filtro_bien" id="filtro_bien" 
                           placeholder="Buscar por código de bien (opcional)" 
                           value="<?= $_POST['filtro_bien'] ?? ''; ?>" 
                           class="form-control" />
                </div>
            </div>
            
            <!-- Botones -->
            <div class="button-container">
                <button type="reset" class="btn btn-secondary">
                    <i class="zmdi zmdi-refresh"></i> Limpiar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="zmdi zmdi-search"></i> Buscar
                </button>
            </div>
        </form>

        <!-- Resultados -->
        <?php if ($mostrar_tabla && !empty($resultados)): ?>
        <div class="section-container">
            <h4 class="section-title">Resultados de la Búsqueda (<?= count($resultados); ?> movimientos)</h4>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #ff6600; color: white;">
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Nº</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Código BN</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Fecha</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Tipo</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Origen</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Destino</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Responsable</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $contador = 1; foreach ($resultados as $mov): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;"><?= $contador++; ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($mov['codigo_bien_nacional'] ?? 'N/A'); ?></td>
                            <td style="padding: 10px;"><?= isset($mov['fecha_movimiento']) ? date('d/m/Y', strtotime($mov['fecha_movimiento'])) : 'N/A'; ?></td>
                            <td style="padding: 10px;">
                                <?php 
                                    $tipo = strtolower($mov['tipo_movimiento'] ?? '');
                                    $color = '#4caf50';
                                    if (strpos($tipo, 'traslado') !== false) $color = '#2196f3';
                                    elseif (strpos($tipo, 'mantenimiento') !== false) $color = '#ff9800';
                                    elseif (strpos($tipo, 'desincorpor') !== false) $color = '#f44336';
                                    elseif (strpos($tipo, 'prestamo') !== false) $color = '#9c27b0';
                                ?>
                                <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; background-color: <?= $color; ?>; color: white; font-size: 0.85em;">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $mov['tipo_movimiento'] ?? 'N/A'))); ?>
                                </span>
                            </td>
                            <td style="padding: 10px;"><?= htmlspecialchars($mov['ubicacion_origen'] ?? 'N/A'); ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($mov['ubicacion_destino'] ?? 'N/A'); ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($mov['responsable'] ?? 'N/A'); ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($mov['motivo'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Botón para generar PDF -->
            <form action="reporte_movimientos.php" method="POST" target="_blank" style="margin-top: 20px; text-align: right;">
                <input type="hidden" name="fecha_inicio" value="<?= $_POST['fecha_inicio']; ?>">
                <input type="hidden" name="fecha_fin" value="<?= $_POST['fecha_fin']; ?>">
                <input type="hidden" name="filtro_tipo" value="<?= $_POST['filtro_tipo'] ?? 'todos'; ?>">
                <input type="hidden" name="resultados_json" value="<?php echo htmlspecialchars(json_encode($resultados), ENT_QUOTES, 'UTF-8'); ?>">
                
                <button type="submit" class="btn btn-primary" style="background-color: #ff6600; border-color: #ff6600;">
                    <i class="zmdi zmdi-download"></i> Descargar PDF
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Información -->
        <div class="section-container" style="background-color: #fff3e0;">
            <h4 class="section-title" style="color: #ff6600;">
                <i class="zmdi zmdi-info-outline"></i> Información sobre el Reporte de Movimientos
            </h4>
            <ul style="margin: 0; padding-left: 20px; color: #333;">
                <li style="margin-bottom: 8px;"><strong>Rango de Fechas:</strong> Permite filtrar movimientos por fecha de realización.</li>
                <li style="margin-bottom: 8px;"><strong>Tipos de Movimiento:</strong> Traslados, cambios de estatus, mantenimiento, reparación, desincorporaciones, préstamos, devoluciones y otros.</li>
                <li style="margin-bottom: 8px;"><strong>Filtro por Bien:</strong> Puede buscar movimientos de un bien específico por su código.</li>
                <li style="margin-bottom: 8px;"><strong>Resultados:</strong> Muestra información detallada de origen, destino, responsables y motivos.</li>
                <li><strong>PDF:</strong> El reporte en PDF incluye toda la información y está diseñado para impresión en formato A4 horizontal.</li>
            </ul>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script src="js/jquery-3.1.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        // Validar fechas antes de enviar
        document.getElementById('form-reporte').addEventListener('submit', function(e) {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            
            if (!fechaInicio || !fechaFin) {
                e.preventDefault();
                alert('Por favor, complete el rango de fechas.');
                return false;
            }
            
            if (new Date(fechaInicio) > new Date(fechaFin)) {
                e.preventDefault();
                alert('La fecha de inicio no puede ser mayor a la fecha fin.');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>



	<!-- Scripts -->
	<script src="./js/jquery-3.1.1.min.js"></script>
	<script src="./js/bootstrap.min.js"></script>
	<script src="./js/material.min.js"></script>
	<script src="./js/ripples.min.js"></script>
	<script src="./js/jquery.mCustomScrollbar.concat.min.js"></script>
	<script src="./js/main.js"></script>
	<script>
		$.material.init();
	</script>