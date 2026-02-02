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
        $vida_util_anos = (int)($_POST['vida_util_anos'] ?? 0);
        $observaciones = trim($_POST['observaciones'] ?? '');
        $fecha_incorporacion = $_POST['fecha_incorporacion'] ?? '';
        
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
        
        // Verificar si el código ya existe
        $stmt_check = $conn->prepare("SELECT codigo_bien_nacional FROM bienes WHERE codigo_bien_nacional = ?");
        $stmt_check->bind_param("s", $codigo_bien_nacional);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            throw new Exception("Ya existe un bien registrado con el código: $codigo_bien_nacional");
        }
        $stmt_check->close();
        
        // Obtener categoria_id (obligatorio)
        $categoria_id = 1; // Por defecto
        $result_cat = $conn->query("SELECT id FROM categorias WHERE activo = 1 LIMIT 1");
        if ($result_cat && $result_cat->num_rows > 0) {
            $cat = $result_cat->fetch_assoc();
            $categoria_id = $cat['id'];
        }
        
        // Obtener estatus_id (obligatorio) - verificar si la tabla existe
        $estatus_id = 1; // Por defecto
        $result_estatus = $conn->query("SHOW TABLES LIKE 'estatus'");
        if ($result_estatus && $result_estatus->num_rows > 0) {
            $result_estatus = $conn->query("SELECT id FROM estatus WHERE LOWER(nombre) = 'activo' LIMIT 1");
            if ($result_estatus && $result_estatus->num_rows > 0) {
                $est = $result_estatus->fetch_assoc();
                $estatus_id = $est['id'];
            } else {
                $result_estatus = $conn->query("SELECT id FROM estatus LIMIT 1");
                if ($result_estatus && $result_estatus->num_rows > 0) {
                    $est = $result_estatus->fetch_assoc();
                    $estatus_id = $est['id'];
                }
            }
        }
        
        // Si no existe la tabla estatus_bienes, verificar si la columna permite NULL
        // Si no permite NULL, intentamos usar un valor por defecto (asumiendo que existe el ID 1)
        // Si falla, el usuario deberá crear la tabla de estatus
        
        // Insertar el bien usando las columnas correctas de la tabla bienes
        $stmt = $conn->prepare("INSERT INTO bienes(codigo_bien_nacional, codigo_anterior, categoria_id, descripcion, marca, modelo, serial, color, dimensiones, valor_adquisicion, vida_util_anos, estatus_id, observaciones, fecha_incorporacion, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("ssisssssdissss", 
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
            $vida_util_anos,
            $estatus_id,
            $observaciones,
            $fecha_incorporacion
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al registrar el bien: " . $stmt->error);
        }
        $stmt->close();
        
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
    // Si las tablas no existen, mostrar advertencia
    $mensaje .= "\nNota: Algunas tablas de referencia no están disponibles aún.";
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

