<?php
// INICIO - Todo lo de aquí debe estar al principio sin espacios
ob_start(); // Capturar cualquier output

require_once 'conexion.php';

// Verificar autenticación ANTES de cualquier output
if (!isset($_SESSION) || session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['loggeado']) || $_SESSION['usuario']['loggeado'] !== true) {
    ob_end_clean(); // Limpiar buffer
    header('Location: Loggin.php?error=Debe+iniciar+sesi%C3%B3n+para+acceder');
    exit;
}

// Variables de paginación y búsqueda
$mensaje = '';
$tipo_mensaje = '';
$resultados = [];
$total_registros = 0;
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$registros_por_pagina = 15;

// Término de búsqueda
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$columnas_categorias = ['id', 'nombre', 'codigo', 'descripcion', 'activo']; // columnas por defecto
$where_clause = '';

// Obtener categorías con paginación y búsqueda PHP
try {
    // Verificar columnas existentes en la tabla categorias (dentro del try)
    $result_cols = $conn->query("SHOW COLUMNS FROM categorias");
    if ($result_cols) {
        while ($col = $result_cols->fetch_assoc()) {
            $columnas_categorias[] = $col['Field'];
        }
    }
    
    // Construir consulta con búsqueda solo en columnas existentes
    if (!empty($busqueda) && !empty($columnas_categorias)) {
        $busqueda_escaped = $conn->real_escape_string($busqueda);
        $condiciones = [];
        
        // Buscar en columnas que existen
        if (in_array('nombre', $columnas_categorias)) {
            $condiciones[] = "nombre LIKE '%{$busqueda_escaped}%'";
        }
        if (in_array('codigo', $columnas_categorias)) {
            $condiciones[] = "codigo LIKE '%{$busqueda_escaped}%'";
        }
        if (in_array('descripcion', $columnas_categorias)) {
            $condiciones[] = "descripcion LIKE '%{$busqueda_escaped}%'";
        }
        if (in_array('denominacion', $columnas_categorias)) {
            $condiciones[] = "denominacion LIKE '%{$busqueda_escaped}%'";
        }
        
        if (!empty($condiciones)) {
            $where_clause = " WHERE " . implode(' OR ', $condiciones);
        }
    }
    
    // Contar total de registros (con o sin filtro)
    $sql_count = "SELECT COUNT(*) as total FROM categorias" . $where_clause;
    $result_count = $conn->query($sql_count);
    if ($result_count) {
        $row_count = $result_count->fetch_assoc();
        $total_registros = (int)$row_count['total'];
    }
    
    // Calcular paginación
    $total_paginas = (int)ceil($total_registros / $registros_por_pagina);
    $offset = ($pagina_actual - 1) * $registros_por_pagina;
    
    // Asegurar que página actual sea válida
    if ($pagina_actual > $total_paginas && $total_paginas > 0) {
        $pagina_actual = $total_paginas;
        $offset = ($pagina_actual - 1) * $registros_por_pagina;
    }
    
    // Consulta principal con LIMIT y OFFSET
    $sql = "SELECT * FROM categorias" . $where_clause . " ORDER BY id ASC LIMIT " . $registros_por_pagina . " OFFSET " . $offset;
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $resultados[] = $row;
        }
    }
    
    if (empty($resultados) && !empty($busqueda)) {
        $mensaje = "No se encontraron categorías que coincidan con \"" . htmlspecialchars($busqueda) . "\".";
        $tipo_mensaje = "info";
    } elseif (empty($resultados)) {
        $mensaje = "No hay categorías registradas en el sistema.";
        $tipo_mensaje = "info";
    }
} catch (Exception $e) {
    $mensaje = "Error al cargar categorías: " . $e->getMessage();
    $tipo_mensaje = "error";
} catch (Throwable $e) {
    $mensaje = "Error al cargar categorías: " . $e->getMessage();
    $tipo_mensaje = "error";
}

// Obtener TODOS los resultados para el PDF (sin límite)
$todos_resultados = [];
if (!empty($busqueda) && empty($mensaje)) {
    try {
        $sql_todos = "SELECT * FROM categorias" . $where_clause . " ORDER BY id ASC";
        $result_todos = $conn->query($sql_todos);
        if ($result_todos) {
            while ($row = $result_todos->fetch_assoc()) {
                $todos_resultados[] = $row;
            }
        }
    } catch (Exception $e) {
        // Ignorar error en PDF
    } catch (Throwable $e) {
        // Ignorar error en PDF
    }
}

