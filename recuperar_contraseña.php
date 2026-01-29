<?php
// No usamos session_start() ni ninguna variable de sesión

include('conexion.php');

$mensaje = '';
$tipo_mensaje = '';
$accion = $_POST['accion'] ?? 'seleccionar';
$usuarios = [];

// Obtener lista de usuarios con email desde la base de datos
$sql_usuarios = "SELECT cedula, nombre, apellido, email FROM usuarios WHERE activo = 1 AND email != '' ORDER BY nombre";
$result_usuarios = mysqli_query($conn, $sql_usuarios);
if ($result_usuarios) {
    while ($row = mysqli_fetch_assoc($result_usuarios)) {
        $usuarios[] = $row;
    }
} else {
    $mensaje = "Error al cargar usuarios.";
    $tipo_mensaje = "error";
}

// Generar código de recuperación (6 dígitos)
function generarCodigo() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Almacenamiento temporal en memoria del servidor (array simulando una caché)
$temp_storage = [];
$storage_file = sys_get_temp_dir() . '/recuperacion_password.tmp';

// Cargar el almacenamiento temporal si existe
if (file_exists($storage_file)) {
    $temp_data = file_get_contents($storage_file);
    if (!empty($temp_data)) {
        $temp_storage = json_decode($temp_data, true) ?: [];
    }
}

// Limpiar registros expirados al inicio
foreach ($temp_storage as $key => $data) {
    if (time() > $data['expiracion']) {
        unset($temp_storage[$key]);
    }
}

