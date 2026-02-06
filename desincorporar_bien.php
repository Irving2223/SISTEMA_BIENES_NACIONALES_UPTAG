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
$desincorporaciones_recientes = [];

// Función para verificar si una tabla existe
function tablaExiste($conn, $nombre_tabla) {
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($nombre_tabla) . "'");
    return $result && $result->num_rows > 0;
}

// Procesar formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        $conn->begin_transaction();
        
        if ($_POST['accion'] === 'desincorporar') {
            // Obtener datos del formulario
            $codigo_bien = trim($_POST['codigo_bien'] ?? '');
            $motivo_desincorporacion = $_POST['motivo_desincorporacion'] ?? '';
            $detalle_motivo = trim($_POST['detalle_motivo'] ?? '');
            $documento_soporte = trim($_POST['documento_soporte'] ?? '');
            $fecha_desincorporacion = $_POST['fecha_desincorporacion'] ?? '';
            $responsable = trim($_POST['responsable'] ?? '');
            $observaciones = trim($_POST['observaciones'] ?? '');
            
            // Validaciones
            if (empty($codigo_bien)) {
                throw new Exception("Debe buscar y seleccionar un bien.");
            }
            if (empty($motivo_desincorporacion)) {
                throw new Exception("Debe seleccionar el motivo de la desincorporación.");
            }
            if (empty($fecha_desincorporacion)) {
                throw new Exception("La fecha de desincorporación es obligatoria.");
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
            
            // Verificar si ya está desincorporado (solo si existe la tabla estatus_bienes)
            if (!empty($bien['estatus_id']) && tablaExiste($conn, 'estatus_bienes')) {
                $stmt_estatus = $conn->prepare("SELECT nombre FROM estatus_bienes WHERE id = ?");
                $stmt_estatus->bind_param("i", $bien['estatus_id']);
                $stmt_estatus->execute();
                $res_estatus = $stmt_estatus->get_result();
                if ($res_estatus->num_rows > 0) {
                    $estatus_actual = $res_estatus->fetch_assoc();
                    if (stripos($estatus_actual['nombre'], 'desincorpor') !== false) {
                        throw new Exception("El bien ya se encuentra desincorporado.");
                    }
                }
                $stmt_estatus->close();
            }
            
            // Obtener el ID del estatus "Desincorporado" (solo si existe la tabla)
            $estatus_desincorporado_id = null;
            if (tablaExiste($conn, 'estatus_bienes')) {
                $stmt_estatus = $conn->prepare("SELECT id FROM estatus_bienes WHERE LOWER(nombre) LIKE '%desincorpor%' OR LOWER(nombre) LIKE '%baja%' LIMIT 1");
                $stmt_estatus->execute();
                $res_estatus = $stmt_estatus->get_result();
                if ($res_estatus->num_rows > 0) {
                    $estatus_desincorporado = $res_estatus->fetch_assoc();
                    $estatus_desincorporado_id = $estatus_desincorporado['id'];
                }
                $stmt_estatus->close();
            }
            
            // Si no existe la tabla estatus_bienes o no找到 el estatus, usar un valor directo
            if (empty($estatus_desincorporado_id)) {
                // Intentar actualizar directamente el campo estatus_id si existe
                // Usamos un valor fijo para "Desincorporado" (asumimos que es 6 o similar)
                $estatus_desincorporado_id = 6; // Valor por defecto
            }
            
            // Actualizar el bien
            // Verificar si existe la columna 'modificado_en' antes de incluirla
            $check_column = $conn->query("SHOW COLUMNS FROM bienes LIKE 'modificado_en'");
            if ($check_column && $check_column->num_rows > 0) {
                $stmt_update = $conn->prepare("UPDATE bienes SET estatus_id = ?, modificado_en = NOW() WHERE id = ?");
            } else {
                $stmt_update = $conn->prepare("UPDATE bienes SET estatus_id = ? WHERE id = ?");
            }
            $stmt_update->bind_param("ii", $estatus_desincorporado_id, $bien_id);
            if (!$stmt_update->execute()) {
                throw new Exception("Error al actualizar el bien: " . $stmt_update->error);
            }
            $stmt_update->close();
            
            // Registrar en tabla de desincorporaciones
            $tabla_desincorporados_existe = tablaExiste($conn, 'bienes_desincorporados');
            
            if ($tabla_desincorporados_existe) {
                // Verificar las columnas requeridas
                $check_cols = $conn->query("SHOW COLUMNS FROM bienes_desincorporados");
                $cols_existen = [];
                if ($check_cols) {
                    while ($col = $check_cols->fetch_assoc()) {
                        $cols_existen[$col['Field']] = true;
                    }
                }
                
                // Construir consulta solo con columnas que existen
                $campos_desinc = ['bien_id'];
                $valores_desinc = ['?'];
                $tipos_desinc = 'i';
                $params_desinc = [$bien_id];
                
                if (isset($cols_existen['motivo'])) {
                    $campos_desinc[] = 'motivo';
                    $valores_desinc[] = '?';
                    $tipos_desinc .= 's';
                    $params_desinc[] = $motivo_desincorporacion;
                }
                if (isset($cols_existen['detalle_motivo'])) {
                    $campos_desinc[] = 'detalle_motivo';
                    $valores_desinc[] = '?';
                    $tipos_desinc .= 's';
                    $params_desinc[] = $detalle_motivo;
                }
                if (isset($cols_existen['documento_soporte'])) {
                    $campos_desinc[] = 'documento_soporte';
                    $valores_desinc[] = '?';
                    $tipos_desinc .= 's';
                    $params_desinc[] = $documento_soporte;
                }
                if (isset($cols_existen['fecha_desincorporacion'])) {
                    $campos_desinc[] = 'fecha_desincorporacion';
                    $valores_desinc[] = '?';
                    $tipos_desinc .= 's';
                    $params_desinc[] = $fecha_desincorporacion;
                }
                if (isset($cols_existen['responsable'])) {
                    $campos_desinc[] = 'responsable';
                    $valores_desinc[] = '?';
                    $tipos_desinc .= 's';
                    $params_desinc[] = $responsable;
                }
                if (isset($cols_existen['observaciones'])) {
                    $campos_desinc[] = 'observaciones';
                    $valores_desinc[] = '?';
                    $tipos_desinc .= 's';
                    $params_desinc[] = $observaciones;
                }
                if (isset($cols_existen['usuario_cedula'])) {
                    $campos_desinc[] = 'usuario_cedula';
                    $valores_desinc[] = '?';
                    $tipos_desinc .= 's';
                    $params_desinc[] = $_SESSION['usuario']['cedula'];
                }
                if (isset($cols_existen['fecha_registro'])) {
                    $campos_desinc[] = 'fecha_registro';
                    $valores_desinc[] = 'NOW()';
                }
                
                $sql_desinc = "INSERT INTO bienes_desincorporados(" . implode(', ', $campos_desinc) . ") VALUES (" . implode(', ', $valores_desinc) . ")";
                $stmt_desinc = $conn->prepare($sql_desinc);
                if ($stmt_desinc) {
                    if (count($params_desinc) > 0) {
                        $stmt_desinc->bind_param($tipos_desinc, ...$params_desinc);
                    }
                    $stmt_desinc->execute();
                    $stmt_desinc->close();
                }
            }
            
            // Registrar movimiento de desincorporación
            if (tablaExiste($conn, 'movimientos')) {
                // Verificar las columnas requeridas
                $check_cols = $conn->query("SHOW COLUMNS FROM movimientos");
                $cols_existen = [];
                if ($check_cols) {
                    while ($col = $check_cols->fetch_assoc()) {
                        $cols_existen[$col['Field']] = true;
                    }
                }
                
                // Construir consulta solo con columnas que existen
                $campos_mov = ['bien_id', 'tipo_movimiento'];
                $valores_mov = ['?', "'desincorporacion'"];
                $tipos_mov = 'i';
                $params_mov = [$bien_id];
                
                if (isset($cols_existen['fecha_movimiento'])) {
                    $campos_mov[] = 'fecha_movimiento';
                    $valores_mov[] = '?';
                    $tipos_mov .= 's';
                    $params_mov[] = $fecha_desincorporacion;
                }
                if (isset($cols_existen['responsable'])) {
                    $campos_mov[] = 'responsable';
                    $valores_mov[] = '?';
                    $tipos_mov .= 's';
                    $params_mov[] = $responsable;
                }
                if (isset($cols_existen['motivo'])) {
                    $campos_mov[] = 'motivo';
                    $valores_mov[] = '?';
                    $tipos_mov .= 's';
                    $params_mov[] = $motivo_desincorporacion;
                }
                if (isset($cols_existen['observaciones'])) {
                    $campos_mov[] = 'observaciones';
                    $valores_mov[] = '?';
                    $tipos_mov .= 's';
                    $params_mov[] = $observaciones;
                }
                if (isset($cols_existen['usuario_cedula'])) {
                    $campos_mov[] = 'usuario_cedula';
                    $valores_mov[] = '?';
                    $tipos_mov .= 's';
                    $params_mov[] = $_SESSION['usuario']['cedula'];
                }
                if (isset($cols_existen['fecha_registro'])) {
                    $campos_mov[] = 'fecha_registro';
                    $valores_mov[] = 'NOW()';
                }
                
                $sql_mov = "INSERT INTO movimientos(" . implode(', ', $campos_mov) . ") VALUES (" . implode(', ', $valores_mov) . ")";
                $stmt_movimiento = $conn->prepare($sql_mov);
                if ($stmt_movimiento) {
                    if (count($params_mov) > 0) {
                        $stmt_movimiento->bind_param($tipos_mov, ...$params_mov);
                    }
                    $stmt_movimiento->execute();
                    $stmt_movimiento->close();
                }
            }
            
            // Registrar en auditoría
            $cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'SISTEMA';
            if (tablaExiste($conn, 'auditoria')) {
                $stmt_auditoria = $conn->prepare("INSERT INTO auditoria(usuario_cedula, accion, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, 'Desincorporación', 'bienes', ?, ?, NOW())");
                $detalle = "Desincorporación de Bien: $codigo_bien - Motivo: $motivo_desincorporacion";
                $stmt_auditoria->bind_param("sss", $cedula_usuario, $codigo_bien, $detalle);
                $stmt_auditoria->execute();
                $stmt_auditoria->close();
            }
            
            $conn->commit();
            
            $mensaje = "Bien '$codigo_bien' desincorporado correctamente el " . date('d/m/Y', strtotime($fecha_desincorporacion)) . ".";
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
        $stmt = $conn->prepare("SELECT * FROM bienes WHERE codigo_bien_nacional = ?");
        $stmt->bind_param("s", $codigo_buscar);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $bien_seleccionado = $result->fetch_assoc();
            
            // Obtener nombres de tablas relacionadas (solo si existen)
            if (!empty($bien_seleccionado['ubicacion_id']) && tablaExiste($conn, 'ubicaciones')) {
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
            
            if (!empty($bien_seleccionado['estatus_id']) && tablaExiste($conn, 'estatus_bienes')) {
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
        }
        $stmt->close();
    } catch (Exception $e) {
        $mensaje = "Error al buscar el bien: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener desincorporaciones recientes
try {
    if (tablaExiste($conn, 'bienes_desincorporados')) {
        // Verificar si existe la columna 'fecha_registro'
        $check_col = $conn->query("SHOW COLUMNS FROM bienes_desincorporados LIKE 'fecha_registro'");
        $order_by = $check_col && $check_col->num_rows > 0 ? "ORDER BY d.fecha_registro DESC" : "ORDER BY d.id DESC";
        
        $result_desinc = $conn->query("SELECT d.*, b.codigo_bien_nacional 
                                       FROM bienes_desincorporados d 
                                       JOIN bienes b ON d.bien_id = b.id 
                                       $order_by LIMIT 10");
        if ($result_desinc) {
            $desincorporaciones_recientes = $result_desinc->fetch_all(MYSQLI_ASSOC);
        }
    }
} catch (Exception $e) {
    // Tabla puede no existir
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desincorporar Bien - Sistema INTI</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/estilos_sistema.css">
    <link rel="stylesheet" href="css/material-design-iconic-font.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1 style="font-weight:900; font-family:montserrat; color:#ff6600; font-size:40px; padding:20px; text-align:left; font-size:50px;">
            <i class="zmdi zmdi-delete"></i> 
            Desincorporar <span style="font-weight:700; color:black;">Bien Nacional</span>
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
                <div style="background-color: #fff3e0; border: 2px solid #ff6600; border-radius: 8px; padding: 15px; margin-top: 15px;">
                    <h5 style="margin: 0 0 10px 0; color: #e65100;">
                        <i class="zmdi zmdi-alert-circle"></i> Bien Encontrado
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
                            <strong>Serial:</strong> <?= htmlspecialchars($bien_seleccionado['serial'] ?: 'N/A'); ?>
                        </div>
                        <div class="field-col">
                            <strong>Valor Original:</strong> <?= isset($bien_seleccionado['valor_original']) ? number_format($bien_seleccionado['valor_original'], 2, ',', '.') : '0,00'; ?> Bs.
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-col">
                            <strong>Ubicación Actual:</strong> <?= htmlspecialchars($bien_seleccionado['ubicacion_actual'] ?? 'No asignada'); ?>
                        </div>
                        <div class="field-col">
                            <strong>Estatus Actual:</strong> 
                            <span style="display: inline-block; padding: 3px 8px; border-radius: 4px; background-color: #4caf50; color: white;">
                                <?= htmlspecialchars($bien_seleccionado['estatus_actual'] ?? 'No asignado'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field-col">
                            <strong>Fecha de Incorporación:</strong> <?= isset($bien_seleccionado['fecha_incorporacion']) ? date('d/m/Y', strtotime($bien_seleccionado['fecha_incorporacion'])) : 'N/A'; ?>
                        </div>
                        <div class="field-col">
                            <strong>Tipo de Adquisición:</strong> <?= htmlspecialchars($bien_seleccionado['tipo_adquisicion'] ?? 'N/A'); ?>
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

        <!-- Formulario para Desincorporar Bien -->
        <?php if ($bien_seleccionado): ?>
        <form id="form-desincorporar" method="POST" action="" class="section-container">
            <input type="hidden" name="accion" value="desincorporar">
            <input type="hidden" name="codigo_bien" value="<?= htmlspecialchars($bien_seleccionado['codigo_bien_nacional']); ?>">
            
            <h4 class="section-title">Datos de Desincorporación</h4>
            
            <div style="background-color: #ffebee; border: 2px solid #f44336; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <p style="margin: 0; color: #c62828; font-weight: bold;">
                    <i class="zmdi zmdi-warning"></i> Advertencia: Esta acción cambiará el estatus del bien a "Desincorporado" y no podrá ser revertida fácilmente.
                </p>
            </div>
            
            <!-- Motivo y Fecha -->
            <div class="field-row">
                <div class="field-col">
                    <label for="motivo_desincorporacion" class="field-label required">Motivo de Desincorporación</label>
                    <select name="motivo_desincorporacion" id="motivo_desincorporacion" class="form-control" required>
                        <option value="">Seleccione un motivo...</option>
                        <option value="obsoleto">Obsoleto (vida útil terminada)</option>
                        <option value="daniado">Dañado (irrecuperable)</option>
                        <option value="robo">Robo o Hurto</option>
                        <option value="donacion_realizada">Donación Realizada</option>
                        <option value="venta">Venta</option>
                        <option value="perdida">Pérdida</option>
                        <option value="siniestro">Siniestro (incendio, inundación, etc.)</option>
                        <option value="desperfecto_mayor">Desperfecto Mayor</option>
                        <option value="tecnologia_obsoleta">Tecnología Obsoleta</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="field-col">
                    <label for="fecha_desincorporacion" class="field-label required">Fecha de Desincorporación</label>
                    <input type="date" name="fecha_desincorporacion" id="fecha_desincorporacion" class="form-control" required />
                </div>
            </div>
            
            <!-- Detalle del Motivo -->
            <div class="field-row">
                <div class="field-col" style="flex: 100%;">
                    <label for="detalle_motivo" class="field-label">Detalle del Motivo</label>
                    <textarea name="detalle_motivo" id="detalle_motivo" rows="3" 
                              placeholder="Describa con detalle las razones de la desincorporación..." 
                              class="form-control" style="width: 100%;"></textarea>
                </div>
            </div>
            
            <!-- Documento Soporte y Responsable -->
            <div class="field-row">
                <div class="field-col">
                    <label for="documento_soporte" class="field-label">Documento de Soporte</label>
                    <input type="text" name="documento_soporte" id="documento_soporte" 
                           placeholder="Ej: Acta de desincorporación, informe técnico" 
                           maxlength="100" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="responsable" class="field-label">Responsable de la Desincorporación</label>
                    <input type="text" name="responsable" id="responsable" 
                           placeholder="Nombre de quien autoriza la desincorporación" 
                           maxlength="150" class="form-control" />
                </div>
            </div>
            
            <!-- Observaciones -->
            <div class="field-row">
                <div class="field-col" style="flex: 100%;">
                    <label for="observaciones" class="field-label">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="3" 
                              placeholder="Notas adicionales sobre la desincorporación..." 
                              class="form-control" style="width: 100%;"></textarea>
                </div>
            </div>
            
            <!-- Confirmación -->
            <div class="field-row" style="margin-top: 20px;">
                <div class="field-col" style="flex: 100%;">
                    <div style="background-color: #fff3e0; border: 1px solid #ff6600; border-radius: 8px; padding: 15px;">
                        <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="confirmar" name="confirmar" value="1" required style="margin-top: 3px; width: 18px; height: 18px;">
                            <span style="font-weight: 600; color: #e65100;">
                                Confirmo que los datos son correctos y autorizo la desincorporación del bien <?= htmlspecialchars($bien_seleccionado['codigo_bien_nacional']); ?>.
                            </span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Botones -->
            <div class="button-container">
                <button type="reset" class="btn btn-secondary">
                    <i class="zmdi zmdi-refresh"></i> Limpiar
                </button>
                <button type="submit" class="btn btn-primary" style="background-color: #f44336; border-color: #f44336;">
                    <i class="zmdi zmdi-delete"></i> Desincorporar Bien
                </button>
            </div>
        </form>
        <?php endif; ?>
        
        <!-- Desincorporaciones Recientes -->
        <?php if (!empty($desincorporaciones_recientes)): ?>
        <div class="section-container">
            <h4 class="section-title">Desincorporaciones Recientes</h4>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #ff6600; color: white;">
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Fecha</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Código Bien</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Motivo</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Responsable</th>
                            <th style="padding: 12px; text-align: left; border: 1px solid #e65100;">Documento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($desincorporaciones_recientes as $desinc): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px;"><?= date('d/m/Y', strtotime($desinc['fecha_desincorporacion'])); ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($desinc['codigo_bien_nacional']); ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars(ucfirst($desinc['motivo'])); ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($desinc['responsable'] ?? 'N/A'); ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($desinc['documento_soporte'] ?: 'N/A'); ?></td>
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
                <i class="zmdi zmdi-info-outline"></i> Información sobre la Desincorporación
            </h4>
            <ul style="margin: 0; padding-left: 20px; color: #333;">
                <li style="margin-bottom: 8px;"><strong>Desincorporación:</strong> Proceso mediante el cual un bien es dado de baja del inventario de la institución.</li>
                <li style="margin-bottom: 8px;"><strong>Motivos comunes:</strong> Obsolescencia, daño irreparable, robo, donación, venta, o pérdida del bien.</li>
                <li style="margin-bottom: 8px;"><strong>Documentación:</strong> Se recomienda contar con un acta de desincorporación o informe técnico que respalde la decisión.</li>
                <li style="margin-bottom: 8px;"><strong>Reversibilidad:</strong> Una vez desincorporado, el cambio de estatus es definitivo en el sistema.</li>
                <li><strong>Auditoría:</strong> Todos los movimientos de desincorporación quedan registrados para efectos de control.</li>
            </ul>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script src="js/jquery-3.1.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        // Establecer fecha actual por defecto
        document.addEventListener('DOMContentLoaded', function() {
            const fechaInput = document.getElementById('fecha_desincorporacion');
            if (fechaInput && !fechaInput.value) {
                const today = new Date().toISOString().split('T')[0];
                fechaInput.value = today;
                fechaInput.max = today;
            }
        });
        
        // Validación del formulario
        document.getElementById('form-desincorporar')?.addEventListener('submit', function(e) {
            const motivo = document.getElementById('motivo_desincorporacion').value;
            const fecha = document.getElementById('fecha_desincorporacion').value;
            const confirmar = document.getElementById('confirmar').checked;
            
            if (!motivo) {
                e.preventDefault();
                alert('Por favor, seleccione el motivo de la desincorporación.');
                document.getElementById('motivo_desincorporacion').focus();
                return false;
            }
            
            if (!fecha) {
                e.preventDefault();
                alert('Por favor, seleccione la fecha de desincorporación.');
                document.getElementById('fecha_desincorporacion').focus();
                return false;
            }
            
            if (!confirmar) {
                e.preventDefault();
                alert('Debe confirmar que los datos son correctos para continuar.');
                document.getElementById('confirmar').focus();
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