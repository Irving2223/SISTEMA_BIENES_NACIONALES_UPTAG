<?php
session_start();

// Establecer zona horaria de Venezuela
date_default_timezone_set('America/Caracas');

// 🔐 Protección de sesión
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['loggeado'] !== true) {
    header('Location: Loggin.php?error=Debe+iniciar+sesi%C3%B3n+para+acceder');
    exit;
}

// ⏱️ Verificar inactividad (10 minutos)
$inactividad_maxima = 600;

if (isset($_SESSION['usuario']['ultimo_acceso']) && (time() - $_SESSION['usuario']['ultimo_acceso']) > $inactividad_maxima) {
    session_destroy();
    header('Location: Loggin.php?inactividad=1');
    exit;
}

// Actualizar actividad para esta sesión
$_SESSION['usuario']['ultimo_acceso'] = time();

// --- Obtener la penúltima fecha de inicio de sesión real desde la BD (auditoria) ---
include("conexion.php");

$cedula_usuario = $_SESSION['usuario']['cedula'];
$ultimaSesion = "Primera vez o registro no disponible";

try {
    // Buscar la penúltima entrada en auditoria para esta cédula con acción 'Inicio de sesión'
    $sql_penultima_sesion = "
        SELECT fecha_accion 
        FROM auditoria 
        WHERE usuario_cedula = ? 
        AND accion = 'INSERT'
        AND datos_nuevos LIKE '%Inicio de sesi%C3%B3n%' 
        ORDER BY fecha_accion DESC 
        LIMIT 1 OFFSET 1
    ";

    $stmt_penultima = $conn->prepare($sql_penultima_sesion);
    if ($stmt_penultima) {
        $stmt_penultima->bind_param("s", $cedula_usuario);
        $stmt_penultima->execute();
        $result_penultima = $stmt_penultima->get_result();

        if ($result_penultima && $row_penultima = $result_penultima->fetch_assoc()) {
            $fecha_accion = $row_penultima['fecha_accion'];
            $ultimaSesion = date("d/m/Y H:i:s", strtotime($fecha_accion));
        } else {
            // Si no hay penúltimo registro, buscar el último registro de cualquier tipo para este usuario
            $sql_cualquier_accion = "
                SELECT fecha_accion 
                FROM auditoria 
                WHERE usuario_cedula = ? 
                ORDER BY fecha_accion DESC 
                LIMIT 1 OFFSET 1
            ";
            
            $stmt_cualquier = $conn->prepare($sql_cualquier_accion);
            if ($stmt_cualquier) {
                $stmt_cualquier->bind_param("s", $cedula_usuario);
                $stmt_cualquier->execute();
                $result_cualquier = $stmt_cualquier->get_result();
                
                if ($result_cualquier && $row_cualquier = $result_cualquier->fetch_assoc()) {
                    $fecha_accion = $row_cualquier['fecha_accion'];
                    $ultimaSesion = date("d/m/Y H:i:s", strtotime($fecha_accion)) . " (Primer inicio registrado)";
                } else {
                    $ultimaSesion = "Primera vez";
                }
                
                $stmt_cualquier->close();
            }
        }
        
        $stmt_penultima->close();
    }
} catch (Exception $e) {
    error_log("Error al obtener última sesión: " . $e->getMessage());
    $ultimaSesion = "Error al cargar";
}

// Datos del usuario
$usuario_nombre = $_SESSION['usuario']['nombre'] ?? 'Usuario';
$usuario_apellido = $_SESSION['usuario']['apellido'] ?? '';
$usuario_rol = $_SESSION['usuario']['rol'] ?? 'Usuario';

// Función para ejecutar consulta y manejar errores
function ejecutarConsulta($conn, $sql, $descripcion = "Consulta") {
    try {
        $resultado = $conn->query($sql);
        if (!$resultado) {
            error_log("Error en $descripcion: " . $conn->error);
        }
        return $resultado;
    } catch (Exception $e) {
        error_log("Excepción en $descripcion: " . $e->getMessage());
        return false;
    }
}

// --- 1. Total de Bienes Registrados ---
$sql_bienes = "SELECT COUNT(*) AS total FROM bienes WHERE activo = 1";
$resultado_bienes = ejecutarConsulta($conn, $sql_bienes, "Total de Bienes");
$total_bienes = $resultado_bienes ? $resultado_bienes->fetch_assoc()['total'] : 0;

