<?php
$icsFile = '/srv/samba/hub/horario_ulsa.ics';

if (!file_exists($icsFile)) {
    // Si no existe, ejecutar generación con python
    shell_exec("/opt/chatosync-venv/bin/python /srv/samba/hub/procesar_horario.py --file /root/ChatoSync/samples/horario_muestra.png >/dev/null 2>&1");
}

if (file_exists($icsFile)) {
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="Horario_ULSA_ChatoSync.ics"');
    header('Content-Length: ' . filesize($icsFile));
    readfile($icsFile);
    exit;
} else {
    echo "Archivo de calendario no generado aún. Por favor sube un horario primero.";
}
