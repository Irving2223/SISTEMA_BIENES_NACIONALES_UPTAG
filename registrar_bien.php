<?php
require_once 'conexion.php';

// Verificar autenticación
if (!isset($_SESSION['usuario'])) {
    header("Location: Loggin.php");
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'registrar_bien') {
    try {
        $conn->begin_transaction();

        // Obtener datos del formulario
        $codigo_bien_nacional = trim($_POST['codigo_bien_nacional'] ?? '');
        $codigo_anterior = trim($_POST['codigo_anterior'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $marca = trim($_POST['marca'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $serial = trim($_POST['serial'] ?? '');
        $color = trim($_POST['color'] ?? '');
        $dimensiones = trim($_POST['dimensiones'] ?? '');
        $valor_adquisicion = (float)($_POST['valor_adquisicion'] ?? 0);
        $valor_actual = (float)($_POST['valor_actual'] ?? 0);
        $vida_util_anos = (int)($_POST['vida_util_anos'] ?? 0);
        $observaciones = trim($_POST['observaciones'] ?? '');
        $fecha_incorporacion = $_POST['fecha_incorporacion'] ?? '';
        
        // Nuevos campos
        $categoria_id = (int)($_POST['categoria_id'] ?? 0);
        $estatus_id = (int)($_POST['estatus_id'] ?? 1);
        $ubicacion_id = (int)($_POST['ubicacion_id'] ?? 0);
        
        // Validaciones
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
        
        // Verificar si el código ya existe
        $stmt_check = $conn->prepare("SELECT id FROM bienes WHERE codigo_bien_nacional = ?");
        $stmt_check->bind_param("s", $codigo_bien_nacional);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            throw new Exception("Ya existe un bien registrado con el código: $codigo_bien_nacional");
        }
        $stmt_check->close();
        
        // Si no hay estatus_id válido, buscar el primero disponible
        if ($estatus_id <= 0) {
            $result_estatus = $conn->query("SELECT id FROM estatus WHERE activo = 1 LIMIT 1");
            if ($result_estatus && $result_estatus->num_rows > 0) {
                $est = $result_estatus->fetch_assoc();
                $estatus_id = $est['id'];
            } else {
                $estatus_id = 1; // Valor por defecto
            }
        }
        
        // Si no hay categoría_id válido, buscar el primero disponible
        if ($categoria_id <= 0) {
            $result_cat = $conn->query("SELECT id FROM categorias WHERE activo = 1 LIMIT 1");
            if ($result_cat && $result_cat->num_rows > 0) {
                $cat = $result_cat->fetch_assoc();
                $categoria_id = $cat['id'];
            } else {
                throw new Exception("No hay categorías disponibles.");
            }
        }
        
        // Insertar el bien usando las columnas correctas de la tabla bienes
        $sql = "
            INSERT INTO bienes(
                codigo_bien_nacional, 
                codigo_anterior, 
                categoria_id, 
                descripcion, 
                marca, 
                modelo, 
                serial, 
                color, 
                dimensiones, 
                valor_adquisicion,
                valor_actual,
                vida_util_anos, 
                estatus_id, 
                observaciones, 
                fecha_incorporacion,
                activo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ";
        
        $stmt = $conn->prepare($sql);
        
        // En PHP 8, bind_param puede ser estricto con tipos. 
        // Convertimos todos los valores a tipos correctos
        $categoria_id_int = (int)$categoria_id;
        $estatus_id_int = (int)$estatus_id;
        $vida_util_int = (int)$vida_util_anos;
        $valor_adq = (float)$valor_adquisicion;
        $valor_act = (float)$valor_actual;
        
        $stmt->bind_param(
            "ssisssssdiiisss", 
            $codigo_bien_nacional,
            $codigo_anterior,
            $categoria_id_int,
            $descripcion,
            $marca,
            $modelo,
            $serial,
            $color,
            $dimensiones,
            $valor_adq,
            $valor_act,
            $vida_util_int,
            $estatus_id_int,
            $observaciones,
            $fecha_incorporacion
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al registrar el bien: " . $stmt->error);
        }
        
        $bien_id = $conn->insert_id;
        $stmt->close();
        
        // Si se seleccionó una ubicación, registrar el movimiento inicial de incorporación
        if ($ubicacion_id > 0) {
            $stmt_mov = $conn->prepare("
                INSERT INTO movimientos(
                    bien_id, 
                    tipo_movimiento, 
                    ubicacion_destino_id, 
                    fecha_movimiento, 
                    razon
                ) VALUES (?, 'Incorporacion', ?, ?, 'Incorporación inicial del bien')
            ");
            $fecha_mov = $fecha_incorporacion;
            $stmt_mov->bind_param("iis", $bien_id, $ubicacion_id, $fecha_mov);
            $stmt_mov->execute();
            $stmt_mov->close();
        }
        
        $conn->commit();
        
        $mensaje = "Bien Nacional '$codigo_bien_nacional' registrado correctamente.";
        $tipo_mensaje = "success";
        
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener datos para los dropdowns
$ubicaciones = [];
$dependencias = [];
$categorias = [];
$estatus = [];

try {
    // Función para verificar si una tabla existe
    function tablaExiste($conn, $nombre_tabla) {
        $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($nombre_tabla) . "'");
        return $result && $result->num_rows > 0;
    }
    
    // Ubicaciones
    if (tablaExiste($conn, 'ubicaciones')) {
        $result_ubicaciones = $conn->query("SELECT id, nombre, descripcion FROM ubicaciones WHERE activo = 1 ORDER BY nombre");
        if ($result_ubicaciones) {
            $ubicaciones = $result_ubicaciones->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // Dependencias
    if (tablaExiste($conn, 'dependencias')) {
        $result_dependencias = $conn->query("SELECT id, nombre, codigo FROM dependencias WHERE activo = 1 ORDER BY nombre");
        if ($result_dependencias) {
            $dependencias = $result_dependencias->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // Categorías
    if (tablaExiste($conn, 'categorias')) {
        $result_categorias = $conn->query("SELECT id, nombre, codigo FROM categorias WHERE activo = 1 ORDER BY nombre");
        if ($result_categorias) {
            $categorias = $result_categorias->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // Estatus
    if (tablaExiste($conn, 'estatus')) {
        $result_estatus = $conn->query("SELECT id, nombre FROM estatus WHERE activo = 1 ORDER BY nombre");
        if ($result_estatus) {
            $estatus = $result_estatus->fetch_all(MYSQLI_ASSOC);
        }
    }
} catch (Exception $e) {
    $mensaje .= "\nNota: Error al cargar algunos datos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Bien Nacional</title>
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
        <!-- Título -->
        <div class="hero-header">
            <h1>
                <i class="zmdi zmdi-store"></i> Registrar Bien Nacional
            </h1>
            <p>Complete el formulario para registrar un nuevo bien en el inventario de la institución</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $tipo_mensaje == 'error' ? 'danger' : ($tipo_mensaje == 'info' ? 'warning' : 'success'); ?>" style="margin: 20px;">
                <?= htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario para Registrar Bien Nacional -->
        <form id="form-registrar-bien" method="POST" action="" class="form-section">
            <input type="hidden" name="accion" value="registrar_bien">
            
            <!-- Sección: Identificación del Bien -->
            <div class="form-section-title">
                <i class="zmdi zmdi-tag"></i> Identificación del Bien
            </div>
            
            <div class="field-row">
                <div class="field-col">
                    <label for="codigo_bien_nacional" class="field-label required-field">Código de Bien Nacional</label>
                    <input type="text" name="codigo_bien_nacional" id="codigo_bien_nacional" 
                           placeholder="Ej: BN-2026-0001" maxlength="50" class="form-control" 
                           pattern="[A-Za-z0-9\-]+" title="Solo letras, números y guiones" required />
                </div>
                <div class="field-col">
                    <label for="codigo_anterior" class="field-label">Código Anterior</label>
                    <input type="text" name="codigo_anterior" id="codigo_anterior" 
                           placeholder="Código anterior del bien" maxlength="50" class="form-control" />
                </div>
            </div>
            
            <div class="field-row" style="margin-top: 15px;">
                <div class="field-col">
                    <label for="categoria_id" class="field-label required-field">Categoría</label>
                    <select name="categoria_id" id="categoria_id" class="form-control" required>
                        <option value="">Seleccionar categoría</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id']; ?>">
                                <?= htmlspecialchars(($cat['codigo'] ?? '') . ' - ' . $cat['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-col">
                    <label for="estatus_id" class="field-label">Estatus</label>
                    <select name="estatus_id" id="estatus_id" class="form-control">
                        <?php foreach ($estatus as $est): ?>
                            <option value="<?= $est['id']; ?>" <?= ($est['nombre'] == 'Activo') ? 'selected' : ''; ?>>
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
                              class="form-control" required style="width: 100%;"></textarea>
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
                           placeholder="Ej: Dell, HP, Lenovo" maxlength="100" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="modelo" class="field-label">Modelo</label>
                    <input type="text" name="modelo" id="modelo" 
                           placeholder="Ej: OptiPlex 3080" maxlength="100" class="form-control" />
                </div>
            </div>
            
            <div class="field-row" style="margin-top: 15px;">
                <div class="field-col">
                    <label for="serial" class="field-label">Número de Serie</label>
                    <input type="text" name="serial" id="serial" 
                           placeholder="Número de serie del equipo" maxlength="100" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="color" class="field-label">Color</label>
                    <input type="text" name="color" id="color" 
                           placeholder="Ej: Negro, Gris" maxlength="50" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="dimensiones" class="field-label">Dimensiones</label>
                    <input type="text" name="dimensiones" id="dimensiones" 
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
                           placeholder="0.00" step="0.01" min="0" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="valor_actual" class="field-label">Valor Actual (Bs.)</label>
                    <input type="number" name="valor_actual" id="valor_actual" 
                           placeholder="0.00" step="0.01" min="0" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="vida_util_anos" class="field-label">Vida Útil (Años)</label>
                    <input type="number" name="vida_util_anos" id="vida_util_anos" 
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
                        // Agrupar por dependencia
                        $current_dependencia = '';
                        foreach ($ubicaciones as $ubic): 
                            $dep_id = $ubic['dependencia_id'] ?? 0;
                            $dep_nombre = 'Sin dependencia';
                            
                            // Buscar nombre de la dependencia
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
                            <option value="<?= $ubic['id']; ?>">
                                <?= htmlspecialchars(($ubic['descripcion'] ?? '') . ' - ' . $ubic['nombre']); ?>
                            </option>
                        <?php 
                        endforeach;
                        if ($current_dependencia !== '') {
                            echo '</optgroup>';
                        }
                        ?>
                    </select>
                    <small style="color: #666;">La ubicación se asignará al registrar el movimiento de incorporación</small>
                </div>
                <div class="field-col">
                    <label for="fecha_incorporacion" class="field-label required-field">Fecha de Incorporación</label>
                    <input type="date" name="fecha_incorporacion" id="fecha_incorporacion" class="form-control" required />
                </div>
            </div>
            
            <!-- Sección: Observaciones -->
            <div class="form-section-title" style="margin-top: 25px;">
                <i class="zmdi zmdi-comment-text"></i> Observaciones
            </div>
            
            <div class="field-row">
                <div class="field-col" style="flex: 100%;">
                    <label for="observaciones" class="field-label">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="4" 
                              placeholder="Información adicional relevante sobre el bien..." 
                              class="form-control" style="width: 100%;"></textarea>
                </div>
            </div>
            
            <!-- Botones -->
            <div class="field-row" style="margin-top: 30px;">
                <div class="field-col">
                    <button type="reset" class="btn btn-secondary" style="margin-right: 10px;">
                        <i class="zmdi zmdi-refresh"></i> Limpiar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="zmdi zmdi-save"></i> Registrar Bien
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Información adicional -->
        <div class="info-card">
            <h5><i class="zmdi zmdi-info-outline"></i> Información sobre el Registro de Bienes</h5>
            <ul>
                <li><strong>Código de Bien Nacional:</strong> Identificador único asignado a cada bien de la institución.</li>
                <li><strong>Valor Actual:</strong> Se calcula automáticamente o se puede ingresar manualmente según depreciación.</li>
                <li><strong>Vida Útil:</strong> Años estimados de uso del bien según normativas de la administración pública.</li>
                <li><strong>Campos obligatorios:</strong> Los marcados con asterisco (*) son requeridos para el registro.</li>
                <li><strong>Movimientos:</strong> Al registrar el bien, se creará un movimiento de incorporación inicial.</li>
            </ul>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script src="js/jquery-3.1.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        // Establecer fecha actual por defecto
        document.addEventListener('DOMContentLoaded', function() {
            const fechaInput = document.getElementById('fecha_incorporacion');
            if (fechaInput && !fechaInput.value) {
                const today = new Date().toISOString().split('T')[0];
                fechaInput.value = today;
                fechaInput.max = today;
            }
        });
        
        // Validación del formulario
        document.getElementById('form-registrar-bien').addEventListener('submit', function(e) {
            const codigo = document.getElementById('codigo_bien_nacional').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            const fecha = document.getElementById('fecha_incorporacion').value;
            const categoria = document.getElementById('categoria_id').value;
            
            if (!codigo || !descripcion || !fecha || !categoria) {
                e.preventDefault();
                alert('Por favor, complete todos los campos obligatorios.');
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