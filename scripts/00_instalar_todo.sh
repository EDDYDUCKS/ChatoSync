#!/bin/bash
# Master Installer - ULSA Local-Hub
# Ejecutar como usuario root en Debian 13
set -e

echo "========================================================"
echo "    INSTALADOR MAESTRO DE ULSA LOCAL-HUB (Debian 13)    "
echo "========================================================"

chmod +x *.sh

echo "[1/6] Configurando DNS BIND9..."
./02_setup_bind9.sh

echo "[2/6] Configurando Servidor de Correo Postfix + Dovecot..."
./03_setup_correo.sh

echo "[3/6] Configurando Servidor Samba..."
./04_setup_samba.sh

echo "[4/6] Configurando Stack LAMP + Nextcloud..."
./05_setup_nextcloud.sh

echo "[5/6] Configurando Servidor de Impresión CUPS-PDF..."
./06_setup_cups.sh

echo "[6/6] Desplegando Servicio de OCR y Google Calendar Daemon..."
./08_setup_servicio.sh

echo "========================================================"
echo " ¡INSTALACIÓN COMPLETA! ULSA Local-Hub está operativo. "
echo "========================================================"
