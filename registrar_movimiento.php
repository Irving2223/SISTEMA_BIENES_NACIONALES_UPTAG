<?php
require_once 'conexion.php';

// Verificar autenticación
if (!isset($_SESSION['usuario'])) {
    header("Location: Loggin.php");
    exit;
}

$mensaje = '';
$tipo_mensaje = '';
$bien_seleccionado = null;

// Procesar formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        $conn->begin_transaction();
        
        if ($_POST['accion'] === 'registrar_movimiento') {
            // Obtener datos del formulario
            $codigo_bien = trim($_POST['codigo_bien'] ?? '');
            $tipo_movimiento = $_POST['tipo_movimiento'] ?? '';
            $ubicacion_origen_id = $_POST['ubicacion_origen_id'] ?? null;
            $ubicacion_destino_id = $_POST['ubicacion_destino_id'] ?? null;
            $estatus_origen_id = $_POST['estatus_origen_id'] ?? null;
            $estatus_destino_id = $_POST['estatus_destino_id'] ?? null;
            $fecha_movimiento = $_POST['fecha_movimiento'] ?? '';
            $responsable = trim($_POST['responsable'] ?? '');
            $motivo = trim($_POST['motivo'] ?? '');
            $observaciones = trim($_POST['observaciones'] ?? '');
            
            // Validaciones
            if (empty($codigo_bien)) {
                throw new Exception("Debe buscar y seleccionar un bien.");
            }
            if (empty($tipo_movimiento)) {
                throw new Exception("Debe seleccionar el tipo de movimiento.");
            }
            if (empty($fecha_movimiento)) {
                throw new Exception("La fecha del movimiento es obligatoria.");
            }
            
            // Verificar que el bien existe
            $stmt_check = $conn->prepare("SELECT * FROM bienes WHERE codigo_bien_nacional = ?");
            $stmt_check->bind_param("s", $codigo_bien);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows === 0) {
                throw new Exception("No se encontró ningún bien con el código: $codigo_bien");
            }
            
            $bien = $result_check->fetch_assoc();
            $bien_id = $bien['id'];
            $stmt_check->close();
            
            // Validaciones específicas por tipo de movimiento
            if ($tipo_movimiento === 'traslado') {
                if (empty($ubicacion_destino_id)) {
                    throw new Exception("Debe seleccionar la ubicación de destino para un traslado.");
                }
                if ($ubicacion_destino_id == $ubicacion_origen_id) {
                    throw new Exception("La ubicación de origen y destino no pueden ser iguales.");
                }
            } elseif (in_array($tipo_movimiento, ['cambio_estatus', 'desincorporacion'])) {
                if (empty($estatus_destino_id)) {
                    throw new Exception("Debe seleccionar el nuevo estatus.");
                }
            }
            
            // Verificar qué columnas existen en la tabla movimientos
            $columnas_movimientos = [];
            $result_cols = $conn->query("SHOW COLUMNS FROM movimientos");
            while ($row = $result_cols->fetch_assoc()) {
                $columnas_movimientos[] = $row['Field'];
            }
            
            // Construir consulta de inserción dinámicamente
            $sql = "INSERT INTO movimientos (bien_id, tipo_movimiento, fecha_movimiento";
            $values = "?, ?, ?";
            $params = [$bien_id, $tipo_movimiento, $fecha_movimiento];
            $types = "iss";
            
            // Agregar columnas opcionales si existen
            if (in_array('responsable', $columnas_movimientos)) {
                $sql .= ", responsable";
                $values .= ", ?";
                $params[] = $responsable;
                $types .= "s";
            }
            
            if (in_array('motivo', $columnas_movimientos)) {
                $sql .= ", motivo";
                $values .= ", ?";
                $params[] = $motivo;
                $types .= "s";
            }
            
            if (in_array('observaciones', $columnas_movimientos)) {
                $sql .= ", observaciones";
                $values .= ", ?";
                $params[] = $observaciones;
                $types .= "s";
            }
            
            if (in_array('usuario_cedula', $columnas_movimientos)) {
                $sql .= ", usuario_cedula";
                $values .= ", ?";
                $params[] = $_SESSION['usuario']['cedula'];
                $types .= "s";
            }
            
            $sql .= ") VALUES ($values)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al registrar el movimiento: " . $stmt->error);
            }
            $movimiento_id = $conn->insert_id;
            $stmt->close();
            
            // Verificar qué columnas existen en la tabla bienes
            $columnas_bienes = [];
            $result_cols = $conn->query("SHOW COLUMNS FROM bienes");
            while ($row = $result_cols->fetch_assoc()) {
                $columnas_bienes[] = $row['Field'];
            }
            
            // Actualizar la ubicación del bien si es un traslado
            if ($tipo_movimiento === 'traslado' && !empty($ubicacion_destino_id) && in_array('ubicacion_id', $columnas_bienes)) {
                $stmt_update = $conn->prepare("UPDATE bienes SET ubicacion_id = ? WHERE id = ?");
                $stmt_update->bind_param("ii", $ubicacion_destino_id, $bien_id);
                $stmt_update->execute();
                $stmt_update->close();
            }
            
            // Actualizar el estatus del bien si es cambio de estatus o desincorporación
            if (in_array($tipo_movimiento, ['cambio_estatus', 'desincorporacion']) && !empty($estatus_destino_id) && in_array('estatus_id', $columnas_bienes)) {
                $stmt_update = $conn->prepare("UPDATE bienes SET estatus_id = ? WHERE id = ?");
                $stmt_update->bind_param("ii", $estatus_destino_id, $bien_id);
                $stmt_update->execute();
                $stmt_update->close();
            }
            
            $conn->commit();
            
            $tipo_movimiento_display = str_replace('_', ' ', strtoupper($tipo_movimiento));
            $mensaje = "Movimiento '$tipo_movimiento_display' registrado correctamente para el bien: $codigo_bien";
            $tipo_mensaje = "success";
            
            // Guardar datos para PDF
            $_SESSION['ultimo_movimiento'] = [
                'id' => $movimiento_id,
                'codigo_bien' => $codigo_bien,
                'tipo_movimiento' => $tipo_movimiento_display,
                'fecha_movimiento' => $fecha_movimiento,
                'responsable' => $responsable,
                'motivo' => $motivo,
                'observaciones' => $observaciones,
                'ubicacion_origen' => $bien_seleccionado['ubicacion_actual'] ?? 'N/A',
                'ubicacion_destino' => '',
                'estatus_origen' => $bien_seleccionado['estatus_actual'] ?? 'N/A',
                'estatus_destino' => ''
            ];
            
            // Limpiar selección
            $bien_seleccionado = null;
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Buscar bien si se proporciona código
if (isset($_GET['buscar']) && !empty($_GET['codigo_bien'])) {
    $codigo_buscar = trim($_GET['codigo_bien']);
    try {
        // Primero verificar si la tabla bienes existe
        $check_table = $conn->query("SHOW TABLES LIKE 'bienes'");
        if ($check_table->num_rows === 0) {
            throw new Exception("La tabla 'bienes' no existe en la base de datos.");
        }
        
        // Query simplificado sin subqueries para evitar errores si las tablas no existen
        $stmt = $conn->prepare("SELECT * FROM bienes WHERE codigo_bien_nacional = ?");
        $stmt->bind_param("s", $codigo_buscar);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $bien_seleccionado = $result->fetch_assoc();
            
            // Obtener nombres de tablas relacionadas si existen
            try {
                // Ubicación actual
                if (!empty($bien_seleccionado['ubicacion_id'])) {
                    $stmt_ubic = $conn->prepare("SELECT nombre FROM ubicaciones WHERE id = ?");
                    $stmt_ubic->bind_param("i", $bien_seleccionado['ubicacion_id']);
                    $stmt_ubic->execute();
                    $res_ubic = $stmt_ubic->get_result();
                    if ($res_ubic->num_rows > 0) {
                        $ubic = $res_ubic->fetch_assoc();
                        $bien_seleccionado['ubicacion_actual'] = $ubic['nombre'];
                    }
                    $stmt_ubic->close();
                }
                
                // Estatus actual
                if (!empty($bien_seleccionado['estatus_id'])) {
                    $stmt_est = $conn->prepare("SELECT nombre FROM estatus_bienes WHERE id = ?");
                    $stmt_est->bind_param("i", $bien_seleccionado['estatus_id']);
                    $stmt_est->execute();
                    $res_est = $stmt_est->get_result();
                    if ($res_est->num_rows > 0) {
                        $est = $res_est->fetch_assoc();
                        $bien_seleccionado['estatus_actual'] = $est['nombre'];
                    }
                    $stmt_est->close();
                }
                
                // Categoría
                if (!empty($bien_seleccionado['categoria_id'])) {
                    $stmt_cat = $conn->prepare("SELECT nombre FROM categorias WHERE id = ?");
                    $stmt_cat->bind_param("i", $bien_seleccionado['categoria_id']);
                    $stmt_cat->execute();
                    $res_cat = $stmt_cat->get_result();
                    if ($res_cat->num_rows > 0) {
                        $cat = $res_cat->fetch_assoc();
                        $bien_seleccionado['categoria'] = $cat['nombre'];
                    }
                    $stmt_cat->close();
                }
                
                // Dependencia
                if (!empty($bien_seleccionado['dependencia_id'])) {
                    $stmt_dep = $conn->prepare("SELECT nombre FROM dependencias WHERE id = ?");
                    $stmt_dep->bind_param("i", $bien_seleccionado['dependencia_id']);
                    $stmt_dep->execute();
                    $res_dep = $stmt_dep->get_result();
                    if ($res_dep->num_rows > 0) {
                        $dep = $res_dep->fetch_assoc();
                        $bien_seleccionado['dependencia'] = $dep['nombre'];
                    }
                    $stmt_dep->close();
                }
            } catch (Exception $e) {
                // Ignorar errores de tablas relacionadas
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        $mensaje = "Error al buscar el bien: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener datos para los dropdowns
$ubicaciones = [];
$dependencias = [];
$categorias = [];
$estatus = [];

try {
    function tablaExiste($conn, $nombre_tabla) {
        $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($nombre_tabla) . "'");
        return $result && $result->num_rows > 0;
    }
    
    // Ubicaciones (PNF, sedes, etc.)
    if (tablaExiste($conn, 'ubicaciones')) {
        $result_ubicaciones = $conn->query("SELECT id, nombre FROM ubicaciones WHERE activo = 1 ORDER BY nombre");
        if ($result_ubicaciones) {
            $ubicaciones = $result_ubicaciones->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // Dependencias
    if (tablaExiste($conn, 'dependencias')) {
        $result_dependencias = $conn->query("SELECT id, nombre FROM dependencias WHERE activo = 1 ORDER BY nombre");
        if ($result_dependencias) {
            $dependencias = $result_dependencias->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // Categorías
    if (tablaExiste($conn, 'categorias')) {
        $result_categorias = $conn->query("SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre");
        if ($result_categorias) {
            $categorias = $result_categorias->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // Estatus
    if (tablaExiste($conn, 'estatus_bienes')) {
        $result_estatus = $conn->query("SELECT id, nombre FROM estatus_bienes WHERE activo = 1 ORDER BY nombre");
        if ($result_estatus) {
            $estatus = $result_estatus->fetch_all(MYSQLI_ASSOC);
        }
    }
} catch (Exception $e) {
    // Si las tablas no existen
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Movimiento - Sistema INTI</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/estilos_sistema.css">
    <link rel="stylesheet" href="css/material-design-iconic-font.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1 style="font-weight:900; font-family:montserrat; color:#ff6600; font-size:40px; padding:20px; text-align:left; font-size:50px;">
            <i class="zmdi zmdi-swap"></i> 
            Registrar <span style="font-weight:700; color:black;">Movimiento</span>
        </h1>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $tipo_mensaje == 'error' ? 'danger' : ($tipo_mensaje == 'info' ? 'warning' : 'success'); ?>" style="margin: 20px;">
                <?= htmlspecialchars($mensaje); ?>
            </div>
            <?php if ($tipo_mensaje == 'success' && isset($_SESSION['ultimo_movimiento'])): ?>
                <div style="margin: 20px; text-align: center;">
                    <a href="pdf_movimiento.php" target="_blank" class="btn btn-primary" style="background-color: #ff6600; border-color: #ff6600;">
                        <i class="zmdi zmdi-download"></i> Descargar PDF del Movimiento
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Búsqueda de Bien -->
        <div class="section-container">
            <h4 class="section-title">Buscar Bien Nacional</h4>
            <form method="GET" action="" class="field-row" style="align-items: flex-end;">
                <div class="field-col" style="flex: 2;">
                    <label for="codigo_bien" class="field-label required">Código de Bien Nacional</label>
                    <input type="text" name="codigo_bien" id="codigo_bien" 
                           placeholder="Ej: BN-2026-0001" maxlength="50" class="form-control" 
                           value="<?= isset($_GET['codigo_bien']) ? htmlspecialchars($_GET['codigo_bien']) : ''; ?>" />
                </div>
                <div class="field-col" style="flex: 0.5;">
                    <button type="submit" name="buscar" value="1" class="btn btn-primary" style="width: 100%;">
                        <i class="zmdi zmdi-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>

        <!-- Información del Bien Encontrado -->
        <div class="container">
        <?php if ($bien_seleccionado): ?>
            <div style="background-color: #e8f5e9; border: 2px solid #4caf50; border-radius: 8px; padding: 15px; margin-top: 15px;">
                <h5 style="margin: 0 0 10px 0; color: #2e7d32;">
                    <i class="zmdi zmdi-check-circle"></i> Bien Encontrado
                </h5>
                <div class="field-row">
                    <div class="field-col">
                        <strong>Código:</strong> <?= htmlspecialchars($bien_seleccionado['codigo_bien_nacional']); ?>
                    </div>
                    <div class="field-col">
                        <strong>Descripción:</strong> <?= htmlspecialchars($bien_seleccionado['descripcion']); ?>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <strong>Marca:</strong> <?= htmlspecialchars($bien_seleccionado['marca'] ?: 'N/A'); ?>
                    </div>
                    <div class="field-col">
                        <strong>Modelo:</strong> <?= htmlspecialchars($bien_seleccionado['modelo'] ?: 'N/A'); ?>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <strong>Ubicación Actual:</strong> <?= htmlspecialchars($bien_seleccionado['ubicacion_actual'] ?? 'No asignada'); ?>
                    </div>
                    <div class="field-col">
                        <strong>Estatus Actual:</strong> <?= htmlspecialchars($bien_seleccionado['estatus_actual'] ?? 'No asignado'); ?>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <strong>Dependencia:</strong> <?= htmlspecialchars($bien_seleccionado['dependencia'] ?? 'N/A'); ?>
                    </div>
                    <div class="field-col">
                        <strong>Categoría:</strong> <?= htmlspecialchars($bien_seleccionado['categoria'] ?? 'N/A'); ?>
                    </div>
                </div>
            </div>
        <?php elseif (isset($_GET['buscar']) && !empty($_GET['codigo_bien'])): ?>
            <div style="background-color: #ffebee; border: 2px solid #f44336; border-radius: 8px; padding: 15px; margin-top: 15px;">
                <h5 style="margin: 0; color: #c62828;">
                    <i class="zmdi zmdi-error"></i> Bien no encontrado
                </h5>
                <p style="margin: 10px 0 0 0;">No se encontró ningún bien con el código: <?= htmlspecialchars($_GET['codigo_bien']); ?></p>
            </div>
        <?php endif; ?>
        </div>

        <!-- Formulario para Registrar Movimiento -->
        <?php if ($bien_seleccionado): ?>
        <form id="form-registrar-movimiento" method="POST" action="" class="section-container">
            <input type="hidden" name="accion" value="registrar_movimiento">
            <input type="hidden" name="codigo_bien" value="<?= htmlspecialchars($bien_seleccionado['codigo_bien_nacional']); ?>">
            <input type="hidden" name="ubicacion_origen_id" value="<?= $bien_seleccionado['ubicacion_id'] ?? ''; ?>">
            <input type="hidden" name="estatus_origen_id" value="<?= $bien_seleccionado['estatus_id'] ?? ''; ?>">
            
            <h4 class="section-title">Datos del Movimiento</h4>
            
            <!-- Tipo de Movimiento y Fecha -->
            <div class="field-row">
                <div class="field-col">
                    <label for="tipo_movimiento" class="field-label required">Tipo de Movimiento</label>
                    <select name="tipo_movimiento" id="tipo_movimiento" class="form-control" required onchange="toggleFields()">
                        <option value="">Seleccionar...</option>
                        <option value="traslado">Traslado</option>
                        <option value="prestamo">Préstamo</option>
                        <option value="cambio_estatus">Cambio de Estatus</option>
                        <option value="mantenimiento">Mantenimiento</option>
                        <option value="desincorporacion">Desincorporación</option>
                    </select>
                </div>
                <div class="field-col">
                    <label for="fecha_movimiento" class="field-label required">Fecha del Movimiento</label>
                    <input type="date" name="fecha_movimiento" id="fecha_movimiento" class="form-control" required />
                </div>
            </div>
            
            <!-- Ubicación de Destino (para traslado) -->
            <div class="field-row" id="ubicacion_destino_container" style="display: none;">
                <div class="field-col">
                    <label for="ubicacion_destino_id" class="field-label required">Ubicación de Destino</label>
                    <select name="ubicacion_destino_id" id="ubicacion_destino_id" class="form-control">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($ubicaciones as $ubic): ?>
                            <option value="<?= $ubic['id']; ?>"><?= htmlspecialchars($ubic['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Estatus de Destino (para cambio de estatus) -->
            <div class="field-row" id="estatus_destino_container" style="display: none;">
                <div class="field-col">
                    <label for="estatus_destino_id" class="field-label required">Nuevo Estatus</label>
                    <select name="estatus_destino_id" id="estatus_destino_id" class="form-control">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($estatus as $est): ?>
                            <option value="<?= $est['id']; ?>"><?= htmlspecialchars($est['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Responsable y Motivo -->
            <div class="field-row">
                <div class="field-col">
                    <label for="responsable" class="field-label">Responsable</label>
                    <input type="text" name="responsable" id="responsable" placeholder="Nombre del responsable" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="motivo" class="field-label">Motivo</label>
                    <input type="text" name="motivo" id="motivo" placeholder="Motivo del movimiento" class="form-control" />
                </div>
            </div>
            
            <!-- Observaciones -->
            <div class="field-row">
                <div class="field-col" style="flex: 100%;">
                    <label for="observaciones" class="field-label">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="3" placeholder="Observaciones adicionales..." class="form-control" style="width: 100%;"></textarea>
                </div>
            </div>
            
            <!-- Botones -->
            <div class="button-container">
                <button type="reset" class="btn btn-secondary">
                    <i class="zmdi zmdi-refresh"></i> Limpiar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="zmdi zmdi-save"></i> Registrar Movimiento
                </button>
            </div>
        </form>
        <?php endif; ?>
        
        <!-- Movimientos Recientes -->
        <?php if (!empty($movimientos_recientes)): ?>
        <div class="section-container">
            <h4 class="section-title">Movimientos Recientes</h4>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #ff6600; color: white;">
                            <th style="padding: 10px; text-align: left;">Fecha</th>
                            <th style="padding: 10px; text-align: left;">Código Bien</th>
                            <th style="padding: 10px; text-align: left;">Tipo</th>
                            <th style="padding: 10px; text-align: left;">Responsable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos_recientes as $mov): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px;"><?= htmlspecialchars($mov['fecha_movimiento']); ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($mov['codigo_bien_nacional']); ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($mov['tipo_movimiento']); ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($mov['responsable'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Información -->
        <div class="section-container" style="background-color: #fff3e0;">
            <h4 class="section-title" style="color: #ff6600;">
                <i class="zmdi zmdi-info-outline"></i> Información sobre Movimientos
            </h4>
            <ul style="margin: 0; padding-left: 20px; color: #333;">
                <li style="margin-bottom: 8px;"><strong>Traslado:</strong> Cambio de ubicación física del bien.</li>
                <li style="margin-bottom: 8px;"><strong>Préstamo:</strong> Salida temporal del bien de las instalaciones.</li>
                <li style="margin-bottom: 8px;"><strong>Cambio de Estatus:</strong> Modificación del estado del bien (activo, mantenimiento, etc.).</li>
                <li style="margin-bottom: 8px;"><strong>Mantenimiento:</strong> Envío a reparación o mantenimiento externo.</li>
                <li><strong>Desincorporación:</strong> Baja definitiva del bien del inventario.</li>
            </ul>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script src="js/jquery-3.1.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        function toggleFields() {
            const tipo = document.getElementById('tipo_movimiento').value;
            const ubicacionDestino = document.getElementById('ubicacion_destino_container');
            const estatusDestino = document.getElementById('estatus_destino_container');
            
            ubicacionDestino.style.display = 'none';
            estatusDestino.style.display = 'none';
            
            if (tipo === 'traslado') {
                ubicacionDestino.style.display = 'flex';
            } else if (tipo === 'cambio_estatus' || tipo === 'desincorporacion') {
                estatusDestino.style.display = 'flex';
            }
        }
        
        document.getElementById('form-registrar-movimiento')?.addEventListener('submit', function(e) {
            const tipo = document.getElementById('tipo_movimiento').value;
            const fecha = document.getElementById('fecha_movimiento').value;
            
            if (!tipo || !fecha) {
                e.preventDefault();
                alert('Por favor, complete todos los campos obligatorios.');
                return false;
            }
            
            return true;
        });
        
        // Establecer fecha actual por defecto
        document.addEventListener('DOMContentLoaded', function() {
            const fechaInput = document.getElementById('fecha_movimiento');
            if (fechaInput && !fechaInput.value) {
                const today = new Date().toISOString().split('T')[0];
                fechaInput.value = today;
                fechaInput.max = today;
            }
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