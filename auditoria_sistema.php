<?php
include("header.php");

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'Administrador') {
    header("Location: index.php");
    exit();
}

// Incluir conexión a la base de datos
include("conexion.php");

// Variables para manejar el estado
$mensaje_error = '';
$mensaje_exito = '';
$registros = [];
$usuarios = [];

// Obtener lista de usuarios para el filtro
// Primero verificar si la tabla usuarios existe
$check_usuarios = $conn->query("SHOW TABLES LIKE 'usuarios'");
$usuarios_tabla_existe = $check_usuarios && $check_usuarios->num_rows > 0;

$col_nombre = 'nombre';
$col_apellido = 'apellido';

$usuarios = [];
if ($usuarios_tabla_existe) {
    // Verificar estructura de la tabla
    $check_columns = $conn->prepare("SHOW COLUMNS FROM usuarios LIKE 'nombres'");
    $check_columns->execute();
    $has_nombres = $check_columns->get_result()->num_rows > 0;
    $check_columns->close();
    
    $col_nombre = $has_nombres ? 'nombres' : 'nombre';
    $col_apellido = $has_nombres ? 'apellidos' : 'apellido';
    
    $sql_usuarios = "SELECT cedula, {$col_nombre}, {$col_apellido} FROM usuarios ORDER BY {$col_nombre}";
    $result_usuarios = $conn->query($sql_usuarios);
    if ($result_usuarios) {
        while ($row = $result_usuarios->fetch_assoc()) {
            $usuarios[] = $row;
        }
    } else {
        $mensaje_error = "Error al cargar usuarios: " . $conn->error;
    }
} else {
    $mensaje_error = "La tabla 'usuarios' no existe en la base de datos.";
}

// Verificar si la tabla auditoria existe
$check_auditoria = $conn->query("SHOW TABLES LIKE 'auditoria'");
$auditoria_existe = $check_auditoria && $check_auditoria->num_rows > 0;

// Procesar filtros cuando se envía el formulario
$fecha_desde = $_POST['fecha_desde'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_hasta = $_POST['fecha_hasta'] ?? date('Y-m-d');
$cedula_usuario = $_POST['cedula_usuario'] ?? 'todos';

// Construir consulta base solo si la tabla auditoria existe
if ($auditoria_existe) {
    $sql_base = "SELECT b.*, u.{$col_nombre}, u.{$col_apellido} 
                 FROM auditoria b 
                 LEFT JOIN usuarios u ON b.usuario_cedula = u.cedula 
                 WHERE 1=1";
} else {
    $sql_base = "SELECT 'N/A' as id, 'Tabla auditoria no existe' as accion, 'N/A' as detalle, NOW() as fecha_accion";
    $mensaje_error = "La tabla 'auditoria' no existe en la base de datos.";
}

// Añadir condiciones según filtros
$params = [];
$types = "";

if ($auditoria_existe && !empty($fecha_desde)) {
    $sql_base .= " AND DATE(b.fecha_accion) >= ?";
    $params[] = $fecha_desde;
    $types .= "s";
}

if ($auditoria_existe && !empty($fecha_hasta)) {
    $sql_base .= " AND DATE(b.fecha_accion) <= ?";
    $params[] = $fecha_hasta;
    $types .= "s";
}

if ($auditoria_existe && $cedula_usuario !== 'todos' && !empty($cedula_usuario)) {
    $sql_base .= " AND b.usuario_cedula = ?";
    $params[] = $cedula_usuario;
    $types .= "s";
}

if ($auditoria_existe) {
    $sql_base .= " ORDER BY b.fecha_accion DESC";
}

// Ejecutar consulta con filtros
$stmt = mysqli_prepare($conn, $sql_base);
if ($stmt && !empty($types)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} elseif ($stmt) {
    // Caso sin parámetros (cuando no hay filtros aplicados)
    $result = mysqli_query($conn, $sql_base);
} else {
    $mensaje_error = "Error en la consulta: " . mysqli_error($conn);
    $result = false;
}

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $registros[] = $row;
    }
    if (empty($registros) && isset($_POST['buscar'])) {
        $mensaje_error = "No se encontraron registros que coincidan con los criterios de búsqueda.";
    }
}
?>

