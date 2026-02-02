<?php
include('header.php');
include('conexion.php');

$cedula = $_SESSION['usuario']['cedula'];
$mensaje_error = '';
$mensaje_exito = '';

// Usar datos de la sesión directamente (ya fueron obtenidos durante el login)
$usuario = [
    'nombre_completo' => trim(($_SESSION['usuario']['nombre'] ?? '') . ' ' . ($_SESSION['usuario']['apellido'] ?? '')),
    'rol' => $_SESSION['usuario']['rol'] ?? 'Usuario',
    'email' => $_SESSION['usuario']['email'] ?? ''
];

// Si necesitamos obtener email actualizado de la BD, intentar con estructura flexible
try {
    $check_columns = $conn->prepare("SHOW COLUMNS FROM usuarios LIKE 'email'");
    if ($check_columns && $check_columns->execute() && $check_columns->get_result()->num_rows > 0) {
        $check_columns->close();
        // Intentar obtener email - usando la cedula como identificador
        $email_query = $conn->prepare("SELECT email FROM usuarios WHERE cedula = ?");
        if ($email_query) {
            $email_query->bind_param("s", $cedula);
            $email_query->execute();
            $email_result = $email_query->get_result();
            if ($email_result->num_rows > 0) {
                $email_data = $email_result->fetch_assoc();
                $usuario['email'] = $email_data['email'] ?? '';
            }
            $email_query->close();
        }
    } else {
        $check_columns->close();
    }
} catch (Exception $e) {
    // Si hay error, usar el email de la sesión
    error_log("Error al obtener email: " . $e->getMessage());
}


// Procesar formulario si se envío
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // === CAMBIAR CONTRASEÑA ===
    if ($accion === 'cambiar_contrasena') {
        $nueva_contrasena = $_POST['nueva_contrasena'] ?? '';
        $confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';

        // Validaciones
        $errores = [];

        if (empty($nueva_contrasena)) {
            $errores[] = "La nueva contraseña es obligatoria.";
        } elseif (strlen($nueva_contrasena) < 8) {
            $errores[] = "La nueva contraseña debe tener al menos 8 caracteres.";
        } elseif (!preg_match('/[A-Z]/', $nueva_contrasena)) {
            $errores[] = "La nueva contraseña debe contener al menos una letra mayúscula.";
        } elseif (!preg_match('/[a-z]/', $nueva_contrasena)) {
            $errores[] = "La nueva contraseña debe contener al menos una letra minúscula.";
        } elseif (!preg_match('/\d/', $nueva_contrasena)) {
            $errores[] = "La nueva contraseña debe contener al menos un número.";
        } elseif (!preg_match('/[^A-Za-z0-9]/', $nueva_contrasena)) {
            $errores[] = "La nueva contraseña debe contener al menos un carácter especial.";
        }

        if ($nueva_contrasena !== $confirmar_contrasena) {
            $errores[] = "Las contraseñas no coinciden.";
        }

        if (empty($errores)) {
            // Hashear y actualizar
            $hash_nuevo = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
            $update = "UPDATE usuarios SET password_hash = ? WHERE cedula = ?";
            $stmt_update = $conn->prepare($update);
            $stmt_update->bind_param("ss", $hash_nuevo, $cedula);

            if ($stmt_update->execute()) {
                // Registrar en auditoria
                $detalle = "Cambio de contraseña";
                $auditoria = "INSERT INTO auditoria (tabla_afectada, accion, usuario_cedula, datos_nuevos, ip_address) VALUES ('usuarios', 'UPDATE', ?, ?, ?)";
                $stmt_aud = $conn->prepare($auditoria);
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $datos = json_encode(['password' => 'cambiado']);
                $stmt_aud->bind_param("sss", $cedula, $datos, $ip);
                $stmt_aud->execute();
                $stmt_aud->close();

                $mensaje_exito = "Contraseña actualizada correctamente.";
            } else {
                $mensaje_error = "Error al actualizar la contraseña.";
            }
            $stmt_update->close();
        } else {
            $mensaje_error = implode("<br>", $errores);
        }
    }

    // === CAMBIAR CORREO ===
    elseif ($accion === 'cambiar_correo') {
        $email_actual = trim($_POST['email_actual'] ?? '');
        $nuevo_email = trim($_POST['nuevo_email'] ?? '');

        // Validaciones
        $errores = [];
        if (empty($email_actual)) $errores[] = "El correo actual es obligatorio.";
        if (empty($nuevo_email)) $errores[] = "El nuevo correo es obligatorio.";
        if (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) $errores[] = "El formato del nuevo correo es inválido.";
        if ($email_actual !== $usuario['email']) $errores[] = "El correo actual no coincide.";

        // Verificar si ya existe otro usuario con ese correo
        $check = "SELECT cedula FROM usuarios WHERE email = ? AND cedula != ?";
        $stmt_check = $conn->prepare($check);
        $stmt_check->bind_param("ss", $nuevo_email, $cedula);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $errores[] = "Este correo ya está en uso por otro usuario.";
        }
        $stmt_check->close();

        if (empty($errores)) {
            $update = "UPDATE usuarios SET email = ? WHERE cedula = ?";
            $stmt_update = $conn->prepare($update);
            $stmt_update->bind_param("ss", $nuevo_email, $cedula);

            if ($stmt_update->execute()) {
                // Registrar en auditoria
                $auditoria = "INSERT INTO auditoria (tabla_afectada, accion, usuario_cedula, datos_anteriores, datos_nuevos, ip_address) VALUES ('usuarios', 'UPDATE', ?, ?, ?, ?)";
                $stmt_aud = $conn->prepare($auditoria);
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $datos_ant = json_encode(['email' => $email_actual]);
                $datos_nue = json_encode(['email' => $nuevo_email]);
                $stmt_aud->bind_param("ssss", $cedula, $datos_ant, $datos_nue, $ip);
                $stmt_aud->execute();
                $stmt_aud->close();

                $_SESSION['usuario']['email'] = $nuevo_email;
                $usuario['email'] = $nuevo_email;
                $mensaje_exito = "Correo actualizado correctamente.";
            } else {
                $mensaje_error = "Error al actualizar el correo.";
            }
            $stmt_update->close();
        } else {
            $mensaje_error = implode("<br>", $errores);
        }
    }
}
?>

