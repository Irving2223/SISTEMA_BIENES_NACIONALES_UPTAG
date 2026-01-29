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
$sql_usuarios = "SELECT cedula, nombre, apellido FROM usuarios ORDER BY nombre";
$result_usuarios = mysqli_query($conn, $sql_usuarios);
if ($result_usuarios) {
    while ($row = mysqli_fetch_assoc($result_usuarios)) {
        $usuarios[] = $row;
    }
} else {
    $mensaje_error = "Error al cargar usuarios.";
}

// Procesar filtros cuando se envía el formulario
$fecha_desde = $_POST['fecha_desde'] ?? date('Y-m-d', strtotime('-7 days'));
$fecha_hasta = $_POST['fecha_hasta'] ?? date('Y-m-d');
$cedula_usuario = $_POST['cedula_usuario'] ?? 'todos';

// Construir consulta base
$sql_base = "SELECT b.*, u.nombre, u.apellido 
             FROM bitacora b 
             LEFT JOIN usuarios u ON b.cedula_usuario = u.cedula 
             WHERE 1=1";

// Añadir condiciones según filtros
$params = [];
$types = "";

if (!empty($fecha_desde)) {
    $sql_base .= " AND DATE(b.fecha_accion) >= ?";
    $params[] = $fecha_desde;
    $types .= "s";
}

if (!empty($fecha_hasta)) {
    $sql_base .= " AND DATE(b.fecha_accion) <= ?";
    $params[] = $fecha_hasta;
    $types .= "s";
}

if ($cedula_usuario !== 'todos' && !empty($cedula_usuario)) {
    $sql_base .= " AND b.cedula_usuario = ?";
    $params[] = $cedula_usuario;
    $types .= "s";
}

$sql_base .= " ORDER BY b.fecha_accion DESC";

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
                                <?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellido'] . ' (' . $user['cedula'] . ')'); ?>
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
                    <td><?php echo htmlspecialchars($registro['id_bitacora']); ?></td>
                    <td>
                        <?php 
                        $nombre_completo = trim(($registro['nombre'] ?? '') . ' ' . ($registro['apellido'] ?? ''));
                        echo !empty($nombre_completo) ? htmlspecialchars($nombre_completo) : 'Sistema';
                        ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($registro['cedula_usuario']); ?></small>
                    </td>
                    <td>
                        <span class="badge 
                            <?php 
                                switch($registro['accion']) {
                                    case 'Registro': echo 'bg-success'; break;
                                    case 'Edicion': echo 'bg-warning text-dark'; break;
                                    case 'Consulta': echo 'bg-info'; break;
                                    default: echo 'bg-secondary'; break;
                                }
                            ?>">
                            <?php echo htmlspecialchars($registro['accion']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($registro['tabla_afectada']); ?></td>
                    <td><?php echo htmlspecialchars($registro['registro_afectado']); ?></td>
                    <td><?php echo htmlspecialchars($registro['detalle']); ?></td>
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