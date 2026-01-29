<?php
include("header.php"); // Incluye la conexión, verificación de sesión y encabezado HTML

// Manejar la inserción de un nuevo sector
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'agregar_sector') {
    $id_parroquia = intval($_POST['id_parroquia']);
    $nombre_sector = trim($_POST['nombre_sector']);

    // Solo validamos cuando se intenta guardar el sector
    if (empty($nombre_sector)) {
        $error_msg = " Se selecciono el nombre del municipio.";
    } else {
        // Verificar si el sector ya existe en la misma parroquia
        $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM sectores WHERE id_parroquia = ? AND nombre_sector = ?");
        $stmt_check->bind_param("is", $id_parroquia, $nombre_sector);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $row_check = $result_check->fetch_assoc();
        
        if ($row_check['count'] > 0) {
            $error_msg = "❌ El sector '$nombre_sector' ya existe en esta parroquia.";
        } else {
            $stmt = $conn->prepare("INSERT INTO sectores (id_parroquia, nombre_sector) VALUES (?, ?)");
            $stmt->bind_param("is", $id_parroquia, $nombre_sector);

            if ($stmt->execute()) {
                $success_msg = "✅ Sector '$nombre_sector' agregado exitosamente.";
                // Limpiar el campo después de agregar
                $_POST['nombre_sector'] = '';
                // Resetear los selects
                unset($_POST['id_municipio']);
                unset($_POST['id_parroquia']);
            } else {
                $error_msg = "❌ Error al agregar el sector: " . $stmt->error;
            }
            $stmt->close();
        }
        $stmt_check->close();
    }
}

// Variables para los filtros
$id_municipio_filtro = isset($_POST['id_municipio_filtro']) ? intval($_POST['id_municipio_filtro']) : 0;

// Obtener todos los municipios para el filtro y para el formulario de agregar
$municipios = [];
$result_municipios = $conn->query("SELECT id_municipio, nombre_municipio FROM municipios ORDER BY nombre_municipio ASC");
if ($result_municipios) {
    while ($row = $result_municipios->fetch_assoc()) {
        $municipios[] = $row;
    }
}

// Obtener parroquias para el filtro si se ha seleccionado un municipio
$parroquias_filtro = [];
if ($id_municipio_filtro > 0) {
    $stmt_parroquias_filtro = $conn->prepare("SELECT id_parroquia, nombre_parroquia FROM parroquias WHERE id_municipio = ? ORDER BY nombre_parroquia ASC");
    $stmt_parroquias_filtro->bind_param("i", $id_municipio_filtro);
    $stmt_parroquias_filtro->execute();
    $result_parroquias_filtro = $stmt_parroquias_filtro->get_result();
    while ($row = $result_parroquias_filtro->fetch_assoc()) {
        $parroquias_filtro[] = $row;
    }
    $stmt_parroquias_filtro->close();
}

// Obtener parroquias para el formulario de agregar si se ha seleccionado un municipio
$parroquias_agregar = [];
if (isset($_POST['id_municipio']) && !empty($_POST['id_municipio'])) {
    $id_municipio_agregar = intval($_POST['id_municipio']);
    $stmt_parroquias_agregar = $conn->prepare("SELECT id_parroquia, nombre_parroquia FROM parroquias WHERE id_municipio = ? ORDER BY nombre_parroquia ASC");
    $stmt_parroquias_agregar->bind_param("i", $id_municipio_agregar);
    $stmt_parroquias_agregar->execute();
    $result_parroquias_agregar = $stmt_parroquias_agregar->get_result();
    while ($row = $result_parroquias_agregar->fetch_assoc()) {
        $parroquias_agregar[] = $row;
    }
    $stmt_parroquias_agregar->close();
}
?>

