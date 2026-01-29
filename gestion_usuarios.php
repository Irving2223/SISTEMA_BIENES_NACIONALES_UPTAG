<?php
include("header.php");

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'Administrador') {
    // Si no es administrador, redirigir al dashboard o página principal
    header("Location: index.php");
    exit();
}

// Incluir conexión a la base de datos
include("conexion.php");

// Variables para manejar el estado
$accion = $_POST['accion'] ?? ($_GET['accion'] ?? '');
$mensaje_error = '';
$mensaje_exito = '';
$usuarios = [];
$usuario_a_editar = null;

// Función para obtener todos los usuarios
function obtenerUsuarios($conn) {
    $sql = "SELECT cedula, nombre, apellido, email, rol, activo FROM usuarios ORDER BY nombre";
    $result = mysqli_query($conn, $sql);
    $usuarios = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $usuarios[] = $row;
        }
    }
    return $usuarios;
}

// Cargar lista de usuarios al inicio
if (!$accion || in_array($accion, ['crear', 'editar'])) {
    $usuarios = obtenerUsuarios($conn);
}

// Manejar la acción de Editar un usuario específico
if ($accion === 'editar' && isset($_GET['cedula'])) {
    $cedula = mysqli_real_escape_string($conn, $_GET['cedula']);
    $sql = "SELECT cedula, nombre, apellido, email, rol, activo FROM usuarios WHERE cedula = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $cedula);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $usuario_a_editar = $row;
    } else {
        $mensaje_error = "Usuario no encontrado.";
    }
    mysqli_stmt_close($stmt);
}

// Manejar el registro del usuario (solo creación)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear') {
    $cedula = trim($_POST['cedula'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rol = $_POST['rol'] ?? '';
    $activo = isset($_POST['activo']) ? 1 : 0;
    $clave = $_POST['clave'] ?? '';

    // Validación
    $errores = [];
    if (empty($cedula)) $errores[] = "La cédula es obligatoria.";
    if (!preg_match('/^\d{7,8}$/', $cedula)) $errores[] = "La cédula debe tener entre 7 y 8 dígitos numéricos.";
    if (empty($nombre)) $errores[] = "El nombre es obligatorio.";
    if (empty($apellido)) $errores[] = "El apellido es obligatorio.";
    if (empty($email)) {
        $errores[] = "El correo electrónico es obligatorio.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El formato del correo electrónico es inválido.";
    }
    if (empty($rol)) $errores[] = "Debe seleccionar un rol.";
    if (empty($clave)) {
        $errores[] = "La contraseña es obligatoria para nuevos usuarios.";
    } else {
        // Validar complejidad de la contraseña
        if (strlen($clave) < 8) {
            $errores[] = "La contraseña debe tener al menos 8 caracteres.";
        }
        if (!preg_match('/[A-Z]/', $clave)) {
            $errores[] = "La contraseña debe contener al menos una letra mayúscula.";
        }
        if (!preg_match('/[a-z]/', $clave)) {
            $errores[] = "La contraseña debe contener al menos una letra minúscula.";
        }
        if (!preg_match('/\d/', $clave)) {
            $errores[] = "La contraseña debe contener al menos un número.";
        }
        if (!preg_match('/[^A-Za-z0-9]/', $clave)) {
            $errores[] = "La contraseña debe contener al menos un carácter especial.";
        }
    }

    if (empty($errores)) {
        try {
            // Verificar si ya existe
            $sql_check = "SELECT cedula FROM usuarios WHERE cedula = ?";
            $stmt_check = mysqli_prepare($conn, $sql_check);
            mysqli_stmt_bind_param($stmt_check, "s", $cedula);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            
            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                throw new Exception("Ya existe un usuario con esta cédula.");
            }
            mysqli_stmt_close($stmt_check);

            // Hash de la contraseña con password_hash
            $hash_clave = password_hash($clave, PASSWORD_DEFAULT);

            // Insertar nuevo usuario con email
            $sql_insert = "INSERT INTO usuarios (cedula, nombre, apellido, email, clave_usuario, rol, activo) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql_insert);
            mysqli_stmt_bind_param($stmt, "ssssssi", $cedula, $nombre, $apellido, $email, $hash_clave, $rol, $activo);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error al crear el usuario: " . mysqli_error($conn));
            }

            // Registrar en bitácora
            $detalle = "Creación de usuario: {$nombre} {$apellido}, Email: {$email}, Rol: {$rol}";
            $sql_bitacora = "INSERT INTO bitacora (cedula_usuario, accion, tabla_afectada, registro_afectado, detalle) VALUES (?, 'Registro', 'usuarios', ?, ?)";
            $stmt_bitacora = mysqli_prepare($conn, $sql_bitacora);
            mysqli_stmt_bind_param($stmt_bitacora, "sss", $_SESSION['usuario']['cedula'], $cedula, $detalle);
            mysqli_stmt_execute($stmt_bitacora);
            mysqli_stmt_close($stmt_bitacora);

            $mensaje_exito = "Usuario creado exitosamente.";

            // Recargar lista
            $usuarios = obtenerUsuarios($conn);
            
        } catch (Exception $e) {
            $mensaje_error = $e->getMessage();
        }
    } else {
        $mensaje_error = implode("<br>", $errores);
    }
}

