#!/bin/bash
# Script de automatización para Samba Share - ULSA Local-Hub
set -e

echo "[*] Instalando Samba..."
apt update && apt install -y samba samba-common-bin

echo "[*] Aplicando configuración /etc/samba/smb.conf..."
cp ../config/samba/smb.conf /etc/samba/smb.conf

echo "[*] Creando directorio compartido /srv/samba/hub..."
mkdir -p /srv/samba/hub
chown -R importar:importar /srv/samba/hub
chmod -R 775 /srv/samba/hub

echo "[*] Configurando usuario de Samba para 'importar'..."
(echo "1234"; echo "1234") | smbpasswd -a -s importar

echo "[*] Reiniciando y habilitando servicio Samba..."
systemctl restart smbd nmbd
systemctl enable smbd nmbd

echo "[+] Compartición Samba disponible en \\\\192.168.137.10\\hub-compartido o \\\\hub.ulsa.local\\hub-compartido"
