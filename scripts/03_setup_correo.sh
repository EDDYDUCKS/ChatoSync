#!/bin/bash
# Script de automatización para Postfix + Dovecot - ULSA Local-Hub
set -e

echo "[*] Instalando Postfix, Dovecot IMAP/POP3..."
DEBIAN_FRONTEND=noninteractive apt install -y postfix dovecot-imapd dovecot-pop3d

echo "[*] Aplicando configuración de Postfix..."
cp ../config/postfix/main.cf /etc/postfix/main.cf

echo "[*] Aplicando configuración de Dovecot..."
cp ../config/dovecot/10-mail.conf /etc/dovecot/conf.d/10-mail.conf
cp ../config/dovecot/10-auth.conf /etc/dovecot/conf.d/10-auth.conf

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
systemctl restart postfix dovecot
systemctl enable postfix dovecot

echo "[+] Servidor de correo configurado para importar@ulsa.local."
