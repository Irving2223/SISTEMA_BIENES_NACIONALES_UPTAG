<?php

// Configuración de logging
ini_set('log_errors', 1);
ini_set('error_log', 'bot_errors.log');
error_reporting(E_ALL);

// Remover límite de tiempo de ejecución
ini_set('max_execution_time', 0);

// Conexión a la base de datos
$host = "localhost";
$user = "root";
$pass = "";
$db = "bd_INTI";

$conn = mysqli_connect($host, $user, $pass, $db);

// Verificar conexión a la BD
if (!$conn) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Configuración del bot
$BOT_TOKEN = '8439056768:AAFfXBOB8Vxz-lQ2MVzJCnYu8_UxmKav4OY'; // Reemplaza con tu token real
$API_URL = "https://api.telegram.org/bot$BOT_TOKEN/";
// Estados de la conversación
define('SELECTING_ACTION', 0);
define('SELECTING_IDENTIFICATION', 1);
define('SELECTING_DATE_RANGE', 2);

// Almacenamiento de estados de conversación (en memoria)
$user_states = [];
$user_data = [];

// IDs de Telegram permitidos
$ALLOWED_USER_IDS = [
    1796586571, // IRVING COELLO
    5533587155, // RICHARD MOLINA
    1994641948, // DIXON VELIZ
    5482898999
];
// Función para hacer peticiones HTTP
function makeRequest($url, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Función para enviar mensajes
function sendMessage($chat_id, $text, $reply_markup = null) {
    global $API_URL;

    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    if ($reply_markup) {
        $data['reply_markup'] = $reply_markup;
    }

    return makeRequest($API_URL . 'sendMessage', $data);
}

// Función para editar mensajes
function editMessageText($chat_id, $message_id, $text, $reply_markup = null) {
    global $API_URL;

    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    if ($reply_markup) {
        $data['reply_markup'] = $reply_markup;
    }

    return makeRequest($API_URL . 'editMessageText', $data);
}

// Función para obtener actualizaciones
function getUpdates($offset = null) {
    global $API_URL;

    $data = [];
    if ($offset) {
        $data['offset'] = $offset;
    }

    return makeRequest($API_URL . 'getUpdates', $data);
}

// Verificar si el usuario está permitido
function isUserAllowed($user_id) {
    global $ALLOWED_USER_IDS;
    return in_array($user_id, $ALLOWED_USER_IDS);
}

// Obtener la hora actual de Venezuela
function getVenezuelaTime() {
    date_default_timezone_set('America/Caracas');
    return date('Y-m-d H:i:s');
}

// Obtener la última actualización de la base de datos
function getLastDbUpdate() {
    global $conn;

    $queries = [
        "SELECT MAX(creado_en) as ultima FROM personas_naturales",
        "SELECT MAX(creado_en) as ultima FROM personas_juridicas",
        "SELECT MAX(creado_en) as ultima FROM colectivos",
        "SELECT MAX(creado_en) as ultima FROM solicitudes",
        "SELECT MAX(fecha_accion) as ultima FROM bitacora"
    ];

    $last_update = null;
    foreach ($queries as $query) {
        $result = mysqli_query($conn, $query);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            if ($row && $row['ultima']) {
                if ($last_update === null || $row['ultima'] > $last_update) {
                    $last_update = $row['ultima'];
                }
            }
        }
    }

    return $last_update ?: getVenezuelaTime();
}

// Agregar información de actualización al mensaje
function addUpdateInfo($message) {
    $last_update = getLastDbUpdate();
    $update_str = date('d/m/Y H:i:s', strtotime($last_update));
    return $message . "\n\n🕒 Base de datos actualizada hasta: {$update_str} (Hora Venezuela)";
}

// Comando /start
function handleStart($chat_id, $user) {
    if (!isUserAllowed($user['id'])) {
        sendMessage($chat_id, "❌ No tienes permiso para usar este bot.");
        return;
    }

    $welcome_message = "Hola {$user['first_name']}! 👋\n\nSoy un bot para consultar información del sistema INTI.\n\nSelecciona una opción:";

    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🔍 Buscar Solicitante', 'callback_data' => 'solicitantes']],
            [['text' => '📋 Consultar Solicitudes', 'callback_data' => 'solicitudes']],
            [['text' => '📊 Generar Reportes', 'callback_data' => 'reportes']],
            [['text' => 'ℹ️ Información del Sistema', 'callback_data' => 'info']]
        ]
    ];

    sendMessage($chat_id, addUpdateInfo($welcome_message), $keyboard);
}

