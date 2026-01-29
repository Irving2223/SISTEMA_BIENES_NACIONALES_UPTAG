<?php
include('header.php');
include('conexion.php');

// Variables para el reporte
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$filtro_tipo = $_GET['filtro_tipo'] ?? 'todos';
$resultados = array();
$mensaje = '';
$tipo_mensaje = '';

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
                    s.numero_solicitud,
                    s.fecha_solicitud,
                    s.tipo_solicitante,
                    CASE
                        WHEN s.tipo_solicitante = 'N' THEN CONCAT(pn.primer_nombre, ' ', IFNULL(pn.segundo_nombre, ''), ' ', pn.primer_apellido, ' ', IFNULL(pn.segundo_apellido, ''))
                        WHEN s.tipo_solicitante = 'J' THEN pj.razon_social
                        WHEN s.tipo_solicitante = 'C' THEN c.nombre_colectivo
                        ELSE 'Desconocido'
                    END as beneficiario,
                    CASE
                        WHEN s.tipo_solicitante = 'N' THEN pn.cedula
                        WHEN s.tipo_solicitante = 'J' THEN pj.rif
                        WHEN s.tipo_solicitante = 'C' THEN c.rif_o_ci_referente
                        ELSE 'N/A'
                    END as identificacion,
                    CASE
                        WHEN s.tipo_solicitante = 'N' THEN pn.sexo
                        WHEN s.tipo_solicitante = 'J' THEN 'N/A'
                        WHEN s.tipo_solicitante = 'C' THEN ci.sexo
                        ELSE 'N/A'
                    END as sexo,
                    CASE
                        WHEN s.tipo_solicitante = 'N' THEN TIMESTAMPDIFF(YEAR, pn.fecha_nacimiento, CURDATE())
                        WHEN s.tipo_solicitante = 'C' THEN TIMESTAMPDIFF(YEAR, ci.fecha_nacimiento, CURDATE())
                        ELSE NULL
                    END as edad,
                    CASE
                        WHEN s.tipo_solicitante = 'N' THEN pn.telefono
                        WHEN s.tipo_solicitante = 'J' THEN pj.telefono
                        WHEN s.tipo_solicitante = 'C' THEN c.telefono
                        ELSE 'N/A'
                    END as telefono,
                    p.nombre_predio as predio,
                    p.superficie_ha as superficie,
                    'Falcón' as estado_atencion,
                    'Falcón' as estado_predio,
                    m.nombre_municipio as municipio,
                    pa.nombre_parroquia as parroquia,
                    se.nombre_sector as sector,
                    tp.nombre_procedimiento as requerimiento,
                    s.observaciones
                FROM solicitudes s
                LEFT JOIN tipo_procedimiento tp ON s.id_procedimiento = tp.id_procedimiento
                LEFT JOIN predios p ON s.id_predio = p.id_predio
                LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
                LEFT JOIN parroquias pa ON p.id_parroquia = pa.id_parroquia
                LEFT JOIN sectores se ON p.id_sector = se.id_sector
                LEFT JOIN personas_naturales pn ON s.cedula_solicitante_n = pn.cedula
                LEFT JOIN personas_juridicas pj ON s.rif_solicitante_j = pj.rif
                LEFT JOIN colectivos c ON s.rif_ci_solicitante_c = c.rif_o_ci_referente
                LEFT JOIN colectivo_integrantes ci ON c.rif_o_ci_referente = ci.rif_o_ci_colectivo AND ci.es_referente = 1
                WHERE s.fecha_solicitud BETWEEN ? AND ?
            ";

            // Agregar filtro por tipo de solicitante
            if ($filtro_tipo !== 'todos') {
                $tipo_map = [
                    'naturales' => 'N',
                    'juridicos' => 'J',
                    'colectivos' => 'C'
                ];
                if (isset($tipo_map[$filtro_tipo])) {
                    $sql .= " AND s.tipo_solicitante = '" . $tipo_map[$filtro_tipo] . "'";
                }
            }

            $sql .= " ORDER BY s.fecha_solicitud DESC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $resultados[] = $row;
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
	    <h1 style="font-weight:900; font-family:montserrat; color:green; font-size:40px; padding:20px; text-align:left; font-size:50px;"><i class="zmdi zmdi-file-plus"></i> Reporte <span style="font-weight:700; color:black;">de Solicitudes</span></h1>

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
                        <label for="filtro_tipo" class="field-label">Tipo de Solicitante</label>
                        <select id="filtro_tipo" name="filtro_tipo" class="form-control">
                            <option value="todos" <?= $filtro_tipo == 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="naturales" <?= $filtro_tipo == 'naturales' ? 'selected' : ''; ?>>Personas Naturales</option>
                            <option value="juridicos" <?= $filtro_tipo == 'juridicos' ? 'selected' : ''; ?>>Personas Jurídicas</option>
                            <option value="colectivos" <?= $filtro_tipo == 'colectivos' ? 'selected' : ''; ?>>Colectivos</option>
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
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Nº</th>
                                <th>FECHA</th>
                                <th>BENEFICIARIO</th>
                                <th>CÉDULA DE IDENTIDAD / RIF</th>
                                <th>TIPO SOLICITANTE</th>
                                <th>SEXO</th>
                                <th>EDAD</th>
                                <th>TELÉFONO</th>
                                <th>PREDIO</th>
                                <th>SUPERFICIE</th>
                                <th>ESTADO DE ATENCIÓN</th>
                                <th>ESTADO DEL PREDIO</th>
                                <th>MUNICIPIO</th>
                                <th>PARROQUIA</th>
                                <th>SECTOR</th>
                                <th>REQUERIMIENTO</th>
                                <th>OBSERVACIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $contador = 1; ?>
                            <?php foreach ($resultados as $solicitud): ?>
                                <tr>
                                    <td data-label="Nº"><?= $contador++; ?></td>
                                    <td data-label="FECHA"><?= date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?></td>
                                    <td data-label="BENEFICIARIO"><?= htmlspecialchars($solicitud['beneficiario']); ?></td>
                                    <td data-label="CÉDULA DE IDENTIDAD / RIF"><strong><?= htmlspecialchars($solicitud['identificacion']); ?></strong></td>
                                    <td data-label="TIPO SOLICITANTE">
                                        <?php
                                        $tipo_descripcion = '';
                                        switch ($solicitud['tipo_solicitante']) {
                                            case 'N':
                                                $tipo_descripcion = 'Natural';
                                                break;
                                            case 'J':
                                                $tipo_descripcion = 'Jurídico';
                                                break;
                                            case 'C':
                                                $tipo_descripcion = 'Colectivo';
                                                break;
                                            default:
                                                $tipo_descripcion = 'Desconocido';
                                        }
                                        ?>
                                        <span class="status tipo-<?= strtolower($tipo_descripcion); ?>"><?= htmlspecialchars($tipo_descripcion); ?></span>
                                    </td>
                                    <td data-label="SEXO"><?= htmlspecialchars($solicitud['sexo'] ?? 'N/A'); ?></td>
                                    <td data-label="EDAD"><?php
                                        if ($solicitud['edad'] !== null && $solicitud['edad'] > 0) {
                                            echo htmlspecialchars($solicitud['edad']) . ' años';
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?></td>
                                    <td data-label="TELÉFONO"><?= htmlspecialchars($solicitud['telefono'] ?? 'N/A'); ?></td>
                                    <td data-label="PREDIO"><?= htmlspecialchars($solicitud['predio'] ?? 'N/A'); ?></td>
                                    <td data-label="SUPERFICIE"><?= htmlspecialchars($solicitud['superficie'] ?? 'N/A'); ?> ha</td>
                                    <td data-label="ESTADO DE ATENCIÓN"><?= htmlspecialchars($solicitud['estado_atencion']); ?></td>
                                    <td data-label="ESTADO DEL PREDIO"><?= htmlspecialchars($solicitud['estado_predio']); ?></td>
                                    <td data-label="MUNICIPIO"><?= htmlspecialchars($solicitud['municipio'] ?? 'N/A'); ?></td>
                                    <td data-label="PARROQUIA"><?= htmlspecialchars($solicitud['parroquia'] ?? 'N/A'); ?></td>
                                    <td data-label="SECTOR"><?= htmlspecialchars($solicitud['sector'] ?? 'N/A'); ?></td>
                                    <td data-label="REQUERIMIENTO"><?= htmlspecialchars($solicitud['requerimiento'] ?? 'N/A'); ?></td>
                                    <td data-label="OBSERVACIONES"><?= htmlspecialchars($solicitud['observaciones'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Botón para generar PDF -->
                <form action="pdf_solicitudes.php" method="POST" target="_blank" style="margin-top: 20px;">
                    <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio); ?>">
                    <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin); ?>">
                    <input type="hidden" name="filtro_tipo" value="<?= htmlspecialchars($filtro_tipo); ?>">
                    <input type="hidden" name="resultados_json" value='<?= json_encode($resultados, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                    <button type="submit" class="btn btn-primary"><i class="zmdi zmdi-file-plus"></i> Generar PDF</button>
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
<?php include("footer.php"); ?>