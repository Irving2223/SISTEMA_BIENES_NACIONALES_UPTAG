<?php
// No usamos session_start() ni ninguna variable de sesión

include('conexion.php');

$mensaje = '';
$tipo_mensaje = '';
$accion = $_POST['accion'] ?? 'seleccionar';

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

// Guardar almacenamiento actualizado
file_put_contents($storage_file, json_encode($temp_storage));

// Procesar envío de código
if ($accion === 'enviar_codigo' && isset($_POST['email_usuario'])) {
    $email_usuario = mysqli_real_escape_string($conn, trim($_POST['email_usuario']));
    
    // Buscar usuario por email
    $sql_buscar = "SELECT cedula, nombres, email FROM usuarios WHERE email = ? AND activo = 1";
    $stmt_buscar = mysqli_prepare($conn, $sql_buscar);
    mysqli_stmt_bind_param($stmt_buscar, "s", $email_usuario);
    mysqli_stmt_execute($stmt_buscar);
    $result_buscar = mysqli_stmt_get_result($stmt_buscar);
    
    if ($usuario = mysqli_fetch_assoc($result_buscar)) {
        $codigo = generarCodigo();
        
        // Guardar código en almacenamiento temporal
        $temp_storage[$email_usuario] = [
            'cedula' => $usuario['cedula'],
            'nombre_completo' => $usuario['nombres'],
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
            // Configuración del servidor SMTP (Gmail) - Puerto SSL
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'gestiondebienesnacionalesuptag@gmail.com';
            $mail->Password   = 'xoig bjor txpw qdms';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            // Configuración adicional para evitar problemas comunes
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Remitente y destinatario
            $mail->setFrom('sistema.inti.dac@gmail.com', 'Sistema Bienes Nacionales');
            $mail->addAddress($usuario['email']);

            // Contenido del correo
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = "Código de Recuperación - Sistema Bienes Nacionales";
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
                        background: linear-gradient(135deg, #ff6600, #ff8533);
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
                        background: linear-gradient(135deg, #fff5eb, #ffe0cc);
                        padding: 30px;
                        border-radius: 15px;
                        margin: 30px 0;
                        border: 3px solid #ff6600;
                        box-shadow: 0 5px 15px rgba(255, 102, 0, 0.1);
                    }
                    .code {
                        font-size: 36px;
                        font-weight: 900;
                        letter-spacing: 8px;
                        color: #ff6600;
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
                        background: #ff6600;
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
                        color: #fff;
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
                        <h1 class='title'>Bienes Nacionales</h1>
                        <p class='subtitle'>Oficina de Gestión Administrativa</p>
                    </div>

                    <!-- Content -->
                    <div class='content'>
                        <h2 class='greeting'>¡Hola {$usuario['nombres']}!</h2>
                        <p class='message'>
                            Has solicitado restablecer tu contraseña en el Sistema de Bienes Nacionales. Para completar el proceso,
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
                            <strong>Sistema de Gestión de Bienes Nacionales</strong><br>
                            UPTAG © 2026<br>
                            Todos los derechos reservados
                        </p>
                        <div class='footer-links'>

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
            
            // Registrar en auditoria
            $detalle = "Solicitud de recuperación de contraseña enviada a {$usuario['email']}";
            $sql_auditoria = "INSERT INTO auditoria (tabla_afectada, accion, usuario_cedula, datos_nuevos, ip_address) VALUES ('usuarios', 'INSERT', ?, ?, ?)";
            $stmt_auditoria = mysqli_prepare($conn, $sql_auditoria);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $datos = json_encode(['accion' => 'recuperacion_password', 'email' => $usuario['email']]);
            mysqli_stmt_bind_param($stmt_auditoria, "sss", $usuario['cedula'], $datos, $ip);
            mysqli_stmt_execute($stmt_auditoria);
            mysqli_stmt_close($stmt_auditoria);
            
        } catch (Exception $e) {
            // Si falla el envío de email, mostrar código en pantalla para desarrollo
            $codigo_para_mostrar = $codigo;
            $mensaje = "Modo desarrollo: Servidor de correo no disponible. Codigo: $codigo_para_mostrar";
            $tipo_mensaje = "warning";
            $accion = 'verificar_codigo';
        }
    } else {
        $mensaje = "No se encontró un usuario activo con ese correo electrónico.";
        $tipo_mensaje = "error";
    }
    mysqli_stmt_close($stmt_buscar);
}

// Procesar verificación de código
if ($accion === 'verificar_codigo' && isset($_POST['codigo'])) {
    $codigo_ingresado = trim($_POST['codigo']);
    $email_usuario = $_POST['email_usuario'] ?? '';
    
    if (empty($email_usuario)) {
        $mensaje = "Información de usuario faltante.";
        $tipo_mensaje = "error";
        $accion = 'seleccionar';
    } elseif (!isset($temp_storage[$email_usuario])) {
        $mensaje = "No hay una solicitud de recuperación activa para este usuario.";
        $tipo_mensaje = "error";
        $accion = 'seleccionar';
    } else {
        $datos_recuperacion = $temp_storage[$email_usuario];
        
        // Verificar expiración
        if (time() > $datos_recuperacion['expiracion']) {
            unset($temp_storage[$email_usuario]);
            file_put_contents($storage_file, json_encode($temp_storage));
            $mensaje = "El código ha expirado. Por favor, solicite uno nuevo.";
            $tipo_mensaje = "error";
            $accion = 'seleccionar';
        } 
        // Verificar intentos
        elseif ($datos_recuperacion['intentos'] >= $datos_recuperacion['max_intentos']) {
            unset($temp_storage[$email_usuario]);
            file_put_contents($storage_file, json_encode($temp_storage));
            $mensaje = "Demasiados intentos fallidos. Por favor, solicite un nuevo código.";
            $tipo_mensaje = "error";
            $accion = 'seleccionar';
        }
        // Verificar código
        elseif ($codigo_ingresado === $datos_recuperacion['codigo']) {
            // Eliminar el código después de verificarlo
            unset($temp_storage[$email_usuario]);
            file_put_contents($storage_file, json_encode($temp_storage));
            
            $accion = 'cambiar_contraseña';
            $mensaje = "Código verificado correctamente. Puede cambiar su contraseña.";
            $tipo_mensaje = "success";
        } else {
            // Incrementar intentos fallidos
            $temp_storage[$email_usuario]['intentos']++;
            file_put_contents($storage_file, json_encode($temp_storage));
            
            $intentos_restantes = $datos_recuperacion['max_intentos'] - $temp_storage[$email_usuario]['intentos'];
            $mensaje = "Código incorrecto. Intentos restantes: $intentos_restantes.";
            $tipo_mensaje = "error";
        }
    }
}

// Procesar cambio de contraseña
if ($accion === 'cambiar_contraseña' && isset($_POST['nueva_clave'])) {
    $nueva_clave = $_POST['nueva_clave'] ?? '';
    $confirmar_clave = $_POST['confirmar_clave'] ?? '';
    $email_usuario = $_POST['email_usuario'] ?? '';
    
    if (empty($nueva_clave) || empty($confirmar_clave) || empty($email_usuario)) {
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
            // Actualizar contraseña con password_hash
            $hash_clave = password_hash($nueva_clave, PASSWORD_DEFAULT);
            
            // Obtener la cédula del usuario desde el almacenamiento temporal
            $cedula_usuario = '';
            if (isset($temp_storage[$email_usuario])) {
                $cedula_usuario = $temp_storage[$email_usuario]['cedula'];
            } else {
                // Si no está en storage, buscar en la base de datos
                $sql_cedula = "SELECT cedula FROM usuarios WHERE email = ?";
                $stmt_cedula = mysqli_prepare($conn, $sql_cedula);
                mysqli_stmt_bind_param($stmt_cedula, "s", $email_usuario);
                mysqli_stmt_execute($stmt_cedula);
                $result_cedula = mysqli_stmt_get_result($stmt_cedula);
                if ($row_cedula = mysqli_fetch_assoc($result_cedula)) {
                    $cedula_usuario = $row_cedula['cedula'];
                }
                mysqli_stmt_close($stmt_cedula);
            }
            
            if (empty($cedula_usuario)) {
                $mensaje = "Error al identificar el usuario.";
                $tipo_mensaje = "error";
                $accion = 'seleccionar';
            } else {
                $sql_update = "UPDATE usuarios SET password_hash = ? WHERE cedula = ?";
                $stmt_update = mysqli_prepare($conn, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "ss", $hash_clave, $cedula_usuario);
                
                if (mysqli_stmt_execute($stmt_update)) {
                    $mensaje = "Contraseña actualizada con éxito. Puede iniciar sesión.";
                    $tipo_mensaje = "success";
                    $accion = 'finalizado';
                    
                    // Registrar en auditoria
                    $detalle = "Contraseña restablecida mediante recuperación";
                    $sql_auditoria = "INSERT INTO auditoria (tabla_afectada, accion, usuario_cedula, datos_nuevos, ip_address) VALUES ('usuarios', 'UPDATE', ?, ?, ?)";
                    $stmt_auditoria = mysqli_prepare($conn, $sql_auditoria);
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $datos = json_encode(['accion' => 'password_restablecido']);
                    mysqli_stmt_bind_param($stmt_auditoria, "sss", $cedula_usuario, $datos, $ip);
                    mysqli_stmt_execute($stmt_auditoria);
                    mysqli_stmt_close($stmt_auditoria);
                    
                } else {
                    $mensaje = "Error al actualizar la contraseña.";
                    $tipo_mensaje = "error";
                    $accion = 'cambiar_contraseña';
                }
                mysqli_stmt_close($stmt_update);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/img/LOGO INTI.png" type="image/x-icon">

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
            color: #ff6600;
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
            border-color: #ff6600;
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 102, 0, 0.1);
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
            background: linear-gradient(90deg, #ff6600, #ff8533) !important;
            border: none !important;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #e65c00, #ff6600) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 102, 0, 0.3);
        }
        .btn-secondary {
            background: #6c757d !important;
            border: none !important;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 8px;
        }
        .alert {
            font-family: 'Montserrat', sans-serif;
        }
        .text-center a {
            color: #ff6600 !important;
            font-family: 'Montserrat', sans-serif;
        }
        .text-center a:hover {
            text-decoration: underline !important;
        }
    </style>
</head>
<body>

<?php if (!empty($mensaje)): ?>
    <div style="background: <?= $tipo_mensaje === 'error' ? '#f8d7da' : '#d4edda' ?>; color: <?= $tipo_mensaje === 'error' ? '#721c24' : '#155724' ?>; text-align:center; padding:10px; font-size:clamp(1rem, 5vw, 18px); font-weight:700;">
        <?= $mensaje ?>
    </div>
<?php endif; ?>

<div class="centered-container">
    <div class="modern-login-container">

        <!-- Logo -->
        <div style="text-align: center; margin-bottom: 25px;">
            <img src="assets/img/LOGO INTI.png" alt="Logo" style="width: 80px; height: auto;">
        </div>

        <!-- Paso 1: Ingresar Correo Electrónico -->
        <?php if ($accion === 'seleccionar'): ?>
            <div class="form-step">
                <h3>Recuperar Contraseña</h3>
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="enviar_codigo">
                    <div class="modern-input-group">
                        <label for="email_usuario" class="form-label" style="font-family: 'Montserrat', sans-serif; font-weight: 500;">Correo Electrónico</label>
                        <input type="email" name="email_usuario" id="email_usuario" class="form-control" required 
                               placeholder="correo@ejemplo.com" style="font-family: 'Montserrat', sans-serif;">
                    </div>
                    <div style="text-align: center;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="bi bi-send"></i> Enviar Código
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Paso 2: Verificar Código -->
        <?php if ($accion === 'verificar_codigo'): ?>
            <div class="form-step">
                <h3>Verificar Código</h3>
                <p style="text-align: center; font-family: 'Montserrat', sans-serif; color: #666; margin-bottom: 20px;">
                    Ingrese el código de 6 dígitos enviado a <strong><?= htmlspecialchars($_POST['email_usuario'] ?? '') ?></strong>
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="verificar_codigo">
                    <input type="hidden" name="email_usuario" value="<?= htmlspecialchars($_POST['email_usuario'] ?? '') ?>">
                    <div class="modern-input-group">
                        <label for="codigo" class="form-label" style="font-family: 'Montserrat', sans-serif; font-weight: 500;">Código de Verificación</label>
                        <input type="text" name="codigo" id="codigo" class="form-control" required 
                               placeholder="000000" maxlength="6" 
                               style="text-align: center; font-size: 24px; letter-spacing: 8px; font-weight: 700;"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                    <div class="countdown" id="countdown"></div>
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='recuperar_contraseña.php'">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Verificar Código
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Paso 3: Cambiar Contraseña -->
        <?php if ($accion === 'cambiar_contraseña'): ?>
            <div class="form-step">
                <h3>Nueva Contraseña</h3>
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="cambiar_contraseña">
                    <input type="hidden" name="email_usuario" value="<?= htmlspecialchars($_POST['email_usuario'] ?? '') ?>">
                    <div class="modern-input-group">
                        <label for="nueva_clave" class="form-label" style="font-family: 'Montserrat', sans-serif; font-weight: 500;">Nueva Contraseña</label>
                        <input type="password" name="nueva_clave" id="nueva_clave" class="form-control" required minlength="8"
                               placeholder="Mínimo 8 caracteres" style="font-family: 'Montserrat', sans-serif;">
                        <div class="feedback-message" id="password-feedback"></div>
                    </div>
                    <div class="modern-input-group">
                        <label for="confirmar_clave" class="form-label" style="font-family: 'Montserrat', sans-serif; font-weight: 500;">Confirmar Contraseña</label>
                        <input type="password" name="confirmar_clave" id="confirmar_clave" class="form-control" required minlength="8"
                               placeholder="Repita la contraseña" style="font-family: 'Montserrat', sans-serif;">
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='recuperar_contraseña.php'">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btn-cambiar" disabled>
                            Cambiar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Paso 4: Finalizado -->
        <?php if ($accion === 'finalizado'): ?>
            <div class="form-step" style="text-align: center;">
                <div style="color: #28a745; font-size: 60px; margin-bottom: 20px;">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h3 style="color: #28a745;">¡Contraseña Actualizada!</h3>
                <p style="font-family: 'Montserrat', sans-serif; color: #666; margin-bottom: 30px;">
                    Su contraseña ha sido cambiada exitosamente.
                </p>
                <a href="Loggin.php" class="btn btn-primary" style="display: inline-block; text-decoration: none;">
                    <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                </a>
            </div>
        <?php endif; ?>

        <!-- Enlace para volver al login -->
        <?php if ($accion !== 'finalizado'): ?>
            <div class="text-center mt-3" style="margin-top: 20px;">
                <a href="Loggin.php" style="color: #ff6600; text-decoration: none; font-size: 14px; font-weight: 300;">
                    <i class="bi bi-arrow-left"></i> Volver al Inicio de Sesión
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
// Validación de contraseña en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    const nuevaClaveInput = document.getElementById('nueva_clave');
    const confirmarClaveInput = document.getElementById('confirmar_clave');
    const btnCambiar = document.getElementById('btn-cambiar');
    const feedback = document.getElementById('password-feedback');

    if (nuevaClaveInput && confirmarClaveInput && btnCambiar) {
        function validarPassword() {
            const password = nuevaClaveInput.value;
            let criterios = [];
            let cumplidos = 0;
            const total = 5;

            if (password.length >= 8) {
                criterios.push('<span style="color:green;">✓</span> Mínimo 8 caracteres');
                cumplidos++;
            } else {
                criterios.push('<span style="color:red;">✗</span> Mínimo 8 caracteres');
            }

            if (/[A-Z]/.test(password)) {
                criterios.push('<span style="color:green;">✓</span> Una mayúscula');
                cumplidos++;
            } else {
                criterios.push('<span style="color:red;">✗</span> Una mayúscula');
            }

            if (/[a-z]/.test(password)) {
                criterios.push('<span style="color:green;">✓</span> Una minúscula');
                cumplidos++;
            } else {
                criterios.push('<span style="color:red;">✗</span> Una minúscula');
            }

            if (/\d/.test(password)) {
                criterios.push('<span style="color:green;">✓</span> Un número');
                cumplidos++;
            } else {
                criterios.push('<span style="color:red;">✗</span> Un número');
            }

            if (/[^A-Za-z0-9]/.test(password)) {
                criterios.push('<span style="color:green;">✓</span> Un carácter especial');
                cumplidos++;
            } else {
                criterios.push('<span style="color:red;">✗</span> Un carácter especial');
            }

            feedback.innerHTML = criterios.join('<br>');

            const confirmar = confirmarClaveInput.value;
            if (cumplidos === total && password === confirmar && password.length > 0) {
                btnCambiar.disabled = false;
            } else {
                btnCambiar.disabled = true;
            }
        }

        nuevaClaveInput.addEventListener('input', validarPassword);
        confirmarClaveInput.addEventListener('input', validarPassword);
    }

    // Countdown para verificación de código
    const countdownEl = document.getElementById('countdown');
    if (countdownEl && <?= json_encode($accion === 'verificar_codigo') ?>) {
        let tiempoRestante = 180; // 3 minutos
        countdownEl.innerHTML = `Tiempo restante: ${Math.floor(tiempoRestante / 60)}:${(tiempoRestante % 60).toString().padStart(2, '0')}`;
        
        const intervalo = setInterval(() => {
            tiempoRestante--;
            if (tiempoRestante <= 0) {
                clearInterval(intervalo);
                countdownEl.innerHTML = '<span style="color: red;">El código ha expirado</span>';
            } else {
                countdownEl.innerHTML = `Tiempo restante: ${Math.floor(tiempoRestante / 60)}:${(tiempoRestante % 60).toString().padStart(2, '0')}`;
            }
        }, 1000);
    }
});
</script>

</body>
</html>