// Manejar botones inline
function handleCallback($callback_query) {
    global $user_states, $user_data;

    $chat_id = $callback_query['message']['chat']['id'];
    $message_id = $callback_query['message']['message_id'];
    $data = $callback_query['data'];
    $user = $callback_query['from'];
    $user_id = $user['id'];

    if (!isUserAllowed($user_id)) {
        editMessageText($chat_id, $message_id, "❌ No tienes permiso para usar este bot.");
        return;
    }

    if ($data == 'solicitantes') {
        editMessageText($chat_id, $message_id, addUpdateInfo("🔍 Por favor, ingresa la cédula o RIF del solicitante que deseas buscar:"));
        $user_states[$user_id] = SELECTING_IDENTIFICATION;
        $user_data[$user_id]['action'] = 'solicitantes';
    } elseif ($data == 'solicitudes') {
        editMessageText($chat_id, $message_id, addUpdateInfo("📋 Por favor, ingresa la cédula o RIF para consultar sus solicitudes:"));
        $user_states[$user_id] = SELECTING_IDENTIFICATION;
        $user_data[$user_id]['action'] = 'solicitudes';
    } elseif ($data == 'reportes') {
        $hoy = date('d/m/Y');
        $hace_30_dias = date('d/m/Y', strtotime('-30 days'));
        $rango_fechas = "{$hace_30_dias} - {$hoy}";

        editMessageText($chat_id, $message_id, addUpdateInfo("📊 Por favor, ingresa el rango de fechas para generar el reporte.\nFormato: DD/MM/YYYY - DD/MM/YYYY\n\nEjemplo: {$rango_fechas}\n\nPuedes copiar y pegar este rango: {$rango_fechas}"));
        $user_states[$user_id] = SELECTING_DATE_RANGE;
        $user_data[$user_id]['action'] = 'reportes';
    } elseif ($data == 'info') {
        handleInfo($chat_id, $message_id);
    } elseif ($data == 'menu') {
        handleMenu($chat_id, $message_id, $user);
        unset($user_states[$user_id]);
        unset($user_data[$user_id]);
    }
}

// Información del sistema
function handleInfo($chat_id, $message_id) {
    global $conn;

    $response = "ℹ️ Información del Sistema INTI:\n\n";

    try {
        // Personas naturales
        $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM personas_naturales WHERE activo = 1");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $response .= "👤 Personas naturales: {$row['total']}\n";
            mysqli_free_result($result);
        }

        // Personas jurídicas
        $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM personas_juridicas WHERE activo = 1");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $response .= "🏢 Personas jurídicas: {$row['total']}\n";
            mysqli_free_result($result);
        }

        // Colectivos
        $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM colectivos WHERE activo = 1");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $response .= "👥 Colectivos: {$row['total']}\n";
            mysqli_free_result($result);
        }

        // Total de solicitudes
        $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM solicitudes");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $response .= "📋 Total de solicitudes: {$row['total']}\n";
            mysqli_free_result($result);
        }

        // Última solicitud
        $result = mysqli_query($conn, "SELECT MAX(fecha_solicitud) as ultima FROM solicitudes");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $ultima = $row['ultima'] ? date('d/m/Y', strtotime($row['ultima'])) : 'N/A';
            $response .= "📅 Última solicitud registrada: {$ultima}\n";
            mysqli_free_result($result);
        }

    } catch (Exception $e) {
        $response = "❌ Error al obtener información del sistema: " . $e->getMessage();
    }

    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🔙 Volver al Menú Principal', 'callback_data' => 'menu']]
        ]
    ];

    editMessageText($chat_id, $message_id, addUpdateInfo($response), $keyboard);
}

// Volver al menú principal
function handleMenu($chat_id, $message_id, $user) {
    $welcome_message = "Hola {$user['first_name']}! 👋\n\nSoy un bot para consultar información del sistema INTI.\n\nSelecciona una opción:";

    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🔍 Buscar Solicitante', 'callback_data' => 'solicitantes']],
            [['text' => '📋 Consultar Solicitudes', 'callback_data' => 'solicitudes']],
            [['text' => '📊 Generar Reportes', 'callback_data' => 'reportes']],
            [['text' => 'ℹ️ Información del Sistema', 'callback_data' => 'info']]
        ]
    ];

    editMessageText($chat_id, $message_id, addUpdateInfo($welcome_message), $keyboard);
}

