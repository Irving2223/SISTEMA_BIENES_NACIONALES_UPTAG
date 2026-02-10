<?php
include('header.php');
include('conexion.php');

$busqueda_realizada = false;
$resultados_bienes = array();
$mensaje = '';
$tipo_mensaje = '';

// Obtener dependencias
$dependencias = array();
$result_dependencias = $conn->query("SELECT id, nombre, codigo FROM dependencias WHERE activo = 1 ORDER BY nombre");
if ($result_dependencias && $result_dependencias->num_rows > 0) {
    while ($row = $result_dependencias->fetch_assoc()) {
        $dependencias[] = $row;
    }
}

// Obtener ubicaciones con su dependencia
$ubicaciones = array();
$result_ubicaciones = $conn->query("
    SELECT u.id, u.nombre, u.descripcion, d.nombre as dependencia_nombre, d.id as dependencia_id 
    FROM ubicaciones u 
    LEFT JOIN dependencias d ON u.dependencia_id = d.id 
    WHERE u.activo = 1 
    ORDER BY d.nombre, u.nombre
");
if ($result_ubicaciones && $result_ubicaciones->num_rows > 0) {
    while ($row = $result_ubicaciones->fetch_assoc()) {
        $ubicaciones[] = $row;
    }
}

// Organizar ubicaciones por dependencia
$ubicaciones_por_dependencia = array();
foreach ($ubicaciones as $ubicacion) {
    $dep_id = $ubicacion['dependencia_id'] ?? 0;
    if (!isset($ubicaciones_por_dependencia[$dep_id])) {
        $ubicaciones_por_dependencia[$dep_id] = array();
    }
    $ubicaciones_por_dependencia[$dep_id][] = $ubicacion;
}

// Procesar búsqueda
if (isset($_GET['buscar'])) {
    $busqueda_realizada = true;
    
    $filtro_dependencia = $_GET['dependencia'] ?? '';
    $filtro_ubicacion = $_GET['ubicacion'] ?? '';
    $buscar_todo_dependencia = isset($_GET['buscar_todo_dependencia']);
    $buscar_todo_ubicacion = isset($_GET['buscar_todo_ubicacion']);
    $filtro_estatus = $_GET['estatus'] ?? '';
    $filtro_categoria = $_GET['categoria'] ?? '';
    
    try {
        // Construir consulta base
        // Obtenemos la ubicación actual del bien a través del último movimiento
        $sql = "
            SELECT 
                b.id,
                b.codigo_bien_nacional,
                b.codigo_anterior,
                b.descripcion,
                b.marca,
                b.modelo,
                b.serial,
                b.color,
                b.dimensiones,
                b.fecha_incorporacion,
                b.valor_adquisicion,
                b.valor_actual,
                b.vida_util_anos,
                b.estatus_id,
                b.categoria_id,
                b.activo,
                b.observaciones,
                e.nombre AS estatus_nombre,
                c.nombre AS categoria_nombre,
                u.id AS ubicacion_id,
                u.nombre AS ubicacion_nombre,
                u.descripcion AS ubicacion_codigo,
                dep.nombre AS dependencia_nombre
            FROM bienes b
            LEFT JOIN estatus e ON b.estatus_id = e.id
            LEFT JOIN categorias c ON b.categoria_id = c.id
            LEFT JOIN (
                SELECT bien_id, ubicacion_destino_id
                FROM movimientos 
                WHERE (bien_id, id) IN (
                    SELECT bien_id, MAX(id)
                    FROM movimientos 
                    WHERE ubicacion_destino_id IS NOT NULL
                    GROUP BY bien_id
                )
            ) ult_mov ON b.id = ult_mov.bien_id
            LEFT JOIN ubicaciones u ON ult_mov.ubicacion_destino_id = u.id
            LEFT JOIN dependencias dep ON u.dependencia_id = dep.id
            WHERE b.activo = 1
        ";
        
        $params = array();
        $types = '';
        
        // Filtro por dependencia
        if (!empty($filtro_dependencia)) {
            $sql .= " AND u.dependencia_id = ?";
            $params[] = $filtro_dependencia;
            $types .= 'i';
        }
        
        // Filtro por ubicación específica
        if (!empty($filtro_ubicacion)) {
            if ($buscar_todo_ubicacion) {
                // Obtener el código de la ubicación seleccionada
                $ubicacion_codigo = '';
                foreach ($ubicaciones as $ub) {
                    if ($ub['id'] == $filtro_ubicacion) {
                        $ubicacion_codigo = $ub['descripcion'];
                        break;
                    }
                }
                
                if (!empty($ubicacion_codigo)) {
                    $sql .= " AND u.descripcion LIKE ?";
                    $params[] = "$ubicacion_codigo%";
                    $types .= 's';
                } else {
                    $sql .= " AND u.id = ?";
                    $params[] = $filtro_ubicacion;
                    $types .= 'i';
                }
            } else {
                $sql .= " AND u.id = ?";
                $params[] = $filtro_ubicacion;
                $types .= 'i';
            }
        }
        
        // Filtro por estatus
        if (!empty($filtro_estatus)) {
            $sql .= " AND b.estatus_id = ?";
            $params[] = $filtro_estatus;
            $types .= 'i';
        }
        
        // Filtro por categoría
        if (!empty($filtro_categoria)) {
            $sql .= " AND b.categoria_id = ?";
            $params[] = $filtro_categoria;
            $types .= 'i';
        }
        
        $sql .= " ORDER BY b.id DESC LIMIT 200";
        
        // Guardar parámetros para PDF
        $_SESSION['busqueda_params'] = [
            'ubicacion' => $filtro_ubicacion,
            'buscar_todo_ubicacion' => $buscar_todo_ubicacion,
            'estatus' => $filtro_estatus,
            'categoria' => $filtro_categoria,
            'sql' => $sql,
            'params' => $params,
            'types' => $types
        ];
        
        // Ejecutar consulta
        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $resultados_bienes[] = $row;
                    }
                }
                $stmt->close();
            }
        } else {
            $result = $conn->query($sql);
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $resultados_bienes[] = $row;
                }
            }
        }
        
        if (empty($resultados_bienes)) {
            $mensaje = "No se encontraron bienes en la ubicación/dependencia seleccionada";
            $tipo_mensaje = "info";
        }
        
    } catch (Exception $e) {
        $mensaje = "Error al realizar la búsqueda: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener estatus y categorias para filtros
$estatus = array();
$result_estatus = $conn->query("SELECT id, nombre FROM estatus WHERE activo = 1 ORDER BY nombre");
if ($result_estatus && $result_estatus->num_rows > 0) {
    while ($row = $result_estatus->fetch_assoc()) {
        $estatus[] = $row;
    }
}

$categorias = array();
$result_categorias = $conn->query("SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre");
if ($result_categorias && $result_categorias->num_rows > 0) {
    while ($row = $result_categorias->fetch_assoc()) {
        $categorias[] = $row;
    }
}
?>

<style>
    /* Estilos para el diseño moderno de búsqueda por ubicación */
    .hero-search {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        padding: 50px 40px;
        border-radius: 20px;
        margin-bottom: 30px;
        box-shadow: 0 15px 50px rgba(0,0,0,0.3);
        position: relative;
        overflow: hidden;
    }
    
    .hero-search::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,102,0,0.15) 0%, transparent 70%);
        border-radius: 50%;
    }
    
    .hero-title {
        font-family: 'Montserrat', sans-serif;
        font-weight: 900;
        font-size: 2.8rem;
        color: white;
        margin-bottom: 8px;
        text-shadow: 2px 2px 8px rgba(0,0,0,0.3);
        position: relative;
        z-index: 1;
    }
    
    .hero-subtitle {
        color: rgba(255,255,255,0.85);
        font-size: 1.1rem;
        margin-bottom: 35px;
        position: relative;
        z-index: 1;
    }
    
    .search-form {
        position: relative;
        z-index: 1;
    }
    
    .filters-section {
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }
    
    .filters-title {
        font-weight: 800;
        color: #333;
        margin-bottom: 25px;
        font-size: 1.3rem;
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .form-control-filter {
        border-radius: 10px;
        padding: 14px 18px;
        border: 2px solid #e8e8e8;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }
    
    .form-control-filter:focus {
        border-color: #ff6600;
        box-shadow: 0 0 0 3px rgba(255,102,0,0.1);
        outline: none;
    }
    
    .checkbox-card {
        padding: 15px 20px;
        border-radius: 12px;
        border: 2px solid;
        margin-top: 12px;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .checkbox-card-ubicacion {
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        border-color: #2196f3;
    }
    
    .checkbox-card-dependencia {
        background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
        border-color: #ff9800;
    }
    
    .checkbox-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .checkbox-card input[type="checkbox"] {
        width: 22px;
        height: 22px;
        accent-color: #ff6600;
        cursor: pointer;
    }
    
    .checkbox-card label {
        cursor: pointer;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .quick-links {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-top: 20px;
    }
    
    .quick-link {
        background: rgba(255,255,255,0.1);
        color: white;
        padding: 10px 20px;
        border-radius: 25px;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    .quick-link:hover {
        background: rgba(255,255,255,0.2);
        color: white;
        transform: translateY(-2px);
    }
    
    .btn-hero {
        background: linear-gradient(135deg, #ff6600 0%, #ff8533 100%);
        color: white;
        border: none;
        font-weight: 800;
        padding: 18px 45px !important;
        border-radius: 12px;
        font-size: 1.1rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 4px 20px rgba(255,102,0,0.4);
        transition: all 0.3s ease;
    }
    
    .btn-hero:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 35px rgba(255,102,0,0.5);
        background: linear-gradient(135deg, #ff8533 0%, #ff6600 100%);
        color: white;
    }
    
    .btn-filter {
        background: #f5f5f5;
        color: #555;
        border: 2px solid #e0e0e0;
        font-weight: 600;
        border-radius: 10px;
        padding: 14px 28px;
        transition: all 0.3s ease;
    }
    
    .btn-filter:hover {
        background: #e8e8e8;
        color: #333;
        border-color: #ccc;
    }
    
    .btn-apply-filter {
        background: linear-gradient(135deg, #ff6600 0%, #ff8533 100%);
        color: white;
        border: none;
        font-weight: 700;
        border-radius: 10px;
        padding: 14px 35px;
        transition: all 0.3s ease;
    }
    
    .btn-apply-filter:hover {
        background: linear-gradient(135deg, #ff8533 0%, #ff6600 100%);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(255,102,0,0.3);
    }
    
    .location-info {
        font-size: 0.8rem;
        color: #666;
        margin-top: 5px;
        margin-left: 32px;
    }
    
    .section-header {
        background: linear-gradient(135deg, #ff6600 0%, #ff8533 100%);
        color: white;
        padding: 20px 25px;
        border-radius: 12px;
        margin-bottom: 20px;
    }
    
    .section-header h3 {
        margin: 0;
        font-weight: 800;
    }
    
    @media (max-width: 768px) {
        .hero-title {
            font-size: 2rem;
        }
    }
</style>

<div class="container">
    <!-- Título principal -->
    <h1 style="font-family:montserrat; font-weight:900; color:#ff6600; padding:20px; text-align:left; font-size:50px;">
        <i class="zmdi zmdi-pin"></i> Búsqueda por <span style="font-weight:700; color:black;">Ubicación</span>
    </h1>

    <!-- Búsqueda por Ubicación -->
    <div class="hero-search">
        <h2 class="hero-title">
            <i class="zmdi zmdi-map"></i> Buscar por Dependencia y Ubicación
        </h2>
        <p class="hero-subtitle">Seleccione una dependencia y/o ubicación para ver los bienes registrados en ese lugar</p>
        
        <div class="quick-links">
            <a href="buscar.php" class="quick-link">
                <i class="zmdi zmdi-search"></i> Búsqueda General
            </a>
        </div>
    </div>

    <!-- Filtros de Búsqueda -->
    <div class="filters-section">
        <h3 class="filters-title">
            <i class="zmdi zmdi-tune" style="color: #ff6600;"></i> Seleccionar Ubicación
        </h3>
        
        <form method="GET">
            <div class="field-row" style="align-items: flex-end;">
                <!-- Dependencia -->
                <div class="field-col">
                    <label for="dependencia" class="field-label" style="font-weight: 600; margin-bottom: 8px; color: #555;">
                        <i class="zmdi zmdi-account-balance" style="color: #9c27b0;"></i> Dependencia
                    </label>
                    <select id="dependencia" name="dependencia" class="form-control form-control-filter" onchange="cargarUbicaciones()">
                        <option value="">Seleccione una dependencia</option>
                        <?php foreach ($dependencias as $dep): ?>
                            <option value="<?= $dep['id']; ?>" <?= ($_GET['dependencia'] ?? '') == $dep['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($dep['codigo'] . ' - ' . $dep['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Ubicación -->
                <div class="field-col">
                    <label for="ubicacion" class="field-label" style="font-weight: 600; margin-bottom: 8px; color: #555;">
                        <i class="zmdi zmdi-pin" style="color: #ff9800;"></i> Ubicación
                    </label>
                    <select id="ubicacion" name="ubicacion" class="form-control form-control-filter">
                        <option value="">Todas las ubicaciones</option>
                        <?php if (!empty($_GET['dependencia'])): ?>
                            <?php foreach ($ubicaciones_por_dependencia[$_GET['dependencia']] ?? array() as $ub): ?>
                                <option value="<?= $ub['id']; ?>" <?= ($_GET['ubicacion'] ?? '') == $ub['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($ub['descripcion'] . ' - ' . $ub['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            
            <!-- Opciones de búsqueda -->
            <div class="field-row" style="margin-top: 20px;">
                <div class="field-col" style="flex: 1;">
                    <div class="checkbox-card checkbox-card-dependencia">
                        <input type="checkbox" id="buscar_todo_dependencia" name="buscar_todo_dependencia" value="1" <?= isset($_GET['buscar_todo_dependencia']) ? 'checked' : ''; ?>>
                        <label for="buscar_todo_dependencia" style="color: #e65100;">
                            <i class="zmdi zmdi-folder-outline"></i> Incluir todas las ubicaciones de esta dependencia
                        </label>
                    </div>
                    <p class="location-info">Buscará en todas las ubicaciones que pertenezcan a la dependencia seleccionada</p>
                </div>
            </div>
            
            <div class="field-row" style="margin-top: 10px;">
                <div class="field-col" style="flex: 1;">
                    <div class="checkbox-card checkbox-card-ubicacion">
                        <input type="checkbox" id="buscar_todo_ubicacion" name="buscar_todo_ubicacion" value="1" <?= isset($_GET['buscar_todo_ubicacion']) ? 'checked' : ''; ?>>
                        <label for="buscar_todo_ubicacion" style="color: #1565c0;">
                            <i class="zmdi zmdi-folder-outline"></i> Incluir sub-ubicaciones
                        </label>
                    </div>
                    <p class="location-info">Buscará en todas las sub-ubicaciones dentro de la ubicación seleccionada</p>
                </div>
            </div>
            
            <!-- Filtros adicionales -->
            <div class="section-header" style="margin-top: 25px;">
                <h3><i class="zmdi zmdi-filter-list"></i> Filtros Adicionales</h3>
            </div>
            
            <div class="field-row" style="margin-top: 20px;">
                <div class="field-col">
                    <label for="estatus" class="field-label" style="font-weight: 600; margin-bottom: 8px; color: #555;">
                        <i class="zmdi zmdi-check-circle" style="color: #4caf50;"></i> Estatus
                    </label>
                    <select id="estatus" name="estatus" class="form-control form-control-filter">
                        <option value="">Todos los estatus</option>
                        <?php foreach ($estatus as $e): ?>
                            <option value="<?= $e['id']; ?>" <?= ($_GET['estatus'] ?? '') == $e['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($e['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-col">
                    <label for="categoria" class="field-label" style="font-weight: 600; margin-bottom: 8px; color: #555;">
                        <i class="zmdi zmdi-view-module" style="color: #2196f3;"></i> Categoría
                    </label>
                    <select id="categoria" name="categoria" class="form-control form-control-filter">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= $c['id']; ?>" <?= ($_GET['categoria'] ?? '') == $c['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($c['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Botones -->
            <div class="field-row" style="margin-top: 25px;">
                <div class="field-col" style="flex: 0 0 auto; display: flex; gap: 10px;">
                    <a href="buscar_por_ubicacion.php" class="btn btn-filter">
                        <i class="zmdi zmdi-refresh"></i> Limpiar
                    </a>
                    <button type="submit" name="buscar" value="1" class="btn btn-hero">
                        <i class="zmdi zmdi-search"></i> Buscar Bienes
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($busqueda_realizada): ?>
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje == 'error' ? 'danger' : 'info'; ?> section-container">
                <?= $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($resultados_bienes)): ?>
            <!-- Botón Exportar PDF -->
            <div class="section-container" style="background-color: #e3f2fd; border: 2px solid #2196f3;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <h4 style="margin: 0; color: #1976d2;">
                        <i class="zmdi zmdi-download"></i> Resultados: <?= count($resultados_bienes); ?> bienes encontrados
                        <?php if (!empty($_GET['dependencia'])): ?>
                            <?php foreach ($dependencias as $dep): ?>
                                <?php if ($dep['id'] == $_GET['dependencia']): ?>
                                    en <?= htmlspecialchars($dep['nombre']); ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </h4>
                    <form action="generar_reporte_inventario.php" method="POST" target="_blank">
                        <input type="hidden" name="busqueda_personalizada" value="1">
                        <input type="hidden" name="dependencia" value="<?= htmlspecialchars($_GET['dependencia'] ?? ''); ?>">
                        <input type="hidden" name="ubicacion" value="<?= htmlspecialchars($_GET['ubicacion'] ?? ''); ?>">
                        <input type="hidden" name="buscar_todo_dependencia" value="<?= isset($_GET['buscar_todo_dependencia']) ? '1' : ''; ?>">
                        <input type="hidden" name="buscar_todo_ubicacion" value="<?= isset($_GET['buscar_todo_ubicacion']) ? '1' : ''; ?>">
                        <input type="hidden" name="estatus" value="<?= htmlspecialchars($_GET['estatus'] ?? ''); ?>">
                        <input type="hidden" name="categoria" value="<?= htmlspecialchars($_GET['categoria'] ?? ''); ?>">
                        <button type="submit" class="btn btn-primary" style="background-color: #2196f3;">
                            <i class="zmdi zmdi-file-pdf"></i> Descargar PDF
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Resultados de búsqueda -->
            <div class="section-container">
                <h2 class="section-title">Bienes Encontrados</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Código</th>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Código Anterior</th>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Descripción</th>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Marca/Modelo</th>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Serial</th>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Color</th>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Dimensiones</th>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Valor Adquisición</th>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Valor Actual</th>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Vida Útil</th>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Ubicación</th>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Dependencia</th>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Categoría</th>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Estatus</th>
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados_bienes as $bien): ?>
                                <tr>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee; color:#ff6600; font-weight:600;">
                                        <?= htmlspecialchars($bien['codigo_bien_nacional'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <?= htmlspecialchars($bien['codigo_anterior'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <?= htmlspecialchars($bien['descripcion'] ?? 'Sin descripción'); ?>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <?= htmlspecialchars(($bien['marca'] ?? '') . ' ' . ($bien['modelo'] ?? '')); ?>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <?= htmlspecialchars($bien['serial'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <?= htmlspecialchars($bien['color'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <?= htmlspecialchars($bien['dimensiones'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <?= isset($bien['valor_adquisicion']) ? number_format($bien['valor_adquisicion'], 2, ',', '.') : 'N/A'; ?>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <?= isset($bien['valor_actual']) ? number_format($bien['valor_actual'], 2, ',', '.') : 'N/A'; ?>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <?= htmlspecialchars($bien['vida_util_anos'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <?php if (!empty($bien['ubicacion_nombre'])): ?>
                                            <span style="background:#e3f2fd; color:#1565c0; padding:3px 8px; border-radius:4px; font-size:0.8rem;">
                                                <?= htmlspecialchars($bien['ubicacion_codigo'] . ' - ' . $bien['ubicacion_nombre']); ?>
                                            </span>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <?= htmlspecialchars($bien['dependencia_nombre'] ?? 'Sin dependencia'); ?>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <?= htmlspecialchars($bien['categoria_nombre'] ?? 'Sin categoría'); ?>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <span style="background:<?= $bien['estatus_id'] == 4 ? '#f8d7da' : '#d4edda' ?>; 
                                              color:<?= $bien['estatus_id'] == 4 ? '#721c24' : '#155724' ?>; 
                                              padding:4px 8px; border-radius:4px; font-size:0.8rem; font-weight:600;">
                                            <?= htmlspecialchars($bien['estatus_nombre'] ?? 'Sin estatus'); ?>
                                        </span>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <?= htmlspecialchars($bien['fecha_incorporacion'] ?? 'N/A'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Datos de ubicaciones por dependencia para JavaScript
const ubicacionesPorDependencia = <?= json_encode($ubicaciones_por_dependencia); ?>;

function cargarUbicaciones() {
    const dependenciaId = document.getElementById('dependencia').value;
    const ubicacionSelect = document.getElementById('ubicacion');
    
    // Limpiar opciones actuales
    ubicacionSelect.innerHTML = '<option value="">Todas las ubicaciones</option>';
    
    if (dependenciaId && ubicacionesPorDependencia[dependenciaId]) {
        ubicacionesPorDependencia[dependenciaId].forEach(function(ubicacion) {
            const option = document.createElement('option');
            option.value = ubicacion.id;
            option.textContent = ubicacion.descripcion + ' - ' + ubicacion.nombre;
            ubicacionSelect.appendChild(option);
        });
    }
}

// Cargar ubicaciones si hay una dependencia seleccionada al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($_GET['dependencia'])): ?>
    cargarUbicaciones();
    <?php endif; ?>
});
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