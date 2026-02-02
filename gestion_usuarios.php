<?php
include("header.php");

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'Administrador') {
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

// Descubrir estructura real de la tabla usuarios
$columnas_usuarios = [];
$result_cols = $conn->query("SHOW COLUMNS FROM usuarios");
if ($result_cols) {
    while ($col = $result_cols->fetch_assoc()) {
        $columnas_usuarios[] = $col['Field'];
    }
}

// Mapear columnas conocidas
$col_cedula = in_array('cedula', $columnas_usuarios) ? 'cedula' : null;
$col_nombre = in_array('nombres', $columnas_usuarios) ? 'nombres' : (in_array('nombre', $columnas_usuarios) ? 'nombre' : null);
$col_apellido1 = in_array('primer_apellido', $columnas_usuarios) ? 'primer_apellido' : (in_array('apellidos', $columnas_usuarios) ? 'apellidos' : (in_array('apellido', $columnas_usuarios) ? 'apellido' : null));
$col_apellido2 = in_array('segundo_apellido', $columnas_usuarios) ? 'segundo_apellido' : null;
$col_email = in_array('email', $columnas_usuarios) ? 'email' : null;
$col_rol = in_array('rol', $columnas_usuarios) ? 'rol' : null;
$col_activo = in_array('activo', $columnas_usuarios) ? 'activo' : null;
$col_clave = in_array('clave_usuario', $columnas_usuarios) ? 'clave_usuario' : (in_array('password', $columnas_usuarios) ? 'password' : (in_array('password_hash', $columnas_usuarios) ? 'password_hash' : null));

// Verificar que tenemos las columnas mínimas necesarias
if (!$col_cedula) {
    die("Error: La tabla usuarios no tiene una columna de cédula identificada.");
}

// Si no hay columna de nombre, buscar cualquier columna de texto
if (!$col_nombre) {
    foreach ($columnas_usuarios as $col) {
        if ($col !== $col_cedula && $col !== $col_email && $col !== $col_rol && $col !== $col_activo && $col !== $col_clave && strpos($col, '_id') === false) {
            $col_nombre = $col;
            break;
        }
    }
}

// Si no hay columna de apellido, usar la misma que nombre
if (!$col_apellido1) {
    $col_apellido1 = $col_nombre;
}

// Función para obtener todos los usuarios
function obtenerUsuarios($conn, $cols) {
    $usuarios = [];
    
    // Construir consulta solo con columnas que existen
    $select_cols = [$cols['cedula']];
    $order_cols = [];
    
    if ($cols['nombre']) {
        $select_cols[] = $cols['nombre'];
        $order_cols[] = $cols['nombre'];
    }
    
    if ($cols['apellido1']) {
        $select_cols[] = $cols['apellido1'];
        if (!$cols['nombre']) {
            $order_cols[] = $cols['apellido1'];
        }
    }
    
    if ($cols['apellido2']) {
        $select_cols[] = $cols['apellido2'];
    }
    
    if ($cols['email']) $select_cols[] = $cols['email'];
    if ($cols['rol']) $select_cols[] = $cols['rol'];
    if ($cols['activo']) $select_cols[] = $cols['activo'];
    
    $sql = "SELECT " . implode(', ', $select_cols) . " FROM usuarios";
    if (!empty($order_cols)) {
        $sql .= " ORDER BY " . implode(', ', $order_cols);
    }
    
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $nombre = $row[$cols['nombre']] ?? '';
            $apellido = '';
            
            if ($cols['apellido1']) {
                if ($cols['apellido2']) {
                    $apellido = trim(($row[$cols['apellido1']] ?? '') . ' ' . ($row[$cols['apellido2']] ?? ''));
                } else {
                    $apellido = $row[$cols['apellido1']] ?? '';
                }
            }
            
            $usuarios[] = [
                'cedula' => $row[$cols['cedula']] ?? '',
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $row[$cols['email']] ?? '',
                'rol' => $row[$cols['rol']] ?? '',
                'activo' => $row[$cols['activo']] ?? 0
            ];
        }
    }
    return $usuarios;
}

// Cargar lista de usuarios al inicio
if (!$accion || in_array($accion, ['crear', 'editar'])) {
    $usuarios = obtenerUsuarios($conn, [
        'cedula' => $col_cedula,
        'nombre' => $col_nombre,
        'apellido1' => $col_apellido1,
        'apellido2' => $col_apellido2,
        'email' => $col_email,
        'rol' => $col_rol,
        'activo' => $col_activo
    ]);
}