</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1 style="font-weight:900; font-family:montserrat; color:#ff6600; font-size:40px; padding:20px; text-align:left; font-size:50px;">
            <i class="zmdi zmdi-store"></i> 
            Registrar <span style="font-weight:700; color:black;">Bien Nacional</span>
        </h1>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $tipo_mensaje == 'error' ? 'danger' : ($tipo_mensaje == 'info' ? 'warning' : 'success'); ?>" style="margin: 20px;">
                <?= htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario para Registrar Bien Nacional -->
        <form id="form-registrar-bien" method="POST" action="" class="section-container">
            <input type="hidden" name="accion" value="registrar_bien">
            
            <h4 class="section-title">Datos del Bien Nacional</h4>
            
            <!-- Código y Descripción -->
            <div class="field-row">
                <div class="field-col">
                    <label for="codigo_bien_nacional" class="field-label required">Código de Bien Nacional</label>
                    <input type="text" name="codigo_bien_nacional" id="codigo_bien_nacional" 
                           placeholder="Ej: BN-2026-0001" maxlength="50" class="form-control" 
                           pattern="[A-Za-z0-9\-]+" title="Solo letras, números y guiones" required />
                </div>
                <div class="field-col">
                    <label for="descripcion" class="field-label required">Descripción</label>
                    <input type="text" name="descripcion" id="descripcion" 
                           placeholder="Ej: Computadora de escritorio Dell" maxlength="255" class="form-control" required />
                </div>
            </div>
            
            <!-- Marca y Modelo -->
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
            
            <!-- Código Anterior y Serial -->
            <div class="field-row">
                <div class="field-col">
                    <label for="codigo_anterior" class="field-label">Código Anterior</label>
                    <input type="text" name="codigo_anterior" id="codigo_anterior" 
                           placeholder="Código anterior del bien" maxlength="50" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="serial" class="field-label">Serial</label>
                    <input type="text" name="serial" id="serial" 
                           placeholder="Número de serie del equipo" maxlength="100" class="form-control" />
                </div>
            </div>
            
            <!-- Color y Dimensiones -->
            <div class="field-row">
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
            
            <!-- Valor de Adquisición y Vida Útil -->
            <div class="field-row">
                <div class="field-col">
                    <label for="valor_adquisicion" class="field-label">Valor de Adquisición (Bs.)</label>
                    <input type="number" name="valor_adquisicion" id="valor_adquisicion" 
                           placeholder="0.00" step="0.01" min="0" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="vida_util_anos" class="field-label">Vida Útil (Años)</label>
                    <input type="number" name="vida_util_anos" id="vida_util_anos" 
                           placeholder="Ej: 5" min="0" max="100" class="form-control" />
                </div>
            </div>
            
            <!-- Fecha de Incorporación -->
            <div class="field-row">
                <div class="field-col">
                    <label for="fecha_incorporacion" class="field-label required">Fecha de Incorporación</label>
                    <input type="date" name="fecha_incorporacion" id="fecha_incorporacion" class="form-control" required />
                </div>
            </div>
            
            <!-- Observaciones -->
            <div class="field-row">
                <div class="field-col" style="flex: 100%;">
                    <label for="observaciones" class="field-label">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="4" 
                              placeholder="Información adicional relevante sobre el bien..." 
                              class="form-control" style="width: 100%;"></textarea>
                </div>
            </div>
            
            <!-- Botones -->
            <div class="button-container">
                <button type="reset" class="btn btn-secondary">
                    <i class="zmdi zmdi-refresh"></i> Limpiar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="zmdi zmdi-save"></i> Registrar Bien
                </button>
            </div>
        </form>
        
        <!-- Información adicional -->
        <div class="section-container" style="background-color: #fff3e0;">
            <h4 class="section-title" style="color: #ff6600;">
                <i class="zmdi zmdi-info-outline"></i> Información sobre el Registro de Bienes
            </h4>
            <ul style="margin: 0; padding-left: 20px; color: #333;">
                <li style="margin-bottom: 8px;"><strong>Código de Bien Nacional:</strong> Identificador único asignado a cada bien de la universidad.</li>
                <li style="margin-bottom: 8px;"><strong>Tipos de Adquisición:</strong>
                    <ul style="margin-top: 5px;">
                        <li><em>Compra:</em> Bienes adquiridos mediante proceso de contratación.</li>
                        <li><em>Donación:</em> Bienes recibidos como donación de terceros.</li>
                        <li><em>Ingreso Propio:</em> Bienes producidos o generados por la institución.</li>
                    </ul>
                </li>
                <li style="margin-bottom: 8px;"><strong>Obligatoriedad:</strong> Los campos marcados con asterisco (*) son obligatorios.</li>
                <li><strong>Registro:</strong> Una vez registrado, el bien quedará disponible para búsquedas y movimientos.</li>
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
            const tipoAdquisicion = document.getElementById('tipo_adquisicion').value;
            const fecha = document.getElementById('fecha_incorporacion').value;
            
            if (!codigo || !descripcion || !tipoAdquisicion || !fecha) {
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
