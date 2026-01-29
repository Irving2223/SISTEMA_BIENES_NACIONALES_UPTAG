<?php
include('header.php');
include('conexion.php');

// Variables para el reporte
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$tipo_solicitante = $_GET['tipo_solicitante'] ?? 'todos';
$sexo_filtro = $_GET['sexo'] ?? '';
$edad_min = $_GET['edad_min'] ?? '';
$edad_max = $_GET['edad_max'] ?? '';
$resultados_naturales = array();
$resultados_juridicas = array();
$resultados_colectivos = array();
$resultados_integrantes_colectivos = array();
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
            // Obtener personas naturales activas en el rango de fechas
            if ($tipo_solicitante == 'todos' || $tipo_solicitante == 'natural') {
                $sql_naturales = "
                    SELECT 
                        pn.cedula,
                        CONCAT(pn.primer_nombre, ' ', IFNULL(pn.segundo_nombre, ''), ' ', pn.primer_apellido, ' ', IFNULL(pn.segundo_apellido, '')) as nombre_completo,
                        pn.sexo,
                        pn.fecha_nacimiento,
                        pn.telefono,
                        pn.direccion_habitacion,
                        pn.estado_civil,
                        pn.numero_hijos,
                        pn.grado_instruccion,
                        pn.sabe_leer,
                        pn.posee_ayuda_economica,
                        pn.trabaja_actualmente,
                        pn.pertenece_comuna,
                        pn.enfermedades,
                        pn.creado_en,
                        CASE WHEN pn.id_representante IS NOT NULL THEN 'Sí' ELSE 'No' END as tiene_representante,
                        r.primer_nombre as rep_primer_nombre,
                        r.segundo_nombre as rep_segundo_nombre,
                        r.primer_apellido as rep_primer_apellido,
                        r.segundo_apellido as rep_segundo_apellido,
                        r.sexo as rep_sexo,
                        r.telefono as rep_telefono,
                        r.direccion as rep_direccion,
                        r.email as rep_email,
                        r.profesion as rep_profesion,
                        r.tipo as rep_tipo
                    FROM personas_naturales pn
                    LEFT JOIN representantes r ON pn.id_representante = r.id_representante
                    WHERE pn.activo = 1 AND pn.creado_en BETWEEN ? AND ?
                ";
                
                // Agregar filtros de sexo y edad si están presentes
                $params = array($fecha_inicio, $fecha_fin);
                $types = "ss";
                
                if (!empty($sexo_filtro)) {
                    $sql_naturales .= " AND pn.sexo = ?";
                    $params[] = $sexo_filtro;
                    $types .= "s";
                }
                
                if (!empty($edad_min)) {
                    $sql_naturales .= " AND TIMESTAMPDIFF(YEAR, pn.fecha_nacimiento, CURDATE()) >= ?";
                    $params[] = $edad_min;
                    $types .= "i";
                }
                
                if (!empty($edad_max)) {
                    $sql_naturales .= " AND TIMESTAMPDIFF(YEAR, pn.fecha_nacimiento, CURDATE()) <= ?";
                    $params[] = $edad_max;
                    $types .= "i";
                }
                
                $sql_naturales .= " ORDER BY pn.primer_apellido, pn.primer_nombre";
                
                $stmt_naturales = $conn->prepare($sql_naturales);
                $stmt_naturales->bind_param($types, ...$params);
                $stmt_naturales->execute();
                $result_naturales = $stmt_naturales->get_result();

                if ($result_naturales->num_rows > 0) {
                    while ($row = $result_naturales->fetch_assoc()) {
                        // Calcular edad
                        $fecha_nac = new DateTime($row['fecha_nacimiento']);
                        $hoy = new DateTime();
                        $edad = $hoy->diff($fecha_nac)->y;
                        $row['edad'] = $edad;
                        
                        // Excluir registros con sexo 'Otro'
                        if ($row['sexo'] !== 'Otro') {
                            $resultados_naturales[] = $row;
                        }
                    }
                }
                $stmt_naturales->close();
            }

            // Obtener personas jurídicas activas en el rango de fechas
            if ($tipo_solicitante == 'todos' || $tipo_solicitante == 'juridica') {
                $sql_juridicas = "
                    SELECT 
                        pj.rif,
                        pj.razon_social,
                        pj.telefono,
                        pj.direccion_habitacion,
                        pj.estado_civil,
                        pj.numero_hijos,
                        pj.grado_instruccion,
                        pj.sabe_leer,
                        pj.posee_ayuda_economica,
                        pj.trabaja_actualmente,
                        pj.pertenece_comuna,
                        pj.enfermedades,
                        pj.creado_en,
                        r.primer_nombre as rep_primer_nombre,
                        r.segundo_nombre as rep_segundo_nombre,
                        r.primer_apellido as rep_primer_apellido,
                        r.segundo_apellido as rep_segundo_apellido,
                        r.sexo as rep_sexo,
                        r.telefono as rep_telefono,
                        r.direccion as rep_direccion,
                        r.email as rep_email,
                        r.profesion as rep_profesion,
                        r.tipo as rep_tipo
                    FROM personas_juridicas pj
                    LEFT JOIN representantes r ON pj.id_representante = r.id_representante
                    WHERE pj.activo = 1 AND pj.creado_en BETWEEN ? AND ?
                ";
                
                // Aplicar filtro de sexo al representante legal
                $params = array($fecha_inicio, $fecha_fin);
                $types = "ss";
                
                if (!empty($sexo_filtro)) {
                    $sql_juridicas .= " AND r.sexo = ?";
                    $params[] = $sexo_filtro;
                    $types .= "s";
                }
                
                $sql_juridicas .= " ORDER BY pj.razon_social";
                
                $stmt_juridicas = $conn->prepare($sql_juridicas);
                $stmt_juridicas->bind_param($types, ...$params);
                $stmt_juridicas->execute();
                $result_juridicas = $stmt_juridicas->get_result();

                if ($result_juridicas->num_rows > 0) {
                    while ($row = $result_juridicas->fetch_assoc()) {
                        // Calcular edad del representante si tiene fecha de nacimiento
                        $edad_rep = 'N/A';
                        if (!empty($row['rep_fecha_nacimiento'])) {
                            $fecha_nac_rep = new DateTime($row['rep_fecha_nacimiento']);
                            $hoy = new DateTime();
                            $edad_rep = $hoy->diff($fecha_nac_rep)->y;
                        }
                        $row['edad_representante'] = $edad_rep;
                        
                        $resultados_juridicas[] = $row;
                    }
                }
                $stmt_juridicas->close();
            }

            // Obtener colectivos activos en el rango de fechas
            if ($tipo_solicitante == 'todos' || $tipo_solicitante == 'colectivo') {
                $sql_colectivos = "
                    SELECT 
                        c.rif_o_ci_referente,
                        c.nombre_colectivo,
                        c.numero_integrantes,
                        c.telefono,
                        c.direccion_habitacion,
                        c.creado_en
                    FROM colectivos c
                    WHERE c.activo = 1 AND c.creado_en BETWEEN ? AND ?
                    ORDER BY c.nombre_colectivo
                ";
                
                $stmt_colectivos = $conn->prepare($sql_colectivos);
                $stmt_colectivos->bind_param("ss", $fecha_inicio, $fecha_fin);
                $stmt_colectivos->execute();
                $result_colectivos = $stmt_colectivos->get_result();

                if ($result_colectivos->num_rows > 0) {
                    while ($row = $result_colectivos->fetch_assoc()) {
                        $resultados_colectivos[] = $row;
                        
                        // Obtener integrantes del colectivo
                        $sql_integrantes = "
                            SELECT 
                                ci.cedula,
                                CONCAT(ci.primer_nombre, ' ', IFNULL(ci.segundo_nombre, ''), ' ', ci.primer_apellido, ' ', IFNULL(ci.segundo_apellido, '')) as nombre_completo,
                                ci.sexo,
                                ci.fecha_nacimiento,
                                ci.telefono,
                                ci.direccion_habitacion,
                                ci.estado_civil,
                                ci.numero_hijos,
                                ci.grado_instruccion,
                                ci.sabe_leer,
                                ci.posee_ayuda_economica,
                                ci.trabaja_actualmente,
                                ci.pertenece_comuna,
                                ci.enfermedades,
                                ci.es_referente,
                                ci.cargo_en_colectivo,
                                ci.fecha_ingreso,
                                ci.rif_o_ci_colectivo as rif_colectivo
                            FROM colectivo_integrantes ci
                            WHERE ci.rif_o_ci_colectivo = ? AND ci.activo = 1
                        ";
                        
                        $stmt_integrantes = $conn->prepare($sql_integrantes);
                        $stmt_integrantes->bind_param("s", $row['rif_o_ci_referente']);
                        $stmt_integrantes->execute();
                        $result_integrantes = $stmt_integrantes->get_result();
                        
                        if ($result_integrantes->num_rows > 0) {
                            while ($integrante = $result_integrantes->fetch_assoc()) {
                                // Calcular edad
                                $fecha_nac = new DateTime($integrante['fecha_nacimiento']);
                                $hoy = new DateTime();
                                $edad = $hoy->diff($fecha_nac)->y;
                                $integrante['edad'] = $edad;
                                
                                // Aplicar filtros de sexo y edad
                                $cumple_filtros = true;
                                
                                if (!empty($sexo_filtro) && $integrante['sexo'] !== $sexo_filtro) {
                                    $cumple_filtros = false;
                                }
                                
                                if (!empty($edad_min) && $edad < $edad_min) {
                                    $cumple_filtros = false;
                                }
                                
                                if (!empty($edad_max) && $edad > $edad_max) {
                                    $cumple_filtros = false;
                                }
                                
                                if ($cumple_filtros && $integrante['sexo'] !== 'Otro') {
                                    $resultados_integrantes_colectivos[] = $integrante;
                                }
                            }
                        }
                        $stmt_integrantes->close();
                    }
                }
                $stmt_colectivos->close();
            }

            // Verificar si no se encontraron resultados
            if (empty($resultados_naturales) && empty($resultados_juridicas) && empty($resultados_colectivos)) {
                $mensaje = "No se encontraron solicitantes entre las fechas: $fecha_inicio y $fecha_fin";
                $tipo_mensaje = "info";
            }

        } catch (Exception $e) {
            $mensaje = "Error al generar el reporte: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}
?>

<div class="container">
    <!-- Título principal -->
	<h1 style="font-weight:900; font-family:montserrat; color:green; font-size:40px; padding:20px; text-align:left; font-size:50px;"><i class="zmdi zmdi-file-plus"></i> Reporte <span style="font-weight:700; color:black;">de Solicitantes</span></h1>

    <!-- Formulario de selección de fechas y tipo -->
    <div class="section-container">
        <h2 class="section-title" style="font-family: montserrat; font-weight: 900; color: green;">Filtros del Reporte</h2>
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
                    <label for="tipo_solicitante" class="field-label">Tipo de Solicitante</label>
                    <select id="tipo_solicitante" name="tipo_solicitante" class="form-control">
                        <option value="todos" <?= $tipo_solicitante == 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="natural" <?= $tipo_solicitante == 'natural' ? 'selected' : '' ?>>Persona Natural</option>
                        <option value="juridica" <?= $tipo_solicitante == 'juridica' ? 'selected' : '' ?>>Persona Jurídica</option>
                        <option value="colectivo" <?= $tipo_solicitante == 'colectivo' ? 'selected' : '' ?>>Colectivo</option>
                    </select>
                </div>
            </div>
            
            <div class="field-row" style="margin-top: 15px;">
                <div class="field-col">
                    <label for="sexo" class="field-label">Sexo</label>
                    <select id="sexo" name="sexo" class="form-control">
                        <option value="" <?= empty($sexo_filtro) ? 'selected' : '' ?>>Todos</option>
                        <option value="M" <?= $sexo_filtro == 'M' ? 'selected' : '' ?>>Masculino</option>
                        <option value="F" <?= $sexo_filtro == 'F' ? 'selected' : '' ?>>Femenino</option>
                    </select>
                </div>
                <div class="field-col">
                    <label for="edad_min" class="field-label">Edad Mínima</label>
                    <input type="number" id="edad_min" name="edad_min" value="<?= htmlspecialchars($edad_min); ?>" min="0" max="120" class="form-control">
                </div>
                <div class="field-col">
                    <label for="edad_max" class="field-label">Edad Máxima</label>
                    <input type="number" id="edad_max" name="edad_max" value="<?= htmlspecialchars($edad_max); ?>" min="0" max="120" class="form-control">
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
    <?php if (!empty($resultados_naturales) || !empty($resultados_juridicas) || !empty($resultados_colectivos)): ?>
        <?php 
        $total_registros = count($resultados_naturales) + count($resultados_juridicas) + count($resultados_colectivos);
        $total_naturales = count($resultados_naturales);
        $total_juridicas = count($resultados_juridicas);
        $total_colectivos = count($resultados_colectivos);
        
        // Estadísticas socioeconómicas para Personas Naturales
        $estadisticas_naturales = [
            'sexo' => ['M' => 0, 'F' => 0],
            'estado_civil' => ['Soltero' => 0, 'Casado' => 0, 'Viudo' => 0, 'Divorciado' => 0, 'Concubinato' => 0],
            'grado_instruccion' => ['Sin_nivel' => 0, 'Primaria' => 0, 'Secundaria' => 0, 'Tecnico' => 0, 'Universitario' => 0, 'Postgrado' => 0, 'Otro' => 0],
            'sabe_leer' => ['Si' => 0, 'No' => 0],
            'posee_ayuda_economica' => ['Si' => 0, 'No' => 0],
            'trabaja_actualmente' => ['Si' => 0, 'No' => 0],
            'pertenece_comuna' => ['Si' => 0, 'No' => 0]
        ];
        
        foreach ($resultados_naturales as $pn) {
            if ($pn['sexo'] !== 'Otro') {
                if (isset($estadisticas_naturales['sexo'][$pn['sexo']])) {
                    $estadisticas_naturales['sexo'][$pn['sexo']]++;
                }
            }
            
            if (isset($estadisticas_naturales['estado_civil'][$pn['estado_civil']])) {
                $estadisticas_naturales['estado_civil'][$pn['estado_civil']]++;
            }
            
            if (isset($estadisticas_naturales['grado_instruccion'][$pn['grado_instruccion']])) {
                $estadisticas_naturales['grado_instruccion'][$pn['grado_instruccion']]++;
            }
            
            if (isset($estadisticas_naturales['sabe_leer'][$pn['sabe_leer']])) {
                $estadisticas_naturales['sabe_leer'][$pn['sabe_leer']]++;
            }
            
            if (isset($estadisticas_naturales['posee_ayuda_economica'][$pn['posee_ayuda_economica']])) {
                $estadisticas_naturales['posee_ayuda_economica'][$pn['posee_ayuda_economica']]++;
            }
            
            if (isset($estadisticas_naturales['trabaja_actualmente'][$pn['trabaja_actualmente']])) {
                $estadisticas_naturales['trabaja_actualmente'][$pn['trabaja_actualmente']]++;
            }
            
            if (isset($estadisticas_naturales['pertenece_comuna'][$pn['pertenece_comuna']])) {
                $estadisticas_naturales['pertenece_comuna'][$pn['pertenece_comuna']]++;
            }
        }
        
        // Estadísticas socioeconómicas para Personas Jurídicas (basadas en representantes)
        $estadisticas_juridicas = [
            'sexo_rep' => ['M' => 0, 'F' => 0],
            'estado_civil' => ['Soltero' => 0, 'Casado' => 0, 'Viudo' => 0, 'Divorciado' => 0, 'Concubinato' => 0],
            'grado_instruccion' => ['Sin_nivel' => 0, 'Primaria' => 0, 'Secundaria' => 0, 'Tecnico' => 0, 'Universitario' => 0, 'Postgrado' => 0, 'Otro' => 0],
            'sabe_leer' => ['Si' => 0, 'No' => 0],
            'posee_ayuda_economica' => ['Si' => 0, 'No' => 0],
            'trabaja_actualmente' => ['Si' => 0, 'No' => 0],
            'pertenece_comuna' => ['Si' => 0, 'No' => 0]
        ];
        
        foreach ($resultados_juridicas as $pj) {
            if (isset($estadisticas_juridicas['sexo_rep'][$pj['rep_sexo']])) {
                $estadisticas_juridicas['sexo_rep'][$pj['rep_sexo']]++;
            }
            
            if (isset($estadisticas_juridicas['estado_civil'][$pj['estado_civil']])) {
                $estadisticas_juridicas['estado_civil'][$pj['estado_civil']]++;
            }
            
            if (isset($estadisticas_juridicas['grado_instruccion'][$pj['grado_instruccion']])) {
                $estadisticas_juridicas['grado_instruccion'][$pj['grado_instruccion']]++;
            }
            
            if (isset($estadisticas_juridicas['sabe_leer'][$pj['sabe_leer']])) {
                $estadisticas_juridicas['sabe_leer'][$pj['sabe_leer']]++;
            }
            
            if (isset($estadisticas_juridicas['posee_ayuda_economica'][$pj['posee_ayuda_economica']])) {
                $estadisticas_juridicas['posee_ayuda_economica'][$pj['posee_ayuda_economica']]++;
            }
            
            if (isset($estadisticas_juridicas['trabaja_actualmente'][$pj['trabaja_actualmente']])) {
                $estadisticas_juridicas['trabaja_actualmente'][$pj['trabaja_actualmente']]++;
            }
            
            if (isset($estadisticas_juridicas['pertenece_comuna'][$pj['pertenece_comuna']])) {
                $estadisticas_juridicas['pertenece_comuna'][$pj['pertenece_comuna']]++;
            }
        }
        
        // Estadísticas socioeconómicas para Integrantes de Colectivos
        $estadisticas_colectivos = [
            'sexo' => ['M' => 0, 'F' => 0],
            'estado_civil' => ['Soltero' => 0, 'Casado' => 0, 'Viudo' => 0, 'Divorciado' => 0, 'Concubinato' => 0],
            'grado_instruccion' => ['Sin_nivel' => 0, 'Primaria' => 0, 'Secundaria' => 0, 'Tecnico' => 0, 'Universitario' => 0, 'Postgrado' => 0, 'Otro' => 0],
            'sabe_leer' => ['Si' => 0, 'No' => 0],
            'posee_ayuda_economica' => ['Si' => 0, 'No' => 0],
            'trabaja_actualmente' => ['Si' => 0, 'No' => 0],
            'pertenece_comuna' => ['Si' => 0, 'No' => 0]
        ];
        
        foreach ($resultados_integrantes_colectivos as $ic) {
            if ($ic['sexo'] !== 'Otro') {
                if (isset($estadisticas_colectivos['sexo'][$ic['sexo']])) {
                    $estadisticas_colectivos['sexo'][$ic['sexo']]++;
                }
            }
            
            if (isset($estadisticas_colectivos['estado_civil'][$ic['estado_civil']])) {
                $estadisticas_colectivos['estado_civil'][$ic['estado_civil']]++;
            }
            
            if (isset($estadisticas_colectivos['grado_instruccion'][$ic['grado_instruccion']])) {
                $estadisticas_colectivos['grado_instruccion'][$ic['grado_instruccion']]++;
            }
            
            if (isset($estadisticas_colectivos['sabe_leer'][$ic['sabe_leer']])) {
                $estadisticas_colectivos['sabe_leer'][$ic['sabe_leer']]++;
            }
            
            if (isset($estadisticas_colectivos['posee_ayuda_economica'][$ic['posee_ayuda_economica']])) {
                $estadisticas_colectivos['posee_ayuda_economica'][$ic['posee_ayuda_economica']]++;
            }
            
            if (isset($estadisticas_colectivos['trabaja_actualmente'][$ic['trabaja_actualmente']])) {
                $estadisticas_colectivos['trabaja_actualmente'][$ic['trabaja_actualmente']]++;
            }
            
            if (isset($estadisticas_colectivos['pertenece_comuna'][$ic['pertenece_comuna']])) {
                $estadisticas_colectivos['pertenece_comuna'][$ic['pertenece_comuna']]++;
            }
        }
        ?>
        
        <div class="section-container">
            <h2 class="section-title" style="font-family: montserrat; font-weight: 900; color: green;">Resultados del Reporte</h2>
            
            <div class="results-count">
                Se encontraron <strong><?= $total_registros ?></strong> solicitante(s) entre <strong><?= date('d/m/Y', strtotime($fecha_inicio)); ?></strong> y <strong><?= date('d/m/Y', strtotime($fecha_fin)); ?></strong>
            </div>

            <!-- Estadísticas generales -->
            <div class="table-responsive" style="margin-top: 20px;">
                <h3 class="subsection-title" style="font-family: montserrat; font-weight: 900; color: green;">Estadísticas Generales</h3>
                <table>
                    <thead>
                        <tr>
                            <th>CATEGORÍA</th>
                            <th>PERSONAS NATURALES</th>
                            <th>PERSONAS JURÍDICAS</th>
                            <th>COLECTIVOS</th>
                            <th>TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td data-label="CATEGORÍA"><strong>Total</strong></td>
                            <td data-label="PERSONAS NATURALES"><strong><?= $total_naturales ?></strong></td>
                            <td data-label="PERSONAS JURÍDICAS"><strong><?= $total_juridicas ?></strong></td>
                            <td data-label="COLECTIVOS"><strong><?= $total_colectivos ?></strong></td>
                            <td data-label="TOTAL"><strong><?= $total_registros ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Datos Socioeconómicos por tipo de solicitante -->
            <div class="table-responsive" style="margin-top: 20px;">
                <h3 class="subsection-title" style="font-family: montserrat; font-weight: 900; color: green;">Datos Socioeconómicos - Personas Naturales</h3>
                <table>
                    <thead>
                        <tr>
                            <th>INDICADOR</th>
                            <th>MASCULINO</th>
                            <th>FEMENINO</th>
                            <th>SOLTERO</th>
                            <th>CASADO</th>
                            <th>VIUDO</th>
                            <th>DIVORCIADO</th>
                            <th>CONCUBINATO</th>
                            <th>SABE LEER</th>
                            <th>NO SABE LEER</th>
                            <th>AYUDA ECONÓMICA</th>
                            <th>TRABAJA</th>
                            <th>PERTENECE A COMUNA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td data-label="INDICADOR"><strong>CANTIDAD</strong></td>
                            <td data-label="MASCULINO"><strong><?= $estadisticas_naturales['sexo']['M'] ?></strong></td>
                            <td data-label="FEMENINO"><strong><?= $estadisticas_naturales['sexo']['F'] ?></strong></td>
                            <td data-label="SOLTERO"><strong><?= $estadisticas_naturales['estado_civil']['Soltero'] ?></strong></td>
                            <td data-label="CASADO"><strong><?= $estadisticas_naturales['estado_civil']['Casado'] ?></strong></td>
                            <td data-label="VIUDO"><strong><?= $estadisticas_naturales['estado_civil']['Viudo'] ?></strong></td>
                            <td data-label="DIVORCIADO"><strong><?= $estadisticas_naturales['estado_civil']['Divorciado'] ?></strong></td>
                            <td data-label="CONCUBINATO"><strong><?= $estadisticas_naturales['estado_civil']['Concubinato'] ?></strong></td>
                            <td data-label="SABE LEER"><strong><?= $estadisticas_naturales['sabe_leer']['Si'] ?></strong></td>
                            <td data-label="NO SABE LEER"><strong><?= $estadisticas_naturales['sabe_leer']['No'] ?></strong></td>
                            <td data-label="AYUDA ECONÓMICA"><strong><?= $estadisticas_naturales['posee_ayuda_economica']['Si'] ?></strong></td>
                            <td data-label="TRABAJA"><strong><?= $estadisticas_naturales['trabaja_actualmente']['Si'] ?></strong></td>
                            <td data-label="PERTENECE A COMUNA"><strong><?= $estadisticas_naturales['pertenece_comuna']['Si'] ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Datos Socioeconómicos - Personas Jurídicas -->
            <?php if (!empty($resultados_juridicas)): ?>
                <div class="table-responsive" style="margin-top: 20px;">
                    <h3 class="subsection-title" style="font-family: montserrat; font-weight: 900; color: green;">Datos Socioeconómicos - Personas Jurídicas (Representantes Legales)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>INDICADOR</th>
                                <th>MASCULINO</th>
                                <th>FEMENINO</th>
                                <th>SOLTERO</th>
                                <th>CASADO</th>
                                <th>VIUDO</th>
                                <th>DIVORCIADO</th>
                                <th>CONCUBINATO</th>
                                <th>SIN NIVEL</th>
                                <th>PRIMARIA</th>
                                <th>SECUNDARIA</th>
                                <th>TECNICO</th>
                                <th>UNIVERSITARIO</th>
                                <th>POSTGRADO</th>
                                <th>OTRO</th>
                                <th>SABE LEER</th>
                                <th>NO SABE LEER</th>
                                <th>AYUDA ECONÓMICA</th>
                                <th>TRABAJA</th>
                                <th>PERTENECE A COMUNA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td data-label="INDICADOR"><strong>CANTIDAD</strong></td>
                                <td data-label="MASCULINO"><strong><?= $estadisticas_juridicas['sexo_rep']['M'] ?></strong></td>
                                <td data-label="FEMENINO"><strong><?= $estadisticas_juridicas['sexo_rep']['F'] ?></strong></td>
                                <td data-label="SOLTERO"><strong><?= $estadisticas_juridicas['estado_civil']['Soltero'] ?></strong></td>
                                <td data-label="CASADO"><strong><?= $estadisticas_juridicas['estado_civil']['Casado'] ?></strong></td>
                                <td data-label="VIUDO"><strong><?= $estadisticas_juridicas['estado_civil']['Viudo'] ?></strong></td>
                                <td data-label="DIVORCIADO"><strong><?= $estadisticas_juridicas['estado_civil']['Divorciado'] ?></strong></td>
                                <td data-label="CONCUBINATO"><strong><?= $estadisticas_juridicas['estado_civil']['Concubinato'] ?></strong></td>
                                <td data-label="SIN NIVEL"><strong><?= $estadisticas_juridicas['grado_instruccion']['Sin_nivel'] ?></strong></td>
                                <td data-label="PRIMARIA"><strong><?= $estadisticas_juridicas['grado_instruccion']['Primaria'] ?></strong></td>
                                <td data-label="SECUNDARIA"><strong><?= $estadisticas_juridicas['grado_instruccion']['Secundaria'] ?></strong></td>
                                <td data-label="TECNICO"><strong><?= $estadisticas_juridicas['grado_instruccion']['Tecnico'] ?></strong></td>
                                <td data-label="UNIVERSITARIO"><strong><?= $estadisticas_juridicas['grado_instruccion']['Universitario'] ?></strong></td>
                                <td data-label="POSTGRADO"><strong><?= $estadisticas_juridicas['grado_instruccion']['Postgrado'] ?></strong></td>
                                <td data-label="OTRO"><strong><?= $estadisticas_juridicas['grado_instruccion']['Otro'] ?></strong></td>
                                <td data-label="SABE LEER"><strong><?= $estadisticas_juridicas['sabe_leer']['Si'] ?></strong></td>
                                <td data-label="NO SABE LEER"><strong><?= $estadisticas_juridicas['sabe_leer']['No'] ?></strong></td>
                                <td data-label="AYUDA ECONÓMICA"><strong><?= $estadisticas_juridicas['posee_ayuda_economica']['Si'] ?></strong></td>
                                <td data-label="TRABAJA"><strong><?= $estadisticas_juridicas['trabaja_actualmente']['Si'] ?></strong></td>
                                <td data-label="PERTENECE A COMUNA"><strong><?= $estadisticas_juridicas['pertenece_comuna']['Si'] ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Datos Socioeconómicos - Integrantes de Colectivos -->
            <?php if (!empty($resultados_integrantes_colectivos)): ?>
                <div class="table-responsive" style="margin-top: 20px;">
                    <h3 class="subsection-title" style="font-family: montserrat; font-weight: 900; color: green;">Datos Socioeconómicos - Integrantes de Colectivos</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>INDICADOR</th>
                                <th>MASCULINO</th>
                                <th>FEMENINO</th>
                                <th>SOLTERO</th>
                                <th>CASADO</th>
                                <th>VIUDO</th>
                                <th>DIVORCIADO</th>
                                <th>CONCUBINATO</th>
                                <th>SIN NIVEL</th>
                                <th>PRIMARIA</th>
                                <th>SECUNDARIA</th>
                                <th>TECNICO</th>
                                <th>UNIVERSITARIO</th>
                                <th>POSTGRADO</th>
                                <th>OTRO</th>
                                <th>SABE LEER</th>
                                <th>NO SABE LEER</th>
                                <th>AYUDA ECONÓMICA</th>
                                <th>TRABAJA</th>
                                <th>PERTENECE A COMUNA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td data-label="INDICADOR"><strong>CANTIDAD</strong></td>
                                <td data-label="MASCULINO"><strong><?= $estadisticas_colectivos['sexo']['M'] ?></strong></td>
                                <td data-label="FEMENINO"><strong><?= $estadisticas_colectivos['sexo']['F'] ?></strong></td>
                                <td data-label="SOLTERO"><strong><?= $estadisticas_colectivos['estado_civil']['Soltero'] ?></strong></td>
                                <td data-label="CASADO"><strong><?= $estadisticas_colectivos['estado_civil']['Casado'] ?></strong></td>
                                <td data-label="VIUDO"><strong><?= $estadisticas_colectivos['estado_civil']['Viudo'] ?></strong></td>
                                <td data-label="DIVORCIADO"><strong><?= $estadisticas_colectivos['estado_civil']['Divorciado'] ?></strong></td>
                                <td data-label="CONCUBINATO"><strong><?= $estadisticas_colectivos['estado_civil']['Concubinato'] ?></strong></td>
                                <td data-label="SIN NIVEL"><strong><?= $estadisticas_colectivos['grado_instruccion']['Sin_nivel'] ?></strong></td>
                                <td data-label="PRIMARIA"><strong><?= $estadisticas_colectivos['grado_instruccion']['Primaria'] ?></strong></td>
                                <td data-label="SECUNDARIA"><strong><?= $estadisticas_colectivos['grado_instruccion']['Secundaria'] ?></strong></td>
                                <td data-label="TECNICO"><strong><?= $estadisticas_colectivos['grado_instruccion']['Tecnico'] ?></strong></td>
                                <td data-label="UNIVERSITARIO"><strong><?= $estadisticas_colectivos['grado_instruccion']['Universitario'] ?></strong></td>
                                <td data-label="POSTGRADO"><strong><?= $estadisticas_colectivos['grado_instruccion']['Postgrado'] ?></strong></td>
                                <td data-label="OTRO"><strong><?= $estadisticas_colectivos['grado_instruccion']['Otro'] ?></strong></td>
                                <td data-label="SABE LEER"><strong><?= $estadisticas_colectivos['sabe_leer']['Si'] ?></strong></td>
                                <td data-label="NO SABE LEER"><strong><?= $estadisticas_colectivos['sabe_leer']['No'] ?></strong></td>
                                <td data-label="AYUDA ECONÓMICA"><strong><?= $estadisticas_colectivos['posee_ayuda_economica']['Si'] ?></strong></td>
                                <td data-label="TRABAJA"><strong><?= $estadisticas_colectivos['trabaja_actualmente']['Si'] ?></strong></td>
                                <td data-label="PERTENECE A COMUNA"><strong><?= $estadisticas_colectivos['pertenece_comuna']['Si'] ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Personas Naturales -->
            <?php if (!empty($resultados_naturales) && ($tipo_solicitante == 'todos' || $tipo_solicitante == 'natural')): ?>
                <div class="table-responsive" style="margin-top: 20px;">
                    <h3 class="subsection-title" style="font-family: montserrat; font-weight: 900; color: green;">Personas Naturales (<?= count($resultados_naturales) ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>CÉDULA</th>
                                <th>NOMBRE COMPLETO</th>
                                <th>SEXO</th>
                                <th>FECHA NAC.</th>
                                <th>EDAD</th>
                                <th>TELÉFONO</th>
                                <th>DIRECCIÓN</th>
                                <th>ESTADO CIVIL</th>
                                <th>HIJOS</th>
                                <th>GRADO INSTRUCCIÓN</th>
                                <th>SABE LEER</th>
                                <th>AYUDA ECONÓMICA</th>
                                <th>TRABAJA</th>
                                <th>PERTENECE A COMUNA</th>
                                <th>ENFERMEDADES</th>
                                <th>REPRESENTANTE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados_naturales as $pn): ?>
                                <?php if ($pn['sexo'] !== 'Otro'): ?>
                                    <tr>
                                        <td data-label="CÉDULA"><strong><?= htmlspecialchars($pn['cedula']); ?></strong></td>
                                        <td data-label="NOMBRE COMPLETO"><?= htmlspecialchars($pn['nombre_completo']); ?></td>
                                        <td data-label="SEXO"><?= htmlspecialchars($pn['sexo'] ?? 'N/A'); ?></td>
                                        <td data-label="FECHA NAC."><?= $pn['fecha_nacimiento'] ? date('d/m/Y', strtotime($pn['fecha_nacimiento'])) : 'N/A'; ?></td>
                                        <td data-label="EDAD"><?= htmlspecialchars($pn['edad']); ?></td>
                                        <td data-label="TELÉFONO"><?= htmlspecialchars($pn['telefono'] ?? 'N/A'); ?></td>
                                        <td data-label="DIRECCIÓN"><?= htmlspecialchars(substr($pn['direccion_habitacion'], 0, 30)); ?><?php echo strlen($pn['direccion_habitacion']) > 30 ? '...' : ''; ?></td>
                                        <td data-label="ESTADO CIVIL"><?= htmlspecialchars($pn['estado_civil'] ?? 'N/A'); ?></td>
                                        <td data-label="HIJOS"><?= htmlspecialchars($pn['numero_hijos'] ?? '0'); ?></td>
                                        <td data-label="GRADO INSTRUCCIÓN"><?= htmlspecialchars($pn['grado_instruccion'] ?? 'N/A'); ?></td>
                                        <td data-label="SABE LEER"><?= htmlspecialchars($pn['sabe_leer'] ?? 'N/A'); ?></td>
                                        <td data-label="AYUDA ECONÓMICA"><?= htmlspecialchars($pn['posee_ayuda_economica'] ?? 'N/A'); ?></td>
                                        <td data-label="TRABAJA"><?= htmlspecialchars($pn['trabaja_actualmente'] ?? 'N/A'); ?></td>
                                        <td data-label="PERTENECE A COMUNA"><?= htmlspecialchars($pn['pertenece_comuna'] ?? 'N/A'); ?></td>
                                        <td data-label="ENFERMEDADES"><?= htmlspecialchars($pn['enfermedades'] ?? 'N/A'); ?></td>
                                        <td data-label="REPRESENTANTE">
                                            <?php if ($pn['tiene_representante'] === 'Sí'): ?>
                                                <span class="representante">
                                                    <?= htmlspecialchars(($pn['rep_primer_nombre'] ?? '') . ' ' . ($pn['rep_segundo_nombre'] ?? '') . ' ' . ($pn['rep_primer_apellido'] ?? '') . ' ' . ($pn['rep_segundo_apellido'] ?? '')); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="sin-representante">No aplica</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Personas Jurídicas -->
            <?php if (!empty($resultados_juridicas) && ($tipo_solicitante == 'todos' || $tipo_solicitante == 'juridica')): ?>
                <div class="table-responsive" style="margin-top: 30px;">
                    <h3 class="subsection-title" style="font-family: montserrat; font-weight: 900; color: green;">Personas Jurídicas (<?= count($resultados_juridicas) ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>RIF</th>
                                <th>RAZÓN SOCIAL</th>
                                <th>TELÉFONO</th>
                                <th>DIRECCIÓN</th>
                                <th>REPRESENTANTE LEGAL</th>
                                <th>SEXO REPRESENTANTE</th>
                                <th>EDAD REPRESENTANTE</th>
                                <th>PROFESIÓN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados_juridicas as $pj): ?>
                                <tr>
                                    <td data-label="RIF"><strong><?= htmlspecialchars($pj['rif']); ?></strong></td>
                                    <td data-label="RAZÓN SOCIAL"><?= htmlspecialchars($pj['razon_social']); ?></td>
                                    <td data-label="TELÉFONO"><?= htmlspecialchars($pj['telefono'] ?? 'N/A'); ?></td>
                                    <td data-label="DIRECCIÓN"><?= htmlspecialchars(substr($pj['direccion_habitacion'], 0, 30)); ?><?php echo strlen($pj['direccion_habitacion']) > 30 ? '...' : ''; ?></td>
                                    <td data-label="REPRESENTANTE LEGAL">
                                        <?php if ($pj['rep_primer_nombre']): ?>
                                            <span class="representante">
                                                <?= htmlspecialchars($pj['rep_primer_nombre'] . ' ' . ($pj['rep_segundo_nombre'] ?? '') . ' ' . $pj['rep_primer_apellido'] . ' ' . ($pj['rep_segundo_apellido'] ?? '')); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="sin-representante">No asignado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="SEXO REPRESENTANTE"><?= htmlspecialchars($pj['rep_sexo'] ?? 'N/A'); ?></td>
                                    <td data-label="EDAD REPRESENTANTE"><?= htmlspecialchars($pj['edad_representante']); ?></td>
                                    <td data-label="PROFESIÓN"><?= htmlspecialchars($pj['rep_profesion'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Colectivos -->
            <?php if (!empty($resultados_colectivos) && ($tipo_solicitante == 'todos' || $tipo_solicitante == 'colectivo')): ?>
                <div class="table-responsive" style="margin-top: 30px;">
                    <h3 class="subsection-title" style="font-family: montserrat; font-weight: 900; color: green;">Colectivos (<?= count($resultados_colectivos) ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>RIF/CI REFERENTE</th>
                                <th>NOMBRE DEL COLECTIVO</th>
                                <th>INTEGRANTES</th>
                                <th>TELÉFONO</th>
                                <th>DIRECCIÓN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados_colectivos as $c): ?>
                                <tr>
                                    <td data-label="RIF/CI REFERENTE"><strong><?= htmlspecialchars($c['rif_o_ci_referente']); ?></strong></td>
                                    <td data-label="NOMBRE DEL COLECTIVO"><?= htmlspecialchars($c['nombre_colectivo']); ?></td>
                                    <td data-label="INTEGRANTES"><?= htmlspecialchars($c['numero_integrantes']); ?></td>
                                    <td data-label="TELÉFONO"><?= htmlspecialchars($c['telefono'] ?? 'N/A'); ?></td>
                                    <td data-label="DIRECCIÓN"><?= htmlspecialchars(substr($c['direccion_habitacion'], 0, 30)); ?><?php echo strlen($c['direccion_habitacion']) > 30 ? '...' : ''; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Integrantes de Colectivos -->
            <?php if (!empty($resultados_integrantes_colectivos) && ($tipo_solicitante == 'todos' || $tipo_solicitante == 'colectivo')): ?>
                <div class="table-responsive" style="margin-top: 30px;">
                    <h3 class="subsection-title" style="font-family: montserrat; font-weight: 900; color: green;">Integrantes de Colectivos (<?= count($resultados_integrantes_colectivos) ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>RIF COLECTIVO</th>
                                <th>CÉDULA</th>
                                <th>NOMBRE COMPLETO</th>
                                <th>SEXO</th>
                                <th>FECHA NAC.</th>
                                <th>EDAD</th>
                                <th>TELÉFONO</th>
                                <th>DIRECCIÓN</th>
                                <th>ESTADO CIVIL</th>
                                <th>HIJOS</th>
                                <th>GRADO INSTRUCCIÓN</th>
                                <th>SABE LEER</th>
                                <th>AYUDA ECONÓMICA</th>
                                <th>TRABAJA</th>
                                <th>PERTENECE A COMUNA</th>
                                <th>ENFERMEDADES</th>
                                <th>ES REFERENTE</th>
                                <th>CARGO</th>
                                <th>FECHA INGRESO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados_integrantes_colectivos as $ic): ?>
                                <?php if ($ic['sexo'] !== 'Otro'): ?>
                                    <tr>
                                        <td data-label="RIF COLECTIVO"><strong><?= htmlspecialchars($ic['rif_colectivo']); ?></strong></td>
                                        <td data-label="CÉDULA"><strong><?= htmlspecialchars($ic['cedula']); ?></strong></td>
                                        <td data-label="NOMBRE COMPLETO"><?= htmlspecialchars($ic['nombre_completo']); ?></td>
                                        <td data-label="SEXO"><?= htmlspecialchars($ic['sexo'] ?? 'N/A'); ?></td>
                                        <td data-label="FECHA NAC."><?= $ic['fecha_nacimiento'] ? date('d/m/Y', strtotime($ic['fecha_nacimiento'])) : 'N/A'; ?></td>
                                        <td data-label="EDAD"><?= htmlspecialchars($ic['edad']); ?></td>
                                        <td data-label="TELÉFONO"><?= htmlspecialchars($ic['telefono'] ?? 'N/A'); ?></td>
                                        <td data-label="DIRECCIÓN"><?= htmlspecialchars(substr($ic['direccion_habitacion'], 0, 30)); ?><?php echo strlen($ic['direccion_habitacion']) > 30 ? '...' : ''; ?></td>
                                        <td data-label="ESTADO CIVIL"><?= htmlspecialchars($ic['estado_civil'] ?? 'N/A'); ?></td>
                                        <td data-label="HIJOS"><?= htmlspecialchars($ic['numero_hijos'] ?? '0'); ?></td>
                                        <td data-label="GRADO INSTRUCCIÓN"><?= htmlspecialchars($ic['grado_instruccion'] ?? 'N/A'); ?></td>
                                        <td data-label="SABE LEER"><?= htmlspecialchars($ic['sabe_leer'] ?? 'N/A'); ?></td>
                                        <td data-label="AYUDA ECONÓMICA"><?= htmlspecialchars($ic['posee_ayuda_economica'] ?? 'N/A'); ?></td>
                                        <td data-label="TRABAJA"><?= htmlspecialchars($ic['trabaja_actualmente'] ?? 'N/A'); ?></td>
                                        <td data-label="PERTENECE A COMUNA"><?= htmlspecialchars($ic['pertenece_comuna'] ?? 'N/A'); ?></td>
                                        <td data-label="ENFERMEDADES"><?= htmlspecialchars($ic['enfermedades'] ?? 'N/A'); ?></td>
                                        <td data-label="ES REFERENTE"><?= $ic['es_referente'] ? 'Sí' : 'No'; ?></td>
                                        <td data-label="CARGO"><?= htmlspecialchars($ic['cargo_en_colectivo'] ?? 'N/A'); ?></td>
                                        <td data-label="FECHA INGRESO"><?= date('d/m/Y', strtotime($ic['fecha_ingreso'])); ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Botón para generar PDF -->
            <form action="pdf_solicitantes.php" method="POST" target="_blank" style="margin-top: 20px;">
                <input type="hidden" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio); ?>">
                <input type="hidden" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin); ?>">
                <input type="hidden" name="tipo_solicitante" value="<?= htmlspecialchars($tipo_solicitante); ?>">
                <input type="hidden" name="sexo_filtro" value="<?= htmlspecialchars($sexo_filtro); ?>">
                <input type="hidden" name="edad_min" value="<?= htmlspecialchars($edad_min); ?>">
                <input type="hidden" name="edad_max" value="<?= htmlspecialchars($edad_max); ?>">
                <input type="hidden" name="resultados_naturales_json" value='<?= json_encode($resultados_naturales, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                <input type="hidden" name="resultados_juridicas_json" value='<?= json_encode($resultados_juridicas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                <input type="hidden" name="resultados_colectivos_json" value='<?= json_encode($resultados_colectivos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                <input type="hidden" name="resultados_integrantes_colectivos_json" value='<?= json_encode($resultados_integrantes_colectivos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                <button type="submit" class="btn btn-primary"><i class="zmdi zmdi-file-plus"></i> Generar PDF</button>
            </form>
        </div>
    <?php elseif ($fecha_inicio && $fecha_fin && empty($mensaje)): ?>
        <div class="section-container">
            <div class="no-results">
                <h3>No se encontraron resultados</h3>
                <p>No hay solicitantes registrados entre las fechas seleccionadas.</p>
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
    
    // Validar que la edad máxima no sea menor que la mínima
    document.getElementById('edad_min').addEventListener('change', function() {
        var min = parseInt(this.value) || 0;
        var max = parseInt(document.getElementById('edad_max').value) || 120;
        
        if (min > max) {
            document.getElementById('edad_max').value = min;
        }
    });
    
    document.getElementById('edad_max').addEventListener('change', function() {
        var max = parseInt(this.value) || 120;
        var min = parseInt(document.getElementById('edad_min').value) || 0;
        
        if (max < min) {
            document.getElementById('edad_min').value = max;
        }
    });
</script>
<?php include("footer.php"); ?>
