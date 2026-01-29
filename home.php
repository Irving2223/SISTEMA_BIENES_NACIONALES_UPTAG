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

// --- Obtener la penúltima fecha de inicio de sesión real desde la BD (bitacora) ---
include("conexion.php");

$cedula_usuario = $_SESSION['usuario']['cedula'];
$ultimaSesion = "Primera vez o registro no disponible";

try {
    // Buscar la penúltima entrada en bitácora para esta cédula con acción 'Inicio de sesión'
    $sql_penultima_sesion = "
        SELECT fecha_accion 
        FROM bitacora 
        WHERE cedula_usuario = ? 
        AND (
            accion IN ('Registro', 'Consulta') OR accion = ''
        )
        AND (
            detalle LIKE '%Inicio de sesión%' OR 
            detalle LIKE '%exitoso%'
        )
        ORDER BY fecha_accion DESC 
        LIMIT 1 OFFSET 1
    ";

    $stmt_penultima = $conn->prepare($sql_penultima_sesion);
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
            FROM bitacora 
            WHERE cedula_usuario = ? 
            ORDER BY fecha_accion DESC 
            LIMIT 1 OFFSET 1
        ";
        
        $stmt_cualquier = $conn->prepare($sql_cualquier_accion);
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
    
    $stmt_penultima->close();
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
    $resultado = $conn->query($sql);
    if (!$resultado) {
        error_log("Error en $descripcion: " . $conn->error);
    }
    return $resultado;
}

// --- 1. Total de Solicitantes (Natural + Jurídica + Colectivo) ---
$sql_solicitantes = "
    SELECT 
        (SELECT COUNT(*) FROM Personas_Naturales) +
        (SELECT COUNT(*) FROM Personas_Juridicas) +
        (SELECT COUNT(*) FROM Colectivos)
    AS total
";
$resultado_solicitantes = ejecutarConsulta($conn, $sql_solicitantes, "Total de Solicitantes");
$total_solicitantes = $resultado_solicitantes ? $resultado_solicitantes->fetch_assoc()['total'] : 0;

// --- 2. Total de Solicitudes ---
$sql_solicitudes = "SELECT COUNT(*) AS total FROM Solicitudes";
$resultado_solicitudes = ejecutarConsulta($conn, $sql_solicitudes, "Total de Solicitudes");
$total_solicitudes = $resultado_solicitudes ? $resultado_solicitudes->fetch_assoc()['total'] : 0;

// --- 3. Total de Predios ---
$sql_predios = "SELECT COUNT(*) AS total FROM Predios";
$resultado_predios = ejecutarConsulta($conn, $sql_predios, "Total de Predios");
$total_predios = $resultado_predios ? $resultado_predios->fetch_assoc()['total'] : 0;

// --- 4. Solicitudes registradas HOY ---
$hoy = date('Y-m-d');
$sql_hoy = "SELECT COUNT(*) AS total FROM Solicitudes WHERE DATE(creado_en) = ?";
$stmt_hoy = $conn->prepare($sql_hoy);
$stmt_hoy->bind_param("s", $hoy);
$stmt_hoy->execute();
$resultado_hoy = $stmt_hoy->get_result();
$total_hoy = $resultado_hoy ? $resultado_hoy->fetch_assoc()['total'] : 0;
$stmt_hoy->close();

