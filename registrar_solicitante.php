<?php
include('header.php');
include('conexion.php');

// Inicializar variables de mensaje
$mensaje = '';
$tipo_mensaje = '';

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->begin_transaction();
        
        // Determinar la acción a realizar
        $accion = $_POST['accion'] ?? '';
        
        if ($accion == 'registrar_natural') {
            // Validar datos de persona natural
            $cedula = trim($_POST['cedula'] ?? '');
            $primer_nombre = trim($_POST['primer_nombre'] ?? '');
            $segundo_nombre = trim($_POST['segundo_nombre'] ?? '');
            $primer_apellido = trim($_POST['primer_apellido'] ?? '');
            $segundo_apellido = trim($_POST['segundo_apellido'] ?? '');
            $sexo = $_POST['sexo'] ?? '';
            $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
            $telefono = trim($_POST['telefono'] ?? '');
            $direccion_habitacion = trim($_POST['direccion_habitacion'] ?? '');
            $estado_civil = $_POST['estado_civil'] ?? '';
            $numero_hijos = (int)($_POST['numero_hijos'] ?? 0);
            $grado_instruccion = $_POST['grado_instruccion'] ?? '';
            $sabe_leer = $_POST['sabe_leer'] ?? '';
            $posee_ayuda_economica = $_POST['posee_ayuda_economica'] ?? '';
            $trabaja_actualmente = $_POST['trabaja_actualmente'] ?? '';
            $pertenece_comuna = $_POST['pertenece_comuna'] ?? '';
            $enfermedades = trim($_POST['enfermedades'] ?? '');
            
            // Validaciones básicas
            if (empty($cedula) || empty($primer_nombre) || empty($primer_apellido) || empty($sexo)) {
                throw new Exception("Los campos cédula, nombre, apellido y sexo son obligatorios.");
            }
            
            // Verificar si ya existe una persona con esta cédula
            $stmt_check = $conn->prepare("SELECT cedula FROM personas_naturales WHERE cedula = ? AND activo = 1");
            $stmt_check->bind_param("s", $cedula);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                throw new Exception("Ya existe una persona natural con la cédula $cedula.");
            }
            $stmt_check->close();
            
            // Insertar persona natural - CORREGIDO: bind_param con tipos correctos
            $stmt = $conn->prepare("INSERT INTO personas_naturales(cedula, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, sexo, fecha_nacimiento, telefono, direccion_habitacion, estado_civil, numero_hijos, grado_instruccion, sabe_leer, posee_ayuda_economica, trabaja_actualmente, pertenece_comuna, enfermedades, id_representante, tipo_representacion, activo, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, 1, NOW())");
            
           // TIPOS CORREGIDOS: 17 parámetros (i solo para numero_hijos)