// Manejar la acción de Editar un usuario específico
if ($accion === 'editar' && isset($_GET['cedula'])) {
    $cedula = $conn->real_escape_string($_GET['cedula']);
    
    $select_cols = [$col_cedula];
    if ($col_nombre) $select_cols[] = $col_nombre;
    if ($col_apellido1) $select_cols[] = $col_apellido1;
    if ($col_apellido2) $select_cols[] = $col_apellido2;
    if ($col_email) $select_cols[] = $col_email;
    if ($col_rol) $select_cols[] = $col_rol;
    if ($col_activo) $select_cols[] = $col_activo;
    
    $sql = "SELECT " . implode(', ', $select_cols) . " FROM usuarios WHERE {$col_cedula} = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $cedula);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $nombre = $row[$col_nombre] ?? '';
        if ($col_apellido2) {
            $apellido = trim(($row[$col_apellido1] ?? '') . ' ' . ($row[$col_apellido2] ?? ''));
        } elseif ($col_apellido1) {
            $apellido = $row[$col_apellido1] ?? '';
        } else {
            $apellido = '';
        }
        
        $usuario_a_editar = [
            'cedula' => $row[$col_cedula] ?? '',
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $row[$col_email] ?? '',
            'rol' => $row[$col_rol] ?? '',
            'activo' => $row[$col_activo] ?? 0
        ];
    } else {
        $mensaje_error = "Usuario no encontrado.";
    }
    $stmt->close();
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
    if (empty($nombre)) $errores[] = "El nombre es obligatorio.";
    if (empty($email)) {
        $errores[] = "El correo electrónico es obligatorio.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El formato del correo electrónico es inválido.";
    }
    if (empty($rol)) $errores[] = "Debe seleccionar un rol.";
    if (empty($clave)) {
        $errores[] = "La contraseña es obligatoria para nuevos usuarios.";
    } elseif (strlen($clave) < 8) {
        $errores[] = "La contraseña debe tener al menos 8 caracteres.";
    }

    if (empty($errores)) {
        try {
            // Verificar si ya existe
            $sql_check = "SELECT {$col_cedula} FROM usuarios WHERE {$col_cedula} = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("s", $cedula);
            $stmt_check->execute();
            $stmt_check->store_result();
            
            if ($stmt_check->num_rows > 0) {
                throw new Exception("Ya existe un usuario con esta cédula.");
            }
            $stmt_check->close();

            // Hash de la contraseña
            $hash_clave = password_hash($clave, PASSWORD_DEFAULT);

            // Insertar usuario
            $campos = [$col_cedula, $col_nombre];
            $valores = "?, ?";
            $tipos = "ss";
            $params = [$cedula, $nombre];
            
            if ($col_apellido2) {
                $apellidos = explode(' ', trim($apellido), 2);
                $primer_apellido = $apellidos[0] ?? '';
                $segundo_apellido = $apellidos[1] ?? '';
                $campos[] = $col_apellido1;
                $campos[] = $col_apellido2;
                $valores .= ", ?, ?";
                $tipos .= "ss";
                $params[] = $primer_apellido;
                $params[] = $segundo_apellido;
            } elseif ($col_apellido1 && $col_apellido1 !== $col_nombre) {
                $campos[] = $col_apellido1;
                $valores .= ", ?";
                $tipos .= "s";
                $params[] = $apellido;
            }
            
            if ($col_email) {
                $campos[] = $col_email;
                $valores .= ", ?";
                $tipos .= "s";
                $params[] = $email;
            }
            if ($col_clave) {
                $campos[] = $col_clave;
                $valores .= ", ?";
                $tipos .= "s";
                $params[] = $hash_clave;
            }
            if ($col_rol) {
                $campos[] = $col_rol;
                $valores .= ", ?";
                $tipos .= "s";
                $params[] = $rol;
            }
            if ($col_activo) {
                $campos[] = $col_activo;
                $valores .= ", ?";
                $tipos .= "i";
                $params[] = $activo;
            }

            $sql_insert = "INSERT INTO usuarios (" . implode(', ', $campos) . ") VALUES (" . $valores . ")";
            $stmt = $conn->prepare($sql_insert);
            $stmt->bind_param($tipos, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al crear el usuario: " . $conn->error);
            }

            $mensaje_exito = "Usuario creado exitosamente.";
            $usuarios = obtenerUsuarios($conn, [
                'cedula' => $col_cedula,
                'nombre' => $col_nombre,
                'apellido1' => $col_apellido1,
                'apellido2' => $col_apellido2,
                'email' => $col_email,
                'rol' => $col_rol,
                'activo' => $col_activo
            ]);
            
        } catch (Exception $e) {
            $mensaje_error = $e->getMessage();
        }
    } else {
        $mensaje_error = implode("<br>", $errores);
    }
}

