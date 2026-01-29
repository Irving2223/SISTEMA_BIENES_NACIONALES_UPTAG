<?php
include('header.php');
include('conexion.php');

// Inicializar variables de mensaje
$mensaje = '';
$tipo_mensaje = '';

// Variables para mantener el estado
$solicitante_seleccionado = null;
$id_solicitante = $_GET['id'] ?? null;
$tipo_solicitante = $_GET['tipo'] ?? null;
$agregar_integrante = isset($_GET['agregar_integrante']);
$editar_integrante = $_GET['editar_integrante'] ?? null;

// Procesar la edición del solicitante
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar'])) {
    try {
        $conn->begin_transaction();
        
        $accion = $_POST['accion'] ?? '';
        
        if ($accion == 'editar_natural') {
            $cedula = trim($_POST['cedula'] ?? '');
            $primer_nombre = trim($_POST['primer_nombre'] ?? '');
            $segundo_nombre = trim($_POST['segundo_nombre'] ?? '');
            $primer_apellido = trim($_POST['primer_apellido'] ?? '');
            $segundo_apellido = trim($_POST['segundo_apellido'] ?? '');
            $sexo = $_POST['sexo'] ?? '';
            $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
            $telefono = trim($_POST['telefono'] ?? '');
            $direccion_habitacion = trim($_POST['direccion_habitacion'] ?? '');
            $estado_civil = $_POST['estado_civil'] ?? '';
            $numero_hijos = $_POST['numero_hijos'] ?? '0';
            $grado_instruccion = $_POST['grado_instruccion'] ?? '';
            $sabe_leer = $_POST['sabe_leer'] ?? '';
            $posee_ayuda_economica = $_POST['posee_ayuda_economica'] ?? '';
            $trabaja_actualmente = $_POST['trabaja_actualmente'] ?? '';
            $pertenece_comuna = $_POST['pertenece_comuna'] ?? '';
            $enfermedades = trim($_POST['enfermedades'] ?? '');
            $activo = $_POST['activo'] ?? '1';

            // Validaciones básicas
            if (empty($cedula) || empty($primer_nombre) || empty($primer_apellido) || empty($sexo)) {
                throw new Exception("Los campos cédula, nombre, apellido y sexo son obligatorios.");
            }

            // Verificar si existe la persona natural
            $stmt_check = $conn->prepare("SELECT cedula FROM personas_naturales WHERE cedula = ?");
            $stmt_check->bind_param("s", $cedula);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows === 0) {
                throw new Exception("No se encontró la persona natural con la cédula $cedula.");
            }
            $stmt_check->close();

$fecha_nacimiento_val = $fecha_nacimiento ?: null;

$stmt = $conn->prepare("UPDATE personas_naturales
   SET primer_nombre=?, segundo_nombre=?, primer_apellido=?, segundo_apellido=?,
       sexo=?, fecha_nacimiento=?, telefono=?, direccion_habitacion=?,
       estado_civil=?, numero_hijos=?, grado_instruccion=?, sabe_leer=?,
       posee_ayuda_economica=?, trabaja_actualmente=?, pertenece_comuna=?, enfermedades=?, activo=?
   WHERE cedula=?");

$stmt->bind_param("ssssssssssssssssss",
    $primer_nombre,
    $segundo_nombre,
    $primer_apellido,
    $segundo_apellido,
    $sexo,
    $fecha_nacimiento_val,
    $telefono,
    $direccion_habitacion,
    $estado_civil,
    $numero_hijos,
    $grado_instruccion,
    $sabe_leer,
    $posee_ayuda_economica,
    $trabaja_actualmente,
    $pertenece_comuna,
    $enfermedades,
    $activo,
    $cedula
);

if (!$stmt->execute()) {
    throw new Exception("Error al actualizar: " . $stmt->error);
}

$stmt->close();

            
            // Registrar en bitácora
            $cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'SISTEMA';
            $stmt_bitacora = $conn->prepare("INSERT INTO bitacora(cedula_usuario, accion, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, 'Edición', 'personas_naturales', ?, ?, NOW())");
            $detalle = "Edición de persona natural: $primer_nombre $primer_apellido";
            $stmt_bitacora->bind_param("sss", $cedula_usuario, $cedula, $detalle);
            $stmt_bitacora->execute();
            $stmt_bitacora->close();
            
            $mensaje = "Se cambió la persona natural {$primer_nombre} {$primer_apellido} con cédula {$cedula}.";
            $tipo_mensaje = "success";
            
        } elseif ($accion == 'editar_juridica') {
            $rif = trim($_POST['rif'] ?? '');
            $razon_social = trim($_POST['razon_social'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $direccion_habitacion = trim($_POST['direccion_habitacion'] ?? '');
            $estado_civil = $_POST['estado_civil'] ?? '';
            $numero_hijos = $_POST['numero_hijos'] ?? '0';
            $grado_instruccion = $_POST['grado_instruccion'] ?? '';
            $sabe_leer = $_POST['sabe_leer'] ?? '';
            $posee_ayuda_economica = $_POST['posee_ayuda_economica'] ?? '';
            $trabaja_actualmente = $_POST['trabaja_actualmente'] ?? '';
            $pertenece_comuna = $_POST['pertenece_comuna'] ?? '';
            $enfermedades = trim($_POST['enfermedades'] ?? '');
            $activo = $_POST['activo'] ?? '1';

            // Validaciones básicas
            if (empty($rif) || empty($razon_social)) {
                throw new Exception("Los campos RIF y Razón Social son obligatorios para personas jurídicas.");
            }

            // Verificar si existe la persona jurídica
            $stmt_check = $conn->prepare("SELECT rif FROM personas_juridicas WHERE rif = ?");
            $stmt_check->bind_param("s", $rif);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows === 0) {
                throw new Exception("No se encontró la persona jurídica con el RIF $rif.");
            }
            $stmt_check->close();

            // Actualizar persona jurídica
            $stmt_pj = $conn->prepare("UPDATE personas_juridicas SET razon_social=?, telefono=?, direccion_habitacion=?, estado_civil=?, numero_hijos=?, grado_instruccion=?, sabe_leer=?, posee_ayuda_economica=?, trabaja_actualmente=?, pertenece_comuna=?, enfermedades=?, activo=? WHERE rif=?");
            $stmt_pj->bind_param("sssssssssssss",
                $razon_social,
                $telefono,
                $direccion_habitacion,
                $estado_civil,
                $numero_hijos,
                $grado_instruccion,
                $sabe_leer,
                $posee_ayuda_economica,
                $trabaja_actualmente,
                $pertenece_comuna,
                $enfermedades,
                $activo,
                $rif
            );
            
            if (!$stmt_pj->execute()) {
                throw new Exception("Error al actualizar la persona jurídica: " . $stmt_pj->error);
            }
            
            $stmt_pj->close();
            
            // Registrar en bitácora
            $cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'SISTEMA';
            $stmt_bitacora = $conn->prepare("INSERT INTO bitacora(cedula_usuario, accion, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, 'Edición', 'personas_juridicas', ?, ?, NOW())");
            $detalle = "Edición de persona jurídica: $razon_social";
            $stmt_bitacora->bind_param("sss", $cedula_usuario, $rif, $detalle);
            $stmt_bitacora->execute();
            $stmt_bitacora->close();
            
            $mensaje = "Se cambió la persona jurídica {$razon_social} con RIF {$rif}.";
            $tipo_mensaje = "success";
            
        } elseif ($accion == 'editar_colectivo') {
            $rif_referente = trim($_POST['rif_referente'] ?? '');
            $nombre_colectivo = trim($_POST['nombre_colectivo'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $direccion_habitacion = trim($_POST['direccion_habitacion'] ?? '');
            $numero_integrantes = $_POST['numero_integrantes'] ?? '0';
            $activo = $_POST['activo'] ?? '1';

            // Validaciones básicas
            if (empty($rif_referente) || empty($nombre_colectivo)) {
                throw new Exception("Los campos RIF/CI Referente y Nombre del Colectivo son obligatorios.");
            }

            // Verificar si existe el colectivo
            $stmt_check = $conn->prepare("SELECT rif_o_ci_referente FROM colectivos WHERE rif_o_ci_referente = ?");
            $stmt_check->bind_param("s", $rif_referente);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows === 0) {
                throw new Exception("No se encontró el colectivo con el RIF/CI $rif_referente.");
            }
            $stmt_check->close();

            // Actualizar colectivo
            $stmt_colectivo = $conn->prepare("UPDATE colectivos SET nombre_colectivo=?, telefono=?, direccion_habitacion=?, numero_integrantes=?, activo=? WHERE rif_o_ci_referente=?");
            $stmt_colectivo->bind_param("ssssss",
                $nombre_colectivo,
                $telefono,
                $direccion_habitacion,
                $numero_integrantes,
                $activo,
                $rif_referente
            );
            
            if (!$stmt_colectivo->execute()) {
                throw new Exception("Error al actualizar el colectivo: " . $stmt_colectivo->error);
            }
            
            $stmt_colectivo->close();
            
            // Registrar en bitácora
            $cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'SISTEMA';
            $stmt_bitacora = $conn->prepare("INSERT INTO bitacora(cedula_usuario, accion, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, 'Edición', 'colectivos', ?, ?, NOW())");
            $detalle = "Edición de colectivo: $nombre_colectivo";
            $stmt_bitacora->bind_param("sss", $cedula_usuario, $rif_referente, $detalle);
            $stmt_bitacora->execute();
            $stmt_bitacora->close();
            
            $mensaje = "Se cambió el colectivo {$nombre_colectivo} con RIF/CI {$rif_referente}.";
            $tipo_mensaje = "success";
            
        } elseif ($accion == 'editar_representante' || $accion == 'editar_apoderado') {
            $id_representante = (int)($_POST['id_representante'] ?? 0);
            $primer_nombre = trim($_POST['rep_primer_nombre'] ?? '');
            $segundo_nombre = trim($_POST['rep_segundo_nombre'] ?? '');
            $primer_apellido = trim($_POST['rep_primer_apellido'] ?? '');
            $segundo_apellido = trim($_POST['rep_segundo_apellido'] ?? '');
            $sexo = $_POST['rep_sexo'] ?? '';
            $telefono = trim($_POST['rep_telefono'] ?? '');
            $direccion = trim($_POST['rep_direccion'] ?? '');
            $email = trim($_POST['rep_email'] ?? '');
            $profesion = trim($_POST['rep_profesion'] ?? '');
            $activo = $_POST['rep_activo'] ?? '1';

            // Validaciones básicas
            if (empty($primer_nombre) || empty($primer_apellido) || empty($sexo)) {
                throw new Exception("Los campos Primer Nombre, Primer Apellido y Sexo son obligatorios para el representante.");
            }

            // Verificar si existe el representante
            $stmt_check = $conn->prepare("SELECT id_representante FROM representantes WHERE id_representante = ?");
            $stmt_check->bind_param("i", $id_representante);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows === 0) {
                throw new Exception("No se encontró el representante con ID $id_representante.");
            }
            $stmt_check->close();

            // Actualizar representante
            $tipo_representante = $accion == 'editar_apoderado' ? 'Apoderado' : 'Representante_Legal';

            $stmt_rep = $conn->prepare("UPDATE representantes SET primer_nombre=?, segundo_nombre=?, primer_apellido=?, segundo_apellido=?, sexo=?, telefono=?, direccion=?, email=?, profesion=?, tipo=?, activo=? WHERE id_representante=?");
            $stmt_rep->bind_param("sssssssssssi",
                $primer_nombre,
                $segundo_nombre,
                $primer_apellido,
                $segundo_apellido,
                $sexo,
                $telefono,
                $direccion,
                $email,
                $profesion,
                $tipo_representante,
                $activo,
                $id_representante
            );
            
            if (!$stmt_rep->execute()) {
                throw new Exception("Error al actualizar el representante: " . $stmt_rep->error);
            }
            
            $stmt_rep->close();
            
            // Registrar en bitácora
            $cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'SISTEMA';
            $stmt_bitacora = $conn->prepare("INSERT INTO bitacora(cedula_usuario, accion, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, 'Edición', 'representantes', ?, ?, NOW())");
            $detalle = "Edición de " . ($accion == 'editar_apoderado' ? 'apoderado' : 'representante legal') . ": $primer_nombre $primer_apellido";
            $stmt_bitacora->bind_param("sss", $cedula_usuario, $id_representante, $detalle);
            $stmt_bitacora->execute();
            $stmt_bitacora->close();
            
            $mensaje = "Se cambió el " . ($accion == 'editar_apoderado' ? "apoderado" : "representante legal") . " {$primer_nombre} {$primer_apellido}.";
            $tipo_mensaje = "success";

         } elseif ($accion == 'agregar_integrante') {
             $rif_colectivo = trim($_POST['rif_colectivo'] ?? '');
             $cedula = trim($_POST['cedula'] ?? '');
             $primer_nombre = trim($_POST['primer_nombre'] ?? '');
             $segundo_nombre = trim($_POST['segundo_nombre'] ?? '');
             $primer_apellido = trim($_POST['primer_apellido'] ?? '');
             $segundo_apellido = trim($_POST['segundo_apellido'] ?? '');
             $sexo = $_POST['sexo'] ?? '';
             $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
             $telefono = trim($_POST['telefono'] ?? '');
             $direccion_habitacion = trim($_POST['direccion_habitacion'] ?? '');
             $estado_civil = $_POST['estado_civil'] ?? '';
             $numero_hijos = $_POST['numero_hijos'] ?? '0';
             $grado_instruccion = $_POST['grado_instruccion'] ?? '';
             $sabe_leer = $_POST['sabe_leer'] ?? '';
             $posee_ayuda_economica = $_POST['posee_ayuda_economica'] ?? '';
             $trabaja_actualmente = $_POST['trabaja_actualmente'] ?? '';
             $pertenece_comuna = $_POST['pertenece_comuna'] ?? '';
             $enfermedades = trim($_POST['enfermedades'] ?? '');
             $cargo_en_colectivo = trim($_POST['cargo_en_colectivo'] ?? '');
             $fecha_ingreso = $_POST['fecha_ingreso'] ?? '';
             $es_referente = $_POST['es_referente'] ?? '0';

             // Validaciones
             if (empty($cedula) || empty($primer_nombre) || empty($primer_apellido) || empty($sexo) || empty($rif_colectivo)) {
                 throw new Exception("Campos obligatorios faltantes para el integrante.");
             }

             // Verificar si ya existe
             $stmt_check = $conn->prepare("SELECT cedula FROM colectivo_integrantes WHERE cedula = ? AND rif_o_ci_colectivo = ?");
             $stmt_check->bind_param("ss", $cedula, $rif_colectivo);
             $stmt_check->execute();
             if ($stmt_check->get_result()->num_rows > 0) {
                 throw new Exception("El integrante con cédula $cedula ya existe en este colectivo.");
             }
             $stmt_check->close();

             // Insertar
             $stmt_insert = $conn->prepare("INSERT INTO colectivo_integrantes(cedula, rif_o_ci_colectivo, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, sexo, fecha_nacimiento, telefono, direccion_habitacion, estado_civil, numero_hijos, grado_instruccion, sabe_leer, posee_ayuda_economica, trabaja_actualmente, pertenece_comuna, enfermedades, es_referente, cargo_en_colectivo, fecha_ingreso, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
             $stmt_insert->bind_param("sssssssssssssssssssss", $cedula, $rif_colectivo, $primer_nombre, $segundo_nombre, $primer_apellido, $segundo_apellido, $sexo, $fecha_nacimiento, $telefono, $direccion_habitacion, $estado_civil, $numero_hijos, $grado_instruccion, $sabe_leer, $posee_ayuda_economica, $trabaja_actualmente, $pertenece_comuna, $enfermedades, $es_referente, $cargo_en_colectivo, $fecha_ingreso);
             if (!$stmt_insert->execute()) {
                 throw new Exception("Error al añadir integrante: " . $stmt_insert->error);
             }
             $stmt_insert->close();

             // Bitácora
             $cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'SISTEMA';
             $stmt_bitacora = $conn->prepare("INSERT INTO bitacora(cedula_usuario, accion, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, 'Registro', 'colectivo_integrantes', ?, ?, NOW())");
             $detalle = "Registro de integrante: $primer_nombre $primer_apellido (Cédula: $cedula) en colectivo $rif_colectivo";
             $stmt_bitacora->bind_param("sss", $cedula_usuario, $cedula, $detalle);
             $stmt_bitacora->execute();
             $stmt_bitacora->close();

             $mensaje = "Integrante añadido correctamente.";
             $tipo_mensaje = "success";

         } elseif ($accion == 'editar_integrante') {
             $cedula = trim($_POST['cedula'] ?? '');
             $rif_colectivo = trim($_POST['rif_colectivo'] ?? '');
             $primer_nombre = trim($_POST['primer_nombre'] ?? '');
             $segundo_nombre = trim($_POST['segundo_nombre'] ?? '');
             $primer_apellido = trim($_POST['primer_apellido'] ?? '');
             $segundo_apellido = trim($_POST['segundo_apellido'] ?? '');
             $sexo = $_POST['sexo'] ?? '';
             $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
             $telefono = trim($_POST['telefono'] ?? '');
             $direccion_habitacion = trim($_POST['direccion_habitacion'] ?? '');
             $estado_civil = $_POST['estado_civil'] ?? '';
             $numero_hijos = $_POST['numero_hijos'] ?? '0';
             $grado_instruccion = $_POST['grado_instruccion'] ?? '';
             $sabe_leer = $_POST['sabe_leer'] ?? '';
             $posee_ayuda_economica = $_POST['posee_ayuda_economica'] ?? '';
             $trabaja_actualmente = $_POST['trabaja_actualmente'] ?? '';
             $pertenece_comuna = $_POST['pertenece_comuna'] ?? '';
             $enfermedades = trim($_POST['enfermedades'] ?? '');
             $cargo_en_colectivo = trim($_POST['cargo_en_colectivo'] ?? '');
             $fecha_ingreso = $_POST['fecha_ingreso'] ?? '';
             $es_referente = $_POST['es_referente'] ?? '0';
             $activo = $_POST['activo'] ?? '1';

             // Validaciones
             if (empty($cedula) || empty($primer_nombre) || empty($primer_apellido) || empty($sexo)) {
                 throw new Exception("Campos obligatorios faltantes para el integrante.");
             }

             // Actualizar
             $stmt_update = $conn->prepare("UPDATE colectivo_integrantes SET primer_nombre=?, segundo_nombre=?, primer_apellido=?, segundo_apellido=?, sexo=?, fecha_nacimiento=?, telefono=?, direccion_habitacion=?, estado_civil=?, numero_hijos=?, grado_instruccion=?, sabe_leer=?, posee_ayuda_economica=?, trabaja_actualmente=?, pertenece_comuna=?, enfermedades=?, es_referente=?, cargo_en_colectivo=?, fecha_ingreso=?, activo=? WHERE cedula=? AND rif_o_ci_colectivo=?");
             $stmt_update->bind_param("ssssssssssssssssssssss", $primer_nombre, $segundo_nombre, $primer_apellido, $segundo_apellido, $sexo, $fecha_nacimiento, $telefono, $direccion_habitacion, $estado_civil, $numero_hijos, $grado_instruccion, $sabe_leer, $posee_ayuda_economica, $trabaja_actualmente, $pertenece_comuna, $enfermedades, $es_referente, $cargo_en_colectivo, $fecha_ingreso, $activo, $cedula, $rif_colectivo);
             if (!$stmt_update->execute()) {
                 throw new Exception("Error al actualizar integrante: " . $stmt_update->error);
             }
             $stmt_update->close();

             // Bitácora
             $cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'SISTEMA';
             $stmt_bitacora = $conn->prepare("INSERT INTO bitacora(cedula_usuario, accion, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, 'Edición', 'colectivo_integrantes', ?, ?, NOW())");
             $detalle = "Edición de integrante: $primer_nombre $primer_apellido (Cédula: $cedula) en colectivo $rif_colectivo";
             $stmt_bitacora->bind_param("sss", $cedula_usuario, $cedula, $detalle);
             $stmt_bitacora->execute();
             $stmt_bitacora->close();

             $mensaje = "Integrante actualizado correctamente.";
             $tipo_mensaje = "success";

         }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Cargar datos del solicitante seleccionado
if ($id_solicitante && $tipo_solicitante) {
    try {
        switch ($tipo_solicitante) {
            case 'natural':
                $stmt = $conn->prepare("SELECT * FROM personas_naturales WHERE cedula = ?");
                $stmt->bind_param("s", $id_solicitante);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $solicitante_seleccionado = $result->fetch_assoc();
                    $solicitante_seleccionado['tipo'] = 'natural';
                }
                $stmt->close();
                break;
                
            case 'juridica':
                $stmt = $conn->prepare("SELECT * FROM personas_juridicas WHERE rif = ?");
                $stmt->bind_param("s", $id_solicitante);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $solicitante_seleccionado = $result->fetch_assoc();
                    $solicitante_seleccionado['tipo'] = 'juridica';
                }
                $stmt->close();
                break;
                
            case 'colectivo':
                $stmt = $conn->prepare("SELECT * FROM colectivos WHERE rif_o_ci_referente = ?");
                $stmt->bind_param("s", $id_solicitante);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $solicitante_seleccionado = $result->fetch_assoc();
                    $solicitante_seleccionado['tipo'] = 'colectivo';
                }
                $stmt->close();
                break;
                
            case 'representante':
                $stmt = $conn->prepare("SELECT * FROM representantes WHERE id_representante = ?");
                $stmt->bind_param("i", $id_solicitante);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $solicitante_seleccionado = $result->fetch_assoc();
                    $solicitante_seleccionado['tipo'] = 'representante';
                }
                $stmt->close();
                break;
                
            case 'apoderado':
                $stmt = $conn->prepare("SELECT * FROM representantes WHERE id_representante = ? AND tipo = 'Apoderado'");
                $stmt->bind_param("i", $id_solicitante);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $solicitante_seleccionado = $result->fetch_assoc();
                    $solicitante_seleccionado['tipo'] = 'apoderado';
                }
                $stmt->close();
                break;
        }
    } catch (Exception $e) {
        $mensaje = "Error al cargar los datos: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Cargar integrantes del colectivo si es colectivo
$integrantes = [];
$integrante_a_editar = null;
if ($solicitante_seleccionado && $solicitante_seleccionado['tipo'] == 'colectivo') {
    $stmt_integrantes = $conn->prepare("SELECT * FROM colectivo_integrantes WHERE rif_o_ci_colectivo = ? ORDER BY primer_nombre, primer_apellido");
    $stmt_integrantes->bind_param("s", $id_solicitante);
    $stmt_integrantes->execute();
    $result_integrantes = $stmt_integrantes->get_result();
    while ($row = $result_integrantes->fetch_assoc()) {
        $integrantes[] = $row;
    }
    $stmt_integrantes->close();

    // Cargar integrante a editar si se especifica
    if ($editar_integrante) {
        $stmt_edit = $conn->prepare("SELECT * FROM colectivo_integrantes WHERE cedula = ? AND rif_o_ci_colectivo = ?");
        $stmt_edit->bind_param("ss", $editar_integrante, $id_solicitante);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        if ($result_edit->num_rows > 0) {
            $integrante_a_editar = $result_edit->fetch_assoc();
        }
        $stmt_edit->close();
    }
}

// Cargar datos de la persona asociada si es apoderado o representante
$persona_asociada = null;
if ($solicitante_seleccionado && in_array($solicitante_seleccionado['tipo'], ['apoderado', 'representante'])) {
    $stmt_pn = $conn->prepare("SELECT * FROM personas_naturales WHERE id_representante = ?");
    $stmt_pn->bind_param("i", $id_solicitante);
    $stmt_pn->execute();
    $result_pn = $stmt_pn->get_result();
    if ($result_pn->num_rows > 0) {
        $persona_asociada = $result_pn->fetch_assoc();
        $persona_asociada['tipo'] = 'natural';
    }
    $stmt_pn->close();

    if (!$persona_asociada) {
        $stmt_pj = $conn->prepare("SELECT * FROM personas_juridicas WHERE id_representante = ?");
        $stmt_pj->bind_param("i", $id_solicitante);
        $stmt_pj->execute();
        $result_pj = $stmt_pj->get_result();
        if ($result_pj->num_rows > 0) {
            $persona_asociada = $result_pj->fetch_assoc();
            $persona_asociada['tipo'] = 'juridica';
        }
        $stmt_pj->close();
    }
}

// Si no se encontró el solicitante, mostrar mensaje informativo
if ($id_solicitante && $tipo_solicitante && !$solicitante_seleccionado) {
    $mensaje = "No se encontraron coincidencias para la búsqueda realizada.";
    $tipo_mensaje = "info";
}
?>

<div class="container">
    <h1 style="font-weight:900; font-family:montserrat; color:green; font-size:40px; padding:20px; text-align:left; font-size:50px;">
        <i class="zmdi zmdi-edit"></i> Edición de <span style="font-weight:700; color:black;">Solicitantes</span>
    </h1>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-<?= $tipo_mensaje == 'error' ? 'danger' : ($tipo_mensaje == 'info' ? 'warning' : 'success'); ?>">
            <?= htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <!-- Buscador de solicitantes -->
    <div class="section-container">
        <h2 class="section-title">Buscar Solicitante</h2>
        <form method="GET" action="" id="form-busqueda">
            <div class="field-row">
                <div class="field-col">
                    <label for="filtro_tipo" class="field-label required">Tipo de Solicitante *</label>
                    <select id="filtro_tipo" name="tipo" class="form-control" required>
                        <option value="">Seleccione un tipo...</option>
                        <option value="natural" <?= ($tipo_solicitante == 'natural') ? 'selected' : ''; ?>>Persona Natural</option>
                        <option value="juridica" <?= ($tipo_solicitante == 'juridica') ? 'selected' : ''; ?>>Persona Jurídica</option>
                        <option value="colectivo" <?= ($tipo_solicitante == 'colectivo') ? 'selected' : ''; ?>>Colectivo</option>
                        <option value="apoderado" <?= ($tipo_solicitante == 'apoderado') ? 'selected' : ''; ?>>Apoderado</option>
                        <option value="representante" <?= ($tipo_solicitante == 'representante') ? 'selected' : ''; ?>>Representante Legal</option>
                    </select>
                </div>
                <div class="field-col">
                    <label for="identificacion_busqueda" class="field-label required">Cédula/RIF *</label>
                    <input type="text" id="identificacion_busqueda" name="id" placeholder="Ingrese solo números" maxlength="10" class="form-control" pattern="\d+" title="Solo números" required value="<?= htmlspecialchars($id_solicitante); ?>" />
                </div>
                <div class="field-col" style="align-self: end;">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Resultados de búsqueda -->
    <?php if ($solicitante_seleccionado): ?>
        <div class="section-container">
            <h3 class="section-title">Editar <?= ucfirst($solicitante_seleccionado['tipo']); ?></h3>
            
            <?php if ($solicitante_seleccionado['tipo'] == 'natural'): ?>
                <form method="POST" action="" class="section-container">
                    <input type="hidden" name="accion" value="editar_natural">
                    <input type="hidden" name="editar" value="1">
                    <div class="field-row">
                        <div class="field-col">
                            <label for="cedula" class="field-label required">Cédula *</label>
                            <input type="text" name="cedula" id="cedula" value="<?= htmlspecialchars($solicitante_seleccionado['cedula']); ?>" maxlength="8" class="form-control" pattern="\d{7,8}" title="Solo números, 7 u 8 dígitos" required readonly />
                        </div>
                        <div class="field-col">
                            <label for="primer_nombre" class="field-label required">Primer Nombre *</label>
                            <input type="text" name="primer_nombre" id="primer_nombre" value="<?= htmlspecialchars($solicitante_seleccionado['primer_nombre']); ?>" placeholder="Ingrese primer nombre" class="form-control" required />
                        </div>
                        <div class="field-col">
                            <label for="segundo_nombre" class="field-label">Segundo Nombre</label>
                            <input type="text" name="segundo_nombre" id="segundo_nombre" value="<?= htmlspecialchars($solicitante_seleccionado['segundo_nombre'] ?? ''); ?>" placeholder="Ingrese segundo nombre" class="form-control" />
                        </div>
                        <div class="field-col">
                            <label for="primer_apellido" class="field-label required">Primer Apellido *</label>
                            <input type="text" name="primer_apellido" id="primer_apellido" value="<?= htmlspecialchars($solicitante_seleccionado['primer_apellido']); ?>" placeholder="Ingrese primer apellido" class="form-control" required />
                        </div>
                        <div class="field-col">
                            <label for="segundo_apellido" class="field-label">Segundo Apellido</label>
                            <input type="text" name="segundo_apellido" id="segundo_apellido" value="<?= htmlspecialchars($solicitante_seleccionado['segundo_apellido'] ?? ''); ?>" placeholder="Ingrese segundo apellido" class="form-control" />
                        </div>
                        <div class="field-col">
                            <label for="sexo" class="field-label required">Sexo *</label>
                            <select name="sexo" id="sexo" class="form-control" required>
                                <option value="">Seleccione...</option>
                                <option value="M" <?= ($solicitante_seleccionado['sexo'] == 'M') ? 'selected' : ''; ?>>Masculino</option>
                                <option value="F" <?= ($solicitante_seleccionado['sexo'] == 'F') ? 'selected' : ''; ?>>Femenino</option>
                            </select>
                        </div>
                        <div class="field-col">
                            <label for="fecha_nacimiento" class="field-label">Fecha de Nacimiento</label>
                            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" value="<?= htmlspecialchars($solicitante_seleccionado['fecha_nacimiento'] ?? ''); ?>" class="form-control" />
                        </div>
                        <div class="field-col">
                            <label for="telefono" class="field-label">Teléfono</label>
                            <input type="tel" name="telefono" id="telefono" value="<?= htmlspecialchars($solicitante_seleccionado['telefono'] ?? ''); ?>" placeholder="0412-1234567" class="form-control" />
                        </div>
                        <div class="field-col" style="grid-column: 1 / -1;">
                            <label for="direccion_habitacion" class="field-label">Dirección de Habitación</label>
                            <textarea name="direccion_habitacion" id="direccion_habitacion" rows="2" class="form-control" placeholder="Ingrese dirección completa"><?= htmlspecialchars($solicitante_seleccionado['direccion_habitacion'] ?? ''); ?></textarea>
                        </div>
                        <div class="field-col">
                            <label for="estado_civil" class="field-label">Estado Civil</label>
                            <select name="estado_civil" id="estado_civil" class="form-control">
                                <option value="">Seleccione...</option>
                                <option value="Soltero" <?= ($solicitante_seleccionado['estado_civil'] == 'Soltero') ? 'selected' : ''; ?>>Soltero</option>
                                <option value="Casado" <?= ($solicitante_seleccionado['estado_civil'] == 'Casado') ? 'selected' : ''; ?>>Casado</option>
                                <option value="Viudo" <?= ($solicitante_seleccionado['estado_civil'] == 'Viudo') ? 'selected' : ''; ?>>Viudo</option>
                                <option value="Divorciado" <?= ($solicitante_seleccionado['estado_civil'] == 'Divorciado') ? 'selected' : ''; ?>>Divorciado</option>
                                <option value="Concubinato" <?= ($solicitante_seleccionado['estado_civil'] == 'Concubinato') ? 'selected' : ''; ?>>Concubinato</option>
                            </select>
                        </div>
                        <div class="field-col">
                            <label for="numero_hijos" class="field-label">Número de Hijos</label>
                            <input type="number" name="numero_hijos" id="numero_hijos" min="0" value="<?= htmlspecialchars($solicitante_seleccionado['numero_hijos'] ?? 0); ?>" class="form-control" />
                        </div>
                        <div class="field-col">
                            <label for="grado_instruccion" class="field-label">Grado de Instrucción</label>
                            <select name="grado_instruccion" id="grado_instruccion" class="form-control">
                                <option value="">Seleccione...</option>
                                <option value="Sin_nivel" <?= ($solicitante_seleccionado['grado_instruccion'] == 'Sin_nivel') ? 'selected' : ''; ?>>Sin nivel</option>
                                <option value="Primaria" <?= ($solicitante_seleccionado['grado_instruccion'] == 'Primaria') ? 'selected' : ''; ?>>Primaria</option>
                                <option value="Secundaria" <?= ($solicitante_seleccionado['grado_instruccion'] == 'Secundaria') ? 'selected' : ''; ?>>Secundaria</option>
                                <option value="Tecnico" <?= ($solicitante_seleccionado['grado_instruccion'] == 'Tecnico') ? 'selected' : ''; ?>>Técnico</option>
                                <option value="Universitario" <?= ($solicitante_seleccionado['grado_instruccion'] == 'Universitario') ? 'selected' : ''; ?>>Universitario</option>
                                <option value="Postgrado" <?= ($solicitante_seleccionado['grado_instruccion'] == 'Postgrado') ? 'selected' : ''; ?>>Postgrado</option>
                                <option value="Otro" <?= ($solicitante_seleccionado['grado_instruccion'] == 'Otro') ? 'selected' : ''; ?>>Otro</option>
                            </select>
                        </div>
                        <div class="field-col">
                            <label for="sabe_leer" class="field-label">Sabe Leer</label>
                            <select name="sabe_leer" id="sabe_leer" class="form-control">
                                <option value="">Seleccione...</option>
                                <option value="Si" <?= ($solicitante_seleccionado['sabe_leer'] == 'Si') ? 'selected' : ''; ?>>Sí</option>
                                <option value="No" <?= ($solicitante_seleccionado['sabe_leer'] == 'No') ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="field-col">
                            <label for="posee_ayuda_economica" class="field-label">Posee Ayuda Económica</label>
                            <select name="posee_ayuda_economica" id="posee_ayuda_economica" class="form-control">
                                <option value="">Seleccione...</option>
                                <option value="Si" <?= ($solicitante_seleccionado['posee_ayuda_economica'] == 'Si') ? 'selected' : ''; ?>>Sí</option>
                                <option value="No" <?= ($solicitante_seleccionado['posee_ayuda_economica'] == 'No') ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="field-col">
                            <label for="trabaja_actualmente" class="field-label">Trabaja Actualmente</label>
                            <select name="trabaja_actualmente" id="trabaja_actualmente" class="form-control">
                                <option value="">Seleccione...</option>
                                <option value="Si" <?= ($solicitante_seleccionado['trabaja_actualmente'] == 'Si') ? 'selected' : ''; ?>>Sí</option>
                                <option value="No" <?= ($solicitante_seleccionado['trabaja_actualmente'] == 'No') ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="field-col">
                            <label for="pertenece_comuna" class="field-label">Pertenece a Comuna</label>
                            <select name="pertenece_comuna" id="pertenece_comuna" class="form-control">
                                <option value="">Seleccione...</option>
                                <option value="Si" <?= ($solicitante_seleccionado['pertenece_comuna'] == 'Si') ? 'selected' : ''; ?>>Sí</option>
                                <option value="No" <?= ($solicitante_seleccionado['pertenece_comuna'] == 'No') ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="field-col" style="grid-column: 1 / -1;">
                            <label for="enfermedades" class="field-label">Enfermedades</label>
                            <textarea name="enfermedades" id="enfermedades" rows="2" class="form-control" placeholder="Liste las enfermedades que padece"><?= htmlspecialchars($solicitante_seleccionado['enfermedades'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="button-container">
                        <button type="submit" class="btn btn-primary">Actualizar Persona Natural</button>
                        <a href="editar_solicitante.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
                
            <?php elseif ($solicitante_seleccionado['tipo'] == 'juridica'): ?>
                <form method="POST" action="" class="section-container">
                    <input type="hidden" name="accion" value="editar_juridica">
                    <input type="hidden" name="editar" value="1">
                    <div class="field-row">
                        <div class="field-col">
                            <label for="rif" class="field-label required">RIF *</label>
                            <input type="text" name="rif" id="rif" value="<?= htmlspecialchars($solicitante_seleccionado['rif']); ?>" maxlength="10" class="form-control" pattern="\d+" title="Solo números" required readonly />
                        </div>
                        <div class="field-col">
                            <label for="razon_social" class="field-label required">Razón Social *</label>
                            <input type="text" name="razon_social" id="razon_social" value="<?= htmlspecialchars($solicitante_seleccionado['razon_social']); ?>" placeholder="Ingrese razón social" class="form-control" required />
                        </div>
                        <div class="field-col">
                            <label for="telefono_juridica" class="field-label">Teléfono</label>
                            <input type="tel" name="telefono" id="telefono_juridica" value="<?= htmlspecialchars($solicitante_seleccionado['telefono'] ?? ''); ?>" placeholder="0412-1234567" class="form-control" />
                        </div>
                        <div class="field-col" style="grid-column: 1 / -1;">
                            <label for="direccion_habitacion_juridica" class="field-label">Dirección de Habitación</label>
                            <textarea name="direccion_habitacion" id="direccion_habitacion_juridica" rows="2" class="form-control" placeholder="Ingrese dirección completa"><?= htmlspecialchars($solicitante_seleccionado['direccion_habitacion'] ?? ''); ?></textarea>
                        </div>
                        <div class="field-col">
                            <label for="estado_civil_juridica" class="field-label">Estado Civil</label>
                            <select name="estado_civil" id="estado_civil_juridica" class="form-control">
                                <option value="">Seleccione...</option>
                                <option value="Soltero" <?= ($solicitante_seleccionado['estado_civil'] == 'Soltero') ? 'selected' : ''; ?>>Soltero</option>
                                <option value="Casado" <?= ($solicitante_seleccionado['estado_civil'] == 'Casado') ? 'selected' : ''; ?>>Casado</option>
                                <option value="Viudo" <?= ($solicitante_seleccionado['estado_civil'] == 'Viudo') ? 'selected' : ''; ?>>Viudo</option>
                                <option value="Divorciado" <?= ($solicitante_seleccionado['estado_civil'] == 'Divorciado') ? 'selected' : ''; ?>>Divorciado</option>
                                <option value="Concubinato" <?= ($solicitante_seleccionado['estado_civil'] == 'Concubinato') ? 'selected' : ''; ?>>Concubinato</option>
                            </select>
                        </div>
                        <div class="field-col">
                            <label for="numero_hijos_juridica" class="field-label">Número de Hijos</label>
                            <input type="number" name="numero_hijos" id="numero_hijos_juridica" min="0" value="<?= htmlspecialchars($solicitante_seleccionado['numero_hijos'] ?? 0); ?>" class="form-control" />
                        </div>
                        <div class="field-col">
                            <label for="grado_instruccion_juridica" class="field-label">Grado de Instrucción</label>
                            <select name="grado_instruccion" id="grado_instruccion_juridica" class="form-control">
                                <option value="">Seleccione...</option>
                                <option value="Sin_nivel" <?= ($solicitante_seleccionado['grado_instruccion'] == 'Sin_nivel') ? 'selected' : ''; ?>>Sin nivel</option>
                                <option value="Primaria" <?= ($solicitante_seleccionado['grado_instruccion'] == 'Primaria') ? 'selected' : ''; ?>>Primaria</option>
                                <option value="Secundaria" <?= ($solicitante_seleccionado['grado_instruccion'] == 'Secundaria') ? 'selected' : ''; ?>>Secundaria</option>
                                <option value="Tecnico" <?= ($solicitante_seleccionado['grado_instruccion'] == 'Tecnico') ? 'selected' : ''; ?>>Técnico</option>
                                <option value="Universitario" <?= ($solicitante_seleccionado['grado_instruccion'] == 'Universitario') ? 'selected' : ''; ?>>Universitario</option>
                                <option value="Postgrado" <?= ($solicitante_seleccionado['grado_instruccion'] == 'Postgrado') ? 'selected' : ''; ?>>Postgrado</option>
                                <option value="Otro" <?= ($solicitante_seleccionado['grado_instruccion'] == 'Otro') ? 'selected' : ''; ?>>Otro</option>
                            </select>
                        </div>
                        <div class="field-col">
                            <label for="sabe_leer_juridica" class="field-label">Sabe Leer</label>
                            <select name="sabe_leer" id="sabe_leer_juridica" class="form-control">
                                <option value="">Seleccione...</option>
                                <option value="Si" <?= ($solicitante_seleccionado['sabe_leer'] == 'Si') ? 'selected' : ''; ?>>Sí</option>
                                <option value="No" <?= ($solicitante_seleccionado['sabe_leer'] == 'No') ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="field-col">
                            <label for="posee_ayuda_economica_juridica" class="field-label">Posee Ayuda Económica</label>
                            <select name="posee_ayuda_economica" id="posee_ayuda_economica_juridica" class="form-control">
                                <option value="">Seleccione...</option>
                                <option value="Si" <?= ($solicitante_seleccionado['posee_ayuda_economica'] == 'Si') ? 'selected' : ''; ?>>Sí</option>
                                <option value="No" <?= ($solicitante_seleccionado['posee_ayuda_economica'] == 'No') ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="field-col">
                            <label for="trabaja_actualmente_juridica" class="field-label">Trabaja Actualmente</label>
                            <select name="trabaja_actualmente" id="trabaja_actualmente_juridica" class="form-control">
                                <option value="">Seleccione...</option>
                                <option value="Si" <?= ($solicitante_seleccionado['trabaja_actualmente'] == 'Si') ? 'selected' : ''; ?>>Sí</option>
                                <option value="No" <?= ($solicitante_seleccionado['trabaja_actualmente'] == 'No') ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="field-col">
                            <label for="pertenece_comuna_juridica" class="field-label">Pertenece a Comuna</label>
                            <select name="pertenece_comuna" id="pertenece_comuna_juridica" class="form-control">
                                <option value="">Seleccione...</option>
                                <option value="Si" <?= ($solicitante_seleccionado['pertenece_comuna'] == 'Si') ? 'selected' : ''; ?>>Sí</option>
                                <option value="No" <?= ($solicitante_seleccionado['pertenece_comuna'] == 'No') ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="field-col" style="grid-column: 1 / -1;">
                            <label for="enfermedades_juridica" class="field-label">Enfermedades</label>
                            <textarea name="enfermedades" id="enfermedades_juridica" rows="2" class="form-control" placeholder="Liste las enfermedades que padece"><?= htmlspecialchars($solicitante_seleccionado['enfermedades'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="button-container">
                        <button type="submit" class="btn btn-primary">Actualizar Persona Jurídica</button>
                        <a href="editar_solicitante.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
                
            <?php elseif ($solicitante_seleccionado['tipo'] == 'colectivo'): ?>
                <form method="POST" action="" class="section-container">
                    <input type="hidden" name="accion" value="editar_colectivo">
                    <input type="hidden" name="editar" value="1">
                    <div class="field-row">
                        <div class="field-col">
                            <label for="rif_referente" class="field-label required">RIF/CI Referente *</label>
                            <input type="text" name="rif_referente" id="rif_referente" value="<?= htmlspecialchars($solicitante_seleccionado['rif_o_ci_referente']); ?>" maxlength="10" class="form-control" pattern="\d+" title="Solo números" required readonly />
                        </div>
                        <div class="field-col">
                            <label for="nombre_colectivo" class="field-label required">Nombre del Colectivo *</label>
                            <input type="text" name="nombre_colectivo" id="nombre_colectivo" value="<?= htmlspecialchars($solicitante_seleccionado['nombre_colectivo']); ?>" placeholder="Ingrese nombre del colectivo" class="form-control" required />
                        </div>
                        <div class="field-col">
                            <label for="telefono_colectivo" class="field-label">Teléfono</label>
                            <input type="tel" name="telefono" id="telefono_colectivo" value="<?= htmlspecialchars($solicitante_seleccionado['telefono'] ?? ''); ?>" placeholder="0412-1234567" class="form-control" />
                        </div>
                        <div class="field-col" style="grid-column: 1 / -1;">
                            <label for="direccion_habitacion_colectivo" class="field-label">Dirección de Habitación</label>
                            <textarea name="direccion_habitacion" id="direccion_habitacion_colectivo" rows="2" class="form-control" placeholder="Ingrese dirección completa"><?= htmlspecialchars($solicitante_seleccionado['direccion_habitacion'] ?? ''); ?></textarea>
                        </div>
                        <div class="field-col">
                            <label for="numero_integrantes" class="field-label">Número de Integrantes</label>
                            <input type="number" name="numero_integrantes" id="numero_integrantes" min="1" value="<?= htmlspecialchars($solicitante_seleccionado['numero_integrantes'] ?? 1); ?>" class="form-control" required />
                        </div>
                        <div class="field-col">
                            <label for="activo_colectivo" class="field-label">Activo</label>
                            <select name="activo" id="activo_colectivo" class="form-control">
                                <option value="1" <?= ($solicitante_seleccionado['activo'] == 1) ? 'selected' : ''; ?>>Sí</option>
                                <option value="0" <?= ($solicitante_seleccionado['activo'] == 0) ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                    </div>
                    <div class="button-container">
                        <button type="submit" class="btn btn-primary">Actualizar Colectivo</button>
                        <a href="editar_solicitante.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>

                <!-- Integrantes del Colectivo -->
                <div class="section-container" style="margin-top: 20px;">
                    <h3 class="section-title">Integrantes del Colectivo</h3>
                    <a href="?id=<?= htmlspecialchars($id_solicitante); ?>&tipo=colectivo&agregar_integrante=1" class="btn btn-primary">Añadir Integrante</a>
                    <?php if (!empty($integrantes)): ?>
                        <table class="table table-striped" style="margin-top: 20px;">
                            <thead>
                                <tr>
                                    <th>Cédula</th>
                                    <th>Nombre Completo</th>
                                    <th>Sexo</th>
                                    <th>Teléfono</th>
                                    <th>Cargo</th>
                                    <th>Activo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($integrantes as $integrante): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($integrante['cedula']); ?></td>
                                        <td><?= htmlspecialchars($integrante['primer_nombre'] . ' ' . ($integrante['segundo_nombre'] ?? '') . ' ' . $integrante['primer_apellido'] . ' ' . ($integrante['segundo_apellido'] ?? '')); ?></td>
                                        <td><?= htmlspecialchars($integrante['sexo']); ?></td>
                                        <td><?= htmlspecialchars($integrante['telefono'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars($integrante['cargo_en_colectivo'] ?? 'N/A'); ?></td>
                                        <td><?= $integrante['activo'] ? 'Sí' : 'No'; ?></td>
                                        <td>
                                            <a href="?id=<?= htmlspecialchars($id_solicitante); ?>&tipo=colectivo&editar_integrante=<?= $integrante['cedula']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No hay integrantes registrados para este colectivo.</p>
                    <?php endif; ?>
                </div>

                <!-- Formulario para Añadir Integrante -->
                <?php if ($agregar_integrante): ?>
                    <div class="section-container" style="margin-top: 20px;">
                        <h3 class="section-title">Añadir Integrante al Colectivo</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="accion" value="agregar_integrante">
                            <input type="hidden" name="editar" value="1">
                            <input type="hidden" name="rif_colectivo" value="<?= htmlspecialchars($solicitante_seleccionado['rif_o_ci_referente']); ?>">
                            <div class="field-row">
                                <div class="field-col">
                                    <label for="int_cedula" class="field-label required">Cédula *</label>
                                    <input type="text" name="cedula" id="int_cedula" maxlength="8" class="form-control" pattern="\d{7,8}" title="Solo números, 7 u 8 dígitos" required />
                                </div>
                                <div class="field-col">
                                    <label for="int_primer_nombre" class="field-label required">Primer Nombre *</label>
                                    <input type="text" name="primer_nombre" id="int_primer_nombre" class="form-control" required />
                                </div>
                                <div class="field-col">
                                    <label for="int_segundo_nombre" class="field-label">Segundo Nombre</label>
                                    <input type="text" name="segundo_nombre" id="int_segundo_nombre" class="form-control" />
                                </div>
                                <div class="field-col">
                                    <label for="int_primer_apellido" class="field-label required">Primer Apellido *</label>
                                    <input type="text" name="primer_apellido" id="int_primer_apellido" class="form-control" required />
                                </div>
                                <div class="field-col">
                                    <label for="int_segundo_apellido" class="field-label">Segundo Apellido</label>
                                    <input type="text" name="segundo_apellido" id="int_segundo_apellido" class="form-control" />
                                </div>
                                <div class="field-col">
                                    <label for="int_sexo" class="field-label required">Sexo *</label>
                                    <select name="sexo" id="int_sexo" class="form-control" required>
                                        <option value="">Seleccione...</option>
                                        <option value="M">Masculino</option>
                                        <option value="F">Femenino</option>
                                    </select>
                                </div>
                                <div class="field-col">
                                    <label for="int_fecha_nacimiento" class="field-label">Fecha de Nacimiento</label>
                                    <input type="date" name="fecha_nacimiento" id="int_fecha_nacimiento" class="form-control" />
                                </div>
                                <div class="field-col">
                                    <label for="int_telefono" class="field-label">Teléfono</label>
                                    <input type="tel" name="telefono" id="int_telefono" class="form-control" />
                                </div>
                                <div class="field-col" style="grid-column: 1 / -1;">
                                    <label for="int_direccion" class="field-label">Dirección de Habitación</label>
                                    <textarea name="direccion_habitacion" id="int_direccion" rows="2" class="form-control"></textarea>
                                </div>
                                <div class="field-col">
                                    <label for="int_estado_civil" class="field-label">Estado Civil</label>
                                    <select name="estado_civil" id="int_estado_civil" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="Soltero">Soltero</option>
                                        <option value="Casado">Casado</option>
                                        <option value="Viudo">Viudo</option>
                                        <option value="Divorciado">Divorciado</option>
                                        <option value="Concubinato">Concubinato</option>
                                    </select>
                                </div>
                                <div class="field-col">
                                    <label for="int_numero_hijos" class="field-label">Número de Hijos</label>
                                    <input type="number" name="numero_hijos" id="int_numero_hijos" min="0" class="form-control" />
                                </div>
                                <div class="field-col">
                                    <label for="int_grado_instruccion" class="field-label">Grado de Instrucción</label>
                                    <select name="grado_instruccion" id="int_grado_instruccion" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="Sin_nivel">Sin nivel</option>
                                        <option value="Primaria">Primaria</option>
                                        <option value="Secundaria">Secundaria</option>
                                        <option value="Tecnico">Técnico</option>
                                        <option value="Universitario">Universitario</option>
                                        <option value="Postgrado">Postgrado</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                                <div class="field-col">
                                    <label for="int_sabe_leer" class="field-label">Sabe Leer</label>
                                    <select name="sabe_leer" id="int_sabe_leer" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="Si">Sí</option>
                                        <option value="No">No</option>
                                    </select>
                                </div>
                                <div class="field-col">
                                    <label for="int_posee_ayuda_economica" class="field-label">Posee Ayuda Económica</label>
                                    <select name="posee_ayuda_economica" id="int_posee_ayuda_economica" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="Si">Sí</option>
                                        <option value="No">No</option>
                                    </select>
                                </div>
                                <div class="field-col">
                                    <label for="int_trabaja_actualmente" class="field-label">Trabaja Actualmente</label>
                                    <select name="trabaja_actualmente" id="int_trabaja_actualmente" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="Si">Sí</option>
                                        <option value="No">No</option>
                                    </select>
                                </div>
                                <div class="field-col">
                                    <label for="int_pertenece_comuna" class="field-label">Pertenece a Comuna</label>
                                    <select name="pertenece_comuna" id="int_pertenece_comuna" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="Si">Sí</option>
                                        <option value="No">No</option>
                                    </select>
                                </div>
                                <div class="field-col" style="grid-column: 1 / -1;">
                                    <label for="int_enfermedades" class="field-label">Enfermedades</label>
                                    <textarea name="enfermedades" id="int_enfermedades" rows="2" class="form-control"></textarea>
                                </div>
                                <div class="field-col">
                                    <label for="int_cargo" class="field-label">Cargo en el Colectivo</label>
                                    <input type="text" name="cargo_en_colectivo" id="int_cargo" class="form-control" />
                                </div>
                                <div class="field-col">
                                    <label for="int_fecha_ingreso" class="field-label">Fecha de Ingreso</label>
                                    <input type="date" name="fecha_ingreso" id="int_fecha_ingreso" class="form-control" />
                                </div>
                                <div class="field-col">
                                    <label for="int_es_referente" class="field-label">Es Referente</label>
                                    <select name="es_referente" id="int_es_referente" class="form-control">
                                        <option value="0">No</option>
                                        <option value="1">Sí</option>
                                    </select>
                                </div>
                            </div>
                            <div class="button-container">
                                <button type="submit" class="btn btn-primary">Añadir Integrante</button>
                                <a href="?id=<?= htmlspecialchars($id_solicitante); ?>&tipo=colectivo" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Formulario para Editar Integrante -->
                <?php if ($integrante_a_editar): ?>
                    <div class="section-container" style="margin-top: 20px;">
                        <h3 class="section-title">Editar Integrante del Colectivo</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="accion" value="editar_integrante">
                            <input type="hidden" name="editar" value="1">
                            <input type="hidden" name="cedula" value="<?= htmlspecialchars($integrante_a_editar['cedula']); ?>">
                            <input type="hidden" name="rif_colectivo" value="<?= htmlspecialchars($solicitante_seleccionado['rif_o_ci_referente']); ?>">
                            <div class="field-row">
                                <div class="field-col">
                                    <label for="edit_primer_nombre" class="field-label required">Primer Nombre *</label>
                                    <input type="text" name="primer_nombre" id="edit_primer_nombre" value="<?= htmlspecialchars($integrante_a_editar['primer_nombre']); ?>" class="form-control" required />
                                </div>
                                <div class="field-col">
                                    <label for="edit_segundo_nombre" class="field-label">Segundo Nombre</label>
                                    <input type="text" name="segundo_nombre" id="edit_segundo_nombre" value="<?= htmlspecialchars($integrante_a_editar['segundo_nombre'] ?? ''); ?>" class="form-control" />
                                </div>
                                <div class="field-col">
                                    <label for="edit_primer_apellido" class="field-label required">Primer Apellido *</label>
                                    <input type="text" name="primer_apellido" id="edit_primer_apellido" value="<?= htmlspecialchars($integrante_a_editar['primer_apellido']); ?>" class="form-control" required />
                                </div>
                                <div class="field-col">
                                    <label for="edit_segundo_apellido" class="field-label">Segundo Apellido</label>
                                    <input type="text" name="segundo_apellido" id="edit_segundo_apellido" value="<?= htmlspecialchars($integrante_a_editar['segundo_apellido'] ?? ''); ?>" class="form-control" />
                                </div>
                                <div class="field-col">
                                    <label for="edit_sexo" class="field-label required">Sexo *</label>
                                    <select name="sexo" id="edit_sexo" class="form-control" required>
                                        <option value="M" <?= ($integrante_a_editar['sexo'] == 'M') ? 'selected' : ''; ?>>Masculino</option>
                                        <option value="F" <?= ($integrante_a_editar['sexo'] == 'F') ? 'selected' : ''; ?>>Femenino</option>
                                    </select>
                                </div>
                                <div class="field-col">
                                    <label for="edit_fecha_nacimiento" class="field-label">Fecha de Nacimiento</label>
                                    <input type="date" name="fecha_nacimiento" id="edit_fecha_nacimiento" value="<?= htmlspecialchars($integrante_a_editar['fecha_nacimiento'] ?? ''); ?>" class="form-control" />
                                </div>
                                <div class="field-col">
                                    <label for="edit_telefono" class="field-label">Teléfono</label>
                                    <input type="tel" name="telefono" id="edit_telefono" value="<?= htmlspecialchars($integrante_a_editar['telefono'] ?? ''); ?>" class="form-control" />
                                </div>
                                <div class="field-col" style="grid-column: 1 / -1;">
                                    <label for="edit_direccion" class="field-label">Dirección de Habitación</label>
                                    <textarea name="direccion_habitacion" id="edit_direccion" rows="2" class="form-control"><?= htmlspecialchars($integrante_a_editar['direccion_habitacion'] ?? ''); ?></textarea>
                                </div>
                                <div class="field-col">
                                    <label for="edit_estado_civil" class="field-label">Estado Civil</label>
                                    <select name="estado_civil" id="edit_estado_civil" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="Soltero" <?= ($integrante_a_editar['estado_civil'] == 'Soltero') ? 'selected' : ''; ?>>Soltero</option>
                                        <option value="Casado" <?= ($integrante_a_editar['estado_civil'] == 'Casado') ? 'selected' : ''; ?>>Casado</option>
                                        <option value="Viudo" <?= ($integrante_a_editar['estado_civil'] == 'Viudo') ? 'selected' : ''; ?>>Viudo</option>
                                        <option value="Divorciado" <?= ($integrante_a_editar['estado_civil'] == 'Divorciado') ? 'selected' : ''; ?>>Divorciado</option>
                                        <option value="Concubinato" <?= ($integrante_a_editar['estado_civil'] == 'Concubinato') ? 'selected' : ''; ?>>Concubinato</option>
                                    </select>
                                </div>
                                <div class="field-col">
                                    <label for="edit_numero_hijos" class="field-label">Número de Hijos</label>
                                    <input type="number" name="numero_hijos" id="edit_numero_hijos" min="0" value="<?= htmlspecialchars($integrante_a_editar['numero_hijos'] ?? 0); ?>" class="form-control" />
                                </div>
                                <div class="field-col">
                                    <label for="edit_grado_instruccion" class="field-label">Grado de Instrucción</label>
                                    <select name="grado_instruccion" id="edit_grado_instruccion" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="Sin_nivel" <?= ($integrante_a_editar['grado_instruccion'] == 'Sin_nivel') ? 'selected' : ''; ?>>Sin nivel</option>
                                        <option value="Primaria" <?= ($integrante_a_editar['grado_instruccion'] == 'Primaria') ? 'selected' : ''; ?>>Primaria</option>
                                        <option value="Secundaria" <?= ($integrante_a_editar['grado_instruccion'] == 'Secundaria') ? 'selected' : ''; ?>>Secundaria</option>
                                        <option value="Tecnico" <?= ($integrante_a_editar['grado_instruccion'] == 'Tecnico') ? 'selected' : ''; ?>>Técnico</option>
                                        <option value="Universitario" <?= ($integrante_a_editar['grado_instruccion'] == 'Universitario') ? 'selected' : ''; ?>>Universitario</option>
                                        <option value="Postgrado" <?= ($integrante_a_editar['grado_instruccion'] == 'Postgrado') ? 'selected' : ''; ?>>Postgrado</option>
                                        <option value="Otro" <?= ($integrante_a_editar['grado_instruccion'] == 'Otro') ? 'selected' : ''; ?>>Otro</option>
                                    </select>
                                </div>
                                <div class="field-col">
                                    <label for="edit_sabe_leer" class="field-label">Sabe Leer</label>
                                    <select name="sabe_leer" id="edit_sabe_leer" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="Si" <?= ($integrante_a_editar['sabe_leer'] == 'Si') ? 'selected' : ''; ?>>Sí</option>
                                        <option value="No" <?= ($integrante_a_editar['sabe_leer'] == 'No') ? 'selected' : ''; ?>>No</option>
                                    </select>
                                </div>
                                <div class="field-col">
                                    <label for="edit_posee_ayuda_economica" class="field-label">Posee Ayuda Económica</label>
                                    <select name="posee_ayuda_economica" id="edit_posee_ayuda_economica" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="Si" <?= ($integrante_a_editar['posee_ayuda_economica'] == 'Si') ? 'selected' : ''; ?>>Sí</option>
                                        <option value="No" <?= ($integrante_a_editar['posee_ayuda_economica'] == 'No') ? 'selected' : ''; ?>>No</option>
                                    </select>
                                </div>
                                <div class="field-col">
                                    <label for="edit_trabaja_actualmente" class="field-label">Trabaja Actualmente</label>
                                    <select name="trabaja_actualmente" id="edit_trabaja_actualmente" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="Si" <?= ($integrante_a_editar['trabaja_actualmente'] == 'Si') ? 'selected' : ''; ?>>Sí</option>
                                        <option value="No" <?= ($integrante_a_editar['trabaja_actualmente'] == 'No') ? 'selected' : ''; ?>>No</option>
                                    </select>
                                </div>
                                <div class="field-col">
                                    <label for="edit_pertenece_comuna" class="field-label">Pertenece a Comuna</label>
                                    <select name="pertenece_comuna" id="edit_pertenece_comuna" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="Si" <?= ($integrante_a_editar['pertenece_comuna'] == 'Si') ? 'selected' : ''; ?>>Sí</option>
                                        <option value="No" <?= ($integrante_a_editar['pertenece_comuna'] == 'No') ? 'selected' : ''; ?>>No</option>
                                    </select>
                                </div>
                                <div class="field-col" style="grid-column: 1 / -1;">
                                    <label for="edit_enfermedades" class="field-label">Enfermedades</label>
                                    <textarea name="enfermedades" id="edit_enfermedades" rows="2" class="form-control"><?= htmlspecialchars($integrante_a_editar['enfermedades'] ?? ''); ?></textarea>
                                </div>
                                <div class="field-col">
                                    <label for="edit_cargo" class="field-label">Cargo en el Colectivo</label>
                                    <input type="text" name="cargo_en_colectivo" id="edit_cargo" value="<?= htmlspecialchars($integrante_a_editar['cargo_en_colectivo'] ?? ''); ?>" class="form-control" />
                                </div>
                                <div class="field-col">
                                    <label for="edit_fecha_ingreso" class="field-label">Fecha de Ingreso</label>
                                    <input type="date" name="fecha_ingreso" id="edit_fecha_ingreso" value="<?= htmlspecialchars($integrante_a_editar['fecha_ingreso'] ?? ''); ?>" class="form-control" />
                                </div>
                                <div class="field-col">
                                    <label for="edit_es_referente" class="field-label">Es Referente</label>
                                    <select name="es_referente" id="edit_es_referente" class="form-control">
                                        <option value="0" <?= ($integrante_a_editar['es_referente'] == 0) ? 'selected' : ''; ?>>No</option>
                                        <option value="1" <?= ($integrante_a_editar['es_referente'] == 1) ? 'selected' : ''; ?>>Sí</option>
                                    </select>
                                </div>
                                <div class="field-col">
                                    <label for="edit_activo" class="field-label">Activo</label>
                                    <select name="activo" id="edit_activo" class="form-control">
                                        <option value="1" <?= ($integrante_a_editar['activo'] == 1) ? 'selected' : ''; ?>>Sí</option>
                                        <option value="0" <?= ($integrante_a_editar['activo'] == 0) ? 'selected' : ''; ?>>No</option>
                                    </select>
                                </div>
                            </div>
                            <div class="button-container">
                                <button type="submit" class="btn btn-primary">Actualizar Integrante</button>
                                <a href="?id=<?= htmlspecialchars($id_solicitante); ?>&tipo=colectivo" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($solicitante_seleccionado['tipo'] == 'apoderado' || $solicitante_seleccionado['tipo'] == 'representante'): ?>
                <form method="POST" action="" class="section-container">
                    <input type="hidden" name="accion" value="<?= $solicitante_seleccionado['tipo'] == 'apoderado' ? 'editar_apoderado' : 'editar_representante'; ?>">
                    <input type="hidden" name="editar" value="1">
                    <input type="hidden" name="id_representante" value="<?= htmlspecialchars($solicitante_seleccionado['id_representante']); ?>">
                    <div class="field-row">
                        <div class="field-col">
                            <label for="rep_id_representante" class="field-label">ID del Representante</label>
                            <input type="text" id="rep_id_representante" value="<?= htmlspecialchars($solicitante_seleccionado['id_representante']); ?>" class="form-control" readonly />
                        </div>
                        <div class="field-col">
                            <label for="rep_primer_nombre" class="field-label required">Primer Nombre *</label>
                            <input type="text" name="rep_primer_nombre" id="rep_primer_nombre" value="<?= htmlspecialchars($solicitante_seleccionado['primer_nombre']); ?>" placeholder="Ingrese primer nombre" class="form-control" required />
                        </div>
                        <div class="field-col">
                            <label for="rep_segundo_nombre" class="field-label">Segundo Nombre</label>
                            <input type="text" name="rep_segundo_nombre" id="rep_segundo_nombre" value="<?= htmlspecialchars($solicitante_seleccionado['segundo_nombre'] ?? ''); ?>" placeholder="Ingrese segundo nombre" class="form-control" />
                        </div>
                        <div class="field-col">
                            <label for="rep_primer_apellido" class="field-label required">Primer Apellido *</label>
                            <input type="text" name="rep_primer_apellido" id="rep_primer_apellido" value="<?= htmlspecialchars($solicitante_seleccionado['primer_apellido']); ?>" placeholder="Ingrese primer apellido" class="form-control" required />
                        </div>
                        <div class="field-col">
                            <label for="rep_segundo_apellido" class="field-label">Segundo Apellido</label>
                            <input type="text" name="rep_segundo_apellido" id="rep_segundo_apellido" value="<?= htmlspecialchars($solicitante_seleccionado['segundo_apellido'] ?? ''); ?>" placeholder="Ingrese segundo apellido" class="form-control" />
                        </div>
                        <div class="field-col">
                            <label for="rep_sexo" class="field-label required">Sexo *</label>
                            <select name="rep_sexo" id="rep_sexo" class="form-control" required>
                                <option value="">Seleccione...</option>
                                <option value="M" <?= ($solicitante_seleccionado['sexo'] == 'M') ? 'selected' : ''; ?>>Masculino</option>
                                <option value="F" <?= ($solicitante_seleccionado['sexo'] == 'F') ? 'selected' : ''; ?>>Femenino</option>
                            </select>
                        </div>
                        <div class="field-col">
                            <label for="rep_telefono" class="field-label">Teléfono</label>
                            <input type="tel" name="rep_telefono" id="rep_telefono" value="<?= htmlspecialchars($solicitante_seleccionado['telefono'] ?? ''); ?>" placeholder="0412-1234567" class="form-control" />
                        </div>
                        <div class="field-col" style="grid-column: 1 / -1;">
                            <label for="rep_direccion" class="field-label">Dirección</label>
                            <textarea name="rep_direccion" id="rep_direccion" rows="2" class="form-control" placeholder="Ingrese dirección completa"><?= htmlspecialchars($solicitante_seleccionado['direccion'] ?? ''); ?></textarea>
                        </div>
                        <div class="field-col">
                            <label for="rep_email" class="field-label">Email</label>
                            <input type="email" name="rep_email" id="rep_email" value="<?= htmlspecialchars($solicitante_seleccionado['email'] ?? ''); ?>" placeholder="correo@ejemplo.com" class="form-control" />
                        </div>
                        <div class="field-col">
                            <label for="rep_profesion" class="field-label">Profesión</label>
                            <input type="text" name="rep_profesion" id="rep_profesion" value="<?= htmlspecialchars($solicitante_seleccionado['profesion'] ?? ''); ?>" placeholder="Ingrese profesión" class="form-control" />
                        </div>
                    </div>
                    <div class="button-container">
                        <button type="submit" class="btn btn-primary">Actualizar <?= $solicitante_seleccionado['tipo'] == 'apoderado' ? 'Apoderado' : 'Representante Legal'; ?></button>
                        <a href="editar_solicitante.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>

                <!-- Datos de la Persona Asociada -->
                <?php if ($persona_asociada): ?>
                    <div class="section-container" style="margin-top: 20px;">
                        <h3 class="section-title">Datos de la Persona <?= ucfirst($persona_asociada['tipo']); ?> Asociada</h3>
                        <table class="table table-striped">
                            <tbody>
                                <?php if ($persona_asociada['tipo'] == 'natural'): ?>
                                    <tr><th>Cédula</th><td><?= htmlspecialchars($persona_asociada['cedula']); ?></td></tr>
                                    <tr><th>Nombre Completo</th><td><?= htmlspecialchars($persona_asociada['primer_nombre'] . ' ' . ($persona_asociada['segundo_nombre'] ?? '') . ' ' . $persona_asociada['primer_apellido'] . ' ' . ($persona_asociada['segundo_apellido'] ?? '')); ?></td></tr>
                                    <tr><th>Sexo</th><td><?= htmlspecialchars($persona_asociada['sexo']); ?></td></tr>
                                    <tr><th>Fecha de Nacimiento</th><td><?= htmlspecialchars($persona_asociada['fecha_nacimiento'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Teléfono</th><td><?= htmlspecialchars($persona_asociada['telefono'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Dirección</th><td><?= htmlspecialchars($persona_asociada['direccion_habitacion'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Estado Civil</th><td><?= htmlspecialchars($persona_asociada['estado_civil'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Número de Hijos</th><td><?= htmlspecialchars($persona_asociada['numero_hijos'] ?? 0); ?></td></tr>
                                    <tr><th>Grado de Instrucción</th><td><?= htmlspecialchars($persona_asociada['grado_instruccion'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Sabe Leer</th><td><?= htmlspecialchars($persona_asociada['sabe_leer'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Posee Ayuda Económica</th><td><?= htmlspecialchars($persona_asociada['posee_ayuda_economica'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Trabaja Actualmente</th><td><?= htmlspecialchars($persona_asociada['trabaja_actualmente'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Pertenece a Comuna</th><td><?= htmlspecialchars($persona_asociada['pertenece_comuna'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Enfermedades</th><td><?= htmlspecialchars($persona_asociada['enfermedades'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Activo</th><td><?= $persona_asociada['activo'] ? 'Sí' : 'No'; ?></td></tr>
                                <?php elseif ($persona_asociada['tipo'] == 'juridica'): ?>
                                    <tr><th>RIF</th><td><?= htmlspecialchars($persona_asociada['rif']); ?></td></tr>
                                    <tr><th>Razón Social</th><td><?= htmlspecialchars($persona_asociada['razon_social']); ?></td></tr>
                                    <tr><th>Teléfono</th><td><?= htmlspecialchars($persona_asociada['telefono'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Dirección</th><td><?= htmlspecialchars($persona_asociada['direccion_habitacion'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Estado Civil</th><td><?= htmlspecialchars($persona_asociada['estado_civil'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Número de Hijos</th><td><?= htmlspecialchars($persona_asociada['numero_hijos'] ?? 0); ?></td></tr>
                                    <tr><th>Grado de Instrucción</th><td><?= htmlspecialchars($persona_asociada['grado_instruccion'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Sabe Leer</th><td><?= htmlspecialchars($persona_asociada['sabe_leer'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Posee Ayuda Económica</th><td><?= htmlspecialchars($persona_asociada['posee_ayuda_economica'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Trabaja Actualmente</th><td><?= htmlspecialchars($persona_asociada['trabaja_actualmente'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Pertenece a Comuna</th><td><?= htmlspecialchars($persona_asociada['pertenece_comuna'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Enfermedades</th><td><?= htmlspecialchars($persona_asociada['enfermedades'] ?? 'N/A'); ?></td></tr>
                                    <tr><th>Activo</th><td><?= $persona_asociada['activo'] ? 'Sí' : 'No'; ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Funcionalidad para mantener el estado del formulario
document.addEventListener('DOMContentLoaded', function() {
    const formBusqueda = document.getElementById('form-busqueda');

    // Si hay un resultado seleccionado, asegurarse de que el formulario de búsqueda tenga los valores correctos
    if (<?= $solicitante_seleccionado ? 'true' : 'false'; ?>) {
        const filtroTipo = document.getElementById('filtro_tipo');
        const identificacionBusqueda = document.getElementById('identificacion_busqueda');

        if (filtroTipo.value && identificacionBusqueda.value) {
            // Los valores ya están establecidos por PHP
        }
    }
});
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