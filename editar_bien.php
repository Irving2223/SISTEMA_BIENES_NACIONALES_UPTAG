<?php
require_once 'conexion.php';

// Verificar autenticación
if (!isset($_SESSION['usuario'])) {
    header("Location: Loggin.php");
    exit;
}

$mensaje = '';
$tipo_mensaje = '';
$bien_encontrado = null;
$modo_edicion = false;

// Procesar búsqueda de bien
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'buscar_bien') {
    $codigo_bien_nacional = trim($_POST['codigo_bien_nacional'] ?? '');
    
    if (empty($codigo_bien_nacional)) {
        $mensaje = "Debe ingresar un código de bien nacional.";
        $tipo_mensaje = "error";
    } else {
        $stmt = $conn->prepare("SELECT * FROM bienes WHERE codigo_bien_nacional = ?");
        $stmt->bind_param("s", $codigo_bien_nacional);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $bien_encontrado = $result->fetch_assoc();
            $modo_edicion = true;
            $mensaje = "Bien encontrado. Puede editar los datos.";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "No se encontró ningún bien con el código: $codigo_bien_nacional";
            $tipo_mensaje = "error";
        }
        $stmt->close();
    }
}

// Procesar actualización de bien
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_bien') {
    try {
        $conn->begin_transaction();
        
        $id = (int)($_POST['id'] ?? 0);
        $codigo_bien_nacional = trim($_POST['codigo_bien_nacional'] ?? '');
        $codigo_anterior = trim($_POST['codigo_anterior'] ?? '');
        $categoria_id = (int)($_POST['categoria_id'] ?? 0);
        $ubicacion_id = (int)($_POST['ubicacion_id'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $marca = trim($_POST['marca'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $serial = trim($_POST['serial'] ?? '');
        $color = trim($_POST['color'] ?? '');
        $dimensiones = trim($_POST['dimensiones'] ?? '');
        $valor_adquisicion = (float)($_POST['valor_adquisicion'] ?? 0);
        $valor_actual = (float)($_POST['valor_actual'] ?? 0);
        $vida_util_anos = (int)($_POST['vida_util_anos'] ?? 0);
        $estatus_id = (int)($_POST['estatus_id'] ?? 1);
        $observaciones = trim($_POST['observaciones'] ?? '');
        $fecha_incorporacion = $_POST['fecha_incorporacion'] ?? '';
        
        if (empty($codigo_bien_nacional)) {
            throw new Exception("El Código de Bien Nacional es obligatorio.");
        }
        if (empty($descripcion)) {
            throw new Exception("La descripción del bien es obligatoria.");
        }
        if (empty($fecha_incorporacion)) {
            throw new Exception("La fecha de incorporación es obligatoria.");
        }
        if ($categoria_id <= 0) {
            throw new Exception("Debe seleccionar una categoría.");
        }
        
        // Verificar qué columnas existen en la tabla bienes
        $columnas = [];
        $result_cols = $conn->query("SHOW COLUMNS FROM bienes");
        while ($row = $result_cols->fetch_assoc()) {
            $columnas[] = $row['Field'];
        }
        
        // Construir consulta de actualización dinámicamente
        $sql = "UPDATE bienes SET 
            codigo_bien_nacional = ?, 
            codigo_anterior = ?, 
            categoria_id = ?,
            descripcion = ?, 
            marca = ?, 
            modelo = ?, 
            serial = ?, 
            color = ?, 
            dimensiones = ?, 
            valor_adquisicion = ?,
            valor_actual = ?,
            vida_util_anos = ?, 
            estatus_id = ?,
            observaciones = ?, 
            fecha_incorporacion = ?";
        
        $params = [
            $codigo_bien_nacional,
            $codigo_anterior,
            $categoria_id,
            $descripcion,
            $marca,
            $modelo,
            $serial,
            $color,
            $dimensiones,
            $valor_adquisicion,
            $valor_actual,
            $vida_util_anos,
            $estatus_id,
            $observaciones,
            $fecha_incorporacion
        ];
        $types = "ssisssssdiiisss";
        
        // Agregar ubicacion_id si existe la columna
        if (in_array('ubicacion_id', $columnas)) {
            $sql .= ", ubicacion_id = ?";
            $params[] = $ubicacion_id;
            $types .= "i";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        $types .= "i";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar el bien: " . $stmt->error);
        }
        $stmt->close();
        
        $conn->commit();
        
        $stmt = $conn->prepare("SELECT * FROM bienes WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $bien_encontrado = $result->fetch_assoc();
        $stmt->close();
        
        $modo_edicion = true;
        $mensaje = "Bien '$codigo_bien_nacional' actualizado correctamente.";
        $tipo_mensaje = "success";
        
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
        $modo_edicion = true;
        
        if (isset($_POST['id'])) {
            $bien_encontrado = [
                'id' => $_POST['id'],
                'codigo_bien_nacional' => $_POST['codigo_bien_nacional'] ?? '',
                'codigo_anterior' => $_POST['codigo_anterior'] ?? '',
                'categoria_id' => $_POST['categoria_id'] ?? 0,
                'ubicacion_id' => $_POST['ubicacion_id'] ?? 0,
                'descripcion' => $_POST['descripcion'] ?? '',
                'marca' => $_POST['marca'] ?? '',
                'modelo' => $_POST['modelo'] ?? '',
                'serial' => $_POST['serial'] ?? '',
                'color' => $_POST['color'] ?? '',
                'dimensiones' => $_POST['dimensiones'] ?? '',
                'valor_adquisicion' => $_POST['valor_adquisicion'] ?? 0,
                'valor_actual' => $_POST['valor_actual'] ?? 0,
                'vida_util_anos' => $_POST['vida_util_anos'] ?? 0,
                'estatus_id' => $_POST['estatus_id'] ?? 1,
                'observaciones' => $_POST['observaciones'] ?? '',
                'fecha_incorporacion' => $_POST['fecha_incorporacion'] ?? ''
            ];
        }
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
    
    if (tablaExiste($conn, 'ubicaciones')) {
        $result_ubicaciones = $conn->query("SELECT id, nombre, descripcion, dependencia_id FROM ubicaciones WHERE activo = 1 ORDER BY nombre");
        if ($result_ubicaciones) {
            $ubicaciones = $result_ubicaciones->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    if (tablaExiste($conn, 'dependencias')) {
        $result_dependencias = $conn->query("SELECT id, nombre, codigo FROM dependencias WHERE activo = 1 ORDER BY nombre");
        if ($result_dependencias) {
            $dependencias = $result_dependencias->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    if (tablaExiste($conn, 'categorias')) {
        $result_categorias = $conn->query("SELECT id, nombre, codigo FROM categorias WHERE activo = 1 ORDER BY nombre");
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
} catch (Exception $e) {}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Bien Nacional</title>
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
        .form-section-title i {
            color: #ff6600;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
        .form-control:focus {
            border-color: #ff6600;
            box-shadow: 0 0 0 3px rgba(255,102,0,0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #ff6600 0%, #ff8533 100%);
            border: none;
            font-weight: 700;
            padding: 12px 30px;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #ff8533 0%, #ff6600 100%);
        }
        .hero-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            color: white;
        }
        .hero-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 900;
            margin: 0;
        }
        .hero-header p {
            opacity: 0.9;
            margin-top: 10px;
        }
        .search-box {
            background: linear-gradient(135deg, #ff6600 0%, #ff8533 100%);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .search-box input {
            border: none !important;
            border-radius: 8px !important;
            padding: 15px 20px !important;
        }
        .info-card {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            border: 1px solid #ffcc80;
            border-radius: 10px;
            padding: 20px;
        }
        .info-card h5 {
            color: #e65100;
            font-weight: 700;
        }
        .info-card ul {
            margin: 0;
            padding-left: 20px;
            color: #333;
        }
        .info-card li {
            margin-bottom: 8px;
        }
        @media (max-width: 768px) {
            .field-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="hero-header">
            <h1>
                <i class="zmdi zmdi-edit"></i> Editar Bien Nacional
            </h1>
            <p>Busque y modifique los datos de un bien registrado en el inventario</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $tipo_mensaje == 'error' ? 'danger' : ($tipo_mensaje == 'info' ? 'warning' : 'success'); ?>" style="margin: 20px;">
                <?= htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if (!$modo_edicion): ?>
        <div class="search-box">
            <h4 style="color: white; font-weight: 700; margin-bottom: 15px;">
                <i class="zmdi zmdi-search"></i> Buscar Bien Nacional
            </h4>
            <form id="form-buscar-bien" method="POST" action="">
                <input type="hidden" name="accion" value="buscar_bien">
                <div class="field-row">
                    <div class="field-col" style="flex: 1;">
                        <input type="text" name="codigo_bien_nacional" id="codigo_bien_nacional" 
                               placeholder="Ingrese el código de bien nacional (Ej: BN-2026-0001)" 
                               maxlength="50" class="form-control" required />
                    </div>
                    <div class="field-col" style="flex: 0 0 auto;">
                        <button type="submit" class="btn btn-primary" style="background: white; color: #ff6600; height: 50px;">
                            <i class="zmdi zmdi-search"></i> Buscar
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($modo_edicion && $bien_encontrado): ?>
        <form id="form-editar-bien" method="POST" action="" class="form-section">
            <input type="hidden" name="accion" value="actualizar_bien">
            <input type="hidden" name="id" value="<?= $bien_encontrado['id'] ?>">
            
            <!-- Sección: Identificación del Bien -->
            <div class="form-section-title">
                <i class="zmdi zmdi-tag"></i> Identificación del Bien
            </div>
            
            <div class="field-row">
                <div class="field-col">
                    <label for="codigo_bien_nacional" class="field-label required-field">Código de Bien Nacional</label>
                    <input type="text" name="codigo_bien_nacional" id="codigo_bien_nacional" 
                           value="<?= htmlspecialchars($bien_encontrado['codigo_bien_nacional'] ?? '') ?>"
                           placeholder="Ej: BN-2026-0001" maxlength="50" class="form-control" 
                           pattern="[A-Za-z0-9\-]+" title="Solo letras, números y guiones" required />
                </div>
                <div class="field-col">
                    <label for="codigo_anterior" class="field-label">Código Anterior</label>
                    <input type="text" name="codigo_anterior" id="codigo_anterior" 
                           value="<?= htmlspecialchars($bien_encontrado['codigo_anterior'] ?? '') ?>"
                           placeholder="Código anterior del bien" maxlength="50" class="form-control" />
                </div>
            </div>
            
            <div class="field-row" style="margin-top: 15px;">
                <div class="field-col">
                    <label for="categoria_id" class="field-label required-field">Categoría</label>
                    <select name="categoria_id" id="categoria_id" class="form-control" required>
                        <option value="">Seleccionar categoría</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id']; ?>" <?= ($bien_encontrado['categoria_id'] ?? 0) == $cat['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars(($cat['codigo'] ?? '') . ' - ' . $cat['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-col">
                    <label for="estatus_id" class="field-label">Estatus</label>
                    <select name="estatus_id" id="estatus_id" class="form-control">
                        <?php foreach ($estatus as $est): ?>
                            <option value="<?= $est['id']; ?>" <?= ($bien_encontrado['estatus_id'] ?? 1) == $est['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($est['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Sección: Descripción -->
            <div class="form-section-title" style="margin-top: 25px;">
                <i class="zmdi zmdi-text-description"></i> Descripción
            </div>
            
            <div class="field-row">
                <div class="field-col" style="flex: 100%;">
                    <label for="descripcion" class="field-label required-field">Descripción del Bien</label>
                    <textarea name="descripcion" id="descripcion" rows="3" 
                              placeholder="Ej: Computadora de escritorio Dell OptiPlex 3080 con procesador Intel Core i5" 
                              class="form-control" style="width: 100%;" required><?= htmlspecialchars($bien_encontrado['descripcion'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- Sección: Características Físicas -->
            <div class="form-section-title" style="margin-top: 25px;">
                <i class="zmdi zmdi-ruler"></i> Características Físicas
            </div>
            
            <div class="field-row">
                <div class="field-col">
                    <label for="marca" class="field-label">Marca</label>
                    <input type="text" name="marca" id="marca" 
                           value="<?= htmlspecialchars($bien_encontrado['marca'] ?? '') ?>"
                           placeholder="Ej: Dell, HP, Lenovo" maxlength="100" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="modelo" class="field-label">Modelo</label>
                    <input type="text" name="modelo" id="modelo" 
                           value="<?= htmlspecialchars($bien_encontrado['modelo'] ?? '') ?>"
                           placeholder="Ej: OptiPlex 3080" maxlength="100" class="form-control" />
                </div>
            </div>
            
            <div class="field-row" style="margin-top: 15px;">
                <div class="field-col">
                    <label for="serial" class="field-label">Número de Serie</label>
                    <input type="text" name="serial" id="serial" 
                           value="<?= htmlspecialchars($bien_encontrado['serial'] ?? '') ?>"
                           placeholder="Número de serie del equipo" maxlength="100" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="color" class="field-label">Color</label>
                    <input type="text" name="color" id="color" 
                           value="<?= htmlspecialchars($bien_encontrado['color'] ?? '') ?>"
                           placeholder="Ej: Negro, Gris" maxlength="50" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="dimensiones" class="field-label">Dimensiones</label>
                    <input type="text" name="dimensiones" id="dimensiones" 
                           value="<?= htmlspecialchars($bien_encontrado['dimensiones'] ?? '') ?>"
                           placeholder="Ej: 50x30x80 cm" maxlength="100" class="form-control" />
                </div>
            </div>
            
            <!-- Sección: Valoración -->
            <div class="form-section-title" style="margin-top: 25px;">
                <i class="zmdi zmdi-money"></i> Valoración
            </div>
            
            <div class="field-row">
                <div class="field-col">
                    <label for="valor_adquisicion" class="field-label">Valor de Adquisición (Bs.)</label>
                    <input type="number" name="valor_adquisicion" id="valor_adquisicion" 
                           value="<?= $bien_encontrado['valor_adquisicion'] ?? 0 ?>"
                           placeholder="0.00" step="0.01" min="0" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="valor_actual" class="field-label">Valor Actual (Bs.)</label>
                    <input type="number" name="valor_actual" id="valor_actual" 
                           value="<?= $bien_encontrado['valor_actual'] ?? 0 ?>"
                           placeholder="0.00" step="0.01" min="0" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="vida_util_anos" class="field-label">Vida Útil (Años)</label>
                    <input type="number" name="vida_util_anos" id="vida_util_anos" 
                           value="<?= $bien_encontrado['vida_util_anos'] ?? 0 ?>"
                           placeholder="Ej: 5" min="0" max="100" class="form-control" />
                </div>
            </div>
            
            <!-- Sección: Ubicación -->
            <div class="form-section-title" style="margin-top: 25px;">
                <i class="zmdi zmdi-pin"></i> Ubicación
            </div>
            
            <div class="field-row">
                <div class="field-col">
                    <label for="ubicacion_id" class="field-label">Ubicación Actual</label>
                    <select name="ubicacion_id" id="ubicacion_id" class="form-control">
                        <option value="">Seleccionar ubicación</option>
                        <?php 
                        $current_dependencia = '';
                        foreach ($ubicaciones as $ubic): 
                            $dep_id = $ubic['dependencia_id'] ?? 0;
                            $dep_nombre = 'Sin dependencia';
                            
                            foreach ($dependencias as $dep) {
                                if ($dep['id'] == $dep_id) {
                                    $dep_nombre = $dep['codigo'] . ' - ' . $dep['nombre'];
                                    break;
                                }
                            }
                            
                            if ($current_dependencia !== $dep_nombre) {
                                if ($current_dependencia !== '') {
                                    echo '</optgroup>';
                                }
                                $current_dependencia = $dep_nombre;
                                echo '<optgroup label="' . htmlspecialchars($dep_nombre) . '">';
                            }
                        ?>
                            <option value="<?= $ubic['id']; ?>" <?= ($bien_encontrado['ubicacion_id'] ?? 0) == $ubic['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars(($ubic['descripcion'] ?? '') . ' - ' . $ubic['nombre']); ?>
                            </option>
                        <?php 
                        endforeach;
                        if ($current_dependencia !== '') {
                            echo '</optgroup>';
                        }
                        ?>
                    </select>
                </div>
                <div class="field-col">
                    <label for="fecha_incorporacion" class="field-label required-field">Fecha de Incorporación</label>
                    <input type="date" name="fecha_incorporacion" id="fecha_incorporacion" 
                           value="<?= $bien_encontrado['fecha_incorporacion'] ?? '' ?>" class="form-control" required />
                </div>
            </div>
            
            <!-- Sección: Observaciones -->
            <div class="form-section-title" style="margin-top: 25px;">
                <i class="zmdi zmdi-comment-text"></i> Observaciones
            </div>
            
            <div class="field-row">
                <div class="field-col" style="flex: 100%;">
                    <textarea name="observaciones" id="observaciones" rows="4" 
                              placeholder="Información adicional sobre el bien..." 
                              class="form-control" style="width: 100%;"><?= htmlspecialchars($bien_encontrado['observaciones'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div class="field-row" style="margin-top: 30px;">
                <div class="field-col">
                    <a href="editar_bien.php" class="btn btn-secondary" style="margin-right: 10px;">
                        <i class="zmdi zmdi-close"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="zmdi zmdi-save"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </form>
        <?php endif; ?>
        
        <div class="info-card">
            <h5><i class="zmdi zmdi-info-outline"></i> Información</h5>
            <ul>
                <li>Ingrese el código de bien nacional para buscar</li>
                <li>Modifique los campos necesarios</li>
                <li>Los cambios se guardarán al hacer clic en "Guardar"</li>
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