<!-- Contenido de la página -->
<div class="container">

    <!-- Título principal -->
	<h1 style="font-family:montserrat; font-weight:900; color:green; padding:20px; text-align:left; font-size:50px;"><i class="zmdi zmdi-file-plus"></i> Auditoría del <span style="font-weight:700; color:black;">Sistema</span></h1>

    <!-- Formulario de Filtros -->
    <div class="section-container">
        <h3 class="section-title">Filtrar Registros</h3>
        <form method="POST" action="" class="search-form">
            <div class="field-row">
                <div class="field-col">
                    <label class="field-label required">Fecha Desde</label>
                    <input type="date" name="fecha_desde" class="form-control" 
                           value="<?php echo htmlspecialchars($fecha_desde); ?>" required />
                </div>
                <div class="field-col">
                    <label class="field-label required">Fecha Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control" 
                           value="<?php echo htmlspecialchars($fecha_hasta); ?>" required />
                </div>
            </div>
            <div class="field-row">
                <div class="field-col">
                    <label class="field-label">Usuario</label>
                    <select name="cedula_usuario" class="form-control">
                        <option value="todos" <?php echo ($cedula_usuario === 'todos') ? 'selected' : ''; ?>>Todos los Usuarios</option>
                        <?php foreach ($usuarios as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['cedula']); ?>" 
                                    <?php echo ($cedula_usuario === $user['cedula']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(trim($user['nombre'] . ' ' . $user['apellido']) . ' (' . $user['cedula'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-col" style="align-self:flex-end;">
                    <button type="submit" name="buscar" class="btn btn-primary">
                        <i class="zmdi zmdi-search"></i> Buscar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($mensaje_error): ?>
        <div class="alert alert-danger mt-3"><?php echo $mensaje_error; ?></div>
    <?php endif; ?>

    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success mt-3"><?php echo $mensaje_exito; ?></div>
    <?php endif; ?>

<!-- Tabla de Resultados -->
<?php if (!empty($registros) && isset($_POST['buscar'])): ?>
<div class="section-container">
    <h3 class="section-title">Registros Encontrados</h3>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Tabla Afectada</th>
                    <th>Registro Afectado</th>
                    <th>Detalle</th>
                    <th>Fecha y Hora</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $registro): ?>
                <tr>
                    <td><?php echo htmlspecialchars($registro['id']); ?></td>
                    <td>
                        <?php 
                        $nombre_completo = trim(($registro[$col_nombre] ?? '') . ' ' . ($registro[$col_apellido] ?? ''));
                        echo !empty($nombre_completo) ? htmlspecialchars($nombre_completo) : 'Sistema';
                        ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($registro['usuario_cedula']); ?></small>
                    </td>
                    <td>
                        <span class="badge 
                            <?php 
                                $accion = strtoupper($registro['accion']);
                                switch($accion) {
                                    case 'REGISTRO': 
                                    case 'INSERT': echo 'bg-success'; break;
                                    case 'EDICION': 
                                    case 'UPDATE': echo 'bg-warning text-dark'; break;
                                    case 'CONSULTA': 
                                    case 'SELECT': echo 'bg-info'; break;
                                    case 'INICIO DE SESIÓN': 
                                    case 'LOGIN': echo 'bg-primary'; break;
                                    case 'ELIMINAR': 
                                    case 'DELETE': echo 'bg-danger'; break;
                                    default: echo 'bg-secondary'; break;
                                }
                            ?>">
                            <?php echo htmlspecialchars($registro['accion']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($registro['tabla_afectada']); ?></td>
                    <td><?php echo htmlspecialchars($registro['datos_nuevos'] ?? $registro['datos_anteriores'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($registro['datos_nuevos'] ?? ''); ?></td>
                    <td><?php echo date('d/m/Y H:i:s', strtotime($registro['fecha_accion'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div>

<?php include("footer.php"); ?>

<script src="./js/jquery-3.1.1.min.js"></script>
<script src="./js/sweetalert2.min.js"></script>
<script src="./js/bootstrap.min.js"></script>
<script src="./js/material.min.js"></script>
<script src="./js/ripples.min.js"></script>
<script src="./js/jquery.mCustomScrollbar.concat.min.js"></script>
<script src="./js/main.js"></script>
<script>
    $.material.init();

    // Establecer fecha por defecto si no hay valores
    document.addEventListener('DOMContentLoaded', function() {
        const fechaDesde = document.querySelector('input[name="fecha_desde"]');
        const fechaHasta = document.querySelector('input[name="fecha_hasta"]');
        
        if (!fechaDesde.value) {
            fechaDesde.value = '<?php echo date('Y-m-d', strtotime('-7 days')); ?>';
        }
        if (!fechaHasta.value) {
            fechaHasta.value = '<?php echo date('Y-m-d'); ?>';
        }
    });
</script>