// --- 2. Total de Desincorporados ---
$sql_ubicaciones = "SELECT COUNT(*) AS total FROM bienes WHERE estatus_id = 4 AND activo = 1";
$resultado_ubicaciones = ejecutarConsulta($conn, $sql_ubicaciones, "Total de Desincorporados");
$total_ubicaciones = $resultado_ubicaciones ? $resultado_ubicaciones->fetch_assoc()['total'] : 0;

// --- 3. Total de Dependencias ---
$sql_dependencias = "SELECT COUNT(*) AS total FROM dependencias WHERE activo = 1";
$resultado_dependencias = ejecutarConsulta($conn, $sql_dependencias, "Total de Dependencias");
$total_dependencias = $resultado_dependencias ? $resultado_dependencias->fetch_assoc()['total'] : 0;

// --- 4. Total de Movimientos ---
$sql_movimientos = "SELECT COUNT(*) AS total FROM movimientos";
$resultado_movimientos = ejecutarConsulta($conn, $sql_movimientos, "Total de Movimientos");
$total_movimientos = $resultado_movimientos ? $resultado_movimientos->fetch_assoc()['total'] : 0;

// --- 5. Bienes registrados HOY ---
$hoy = date('Y-m-d');
$sql_hoy = "SELECT COUNT(*) AS total FROM bienes WHERE DATE(fecha_incorporacion) = ?";
$stmt_hoy = $conn->prepare($sql_hoy);
if ($stmt_hoy) {
    $stmt_hoy->bind_param("s", $hoy);
    $stmt_hoy->execute();
    $resultado_hoy = $stmt_hoy->get_result();
    $total_hoy = $resultado_hoy ? $resultado_hoy->fetch_assoc()['total'] : 0;
    $stmt_hoy->close();
} else {
    $total_hoy = 0;
}

// --- 6. Últimos 4 bienes ingresados ---
$sql_ultimos_bienes = "
    SELECT 
        b.codigo_bien_nacional,
        b.descripcion,
        b.marca,
        b.modelo,
        b.serial,
        b.fecha_incorporacion,
        e.nombre AS estatus_nombre,
        u.nombre AS ubicacion_nombre,
        c.nombre AS categoria_nombre
    FROM bienes b
    LEFT JOIN estatus e ON b.estatus_id = e.id
    LEFT JOIN ubicaciones u ON b.id IS NULL
    LEFT JOIN categorias c ON b.categoria_id = c.id
    WHERE b.activo = 1
    ORDER BY b.id DESC
    LIMIT 4
";
$resultado_ultimos_bienes = ejecutarConsulta($conn, $sql_ultimos_bienes, "Últimos Bienes");

// --- 7. Conteo de bienes por estatus ---
$sql_estatus = "
    SELECT 
        e.nombre AS estatus, 
        COUNT(b.id) AS cantidad 
    FROM estatus e
    LEFT JOIN bienes b ON b.estatus_id = e.id AND b.activo = 1
    WHERE e.activo = 1
    GROUP BY e.id, e.nombre
    ORDER BY e.id
";
$resultado_estatus = ejecutarConsulta($conn, $sql_estatus, "Conteo por Estatus");

// --- 8. Últimos 4 movimientos ---
$sql_ultimos_movimientos = "
    SELECT 
        m.id,
        m.fecha_movimiento,
        m.tipo_movimiento,
        m.observaciones,
        b.descripcion AS bien_descripcion,
        b.codigo_bien_nacional,
        u_origen.nombre AS ubicacion_origen,
        u_destino.nombre AS ubicacion_destino
    FROM movimientos m
    LEFT JOIN bienes b ON m.bien_id = b.id
    LEFT JOIN ubicaciones u_origen ON m.ubicacion_origen_id = u_origen.id
    LEFT JOIN ubicaciones u_destino ON m.ubicacion_destino_id = u_destino.id
    ORDER BY m.id DESC
    LIMIT 4
";
$resultado_movimientos_recientes = ejecutarConsulta($conn, $sql_ultimos_movimientos, "Últimos Movimientos");