// Buscar solicitante por cédula/RIF
function buscarSolicitante($chat_id, $identificacion) {
    global $conn;

    $response = "🔍 Resultados para: {$identificacion}\n\n";
    $encontrado = false;

    try {
        // Persona Natural
        $stmt = mysqli_prepare($conn, "
            SELECT pn.*, r.primer_nombre as rep_nombre, r.primer_apellido as rep_apellido,
                   r.telefono as rep_telefono, r.email as rep_email
            FROM personas_naturales pn
            LEFT JOIN representantes r ON pn.id_representante = r.id_representante
            WHERE pn.cedula = ? AND pn.activo = 1
        ");
        mysqli_stmt_bind_param($stmt, "s", $identificacion);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($persona_natural = mysqli_fetch_assoc($result)) {
            $encontrado = true;
            $response .= "👤 Persona Natural:\n";
            $response .= "   📝 Nombre: {$persona_natural['primer_nombre']} {$persona_natural['segundo_nombre']} {$persona_natural['primer_apellido']} {$persona_natural['segundo_apellido']}\n";
            $response .= "   🆔 Cédula: {$persona_natural['cedula']}\n";
            $response .= "   📞 Teléfono: {$persona_natural['telefono']}\n";
            $response .= "   🏠 Dirección: {$persona_natural['direccion_habitacion']}\n";
            $response .= "   👫 Estado Civil: {$persona_natural['estado_civil']}\n";
            $response .= "   👶 N° Hijos: {$persona_natural['numero_hijos']}\n";
            $response .= "   🎓 Grado Instrucción: " . str_replace('_', ' ', $persona_natural['grado_instruccion']) . "\n";
            $response .= "   📖 Sabe Leer: {$persona_natural['sabe_leer']}\n";
            $response .= "   💰 Ayuda Económica: {$persona_natural['posee_ayuda_economica']}\n";
            $response .= "   💼 Trabaja: {$persona_natural['trabaja_actualmente']}\n";
            $response .= "   🏘️ Pertenece a Comuna: {$persona_natural['pertenece_comuna']}\n";
            $response .= "   🏥 Enfermedades: " . ($persona_natural['enfermedades'] ?: 'Ninguna') . "\n";

            if ($persona_natural['rep_nombre']) {
                $response .= "   👔 Representante: {$persona_natural['rep_nombre']} {$persona_natural['rep_apellido']}\n";
                $response .= "   📞 Teléfono Representante: {$persona_natural['rep_telefono']}\n";
                $response .= "   📧 Email Representante: {$persona_natural['rep_email']}\n";
            }
            $response .= "\n";
        }
        mysqli_stmt_close($stmt);

        // Persona Jurídica
        $stmt = mysqli_prepare($conn, "
            SELECT pj.*, r.primer_nombre as rep_nombre, r.primer_apellido as rep_apellido,
                   r.telefono as rep_telefono, r.email as rep_email, r.profesion as rep_profesion
            FROM personas_juridicas pj
            LEFT JOIN representantes r ON pj.id_representante = r.id_representante
            WHERE pj.rif = ? AND pj.activo = 1
        ");
        mysqli_stmt_bind_param($stmt, "s", $identificacion);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($persona_juridica = mysqli_fetch_assoc($result)) {
            $encontrado = true;
            $response .= "🏢 Persona Jurídica:\n";
            $response .= "   📝 Razón Social: {$persona_juridica['razon_social']}\n";
            $response .= "   🆔 RIF: {$persona_juridica['rif']}\n";
            $response .= "   📞 Teléfono: {$persona_juridica['telefono']}\n";
            $response .= "   🏠 Dirección: {$persona_juridica['direccion_habitacion']}\n";
            $response .= "   👫 Estado Civil: {$persona_juridica['estado_civil']}\n";
            $response .= "   👶 N° Hijos: {$persona_juridica['numero_hijos']}\n";
            $response .= "   🎓 Grado Instrucción: " . str_replace('_', ' ', $persona_juridica['grado_instruccion']) . "\n";
            $response .= "   📖 Sabe Leer: {$persona_juridica['sabe_leer']}\n";
            $response .= "   💰 Ayuda Económica: {$persona_juridica['posee_ayuda_economica']}\n";
            $response .= "   💼 Trabaja: {$persona_juridica['trabaja_actualmente']}\n";
            $response .= "   🏘️ Pertenece a Comuna: {$persona_juridica['pertenece_comuna']}\n";
            $response .= "   🏥 Enfermedades: " . ($persona_juridica['enfermedades'] ?: 'Ninguna') . "\n";

            if ($persona_juridica['rep_nombre']) {
                $response .= "   👔 Representante Legal: {$persona_juridica['rep_nombre']} {$persona_juridica['rep_apellido']}\n";
                $response .= "   📞 Teléfono Representante: {$persona_juridica['rep_telefono']}\n";
                $response .= "   📧 Email Representante: {$persona_juridica['rep_email']}\n";
                $response .= "   🎓 Profesión Representante: {$persona_juridica['rep_profesion']}\n";
            }
            $response .= "\n";
        }
        mysqli_stmt_close($stmt);

        // Colectivo
        $stmt = mysqli_prepare($conn, "SELECT * FROM colectivos WHERE rif_o_ci_referente = ? AND activo = 1");
        mysqli_stmt_bind_param($stmt, "s", $identificacion);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($colectivo = mysqli_fetch_assoc($result)) {
            $encontrado = true;
            $response .= "👥 Colectivo:\n";
            $response .= "   📝 Nombre: {$colectivo['nombre_colectivo']}\n";
            $response .= "   🆔 Referente: {$colectivo['rif_o_ci_referente']}\n";
            $response .= "   📞 Teléfono: {$colectivo['telefono']}\n";
            $response .= "   👥 Integrantes: {$colectivo['numero_integrantes']}\n";
            $response .= "   🏠 Dirección: {$colectivo['direccion_habitacion']}\n\n";

            // Integrantes del colectivo
            $stmt_int = mysqli_prepare($conn, "SELECT * FROM colectivo_integrantes WHERE rif_o_ci_colectivo = ? AND activo = 1");
            mysqli_stmt_bind_param($stmt_int, "s", $identificacion);
            mysqli_stmt_execute($stmt_int);
            $result_int = mysqli_stmt_get_result($stmt_int);

            if (mysqli_num_rows($result_int) > 0) {
                $response .= "   👤 Integrantes:\n";
                $i = 1;
                while ($integrante = mysqli_fetch_assoc($result_int)) {
                    $response .= "      {$i}. {$integrante['primer_nombre']} {$integrante['segundo_nombre']} {$integrante['primer_apellido']} {$integrante['segundo_apellido']}\n";
                    $response .= "         🆔 Cédula: {$integrante['cedula']}\n";
                    $response .= "         📞 Teléfono: {$integrante['telefono']}\n";
                    $response .= "         👫 Sexo: " . ($integrante['sexo'] == 'M' ? 'Masculino' : ($integrante['sexo'] == 'F' ? 'Femenino' : 'Otro')) . "\n";
                    $response .= "         🎂 Fecha Nacimiento: {$integrante['fecha_nacimiento']}\n";
                    if ($integrante['es_referente']) {
                        $response .= "         👑 Referente del Colectivo\n";
                    }
                    $response .= "\n";
                    $i++;
                }
            }
            mysqli_stmt_close($stmt_int);
        }
        mysqli_stmt_close($stmt);

        if (!$encontrado) {
            $response = addUpdateInfo("❌ No se encontraron resultados para: {$identificacion}\n\n⚠️ Posibles causas:\n• El solicitante no está registrado en el sistema\n• La cédula/RIF puede tener un formato incorrecto\n• El solicitante puede estar marcado como inactivo");
        } else {
            $response = addUpdateInfo($response);
        }

    } catch (Exception $e) {
        $response = addUpdateInfo("❌ Error en la búsqueda: " . $e->getMessage());
    }

    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🔄 Intentar con otro ID', 'callback_data' => 'solicitantes']],
            [['text' => '🔙 Volver al Menú Principal', 'callback_data' => 'menu']]
        ]
    ];

    sendMessage($chat_id, $response, $keyboard);
}

