<?php
session_start();


// Si ya está logueado, redirigir al home
if (isset($_SESSION['usuario']) && isset($_SESSION['usuario']['loggeado']) && $_SESSION['usuario']['loggeado'] === true) {
    header('Location: home.php');
    exit;
}

// Verificar si hay un mensaje de cierre de sesión por inactividad
$mensaje_cierre_sesion = '';
if (isset($_GET['inactividad'])) {
    $mensaje_cierre_sesion = "Su sesión ha sido cerrada por inactividad. Por favor, inicie sesión nuevamente.";
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include('conexion.php');
    
    $cedula = trim($_POST['username'] ?? '');
    $clave = trim($_POST['password'] ?? '');
    
    if (empty($cedula) || empty($clave)) {
        $mensaje = "Por favor, complete todos los campos";
        $tipo_mensaje = "error";
    } else {
        // Validar formato de cédula (solo 7-8 dígitos numéricos)
        if (!preg_match('/^[0-9]{7,8}$/', $cedula)) {
            $mensaje = "Formato de cédula inválido. Use solo números (7-8 dígitos)";
            $tipo_mensaje = "error";
        } else {
            try {
                // Consulta adaptada a la estructura real de la BD bienes_nacionales_uptag
                $stmt = $conn->prepare("SELECT cedula, nombres, rol, password_hash, activo FROM usuarios WHERE cedula = ?");
                $stmt->bind_param("s", $cedula);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $usuario_data = $result->fetch_assoc();
                    
                    // Verificar si la cuenta está activa
                    if ($usuario_data['activo'] != 1) {
                        $mensaje = "Su usuario está inactivo. Contacte al administrador.";
                        $tipo_mensaje = "error";
                        
                        // Registrar intento fallido en auditoria
                        if ($conn) {
                            $stmt_auditoria = $conn->prepare("INSERT INTO auditoria (tabla_afectada, accion, usuario_cedula, datos_nuevos, ip_address) VALUES ('usuarios', 'INSERT', ?, ?, ?)");
                            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                            $datos = json_encode(['intento' => 'sesion_fallida', 'motivo' => 'usuario_inactivo']);
                            $stmt_auditoria->bind_param("sss", $cedula, $datos, $ip);
                            $stmt_auditoria->execute();
                            $stmt_auditoria->close();
                        }
                    } 
                    // Verificar contraseña con password_verify
                    elseif (password_verify($clave, $usuario_data['password_hash'])) {
                        // Crear sesión con datos del usuario
                        $_SESSION['usuario'] = [
                            'cedula' => $usuario_data['cedula'],
                            'nombre' => $usuario_data['nombres'],
                            'apellido' => '',
                            'nombre_completo' => trim($usuario_data['nombres']),
                            'rol' => $usuario_data['rol'],
                            'loggeado' => true,
                            'ultimo_acceso' => time()
                        ];

                        
                        // Registrar en auditoria el inicio de sesión
                        $stmt_auditoria = $conn->prepare("INSERT INTO auditoria (tabla_afectada, accion, usuario_cedula, datos_nuevos, ip_address) VALUES ('usuarios', 'INSERT', ?, ?, ?)");
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                        $datos = json_encode(['accion' => 'inicio_sesion_exitoso']);
                        $stmt_auditoria->bind_param("sss", $cedula, $datos, $ip);
                        $stmt_auditoria->execute();
                        $stmt_auditoria->close();
                        
                        // Cerrar conexión después de guardar todo
                        if (isset($conn) && $conn) {
                            $conn->close();
                        }
                        
                        header("Location: home.php");
                        exit();
                    } else {
                        $mensaje = "Cédula o contraseña incorrectas";
                        $tipo_mensaje = "error";
                        
                        // Registrar intento fallido en auditoria
                        if ($conn) {
                            $stmt_auditoria = $conn->prepare("INSERT INTO auditoria (tabla_afectada, accion, usuario_cedula, datos_nuevos, ip_address) VALUES ('usuarios', 'INSERT', ?, ?, ?)");
                            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                            $datos = json_encode(['intento' => 'sesion_fallida', 'motivo' => 'credenciales_incorrectas']);
                            $stmt_auditoria->bind_param("sss", $cedula, $datos, $ip);
                            $stmt_auditoria->execute();
                            $stmt_auditoria->close();
                        }
                    }
                } else {
                    $mensaje = "Cédula o contraseña incorrectas";
                    $tipo_mensaje = "error";
                    
                    // Registrar intento fallido en auditoria
                    if ($conn) {
                        $stmt_auditoria = $conn->prepare("INSERT INTO auditoria (tabla_afectada, accion, usuario_cedula, datos_nuevos, ip_address) VALUES ('usuarios', 'INSERT', ?, ?, ?)");
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                        $datos = json_encode(['intento' => 'sesion_fallida', 'motivo' => 'usuario_no_encontrado']);
                        $stmt_auditoria->bind_param("sss", $cedula, $datos, $ip);
                        $stmt_auditoria->execute();
                        $stmt_auditoria->close();
                    }
                }
                
                $stmt->close();
            } catch (Exception $e) {
                $mensaje = "Error en el sistema. Intente nuevamente.";
                $tipo_mensaje = "error";
                error_log("Error de login: " . $e->getMessage());
            }
            
            if (isset($conn) && $conn) {
                $conn->close();
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
    <link rel="icon" href="imagenes/LOGO INTI.png" type="image/x-icon">

    <!-- Favicons -->
    <link href="assets/img/LOGO INTI.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">


    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">

    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="assets/css/estilos_loggin.css">

    <title>Inicio de Sesión</title>

</head>
<body>

    <!-- Mensaje de cierre por inactividad -->
    <?php if (!empty($mensaje_cierre_sesion)): ?>
        <div style="background: #f8d7da; color: #721c24; text-align:center; padding:10px; font-size:clamp(1rem, 5vw, 25px); font-weight:700;">
            <?= htmlspecialchars($mensaje_cierre_sesion) ?>
        </div>
    <?php endif; ?>

    <!-- Aquí se muestra el mensaje de error si existe -->
    <?php if (!empty($mensaje)): ?>
        <div style="background: #f8d7da; color: #721c24; text-align:center; padding:10px; font-size:clamp(1rem, 5vw, 25px); font-weight:700;">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <div class="centered-container">
        <div class="modern-login-container">

            <!-- Logo circular -->
            <img src="assets/img/LOGO INTI.png" alt="Logo INTI" class="modern-logo" style="pointer-events: none;">

            <!-- Formulario de login -->
            <form class="modern-login-form" action="" method="POST" autocomplete="off">
                <!-- Campo: Usuario -->
                <div class="modern-input-group">
                    <span class="modern-input-icon">
                        <svg width="20" height="20" fill="none" stroke="#888" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </span>
                    <input 
                        type="number" 
                        id="username"
                        style="font-weight: 600;"
                        name="username" 
                        placeholder="Cédula" 
                        autocomplete="off" 
                        required 
                        value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                    />
                </div>

                <!-- Campo: Contraseña -->
                <div class="modern-input-group">
                    <span class="modern-input-icon">
                        <svg width="20" height="20" fill="none" stroke="#888" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="10" rx="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        style="font-weight: 600;"
                        placeholder="Contraseña"
                        autocomplete="off"
                        required
                    />
                    <br>
                </div>

                <!-- Botón de inicio de sesión -->
                <button type="submit" class="modern-login-button" style="font-weight: 700; background: linear-gradient(90deg, #ffc400 0%, #ff9900 100%);">Iniciar Sesión</button>
            </form>

            <!-- Enlace para recuperar contraseña -->
            <div class="text-center mt-3">
                <a href="recuperar_contraseña.php" style="color: #ff6600; text-decoration: none; font-size: 14px; font-weight: 300;">
                    ¿Olvidó su contraseña?
                </a>
                <br>    
                <a href="index.php" style="color: #ff6600; text-decoration: none; font-size: 14px; font-weight: 300;">
                    Volver al Inicio
                </a>
            </div>
        </div>
    </div>

    <script>
        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const cedula = document.getElementById('username').value.trim();
            const clave = document.getElementById('password').value.trim();
            
            if (!cedula || !clave) {
                e.preventDefault();
                return;
            }
            
            // Validar que la cédula tenga el formato correcto (solo números, 7-8 dígitos)
            const cedulaRegex = /^[0-9]{7,8}$/;
            if (!cedulaRegex.test(cedula)) {
                e.preventDefault();
                alert('La cédula debe tener entre 7 y 8 dígitos numéricos');
                document.getElementById('username').focus();
                return false;
            }
        });

        // Solo permitir números en el campo de cédula
        document.getElementById('username').addEventListener('input', function(e) {
            // Eliminar cualquier carácter que no sea número
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Limitar a 8 caracteres (máximo 8 dígitos)
            if (this.value.length > 8) {
                this.value = this.value.substring(0, 8);
            }
        });
    </script>
</body>
</html>
