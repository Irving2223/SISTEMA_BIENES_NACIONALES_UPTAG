<?php
// Verificar autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si no está logueado, redirigir
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['loggeado'] !== true) {
    header('Location: Loggin.php?error=Debe+iniciar+sesi%C3%B1n+para+acceder');
    exit;
}

require_once 'conexion.php';

$mensaje = '';
$tipo_mensaje = '';
$resultados = [];

// Obtener todas las categorías
try {
    $result = $conn->query("SELECT * FROM categorias ORDER BY id ASC");
    while ($row = $result->fetch_assoc()) {
        $resultados[] = $row;
    }
    
    if (empty($resultados)) {
        $mensaje = "No hay categorías registradas en el sistema.";
        $tipo_mensaje = "info";
    }
} catch (Exception $e) {
    $mensaje = "Error al cargar categorías: " . $e->getMessage();
    $tipo_mensaje = "error";
}

// Convertir resultados a JSON para JavaScript
$categorias_json = json_encode($resultados, JSON_HEX_APOS | JSON_HEX_QUOT);
?>

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
</style>

<?php include ("header.php"); ?>

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
                <h3 id="total-mostrado" style="font-weight: 900;"><?php echo count($resultados); ?></h3>
                <p>Total Categorías</p>
            </div>
        </div>

        <!-- Buscador -->
        <div class="section-container">
            <h4 class="section-title"><i class="zmdi zmdi-search"></i> Buscar Categorías</h4>
            <div class="search-container">
                <input type="text" id="buscador" class="search-input" 
                       placeholder="Buscar por código, nombre, descripción o cualquier campo..." 
                       onkeyup="filtrarCategorias()">
            </div>
        </div>

        <!-- Resultados -->
        <div class="section-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h4 class="section-title" style="margin-bottom: 0; border-bottom: none;">
                    <i class="zmdi zmdi-view-list"></i> Listado de Categorías
                </h4>
                
                <!-- Botón para generar PDF -->
                <form id="form-pdf" action="reporte_categorias.php" method="POST" target="_blank">
                    <input type="hidden" name="buscar" id="buscar-pdf" value="">
                    <input type="hidden" name="resultados_json" id="resultados-pdf" value="">
                    <button type="submit" class="btn btn-success">
                        <i class="zmdi zmdi-download"></i> Descargar PDF
                    </button>
                </form>
            </div>
            
            <div style="overflow-x: auto;">
                <table id="tabla-categorias">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Cuenta Presupuestaria</th>
                            <th>Estatus</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-categorias">
                        <?php foreach ($resultados as $cat): ?>
                        <tr class="categoria-row" 
                            data-id="<?php echo htmlspecialchars($cat['id'] ?? ''); ?>"
                            data-codigo="<?php echo htmlspecialchars($cat['codigo'] ?? $cat['id'] ?? ''); ?>"
                            data-nombre="<?php echo htmlspecialchars($cat['nombre'] ?? $cat['denominacion'] ?? ''); ?>"
                            data-descripcion="<?php echo htmlspecialchars($cat['descripcion'] ?? ''); ?>"
                            data-cuenta="<?php echo htmlspecialchars($cat['cuenta_presupuestaria'] ?? ''); ?>">
                            <td><?php echo htmlspecialchars($cat['id'] ?? 'N/A'); ?></td>
                            <td><strong><?php echo htmlspecialchars($cat['codigo'] ?? $cat['id'] ?? 'N/A'); ?></strong></td>
                            <td><?php echo htmlspecialchars($cat['nombre'] ?? $cat['denominacion'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($cat['descripcion'] ?? 'Sin descripción'); ?></td>
                            <td><?php echo htmlspecialchars($cat['cuenta_presupuestaria'] ?? 'N/A'); ?></td>
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
        </div>

        <!-- Información -->
        <div class="info-box">
            <h5><i class="zmdi zmdi-info-outline"></i> Información</h5>
            <div>
                <li><strong>Búsqueda:</strong> Escriba en el campo de arriba para filtrar cualquier dato de la tabla.</li>
                <li><strong>PDF:</strong> Descargue un reporte con las categorías visibles o use el buscador primero.</li>
                <li><strong>Total:</strong> El sistema tiene <strong><?php echo count($resultados); ?></strong> categorías registradas.</li>
                        </div>
        </div>
    </div>

    <script>
        // Datos de categorías en JSON
        var categorias = <?php echo $categorias_json; ?>;
        
        function filtrarCategorias() {
            var busqueda = document.getElementById('buscador').value.toLowerCase().trim();
            var filas = document.querySelectorAll('.categoria-row');
            var contador = 0;
            
            filas.forEach(function(fila) {
                var id = fila.getAttribute('data-id').toLowerCase();
                var codigo = fila.getAttribute('data-codigo').toLowerCase();
                var nombre = fila.getAttribute('data-nombre').toLowerCase();
                var descripcion = fila.getAttribute('data-descripcion').toLowerCase();
                var cuenta = fila.getAttribute('data-cuenta').toLowerCase();
                
                // Buscar en todos los campos
                var coincide = busqueda === '' || 
                              id.includes(busqueda) || 
                              codigo.includes(busqueda) || 
                              nombre.includes(busqueda) || 
                              descripcion.includes(busqueda) ||
                              cuenta.includes(busqueda);
                
                if (coincide) {
                    fila.style.display = '';
                    contador++;
                } else {
                    fila.style.display = 'none';
                }
            });
            
            // Actualizar contador
            document.getElementById('total-mostrado').textContent = contador;
            
            // Mostrar/ocultar mensaje de sin resultados
            var sinResultados = document.getElementById('sin-resultados');
            if (contador === 0) {
                sinResultados.style.display = 'block';
            } else {
                sinResultados.style.display = 'none';
            }
        }
        
        // Actualizar formulario PDF antes de enviar
        document.getElementById('form-pdf').addEventListener('submit', function(e) {
            var busqueda = document.getElementById('buscador').value;
            var filasVisibles = [];
            
            document.querySelectorAll('.categoria-row').forEach(function(fila) {
                if (fila.style.display !== 'none') {
                    filasVisibles.push({
                        id: fila.getAttribute('data-id'),
                        codigo: fila.getAttribute('data-codigo'),
                        nombre: fila.getAttribute('data-nombre'),
                        descripcion: fila.getAttribute('data-descripcion'),
                        cuenta_presupuestaria: fila.getAttribute('data-cuenta'),
                        activo: 1
                    });
                }
            });
            
            document.getElementById('buscar-pdf').value = busqueda;
            document.getElementById('resultados-pdf').value = JSON.stringify(filasVisibles);
        });
        
        // Enfocar buscador al cargar
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('buscador').focus();
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
