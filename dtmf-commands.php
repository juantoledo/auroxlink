<?php
    require 'includes/environment.php';

    // JSON file to store custom DTMF commands (use absolute path from script directory)
    $customCommandsFile = __DIR__ . '/dtmf_custom_commands.json';
    
    // JSON file to store DTMF configuration (use absolute path from script directory)
    $dtmfConfigFile = __DIR__ . '/dtmf_config.json';

    // Function to get PTY path from SVXLink configuration
    function getPTYPathFromConfig() {
        $svxlinkConfig = '/etc/svxlink/svxlink.conf';
        $ptyPath = '/dev/shm/dtmf_ctrl'; // Default value
        
        if (file_exists($svxlinkConfig)) {
            $lines = @file($svxlinkConfig);
            if ($lines) {
                $inSimplexLogic = false;
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    // Check if we're in SimplexLogic section
                    if (preg_match('/^\[SimplexLogic\]/i', $line)) {
                        $inSimplexLogic = true;
                        continue;
                    }
                    
                    // Check if we're entering a new section
                    if (preg_match('/^\[.*\]/', $line) && !preg_match('/^\[SimplexLogic\]/i', $line)) {
                        $inSimplexLogic = false;
                        continue;
                    }
                    
                    // Look for DTMF_CTRL_PTY in SimplexLogic section
                    if ($inSimplexLogic && preg_match('/^\s*DTMF_CTRL_PTY\s*=\s*(.+)$/i', $line, $matches)) {
                        $ptyPath = trim($matches[1]);
                        break;
                    }
                }
            }
        }
        
        return $ptyPath;
    }

    // Function to load DTMF configuration
    function loadDTMFConfig($file) {
        if (!file_exists($file)) {
            // Default configuration
            return [
                'execution_command' => "printf '{DTMF_CODE}' | sudo -u svxlink tee {PTY_DEVICE} >/dev/null",
                'pty_path' => '/dev/shm/dtmf_ctrl'
            ];
        }
        $content = @file_get_contents($file);
        if ($content === false) {
            return [
                'execution_command' => "printf '{DTMF_CODE}' | tee {PTY_DEVICE} >/dev/null",
                'pty_path' => '/dev/shm/dtmf_ctrl'
            ];
        }
        $config = json_decode($content, true);
        return is_array($config) ? $config : [
            'execution_command' => "printf '{DTMF_CODE}' | tee {PTY_DEVICE} >/dev/null",
            'pty_path' => '/dev/shm/dtmf_ctrl'
        ];
    }

    // Function to save DTMF configuration
    function saveDTMFConfig($file, $config) {
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $result = file_put_contents($file, $json);
        if ($result !== false) {
            // Set proper permissions so www-data can write to it
            @chmod($file, 0664);
            @chgrp($file, 'www-data');
        }
        return $result !== false;
    }

    // Function to load custom commands from JSON file
    function loadCustomCommands($file) {
        if (!file_exists($file)) {
            return [];
        }
        $content = @file_get_contents($file);
        if ($content === false) {
            return [];
        }
        $commands = json_decode($content, true);
        return is_array($commands) ? $commands : [];
    }

    // Function to save custom commands to JSON file
    function saveCustomCommands($file, $commands) {
        $json = json_encode($commands, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $result = @file_put_contents($file, $json);
        if ($result !== false) {
            // Set proper permissions so www-data can write to it
            @chmod($file, 0664);
            @chgrp($file, 'www-data');
        }
        return $result !== false;
    }

    // Function to read DTMF commands from the PTY device
    function getDTMFStatus() {
        // Get PTY path from SVXLink configuration
        $dtmfPtyPath = getPTYPathFromConfig();
        
        $status = [
            'enabled' => false,
            'path' => $dtmfPtyPath,
            'exists' => file_exists($dtmfPtyPath),
            'readable' => false,
            'writable' => false
        ];

        if ($status['exists']) {
            // For symlinks (like PTY devices), resolve to the real path
            $realPath = is_link($dtmfPtyPath) ? readlink($dtmfPtyPath) : $dtmfPtyPath;
            
            // Check if it's readable (don't check writable as it blocks on PTYs/FIFOs)
            $status['readable'] = is_readable($dtmfPtyPath);
            
            // Assume writable if it exists and is readable (actual write test would block)
            // The real write permission check happens when executing commands
            $status['writable'] = $status['readable'];
            $status['enabled'] = true;
        }

        return $status;
    }

    // Function to send DTMF command
    function sendDTMFCommand($command, $config) {
        // Check if PTY device exists before executing
        if (!file_exists($config['pty_path'])) {
            return [
                'success' => false, 
                'message' => 'Error: El dispositivo PTY no existe: ' . $config['pty_path'],
                'executed_command' => null
            ];
        }
        
        // Skip is_writable() check for FIFOs/PTYs as they block when there's no reader
        // The actual write operation will fail properly if there are permission issues
        
        // Use configured execution command
        $executionCmd = $config['execution_command'];
        
        // Replace {DTMF_CODE} with the actual command and {PTY_DEVICE} with the configured path
        // Don't use escapeshellarg - the placeholders are already in the right format
        $finalCmd = str_replace('{DTMF_CODE}', $command, $executionCmd);
        $finalCmd = str_replace('{PTY_DEVICE}', $config['pty_path'], $finalCmd);
        
        // Execute the command and capture both output and exit code
        // We need to wrap it properly to get the real exit code
        $wrappedCmd = "bash -c " . escapeshellarg($finalCmd) . " 2>&1; echo \"EXIT_CODE:\$?\"";
        $output = shell_exec($wrappedCmd);
        
        // Extract the exit code from the last line
        $exitCode = 1; // Default to error
        if ($output !== null && preg_match('/EXIT_CODE:(\d+)$/', $output, $matches)) {
            $exitCode = (int)$matches[1];
            $output = preg_replace('/EXIT_CODE:\d+$/', '', $output); // Remove exit code marker
        }
        $output = trim($output);
        
        // Success is determined by exit code 0
        if ($exitCode === 0) {
            return [
                'success' => true, 
                'message' => '✅ Comando DTMF "<strong>' . htmlspecialchars($command) . '</strong>" enviado correctamente al sistema.',
                'executed_command' => $finalCmd
            ];
        } else {
            // Only show output if there's an actual error
            $errorDetails = !empty($output) ? ' Detalles: ' . $output : '';
            return [
                'success' => false, 
                'message' => 'Error al ejecutar el comando (código de salida: ' . $exitCode . ').' . $errorDetails,
                'executed_command' => $finalCmd
            ];
        }
    }

    // Handle DTMF command submission
    $responseMessage = null;
    $messageType = 'info';

    // Load DTMF configuration
    $dtmfConfig = loadDTMFConfig($dtmfConfigFile);

    // Load custom commands
    $customCommands = loadCustomCommands($customCommandsFile);

    // Handle saving DTMF configuration
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {
        $executionCommand = trim($_POST['execution_command'] ?? '');
        
        if (empty($executionCommand)) {
            $responseMessage = 'Por favor completa el campo de comando de ejecución.';
            $messageType = 'warning';
        } else {
            // Get PTY path from SVXLink configuration
            $ptyPath = getPTYPathFromConfig();
            
            $newConfig = [
                'execution_command' => $executionCommand,
                'pty_path' => $ptyPath
            ];
            
            $saveResult = saveDTMFConfig($dtmfConfigFile, $newConfig);
            
            if ($saveResult) {
                $responseMessage = '✅ Configuración de ejecución guardada exitosamente.';
                $messageType = 'success';
                // Update the loaded config with the saved values
                $dtmfConfig = $newConfig;
            } else {
                // More detailed error message
                $responseMessage = 'Error al guardar la configuración. Verifica los permisos del archivo: ' . $dtmfConfigFile;
                $messageType = 'danger';
            }
        }
    } else {
        // Only update PTY path from SVXLink configuration if we're not saving
        $dtmfConfig['pty_path'] = getPTYPathFromConfig();
    }

    // Handle adding a new custom command
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_command') {
        $commandName = trim($_POST['command_name'] ?? '');
        $commandCode = trim($_POST['command_code'] ?? '');
        
        if (empty($commandName) || empty($commandCode)) {
            $responseMessage = 'Por favor completa todos los campos para crear el comando.';
            $messageType = 'warning';
        } else {
            // Add new command
            $customCommands[] = [
                'id' => uniqid(),
                'name' => $commandName,
                'code' => $commandCode,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if (saveCustomCommands($customCommandsFile, $customCommands)) {
                $responseMessage = "✅ Comando personalizado '$commandName' creado exitosamente.";
                $messageType = 'success';
            } else {
                $responseMessage = 'Error al guardar el comando personalizado.';
                $messageType = 'danger';
            }
        }
    }

    // Handle deleting a custom command
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_command') {
        $commandId = trim($_POST['command_id'] ?? '');
        
        if (!empty($commandId)) {
            $customCommands = array_filter($customCommands, function($cmd) use ($commandId) {
                return $cmd['id'] !== $commandId;
            });
            $customCommands = array_values($customCommands); // Re-index array
            
            if (saveCustomCommands($customCommandsFile, $customCommands)) {
                $responseMessage = '🗑️ Comando personalizado eliminado exitosamente.';
                $messageType = 'success';
            } else {
                $responseMessage = 'Error al eliminar el comando personalizado.';
                $messageType = 'danger';
            }
        }
    }

    // Handle executing a DTMF command
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dtmf_command'])) {
        $command = trim($_POST['dtmf_command']);
        
        if (empty($command)) {
            $responseMessage = 'Por favor ingresa un comando DTMF.';
            $messageType = 'warning';
            $executedCommand = null;
        } else {
            $result = sendDTMFCommand($command, $dtmfConfig);
            $responseMessage = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            $executedCommand = $result['executed_command'];
        }
    }

    $dtmfStatus = getDTMFStatus();

    // Common DTMF commands for SVXLink
    $commonCommands = [
        ['code' => '*#', 'description' => 'Reproduce un breve mensaje de identificación/estado'],
        ['code' => '#', 'description' => 'Desconecta la estación conectada más recientemente'],
        ['code' => '##', 'description' => 'Desconecta todas las estaciones conectadas'],
        ['code' => '2#', 'description' => 'Conectar a echolink'],
        ['code' => '2#570916#', 'description' => 'Conectar a Echolink RedChile'],
    ];
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/style.css.php">
    <link rel="shortcut icon" href="img/favicon.png" type="image/png">
    <title><?php echo $titleSite; ?> - Comandos DTMF</title>
