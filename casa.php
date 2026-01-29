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

// Determinar si el usuario es administrador
$es_administrador = ($_SESSION['usuario']['rol'] === 'Administrador');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <title>Casa - Sistema INTI</title>
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
				<li><a href="casa.php"><i class="zmdi zmdi-view-dashboard zmdi-hc-fw"></i> Casa</a></li>
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
	nnn

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

			document.getElementById('btn-consultar').addEventListener('click', function() {
				document.getElementById('info-paneles').style.display = 'block';
				this.style.display = 'none';
			});
		</script>
	</section>
</body>
</html>