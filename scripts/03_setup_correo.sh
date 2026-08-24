#!/bin/bash
# Script de automatización para Postfix + Dovecot - ChatoSync
set -e

echo "[*] Instalando Postfix, Dovecot IMAP/POP3..."
DEBIAN_FRONTEND=noninteractive apt install -y postfix dovecot-imapd dovecot-pop3d

echo "[*] Aplicando configuración de Postfix..."
cp ../config/postfix/main.cf /etc/postfix/main.cf

echo "[*] Aplicando configuración de override para Dovecot (/etc/dovecot/conf.d/99-chatosync.conf)..."
cat << 'EOF' > /etc/dovecot/conf.d/99-chatosync.conf
mail_location = maildir:~/Maildir
disable_plaintext_auth = no
auth_mechanisms = plain login
EOF

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

echo "[+] Servidor de correo configurado para importar@ulsa.local."
