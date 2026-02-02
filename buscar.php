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

// Procesar búsqueda
if (isset($_GET['buscar'])) {
    $busqueda_realizada = true;
    
    $termino = trim($_GET['termino_busqueda'] ?? '');
    $codigo_bien = trim($_GET['codigo_bien'] ?? '');
    $filtro_estatus = $_GET['estatus'] ?? '';
    $filtro_categoria = $_GET['categoria'] ?? '';
    
    try {
        // Construir consulta base
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
                c.nombre AS categoria_nombre
            FROM bienes b
            LEFT JOIN estatus e ON b.estatus_id = e.id
            LEFT JOIN categorias c ON b.categoria_id = c.id
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
        
        // Nota: Los filtros de ubicación, dependencia y tipo_adquisicion 
        // están disponibles en las tablas relacionadas pero no en la tabla bienes directamente
        
        $sql .= " ORDER BY b.id DESC LIMIT 100";
        
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
        }
        
    } catch (Exception $e) {
        $mensaje = "Error al realizar la búsqueda: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}
?>

<div class="container">
    <!-- Título principal -->
    <h1 style="font-family:montserrat; font-weight:900; color:#ff6600; padding:20px; text-align:left; font-size:50px;">
        <i class="zmdi zmdi-search"></i> Búsqueda de <span style="font-weight:700; color:black;">Bienes Nacionales</span>
    </h1>

    <!-- Formulario de búsqueda -->
    <div class="section-container">
        <h2 class="section-title" style="font-weight: 800;">Buscar Bienes</h2>
        <form method="GET" style="margin-bottom: 30px;">
            <div class="field-row">
                <div class="field-col">
                    <label for="codigo_bien" class="field-label" style="font-weight: 600;">Código de Bien Nacional</label>
                    <input type="text" style="font-weight: 00;" id="codigo_bien" name="codigo_bien" 
                           placeholder="Ej: BN-2026-0001" 
                           value="<?= htmlspecialchars($_GET['codigo_bien'] ?? ''); ?>" class="form-control">
                </div>
                <div class="field-col">
                    <label for="termino_busqueda" class="field-label" style="font-weight: 600;">Término de Búsqueda</label>
                    <input type="text" style="font-weight: 00;" id="termino_busqueda" name="termino_busqueda" 
                           placeholder="Buscar por código, descripción, marca, modelo, serial..." 
                           value="<?= htmlspecialchars($_GET['termino_busqueda'] ?? ''); ?>" class="form-control">
                </div>
                <div class="field-col" style="align-self: end;">
                    <button type="submit" name="buscar" value="1" class="btn btn-primary">Buscar</button>
                </div>
            </div>
            
            <!-- Filtros -->
            <div style="margin-top: 20px;">
                <h4 style="font-weight: 600; color: #ff6600; margin-bottom: 15px;">Filtros</h4>
                <div class="field-row">
                    <div class="field-col">
                        <label for="estatus" class="field-label">Estatus</label>
                        <select id="estatus" name="estatus" class="form-control">
                            <option value="">Todos los estatus</option>
                            <?php foreach ($estatus as $e): ?>
                                <option value="<?= $e['id']; ?>" <?= ($_GET['estatus'] ?? '') == $e['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($e['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-col">
                        <label for="categoria" class="field-label">Categoría</label>
                        <select id="categoria" name="categoria" class="form-control">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= $c['id']; ?>" <?= ($_GET['categoria'] ?? '') == $c['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($c['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field-row" style="margin-top: 15px;">>
                    <div class="field-col">
                        <a href="buscar.php" class="btn btn-secondary" style="margin-right: 10px;">
                            <i class="zmdi zmdi-refresh"></i> Limpiar Filtros
                        </a>
                    </div>
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
            <!-- Resultados de búsqueda -->
            <div class="section-container">
                <h2 class="section-title">Resultados de Búsqueda (<?= count($resultados_bienes); ?> bienes encontrados)</h2>
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
                                <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700;">Vida Útil (Años)</th>
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
                                        <?= htmlspecialchars($bien['categoria_nombre'] ?? 'Sin categoría'); ?>
                                    </td>
                                    <td style="padding:14px 15px; border-bottom:1px solid #eee;">
                                        <span style="background:<?= $bien['estatus_id'] == 4 ? '#f8d7da' : '#d4edda'; ?>; 
                                              color:<?= $bien['estatus_id'] == 4 ? '#721c24' : '#155724'; ?>; 
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
    <?php else: ?>
        <!-- Mensaje inicial -->
        <div class="section-container" style="text-align:center; padding:40px;">
            <i class="zmdi zmdi-search" style="font-size:80px; color:#ff6600; margin-bottom:20px;"></i>
            <h3 style="color:#333; font-weight:600;">Ingrese un término de búsqueda o seleccione filtros</h3>
            <p style="color:#666;">Puede buscar por código, descripción, marca, modelo o serial del bien.</p>
        </div>
    <?php endif; ?>
</div>

<?php include('footer.php'); ?>

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

