<?php
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function checkService($service) {
    $out = shell_exec("systemctl is-active " . escapeshellarg($service) . " 2>/dev/null");
    return trim($out) === 'active';
}

function fmtSize($b){if($b>=1073741824)return round($b/1073741824,1).'GB';if($b>=1048576)return round($b/1048576,1).'MB';if($b>=1024)return round($b/1024,1).'KB';return $b.'B';}
function fmtIcon($ext){$m=['pdf'=>'📄','doc'=>'📝','docx'=>'📝','zip'=>'🗜️','rar'=>'🗜️','mp4'=>'🎬','avi'=>'🎬','mp3'=>'🎵','jpg'=>'🖼️','jpeg'=>'🖼️','png'=>'🖼️','apk'=>'📱','ova'=>'💻','exe'=>'⚙️'];return $m[$ext]??'📁';}

// ─── Estado de Servicios ──────────────────────────────────────────────────────
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

// ─── Archivos en Tiempo Real (AJAX Polling) ───────────────────────────────────
if ($action === 'files') {
    $hubDir = "/srv/samba/hub/";
    $SYSTEM_FILES = ['ultimo_horario.json','horario_ulsa.ics','procesar_horario.py','chatosync.service','index.php','api.php','transfer.php','download.php'];
    $SYSTEM_EXTS  = ['php','py','sh','json','ics','log','conf','service','bak'];
    $previewableExts = ['pdf','png','jpg','jpeg','gif','webp','svg','mp4','webm','mp3','wav','ogg','txt','log','json','py','sh','md','csv'];

    $files = [];
    if (is_dir($hubDir)) {
        foreach (scandir($hubDir) as $f) {
            if ($f === '.' || $f === '..' || is_dir($hubDir.$f)) continue;
            if (str_starts_with($f, '.')) continue;
            if (in_array($f, $SYSTEM_FILES)) continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, $SYSTEM_EXTS)) continue;
            $sz = filesize($hubDir.$f);
            $files[] = [
                'name' => $f,
                'size' => $sz,
                'size_formatted' => fmtSize($sz),
                'date' => filemtime($hubDir.$f),
                'date_formatted' => date('d/m H:i', filemtime($hubDir.$f)),
                'ext' => $ext,
                'icon' => fmtIcon($ext),
                'can_preview' => in_array($ext, $previewableExts)
            ];
        }
        usort($files, fn($a,$b)=>$b['date']-$a['date']);
    }
    
    $totalSize = array_sum(array_column($files, 'size'));
    echo json_encode([
        'status' => 'ok',
        'count' => count($files),
        'total_size' => $totalSize,
        'total_size_formatted' => fmtSize($totalSize),
        'files' => $files
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Logs del Sistema ─────────────────────────────────────────────────────────
if ($action === 'logs') {
    $lines = 40;
    $logFile = '/var/log/chatosync.log';
    $logs = file_exists($logFile) ? shell_exec("tail -n $lines $logFile 2>/dev/null") : "No se encontró archivo de log.";
    echo json_encode(['status' => 'ok', 'logs' => $logs]);
    exit;
}

// ─── Subida y Procesamiento OCR (Auto-Limpieza Inmediata de Memoria) ───────────
if ($action === 'upload') {
    if (!isset($_FILES['horario']) || $_FILES['horario']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Error al subir archivo desde el navegador.']);
        exit;
    }
    
    $tmpName = $_FILES['horario']['tmp_name'];
    $origExt = strtolower(pathinfo($_FILES['horario']['name'], PATHINFO_EXTENSION)) ?: 'png';
    // Guardar temporalmente en /tmp (RAM/disco temporal)
    $tempPath = "/tmp/ocr_schedule_" . time() . "_" . mt_rand(1000, 9999) . "." . $origExt;
    
    if (move_uploaded_file($tmpName, $tempPath)) {
        @chmod($tempPath, 0777);
        // Buscar el script procesar_horario.py más reciente
        $scriptPath = "/srv/samba/hub/procesar_horario.py";
        if (!file_exists($scriptPath) || filesize($scriptPath) < 500) {
            $scriptPath = "/var/www/html/procesar_horario.py";
        }
        
        $cmd = "/opt/chatosync-venv/bin/python " . escapeshellarg($scriptPath) . " --file " . escapeshellarg($tempPath) . " 2>&1";
        $out = shell_exec($cmd);
        
        if (file_exists($tempPath)) { @unlink($tempPath); }
        
        $clases = [];
        if ($out) {
            $jsonStart = strpos($out, '[');
            $jsonEnd = strrpos($out, ']');
            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                $rawJson = substr($out, $jsonStart, $jsonEnd - $jsonStart + 1);
                $clases = json_decode($rawJson, true) ?: [];
            }
        }
        
        echo json_encode([
            'status' => 'ok',
            'message' => 'Horario procesado exitosamente.',
            'count' => count($clases),
            'clases' => $clases,
            'debug' => substr($out, 0, 500)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo procesar la imagen temporal.']);
    }
    exit;
}

// ─── Muestra de Prueba ────────────────────────────────────────────────────────
if ($action === 'test_sample') {
    $samples = [
        "/var/www/html/samples/horario_muestra.png",
        "/srv/samba/hub/samples/horario_muestra.png",
        "/root/ChatoSync/samples/horario_muestra.png",
        "/home/chatosync/ChatoSync/samples/horario_muestra.png"
    ];
    
    $sampleFile = null;
    foreach ($samples as $s) {
        if (file_exists($s)) { $sampleFile = $s; break; }
    }
    
    if (!$sampleFile) {
        echo json_encode(['status' => 'error', 'message' => 'No se encontró archivo de muestra en el servidor.']);
        exit;
    }
    
    $tempDest = "/tmp/sample_ocr_" . time() . ".png";
    @copy($sampleFile, $tempDest);
    
    $cmd = "/opt/chatosync-venv/bin/python /srv/samba/hub/procesar_horario.py --file " . escapeshellarg($tempDest) . " 2>&1";
    $out = shell_exec($cmd);

    
    if (file_exists($tempDest)) { @unlink($tempDest); }
    
    $res = json_decode($out, true);
    if (!$res && preg_match('/\[.*\]/s', $out, $matches)) {
        $res = json_decode($matches[0], true);
    }
    
    echo json_encode([
        'status' => 'ok',
        'message' => 'Horario de muestra procesado.',
        'clases' => is_array($res) ? $res : []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Generación y Exportación de .ICS para Google Calendar ────────────────────
if ($action === 'export_ics') {
    $postBody = file_get_contents('php://input');
    $clases = json_decode($postBody, true);
    
    if (!is_array($clases) || empty($clases)) {
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
        $ics[] = "UID:ulsa-{$cod}-{$dia}-{$i}-" . time() . "@chatosync.ulsa.local";
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
    
    // Guardar temporalmente para descarga directa
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
