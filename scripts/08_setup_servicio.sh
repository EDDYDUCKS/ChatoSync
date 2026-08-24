#!/bin/bash
# Script de automatización para desplegar el demonio systemd - ChatoSync
set -e

echo "[*] Instalando dependencias del sistema y Tesseract OCR..."
apt update && apt install -y tesseract-ocr tesseract-ocr-spa python3-venv python3-full

echo "[*] Creando entorno virtual aislado de Python en /opt/chatosync-venv..."
python3 -m venv /opt/chatosync-venv

echo "[*] Instalando librerías Python en el entorno virtual..."
/opt/chatosync-venv/bin/pip install --upgrade pip
/opt/chatosync-venv/bin/pip install pytesseract google-api-python-client google-auth-httplib2 google-auth-oauthlib pillow

echo "[*] Copiando script principal procesar_horario.py a /srv/samba/hub/..."
mkdir -p /srv/samba/hub
cp ../src/procesar_horario.py /srv/samba/hub/procesar_horario.py
chmod +x /srv/samba/hub/procesar_horario.py

echo "[*] Copiando unit file de systemd a /etc/systemd/system/..."
cp ../systemd/chatosync.service /etc/systemd/system/chatosync.service

echo "[*] Recargando demonio systemd y activando servicio..."
systemctl daemon-reload
systemctl enable chatosync.service
systemctl restart chatosync.service

echo "[+] Servicio ChatoSync activado en segundo plano. Logs en /var/log/chatosync.log"