// --- 5. Últimas 4 solicitudes ingresadas (con datos del solicitante y predio) ---
$sql_ultimos = "
    SELECT 
        s.fecha_solicitud,
        s.tipo_solicitante,
        s.creado_por,
        tp.nombre_procedimiento,
        u.nombre AS usuario_nombre,
        -- Datos del solicitante según tipo
        CASE 
            WHEN s.tipo_solicitante = 'N' THEN CONCAT(pn.primer_nombre, ' ', pn.primer_apellido)
            WHEN s.tipo_solicitante = 'J' THEN pj.razon_social
            WHEN s.tipo_solicitante = 'C' THEN c.nombre_colectivo
        END AS nombre_solicitante,
        CASE 
            WHEN s.tipo_solicitante = 'N' THEN s.cedula_solicitante_n
            WHEN s.tipo_solicitante = 'J' THEN s.rif_solicitante_j
            WHEN s.tipo_solicitante = 'C' THEN s.rif_ci_solicitante_c
        END AS identificador,
        s.estatus
    FROM Solicitudes s
    LEFT JOIN Personas_Naturales pn ON s.cedula_solicitante_n = pn.cedula
    LEFT JOIN Personas_Juridicas pj ON s.rif_solicitante_j = pj.rif
    LEFT JOIN Colectivos c ON s.rif_ci_solicitante_c = c.rif_o_ci_referente
    LEFT JOIN Tipo_Procedimiento tp ON s.id_procedimiento = tp.id_procedimiento
    LEFT JOIN Usuarios u ON s.creado_por = u.cedula
    ORDER BY s.creado_en DESC
    LIMIT 4
";
$resultado_ultimos = ejecutarConsulta($conn, $sql_ultimos, "Últimas Solicitudes");

// --- 6. Conteo de solicitudes por estatus ---
$sql_estatus = "
    SELECT 
        estatus, 
        COUNT(*) AS cantidad 
    FROM Solicitudes 
    GROUP BY estatus 
    ORDER BY FIELD(estatus, 'Por_Inspeccion', 'En_Ejecucion', 'En_INTI_Central', 'Aprobado')
";
$resultado_estatus = ejecutarConsulta($conn, $sql_estatus, "Conteo por Estatus");

// --- 7. Últimos 4 solicitantes registrados (de cualquier tipo) ---
$sql_ultimos_solicitantes = "
    (
        SELECT 
            pn.cedula as identificador,
            CONCAT(pn.primer_nombre, ' ', pn.primer_apellido) AS nombre_completo,
            'Natural' as tipo,
            pn.creado_en
        FROM Personas_Naturales pn
    )
    UNION ALL
    (
        SELECT 
            pj.rif as identificador,
            pj.razon_social AS nombre_completo,
            'Jurídica' as tipo,
            pj.creado_en
        FROM Personas_Juridicas pj
    )
    UNION ALL
    (
        SELECT 
            c.rif_o_ci_referente as identificador,
            c.nombre_colectivo AS nombre_completo,
            'Colectivo' as tipo,
            c.creado_en
        FROM Colectivos c
    )
    ORDER BY creado_en DESC
    LIMIT 4
";
$resultado_solicitantes = ejecutarConsulta($conn, $sql_ultimos_solicitantes, "Últimos Solicitantes");

