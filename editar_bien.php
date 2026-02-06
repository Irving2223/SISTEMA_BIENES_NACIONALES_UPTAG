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
        // Buscar el bien en la base de datos
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
        
        // Obtener datos del formulario
        $id = $_POST['id'] ?? 0;
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
        $categoria_id = $_POST['categoria_id'] ?? 1;
        $estatus_id = $_POST['estatus_id'] ?? 1;
        
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
            descripcion = ?, 
            marca = ?, 
            modelo = ?, 
            serial = ?, 
            color = ?, 
            dimensiones = ?, 
            valor_adquisicion = ?, 
            vida_util_anos = ?, 
            observaciones = ?, 
            fecha_incorporacion = ?";
        
        $params = [
            $codigo_bien_nacional,
            $codigo_anterior,
            $descripcion,
            $marca,
            $modelo,
            $serial,
            $color,
            $dimensiones,
            $valor_adquisicion,
            $vida_util_anos,
            $observaciones,
            $fecha_incorporacion
        ];
        $types = "sssssssdisss";
        
        // Agregar categoria_id si existe la columna
        if (in_array('categoria_id', $columnas)) {
            $sql .= ", categoria_id = ?";
            $params[] = $categoria_id;
            $types .= "i";
        }
        
        // Agregar estatus_id si existe la columna
        if (in_array('estatus_id', $columnas)) {
            $sql .= ", estatus_id = ?";
            $params[] = $estatus_id;
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
        
        // Volver a buscar el bien actualizado
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
        
        // Recuperar datos del formulario para mantenerlos
        if (isset($_POST['id'])) {
            $bien_encontrado = [
                'id' => $_POST['id'],
                'codigo_bien_nacional' => $_POST['codigo_bien_nacional'] ?? '',
                'codigo_anterior' => $_POST['codigo_anterior'] ?? '',
                'descripcion' => $_POST['descripcion'] ?? '',
                'marca' => $_POST['marca'] ?? '',
                'modelo' => $_POST['modelo'] ?? '',
                'serial' => $_POST['serial'] ?? '',
                'color' => $_POST['color'] ?? '',
                'dimensiones' => $_POST['dimensiones'] ?? '',
                'valor_adquisicion' => $_POST['valor_adquisicion'] ?? 0,
                'vida_util_anos' => $_POST['vida_util_anos'] ?? 0,
                'observaciones' => $_POST['observaciones'] ?? '',
                'fecha_incorporacion' => $_POST['fecha_incorporacion'] ?? '',
                'categoria_id' => $_POST['categoria_id'] ?? 1,
                'estatus_id' => $_POST['estatus_id'] ?? 1
            ];
        }
    }
}

// Obtener datos para los dropdowns
$categorias = [];
$estatus = [];

