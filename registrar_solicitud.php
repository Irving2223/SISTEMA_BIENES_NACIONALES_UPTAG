<?php
include("header.php");

// Incluir conexión a la base de datos
include("conexion.php");

// Variables para manejar el estado
$cedula_rif = '';
$solicitante_encontrado = null;
$tipo_solicitante = '';
$mostrar_formulario = false;
$mensaje_error = '';
$mensaje_exito = '';

// Procesar la búsqueda del solicitante
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar'])) {
    $cedula_rif = trim($_POST['cedula_rif']);

    if (!empty($cedula_rif)) {
        $cedula_rif = mysqli_real_escape_string($conn, $cedula_rif);

        $sql_natural = "SELECT cedula, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, telefono, direccion_habitacion FROM personas_naturales WHERE cedula LIKE '%$cedula_rif%'";
        $sql_juridica = "SELECT rif, razon_social, telefono, direccion_habitacion FROM personas_juridicas WHERE rif LIKE '%$cedula_rif%'";
        $sql_colectivo = "SELECT rif_o_ci_referente, nombre_colectivo, telefono, direccion_habitacion FROM colectivos WHERE rif_o_ci_referente LIKE '%$cedula_rif%'";

        $result_natural = mysqli_query($conn, $sql_natural);
        $result_juridica = mysqli_query($conn, $sql_juridica);
        $result_colectivo = mysqli_query($conn, $sql_colectivo);

        if ($row = mysqli_fetch_assoc($result_natural)) {
            $solicitante_encontrado = $row;
            $tipo_solicitante = 'N';
        } elseif ($row = mysqli_fetch_assoc($result_juridica)) {
            $solicitante_encontrado = $row;
            $tipo_solicitante = 'J';
        } elseif ($row = mysqli_fetch_assoc($result_colectivo)) {
            $solicitante_encontrado = $row;
            $tipo_solicitante = 'C';
        } else {
            $mensaje_error = "No se encontró ningún solicitante.";
        }
    } else {
        $mensaje_error = "Por favor, ingrese una cédula o RIF.";
    }
}

// Manejar la acción de "Agregar Solicitud"
if (isset($_GET['action']) && $_GET['action'] === 'form' && isset($_SESSION['temp_solicitante'])) {
    $mostrar_formulario = true;
    $solicitante_encontrado = $_SESSION['temp_solicitante'];
    $tipo_solicitante = $_SESSION['temp_tipo_solicitante'];
} elseif (isset($_GET['action']) && $_GET['action'] === 'search') {
    $mostrar_formulario = false;
}

