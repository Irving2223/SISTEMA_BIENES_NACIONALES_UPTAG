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
$total_registros = 0;
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$registros_por_pagina = 15;

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

// Mantener filtros en GET para paginación
$get_params = [];
if (!empty($fecha_desde)) $get_params[] = 'fecha_desde=' . urlencode($fecha_desde);
if (!empty($fecha_hasta)) $get_params[] = 'fecha_hasta=' . urlencode($fecha_hasta);
if ($cedula_usuario !== 'todos') $get_params[] = 'cedula_usuario=' . urlencode($cedula_usuario);
$query_string = !empty($get_params) ? '&' . implode('&', $get_params) : '';

// Función para obtener color del badge según la acción
function obtenerColorAccion($accion) {
    $colores = [
        'INSERT' => '#28a745',      // Verde - Nuevo registro
        'REGISTRO' => '#28a745',    // Verde
        'UPDATE' => '#ffc107',      // Amarillo - Modificación
        'EDICION' => '#ffc107',     // Amarillo
        'DELETE' => '#dc3545',      // Rojo - Eliminación
        'ELIMINAR' => '#dc3545',    // Rojo
        'SELECT' => '#17a2b8',      // Azul claro - Consulta
        'CONSULTA' => '#17a2b8',    // Azul claro
        'LOGIN' => '#007bff',       // Azul - Inicio de sesión
        'INICIO DE SESIÓN' => '#007bff', // Azul
    ];
    return $colores[strtoupper($accion)] ?? '#6c757d'; // Gris por defecto
}

