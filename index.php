<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Inicio - Sistema de Información Territorial y Adjudicación de Tierras</title>
  <meta name="description" content="">
  <meta name="keywords" content="">

  <!-- Icono -->
  <link href="assets/img/LOGO INTI.png" rel="icon">
  
  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

  <style>

    //tipografia montserrat
    
    * {
      font-family: 'Montserrat';
    }
  </style>

</head>

<body class="index-page">


  <header id="header" class="header d-flex align-items-center fixed-top" style="background-color: rgba(75, 75, 75, 0.9);">
    <div class="container position-relative d-flex align-items-center justify-content-between">

      <a href="index.php" class="logo d-flex align-items-center me-auto me-xl-0">
        <img src="assets/img/LOGO INTI.png" class="logo-img" alt="Logo INTI" style="height: 50px;">
      </a>

      <nav id="navmenu" class="navmenu" >
        <ul>
          <li><a href="#Inicio" class="active">Inicio</a></li>
          <li><a href="#¿Quienes somos?">¿Quiénes somos?</a></li>
          <li><a href="#mision">Mision</a></li>
          <li><a href="#Vision">Vision</a></li>
          <li><a href="#valores">Valores</a></li>
          </ul>
    
      </nav>

      <a class="btn-getstarted" href="Loggin.php" >Iniciar Sesion</a>

    </div>
  </header>

  <main class="main">

    <!-- Hero Section -->
    <section id="Inicio" class="hero section dark-background" style="background: linear-gradient(90deg,rgba(219, 155, 35, 1) 0%, rgba(255, 145, 0, 1) 50%, rgba(252, 203, 69, 1) 100%);">
      <div class="container" data-aos="fade-up" data-aos-delay="100">
        <div class="row align-items-center">
          <div class="col-lg-6">
            <div class="hero-content" data-aos="fade-up" data-aos-delay="200">
              <h2 style="font-weight:900;">Sistema Oficina de Bienes Nacionales UPTAG</h2>
              <p style="font-weight:400;"> Universidad Politécnica Territorial de Falcón Alonso Gamero (UPTAG)</p>
              <div class="hero-btns">
                <a href="Loggin.php" class="btn btn-primary" style="font-weight:500;">Iniciar Sesion</a>
              </div>
              <div class="hero-stats">

              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="hero-image" data-aos="zoom-out" data-aos-delay="300">
              <img src="assets/img/index/aula magna.webp" alt="" class="img-fluid">
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- /Hero Section -->

    <!-- About Section -->
    <section id="¿Quienes somos?" class="about section">

      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <div class="row align-items-center">
          <div class="col-lg-6" data-aos="zoom-out" data-aos-delay="200">
            <div class="about-image">
              <img src="assets/img/index/foto gente.jpg" alt="About Our Consulting Firm" class="img-fluid main-image">
              <div class="experience-badge">
                <span class="years">55+</span>
                <span class="text">Años de Experiencia</span>
              </div>
            </div>
          </div>

          <div class="col-lg-6" data-aos="fade-up" data-aos-delay="300">
            <div class="about-content">
              <h2>¿Quiénes somos en la oficina de Bienes Nacionales UPTAG?</h2>
              
              <div class="row features-row">
                <div class="col-md-6">
                  <div class="feature-item">
                    <div class="icon">
                      <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h4>Registradores de Activos Universitarios</h4>
                    <p>Somos los encargados de oficializar el ingreso de cada bien a la institución</p>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="feature-item">
                    <div class="icon">
                      <i class="bi bi-lightbulb"></i>
                    </div>
                    <h4>Gestores de Movilidad y Ubicación</h4>
                    <p>Actuamos como los supervisores del flujo interno de mobiliario y equipos entre departamentos, oficinas y PNF.</p>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="feature-item">
                    <div class="icon">
                      <i class="bi bi-people"></i>
                    </div>
                    <h4>Auditores de Vida Útil y Salidas</h4>
                    <p>Realizamos la desincorporación formal en el sistema, dejando una "leyenda" o registro detallado del motivo y la fecha de salida del inventario.</p>
                  </div>
                </div>



      </div>

    </section>
    
    <!-- /About Section -->



 
</div>

<?php

include ('footer.php');

?>

  </main>



  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>


  <!-- Bootstrap Spinner Preloader -->
  <div id="preloader" class="d-flex justify-content-center align-items-center" style="position: fixed; inset: 0; background: #fff; z-index: 9999;">
    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
      <span class="visually-hidden">Cargando...</span>
    </div>
  </div>
  <script>
    window.addEventListener('load', function() {
      var preloader = document.getElementById('preloader');
      if (preloader) {
        preloader.style.display = 'none';
      }
    });
  </script>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
  <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>

  <!-- Main JS File -->
  <script src="assets/js/main.js"></script>

</body>

</html>
