<?php
include("header.php");

// Incluir conexión a la base de datos
include("conexion.php");

// Variables para manejar el estado
$busqueda = '';
$solicitudes_encontradas = [];
$mensaje_error = '';
$mensaje_exito = '';
$mostrar_formulario_edicion = false;
$solicitud_a_editar = null;

// Procesar la búsqueda del solicitante (solo por CI o RIF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar'])) {
    $busqueda = trim($_POST['busqueda']);

    if (!empty($busqueda)) {
        $busqueda = mysqli_real_escape_string($conn, $busqueda);

        // Consulta principal que une solicitudes con los datos del solicitante según su tipo
        $sql = "
            SELECT 
                s.id_solicitud,
                s.numero_solicitud,
                s.fecha_solicitud,
                s.tipo_solicitante,
                s.cedula_solicitante_n,
                s.rif_solicitante_j,
                s.rif_ci_solicitante_c,
                s.id_procedimiento,
                s.id_predio,
                s.rubros_a_producir,
                s.estatus,
                s.observaciones,
                s.creado_por,
                p.nombre_predio,
                p.id_municipio,
                p.id_parroquia,
                p.id_sector,
                p.superficie_ha,
                p.lindero_norte,
                p.lindero_sur,
                p.lindero_este,
                p.lindero_oeste,
                p.direccion AS direccion_predio,
                -- Datos del solicitante
                CASE 
                    WHEN s.tipo_solicitante = 'N' THEN CONCAT(pn.primer_nombre, ' ', COALESCE(pn.segundo_nombre, ''), ' ', pn.primer_apellido, ' ', COALESCE(pn.segundo_apellido, ''))
                    WHEN s.tipo_solicitante = 'J' THEN pj.razon_social
                    WHEN s.tipo_solicitante = 'C' THEN c.nombre_colectivo
                END AS nombre_completo,
                CASE 
                    WHEN s.tipo_solicitante = 'N' THEN pn.telefono
                    WHEN s.tipo_solicitante = 'J' THEN pj.telefono
                    WHEN s.tipo_solicitante = 'C' THEN c.telefono
                END AS telefono_solicitante,
                CASE 
                    WHEN s.tipo_solicitante = 'N' THEN pn.direccion_habitacion
                    WHEN s.tipo_solicitante = 'J' THEN pj.direccion_habitacion
                    WHEN s.tipo_solicitante = 'C' THEN c.direccion_habitacion
                END AS direccion_solicitante,
                tp.nombre_procedimiento
            FROM solicitudes s
            LEFT JOIN predios p ON s.id_predio = p.id_predio
            LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
            LEFT JOIN parroquias par ON p.id_parroquia = par.id_parroquia
            LEFT JOIN sectores sec ON p.id_sector = sec.id_sector
            LEFT JOIN personas_naturales pn ON s.cedula_solicitante_n = pn.cedula
            LEFT JOIN personas_juridicas pj ON s.rif_solicitante_j = pj.rif
            LEFT JOIN colectivos c ON s.rif_ci_solicitante_c = c.rif_o_ci_referente
            LEFT JOIN tipo_procedimiento tp ON s.id_procedimiento = tp.id_procedimiento
            WHERE (
                (s.tipo_solicitante = 'N' AND pn.cedula LIKE '%$busqueda%')
                OR
                (s.tipo_solicitante = 'J' AND pj.rif LIKE '%$busqueda%')
                OR
                (s.tipo_solicitante = 'C' AND c.rif_o_ci_referente LIKE '%$busqueda%')
            )
            ORDER BY s.creado_en DESC";

        $result = mysqli_query($conn, $sql);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $solicitudes_encontradas[] = $row;
            }
            if (empty($solicitudes_encontradas)) {
                $mensaje_error = "No se encontraron solicitudes relacionadas con '$busqueda'.";
            }
        } else {
            $mensaje_error = "Error en la consulta: " . mysqli_error($conn);
        }
    } else {
        $mensaje_error = "Por favor, ingrese un término de búsqueda.";
    }
}

