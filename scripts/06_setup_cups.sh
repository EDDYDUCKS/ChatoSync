#!/bin/bash
# Script de automatización para CUPS + CUPS-PDF - ULSA Local-Hub
set -e

echo "[*] Instalando CUPS, CUPS-PDF y LibreOffice Headless..."
apt update && apt install -y cups cups-pdf libreoffice-writer libreoffice-impress

echo "[*] Aplicando configuración de /etc/cups/cupsd.conf..."
cp ../config/cups/cupsd.conf /etc/cups/cupsd.conf

echo "[*] Reiniciando servicio CUPS..."
systemctl restart cups
systemctl enable cups

echo "[*] Creando e instalando impresora virtual Impresora_PDF..."
lpadmin -p Impresora_PDF -E -v cups-pdf:/ -m remaining || lpadmin -p Impresora_PDF -E -v cups-pdf:/

echo "[+] Impresora virtual CUPS-PDF configurada correctamente."
