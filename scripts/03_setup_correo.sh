#!/bin/bash
# Script de automatización para Postfix + Dovecot - ChatoSync
# Compatible con Dovecot 2.4.x (Debian 13 Trixie)
set -e

echo "[*] Instalando Postfix..."
DEBIAN_FRONTEND=noninteractive apt install -y postfix

echo "[*] Aplicando configuración de Postfix..."
cp ../config/postfix/main.cf /etc/postfix/main.cf

echo "[*] Realizando purge completo de Dovecot para instalación limpia..."
DEBIAN_FRONTEND=noninteractive apt purge -y dovecot-core dovecot-imapd dovecot-pop3d 2>/dev/null || true

echo "[*] Instalando Dovecot fresco..."
DEBIAN_FRONTEND=noninteractive apt install -y dovecot-imapd dovecot-pop3d

echo "[*] Aplicando configuración ChatoSync para Dovecot 2.4..."
# Dovecot 2.4: mail_location global fue eliminado; usar namespace inbox
# Dovecot 2.4: disable_plaintext_auth fue renombrado a auth_allow_cleartext
cp ../config/dovecot/99-chatosync.conf /etc/dovecot/conf.d/99-chatosync.conf

echo "[*] Creando usuario receptor 'importar' si no existe..."
if ! id "importar" &>/dev/null; then
    useradd -m -s /bin/bash importar
    echo "importar:1234" | chpasswd
    echo "[+] Usuario 'importar' creado con contraseña '1234'."
fi

echo "[*] Preparando directorio Maildir..."
mkdir -p /home/importar/Maildir/{new,cur,tmp}
chown -R importar:importar /home/importar/Maildir
chmod -R 700 /home/importar/Maildir

echo "[*] Reiniciando y habilitando servicios de correo..."
systemctl restart postfix
systemctl restart dovecot
systemctl enable postfix dovecot

echo "[+] Servidor de correo configurado correctamente para importar@ulsa.local."
