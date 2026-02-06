<?php
include('header.php');
include('conexion.php');

$busqueda_realizada = false;
$resultados_bienes = array();
$historial_movimientos = array();
$mensaje = '';
$tipo_mensaje = '';
$bien_seleccionado = null;

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

// Obtener lugares/ubicaciones
$lugares = array();
$result_lugares = $conn->query("SELECT id, nombre FROM ubicaciones ORDER BY nombre");
if ($result_lugares && $result_lugares->num_rows > 0) {
    while ($row = $result_lugares->fetch_assoc()) {
        $lugares[] = $row;
    }
}

// Obtener dependencias
$dependencias = array();
$result_dependencias = $conn->query("SELECT id, nombre FROM dependencias ORDER BY nombre");
if ($result_dependencias && $result_dependencias->num_rows > 0) {
    while ($row = $result_dependencias->fetch_assoc()) {
        $dependencias[] = $row;
    }
}

// Procesar búsqueda
if (isset($_GET['buscar'])) {
    $busqueda_realizada = true;
    
    $termino = trim($_GET['termino_busqueda'] ?? '');
    $codigo_bien = trim($_GET['codigo_bien'] ?? '');
    $filtro_estatus = $_GET['estatus'] ?? '';
    $filtro_categoria = $_GET['categoria'] ?? '';
    $filtro_lugar = $_GET['lugar'] ?? '';
    $filtro_dependencia = $_GET['dependencia'] ?? '';
    $buscar_todo_lugar = isset($_GET['buscar_todo_lugar']);
    $buscar_todo_dependencia = isset($_GET['buscar_todo_dependencia']);
    
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
        
        // Filtro por lugar/ubicación
        if (!empty($filtro_lugar)) {
            // Verificar si la columna ubicacion_id existe
            $check_col = $conn->query("SHOW COLUMNS FROM bienes LIKE 'ubicacion_id'");
            if ($check_col && $check_col->num_rows > 0) {
                if ($buscar_todo_lugar) {
                    $sql .= " AND b.ubicacion_id IN (
                        SELECT id FROM ubicaciones WHERE id = ? OR ubicacion_padre_id = ?
                    )";
                    $params[] = $filtro_lugar;
                    $params[] = $filtro_lugar;
                    $types .= 'ii';
                } else {
                    $sql .= " AND b.ubicacion_id = ?";
                    $params[] = $filtro_lugar;
                    $types .= 'i';
                }
            }
        }
        
        // Filtro por dependencia
        if (!empty($filtro_dependencia)) {
            // Verificar si la columna dependencia_id existe
            $check_col = $conn->query("SHOW COLUMNS FROM bienes LIKE 'dependencia_id'");
            if ($check_col && $check_col->num_rows > 0) {
                if ($buscar_todo_dependencia) {
                    $sql .= " AND b.dependencia_id IN (
                        SELECT id FROM dependencias WHERE id = ? OR dependencia_padre_id = ?
                    )";
                    $params[] = $filtro_dependencia;
                    $params[] = $filtro_dependencia;
                    $types .= 'ii';
                } else {
                    $sql .= " AND b.dependencia_id = ?";
                    $params[] = $filtro_dependencia;
                    $types .= 'i';
                }
            }
        }
        
        $sql .= " ORDER BY b.id DESC LIMIT 200";
        
        // Guardar parámetros para PDF
        $_SESSION['busqueda_params'] = [
            'termino' => $termino,
            'codigo_bien' => $codigo_bien,
            'estatus' => $filtro_estatus,
            'categoria' => $filtro_categoria,
            'lugar' => $filtro_lugar,
            'dependencia' => $filtro_dependencia,
            'buscar_todo_lugar' => $buscar_todo_lugar,
            'buscar_todo_dependencia' => $buscar_todo_dependencia,
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
        
        // Si se encontró un único bien por código, obtener su historial de movimientos
        if (!empty($codigo_bien) && count($resultados_bienes) == 1) {
            $bien_seleccionado = $resultados_bienes[0];
            $bien_id = $bien_seleccionado['id'];
            
            // Verificar si la tabla movimientos existe
            $check_movimientos = $conn->query("SHOW TABLES LIKE 'movimientos'");
            if ($check_movimientos->num_rows > 0) {
                $sql_movimientos = "SELECT * FROM movimientos WHERE bien_id = ? ORDER BY fecha_movimiento DESC LIMIT 50";
                $stmt_mov = $conn->prepare($sql_movimientos);
                if ($stmt_mov) {
                    $stmt_mov->bind_param("i", $bien_id);
                    $stmt_mov->execute();
                    $result_mov = $stmt_mov->get_result();
                    if ($result_mov && $result_mov->num_rows > 0) {
                        while ($row = $result_mov->fetch_assoc()) {
                            $historial_movimientos[] = $row;
                        }
                    }
                    $stmt_mov->close();
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
                <div class="field-row" style="margin-top: 15px;">
                    <div class="field-col">
                        <label for="lugar" class="field-label">Lugar/Ubicación</label>
                        <select id="lugar" name="lugar" class="form-control">
                            <option value="">Todos los lugares</option>
                            <?php foreach ($lugares as $l): ?>
                                <option value="<?= $l['id']; ?>" <?= ($_GET['lugar'] ?? '') == $l['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($l['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-col">
                        <label for="dependencia" class="field-label">Dependencia</label>
                        <select id="dependencia" name="dependencia" class="form-control">
                            <option value="">Todas las dependencias</option>
                            <?php foreach ($dependencias as $dep): ?>
                                <option value="<?= $dep['id']; ?>" <?= ($_GET['dependencia'] ?? '') == $dep['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($dep['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field-row" style="margin-top: 15px;">
                    <div class="field-col" style="flex: 100%;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="buscar_todo_lugar" name="buscar_todo_lugar" value="1" <?= isset($_GET['buscar_todo_lugar']) ? 'checked' : ''; ?>>
                            <span>Incluir sub-ubicaciones del lugar seleccionado</span>
                        </label>
                    </div>
                </div>
                <div class="field-row" style="margin-top: 10px;">
                    <div class="field-col" style="flex: 100%;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="buscar_todo_dependencia" name="buscar_todo_dependencia" value="1" <?= isset($_GET['buscar_todo_dependencia']) ? 'checked' : ''; ?>>
                            <span>Incluir sub-dependencias de la dependencia seleccionada</span>
                        </label>
                    </div>
                </div>
                <div class="field-row" style="margin-top: 15px;">
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
            <!-- Botón Exportar PDF -->
            <div class="section-container" style="background-color: #e3f2fd; border: 2px solid #2196f3;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="margin: 0; color: #1976d2;">
                        <i class="zmdi zmdi-download"></i> Exportar Resultados
                    </h4>
                    <form action="generar_reporte_inventario.php" method="POST" target="_blank">
                        <input type="hidden" name="busqueda_personalizada" value="1">
                        <input type="hidden" name="termino" value="<?= htmlspecialchars($_GET['termino_busqueda'] ?? ''); ?>">
                        <input type="hidden" name="codigo_bien" value="<?= htmlspecialchars($_GET['codigo_bien'] ?? ''); ?>">
                        <input type="hidden" name="estatus" value="<?= htmlspecialchars($_GET['estatus'] ?? ''); ?>">
                        <input type="hidden" name="categoria" value="<?= htmlspecialchars($_GET['categoria'] ?? ''); ?>">
                        <input type="hidden" name="lugar" value="<?= htmlspecialchars($_GET['lugar'] ?? ''); ?>">
                        <input type="hidden" name="dependencia" value="<?= htmlspecialchars($_GET['dependencia'] ?? ''); ?>">
                        <input type="hidden" name="buscar_todo_lugar" value="<?= isset($_GET['buscar_todo_lugar']) ? '1' : ''; ?>">
                        <input type="hidden" name="buscar_todo_dependencia" value="<?= isset($_GET['buscar_todo_dependencia']) ? '1' : ''; ?>">
                        <button type="submit" class="btn btn-primary" style="background-color: #2196f3;">
                            <i class="zmdi zmdi-file-pdf"></i> Descargar PDF
                        </button>
                    </form>
                </div>
            </div>
            
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
            
            <!-- Historial de Movimientos -->
            <?php if ($bien_seleccionado && !empty($historial_movimientos)): ?>
            <div class="section-container" style="background-color: #fff3e0; border: 2px solid #ff6600;">
                <h2 class="section-title" style="color: #ff6600;">
                    <i class="zmdi zmdi-timeRestore"></i> Historial de Movimientos
                </h2>
                <p style="margin-bottom: 15px;">
                    <strong>Bien:</strong> <?= htmlspecialchars($bien_seleccionado['codigo_bien_nacional']); ?> - 
                    <?= htmlspecialchars($bien_seleccionado['descripcion']); ?>
                </p>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr style="background:#ff6600; color:white;">
                                <th style="padding:12px 15px; text-align:left;">Fecha</th>
                                <th style="padding:12px 15px; text-align:left;">Tipo de Movimiento</th>
                                <th style="padding:12px 15px; text-align:left;">Responsable</th>
                                <th style="padding:12px 15px; text-align:left;">Motivo</th>
                                <th style="padding:12px 15px; text-align:left;">Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial_movimientos as $mov): ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:10px 15px;"><?= htmlspecialchars($mov['fecha_movimiento'] ?? 'N/A'); ?></td>
                                <td style="padding:10px 15px; font-weight:600; color:#ff6600;">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $mov['tipo_movimiento'] ?? 'N/A'))); ?>
                                </td>
                                <td style="padding:10px 15px;"><?= htmlspecialchars($mov['responsable'] ?? 'N/A'); ?></td>
                                <td style="padding:10px 15px;"><?= htmlspecialchars($mov['motivo'] ?? 'N/A'); ?></td>
                                <td style="padding:10px 15px;"><?= htmlspecialchars($mov['observaciones'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php elseif ($bien_seleccionado): ?>
            <div class="section-container" style="background-color: #e8f5e9; border: 2px solid #4caf50;">
                <h2 class="section-title" style="color: #4caf50;">
                    <i class="zmdi zmdi-check-circle"></i> Sin Movimientos
                </h2>
                <p>El bien <strong><?= htmlspecialchars($bien_seleccionado['codigo_bien_nacional']); ?></strong> no tiene movimientos registrados.</p>
            </div>
            <?php endif; ?>
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
