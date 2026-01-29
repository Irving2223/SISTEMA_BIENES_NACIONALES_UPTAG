<?php
include('header.php');
include('conexion.php');

function formatear_edad($edad) {
    if (!$edad || $edad == 0) return '';
    $num = (int)$edad;
    return $num . ($num == 1 ? ' año' : ' años');
}
$modo_busqueda = $_GET['modo'] ?? 'solicitud';
$busqueda_realizada = false;
$resultados_solicitudes = array();
$resultados_solicitantes = array();
$mensaje = '';
$tipo_mensaje = '';
if (isset($_GET['buscar']) && !empty($_GET['termino_busqueda'])) {
    $busqueda_realizada = true;
    $termino = $conn->real_escape_string($_GET['termino_busqueda']);
    try {
        if ($modo_busqueda == 'solicitud') {
            // Búsqueda por solicitudes - solo campos reales de la BD
            
                        $sql = "
                    SELECT 
    s.id_solicitud,
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
        WHEN s.tipo_solicitante = 'N' THEN 'Persona Natural'
        WHEN s.tipo_solicitante = 'J' THEN 'Persona Jurídica'
        WHEN s.tipo_solicitante = 'C' THEN 'Colectivo'
        ELSE 'Desconocido'
    END as tipo_solicitante_descripcion,
    tp.nombre_procedimiento as tipo_solicitud,
    CASE 
        WHEN s.tipo_solicitante = 'N' THEN pn.sexo
        WHEN s.tipo_solicitante = 'J' THEN 'N/A'
        WHEN s.tipo_solicitante = 'C' THEN 'N/A'
        ELSE 'N/A'
    END as sexo,
    CASE 
        WHEN s.tipo_solicitante = 'N' THEN TIMESTAMPDIFF(YEAR, pn.fecha_nacimiento, CURDATE())
        ELSE NULL
    END as edad,
    CASE 
        WHEN s.tipo_solicitante = 'N' THEN pn.telefono
        WHEN s.tipo_solicitante = 'J' THEN pj.telefono
        WHEN s.tipo_solicitante = 'C' THEN c.telefono
        ELSE 'N/A'
    END as telefono,
    p.superficie_ha as superficie,
    m.nombre_municipio as estado,
    p.nombre_predio as predio,
    m.nombre_municipio as municipio,
    pa.nombre_parroquia as parroquia,
    se.nombre_sector as sector,
    p.lindero_norte,
    p.lindero_sur,
    p.lindero_este,
    p.lindero_oeste,
    s.rubros_a_producir,
    s.estatus,
    s.observaciones,
    CASE 
        WHEN s.tipo_solicitante = 'N' AND pn.id_representante IS NOT NULL THEN 'Sí'
        WHEN s.tipo_solicitante = 'J' AND pj.id_representante IS NOT NULL THEN 'Sí'
        WHEN s.tipo_solicitante = 'C' THEN 'No'
        ELSE 'No'
    END as tiene_representante,
    CASE
        WHEN s.tipo_solicitante = 'N' AND pn.id_representante IS NOT NULL THEN CONCAT(r_pn.primer_nombre, ' ', IFNULL(r_pn.segundo_nombre, ''), ' ', r_pn.primer_apellido, ' ', IFNULL(r_pn.segundo_apellido, ''), ' (', r_pn.tipo, ')')
        WHEN s.tipo_solicitante = 'J' AND pj.id_representante IS NOT NULL THEN CONCAT(r_pj.primer_nombre, ' ', IFNULL(r_pj.segundo_nombre, ''), ' ', r_pj.primer_apellido, ' ', IFNULL(r_pj.segundo_apellido, ''), ' (', r_pj.tipo, ')')
        WHEN s.tipo_solicitante = 'C' THEN CONCAT(ci.primer_nombre, ' ', IFNULL(ci.segundo_nombre, ''), ' ', ci.primer_apellido, ' ', IFNULL(ci.segundo_apellido, ''), ' (Referente)')
        ELSE 'N/A'
    END as nombre_representante,
    CASE
        WHEN s.tipo_solicitante = 'N' AND pn.id_representante IS NOT NULL THEN r_pn.id_representante
        WHEN s.tipo_solicitante = 'J' AND pj.id_representante IS NOT NULL THEN r_pj.id_representante
        ELSE 'N/A'
    END as cedula_representante,
    -- PERSONAS NATURALES (campos reales)
    pn.cedula as pn_cedula,
    pn.primer_nombre as pn_primer_nombre,
    pn.segundo_nombre as pn_segundo_nombre,
    pn.primer_apellido as pn_primer_apellido,
    pn.segundo_apellido as pn_segundo_apellido,
    pn.sexo as pn_sexo,
    pn.fecha_nacimiento as pn_fecha_nacimiento,
    pn.telefono as pn_telefono,
    pn.direccion_habitacion as pn_direccion,
    pn.estado_civil as pn_estado_civil,
    pn.numero_hijos as pn_numero_hijos,
    pn.grado_instruccion as pn_grado_instruccion,
    pn.sabe_leer as pn_sabe_leer,
    pn.posee_ayuda_economica as pn_posee_ayuda_economica,
    pn.trabaja_actualmente as pn_trabaja_actualmente,
    pn.pertenece_comuna as pn_pertenece_comuna,
    pn.enfermedades as pn_enfermedades,
    -- PERSONAS JURÍDICAS (campos reales)
    pj.razon_social as pj_razon_social,
    pj.rif as pj_rif,
    pj.telefono as pj_telefono,
    pj.direccion_habitacion as pj_direccion,
    pj.estado_civil as pj_estado_civil,
    pj.numero_hijos as pj_numero_hijos,
    pj.grado_instruccion as pj_grado_instruccion,
    pj.sabe_leer as pj_sabe_leer,
    pj.posee_ayuda_economica as pj_posee_ayuda_economica,
    pj.trabaja_actualmente as pj_trabaja_actualmente,
    pj.pertenece_comuna as pj_pertenece_comuna,
    pj.enfermedades as pj_enfermedades,
    -- COLECTIVOS (campos reales)
    c.nombre_colectivo as c_nombre,
    c.rif_o_ci_referente as c_rif,
    c.telefono as c_telefono,
    c.direccion_habitacion as c_direccion,
    c.numero_integrantes as c_numero_integrantes,
    c.activo as c_activo,
    -- INTEGRANTE REFERENTE (campos reales)
    ci.primer_nombre as ci_primer_nombre,
    ci.segundo_nombre as ci_segundo_nombre,
    ci.primer_apellido as ci_primer_apellido,
    ci.segundo_apellido as ci_segundo_apellido,
    ci.sexo as ci_sexo,
    ci.fecha_nacimiento as ci_fecha_nacimiento,
    TIMESTAMPDIFF(YEAR, ci.fecha_nacimiento, CURDATE()) as edad_referente,
    ci.telefono as ci_telefono,
    ci.direccion_habitacion as ci_direccion,
    ci.estado_civil as ci_estado_civil,
    ci.numero_hijos as ci_numero_hijos,
    ci.grado_instruccion as ci_grado_instruccion,
    ci.sabe_leer as ci_sabe_leer,
    ci.posee_ayuda_economica as ci_posee_ayuda_economica,
    ci.trabaja_actualmente as ci_trabaja_actualmente,
    ci.pertenece_comuna as ci_pertenece_comuna,
    ci.enfermedades as ci_enfermedades,
    ci.cargo_en_colectivo as ci_cargo
FROM solicitudes s
LEFT JOIN tipo_procedimiento tp ON s.id_procedimiento = tp.id_procedimiento
LEFT JOIN predios p ON s.id_predio = p.id_predio
LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
LEFT JOIN parroquias pa ON p.id_parroquia = pa.id_parroquia
LEFT JOIN sectores se ON p.id_sector = se.id_sector
LEFT JOIN personas_naturales pn ON s.cedula_solicitante_n = pn.cedula
LEFT JOIN personas_juridicas pj ON s.rif_solicitante_j = pj.rif
LEFT JOIN colectivos c ON s.rif_ci_solicitante_c = c.rif_o_ci_referente
LEFT JOIN representantes r_pn ON pn.id_representante = r_pn.id_representante
LEFT JOIN representantes r_pj ON pj.id_representante = r_pj.id_representante
LEFT JOIN colectivo_integrantes ci ON c.rif_o_ci_referente = ci.rif_o_ci_colectivo AND ci.es_referente = 1
WHERE
    s.numero_solicitud LIKE '%$termino%' OR
    pn.cedula LIKE '%$termino%' OR
    pn.primer_nombre LIKE '%$termino%' OR
    pn.primer_apellido LIKE '%$termino%' OR
    pj.rif LIKE '%$termino%' OR
    pj.razon_social LIKE '%$termino%' OR
    c.rif_o_ci_referente LIKE '%$termino%' OR
    c.nombre_colectivo LIKE '%$termino%' OR
    p.nombre_predio LIKE '%$termino%' OR
    m.nombre_municipio LIKE '%$termino%' OR
    pa.nombre_parroquia LIKE '%$termino%' OR
    se.nombre_sector LIKE '%$termino%' OR
    r_pn.primer_nombre LIKE '%$termino%' OR
    r_pn.primer_apellido LIKE '%$termino%' OR
    r_pj.primer_nombre LIKE '%$termino%' OR
    r_pj.primer_apellido LIKE '%$termino%' OR
    ci.primer_nombre LIKE '%$termino%' OR
    ci.primer_apellido LIKE '%$termino%'
ORDER BY s.fecha_solicitud DESC
LIMIT 100
            ";
            $result = $conn->query($sql);
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $resultados_solicitudes[] = $row;
                }
            } else {
                $mensaje = "No se encontraron solicitudes con el término de búsqueda: '$termino'";
                $tipo_mensaje = "error";
            }
        } else {
            $resultados_solicitantes = array();
            // Personas naturales + representante (solo campos reales)
            $sql_pn = "
                SELECT 
                    'N' as tipo_solicitante,
                    pn.cedula as identificacion,
                    CONCAT(pn.primer_nombre, ' ', IFNULL(pn.segundo_nombre, ''), ' ', pn.primer_apellido, ' ', IFNULL(pn.segundo_apellido, '')) as nombre,
                    pn.primer_nombre as pn_primer_nombre,
                    pn.segundo_nombre as pn_segundo_nombre,
                    pn.primer_apellido as pn_primer_apellido,
                    pn.segundo_apellido as pn_segundo_apellido,
                    pn.sexo as pn_sexo,
                    pn.fecha_nacimiento as pn_fecha_nacimiento,
                    TIMESTAMPDIFF(YEAR, pn.fecha_nacimiento, CURDATE()) as edad,
                    pn.telefono as pn_telefono,
                    pn.direccion_habitacion as pn_direccion,
                    pn.estado_civil as pn_estado_civil,
                    pn.numero_hijos as pn_numero_hijos,
                    pn.grado_instruccion as pn_grado_instruccion,
                    pn.sabe_leer as pn_sabe_leer,
                    pn.posee_ayuda_economica as pn_posee_ayuda_economica,
                    pn.trabaja_actualmente as pn_trabaja_actualmente,
                    pn.pertenece_comuna as pn_pertenece_comuna,
                    pn.enfermedades as pn_enfermedades,
                    CASE WHEN pn.id_representante IS NOT NULL THEN 'Sí' ELSE 'No' END as tiene_representante,
                    CASE WHEN pn.id_representante IS NOT NULL THEN CONCAT(r.primer_nombre, ' ', IFNULL(r.segundo_nombre, ''), ' ', r.primer_apellido, ' ', IFNULL(r.segundo_apellido, '')) ELSE 'N/A' END as nombre_representante,
                    r.primer_nombre as rp_primer_nombre,
                    r.segundo_nombre as rp_segundo_nombre,
                    r.primer_apellido as rp_primer_apellido,
                    r.segundo_apellido as rp_segundo_apellido,
                    r.id_representante as rp_cedula,
                    r.sexo as rp_sexo,
                    r.telefono as rp_telefono,
                    r.direccion as rp_direccion,
                    r.email as rp_email,
                    r.profesion as rp_profesion,
                    r.tipo as rp_tipo,
                    r.activo as rp_activo
                FROM personas_naturales pn
                LEFT JOIN representantes r ON pn.id_representante = r.id_representante
                WHERE
                    pn.cedula LIKE '%$termino%' OR
                    pn.primer_nombre LIKE '%$termino%' OR
                    pn.primer_apellido LIKE '%$termino%' OR
                    pn.segundo_nombre LIKE '%$termino%' OR
                    pn.segundo_apellido LIKE '%$termino%' OR
                    r.primer_nombre LIKE '%$termino%' OR
                    r.primer_apellido LIKE '%$termino%'
                ORDER BY pn.primer_apellido, pn.primer_nombre
                LIMIT 30
            ";
            $result_pn = $conn->query($sql_pn);
            if ($result_pn && $result_pn->num_rows > 0) {
                while ($row = $result_pn->fetch_assoc()) {
                    $resultados_solicitantes[] = $row;
                }
            }
            // Personas jurídicas + representante (campos reales)
            $sql_pj = "
                SELECT 
                    'J' as tipo_solicitante,
                    pj.rif as identificacion,
                    pj.razon_social as nombre,
                    pj.razon_social as pj_razon_social,
                    pj.rif as pj_rif,
                    pj.telefono as pj_telefono,
                    pj.direccion_habitacion as pj_direccion,
                    pj.estado_civil as pj_estado_civil,
                    pj.numero_hijos as pj_numero_hijos,
                    pj.grado_instruccion as pj_grado_instruccion,
                    pj.sabe_leer as pj_sabe_leer,
                    pj.posee_ayuda_economica as pj_posee_ayuda_economica,
                    pj.trabaja_actualmente as pj_trabaja_actualmente,
                    pj.pertenece_comuna as pj_pertenece_comuna,
                    pj.enfermedades as pj_enfermedades,
                    CASE WHEN pj.id_representante IS NOT NULL THEN 'Sí' ELSE 'No' END as tiene_representante,
                    CASE WHEN pj.id_representante IS NOT NULL THEN CONCAT(r.primer_nombre, ' ', IFNULL(r.segundo_nombre, ''), ' ', r.primer_apellido, ' ', IFNULL(r.segundo_apellido, '')) ELSE 'N/A' END as nombre_representante,
                    r.primer_nombre as rp_primer_nombre,
                    r.segundo_nombre as rp_segundo_nombre,
                    r.primer_apellido as rp_primer_apellido,
                    r.segundo_apellido as rp_segundo_apellido,
                    r.id_representante as rp_cedula,
                    r.sexo as rp_sexo,
                    r.telefono as rp_telefono,
                    r.direccion as rp_direccion,
                    r.email as rp_email,
                    r.profesion as rp_profesion,
                    r.tipo as rp_tipo,
                    r.activo as rp_activo
                FROM personas_juridicas pj
                LEFT JOIN representantes r ON pj.id_representante = r.id_representante
                WHERE
                    pj.rif LIKE '%$termino%' OR
                    pj.razon_social LIKE '%$termino%' OR
                    r.primer_nombre LIKE '%$termino%' OR
                    r.primer_apellido LIKE '%$termino%'
                ORDER BY pj.razon_social
                LIMIT 30
            ";
            $result_pj = $conn->query($sql_pj);
            if ($result_pj && $result_pj->num_rows > 0) {
                while ($row = $result_pj->fetch_assoc()) {
                    $resultados_solicitantes[] = $row;
                }
            }
            // Colectivos (campos reales)
            $sql_c = "
                SELECT
                    'C' as tipo_solicitante,
                    c.rif_o_ci_referente as identificacion,
                    c.nombre_colectivo as nombre,
                    c.nombre_colectivo as c_nombre,
                    c.rif_o_ci_referente as c_rif,
                    c.telefono as c_telefono,
                    c.direccion_habitacion as c_direccion,
                    c.numero_integrantes as c_numero_integrantes,
                    c.activo as c_activo,
                    ci.fecha_nacimiento as ci_fecha_nacimiento,
                    TIMESTAMPDIFF(YEAR, ci.fecha_nacimiento, CURDATE()) as edad_referente
                FROM colectivos c
                LEFT JOIN colectivo_integrantes ci ON c.rif_o_ci_referente = ci.rif_o_ci_colectivo AND ci.es_referente = 1
                WHERE
                    c.rif_o_ci_referente LIKE '%$termino%' OR
                    c.nombre_colectivo LIKE '%$termino%' OR
                    ci.primer_nombre LIKE '%$termino%' OR
                    ci.primer_apellido LIKE '%$termino%'
                ORDER BY c.nombre_colectivo
                LIMIT 30
            ";
            $result_c = $conn->query($sql_c);
            if ($result_c && $result_c->num_rows > 0) {
                while ($row = $result_c->fetch_assoc()) {
                    $resultados_solicitantes[] = $row;
                }
            }
            if (empty($resultados_solicitantes)) {
                $mensaje = "No se encontraron solicitantes con el término de búsqueda: '$termino'";
                $tipo_mensaje = "error";
            }
        }
    } catch (Exception $e) {
        $mensaje = "Error al realizar la búsqueda: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}
// Obtener listas para los selects (no se usan en esta vista, pero por si acaso)
$procedimientos = array();
$result_proc = $conn->query("SELECT id_procedimiento, nombre_procedimiento FROM tipo_procedimiento ORDER BY nombre_procedimiento");
if ($result_proc && $result_proc->num_rows > 0) {
    while ($row = $result_proc->fetch_assoc()) {
        $procedimientos[] = $row;
    }
}
$municipios = array();
$result_municipios = $conn->query("SELECT id_municipio, nombre_municipio FROM municipios ORDER BY nombre_municipio");
if ($result_municipios && $result_municipios->num_rows > 0) {
    while ($row = $result_municipios->fetch_assoc()) {
        $municipios[] = $row;
    }
}
?>


    <div class="container">
        <!-- Título principal -->

	<h1 style="font-family:montserrat; font-weight:900; color:green; padding:20px; text-align:left; font-size:50px;"><i class="zmdi zmdi-file-plus"></i> Busqueda <span style="font-weight:700; color:black;">del sistema</span></h1>


        <!-- Formulario de modo de búsqueda -->
        <div class="section-container">
            <h2 class="section-title" style="font-weight: 800;">Modo de Búsqueda</h2>
            <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
                <button type="button" class="btn <?= $modo_busqueda == 'solicitud' ? 'btn-primary' : 'btn-secondary'; ?>" onclick="window.location.href='?modo=solicitud'"> Buscar por Solicitud</button>
                <button type="button" class="btn <?= $modo_busqueda == 'solicitante' ? 'btn-primary' : 'btn-secondary'; ?>" onclick="window.location.href='?modo=solicitante'"> Buscar por Solicitante</button>
            </div>

            <h2 class="section-title" style="font-weight: 800;">Buscar</h2>
            <form method="GET" style="margin-bottom: 30px;">
                <input type="hidden" name="modo" value="<?= htmlspecialchars($modo_busqueda); ?>">
                <div class="field-row">
                    <div class="field-col">
                        <label for="termino_busqueda" class="field-label required" style="font-weight: 600;">Término de Búsqueda</label>
                        <input type="text" style="font-weight: 00;" id="termino_busqueda" name="termino_busqueda" placeholder="Buscar por número, nombre, cédula, RIF, predio, municipio..." value="<?= htmlspecialchars($_GET['termino_busqueda'] ?? ''); ?>" class="form-control" required>
                    </div>
                    <div class="field-col" style="align-self: end;">
                        <button type="submit" name="buscar" value="1" class="btn btn-primary">Buscar</button>
                    </div>
                </div>
            </form>
        </div>
        <?php if ($busqueda_realizada): ?>
            <?php if ($modo_busqueda == 'solicitud'): ?>
                
                <!-- Resultados de búsqueda por solicitudes -->
                <div class="section-container">
                    <h2 class="section-title">Resultados de Búsqueda por Solicitudes</h2>
                    <div class="results-count">
                        Se encontraron <?= count($resultados_solicitudes); ?> solicitud(es) para "<?= htmlspecialchars($_GET['termino_busqueda']); ?>"
                    </div>
                    <?php if (!empty($resultados_solicitudes)): ?>
                        <div class="table-responsive">
                            <table>
                        <thead>
                              <tr>
                                   <th>NRO</th>
                                    <th>FECHA</th>
                                  <th>BENEFICIARIO</th>
                                  <th>CÉDULA/RIF</th>
                                 <th>TIPO SOLICITANTE</th>
                                 <th>EDAD</th>
                                 <th>TIPO SOLICITUD</th>
        <th>ESTATUS</th>
        <th>PREDIO</th>
        <th>MUNICIPIO</th>
        <th>SECTOR</th>
        <th>PARROQUIA</th>
        <th>LINDERO NORTE</th>
        <th>LINDERO SUR</th>
        <th>LINDERO ESTE</th>
        <th>LINDERO OESTE</th>
        <th>SUPERFICIE</th>
        <th>OBSERVACIONES</th>
        <th>REPRESENTANTE</th>
                             </tr>
                        </thead>
                               <tbody>
    <?php foreach ($resultados_solicitudes as $solicitud): ?>
        <tr>
             <td><?= htmlspecialchars($solicitud['numero_solicitud']); ?></td>
             <td><?= date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?></td>
             <td><?= htmlspecialchars($solicitud['beneficiario']); ?></td>
             <td><?= htmlspecialchars($solicitud['identificacion']); ?></td>
             <td><?= htmlspecialchars($solicitud['tipo_solicitante_descripcion']); ?></td>
             <td><?php
                 if ($solicitud['tipo_solicitante'] == 'N') {
                     echo formatear_edad($solicitud['edad'] ?? null);
                 } elseif ($solicitud['tipo_solicitante'] == 'C') {
                     echo formatear_edad($solicitud['edad_referente'] ?? null);
                 } else {
                     echo '';
                 }
             ?></td>
             <td><?= htmlspecialchars($solicitud['tipo_solicitud']); ?></td>
             <td><span class="status <?= strtolower(str_replace(' ', '_', $solicitud['estatus'])); ?>"><?= htmlspecialchars(str_replace('_', ' ', $solicitud['estatus'])); ?></span></td>
             <td><?= htmlspecialchars($solicitud['predio']); ?></td>
             <td><?= htmlspecialchars($solicitud['municipio']); ?></td>
             <td><?= htmlspecialchars($solicitud['sector']); ?></td>
             <td><?= htmlspecialchars($solicitud['parroquia']); ?></td>
             <td><?= htmlspecialchars($solicitud['lindero_norte'] ?? 'N/A'); ?></td>
             <td><?= htmlspecialchars($solicitud['lindero_sur'] ?? 'N/A'); ?></td>
             <td><?= htmlspecialchars($solicitud['lindero_este'] ?? 'N/A'); ?></td>
             <td><?= htmlspecialchars($solicitud['lindero_oeste'] ?? 'N/A'); ?></td>
             <td><?= htmlspecialchars($solicitud['superficie'] ?? 'N/A'); ?> ha</td>
             <td><?= htmlspecialchars($solicitud['observaciones'] ?? 'N/A'); ?></td>
             <td><?= htmlspecialchars($solicitud['nombre_representante'] ?? 'N/A'); ?></td>
         </tr>
    <?php endforeach; ?>
</tbody>
                            </table>
                        </div>
                        <!-- Detalles adicionales por tipo de solicitante -->
                        <?php foreach ($resultados_solicitudes as $solicitud): ?>
                            <div class="section-container">
                                <h3 class="section-title">Detalles del Solicitante: <?= htmlspecialchars($solicitud['beneficiario']); ?></h3>
                                <?php if ($solicitud['tipo_solicitante'] == 'N'): ?>
                                    <div class="section-container">
                                        <h4 class="section-title">Persona Natural</h4>
                                        <div class="field-row">
                                            <div class="field-col"><label class="field-label">Nombre Completo</label><input type="text" value="<?= htmlspecialchars($solicitud['pn_primer_nombre'] . ' ' . ($solicitud['pn_segundo_nombre'] ?? '') . ' ' . $solicitud['pn_primer_apellido'] . ' ' . ($solicitud['pn_segundo_apellido'] ?? '')); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Cédula</label><input type="text" value="<?= htmlspecialchars($solicitud['pn_cedula']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Teléfono</label><input type="text" value="<?= htmlspecialchars($solicitud['pn_telefono']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Fecha Nac.</label><input type="text" value="<?= $solicitud['pn_fecha_nacimiento'] ? date('d/m/Y', strtotime($solicitud['pn_fecha_nacimiento'])) : 'N/A'; ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Sexo</label><input type="text" value="<?= htmlspecialchars($solicitud['pn_sexo'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Estado Civil</label><input type="text" value="<?= htmlspecialchars($solicitud['pn_estado_civil'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Hijos</label><input type="text" value="<?= htmlspecialchars($solicitud['pn_numero_hijos'] ?? '0'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Grado Instrucción</label><input type="text" value="<?= htmlspecialchars($solicitud['pn_grado_instruccion'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Sabe Leer</label><input type="text" value="<?= htmlspecialchars($solicitud['pn_sabe_leer'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Ayuda Económica</label><input type="text" value="<?= htmlspecialchars($solicitud['pn_posee_ayuda_economica'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Trabaja Actualmente</label><input type="text" value="<?= htmlspecialchars($solicitud['pn_trabaja_actualmente'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Pertenece a Comuna</label><input type="text" value="<?= htmlspecialchars($solicitud['pn_pertenece_comuna'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col" style="grid-column: 1 / -1;"><label class="field-label">Dirección</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitud['pn_direccion'] ?? 'N/A'); ?></textarea></div>
                                            <div class="field-col" style="grid-column: 1 / -1;"><label class="field-label">Enfermedades</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitud['pn_enfermedades'] ?? 'N/A'); ?></textarea></div>
                                        </div>
                                        <div class="representante-section">
                                            <h4 class="section-title">Datos del Apoderado</h4>
                                            <div class="field-row">
                                                <div class="field-col"><label class="field-label">Nombre Completo</label><input type="text" value="<?= htmlspecialchars(($solicitud['rp_primer_nombre'] ?? '') . ' ' . ($solicitud['rp_segundo_nombre'] ?? '') . ' ' . ($solicitud['rp_primer_apellido'] ?? '') . ' ' . ($solicitud['rp_segundo_apellido'] ?? '')); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Cédula</label><input type="text" value="<?= htmlspecialchars($solicitud['cedula_representante'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Teléfono</label><input type="text" value="<?= htmlspecialchars($solicitud['rp_telefono'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Email</label><input type="text" value="<?= htmlspecialchars($solicitud['rp_email'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Profesión</label><input type="text" value="<?= htmlspecialchars($solicitud['rp_profesion'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Sexo</label><input type="text" value="<?= htmlspecialchars($solicitud['rp_sexo'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Tipo</label><input type="text" value="<?= htmlspecialchars($solicitud['rp_tipo'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col" style="grid-column: 1 / -1;"><label class="field-label">Dirección</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitud['rp_direccion'] ?? ''); ?></textarea></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif ($solicitud['tipo_solicitante'] == 'J'): ?>
                                    <div class="section-container">
                                        <h4 class="section-title">Persona Jurídica</h4>
                                        <div class="field-row">
                                            <div class="field-col"><label class="field-label">Razón Social</label><input type="text" value="<?= htmlspecialchars($solicitud['pj_razon_social']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">RIF</label><input type="text" value="<?= htmlspecialchars($solicitud['pj_rif']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Teléfono</label><input type="text" value="<?= htmlspecialchars($solicitud['pj_telefono']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Dirección</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitud['pj_direccion'] ?? 'N/A'); ?></textarea></div>
                                            <div class="field-col"><label class="field-label">Estado Civil Representante</label><input type="text" value="<?= htmlspecialchars($solicitud['pj_estado_civil'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Hijos Representante</label><input type="text" value="<?= htmlspecialchars($solicitud['pj_numero_hijos'] ?? '0'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Grado Instrucción</label><input type="text" value="<?= htmlspecialchars($solicitud['pj_grado_instruccion'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Sabe Leer</label><input type="text" value="<?= htmlspecialchars($solicitud['pj_sabe_leer'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Ayuda Económica</label><input type="text" value="<?= htmlspecialchars($solicitud['pj_posee_ayuda_economica'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Trabaja Actualmente</label><input type="text" value="<?= htmlspecialchars($solicitud['pj_trabaja_actualmente'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Pertenece a Comuna</label><input type="text" value="<?= htmlspecialchars($solicitud['pj_pertenece_comuna'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col" style="grid-column: 1 / -1;"><label class="field-label">Enfermedades</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitud['pj_enfermedades'] ?? 'N/A'); ?></textarea></div>
                                        </div>
                                        <div class="representante-section">
                                            <h4 class="section-title">Datos del Representante Legal</h4>
                                            <div class="field-row">
                                                <div class="field-col"><label class="field-label">Nombre Completo</label><input type="text" value="<?= htmlspecialchars(($solicitud['rp_primer_nombre'] ?? '') . ' ' . ($solicitud['rp_segundo_nombre'] ?? '') . ' ' . ($solicitud['rp_primer_apellido'] ?? '') . ' ' . ($solicitud['rp_segundo_apellido'] ?? '')); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Cédula</label><input type="text" value="<?= htmlspecialchars($solicitud['cedula_representante'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Teléfono</label><input type="text" value="<?= htmlspecialchars($solicitud['rp_telefono'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Email</label><input type="text" value="<?= htmlspecialchars($solicitud['rp_email'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Profesión</label><input type="text" value="<?= htmlspecialchars($solicitud['rp_profesion'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Sexo</label><input type="text" value="<?= htmlspecialchars($solicitud['rp_sexo'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Tipo</label><input type="text" value="<?= htmlspecialchars($solicitud['rp_tipo'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Activo</label><input type="text" value="<?= isset($solicitud['c_activo']) ? ($solicitud['c_activo'] ? 'Sí' : 'No') : ''; ?>" readonly class="form-control"></div>                                                    <div class="field-col" style="grid-column: 1 / -1;"><label class="field-label">Dirección</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitud['rp_direccion'] ?? ''); ?></textarea></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                <?php elseif ($solicitud['tipo_solicitante'] == 'C'): ?>
                                    <div class="section-container">
                                        <h4 class="section-title">Colectivo</h4>
                                        <div class="field-row">
                                            <div class="field-col"><label class="field-label">Nombre del Colectivo</label><input type="text" value="<?= htmlspecialchars($solicitud['c_nombre']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">RIF/CI Referente</label><input type="text" value="<?= htmlspecialchars($solicitud['c_rif']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Teléfono</label><input type="text" value="<?= htmlspecialchars($solicitud['c_telefono']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Dirección</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitud['c_direccion'] ?? 'N/A'); ?></textarea></div>
                                            <div class="field-col"><label class="field-label">Número de Integrantes</label><input type="text" value="<?= htmlspecialchars($solicitud['c_numero_integrantes']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Activo</label><input type="text" value="<?= $solicitud['c_activo'] ? 'Sí' : 'No'; ?>" readonly class="form-control"></div>
                                        </div>
                                        <!-- Integrantes del Colectivo -->
                                        <div class="integrantes-section">
                                            <h4 class="section-title">Integrantes del Colectivo</h4>
                                            <?php
                                            $rif_colectivo = $conn->real_escape_string($solicitud['c_rif']);
                                            $sql_integrantes = "SELECT
                                                cedula,
                                                CONCAT(primer_nombre, ' ', IFNULL(segundo_nombre, ''), ' ', primer_apellido, ' ', IFNULL(segundo_apellido, '')) as nombre_completo,
                                                sexo,
                                                TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) as edad,
                                                telefono,
                                                es_referente,
                                                cargo_en_colectivo,
                                                fecha_ingreso,
                                                estado_civil,
                                                numero_hijos,
                                                grado_instruccion,
                                                sabe_leer,
                                                posee_ayuda_economica,
                                                trabaja_actualmente,
                                                pertenece_comuna,
                                                enfermedades,
                                                activo
                                                FROM colectivo_integrantes
                                                WHERE rif_o_ci_colectivo = '$rif_colectivo'
                                                ORDER BY es_referente DESC, primer_apellido, primer_nombre";
                                            $result_integrantes = $conn->query($sql_integrantes);
                                            if ($result_integrantes && $result_integrantes->num_rows > 0):
                                            ?>
                                                <div class="table-responsive">
                                                    <table>
                                                        <thead>
                                                            <tr>
                                                                <th>Cédula</th>
                                                                <th>Nombre Completo</th>
                                                                <th>Sexo</th>
                                                                <th>Edad</th>
                                                                <th>Teléfono</th>
                                                                <th>Es Referente</th>
                                                                <th>Cargo</th>
                                                                <th>Fecha Ingreso</th>
                                                                <th>Estado Civil</th>
                                                                <th>Hijos</th>
                                                                <th>Grado Instr.</th>
                                                                <th>Sabe Leer</th>
                                                                <th>Ayuda Econ.</th>
                                                                <th>Trabaja</th>
                                                                <th>Pertenece Comuna</th>
                                                                <th>Enfermedades</th>
                                                                <th>Activo</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php while ($integrante = $result_integrantes->fetch_assoc()): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($integrante['cedula']); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['nombre_completo']); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['sexo']); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['edad']); ?> años</td>
                                                                    <td><?= htmlspecialchars($integrante['telefono']); ?></td>
                                                                    <td><?= $integrante['es_referente'] ? 'Sí' : 'No'; ?></td>
                                                                    <td><?= htmlspecialchars($integrante['cargo_en_colectivo'] ?? 'N/A'); ?></td>
                                                                    <td><?= date('d/m/Y', strtotime($integrante['fecha_ingreso'])); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['estado_civil'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['numero_hijos'] ?? '0'); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['grado_instruccion'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['sabe_leer'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['posee_ayuda_economica'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['trabaja_actualmente'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['pertenece_comuna'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars(substr($integrante['enfermedades'] ?? 'N/A', 0, 20)); ?><?php echo strlen($integrante['enfermedades'] ?? '') > 20 ? '...' : ''; ?></td>
                                                                    <td><?= $integrante['activo'] ? 'Sí' : 'No'; ?></td>
                                                                </tr>
                                                            <?php endwhile; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <p class="sin-representante">No se encontraron integrantes para este colectivo.</p>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Datos del Referente -->
                                        <div class="representante-section">
                                            <h4 class="section-title">Datos del Referente</h4>
                                            <div class="field-row">
                                                <div class="field-col"><label class="field-label">Nombre Completo</label><input type="text" value="<?= htmlspecialchars($solicitud['ci_primer_nombre'] . ' ' . ($solicitud['ci_segundo_nombre'] ?? '') . ' ' . $solicitud['ci_primer_apellido'] . ' ' . ($solicitud['ci_segundo_apellido'] ?? '')); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Cédula</label><input type="text" value="<?= htmlspecialchars($solicitud['c_rif']); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Teléfono</label><input type="text" value="<?= htmlspecialchars($solicitud['ci_telefono'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Fecha Nac.</label><input type="text" value="<?= $solicitud['ci_fecha_nacimiento'] ? date('d/m/Y', strtotime($solicitud['ci_fecha_nacimiento'])) : ''; ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Sexo</label><input type="text" value="<?= htmlspecialchars($solicitud['ci_sexo'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Estado Civil</label><input type="text" value="<?= htmlspecialchars($solicitud['ci_estado_civil'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Hijos</label><input type="text" value="<?= htmlspecialchars($solicitud['ci_numero_hijos'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Grado Instrucción</label><input type="text" value="<?= htmlspecialchars($solicitud['ci_grado_instruccion'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Sabe Leer</label><input type="text" value="<?= htmlspecialchars($solicitud['ci_sabe_leer'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Ayuda Económica</label><input type="text" value="<?= htmlspecialchars($solicitud['ci_posee_ayuda_economica'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Trabaja Actualmente</label><input type="text" value="<?= htmlspecialchars($solicitud['ci_trabaja_actualmente'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Pertenece a Comuna</label><input type="text" value="<?= htmlspecialchars($solicitud['ci_pertenece_comuna'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col"><label class="field-label">Cargo en Colectivo</label><input type="text" value="<?= htmlspecialchars($solicitud['ci_cargo'] ?? ''); ?>" readonly class="form-control"></div>
                                                <div class="field-col" style="grid-column: 1 / -1;"><label class="field-label">Dirección</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitud['ci_direccion'] ?? ''); ?></textarea></div>
                                                <div class="field-col" style="grid-column: 1 / -1;"><label class="field-label">Enfermedades</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitud['ci_enfermedades'] ?? ''); ?></textarea></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <h3>No se encontraron resultados</h3>
                            <p>No se encontraron solicitudes que coincidan con "<?= htmlspecialchars($_GET['termino_busqueda']); ?>"</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>

                <!-- Resultados de búsqueda por solicitantes -->
                <div class="section-container">
                    <h2 class="section-title">Resultados de Búsqueda por Solicitantes</h2>
                    <div class="results-count">
                        Se encontraron <?= count($resultados_solicitantes); ?> solicitante(s) para "<?= htmlspecialchars($_GET['termino_busqueda']); ?>"
                    </div>
                    <?php if (!empty($resultados_solicitantes)): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>TIPO</th>
                                        <th>IDENTIFICACIÓN</th>
                                        <th>NOMBRE / RAZÓN SOCIAL</th>
                                        <th>EDAD</th>
                                        <th>TELÉFONO</th>
                                        <th>DIRECCIÓN</th>
                                        <th>REPRESENTANTE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resultados_solicitantes as $solicitante): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($solicitante['tipo_solicitante'] == 'N' ? 'Natural' : ($solicitante['tipo_solicitante'] == 'J' ? 'Jurídica' : 'Colectivo')); ?></td>
                                            <td><?= htmlspecialchars($solicitante['identificacion']); ?></td>
                                            <td><?= htmlspecialchars($solicitante['nombre']); ?></td>
                                            <td><?php
                                                if ($solicitante['tipo_solicitante'] == 'N') {
                                                    echo formatear_edad($solicitante['edad'] ?? null);
                                                } elseif ($solicitante['tipo_solicitante'] == 'C') {
                                                    echo formatear_edad($solicitante['edad_referente'] ?? null);
                                                } else {
                                                    echo '';
                                                }
                                            ?></td>
                                            <td><?= htmlspecialchars($solicitante['pn_telefono'] ?? $solicitante['pj_telefono'] ?? $solicitante['c_telefono'] ?? 'N/A'); ?></td>
                                            <td><?= htmlspecialchars(substr($solicitante['pn_direccion'] ?? $solicitante['pj_direccion'] ?? $solicitante['c_direccion'] ?? 'N/A', 0, 30)); ?><?php echo strlen($solicitante['pn_direccion'] ?? $solicitante['pj_direccion'] ?? $solicitante['c_direccion'] ?? '') > 30 ? '...' : ''; ?></td>
                                            <td><?= htmlspecialchars($solicitante['nombre_representante'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Detalles adicionales por tipo de solicitante -->
                        <?php foreach ($resultados_solicitantes as $solicitante): ?>
                            <div class="section-container">
                                <h3 class="section-title">Detalles del Solicitante: <?= htmlspecialchars($solicitante['nombre']); ?></h3>
                                <?php if ($solicitante['tipo_solicitante'] == 'N'): ?>
                                    <div class="section-container">
                                        <h4 class="section-title">Persona Natural</h4>
                                        <div class="field-row">
                                            <div class="field-col"><label class="field-label">Nombre Completo</label><input type="text" value="<?= htmlspecialchars($solicitante['pn_primer_nombre'] . ' ' . ($solicitante['pn_segundo_nombre'] ?? '') . ' ' . $solicitante['pn_primer_apellido'] . ' ' . ($solicitante['pn_segundo_apellido'] ?? '')); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Cédula</label><input type="text" value="<?= htmlspecialchars($solicitante['identificacion']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Teléfono</label><input type="text" value="<?= htmlspecialchars($solicitante['pn_telefono']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Fecha Nac.</label><input type="text" value="<?= $solicitante['pn_fecha_nacimiento'] ? date('d/m/Y', strtotime($solicitante['pn_fecha_nacimiento'])) : 'N/A'; ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Sexo</label><input type="text" value="<?= htmlspecialchars($solicitante['pn_sexo'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Estado Civil</label><input type="text" value="<?= htmlspecialchars($solicitante['pn_estado_civil'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Hijos</label><input type="text" value="<?= htmlspecialchars($solicitante['pn_numero_hijos'] ?? '0'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Grado Instrucción</label><input type="text" value="<?= htmlspecialchars($solicitante['pn_grado_instruccion'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Sabe Leer</label><input type="text" value="<?= htmlspecialchars($solicitante['pn_sabe_leer'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Ayuda Económica</label><input type="text" value="<?= htmlspecialchars($solicitante['pn_posee_ayuda_economica'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Trabaja Actualmente</label><input type="text" value="<?= htmlspecialchars($solicitante['pn_trabaja_actualmente'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Pertenece a Comuna</label><input type="text" value="<?= htmlspecialchars($solicitante['pn_pertenece_comuna'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col" style="grid-column: 1 / -1;"><label class="field-label">Dirección</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitante['pn_direccion'] ?? 'N/A'); ?></textarea></div>
                                            <div class="field-col" style="grid-column: 1 / -1;"><label class="field-label">Enfermedades</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitante['pn_enfermedades'] ?? 'N/A'); ?></textarea></div>
                                        </div>
                                        <?php if ($solicitante['tiene_representante'] == 'Sí' && $solicitante['nombre_representante'] != 'N/A'): ?>
                                            <div class="representante-section">
                                                <h4 class="section-title">Datos del Apoderado</h4>
                                                <div class="field-row">
                                                    <div class="field-col"><label class="field-label">Nombre Completo</label><input type="text" value="<?= htmlspecialchars(($solicitante['rp_primer_nombre'] ?? '') . ' ' . ($solicitante['rp_segundo_nombre'] ?? '') . ' ' . ($solicitante['rp_primer_apellido'] ?? '') . ' ' . ($solicitante['rp_segundo_apellido'] ?? '')); ?>" readonly class="form-control"></div>
                                                    <div class="field-col"><label class="field-label">Cédula</label><input type="text" value="<?= htmlspecialchars($solicitante['rp_cedula'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                                    <div class="field-col"><label class="field-label">Teléfono</label><input type="text" value="<?= htmlspecialchars($solicitante['rp_telefono'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                                    <div class="field-col"><label class="field-label">Email</label><input type="text" value="<?= htmlspecialchars($solicitante['rp_email'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                                    <div class="field-col"><label class="field-label">Profesión</label><input type="text" value="<?= htmlspecialchars($solicitante['rp_profesion'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                                    <div class="field-col"><label class="field-label">Sexo</label><input type="text" value="<?= htmlspecialchars($solicitante['rp_sexo'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                                    <div class="field-col"><label class="field-label">Tipo</label><input type="text" value="<?= htmlspecialchars($solicitante['rp_tipo'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                                    <div class="field-col"><label class="field-label">Activo</label><input type="text" value="<?= $solicitante['rp_activo'] ? 'Sí' : 'No'; ?>" readonly class="form-control"></div>
                                                    <div class="field-col" style="grid-column: 1 / -1;"><label class="field-label">Dirección</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitante['rp_direccion'] ?? 'N/A'); ?></textarea></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p class="sin-representante">Esta persona natural no tiene apoderado asociado.</p>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($solicitante['tipo_solicitante'] == 'J'): ?>
                                    <div class="section-container">
                                        <h4 class="section-title">Persona Jurídica</h4>
                                        <div class="field-row">
                                            <div class="field-col"><label class="field-label">Razón Social</label><input type="text" value="<?= htmlspecialchars($solicitante['pj_razon_social']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">RIF</label><input type="text" value="<?= htmlspecialchars($solicitante['identificacion']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Teléfono</label><input type="text" value="<?= htmlspecialchars($solicitante['pj_telefono']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Dirección</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitante['pj_direccion'] ?? 'N/A'); ?></textarea></div>
                                            <div class="field-col"><label class="field-label">Estado Civil Representante</label><input type="text" value="<?= htmlspecialchars($solicitante['pj_estado_civil'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Hijos Representante</label><input type="text" value="<?= htmlspecialchars($solicitante['pj_numero_hijos'] ?? '0'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Grado Instrucción</label><input type="text" value="<?= htmlspecialchars($solicitante['pj_grado_instruccion'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Sabe Leer</label><input type="text" value="<?= htmlspecialchars($solicitante['pj_sabe_leer'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Ayuda Económica</label><input type="text" value="<?= htmlspecialchars($solicitante['pj_posee_ayuda_economica'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Trabaja Actualmente</label><input type="text" value="<?= htmlspecialchars($solicitante['pj_trabaja_actualmente'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Pertenece a Comuna</label><input type="text" value="<?= htmlspecialchars($solicitante['pj_pertenece_comuna'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                            <div class="field-col" style="grid-column: 1 / -1;"><label class="field-label">Enfermedades</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitante['pj_enfermedades'] ?? 'N/A'); ?></textarea></div>
                                        </div>
                                        <?php if ($solicitante['tiene_representante'] == 'Sí' && $solicitante['nombre_representante'] != 'N/A'): ?>
                                            <div class="representante-section">
                                                <h4 class="section-title">Datos del Representante Legal</h4>
                                                <div class="field-row">
                                                    <div class="field-col"><label class="field-label">Nombre Completo</label><input type="text" value="<?= htmlspecialchars(($solicitante['rp_primer_nombre'] ?? '') . ' ' . ($solicitante['rp_segundo_nombre'] ?? '') . ' ' . ($solicitante['rp_primer_apellido'] ?? '') . ' ' . ($solicitante['rp_segundo_apellido'] ?? '')); ?>" readonly class="form-control"></div>
                                                    <div class="field-col"><label class="field-label">Cédula</label><input type="text" value="<?= htmlspecialchars($solicitante['rp_cedula'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                                    <div class="field-col"><label class="field-label">Teléfono</label><input type="text" value="<?= htmlspecialchars($solicitante['rp_telefono'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                                    <div class="field-col"><label class="field-label">Email</label><input type="text" value="<?= htmlspecialchars($solicitante['rp_email'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                                    <div class="field-col"><label class="field-label">Profesión</label><input type="text" value="<?= htmlspecialchars($solicitante['rp_profesion'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                                    <div class="field-col"><label class="field-label">Sexo</label><input type="text" value="<?= htmlspecialchars($solicitante['rp_sexo'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                                    <div class="field-col"><label class="field-label">Tipo</label><input type="text" value="<?= htmlspecialchars($solicitante['rp_tipo'] ?? 'N/A'); ?>" readonly class="form-control"></div>
                                                    <div class="field-col"><label class="field-label">Activo</label><input type="text" value="<?= $solicitante['rp_activo'] ? 'Sí' : 'No'; ?>" readonly class="form-control"></div>
                                                    <div class="field-col" style="grid-column: 1 / -1;"><label class="field-label">Dirección</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitante['rp_direccion'] ?? 'N/A'); ?></textarea></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p class="sin-representante">Esta persona jurídica no tiene representante legal asociado.</p>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($solicitante['tipo_solicitante'] == 'C'): ?>
                                    <div class="section-container">
                                        <h4 class="section-title">Colectivo</h4>
                                        <div class="field-row">
                                            <div class="field-col"><label class="field-label">Nombre del Colectivo</label><input type="text" value="<?= htmlspecialchars($solicitante['c_nombre']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">RIF/CI Referente</label><input type="text" value="<?= htmlspecialchars($solicitante['identificacion']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Teléfono</label><input type="text" value="<?= htmlspecialchars($solicitante['c_telefono']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Dirección</label><textarea readonly rows="2" class="form-control"><?= htmlspecialchars($solicitante['c_direccion'] ?? 'N/A'); ?></textarea></div>
                                            <div class="field-col"><label class="field-label">Número de Integrantes</label><input type="text" value="<?= htmlspecialchars($solicitante['c_numero_integrantes']); ?>" readonly class="form-control"></div>
                                            <div class="field-col"><label class="field-label">Activo</label><input type="text" value="<?= $solicitante['c_activo'] ? 'Sí' : 'No'; ?>" readonly class="form-control"></div>
                                        </div>
                                        <!-- Integrantes del Colectivo -->
                                        <div class="integrantes-section">
                                            <h4 class="section-title">Integrantes del Colectivo</h4>
                                            <?php
                                            $rif_colectivo = $conn->real_escape_string($solicitante['identificacion']);
                                            $sql_integrantes = "SELECT
                                                cedula,
                                                CONCAT(primer_nombre, ' ', IFNULL(segundo_nombre, ''), ' ', primer_apellido, ' ', IFNULL(segundo_apellido, '')) as nombre_completo,
                                                sexo,
                                                TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) as edad,
                                                telefono,
                                                es_referente,
                                                cargo_en_colectivo,
                                                fecha_ingreso,
                                                estado_civil,
                                                numero_hijos,
                                                grado_instruccion,
                                                sabe_leer,
                                                posee_ayuda_economica,
                                                trabaja_actualmente,
                                                pertenece_comuna,
                                                enfermedades,
                                                activo
                                                FROM colectivo_integrantes
                                                WHERE rif_o_ci_colectivo = '$rif_colectivo'
                                                ORDER BY es_referente DESC, primer_apellido, primer_nombre";
                                            $result_integrantes = $conn->query($sql_integrantes);
                                            if ($result_integrantes && $result_integrantes->num_rows > 0):
                                            ?>
                                                <div class="table-responsive">
                                                    <table>
                                                        <thead>
                                                            <tr>
                                                                <th>Cédula</th>
                                                                <th>Nombre Completo</th>
                                                                <th>Sexo</th>
                                                                <th>Edad</th>
                                                                <th>Teléfono</th>
                                                                <th>Es Referente</th>
                                                                <th>Cargo</th>
                                                                <th>Fecha Ingreso</th>
                                                                <th>Estado Civil</th>
                                                                <th>Hijos</th>
                                                                <th>Grado Instr.</th>
                                                                <th>Sabe Leer</th>
                                                                <th>Ayuda Econ.</th>
                                                                <th>Trabaja</th>
                                                                <th>Pertenece Comuna</th>
                                                                <th>Enfermedades</th>
                                                                <th>Activo</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php while ($integrante = $result_integrantes->fetch_assoc()): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($integrante['cedula']); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['nombre_completo']); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['sexo']); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['edad']); ?> años</td>
                                                                    <td><?= htmlspecialchars($integrante['telefono']); ?></td>
                                                                    <td><?= $integrante['es_referente'] ? 'Sí' : 'No'; ?></td>
                                                                    <td><?= htmlspecialchars($integrante['cargo_en_colectivo'] ?? 'N/A'); ?></td>
                                                                    <td><?= date('d/m/Y', strtotime($integrante['fecha_ingreso'])); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['estado_civil'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['numero_hijos'] ?? '0'); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['grado_instruccion'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['sabe_leer'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['posee_ayuda_economica'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['trabaja_actualmente'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($integrante['pertenece_comuna'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars(substr($integrante['enfermedades'] ?? 'N/A', 0, 20)); ?><?php echo strlen($integrante['enfermedades'] ?? '') > 20 ? '...' : ''; ?></td>
                                                                    <td><?= $integrante['activo'] ? 'Sí' : 'No'; ?></td>
                                                                </tr>
                                                            <?php endwhile; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <p class="sin-representante">No se encontraron integrantes para este colectivo.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <h3>No se encontraron resultados</h3>
                            <p>No se encontraron solicitantes que coincidan con "<?= htmlspecialchars($_GET['termino_busqueda']); ?>"</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <script>
        function cambiarModo(modo) {
            window.location.href = '?modo=' + modo;
        }
    </script>

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