// Manejar la acción de editar una solicitud específica
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $id_solicitud = intval($_GET['editar']);
    
    $sql = "
        SELECT 
            s.*,
            p.nombre_predio,
            p.id_municipio,
            p.id_parroquia,
            p.id_sector,
            p.superficie_ha,
            p.lindero_norte,
            p.lindero_sur,
            p.lindero_este,
            p.lindero_oeste,
            p.direccion AS direccion_predio
        FROM solicitudes s
        LEFT JOIN predios p ON s.id_predio = p.id_predio
        WHERE s.id_solicitud = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $mensaje_error = "Error al preparar la consulta: " . mysqli_error($conn);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $id_solicitud);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $solicitud_a_editar = $row;
            $mostrar_formulario_edicion = true;
        } else {
            $mensaje_error = "No se encontró la solicitud especificada.";
        }
        mysqli_stmt_close($stmt);
    }
}

// Manejar la actualización de la solicitud y el predio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_cambios'])) {
    $id_solicitud = intval($_POST['id_solicitud']);
    $id_predio = intval($_POST['id_predio']);
    
    // Datos de la Solicitud
    $numero_solicitud = $_POST['numero_solicitud'] ?? '';
    $fecha_solicitud = $_POST['fecha_solicitud'] ?? '';
    $id_procedimiento = intval($_POST['tipo_procedimiento'] ?? 0);
    $rubros_a_producir = $_POST['rubros_producir'] ?? null;
    $estatus = $_POST['estatus'] ?? '';
    $observaciones = $_POST['observaciones'] ?? null;
    $creado_por = $_SESSION['usuario']['cedula'];

    // Datos del Predio
    $nombre_predio = $_POST['nombre_predio'] ?? '';
    $id_municipio = intval($_POST['municipio'] ?? 0);
    $id_parroquia = intval($_POST['parroquia'] ?? 0);
    $id_sector = intval($_POST['sector'] ?? 0);
    $superficie_ha = $_POST['superficie_total'] ?? '';
    $lindero_norte = $_POST['lindero_norte'] ?? null;
    $lindero_sur = $_POST['lindero_sur'] ?? null;
    $lindero_este = $_POST['lindero_este'] ?? null;
    $lindero_oeste = $_POST['lindero_oeste'] ?? null;
    $direccion_predio = $_POST['direccion_predio'] ?? null;

    // Validación exhaustiva
    $errores = [];
    if (empty($nombre_predio)) $errores[] = "El nombre del predio es obligatorio.";
    if ($id_municipio <= 0) $errores[] = "Debe seleccionar un municipio válido.";
    if ($id_parroquia <= 0) $errores[] = "Debe seleccionar una parroquia válida.";
    if ($id_sector <= 0) $errores[] = "Debe seleccionar un sector válido.";
    if ($id_procedimiento <= 0) $errores[] = "Debe seleccionar un tipo de procedimiento.";
    if (empty($numero_solicitud)) $errores[] = "El número de solicitud es obligatorio.";
    if (empty($fecha_solicitud)) $errores[] = "La fecha de solicitud es obligatoria.";
    if (empty($estatus)) $errores[] = "Debe seleccionar un estatus.";

    if (!empty($errores)) {
        $mensaje_error = implode("<br>", $errores);
        $mostrar_formulario_edicion = true; // Mantener el formulario visible si hay error
    } else {
        // Comenzar transacción
        mysqli_autocommit($conn, FALSE);

        try {
            // --- Actualizar el predio ---
            $sql_update_predio = "UPDATE predios SET 
                nombre_predio = ?, id_municipio = ?, id_parroquia = ?, id_sector = ?, 
                superficie_ha = ?, lindero_norte = ?, lindero_sur = ?, lindero_este = ?, 
                lindero_oeste = ?, direccion = ? 
                WHERE id_predio = ?";

            $stmt_predio = mysqli_prepare($conn, $sql_update_predio);
            if (!$stmt_predio) {
                throw new Exception("Error al preparar la consulta del predio: " . mysqli_error($conn));
            }

            $var_nombre_predio = $nombre_predio;
            $var_id_municipio = $id_municipio;
            $var_id_parroquia = $id_parroquia;
            $var_id_sector = $id_sector;
            $var_superficie_ha = $superficie_ha;
            $var_lindero_norte = $lindero_norte ?: null;
            $var_lindero_sur = $lindero_sur ?: null;
            $var_lindero_este = $lindero_este ?: null;
            $var_lindero_oeste = $lindero_oeste ?: null;
            $var_direccion_predio = $direccion_predio ?: null;
            $var_id_predio = $id_predio;

            $bind_result = mysqli_stmt_bind_param(
                $stmt_predio,
                "siisssssssi",
                $var_nombre_predio,
                $var_id_municipio,
                $var_id_parroquia,
                $var_id_sector,
                $var_superficie_ha,
                $var_lindero_norte,
                $var_lindero_sur,
                $var_lindero_este,
                $var_lindero_oeste,
                $var_direccion_predio,
                $var_id_predio
            );

            if (!$bind_result) {
                throw new Exception("Error al vincular parámetros del predio: " . mysqli_stmt_error($stmt_predio));
            }

            if (!mysqli_stmt_execute($stmt_predio)) {
                throw new Exception("Error al ejecutar la consulta del predio: " . mysqli_stmt_error($stmt_predio));
            }
            mysqli_stmt_close($stmt_predio);

            // --- Actualizar la solicitud ---
            $sql_update_solicitud = "UPDATE solicitudes SET 
                numero_solicitud = ?, fecha_solicitud = ?, id_procedimiento = ?, 
                rubros_a_producir = ?, estatus = ?, observaciones = ? 
                WHERE id_solicitud = ?";

            $stmt_solicitud = mysqli_prepare($conn, $sql_update_solicitud);
            if (!$stmt_solicitud) {
                throw new Exception("Error al preparar la consulta de la solicitud: " . mysqli_error($conn));
            }

            $var_numero_solicitud = $numero_solicitud;
            $var_fecha_solicitud = $fecha_solicitud;
            $var_id_procedimiento = $id_procedimiento;
            $var_rubros_a_producir = $rubros_a_producir ?: null;
            $var_estatus = $estatus;
            $var_observaciones = $observaciones ?: null;
            $var_id_solicitud = $id_solicitud;

            $bind_result = mysqli_stmt_bind_param(
                $stmt_solicitud, 
                "ssissss", 
                $var_numero_solicitud, 
                $var_fecha_solicitud, 
                $var_id_procedimiento, 
                $var_rubros_a_producir, 
                $var_estatus, 
                $var_observaciones, 
                $var_id_solicitud
            );

            if (!$bind_result) {
                throw new Exception("Error al vincular parámetros de la solicitud: " . mysqli_stmt_error($stmt_solicitud));
            }

            if (!mysqli_stmt_execute($stmt_solicitud)) {
                throw new Exception("Error al ejecutar la consulta de la solicitud: " . mysqli_stmt_error($stmt_solicitud));
            }
            mysqli_stmt_close($stmt_solicitud);

            // --- Registrar en la bitácora ---
            $accion = "Edición";
            $tabla_afectada = "solicitudes";
            $registro_afectado = $id_solicitud;
            $detalle = "Se editó la solicitud número $numero_solicitud. Cambios: " . json_encode([
                'numero_solicitud' => $numero_solicitud,
                'fecha_solicitud' => $fecha_solicitud,
                'id_procedimiento' => $id_procedimiento,
                'rubros_a_producir' => $rubros_a_producir,
                'estatus' => $estatus,
                'observaciones' => $observaciones
            ]);

            $sql_bitacora = "INSERT INTO bitacora (cedula_usuario, accion, tabla_afectada, registro_afectado, detalle) VALUES (?, ?, ?, ?, ?)";
            $stmt_bitacora = mysqli_prepare($conn, $sql_bitacora);
            if (!$stmt_bitacora) {
                throw new Exception("Error al preparar la consulta de la bitácora: " . mysqli_error($conn));
            }

            $var_cedula_usuario = $creado_por;
            $var_accion = $accion;
            $var_tabla_afectada = $tabla_afectada;
            $var_registro_afectado = $registro_afectado;
            $var_detalle = $detalle;

            $bind_result = mysqli_stmt_bind_param($stmt_bitacora, "sssss", $var_cedula_usuario, $var_accion, $var_tabla_afectada, $var_registro_afectado, $var_detalle);
            if (!$bind_result) {
                throw new Exception("Error al vincular parámetros de la bitácora: " . mysqli_stmt_error($stmt_bitacora));
            }

            if (!mysqli_stmt_execute($stmt_bitacora)) {
                throw new Exception("Error al ejecutar la consulta de la bitácora: " . mysqli_stmt_error($stmt_bitacora));
            }
            mysqli_stmt_close($stmt_bitacora);

            // Confirmar transacción
            mysqli_commit($conn);
            $mensaje_exito = "Solicitud y predio actualizados exitosamente.";
            $mostrar_formulario_edicion = false;
            $solicitudes_encontradas = []; // Limpiar resultados después de guardar

        } catch (Exception $e) {
            // Revertir transacción en caso de error
            mysqli_rollback($conn);
            $mensaje_error = "Error durante la actualización: " . $e->getMessage();
            $mostrar_formulario_edicion = true; // Mantener el formulario visible
        }

        // Restaurar autocommit
        mysqli_autocommit($conn, TRUE);
    }
}
?>

