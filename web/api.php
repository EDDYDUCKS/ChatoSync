<?php
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function checkService($service) {
    $out = shell_exec("systemctl is-active " . escapeshellarg($service) . " 2>/dev/null");
    return trim($out) === 'active';
}

if ($action === 'status') {
    $services = [
        'dns'   => ['name' => 'DNS BIND9 (ulsa.local)', 'active' => checkService('named')],
        'web'   => ['name' => 'Servidor Web & Nextcloud (Apache)', 'active' => checkService('apache2')],
        'samba' => ['name' => 'Archivos Compartidos (Samba)', 'active' => checkService('smbd')],
        'cups'  => ['name' => 'Servidor Impresión CUPS-PDF', 'active' => checkService('cups')],
        'ocr'   => ['name' => 'Motor OCR ChatoSync', 'active' => checkService('chatosync')],
    ];
    
    $ip = trim(shell_exec("hostname -I | awk '{print $1}'") ?? '192.168.137.102');
    $uptime = trim(shell_exec("uptime -p 2>/dev/null") ?? 'Activo');
    
    echo json_encode([
        'status' => 'ok',
        'ip' => $ip,
        'uptime' => $uptime,
        'services' => $services
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'logs') {
    $lines = 40;
    $logFile = '/var/log/chatosync.log';
    if (file_exists($logFile)) {
        $logs = shell_exec("tail -n $lines $logFile 2>/dev/null");
    } else {
        $logs = "No se encontró archivo de log.";
    }
    echo json_encode(['status' => 'ok', 'logs' => $logs]);
    exit;
}

if ($action === 'last_data') {
    $jsonFile = '/srv/samba/hub/ultimo_horario.json';
    if (file_exists($jsonFile)) {
        $content = file_get_contents($jsonFile);
        $data = json_decode($content, true);
        echo json_encode(['status' => 'ok', 'data' => $data], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => 'empty', 'message' => 'Aún no se ha procesado ningún horario.']);
    }
    exit;
}

if ($action === 'upload') {
    if (!isset($_FILES['horario']) || $_FILES['horario']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Error al subir archivo desde el navegador.']);
        exit;
    }
    
    $uploadDir = "/srv/samba/hub/entrada/";
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    @chmod($uploadDir, 0777);
    
    $tmpName = $_FILES['horario']['tmp_name'];
    $origName = basename($_FILES['horario']['name']);
    $dest = $uploadDir . time() . "_" . $origName;
    
    if (move_uploaded_file($tmpName, $dest)) {
        @chmod($dest, 0777);
        // Ejecutar procesamiento con Python
        $cmd = "/opt/chatosync-venv/bin/python /srv/samba/hub/procesar_horario.py --file " . escapeshellarg($dest);
        $out = shell_exec($cmd);
        
        // Extraer JSON limpio del output
        $res = json_decode($out, true);
        if (!$res && preg_match('/\[.*\]/s', $out, $matches)) {
            $res = json_decode($matches[0], true);
        }
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Horario procesado exitosamente.',
            'clases' => is_array($res) ? $res : [],
            'raw_output' => $out
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo en la carpeta del servidor.']);
    }
    exit;
}

if ($action === 'test_sample') {
    $samples = [
        "/var/www/html/samples/horario_muestra.png",
        "/srv/samba/hub/samples/horario_muestra.png",
        "/root/ChatoSync/samples/horario_muestra.png",
        "/home/chatosync/ChatoSync/samples/horario_muestra.png"
    ];
    
    $sampleFile = null;
    foreach ($samples as $s) {
        if (file_exists($s)) {
            $sampleFile = $s;
            break;
        }
    }
    
    if (!$sampleFile) {
        echo json_encode(['status' => 'error', 'message' => 'No se encontró archivo de muestra en el servidor.']);
        exit;
    }
    
    $dest = "/srv/samba/hub/entrada/horario_muestra_" . time() . ".png";
    @mkdir(dirname($dest), 0777, true);
    @copy($sampleFile, $dest);
    @chmod($dest, 0777);
    
    $cmd = "/opt/chatosync-venv/bin/python /srv/samba/hub/procesar_horario.py --file " . escapeshellarg($dest);
    $out = shell_exec($cmd);
    
    $res = json_decode($out, true);
    if (!$res && preg_match('/\[.*\]/s', $out, $matches)) {
        $res = json_decode($matches[0], true);
    }
    
    echo json_encode([
        'status' => 'ok',
        'message' => 'Horario de muestra procesado con éxito.',
        'clases' => is_array($res) ? $res : []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'export_ics') {
    // Generación dinámica de .ics a partir de JSON enviado por el cliente
    $postBody = file_get_contents('php://input');
    $clases = json_decode($postBody, true);
    
    if (!is_array($clases) || empty($clases)) {
        // Fallback: leer del archivo último
        $lastJson = "/srv/samba/hub/ultimo_horario.json";
        if (file_exists($lastJson)) {
            $clases = json_decode(file_get_contents($lastJson), true);
        }
    }
    
    if (empty($clases)) {
        echo json_encode(['status' => 'error', 'message' => 'No hay clases para exportar.']);
        exit;
    }
    
    $diasOffset = ["Lu" => 0, "Ma" => 1, "Mi" => 2, "Ju" => 3, "Vi" => 4, "Sa" => 5];
    $hoy = new DateTime();
    $dayOfWeek = (int)$hoy->format('N') - 1; // 0 = Lunes
    $inicioSemana = (clone $hoy)->modify("-{$dayOfWeek} days");
    $until = "20261218T235959Z";
    
    $ics = [
        "BEGIN:VCALENDAR",
        "VERSION:2.0",
        "PRODID:-//ChatoSync Hub//ULSA Horario Universitario//ES",
        "CALSCALE:GREGORIAN",
        "METHOD:PUBLISH",
        "X-WR-CALNAME:Horario de Clases ULSA",
        "X-WR-TIMEZONE:America/Managua"
    ];
    
    foreach ($clases as $i => $c) {
        $dia = $c['dia'] ?? 'Lu';
        $offset = $diasOffset[$dia] ?? 0;
        $fechaClase = (clone $inicioSemana)->modify("+{$offset} days");
        
        $hIni = trim($c['hora_inicio'] ?? '01:00 pm');
        $hFin = trim($c['hora_fin'] ?? '02:40 pm');
        
        $dtStartObj = DateTime::createFromFormat('Y-m-d h:i a', $fechaClase->format('Y-m-d') . ' ' . $hIni) ?: (clone $fechaClase);
        $dtEndObj   = DateTime::createFromFormat('Y-m-d h:i a', $fechaClase->format('Y-m-d') . ' ' . $hFin) ?: (clone $fechaClase);
        
        $dtStart = $dtStartObj->format('Ymd\THis');
        $dtEnd   = $dtEndObj->format('Ymd\THis');
        
        $cod = $c['codigo'] ?? '0000';
        $mat = $c['materia'] ?? 'Clase';
        $aula = $c['aula'] ?? 'ULSA';
        $doc = $c['docente'] ?? 'Docente Asignado';
        
        $ics[] = "BEGIN:VEVENT";
        $ics[] = "UID:ulsa-{$cod}-{$dia}-{$i}@chatosync.ulsa.local";
        $ics[] = "DTSTAMP:" . gmdate('Ymd\THis\Z');
        $ics[] = "DTSTART:{$dtStart}";
        $ics[] = "DTEND:{$dtEnd}";
        $ics[] = "RRULE:FREQ=WEEKLY;UNTIL={$until}";
        $ics[] = "SUMMARY:[{$cod}] {$mat}";
        $ics[] = "LOCATION:Aula {$aula}";
        $ics[] = "DESCRIPTION:Docente: {$doc}\\nAula: {$aula}\\nGenerado por ChatoSync Hub";
        $ics[] = "STATUS:CONFIRMED";
        $ics[] = "BEGIN:VALARM";
        $ics[] = "ACTION:DISPLAY";
        $ics[] = "DESCRIPTION:Recordatorio de Clase ULSA";
        $ics[] = "TRIGGER:-PT15M";
        $ics[] = "END:VALARM";
        $ics[] = "END:VEVENT";
    }
    
    $ics[] = "END:VCALENDAR";
    $icsContent = implode("\r\n", $ics) . "\r\n";
    
    // Guardar en el hub para descarga estándar
    @file_put_contents("/srv/samba/hub/horario_ulsa.ics", $icsContent);
    @chmod("/srv/samba/hub/horario_ulsa.ics", 0777);
    
    echo json_encode([
        'status' => 'ok',
        'message' => 'Calendario generado exitosamente.',
        'ics_url' => '/download.php?file=horario_ulsa.ics',
        'classes_count' => count($clases)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
