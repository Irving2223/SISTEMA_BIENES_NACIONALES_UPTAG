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
$ubicaciones = [];
$dependencias = [];

// Obtener ubicaciones
try {
    function tablaExiste($conn, $nombre_tabla) {
        $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($nombre_tabla) . "'");
        return $result && $result->num_rows > 0;
    }
    
    if (tablaExiste($conn, 'ubicaciones')) {
        $result = $conn->query("SELECT * FROM ubicaciones ORDER BY nombre ASC");
        while ($row = $result->fetch_assoc()) {
            $ubicaciones[] = $row;
        }
    }
    
    if (tablaExiste($conn, 'dependencias')) {
        $result = $conn->query("SELECT * FROM dependencias ORDER BY nombre ASC");
        while ($row = $result->fetch_assoc()) {
            $dependencias[] = $row;
        }
    }
    
    if (empty($ubicaciones) && empty($dependencias)) {
        $mensaje = "No hay ubicaciones ni dependencias registradas en el sistema.";
        $tipo_mensaje = "info";
    }
} catch (Exception $e) {
    $mensaje = "Error al cargar datos: " . $e->getMessage();
    $tipo_mensaje = "error";
}

// Combinar datos para JSON
$todos_datos = array(
    'ubicaciones' => $ubicaciones,
    'dependencias' => $dependencias
);
$datos_json = json_encode($todos_datos, JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lugares y Dependencias - Sistema INTI</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/estilos_sistema.css">
    <link rel="stylesheet" href="css/material-design-iconic-font.min.css">
    <style>
        html, body {
            font-family: montserrat;
            font-weight: 500;
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
        
        .tipo-ubicacion {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .tipo-pnf { background-color: #2196f3; color: white; }
        .tipo-sede { background-color: #4caf50; color: white; }
        .tipo-edificio { background-color: #ff9800; color: white; }
        .tipo-piso { background-color: #9c27b0; color: white; }
        .tipo-oficina { background-color: #00bcd4; color: white; }
        .tipo-aula { background-color: #e91e63; color: white; }
        .tipo-laboratorio { background-color: #795548; color: white; }
        .tipo-otro { background-color: #607d8b; color: white; }
        
        .tabs-container {
            margin-bottom: 20px;
        }
        
        .tab-buttons {
            display: flex;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background-color: #f0f0f0;
            border: none;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            background-color: #ff6600;
            color: white;
        }
        
        .tab-btn:hover:not(.active) {
            background-color: #e0e0e0;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <h1 class="page-header">
            <i class="zmdi zmdi-pin"></i> 
            Lugares y <span>Dependencias</span>
        </h1>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>" style="margin: 20px;">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-container">
            <div class="stat-box">
                <h3 id="total-ubicaciones"><?php echo count($ubicaciones); ?></h3>
                <p>Ubicaciones</p>
            </div>
            <div class="stat-box">
                <h3 id="total-dependencias"><?php echo count($dependencias); ?></h3>
                <p>Dependencias</p>
            </div>
            <div class="stat-box">
                <h3 id="total-mostrado"><?php echo count($ubicaciones) + count($dependencias); ?></h3>
                <p>Total Mostrado</p>
            </div>
        </div>

        <!-- Buscador -->
        <div class="section-container">
            <h4 class="section-title"><i class="zmdi zmdi-search"></i> Buscar</h4>
            <div class="search-container">
                <input type="text" id="buscador" class="search-input" 
                       placeholder="Buscar en ubicaciones y dependencias..." 
                       onkeyup="filtrarDatos()">
            </div>
        </div>

        <!-- Tabs para mostrar ubicaciones y dependencias -->
        <div class="section-container tabs-container">
            <div class="tab-buttons">
                <button class="tab-btn active" onclick="cambiarTab('ubicaciones')">
                    <i class="zmdi zmdi-pin"></i> Ubicaciones (<?php echo count($ubicaciones); ?>)
                </button>
                <button class="tab-btn" onclick="cambiarTab('dependencias')">
                    <i class="zmdi zmdi-balance"></i> Dependencias (<?php echo count($dependencias); ?>)
                </button>
            </div>
            
            <!-- Botón PDF -->
            <div style="margin-bottom: 15px; text-align: right;">
                <button type="button" class="btn btn-success" onclick="generarPDF()">
                    <i class="zmdi zmdi-download"></i> Descargar PDF
                </button>
            </div>

            <!-- Tabla de Ubicaciones -->
            <div id="tab-ubicaciones" class="tab-content active">
                <h4 class="section-title" style="margin-bottom: 15px;">Ubicaciones</h4>
                <div style="overflow-x: auto;">
                    <table id="tabla-ubicaciones">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Dependencia</th>
                                <th>Responsable</th>
                                <th>Dirección</th>
                                <th>Estatus</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-ubicaciones">
                            <?php foreach ($ubicaciones as $ubic): ?>
                            <tr class="ubicacion-row" 
                                data-id="<?php echo htmlspecialchars($ubic['id'] ?? ''); ?>"
                                data-nombre="<?php echo htmlspecialchars($ubic['nombre'] ?? ''); ?>"
                                data-tipo="<?php echo htmlspecialchars($ubic['tipo'] ?? ''); ?>"
                                data-dependencia="<?php echo htmlspecialchars($ubic['dependencia_id'] ?? ''); ?>"
                                data-responsable="<?php echo htmlspecialchars($ubic['responsable'] ?? ''); ?>"
                                data-direccion="<?php echo htmlspecialchars($ubic['direccion'] ?? ''); ?>">
                                <td><?php echo htmlspecialchars($ubic['id'] ?? 'N/A'); ?></td>
                                <td><strong><?php echo htmlspecialchars($ubic['nombre'] ?? 'N/A'); ?></strong></td>
                                <td>
                                    <?php 
                                        $tipo = strtolower($ubic['tipo'] ?? '');
                                        $tipo_class = 'tipo-otro';
                                        if (strpos($tipo, 'pnf') !== false) $tipo_class = 'tipo-pnf';
                                        elseif (strpos($tipo, 'sede') !== false) $tipo_class = 'tipo-sede';
                                        elseif (strpos($tipo, 'edificio') !== false) $tipo_class = 'tipo-edificio';
                                        elseif (strpos($tipo, 'piso') !== false) $tipo_class = 'tipo-piso';
                                        elseif (strpos($tipo, 'oficina') !== false) $tipo_class = 'tipo-oficina';
                                        elseif (strpos($tipo, 'aula') !== false) $tipo_class = 'tipo-aula';
                                        elseif (strpos($tipo, 'laboratorio') !== false) $tipo_class = 'tipo-laboratorio';
                                    ?>
                                    <span class="tipo-ubicacion <?php echo $tipo_class; ?>">
                                        <?php echo htmlspecialchars(ucfirst($ubic['tipo'] ?? 'N/A')); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($ubic['dependencia_id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($ubic['responsable'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($ubic['direccion'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                        $estatus = isset($ubic['activo']) ? ($ubic['activo'] == 1 ? 'Activo' : 'Inactivo') : 'Activo';
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
                    <p id="sin-ubicaciones" style="display: none; text-align: center; padding: 20px; color: #666;">
                        No hay ubicaciones que coincidan con la búsqueda.
                    </p>
                </div>
            </div>

            <!-- Tabla de Dependencias -->
            <div id="tab-dependencias" class="tab-content">
                <h4 class="section-title" style="margin-bottom: 15px;">Dependencias</h4>
                <div style="overflow-x: auto;">
                    <table id="tabla-dependencias">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Responsable</th>
                                <th>Estatus</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-dependencias">
                            <?php foreach ($dependencias as $dep): ?>
                            <tr class="dependencia-row" 
                                data-id="<?php echo htmlspecialchars($dep['id'] ?? ''); ?>"
                                data-nombre="<?php echo htmlspecialchars($dep['nombre'] ?? ''); ?>"
                                data-descripcion="<?php echo htmlspecialchars($dep['descripcion'] ?? ''); ?>"
                                data-responsable="<?php echo htmlspecialchars($dep['responsable'] ?? ''); ?>">
                                <td><?php echo htmlspecialchars($dep['id'] ?? 'N/A'); ?></td>
                                <td><strong><?php echo htmlspecialchars($dep['nombre'] ?? 'N/A'); ?></strong></td>
                                <td><?php echo htmlspecialchars($dep['descripcion'] ?? 'Sin descripción'); ?></td>
                                <td><?php echo htmlspecialchars($dep['responsable'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                        $estatus = isset($dep['activo']) ? ($dep['activo'] == 1 ? 'Activo' : 'Inactivo') : 'Activo';
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
                    <p id="sin-dependencias" style="display: none; text-align: center; padding: 20px; color: #666;">
                        No hay dependencias que coincidan con la búsqueda.
                    </p>
                </div>
            </div>
        </div>

        <!-- Información -->
        <div class="info-box">
            <h5><i class="zmdi zmdi-info-outline"></i> Información</h5>
            <ul>
                <li><strong>Búsqueda:</strong> Escriba en el campo de arriba para filtrar por cualquier dato.</li>
                <li><strong>Ubicaciones:</strong> Lugares físicos donde se encuentran los bienes (PNF, Sedes, Edificios, Oficinas, Aulas, etc.).</li>
                <li><strong>Dependencias:</strong> Departamentos o áreas administrativas de la institución.</li>
                <li><strong>PDF:</strong> Descargue un reporte completo con la información visible.</li>
            </ul>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script src="js/jquery-3.1.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        // Datos en JSON
        var datos = <?php echo $datos_json; ?>;
        var tabActual = 'ubicaciones';
        
        function cambiarTab(tab) {
            tabActual = tab;
            
            // Actualizar botones
            document.querySelectorAll('.tab-btn').forEach(function(btn) {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Actualizar contenido
            document.querySelectorAll('.tab-content').forEach(function(content) {
                content.classList.remove('active');
            });
            document.getElementById('tab-' + tab).classList.add('active');
            
            // Aplicar filtro actual
            filtrarDatos();
        }
        
        function filtrarDatos() {
            var busqueda = document.getElementById('buscador').value.toLowerCase().trim();
            
            // Filtrar ubicaciones
            var filasUbicaciones = document.querySelectorAll('.ubicacion-row');
            var countUbicaciones = 0;
            
            filasUbicaciones.forEach(function(fila) {
                var nombre = fila.getAttribute('data-nombre').toLowerCase();
                var tipo = fila.getAttribute('data-tipo').toLowerCase();
                var responsable = fila.getAttribute('data-responsable').toLowerCase();
                var direccion = fila.getAttribute('data-direccion').toLowerCase();
                
                var coincide = busqueda === '' || 
                              nombre.includes(busqueda) || 
                              tipo.includes(busqueda) ||
                              responsable.includes(busqueda) ||
                              direccion.includes(busqueda);
                
                if (coincide) {
                    fila.style.display = '';
                    countUbicaciones++;
                } else {
                    fila.style.display = 'none';
                }
            });
            
            // Mostrar/ocultar mensaje sin resultados para ubicaciones
            document.getElementById('sin-ubicaciones').style.display = countUbicaciones === 0 ? 'block' : 'none';
            
            // Filtrar dependencias
            var filasDependencias = document.querySelectorAll('.dependencia-row');
            var countDependencias = 0;
            
            filasDependencias.forEach(function(fila) {
                var nombre = fila.getAttribute('data-nombre').toLowerCase();
                var descripcion = fila.getAttribute('data-descripcion').toLowerCase();
                var responsable = fila.getAttribute('data-responsable').toLowerCase();
                
                var coincide = busqueda === '' || 
                              nombre.includes(busqueda) || 
                              descripcion.includes(busqueda) ||
                              responsable.includes(busqueda);
                
                if (coincide) {
                    fila.style.display = '';
                    countDependencias++;
                } else {
                    fila.style.display = 'none';
                }
            });
            
            // Mostrar/ocultar mensaje sin resultados para dependencias
            document.getElementById('sin-dependencias').style.display = countDependencias === 0 ? 'block' : 'none';
            
            // Actualizar contadores
            document.getElementById('total-mostrado').textContent = countUbicaciones + countDependencias;
        }
        
        function generarPDF() {
            // Recopilar datos visibles
            var datosPDF = {
                ubicaciones: [],
                dependencias: []
            };
            
            document.querySelectorAll('.ubicacion-row').forEach(function(fila) {
                if (fila.style.display !== 'none') {
                    datosPDF.ubicaciones.push({
                        id: fila.getAttribute('data-id'),
                        nombre: fila.getAttribute('data-nombre'),
                        tipo: fila.getAttribute('data-tipo'),
                        dependencia: fila.getAttribute('data-dependencia'),
                        responsable: fila.getAttribute('data-responsable'),
                        direccion: fila.getAttribute('data-direccion'),
                        activo: 1
                    });
                }
            });
            
            document.querySelectorAll('.dependencia-row').forEach(function(fila) {
                if (fila.style.display !== 'none') {
                    datosPDF.dependencias.push({
                        id: fila.getAttribute('data-id'),
                        nombre: fila.getAttribute('data-nombre'),
                        descripcion: fila.getAttribute('data-descripcion'),
                        responsable: fila.getAttribute('data-responsable'),
                        activo: 1
                    });
                }
            });
            
            // Crear formulario y enviar
            var form = document.createElement('form');
            form.action = 'reporte_lugares_dependencias.php';
            form.method = 'POST';
            form.target = '_blank';
            
            var inputBuscar = document.createElement('input');
            inputBuscar.type = 'hidden';
            inputBuscar.name = 'buscar';
            inputBuscar.value = document.getElementById('buscador').value;
            
            var inputDatos = document.createElement('input');
            inputDatos.type = 'hidden';
            inputDatos.name = 'datos_json';
            inputDatos.value = JSON.stringify(datosPDF);
            
            form.appendChild(inputBuscar);
            form.appendChild(inputDatos);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        // Enfocar buscador al cargar
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('buscador').focus();
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

