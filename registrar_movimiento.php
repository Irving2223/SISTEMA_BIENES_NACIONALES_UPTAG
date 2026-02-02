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
            
            // Validaciones según el tipo de movimiento
            if ($tipo_movimiento === 'traslado') {
                if (empty($ubicacion_destino_id)) {
                    throw new Exception("Para un traslado debe seleccionar la ubicación de destino.");
                }
                if ($ubicacion_destino_id == $ubicacion_origen_id) {
                    throw new Exception("La ubicación de origen y destino no pueden ser iguales.");
                }
            } elseif (in_array($tipo_movimiento, ['cambio_estatus', 'desincorporacion'])) {
                if (empty($estatus_destino_id)) {
                    throw new Exception("Debe seleccionar el nuevo estatus.");
                }
            }
            
            // Insertar el movimiento
            $stmt = $conn->prepare("INSERT INTO movimientos_bienes(bien_id, tipo_movimiento, ubicacion_origen_id, ubicacion_destino_id, estatus_origen_id, estatus_destino_id, fecha_movimiento, responsable, motivo, observaciones, usuario_cedula, fecha_registro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issiiisssss", 
                $bien_id,
                $tipo_movimiento,
                $ubicacion_origen_id,
                $ubicacion_destino_id,
                $estatus_origen_id,
                $estatus_destino_id,
                $fecha_movimiento,
                $responsable,
                $motivo,
                $observaciones,
                $_SESSION['usuario']['cedula']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error al registrar el movimiento: " . $stmt->error);
            }
            $movimiento_id = $conn->insert_id;
            $stmt->close();
            
            // Actualizar la ubicación del bien si es un traslado
            if ($tipo_movimiento === 'traslado' && !empty($ubicacion_destino_id)) {
                $stmt_update = $conn->prepare("UPDATE bienes SET ubicacion_id = ? WHERE id = ?");
                $stmt_update->bind_param("ii", $ubicacion_destino_id, $bien_id);
                $stmt_update->execute();
                $stmt_update->close();
            }
            
            // Actualizar el estatus del bien si es cambio de estatus o desincorporación
            if (in_array($tipo_movimiento, ['cambio_estatus', 'desincorporacion']) && !empty($estatus_destino_id)) {
                $stmt_update = $conn->prepare("UPDATE bienes SET estatus_id = ? WHERE id = ?");
                $stmt_update->bind_param("ii", $estatus_destino_id, $bien_id);
                $stmt_update->execute();
                $stmt_update->close();
            }
            
            // Si es desincorporación, registrar en tabla de desincorporados
            if ($tipo_movimiento === 'desincorporacion' && !empty($estatus_destino_id)) {
                // Verificar si existe tabla de desincorporados
                $stmt_check_table = $conn->query("SHOW TABLES LIKE 'bienes_desincorporados'");
                if ($stmt_check_table->num_rows > 0) {
                    $stmt_desinc = $conn->prepare("INSERT INTO bienes_desincorporados(bien_id, motivo, fecha_desincorporacion, usuario_cedula) VALUES (?, ?, ?, ?)");
                    $stmt_desinc->bind_param("isss", $bien_id, $motivo, $fecha_movimiento, $_SESSION['usuario']['cedula']);
                    $stmt_desinc->execute();
                    $stmt_desinc->close();
                }
            }
            
            // Registrar en auditoría
            $cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'SISTEMA';
            $stmt_auditoria = $conn->prepare("INSERT INTO auditoria(usuario_cedula, accion, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, 'Registro', 'movimientos_bienes', ?, ?, NOW())");
            $tipo_movimiento_display = str_replace('_', ' ', strtoupper($tipo_movimiento));
            $detalle = "Registro de Movimiento: $tipo_movimiento_display - Bien: $codigo_bien";
            $stmt_auditoria->bind_param("sss", $cedula_usuario, $movimiento_id, $detalle);
            $stmt_auditoria->execute();
            $stmt_auditoria->close();
            
            $conn->commit();
            
            $mensaje = "Movimiento '$tipo_movimiento_display' registrado correctamente para el bien: $codigo_bien";
            $tipo_mensaje = "success";
            
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

// Obtener datos para dropdowns
$ubicaciones = [];
$estatus = [];
$movimientos_recientes = [];

