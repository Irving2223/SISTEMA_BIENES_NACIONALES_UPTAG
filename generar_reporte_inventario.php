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

// Obtener datos para dropdowns
$ubicaciones = [];
$categorias = [];
$estatus = [];

try {
    // Verificar si las tablas existen
    function tablaExiste($conn, $nombre_tabla) {
        $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($nombre_tabla) . "'");
        return $result && $result->num_rows > 0;
    }
    
    if (tablaExiste($conn, 'ubicaciones')) {
        $result_ubicaciones = $conn->query("SELECT id, nombre FROM ubicaciones WHERE activo = 1 ORDER BY nombre");
        if ($result_ubicaciones) {
            $ubicaciones = $result_ubicaciones->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    if (tablaExiste($conn, 'categorias')) {
        $result_categorias = $conn->query("SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre");
        if ($result_categorias) {
            $categorias = $result_categorias->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    if (tablaExiste($conn, 'estatus_bienes')) {
        $result_estatus = $conn->query("SELECT id, nombre FROM estatus_bienes WHERE activo = 1 ORDER BY nombre");
        if ($result_estatus) {
            $estatus = $result_estatus->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // Procesar búsqueda si se envía el formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'buscar') {
        $fecha_inicio = $_POST['fecha_inicio'] ?? '';
        $fecha_fin = $_POST['fecha_fin'] ?? '';
        $filtro_estatus = $_POST['filtro_estatus'] ?? 'todos';
        $filtro_ubicacion = $_POST['filtro_ubicacion'] ?? 'todos';
        $filtro_categoria = $_POST['filtro_categoria'] ?? 'todos';
        
        // Validar fechas
        if (empty($fecha_inicio) || empty($fecha_fin)) {
            $mensaje = "Debe seleccionar un rango de fechas.";
            $tipo_mensaje = "error";
        } elseif (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
            $mensaje = "La fecha de inicio no puede ser mayor a la fecha fin.";
            $tipo_mensaje = "error";
        } else {
            // Construir consulta
            $sql = "SELECT b.* FROM bienes b WHERE 1=1";
            $params = [];
            $types = '';
            
            // Filtro por fecha de incorporación
            $sql .= " AND b.fecha_incorporacion BETWEEN ? AND ?";
            $params[] = $fecha_inicio;
            $params[] = $fecha_fin;
            $types .= 'ss';
            
            // Filtro por estatus
            if ($filtro_estatus !== 'todos' && tablaExiste($conn, 'estatus_bienes')) {
                $sql .= " AND b.estatus_id = ?";
                $params[] = $filtro_estatus;
                $types .= 'i';
            }
            
            // Filtro por ubicación
            if ($filtro_ubicacion !== 'todos' && tablaExiste($conn, 'ubicaciones')) {
                $sql .= " AND b.ubicacion_id = ?";
                $params[] = $filtro_ubicacion;
                $types .= 'i';
            }
            
            // Filtro por categoría
            if ($filtro_categoria !== 'todos' && tablaExiste($conn, 'categorias')) {
                $sql .= " AND b.categoria_id = ?";
                $params[] = $filtro_categoria;
                $types .= 'i';
            }
            
            $sql .= " ORDER BY b.fecha_incorporacion DESC";
            
            // Ejecutar consulta
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                // Obtener nombres de tablas relacionadas
                if (!empty($row['ubicacion_id']) && tablaExiste($conn, 'ubicaciones')) {
                    $stmt_ubic = $conn->prepare("SELECT nombre FROM ubicaciones WHERE id = ?");
                    $stmt_ubic->bind_param("i", $row['ubicacion_id']);
                    $stmt_ubic->execute();
                    $res_ubic = $stmt_ubic->get_result();
                    if ($res_ubic->num_rows > 0) {
                        $ubic = $res_ubic->fetch_assoc();
                        $row['ubicacion'] = $ubic['nombre'];
                    }
                    $stmt_ubic->close();
                }
                
                if (!empty($row['estatus_id']) && tablaExiste($conn, 'estatus_bienes')) {
                    $stmt_est = $conn->prepare("SELECT nombre FROM estatus_bienes WHERE id = ?");
                    $stmt_est->bind_param("i", $row['estatus_id']);
                    $stmt_est->execute();
                    $res_est = $stmt_est->get_result();
                    if ($res_est->num_rows > 0) {
                        $est = $res_est->fetch_assoc();
                        $row['estatus'] = $est['nombre'];
                    }
                    $stmt_est->close();
                }
                
                if (!empty($row['categoria_id']) && tablaExiste($conn, 'categorias')) {
                    $stmt_cat = $conn->prepare("SELECT nombre FROM categorias WHERE id = ?");
                    $stmt_cat->bind_param("i", $row['categoria_id']);
                    $stmt_cat->execute();
                    $res_cat = $stmt_cat->get_result();
                    if ($res_cat->num_rows > 0) {
                        $cat = $res_cat->fetch_assoc();
                        $row['categoria'] = $cat['nombre'];
                    }
                    $stmt_cat->close();
                }
                
                if (!empty($row['dependencia_id']) && tablaExiste($conn, 'dependencias')) {
                    $stmt_dep = $conn->prepare("SELECT nombre FROM dependencias WHERE id = ?");
                    $stmt_dep->bind_param("i", $row['dependencia_id']);
                    $stmt_dep->execute();
                    $res_dep = $stmt_dep->get_result();
                    if ($res_dep->num_rows > 0) {
                        $dep = $res_dep->fetch_assoc();
                        $row['dependencia'] = $dep['nombre'];
                    }
                    $stmt_dep->close();
                }
                
                $resultados[] = $row;
            }
            $stmt->close();
            
            $mostrar_tabla = true;
            
            if (empty($resultados)) {
                $mensaje = "No se encontraron bienes en el rango de fechas especificado.";
                $tipo_mensaje = "info";
            } else {
                $mensaje = "Se encontraron " . count($resultados) . " bienes.";
                $tipo_mensaje = "success";
            }
        }
    }
} catch (Exception $e) {
    $mensaje = "Error: " . $e->getMessage();
    $tipo_mensaje = "error";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Reporte de Inventario - Sistema INTI</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/estilos_sistema.css">
    <link rel="stylesheet" href="css/material-design-iconic-font.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1 style="font-weight:900; font-family:montserrat; color:#ff6600; font-size:40px; padding:20px; text-align:left; font-size:50px;">
            <i class="zmdi zmdi-file-text"></i> 
            Generar Reporte <span style="font-weight:700; color:black;">de Inventario</span>
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
                    <label for="filtro_estatus" class="field-label">Estatus</label>
                    <select name="filtro_estatus" id="filtro_estatus" class="form-control">
                        <option value="todos">Todos los estatus</option>
                        <?php foreach ($estatus as $est): ?>
                            <option value="<?= $est['id']; ?>" <?= (isset($_POST['filtro_estatus']) && $_POST['filtro_estatus'] == $est['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($est['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-col">
                    <label for="filtro_ubicacion" class="field-label">Ubicación</label>
                    <select name="filtro_ubicacion" id="filtro_ubicacion" class="form-control">
                        <option value="todas">Todas las ubicaciones</option>
                        <?php foreach ($ubicaciones as $ubic): ?>
                            <option value="<?= $ubic['id']; ?>" <?= (isset($_POST['filtro_ubicacion']) && $_POST['filtro_ubicacion'] == $ubic['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($ubic['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-col">
                    <label for="filtro_categoria" class="field-label">Categoría</label>
                    <select name="filtro_categoria" id="filtro_categoria" class="form-control">
                        <option value="todas">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id']; ?>" <?= (isset($_POST['filtro_categoria']) && $_POST['filtro_categoria'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($cat['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
            <h4 class="section-title">Resultados de la Búsqueda (<?= count($resultados); ?> bienes)</h4>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #ff6600; color: white;">
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Nº</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Código BN</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Descripción</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Marca</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Modelo</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Serial</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Ubicación</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Estatus</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $contador = 1; foreach ($resultados as $bien): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;"><?= $contador++; ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($bien['codigo_bien_nacional'] ?? 'N/A'); ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($bien['descripcion'] ?? 'N/A'); ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($bien['marca'] ?? 'N/A'); ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($bien['modelo'] ?? 'N/A'); ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($bien['serial'] ?? 'N/A'); ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($bien['ubicacion'] ?? 'No asignada'); ?></td>
                            <td style="padding: 10px;">
                                <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; background-color: #4caf50; color: white; font-size: 0.85em;">
                                    <?= htmlspecialchars($bien['estatus'] ?? 'N/A'); ?>
                                </span>
                            </td>
                            <td style="padding: 10px;"><?= isset($bien['valor_original']) ? number_format($bien['valor_original'], 2, ',', '.') : '0,00'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Botón para generar PDF -->
            <form action="reporte_inventario.php" method="POST" target="_blank" style="margin-top: 20px; text-align: right;">
                <input type="hidden" name="fecha_inicio" value="<?= $_POST['fecha_inicio']; ?>">
                <input type="hidden" name="fecha_fin" value="<?= $_POST['fecha_fin']; ?>">
                <input type="hidden" name="filtro_estatus" value="<?= $_POST['filtro_estatus'] ?? 'todos'; ?>">
                <input type="hidden" name="filtro_ubicacion" value="<?= $_POST['filtro_ubicacion'] ?? 'todos'; ?>">
                <input type="hidden" name="filtro_categoria" value="<?= $_POST['filtro_categoria'] ?? 'todos'; ?>">
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
                <i class="zmdi zmdi-info-outline"></i> Información sobre el Reporte
            </h4>
            <ul style="margin: 0; padding-left: 20px; color: #333;">
                <li style="margin-bottom: 8px;"><strong>Rango de Fechas:</strong> Permite filtrar bienes por fecha de incorporación.</li>
                <li style="margin-bottom: 8px;"><strong>Filtros:</strong> Puede filtrar por estatus, ubicación y categoría para mayor precisión.</li>
                <li style="margin-bottom: 8px;"><strong>Resultados:</strong> Muestra una vista previa de los bienes encontrados.</li>
                <li><strong>PDF:</strong> El reporte en PDF incluye información detallada y está diseñado para impresión en formato A4 horizontal.</li>
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
