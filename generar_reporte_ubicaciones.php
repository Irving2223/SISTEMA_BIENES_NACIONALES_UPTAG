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

// Verificar si la tabla ubicaciones existe
function tablaExiste($conn, $nombre_tabla) {
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($nombre_tabla) . "'");
    return $result && $result->num_rows > 0;
}

// Procesar búsqueda si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'buscar') {
    $filtro_tipo = $_POST['filtro_tipo'] ?? 'todos';
    $filtro_dependencia = $_POST['filtro_dependencia'] ?? 'todos';
    
    if (!tablaExiste($conn, 'ubicaciones')) {
        $mensaje = "La tabla de ubicaciones no existe aún. Debe registrar ubicaciones primero.";
        $tipo_mensaje = "error";
    } else {
        // Construir consulta para ubicaciones
        $sql = "SELECT u.*, d.nombre as dependencia_nombre FROM ubicaciones u 
                LEFT JOIN dependencias d ON u.dependencia_id = d.id 
                WHERE 1=1";
        
        // Filtro por tipo de ubicación
        if ($filtro_tipo !== 'todos') {
            $sql .= " AND u.tipo = ?";
        }
        
        // Filtro por dependencia
        if ($filtro_dependencia !== 'todas') {
            $sql .= " AND u.dependencia_id = ?";
        }
        
        // Ordenar por nombre (evitar usar columnas que pueden no existir)
        $sql .= " ORDER BY u.nombre";
        
        // Ejecutar consulta
        $stmt = $conn->prepare($sql);
        
        $params = [];
        $types = '';
        
        if ($filtro_tipo !== 'todos') {
            $params[] = $filtro_tipo;
            $types .= 's';
        }
        
        if ($filtro_dependencia !== 'todas') {
            $params[] = $filtro_dependencia;
            $types .= 'i';
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Contar bienes en esta ubicación
            $cantidad_bienes = 0;
            $valor_total = 0;
            
            if (tablaExiste($conn, 'bienes')) {
                $stmt_bienes = $conn->prepare("SELECT COUNT(*) as cantidad, COALESCE(SUM(valor_original), 0) as valor FROM bienes WHERE ubicacion_id = ? AND activo = 1");
                $stmt_bienes->bind_param("i", $row['id']);
                $stmt_bienes->execute();
                $res_bienes = $stmt_bienes->get_result();
                if ($res_bienes->num_rows > 0) {
                    $bienes = $res_bienes->fetch_assoc();
                    $cantidad_bienes = $bienes['cantidad'];
                    $valor_total = $bienes['valor'];
                }
                $stmt_bienes->close();
            }
            
            $row['cantidad_bienes'] = $cantidad_bienes;
            $row['valor_total'] = $valor_total;
            $row['dependencia'] = $row['dependencia_nombre'] ?? 'Sin asignar';
            
            $resultados[] = $row;
        }
        $stmt->close();
        
        $mostrar_tabla = true;
        
        if (empty($resultados)) {
            $mensaje = "No se encontraron ubicaciones con los filtros especificados.";
            $tipo_mensaje = "info";
        } else {
            $total_bienes = array_sum(array_column($resultados, 'cantidad_bienes'));
            $mensaje = "Se encontraron " . count($resultados) . " ubicaciones con $total_bienes bienes asignados.";
            $tipo_mensaje = "success";
        }
    }
}

