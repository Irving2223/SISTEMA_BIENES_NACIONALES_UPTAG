<?php
session_start();

// Conexión a la base de datos
$host = "localhost";
$user = "root";
$pass = "";
$db = "bd_INTI";

$conn = mysqli_connect($host, $user, $pass, $db);

// Verificar conexión a la BD
if (!$conn) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Función para obtener estado del bot
function getBotStatus() {
    $status_file = 'bot_status.txt';
    if (file_exists($status_file)) {
        return trim(file_get_contents($status_file));
    }
    return 'off'; // Por defecto apagado
}

// Función para cambiar estado del bot
function setBotStatus($status) {
    $status_file = 'bot_status.txt';
    file_put_contents($status_file, $status);
}

// Función para obtener información del sistema
function getSystemInfo() {
    global $conn;

    $info = [];

    try {
        // Personas naturales
        $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM personas_naturales WHERE activo = 1");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $info['personas_naturales'] = $row['total'];
            mysqli_free_result($result);
        }

        // Personas jurídicas
        $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM personas_juridicas WHERE activo = 1");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $info['personas_juridicas'] = $row['total'];
            mysqli_free_result($result);
        }

        // Colectivos
        $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM colectivos WHERE activo = 1");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $info['colectivos'] = $row['total'];
            mysqli_free_result($result);
        }

        // Total de solicitudes
        $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM solicitudes");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $info['total_solicitudes'] = $row['total'];
            mysqli_free_result($result);
        }

        // Última solicitud
        $result = mysqli_query($conn, "SELECT MAX(fecha_solicitud) as ultima FROM solicitudes");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $info['ultima_solicitud'] = $row['ultima'] ? date('d/m/Y', strtotime($row['ultima'])) : 'N/A';
            mysqli_free_result($result);
        }

    } catch (Exception $e) {
        $info['error'] = $e->getMessage();
    }

    return $info;
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'toggle_bot') {
            $current_status = getBotStatus();
            $new_status = ($current_status === 'on') ? 'off' : 'on';
            setBotStatus($new_status);

            // Ejecutar/detener el bot de polling
            if ($new_status === 'on') {
                // Iniciar el bot en background
                $command = 'start /B php bot_polling.php > bot_output.log 2>&1';
                pclose(popen($command, 'r'));
            } else {
                // Detener el bot (matar proceso php)
                $command = 'taskkill /F /IM php.exe /FI "WINDOWTITLE eq bot_polling.php" >nul 2>&1';
                exec($command);
            }

            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'bot_status' => $new_status]);
            exit;
        }

        if ($action === 'update_db') {
            // Aquí iría la lógica para actualizar la base de datos
            // Por ahora solo simulamos la actualización

            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Base de datos actualizada correctamente']);
            exit;
        }
    }
}

$bot_status = getBotStatus();
$system_info = getSystemInfo();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Sistema INTI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #28a745;
            --light-green: #d4edda;
            --dark-green: #155724;
            --success: #28a745;
            --danger: #dc3545;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-green), #20c997);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--primary-green), #20c997);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #fd7e14);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }

        .status-indicator {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-on {
            background-color: var(--success);
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.5);
        }

        .status-off {
            background-color: var(--danger);
            box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);
        }

        .stats-card {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            border-left: 5px solid var(--primary-green);
        }

        .welcome-section {
            background: linear-gradient(135deg, var(--primary-green), #20c997);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .control-panel {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .welcome-section {
                padding: 1.5rem;
                text-align: center;
            }

            .btn-group-vertical {
                width: 100%;
            }

            .btn-group-vertical .btn {
                margin-bottom: 0.5rem;
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-robot me-2"></i>
                Sistema INTI - Panel de Control
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Sección de bienvenida -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 mb-3">
                        <i class="fas fa-chart-line me-3"></i>
                        ¡Bienvenido al Sistema INTI!
                    </h1>
                    <p class="lead mb-0">
                        Gestiona tu bot de Telegram y monitorea el estado del sistema desde este panel de control intuitivo.
                    </p>
                </div>
                <div class="col-lg-4 text-center">
                    <i class="fas fa-robot fa-5x opacity-75"></i>
                </div>
            </div>
        </div>

        <!-- Panel de control -->
        <div class="control-panel">
            <h3 class="mb-4">
                <i class="fas fa-cogs me-2"></i>
                Panel de Control del Bot
            </h3>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card stats-card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-robot me-2"></i>
                                Estado del Bot
                            </h5>
                            <div class="d-flex align-items-center mb-3">
                                <span class="status-indicator <?php echo $bot_status === 'on' ? 'status-on pulse' : 'status-off'; ?>"></span>
                                <span class="fw-bold">
                                    <?php echo $bot_status === 'on' ? 'ACTIVO' : 'INACTIVO'; ?>
                                </span>
                            </div>
                            <button id="toggleBotBtn" class="btn <?php echo $bot_status === 'on' ? 'btn-danger' : 'btn-success'; ?> w-100">
                                <i class="fas <?php echo $bot_status === 'on' ? 'fa-stop' : 'fa-play'; ?> me-2"></i>
                                <?php echo $bot_status === 'on' ? 'Apagar Bot' : 'Activar Bot'; ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card stats-card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-database me-2"></i>
                                Base de Datos
                            </h5>
                            <p class="text-muted mb-3">Actualiza la información del sistema</p>
                            <button id="updateDbBtn" class="btn btn-success w-100">
                                <i class="fas fa-sync-alt me-2"></i>
                                Actualizar BD
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Información adicional -->
        <div class="card stats-card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-info-circle me-2"></i>
                    Información del Sistema
                </h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Última solicitud registrada:</strong> <?php echo $system_info['ultima_solicitud'] ?? 'N/A'; ?></p>
                        <p><strong>Versión del sistema:</strong> INTI v2.0</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Estado del servidor:</strong> <span class="text-success">● Online</span></p>
                        <p><strong>Última actualización:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function() {
            // Toggle bot status
            $('#toggleBotBtn').click(function() {
                const btn = $(this);
                const originalText = btn.html();

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Procesando...');

                $.post('', { action: 'toggle_bot' })
                    .done(function(response) {
                        const data = JSON.parse(response);
                        if (data.status === 'success') {
                            location.reload();
                        } else {
                            alert('Error al cambiar el estado del bot');
                            btn.prop('disabled', false).html(originalText);
                        }
                    })
                    .fail(function() {
                        alert('Error de conexión');
                        btn.prop('disabled', false).html(originalText);
                    });
            });

            // Update database
            $('#updateDbBtn').click(function() {
                const btn = $(this);
                const originalText = btn.html();

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Actualizando...');

                $.post('', { action: 'update_db' })
                    .done(function(response) {
                        const data = JSON.parse(response);
                        if (data.status === 'success') {
                            alert(data.message);
                            location.reload();
                        } else {
                            alert('Error al actualizar la base de datos');
                        }
                        btn.prop('disabled', false).html(originalText);
                    })
                    .fail(function() {
                        alert('Error de conexión');
                        btn.prop('disabled', false).html(originalText);
                    });
            });
        });
    </script>
</body>
</html>