try {
    function tablaExiste($conn, $nombre_tabla) {
        $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($nombre_tabla) . "'");
        return $result && $result->num_rows > 0;
    }
    
    // Verificar qué columnas tiene la tabla bienes
    $columnas_bienes = [];
    $result_cols = $conn->query("SHOW COLUMNS FROM bienes");
    while ($row = $result_cols->fetch_assoc()) {
        $columnas_bienes[] = $row['Field'];
    }
    
    // Categorías - solo mostrar si existe la columna categoria_id
    if (in_array('categoria_id', $columnas_bienes) && tablaExiste($conn, 'categorias')) {
        $result_categorias = $conn->query("SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre");
        if ($result_categorias) {
            $categorias = $result_categorias->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // Estatus - verificar tanto estatus_bienes como estatus
    if (tablaExiste($conn, 'estatus_bienes')) {
        $result_estatus = $conn->query("SELECT id, nombre FROM estatus_bienes WHERE activo = 1 ORDER BY nombre");
        if ($result_estatus) {
            $estatus = $result_estatus->fetch_all(MYSQLI_ASSOC);
        }
    } elseif (tablaExiste($conn, 'estatus')) {
        $result_estatus = $conn->query("SELECT id, nombre FROM estatus ORDER BY nombre");
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
    <title>Editar Bien Nacional</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/estilos_sistema.css">
    <link rel="stylesheet" href="css/material-design-iconic-font.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1 style="font-weight:900; font-family:montserrat; color:#ff6600; font-size:40px; padding:20px; text-align:left; font-size:50px;">
            <i class="zmdi zmdi-edit"></i> 
            Editar <span style="font-weight:700; color:black;">Bien Nacional</span>
        </h1>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $tipo_mensaje == 'error' ? 'danger' : ($tipo_mensaje == 'info' ? 'warning' : 'success'); ?>" style="margin: 20px;">
                <?= htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario para buscar bien -->
        <?php if (!$modo_edicion): ?>
        <form id="form-buscar-bien" method="POST" action="" class="section-container">
            <input type="hidden" name="accion" value="buscar_bien">
            
            <h4 class="section-title">Buscar Bien Nacional</h4>
            
            <div class="field-row">
                <div class="field-col" style="flex: 100%;">
                    <label for="codigo_bien_nacional" class="field-label required">Código de Bien Nacional</label>
                    <input type="text" name="codigo_bien_nacional" id="codigo_bien_nacional" 
                           placeholder="Ej: BN-2026-0001" maxlength="50" class="form-control" 
                           pattern="[A-Za-z0-9\-]+" title="Solo letras, números y guiones" required />
                </div>
            </div>
            
            <div class="button-container">
                <button type="submit" class="btn btn-primary">
                    <i class="zmdi zmdi-search"></i> Buscar Bien
                </button>
                <a href="editar_bien.php" class="btn btn-secondary">
                    <i class="zmdi zmdi-refresh"></i> Limpiar
                </a>
            </div>
        </form>
        <?php endif; ?>

        <!-- Formulario para editar bien -->
        <?php if ($modo_edicion && $bien_encontrado): ?>
        <form id="form-editar-bien" method="POST" action="" class="section-container">
            <input type="hidden" name="accion" value="actualizar_bien">
            <input type="hidden" name="id" value="<?= $bien_encontrado['id'] ?>">
            
            <h4 class="section-title">Datos del Bien Nacional</h4>
            
            <!-- Código y Descripción -->
            <div class="field-row">
                <div class="field-col">
                    <label for="codigo_bien_nacional" class="field-label required">Código de Bien Nacional</label>
                    <input type="text" name="codigo_bien_nacional" id="codigo_bien_nacional" 
                           value="<?= htmlspecialchars($bien_encontrado['codigo_bien_nacional'] ?? '') ?>"
                           placeholder="Ej: BN-2026-0001" maxlength="50" class="form-control" 
                           pattern="[A-Za-z0-9\-]+" title="Solo letras, números y guiones" required />
                </div>
                <div class="field-col">
                    <label for="descripcion" class="field-label required">Descripción</label>
                    <input type="text" name="descripcion" id="descripcion" 
                           value="<?= htmlspecialchars($bien_encontrado['descripcion'] ?? '') ?>"
                           placeholder="Ej: Computadora de escritorio Dell" maxlength="255" class="form-control" required />
                </div>
            </div>
            
            <!-- Marca y Modelo -->
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
            
            <!-- Código Anterior y Serial -->
            <div class="field-row">
                <div class="field-col">
                    <label for="codigo_anterior" class="field-label">Código Anterior</label>
                    <input type="text" name="codigo_anterior" id="codigo_anterior" 
                           value="<?= htmlspecialchars($bien_encontrado['codigo_anterior'] ?? '') ?>"
                           placeholder="Código anterior del bien" maxlength="50" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="serial" class="field-label">Serial</label>
                    <input type="text" name="serial" id="serial" 
                           value="<?= htmlspecialchars($bien_encontrado['serial'] ?? '') ?>"
                           placeholder="Número de serie del equipo" maxlength="100" class="form-control" />
                </div>
            </div>
            
            <!-- Color y Dimensiones -->
            <div class="field-row">
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
            
            <!-- Valor de Adquisición y Vida Útil -->
            <div class="field-row">
                <div class="field-col">
                    <label for="valor_adquisicion" class="field-label">Valor de Adquisición (Bs.)</label>
                    <input type="number" name="valor_adquisicion" id="valor_adquisicion" 
                           value="<?= $bien_encontrado['valor_adquisicion'] ?? 0 ?>"
                           placeholder="0.00" step="0.01" min="0" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="vida_util_anos" class="field-label">Vida Útil (Años)</label>
                    <input type="number" name="vida_util_anos" id="vida_util_anos" 
                           value="<?= $bien_encontrado['vida_util_anos'] ?? 0 ?>"
                           placeholder="Ej: 5" min="0" max="100" class="form-control" />
                </div>
            </div>
            
            <!-- Fecha de Incorporación -->
            <div class="field-row">
                <div class="field-col">
                    <label for="fecha_incorporacion" class="field-label required">Fecha de Incorporación</label>
                    <input type="date" name="fecha_incorporacion" id="fecha_incorporacion" 
                           value="<?= $bien_encontrado['fecha_incorporacion'] ?? '' ?>" class="form-control" required />
                </div>
            </div>
            
            <!-- Categoría y Estatus (solo si existen las columnas) -->
            <?php if (!empty($categorias) || !empty($estatus)): ?>
            <div class="field-row">
                <?php if (!empty($categorias)): ?>
                <div class="field-col">
                    <label for="categoria_id" class="field-label">Categoría</label>
                    <select name="categoria_id" id="categoria_id" class="form-control">
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($bien_encontrado['categoria_id'] ?? 1) == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($estatus)): ?>
                <div class="field-col">
                    <label for="estatus_id" class="field-label">Estatus</label>
                    <select name="estatus_id" id="estatus_id" class="form-control">
                        <?php foreach ($estatus as $est): ?>
                            <option value="<?= $est['id'] ?>" <?= ($bien_encontrado['estatus_id'] ?? 1) == $est['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($est['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Observaciones -->
            <div class="field-row">
                <div class="field-col" style="flex: 100%;">
                    <label for="observaciones" class="field-label">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="4" 
                              placeholder="Información adicional relevante sobre el bien..." 
                              class="form-control" style="width: 100%;"><?= htmlspecialchars($bien_encontrado['observaciones'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- Botones -->
            <div class="button-container">
                <a href="editar_bien.php" class="btn btn-secondary">
                    <i class="zmdi zmdi-close"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary" style="background-color: #ff6600; border-color: #ff6600;">
                    <i class="zmdi zmdi-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
        <?php endif; ?>
        
        <!-- Información adicional -->
        <div class="section-container" style="background-color: #fff3e0;">
            <h4 class="section-title" style="color: #ff6600;">
                <i class="zmdi zmdi-info-outline"></i> Información sobre Edición de Bienes
            </h4>
            <ul style="margin: 0; padding-left: 20px; color: #333;">
                <li style="margin-bottom: 8px;"><strong>Búsqueda:</strong> Ingrese el código de bien nacional del bien que desea editar.</li>
                <li style="margin-bottom: 8px;"><strong>Edición:</strong> Puede modificar cualquier campo del bien excepto el código original.</li>
                <li style="margin-bottom: 8px;"><strong>Historial:</strong> Se recomienda documentar los cambios en las observaciones.</li>
                <li><strong>Guardar:</strong> Los cambios se guardarán inmediatamente al hacer clic en "Guardar Cambios".</li>
            </ul>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script src="js/jquery-3.1.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        // Validación del formulario de búsqueda
        document.getElementById('form-buscar-bien')?.addEventListener('submit', function(e) {
            const codigo = document.getElementById('codigo_bien_nacional').value.trim();
            if (!codigo) {
                e.preventDefault();
                alert('Por favor, ingrese un código de bien nacional.');
                return false;
            }
            return true;
        });
        
        // Validación del formulario de edición
        document.getElementById('form-editar-bien')?.addEventListener('submit', function(e) {
            const codigo = document.getElementById('codigo_bien_nacional').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            const fecha = document.getElementById('fecha_incorporacion').value;
            
            if (!codigo || !descripcion || !fecha) {
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