$stmt->bind_param("ssssssssssissssss",
    $cedula,                // s
    $primer_nombre,         // s
    $segundo_nombre,        // s
    $primer_apellido,       // s
    $segundo_apellido,      // s
    $sexo,                  // s
    $fecha_nacimiento,      // s
    $telefono,              // s
    $direccion_habitacion,  // s
    $estado_civil,          // s
    $numero_hijos,          // i
    $grado_instruccion,     // s
    $sabe_leer,             // s
    $posee_ayuda_economica, // s
    $trabaja_actualmente,   // s
    $pertenece_comuna,      // s
    $enfermedades           // s
);

  
    if (!$stmt->execute()) {
        throw new Exception("Error al registrar la persona natural: " . $stmt->error);
    }
    
    $stmt->close();            
            // Registrar en bitácora
            $cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'SISTEMA';
            $stmt_bitacora = $conn->prepare("INSERT INTO bitacora(cedula_usuario, accion, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, 'Registro', 'personas_naturales', ?, ?, NOW())");
            $detalle = "Registro de persona natural: $primer_nombre $primer_apellido";
            $stmt_bitacora->bind_param("sss", $cedula_usuario, $cedula, $detalle);
            $stmt_bitacora->execute();
            $stmt_bitacora->close();
            
            $mensaje = "Persona natural registrada correctamente.";
            $tipo_mensaje = "success";
            
        } elseif ($accion == 'registrar_juridica') {
            // Validar datos de persona jurídica
            $rif = trim($_POST['rif'] ?? '');
            $razon_social = trim($_POST['razon_social'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $direccion_habitacion = trim($_POST['direccion_habitacion'] ?? '');
            $estado_civil = $_POST['estado_civil'] ?? '';
            $numero_hijos = (int)($_POST['numero_hijos'] ?? 0);
            $grado_instruccion = $_POST['grado_instruccion'] ?? '';
            $sabe_leer = $_POST['sabe_leer'] ?? '';
            $posee_ayuda_economica = $_POST['posee_ayuda_economica'] ?? '';
            $trabaja_actualmente = $_POST['trabaja_actualmente'] ?? '';
            $pertenece_comuna = $_POST['pertenece_comuna'] ?? '';
            $enfermedades = trim($_POST['enfermedades'] ?? '');
            
            // Validaciones básicas
            if (empty($rif) || empty($razon_social)) {
                throw new Exception("Los campos RIF y Razón Social son obligatorios para personas jurídicas.");
            }
            
            // Validar formato del RIF (solo dígitos)
            if (!preg_match('/^\d+$/', $rif)) {
                throw new Exception("El RIF debe contener solo números.");
            }
            
            // Verificar si ya existe una persona jurídica con este RIF
            $stmt_check = $conn->prepare("SELECT rif FROM personas_juridicas WHERE rif = ? AND activo = 1");
            $stmt_check->bind_param("s", $rif);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                throw new Exception("Ya existe una persona jurídica con el RIF $rif.");
            }
            $stmt_check->close();
            
            // Insertar persona jurídica - CORREGIDO: bind_param con tipos correctos
            $stmt_pj = $conn->prepare("INSERT INTO personas_juridicas(rif, razon_social, telefono, direccion_habitacion, estado_civil, numero_hijos, grado_instruccion, sabe_leer, posee_ayuda_economica, trabaja_actualmente, pertenece_comuna, enfermedades, id_representante, tipo_representacion, activo, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, 1, NOW())");
            
            // Corregido: 12 parámetros
            $stmt_pj->bind_param("sssssissssss",
                $rif,
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
                $enfermedades
            );
            
            if (!$stmt_pj->execute()) {
                throw new Exception("Error al registrar la persona jurídica: " . $stmt_pj->error);
            }
            
            $stmt_pj->close();
            
            // Registrar en bitácora
            $cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'SISTEMA';
            $stmt_bitacora = $conn->prepare("INSERT INTO bitacora(cedula_usuario, accion, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, 'Registro', 'personas_juridicas', ?, ?, NOW())");
            $detalle = "Registro de persona jurídica: $razon_social";
            $stmt_bitacora->bind_param("sss", $cedula_usuario, $rif, $detalle);
            $stmt_bitacora->execute();
            $stmt_bitacora->close();
            
            // Procesar representante legal
            $ci_representante = trim($_POST['ci_representante'] ?? '');
            $primer_nombre_rep = trim($_POST['rep_primer_nombre'] ?? '');
            $segundo_nombre_rep = trim($_POST['rep_segundo_nombre'] ?? '');
            $primer_apellido_rep = trim($_POST['rep_primer_apellido'] ?? '');
            $segundo_apellido_rep = trim($_POST['rep_segundo_apellido'] ?? '');
            $sexo_rep = $_POST['rep_sexo'] ?? '';
            $telefono_rep = trim($_POST['rep_telefono'] ?? '');
            $direccion_rep = trim($_POST['rep_direccion'] ?? '');
            $email_rep = trim($_POST['rep_email'] ?? '');
            $profesion_rep = trim($_POST['rep_profesion'] ?? '');

            // Validaciones básicas para representante
            if (empty($ci_representante) || empty($primer_nombre_rep) || empty($primer_apellido_rep) || empty($sexo_rep)) {
                throw new Exception("Los campos CI, Primer Nombre, Primer Apellido y Sexo son obligatorios para el representante legal.");
            }

            // Validar que la CI sea solo números
            if (!preg_match('/^\d+$/', $ci_representante)) {
                throw new Exception("La CI del representante debe contener solo números.");
            }

            // Insertar representante usando la CI como ID
            $id_representante = (int)$ci_representante;
            $tipo_rep = 'Representante_Legal';

            // Verificar si ya existe un representante con este ID
            $stmt_check_rep = $conn->prepare("SELECT id_representante FROM representantes WHERE id_representante = ?");
            $stmt_check_rep->bind_param("i", $id_representante);
            $stmt_check_rep->execute();
            $result_check_rep = $stmt_check_rep->get_result();

            if ($result_check_rep->num_rows > 0) {
                // Actualizar representante existente
                $stmt_rep = $conn->prepare("UPDATE representantes SET primer_nombre=?, segundo_nombre=?, primer_apellido=?, segundo_apellido=?, sexo=?, telefono=?, direccion=?, email=?, profesion=?, tipo=? WHERE id_representante=?");
                $stmt_rep->bind_param("ssssssssssi",
                    $primer_nombre_rep,
                    $segundo_nombre_rep,
                    $primer_apellido_rep,
                    $segundo_apellido_rep,
                    $sexo_rep,
                    $telefono_rep,
                    $direccion_rep,
                    $email_rep,
                    $profesion_rep,
                    $tipo_rep,
                    $id_representante
                );
            } else {
                // Insertar nuevo representante
                $stmt_rep = $conn->prepare("INSERT INTO representantes(id_representante, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, sexo, telefono, direccion, email, profesion, tipo, activo, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt_rep->bind_param("issssssssss",
                    $id_representante,
                    $primer_nombre_rep,
                    $segundo_nombre_rep,
                    $primer_apellido_rep,
                    $segundo_apellido_rep,
                    $sexo_rep,
                    $telefono_rep,
                    $direccion_rep,
                    $email_rep,
                    $profesion_rep,
                    $tipo_rep
                );
            }

            if (!$stmt_rep->execute()) {
                throw new Exception("Error al registrar el representante legal: " . $stmt_rep->error);
            }

            $stmt_rep->close();
            $stmt_check_rep->close();

            // Actualizar la persona jurídica con el representante
            $tipo_representacion = 'Representante_Legal';
            $stmt_update_pj = $conn->prepare("UPDATE personas_juridicas SET id_representante = ?, tipo_representacion = ? WHERE rif = ?");
            $stmt_update_pj->bind_param("iss", $id_representante, $tipo_representacion, $rif);
            $stmt_update_pj->execute();
            $stmt_update_pj->close();

            $mensaje = "Persona jurídica y representante legal registrados correctamente.";
            $tipo_mensaje = "success";

        } elseif ($accion == 'registrar_colectivo') {
            // Validar datos del colectivo
            $rif_referente = trim($_POST['rif_referente'] ?? '');
            $nombre_colectivo = trim($_POST['nombre_colectivo'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $direccion_habitacion = trim($_POST['direccion_habitacion'] ?? '');
            $numero_integrantes = (int)($_POST['numero_integrantes'] ?? 0);
            
            // Validaciones básicas
            if (empty($rif_referente) || empty($nombre_colectivo)) {
                throw new Exception("Los campos RIF/CI Referente y Nombre del Colectivo son obligatorios.");
            }
            
            // Validar formato del RIF/CI (solo dígitos)
            if (!preg_match('/^\d+$/', $rif_referente)) {
                throw new Exception("El RIF/CI debe contener solo números.");
            }
            
            // Verificar si ya existe un colectivo con este RIF
            $stmt_check = $conn->prepare("SELECT rif_o_ci_referente FROM colectivos WHERE rif_o_ci_referente = ? AND activo = 1");
            $stmt_check->bind_param("s", $rif_referente);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                throw new Exception("Ya existe un colectivo con el RIF/CI $rif_referente.");
            }
            $stmt_check->close();
            
            // Insertar colectivo
            $stmt_colectivo = $conn->prepare("INSERT INTO colectivos(rif_o_ci_referente, nombre_colectivo, telefono, direccion_habitacion, numero_integrantes, activo, creado_en) VALUES (?, ?, ?, ?, ?, 1, NOW())");
            $stmt_colectivo->bind_param("ssssi", 
                $rif_referente, 
                $nombre_colectivo, 
                $telefono, 
                $direccion_habitacion, 
                $numero_integrantes
            );
            
            if (!$stmt_colectivo->execute()) {
                throw new Exception("Error al registrar el colectivo: " . $stmt_colectivo->error);
            }
            
            $stmt_colectivo->close();

            // Procesar los integrantes
            $integrantes_cedula = $_POST['integrantes_cedula'] ?? [];
            $integrantes_primer_nombre = $_POST['integrantes_primer_nombre'] ?? [];
            $integrantes_segundo_nombre = $_POST['integrantes_segundo_nombre'] ?? [];
            $integrantes_primer_apellido = $_POST['integrantes_primer_apellido'] ?? [];
            $integrantes_segundo_apellido = $_POST['integrantes_segundo_apellido'] ?? [];
            $integrantes_sexo = $_POST['integrantes_sexo'] ?? [];
            $integrantes_fecha_nacimiento = $_POST['integrantes_fecha_nacimiento'] ?? [];
            $integrantes_telefono = $_POST['integrantes_telefono'] ?? [];
            $integrantes_direccion_habitacion = $_POST['integrantes_direccion_habitacion'] ?? [];
            $integrantes_estado_civil = $_POST['integrantes_estado_civil'] ?? [];
            $integrantes_numero_hijos = $_POST['integrantes_numero_hijos'] ?? [];
            $integrantes_grado_instruccion = $_POST['integrantes_grado_instruccion'] ?? [];
            $integrantes_sabe_leer = $_POST['integrantes_sabe_leer'] ?? [];
            $integrantes_posee_ayuda_economica = $_POST['integrantes_posee_ayuda_economica'] ?? [];
            $integrantes_trabaja_actualmente = $_POST['integrantes_trabaja_actualmente'] ?? [];
            $integrantes_pertenece_comuna = $_POST['integrantes_pertenece_comuna'] ?? [];
            $integrantes_enfermedades = $_POST['integrantes_enfermedades'] ?? [];
            $integrantes_es_referente = $_POST['integrantes_es_referente'] ?? [];
            $integrantes_cargo_en_colectivo = $_POST['integrantes_cargo_en_colectivo'] ?? [];
            $integrantes_fecha_ingreso = $_POST['integrantes_fecha_ingreso'] ?? [];

            $total_integrantes = count($integrantes_cedula);
            $contador_registrados = 0;
            $activo_integrante = 1;

            for ($i = 0; $i < $total_integrantes; $i++) {
                $cedula = trim($integrantes_cedula[$i] ?? '');
                $primer_nombre = trim($integrantes_primer_nombre[$i] ?? '');
                $segundo_nombre = trim($integrantes_segundo_nombre[$i] ?? '');
                $primer_apellido = trim($integrantes_primer_apellido[$i] ?? '');
                $segundo_apellido = trim($integrantes_segundo_apellido[$i] ?? '');
                $sexo = $integrantes_sexo[$i] ?? '';
                $fecha_nacimiento = $integrantes_fecha_nacimiento[$i] ?? null;
                $telefono = trim($integrantes_telefono[$i] ?? '');
                $direccion_habitacion = trim($integrantes_direccion_habitacion[$i] ?? '');
                $estado_civil = $integrantes_estado_civil[$i] ?? '';
                $numero_hijos = (int)($integrantes_numero_hijos[$i] ?? 0);
                $grado_instruccion = $integrantes_grado_instruccion[$i] ?? '';
                $sabe_leer = $integrantes_sabe_leer[$i] ?? '';
                $posee_ayuda_economica = $integrantes_posee_ayuda_economica[$i] ?? '';
                $trabaja_actualmente = $integrantes_trabaja_actualmente[$i] ?? '';
                $pertenece_comuna = $integrantes_pertenece_comuna[$i] ?? '';
                $enfermedades = trim($integrantes_enfermedades[$i] ?? '');
                $es_referente = (int)($integrantes_es_referente[$i] ?? 0);
                $cargo_en_colectivo = trim($integrantes_cargo_en_colectivo[$i] ?? '');
                $fecha_ingreso = $integrantes_fecha_ingreso[$i] ?? null;

                // Validar campos obligatorios
                if (empty($cedula) || empty($primer_nombre) || empty($primer_apellido) || empty($sexo) || empty($fecha_ingreso)) {
                    continue; // Saltar integrantes sin datos obligatorios
                }

                // Verificar si ya existe el integrante
                $stmt_check_int = $conn->prepare("SELECT cedula FROM colectivo_integrantes WHERE cedula = ? AND rif_o_ci_colectivo = ? AND activo = 1");
                $stmt_check_int->bind_param("ss", $cedula, $rif_referente);
                $stmt_check_int->execute();
                $result_check_int = $stmt_check_int->get_result();

                if ($result_check_int->num_rows > 0) {
                    // Actualizar integrante existente
                    $stmt_integrante = $conn->prepare("UPDATE colectivo_integrantes SET primer_nombre=?, segundo_nombre=?, primer_apellido=?, segundo_apellido=?, sexo=?, fecha_nacimiento=?, telefono=?, direccion_habitacion=?, estado_civil=?, numero_hijos=?, grado_instruccion=?, sabe_leer=?, posee_ayuda_economica=?, trabaja_actualmente=?, pertenece_comuna=?, enfermedades=?, es_referente=?, cargo_en_colectivo=?, fecha_ingreso=?, modificado_en=NOW() WHERE cedula=? AND rif_o_ci_colectivo=?");
                    $stmt_integrante->bind_param("sssssssssissssssssss",
                        $primer_nombre,
                        $segundo_nombre,
                        $primer_apellido,
                        $segundo_apellido,
                        $sexo,
                        $fecha_nacimiento,
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
                        $es_referente,
                        $cargo_en_colectivo,
                        $fecha_ingreso,
                        $cedula,
                        $rif_referente
                    );
                } else {
                    // Insertar nuevo integrante
                    $stmt_integrante = $conn->prepare("INSERT INTO colectivo_integrantes(cedula, rif_o_ci_colectivo, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, sexo, fecha_nacimiento, telefono, direccion_habitacion, estado_civil, numero_hijos, grado_instruccion, sabe_leer, posee_ayuda_economica, trabaja_actualmente, pertenece_comuna, enfermedades, es_referente, cargo_en_colectivo, fecha_ingreso, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_integrante->bind_param("ssssssssssissssssisssi",
                        $cedula,
                        $rif_referente,
                        $primer_nombre,
                        $segundo_nombre,
                        $primer_apellido,
                        $segundo_apellido,
                        $sexo,
                        $fecha_nacimiento,
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
                        $es_referente,
                        $cargo_en_colectivo,
                        $fecha_ingreso,
                        $activo_integrante
                    );
                }

                if ($stmt_integrante->execute()) {
                    $contador_registrados++;
                } else {
                    throw new Exception("Error al registrar el integrante $primer_nombre $primer_apellido: " . $stmt_integrante->error);
                }

                $stmt_integrante->close();
                $stmt_check_int->close();
            }

            // Actualizar el número de integrantes en el colectivo
            $stmt_update_colectivo = $conn->prepare("UPDATE colectivos SET numero_integrantes = ? WHERE rif_o_ci_referente = ?");
            $stmt_update_colectivo->bind_param("is", $contador_registrados, $rif_referente);
            $stmt_update_colectivo->execute();
            $stmt_update_colectivo->close();

            // Registrar en bitácora
            $cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'SISTEMA';
            $stmt_bitacora = $conn->prepare("INSERT INTO bitacora(cedula_usuario, accion, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, 'Registro', 'colectivos', ?, ?, NOW())");
            $detalle = "Registro de colectivo: $nombre_colectivo con $contador_registrados integrantes";
            $stmt_bitacora->bind_param("sss", $cedula_usuario, $rif_referente, $detalle);
            $stmt_bitacora->execute();
            $stmt_bitacora->close();

            $mensaje = "Colectivo y $contador_registrados integrantes registrados correctamente.";
            $tipo_mensaje = "success";
            
        } elseif ($accion == 'agregar_apoderado' || $accion == 'agregar_representante_legal') {
            // Obtener identificación desde el formulario
            $identificacion = trim($_POST['identificacion_busqueda'] ?? '');
            $ci_representante = trim($_POST['ci_representante'] ?? '');
            $primer_nombre = trim($_POST['rep_primer_nombre'] ?? '');
            $segundo_nombre = trim($_POST['rep_segundo_nombre'] ?? '');
            $primer_apellido = trim($_POST['rep_primer_apellido'] ?? '');
            $segundo_apellido = trim($_POST['rep_segundo_apellido'] ?? '');
            $sexo = $_POST['rep_sexo'] ?? '';
            $telefono = trim($_POST['rep_telefono'] ?? '');
            $direccion = trim($_POST['rep_direccion'] ?? '');
            $email = trim($_POST['rep_email'] ?? '');
            $profesion = trim($_POST['rep_profesion'] ?? '');
            
            // Validaciones básicas
            if (empty($identificacion)) {
                throw new Exception("Debe ingresar una identificación válida del solicitante.");
            }
            
            if (empty($ci_representante)) {
                throw new Exception("Debe ingresar la CI del representante/apoderado.");
            }
            
            if (empty($primer_nombre) || empty($primer_apellido) || empty($sexo)) {
                throw new Exception("Los campos Primer Nombre, Primer Apellido y Sexo son obligatorios para el representante.");
            }
            
            // Validar que las identificaciones sean solo números
            if (!preg_match('/^\d+$/', $identificacion)) {
                throw new Exception("La identificación del solicitante debe contener solo números.");
            }
            
            if (!preg_match('/^\d+$/', $ci_representante)) {
                throw new Exception("La CI del representante debe contener solo números.");
            }
            
            // Verificar si existe el solicitante
            $persona = null;
            $tabla_origen = '';
            
            // Buscar en personas naturales
            $stmt_pn = $conn->prepare("SELECT cedula, CONCAT(primer_nombre, ' ', IFNULL(segundo_nombre, ''), ' ', primer_apellido, ' ', IFNULL(segundo_apellido, '')) as nombre_completo FROM personas_naturales WHERE cedula = ? AND activo = 1");
            $stmt_pn->bind_param("s", $identificacion);
            $stmt_pn->execute();
            $result_pn = $stmt_pn->get_result();
            
            if ($result_pn->num_rows > 0) {
                $persona = $result_pn->fetch_assoc();
                $tabla_origen = 'personas_naturales';
            } else {
                // Buscar en personas jurídicas
                $stmt_pj = $conn->prepare("SELECT rif, razon_social as nombre_completo FROM personas_juridicas WHERE rif = ? AND activo = 1");
                $stmt_pj->bind_param("s", $identificacion);
                $stmt_pj->execute();
                $result_pj = $stmt_pj->get_result();
                
                if ($result_pj->num_rows > 0) {
                    $persona = $result_pj->fetch_assoc();
                    $tabla_origen = 'personas_juridicas';
                }
                $stmt_pj->close();
            }
            
            $stmt_pn->close();
            
            if (!$persona) {
                throw new Exception("No se encontró ningún solicitante con la identificación $identificacion.");
            }
            
            // Insertar representante usando la CI como ID
            $tipo_representante = $accion == 'agregar_apoderado' ? 'Apoderado' : 'Representante_Legal';
            $id_representante = (int)$ci_representante;
            
            // Verificar si ya existe un representante con este ID
            $stmt_check_rep = $conn->prepare("SELECT id_representante FROM representantes WHERE id_representante = ?");
            $stmt_check_rep->bind_param("i", $id_representante);
            $stmt_check_rep->execute();
            $result_check_rep = $stmt_check_rep->get_result();
            
            if ($result_check_rep->num_rows > 0) {
                // Actualizar representante existente
                $stmt_rep = $conn->prepare("UPDATE representantes SET primer_nombre=?, segundo_nombre=?, primer_apellido=?, segundo_apellido=?, sexo=?, telefono=?, direccion=?, email=?, profesion=?, tipo=? WHERE id_representante=?");
                $stmt_rep->bind_param("ssssssssssi", 
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
                    $id_representante
                );
            } else {
                // Insertar nuevo representante - CORREGIDO: bind_param con tipos correctos
                $stmt_rep = $conn->prepare("INSERT INTO representantes(id_representante, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, sexo, telefono, direccion, email, profesion, tipo, activo, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt_rep->bind_param("issssssssss", 
                    $id_representante,
                    $primer_nombre, 
                    $segundo_nombre, 
                    $primer_apellido, 
                    $segundo_apellido, 
                    $sexo, 
                    $telefono, 
                    $direccion, 
                    $email, 
                    $profesion, 
                    $tipo_representante
                );
            }
            
            if (!$stmt_rep->execute()) {
                throw new Exception("Error al registrar el representante: " . $stmt_rep->error);
            }
            
            $stmt_rep->close();
            $stmt_check_rep->close();
            
            // Actualizar el solicitante con el representante
            if ($tabla_origen == 'personas_naturales') {
                $stmt_update = $conn->prepare("UPDATE personas_naturales SET id_representante = ?, tipo_representacion = ? WHERE cedula = ?");
                $stmt_update->bind_param("iss", $id_representante, $tipo_representante, $identificacion);
            } else {
                $stmt_update = $conn->prepare("UPDATE personas_juridicas SET id_representante = ?, tipo_representacion = ? WHERE rif = ?");
                $stmt_update->bind_param("iss", $id_representante, $tipo_representante, $identificacion);
            }
            
            if (!$stmt_update->execute()) {
                throw new Exception("Error al actualizar el solicitante con el representante: " . $stmt_update->error);
            }
            
            $stmt_update->close();
            
            // Registrar en bitácora
            $cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'SISTEMA';
            $stmt_bitacora = $conn->prepare("INSERT INTO bitacora(cedula_usuario, accion, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, 'Asignación', ?, ?, ?, NOW())");
            $tabla_destino = $tabla_origen == 'personas_naturales' ? 'apoderados' : 'representantes_legales';
            $detalle = $accion == 'agregar_apoderado' ? 
                "Asignación de apoderado a persona natural: $identificacion" : 
                "Asignación de representante legal a persona jurídica: $identificacion";
            $stmt_bitacora->bind_param("ssss", $cedula_usuario, $tabla_destino, $identificacion, $detalle);
            $stmt_bitacora->execute();
            $stmt_bitacora->close();
            
            $mensaje = $accion == 'agregar_apoderado' ? 
                "Apoderado asignado correctamente a la persona natural." : 
                "Representante legal asignado correctamente a la persona jurídica.";
            $tipo_mensaje = "success";
            
        } elseif ($accion == 'registrar_integrantes') {
            // Registro de integrantes de colectivo
            $rif_colectivo = trim($_POST['rif_colectivo'] ?? '');
            
            if (empty($rif_colectivo)) {
                throw new Exception("Debe especificar el RIF/CI del colectivo.");
            }
            
            // Verificar que el colectivo exista
            $stmt_colectivo = $conn->prepare("SELECT rif_o_ci_referente, nombre_colectivo FROM colectivos WHERE rif_o_ci_referente = ? AND activo = 1");
            $stmt_colectivo->bind_param("s", $rif_colectivo);
            $stmt_colectivo->execute();
            $result_colectivo = $stmt_colectivo->get_result();
            
            if ($result_colectivo->num_rows === 0) {
                throw new Exception("No se encontró el colectivo con RIF/CI $rif_colectivo.");
            }
            
            $colectivo = $result_colectivo->fetch_assoc();
            $stmt_colectivo->close();
            
            // Procesar los integrantes
            $integrantes_cedula = $_POST['integrantes_cedula'] ?? [];
            $integrantes_primer_nombre = $_POST['integrantes_primer_nombre'] ?? [];
            $integrantes_segundo_nombre = $_POST['integrantes_segundo_nombre'] ?? [];
            $integrantes_primer_apellido = $_POST['integrantes_primer_apellido'] ?? [];
            $integrantes_segundo_apellido = $_POST['integrantes_segundo_apellido'] ?? [];
            $integrantes_sexo = $_POST['integrantes_sexo'] ?? [];
            $integrantes_fecha_nacimiento = $_POST['integrantes_fecha_nacimiento'] ?? [];
            $integrantes_telefono = $_POST['integrantes_telefono'] ?? [];
            $integrantes_direccion_habitacion = $_POST['integrantes_direccion_habitacion'] ?? [];
            $integrantes_estado_civil = $_POST['integrantes_estado_civil'] ?? [];
            $integrantes_numero_hijos = $_POST['integrantes_numero_hijos'] ?? [];
            $integrantes_grado_instruccion = $_POST['integrantes_grado_instruccion'] ?? [];
            $integrantes_sabe_leer = $_POST['integrantes_sabe_leer'] ?? [];
            $integrantes_posee_ayuda_economica = $_POST['integrantes_posee_ayuda_economica'] ?? [];
            $integrantes_trabaja_actualmente = $_POST['integrantes_trabaja_actualmente'] ?? [];
            $integrantes_pertenece_comuna = $_POST['integrantes_pertenece_comuna'] ?? [];
            $integrantes_enfermedades = $_POST['integrantes_enfermedades'] ?? [];
            $integrantes_es_referente = $_POST['integrantes_es_referente'] ?? [];
            $integrantes_cargo_en_colectivo = $_POST['integrantes_cargo_en_colectivo'] ?? [];
            $integrantes_fecha_ingreso = $_POST['integrantes_fecha_ingreso'] ?? [];
            
            $total_integrantes = count($integrantes_cedula);
            $contador_registrados = 0;
            $activo_integrante = 1;

            for ($i = 0; $i < $total_integrantes; $i++) {
                $cedula = trim($integrantes_cedula[$i] ?? '');
                $primer_nombre = trim($integrantes_primer_nombre[$i] ?? '');
                $segundo_nombre = trim($integrantes_segundo_nombre[$i] ?? '');
                $primer_apellido = trim($integrantes_primer_apellido[$i] ?? '');
                $segundo_apellido = trim($integrantes_segundo_apellido[$i] ?? '');
                $sexo = $integrantes_sexo[$i] ?? '';
                $fecha_nacimiento = $integrantes_fecha_nacimiento[$i] ?? null;
                $telefono = trim($integrantes_telefono[$i] ?? '');
                $direccion_habitacion = trim($integrantes_direccion_habitacion[$i] ?? '');
                $estado_civil = $integrantes_estado_civil[$i] ?? '';
                $numero_hijos = (int)($integrantes_numero_hijos[$i] ?? 0);
                $grado_instruccion = $integrantes_grado_instruccion[$i] ?? '';
                $sabe_leer = $integrantes_sabe_leer[$i] ?? '';
                $posee_ayuda_economica = $integrantes_posee_ayuda_economica[$i] ?? '';
                $trabaja_actualmente = $integrantes_trabaja_actualmente[$i] ?? '';
                $pertenece_comuna = $integrantes_pertenece_comuna[$i] ?? '';
                $enfermedades = trim($integrantes_enfermedades[$i] ?? '');
                $es_referente = (int)($integrantes_es_referente[$i] ?? 0);
                $cargo_en_colectivo = trim($integrantes_cargo_en_colectivo[$i] ?? '');
                $fecha_ingreso = $integrantes_fecha_ingreso[$i] ?? null;
                
                // Validar campos obligatorios
                if (empty($cedula) || empty($primer_nombre) || empty($primer_apellido) || empty($sexo) || empty($fecha_ingreso)) {
                    continue; // Saltar integrantes sin datos obligatorios
                }
                
                // Verificar si ya existe el integrante
                $stmt_check = $conn->prepare("SELECT cedula FROM colectivo_integrantes WHERE cedula = ? AND rif_o_ci_colectivo = ? AND activo = 1");
                $stmt_check->bind_param("ss", $cedula, $rif_colectivo);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                
                if ($result_check->num_rows > 0) {
                    // Actualizar integrante existente - CORREGIDO
                    $stmt_integrante = $conn->prepare("UPDATE colectivo_integrantes SET primer_nombre=?, segundo_nombre=?, primer_apellido=?, segundo_apellido=?, sexo=?, fecha_nacimiento=?, telefono=?, direccion_habitacion=?, estado_civil=?, numero_hijos=?, grado_instruccion=?, sabe_leer=?, posee_ayuda_economica=?, trabaja_actualmente=?, pertenece_comuna=?, enfermedades=?, es_referente=?, cargo_en_colectivo=?, fecha_ingreso=?, modificado_en=NOW() WHERE cedula=? AND rif_o_ci_colectivo=?");
                    $stmt_integrante->bind_param("sssssssssissssssssss",
                        $primer_nombre,
                        $segundo_nombre,
                        $primer_apellido,
                        $segundo_apellido,
                        $sexo,
                        $fecha_nacimiento,
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
                        $es_referente,
                        $cargo_en_colectivo,
                        $fecha_ingreso,
                        $cedula,
                        $rif_colectivo
                    );
                } else {
                    // Insertar nuevo integrante - CORREGIDO
                    $stmt_integrante = $conn->prepare("INSERT INTO colectivo_integrantes(cedula, rif_o_ci_colectivo, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, sexo, fecha_nacimiento, telefono, direccion_habitacion, estado_civil, numero_hijos, grado_instruccion, sabe_leer, posee_ayuda_economica, trabaja_actualmente, pertenece_comuna, enfermedades, es_referente, cargo_en_colectivo, fecha_ingreso, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_integrante->bind_param("ssssssssssissssssisssi",
                        $cedula,
                        $rif_colectivo,
                        $primer_nombre,
                        $segundo_nombre,
                        $primer_apellido,
                        $segundo_apellido,
                        $sexo,
                        $fecha_nacimiento,
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
                        $es_referente,
                        $cargo_en_colectivo,
                        $fecha_ingreso,
                        $activo_integrante
                    );
                }
                
                if ($stmt_integrante->execute()) {
                    $contador_registrados++;
                } else {
                    throw new Exception("Error al registrar el integrante $primer_nombre $primer_apellido: " . $stmt_integrante->error);
                }
                
                $stmt_integrante->close();
                $stmt_check->close();
            }
            
            // Actualizar el número de integrantes en el colectivo
            $stmt_update_colectivo = $conn->prepare("UPDATE colectivos SET numero_integrantes = ? WHERE rif_o_ci_referente = ?");
            $stmt_update_colectivo->bind_param("is", $contador_registrados, $rif_colectivo);
            $stmt_update_colectivo->execute();
            $stmt_update_colectivo->close();
            
            // Registrar en bitácora
            $cedula_usuario = $_SESSION['usuario']['cedula'] ?? 'SISTEMA';
            $stmt_bitacora = $conn->prepare("INSERT INTO bitacora(cedula_usuario, accion, tabla_afectada, registro_afectado, detalle, fecha_accion) VALUES (?, 'Registro', 'colectivo_integrantes', ?, ?, NOW())");
            $detalle = "Registro de $contador_registrados integrantes para el colectivo: {$colectivo['nombre_colectivo']}";
            $stmt_bitacora->bind_param("sss", $cedula_usuario, $rif_colectivo, $detalle);
            $stmt_bitacora->execute();
            $stmt_bitacora->close();
            
            $mensaje = "Se registraron $contador_registrados integrantes correctamente para el colectivo {$colectivo['nombre_colectivo']}.";
            $tipo_mensaje = "success";
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Variables para mantener el estado de búsqueda
$busqueda_apoderado = isset($_POST['accion']) && $_POST['accion'] == 'buscar_apoderado' ? $_POST['cedula_busqueda'] ?? '' : '';
$busqueda_representante = isset($_POST['accion']) && $_POST['accion'] == 'buscar_representante' ? $_POST['rif_busqueda'] ?? '' : '';
$busqueda_colectivo = isset($_POST['accion']) && $_POST['accion'] == 'buscar_colectivo' ? $_POST['rif_colectivo_busqueda'] ?? '' : '';
?>

<div class="container">
    <h1 style="font-weight:900; font-family:montserrat; color:green; font-size:40px; padding:20px; text-align:left; font-size:50px;">
        <i class="zmdi zmdi-account-add"></i> 
        Registro  <span style="font-weight:700; color:black;">del sistema</span>
            
    </h1>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-<?= $tipo_mensaje == 'error' ? 'danger' : ($tipo_mensaje == 'info' ? 'warning' : 'success'); ?>">
            <?= htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <!-- Selector de acción -->
    <div class="section-container">
        <h2 class="section-title">Seleccione una acción</h2>
        <div class="field-row">
            <div class="field-col">
                <label for="accion_selector" class="field-label required">Acción</label>
                <select id="accion_selector" name="accion_selector" class="form-control">
                    <option value="">Seleccione una acción...</option>
                    <option value="registrar_natural">Registrar Persona Natural</option>
                    <option value="registrar_juridica">Registrar Persona Jurídica</option>
                    <option value="registrar_colectivo">Registrar Colectivo</option>
                    <option value="agregar_apoderado">Agregar Apoderado</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Formulario para Persona Natural -->
    <form id="form-registrar-natural" method="POST" action="" class="section-container hidden">
        <input type="hidden" name="accion" value="registrar_natural">
        <h4 class="section-title">Registrar Persona Natural</h4>
        <div class="field-row">
            <div class="field-col">
                <label for="cedula" class="field-label required">Cédula</label>
                <input type="text" name="cedula" id="cedula" placeholder="12345678" maxlength="8" class="form-control" pattern="\d{7,8}" title="Solo números, 7 u 8 dígitos" required />
            </div>
            <div class="field-col">
                <label for="primer_nombre" class="field-label required">Primer Nombre</label>
                <input type="text" name="primer_nombre" id="primer_nombre" placeholder="Ingrese primer nombre" class="form-control" required />
            </div>
            <div class="field-col">
                <label for="segundo_nombre" class="field-label">Segundo Nombre</label>
                <input type="text" name="segundo_nombre" id="segundo_nombre" placeholder="Ingrese segundo nombre" class="form-control" />
            </div>
            <div class="field-col">
                <label for="primer_apellido" class="field-label required">Primer Apellido</label>
                <input type="text" name="primer_apellido" id="primer_apellido" placeholder="Ingrese primer apellido" class="form-control" required />
            </div>
            <div class="field-col">
                <label for="segundo_apellido" class="field-label">Segundo Apellido</label>
                <input type="text" name="segundo_apellido" id="segundo_apellido" placeholder="Ingrese segundo apellido" class="form-control" />
            </div>
            <div class="field-col">
                <label for="sexo" class="field-label required">Sexo</label>
                <select name="sexo" id="sexo" class="form-control" required>
                    <option value="">Seleccione...</option>
                    <option value="M">Masculino</option>
                    <option value="F">Femenino</option>
                </select>
            </div>
            <div class="field-col">
                <label for="fecha_nacimiento" class="field-label">Fecha de Nacimiento</label>
                <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" />
            </div>
            <div class="field-col">
                <label for="telefono" class="field-label">Teléfono</label>
                <input type="tel" name="telefono" id="telefono" placeholder="0412-1234567" class="form-control" />
            </div>
            <div class="field-col" style="grid-column: 1 / -1;">
                <label for="direccion_habitacion" class="field-label">Dirección de Habitación</label>
                <textarea name="direccion_habitacion" id="direccion_habitacion" rows="2" class="form-control" placeholder="Ingrese dirección completa"></textarea>
            </div>
            <div class="field-col">
                <label for="estado_civil" class="field-label">Estado Civil</label>
                <select name="estado_civil" id="estado_civil" class="form-control">
                    <option value="">Seleccione...</option>
                    <option value="Soltero">Soltero</option>
                    <option value="Casado">Casado</option>
                    <option value="Viudo">Viudo</option>
                    <option value="Divorciado">Divorciado</option>
                    <option value="Concubinato">Concubinato</option>
                </select>
            </div>
            <div class="field-col">
                <label for="numero_hijos" class="field-label">Número de Hijos</label>
                <input type="number" name="numero_hijos" id="numero_hijos" min="0" value="0" class="form-control" />
            </div>
            <div class="field-col">
                <label for="grado_instruccion" class="field-label">Grado de Instrucción</label>
                <select name="grado_instruccion" id="grado_instruccion" class="form-control">
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
                <label for="sabe_leer" class="field-label">Sabe Leer</label>
                <select name="sabe_leer" id="sabe_leer" class="form-control">
                    <option value="">Seleccione...</option>
                    <option value="Si">Sí</option>
                    <option value="No">No</option>
                </select>
            </div>
            <div class="field-col">
                <label for="posee_ayuda_economica" class="field-label">Posee Ayuda Económica</label>
                <select name="posee_ayuda_economica" id="posee_ayuda_economica" class="form-control">
                    <option value="">Seleccione...</option>
                    <option value="Si">Sí</option>
                    <option value="No">No</option>
                </select>
            </div>
            <div class="field-col">
                <label for="trabaja_actualmente" class="field-label">Trabaja Actualmente</label>
                <select name="trabaja_actualmente" id="trabaja_actualmente" class="form-control">
                    <option value="">Seleccione...</option>
                    <option value="Si">Sí</option>
                    <option value="No">No</option>
                </select>
            </div>
            <div class="field-col">
                <label for="pertenece_comuna" class="field-label">Pertenece a Comuna</label>
                <select name="pertenece_comuna" id="pertenece_comuna" class="form-control">
                    <option value="">Seleccione...</option>
                    <option value="Si">Sí</option>
                    <option value="No">No</option>
                </select>
            </div>
            <div class="field-col" style="grid-column: 1 / -1;">
                <label for="enfermedades" class="field-label">Enfermedades</label>
                <textarea name="enfermedades" id="enfermedades" rows="2" class="form-control" placeholder="Liste las enfermedades que padece"></textarea>
            </div>
        </div>
        <div class="button-container">
            <button type="submit" class="btn btn-primary">Registrar Persona Natural</button>
            <button type="reset" class="btn btn-secondary">Limpiar</button>
        </div>
    </form>

    <!-- Formulario para Persona Jurídica -->
    <form id="form-registrar-juridica" method="POST" action="" class="section-container hidden">
        <input type="hidden" name="accion" value="registrar_juridica">
        <input type="hidden" name="activo" value="1">
        <input type="hidden" name="tipo_representacion" value="Representante_Legal">
        <h4 class="section-title">Registrar Persona Jurídica</h4>
        <div class="field-row">
            <div class="field-col">
                <label for="rif" class="field-label required">RIF</label>
                <input type="text" name="rif" id="rif" placeholder="123456789" maxlength="10" class="form-control" pattern="\d+" title="Solo números" required />
            </div>
            <div class="field-col">
                <label for="razon_social" class="field-label required">Razón Social</label>
                <input type="text" name="razon_social" id="razon_social" placeholder="Ingrese razón social" class="form-control" required />
            </div>
            <div class="field-col">
                <label for="telefono_juridica" class="field-label">Teléfono</label>
                <input type="tel" name="telefono" id="telefono_juridica" placeholder="0412-1234567" class="form-control" />
            </div>
            <div class="field-col" style="grid-column: 1 / -1;">
                <label for="direccion_habitacion_juridica" class="field-label">Dirección de Habitación</label>
                <textarea name="direccion_habitacion" id="direccion_habitacion_juridica" rows="2" class="form-control" placeholder="Ingrese dirección completa"></textarea>
            </div>
            <div class="field-col">
                <label for="estado_civil_juridica" class="field-label">Estado Civil</label>
                <select name="estado_civil" id="estado_civil_juridica" class="form-control">
                    <option value="">Seleccione...</option>
                    <option value="Soltero">Soltero</option>
                    <option value="Casado">Casado</option>
                    <option value="Viudo">Viudo</option>
                    <option value="Divorciado">Divorciado</option>
                    <option value="Concubinato">Concubinato</option>
                </select>
            </div>
            <div class="field-col">
                <label for="numero_hijos_juridica" class="field-label">Número de Hijos</label>
                <input type="number" name="numero_hijos" id="numero_hijos_juridica" min="0" value="0" class="form-control" />
            </div>
            <div class="field-col">
                <label for="grado_instruccion_juridica" class="field-label">Grado de Instrucción</label>
                <select name="grado_instruccion" id="grado_instruccion_juridica" class="form-control">
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
                <label for="sabe_leer_juridica" class="field-label">Sabe Leer</label>
                <select name="sabe_leer" id="sabe_leer_juridica" class="form-control">
                    <option value="">Seleccione...</option>
                    <option value="Si">Sí</option>
                    <option value="No">No</option>
                </select>
            </div>
            <div class="field-col">
                <label for="posee_ayuda_economica_juridica" class="field-label">Posee Ayuda Económica</label>
                <select name="posee_ayuda_economica" id="posee_ayuda_economica_juridica" class="form-control">
                    <option value="">Seleccione...</option>
                    <option value="Si">Sí</option>
                    <option value="No">No</option>
                </select>
            </div>
            <div class="field-col">
                <label for="trabaja_actualmente_juridica" class="field-label">Trabaja Actualmente</label>
                <select name="trabaja_actualmente" id="trabaja_actualmente_juridica" class="form-control">
                    <option value="">Seleccione...</option>
                    <option value="Si">Sí</option>
                    <option value="No">No</option>
                </select>
            </div>
            <div class="field-col">
                <label for="pertenece_comuna_juridica" class="field-label">Pertenece a Comuna</label>
                <select name="pertenece_comuna" id="pertenece_comuna_juridica" class="form-control">
                    <option value="">Seleccione...</option>
                    <option value="Si">Sí</option>
                    <option value="No">No</option>
                </select>
            </div>
            <div class="field-col" style="grid-column: 1 / -1;">
                <label for="enfermedades_juridica" class="field-label">Enfermedades</label>
                <textarea name="enfermedades" id="enfermedades_juridica" rows="2" class="form-control" placeholder="Liste las enfermedades que padece"></textarea>
            </div>
        </div>

        <h5>Registro de Representante Legal</h5>
        <div class="field-row">
            <div class="field-col">
                <label for="ci_representante_juridica" class="field-label required">CI del Representante Legal</label>
                <input type="text" name="ci_representante" id="ci_representante_juridica" placeholder="12345678" maxlength="8" class="form-control" pattern="\d{7,8}" title="Solo números, 7 u 8 dígitos" required />
            </div>
            <div class="field-col">
                <label for="rep_primer_nombre_juridica" class="field-label required">Primer Nombre</label>
                <input type="text" name="rep_primer_nombre" id="rep_primer_nombre_juridica" placeholder="Ingrese primer nombre" class="form-control" required />
            </div>
            <div class="field-col">
                <label for="rep_segundo_nombre_juridica" class="field-label">Segundo Nombre</label>
                <input type="text" name="rep_segundo_nombre" id="rep_segundo_nombre_juridica" placeholder="Ingrese segundo nombre" class="form-control" />
            </div>
            <div class="field-col">
                <label for="rep_primer_apellido_juridica" class="field-label required">Primer Apellido</label>
                <input type="text" name="rep_primer_apellido" id="rep_primer_apellido_juridica" placeholder="Ingrese primer apellido" class="form-control" required />
            </div>
            <div class="field-col">
                <label for="rep_segundo_apellido_juridica" class="field-label">Segundo Apellido</label>
                <input type="text" name="rep_segundo_apellido" id="rep_segundo_apellido_juridica" placeholder="Ingrese segundo apellido" class="form-control" />
            </div>
            <div class="field-col">
                <label for="rep_sexo_juridica" class="field-label required">Sexo</label>
                <select name="rep_sexo" id="rep_sexo_juridica" class="form-control" required>
                    <option value="">Seleccione...</option>
                    <option value="M">Masculino</option>
                    <option value="F">Femenino</option>
                </select>
            </div>
            <div class="field-col">
                <label for="rep_telefono_juridica" class="field-label">Teléfono</label>
                <input type="tel" name="rep_telefono" id="rep_telefono_juridica" placeholder="0412-1234567" class="form-control" />
            </div>
            <div class="field-col" style="grid-column: 1 / -1;">
                <label for="rep_direccion_juridica" class="field-label">Dirección</label>
                <textarea name="rep_direccion" id="rep_direccion_juridica" rows="2" class="form-control" placeholder="Ingrese dirección completa"></textarea>
            </div>
            <div class="field-col">
                <label for="rep_email_juridica" class="field-label">Email</label>
                <input type="email" name="rep_email" id="rep_email_juridica" placeholder="correo@ejemplo.com" class="form-control" />
            </div>
            <div class="field-col">
                <label for="rep_profesion_juridica" class="field-label">Profesión</label>
                <input type="text" name="rep_profesion" id="rep_profesion_juridica" placeholder="Ingrese profesión" class="form-control" />
            </div>
        </div>
        <div class="button-container">
            <button type="submit" class="btn btn-primary">Registrar Persona Jurídica</button>
            <button type="reset" class="btn btn-secondary">Limpiar</button>
        </div>
    </form>

    <!-- Formulario para Colectivo -->
    <form id="form-registrar-colectivo" method="POST" action="" class="section-container hidden">
        <input type="hidden" name="accion" value="registrar_colectivo">
        <h4 class="section-title">Registrar Colectivo</h4>
        <div class="field-row">
            <div class="field-col">
                <label for="rif_referente" class="field-label required">RIF/CI Referente</label>
                <input type="text" name="rif_referente" id="rif_referente" placeholder="123456789" maxlength="10" class="form-control" pattern="\d+" title="Solo números" required />
            </div>
            <div class="field-col">
                <label for="nombre_colectivo" class="field-label required">Nombre del Colectivo</label>
                <input type="text" name="nombre_colectivo" id="nombre_colectivo" placeholder="Ingrese nombre del colectivo" class="form-control" required />
            </div>
            <div class="field-col">
                <label for="telefono_colectivo" class="field-label">Teléfono</label>
                <input type="tel" name="telefono" id="telefono_colectivo" placeholder="0412-1234567" class="form-control" />
            </div>
            <div class="field-col" style="grid-column: 1 / -1;">
                <label for="direccion_habitacion_colectivo" class="field-label">Dirección de Habitación</label>
                <textarea name="direccion_habitacion" id="direccion_habitacion_colectivo" rows="2" class="form-control" placeholder="Ingrese dirección completa"></textarea>
            </div>
        </div>

        <h5>Registro de Integrantes</h5>
        <div id="integrantes-colectivo-container">
            <!-- Primer integrante (obligatorio) -->
            <div class="integrante-form section-container">
                <h5>Integrante #1</h5>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Cédula</label>
                        <input type="text" name="integrantes_cedula[]" placeholder="12345678" maxlength="8" class="form-control" pattern="\d{7,8}" title="Solo números, 7 u 8 dígitos" required />
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Primer Nombre</label>
                        <input type="text" name="integrantes_primer_nombre[]" placeholder="Ingrese primer nombre" class="form-control" required />
                    </div>
                    <div class="field-col">
                        <label class="field-label">Segundo Nombre</label>
                        <input type="text" name="integrantes_segundo_nombre[]" placeholder="Ingrese segundo nombre" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Primer Apellido</label>
                        <input type="text" name="integrantes_primer_apellido[]" placeholder="Ingrese primer apellido" class="form-control" required />
                    </div>
                    <div class="field-col">
                        <label class="field-label">Segundo Apellido</label>
                        <input type="text" name="integrantes_segundo_apellido[]" placeholder="Ingrese segundo apellido" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Sexo</label>
                        <select name="integrantes_sexo[]" class="form-control" required>
                            <option value="">Seleccione...</option>
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Fecha de Nacimiento</label>
                        <input type="date" name="integrantes_fecha_nacimiento[]" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label">Teléfono</label>
                        <input type="tel" name="integrantes_telefono[]" placeholder="0412-1234567" class="form-control" />
                    </div>
                    <div class="field-col" style="grid-column: 1 / -1;">
                        <label class="field-label">Dirección de Habitación</label>
                        <textarea name="integrantes_direccion_habitacion[]" rows="2" class="form-control" placeholder="Ingrese dirección completa"></textarea>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Estado Civil</label>
                        <select name="integrantes_estado_civil[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Soltero">Soltero</option>
                            <option value="Casado">Casado</option>
                            <option value="Viudo">Viudo</option>
                            <option value="Divorciado">Divorciado</option>
                            <option value="Concubinato">Concubinato</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Número de Hijos</label>
                        <input type="number" name="integrantes_numero_hijos[]" min="0" value="0" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label">Grado de Instrucción</label>
                        <select name="integrantes_grado_instruccion[]" class="form-control">
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
                        <label class="field-label">Sabe Leer</label>
                        <select name="integrantes_sabe_leer[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Si">Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Posee Ayuda Económica</label>
                        <select name="integrantes_posee_ayuda_economica[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Si">Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Trabaja Actualmente</label>
                        <select name="integrantes_trabaja_actualmente[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Si">Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Pertenece a Comuna</label>
                        <select name="integrantes_pertenece_comuna[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Si">Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Enfermedades</label>
                        <textarea name="integrantes_enfermedades[]" rows="2" class="form-control" placeholder="Liste las enfermedades"></textarea>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Es Referente</label>
                        <select name="integrantes_es_referente[]" class="form-control">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Cargo en el Colectivo</label>
                        <input type="text" name="integrantes_cargo_en_colectivo[]" placeholder="Ingrese cargo" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Fecha de Ingreso</label>
                        <input type="date" name="integrantes_fecha_ingreso[]" class="form-control" required />
                    </div>
                </div>
            </div>
        </div>

        <div class="button-container">
            <button type="submit" class="btn btn-primary">Registrar Colectivo</button>
            <button type="button" id="btn-agregar-integrante-colectivo" class="btn btn-secondary">Agregar Integrante</button>
        </div>
    </form>

    <!-- Formulario para Agregar Apoderado -->
    <div id="form-agregar-apoderado" class="section-container hidden">
        <h4 class="section-title">Agregar Apoderado a Persona Natural</h4>
        
        <!-- Búsqueda -->
        <form method="POST" action="">
            <input type="hidden" name="accion" value="buscar_apoderado">
            <div class="field-row">
                <div class="field-col">
                    <label for="cedula_busqueda_apoderado" class="field-label required">Cedula de la persona natural</label>
                    <input type="text" name="cedula_busqueda" id="cedula_busqueda_apoderado" placeholder="12345678" maxlength="8" class="form-control" pattern="\d{7,8}" title="Solo números, 7 u 8 dígitos" value="<?= htmlspecialchars($busqueda_apoderado); ?>" required />
                </div>
                <div class="field-col" style="align-self: end;">
                    <button type="submit" class="btn btn-primary">Buscar Persona Natural</button>
                </div>
            </div>
        </form>
        
        <?php if (!empty($busqueda_apoderado)): ?>
             <?php
             $stmt_pn = $conn->prepare("SELECT cedula, CONCAT(primer_nombre, ' ', IFNULL(segundo_nombre, ''), ' ', primer_apellido, ' ', IFNULL(segundo_apellido, '')) as nombre_completo, telefono, direccion_habitacion, id_representante, tipo_representacion FROM personas_naturales WHERE cedula = ? AND activo = 1");
             $stmt_pn->bind_param("s", $busqueda_apoderado);
             $stmt_pn->execute();
             $result_pn = $stmt_pn->get_result();

             if ($result_pn->num_rows > 0):
                 $persona = $result_pn->fetch_assoc();
             ?>
                 <div style="margin-top: 20px;">
                     <h5>Datos de la Persona Natural Encontrada:</h5>
                     <table class="table table-bordered">
                         <thead>
                             <tr>
                                 <th>Cédula</th>
                                 <th>Nombre Completo</th>
                                 <th>Teléfono</th>
                                 <th>Dirección</th>
                                 <th>Estado Apoderado</th>
                             </tr>
                         </thead>
                         <tbody>
                             <tr>
                                 <td><?= htmlspecialchars($persona['cedula']); ?></td>
                                 <td><?= htmlspecialchars($persona['nombre_completo']); ?></td>
                                 <td><?= htmlspecialchars($persona['telefono'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($persona['direccion_habitacion'] ?? 'N/A'); ?></td>
                                 <td>
                                     <?php if ($persona['id_representante'] && $persona['tipo_representacion'] == 'Apoderado'): ?>
                                         <span class="text-success">Ya tiene apoderado asignado</span>
                                     <?php else: ?>
                                         <span class="text-warning">Sin apoderado</span>
                                     <?php endif; ?>
                                 </td>
                             </tr>
                         </tbody>
                     </table>

                     <?php if ($persona['id_representante'] && $persona['tipo_representacion'] == 'Apoderado'): ?>
                         <div class="alert alert-warning">
                             Esta persona natural ya tiene un apoderado asignado. Si desea cambiarlo, puede editar la información del apoderado existente.
                         </div>
                     <?php else: ?>
                         <form method="POST" action="">
                             <input type="hidden" name="accion" value="agregar_apoderado">
                             <input type="hidden" name="identificacion_busqueda" value="<?= htmlspecialchars($persona['cedula']); ?>">
                             <input type="hidden" name="activo" value="1">
                             <input type="hidden" name="tipo" value="Apoderado">

                             <h5 style="font-family:montserrat; font-weight:700;">Datos del Apoderado</h5>
                        <div class="field-row">
                            <div class="field-col">
                                <label for="ci_representante_apoderado" class="field-label required">Cedula</label>
                                <input type="text" name="ci_representante" id="ci_representante_apoderado" placeholder="12345678" maxlength="8" class="form-control" pattern="\d{7,8}" title="Solo números, 7 u 8 dígitos" required />
                            </div>
                            <div class="field-col">
                                <label for="rep_primer_nombre_apoderado" class="field-label required">Primer Nombre</label>
                                <input type="text" name="rep_primer_nombre" id="rep_primer_nombre_apoderado" placeholder="Ingrese primer nombre" class="form-control" required />
                            </div>
                            <div class="field-col">
                                <label for="rep_segundo_nombre_apoderado" class="field-label">Segundo Nombre</label>
                                <input type="text" name="rep_segundo_nombre" id="rep_segundo_nombre_apoderado" placeholder="Ingrese segundo nombre" class="form-control" />
                            </div>
                            <div class="field-col">
                                <label for="rep_primer_apellido_apoderado" class="field-label required">Primer Apellido</label>
                                <input type="text" name="rep_primer_apellido" id="rep_primer_apellido_apoderado" placeholder="Ingrese primer apellido" class="form-control" required />
                            </div>
                            <div class="field-col">
                                <label for="rep_segundo_apellido_apoderado" class="field-label">Segundo Apellido</label>
                                <input type="text" name="rep_segundo_apellido" id="rep_segundo_apellido_apoderado" placeholder="Ingrese segundo apellido" class="form-control" />
                            </div>
                            <div class="field-col">
                                <label for="rep_sexo_apoderado" class="field-label required">Sexo</label>
                                <select name="rep_sexo" id="rep_sexo_apoderado" class="form-control" required>
                                    <option value="">Seleccione...</option>
                                    <option value="M">Masculino</option>
                                    <option value="F">Femenino</option>
                                </select>
                            </div>
                            <div class="field-col">
                                <label for="rep_telefono_apoderado" class="field-label">Teléfono</label>
                                <input type="tel" name="rep_telefono" id="rep_telefono_apoderado" placeholder="0412-1234567" class="form-control" />
                            </div>
                            <div class="field-col" style="grid-column: 1 / -1;">
                                <label for="rep_direccion_apoderado" class="field-label">Dirección</label>
                                <textarea name="rep_direccion" id="rep_direccion_apoderado" rows="2" class="form-control" placeholder="Ingrese dirección completa"></textarea>
                            </div>
                            <div class="field-col">
                                <label for="rep_email_apoderado" class="field-label">Email</label>
                                <input type="email" name="rep_email" id="rep_email_apoderado" placeholder="correo@ejemplo.com" class="form-control" />
                            </div>
                            <div class="field-col">
                                <label for="rep_profesion_apoderado" class="field-label">Profesión</label>
                                <input type="text" name="rep_profesion" id="rep_profesion_apoderado" placeholder="Ingrese profesión" class="form-control" />
                            </div>
                        </div>
                            <div class="button-container">
                                <button type="submit" class="btn btn-primary">Asignar Apoderado</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="margin-top: 20px;" class="alert alert-warning">
                    No se encontró ninguna persona natural con la cédula <?= htmlspecialchars($busqueda_apoderado); ?>
                </div>
            <?php endif; ?>
            <?php $stmt_pn->close(); ?>
        <?php endif; ?>
    </div>

    <!-- Formulario para Agregar Representante Legal -->
    <div id="form-agregar-representante-legal" class="section-container hidden">
        <h4 class="section-title">Agregar Representante Legal a Persona Jurídica</h4>
        
        <!-- Búsqueda -->
        <form method="POST" action="">
            <input type="hidden" name="accion" value="buscar_representante">
            <div class="field-row">
                <div class="field-col">
                    <label for="rif_busqueda_representante" class="field-label required">RIF de la Persona Jurídica</label>
                    <input type="text" name="rif_busqueda" id="rif_busqueda_representante" placeholder="123456789" maxlength="10" class="form-control" pattern="\d+" title="Solo números" value="<?= htmlspecialchars($busqueda_representante); ?>" required />
                </div>
                <div class="field-col" style="align-self: end;">
                    <button type="submit" class="btn btn-primary">Buscar Persona Jurídica</button>
                </div>
            </div>
        </form>
        
        <?php if (!empty($busqueda_representante)): ?>
            <?php
            $stmt_pj = $conn->prepare("SELECT rif, razon_social FROM personas_juridicas WHERE rif = ? AND activo = 1");
            $stmt_pj->bind_param("s", $busqueda_representante);
            $stmt_pj->execute();
            $result_pj = $stmt_pj->get_result();
            
            if ($result_pj->num_rows > 0):
                $persona = $result_pj->fetch_assoc();
            ?>
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="agregar_representante_legal">
                    <input type="hidden" name="identificacion_busqueda" value="<?= htmlspecialchars($persona['rif']); ?>">
                    
                    <div style="margin-top: 20px;">
                        <h5>Datos de la Persona Jurídica Encontrada:</h5>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>RIF</th>
                                    <th>Razón Social</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?= htmlspecialchars($persona['rif']); ?></td>
                                    <td><?= htmlspecialchars($persona['razon_social']); ?></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <h5>Datos del Representante Legal</h5>
                        <div class="field-row">
                            <div class="field-col">
                                <label for="ci_representante_legal" class="field-label required">CI del Representante Legal</label>
                                <input type="text" name="ci_representante" id="ci_representante_legal" placeholder="12345678" maxlength="8" class="form-control" pattern="\d{7,8}" title="Solo números, 7 u 8 dígitos" required />
                            </div>
                            <div class="field-col">
                                <label for="rep_primer_nombre_representante" class="field-label required">Primer Nombre</label>
                                <input type="text" name="rep_primer_nombre" id="rep_primer_nombre_representante" placeholder="Ingrese primer nombre" class="form-control" required />
                            </div>
                            <div class="field-col">
                                <label for="rep_segundo_nombre_representante" class="field-label">Segundo Nombre</label>
                                <input type="text" name="rep_segundo_nombre" id="rep_segundo_nombre_representante" placeholder="Ingrese segundo nombre" class="form-control" />
                            </div>
                            <div class="field-col">
                                <label for="rep_primer_apellido_representante" class="field-label required">Primer Apellido</label>
                                <input type="text" name="rep_primer_apellido" id="rep_primer_apellido_representante" placeholder="Ingrese primer apellido" class="form-control" required />
                            </div>
                            <div class="field-col">
                                <label for="rep_segundo_apellido_representante" class="field-label">Segundo Apellido</label>
                                <input type="text" name="rep_segundo_apellido" id="rep_segundo_apellido_representante" placeholder="Ingrese segundo apellido" class="form-control" />
                            </div>
                            <div class="field-col">
                                <label for="rep_sexo_representante" class="field-label required">Sexo</label>
                                <select name="rep_sexo" id="rep_sexo_representante" class="form-control" required>
                                    <option value="">Seleccione...</option>
                                    <option value="M">Masculino</option>
                                    <option value="F">Femenino</option>
                                </select>
                            </div>
                            <div class="field-col">
                                <label for="rep_telefono_representante" class="field-label">Teléfono</label>
                                <input type="tel" name="rep_telefono" id="rep_telefono_representante" placeholder="0412-1234567" class="form-control" />
                            </div>
                            <div class="field-col" style="grid-column: 1 / -1;">
                                <label for="rep_direccion_representante" class="field-label">Dirección</label>
                                <textarea name="rep_direccion" id="rep_direccion_representante" rows="2" class="form-control" placeholder="Ingrese dirección completa"></textarea>
                            </div>
                            <div class="field-col">
                                <label for="rep_email_representante" class="field-label">Email</label>
                                <input type="email" name="rep_email" id="rep_email_representante" placeholder="correo@ejemplo.com" class="form-control" />
                            </div>
                            <div class="field-col">
                                <label for="rep_profesion_representante" class="field-label">Profesión</label>
                                <input type="text" name="rep_profesion" id="rep_profesion_representante" placeholder="Ingrese profesión" class="form-control" />
                            </div>
                        </div>
                        <div class="button-container">
                            <button type="submit" class="btn btn-primary">Asignar Representante Legal</button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div style="margin-top: 20px;" class="alert alert-warning">
                    No se encontró ninguna persona jurídica con el RIF <?= htmlspecialchars($busqueda_representante); ?>
                </div>
            <?php endif; ?>
            <?php $stmt_pj->close(); ?>
        <?php endif; ?>
    </div>

    <!-- Formulario para Integrantes de Colectivo -->
    <div id="form-integrantes-colectivo" class="section-container hidden">
        <h4 class="section-title">Integrantes del Colectivo</h4>
        
        <!-- Búsqueda del colectivo -->
        <form method="POST" action="">
            <input type="hidden" name="accion" value="buscar_colectivo">
            <div class="field-row">
                <div class="field-col">
                    <label for="rif_colectivo_integrantes" class="field-label required">RIF/CI del Colectivo</label>
                    <input type="text" name="rif_colectivo_busqueda" id="rif_colectivo_integrantes" placeholder="123456789" maxlength="10" class="form-control" pattern="\d+" title="Solo números" value="<?= htmlspecialchars($busqueda_colectivo); ?>" required />
                </div>
                <div class="field-col" style="align-self: end;">
                    <button type="submit" class="btn btn-primary">Buscar Colectivo</button>
                </div>
            </div>
        </form>
        
        <?php if (!empty($busqueda_colectivo)): ?>
            <?php
            $stmt_colectivo = $conn->prepare("SELECT rif_o_ci_referente, nombre_colectivo FROM colectivos WHERE rif_o_ci_referente = ? AND activo = 1");
            $stmt_colectivo->bind_param("s", $busqueda_colectivo);
            $stmt_colectivo->execute();
            $result_colectivo = $stmt_colectivo->get_result();
            
            if ($result_colectivo->num_rows > 0):
                $colectivo = $result_colectivo->fetch_assoc();
            ?>
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="registrar_integrantes">
                    <input type="hidden" name="rif_colectivo" value="<?= htmlspecialchars($colectivo['rif_o_ci_referente']); ?>">
                    
                    <div style="margin-top: 20px;">
                        <h5>Datos del Colectivo Encontrado:</h5>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>RIF/CI</th>
                                    <th>Nombre del Colectivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?= htmlspecialchars($colectivo['rif_o_ci_referente']); ?></td>
                                    <td><?= htmlspecialchars($colectivo['nombre_colectivo']); ?></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <h5>Registro de Integrantes</h5>
                        
                        <div id="integrantes-container">
                            <!-- Primer integrante (obligatorio) -->
                            <div class="integrante-form section-container">
                                <h5>Integrante #1</h5>
                                <div class="field-row">
                                    <div class="field-col">
                                        <label class="field-label required">Cédula</label>
                                        <input type="text" name="integrantes_cedula[]" placeholder="12345678" maxlength="8" class="form-control" pattern="\d{7,8}" title="Solo números, 7 u 8 dígitos" required />
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label required">Primer Nombre</label>
                                        <input type="text" name="integrantes_primer_nombre[]" placeholder="Ingrese primer nombre" class="form-control" required />
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label">Segundo Nombre</label>
                                        <input type="text" name="integrantes_segundo_nombre[]" placeholder="Ingrese segundo nombre" class="form-control" />
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label required">Primer Apellido</label>
                                        <input type="text" name="integrantes_primer_apellido[]" placeholder="Ingrese primer apellido" class="form-control" required />
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label">Segundo Apellido</label>
                                        <input type="text" name="integrantes_segundo_apellido[]" placeholder="Ingrese segundo apellido" class="form-control" />
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label required">Sexo</label>
                                        <select name="integrantes_sexo[]" class="form-control" required>
                                            <option value="">Seleccione...</option>
                                            <option value="M">Masculino</option>
                                            <option value="F">Femenino</option>
                                        </select>
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label">Fecha de Nacimiento</label>
                                        <input type="date" name="integrantes_fecha_nacimiento[]" class="form-control" />
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label">Teléfono</label>
                                        <input type="tel" name="integrantes_telefono[]" placeholder="0412-1234567" class="form-control" />
                                    </div>
                                    <div class="field-col" style="grid-column: 1 / -1;">
                                        <label class="field-label">Dirección de Habitación</label>
                                        <textarea name="integrantes_direccion_habitacion[]" rows="2" class="form-control" placeholder="Ingrese dirección completa"></textarea>
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label">Estado Civil</label>
                                        <select name="integrantes_estado_civil[]" class="form-control">
                                            <option value="">Seleccione...</option>
                                            <option value="Soltero">Soltero</option>
                                            <option value="Casado">Casado</option>
                                            <option value="Viudo">Viudo</option>
                                            <option value="Divorciado">Divorciado</option>
                                            <option value="Concubinato">Concubinato</option>
                                        </select>
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label">Número de Hijos</label>
                                        <input type="number" name="integrantes_numero_hijos[]" min="0" value="0" class="form-control" />
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label">Grado de Instrucción</label>
                                        <select name="integrantes_grado_instruccion[]" class="form-control">
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
                                        <label class="field-label">Sabe Leer</label>
                                        <select name="integrantes_sabe_leer[]" class="form-control">
                                            <option value="">Seleccione...</option>
                                            <option value="Si">Sí</option>
                                            <option value="No">No</option>
                                        </select>
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label">Posee Ayuda Económica</label>
                                        <select name="integrantes_posee_ayuda_economica[]" class="form-control">
                                            <option value="">Seleccione...</option>
                                            <option value="Si">Sí</option>
                                            <option value="No">No</option>
                                        </select>
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label">Trabaja Actualmente</label>
                                        <select name="integrantes_trabaja_actualmente[]" class="form-control">
                                            <option value="">Seleccione...</option>
                                            <option value="Si">Sí</option>
                                            <option value="No">No</option>
                                        </select>
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label">Pertenece a Comuna</label>
                                        <select name="integrantes_pertenece_comuna[]" class="form-control">
                                            <option value="">Seleccione...</option>
                                            <option value="Si">Sí</option>
                                            <option value="No">No</option>
                                        </select>
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label">Enfermedades</label>
                                        <textarea name="integrantes_enfermedades[]" rows="2" class="form-control" placeholder="Liste las enfermedades"></textarea>
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label">Es Referente</label>
                                        <select name="integrantes_es_referente[]" class="form-control">
                                            <option value="0">No</option>
                                            <option value="1">Sí</option>
                                        </select>
                                    </div>
                                    <div class="field-col">
                                        <label class="field-label">Cargo en el Colectivo</label>
                                        <input type="text" name="integrantes_cargo_en_colectivo[]" placeholder="Ingrese cargo" class="form-control" />
                                    </div>
                                   
                                </div>
                            </div>
                        </div>
                        
                        <div class="button-container">
                            <button type="submit" class="btn btn-primary">Registrar Integrantes</button>
                            <button type="button" id="btn-agregar-integrante" class="btn btn-primary">Agregar otro integrante</button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div style="margin-top: 20px;" class="alert alert-warning">
                    No se encontró ningún colectivo con el RIF/CI <?= htmlspecialchars($busqueda_colectivo); ?>
                </div>
            <?php endif; ?>
            <?php $stmt_colectivo->close(); ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Funcionalidad para mostrar el formulario correspondiente
document.addEventListener('DOMContentLoaded', function() {
    const selectorAccion = document.getElementById('accion_selector');
    
    function ocultarTodosFormularios() {
        document.getElementById('form-registrar-natural').classList.add('hidden');
        document.getElementById('form-registrar-juridica').classList.add('hidden');
        document.getElementById('form-registrar-colectivo').classList.add('hidden');
        document.getElementById('form-agregar-apoderado').classList.add('hidden');
    }
    
    if (selectorAccion) {
        selectorAccion.addEventListener('change', function() {
            ocultarTodosFormularios();
            
            const accion = this.value;
            
            if (accion === 'registrar_natural') {
                document.getElementById('form-registrar-natural').classList.remove('hidden');
            } else if (accion === 'registrar_juridica') {
                document.getElementById('form-registrar-juridica').classList.remove('hidden');
            } else if (accion === 'registrar_colectivo') {
                document.getElementById('form-registrar-colectivo').classList.remove('hidden');
            } else if (accion === 'agregar_apoderado') {
                document.getElementById('form-agregar-apoderado').classList.remove('hidden');
            }
        });
        
        if (selectorAccion.value) {
            selectorAccion.dispatchEvent(new Event('change'));
        }
    }
    
    // Botón para agregar más integrantes en el formulario de colectivo
    const btnAgregarIntegranteColectivo = document.getElementById('btn-agregar-integrante-colectivo');
    if (btnAgregarIntegranteColectivo) {
        let contadorIntegrantesColectivo = 1;

        btnAgregarIntegranteColectivo.addEventListener('click', function() {
            const container = document.getElementById('integrantes-colectivo-container');
            const nuevoIntegrante = document.createElement('div');
            nuevoIntegrante.className = 'integrante-form section-container';
            nuevoIntegrante.innerHTML = `
                <h5>Integrante #${contadorIntegrantesColectivo + 1} <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.integrante-form').remove()">Eliminar</button></h5>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Cédula</label>
                        <input type="text" name="integrantes_cedula[]" placeholder="12345678" maxlength="8" class="form-control" pattern="\\d{7,8}" title="Solo números, 7 u 8 dígitos" required />
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Primer Nombre</label>
                        <input type="text" name="integrantes_primer_nombre[]" placeholder="Ingrese primer nombre" class="form-control" required />
                    </div>
                    <div class="field-col">
                        <label class="field-label">Segundo Nombre</label>
                        <input type="text" name="integrantes_segundo_nombre[]" placeholder="Ingrese segundo nombre" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Primer Apellido</label>
                        <input type="text" name="integrantes_primer_apellido[]" placeholder="Ingrese primer apellido" class="form-control" required />
                    </div>
                    <div class="field-col">
                        <label class="field-label">Segundo Apellido</label>
                        <input type="text" name="integrantes_segundo_apellido[]" placeholder="Ingrese segundo apellido" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Sexo</label>
                        <select name="integrantes_sexo[]" class="form-control" required>
                            <option value="">Seleccione...</option>
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Fecha de Nacimiento</label>
                        <input type="date" name="integrantes_fecha_nacimiento[]" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label">Teléfono</label>
                        <input type="tel" name="integrantes_telefono[]" placeholder="0412-1234567" class="form-control" />
                    </div>
                    <div class="field-col" style="grid-column: 1 / -1;">
                        <label class="field-label">Dirección de Habitación</label>
                        <textarea name="integrantes_direccion_habitacion[]" rows="2" class="form-control" placeholder="Ingrese dirección completa"></textarea>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Estado Civil</label>
                        <select name="integrantes_estado_civil[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Soltero">Soltero</option>
                            <option value="Casado">Casado</option>
                            <option value="Viudo">Viudo</option>
                            <option value="Divorciado">Divorciado</option>
                            <option value="Concubinato">Concubinato</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Número de Hijos</label>
                        <input type="number" name="integrantes_numero_hijos[]" min="0" value="0" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label">Grado de Instrucción</label>
                        <select name="integrantes_grado_instruccion[]" class="form-control">
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
                        <label class="field-label">Sabe Leer</label>
                        <select name="integrantes_sabe_leer[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Si">Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Posee Ayuda Económica</label>
                        <select name="integrantes_posee_ayuda_economica[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Si">Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Trabaja Actualmente</label>
                        <select name="integrantes_trabaja_actualmente[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Si">Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Pertenece a Comuna</label>
                        <select name="integrantes_pertenece_comuna[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Si">Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Enfermedades</label>
                        <textarea name="integrantes_enfermedades[]" rows="2" class="form-control" placeholder="Liste las enfermedades"></textarea>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Es Referente</label>
                        <select name="integrantes_es_referente[]" class="form-control">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Cargo en el Colectivo</label>
                        <input type="text" name="integrantes_cargo_en_colectivo[]" placeholder="Ingrese cargo" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Fecha de Ingreso</label>
                        <input type="date" name="integrantes_fecha_ingreso[]" class="form-control" required />
                    </div>
                </div>
            `;
            container.appendChild(nuevoIntegrante);
            contadorIntegrantesColectivo++;
        });
    }

    // Botón para agregar más integrantes en el formulario separado
    const btnAgregarIntegrante = document.getElementById('btn-agregar-integrante');
    if (btnAgregarIntegrante) {
        let contadorIntegrantes = 1;

        btnAgregarIntegrante.addEventListener('click', function() {
            const container = document.getElementById('integrantes-container');
            const nuevoIntegrante = document.createElement('div');
            nuevoIntegrante.className = 'integrante-form section-container';
            nuevoIntegrante.innerHTML = `
                <h5>Integrante #${contadorIntegrantes + 1} <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.integrante-form').remove()">Eliminar</button></h5>
                <div class="field-row">
                    <div class="field-col">
                        <label class="field-label required">Cédula</label>
                        <input type="text" name="integrantes_cedula[]" placeholder="12345678" maxlength="8" class="form-control" pattern="\\d{7,8}" title="Solo números" required />
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Primer Nombre</label>
                        <input type="text" name="integrantes_primer_nombre[]" placeholder="Ingrese primer nombre" class="form-control" required />
                    </div>
                    <div class="field-col">
                        <label class="field-label">Segundo Nombre</label>
                        <input type="text" name="integrantes_segundo_nombre[]" placeholder="Ingrese segundo nombre" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Primer Apellido</label>
                        <input type="text" name="integrantes_primer_apellido[]" placeholder="Ingrese primer apellido" class="form-control" required />
                    </div>
                    <div class="field-col">
                        <label class="field-label">Segundo Apellido</label>
                        <input type="text" name="integrantes_segundo_apellido[]" placeholder="Ingrese segundo apellido" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Sexo</label>
                        <select name="integrantes_sexo[]" class="form-control" required>
                            <option value="">Seleccione...</option>
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Fecha de Nacimiento</label>
                        <input type="date" name="integrantes_fecha_nacimiento[]" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label">Teléfono</label>
                        <input type="tel" name="integrantes_telefono[]" placeholder="0412-1234567" class="form-control" />
                    </div>
                    <div class="field-col" style="grid-column: 1 / -1;">
                        <label class="field-label">Dirección</label>
                        <textarea name="integrantes_direccion_habitacion[]" rows="2" class="form-control"></textarea>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Estado Civil</label>
                        <select name="integrantes_estado_civil[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Soltero">Soltero</option>
                            <option value="Casado">Casado</option>
                            <option value="Viudo">Viudo</option>
                            <option value="Divorciado">Divorciado</option>
                            <option value="Concubinato">Concubinato</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Número de Hijos</label>
                        <input type="number" name="integrantes_numero_hijos[]" min="0" value="0" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label">Grado de Instrucción</label>
                        <select name="integrantes_grado_instruccion[]" class="form-control">
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
                        <label class="field-label">Sabe Leer</label>
                        <select name="integrantes_sabe_leer[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Si">Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Posee Ayuda Económica</label>
                        <select name="integrantes_posee_ayuda_economica[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Si">Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Trabaja Actualmente</label>
                        <select name="integrantes_trabaja_actualmente[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Si">Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Pertenece a Comuna</label>
                        <select name="integrantes_pertenece_comuna[]" class="form-control">
                            <option value="">Seleccione...</option>
                            <option value="Si">Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Enfermedades</label>
                        <textarea name="integrantes_enfermedades[]" rows="2" class="form-control"></textarea>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Es Referente</label>
                        <select name="integrantes_es_referente[]" class="form-control">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                        </select>
                    </div>
                    <div class="field-col">
                        <label class="field-label">Cargo</label>
                        <input type="text" name="integrantes_cargo_en_colectivo[]" class="form-control" />
                    </div>
                    <div class="field-col">
                        <label class="field-label required">Fecha de Ingreso</label>
                        <input type="date" name="integrantes_fecha_ingreso[]" class="form-control" required />
                    </div>
                </div>
            `;
            container.appendChild(nuevoIntegrante);
            contadorIntegrantes++;
        });
    }
});
</script>

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