<?php
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function checkService($service) {
    $out = shell_exec("systemctl is-active " . escapeshellarg($service) . " 2>/dev/null");
    return trim($out) === 'active';
}

if ($action === 'status') {
    $services = [
        'dns' => ['name' => 'DNS BIND9 (ulsa.local)', 'active' => checkService('named')],
        'mail_smtp' => ['name' => 'Correo SMTP (Postfix)', 'active' => checkService('postfix')],
        'mail_imap' => ['name' => 'Correo IMAP/POP3 (Dovecot)', 'active' => checkService('dovecot')],
        'samba' => ['name' => 'Archivos Compartidos (Samba)', 'active' => checkService('smbd')],
        'web' => ['name' => 'Servidor Web & Nextcloud (Apache)', 'active' => checkService('apache2')],
        'cups' => ['name' => 'Servidor Impresión CUPS-PDF', 'active' => checkService('cups')],
        'ocr' => ['name' => 'Motor OCR ChatoSync', 'active' => checkService('chatosync')],
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
    $lines = 35;
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

echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
