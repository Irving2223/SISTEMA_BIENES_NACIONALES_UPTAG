<?php
// Iniciar buffer de salida
ob_start();

// Conexión a la base de datos
include("conexion.php");

// Establecer zona horaria de Venezuela
date_default_timezone_set('America/Caracas');

// Obtener datos del POST
if (!isset($_POST['fecha_inicio'], $_POST['fecha_fin'], 
          $_POST['resultados_naturales_json'], $_POST['resultados_juridicas_json'], 
          $_POST['resultados_colectivos_json'], $_POST['resultados_integrantes_colectivos_json'])) {
    die("Error: Datos insuficientes para generar el reporte.");
}

$fecha_inicio = $_POST['fecha_inicio'];
$fecha_fin = $_POST['fecha_fin'];
$resultados_naturales = json_decode($_POST['resultados_naturales_json'], true);
$resultados_juridicas = json_decode($_POST['resultados_juridicas_json'], true);
$resultados_colectivos = json_decode($_POST['resultados_colectivos_json'], true);
$resultados_integrantes_colectivos = json_decode($_POST['resultados_integrantes_colectivos_json'], true);

// Validar fechas
if (empty($fecha_inicio) || empty($fecha_fin) || 
    !is_array($resultados_naturales) || !is_array($resultados_juridicas) || 
    !is_array($resultados_colectivos) || !is_array($resultados_integrantes_colectivos)) {
    die("Error: Fechas no válidas o datos inválidos.");
}

// Calcular totales
$total_naturales = count($resultados_naturales);
$total_juridicas = count($resultados_juridicas);
$total_colectivos = count($resultados_colectivos);
$total_integrantes_colectivos = count($resultados_integrantes_colectivos);
$total_general = $total_naturales + $total_juridicas + $total_colectivos;

// Calcular estadísticas socioeconómicas
$estadisticas_naturales = [
    'sexo' => ['M' => 0, 'F' => 0],
    'estado_civil' => ['Soltero' => 0, 'Casado' => 0, 'Viudo' => 0, 'Divorciado' => 0, 'Concubinato' => 0],
    'grado_instruccion' => ['Sin_nivel' => 0, 'Primaria' => 0, 'Secundaria' => 0, 'Tecnico' => 0, 'Universitario' => 0, 'Postgrado' => 0, 'Otro' => 0],
    'sabe_leer' => ['Si' => 0, 'No' => 0],
    'posee_ayuda_economica' => ['Si' => 0, 'No' => 0],
    'trabaja_actualmente' => ['Si' => 0, 'No' => 0],
    'pertenece_comuna' => ['Si' => 0, 'No' => 0]
];

foreach ($resultados_naturales as $pn) {
    if ($pn['sexo'] !== 'Otro') {
        if (isset($estadisticas_naturales['sexo'][$pn['sexo']])) {
            $estadisticas_naturales['sexo'][$pn['sexo']]++;
        }
    }
    
    if (isset($estadisticas_naturales['estado_civil'][$pn['estado_civil']])) {
        $estadisticas_naturales['estado_civil'][$pn['estado_civil']]++;
    }
    
    if (isset($estadisticas_naturales['grado_instruccion'][$pn['grado_instruccion']])) {
        $estadisticas_naturales['grado_instruccion'][$pn['grado_instruccion']]++;
    }
    
    if (isset($estadisticas_naturales['sabe_leer'][$pn['sabe_leer']])) {
        $estadisticas_naturales['sabe_leer'][$pn['sabe_leer']]++;
    }
    
    if (isset($estadisticas_naturales['posee_ayuda_economica'][$pn['posee_ayuda_economica']])) {
        $estadisticas_naturales['posee_ayuda_economica'][$pn['posee_ayuda_economica']]++;
    }
    
    if (isset($estadisticas_naturales['trabaja_actualmente'][$pn['trabaja_actualmente']])) {
        $estadisticas_naturales['trabaja_actualmente'][$pn['trabaja_actualmente']]++;
    }
    
    if (isset($estadisticas_naturales['pertenece_comuna'][$pn['pertenece_comuna']])) {
        $estadisticas_naturales['pertenece_comuna'][$pn['pertenece_comuna']]++;
    }
}

