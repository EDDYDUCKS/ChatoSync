#!/bin/bash
# Script de automatización para desplegar el demonio systemd - ChatoSync
set -e

echo "[*] Instalando dependencias de Python y Tesseract OCR..."
apt update && apt install -y tesseract-ocr tesseract-ocr-spa python3-pytesseract python3-pillow python3-requests python3-pip

echo "[*] Copiando script principal procesar_horario.py a /srv/samba/hub/..."
cp ../src/procesar_horario.py /srv/samba/hub/procesar_horario.py
chmod +x /srv/samba/hub/procesar_horario.py

echo "[*] Copiando unit file de systemd a /etc/systemd/system/..."
cp ../systemd/chatosync.service /etc/systemd/system/chatosync.service

echo "[*] Recargando demonio systemd y activando servicio..."
systemctl daemon-reload
systemctl enable chatosync.service
systemctl restart chatosync.service

echo "[+] Servicio ChatoSync activado en segundo plano. Logs en /var/log/chatosync.log"