// Construir consulta base solo si la tabla auditoria existe
if ($auditoria_existe) {
    // Primero contar total de registros para paginación
    $sql_count = "SELECT COUNT(*) as total 
                  FROM auditoria b 
                  LEFT JOIN usuarios u ON b.usuario_cedula = u.cedula 
                  WHERE 1=1";
    
    // Añadir condiciones de filtros para el conteo
    if (!empty($fecha_desde)) {
        $sql_count .= " AND DATE(b.fecha_accion) >= '" . $conn->real_escape_string($fecha_desde) . "'";
    }
    if (!empty($fecha_hasta)) {
        $sql_count .= " AND DATE(b.fecha_accion) <= '" . $conn->real_escape_string($fecha_hasta) . "'";
    }
    if ($cedula_usuario !== 'todos' && !empty($cedula_usuario)) {
        $sql_count .= " AND b.usuario_cedula = '" . $conn->real_escape_string($cedula_usuario) . "'";
    }
    
    $result_count = $conn->query($sql_count);
    if ($result_count && $row = $result_count->fetch_assoc()) {
        $total_registros = (int)$row['total'];
    }
    
    // Calcular paginación
    $total_paginas = (int)ceil($total_registros / $registros_por_pagina);
    $offset = ($pagina_actual - 1) * $registros_por_pagina;
    
    // Asegurar que página actual sea válida
    if ($pagina_actual > $total_paginas && $total_paginas > 0) {
        $pagina_actual = $total_paginas;
        $offset = ($pagina_actual - 1) * $registros_por_pagina;
    }
    
    $sql_base = "SELECT b.*, u.{$col_nombre}, u.{$col_apellido} 
                 FROM auditoria b 
                 LEFT JOIN usuarios u ON b.usuario_cedula = u.cedula 
                 WHERE 1=1";
} else {
    $sql_base = "SELECT 'N/A' as id, 'Tabla auditoria no existe' as accion, 'N/A' as detalle, NOW() as fecha_accion";
    $mensaje_error = "La tabla 'auditoria' no existe en la base de datos.";
    $total_paginas = 1;
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
    $sql_base .= " ORDER BY b.fecha_accion DESC LIMIT " . $registros_por_pagina . " OFFSET " . $offset;
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
                                <?php echo htmlspecialchars(trim($user[$col_nombre] . ' ' . $user[$col_apellido]) . ' (' . $user['cedula'] . ')'); ?>
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
<?php if ((!empty($registros) || isset($_POST['buscar'])) && $auditoria_existe): ?>
<div class="section-container">
    <h3 class="section-title">Registros Encontrados (<?= $total_registros; ?>)</h3>
    
    <!-- Paginación superior -->
    <?php if ($total_paginas > 1): ?>
    <div class="pagination-container">
        <span class="pagination-info">
            Mostrando <?= min(($pagina_actual - 1) * $registros_por_pagina + 1, $total_registros); ?> - 
            <?= min($pagina_actual * $registros_por_pagina, $total_registros); ?> 
            de <?= $total_registros; ?> registros
        </span>
        <ul class="pagination">
            <?php if ($pagina_actual > 1): ?>
            <li>
                <a href="?pagina=<?= $pagina_actual - 1; ?><?= $query_string; ?>" aria-label="Anterior">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <?php if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - 2 && $i <= $pagina_actual + 2)): ?>
                <li class="<?= $i == $pagina_actual ? 'active' : ''; ?>">
                    <a href="?pagina=<?= $i; ?><?= $query_string; ?>"><?= $i; ?></a>
                </li>
                <?php elseif ($i == $pagina_actual - 3 || $i == $pagina_actual + 3): ?>
                <li class="disabled"><span>...</span></li>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($pagina_actual < $total_paginas): ?>
            <li>
                <a href="?pagina=<?= $pagina_actual + 1; ?><?= $query_string; ?>" aria-label="Siguiente">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div style="overflow-x: auto; margin: 0 -15px; padding: 0 15px;">
        <table style="width: 100%; border-collapse: collapse; min-width: 900px;">
            <thead>
                <tr style="background: #333; color: white;">
                    <th style="padding: 12px 10px; text-align: left; font-size: 0.85rem; white-space: nowrap;">ID</th>
                    <th style="padding: 12px 10px; text-align: left; font-size: 0.85rem; white-space: nowrap;">Usuario</th>
                    <th style="padding: 12px 10px; text-align: left; font-size: 0.85rem; white-space: nowrap;">Acción</th>
                    <th style="padding: 12px 10px; text-align: left; font-size: 0.85rem; white-space: nowrap;">Tabla</th>
                    <th style="padding: 12px 10px; text-align: left; font-size: 0.85rem; white-space: nowrap;">Registro</th>
                    <th style="padding: 12px 10px; text-align: left; font-size: 0.85rem; white-space: nowrap;">Detalle</th>
                    <th style="padding: 12px 10px; text-align: left; font-size: 0.85rem; white-space: nowrap;">Fecha/Hora</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $registro): ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px; font-size: 0.85rem; white-space: nowrap;"><?php echo htmlspecialchars($registro['id']); ?></td>
                    <td style="padding: 10px; font-size: 0.85rem;">
                        <?php 
                        $nombre_completo = trim(($registro[$col_nombre] ?? '') . ' ' . ($registro[$col_apellido] ?? ''));
                        echo !empty($nombre_completo) ? htmlspecialchars($nombre_completo) : 'Sistema';
                        ?>
                        <br><small style="color: #666; font-size: 0.75rem;"><?php echo htmlspecialchars($registro['usuario_cedula']); ?></small>
                    </td>
                    <td style="padding: 10px; font-size: 0.85rem; white-space: nowrap;">
                        <?php $badge_color = obtenerColorAccion($registro['accion']); ?>
                        <span style="background: <?php echo $badge_color; ?>; color: white; padding: 2px 8px; border-radius: 3px; font-size: 0.75rem; white-space: nowrap;">
                            <?php echo htmlspecialchars($registro['accion']); ?>
                        </span>
                    </td>
                    <td style="padding: 10px; font-size: 0.85rem; white-space: nowrap;"><?php echo htmlspecialchars($registro['tabla_afectada']); ?></td>
                    <td style="padding: 10px; font-size: 0.85rem; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($registro['datos_nuevos'] ?? $registro['datos_anteriores'] ?? 'N/A'); ?>">
                        <?php echo htmlspecialchars($registro['datos_nuevos'] ?? $registro['datos_anteriores'] ?? 'N/A'); ?>
                    </td>
                    <td style="padding: 10px; font-size: 0.85rem; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($registro['datos_nuevos'] ?? ''); ?>">
                        <?php echo htmlspecialchars($registro['datos_nuevos'] ?? ''); ?>
                    </td>
                    <td style="padding: 10px; font-size: 0.85rem; white-space: nowrap;"><?php echo date('d/m/Y H:i', strtotime($registro['fecha_accion'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Paginación inferior -->
    <?php if ($total_paginas > 1): ?>
    <div class="pagination-container">
        <span class="pagination-info">
            Mostrando <?= min(($pagina_actual - 1) * $registros_por_pagina + 1, $total_registros); ?> - 
            <?= min($pagina_actual * $registros_por_pagina, $total_registros); ?> 
            de <?= $total_registros; ?> registros
        </span>
        <ul class="pagination">
            <?php if ($pagina_actual > 1): ?>
            <li>
                <a href="?pagina=<?= $pagina_actual - 1; ?><?= $query_string; ?>" aria-label="Anterior">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <?php if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - 2 && $i <= $pagina_actual + 2)): ?>
                <li class="<?= $i == $pagina_actual ? 'active' : ''; ?>">
                    <a href="?pagina=<?= $i; ?><?= $query_string; ?>"><?= $i; ?></a>
                </li>
                <?php elseif ($i == $pagina_actual - 3 || $i == $pagina_actual + 3): ?>
                <li class="disabled"><span>...</span></li>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($pagina_actual < $total_paginas): ?>
            <li>
                <a href="?pagina=<?= $pagina_actual + 1; ?><?= $query_string; ?>" aria-label="Siguiente">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

</div>

<!-- Estilos de paginación -->
<style>
    .pagination-container {
        margin-top: 20px;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        padding: 15px 0;
    }
    
    .pagination-info {
        font-size: 13px;
        color: #666;
    }
    
    .pagination > li > a,
    .pagination > li > span {
        color: #ff6600;
        border: 1px solid #ff6600;
        padding: 8px 12px;
        margin: 0 2px;
        border-radius: 4px;
        font-weight: 500;
    }
    
    .pagination > li > a:hover,
    .pagination > li > span:hover {
        background-color: #ff6600;
        color: white;
        border-color: #ff6600;
    }
    
    .pagination > .active > a,
    .pagination > .active > span,
    .pagination > .active > a:hover,
    .pagination > .active > span:hover {
        background-color: #ff6600;
        border-color: #ff6600;
        color: white;
    }
    
    .pagination > .disabled > a,
    .pagination > .disabled > span {
        color: #999;
        border-color: #ddd;
        cursor: not-allowed;
    }
</style>

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