// --- 9. Bienes por categoría (top 5) ---
$sql_categorias = "
    SELECT 
        c.nombre AS categoria, 
        COUNT(b.id) AS cantidad 
    FROM categorias c
    LEFT JOIN bienes b ON b.categoria_id = c.id AND b.activo = 1
    WHERE c.activo = 1
    GROUP BY c.id, c.nombre
    ORDER BY cantidad DESC
    LIMIT 5
";
$resultado_categorias = ejecutarConsulta($conn, $sql_categorias, "Bienes por Categoría");

// --- 10. Total de responsables ---
$sql_responsables = "SELECT COUNT(*) AS total FROM responsables WHERE activo = 1";
$resultado_responsables = ejecutarConsulta($conn, $sql_responsables, "Total de Responsables");
$total_responsables = $resultado_responsables ? $resultado_responsables->fetch_assoc()['total'] : 0;

// Determinar si el usuario es administrador
$es_administrador = ($_SESSION['usuario']['rol'] === 'Administrador');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Sistema de Bienes Nacionales UPTAG</title>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
	<link rel="stylesheet" href="./css/main.css">
    <link href="assets/img/LOGO INTI.png" rel="icon" type="image/x-icon">
    
	<style>
        html, body {
            font-family: montserrat;
            font-weight:500;

		}


        /* AQUIIII: Cambia el color del sidebar editando el background de .custom-sidebar */
        .custom-sidebar a, .custom-sidebar .dashboard-sideBar-title, .custom-sidebar .dashboard-sideBar-UserInfo, .custom-sidebar .dashboard-sideBar-Menu {
            font-family: Montserrat !important;
            font-weight: 500 !important;
            color: #fff !important;
        }

		    /* Fuente Montserrat - Cargado desde fonts/ */
            
            
@font-face {
    font-family: 'Montserrat';
    src: url('fonts/static/Montserrat-black.ttf') format('truetype');
    font-weight: 900;
    font-style: normal;
}

@font-face {
    font-family: 'Montserrat';
    src: url('fonts/static/Montserrat-ExtraBold.ttf') format('truetype');
    font-weight: 800;
    font-style: normal;
}


@font-face {
    font-family: 'Montserrat';
    src: url('fonts/static/Montserrat-Bold.ttf') format('truetype');
    font-weight: 700;
    font-style: normal;
}

@font-face {
    font-family: 'Montserrat';
    src: url('fonts/static/Montserrat-SemiBold.ttf') format('truetype');
    font-weight: 600;
    font-style: normal;
}

@font-face {
    font-family: 'Montserrat';
    src: url('fonts/static/Montserrat-Medium.ttf') format('truetype');
    font-weight: 500;
    font-style: normal;
}

@font-face {
    font-family: 'Montserrat';
    src: url('fonts/static/Montserrat-Regular.ttf') format('truetype');
    font-weight: 400;
    font-style: normal;
}

@font-face {
    font-family: 'Montserrat';
    src: url('fonts/static/Montserrat-Light.ttf') format('truetype');
    font-weight: 300;
    font-style: normal;
}

/* Media queries para móviles */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.85rem;
    }
    .table-responsive td {
        white-space: normal;
    }
}

	</style>
	