</head>
<body>
    <div class="container-fluid bg-body-content">
        <div class="row">
            <?php require 'includes/sidebar-menu.php'; ?>

            <!-- Contenido principal -->
            <div class="col-12 col-md-10 p-3">
                <div class="d-flex align-items-center">
                    <button class="btn btn-dark d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu">
                        ☰
                    </button>
                    <!-- Título -->
                    <h2 class="fs-4 titulo m-0">📟 Comandos DTMF</h2>
                </div>

                <!-- Status del sistema DTMF -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Estado del Sistema DTMF</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($dtmfStatus['enabled'] && $dtmfStatus['exists']): ?>
                                    <div class="alert alert-success">
                                        <strong>✅ Sistema DTMF Activo</strong><br>
                                        <small>Dispositivo PTY: <code><?php echo htmlspecialchars($dtmfStatus['path']); ?></code></small><br>
                                        <small>Lectura: <?php echo $dtmfStatus['readable'] ? '✅' : '❌'; ?> | Escritura: <?php echo $dtmfStatus['writable'] ? '✅' : '❌'; ?></small>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <strong>⚠️ Sistema DTMF no disponible</strong><br>
                                        <small>El dispositivo PTY no existe o no está configurado.</small><br>
                                        <small>Por favor, habilita la opción DTMF_CTRL_PTY en la <a href="settings.php">Configuración</a>.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Response message -->
                <?php if ($responseMessage): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <?php echo $responseMessage; ?>
                            <?php if (isset($executedCommand) && $executedCommand !== null): ?>
                                <br><small class="text-muted">Comando ejecutado: <code><?php echo htmlspecialchars($executedCommand); ?></code></small>
                            <?php endif; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Configuración de Ejecución -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">⚙️ Configuración de Ejecución de Comandos</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <input type="hidden" name="action" value="save_config">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="execution_command" class="form-label">Comando de Ejecución</label>
                                                <input type="text" class="form-control font-monospace" id="execution_command" name="execution_command" 
                                                    value="<?php echo htmlspecialchars($dtmfConfig['execution_command']); ?>" required>
                                                <small class="form-text text-muted">
                                                    Utiliza <code>{DTMF_CODE}</code> para el código DTMF y <code>{PTY_DEVICE}</code> para la ruta del dispositivo. 
                                                    Ejemplo: <code>printf '{DTMF_CODE}' | sudo -u svxlink tee {PTY_DEVICE} >/dev/null</code>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="pty_path" class="form-label">Ruta del Dispositivo PTY</label>
                                                <input type="text" class="form-control font-monospace" id="pty_path" name="pty_path" 
                                                    value="<?php echo htmlspecialchars($dtmfConfig['pty_path']); ?>" disabled>
                                                <small class="form-text text-muted">
                                                    Configurable en <a href="settings.php">Configuración</a> (DTMF_CTRL_PTY).
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        💾 Guardar Configuración
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('execution_command').value = 'printf \'{DTMF_CODE}\' | sudo -u svxlink tee {PTY_DEVICE} >/dev/null';">
                                        🔄 Restaurar Comando por Defecto
                                    </button>
                                </form>
                                <div class="alert alert-info mt-3 mb-0">
                                    <small>
                                        <strong>ℹ️ Información:</strong> El comando de ejecución define cómo se envían los códigos DTMF al sistema SVXLink. 
                                        Puedes personalizarlo según tu configuración específica. Los marcadores <code>{DTMF_CODE}</code> y <code>{PTY_DEVICE}</code> serán reemplazados por los valores reales.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario para enviar comandos DTMF -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Enviar Comando DTMF</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <div class="mb-3">
                                        <label for="dtmf_command" class="form-label">Comando DTMF</label>
                                        <input type="text" class="form-control" id="dtmf_command" name="dtmf_command" 
                                            placeholder="Ej: 0, 1, 3CALLSIGN#, 4123456#" 
                                            <?php echo (!$dtmfStatus['enabled'] || !$dtmfStatus['writable']) ? 'disabled' : ''; ?>
                                            required>
                                        <small class="form-text text-muted">
                                            Ingresa el comando DTMF que deseas enviar al sistema SVXLink.
                                        </small>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100" 
                                        <?php echo (!$dtmfStatus['enabled'] || !$dtmfStatus['writable']) ? 'disabled' : ''; ?>>
                                        📤 Enviar Comando
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Crear comando personalizado -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">➕ Crear Comando Personalizado</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <input type="hidden" name="action" value="add_command">
                                    <div class="mb-3">
                                        <label for="command_name" class="form-label">Nombre del Comando</label>
                                        <input type="text" class="form-control" id="command_name" name="command_name" 
                                            placeholder="Ej: Conectar con RedChile" required>
                                        <small class="form-text text-muted">
                                            Dale un nombre descriptivo a tu comando.
                                        </small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="command_code" class="form-label">Código DTMF</label>
                                        <input type="text" class="form-control" id="command_code" name="command_code" 
                                            placeholder="Ej: 2#570916#" required>
                                        <small class="form-text text-muted">
                                            Secuencia de códigos DTMF a ejecutar.
                                        </small>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100">
                                        💾 Guardar Comando
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de comandos comunes -->
                    <div class="col-md-6">
                        <!-- Custom Commands -->
                        <?php if (count($customCommands) > 0): ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="mb-0">⭐ Comandos Personalizados</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Código</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($customCommands as $cmd): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($cmd['name']); ?></strong></td>
                                                <td><code><?php echo htmlspecialchars($cmd['code']); ?></code></td>
                                                <td>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('¿Ejecutar comando <?php echo htmlspecialchars($cmd['name']); ?>?');">
                                                        <input type="hidden" name="dtmf_command" value="<?php echo htmlspecialchars($cmd['code']); ?>">
                                                        <button type="submit" class="btn btn-sm btn-primary" 
                                                            <?php echo (!$dtmfStatus['enabled'] || !$dtmfStatus['writable']) ? 'disabled' : ''; ?>
                                                            title="Ejecutar comando">
                                                            ▶️
                                                        </button>
                                                    </form>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar comando <?php echo htmlspecialchars($cmd['name']); ?>?');">
                                                        <input type="hidden" name="action" value="delete_command">
                                                        <input type="hidden" name="command_id" value="<?php echo htmlspecialchars($cmd['id']); ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar comando">
                                                            🗑️
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Common Commands -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">📋 Comandos DTMF Comunes</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Descripción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($commonCommands as $cmd): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($cmd['code']); ?></code></td>
                                                <td><?php echo htmlspecialchars($cmd['description']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="alert alert-info mt-3 mb-0">
                                    <small>
                                        <strong>ℹ️ Nota:</strong> Los comandos DTMF disponibles pueden variar según la configuración de tu nodo SVXLink.
                                        Consulta la documentación de SVXLink para más detalles.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información adicional -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">ℹ️ Acerca de los Comandos DTMF</h5>
                            </div>
                            <div class="card-body">
                                <p>
                                    Los comandos DTMF (Dual-Tone Multi-Frequency) permiten controlar tu nodo SVXLink mediante tonos enviados 
                                    desde un radio transmisor o desde esta interfaz web.
                                </p>
                                <p>
                                    <strong>Requisitos:</strong>
                                </p>
                                <ul>
                                    <li>La opción <code>DTMF_CTRL_PTY</code> debe estar habilitada en la configuración de SVXLink</li>
                                    <li>El dispositivo PTY debe existir y tener permisos de lectura/escritura</li>
                                    <li>El servicio SVXLink debe estar ejecutándose correctamente</li>
                                </ul>
                                <p>
                                    <strong>Uso:</strong> Ingresa el código del comando en el campo de texto y presiona "Enviar Comando". 
                                    El comando será enviado al sistema SVXLink para su procesamiento.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