<!-- Contenido de la página -->
<div class="container">

    <!-- Título principal -->
    <h1 style="font-family:montserrat; font-weight:900; color:green; padding:20px; text-align:left; font-size:50px;"><i class="zmdi zmdi-edit"></i> Editar <span style="font-weight:500; color:black;">Solicitud</span></h1>


    <?php if (!$mostrar_formulario_edicion): ?>
    <!-- Sección de Búsqueda -->
    <div class="section-container">
        
        <h3 class="section-title">Buscar Solicitante o Solicitud</h3>
        <form method="POST" action="" class="search-form">
            <div class="form-group">
                <label for="cedula_rif" class="form-label required" style="font-weight: 700; color:black;">Cédula o RIF</label>
                <input type="number" name="busqueda" id="busqueda" class="form-control" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Ingrese CI o RIF..." required />
            </div>
            <div class="btn-container">
                <button type="submit" name="buscar" class="btn btn-primary"><i class="zmdi zmdi-search"></i> Buscar</button>
            </div>
        </form>

        <?php if ($mensaje_error): ?>
            <div class="alert alert-danger mt-3"><?php echo $mensaje_error; ?></div>
        <?php endif; ?>

        <?php if (!empty($solicitudes_encontradas)): ?>
            <div class="results-section mt-4">
                <div class="results-title">Resultados de la Búsqueda</div>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>N° Solicitud</th>
                            <th>Solicitante</th>
                            <th>Tipo</th>
                            <th>Procedimiento</th>
                            <th>Estatus</th>
                            <th>Fecha</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes_encontradas as $solicitud): ?>
                        <tr>
                            <td data-label="N° Solicitud"><?php echo htmlspecialchars($solicitud['numero_solicitud']); ?></td>
                            <td data-label="Solicitante"><?php echo htmlspecialchars($solicitud['nombre_completo']); ?></td>
                            <td data-label="Tipo">
                                <?php 
                                switch($solicitud['tipo_solicitante']) {
                                    case 'N': echo 'Persona Natural'; break;
                                    case 'J': echo 'Persona Jurídica'; break;
                                    case 'C': echo 'Colectivo'; break;
                                    default: echo 'Desconocido';
                                }
                                ?>
                            </td>
                            <td data-label="Procedimiento"><?php echo htmlspecialchars($solicitud['nombre_procedimiento']); ?></td>
                            <td data-label="Estatus"><?php echo str_replace('_', ' ', htmlspecialchars($solicitud['estatus'])); ?></td>
                            <td data-label="Fecha"><?php echo date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?></td>
                            <td data-label="Acción">
                                <a href="?editar=<?php echo $solicitud['id_solicitud']; ?>" class="btn btn-primary">
                                    <i class="zmdi zmdi-edit"></i> Editar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($mostrar_formulario_edicion && $solicitud_a_editar): ?>
        
    <!-- Formulario de Edición de Solicitud -->
    <div class="section-container">
        <h3 class="section-title">
            Editar Solicitud - 
            <?php 
            // Mostrar la Cédula/RIF del solicitante en lugar del número de solicitud
            if ($solicitud_a_editar['tipo_solicitante'] == 'N') {
                echo $solicitud_a_editar['cedula_solicitante_n'];
            } elseif ($solicitud_a_editar['tipo_solicitante'] == 'J') {
                echo $solicitud_a_editar['rif_solicitante_j'];
            } else {
                echo $solicitud_a_editar['rif_ci_solicitante_c'];
            }
            ?>
        </h3>
        
        <form method="POST" action="">
            <input type="hidden" name="guardar_cambios" value="1">
            <input type="hidden" name="id_solicitud" value="<?php echo $solicitud_a_editar['id_solicitud']; ?>">
            <input type="hidden" name="id_predio" value="<?php echo $solicitud_a_editar['id_predio']; ?>">

            <div class="field-row">
                <div class="field-col">
                    <label class="field-label required">Número de Solicitud</label>
                    <input type="text" name="numero_solicitud" class="form-control" value="<?php echo htmlspecialchars($solicitud_a_editar['numero_solicitud']); ?>" required />
                </div>
                <div class="field-col">
                    <label class="field-label required">Fecha de Solicitud</label>
                    <input type="date" name="fecha_solicitud" class="form-control" value="<?php echo $solicitud_a_editar['fecha_solicitud']; ?>" required />
                </div>
            </div>

            <!-- Datos del Predio -->
            <div class="section-container" style="margin-top: 20px;">
                <h4 class="section-title">Datos del Predio</h4>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Nombre del Predio</label>
                        <input type="text" name="nombre_predio" class="form-control" value="<?php echo htmlspecialchars($solicitud_a_editar['nombre_predio']); ?>" required />
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Municipio</label>
                        <select name="municipio" id="municipio" class="form-control" required onchange="cargarParroquias()">
                            <option value="">Seleccionar...</option>
                            <?php
                                $sql_municipios = "SELECT id_municipio, nombre_municipio FROM municipios ORDER BY nombre_municipio";
                                $result_municipios = mysqli_query($conn, $sql_municipios);
                                if ($result_municipios) {
                                    while ($municipio = mysqli_fetch_assoc($result_municipios)) {
                                        $selected = ($municipio['id_municipio'] == $solicitud_a_editar['id_municipio']) ? 'selected' : '';
                                        echo "<option value='{$municipio['id_municipio']}' $selected>{$municipio['nombre_municipio']}</option>";
                                    }
                                } else {
                                    echo "<option value=''>Error al cargar municipios</option>";
                                }
                            ?>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Parroquia</label>
                        <select name="parroquia" id="parroquia" class="form-control" required onchange="cargarSectores()">
                            <option value="">Primero seleccione un Municipio</option>
                            <?php
                                if ($solicitud_a_editar['id_municipio'] > 0) {
                                    $sql_parroquias = "SELECT id_parroquia, nombre_parroquia FROM parroquias WHERE id_municipio = ? ORDER BY nombre_parroquia";
                                    $stmt_parroquias = mysqli_prepare($conn, $sql_parroquias);
                                    if ($stmt_parroquias) {
                                        mysqli_stmt_bind_param($stmt_parroquias, "i", $solicitud_a_editar['id_municipio']);
                                        mysqli_stmt_execute($stmt_parroquias);
                                        $result_parroquias = mysqli_stmt_get_result($stmt_parroquias);
                                        if ($result_parroquias) {
                                            while ($parroquia = mysqli_fetch_assoc($result_parroquias)) {
                                                $selected = ($parroquia['id_parroquia'] == $solicitud_a_editar['id_parroquia']) ? 'selected' : '';
                                                echo "<option value='{$parroquia['id_parroquia']}' $selected>{$parroquia['nombre_parroquia']}</option>";
                                            }
                                        }
                                        mysqli_stmt_close($stmt_parroquias);
                                    }
                                }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Sector</label>
                        <select name="sector" id="sector" class="form-control" required>
                            <option value="">Primero seleccione una Parroquia</option>
                            <?php
                                if ($solicitud_a_editar['id_parroquia'] > 0) {
                                    $sql_sectores = "SELECT id_sector, nombre_sector FROM sectores WHERE id_parroquia = ? ORDER BY nombre_sector";
                                    $stmt_sectores = mysqli_prepare($conn, $sql_sectores);
                                    if ($stmt_sectores) {
                                        mysqli_stmt_bind_param($stmt_sectores, "i", $solicitud_a_editar['id_parroquia']);
                                        mysqli_stmt_execute($stmt_sectores);
                                        $result_sectores = mysqli_stmt_get_result($stmt_sectores);
                                        if ($result_sectores) {
                                            while ($sector = mysqli_fetch_assoc($result_sectores)) {
                                                $selected = ($sector['id_sector'] == $solicitud_a_editar['id_sector']) ? 'selected' : '';
                                                echo "<option value='{$sector['id_sector']}' $selected>{$sector['nombre_sector']}</option>";
                                            }
                                        }
                                        mysqli_stmt_close($stmt_sectores);
                                    }
                                }
                            ?>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Superficie Total (Ha)</label>
                        <input type="text" name="superficie_total" class="form-control" value="<?php echo $solicitud_a_editar['superficie_ha']; ?>" required/>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Linderos</label>
                        <textarea name="lindero_norte" class="form-control" rows="2" placeholder="Lindero Norte..." required><?php echo htmlspecialchars($solicitud_a_editar['lindero_norte']); ?></textarea>
                    </div>
                    <div class="field-col">
                        <label class="field-label">&nbsp;</label>
                        <textarea name="lindero_sur" class="form-control" rows="2" placeholder="Lindero Sur..." required><?php echo htmlspecialchars($solicitud_a_editar['lindero_sur']); ?></textarea>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <textarea name="lindero_este" class="form-control" rows="2" placeholder="Lindero Este..." required><?php echo htmlspecialchars($solicitud_a_editar['lindero_este']); ?></textarea>
                    </div>
                    <div class="field-col">
                        <textarea name="lindero_oeste" class="form-control" rows="2" placeholder="Lindero Oeste..." required><?php echo htmlspecialchars($solicitud_a_editar['lindero_oeste']); ?></textarea>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Dirección del Predio</label>
                        <textarea name="direccion_predio" class="form-control" rows="2" placeholder="Dirección completa del predio..." required><?php echo htmlspecialchars($solicitud_a_editar['direccion_predio']); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Datos de la Solicitud -->
            <div class="section-container" style="margin-top: 20px;">
                <h4 class="section-title">Datos de la Solicitud</h4>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Tipo de Procedimiento</label>
                        <select name="tipo_procedimiento" class="form-control" required>
                            <option value="">Seleccionar...</option>
                            <?php
                                $sql_proc = "SELECT id_procedimiento, nombre_procedimiento FROM tipo_procedimiento ORDER BY nombre_procedimiento";
                                $result_proc = mysqli_query($conn, $sql_proc);
                                if ($result_proc) {
                                    while ($proc = mysqli_fetch_assoc($result_proc)) {
                                        $selected = ($proc['id_procedimiento'] == $solicitud_a_editar['id_procedimiento']) ? 'selected' : '';
                                        echo "<option value='{$proc['id_procedimiento']}' $selected>{$proc['nombre_procedimiento']}</option>";
                                    }
                                } else {
                                    echo "<option value=''>Error al cargar procedimientos</option>";
                                }
                            ?>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Estatus</label>
                        <select name="estatus" class="form-control" required>
                            <option value="">Seleccionar...</option>
                            <option value="Por_Inspeccion" <?php echo ($solicitud_a_editar['estatus'] == 'Por_Inspeccion') ? 'selected' : ''; ?>>Por Inspección</option>
                            <option value="En_Ejecucion" <?php echo ($solicitud_a_editar['estatus'] == 'En_Ejecucion') ? 'selected' : ''; ?>>En Ejecución</option>
                            <option value="En_INTI_Central" <?php echo ($solicitud_a_editar['estatus'] == 'En_INTI_Central') ? 'selected' : ''; ?>>En INTI Central</option>
                            <option value="Aprobado" <?php echo ($solicitud_a_editar['estatus'] == 'Aprobado') ? 'selected' : ''; ?>>Aprobado</option>
                        </select>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Rubros a Producir</label>
                        <textarea name="rubros_producir" class="form-control" rows="3" placeholder="Ej: Maíz, Yuca..." required><?php echo htmlspecialchars($solicitud_a_editar['rubros_a_producir']); ?></textarea>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3" required><?php echo htmlspecialchars($solicitud_a_editar['observaciones']); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="button-container">
                <a href="editar_solicitud.php" class="btn btn-secondary">
                    <i class="zmdi zmdi-arrow-left"></i> Volver
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="zmdi zmdi-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success mt-3"><?php echo $mensaje_exito; ?></div>
    <?php endif; ?>
