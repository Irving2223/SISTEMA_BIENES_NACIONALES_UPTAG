<?php
// Verificar autenticación y comenzar sesión
session_start();

// Si no está logueado, redirigir
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['loggeado'] !== true) {
    header('Location: Loggin.php?error=Debe+iniciar+sesi%C3%B1n+para+acceder');
    exit;
}

require_once 'conexion.php';

$mensaje = '';
$tipo_mensaje = '';

// Funciones helper
function tablaExiste($conn, $nombre_tabla) {
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($nombre_tabla) . "'");
    return $result && $result->num_rows > 0;
}

function obtenerColumnas($conn, $tabla) {
    $columnas = [];
    $result = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tabla) . "`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columnas[] = $row['Field'];
        }
    }
    return $columnas;
}

function columnaExiste($conn, $tabla, $columna) {
    $columnas = obtenerColumnas($conn, $tabla);
    return in_array($columna, $columnas);
}

// Agregar columnas faltantes si no existen
try {
    if (tablaExiste($conn, 'ubicaciones')) {
        if (!columnaExiste($conn, 'ubicaciones', 'responsable')) {
            $conn->query("ALTER TABLE `ubicaciones` ADD COLUMN `responsable` VARCHAR(200) DEFAULT NULL AFTER `descripcion`");
        }
        if (!columnaExiste($conn, 'ubicaciones', 'telefono')) {
            $conn->query("ALTER TABLE `ubicaciones` ADD COLUMN `telefono` VARCHAR(50) DEFAULT NULL AFTER `responsable`");
        }
        if (!columnaExiste($conn, 'ubicaciones', 'email')) {
            $conn->query("ALTER TABLE `ubicaciones` ADD COLUMN `email` VARCHAR(100) DEFAULT NULL AFTER `telefono`");
        }
    }
} catch (Exception $e) {
    // Ignorar errores al agregar columnas
}