$estadisticas_juridicas = [
    'sexo_rep' => ['M' => 0, 'F' => 0],
    'estado_civil' => ['Soltero' => 0, 'Casado' => 0, 'Viudo' => 0, 'Divorciado' => 0, 'Concubinato' => 0],
    'grado_instruccion' => ['Sin_nivel' => 0, 'Primaria' => 0, 'Secundaria' => 0, 'Tecnico' => 0, 'Universitario' => 0, 'Postgrado' => 0, 'Otro' => 0],
    'sabe_leer' => ['Si' => 0, 'No' => 0],
    'posee_ayuda_economica' => ['Si' => 0, 'No' => 0],
    'trabaja_actualmente' => ['Si' => 0, 'No' => 0],
    'pertenece_comuna' => ['Si' => 0, 'No' => 0]
];

foreach ($resultados_juridicas as $pj) {
    if (isset($estadisticas_juridicas['sexo_rep'][$pj['rep_sexo']])) {
        $estadisticas_juridicas['sexo_rep'][$pj['rep_sexo']]++;
    }
    
    if (isset($estadisticas_juridicas['estado_civil'][$pj['estado_civil']])) {
        $estadisticas_juridicas['estado_civil'][$pj['estado_civil']]++;
    }
    
    if (isset($estadisticas_juridicas['grado_instruccion'][$pj['grado_instruccion']])) {
        $estadisticas_juridicas['grado_instruccion'][$pj['grado_instruccion']]++;
    }
    
    if (isset($estadisticas_juridicas['sabe_leer'][$pj['sabe_leer']])) {
        $estadisticas_juridicas['sabe_leer'][$pj['sabe_leer']]++;
    }
    
    if (isset($estadisticas_juridicas['posee_ayuda_economica'][$pj['posee_ayuda_economica']])) {
        $estadisticas_juridicas['posee_ayuda_economica'][$pj['posee_ayuda_economica']]++;
    }
    
    if (isset($estadisticas_juridicas['trabaja_actualmente'][$pj['trabaja_actualmente']])) {
        $estadisticas_juridicas['trabaja_actualmente'][$pj['trabaja_actualmente']]++;
    }
    
    if (isset($estadisticas_juridicas['pertenece_comuna'][$pj['pertenece_comuna']])) {
        $estadisticas_juridicas['pertenece_comuna'][$pj['pertenece_comuna']]++;
    }
}

$estadisticas_colectivos = [
    'sexo' => ['M' => 0, 'F' => 0],
    'estado_civil' => ['Soltero' => 0, 'Casado' => 0, 'Viudo' => 0, 'Divorciado' => 0, 'Concubinato' => 0],
    'grado_instruccion' => ['Sin_nivel' => 0, 'Primaria' => 0, 'Secundaria' => 0, 'Tecnico' => 0, 'Universitario' => 0, 'Postgrado' => 0, 'Otro' => 0],
    'sabe_leer' => ['Si' => 0, 'No' => 0],
    'posee_ayuda_economica' => ['Si' => 0, 'No' => 0],
    'trabaja_actualmente' => ['Si' => 0, 'No' => 0],
    'pertenece_comuna' => ['Si' => 0, 'No' => 0]
];