// Procesar envío de código
if ($accion === 'enviar_codigo' && isset($_POST['cedula_usuario'])) {
    $cedula_usuario = mysqli_real_escape_string($conn, $_POST['cedula_usuario']);
    
    // Buscar usuario
    $sql_buscar = "SELECT cedula, nombre, apellido, email FROM usuarios WHERE cedula = ? AND activo = 1 AND email != ''";
    $stmt_buscar = mysqli_prepare($conn, $sql_buscar);
    mysqli_stmt_bind_param($stmt_buscar, "s", $cedula_usuario);
    mysqli_stmt_execute($stmt_buscar);
    $result_buscar = mysqli_stmt_get_result($stmt_buscar);
    
    if ($usuario = mysqli_fetch_assoc($result_buscar)) {
        $codigo = generarCodigo();
        
        // Guardar código en almacenamiento temporal
        $temp_storage[$cedula_usuario] = [
            'cedula' => $usuario['cedula'],
            'nombre_completo' => $usuario['nombre'] . ' ' . $usuario['apellido'],
            'email' => $usuario['email'],
            'codigo' => $codigo,
            'intentos' => 0,
            'max_intentos' => 3,
            'expiracion' => time() + 180 // 3 minutos (180 segundos)
        ];
        
        // Guardar en archivo temporal
        file_put_contents($storage_file, json_encode($temp_storage));
        
        // Enviar correo usando PHPMailer
        $base_dir = __DIR__;
        require $base_dir . '/librerias/PHPMailer-master/src/Exception.php';
        require $base_dir . '/librerias/PHPMailer-master/src/PHPMailer.php';
        require $base_dir . '/librerias/PHPMailer-master/src/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // Configuración del servidor SMTP (Gmail)
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'sistemaintidac2@gmail.com'; // Tu correo
            $mail->Password   = 'svjr bcbw lavf ewpq ';         // Contraseña de aplicación
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Configuración adicional para evitar problemas comunes
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Remitente y destinatario
            $mail->setFrom('sistema.inti.dac@gmail.com', 'Sistema INTI');
            $mail->addAddress($usuario['email']);

            // Contenido del correo
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = "Código de Recuperación - Sistema INTI";
            $mail->Body = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap' rel='stylesheet'>
                <style>
                    body {
                        font-family: 'Montserrat', sans-serif;
                        margin: 0;
                        padding: 0;
                        background-color: #f5f5f5;
                    }
                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        background-color: #ffffff;
                        border-radius: 15px;
                        overflow: hidden;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                    }
                    .header {
                        background: linear-gradient(135deg, #1E3A3A, #2E7D32);
                        padding: 30px 20px;
                        text-align: center;
                        color: white;
                    }
                    .logo {
                        width: 80px;
                        height: 80px;
                        margin-bottom: 15px;
                    }
                    .title {
                        font-size: 40px;
                        font-weight: 800;
                        margin: 0;
                        text-transform: uppercase;
                        letter-spacing: 2px;
                    }
                    .subtitle {
                        font-size: 14px;
                        margin: 5px 0 0 0;
                        opacity: 0.9;
                        font-weight: 400;
                    }
                    .content {
                        padding: 40px 30px;
                        text-align: center;
                    }
                    .greeting {
                        font-size: 24px;
                        font-weight: 600;
                        color: #2c3e50;
                        margin-bottom: 20px;
                    }
                    .message {
                        font-size: 16px;
                        line-height: 1.6;
                        color: #555;
                        margin-bottom: 30px;
                    }
                    .code-container {
                        background: linear-gradient(135deg, #e8f5e8, #f1f8e9);
                        padding: 30px;
                        border-radius: 15px;
                        margin: 30px 0;
                        border: 3px solid #a5d6a7;
                        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                    }
                    .code {
                        font-size: 36px;
                        font-weight: 900;
                        letter-spacing: 8px;
                        color: #2e7d32;
                        text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
                        margin: 0;
                    }
                    .warning {
                        background-color: #fff3cd;
                        color: #856404;
                        padding: 15px;
                        border-radius: 8px;
                        margin: 20px 0;
                        font-weight: 600;
                        border-left: 4px solid #ffc107;
                    }
                    .footer {
                        background: #1E3A3A;
                        color: white;
                        padding: 30px 20px;
                        text-align: center;
                        font-size: 12px;
                        line-height: 1.5;
                    }
                    .footer-logo {
                        width: 50px;
                        height: 50px;
                        margin-bottom: 15px;
                        opacity: 0.8;
                    }
                    .footer-text {
                        margin: 0;
                        font-weight: 400;
                    }
                    .footer-links {
                        margin-top: 15px;
                    }
                    .footer-links a {
                        color: #4CAF50;
                        text-decoration: none;
                        margin: 0 10px;
                        font-weight: 500;
                    }
                    .footer-links a:hover {
                        text-decoration: underline;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <!-- Header -->
                    <div class='header'>
                        <h1 class='title'>Sistema INTI</h1>
                        <p class='subtitle'>Instituto Nacional de Tierras</p>
                    </div>

                    <!-- Content -->
                    <div class='content'>
                        <h2 class='greeting'>¡Hola {$usuario['nombre']} {$usuario['apellido']}!</h2>
                        <p class='message'>
                            Has solicitado restablecer tu contraseña en el Sistema INTI. Para completar el proceso,
                            utiliza el siguiente código de verificación:
                        </p>

                        <div class='code-container'>
                            <h1 class='code'>$codigo</h1>
                        </div>

                        <div class='warning'>
                            ⚠️ Este código expirará en 3 minutos por seguridad.
                        </div>

                        <p class='message'>
                            Si no solicitaste este cambio, por favor ignora este mensaje y contacta al administrador del sistema.
                        </p>
                    </div>

                    <!-- Footer -->
                    <div class='footer'>
                        <p class='footer-text'>
                            <strong>Sistema de Información para la Gestión Administrativa del Departamento de Atención al Campesino</strong><br>
                            Instituto Nacional de Tierras (INTI) © 2025<br>
                            Todos los derechos reservados
                        </p>
                        <div class='footer-links'>
                            <a href='http://localhost/SISTEMA%20INTI%20DAC/Loggin.php'>Acceder al Sistema</a> |
                            <a href='https://wa.me/584121028791'>Soporte Técnico A</a> |
                            <a href='https://wa.me/584121263056'>Soporte Técnico B</a>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ";

            $mail->send();
            
            // Éxito
            $mensaje = "Se ha enviado un código de verificación a <strong>{$usuario['email']}</strong>. Revise su bandeja de entrada (y spam).";
            $tipo_mensaje = "success";
            $accion = 'verificar_codigo';
            
            // Registrar en bitácora
            $detalle = "Solicitud de recuperación de contraseña enviada a {$usuario['email']}";
            $sql_bitacora = "INSERT INTO bitacora (cedula_usuario, accion, tabla_afectada, registro_afectado, detalle) VALUES (?, 'Consulta', 'usuarios', ?, ?)";
            $stmt_bitacora = mysqli_prepare($conn, $sql_bitacora);
            mysqli_stmt_bind_param($stmt_bitacora, "sss", $usuario['cedula'], $usuario['cedula'], $detalle);
            mysqli_stmt_execute($stmt_bitacora);
            mysqli_stmt_close($stmt_bitacora);
            
        } catch (Exception $e) {
            $mensaje = "Error al enviar el correo: " . htmlspecialchars($mail->ErrorInfo);
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "No se encontró un usuario activo con ese correo o cédula.";
        $tipo_mensaje = "error";
    }
    mysqli_stmt_close($stmt_buscar);
}

// Procesar verificación de código
if ($accion === 'verificar_codigo' && isset($_POST['codigo'])) {
    $codigo_ingresado = trim($_POST['codigo']);
    $cedula_usuario = $_POST['cedula_usuario'] ?? '';
    
    if (empty($cedula_usuario)) {
        $mensaje = "Información de usuario faltante.";
        $tipo_mensaje = "error";
        $accion = 'seleccionar';
    } elseif (!isset($temp_storage[$cedula_usuario])) {
        $mensaje = "No hay una solicitud de recuperación activa para este usuario.";
        $tipo_mensaje = "error";
        $accion = 'seleccionar';
    } else {
        $datos_recuperacion = $temp_storage[$cedula_usuario];
        
        // Verificar expiración
        if (time() > $datos_recuperacion['expiracion']) {
            unset($temp_storage[$cedula_usuario]);
            file_put_contents($storage_file, json_encode($temp_storage));
            $mensaje = "El código ha expirado. Por favor, solicite uno nuevo.";
            $tipo_mensaje = "error";
            $accion = 'seleccionar';
        } 
        // Verificar intentos
        elseif ($datos_recuperacion['intentos'] >= $datos_recuperacion['max_intentos']) {
            unset($temp_storage[$cedula_usuario]);
            file_put_contents($storage_file, json_encode($temp_storage));
            $mensaje = "Demasiados intentos fallidos. Por favor, solicite un nuevo código.";
            $tipo_mensaje = "error";
            $accion = 'seleccionar';
        }
        // Verificar código
        elseif ($codigo_ingresado === $datos_recuperacion['codigo']) {
            // Eliminar el código después de verificarlo
            unset($temp_storage[$cedula_usuario]);
            file_put_contents($storage_file, json_encode($temp_storage));
            
            $accion = 'cambiar_contraseña';
            $mensaje = "Código verificado correctamente. Puede cambiar su contraseña.";
            $tipo_mensaje = "success";
        } else {
            // Incrementar intentos fallidos
            $temp_storage[$cedula_usuario]['intentos']++;
            file_put_contents($storage_file, json_encode($temp_storage));
            
            $intentos_restantes = $datos_recuperacion['max_intentos'] - $temp_storage[$cedula_usuario]['intentos'];
            $mensaje = "Código incorrecto. Intentos restantes: $intentos_restantes.";
            $tipo_mensaje = "error";
        }
    }
}

// Procesar cambio de contraseña
if ($accion === 'cambiar_contraseña' && isset($_POST['nueva_clave'])) {
    $nueva_clave = $_POST['nueva_clave'] ?? '';
    $confirmar_clave = $_POST['confirmar_clave'] ?? '';
    $cedula_usuario = $_POST['cedula_usuario'] ?? '';
    
    if (empty($nueva_clave) || empty($confirmar_clave) || empty($cedula_usuario)) {
        $mensaje = "Información faltante.";
        $tipo_mensaje = "error";
        $accion = 'seleccionar';
    } elseif ($nueva_clave !== $confirmar_clave) {
        $mensaje = "Las contraseñas no coinciden.";
        $tipo_mensaje = "error";
        $accion = 'cambiar_contraseña';
    } else {
        // Validar que tenga mayúscula, minúscula, número y carácter especial
        if (!preg_match('/[A-Z]/', $nueva_clave) || 
            !preg_match('/[a-z]/', $nueva_clave) || 
            !preg_match('/[0-9]/', $nueva_clave) || 
            !preg_match('/[^A-Za-z0-9]/', $nueva_clave)) {
            $mensaje = "La contraseña debe tener al menos una mayúscula, minúscula, número y carácter especial.";
            $tipo_mensaje = "error";
            $accion = 'cambiar_contraseña';
        } elseif (strlen($nueva_clave) < 8) {
            $mensaje = "La contraseña debe tener al menos 8 caracteres.";
            $tipo_mensaje = "error";
            $accion = 'cambiar_contraseña';
        } else {
            // Actualizar contraseña
            $hash_clave = md5($nueva_clave); // Usando MD5 como en tu sistema
            
            $sql_update = "UPDATE usuarios SET clave_usuario = ? WHERE cedula = ?";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "ss", $hash_clave, $cedula_usuario);
            
            if (mysqli_stmt_execute($stmt_update)) {
                $mensaje = "Contraseña actualizada con éxito. Puede iniciar sesión.";
                $tipo_mensaje = "success";
                $accion = 'finalizado';
                
                // Registrar en bitácora
                $detalle = "Contraseña restablecida mediante recuperación";
                $sql_bitacora = "INSERT INTO bitacora (cedula_usuario, accion, tabla_afectada, registro_afectado, detalle) VALUES (?, 'Edicion', 'usuarios', ?, ?)";
                $stmt_bitacora = mysqli_prepare($conn, $sql_bitacora);
                mysqli_stmt_bind_param($stmt_bitacora, "sss", $cedula_usuario, $cedula_usuario, $detalle);
                mysqli_stmt_execute($stmt_bitacora);
                mysqli_stmt_close($stmt_bitacora);
                
            } else {
                $mensaje = "Error al actualizar la contraseña.";
                $tipo_mensaje = "error";
                $accion = 'cambiar_contraseña';
            }
            mysqli_stmt_close($stmt_update);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="imagenes/LOGO INTI.png" type="image/x-icon">

    <!-- Bootstrap local -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">

    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="assets/css/estilos_loggin.css">
    <link href="assets/img/LOGO INTI.png" rel="icon">


    <title>Recuperar Contraseña</title>

    <style>
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f5f5f5;
        }
        .centered-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .modern-login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            padding: 30px;
            position: relative;
        }

        .form-step h3 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-weight: 600;
            text-align: center;
            font-family: 'Montserrat', sans-serif;
        }
        .modern-input-group {
            position: relative;
            margin-bottom: 20px;
        }
        .modern-input-group select,
        .modern-input-group input[type="password"],
        .modern-input-group input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
        }
        .modern-input-group select:focus,
        .modern-input-group input:focus {
            border-color: #4CAF50;
            outline: none;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        .countdown {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-top: 10px;
            font-family: 'Montserrat', sans-serif;
        }
        .feedback-message {
            margin-top: 10px;
            font-size: 14px;
            line-height: 1.4;
            font-family: 'Montserrat', sans-serif;
        }
        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            width: 100%;
            font-family: 'Montserrat', sans-serif;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .back-link {
            color: #0d6efd;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
            font-family: 'Montserrat', sans-serif;
        }
        .back-link:hover {
            color: #0b5ed7;
            text-decoration: underline;
        }
        .alert {
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            font-family: 'Montserrat', sans-serif;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        /* Ocultar pasos no activos */
        .form-step {
            display: none;
        }
        .form-step.active {
            display: block;
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Aquí se muestra el mensaje de error si existe -->
    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-<?php echo $tipo_mensaje === 'success' ? 'success' : 'danger'; ?> text-center">
            <?= $mensaje ?>
        </div>
    <?php endif; ?>

    <div class="centered-container">
        <div class="modern-login-container">
            <!-- Logo sin borde circular -->
            <img src="assets/img/LOGO INTI.png" alt="Logo INTI" class="modern-logo">

            <!-- Paso 1: Seleccionar usuario -->
            <div id="seleccionar" class="form-step <?php echo $accion === 'seleccionar' ? 'active' : ''; ?>">
                <h3 style="font-weight: 800;">Recuperar Contraseña</h3>
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="enviar_codigo">
                    
                    <div class="modern-input-group">
                        <select name="cedula_usuario" class="form-control" required>
                            <option value="">Seleccione un usuario...</option>
                            <?php foreach ($usuarios as $user): ?>
                                <option value="<?php echo $user['cedula']; ?>">
                                    <?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellido'] . ' (' . $user['cedula'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        Enviar Código de Verificación
                    </button>
                </form>
            </div>

            <!-- Paso 2: Verificar código -->
            <div id="verificar_codigo" class="form-step <?php echo $accion === 'verificar_codigo' ? 'active' : ''; ?>">
                <h3 style="font-weight: 800;">Verificar Código</h3>
                
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="verificar_codigo">
                    <input type="hidden" name="cedula_usuario" value="<?php echo htmlspecialchars($_POST['cedula_usuario'] ?? ''); ?>">
                    
                    <p style="text-align: center; color: #666; line-height: 1.5;">
                        Hemos enviado un código de 6 dígitos a tu correo electrónico.
                    </p>
                    
                    <div class="modern-input-group">
                        <input 
                            type="text" 
                            name="codigo" 
                            placeholder="Ingrese el código de 6 dígitos" 
                            maxlength="6"
                            pattern="[0-9]{6}"
                            title="Debe ingresar exactamente 6 dígitos"
                            required
                        >
                    </div>
                    
                    <div class="countdown">
                        El código expira en 3 minutos
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        Verificar Código
                    </button>
                </form>
            </div>

            <!-- Paso 3: Cambiar contraseña -->
            <div id="cambiar_contraseña" class="form-step <?php echo $accion === 'cambiar_contraseña' ? 'active' : ''; ?>">
                <h3>Cambiar Contraseña</h3>
                
                <form method="POST" action="" id="form-cambio">
                    <input type="hidden" name="accion" value="cambiar_contraseña">
                    <input type="hidden" name="cedula_usuario" value="<?php echo htmlspecialchars($_POST['cedula_usuario'] ?? ''); ?>">
                    
                    <p style="text-align: center; color: #666; line-height: 1.5; margin-bottom: 20px;">
                        Ingrese su nueva contraseña segura
                    </p>
                    
                    <div class="modern-input-group">
                        <input 
                            type="password" 
                            name="nueva_clave" 
                            id="nueva_clave"
                            placeholder="Nueva contraseña" 
                            required 
                            minlength="8"
                        >
                    </div>
                    
                    <div class="feedback-message" id="feedback"></div>
                    
                    <div class="modern-input-group" style="margin-top: 15px;">
                        <input 
                            type="password" 
                            name="confirmar_clave" 
                            id="confirmar_clave"
                            placeholder="Confirmar contraseña" 
                            required 
                            minlength="8"
                        >
                    </div>
                    
                    <div class="feedback-message" id="confirm-feedback"></div>
                    
                    <button type="submit" class="btn btn-primary" id="btnSubmit">
                        Cambiar Contraseña
                    </button>
                </form>
            </div>

            <!-- Paso 4: Finalizado -->
            <div id="finalizado" class="form-step <?php echo $accion === 'finalizado' ? 'active' : ''; ?>">
                <div style="text-align: center; padding: 30px 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" fill="#4CAF50" class="bi bi-check-circle" viewBox="0 0 16 16" style="margin: 0 auto 20px;">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                        <path d="m10.97 4.97-.02.022-3.473 4.425-2.093-2.094a.75.75 0 0 0-1.06 1.06l2.5 2.5a.75.75 0 0 0 1.06 0l3.75-4.75a.75.75 0 0 0-1.06-1.06"/>
                    </svg>
                    <h3 style="color: #2c3e50; margin-bottom: 15px;">¡Éxito!</h3>
                    <p style="color: #666; line-height: 1.5;">
                        Su contraseña ha sido actualizada correctamente. Ahora puede iniciar sesión con sus nuevas credenciales.
                    </p>
                </div>
            </div>

            <!-- Botón para volver al login -->
            <div class="text-center mt-3">
                <a href="Loggin.php" class="back-link">
                    ← Volver al inicio de sesión
                </a>
            </div>
        </div>
    </div>

    <script>
        // Validar que solo se ingresen números en el código
        document.querySelector('input[name="codigo"]')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 6) {
                this.value = this.value.substring(0, 6);
            }
        });

        // Auto-enfoque en el input de código
        document.querySelector('input[name="codigo"]')?.focus();

        // Validación en tiempo real de la contraseña
        const claveInput = document.getElementById('nueva_clave');
        const confirmInput = document.getElementById('confirmar_clave');
        const feedback = document.getElementById('feedback');
        const confirmFeedback = document.getElementById('confirm-feedback');
        const btnSubmit = document.getElementById('btnSubmit');

        function validarContrasena(clave) {
            let criterios = [];
            let cumplidos = 0;
            const total = 4;

            if (clave.length >= 8) {
                criterios.push('<span style="color:green;">✓</span> Al menos 8 caracteres');
                cumplidos++;
            } else {
                criterios.push('<span style="color:red;">✗</span> Al menos 8 caracteres');
            }

            if (/[A-Z]/.test(clave)) {
                criterios.push('<span style="color:green;">✓</span> Una letra mayúscula');
                cumplidos++;
            } else {
                criterios.push('<span style="color:red;">✗</span> Una letra mayúscula');
            }

            if (/[a-z]/.test(clave)) {
                criterios.push('<span style="color:green;">✓</span> Una letra minúscula');
                cumplidos++;
            } else {
                criterios.push('<span style="color:red;">✗</span> Una letra minúscula');
            }

            if (/[0-9]/.test(clave)) {
                criterios.push('<span style="color:green;">✓</span> Un número');
                cumplidos++;
            } else {
                criterios.push('<span style="color:red;">✗</span> Un número');
            }

            if (/[^A-Za-z0-9]/.test(clave)) {
                criterios.push('<span style="color:green;">✓</span> Un carácter especial');
                cumplidos++;
            } else {
                criterios.push('<span style="color:red;">✗</span> Un carácter especial');
            }

            feedback.innerHTML = criterios.join('<br>');
            
            return true; // Siempre devuelve true para que el botón esté habilitado
        }

        function validarConfirmacion() {
            const clave = claveInput.value;
            const confirm = confirmInput.value;
            
            if (confirm.length === 0) {
                confirmFeedback.textContent = '';
                confirmInput.classList.remove('is-valid', 'is-invalid');
                return true; // Siempre devuelve true
            }
            
            if (clave === confirm) {
                confirmFeedback.innerHTML = '<span style="color:green;">✓ Las contraseñas coinciden</span>';
                confirmInput.classList.add('is-valid');
                confirmInput.classList.remove('is-invalid');
                return true;
            } else {
                confirmFeedback.innerHTML = '<span style="color:red;">✗ Las contraseñas no coinciden</span>';
                confirmInput.classList.add('is-invalid');
                confirmInput.classList.remove('is-valid');
                return true; // Siempre devuelve true
            }
        }

        // Validar ambos campos cada vez que cambien, pero no deshabilitar el botón
        claveInput.addEventListener('input', function() {
            validarContrasena(this.value);
            validarConfirmacion();
        });

        confirmInput.addEventListener('input', function() {
            validarContrasena(claveInput.value);
            validarConfirmacion();
        });

        // Asegurar que el botón siempre esté habilitado
        document.addEventListener('DOMContentLoaded', function() {
            btnSubmit.disabled = false;
        });
    </script>
</body>
</html>