// Verificar si las tablas existen antes de consultar
function tablaExiste($conn, $nombre_tabla) {
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($nombre_tabla) . "'");
    return $result && $result->num_rows > 0;
}

// Ubicaciones
if (tablaExiste($conn, 'ubicaciones')) {
    $result_ubicaciones = $conn->query("SELECT id, nombre FROM ubicaciones WHERE activo = 1 ORDER BY nombre");
    if ($result_ubicaciones) {
        $ubicaciones = $result_ubicaciones->fetch_all(MYSQLI_ASSOC);
    }
}

// Estatus
if (tablaExiste($conn, 'estatus_bienes')) {
    $result_estatus = $conn->query("SELECT id, nombre FROM estatus_bienes WHERE activo = 1 ORDER BY nombre");
    if ($result_estatus) {
        $estatus = $result_estatus->fetch_all(MYSQLI_ASSOC);
    }
}

// Movimientos recientes
if (tablaExiste($conn, 'movimientos_bienes') && tablaExiste($conn, 'bienes')) {
    $result_movimientos = $conn->query("SELECT m.*, b.codigo_bien_nacional 
                                        FROM movimientos_bienes m 
                                        JOIN bienes b ON m.bien_id = b.id 
                                        ORDER BY m.fecha_registro DESC LIMIT 10");
    if ($result_movimientos) {
        $movimientos_recientes = $result_movimientos->fetch_all(MYSQLI_ASSOC);
    }
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
                <div class="field-col" style="flex: 0 0 auto;">
                    <button type="submit" name="buscar" value="1" class="btn btn-primary">
                        <i class="zmdi zmdi-search"></i> Buscar
                    </button>
                </div>
            </form>
            
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
                            <strong>Ubicación Actual:</strong> <?= htmlspecialchars($bien_seleccionado['ubicacion_actual'] ?: 'No asignada'); ?>
                        </div>
                        <div class="field-col">
                            <strong>Estatus Actual:</strong> <?= htmlspecialchars($bien_seleccionado['estatus_actual'] ?: 'No asignado'); ?>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-col">
                            <strong>Dependencia:</strong> <?= htmlspecialchars($bien_seleccionado['dependencia'] ?: 'N/A'); ?>
                        </div>
                        <div class="field-col">
                            <strong>Categoría:</strong> <?= htmlspecialchars($bien_seleccionado['categoria'] ?: 'N/A'); ?>
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
                    <select name="tipo_movimiento" id="tipo_movimiento" class="form-control" required onchange="mostrarCamposMovimiento()">
                        <option value="">Seleccione un tipo...</option>
                        <option value="traslado">Traslado de Ubicación</option>
                        <option value="cambio_estatus">Cambio de Estatus</option>
                        <option value="mantenimiento">Envío a Mantenimiento</option>
                        <option value="reparacion">Envío a Reparación</option>
                        <option value="desincorporacion">Desincorporación</option>
                        <option value="prestamo">Préstamo</option>
                        <option value="devolucion">Devolución</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="field-col">
                    <label for="fecha_movimiento" class="field-label required">Fecha del Movimiento</label>
                    <input type="date" name="fecha_movimiento" id="fecha_movimiento" class="form-control" required />
                </div>
            </div>
            
            <!-- Campos para Traslado -->
            <div id="campos_traslado" class="hidden">
                <h5 style="color: #ff6600; margin: 20px 0 15px 0;">Datos del Traslado</h5>
                <div class="field-row">
                    <div class="field-col">
                        <label for="ubicacion_destino_id" class="field-label required">Ubicación de Destino</label>
                        <select name="ubicacion_destino_id" id="ubicacion_destino_id" class="form-control">
                            <option value="">Seleccione la ubicación destino...</option>
                            <?php foreach ($ubicaciones as $ubicacion): ?>
                                <option value="<?= $ubicacion['id']; ?>"><?= htmlspecialchars($ubicacion['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($ubicaciones)): ?>
                            <small style="color: #999;">No hay ubicaciones registradas</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Campos para Cambio de Estatus -->
            <div id="campos_estatus" class="hidden">
                <h5 style="color: #ff6600; margin: 20px 0 15px 0;">Cambio de Estatus</h5>
                <div class="field-row">
                    <div class="field-col">
                        <label for="estatus_destino_id" class="field-label required">Nuevo Estatus</label>
                        <select name="estatus_destino_id" id="estatus_destino_id" class="form-control">
                            <option value="">Seleccione el nuevo estatus...</option>
                            <?php foreach ($estatus as $est): ?>
                                <option value="<?= $est['id']; ?>"><?= htmlspecialchars($est['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Responsable y Motivo -->
            <div class="field-row">
                <div class="field-col">
                    <label for="responsable" class="field-label">Responsable del Movimiento</label>
                    <input type="text" name="responsable" id="responsable" 
                           placeholder="Nombre de quien realiza/mueve el bien" 
                           maxlength="150" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="motivo" class="field-label">Motivo/Razón</label>
                    <input type="text" name="motivo" id="motivo" 
                           placeholder="Razón del movimiento" 
                           maxlength="255" class="form-control" />
                </div>
            </div>
            
            <!-- Observaciones -->
            <div class="field-row">
                <div class="field-col" style="flex: 100%;">
                    <label for="observaciones" class="field-label">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="3" 
                              placeholder="Detalles adicionales sobre el movimiento..." 
                              class="form-control" style="width: 100%;"></textarea>
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
                        <tr style="background-color: #f5f5f5;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Fecha</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Código Bien</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tipo</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Responsable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos_recientes as $mov): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px;"><?= date('d/m/Y', strtotime($mov['fecha_movimiento'])); ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($mov['codigo_bien_nacional']); ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($mov['tipo_movimiento']))); ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($mov['responsable'] ?: 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Información adicional -->
        <div class="section-container" style="background-color: #fff3e0;">
            <h4 class="section-title" style="color: #ff6600;">
                <i class="zmdi zmdi-info-outline"></i> Información sobre Movimientos
            </h4>
            <ul style="margin: 0; padding-left: 20px; color: #333;">
                <li style="margin-bottom: 8px;"><strong>Traslado:</strong> Mover un bien de una ubicación a otra dentro de la universidad.</li>
                <li style="margin-bottom: 8px;"><strong>Cambio de Estatus:</strong> Actualizar el estado del bien (ej: activo, dañado, en reparación).</li>
                <li style="margin-bottom: 8px;"><strong>Mantenimiento/Reparación:</strong> Enviar el bien a servicio técnico.</li>
                <li style="margin-bottom: 8px;"><strong>Desincorporación:</strong> Dar de baja un bien que ya no es útil o está dañado.</li>
                <li style="margin-bottom: 8px;"><strong>Préstamo/Devolución:</strong> Registrar bienes prestados o devueltos.</li>
                <li><strong>Historial:</strong> Todos los movimientos quedan registrados para trazabilidad.</li>
            </ul>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script src="js/jquery-3.1.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        // Establecer fecha actual por defecto
        document.addEventListener('DOMContentLoaded', function() {
            const fechaInput = document.getElementById('fecha_movimiento');
            if (fechaInput && !fechaInput.value) {
                const today = new Date().toISOString().split('T')[0];
                fechaInput.value = today;
                fechaInput.max = today;
            }
        });
        
        // Mostrar campos según tipo de movimiento
        function mostrarCamposMovimiento() {
            const tipo = document.getElementById('tipo_movimiento').value;
            const camposTraslado = document.getElementById('campos_traslado');
            const camposEstatus = document.getElementById('campos_estatus');
            
            // Ocultar todos primero
            camposTraslado.classList.add('hidden');
            camposEstatus.classList.add('hidden');
            
            // Mostrar según tipo
            if (tipo === 'traslado') {
                camposTraslado.classList.remove('hidden');
            } else if (tipo === 'cambio_estatus' || tipo === 'desincorporacion') {
                camposEstatus.classList.remove('hidden');
            }
        }
        
        // Validación del formulario
        document.getElementById('form-registrar-movimiento')?.addEventListener('submit', function(e) {
            const tipoMovimiento = document.getElementById('tipo_movimiento').value;
            const fecha = document.getElementById('fecha_movimiento').value;
            
            if (!tipoMovimiento) {
                e.preventDefault();
                alert('Por favor, seleccione el tipo de movimiento.');
                document.getElementById('tipo_movimiento').focus();
                return false;
            }
            
            if (!fecha) {
                e.preventDefault();
                alert('Por favor, seleccione la fecha del movimiento.');
                document.getElementById('fecha_movimiento').focus();
                return false;
            }
            
            return true;
        });
    </script>
    
    <style>
        .hidden {
            display: none;
        }
    </style>
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
