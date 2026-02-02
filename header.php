<?php

session_start();

// Establecer zona horaria de Venezuela
date_default_timezone_set('America/Caracas');

// 🔐 Protección de sesión
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['loggeado'] !== true) {
    header('Location: Loggin.php?error=Debe+iniciar+sesi%C3%B1n+para+acceder');
    exit;
}

// Calcular tiempo restante en segundos para la sesión
$inactividad_maxima = 600; // 10 minutos

if (isset($_SESSION['usuario']['ultimo_acceso']) && (time() - $_SESSION['usuario']['ultimo_acceso']) > $inactividad_maxima) {
    session_destroy();
    header('Location: Loggin.php?error=Sesión+expirada+por+inactividad');
    exit;
}

// Actualizar actividad para esta sesión
$_SESSION['usuario']['ultimo_acceso'] = time();

// --- Obtener la última fecha de inicio de sesión real desde la BD (bitacora) ---
include("conexion.php");

$cedula_usuario = $_SESSION['usuario']['cedula'];

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
		<link rel="stylesheet" href="assets/css/bootstrap.min.css">
		<link rel="stylesheet" href="assets/css/estilos_sistema.css">
		<script src="js/bootstrap.bundle.min.js"></script>
		<link href="assets/img/LOGO INTI.png" rel="icon">
		
		<style>
			
		/* AQUIIII: Cambia el color del sidebar editando el background de .custom-sidebar */
		
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
	<style>

		
	</style>



<!-- Content page-->
	<section class="full-box dashboard-contentPage" style="background-color:#f5f5f5;" >

		<!-- NavBar -->
		<nav class="full-box dashboard-Navbar" style="background:  linear-gradient(90deg,rgba(219, 155, 35, 1) 0%, rgba(255, 145, 0, 1) 50%, rgba(252, 203, 69, 1) 100%)"> <!-- Barra de navegación principal -->
			<ul class="full-box list-unstyled text-right">
				<li class="pull-left" >
				<a href="#!" style="height:60px;" class="btn-menu-dashboard"><i class="zmdi zmdi-more-horiz"></i></a>
				</li>

				
				<li class="pull-right" >
				<a href="Perfil.php" style="height:60px;"><i class="zmdi zmdi-account-circle"></i></a>
				</li>
			</ul>
		</nav>

		

		

		
<!-- 
		
<script>
    // Configuración basada en tu header.php (inactividad_maxima = 600 segundos)
    const TIEMPO_MAX_INACTIVIDAD_SEG = 600; // 10 minutos
    const MINUTOS_PARA_AVISO = 4;
    const SEGUNDOS_PARA_AVISO = MINUTOS_PARA_AVISO * 60;

    let temporizadorSesion = null;
    let tiempoRestante = TIEMPO_MAX_INACTIVIDAD_SEG;

    function mostrarAlertaExtension() {
        // Calcular minutos y segundos restantes para el mensaje
        const mins = Math.floor(tiempoRestante / 60);
        const segs = tiempoRestante % 60;
        
        Swal.fire({
            title: '¿Desea extender su sesión?',
            html: `Su sesión <strong>expirará en ${mins} minuto${mins !== 1 ? 's' : ''} y ${segs} segundo${segs !== 1 ? 's'}</strong>.<br><br>¿Desea continuar trabajando?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, extender',
            cancelButtonText: 'No, cerrar',
            allowOutsideClick: false,
            allowEscapeKey: false,
            timer: tiempoRestante * 1000, // Auto-cerrar cuando expire
            timerProgressBar: true,
            customClass: {
                popup: 'swal2-custom-popup'
            }
        }).then((result) => {
            if (result.isConfirmed || result.dismiss === Swal.DismissReason.timer) {
                // El usuario hizo clic en "Sí" o se acabó el tiempo del temporizador
                extenderSesion();
            } else {
                // Usuario eligió "No"
                window.location.href = 'Loggin.php?error=Sesión+cerrada+por+usuario';
            }
        });
    }

    function extenderSesion() {
        // Reiniciar contador
        tiempoRestante = TIEMPO_MAX_INACTIVIDAD_SEG;
        
        // Actualizar la variable de sesión en PHP mediante AJAX
        $.ajax({
            url: 'actualizar_sesion.php', // Archivo PHP que actualiza $_SESSION['ultimo_acceso']
            type: 'POST',
            data: {accion: 'actualizar'},
            success: function(response) {
                console.log('Sesión extendida');
            },
            error: function(xhr, status, error) {
                console.error('Error al extender sesión:', error);
                // Si falla el AJAX, redirigir igualmente
                window.location.href = 'Loggin.php?error=Error+al+extender+la+sesión';
            }
        });
    }

    function iniciarTemporizador() {
        clearInterval(temporizadorSesion); // Limpiar cualquier temporizador existente

        temporizadorSesion = setInterval(() => {
            tiempoRestante--;

            // Cuando quedan 4 minutos (o menos), mostrar la alerta
            if (tiempoRestante <= SEGUNDOS_PARA_AVISO) {
                clearInterval(temporizadorSesion); // Detener este temporizador
                mostrarAlertaExtension();
                return;
            }

        }, 1000); // Verificar cada segundo
    }

    function reiniciarTemporizador() {
        tiempoRestante = TIEMPO_MAX_INACTIVIDAD_SEG;
        iniciarTemporizador();
    }

    // Iniciar el temporizador cuando se carga la página
    $(document).ready(function() {
        iniciarTemporizador();

        // Reiniciar el temporizador en eventos de actividad del usuario
        $(document).on('mousemove keypress scroll click', function() {
            reiniciarTemporizador();
        });
    });

    // Manejar cierre de pestaña (opcional, para limpieza)
    $(window).on('beforeunload', function() {
        clearInterval(temporizadorSesion);
    });
</script>
 -->