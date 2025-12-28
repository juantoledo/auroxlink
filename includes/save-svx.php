<?php
$archivo = "/etc/svxlink/svxlink.conf";

// Recibe todos los datos enviados desde configuracion-svx.php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $lineas = file($archivo);
    $nuevas_lineas = [];
    $bloque_actual = null;
    $dtmf_ctrl_pty_procesado = false;

    foreach ($lineas as $linea) {
        $modificado = false;
        $linea_trim = trim($linea);

        // Detectar bloques/secciones
        if (preg_match('/^\[(.*)\]/', $linea_trim, $match)) {
            $bloque_actual = $match[1];
        }

        // Detectar si es una línea tipo CLAVE=VALOR (sin importar espacios) o comentada
        if (preg_match('/^#?\s*([A-Z0-9_]+)\s*=\s*(.*)$/i', $linea_trim, $match)) {
            $clave = $match[1];

            // Manejo especial para DTMF_CTRL_PTY
            if ($clave === "DTMF_CTRL_PTY" && $bloque_actual === "SimplexLogic") {
                $dtmf_ctrl_pty_procesado = true;
                $enabled = isset($_POST['DTMF_CTRL_PTY_enabled']) && $_POST['DTMF_CTRL_PTY_enabled'] == '1';
                $valor = $_POST['DTMF_CTRL_PTY'] ?? '/dev/shm/dtmf_ctrl';
                
                if ($enabled) {
                    $nuevas_lineas[] = "DTMF_CTRL_PTY=$valor\n";
                } else {
                    $nuevas_lineas[] = "#DTMF_CTRL_PTY=$valor\n";
                }
                $modificado = true;
            }
            // Procesamiento normal para otras claves
            else {
                foreach ($_POST as $nombre => $valor) {
                    if (strcasecmp($nombre, $clave) === 0 && !str_ends_with($nombre, '_enabled')) {
                        $nuevas_lineas[] = "$clave=$valor\n";
                        $modificado = true;
                        break;
                    }
                }
            }
        }

        if (!$modificado) {
            $nuevas_lineas[] = $linea;
        }
    }

    // Si DTMF_CTRL_PTY no existía en el archivo y está habilitado, agregarlo
    if (!$dtmf_ctrl_pty_procesado && isset($_POST['DTMF_CTRL_PTY_enabled']) && $_POST['DTMF_CTRL_PTY_enabled'] == '1') {
        $valor = $_POST['DTMF_CTRL_PTY'] ?? '/dev/shm/dtmf_ctrl';
        // Buscar la sección [SimplexLogic] y agregar después
        $nuevas_lineas_final = [];
        $agregado = false;
        foreach ($nuevas_lineas as $i => $linea) {
            $nuevas_lineas_final[] = $linea;
            if (!$agregado && preg_match('/^\[SimplexLogic\]/i', trim($linea))) {
                // Agregar después de encontrar otra clave en esta sección o antes de la siguiente sección
                $j = $i + 1;
                while ($j < count($nuevas_lineas) && !preg_match('/^\[/', trim($nuevas_lineas[$j]))) {
                    $nuevas_lineas_final[] = $nuevas_lineas[$j];
                    $j++;
                }
                $nuevas_lineas_final[] = "DTMF_CTRL_PTY=$valor\n";
                $agregado = true;
                // Continuar con el resto
                while ($j < count($nuevas_lineas)) {
                    $nuevas_lineas_final[] = $nuevas_lineas[$j];
                    $j++;
                }
                break;
            }
        }
        if ($agregado) {
            $nuevas_lineas = $nuevas_lineas_final;
        }
    }

    // Guardar el archivo
    file_put_contents($archivo, implode("", $nuevas_lineas));

    // Reiniciar servicio
    shell_exec("sudo systemctl restart svxlink");

    // Redirigir
    echo "<div style='padding:20px;font-family:sans-serif;'>✅ Cambios guardados, servicio reiniciado.<br><a href='../settings.php'>Volver a configuración de SVXLink</a></div>";
    exit;
}
?>
