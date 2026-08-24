#!/bin/bash
# Script de automatización para Samba - ChatoSync
set -e

echo "[*] Instalando paquetes de Samba..."
apt update && apt install -y samba samba-common-bin

echo "[*] Creando directorios para el recurso compartido..."
mkdir -p /srv/samba/hub/entrada
mkdir -p /srv/samba/hub/procesados
chmod -R 777 /srv/samba/hub

echo "[*] Aplicando configuración en /etc/samba/smb.conf..."
cp ../config/samba/smb.conf /etc/samba/smb.conf

echo "[*] Configurando usuario de Samba 'importar'..."
(echo "1234"; echo "1234") | smbpasswd -a -s importar 2>/dev/null || true

echo "[*] Reiniciando y habilitando servicio Samba..."
systemctl restart smbd nmbd
systemctl enable smbd nmbd

echo "[+] Samba configurado con éxito. Recurso disponible en: \\\\192.168.137.10\\hub"