foreach ($resultados_integrantes_colectivos as $ic) {
    if ($ic['sexo'] !== 'Otro') {
        if (isset($estadisticas_colectivos['sexo'][$ic['sexo']])) {
            $estadisticas_colectivos['sexo'][$ic['sexo']]++;
        }
    }
    
    if (isset($estadisticas_colectivos['estado_civil'][$ic['estado_civil']])) {
        $estadisticas_colectivos['estado_civil'][$ic['estado_civil']]++;
    }
    
    if (isset($estadisticas_colectivos['grado_instruccion'][$ic['grado_instruccion']])) {
        $estadisticas_colectivos['grado_instruccion'][$ic['grado_instruccion']]++;
    }
    
    if (isset($estadisticas_colectivos['sabe_leer'][$ic['sabe_leer']])) {
        $estadisticas_colectivos['sabe_leer'][$ic['sabe_leer']]++;
    }
    
    if (isset($estadisticas_colectivos['posee_ayuda_economica'][$ic['posee_ayuda_economica']])) {
        $estadisticas_colectivos['posee_ayuda_economica'][$ic['posee_ayuda_economica']]++;
    }
    
    if (isset($estadisticas_colectivos['trabaja_actualmente'][$ic['trabaja_actualmente']])) {
        $estadisticas_colectivos['trabaja_actualmente'][$ic['trabaja_actualmente']]++;
    }
    
    if (isset($estadisticas_colectivos['pertenece_comuna'][$ic['pertenece_comuna']])) {
        $estadisticas_colectivos['pertenece_comuna'][$ic['pertenece_comuna']]++;
    }
}

// Simular nombre del usuario generador
$nombre_usuario = 'Usuario del Sistema';