<!-- Contenido principal de la página -->
<div class="container">
    <!-- Título Principal -->
    <h1 style="font-weight:900; font-family:montserrat; color:green; font-size:40px; padding:20px; text-align:left; font-size:50px;">
        <i class="zmdi zmdi-pin"></i> Gestión de <span style="font-weight:700; color:black;">Sectores</span>
    </h1>

    <!-- Sección de Consulta -->
    <div class="section-container">
        <h2 class="section-title">Consulta de Sectores</h2>
        
        <?php if (isset($success_msg)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($error_msg)): ?>
            <div class="alert alert-" style="background-color:green; color:#white;">
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario de Filtros - Solo Municipio -->
        <form method="POST" action="" class="mb-4">
            <input type="hidden" name="action" value="filtrar">
            <div class="field-row">
                <div class="field-col">
                    <label for="id_municipio_filtro" class="field-label">Municipio</label>
                    <select class="form-control" id="id_municipio_filtro" name="id_municipio_filtro">
                        <option value="0">-- Todos los Municipios --</option>
                        <?php foreach ($municipios as $municipio): ?>
                            <option value="<?php echo $municipio['id_municipio']; ?>" <?php echo ($id_municipio_filtro == $municipio['id_municipio']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($municipio['nombre_municipio']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="field-col" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </div>
        </form>

        <!-- Tabla de Resultados -->
        <div class="table-responsive mt-4">
            <table class="table table-striped table-hover">
                <thead class="thead-light">
                    <tr>
                        <th>Municipio</th>
                        <th>Parroquia</th>
                        <th>Sector</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Construir la consulta con filtros
                    $query = "
                        SELECT 
                            m.nombre_municipio,
                            p.nombre_parroquia,
                            s.nombre_sector
                        FROM municipios m
                        INNER JOIN parroquias p ON m.id_municipio = p.id_municipio
                        INNER JOIN sectores s ON p.id_parroquia = s.id_parroquia
                    ";
                    
                    $params = [];
                    $types = "";
                    
                    if ($id_municipio_filtro > 0) {
                        $query .= " WHERE m.id_municipio = ?";
                        $types .= "i";
                        $params[] = $id_municipio_filtro;
                    }
                    
                    $query .= " ORDER BY m.nombre_municipio, p.nombre_parroquia, s.nombre_sector";
                    
                    if (!empty($params)) {
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $stmt->close();
                    } else {
                        $result = $conn->query($query);
                    }
                    
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['nombre_municipio']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['nombre_parroquia']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['nombre_sector']) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3' class='text-center'>No hay sectores registrados para los filtros seleccionados.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sección para Agregar Sector -->
    <div class="section-container">
        <h2 class="section-title">Agregar Nuevo Sector</h2>
        
        <form method="POST" action="" id="formAgregarSector">
            <input type="hidden" name="action" value="agregar_sector">
            
            <div class="field-row">
                <div class="field-col">
                    <label for="id_municipio" class="field-label required">Municipio</label>
                    <select class="form-control" id="id_municipio" name="id_municipio" required>
                        <option value="">-- Seleccione un Municipio --</option>
                        <?php foreach ($municipios as $municipio): ?>
                            <option value="<?php echo $municipio['id_municipio']; ?>" <?php echo (isset($_POST['id_municipio']) && $_POST['id_municipio'] == $municipio['id_municipio']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($municipio['nombre_municipio']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-col">
                    <label for="id_parroquia" class="field-label required">Parroquia</label>
                    <select class="form-control" id="id_parroquia" name="id_parroquia" required>
                        <?php if (!empty($parroquias_agregar)): ?>
                            <option value="">-- Seleccione una Parroquia --</option>
                            <?php foreach ($parroquias_agregar as $parroquia): ?>
                                <option value="<?php echo $parroquia['id_parroquia']; ?>" <?php echo (isset($_POST['id_parroquia']) && $_POST['id_parroquia'] == $parroquia['id_parroquia']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($parroquia['nombre_parroquia']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">-- Primero seleccione un Municipio --</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="field-col">
                    <label for="nombre_sector" class="field-label required">Nombre del Sector</label>
                    <input type="text" class="form-control" id="nombre_sector" name="nombre_sector" 
                           placeholder="Ej: Sector Las Flores" 
                           value="<?php echo isset($_POST['nombre_sector']) ? htmlspecialchars($_POST['nombre_sector']) : ''; ?>" 
                           required>
                </div>
            </div>
            
            <div class="button-container">
                <button type="submit" class="btn btn-primary">Guardar Sector</button>
                <button type="reset" class="btn btn-secondary">Limpiar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar el cambio de municipio en el formulario de agregar
    const municipioSelect = document.getElementById('id_municipio');
    const parroquiaSelect = document.getElementById('id_parroquia');
    
    if (municipioSelect && parroquiaSelect) {
        municipioSelect.addEventListener('change', function() {
            if (this.value) {
                // Mostrar mensaje de carga
                parroquiaSelect.innerHTML = '<option value="">Cargando parroquias...</option>';
                
                // Enviar el formulario para cargar las parroquias
                document.getElementById('formAgregarSector').submit();
            } else {
                // Restablecer el select de parroquias
                parroquiaSelect.innerHTML = '<option value="">-- Primero seleccione un Municipio --</option>';
                parroquiaSelect.disabled = true;
            }
        });
    }
});
</script>

<?php
include("footer.php"); // Incluye el pie de página y cualquier cierre HTML
?>

<!-- Scripts para que funcione el sidebar-->
<script src="./js/jquery-3.1.1.min.js"></script>
<script src="./js/sweetalert2.min.js"></script>
<script src="./js/bootstrap.min.js"></script>
<script src="./js/material.min.js"></script>
<script src="./js/ripples.min.js"></script>
<script src="./js/jquery.mCustomScrollbar.concat.min.js"></script>
<script src="./js/main.js"></script>
<script>
    $.material.init();
</script>