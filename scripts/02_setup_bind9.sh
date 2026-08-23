#!/bin/bash
# Script de automatización para BIND9 - ULSA Local-Hub
set -e

echo "[*] Instalando BIND9 y utilidades DNS..."
apt update && apt install -y bind9 bind9utils bind9-doc

echo "[*] Copiando archivos de configuración de zona..."
cp ../config/bind9/named.conf.local /etc/bind/named.conf.local
cp ../config/bind9/db.ulsa.local /etc/bind/db.ulsa.local
cp ../config/bind9/db.192.168.137 /etc/bind/db.192.168.137

echo "[*] Verificando sintaxis de configuración BIND9..."
named-checkconf
named-checkzone ulsa.local /etc/bind/db.ulsa.local
named-checkzone 137.168.192.in-addr.arpa /etc/bind/db.192.168.137

echo "[*] Reiniciando y habilitando servicio BIND9..."
systemctl restart bind9
systemctl enable bind9

echo "[+] BIND9 instalado y configurado correctamente para ulsa.local (192.168.137.10)."