// Procesar actualización de responsable desde la tabla
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_responsable') {
    try {
        $conn->begin_transaction();
        
        $id = (int)($_POST['id'] ?? 0);
        $responsable = trim($_POST['responsable'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if ($id <= 0) {
            throw new Exception("ID de ubicación inválido.");
        }
        
        $columnas = obtenerColumnas($conn, 'ubicaciones');
        
        // Construir consulta dinámicamente según las columnas existentes
        $sql = "UPDATE ubicaciones SET";
        $params = [];
        $types = "";
        $primera = true;
        
        if (in_array('responsable', $columnas)) {
            $sql .= " responsable = ?";
            $params[] = $responsable;
            $types .= "s";
            $primera = false;
        }
        
        if (in_array('telefono', $columnas)) {
            $sql .= ($primera ? " " : ", ") . "telefono = ?";
            $params[] = $telefono;
            $types .= "s";
            $primera = false;
        }
        
        if (in_array('email', $columnas)) {
            $sql .= ($primera ? " " : ", ") . "email = ?";
            $params[] = $email;
            $types .= "s";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        $types .= "i";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar el responsable: " . $stmt->error);
        }
        $stmt->close();
        
        $conn->commit();
        
        $mensaje = "Responsable actualizado correctamente.";
        $tipo_mensaje = "success";
        
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener todas las ubicaciones para la tabla
$todas_ubicaciones = [];
$total_ubicaciones = 0;
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$registros_por_pagina = 15;
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

if (tablaExiste($conn, 'ubicaciones')) {
    $columnas = obtenerColumnas($conn, 'ubicaciones');
    
    // Contar total de registros con/sin búsqueda
    $sql_count = "SELECT COUNT(*) as total FROM ubicaciones";
    if (!empty($busqueda)) {
        $sql_count .= " WHERE nombre LIKE '%" . $conn->real_escape_string($busqueda) . "%'";
    }
    $result_count = $conn->query($sql_count);
    if ($result_count && $row = $result_count->fetch_assoc()) {
        $total_ubicaciones = (int)$row['total'];
    }
    
    // Calcular offset
    $offset = ($pagina_actual - 1) * $registros_por_pagina;
    $total_paginas = (int)ceil($total_ubicaciones / $registros_por_pagina);
    
    // Asegurar que página actual sea válida
    if ($pagina_actual > $total_paginas && $total_paginas > 0) {
        $pagina_actual = $total_paginas;
        $offset = ($pagina_actual - 1) * $registros_por_pagina;
    }
    
    // Construir consulta según columnas existentes
    $campos = ['id', 'dependencia_id', 'nombre'];
    if (in_array('descripcion', $columnas)) $campos[] = 'descripcion';
    if (in_array('responsable', $columnas)) $campos[] = 'responsable';
    if (in_array('telefono', $columnas)) $campos[] = 'telefono';
    if (in_array('email', $columnas)) $campos[] = 'email';
    if (in_array('activo', $columnas)) $campos[] = 'activo';
    
    $sql = "SELECT " . implode(', ', $campos) . " FROM ubicaciones";
    if (!empty($busqueda)) {
        $sql .= " WHERE nombre LIKE '%" . $conn->real_escape_string($busqueda) . "%'";
    }
    $sql .= " ORDER BY nombre ASC LIMIT " . $registros_por_pagina . " OFFSET " . $offset;
    $result = $conn->query($sql);
    if ($result) {
        $todas_ubicaciones = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Definir variables para usar en la vista
$total_paginas = isset($total_paginas) ? $total_paginas : 1;

// Obtener dependencias para mostrar nombre
$dependencias = [];
if (tablaExiste($conn, 'dependencias')) {
    $result = $conn->query("SELECT id, nombre FROM dependencias WHERE activo = 1 ORDER BY nombre");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $dependencias[$row['id']] = $row['nombre'];
        }
    }
}

// Tipos de ubicación
$tipos_ubicacion = [
    'PNF' => 'PNF',
    'Sede' => 'Sede',
    'Edificio' => 'Edificio',
    'Piso' => 'Piso',
    'Oficina' => 'Oficina',
    'Aula' => 'Aula',
    'Laboratorio' => 'Laboratorio',
    'Otro' => 'Otro'
];

// Verificar si tiene columnas extras
$columnas_ubicaciones = obtenerColumnas($conn, 'ubicaciones');
$tiene_responsable = in_array('responsable', $columnas_ubicaciones);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Lugares y Dependencias - Sistema INTI</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/estilos_sistema.css">
    <link rel="stylesheet" href="css/material-design-iconic-font.min.css">
    <link href="assets/img/LOGO INTI.png" rel="icon">
    
    <style>
        * {
            box-sizing: border-box;
        }
        
        html, body {
            font-family: montserrat;
            font-weight: 500;
        }
        
        .hero-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 25px;
            color: white;
        }
        
        .hero-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 900;
            font-size: 28px;
            margin: 0;
        }
        
        .hero-header p {
            opacity: 0.9;
            margin-top: 5px;
            font-size: 14px;
        }
        
        .section-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #ff6600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ff6600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-title i {
            font-size: 20px;
        }
        
        /* Stats */
        .stats-container {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .stat-box {
            flex: 1;
            min-width: 150px;
            background-color: #fff3e0;
            border: 2px solid #ff6600;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-box h3 {
            margin: 0;
            color: #e65100;
            font-size: 24px;
            font-weight: 800;
        }
        
        .stat-box p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 12px;
            font-weight: 600;
        }
        
        /* Table Styles */
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }
        
        thead th {
            background-color: #ff6600;
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-weight: 700;
            font-size: 13px;
            white-space: nowrap;
        }
        
        tbody td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            vertical-align: middle;
        }
        
        tbody tr:hover {
            background-color: #fafafa;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        tbody tr:nth-child(even):hover {
            background-color: #f0f0f0;
        }
        
        /* Badges */
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .status-activo {
            background-color: #28a745;
            color: white;
        }
        
        .status-inactivo {
            background-color: #dc3545;
            color: white;
        }
        
        .tipo-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .tipo-pnf { background-color: #2196f3; color: white; }
        .tipo-sede { background-color: #4caf50; color: white; }
        .tipo-edificio { background-color: #ff9800; color: white; }
        .tipo-piso { background-color: #9c27b0; color: white; }
        .tipo-oficina { background-color: #00bcd4; color: white; }
        .tipo-aula { background-color: #e91e63; color: white; }
        .tipo-laboratorio { background-color: #795548; color: white; }
        .tipo-otro { background-color: #607d8b; color: white; }
        
        /* Form elements */
        .form-control {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #ff6600;
            box-shadow: 0 0 0 2px rgba(255, 102, 0, 0.2);
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            font-family: inherit;
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
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 11px;
        }
        
        /* Alerts */
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
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
        
        /* Editable fields */
        .editable-field {
            background: white;
            border: 1px solid transparent;
            padding: 5px 8px;
            border-radius: 4px;
            min-width: 100px;
        }
        
        .editable-field:hover {
            border-color: #ddd;
            background: #fafafa;
        }
        
        .editable-field:focus {
            border-color: #ff6600;
            background: white;
            outline: none;
        }
        
        .responsable-input {
            width: 100%;
            max-width: 180px;
        }
        
        .contacto-input {
            width: 100%;
            max-width: 120px;
        }
        
        .empty-text {
            color: #999;
            font-style: italic;
        }
        
        /* Buttons in table */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .stats-container {
                gap: 10px;
            }
            .stat-box {
                min-width: 120px;
                padding: 12px;
            }
            .stat-box h3 {
                font-size: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .hero-header {
                padding: 20px;
            }
            .hero-header h1 {
                font-size: 22px;
            }
            .section-container {
                padding: 15px;
            }
            .stats-container {
                flex-direction: column;
            }
            .stat-box {
                width: 100%;
            }
        }
        
        /* Info box */
        .info-box {
            background-color: #fff3e0;
            border-left: 4px solid #ff6600;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .info-box h5 {
            color: #e65100;
            font-weight: 700;
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        
        .info-box ul {
            margin: 0;
            padding-left: 20px;
            font-size: 13px;
        }
        
        .info-box li {
            margin-bottom: 5px;
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
        
        /* Search Box Styles */
        .search-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            flex: 1;
            max-width: 400px;
        }
        
        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #ff6600;
            box-shadow: 0 0 0 2px rgba(255, 102, 0, 0.2);
        }
        
        .search-box button {
            padding: 10px 20px;
            background-color: #ff6600;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .search-box button:hover {
            background-color: #e65100;
        }
        
        .search-results-info {
            font-size: 13px;
            color: #666;
            padding: 10px 15px;
            background-color: #fff3e0;
            border-radius: 4px;
            border-left: 4px solid #ff6600;
        }
        
        .clear-search {
            font-size: 13px;
        }
        
        .clear-search a {
            color: #ff6600;
            text-decoration: none;
        }
        
        .clear-search a:hover {
            text-decoration: underline;
        }
        
        .pagination {
            margin: 0;
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
        <div class="hero-header">
            <h1>
                <i class="zmdi zmdi-edit"></i> Editar Lugares y Dependencias
            </h1>
            <p>Administre las ubicaciones y asigne responsables</p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $tipo_mensaje; ?>" style="margin: 20px;">
                <?= htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-container">
            <div class="stat-box">
                <h3><?= $total_ubicaciones; ?></h3>
                <p>TOTAL UBICACIONES</p>
            </div>
            <div class="stat-box">
                <h3><?= count($dependencias); ?></h3>
                <p>DEPENDENCIAS</p>
            </div>
            <div class="stat-box">
                <h3><?= $tiene_responsable ? count(array_filter($todas_ubicaciones, function($u) { return !empty($u['responsable']); })) : 'N/A'; ?></h3>
                <p>CON RESPONSABLE</p>
            </div>
            <div class="stat-box">
                <h3><?= $tiene_responsable ? count(array_filter($todas_ubicaciones, function($u) { return empty($u['responsable']); })) : 'N/A'; ?></h3>
                <p>SIN RESPONSABLE</p>
            </div>
        </div>

        <!-- Tabla de ubicaciones -->
        <div class="section-container">
            <div class="section-title">
                <i class="zmdi zmdi-pin"></i> Todas las Ubicaciones
            </div>
            
            <?php if (!$tiene_responsable): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;">
                <i class="zmdi zmdi-warning"></i> 
                Las columnas necesarias para responsables no existen en la base de datos. 
                Se intentarán agregar automáticamente.
            </div>
            <?php endif; ?>
            
            <form id="form-responsables" method="POST" action="">
                <input type="hidden" name="accion" value="actualizar_responsable">
                <input type="hidden" name="id" id="ubicacion_id" value="">
                <input type="hidden" name="responsable" id="ubicacion_responsable" value="">
                <input type="hidden" name="telefono" id="ubicacion_telefono" value="">
                <input type="hidden" name="email" id="ubicacion_email" value="">
            </form>
            
            <!-- Buscador -->
            <div class="search-container">
                <form method="GET" action="" class="search-box">
                    <input type="text" name="buscar" placeholder="Buscar ubicación por nombre..." value="<?= htmlspecialchars($busqueda); ?>" autofocus>
                    <button type="submit"><i class="zmdi zmdi-search"></i> Buscar</button>
                    <?php if (!empty($busqueda)): ?>
                    <a href="?pagina=1" class="btn btn-default clear-search">Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php if (!empty($busqueda)): ?>
            <div class="search-results-info">
                <i class="zmdi zmdi-search"></i> 
                <?php if (count($todas_ubicaciones) > 0): ?>
                    Se encontraron <?= count($todas_ubicaciones); ?> resultado(s) para "<strong><?= htmlspecialchars($busqueda); ?></strong>"
                <?php else: ?>
                    No se encontraron resultados para "<strong><?= htmlspecialchars($busqueda); ?></strong>"
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
            <div class="pagination-container">
                <span class="pagination-info">
                    Mostrando <?= min(($pagina_actual - 1) * $registros_por_pagina + 1, $total_ubicaciones); ?> - 
                    <?= min($pagina_actual * $registros_por_pagina, $total_ubicaciones); ?> 
                    de <?= $total_ubicaciones; ?> registros
                </span>
                <ul class="pagination">
                    <?php if ($pagina_actual > 1): ?>
                    <li>
                        <a href="?pagina=<?= $pagina_actual - 1; ?>" aria-label="Anterior">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <?php if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - 2 && $i <= $pagina_actual + 2)): ?>
                        <li class="<?= $i == $pagina_actual ? 'active' : ''; ?>">
                            <a href="?pagina=<?= $i; ?>"><?= $i; ?></a>
                        </li>
                        <?php elseif ($i == $pagina_actual - 3 || $i == $pagina_actual + 3): ?>
                        <li class="disabled"><span>...</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($pagina_actual < $total_paginas): ?>
                    <li>
                        <a href="?pagina=<?= $pagina_actual + 1; ?>" aria-label="Siguiente">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="table-wrapper">
                <table id="tabla-ubicaciones">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">ID</th>
                            <th style="min-width: 180px;">Nombre</th>
                            <th style="min-width: 150px;">Dependencia</th>
                            <?php if ($tiene_responsable): ?>
                            <th style="min-width: 180px;">Responsable</th>
                            <th style="width: 120px;">Teléfono</th>
                            <th style="width: 140px;">Email</th>
                            <?php else: ?>
                            <th style="min-width: 180px;">Responsable</th>
                            <th style="width: 120px;">Teléfono</th>
                            <th style="width: 140px;">Email</th>
                            <?php endif; ?>
                            <th style="width: 80px; text-align: center;">Estatus</th>
                            <th style="width: 80px; text-align: center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todas_ubicaciones as $ubic): ?>
                        <tr data-id="<?= $ubic['id']; ?>">
                            <td class="text-center"><strong><?= $ubic['id']; ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($ubic['nombre'] ?? 'N/A'); ?></strong>
                                <?php if (!empty($ubic['descripcion'])): ?>
                                    <br><small style="color: #666;"><?= htmlspecialchars($ubic['descripcion']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $dep_nombre = '-';
                                    if (!empty($ubic['dependencia_id']) && isset($dependencias[$ubic['dependencia_id']])) {
                                        $dep_nombre = $dependencias[$ubic['dependencia_id']];
                                    }
                                    echo htmlspecialchars($dep_nombre);
                                ?>
                            </td>
                            <?php if ($tiene_responsable): ?>
                            <td>
                                <input type="text" 
                                       class="form-control responsable-input editable-field responsable-<?= $ubic['id']; ?>" 
                                       data-field="responsable"
                                       data-id="<?= $ubic['id']; ?>"
                                       value="<?= htmlspecialchars($ubic['responsable'] ?? ''); ?>"
                                       placeholder="Sin asignar"
                                       onchange="actualizarCampo(<?= $ubic['id']; ?>, 'responsable', this.value)">
                            </td>
                            <td>
                                <input type="text" 
                                       class="form-control contacto-input editable-field telefono-<?= $ubic['id']; ?>" 
                                       data-field="telefono"
                                       data-id="<?= $ubic['id']; ?>"
                                       value="<?= htmlspecialchars($ubic['telefono'] ?? ''); ?>"
                                       placeholder="Teléfono"
                                       onchange="actualizarCampo(<?= $ubic['id']; ?>, 'telefono', this.value)">
                            </td>
                            <td>
                                <input type="email" 
                                       class="form-control contacto-input editable-field email-<?= $ubic['id']; ?>" 
                                       data-field="email"
                                       data-id="<?= $ubic['id']; ?>"
                                       value="<?= htmlspecialchars($ubic['email'] ?? ''); ?>"
                                       placeholder="Email"
                                       onchange="actualizarCampo(<?= $ubic['id']; ?>, 'email', this.value)">
                            </td>
                            <?php else: ?>
                            <td>
                                <input type="text" 
                                       class="form-control responsable-input editable-field responsable-<?= $ubic['id']; ?>" 
                                       data-field="responsable"
                                       data-id="<?= $ubic['id']; ?>"
                                       value="<?= htmlspecialchars($ubic['responsable'] ?? ''); ?>"
                                       placeholder="Sin asignar"
                                       disabled>
                            </td>
                            <td>
                                <input type="text" 
                                       class="form-control contacto-input editable-field telefono-<?= $ubic['id']; ?>" 
                                       data-field="telefono"
                                       data-id="<?= $ubic['id']; ?>"
                                       value="<?= htmlspecialchars($ubic['telefono'] ?? ''); ?>"
                                       placeholder="Teléfono"
                                       disabled>
                            </td>
                            <td>
                                <input type="email" 
                                       class="form-control contacto-input editable-field email-<?= $ubic['id']; ?>" 
                                       data-field="email"
                                       data-id="<?= $ubic['id']; ?>"
                                       value="<?= htmlspecialchars($ubic['email'] ?? ''); ?>"
                                       placeholder="Email"
                                       disabled>
                            </td>
                            <?php endif; ?>
                            <td class="text-center">
                                <span class="status-badge <?= ($ubic['activo'] ?? 1) == 1 ? 'status-activo' : 'status-inactivo'; ?>">
                                    <?= ($ubic['activo'] ?? 1) == 1 ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($tiene_responsable): ?>
                                <button type="button" class="btn btn-primary btn-sm" 
                                        onclick="guardarResponsable(<?= $ubic['id']; ?>)"
                                        title="Guardar">
                                    <i class="zmdi zmdi-check"></i>
                                </button>
                                <?php else: ?>
                                <button type="button" class="btn btn-primary btn-sm" 
                                        onclick="recargarPagina()"
                                        title="Recargar para activar">
                                    <i class="zmdi zmdi-refresh"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($todas_ubicaciones)): ?>
                        <tr>
                            <td colspan="9" class="text-center" style="padding: 40px; color: #666;">
                                <i class="zmdi zmdi-info" style="font-size: 48px; display: block; margin-bottom: 10px;"></i>
                                No hay ubicaciones registradas aún.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación inferior -->
            <?php if ($total_paginas > 1): ?>
            <div class="pagination-container">
                <span class="pagination-info">
                    Mostrando <?= min(($pagina_actual - 1) * $registros_por_pagina + 1, $total_ubicaciones); ?> - 
                    <?= min($pagina_actual * $registros_por_pagina, $total_ubicaciones); ?> 
                    de <?= $total_ubicaciones; ?> registros
                </span>
                <ul class="pagination">
                    <?php if ($pagina_actual > 1): ?>
                    <li>
                        <a href="?pagina=<?= $pagina_actual - 1; ?>" aria-label="Anterior">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <?php if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - 2 && $i <= $pagina_actual + 2)): ?>
                        <li class="<?= $i == $pagina_actual ? 'active' : ''; ?>">
                            <a href="?pagina=<?= $i; ?>"><?= $i; ?></a>
                        </li>
                        <?php elseif ($i == $pagina_actual - 3 || $i == $pagina_actual + 3): ?>
                        <li class="disabled"><span>...</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($pagina_actual < $total_paginas): ?>
                    <li>
                        <a href="?pagina=<?= $pagina_actual + 1; ?>" aria-label="Siguiente">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <h5><i class="zmdi zmdi-info"></i> Instrucciones</h5>
            <ul>
                <li>Los campos de <strong>Responsable</strong>, <strongTeléfono</strong> y <strong>Email</strong> son editables directamente en la tabla</li>
                <li>Después de modificar un campo, haga clic en el botón <strong>✓</strong> para guardar los cambios</li>
                <?php if (!$tiene_responsable): ?>
                <li>Si las columnas no aparecen, <strong>recargue la página</strong> para que se agreguen automáticamente.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Enlace a gestión completa -->
        <div style="margin-top: 20px; text-align: center;">
            <a href="lugares_dependencias.php" class="btn btn-primary">
                <i class="zmdi zmdi-arrow-left"></i> Volver a Lugares y Dependencias
            </a>
        </div>
    </div>

    <script>
        // Datos de ubicaciones
        let datosUbicaciones = {};
        
        // Inicializar datos
        document.addEventListener('DOMContentLoaded', function() {
            // Recopilar datos iniciales
            const filas = document.querySelectorAll('tbody tr[data-id]');
            filas.forEach(function(fila) {
                const id = fila.dataset.id;
                datosUbicaciones[id] = {
                    responsable: '',
                    telefono: '',
                    email: ''
                };
            });
        });
        
        // Actualizar campo en memoria
        function actualizarCampo(id, campo, valor) {
            if (!datosUbicaciones[id]) {
                datosUbicaciones[id] = {};
            }
            datosUbicaciones[id][campo] = valor;
        }
        
        // Recargar página
        function recargarPagina() {
            window.location.reload();
        }
        
        // Guardar responsable
        function guardarResponsable(id) {
            const datos = datosUbicaciones[id] || {};
            const responsable = document.querySelector('.responsable-' + id).value;
            const telefono = document.querySelector('.telefono-' + id).value;
            const email = document.querySelector('.email-' + id).value;
            
            // Validar que al menos el responsable tenga algo
            if (!responsable.trim()) {
                alert('El nombre del responsable es obligatorio.');
                return;
            }
            
            // Llenar el formulario y enviar
            document.getElementById('ubicacion_id').value = id;
            document.getElementById('ubicacion_responsable').value = responsable;
            document.getElementById('ubicacion_telefono').value = telefono;
            document.getElementById('ubicacion_email').value = email;
            
            document.getElementById('form-responsables').submit();
        }
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