// Manejar el registro de la solicitud y el predio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_solicitud'])) {
    // Datos del Solicitante (recuperados de la sesión)
    $cedula_solicitante_n = $tipo_solicitante == 'N' ? $_POST['cedula_rif'] : null;
    $rif_solicitante_j = $tipo_solicitante == 'J' ? $_POST['cedula_rif'] : null;
    $rif_ci_solicitante_c = $tipo_solicitante == 'C' ? $_POST['cedula_rif'] : null;
    $creado_por = $_SESSION['usuario']['cedula'];

    // Datos de la Solicitud
    $numero_solicitud = $_POST['numero_solicitud'] ?? '';
    $fecha_solicitud = $_POST['fecha_solicitud'] ?? '';
    $id_procedimiento = intval($_POST['tipo_procedimiento'] ?? 0);
    $rubros_a_producir = $_POST['rubros_producir'] ?? null;
    $estatus = $_POST['estatus'] ?? '';
    $observaciones = $_POST['observaciones'] ?? null;

    // Datos del Predio (Desde las listas desplegables)
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
    if (empty($estatus)) $errores[] = "Debe seleccionar un estatus inicial.";

    if (!empty($errores)) {
        $mensaje_error = implode("<br>", $errores);
    } else {
        // Comenzar transacción
        mysqli_autocommit($conn, FALSE);

        try {
            
// --- Insertar el predio primero ---
$sql_insert_predio = "INSERT INTO predios 
    (nombre_predio, id_municipio, id_parroquia, id_sector, superficie_ha, lindero_norte, lindero_sur, lindero_este, lindero_oeste, direccion) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt_predio = mysqli_prepare($conn, $sql_insert_predio);
if (!$stmt_predio) {
    throw new Exception("Error al preparar la consulta del predio: " . mysqli_error($conn));
}

// Variables para bind_param (evitar pasar expresiones directamente)
$var_nombre_predio = $nombre_predio;
$var_id_municipio = $id_municipio;
$var_id_parroquia = $id_parroquia;
$var_id_sector = $id_sector;
$var_superficie_ha = $superficie_ha;
$var_lindero_norte = $lindero_norte ?: null; // Asegura que sea NULL si está vacío
$var_lindero_sur = $lindero_sur ?: null;
$var_lindero_este = $lindero_este ?: null;
$var_lindero_oeste = $lindero_oeste ?: null;
$var_direccion_predio = $direccion_predio ?: null;

$bind_result = mysqli_stmt_bind_param(
    $stmt_predio,
    "siiissssss", // Cambiado para superficie_ha como string
    $var_nombre_predio,
    $var_id_municipio,
    $var_id_parroquia,
    $var_id_sector,
    $var_superficie_ha,
    $var_lindero_norte,
    $var_lindero_sur,
    $var_lindero_este,
    $var_lindero_oeste,
    $var_direccion_predio
);

            if (!$bind_result) {
                throw new Exception("Error al vincular parámetros del predio: " . mysqli_stmt_error($stmt_predio));
            }

            if (!mysqli_stmt_execute($stmt_predio)) {
                throw new Exception("Error al ejecutar la consulta del predio: " . mysqli_stmt_error($stmt_predio));
            }

            $id_predio = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_predio);

            // --- Insertar la solicitud usando el ID del predio ---
            $sql_insert_solicitud = "INSERT INTO solicitudes 
                (numero_solicitud, fecha_solicitud, tipo_solicitante, cedula_solicitante_n, rif_solicitante_j, rif_ci_solicitante_c, id_procedimiento, id_predio, rubros_a_producir, estatus, observaciones, creado_por) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt_solicitud = mysqli_prepare($conn, $sql_insert_solicitud);
            if (!$stmt_solicitud) {
                throw new Exception("Error al preparar la consulta de la solicitud: " . mysqli_error($conn));
            }

            // Variables para bind_param (evitar pasar expresiones directamente)
            $var_numero_solicitud = $numero_solicitud;
            $var_fecha_solicitud = $fecha_solicitud;
            $var_tipo_solicitante = $tipo_solicitante;
            $var_cedula_solicitante_n = $cedula_solicitante_n ?: null;
            $var_rif_solicitante_j = $rif_solicitante_j ?: null;
            $var_rif_ci_solicitante_c = $rif_ci_solicitante_c ?: null;
            $var_id_procedimiento = $id_procedimiento;
            $var_id_predio = $id_predio;
            $var_rubros_a_producir = $rubros_a_producir ?: null;
            $var_estatus = $estatus;
            $var_observaciones = $observaciones ?: null;
            $var_creado_por = $creado_por;

            $bind_result = mysqli_stmt_bind_param(
                $stmt_solicitud, 
                "ssssssisssss", // 12 parámetros
                $var_numero_solicitud, 
                $var_fecha_solicitud, 
                $var_tipo_solicitante, 
                $var_cedula_solicitante_n, 
                $var_rif_solicitante_j, 
                $var_rif_ci_solicitante_c, 
                $var_id_procedimiento, 
                $var_id_predio, 
                $var_rubros_a_producir, 
                $var_estatus, 
                $var_observaciones, 
                $var_creado_por
            );

            if (!$bind_result) {
                throw new Exception("Error al vincular parámetros de la solicitud: " . mysqli_stmt_error($stmt_solicitud));
            }

            if (!mysqli_stmt_execute($stmt_solicitud)) {
                throw new Exception("Error al ejecutar la consulta de la solicitud: " . mysqli_stmt_error($stmt_solicitud));
            }

            $id_solicitud_generada = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_solicitud);

// --- Registrar en la bitácora ---
$accion = "Registro";
$sql_tipo_proc = "SELECT nombre_procedimiento FROM tipo_procedimiento WHERE id_procedimiento = ?";
$stmt_tipo_proc = mysqli_prepare($conn, $sql_tipo_proc);
mysqli_stmt_bind_param($stmt_tipo_proc, "i", $id_procedimiento);
mysqli_stmt_execute($stmt_tipo_proc);
$result_tipo_proc = mysqli_stmt_get_result($stmt_tipo_proc);
$row_tipo_proc = mysqli_fetch_assoc($result_tipo_proc);
$nombre_tipo_procedimiento = $row_tipo_proc['nombre_procedimiento'] ?? 'Tipo desconocido';

$sql_bitacora = "INSERT INTO bitacora (cedula_usuario, accion, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, ?, ?, ?, ?, NOW())";
$stmt_bitacora = mysqli_prepare($conn, $sql_bitacora);
if (!$stmt_bitacora) {
    throw new Exception("Error al preparar la consulta de la bitácora: " . mysqli_error($conn));
}

$var_cedula_usuario = $creado_por;
$var_accion = $accion;
$var_tabla = $tipo_solicitante == 'N' ? 'personas_naturales' : ($tipo_solicitante == 'J' ? 'personas_juridicas' : 'colectivos');
$var_registro_afectado = $cedula_solicitante_n ?: ($rif_solicitante_j ?: $rif_ci_solicitante_c);
$var_detalle = "Registro de solicitud tipo: $nombre_tipo_procedimiento";