// Si tienes sesión iniciada, puedes descomentar esto:
session_start();
$usuario_cedula = $_SESSION['usuario']['cedula'] ?? '';
if (!empty($usuario_cedula)) {
    // Intentar obtener el nombre desde diferentes tablas
    $nombre_completo = '';
    
    // Buscar en personas naturales
    $stmt = $conn->prepare("SELECT CONCAT(primer_nombre, ' ', primer_apellido) as nombre_completo FROM personas_naturales WHERE cedula = ? AND activo = 1");
    $stmt->bind_param("s", $usuario_cedula);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $nombre_completo = $row['nombre_completo'];
    }
    $stmt->close();
    
    // Si no se encontró en personas naturales, buscar en usuarios
    if (empty($nombre_completo)) {
        $stmt = $conn->prepare("SELECT CONCAT(nombre, ' ', apellido) as nombre_completo FROM usuarios WHERE cedula = ?");
        $stmt->bind_param("s", $usuario_cedula);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $nombre_completo = $row['nombre_completo'];
        }
        $stmt->close();
    }
    
    // Si se encontró un nombre, usarlo
    if (!empty($nombre_completo)) {
        $nombre_usuario = $nombre_completo;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Solicitantes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @page {
            margin: 20mm;
            size: A4 landscape;
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: transparent;
            font-size: 9px;
            color: #333;
        }

        .header {
            position: relative;
            background-image: url('http://localhost/SISTEMA INTI DAC/assets/img/sidebar/sidebar.webp');
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            /* background: #1E3A3A; */
            color: white;
            padding: 30px;
            border-radius:12px;
            margin-bottom: 20px;
        }

        .logo {
            position: absolute;
            top: 40px;
            right: 10px;
            text-align: right;
        }

        .title {
            font-size: 40px;
            font-weight: bold;
            margin: 0 0 5px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .subtitle {
            font-size: 20px;
            margin: 0;
            opacity: 0.9;
        }

        .section-title {
            background: #c5e0b3;
            color: black;
            padding: 8px 15px;
            margin: 15px 0 10px 0;
            border-radius: 6px;
            font-size: 17px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .table-container {
            background: white;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7px;
        }

        th {
            background: #c5e0b3;
            color: black;
            padding: 6px 2px;
            text-align: left;
            font-weight: bold;
            font-size: 6px;
            text-transform: uppercase;

        }

      td {
            padding: 10px 5px;
            border-bottom: 1px solid #e8f5e8;
            vertical-align: top;
        }


        tr:nth-child(even) {
            background-color: #f8fdf8;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 7px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .generated-info {
            background: #c5e0b3;
            padding: 8px;
            margin-top: 15px;
            border-radius: 4px;
            font-size: 7px;
            color: #333;
            text-align: left;
        }
        
        .representante-label {
            background-color: #009356ff;
            color: white;
            font-weight: bold;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 6px;
        }
        
        .info-row {
            display: block;
            margin-bottom: 5px;
            
        }

        .info-label {
            font-weight: bold;
            color: #555;

      
        }

        .report-info {
            background: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 2px;
            border-left: 4px solid #c5e0b3;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

    <div class="header">
            <!-- Ruta local al logo -->
            <img src="http://localhost/SISTEMA INTI DAC/assets/img/LOGO INTI.png" style="width:140px; height:140px; item-align: center;">

        <div class="logo">
            <h1 class="title" style="font-size: 40px;">REPORTE DE SOLICITANTES</h1>
            <p class="subtitle" style="font-size: 20px;">Departamento de atención al campesino</p>
       </div>

    </div>
    
        <div class="report-info">
        <div class="info-row">

            <span class="info-label">Rango de Fechas:</span> <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?> 
        <br>
            <span class="info-label">Generado el:</span> <?php echo date('d/m/Y H:i:s'); ?>
        <br>

            <span class="info-label">Generado por:</span> <?php echo htmlspecialchars($nombre_usuario) ?>  <?php echo"   ";?>
<br>
            <span class="info-label">Total Registros:</span> <?= $total_general ?>

        </div>
    </div>



    <!-- Estadísticas Generales -->
    <div class="table-container">
        <div class="section-title">ESTADÍSTICAS GENERALES</div>
        <table>
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>CATEGORÍA</th>
                    <th>PERSONAS NATURALES</th>
                    <th>PERSONAS JURÍDICAS</th>
                    <th>COLECTIVOS</th>
                    <th>TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>1</strong></td>
                    <td><strong>Total</strong></td>
                    <td><strong><?= $total_naturales ?></strong></td>
                    <td><strong><?= $total_juridicas ?></strong></td>
                    <td><strong><?= $total_colectivos ?></strong></td>
                    <td><strong><?= $total_general ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Datos Socioeconómicos - Personas Naturales -->
    <?php if (!empty($resultados_naturales)): ?>
        <div class="table-container">
            <div class="section-title">DATOS SOCIOECONÓMICOS - PERSONAS NATURALES</div>
            <table>
                <thead>
                    <tr>
                        <th>INDICADOR</th>
                        <th>MASCULINO</th>
                        <th>FEMENINO</th>
                        <th>SOLTERO</th>
                        <th>CASADO</th>
                        <th>VIUDO</th>
                        <th>DIVORCIADO</th>
                        <th>CONCUBINATO</th>
                        <th>SABE LEER</th>
                        <th>NO SABE LEER</th>
                        <th>AYUDA ECONÓMICA</th>
                        <th>TRABAJA</th>
                        <th>PERTENECE A COMUNA</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>CANTIDAD</strong></td>
                        <td><strong><?= $estadisticas_naturales['sexo']['M'] ?></strong></td>
                        <td><strong><?= $estadisticas_naturales['sexo']['F'] ?></strong></td>
                        <td><strong><?= $estadisticas_naturales['estado_civil']['Soltero'] ?></strong></td>
                        <td><strong><?= $estadisticas_naturales['estado_civil']['Casado'] ?></strong></td>
                        <td><strong><?= $estadisticas_naturales['estado_civil']['Viudo'] ?></strong></td>
                        <td><strong><?= $estadisticas_naturales['estado_civil']['Divorciado'] ?></strong></td>
                        <td><strong><?= $estadisticas_naturales['estado_civil']['Concubinato'] ?></strong></td>
                        <td><strong><?= $estadisticas_naturales['sabe_leer']['Si'] ?></strong></td>
                        <td><strong><?= $estadisticas_naturales['sabe_leer']['No'] ?></strong></td>
                        <td><strong><?= $estadisticas_naturales['posee_ayuda_economica']['Si'] ?></strong></td>
                        <td><strong><?= $estadisticas_naturales['trabaja_actualmente']['Si'] ?></strong></td>
                        <td><strong><?= $estadisticas_naturales['pertenece_comuna']['Si'] ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Datos Socioeconómicos - Personas Jurídicas -->
    <?php if (!empty($resultados_juridicas)): ?>
        <div class="table-container">
            <div class="section-title">DATOS SOCIOECONÓMICOS - PERSONAS JURÍDICAS (REPRESENTANTES)</div>
            <table>
                <thead>
                    <tr>
                        <th>INDICADOR</th>
                        <th>MASCULINO</th>
                        <th>FEMENINO</th>
                        <th>SOLTERO</th>
                        <th>CASADO</th>
                        <th>VIUDO</th>
                        <th>DIVORCIADO</th>
                        <th>CONCUBINATO</th>
                        <th>SIN NIVEL</th>
                        <th>PRIMARIA</th>
                        <th>SECUNDARIA</th>
                        <th>TECNICO</th>
                        <th>UNIVERSITARIO</th>
                        <th>POSTGRADO</th>
                        <th>OTRO</th>
                        <th>SABE LEER</th>
                        <th>NO SABE LEER</th>
                        <th>AYUDA ECONÓMICA</th>
                        <th>TRABAJA</th>
                        <th>PERTENECE A COMUNA</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>CANTIDAD</strong></td>
                        <td><strong><?= $estadisticas_juridicas['sexo_rep']['M'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['sexo_rep']['F'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['estado_civil']['Soltero'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['estado_civil']['Casado'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['estado_civil']['Viudo'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['estado_civil']['Divorciado'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['estado_civil']['Concubinato'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['grado_instruccion']['Sin_nivel'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['grado_instruccion']['Primaria'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['grado_instruccion']['Secundaria'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['grado_instruccion']['Tecnico'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['grado_instruccion']['Universitario'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['grado_instruccion']['Postgrado'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['grado_instruccion']['Otro'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['sabe_leer']['Si'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['sabe_leer']['No'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['posee_ayuda_economica']['Si'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['trabaja_actualmente']['Si'] ?></strong></td>
                        <td><strong><?= $estadisticas_juridicas['pertenece_comuna']['Si'] ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Datos Socioeconómicos - Integrantes de Colectivos -->
    <?php if (!empty($resultados_integrantes_colectivos)): ?>
        <div class="table-container">
            <div class="section-title">DATOS SOCIOECONÓMICOS - INTEGRANTES DE COLECTIVOS</div>
            <table>
                <thead>
                    <tr>
                        <th>INDICADOR</th>
                        <th>MASCULINO</th>
                        <th>FEMENINO</th>
                        <th>SOLTERO</th>
                        <th>CASADO</th>
                        <th>VIUDO</th>
                        <th>DIVORCIADO</th>
                        <th>CONCUBINATO</th>
                        <th>SIN NIVEL</th>
                        <th>PRIMARIA</th>
                        <th>SECUNDARIA</th>
                        <th>TECNICO</th>
                        <th>UNIVERSITARIO</th>
                        <th>POSTGRADO</th>
                        <th>OTRO</th>
                        <th>SABE LEER</th>
                        <th>NO SABE LEER</th>
                        <th>AYUDA ECONÓMICA</th>
                        <th>TRABAJA</th>
                        <th>PERTENECE A COMUNA</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>CANTIDAD</strong></td>
                        <td><strong><?= $estadisticas_colectivos['sexo']['M'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['sexo']['F'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['estado_civil']['Soltero'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['estado_civil']['Casado'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['estado_civil']['Viudo'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['estado_civil']['Divorciado'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['estado_civil']['Concubinato'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['grado_instruccion']['Sin_nivel'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['grado_instruccion']['Primaria'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['grado_instruccion']['Secundaria'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['grado_instruccion']['Tecnico'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['grado_instruccion']['Universitario'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['grado_instruccion']['Postgrado'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['grado_instruccion']['Otro'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['sabe_leer']['Si'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['sabe_leer']['No'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['posee_ayuda_economica']['Si'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['trabaja_actualmente']['Si'] ?></strong></td>
                        <td><strong><?= $estadisticas_colectivos['pertenece_comuna']['Si'] ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>


    <!-- Personas Naturales -->
     <?php if (!empty($resultados_naturales)): ?>
         <div class="section-title">PERSONAS NATURALES (<?= $total_naturales ?>)</div>
         <div class="table-container">
             <table>
                 <thead>
                     <tr>
                         <th>Nº</th>
                         <th>CÉDULA</th>
                         <th>NOMBRE COMPLETO</th>
                         <th>SEXO</th>
                         <th>FECHA NAC.</th>
                         <th>EDAD</th>
                         <th>TELÉFONO</th>
                         <th>DIRECCIÓN</th>
                         <th>ESTADO CIVIL</th>
                         <th>HIJOS</th>
                         <th>GRADO INSTRUCCIÓN</th>
                         <th>SABE LEER</th>
                         <th>AYUDA ECONÓMICA</th>
                         <th>TRABAJA</th>
                         <th>PERTENECE A COMUNA</th>
                         <th>ENFERMEDADES</th>
                         <th>REPRESENTANTE</th>
                     </tr>
                 </thead>
                 <tbody>
                     <?php $contador_pn = 1; ?>
                     <?php foreach ($resultados_naturales as $pn): ?>
                         <?php if ($pn['sexo'] !== 'Otro'): ?>
                             <?php
                             $edad = 0;
                             if (!empty($pn['fecha_nacimiento'])) {
                                 $fecha_nac = new DateTime($pn['fecha_nacimiento']);
                                 $hoy = new DateTime();
                                 $edad = $hoy->diff($fecha_nac)->y;
                             }
                             ?>
                             <tr>
                                 <td><?= $contador_pn++; ?></td>
                                 <td><?= htmlspecialchars($pn['cedula']); ?></td>
                                 <td><?= htmlspecialchars($pn['nombre_completo']); ?></td>
                                 <td><?= htmlspecialchars($pn['sexo'] ?? 'N/A'); ?></td>
                                 <td><?= $pn['fecha_nacimiento'] ? date('d/m/Y', strtotime($pn['fecha_nacimiento'])) : 'N/A'; ?></td>
                                 <td><?= $edad; ?></td>
                                 <td><?= htmlspecialchars($pn['telefono'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($pn['direccion_habitacion']); ?></td>
                                 <td><?= htmlspecialchars($pn['estado_civil'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($pn['numero_hijos'] ?? '0'); ?></td>
                                 <td><?= htmlspecialchars($pn['grado_instruccion'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($pn['sabe_leer'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($pn['posee_ayuda_economica'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($pn['trabaja_actualmente'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($pn['pertenece_comuna'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($pn['enfermedades'] ?? 'N/A'); ?></td>
                                 <td>
                                     <?php if ($pn['tiene_representante'] === 'Sí'): ?>
                                         <span class="representante-label">
                                             <?= htmlspecialchars(($pn['rep_primer_nombre'] ?? '') . ' ' . ($pn['rep_segundo_nombre'] ?? '') . ' ' . ($pn['rep_primer_apellido'] ?? '') . ' ' . ($pn['rep_segundo_apellido'] ?? '')); ?>
                                         </span>
                                     <?php else: ?>
                                         <span style="background-color: #6c757d; color: white; padding: 1px 4px; border-radius: 3px; font-size: 6px;">No aplica</span>
                                     <?php endif; ?>
                                 </td>
                             </tr>
                         <?php endif; ?>
                     <?php endforeach; ?>
                 </tbody>
             </table>
         </div>
     <?php endif; ?>

    <!-- Personas Jurídicas -->
     <?php if (!empty($resultados_juridicas)): ?>
         <div class="section-title">PERSONAS JURÍDICAS (<?= $total_juridicas ?>)</div>
         <div class="table-container">
             <table>
                 <thead>
                     <tr>
                         <th>Nº</th>
                         <th>RIF</th>
                         <th>RAZÓN SOCIAL</th>
                         <th>TELÉFONO</th>
                         <th>DIRECCIÓN</th>
                         <th>REPRESENTANTE LEGAL</th>
                         <th>SEXO REPRESENTANTE</th>
                         <th>EDAD REPRESENTANTE</th>
                         <th>PROFESIÓN</th>
                     </tr>
                 </thead>
                 <tbody>
                     <?php $contador_pj = 1; ?>
                     <?php foreach ($resultados_juridicas as $pj): ?>
                     <tr>
                         <td><?= $contador_pj++; ?></td>
                         <td><?= htmlspecialchars($pj['rif']); ?></td>
                         <td><?= htmlspecialchars($pj['razon_social']); ?></td>
                         <td><?= htmlspecialchars($pj['telefono'] ?? 'N/A'); ?></td>
                         <td><?= htmlspecialchars($pj['direccion_habitacion']); ?></td>
                         <td>
                             <?php if ($pj['rep_primer_nombre']): ?>
                                 <span class="representante-label">
                                     <?= htmlspecialchars($pj['rep_primer_nombre'] . ' ' . ($pj['rep_segundo_nombre'] ?? '') . ' ' . $pj['rep_primer_apellido'] . ' ' . ($pj['rep_segundo_apellido'] ?? '')); ?>
                                 </span>
                             <?php else: ?>
                                 <span style="background-color: #6c757d; color: white; padding: 1px 4px; border-radius: 3px; font-size: 6px;">No asignado</span>
                             <?php endif; ?>
                         </td>
                         <td><?= htmlspecialchars($pj['rep_sexo'] ?? 'N/A'); ?></td>
                         <td>
                             <?php
                             $edad_rep = 'N/A';
                             if (!empty($pj['rep_fecha_nacimiento'])) {
                                 $fecha_nac_rep = new DateTime($pj['rep_fecha_nacimiento']);
                                 $hoy = new DateTime();
                                 $edad_rep = $hoy->diff($fecha_nac_rep)->y;
                             }
                             echo $edad_rep;
                             ?>
                         </td>
                         <td><?= htmlspecialchars($pj['rep_profesion'] ?? 'N/A'); ?></td>
                     </tr>
                     <?php endforeach; ?>
                 </tbody>
             </table>
         </div>
     <?php endif; ?>

    <!-- Colectivos -->
     <?php if (!empty($resultados_colectivos)): ?>
         <div class="section-title">COLECTIVOS (<?= $total_colectivos ?>)</div>
         <div class="table-container">
             <table>
                 <thead>
                     <tr>
                         <th>Nº</th>
                         <th>RIF/CI REFERENTE</th>
                         <th>NOMBRE DEL COLECTIVO</th>
                         <th>INTEGRANTES</th>
                         <th>TELÉFONO</th>
                         <th>DIRECCIÓN</th>
                     </tr>
                 </thead>
                 <tbody>
                     <?php $contador_c = 1; ?>
                     <?php foreach ($resultados_colectivos as $c): ?>
                     <tr>
                         <td><?= $contador_c++; ?></td>
                         <td><?= htmlspecialchars($c['rif_o_ci_referente']); ?></td>
                         <td><?= htmlspecialchars($c['nombre_colectivo']); ?></td>
                         <td><?= htmlspecialchars($c['numero_integrantes']); ?></td>
                         <td><?= htmlspecialchars($c['telefono'] ?? 'N/A'); ?></td>
                         <td><?= htmlspecialchars($c['direccion_habitacion']); ?></td>
                     </tr>
                     <?php endforeach; ?>
                 </tbody>
             </table>
         </div>
     <?php endif; ?>

    <!-- Integrantes de Colectivos -->
     <?php if (!empty($resultados_integrantes_colectivos)): ?>
         <div class="section-title">INTEGRANTES DE COLECTIVOS (<?= $total_integrantes_colectivos ?>)</div>
         <div class="table-container">
             <table>
                 <thead>
                     <tr>
                         <th>Nº</th>
                         <th>RIF COLECTIVO</th>
                         <th>CÉDULA</th>
                         <th>NOMBRE COMPLETO</th>
                         <th>SEXO</th>
                         <th>FECHA NAC.</th>
                         <th>EDAD</th>
                         <th>TELÉFONO</th>
                         <th>DIRECCIÓN</th>
                         <th>ESTADO CIVIL</th>
                         <th>HIJOS</th>
                         <th>GRADO INSTRUCCIÓN</th>
                         <th>SABE LEER</th>
                         <th>AYUDA ECONÓMICA</th>
                         <th>TRABAJA</th>
                         <th>PERTENECE A COMUNA</th>
                         <th>ENFERMEDADES</th>
                         <th>ES REFERENTE</th>
                         <th>CARGO</th>
                         <th>FECHA INGRESO</th>
                     </tr>
                 </thead>
                 <tbody>
                     <?php $contador_ic = 1; ?>
                     <?php foreach ($resultados_integrantes_colectivos as $ic): ?>
                         <?php if ($ic['sexo'] !== 'Otro'): ?>
                             <?php
                             $edad = 0;
                             if (!empty($ic['fecha_nacimiento'])) {
                                 $fecha_nac = new DateTime($ic['fecha_nacimiento']);
                                 $hoy = new DateTime();
                                 $edad = $hoy->diff($fecha_nac)->y;
                             }
                             ?>
                             <tr>
                                 <td><?= $contador_ic++; ?></td>
                                 <td><?= htmlspecialchars($ic['rif_colectivo']); ?></td>
                                 <td><?= htmlspecialchars($ic['cedula']); ?></td>
                                 <td><?= htmlspecialchars($ic['nombre_completo']); ?></td>
                                 <td><?= htmlspecialchars($ic['sexo'] ?? 'N/A'); ?></td>
                                 <td><?= $ic['fecha_nacimiento'] ? date('d/m/Y', strtotime($ic['fecha_nacimiento'])) : 'N/A'; ?></td>
                                 <td><?= $edad; ?></td>
                                 <td><?= htmlspecialchars($ic['telefono'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($ic['direccion_habitacion']); ?></td>
                                 <td><?= htmlspecialchars($ic['estado_civil'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($ic['numero_hijos'] ?? '0'); ?></td>
                                 <td><?= htmlspecialchars($ic['grado_instruccion'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($ic['sabe_leer'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($ic['posee_ayuda_economica'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($ic['trabaja_actualmente'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($ic['pertenece_comuna'] ?? 'N/A'); ?></td>
                                 <td><?= htmlspecialchars($ic['enfermedades'] ?? 'N/A'); ?></td>
                                 <td><?= $ic['es_referente'] ? 'Sí' : 'No'; ?></td>
                                 <td><?= htmlspecialchars($ic['cargo_en_colectivo'] ?? 'N/A'); ?></td>
                                 <td><?= date('d/m/Y', strtotime($ic['fecha_ingreso'])); ?></td>
                             </tr>
                         <?php endif; ?>
                     <?php endforeach; ?>
                 </tbody>
             </table>
         </div>
     <?php endif; ?>

    <div class="footer">
        Sistema de Información para la Gestión Administrativa del Departamento de Atención al Campesino del Instituto Nacional de Tierras (INTI) © 2025 by Irving Coello, Richard Molina, Dixon Véliz y Brayan Pirona
    </div>  

</body>
</html>

<?php
// Obtener el HTML capturado
$html = ob_get_clean();

// Cargar domPDF
require_once "librerias/dompdf/autoload.inc.php";
use Dompdf\Dompdf;

$dompdf = new Dompdf();

// Habilitar carga de imágenes remotas
$options = $dompdf->getOptions();
$options->set('isRemoteEnabled', true);
$dompdf->setOptions($options);

// Cargar HTML
$dompdf->loadHtml($html);

// Configurar papel
$dompdf->setPaper('A4', 'landscape');

// Renderizar
$dompdf->render();

// Nombre del archivo
$filename = "reporte_solicitantes_" . date('Ymd_His') . ".pdf";

// Enviar al navegador
$dompdf->stream($filename, [
    "Attachment" => true  // Descarga el archivo
]);

exit();
?>