// Manejar la actualización del usuario (sin cambiar contraseña)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'actualizar') {
    $cedula_actual = mysqli_real_escape_string($conn, $_POST['cedula_actual']);
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rol = $_POST['rol'] ?? '';
    $activo = isset($_POST['activo']) ? 1 : 0;

    // Validación
    $errores = [];
    if (empty($nombre)) $errores[] = "El nombre es obligatorio.";
    if (empty($apellido)) $errores[] = "El apellido es obligatorio.";
    if (empty($email)) {
        $errores[] = "El correo electrónico es obligatorio.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El formato del correo electrónico es inválido.";
    }
    if (empty($rol)) $errores[] = "Debe seleccionar un rol.";

    if (empty($errores)) {
        try {
            // Actualizar usuario (sin tocar la contraseña)
            $sql_update = "UPDATE usuarios SET nombre = ?, apellido = ?, email = ?, rol = ?, activo = ? WHERE cedula = ?";
            $stmt = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt, "ssssis", $nombre, $apellido, $email, $rol, $activo, $cedula_actual);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error al actualizar el usuario: " . mysqli_error($conn));
            }

            // Registrar en bitácora
            $estado = $activo ? "activado" : "desactivado";
            $detalle = "Edición de usuario: {$nombre} {$apellido}, Email: {$email}, Rol: {$rol}, Estado: {$estado}";
            $sql_bitacora = "INSERT INTO bitacora (cedula_usuario, accion, tabla_afectada, registro_afectado, detalle) VALUES (?, 'Edicion', 'usuarios', ?, ?)";
            $stmt_bitacora = mysqli_prepare($conn, $sql_bitacora);
            mysqli_stmt_bind_param($stmt_bitacora, "sss", $_SESSION['usuario']['cedula'], $cedula_actual, $detalle);
            mysqli_stmt_execute($stmt_bitacora);
            mysqli_stmt_close($stmt_bitacora);

            $mensaje_exito = "Usuario actualizado exitosamente.";

            // Recargar lista
            $usuarios = obtenerUsuarios($conn);
            
        } catch (Exception $e) {
            $mensaje_error = $e->getMessage();
        }
    } else {
        $mensaje_error = implode("<br>", $errores);
        // Mantener datos en caso de error
        $usuario_a_editar = [
            'cedula' => $cedula_actual,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email,
            'rol' => $rol,
            'activo' => $activo
        ];
    }
}
?>

