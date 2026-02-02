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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        $conn->begin_transaction();
        
        if ($_POST['accion'] === 'registrar_ubicacion') {
            // Obtener datos del formulario
            $nombre = trim($_POST['nombre'] ?? '');
            $tipo = $_POST['tipo'] ?? '';
            $dependencia_id = $_POST['dependencia_id'] ?? null;
            $direccion = trim($_POST['direccion'] ?? '');
            $responsable = trim($_POST['responsable'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $observaciones = trim($_POST['observaciones'] ?? '');
            
            // Validaciones
            if (empty($nombre)) {
                throw new Exception("El nombre de la ubicación es obligatorio.");
            }
            if (empty($tipo)) {
                throw new Exception("Debe seleccionar el tipo de ubicación.");
            }
            
            // Verificar si ya existe una ubicación con el mismo nombre
            $stmt_check = $conn->prepare("SELECT id FROM ubicaciones WHERE nombre = ?");
            $stmt_check->bind_param("s", $nombre);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                throw new Exception("Ya existe una ubicación registrada con el nombre: $nombre");
            }
            $stmt_check->close();
            
            // Insertar la ubicación
            $stmt = $conn->prepare("INSERT INTO ubicaciones(nombre, tipo, dependencia_id, direccion, responsable, telefono, observaciones, activo, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
            $stmt->bind_param("ssissss", 
                $nombre,
                $tipo,
                $dependencia_id,
                $direccion,
                $responsable,
                $telefono,
                $observaciones
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error al registrar la ubicación: " . $stmt->error);
            }
            $ubicacion_id = $conn->insert_id;
            $stmt->close();
            
            // Registrar en auditoría
            $cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'SISTEMA';
            $stmt_auditoria = $conn->prepare("INSERT INTO auditoria(usuario_ceduid, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, 'Registro', ?, ?, NOW())");
            $detalle = "Registro de Ubicación: $nombre (Tipo: $tipo)";
            $stmt_auditoria->bind_param("sss", $cedula_usuario, $ubicacion_id, $detalle);
            $stmt_auditoria->execute();
            $stmt_auditoria->close();
            
            $conn->commit();
            
            $mensaje = "Ubicación '$nombre' registrada correctamente.";
            $tipo_mensaje = "success";
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener dependencias para el dropdown
$dependencias = [];
try {
    $result_dependencias = $conn->query("SELECT id, nombre FROM dependencias WHERE activo = 1 ORDER BY nombre");
    if ($result_dependencias) {
        $dependencias = $result_dependencias->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    // Si la tabla no existe
    $mensaje .= "\nNota: La tabla de dependencias no está disponible aún.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Ubicación - Sistema INTI</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/estilos_sistema.css">
    <link rel="stylesheet" href="css/material-design-iconic-font.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1 style="font-weight:900; font-family:montserrat; color:#ff6600; font-size:40px; padding:20px; text-align:left; font-size:50px;">
            <i class="zmdi zmdi-pin"></i> 
            Registrar <span style="font-weight:700; color:black;">Ubicación</span>
        </h1>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $tipo_mensaje == 'error' ? 'danger' : ($tipo_mensaje == 'info' ? 'warning' : 'success'); ?>" style="margin: 20px;">
                <?= htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario para Registrar Ubicación -->
        <form id="form-registrar-ubicacion" method="POST" action="" class="section-container">
            <input type="hidden" name="accion" value="registrar_ubicacion">
            
            <h4 class="section-title">Datos de la Ubicación</h4>
            
            <!-- Nombre y Tipo -->
            <div class="field-row">
                <div class="field-col">
                    <label for="nombre" class="field-label required">Nombre de la Ubicación</label>
                    <input type="text" name="nombre" id="nombre" 
                           placeholder="Ej: Sala de Reuniones de Rectoría, Laboratorio de Computación" 
                           maxlength="150" class="form-control" required />
                </div>
                <div class="field-col">
                    <label for="tipo" class="field-label required">Tipo de Ubicación</label>
                    <select name="tipo" id="tipo" class="form-control" required>
                        <option value="">Seleccione un tipo...</option>
                        <option value="pnf">Programa Nacional de Formación (PNF)</option>
                        <option value="sede">Sede</option>
                        <option value="edificio">Edificio</option>
                        <option value="piso">Piso/Nivel</option>
                        <option value="oficina">Oficina</option>
                        <option value="aula">Aula</option>
                        <option value="laboratorio">Laboratorio</option>
                        <option value="sala_reunion">Sala de Reuniones</option>
                        <option value="area_comun">Área Común</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
            </div>
            
            <!-- Dependencia y Dirección -->
            <div class="field-row">
                <div class="field-col">
                    <label for="dependencia_id" class="field-label">Dependencia</label>
                    <select name="dependencia_id" id="dependencia_id" class="form-control">
                        <option value="">Seleccione una dependencia (opcional)...</option>
                        <?php foreach ($dependencias as $dep): ?>
                            <option value="<?= $dep['id']; ?>"><?= htmlspecialchars($dep['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($dependencias)): ?>
                        <small style="color: #999;">No hay dependencias registradas</small>
                    <?php endif; ?>
                </div>
                <div class="field-col">
                    <label for="direccion" class="field-label">Dirección/Referencia</label>
                    <input type="text" name="direccion" id="direccion" 
                           placeholder="Ej: Primer piso, edificio principal" 
                           maxlength="255" class="form-control" />
                </div>
            </div>
            
            <!-- Responsable y Teléfono -->
            <div class="field-row">
                <div class="field-col">
                    <label for="responsable" class="field-label">Responsable</label>
                    <input type="text" name="responsable" id="responsable" 
                           placeholder="Nombre del responsable de la ubicación" 
                           maxlength="150" class="form-control" />
                </div>
                <div class="field-col">
                    <label for="telefono" class="field-label">Teléfono</label>
                    <input type="tel" name="telefono" id="telefono" 
                           placeholder="Ej: 0251-1234567" 
                           maxlength="20" class="form-control" />
                </div>
            </div>
            
            <!-- Observaciones -->
            <div class="field-row">
                <div class="field-col" style="flex: 100%;">
                    <label for="observaciones" class="field-label">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="3" 
                              placeholder="Información adicional relevante sobre la ubicación..." 
                              class="form-control" style="width: 100%;"></textarea>
                </div>
            </div>
            
            <!-- Botones -->
            <div class="button-container">
                <button type="reset" class="btn btn-secondary">
                    <i class="zmdi zmdi-refresh"></i> Limpiar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="zmdi zmdi-save"></i> Registrar Ubicación
                </button>
            </div>
        </form>
        
        <!-- Información adicional -->
        <div class="section-container" style="background-color: #fff3e0;">
            <h4 class="section-title" style="color: #ff6600;">
                <i class="zmdi zmdi-info-outline"></i> Información sobre las Ubicaciones
            </h4>
            <ul style="margin: 0; padding-left: 20px; color: #333;">
                <li style="margin-bottom: 8px;"><strong>Tipos de Ubicación:</strong>
                    <ul style="margin-top: 5px;">
                        <li><em>PNF:</em> Programa Nacional de Formación (unidades académicas)</li>
                        <li><em>Sede:</em> Sede principal o extensiones universitarias</li>
                        <li><em>Edificio:</em> Edificios del campus universitario</li>
                        <li><em>Piso/Nivel:</em> Niveles dentro de un edificio</li>
                        <li><em>Oficina:</em> Oficinas administrativas o académicas</li>
                        <li><em>Aula:</em> Salas de clases</li>
                        <li><em>Laboratorio:</em> Laboratorios de investigación o prácticas</li>
                        <li><em>Sala de Reuniones:</em> Espacios para reuniones</li>
                    </ul>
                </li>
                <li style="margin-bottom: 8px;"><strong>Dependencia:</strong> Vincula la ubicación a una dependencia específica (opcional).</li>
                <li style="margin-bottom: 8px;"><strong>Obligatoriedad:</strong> Los campos marcados con asterisco (*) son obligatorios.</li>
                <li><strong>Uso:</strong> Las ubicaciones se utilizan para registrar la ubicación física de los bienes nacionales.</li>
            </ul>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script src="js/jquery-3.1.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        // Validación del formulario
        document.getElementById('form-registrar-ubicacion').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            const tipo = document.getElementById('tipo').value;
            
            if (!nombre) {
                e.preventDefault();
                alert('Por favor, ingrese el nombre de la ubicación.');
                document.getElementById('nombre').focus();
                return false;
            }
            
            if (!tipo) {
                e.preventDefault();
                alert('Por favor, seleccione el tipo de ubicación.');
                document.getElementById('tipo').focus();
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
