#!/bin/bash
# Script de automatización LAMP + Nextcloud manual - ULSA Local-Hub
set -e

echo "[*] Instalando Apache2, MariaDB y PHP 8.2 con extensiones requeridas..."
apt update && apt install -y apache2 mariadb-server \
  php php-curl php-cli php-mysql php-gd php-common php-xml php-json php-intl php-mbstring php-zip php-bz2 php-gmp php-bcmath unzip wget

echo "[*] Configurando base de datos MariaDB para Nextcloud..."
mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS nextcloud CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
GRANT ALL PRIVILEGES ON nextcloud.* TO 'nextclouduser'@'localhost' IDENTIFIED BY 'ulsa2026';
FLUSH PRIVILEGES;
EOF

echo "[*] Descargando e instalando Nextcloud Latest..."
cd /tmp
wget -q https://download.nextcloud.com/server/releases/latest.zip
unzip -q latest.zip
rm -rf /var/www/html/nextcloud
mv nextcloud /var/www/html/

echo "[*] Asignando permisos de Apache (www-data)..."
chown -R www-data:www-data /var/www/html/nextcloud
chmod -R 755 /var/www/html/nextcloud

echo "[*] Habilitando módulos de Apache..."
a2enmod rewrite headers env dir mime

echo "[*] Reiniciando Apache2..."
systemctl restart apache2 mariadb
systemctl enable apache2 mariadb

echo "[+] Nextcloud instalado en http://192.168.137.10/nextcloud o http://hub.ulsa.local/nextcloud"