<!-- Contenido de la página -->
<div class="container">

    <!-- Título principal -->
    <h1 style="font-family:montserrat; font-weight:900; color:green; padding:20px; text-align:left; font-size:50px;"><i class="zmdi zmdi-file-plus"></i> Gestión <span style="font-weight:700; color:black;">de Usuarios</span></h1>

    <!-- Botón para Crear Usuario -->
    <div class="section-container">
        <button type="button" class="btn btn-primary" onclick="window.location.href='?accion=crear'">
            <i class="zmdi zmdi-plus"></i> Crear Nuevo Usuario
        </button>
    </div>

    <?php if ($mensaje_error): ?>
        <div class="alert alert-danger mt-3"><?php echo $mensaje_error; ?></div>
    <?php endif; ?>

    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success mt-3"><?php echo $mensaje_exito; ?></div>
    <?php endif; ?>

    <!-- Formulario para Crear Usuario -->
    <?php if ($accion === 'crear'): ?>
    <div class="section-container">
        <h3 class="section-title">Crear Nuevo Usuario</h3>
        
        <form method="POST" action="" id="form-usuario">
            <input type="hidden" name="accion" value="crear">

            <div class="field-row">
                <div class="field-col">
                    <label class="field-label required">Cédula</label>
                    <input type="text" name="cedula" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['cedula'] ?? ''); ?>" required />
                </div>
                <div class="field-col">
                    <label class="field-label required">Nombre(s)</label>
                    <input type="text" name="nombre" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required />
                </div>
            </div>
            <div class="field-row">
                <div class="field-col">
                    <label class="field-label required">Apellido(s)</label>
                    <input type="text" name="apellido" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['apellido'] ?? ''); ?>" required />
                </div>
                <div class="field-col">
                    <label class="field-label required">Correo Electrónico</label>
                    <input type="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="usuario@dominio.com" required />
                </div>
            </div>
            <div class="field-row">
                <div class="field-col">
                    <label class="field-label required">Rol</label>
                    <select name="rol" class="form-control" required>
                        <option value="">Seleccionar...</option>
                        <option value="Administrador" <?php echo (isset($_POST['rol']) && $_POST['rol'] === 'Administrador') ? 'selected' : ''; ?>>Administrador</option>
                        <option value="Usuario" <?php echo (isset($_POST['rol']) && $_POST['rol'] === 'Usuario') ? 'selected' : ''; ?>>Usuario</option>
                    </select>
                </div>
                <div class="field-col">
                    <label class="field-label required">Contraseña</label>
                    <input type="password" name="clave" class="form-control" id="clave"
                           placeholder="Ingrese al menos 8 caracteres" required />
                    <div id="clave-feedback" class="invalid-feedback" style="display:block;"></div>
                </div>
            </div>
            <div class="field-row">
                <div class="field-col">
                    <label class="field-label">Estado</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" value="1" id="switchActivo" checked>
                        <label class="form-check-label" for="switchActivo">Activo</label>
                    </div>
                </div>
                <div class="field-col">
                    <!-- Espacio vacío para alinear botones -->
                </div>
            </div>

            <!-- Botones -->
            <div class="button-container">
                <a href="gestion_usuarios.php" class="btn btn-secondary">
                    <i class="zmdi zmdi-arrow-left"></i> Volver
                </a>
                <button type="submit" class="btn btn-primary" id="btn-submit" disabled>
                    <i class="zmdi zmdi-check"></i> Crear Usuario
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Lista de Usuarios -->
    <?php if (!$usuario_a_editar): ?>
    <div class="section-container">
        <h3 class="section-title">Usuarios Registrados</h3>
        <?php if (!empty($usuarios)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Cédula</th>
                            <th>Nombre Completo</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['cedula']); ?></td>
                            <td><?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellido']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['rol']); ?></td>
                            <td>
                                <span class="badge <?php echo $user['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $user['activo'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="?accion=editar&cedula=<?php echo $user['cedula']; ?>" class="btn btn-sm btn-primary">
                                    <i class="zmdi zmdi-edit"></i> Editar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No hay usuarios registrados.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Formulario para Editar Usuario -->
    <?php if ($accion === 'editar' && $usuario_a_editar): ?>
    <div class="section-container">
        <h3 class="section-title">Editar Usuario: <?php echo htmlspecialchars($usuario_a_editar['nombre'] . ' ' . $usuario_a_editar['apellido']); ?></h3>
        
        <form method="POST" action="">
            <input type="hidden" name="accion" value="actualizar">
            <input type="hidden" name="cedula_actual" value="<?php echo htmlspecialchars($usuario_a_editar['cedula']); ?>">

            <div class="field-row">
                <div class="field-col">
                    <label class="field-label required">Cédula</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario_a_editar['cedula']); ?>" readonly />
                </div>
                <div class="field-col">
                    <label class="field-label required">Nombre(s)</label>
                    <input type="text" name="nombre" class="form-control" 
                           value="<?php echo htmlspecialchars($usuario_a_editar['nombre']); ?>" required />
                </div>
            </div>
            <div class="field-row">
                <div class="field-col">
                    <label class="field-label required">Apellido(s)</label>
                    <input type="text" name="apellido" class="form-control" 
                           value="<?php echo htmlspecialchars($usuario_a_editar['apellido']); ?>" required />
                </div>
                <div class="field-col">
                    <label class="field-label required">Correo Electrónico</label>
                    <input type="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($usuario_a_editar['email']); ?>" placeholder="usuario@dominio.com" required />
                </div>
            </div>
            <div class="field-row">
                <div class="field-col">
                    <label class="field-label required">Rol</label>
                    <select name="rol" class="form-control" required>
                        <option value="">Seleccionar...</option>
                        <option value="Administrador" <?php echo ($usuario_a_editar['rol'] === 'Administrador') ? 'selected' : ''; ?>>Administrador</option>
                        <option value="Usuario" <?php echo ($usuario_a_editar['rol'] === 'Usuario') ? 'selected' : ''; ?>>Usuario</option>
                    </select>
                </div>
                <div class="field-col">
                    <label class="field-label">Estado</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" value="1" id="switchActivoEdit"
                               <?php echo ($usuario_a_editar['activo'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="switchActivoEdit">Activo</label>
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="button-container">
                <a href="gestion_usuarios.php?accion=editar" class="btn btn-secondary">
                    <i class="zmdi zmdi-arrow-left"></i> Volver a la lista
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="zmdi zmdi-check"></i> Actualizar Usuario
                </button>
            </div>
        </form>
    </div>
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

    // Validación en tiempo real de la contraseña (solo para creación)
    const claveInput = document.getElementById('clave');
    if (claveInput) {
        claveInput.addEventListener('input', function() {
            const clave = this.value;
            const feedback = document.getElementById('clave-feedback');
            const btnSubmit = document.getElementById('btn-submit');

            // Resetear estilos
            this.classList.remove('is-invalid', 'is-valid');
            feedback.innerHTML = '';
            
            if (clave.length === 0) {
                feedback.style.color = 'black';
                feedback.textContent = 'La contraseña debe tener:';
                btnSubmit.disabled = true;
                return;
            }

            let criterios = [];
            let cumplidos = 0;
            const total = 5;

            // Validar cada criterio
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

            // Mostrar resultados
            feedback.innerHTML = criterios.join('<br>');
            
            if (cumplidos === total) {
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
                btnSubmit.disabled = false;
            } else {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
                btnSubmit.disabled = true;
            }
        });
    }

    // Enfocar campo de cédula al cargar
    document.addEventListener('DOMContentLoaded', function() {
        const cedulaInput = document.querySelector('input[name="cedula"]');
        if (cedulaInput) {
            cedulaInput.focus();
        }
    });
</script>