// Determinar si el usuario es administrador
$es_administrador = ($_SESSION['usuario']['rol'] === 'Administrador');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Sistema INTI</title>
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
				<li><a href="buscar.php"><i class="zmdi zmdi-search"></i> Búsqueda</a></li>
				<li>
					<a href="#!" class="btn-sideBar-SubMenu">
						<i class="zmdi zmdi-file-text"></i> Formulario <i class="zmdi zmdi-caret-down pull-right"></i>
					</a>
					<ul class="list-unstyled full-box">
						<li><a href="registrar_solicitante.php"><i class="zmdi zmdi-account-add"></i> Registrar Solicitante</a></li>
						<li><a href="registrar_solicitud.php"><i class="zmdi zmdi-folder-person"></i> Registrar Solicitud</a></li>
						<li><a href="editar_solicitante.php"><i class="zmdi zmdi-edit"></i> Editar Solicitante</a></li>
						<li><a href="editar_solicitud.php"><i class="zmdi zmdi-comment-edit"></i> Editar Solicitud</a></li>
						<li><a href="registrar_sector.php"><i class="zmdi zmdi-pin"></i> Gestión de Sectores</a></li>
					</ul>
				</li>
				<li>
					<a href="#!" class="btn-sideBar-SubMenu">
						<i class="zmdi zmdi-border-color"></i> Reportes <i class="zmdi zmdi-caret-down pull-right"></i>
					</a>
					<ul class="list-unstyled full-box">
						<li><a href="reporte_solicitudes.php"><i class="zmdi zmdi-file-plus"></i> Reporte de Solicitudes</a></li>
						<li><a href="reporte_superficie.php"><i class="zmdi zmdi-assignment-o"></i> Reporte de Superficie</a></li>
                        <li><a href="reporte_solicitantes.php"><i class="zmdi zmdi-accounts-list"></i> Reporte de Solicitantes</a></li>
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
		<nav class="full-box dashboard-Navbar" style="background-image:url(./assets/img/sidebar/sidebar.webp); height:60px;">
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
				<p style="font-family:montserrat; font-weight:600; color:gray;">Última vez que inició sesión: <span style="font-weight:700; color:green;"><?php echo $ultimaSesion; ?></span></p>
				<h1 style="font-weight:900; font-family:montserrat; color:green;">Bienvenido al Sistema, <span style="font-weight:700; color:black;"> <?php echo htmlspecialchars($usuario_nombre . ' ' . $usuario_apellido); ?> </span></h1>
			</div>

			<div class="row">
				<div class="col-md-3">
					<div class="panel panel-default" style="border-radius:15px;">
						<div class="panel-body" style="border-top:5px solid green; border-radius:15px;">
							<h3 style="font-family:montserrat; font-weight:600; text-align:center; font-size:20px;"><i class="zmdi zmdi-account-add zmdi-hc-fw"></i> Solicitantes</h3>
							<p style="font-family:montserrat; font-weight:900; text-align:center; font-size:50px; color:green;"><?php echo $total_solicitantes; ?></p>
						</div>
					</div>
				</div>

				<div class="col-md-3">
					<div class="panel panel-default" style="border-radius:15px;">
						<div class="panel-body" style="border-top:5px solid green; border-radius:15px;">
							<h3 style="font-family:montserrat; font-weight:600; text-align:center; font-size:20px;"><i class="zmdi zmdi-file-text"></i> Solicitudes</h3>
							<p style="font-family:montserrat; font-weight:900; text-align:center; font-size:50px; color:green;"><?php echo $total_solicitudes; ?></p>
						</div>
					</div>
				</div>

				<div class="col-md-3">
					<div class="panel panel-default" style="border-radius:15px;">
						<div class="panel-body" style="border-top:5px solid green; border-radius:15px;">
							<h3 style="font-family:montserrat; font-weight:600; text-align:center; font-size:20px;"><i class="zmdi zmdi-account-add zmdi-hc-fw"></i> Predios</h3>
							<p style="font-family:montserrat; font-weight:900; text-align:center; font-size:50px; color:green;"><?php echo $total_predios; ?></p>
						</div>
					</div>
				</div>

				<div class="col-md-3">
					<div class="panel panel-default" style="border-radius:15px;">
						<div class="panel-body" style="border-top:5px solid green; border-radius:15px;">
							<h3 style="font-family:montserrat; font-weight:600; text-align:center; font-size:20px;"><i class="zmdi zmdi-calendar"></i> Solicitudes de Hoy</h3>
							<p style="font-family:montserrat; font-weight:900; text-align:center; font-size:50px; color:green;"><?php echo $total_hoy; ?></p>
						</div>
					</div>
				</div>
			</div>

			<br>

			<!-- Tabla: Últimas Solicitudes -->
			<div class="col-md-12">
				<div class="panel panel-default" style="border-radius:15px;">
					<div class="panel-body" style="border-top:5px solid green; border-radius:15px;">
						<span style="font-weight:800; color:green;"><i class="zmdi zmdi-accounts-add"></i> Últimas Solicitudes</span>
						<div class="table-responsive">
							<table class="table table-striped mb-0">
								<thead>
									<tr>
										<th>Nombre</th>
										<th>Cédula/RIF</th>
										<th>Procedimiento</th>
										<th>Tipo</th>
										<th>Estatus</th>
										<th>Agregado por</th>
										<th>Fecha</th>
									</tr>
								</thead>
								<tbody style="font-weight:500;">
									<?php if ($resultado_ultimos && $resultado_ultimos->num_rows > 0): ?>
										<?php while ($fila = $resultado_ultimos->fetch_assoc()): ?>
											<?php
											$nombre = !empty($fila['nombre_solicitante']) ? htmlspecialchars(ucwords(strtolower($fila['nombre_solicitante']))) : 'N/A';
											$identificador = !empty($fila['identificador']) ? htmlspecialchars($fila['identificador']) : 'N/A';
											$procedimiento = !empty($fila['nombre_procedimiento']) ? htmlspecialchars($fila['nombre_procedimiento']) : 'N/A';
											switch($fila['tipo_solicitante']) {
												case 'N': $tipo = 'Natural'; break;
												case 'J': $tipo = 'Jurídica'; break;
												case 'C': $tipo = 'Colectivo'; break;
												default: $tipo = 'Desconocido';
											}
											$agregado_por = !empty($fila['usuario_nombre']) ? htmlspecialchars($fila['usuario_nombre']) : 'N/A';
											$fecha = !empty($fila['fecha_solicitud']) ? date("d/m/Y", strtotime($fila['fecha_solicitud'])) : 'N/A';
											$estatus = str_replace('_', ' ', htmlspecialchars($fila['estatus']));
											?>
											<tr>
												<td><?php echo $nombre; ?></td>
												<td><?php echo $identificador; ?></td>
												<td><?php echo $procedimiento; ?></td>
												<td><?php echo $tipo; ?></td>
												<td><?php echo $estatus; ?></td>
												<td><?php echo $agregado_por; ?></td>
												<td><?php echo $fecha; ?></td>
											</tr>
										<?php endwhile; ?>
									<?php else: ?>
										<tr><td colspan="7" class="text-center">No hay registros recientes.</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<!-- Sección: Tablas combinadas -->
			<div class="row" style="margin-top:20px;">
				
				<!-- Tabla: Solicitudes por Estatus -->
				<div class="col-md-4">
					<div class="panel panel-default" style="border-radius:15px;">
						<div class="panel-body" style="border-top:5px solid green; border-radius:15px;">
							<span style="font-weight:800; color:green;"><i class="zmdi zmdi-chart"></i> Solicitudes por Estatus</span>
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
													<td style="font-weight:500;"><?= str_replace('_', ' ', htmlspecialchars($fila['estatus'])); ?></td>
													<td style="color:green; font-weight:900;"><?= $fila['cantidad']; ?></td>
												</tr>
											<?php endwhile; ?>
										<?php else: ?>
											<tr><td colspan="2" class="text-center">Sin datos disponibles</td></tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>

				<!-- Tabla: Últimos Solicitantes Registrados -->
				<div class="col-md-8">
					<div class="panel panel-default" style="border-radius:15px;">
						<div class="panel-body" style="border-top:5px solid green; border-radius:15px;">
							<span style="font-weight:800; color:green;"><i class="zmdi zmdi-accounts-list"></i> Últimos Solicitantes</span>
							<div class="table-responsive" style="max-height: 200px;">
								<table class="table mb-0">
									<thead>
										<tr>
											<th style="width: 40%;">Nombre</th>
											<th style="width: 25%;">Identificador</th>
											<th style="width: 15%;">Tipo</th>
											<th style="width: 20%;">Fecha Registro</th>
										</tr>
									</thead>
									<tbody style="font-weight:500;">
										<?php if ($resultado_solicitantes && $resultado_solicitantes->num_rows > 0): ?>
											<?php while ($fila = $resultado_solicitantes->fetch_assoc()): ?>
												<tr>
													<td style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($fila['nombre_completo']) ?>">
														<?= htmlspecialchars($fila['nombre_completo']) ?>
													</td>
													<td><?= htmlspecialchars($fila['identificador']) ?></td>
													<td><?= htmlspecialchars($fila['tipo']) ?></td>
													<td><?= date("d/m/Y", strtotime($fila['creado_en'])) ?></td>
												</tr>
											<?php endwhile; ?>
										<?php else: ?>
											<tr><td colspan="4" class="text-center">Sin registros</td></tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<?php include("footer.php"); ?>

		<!-- Scripts -->
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
	</section>
</body>
</html>