$bind_result = mysqli_stmt_bind_param($stmt_bitacora, "sssss", $var_cedula_usuario, $var_accion, $var_tabla, $var_registro_afectado, $var_detalle);
if (!$bind_result) {
    throw new Exception("Error al vincular parámetros de la bitácora: " . mysqli_stmt_error($stmt_bitacora));
}

if (!mysqli_stmt_execute($stmt_bitacora)) {
    throw new Exception("Error al ejecutar la consulta de la bitácora: " . mysqli_stmt_error($stmt_bitacora));
}
mysqli_stmt_close($stmt_bitacora);

            // Confirmar transacción
            mysqli_commit($conn);
            $mensaje_exito = "Solicitud y predio registrados exitosamente.";

            // Limpiar variables
            $solicitante_encontrado = null;
            $tipo_solicitante = '';
            $mostrar_formulario = false;

        } catch (Exception $e) {
            // Revertir transacción en caso de error
            mysqli_rollback($conn);
            $mensaje_error = "Error durante el registro: " . $e->getMessage();
        }

        // Restaurar autocommit
        mysqli_autocommit($conn, TRUE);
    }
}
?>

<!-- Contenido de la página -->
<div class="container">

    <!-- Título principal -->
    <h1 style="font-weight:900; font-family:montserrat; color:green; font-size:50px; padding:20px; text-align:left;"><i class="zmdi zmdi-folder-person"></i> Registro <span style="font-weight:700; color:black;">de Solicitud</span></h1>


    <?php if (!$mostrar_formulario): ?>
    <!-- Sección de Búsqueda -->
    <div class="section-container">
        <h3 class="section-title">Buscar Solicitante</h3>
        <form method="POST" action="" class="search-form">
            <div class="form-group">
                <label for="cedula_rif" class="form-label required" style="font-weight: 700; color:black;">Cédula o RIF del Solicitante</label>
                <input type="number" name="cedula_rif" id="cedula_rif" class="form-control" value="<?php echo htmlspecialchars($cedula_rif); ?>" placeholder="Ingrese CI o RIF..." required />
            </div>
            <div class="btn-container">
                <button type="submit" name="buscar" class="btn btn-primary"><i class="zmdi zmdi-search"></i> Buscar</button>
            </div>
        </form>

        <?php if ($mensaje_error): ?>
            <div class="alert alert-danger mt-3"><?php echo $mensaje_error; ?></div>
        <?php endif; ?>

        <?php if ($solicitante_encontrado): ?>
            <div class="results-section mt-4">
                <div class="results-title">Resultado de la Búsqueda</div>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Cédula/RIF</th>
                            <th>Nombre Completo</th>
                            <th>Tipo</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td data-label="Cédula/RIF"><?php echo $tipo_solicitante == 'N' ? $solicitante_encontrado['cedula'] : ($tipo_solicitante == 'J' ? $solicitante_encontrado['rif'] : $solicitante_encontrado['rif_o_ci_referente']); ?></td>
                            <td data-label="Nombre Completo"><?php echo $tipo_solicitante == 'N' ? ($solicitante_encontrado['primer_nombre'] . ' ' . ($solicitante_encontrado['segundo_nombre'] ?? '') . ' ' . $solicitante_encontrado['primer_apellido'] . ' ' . ($solicitante_encontrado['segundo_apellido'] ?? '')) : ($tipo_solicitante == 'J' ? $solicitante_encontrado['razon_social'] : $solicitante_encontrado['nombre_colectivo']); ?></td>
                            <td data-label="Tipo"><?php echo $tipo_solicitante == 'N' ? 'Persona Natural' : ($tipo_solicitante == 'J' ? 'Persona Jurídica' : 'Colectivo'); ?></td>
                            <td data-label="Teléfono"><?php echo $solicitante_encontrado['telefono']; ?></td>
                            <td data-label="Dirección"><?php echo $solicitante_encontrado['direccion_habitacion']; ?></td>
                            <td data-label="Acción">
                                <?php 
                                $_SESSION['temp_solicitante'] = $solicitante_encontrado;
                                $_SESSION['temp_tipo_solicitante'] = $tipo_solicitante;
                                ?>
                                <a href="?action=form" class="btn btn-primary">
                                    <i class="zmdi zmdi-plus"></i> Agregar Solicitud
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($mostrar_formulario && $solicitante_encontrado): ?>
    <!-- Formulario de Solicitud -->
    <div class="section-container">


        <h3 class="section-title" style="color:black;"> Nueva Solicitud para <span style="color:green;">"<?php echo $tipo_solicitante == 'N' ? $solicitante_encontrado['primer_nombre'] : ($tipo_solicitante == 'J' ? $solicitante_encontrado['razon_social'] : $solicitante_encontrado['nombre_colectivo']); ?>"</span></h3>
        
        <form method="POST" action="">
            <input type="hidden" name="guardar_solicitud" value="1">
            <input type="hidden" name="cedula_rif" value="<?php echo $tipo_solicitante == 'N' ? $solicitante_encontrado['cedula'] : ($tipo_solicitante == 'J' ? $solicitante_encontrado['rif'] : $solicitante_encontrado['rif_o_ci_referente']); ?>">
            <input type="hidden" name="tipo_solicitante" value="<?php echo $tipo_solicitante; ?>">

            <?php
                $sql_num = "SELECT MAX(id_solicitud) as max_id FROM solicitudes";
                $result_num = mysqli_query($conn, $sql_num);
                $row_num = mysqli_fetch_assoc($result_num);
                $next_id = ($row_num['max_id'] ?? 0) + 1;
                $numero_solicitud = "SOL-" . date('Y') . "-" . str_pad($next_id, 6, '0', STR_PAD_LEFT);
            ?>
            <div class="field-row">
                <div class="field-col">
                    <label class="field-label required">Número de Solicitud</label>
                    <input type="text" name="numero_solicitud" class="form-control" value="<?php echo $numero_solicitud; ?>" readonly />
                </div>
                <div class="field-col">
                    <label class="field-label required">Fecha de Solicitud</label>
                    <input type="date" name="fecha_solicitud" class="form-control" value="<?php echo date('Y-m-d'); ?>" required />
                </div>
            </div>

            <!-- Datos del Predio -->
            <div class="section-container" style="margin-top: 20px;">
                <h4 class="section-title">Datos del Predio</h4>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Nombre del Predio</label>
                        <input type="text" name="nombre_predio" class="form-control" placeholder="Ej: Finca La Esperanza" required />
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
                                while ($municipio = mysqli_fetch_assoc($result_municipios)) {
                                    echo "<option value='{$municipio['id_municipio']}'>{$municipio['nombre_municipio']}</option>";
                                }
                            ?>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Parroquia</label>
                        <select name="parroquia" id="parroquia" class="form-control" required onchange="cargarSectores()">
                            <option value="">Primero seleccione un Municipio</option>
                        </select>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Sector</label>
                        <select name="sector" id="sector" class="form-control" required>
                            <option value="">Primero seleccione una Parroquia</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Superficie Total (Ha)</label>
                        <input type="text" name="superficie_total" class="form-control" placeholder="Ej: 15.5" required />
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Linderos</label>
                        <textarea name="lindero_norte" class="form-control" rows="2" placeholder="Lindero Norte..." required></textarea>
                    </div>
                    <div class="field-col">
                        <label class="field-label">&nbsp;</label>
                        <textarea name="lindero_sur" class="form-control" rows="2" placeholder="Lindero Sur..." required></textarea>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <textarea name="lindero_este" class="form-control" rows="2" placeholder="Lindero Este..." required></textarea>
                    </div>
                    <div class="field-col">
                        <textarea name="lindero_oeste" class="form-control" rows="2" placeholder="Lindero Oeste..." required></textarea>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Dirección del Predio</label>
                        <textarea name="direccion_predio" class="form-control" rows="2" placeholder="Dirección completa del predio..." required></textarea>
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
                                while ($proc = mysqli_fetch_assoc($result_proc)) {
                                    echo "<option value='{$proc['id_procedimiento']}'>{$proc['nombre_procedimiento']}</option>";
                                }
                            ?>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Estatus Inicial</label>
                        <select name="estatus" class="form-control" required>
                            <option value="">Seleccionar...</option>
                            <option value="Por_Inspeccion">Por Inspección</option>
                            <option value="En_Ejecucion">En Ejecución</option>
                            <option value="En_INTI_Central">En INTI Central</option>
                            <option value="Aprobado">Aprobado</option>
                        </select>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Rubros a Producir</label>
                        <textarea name="rubros_producir" class="form-control" rows="3" placeholder="Ej: Maíz, Yuca..." required></textarea>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="button-container">
                <a href="?action=search" class="btn btn-secondary">
                    <i class="zmdi zmdi-arrow-left"></i> Volver
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="zmdi zmdi-save"></i> Guardar Solicitud y Predio
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

    function cargarParroquias() {
        var id_municipio = $('#municipio').val();
        if (id_municipio) {
            $.ajax({
                url: 'obtener_datos.php',
                type: 'POST',
                data: {tipo: 'parroquias', id_municipio: id_municipio},
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
                data: {tipo: 'sectores', id_parroquia: id_parroquia},
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