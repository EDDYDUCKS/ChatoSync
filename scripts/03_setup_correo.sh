#!/bin/bash
# Script de automatización para Postfix + Dovecot - ChatoSync
# Compatible con Dovecot 2.4.x (Debian 13 Trixie)
set -e

echo "[*] Instalando Postfix y Dovecot IMAP/POP3..."
DEBIAN_FRONTEND=noninteractive apt install -y postfix dovecot-imapd dovecot-pop3d

echo "[*] Aplicando configuración de Postfix..."
cp ../config/postfix/main.cf /etc/postfix/main.cf

echo "[*] Eliminando archivos de configuración rotos de runs anteriores..."
rm -f /etc/dovecot/conf.d/10-auth.conf
rm -f /etc/dovecot/conf.d/10-mail.conf
rm -f /etc/dovecot/conf.d/99-chatosync.conf

echo "[*] Restaurando archivos originales de Dovecot..."
DEBIAN_FRONTEND=noninteractive apt install --reinstall \
    -o Dpkg::Options::="--force-confmiss" \
    -y dovecot-core dovecot-imapd dovecot-pop3d 2>/dev/null || true

echo "[*] Aplicando configuración personalizada de ChatoSync (Dovecot 2.4 syntax)..."
# En Dovecot 2.4 'disable_plaintext_auth' fue reemplazado por 'auth_allow_cleartext'
printf "auth_allow_cleartext = yes\nmail_location = maildir:~/Maildir\n" \
    > /etc/dovecot/conf.d/99-chatosync.conf

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