<div class="container">

    <!-- Mensajes arriba del título -->
    <?php if ($mensaje_error): ?>
        <div class="alert alert-danger section-container">
            <?= $mensaje_error ?>
        </div>
    <?php endif; ?>

    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success section-container">
            <?= $mensaje_exito ?>
        </div>
    <?php endif; ?>

    <!-- Título principal -->


    <?php if (isset($usuario)): ?>
        <!-- Sección: Imagen y Nombre -->
        <div class="section-container" style="margin-bottom:25px; background:url('assets/img/sidebar/sidebar.webp'); background-size:cover; background-position:center; background-repeat:no-repeat;">
            <div style="display:flex; align-items:center; gap:30px; flex-wrap:wrap; padding:20px;">
                <div>
                    <p style="margin:4px 0; color:white; text-shadow:0.1px 0.1px 10px black;"><strong>Usuario:</strong></p>
                    <h2 style="margin:0; font-size:clamp(2rem, 8vw, 80px); color:white; font-weight:900; font-family:montserrat; text-shadow:0.1px 0.1px 10px black;"> <i class="zmdi zmdi-account"></i>   <?= htmlspecialchars($_SESSION['usuario']['nombre'] . ' ' . $_SESSION['usuario']['apellido']) ?></h2>
                    <p style="margin:4px 0; color:white; text-shadow:0.1px 0.1px 10px black;">Cédula: <strong> <?= htmlspecialchars($cedula) ?> </strong></p>
                    <span style="background:white; color:black; padding:6px 12px; border-radius:6px; font-size:0.9rem; font-weight:800;">
                        <?= htmlspecialchars($usuario['rol']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Sección: Información del usuario -->
        <div class="section-container">
            <!-- Botones principales -->
            <div class="d-flex justify-content-center justify-content-md-end flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-primary" onclick="mostrarFormulario('correo', true)">
                    <i class="zmdi zmdi-email"></i> Cambiar Correo
                </button>
                <button type="button" class="btn btn-primary" onclick="mostrarFormulario('contrasena', true)">
                    <i class="zmdi zmdi-lock"></i> Cambiar Contraseña
                </button>
            </div>

            <!-- Tabla con datos del usuario -->
            <div class="table-responsive">
                <table style="width:100%; border-collapse:collapse; font-size:0.95rem;">
                    <thead>
                        <tr>
                            <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700; border-bottom:2px solid #ddd;">Campo</th>
                            <th style="padding:14px 15px; background:#f0f0f0; color:#333; text-align:left; font-weight:700; border-bottom:2px solid #ddd;">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td data-label="Campo" style="padding:14px 15px; border-bottom:1px solid #eee; font-weight:600; color:#ff6600;">Nombre Completo</td>
                            <td data-label="Valor" style="padding:14px 15px; border-bottom:1px solid #eee; color:#333;">
                                <?= htmlspecialchars($_SESSION['usuario']['nombre'] . ' ' . $_SESSION['usuario']['apellido']) ?>
                            </td>
                        </tr>
                        <tr>
                            <td data-label="Campo" style="padding:14px 15px; border-bottom:1px solid #eee; font-weight:600; color:#ff6600;">Cédula</td>
                            <td data-label="Valor" style="padding:14px 15px; border-bottom:1px solid #eee; color:#333;">
                                <?= htmlspecialchars($cedula) ?>
                            </td>
                        </tr>
                        <tr>
                            <td data-label="Campo" style="padding:14px 15px; border-bottom:1px solid #eee; font-weight:600; color:#ff6600;">Correo Electrónico</td>
                            <td data-label="Valor" style="padding:14px 15px; border-bottom:1px solid #eee; color:#333;" id="email-display">
                                <?= htmlspecialchars($usuario['email']) ?>
                            </td>
                        </tr>
                        <tr>
                            <td data-label="Campo" style="padding:14px 15px; border-bottom:1px solid #eee; font-weight:600; color:#ff6600;">Rol</td>
                            <td data-label="Valor" style="padding:14px 15px; border-bottom:1px solid #eee; color:#333;">
                                <?= htmlspecialchars($usuario['rol']) ?>
                            </td>
                        </tr>
                        <tr>
                            <td data-label="Campo" style="padding:14px 15px; border-bottom:1px solid #eee; font-weight:600; color:#ff6600;">Estado</td>
                            <td data-label="Valor" style="padding:14px 15px; border-bottom:1px solid #eee; color:#333;">
                                <span class="status" style="background:#d4edda; color:#155724; padding:6px 12px; border-radius:4px; font-size:0.8rem; font-weight:600;">
                                    Activo
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Formulario: Cambiar Correo -->
            <div id="form-correo" class="section-container" style="display:none; margin-top:25px;">
                <h2 class="section-title">Actualizar Correo Electrónico</h2>
                <form method="POST" action="" style="margin-bottom:20px;">
                    <input type="hidden" name="accion" value="cambiar_correo">
                    <div class="field-row">
                        <div class="field-col">
                            <label for="email_actual" class="field-label required">Correo Actual</label>
                            <input type="email" name="email_actual" value="<?= htmlspecialchars($usuario['email']) ?>" readonly class="form-control" style="background:#eee;">
                        </div>
                        <div class="field-col">
                            <label for="nuevo_email" class="field-label required">Nuevo Correo</label>
                            <input type="email" name="nuevo_email" required class="form-control">
                        </div>
                    </div>
                    <div class="d-flex justify-content-center justify-content-md-end flex-wrap gap-2 mt-3">
                        <button type="button" class="btn btn-secondary" onclick="ocultarFormulario('correo')">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Correo</button>
                    </div>
                </form>
            </div>

            <!-- Formulario: Cambiar Contraseña -->
            <div id="form-contrasena" class="section-container" style="display:none; margin-top:25px;">
                <h2 class="section-title">Actualizar Contraseña</h2>
                <form method="POST" action="" id="form-cambiar-contrasena">
                    <input type="hidden" name="accion" value="cambiar_contrasena">

                    <div class="field-row">
                        <div class="field-col">
                            <label for="nueva_contrasena" class="field-label required">Nueva Contraseña</label>
                            <input type="password" name="nueva_contrasena" id="nueva_contrasena" class="form-control" required minlength="8">
                            <div id="clave-feedback" class="invalid-feedback" style="display:block; margin-top:6px;"></div>
                        </div>
                        <div class="field-col">
                            <label for="confirmar_contrasena" class="field-label required">Confirmar Nueva Contraseña</label>
                            <input type="password" name="confirmar_contrasena" id="confirmar_contrasena" class="form-control" required minlength="8">
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="d-flex justify-content-center justify-content-md-end flex-wrap gap-2 mt-3">
                        <button type="button" class="btn btn-secondary" onclick="ocultarFormulario('contrasena')">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btn-submit" disabled>
                            Actualizar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sección: Manual de Usuario -->
        <div class="section-container">
            <h2 class="section-title">Documentación</h2>
            <p style="color:#555; margin-bottom:20px;">Consulta el manual oficial para aprender a usar todas las funciones del sistema.</p>
            <div style="text-align:center;">
                <a href="assets/manual_de_usuario.pdf" target="_blank" class="btn btn-primary" style="background-color:#ff6600; border:2px solid #ff6600; color:white; padding:12px 24px;">
                    <i class="zmdi zmdi-file-text"></i> Manual de Usuario
                </a>

                <a href="assets/Manual de Requerimientos ERS_ Sistema INTI.pdf" target="_blank" class="btn btn-primary" style="background-color:#ff6600; border:2px solid #ff6600; color:white; padding:12px 24px;">
                    <i class="zmdi zmdi-file-text"></i> Manual requerimientos
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Scripts locales -->
<script src="./js/jquery-3.1.1.min.js"></script>
<script src="./js/bootstrap.min.js"></script>
<script src="./js/material.min.js"></script>
<script src="./js/ripples.min.js"></script>
<script src="./js/jquery.mCustomScrollbar.concat.min.js"></script>
<script src="./js/main.js"></script>
<script>
    $.material.init();

    function mostrarFormulario(tipo, scroll = false) {
        document.getElementById('form-correo').style.display = 'none';
        document.getElementById('form-contrasena').style.display = 'none';
        const form = document.getElementById(`form-${tipo}`);
        form.style.display = 'block';

        if (scroll) {
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function ocultarFormulario(tipo) {
        document.getElementById(`form-${tipo}`).style.display = 'none';
    }

    // Validación en tiempo real como en gestion_usuarios.php
    const claveInput = document.getElementById('nueva_contrasena');
    if (claveInput) {
        claveInput.addEventListener('input', function() {
            const clave = this.value;
            const feedback = document.getElementById('clave-feedback');
            const btnSubmit = document.getElementById('btn-submit');

            this.classList.remove('is-invalid', 'is-valid');
            feedback.innerHTML = '';

            if (clave.length === 0) {
                feedback.textContent = 'La contraseña debe tener:';
                btnSubmit.disabled = true;
                return;
            }

            let criterios = [];
            let cumplidos = 0;
            const total = 5;

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

            if (/\d/.test(clave)) {
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

            const confirmar = document.getElementById('confirmar_contrasena').value;
            if (cumplidos === total && clave === confirmar) {
                this.classList.add('is-valid');
                btnSubmit.disabled = false;
            } else {
                this.classList.add('is-invalid');
                btnSubmit.disabled = true;
            }
        });

        document.getElementById('confirmar_contrasena')?.addEventListener('input', function() {
            const clave = claveInput.value;
            const confirmar = this.value;
            const btnSubmit = document.getElementById('btn-submit');
            if (clave && confirmar && clave === confirmar && document.querySelectorAll('#clave-feedback span[style*="red"]').length === 0) {
                btnSubmit.disabled = false;
            } else {
                btnSubmit.disabled = true;
            }
        });
    }
</script>

<?php include("footer.php"); ?>