// Consultar solicitudes por cédula/RIF
function consultarSolicitudes($chat_id, $identificacion) {
    global $conn;

    try {
        $stmt = mysqli_prepare($conn, "
            SELECT s.*,
                   CASE
                       WHEN s.tipo_solicitante = 'N' THEN CONCAT(pn.primer_nombre, ' ', pn.primer_apellido)
                       WHEN s.tipo_solicitante = 'J' THEN pj.razon_social
                       WHEN s.tipo_solicitante = 'C' THEN c.nombre_colectivo
                   END AS nombre_solicitante,
                   tp.nombre_procedimiento,
                   p.nombre_predio
            FROM solicitudes s
            LEFT JOIN personas_naturales pn ON s.cedula_solicitante_n = pn.cedula
            LEFT JOIN personas_juridicas pj ON s.rif_solicitante_j = pj.rif
            LEFT JOIN colectivos c ON s.rif_ci_solicitante_c = c.rif_o_ci_referente
            LEFT JOIN tipo_procedimiento tp ON s.id_procedimiento = tp.id_procedimiento
            LEFT JOIN predios p ON s.id_predio = p.id_predio
            WHERE s.cedula_solicitante_n = ? OR s.rif_solicitante_j = ? OR s.rif_ci_solicitante_c = ?
            ORDER BY s.fecha_solicitud DESC
        ");
        mysqli_stmt_bind_param($stmt, "sss", $identificacion, $identificacion, $identificacion);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        // Verificar si el solicitante está registrado
        $solicitante_registrado = false;
        $stmt_check = mysqli_prepare($conn, "
            SELECT 'N' as tipo FROM personas_naturales WHERE cedula = ? AND activo = 1
            UNION
            SELECT 'J' as tipo FROM personas_juridicas WHERE rif = ? AND activo = 1
            UNION
            SELECT 'C' as tipo FROM colectivos WHERE rif_o_ci_referente = ? AND activo = 1
        ");
        mysqli_stmt_bind_param($stmt_check, "sss", $identificacion, $identificacion, $identificacion);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        if (mysqli_num_rows($result_check) > 0) {
            $solicitante_registrado = true;
        }
        mysqli_stmt_close($stmt_check);

        if (mysqli_num_rows($result) == 0) {
            if ($solicitante_registrado) {
                $response = addUpdateInfo("✅ El solicitante {$identificacion} está registrado en el sistema, pero no tiene solicitudes registradas.");
            } else {
                $response = addUpdateInfo("❌ No se encontraron solicitudes para: {$identificacion}\n\n⚠️ Posibles causas:\n• El solicitante no tiene solicitudes registradas\n• La cédula/RIF puede tener un formato incorrecto\n• Las solicitudes pueden estar en otro estado");
            }
        } else {
            $response = "📋 Solicitudes para: {$identificacion}\n\n";
            $i = 1;
            while ($sol = mysqli_fetch_assoc($result)) {
                $response .= "{$i}. 📋 {$sol['numero_solicitud']}\n";
                $response .= "   👤 Solicitante: {$sol['nombre_solicitante']}\n";
                $response .= "   📝 Procedimiento: {$sol['nombre_procedimiento']}\n";
                $response .= "   🏞️ Predio: {$sol['nombre_predio']}\n";
                $response .= "   📅 Fecha: " . date('d/m/Y', strtotime($sol['fecha_solicitud'])) . "\n";
                $response .= "   🟢 Estado: " . str_replace('_', ' ', $sol['estatus']) . "\n";
                $response .= "   📝 Observaciones: " . ($sol['observaciones'] ?: 'Ninguna') . "\n\n";
                $i++;
            }
            $response = addUpdateInfo($response);
        }

        mysqli_stmt_close($stmt);

    } catch (Exception $e) {
        $response = addUpdateInfo("❌ Error en la consulta: " . $e->getMessage());
    }

    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🔍 Buscar otro solicitante', 'callback_data' => 'solicitudes']],
            [['text' => '🔙 Volver al Menú Principal', 'callback_data' => 'menu']]
        ]
    ];

    sendMessage($chat_id, $response, $keyboard);
}

