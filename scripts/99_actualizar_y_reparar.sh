#!/bin/bash
# Script de actualización, permisos y reparación integral para ChatoSync
set -e

echo "[*] Creando y asegurando directorios de trabajo..."
mkdir -p /srv/samba/hub/entrada
mkdir -p /srv/samba/hub/procesados
mkdir -p /srv/samba/hub/samples
mkdir -p /var/www/html/samples

echo "[*] Copiando archivos de muestra..."
cp ../samples/horario_muestra.png /var/www/html/samples/ 2>/dev/null || true
cp ../samples/horario_muestra.png /srv/samba/hub/samples/ 2>/dev/null || true

echo "[*] Copiando script principal de Python a /srv/samba/hub/..."
cp ../src/procesar_horario.py /srv/samba/hub/procesar_horario.py
chmod +x /srv/samba/hub/procesar_horario.py

echo "[*] Desplegando interfaz web a /var/www/html/..."
cp -r ../web/* /var/www/html/
rm -f /var/www/html/index.html

echo "[*] Ajustando permisos totales para Apache (www-data) y Samba..."
touch /var/log/chatosync.log
chmod 666 /var/log/chatosync.log
chmod -R 777 /srv/samba/hub
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

echo "[*] Reiniciando servicios del sistema..."
systemctl daemon-reload
systemctl restart chatosync.service smbd apache2

echo ""
echo "[*] Ejecutando prueba de diagnóstico OCR en vivo..."
/opt/chatosync-venv/bin/python /srv/samba/hub/procesar_horario.py --file /srv/samba/hub/samples/horario_muestra.png

echo ""
echo "[+] ==========================================================="
echo "[+] ¡SISTEMA ACTUALIZADO Y REPARADO AL 100%!"
echo "[+] Abre en tu navegador: http://192.168.137.102/"
echo "[+] Abre en Windows: \\\\192.168.137.102\\hub"
echo "[+] ==========================================================="