</div>

<?php include("footer.php"); ?>

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

<script>
    function cargarParroquias() {
        var id_municipio = $('#municipio').val();
        if (id_municipio) {
            $.ajax({
                url: 'obtener_datos.php',
                type: 'POST',
                 {tipo: 'parroquias', id_municipio: id_municipio},
                dataType: 'json',
                success: function(data) {
                    var parroquiaSelect = $('#parroquia');
                    parroquiaSelect.empty();
                    parroquiaSelect.append('<option value="">Seleccionar...</option>');
                    $.each(data, function(key, value) {
                        parroquiaSelect.append('<option value="' + value.id_parroquia + '">' + value.nombre_parroquia + '</option>');
                    });
                    $('#sector').empty().append('<option value="">Primero seleccione una Parroquia</option>');
                },
                error: function(xhr, status, error) {
                    console.error("Error AJAX al cargar parroquias:", error);
                    alert("Hubo un problema al cargar las parroquias.");
                }
            });
        } else {
            $('#parroquia').empty().append('<option value="">Primero seleccione un Municipio</option>');
            $('#sector').empty().append('<option value="">Primero seleccione una Parroquia</option>');
        }
    }

    function cargarSectores() {
        var id_parroquia = $('#parroquia').val();
        if (id_parroquia) {
            $.ajax({
                url: 'obtener_datos.php',
                type: 'POST',
                 {tipo: 'sectores', id_parroquia: id_parroquia},
                dataType: 'json',
                success: function(data) {
                    var sectorSelect = $('#sector');
                    sectorSelect.empty();
                    sectorSelect.append('<option value="">Seleccionar...</option>');
                    $.each(data, function(key, value) {
                        sectorSelect.append('<option value="' + value.id_sector + '">' + value.nombre_sector + '</option>');
                    });
                },
                error: function(xhr, status, error) {
                    console.error("Error AJAX al cargar sectores:", error);
                    alert("Hubo un problema al cargar los sectores.");
                }
            });
        } else {
            $('#sector').empty().append('<option value="">Primero seleccione una Parroquia</option>');
        }
    }
</script>