</head>
<body>
	<!-- SideBar -->
	<section class="full-box cover dashboard-sideBar custom-sidebar" style="background-image:url(./assets/img/sidebar/sidebar.webp);">
		<div class="full-box dashboard-sideBar-bg btn-menu-dashboard"></div>
		<div class="full-box dashboard-sideBar-ct">

			<!--SideBar Title -->
			<div class="full-box text-uppercase text-center text-titles dashboard-sideBar-title">
			</div>

			<!-- SideBar User info -->
			<div class="full-box dashboard-sideBar-">
				<figure class="full-box">
					<img src="assets/img/LOGO INTI.png" alt="" style="pointer-events: none; width:175px; left:35px; bottom:30px; position:relative;"> 
				</figure>
			</div>

			<!-- SideBar Menu -->
			<ul class="list-unstyled full-box dashboard-sideBar-Menu" style="font-family:montserrat;">
				<li><a href="home.php"><i class="zmdi zmdi-view-dashboard zmdi-hc-fw"></i> Inicio</a></li>
				<li><a href="buscar.php"><i class="zmdi zmdi-search"></i> Búsqueda de Bienes</a></li>
				<li>
					<a href="#!" class="btn-sideBar-SubMenu">
						<i class="zmdi zmdi-file-text"></i> Gestión de Bienes <i class="zmdi zmdi-caret-down pull-right"></i>
					</a>
					<ul class="list-unstyled full-box">
						<li><a href="registrar_bien.php"><i class="zmdi zmdi-plus-circle"></i> Registrar Bien</a></li>
						<li><a href="registrar_ubicacion.php"><i class="zmdi zmdi-pin"></i> Registrar Ubicación</a></li>
						<li><a href="registrar_movimiento.php"><i class="zmdi zmdi-swap"></i> Registrar Movimiento</a></li>
						<li><a href="desincorporar_bien.php"><i class="zmdi zmdi-delete"></i> Desincorporar Bien</a></li>
					</ul>
				</li>
				<li>
					<a href="#!" class="btn-sideBar-SubMenu">
						<i class="zmdi zmdi-border-color"></i> Reportes <i class="zmdi zmdi-caret-down pull-right"></i>
					</a>
					<ul class="list-unstyled full-box">
						<li><a href="generar_reporte_inventario.php"><i class="zmdi zmdi-assignment"></i> Inventario General</a></li>
						<li><a href="generar_reporte_movimientos.php"><i class="zmdi zmdi-swap"></i> Reporte de Movimientos</a></li>
                        <li><a href="generar_reporte_ubicaciones.php"><i class="zmdi zmdi-pin"></i> Reporte por Ubicación</a></li>
					</ul>
				</li>
				<?php if ($es_administrador): ?>
					<li><a href="configuracion.php"><i class="zmdi zmdi-settings"></i> Configuración</a></li>
				<?php endif; ?>
				<li><a href="salir.php"><i class="zmdi zmdi-power zmdi-hc-fw"></i>Cerrar sesión</a></li>
				<?php if (!$es_administrador): ?>
					<li><a href="#" style="opacity:0;"><i class="zmdi zmdi-settings"></i></a></li>
				<?php endif; ?>
			</ul>

			<ul class="list-unstyled full-box dashboard-sideBar-Menu" style="top:50px; position:relative;">
			</ul>
			
		</div>
	</section>

	<!-- Contenido principal -->
	<section class="full-box dashboard-contentPage" style="background-color:#f5f5f5;">
		
		<!-- NavBar -->
		<nav class="full-box dashboard-Navbar" style="background: linear-gradient(90deg,rgba(219, 155, 35, 1) 0%, rgba(255, 145, 0, 1) 50%, rgba(252, 203, 69, 1) 100%);">
			<ul class="full-box list-unstyled text-right">
				<li class="pull-left">
					<a href="#!" style="height:60px;" class="btn-menu-dashboard"><i class="zmdi zmdi-more-horiz"></i></a>
				</li>
				<li class="pull-right">
					<a href="Perfil.php" style="height:60px;"><i class="zmdi zmdi-account-circle"></i></a>
				</li>
			</ul>
		</nav>
		
		<!-- Contenido -->
		<div class="container-fluid">
			<div class="page-header">
				<p style="font-family:montserrat; font-weight:600; color:gray;">Última vez que inició sesión: <span style="font-weight:700; color:rgb(121, 75, 0);"><?php echo $ultimaSesion; ?></span></p>
				<h1 style="font-weight:900; font-family:montserrat; color:#ff8a00;">Oficina de Bienes Nacionales, <span style="font-weight:700; color:black;"> <?php echo htmlspecialchars($usuario_nombre . ' ' . $usuario_apellido); ?> </span></h1>
			</div>

			<div class="row">
				<div class="col-md-3">
					<div class="panel panel-default" style="border-radius:15px;">
						<div class="panel-body" style="border-top:5px solid rgb(121, 75, 0); border-radius:15px;">
							<h3 style="font-family:montserrat; font-weight:600; text-align:center; font-size:20px;"><i class="zmdi zmdi-border-all"></i> Total Bienes</h3>
							<p style="font-family:montserrat; font-weight:900; text-align:center; font-size:50px; color:#ff8a00;"><?php echo $total_bienes; ?></p>
						</div>
					</div>
				</div>

				<div class="col-md-3">
					<div class="panel panel-default" style="border-radius:15px;">
						<div class="panel-body" style="border-top:5px solid rgb(121, 75, 0);; border-radius:15px;">
							<h3 style="font-family:montserrat; font-weight:600; text-align:center; font-size:20px;"><i class="zmdi zmdi-delete"></i> Desincorporados</h3>
							<p style="font-family:montserrat; font-weight:900; text-align:center; font-size:50px; color:#ff8a00;"><?php echo $total_ubicaciones; ?></p>
						</div>
					</div>
				</div>

				<div class="col-md-3">
					<div class="panel panel-default" style="border-radius:15px;">
						<div class="panel-body" style="border-top:5px solid rgb(121, 75, 0);; border-radius:15px;">
							<h3 style="font-family:montserrat; font-weight:600; text-align:center; font-size:20px;"><i class="zmdi zmdi-account"></i> Responsables</h3>
							<p style="font-family:montserrat; font-weight:900; text-align:center; font-size:50px; color:#ff8a00;"><?php echo $total_responsables; ?></p>
						</div>
					</div>
				</div>

				<div class="col-md-3">
					<div class="panel panel-default" style="border-radius:15px;">
						<div class="panel-body" style="border-top:5px solid rgb(121, 75, 0);; border-radius:15px;">
							<h3 style="font-family:montserrat; font-weight:600; text-align:center; font-size:20px;"><i class="zmdi zmdi-calendar"></i> Bienes de Hoy</h3>
							<p style="font-family:montserrat; font-weight:900; text-align:center; font-size:50px; color:#ff8a00;"><?php echo $total_hoy; ?></p>
						</div>
					</div>
				</div>
			</div>

			<br>

			<!-- Tabla: Últimos Bienes Ingresados -->
			<div class="col-md-12">
				<div class="panel panel-default" style="border-radius:15px;">
					<div class="panel-body" style="border-top:5px solid rgb(121, 75, 0);; border-radius:15px;">
						<span style="font-weight:800; color:#ff8a00;"><i class="zmdi zmdi-view-list"></i> Últimos Bienes Registrados</span>
						<div class="table-responsive">
							<table class="table table-striped mb-0">
								<thead>
									<tr>
										<th>Código</th>
										<th>Descripción</th>
										<th>Marca/Modelo</th>
										<th>Serial</th>
										<th>Estatus</th>
										<th>Fecha Incorporación</th>
									</tr>
								</thead>
								<tbody style="font-weight:500;">
									<?php if ($resultado_ultimos_bienes && $resultado_ultimos_bienes->num_rows > 0): ?>
										<?php while ($fila = $resultado_ultimos_bienes->fetch_assoc()): ?>
											<?php
											$codigo = !empty($fila['codigo_bien_nacional']) ? htmlspecialchars($fila['codigo_bien_nacional']) : 'N/A';
											$descripcion = !empty($fila['descripcion']) ? htmlspecialchars($fila['descripcion']) : 'N/A';
											$marca_modelo = trim(!empty($fila['marca']) ? htmlspecialchars($fila['marca']) . ' ' . htmlspecialchars($fila['modelo']) : htmlspecialchars($fila['modelo'] ?? ''));
											$serial = !empty($fila['serial']) ? htmlspecialchars($fila['serial']) : 'N/A';
											$estatus = !empty($fila['estatus_nombre']) ? htmlspecialchars($fila['estatus_nombre']) : 'Sin estatus';
											$fecha = !empty($fila['fecha_incorporacion']) ? date("d/m/Y", strtotime($fila['fecha_incorporacion'])) : 'N/A';
											?>
											<tr>
												<td><strong><?php echo $codigo; ?></strong></td>
												<td><?php echo $descripcion; ?></td>
												<td><?php echo $marca_modelo; ?></td>
												<td><?php echo $serial; ?></td>
												<td><span class="label label-<?php echo strtolower(str_replace(' ', '-', $estatus)); ?>"><?php echo $estatus; ?></span></td>
												<td><?php echo $fecha; ?></td>
											</tr>
										<?php endwhile; ?>
									<?php else: ?>
										<tr><td colspan="6" class="text-center">No hay bienes registrados aún.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<!-- Sección: Tablas combinadas -->
			<div class="row" style="margin-top:20px;">
				
				<!-- Tabla: Bienes por Estatus -->
				<div class="col-md-4">
					<div class="panel panel-default" style="border-radius:15px;">
						<div class="panel-body" style="border-top:5px solid rgb(121, 75, 0); border-radius:15px;">
							<span style="font-weight:800; color:#ff8a00;"><i class="zmdi zmdi-chart"></i> Bienes por Estatus</span>
							<div class="table-responsive" style="max-height: 200px; ">
								<table class="table mb-0">
									<thead>
										<tr>
											<th>Estatus</th>
											<th>Cantidad</th>
										</tr>
									</thead>
									<tbody>
										<?php if ($resultado_estatus && $resultado_estatus->num_rows > 0): ?>
											<?php while ($fila = $resultado_estatus->fetch_assoc()): ?>
												<tr>
													<td style="font-weight:500;"><?= htmlspecialchars($fila['estatus']); ?></td>
													<td style="color:#ff8a00; font-weight:900;"><?= $fila['cantidad']; ?></td>
												</tr>
											<?php endwhile; ?>
										<?php else: ?>
											<tr><td colspan="2" class="text-center">No hay datos</td></tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>

				<!-- Tabla: Bienes por Categoría -->
				<div class="col-md-4">
					<div class="panel panel-default" style="border-radius:15px;">
						<div class="panel-body" style="border-top:5px solid rgb(121, 75, 0); border-radius:15px;">
							<span style="font-weight:800; color:#ff8a00;"><i class="zmdi zmdi-tag"></i> Top Categorías</span>
							<div class="table-responsive" style="max-height: 200px; ">
								<table class="table mb-0">
									<thead>
										<tr>
											<th>Categoría</th>
											<th>Cantidad</th>
										</tr>
									</thead>
									<tbody>
										<?php if ($resultado_categorias && $resultado_categorias->num_rows > 0): ?>
											<?php while ($fila = $resultado_categorias->fetch_assoc()): ?>
												<tr>
													<td style="font-weight:500;"><?= htmlspecialchars($fila['categoria']); ?></td>
													<td style="color:#ff8a00; font-weight:900;"><?= $fila['cantidad']; ?></td>
												</tr>
											<?php endwhile; ?>
										<?php else: ?>
											<tr><td colspan="2" class="text-center">No hay datos</td></tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>

				<!-- Tabla: Últimos Movimientos -->
				<div class="col-md-4">
					<div class="panel panel-default" style="border-radius:15px;">
						<div class="panel-body" style="border-top:5px solid rgb(121, 75, 0); border-radius:15px;">
							<span style="font-weight:800; color:#ff8a00;"><i class="zmdi zmdi-swap"></i> Últimos Movimientos</span>
							<div class="table-responsive" style="max-height: 200px; ">
								<table class="table mb-0">
									<thead>
										<tr>
											<th>Tipo</th>
											<th>Bien</th>
											<th>Fecha</th>
										</tr>
									</thead>
									<tbody>
										<?php if ($resultado_movimientos_recientes && $resultado_movimientos_recientes->num_rows > 0): ?>
											<?php while ($fila = $resultado_movimientos_recientes->fetch_assoc()): ?>
												<tr>
													<td style="font-weight:500;"><?= htmlspecialchars($fila['tipo_movimiento']); ?></td>
													<td style="font-weight:500; font-size:0.9em;"><?= htmlspecialchars(substr($fila['bien_descripcion'] ?? $fila['codigo_bien_nacional'], 0, 15) . (strlen($fila['bien_descripcion'] ?? $fila['codigo_bien_nacional']) > 15 ? '...' : '')); ?></td>
													<td style="color:#ff8a00; font-weight:500;"><?= !empty($fila['fecha_movimiento']) ? date("d/m/Y", strtotime($fila['fecha_movimiento'])) : 'N/A'; ?></td>
												</tr>
											<?php endwhile; ?>
										<?php else: ?>
											<tr><td colspan="3" class="text-center">No hay movimientos</td></tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>

			</div>

			<br><br>

		</div>

	</section>

	<?php
	
	include("footer.php");
	
	?>

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

</body>
</html>
