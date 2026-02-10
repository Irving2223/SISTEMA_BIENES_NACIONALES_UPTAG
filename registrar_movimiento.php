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
            $bien_id = (int)($_POST['bien_id'] ?? 0);
            $tipo_movimiento = $_POST['tipo_movimiento'] ?? '';
            $ubicacion_origen_id = (int)($_POST['ubicacion_origen_id'] ?? 0);
            $ubicacion_destino_id = (int)($_POST['ubicacion_destino_id'] ?? 0);
            $responsable_origen_id = (int)($_POST['responsable_origen_id'] ?? 0);
            $responsable_destino_id = (int)($_POST['responsable_destino_id'] ?? 0);
            $fecha_movimiento = $_POST['fecha_movimiento'] ?? '';
            $razon = trim($_POST['razon'] ?? '');
            $numero_documento = trim($_POST['numero_documento'] ?? '');
            $observaciones = trim($_POST['observaciones'] ?? '');
            
            // Validaciones
            if (empty($codigo_bien) && $bien_id <= 0) {
                throw new Exception("Debe buscar y seleccionar un bien.");
            }
            if (empty($tipo_movimiento)) {
                throw new Exception("Debe seleccionar el tipo de movimiento.");
            }
            if (empty($fecha_movimiento)) {
                throw new Exception("La fecha del movimiento es obligatoria.");
            }
            
            // Obtener bien_id si solo tenemos el código
            if ($bien_id <= 0) {
                $stmt_check = $conn->prepare("SELECT id FROM bienes WHERE codigo_bien_nacional = ?");
                $stmt_check->bind_param("s", $codigo_bien);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                
                if ($result_check->num_rows === 0) {
                    throw new Exception("No se encontró ningún bien con el código: $codigo_bien");
                }
                
                $bien_data = $result_check->fetch_assoc();
                $bien_id = $bien_data['id'];
                $stmt_check->close();
            }
            
            // Verificar qué columnas existen en la tabla movimientos
            $columnas_movimientos = [];
            $result_cols = $conn->query("SHOW COLUMNS FROM movimientos");
            while ($row = $result_cols->fetch_assoc()) {
                $columnas_movimientos[] = $row['Field'];
            }
            
            // Construir consulta de inserción con todos los campos
            $razon_final = !empty($razon) ? $razon : 'Movimiento registrado sin razón específica';
            
            // Verificar si el usuario existe en la tabla usuarios
            $usuario_registro = $_SESSION['usuario']['cedula'] ?? $_SESSION['usuario']['id'] ?? null;
            if ($usuario_registro) {
                $check_usuario = $conn->prepare("SELECT cedula FROM usuarios WHERE cedula = ?");
                $check_usuario->bind_param("s", $usuario_registro);
                $check_usuario->execute();
                $res_usuario = $check_usuario->get_result();
                if ($res_usuario->num_rows === 0) {
                    $usuario_registro = null; // Si no existe, usar NULL
                }
                $check_usuario->close();
            }
            
            $sql = "INSERT INTO movimientos (bien_id, tipo_movimiento, ubicacion_origen_id, ubicacion_destino_id, responsable_origen_id, responsable_destino_id, fecha_movimiento, razon, numero_documento, observaciones, usuario_registro, fecha_creacion";
            $values = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()";
            $params = [
                $bien_id,
                $tipo_movimiento,
                $ubicacion_origen_id > 0 ? $ubicacion_origen_id : null,
                $ubicacion_destino_id > 0 ? $ubicacion_destino_id : null,
                $responsable_origen_id > 0 ? $responsable_origen_id : null,
                $responsable_destino_id > 0 ? $responsable_destino_id : null,
                $fecha_movimiento,
                $razon_final,
                !empty($numero_documento) ? $numero_documento : null,
                !empty($observaciones) ? $observaciones : null,
                $usuario_registro
            ];
            $types = "iiiiissssss";
            
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
            if ($tipo_movimiento === 'traslado' && $ubicacion_destino_id > 0 && in_array('ubicacion_id', $columnas_bienes)) {
                $stmt_update = $conn->prepare("UPDATE bienes SET ubicacion_id = ? WHERE id = ?");
                $stmt_update->bind_param("ii", $ubicacion_destino_id, $bien_id);
                $stmt_update->execute();
                $stmt_update->close();
            }
            
            // Actualizar el estatus del bien si es cambio de estatus o desincorporación
            if (in_array($tipo_movimiento, ['cambio_estatus', 'desincorporacion'])) {
                $nuevo_estatus = 0;
                switch ($tipo_movimiento) {
                    case 'desincorporacion':
                        $nuevo_estatus = 4;
                        break;
                    case 'cambio_estatus':
                        $nuevo_estatus = (int)($_POST['estatus_destino_id'] ?? 0);
                        break;
                }
                if ($nuevo_estatus > 0 && in_array('estatus_id', $columnas_bienes)) {
                    $stmt_update = $conn->prepare("UPDATE bienes SET estatus_id = ? WHERE id = ?");
                    $stmt_update->bind_param("ii", $nuevo_estatus, $bien_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                }
            }
            
            $conn->commit();
            
            $tipo_movimiento_display = str_replace('_', ' ', strtoupper($tipo_movimiento));
            $mensaje = "Movimiento '$tipo_movimiento_display' registrado correctamente. ID: $movimiento_id";
            $tipo_mensaje = "success";
            
            // Guardar datos para PDF
            $_SESSION['ultimo_movimiento'] = [
                'id' => $movimiento_id,
                'codigo_bien' => $codigo_bien,
                'tipo_movimiento' => $tipo_movimiento_display,
                'fecha_movimiento' => $fecha_movimiento,
                'ubicacion_origen_id' => $ubicacion_origen_id,
                'ubicacion_destino_id' => $ubicacion_destino_id,
                'responsable_origen_id' => $responsable_origen_id,
                'responsable_destino_id' => $responsable_destino_id,
                'razon' => $razon,
                'numero_documento' => $numero_documento,
                'observaciones' => $observaciones
            ];
            
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
        $check_table = $conn->query("SHOW TABLES LIKE 'bienes'");
        if ($check_table->num_rows === 0) {
            throw new Exception("La tabla 'bienes' no existe en la base de datos.");
        }
        
        $stmt = $conn->prepare("SELECT * FROM bienes WHERE codigo_bien_nacional = ?");
        $stmt->bind_param("s", $codigo_buscar);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $bien_seleccionado = $result->fetch_assoc();
            
            try {
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
                
                if (!empty($bien_seleccionado['estatus_id'])) {
                    $stmt_est = $conn->prepare("SELECT nombre FROM estatus WHERE id = ?");
                    $stmt_est->bind_param("i", $bien_seleccionado['estatus_id']);
                    $stmt_est->execute();
                    $res_est = $stmt_est->get_result();
                    if ($res_est->num_rows > 0) {
                        $est = $res_est->fetch_assoc();
                        $bien_seleccionado['estatus_actual'] = $est['nombre'];
                    }
                    $stmt_est->close();
                }
                
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
            } catch (Exception $e) {
                // Ignorar errores
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
$categorias = [];
$estatus = [];
$responsables = [];

try {
    function tablaExiste($conn, $nombre_tabla) {
        $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($nombre_tabla) . "'");
        return $result && $result->num_rows > 0;
    }
    
    if (tablaExiste($conn, 'ubicaciones')) {
        $result_ubicaciones = $conn->query("SELECT id, nombre, descripcion FROM ubicaciones WHERE activo = 1 ORDER BY nombre");
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
    
    if (tablaExiste($conn, 'estatus')) {
        $result_estatus = $conn->query("SELECT id, nombre FROM estatus WHERE activo = 1 ORDER BY nombre");
        if ($result_estatus) {
            $estatus = $result_estatus->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    if (tablaExiste($conn, 'responsables')) {
        $result_resp = $conn->query("SELECT id, nombre, cedula FROM responsables WHERE activo = 1 ORDER BY nombre");
        if ($result_resp) {
            $responsables = $result_resp->fetch_all(MYSQLI_ASSOC);
        }
    } elseif (tablaExiste($conn, 'personas')) {
        $result_resp = $conn->query("SELECT id, nombre, cedula FROM personas ORDER BY nombre");
        if ($result_resp) {
            $responsables = $result_resp->fetch_all(MYSQLI_ASSOC);
        }
    }
} catch (Exception $e) {}
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
    <link href="assets/img/LOGO INTI.png" rel="icon">
    
    <style>
        .form-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .form-section-title {
            font-weight: 800;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-section-title i { color: #ff6600; }
        .required-field::after { content: " *"; color: red; }
        .form-control:focus { border-color: #ff6600; box-shadow: 0 0 0 3px rgba(255,102,0,0.1); }
        .btn-primary {
            background: linear-gradient(135deg, #ff6600 0%, #ff8533 100%);
            border: none;
            font-weight: 700;
            padding: 12px 30px;
            border-radius: 8px;
        }
        .btn-primary:hover { background: linear-gradient(135deg, #ff8533 0%, #ff6600 100%); }
        .hero-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            color: white;
        }
        .hero-header h1 { font-family: 'Montserrat', sans-serif; font-weight: 900; margin: 0; }
        .hero-header p { opacity: 0.9; margin-top: 10px; }
        @media (max-width: 768px) { .field-row { flex-direction: column; } }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="hero-header">
            <h1><i class="zmdi zmdi-swap"></i> Registrar Movimiento</h1>
            <p>Complete el formulario para registrar un movimiento de bien nacional</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $tipo_mensaje == 'error' ? 'danger' : 'success'; ?>" style="margin: 20px;">
                <?= htmlspecialchars($mensaje); ?>
            </div>
            <?php if ($tipo_mensaje == 'success' && isset($_SESSION['ultimo_movimiento'])): ?>
                <div style="margin: 20px; text-align: center;">
                    <a href="pdf_movimiento.php" target="_blank" class="btn btn-primary">
                        <i class="zmdi zmdi-download"></i> Descargar PDF del Movimiento
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Búsqueda de Bien -->
        <div class="form-section">
            <div class="form-section-title"><i class="zmdi zmdi-search"></i> Buscar Bien Nacional</div>
            <form method="GET" action="" class="field-row" style="align-items: flex-end;">
                <div class="field-col" style="flex: 2;">
                    <label for="codigo_bien" class="field-label required-field">Código de Bien Nacional</label>
                    <input type="text" name="codigo_bien" id="codigo_bien" 
                           placeholder="Ej: BN-2026-0001" maxlength="50" class="form-control" 
                           value="<?= isset($_GET['codigo_bien']) ? htmlspecialchars($_GET['codigo_bien']) : ''; ?>" required />
                </div>
                <div class="field-col" style="flex: 0 0 auto;">
                    <button type="submit" name="buscar" value="1" class="btn btn-primary">
                        <i class="zmdi zmdi-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>

        <!-- Información del Bien Encontrado -->
        <?php if ($bien_seleccionado): ?>
        <div class="form-section" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 2px solid #4caf50;">
            <div class="form-section-title"><i class="zmdi zmdi-check-circle" style="color: #4caf50;"></i> Bien Encontrado</div>
            <input type="hidden" name="bien_id" id="bien_id" value="<?= $bien_seleccionado['id'] ?>">
            
            <div class="field-row">
                <div class="field-col">
                    <label class="field-label">Código:</label>
                    <span style="color: #ff6600; font-weight: 700;"><?= htmlspecialchars($bien_seleccionado['codigo_bien_nacional']); ?></span>
                </div>
                <div class="field-col">
                    <label class="field-label">Descripción:</label>
                    <span><?= htmlspecialchars($bien_seleccionado['descripcion']); ?></span>
                </div>
            </div>
            
            <div class="field-row" style="margin-top: 15px;">
                <div class="field-col">
                    <label class="field-label">Marca:</label>
                    <span><?= htmlspecialchars($bien_seleccionado['marca'] ?: 'N/A'); ?></span>
                </div>
                <div class="field-col">
                    <label class="field-label">Modelo:</label>
                    <span><?= htmlspecialchars($bien_seleccionado['modelo'] ?: 'N/A'); ?></span>
                </div>
                <div class="field-col">
                    <label class="field-label">Serial:</label>
                    <span><?= htmlspecialchars($bien_seleccionado['serial'] ?: 'N/A'); ?></span>
                </div>
            </div>
            
            <div class="field-row" style="margin-top: 15px;">
                <div class="field-col">
                    <label class="field-label">Ubicación Actual:</label>
                    <span style="background: #e3f2fd; color: #1565c0; padding: 3px 8px; border-radius: 4px;">
                        <?= htmlspecialchars($bien_seleccionado['ubicacion_actual'] ?? 'No asignada'); ?>
                    </span>
                </div>
                <div class="field-col">
                    <label class="field-label">Estatus Actual:</label>
                    <span style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 4px;">
                        <?= htmlspecialchars($bien_seleccionado['estatus_actual'] ?? 'No asignado'); ?>
                    </span>
                </div>
            </div>
        </div>
        <?php elseif (isset($_GET['buscar']) && !empty($_GET['codigo_bien'])): ?>
        <div class="form-section" style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%); border: 2px solid #f44336;">
            <div class="form-section-title" style="color: #c62828;">
                <i class="zmdi zmdi-error" style="color: #f44336;"></i> Bien no encontrado
            </div>
            <p>No se encontró ningún bien con el código: <?= htmlspecialchars($_GET['codigo_bien']); ?></p>
        </div>
        <?php endif; ?>

        <!-- Formulario para Registrar Movimiento -->
        <?php if ($bien_seleccionado): ?>
        <form id="form-registrar-movimiento" method="POST" action="" class="form-section">
            <input type="hidden" name="accion" value="registrar_movimiento">
            <input type="hidden" name="codigo_bien" value="<?= htmlspecialchars($bien_seleccionado['codigo_bien_nacional']); ?>">
            <input type="hidden" name="bien_id" value="<?= $bien_seleccionado['id']; ?>">
            <input type="hidden" name="ubicacion_origen_id" value="<?= $bien_seleccionado['ubicacion_id'] ?? 0; ?>">
            
            <div class="form-section-title"><i class="zmdi zmdi-swap"></i> Datos del Movimiento</div>
            
            <!-- Sección: Información Principal -->
            <div class="form-section-title" style="margin-top: 0; font-size: 1rem; color: #666;">
                <i class="zmdi zmdi-info"></i> Información Principal
            </div>
            
            <div class="field-row">
                <div class="field-col">
                    <label for="tipo_movimiento" class="field-label required-field">Tipo de Movimiento</label>
                    <select name="tipo_movimiento" id="tipo_movimiento" class="form-control" required onchange="toggleFields()">
                        <option value="">Seleccionar...</option>
                        <option value="traslado">Traslado</option>
                        <option value="prestamo">Préstamo</option>
                        <option value="cambio_estatus">Cambio de Estatus</option>
                        <option value="mantenimiento">Mantenimiento</option>
                        <option value="desincorporacion">Desincorporación</option>
                        <option value="incorporacion">Incorporación</option>
                    </select>
                </div>
                <div class="field-col">
                    <label for="fecha_movimiento" class="field-label required-field">Fecha del Movimiento</label>
                    <input type="date" name="fecha_movimiento" id="fecha_movimiento" class="form-control" required />
                </div>
                <div class="field-col">
                    <label for="numero_documento" class="field-label">Número de Documento</label>
                    <input type="text" name="numero_documento" id="numero_documento" placeholder="Ej: OFI-2026-001" maxlength="50" class="form-control" />
                </div>
            </div>
            
            <!-- Sección: Ubicaciones -->
            <div class="form-section-title" style="margin-top: 25px; font-size: 1rem; color: #666;">
                <i class="zmdi zmdi-pin"></i> Ubicaciones
            </div>
            
            <div class="field-row">
                <div class="field-col">
                    <label for="ubicacion_origen_id" class="field-label">Ubicación de Origen</label>
                    <select name="ubicacion_origen_id" id="ubicacion_origen_id" class="form-control">
                        <option value="0">Seleccionar...</option>
                        <?php foreach ($ubicaciones as $ubic): ?>
                            <option value="<?= $ubic['id']; ?>" <?= ($bien_seleccionado['ubicacion_id'] ?? 0) == $ubic['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars(($ubic['descripcion'] ?? '') . ' - ' . $ubic['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-col">
                    <label for="ubicacion_destino_id" class="field-label">Ubicación de Destino</label>
                    <select name="ubicacion_destino_id" id="ubicacion_destino_id" class="form-control">
                        <option value="0">Seleccionar...</option>
                        <?php foreach ($ubicaciones as $ubic): ?>
                            <option value="<?= $ubic['id']; ?>">
                                <?= htmlspecialchars(($ubic['descripcion'] ?? '') . ' - ' . $ubic['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Sección: Responsables -->
            <div class="form-section-title" style="margin-top: 25px; font-size: 1rem; color: #666;">
                <i class="zmdi zmdi-account"></i> Responsables
            </div>
            
            <div class="field-row">
                <div class="field-col">
                    <label for="responsable_origen_id" class="field-label">Responsable de Origen</label>
                    <select name="responsable_origen_id" id="responsable_origen_id" class="form-control">
                        <option value="0">Seleccionar...</option>
                        <?php foreach ($responsables as $resp): ?>
                            <option value="<?= $resp['id']; ?>">
                                <?= htmlspecialchars(($resp['cedula'] ?? '') . ' - ' . $resp['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-col">
                    <label for="responsable_destino_id" class="field-label">Responsable de Destino</label>
                    <select name="responsable_destino_id" id="responsable_destino_id" class="form-control">
                        <option value="0">Seleccionar...</option>
                        <?php foreach ($responsables as $resp): ?>
                            <option value="<?= $resp['id']; ?>">
                                <?= htmlspecialchars(($resp['cedula'] ?? '') . ' - ' . $resp['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Sección: Estatus Destino -->
            <div class="field-row" id="estatus_destino_container" style="display: none; margin-top: 25px;">
                <div class="field-col" style="flex: 1;">
                    <label for="estatus_destino_id" class="field-label required-field">Nuevo Estatus</label>
                    <select name="estatus_destino_id" id="estatus_destino_id" class="form-control">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($estatus as $est): ?>
                            <option value="<?= $est['id']; ?>">
                                <?= htmlspecialchars($est['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Sección: Razón y Observaciones -->
            <div class="form-section-title" style="margin-top: 25px; font-size: 1rem; color: #666;">
                <i class="zmdi zmdi-comment-text"></i> Detalles Adicionales
            </div>
            
            <div class="field-row">
                <div class="field-col" style="flex: 1;">
                    <label for="razon" class="field-label">Razón del Movimiento</label>
                    <input type="text" name="razon" id="razon" placeholder="Motivo o razón del movimiento" maxlength="255" class="form-control" />
                </div>
            </div>
            
            <div class="field-row" style="margin-top: 15px;">
                <div class="field-col" style="flex: 100%;">
                    <label for="observaciones" class="field-label">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="4" placeholder="Observaciones adicionales..." class="form-control" style="width: 100%;"></textarea>
                </div>
            </div>
            
            <!-- Botones -->
            <div class="field-row" style="margin-top: 30px;">
                <div class="field-col">
                    <a href="registrar_movimiento.php" class="btn btn-secondary" style="margin-right: 10px;">
                        <i class="zmdi zmdi-close"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="zmdi zmdi-save"></i> Registrar Movimiento
                    </button>
                </div>
            </div>
        </form>
        <?php endif; ?>
        
        <!-- Información -->
        <div class="form-section" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); border: 1px solid #ffcc80;">
            <div class="form-section-title"><i class="zmdi zmdi-info-outline" style="color: #e65100;"></i> Información sobre Movimientos</div>
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
            const estatusDestino = document.getElementById('estatus_destino_container');
            const ubicacionDestino = document.getElementById('ubicacion_destino_id')?.parentElement?.parentElement;
            
            if (estatusDestino) estatusDestino.style.display = 'none';
            
            if (tipo === 'cambio_estatus' || tipo === 'desincorporacion') {
                if (estatusDestino) estatusDestino.style.display = 'flex';
            }
        }
        
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