// Convertir resultados a JSON para JavaScript (solo los de la página actual)
$categorias_json = json_encode($resultados, JSON_HEX_APOS | JSON_HEX_QUOT);

// Limpiar buffer antes de enviar headers
ob_end_clean();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías - Sistema INTI</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/estilos_sistema.css">
    <link rel="stylesheet" href="css/material-design-iconic-font.min.css">
    <link href="assets/img/LOGO INTI.png" rel="icon">
    
    <style>
        .page-header {
            font-weight: 900;
            font-family: montserrat;
            color: #ff6600;
            font-size: 50px;
            padding: 20px;
            text-align: left;
        }
        
        .page-header span {
            font-weight: 700;
            color: black;
        }
        
        .section-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #ff6600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ff6600;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #ff6600;
            box-shadow: 0 0 0 2px rgba(255, 102, 0, 0.2);
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: #ff6600;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #e65100;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-default {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .btn-default:hover {
            background-color: #e2e6ea;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .stats-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            flex: 1;
            background-color: #fff3e0;
            border: 2px solid #ff6600;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-box h3 {
            margin: 0;
            color: #e65100;
            font-size: 28px;
        }
        
        .stat-box p {
            margin: 5px 0 0 0;
            color: #666;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        thead th {
            background-color: #ff6600;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        
        tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
        }
        
        tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .status-activo {
            background-color: #28a745;
            color: white;
        }
        
        .status-inactivo {
            background-color: #dc3545;
            color: white;
        }
        
        .info-box {
            background-color: #fff3e0;
            border-left: 4px solid #ff6600;
            padding: 15px;
            margin-top: 20px;
        }
        
        .search-container {
            margin-bottom: 20px;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 15px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 8px;
            transition: border-color 0.3s;
        }
        
        .search-input:focus {
            border-color: #ff6600;
            outline: none;
        }
        
        /* Pagination Styles */
        .pagination-container {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
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
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1 class="page-header">
            <i class="zmdi zmdi-folder"></i> 
            Categorías <span>del Sistema</span>
        </h1>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>" style="margin: 20px;">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-container">
            <div class="stat-box">
                <h3 id="total-mostrado" style="font-weight: 900;"><?php echo $total_registros; ?></h3>
                <p>Total Categorías</p>
            </div>
        </div>

        <!-- Buscador PHP -->
        <div class="section-container">
            <h4 class="section-title"><i class="zmdi zmdi-search"></i> Buscar Categorías</h4>
            <form method="GET" action="" class="search-container">
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="buscar" class="search-input" 
                           placeholder="Buscar por código, nombre, descripción..." 
                           value="<?php echo htmlspecialchars($busqueda ?? ''); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="zmdi zmdi-search"></i> Buscar
                    </button>
                    <?php if (!empty($busqueda)): ?>
                    <a href="?pagina=1" class="btn btn-default" style="border: 1px solid #ddd; border-radius: 4px; padding: 10px 15px; color: #666; display: flex; align-items: center; text-decoration: none;">
                        <i class="zmdi zmdi-close"></i> Limpiar
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Resultados -->
        <div class="section-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h4 class="section-title" style="margin-bottom: 0; border-bottom: none;">
                    <i class="zmdi zmdi-view-list"></i> Listado de Categorías (<?php echo count($resultados); ?> de <?php echo $total_registros; ?>)
                </h4>
                
                <!-- Botón para generar PDF con todos los resultados -->
                <form id="form-pdf" action="reporte_categorias.php" method="POST" target="_blank">
                    <input type="hidden" name="buscar" value="<?php echo htmlspecialchars($busqueda ?? ''); ?>">
                    <input type="hidden" name="resultados_json" id="resultados-pdf" value="<?php echo htmlspecialchars(json_encode(!empty($todos_resultados) ? $todos_resultados : $resultados)); ?>">
                    <button type="submit" class="btn btn-success" <?php echo (empty($todos_resultados) && empty($resultados)) ? 'disabled' : ''; ?>>
                        <i class="zmdi zmdi-download"></i> Descargar PDF
                    </button>
                </form>
            </div>
            
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
                        <a href="?pagina=<?= $pagina_actual - 1; ?>&buscar=<?php echo urlencode($busqueda ?? ''); ?>" aria-label="Anterior">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <?php if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - 2 && $i <= $pagina_actual + 2)): ?>
                        <li class="<?= $i == $pagina_actual ? 'active' : ''; ?>">
                            <a href="?pagina=<?= $i; ?>&buscar=<?php echo urlencode($busqueda ?? ''); ?>"><?= $i; ?></a>
                        </li>
                        <?php elseif ($i == $pagina_actual - 3 || $i == $pagina_actual + 3): ?>
                        <li class="disabled"><span>...</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($pagina_actual < $total_paginas): ?>
                    <li>
                        <a href="?pagina=<?= $pagina_actual + 1; ?>&buscar=<?php echo urlencode($busqueda ?? ''); ?>" aria-label="Siguiente">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div style="overflow-x: auto;">
                <table id="tabla-categorias">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Estatus</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-categorias">
                        <?php foreach ($resultados as $cat): ?>
                        <tr class="categoria-row" 
                            data-id="<?php echo htmlspecialchars($cat['id'] ?? ''); ?>"
                            data-codigo="<?php echo htmlspecialchars($cat['codigo'] ?? $cat['id'] ?? ''); ?>"
                            data-nombre="<?php echo htmlspecialchars($cat['nombre'] ?? $cat['denominacion'] ?? ''); ?>"
                            data-descripcion="<?php echo htmlspecialchars($cat['descripcion'] ?? ''); ?>">
                            <td><?php echo htmlspecialchars($cat['id'] ?? 'N/A'); ?></td>
                            <td><strong><?php echo htmlspecialchars($cat['codigo'] ?? $cat['id'] ?? 'N/A'); ?></strong></td>
                            <td><?php echo htmlspecialchars($cat['nombre'] ?? $cat['denominacion'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($cat['descripcion'] ?? 'Sin descripción'); ?></td>
                            <td>
                                <?php 
                                    $estatus = isset($cat['activo']) ? ($cat['activo'] == 1 ? 'Activo' : 'Inactivo') : 'Activo';
                                    $badge_class = $estatus == 'Activo' ? 'status-activo' : 'status-inactivo';
                                ?>
                                <span class="status-badge <?php echo $badge_class; ?>">
                                    <?php echo htmlspecialchars($estatus); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <p id="sin-resultados" style="display: none; text-align: center; padding: 20px; color: #666;">
                No se encontraron categorías que coincidan con la búsqueda.
            </p>
            
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
                        <a href="?pagina=<?= $pagina_actual - 1; ?>&buscar=<?php echo urlencode($busqueda ?? ''); ?>" aria-label="Anterior">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <?php if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - 2 && $i <= $pagina_actual + 2)): ?>
                        <li class="<?= $i == $pagina_actual ? 'active' : ''; ?>">
                            <a href="?pagina=<?= $i; ?>&buscar=<?php echo urlencode($busqueda ?? ''); ?>"><?= $i; ?></a>
                        </li>
                        <?php elseif ($i == $pagina_actual - 3 || $i == $pagina_actual + 3): ?>
                        <li class="disabled"><span>...</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($pagina_actual < $total_paginas): ?>
                    <li>
                        <a href="?pagina=<?= $pagina_actual + 1; ?>&buscar=<?php echo urlencode($busqueda ?? ''); ?>" aria-label="Siguiente">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <!-- Información -->
        <div class="info-box">
            <h5><i class="zmdi zmdi-info-outline"></i> Información</h5>
            <div>
                <li><strong>Búsqueda:</strong> Escriba en el campo de arriba para filtrar cualquier dato de la tabla.</li>
                <li><strong>PDF:</strong> Descargue un reporte con las categorías visibles o use el buscador primero.</li>
                <li><strong>Total:</strong> El sistema tiene <strong><?php echo $total_registros; ?></strong> categorías registradas.</li>
                        </div>
        </div>
    </div>

    <script>
        // El buscador ahora usa PHP - no necesita JavaScript para filtrar
    </script>

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
</body>
</html>