// Procesar rango de fechas para reportes
function processDateRange($chat_id, $user_id, $rango_fechas) {
    global $user_data;

    // Limpiar estado
    unset($user_states[$user_id]);
    unset($user_data[$user_id]);

    // Validar formato del rango
    if (!preg_match('/^(\d{2}\/\d{2}\/\d{4})\s*-\s*(\d{2}\/\d{2}\/\d{4})$/', $rango_fechas, $matches)) {
        sendMessage($chat_id, addUpdateInfo("❌ Formato de fecha incorrecto. Usa: DD/MM/YYYY - DD/MM/YYYY"));
        return;
    }

    $fecha_inicio = DateTime::createFromFormat('d/m/Y', $matches[1]);
    $fecha_fin = DateTime::createFromFormat('d/m/Y', $matches[2]);

    if (!$fecha_inicio || !$fecha_fin || $fecha_inicio > $fecha_fin) {
        sendMessage($chat_id, addUpdateInfo("❌ Rango de fechas inválido."));
        return;
    }

    generarReportes($chat_id, $fecha_inicio->format('Y-m-d'), $fecha_fin->format('Y-m-d'));
}

// Generar reportes por rango de fechas
function generarReportes($chat_id, $fecha_inicio, $fecha_fin) {
    global $conn;

    try {
        $response = "📊 Reporte de Solicitudes\n";
        $response .= "Período: " . date('d/m/Y', strtotime($fecha_inicio)) . " - " . date('d/m/Y', strtotime($fecha_fin)) . "\n\n";

        // Total de solicitudes en el período
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) as total
            FROM solicitudes
            WHERE fecha_solicitud BETWEEN ? AND ?
        ");
        mysqli_stmt_bind_param($stmt, "ss", $fecha_inicio, $fecha_fin);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $response .= "📋 Total de solicitudes: {$row['total']}\n";
        mysqli_stmt_close($stmt);

        // Solicitudes por tipo de solicitante
        $stmt = mysqli_prepare($conn, "
            SELECT tipo_solicitante, COUNT(*) as cantidad
            FROM solicitudes
            WHERE fecha_solicitud BETWEEN ? AND ?
            GROUP BY tipo_solicitante
        ");
        mysqli_stmt_bind_param($stmt, "ss", $fecha_inicio, $fecha_fin);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $response .= "\n👥 Por tipo de solicitante:\n";
        while ($row = mysqli_fetch_assoc($result)) {
            $tipo = $row['tipo_solicitante'] == 'N' ? 'Natural' : ($row['tipo_solicitante'] == 'J' ? 'Jurídica' : 'Colectivo');
            $response .= "   {$tipo}: {$row['cantidad']}\n";
        }
        mysqli_stmt_close($stmt);

        // Solicitudes por procedimiento
        $stmt = mysqli_prepare($conn, "
            SELECT tp.nombre_procedimiento, COUNT(*) as cantidad
            FROM solicitudes s
            LEFT JOIN tipo_procedimiento tp ON s.id_procedimiento = tp.id_procedimiento
            WHERE s.fecha_solicitud BETWEEN ? AND ?
            GROUP BY tp.nombre_procedimiento
            ORDER BY cantidad DESC
        ");
        mysqli_stmt_bind_param($stmt, "ss", $fecha_inicio, $fecha_fin);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $response .= "\n📝 Por procedimiento:\n";
        while ($row = mysqli_fetch_assoc($result)) {
            $response .= "   {$row['nombre_procedimiento']}: {$row['cantidad']}\n";
        }
        mysqli_stmt_close($stmt);

        // Solicitudes por estado
        $stmt = mysqli_prepare($conn, "
            SELECT estatus, COUNT(*) as cantidad
            FROM solicitudes
            WHERE fecha_solicitud BETWEEN ? AND ?
            GROUP BY estatus
        ");
        mysqli_stmt_bind_param($stmt, "ss", $fecha_inicio, $fecha_fin);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $response .= "\n🟢 Por estado:\n";
        while ($row = mysqli_fetch_assoc($result)) {
            $estado = str_replace('_', ' ', $row['estatus']);
            $response .= "   {$estado}: {$row['cantidad']}\n";
        }
        mysqli_stmt_close($stmt);

        // Detalle de solicitudes individuales (guardar result para PDF)
        $stmt = mysqli_prepare($conn, "
            SELECT s.numero_solicitud, s.fecha_solicitud, s.estatus, s.observaciones,
                   CASE
                       WHEN s.tipo_solicitante = 'N' THEN CONCAT(pn.primer_nombre, ' ', pn.primer_apellido)
                       WHEN s.tipo_solicitante = 'J' THEN pj.razon_social
                       WHEN s.tipo_solicitante = 'C' THEN c.nombre_colectivo
                   END AS nombre_solicitante,
                   tp.nombre_procedimiento,
                   p.nombre_predio
            FROM solicitudes s
            LEFT JOIN personas_naturales pn ON s.cedula_solicitante_n = pn.cedula
            LEFT JOIN personas_juridicas pj ON s.rif_solicitante_j = pj.rif
            LEFT JOIN colectivos c ON s.rif_ci_solicitante_c = c.rif_o_ci_referente
            LEFT JOIN tipo_procedimiento tp ON s.id_procedimiento = tp.id_procedimiento
            LEFT JOIN predios p ON s.id_predio = p.id_predio
            WHERE s.fecha_solicitud BETWEEN ? AND ?
            ORDER BY s.fecha_solicitud DESC
            LIMIT 50
        ");
        mysqli_stmt_bind_param($stmt, "ss", $fecha_inicio, $fecha_fin);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $result_copy = $result; // Copia para el PDF

        if (mysqli_num_rows($result) > 0) {
            $response .= "\n📋 Detalle de Solicitudes:\n";
            $i = 1;
            while ($sol = mysqli_fetch_assoc($result)) {
                $response .= "\n{$i}. 📋 {$sol['numero_solicitud']}\n";
                $response .= "   👤 Solicitante: {$sol['nombre_solicitante']}\n";
                $response .= "   📝 Procedimiento: {$sol['nombre_procedimiento']}\n";
                $response .= "   🏞️ Predio: {$sol['nombre_predio']}\n";
                $response .= "   📅 Fecha: " . date('d/m/Y', strtotime($sol['fecha_solicitud'])) . "\n";
                $response .= "   🟢 Estado: " . str_replace('_', ' ', $sol['estatus']) . "\n";
                if ($sol['observaciones']) {
                    $response .= "   📝 Obs: {$sol['observaciones']}\n";
                }
                $i++;
            }
        }
        mysqli_stmt_close($stmt);

        $response = addUpdateInfo($response);

    } catch (Exception $e) {
        $response = addUpdateInfo("❌ Error al generar reporte: " . $e->getMessage());
    }

    // Generar PDF del reporte
    $pdf_filename = generarPDFReporte($fecha_inicio, $fecha_fin, $result_copy);

    $keyboard = [
        'inline_keyboard' => [
            [['text' => '📄 Descargar PDF', 'url' => 'http://localhost/SISTEMA%20INTI%20DAC/Bots/' . $pdf_filename]],
            [['text' => '🔄 Generar otro reporte', 'callback_data' => 'reportes']],
            [['text' => '🔙 Volver al Menú Principal', 'callback_data' => 'menu']]
        ]
    ];

    sendMessage($chat_id, $response, $keyboard);
}

// Función para generar PDF del reporte
function generarPDFReporte($fecha_inicio, $fecha_fin, $result) {
    global $conn;

    // Preparar datos para el PDF (similar a pdf_solicitudes.php)
    $resultados = [];
    mysqli_data_seek($result, 0); // Resetear el puntero del result set

    while ($sol = mysqli_fetch_assoc($result)) {
        // Obtener más información del solicitante
        $beneficiario = $sol['nombre_solicitante'];
        $identificacion = '';
        $sexo = 'N/A';
        $edad = null;
        $telefono = 'N/A';

        // Buscar información adicional según el tipo de solicitante
        if ($sol['tipo_solicitante'] == 'N') {
            $stmt_info = mysqli_prepare($conn, "
                SELECT cedula, telefono, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) as edad, sexo
                FROM personas_naturales
                WHERE cedula = (SELECT cedula_solicitante_n FROM solicitudes WHERE numero_solicitud = ?)
            ");
            mysqli_stmt_bind_param($stmt_info, "s", $sol['numero_solicitud']);
            mysqli_stmt_execute($stmt_info);
            $result_info = mysqli_stmt_get_result($stmt_info);
            if ($info = mysqli_fetch_assoc($result_info)) {
                $identificacion = $info['cedula'];
                $telefono = $info['telefono'];
                $edad = $info['edad'];
                $sexo = $info['sexo'] == 'M' ? 'Masculino' : ($info['sexo'] == 'F' ? 'Femenino' : 'N/A');
            }
            mysqli_stmt_close($stmt_info);
        } elseif ($sol['tipo_solicitante'] == 'J') {
            $stmt_info = mysqli_prepare($conn, "
                SELECT rif, telefono
                FROM personas_juridicas
                WHERE rif = (SELECT rif_solicitante_j FROM solicitudes WHERE numero_solicitud = ?)
            ");
            mysqli_stmt_bind_param($stmt_info, "s", $sol['numero_solicitud']);
            mysqli_stmt_execute($stmt_info);
            $result_info = mysqli_stmt_get_result($stmt_info);
            if ($info = mysqli_fetch_assoc($result_info)) {
                $identificacion = $info['rif'];
                $telefono = $info['telefono'];
            }
            mysqli_stmt_close($stmt_info);
        } elseif ($sol['tipo_solicitante'] == 'C') {
            $stmt_info = mysqli_prepare($conn, "
                SELECT rif_o_ci_referente, telefono
                FROM colectivos
                WHERE rif_o_ci_referente = (SELECT rif_ci_solicitante_c FROM solicitudes WHERE numero_solicitud = ?)
            ");
            mysqli_stmt_bind_param($stmt_info, "s", $sol['numero_solicitud']);
            mysqli_stmt_execute($stmt_info);
            $result_info = mysqli_stmt_get_result($stmt_info);
            if ($info = mysqli_fetch_assoc($result_info)) {
                $identificacion = $info['rif_o_ci_referente'];
                $telefono = $info['telefono'];
            }
            mysqli_stmt_close($stmt_info);
        }

        $resultados[] = [
            'fecha_solicitud' => $sol['fecha_solicitud'],
            'beneficiario' => $beneficiario,
            'identificacion' => $identificacion,
            'tipo_solicitante' => $sol['tipo_solicitante'],
            'sexo' => $sexo,
            'edad' => $edad,
            'telefono' => $telefono,
            'predio' => $sol['nombre_predio'],
            'superficie' => 'N/A', // No tenemos esta info en la BD actual
            'estado_atencion' => str_replace('_', ' ', $sol['estatus']),
            'estado_predio' => 'N/A', // No tenemos esta info
            'municipio' => 'N/A', // No tenemos esta info
            'parroquia' => 'N/A', // No tenemos esta info
            'sector' => 'N/A', // No tenemos esta info
            'requerimiento' => $sol['nombre_procedimiento'],
            'observaciones' => $sol['observaciones'] ?: 'N/A'
        ];
    }

    // Generar HTML del PDF (basado en pdf_solicitudes.php)
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Reporte de Solicitudes</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            @page {
                margin: 20mm;
                size: A4 landscape;
            }

            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 0;
                background: transparent;
                font-size: 9px;
                color: #333;
            }

            .header {
                position: relative;
                background-image: url('http://localhost/SISTEMA INTI DAC/assets/img/sidebar/sidebar.webp');
                background-attachment: fixed;
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
                color: white;
                padding: 30px;
                border-radius:12px;
                margin-bottom: 20px;
            }

            .logo {
                position: absolute;
                top: 40px;
                right: 10px;
                text-align: right;
            }

            .title {
                font-size: 25px;
                font-weight: bold;
                margin: 0 0 5px 0;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .subtitle {
                font-size: 11px;
                margin: 0;
                opacity: 0.9;
            }

            .report-info {
                background: white;
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 2px;
                border-left: 4px solid #c5e0b3;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .info-row {
                display: inline-block;
                margin-right: 30px;
                margin-bottom: 5px;
            }

            .info-label {
                font-weight: bold;
                color: #555;
            }

            .table-container {
                background: white;
                border-radius: 6px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 7px;
            }

            th {
                background: #c5e0b3;
                color: black;
                padding: 6px 2px;
                text-align: left;
                font-weight: bold;
                font-size: 6px;
                text-transform: uppercase;
            }

            td {
                padding: 10px 5px;
                border-bottom: 1px solid #e8f5e8;
                vertical-align: top;
            }

            tr:nth-child(even) {
                background-color: #f8fdf8;
            }

            .footer {
                margin-top: 20px;
                text-align: center;
                font-size: 7px;
                color: #666;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <img src="http://localhost/SISTEMA INTI DAC/assets/img/LOGO INTI.png" style="width:140px; height:140px; item-align: center;">
            <div class="logo">
                <h1 class="title" style="font-size: 40px;">REPORTE DE SOLICITUDES</h1>
                <p class="subtitle" style="font-size: 20px;">Departamento de atención al campesino</p>
            </div>
        </div>

        <div class="report-info">
            <div class="info-row">
                <span class="info-label">Rango de Fechas:</span> <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
            </div>
            <div class="info-row">
                <span class="info-label">Filtro Tipo:</span> Todos
            </div>
            <div class="info-row">
                <span class="info-label">Generado el:</span> <?php echo date('d/m/Y H:i:s'); ?>
            </div>
            <div class="info-row">
                <span class="info-label">Generado por:</span> Bot de Telegram
            </div>
            <div class="info-row">
                <span class="info-label">Total de Registros:</span> <?php echo count($resultados); ?>
            </div>
        </div>

        <div class="table-container">
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
                        <td><?php echo $contador++; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?></td>
                        <td><?php echo htmlspecialchars($solicitud['beneficiario']); ?></td>
                        <td><?php echo htmlspecialchars($solicitud['identificacion']); ?></td>
                        <td><?php
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
                            echo htmlspecialchars($tipo_descripcion);
                        ?></td>
                        <td><?php echo htmlspecialchars($solicitud['sexo'] ?? 'N/A'); ?></td>
                        <td><?php
                            if ($solicitud['edad'] !== null && $solicitud['edad'] > 0) {
                                echo htmlspecialchars($solicitud['edad']) . ' años';
                            } else {
                                echo 'N/A';
                            }
                        ?></td>
                        <td><?php echo htmlspecialchars($solicitud['telefono'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($solicitud['predio'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($solicitud['superficie'] ?? 'N/A'); ?> ha</td>
                        <td><?php echo htmlspecialchars($solicitud['estado_atencion']); ?></td>
                        <td><?php echo htmlspecialchars($solicitud['estado_predio']); ?></td>
                        <td><?php echo htmlspecialchars($solicitud['municipio'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($solicitud['parroquia'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($solicitud['sector'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($solicitud['requerimiento'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($solicitud['observaciones'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="footer">
            Sistema de Información para la Gestión Administrativa del Departamento de Atención al Campesino del Instituto Nacional de Tierras (INTI) © 2025
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    // Generar PDF usando domPDF
    require_once "librerias/dompdf/autoload.inc.php";
    use Dompdf\Dompdf;

    $dompdf = new Dompdf();
    $options = $dompdf->getOptions();
    $options->set('isRemoteEnabled', true);
    $dompdf->setOptions($options);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    // Guardar PDF
    $filename = "reporte_solicitudes_" . date('Ymd_His') . ".pdf";
    $pdf_content = $dompdf->output();
    file_put_contents($filename, $pdf_content);

    return $filename;
}

// Procesar identificación
function processIdentification($chat_id, $user_id, $identificacion) {
    global $user_data;

    $action = $user_data[$user_id]['action'] ?? '';

    if ($action == 'solicitantes') {
        buscarSolicitante($chat_id, $identificacion);
    } elseif ($action == 'solicitudes') {
        consultarSolicitudes($chat_id, $identificacion);
    }

    // Limpiar estado
    unset($user_states[$user_id]);
    unset($user_data[$user_id]);
}

// Procesar actualizaciones
function processUpdates($updates) {
    global $user_states;

    static $offset = 0;

    foreach ($updates['result'] as $update) {
        if (isset($update['update_id'])) {
            $offset = max($offset, $update['update_id'] + 1);
        }

        if (isset($update['message'])) {
            $message = $update['message'];
            $chat_id = $message['chat']['id'];
            $text = $message['text'] ?? '';
            $user = $message['from'];
            $user_id = $user['id'];

            if (!isUserAllowed($user_id)) {
                sendMessage($chat_id, "❌ No tienes permiso para usar este bot.");
                continue;
            }

            if ($text == '/start') {
                handleStart($chat_id, $user);
            } elseif (isset($user_states[$user_id])) {
                if ($user_states[$user_id] == SELECTING_IDENTIFICATION) {
                    processIdentification($chat_id, $user_id, trim($text));
                } elseif ($user_states[$user_id] == SELECTING_DATE_RANGE) {
                    processDateRange($chat_id, $user_id, trim($text));
                }
            } else {
                sendMessage($chat_id, addUpdateInfo("❌ Comando no reconocido.\n\nPor favor, usa los botones del menú o escribe /start para volver al menú principal."));
            }
        } elseif (isset($update['callback_query'])) {
            handleCallback($update['callback_query']);
        }
    }

    return $offset;
}

// Bucle principal
function main() {
    $offset = 0;

    echo "Bot iniciado. Esperando mensajes...\n";

    while (true) {
        $updates = getUpdates($offset);

        if ($updates && isset($updates['result'])) {
            $offset = processUpdates($updates);
        }

        // Esperar 1 segundo antes de la siguiente consulta
        sleep(1);
    }
}

// Ejecutar el bot
main();

?>