<?php
include('header.php');
include('conexion.php');

// Variables para el reporte
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$filtro_municipio = $_GET['filtro_municipio'] ?? 'todos';
$resultados = array();
$mensaje = '';
$tipo_mensaje = '';
$total_superficie = 0; // Variable para almacenar la sumatoria total

// Obtener lista de municipios para el filtro
$municipios = array();
$sql_municipios = "SELECT id_municipio, nombre_municipio FROM municipios ORDER BY nombre_municipio";
$result_municipios = $conn->query($sql_municipios);
if ($result_municipios) {
    while ($row = $result_municipios->fetch_assoc()) {
        $municipios[] = $row;
    }
    $result_municipios->free();
}

// Procesar el reporte cuando se envíe el formulario
if (isset($_GET['generar']) && !empty($fecha_inicio) && !empty($fecha_fin)) {
    // Validar que las fechas sean válidas
    if (strtotime($fecha_inicio) === false || strtotime($fecha_fin) === false) {
        $mensaje = "Por favor, ingrese fechas válidas en formato YYYY-MM-DD.";
        $tipo_mensaje = "error";
    } elseif (strtotime($fecha_inicio) > strtotime($fecha_fin)) {
        $mensaje = "La fecha de inicio no puede ser posterior a la fecha de fin.";
        $tipo_mensaje = "error";
    } else {
        try {
            $sql = "
                SELECT
                    s.fecha_solicitud,
                    CASE
                        WHEN s.tipo_solicitante = 'N' THEN pn.cedula
                        WHEN s.tipo_solicitante = 'J' THEN pj.rif
                        WHEN s.tipo_solicitante = 'C' THEN c.rif_o_ci_referente
                        ELSE 'N/A'
                    END as identificacion,
                    CASE
                        WHEN s.tipo_solicitante = 'N' THEN CONCAT(pn.primer_nombre, ' ', IFNULL(pn.segundo_nombre, ''), ' ', pn.primer_apellido, ' ', IFNULL(pn.segundo_apellido, ''))
                        WHEN s.tipo_solicitante = 'J' THEN pj.razon_social
                        WHEN s.tipo_solicitante = 'C' THEN c.nombre_colectivo
                        ELSE 'Desconocido'
                    END as nombre,
                    tp.nombre_procedimiento as tipo_solicitud,
                    p.nombre_predio as predio,
                    p.direccion as direccion_predio,
                    p.superficie_ha as superficie,
                    p.lindero_norte,
                    p.lindero_sur,
                    p.lindero_este,
                    p.lindero_oeste,
                    pa.nombre_parroquia as parroquia,
                    se.nombre_sector as sector,
                    s.estatus,
                    s.observaciones,
                    m.nombre_municipio as municipio  -- Añadido el municipio
                FROM solicitudes s
                LEFT JOIN tipo_procedimiento tp ON s.id_procedimiento = tp.id_procedimiento
                LEFT JOIN predios p ON s.id_predio = p.id_predio
                LEFT JOIN municipios m ON p.id_municipio = m.id_municipio  -- Unión con la tabla municipios
                LEFT JOIN parroquias pa ON p.id_parroquia = pa.id_parroquia
                LEFT JOIN sectores se ON p.id_sector = se.id_sector
                LEFT JOIN personas_naturales pn ON s.cedula_solicitante_n = pn.cedula
                LEFT JOIN personas_juridicas pj ON s.rif_solicitante_j = pj.rif
                LEFT JOIN colectivos c ON s.rif_ci_solicitante_c = c.rif_o_ci_referente
                WHERE s.fecha_solicitud BETWEEN ? AND ?
            ";

            // Agregar filtro por municipio
            if ($filtro_municipio !== 'todos') {
                $sql .= " AND p.id_municipio = '" . $conn->real_escape_string($filtro_municipio) . "'";
            }

            $sql .= " ORDER BY s.fecha_solicitud DESC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $resultados[] = $row;
                    // Sumar la superficie al total (convertir a float si es necesario)
                    $total_superficie += floatval($row['superficie'] ?? 0);
                }
            } else {
                $mensaje = "No se encontraron solicitudes entre las fechas: $fecha_inicio y $fecha_fin";
                $tipo_mensaje = "info";
            }
            $stmt->close();
        } catch (Exception $e) {
            $mensaje = "Error al generar el reporte: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}
?>

    <div class="container">
        <!-- Título principal -->
        
        <h1 style="font-weight:900; font-family:montserrat; color:green; font-size:40px; padding:20px; text-align:left; font-size:50px;"><i class="zmdi zmdi-assignment-o"></i> Reporte <span style="font-weight:700; color:black;">de Superficie</span></h1>

        <!-- Formulario de selección de fechas y filtros -->
        <div class="section-container">
            <h2 class="section-title">Seleccionar Rango de Fechas y Filtros</h2>
            <form method="GET" style="margin-bottom: 30px;">
                <input type="hidden" name="generar" value="1">
                <div class="field-row">
                    <div class="field-col">
                        <label for="fecha_inicio" class="field-label required">Fecha de Inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio); ?>" class="form-control" required>
                    </div>
                    <div class="field-col">
                        <label for="fecha_fin" class="field-label required">Fecha de Fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin); ?>" class="form-control" required>
                    </div>
                    <div class="field-col">
                        <label for="filtro_municipio" class="field-label">Municipio</label>
                        <select id="filtro_municipio" name="filtro_municipio" class="form-control">
                            <option value="todos" <?= $filtro_municipio == 'todos' ? 'selected' : ''; ?>>Todos los Municipios</option>
                            <?php foreach ($municipios as $municipio): ?>
                                <option value="<?= $municipio['id_municipio']; ?>" <?= $filtro_municipio == $municipio['id_municipio'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($municipio['nombre_municipio']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-col" style="align-self: end;">
                        <button type="submit" class="btn btn-primary">Generar Reporte</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $tipo_mensaje == 'error' ? 'danger' : ($tipo_mensaje == 'info' ? 'warning' : 'success'); ?>">
                <?= htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Resultados del reporte -->
        <?php if (!empty($resultados)): ?>
            <div class="section-container">
                <h2 class="section-title">Resultados del Reporte</h2>
                <div class="results-count">
                    Se encontraron <strong><?= count($resultados); ?></strong> solicitud(es) entre <strong><?= date('d/m/Y', strtotime($fecha_inicio)); ?></strong> y <strong><?= date('d/m/Y', strtotime($fecha_fin)); ?></strong>
                    <br><strong>Total de Superficie (Ha): <?= number_format($total_superficie, 2); ?></strong>
                </div>

                <div class="table-responsive">
                    <table id="tabla-superficie" class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Nº</th>
                                <th>FECHA</th>
                                <th>CÉDULA/RIF</th>
                                <th>NOMBRE</th>
                                <th>SOLICITUD TIPO</th>
                                <th>PREDIO</th>
                                <th>DIRECCIÓN</th>
                                <th>SUPERFICIE (Ha)</th>
                                <th>LINDEROS</th>
                                <th>MUNICIPIO</th> <!-- Columna añadida -->
                                <th>PARROQUIA</th>
                                <th>SECTOR</th>
                                <th>ESTATUS</th>
                                <th>OBSERVACIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $contador = 1; ?>
                            <?php foreach ($resultados as $solicitud): ?>
                                <tr>
                                    <td><strong><?= $contador++; ?></strong></td>
                                    <td><?= date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?></td>
                                    <td><strong><?= htmlspecialchars($solicitud['identificacion']); ?></strong></td>
                                    <td><?= htmlspecialchars($solicitud['nombre']); ?></td>
                                    <td><?= htmlspecialchars($solicitud['tipo_solicitud']); ?></td>
                                    <td><?= htmlspecialchars($solicitud['predio']); ?></td>
                                    <td><?= htmlspecialchars($solicitud['direccion_predio'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars($solicitud['superficie'] ?? 'N/A'); ?></td>
                                    <td>
                                        <small>
                                            N: <?= htmlspecialchars($solicitud['lindero_norte'] ?? 'N/A'); ?><br>
                                            S: <?= htmlspecialchars($solicitud['lindero_sur'] ?? 'N/A'); ?><br>
                                            E: <?= htmlspecialchars($solicitud['lindero_este'] ?? 'N/A'); ?><br>
                                            O: <?= htmlspecialchars($solicitud['lindero_oeste'] ?? 'N/A'); ?>
                                        </small>
                                    </td>
                                    <td><?= htmlspecialchars($solicitud['municipio'] ?? 'N/A'); ?></td> <!-- Dato del municipio -->
                                    <td><?= htmlspecialchars($solicitud['parroquia']); ?></td>
                                    <td><?= htmlspecialchars($solicitud['sector']); ?></td>
                                    <td><span class="status <?= strtolower(str_replace(' ', '_', $solicitud['estatus'])); ?>"><?= htmlspecialchars(str_replace('_', ' ', $solicitud['estatus'])); ?></span></td>
                                    <td><?= htmlspecialchars($solicitud['observaciones'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Botón para generar PDF -->
                <form action="pdf_superficie.php" method="POST" target="_blank" style="margin-top: 20px;">
                    <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio); ?>">
                    <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin); ?>">
                    <input type="hidden" name="filtro_municipio" value="<?= htmlspecialchars($filtro_municipio); ?>">
                    <input type="hidden" name="resultados_json" value='<?= json_encode($resultados, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                    <input type="hidden" name="total_superficie" value="<?= $total_superficie; ?>"> <!-- Pasar el total al PDF -->
                    <button type="submit" class="btn btn-primary"><i class="zmdi zmdi-assignment-o"></i> Generar PDF</button>
                </form>
            </div>
        <?php elseif ($fecha_inicio && $fecha_fin && empty($mensaje)): ?>
            <div class="section-container">
                <div class="no-results">
                    <h3>No se encontraron resultados</h3>
                    <p>No hay solicitudes registradas entre las fechas seleccionadas.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts para que funcione el sidebar -->
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

    <!-- Script para inicializar DataTables -->
    <script>
        $(document).ready(function() {
            $('#tabla-superficie').DataTable({
                "pageLength": 10,
                "lengthMenu": [10, 25, 50, 100],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json"
                },
                "order": [[0, 'desc']]
            });
        });
    </script>

<?php include("footer.php"); ?>