// Manejar la actualización del usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'actualizar') {
    $cedula_actual = $conn->real_escape_string($_POST['cedula_actual']);
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rol = $_POST['rol'] ?? '';
    $activo = isset($_POST['activo']) ? 1 : 0;

    $errores = [];
    if (empty($nombre)) $errores[] = "El nombre es obligatorio.";
    if (empty($email)) $errores[] = "El correo electrónico es obligatorio.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "El formato del correo es inválido.";
    if (empty($rol)) $errores[] = "Debe seleccionar un rol.";

    if (empty($errores)) {
        try {
            // Actualizar usuario
            $sets = [];
            $params = [];
            $tipos = "";
            
            if ($col_nombre) {
                $sets[] = "{$col_nombre} = ?";
                $params[] = $nombre;
                $tipos .= "s";
            }
            
            if ($col_apellido1 && $col_apellido1 !== $col_nombre) {
                if ($col_apellido2) {
                    $apellidos = explode(' ', trim($apellido), 2);
                    $primer_apellido = $apellidos[0] ?? '';
                    $segundo_apellido = $apellidos[1] ?? '';
                    $sets[] = "{$col_apellido1} = ?";
                    $sets[] = "{$col_apellido2} = ?";
                    $params[] = $primer_apellido;
                    $params[] = $segundo_apellido;
                    $tipos .= "ss";
                } else {
                    $sets[] = "{$col_apellido1} = ?";
                    $params[] = $apellido;
                    $tipos .= "s";
                }
            }
            
            if ($col_email) {
                $sets[] = "{$col_email} = ?";
                $params[] = $email;
                $tipos .= "s";
            }
            if ($col_rol) {
                $sets[] = "{$col_rol} = ?";
                $params[] = $rol;
                $tipos .= "s";
            }
            if ($col_activo) {
                $sets[] = "{$col_activo} = ?";
                $params[] = $activo;
                $tipos .= "i";
            }
            
            $params[] = $cedula_actual;
            $tipos .= "s";
            
            $sql_update = "UPDATE usuarios SET " . implode(", ", $sets) . " WHERE {$col_cedula} = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param($tipos, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al actualizar el usuario: " . $conn->error);
            }

            $mensaje_exito = "Usuario actualizado exitosamente.";
            $usuarios = obtenerUsuarios($conn, [
                'cedula' => $col_cedula,
                'nombre' => $col_nombre,
                'apellido1' => $col_apellido1,
                'apellido2' => $col_apellido2,
                'email' => $col_email,
                'rol' => $col_rol,
                'activo' => $col_activo
            ]);
            
        } catch (Exception $e) {
            $mensaje_error = $e->getMessage();
        }
    } else {
        $mensaje_error = implode("<br>", $errores);
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
    <h1 style="font-family:montserrat; font-weight:900; color:#ff6600; padding:20px; text-align:left; font-size:50px;">
        <i class="zmdi zmdi-account-circle"></i> Gestión <span style="font-weight:700; color:black;">de Usuarios</span>
    </h1>

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
                    <label class="field-label">Apellido(s)</label>
                    <input type="text" name="apellido" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['apellido'] ?? ''); ?>" />
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
            </div>

            <!-- Botones -->
            <div class="button-container">
                <a href="gestion_usuarios.php" class="btn btn-secondary">
                    <i class="zmdi zmdi-arrow-left"></i> Volver
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="zmdi zmdi-check"></i> Crear Usuario
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Lista de Usuarios -->
    <?php if (!$usuario_a_editar): ?>
    <div class="section-container">
        <h3 class="section-title">Usuarios Registrados (<?php echo count($usuarios); ?>)</h3>
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
                    <label class="field-label">Apellido(s)</label>
                    <input type="text" name="apellido" class="form-control" 
                           value="<?php echo htmlspecialchars($usuario_a_editar['apellido']); ?>" />
                </div>
                <div class="field-col">
                    <label class="field-label required">Correo Electrónico</label>
                    <input type="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($usuario_a_editar['email']); ?>" required />
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
                <a href="gestion_usuarios.php" class="btn btn-secondary">
                    <i class="zmdi zmdi-arrow-left"></i> Volver
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


	<!-- Scripts -->
	<script src="./js/jquery-3.1.1.min.js"></script>
	<script src="./js/bootstrap.min.js"></script>
	<script src="./js/material.min.js"></script>
	<script src="./js/ripples.min.js"></script>
	<script src="./js/jquery.mCustomScrollbar.concat.min.js"></script>
	<script src="./js/main.js"></script>
	<script>
		$.material.init();
	</script>
