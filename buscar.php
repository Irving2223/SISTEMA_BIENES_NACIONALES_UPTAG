<?php
include('header.php');
include('conexion.php');

$busqueda_realizada = false;
$resultados_bienes = array();
$mensaje = '';
$tipo_mensaje = '';

// Obtener filtros para los select
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

// Variable para mostrar historial
$mostrar_historial = !isset($_GET['buscar']);
$historial_bienes = array();

// Variable para historial de movimientos de los bienes encontrados
$movimientos_por_bien = array();

// Obtener historial completo de bienes nacionales
if ($mostrar_historial) {
    try {
        // Consulta simplificada - obtener bienes directamente
        $sql_historial = "
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
                b.ubicacion_id,
                e.nombre AS estatus_nombre,
                c.nombre AS categoria_nombre
            FROM bienes b
            LEFT JOIN estatus e ON b.estatus_id = e.id
            LEFT JOIN categorias c ON b.categoria_id = c.id
            WHERE b.activo = 1
            ORDER BY b.id DESC
            LIMIT 500
        ";
        
        $result_historial = $conn->query($sql_historial);
        if ($result_historial && $result_historial->num_rows > 0) {
            // Obtener ubicaciones para cada bien
            while ($row = $result_historial->fetch_assoc()) {
                // Buscar ubicación directamente en la tabla bienes
                $ubicacion_id = $row['ubicacion_id'] ?? 0;
                if ($ubicacion_id > 0) {
                    $stmt_ubic = $conn->prepare("SELECT u.nombre, u.descripcion, u.responsable, u.telefono, u.email, d.nombre AS dependencia_nombre FROM ubicaciones u LEFT JOIN dependencias d ON u.dependencia_id = d.id WHERE u.id = ?");
                    $stmt_ubic->bind_param("i", $ubicacion_id);
                    $stmt_ubic->execute();
                    $result_ubic = $stmt_ubic->get_result();
                    if ($result_ubic && $result_ubic->num_rows > 0) {
                        $ubic = $result_ubic->fetch_assoc();
                        $row['ubicacion_nombre'] = $ubic['nombre'] ?? '';
                        $row['ubicacion_codigo'] = $ubic['descripcion'] ?? '';
                        $row['dependencia_nombre'] = $ubic['dependencia_nombre'] ?? '';
                        $row['responsable'] = $ubic['responsable'] ?? '';
                        $row['responsable_telefono'] = $ubic['telefono'] ?? '';
                        $row['responsable_email'] = $ubic['email'] ?? '';
                    } else {
                        $row['ubicacion_nombre'] = '';
                        $row['ubicacion_codigo'] = '';
                        $row['dependencia_nombre'] = '';
                        $row['responsable'] = '';
                        $row['responsable_telefono'] = '';
                        $row['responsable_email'] = '';
                    }
                    $stmt_ubic->close();
                } else {
                    $row['ubicacion_nombre'] = '';
                    $row['ubicacion_codigo'] = '';
                    $row['dependencia_nombre'] = '';
                    $row['responsable'] = '';
                    $row['responsable_telefono'] = '';
                    $row['responsable_email'] = '';
                }
                $historial_bienes[] = $row;
            }
        }
    } catch (Exception $e) {
        $mensaje = "Error al cargar el historial: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Procesar búsqueda
if (isset($_GET['buscar'])) {
    $busqueda_realizada = true;
    
    $termino = trim($_GET['termino_busqueda'] ?? '');
    $codigo_bien = trim($_GET['codigo_bien'] ?? '');
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
        
        // Filtro por término de búsqueda
        if (!empty($termino)) {
            $sql .= " AND (
                b.codigo_bien_nacional LIKE ? 
                OR b.descripcion LIKE ?
                OR b.marca LIKE ?
                OR b.modelo LIKE ?
                OR b.serial LIKE ?
                OR b.observaciones LIKE ?
            )";
            $like_termino = "%$termino%";
            $params[] = $like_termino;
            $params[] = $like_termino;
            $params[] = $like_termino;
            $params[] = $like_termino;
            $params[] = $like_termino;
            $params[] = $like_termino;
            $types .= 'ssssss';
        }
        
        // Filtro por código de bien nacional (búsqueda exacta)
        if (!empty($codigo_bien)) {
            $sql .= " AND b.codigo_bien_nacional = ?";
            $params[] = $codigo_bien;
            $types .= 's';
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
            'termino' => $termino,
            'codigo_bien' => $codigo_bien,
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
            if (!empty($termino)) {
                $mensaje = "No se encontraron bienes con el término de búsqueda: '$termino'";
            } else {
                $mensaje = "No se encontraron bienes con los filtros seleccionados";
            }
            $tipo_mensaje = "info";
        } else {
            // Obtener movimientos para cada bien encontrado
            foreach ($resultados_bienes as &$bien) {
                $bien_id = $bien['id'];
                $sql_mov = "
                    SELECT 
                        m.*,
                        uo.nombre AS ubicacion_origen_nombre,
                        ud.nombre AS ubicacion_destino_nombre,
                        ro.nombres AS responsable_origen_nombre,
                        ro.apellidos AS responsable_origen_apellido,
                        rd.nombres AS responsable_destino_nombre,
                        rd.apellidos AS responsable_destino_apellido,
                        u.nombres AS usuario_nombres,
                        u.apellidos AS usuario_apellidos
                    FROM movimientos m
                    LEFT JOIN ubicaciones uo ON m.ubicacion_origen_id = uo.id
                    LEFT JOIN ubicaciones ud ON m.ubicacion_destino_id = ud.id
                    LEFT JOIN responsables ro ON m.responsable_origen_id = ro.id
                    LEFT JOIN responsables rd ON m.responsable_destino_id = rd.id
                    LEFT JOIN usuarios u ON m.usuario_registro = u.cedula
                    WHERE m.bien_id = ?
                    ORDER BY m.fecha_movimiento DESC, m.id DESC
                ";
                $stmt_mov = $conn->prepare($sql_mov);
                if ($stmt_mov) {
                    $stmt_mov->bind_param("i", $bien_id);
                    $stmt_mov->execute();
                    $result_mov = $stmt_mov->get_result();
                    $movimientos = array();
                    if ($result_mov && $result_mov->num_rows > 0) {
                        while ($row_mov = $result_mov->fetch_assoc()) {
                            $movimientos[] = $row_mov;
                        }
                    }
                    $movimientos_por_bien[$bien_id] = $movimientos;
                    $stmt_mov->close();
                }
            }
            unset($bien);
        }
        
    } catch (Exception $e) {
        $mensaje = "Error al realizar la búsqueda: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}
?>

<style>
    /* Estilos para el diseño moderno de búsqueda */
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
    
    .input-group-custom {
        display: flex;
        gap: 15px;
        align-items: flex-end;
    }
    
    @media (max-width: 768px) {
        .input-group-custom {
            flex-direction: column;
            align-items: stretch;
        }
    }
    
    .form-control-hero {
        background: rgba(255,255,255,0.98) !important;
        border: none !important;
        border-radius: 12px !important;
        padding: 18px 25px !important;
        font-size: 1rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
    }
    
    .form-control-hero:focus {
        transform: translateY(-3px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.25);
        outline: none;
    }
    
    .form-control-hero::placeholder {
        color: #999;
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
        white-space: nowrap;
    }
    
    .btn-hero:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 35px rgba(255,102,0,0.5);
        background: linear-gradient(135deg, #ff8533 0%, #ff6600 100%);
        color: white;
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
    
    .icon-input {
        position: absolute;
        left: 20px;
        top: 50%;
        transform: translateY(-50%);
        color: #ff6600;
        font-size: 1.3rem;
        z-index: 10;
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
        <i class="zmdi zmdi-search"></i> Búsqueda de <span style="font-weight:700; color:black;">Bienes Nacionales</span>
    </h1>

    <!-- Búsqueda Principal -->
    <div class="hero-search">
        <h2 class="hero-title">
            <i class="zmdi zmdi-search"></i> Buscar Bienes
        </h2>
        <p class="hero-subtitle">Ingrese el código de bien nacional o un término de búsqueda para encontrar bienes en el inventario</p>
        
        <form method="GET" class="search-form">
            <div class="input-group-custom">
                <div class="field-col" style="flex: 1; position: relative;">
                    <i class="zmdi zmdi-barcode icon-input" style="font-size: 1.5rem;"></i>
                    <input type="text" id="codigo_bien" name="codigo_bien" 
                           placeholder="Código de Bien Nacional (Ej: BN-2026-0001)" 
                           value="<?= htmlspecialchars($_GET['codigo_bien'] ?? ''); ?>" 
                           class="form-control form-control-hero" style="padding-left: 55px !important;">
                </div>
                <div class="field-col" style="flex: 2; position: relative;">
                    <i class="zmdi zmdi-search icon-input" style="left: 25px;"></i>
                    <input type="text" id="termino_busqueda" name="termino_busqueda" 
                           placeholder="Buscar por descripción, marca, modelo, serial, observaciones..." 
                           value="<?= htmlspecialchars($_GET['termino_busqueda'] ?? ''); ?>" 
                           class="form-control form-control-hero" style="padding-left: 55px !important;">
                </div>
                <div class="field-col" style="flex: 0 0 auto;">
                    <button type="submit" name="buscar" value="1" class="btn btn-hero">
                        <i class="zmdi zmdi-search"></i> Buscar
                    </button>
                </div>
            </div>
            
            <div class="quick-links">
                <a href="buscar_por_ubicacion.php" class="quick-link">
                    <i class="zmdi zmdi-pin"></i> Buscar por Ubicación
                </a>
            </div>
        </form>
    </div>

    <!-- Filtros Adicionales -->
    <div class="filters-section">
        <h3 class="filters-title">
            <i class="zmdi zmdi-tune" style="color: #ff6600;"></i> Filtros Adicionales
        </h3>
        <form method="GET">
            <div class="field-row" style="align-items: flex-end;">
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
                <div class="field-col" style="flex: 0 0 auto; display: flex; gap: 10px;">
                    <a href="buscar.php" class="btn btn-filter">
                        <i class="zmdi zmdi-refresh"></i> Limpiar
                    </a>
                    <button type="submit" name="buscar" value="1" class="btn btn-apply-filter">
                        <i class="zmdi zmdi-check"></i> Aplicar
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
                        <i class="zmdi zmdi-download"></i> Exportar Resultados (<?= count($resultados_bienes); ?> bienes)
                    </h4>
                    <form action="generar_reporte_inventario.php" method="POST" target="_blank">
                        <input type="hidden" name="busqueda_personalizada" value="1">
                        <input type="hidden" name="termino" value="<?= htmlspecialchars($_GET['termino_busqueda'] ?? ''); ?>">
                        <input type="hidden" name="codigo_bien" value="<?= htmlspecialchars($_GET['codigo_bien'] ?? ''); ?>">
                        <input type="hidden" name="estatus" value="<?= htmlspecialchars($_GET['estatus'] ?? ''); ?>">
                        <input type="hidden" name="categoria" value="<?= htmlspecialchars($_GET['categoria'] ?? ''); ?>">
                        <button type="submit" class="btn btn-primary" >
                            <i class="zmdi zmdi-file-pdf"></i> Descargar PDF
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Resultados de búsqueda con historial de movimientos automático -->
            <div class="section-container">
                <h2 class="section-title">Resultados de Búsqueda (<?= count($resultados_bienes); ?> bienes encontrados)</h2>
                
                <?php foreach ($resultados_bienes as $bien): ?>
                    <!-- Tarjeta del Bien -->
                    <div style="background: #fff; border: 2px solid #e0e0e0; border-radius: 12px; margin-bottom: 20px; overflow: hidden;">
                        <!-- Información del Bien -->
                        <div style="background: linear-gradient(135deg, #ff6600 0%, #ff8533 100%); padding: 15px 20px; color: white;">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div>
                                    <strong style="font-size: 1.2rem;"><?= htmlspecialchars($bien['codigo_bien_nacional'] ?? 'N/A'); ?></strong>
                                    <span style="margin-left: 15px; opacity: 0.9;"><?= htmlspecialchars($bien['descripcion'] ?? 'Sin descripción'); ?></span>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <a href="editar_bien.php?codigo=<?= urlencode($bien['codigo_bien_nacional']); ?>" 
                                       class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);"
                                       title="Editar bien">
                                        <i class="zmdi zmdi-edit"></i> Editar
                                    </a>
                                    <a href="registrar_movimiento.php?bien_id=<?= $bien['id']; ?>" 
                                       class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);"
                                       title="Registrar movimiento">
                                        <i class="zmdi zmdi-truck"></i> Movimiento
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detalles del Bien -->
                        <div style="padding: 15px 20px; background: #fafafa;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px;">
                                <div><strong>Código:</strong> <?= htmlspecialchars($bien['codigo_bien_nacional'] ?? 'N/A'); ?></div>
                                <div><strong>Cód. Anterior:</strong> <?= htmlspecialchars($bien['codigo_anterior'] ?? 'N/A'); ?></div>
                                <div><strong>Marca:</strong> <?= htmlspecialchars($bien['marca'] ?? 'N/A'); ?></div>
                                <div><strong>Modelo:</strong> <?= htmlspecialchars($bien['modelo'] ?? 'N/A'); ?></div>
                                <div><strong>Serial:</strong> <?= htmlspecialchars($bien['serial'] ?? 'N/A'); ?></div>
                                <div><strong>Color:</strong> <?= htmlspecialchars($bien['color'] ?? 'N/A'); ?></div>
                                <div><strong>Dimensiones:</strong> <?= htmlspecialchars($bien['dimensiones'] ?? 'N/A'); ?></div>
                                <div><strong>Valor Adq.:</strong> <?= isset($bien['valor_adquisicion']) ? number_format($bien['valor_adquisicion'], 2, ',', '.') : 'N/A'; ?></div>
                                <div><strong>Valor Actual:</strong> <?= isset($bien['valor_actual']) ? number_format($bien['valor_actual'], 2, ',', '.') : 'N/A'; ?></div>
                                <div><strong>Vida Útil:</strong> <?= isset($bien['vida_util_anos']) ? $bien['vida_util_anos'] . ' años' : 'N/A'; ?></div>
                                <div><strong>Fecha Incorp.:</strong> <?= isset($bien['fecha_incorporacion']) ? date('d/m/Y', strtotime($bien['fecha_incorporacion'])) : 'N/A'; ?></div>
                                <div><strong>Ubicación:</strong> <?= htmlspecialchars($bien['ubicacion_nombre'] ?? 'Sin ubicación'); ?></div>
                                <div><strong>Dependencia:</strong> <?= htmlspecialchars($bien['dependencia_nombre'] ?? 'Sin dependencia'); ?></div>
                                <div><strong>Categoría:</strong> <?= htmlspecialchars($bien['categoria_nombre'] ?? 'Sin categoría'); ?></div>
                                <div>
                                    <strong>Estatus:</strong>
                                    <span style="background:<?= $bien['estatus_id'] == 4 ? '#f8d7da' : '#d4edda' ?>; 
                                          color:<?= $bien['estatus_id'] == 4 ? '#721c24' : '#155724' ?>; 
                                          padding:2px 6px; border-radius:3px; font-size:0.8rem;">
                                        <?= htmlspecialchars($bien['estatus_nombre'] ?? 'Sin estatus'); ?>
                                    </span>
                                </div>
                            </div>
                            <?php if (!empty($bien['observaciones'])): ?>
                            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #ddd;">
                                <strong>Observaciones:</strong> <?= htmlspecialchars($bien['observaciones']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Historial de Movimientos -->
                        <?php $movimientos = $movimientos_por_bien[$bien['id']] ?? array(); ?>
                        <div style="border-top: 1px solid #e0e0e0;">
                            <div style="background: #e3f2fd; padding: 10px 20px; font-weight: 600; color: #1565c0;">
                                <i class="zmdi zmdi-history"></i> Historial de Movimientos (<?= count($movimientos); ?>)
                            </div>
                            <?php if (!empty($movimientos)): ?>
                                <div style="padding: 15px 20px; max-height: 300px; overflow-y: auto;">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                                        <thead>
                                            <tr style="background: #f5f5f5;">
                                                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Fecha</th>
                                                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Tipo</th>
                                                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Origen</th>
                                                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Destino</th>
                                                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Responsable</th>
                                                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Razón</th>
                                                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Usuario</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($movimientos as $mov): ?>
                                                <tr>
                                                    <td style="padding: 8px 10px; border-bottom: 1px solid #eee;">
                                                        <?= isset($mov['fecha_movimiento']) ? date('d/m/Y', strtotime($mov['fecha_movimiento'])) : 'N/A'; ?>
                                                    </td>
                                                    <td style="padding: 8px 10px; border-bottom: 1px solid #eee;">
                                                        <?php
                                                        $tipo_colors = [
                                                            'Incorporacion' => '#4caf50',
                                                            'Traslado' => '#2196f3',
                                                            'Desincorporacion' => '#f44336',
                                                            'Asignacion' => '#9c27b0',
                                                            'Reparacion' => '#ff9800',
                                                            'Devolucion' => '#607d8b'
                                                        ];
                                                        $tipo_color = $tipo_colors[$mov['tipo_movimiento']] ?? '#757575';
                                                        ?>
                                                        <span style="background:<?= $tipo_color ?>; color:white; padding:2px 6px; border-radius:3px; font-size:0.8rem;">
                                                            <?= htmlspecialchars($mov['tipo_movimiento'] ?? 'N/A'); ?>
                                                        </span>
                                                    </td>
                                                    <td style="padding: 8px 10px; border-bottom: 1px solid #eee;">
                                                        <?= htmlspecialchars($mov['ubicacion_origen_nombre'] ?? 'N/A'); ?>
                                                    </td>
                                                    <td style="padding: 8px 10px; border-bottom: 1px solid #eee;">
                                                        <?= htmlspecialchars($mov['ubicacion_destino_nombre'] ?? 'N/A'); ?>
                                                    </td>
                                                    <td style="padding: 8px 10px; border-bottom: 1px solid #eee;">
                                                        <?php if (!empty($mov['responsable_destino_nombre'])): ?>
                                                            <?= htmlspecialchars($mov['responsable_destino_nombre'] . ' ' . $mov['responsable_destino_apellido']); ?>
                                                        <?php else: ?>
                                                            <span style="color:#999;">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="padding: 8px 10px; border-bottom: 1px solid #eee;">
                                                        <?= htmlspecialchars($mov['razon'] ?? 'N/A'); ?>
                                                    </td>
                                                    <td style="padding: 8px 10px; border-bottom: 1px solid #eee;">
                                                        <?php if (!empty($mov['usuario_nombres'])): ?>
                                                            <?= htmlspecialchars($mov['usuario_nombres'] . ' ' . $mov['usuario_apellidos']); ?>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars($mov['usuario_registro'] ?? 'N/A'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div style="padding: 20px; text-align: center; color: #999;">
                                    <i class="zmdi zmdi-inbox" style="font-size: 2rem;"></i>
                                    <p style="margin-top: 10px;">No hay movimientos registrados para este bien</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Historial de Bienes Nacionales -->
    <?php if ($mostrar_historial && !empty($historial_bienes)): ?>
        <div class="section-container" style="background-color: #e8f5e9; border: 2px solid #4caf50;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <h4 style="margin: 0; color: #2e7d32;">
                    <i class="zmdi zmdi-history"></i> Historial de Bienes Nacionales (<?= count($historial_bienes); ?> bienes)
                </h4>
                <form action="generar_reporte_inventario.php" method="POST" target="_blank">
                    <input type="hidden" name="busqueda_personalizada" value="0">
                    <button type="submit" class="btn btn-primary" style="background-color: #4caf50;">
                        <i class="zmdi zmdi-file-pdf"></i> Descargar PDF Completo
                    </button>
                </form>
            </div>
        </div>
        
        <div class="section-container">
            <h2 class="section-title">
                <i class="zmdi zmdi-view-list" style="color: #4caf50;"></i> 
                Listado Completo de Bienes Nacionales
            </h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="padding:14px 15px; background:#4caf50; color:white; text-align:left; font-weight:700;">Código</th>
                            <th style="padding:14px 15px; background:#4caf50; color:white; text-align:left; font-weight:700;">Código Anterior</th>
                            <th style="padding:14px 15px; background:#4caf50; color:white; text-align:left; font-weight:700;">Descripción</th>
                            <th style="padding:14px 15px; background:#4caf50; color:white; text-align:left; font-weight:700;">Marca/Modelo</th>
                            <th style="padding:14px 15px; background:#4caf50; color:white; text-align:left; font-weight:700;">Serial</th>
                            <th style="padding:14px 15px; background:#4caf50; color:white; text-align:left; font-weight:700;">Color</th>
                            <th style="padding:14px 15px; background:#4caf50; color:white; text-align:left; font-weight:700;">Valor</th>
                            <th style="padding:14px 15px; background:#4caf50; color:white; text-align:left; font-weight:700;">Ubicación</th>
                            <th style="padding:14px 15px; background:#4caf50; color:white; text-align:left; font-weight:700;">Responsable</th>
                            <th style="padding:14px 15px; background:#4caf50; color:white; text-align:left; font-weight:700;">Estatus</th>
                            <th style="padding:14px 15px; background:#4caf50; color:white; text-align:left; font-weight:700;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial_bienes as $bien): ?>
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
                                    <?= isset($bien['valor_adquisicion']) ? number_format($bien['valor_adquisicion'], 2, ',', '.') : 'N/A'; ?>
                                </td>
                                <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                    <?php if (!empty($bien['ubicacion_nombre'])): ?>
                                        <span style="background:#e3f2fd; color:#1565c0; padding:3px 8px; border-radius:4px; font-size:0.8rem;">
                                            <?= htmlspecialchars(($bien['ubicacion_codigo'] ?? '') . ' - ' . $bien['ubicacion_nombre']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#999;">Sin ubicación</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                    <?php if (!empty($bien['responsable'])): ?>
                                        <div style="font-weight:600;"><?= htmlspecialchars($bien['responsable']); ?></div>
                                        <?php if (!empty($bien['responsable_telefono'])): ?>
                                            <div style="font-size:0.75rem; color:#666;">📞 <?= htmlspecialchars($bien['responsable_telefono']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($bien['responsable_email'])): ?>
                                            <div style="font-size:0.75rem; color:#666;">✉️ <?= htmlspecialchars($bien['responsable_email']); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#999;">Sin responsable</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                    <span style="background:<?= $bien['estatus_id'] == 4 ? '#f8d7da' : '#d4edda' ?>; 
                                          color:<?= $bien['estatus_id'] == 4 ? '#721c24' : '#155724' ?>; 
                                          padding:4px 8px; border-radius:4px; font-size:0.8rem; font-weight:600;">
                                        <?= htmlspecialchars($bien['estatus_nombre'] ?? 'Sin estatus'); ?>
                                    </span>
                                </td>
                                <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                    <a href="editar_bien.php?codigo=<?= urlencode($bien['codigo_bien_nacional']); ?>" 
                                       class="btn btn-sm btn-primary" style="background-color: #ff6600; margin-right: 5px;"
                                       title="Editar bien">
                                        <i class="zmdi zmdi-edit"></i>
                                    </a>
                                    <a href="registrar_movimiento.php?bien_id=<?= $bien['id']; ?>" 
                                       class="btn btn-sm btn-primary" style="background-color: #2196f3;"
                                       title="Registrar movimiento">
                                        <i class="zmdi zmdi-truck"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($mostrar_historial): ?>
        <div class="section-container" style="background-color: #fff3e0; border: 2px solid #ff9800; padding: 40px; text-align: center;">
            <i class="zmdi zmdi-inbox" style="font-size: 4rem; color: #ff9800;"></i>
            <h3 style="color: #e65100; margin-top: 20px;">No hay bienes registrados</h3>
            <p style="color: #666; font-size: 1.1rem;">
               Aún no se han registrado bienes nacionales en el sistema.
            </p>
            <a href="registrar_bien.php" class="btn btn-primary" style="background-color: #ff6600; margin-top: 20px;">
                <i class="zmdi zmdi-plus"></i> Registrar Primer Bien
            </a>
        </div>
    <?php endif; ?>
</div>

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