// Obtener dependencias para dropdown
$dependencias = [];
if (tablaExiste($conn, 'dependencias')) {
    $result_dep = $conn->query("SELECT id, nombre FROM dependencias WHERE activo = 1 ORDER BY nombre");
    if ($result_dep) {
        $dependencias = $result_dep->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Reporte de Ubicaciones - Sistema INTI</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/estilos_sistema.css">
    <link rel="stylesheet" href="css/material-design-iconic-font.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1 style="font-weight:900; font-family:montserrat; color:#ff6600; font-size:40px; padding:20px; text-align:left; font-size:50px;">
            <i class="zmdi zmdi-pin"></i> 
            Generar Reporte <span style="font-weight:700; color:black;">de Ubicaciones</span>
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
            
            <!-- Filtros -->
            <div class="field-row">
                <div class="field-col">
                    <label for="filtro_tipo" class="field-label">Tipo de Ubicación</label>
                    <select name="filtro_tipo" id="filtro_tipo" class="form-control">
                        <option value="todos">Todos los tipos</option>
                        <option value="pnf">Programas Nacionales de Formación (PNF)</option>
                        <option value="sede">Sedes</option>
                        <option value="edificio">Edificios</option>
                        <option value="piso">Pisos/Niveles</option>
                        <option value="oficina">Oficinas</option>
                        <option value="aula">Aulas</option>
                        <option value="laboratorio">Laboratorios</option>
                        <option value="sala_reunion">Salas de Reuniones</option>
                        <option value="area_comun">Áreas Comunes</option>
                        <option value="otro">Otros</option>
                    </select>
                </div>
                <div class="field-col">
                    <label for="filtro_dependencia" class="field-label">Dependencia</label>
                    <select name="filtro_dependencia" id="filtro_dependencia" class="form-control">
                        <option value="todas">Todas las dependencias</option>
                        <?php foreach ($dependencias as $dep): ?>
                            <option value="<?= $dep['id']; ?>"><?= htmlspecialchars($dep['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($dependencias)): ?>
                        <small style="color: #999;">No hay dependencias registradas</small>
                    <?php endif; ?>
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
        <?php 
            $total_bienes = array_sum(array_column($resultados, 'cantidad_bienes'));
            $total_valor = array_sum(array_column($resultados, 'valor_total'));
        ?>
        <!-- Estadísticas -->
        <div class="stats-container" style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div class="stat-box" style="flex: 1; background-color: #fff3e0; border: 2px solid #ff6600; border-radius: 8px; padding: 20px; text-align: center;">
                <h3 style="margin: 0; color: #e65100; font-size: 28px;"><?php echo count($resultados); ?></h3>
                <p style="margin: 5px 0 0 0; color: #666;">Total Ubicaciones</p>
            </div>
            <div class="stat-box" style="flex: 1; background-color: #fff3e0; border: 2px solid #ff6600; border-radius: 8px; padding: 20px; text-align: center;">
                <h3 style="margin: 0; color: #e65100; font-size: 28px;"><?php echo number_format($total_bienes, 0, ',', '.'); ?></h3>
                <p style="margin: 5px 0 0 0; color: #666;">Bienes Asignados</p>
            </div>
            <div class="stat-box" style="flex: 1; background-color: #fff3e0; border: 2px solid #ff6600; border-radius: 8px; padding: 20px; text-align: center;">
                <h3 style="margin: 0; color: #e65100; font-size: 28px;"><?php echo number_format($total_valor, 2, ',', '.'); ?> Bs.</h3>
                <p style="margin: 5px 0 0 0; color: #666;">Valor Total</p>
            </div>
        </div>
        
        <div class="section-container">
            <h4 class="section-title">Resultados de la Búsqueda (<?php echo count($resultados); ?> ubicaciones)</h4>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #ff6600; color: white;">
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Nº</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Nombre</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Tipo</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Dependencia</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Dirección</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Responsable</th>
                            <th style="padding: 12px; text-align: center; border: 1px solid #e65100;">Bienes</th>
                            <th style="padding: 12px; text-align: right; border: 1px solid #e65100;">Valor</th>
                            <th style="padding: 12px; text-align: center; border: 1px solid #e65100;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $contador = 1; foreach ($resultados as $ubic): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;"><?= $contador++; ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($ubic['nombre'] ?? 'N/A'); ?></td>
                            <td style="padding: 10px;">
                                <?php 
                                    $tipo = strtolower($ubic['tipo'] ?? '');
                                    $color = '#4caf50';
                                    if (strpos($tipo, 'pnf') !== false) $color = '#2196f3';
                                    elseif (strpos($tipo, 'edificio') !== false) $color = '#ff9800';
                                    elseif (strpos($tipo, 'oficina') !== false) $color = '#9c27b0';
                                    elseif (strpos($tipo, 'aula') !== false) $color = '#00bcd4';
                                    elseif (strpos($tipo, 'laboratorio') !== false) $color = '#e91e63';
                                ?>
                                <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; background-color: <?= $color; ?>; color: white; font-size: 0.85em;">
                                    <?= htmlspecialchars(ucfirst($ubic['tipo'] ?? 'N/A')); ?>
                                </span>
                            </td>
                            <td style="padding: 10px;"><?= htmlspecialchars($ubic['dependencia'] ?? 'Sin asignar'); ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($ubic['direccion'] ?? 'N/A'); ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($ubic['responsable'] ?? 'N/A'); ?></td>
                            <td style="padding: 10px; text-align: center; font-weight: bold;"><?= number_format($ubic['cantidad_bienes'] ?? 0, 0, ',', '.'); ?></td>
                            <td style="padding: 10px; text-align: right;"><?= number_format($ubic['valor_total'] ?? 0, 2, ',', '.'); ?></td>
                            <td style="padding: 10px; text-align: center;">
                                <?php if (isset($ubic['activo']) && $ubic['activo'] == 1): ?>
                                    <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; background-color: #4caf50; color: white; font-size: 0.85em;">Activo</span>
                                <?php else: ?>
                                    <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; background-color: #f44336; color: white; font-size: 0.85em;">Inactivo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #fff3e0; font-weight: bold;">
                            <td colspan="6" style="padding: 10px; text-align: right;">TOTALES:</td>
                            <td style="padding: 10px; text-align: center;"><?= number_format($total_bienes, 0, ',', '.'); ?></td>
                            <td style="padding: 10px; text-align: right;"><?= number_format($total_valor, 2, ',', '.'); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <!-- Botón para generar PDF -->
            <form action="reporte_ubicaciones.php" method="POST" target="_blank" style="margin-top: 20px; text-align: right;">
                <input type="hidden" name="filtro_tipo" value="<?= $_POST['filtro_tipo'] ?? 'todos'; ?>">
                <input type="hidden" name="filtro_dependencia" value="<?= $_POST['filtro_dependencia'] ?? 'todas'; ?>">
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
                <i class="zmdi zmdi-info-outline"></i> Información sobre el Reporte de Ubicaciones
            </h4>
            <ul style="margin: 0; padding-left: 20px; color: #333;">
                <li style="margin-bottom: 8px;"><strong>Tipos de Ubicación:</strong> PNF, Sedes, Edificios, Pisos, Oficinas, Aulas, Laboratorios, Salas de Reuniones, Áreas Comunes.</li>
                <li style="margin-bottom: 8px;"><strong>Estadísticas:</strong> El reporte muestra el total de ubicaciones, bienes asignados y valor total de los bienes.</li>
                <li style="margin-bottom: 8px;"><strong>Dependencias:</strong> Permite filtrar por dependencia asociada a cada ubicación.</li>
                <li style="margin-bottom: 8px;"><strong>Organización:</strong> Las ubicaciones se organizan jerárquicamente para facilitar la localización de bienes.</li>
                <li><strong>PDF:</strong> El reporte incluye toda la información y está diseñado para impresión en formato A4 horizontal.</li>
            </ul>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script src="js/jquery-3.1.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
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