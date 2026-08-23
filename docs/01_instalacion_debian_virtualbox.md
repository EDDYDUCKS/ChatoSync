# Guía 01: Instalación de VirtualBox y Debian 13 (VM Server)

Esta guía detalla el procedimiento para crear y configurar la máquina virtual con **Debian 13 (Bookworm/Trixie)** que servirá como el servidor de borde **ULSA Local-Hub**.

---

## 1. Requisitos Previos en la Laptop (Windows)

1. **Oracle VM VirtualBox**: Tener instalado VirtualBox 7.x ([Descargar de virtualbox.org](https://www.virtualbox.org/)).
2. **ISO de Debian 13**: Tener la ISO oficial de instalación de Debian Netinst o Standard (`debian-13-netinst-amd64.iso`).
3. **Punto de Acceso Inalámbrico Activo**:
   - Abrir **Configuración de Windows** → **Red e Internet** → **Zona de cobertura inalámbrica móvil (Hotspot)**.
   - Nombre de red: `ULSA-Hub`
   - Contraseña: *(una clave simple para las pruebas, ej. `ulsa123456`)*
   - Banda de red: `2.4 GHz` o `5 GHz` (se recomienda 2.4 GHz para máxima compatibilidad con teléfonos).
   - Encender el Hotspot.

---

## 2. Creación de la Máquina Virtual en VirtualBox

1. Abrir VirtualBox y hacer clic en **Nueva**.
2. **Nombre y Sistema Operativo**:
   - Nombre: `Debian13-ULSA-Hub`
   - Carpeta: Predeterminada
   - Imagen ISO: Seleccionar el archivo `.iso` de Debian 13.
   - Tipo: `Linux`
   - Versión: `Debian (64-bit)`
3. **Hardware**:
   - Memoria base (RAM): `2048 MB` (2 GB)
   - Procesadores (vCPU): `2`
4. **Disco Duro Virtual**:
   - Crear un disco duro virtual ahora.
   - Tamaño: `20 GB` (dinámicamente reservado).
5. Hacer clic en **Terminar**.

---

## 3. Configuración de Red (CRÍTICO: Adaptador Puente)

> [!IMPORTANT]
> Para que los teléfonos conectados al Hotspot de Windows puedan comunicarse directamente con la máquina virtual Debian, el adaptador de red de la VM **DEBE** estar en modo **Puente (Bridged)** apuntando al adaptador del Hotspot.

1. Seleccionar la VM `Debian13-ULSA-Hub` y hacer clic en **Configuración**.
2. Ir a la sección **Red** → **Adaptador 1**:
   - Marcar: **Habilitar adaptador de red**
   - Conectado a: **Adaptador puente (Bridged Adapter)**
   - Nombre: Seleccionar el adaptador virtual del Hotspot de Windows (suele llamarse **Microsoft Wi-Fi Direct Virtual Adapter** o la tarjeta de red Wi-Fi de tu laptop).
   - Modo promiscuo: `Permitir todo`
3. Hacer clic en **Aceptar**.

---

## 4. Instalación de Debian 13 paso a paso

1. Iniciar la máquina virtual.
2. En el menú de arranque de Debian, seleccionar **Install** (Instalación en modo texto) o **Graphical Install**.
3. **Idioma / Ubicación / Teclado**:
   - Idioma: `Spanish - Español`
   - Ubicación: `Nicaragua`
   - Teclado: `Latinoamericano`
4. **Configuración de Red**:
   - Nombre de host: `servidor`
   - Nombre de dominio: `ulsa.local`
5. **Usuarios y Contraseñas**:
   - Contraseña de `root`: `ulsa2026` (o la de tu preferencia)
   - Nombre completo de usuario: `Usuario ULSA`
   - Nombre de usuario: `importar` *(Este usuario será el buzón y propietario de Samba)*
   - Contraseña para `importar`: `1234`
6. **Particionado de Disco**:
   - Guiado - utilizar todo el disco.
   - Todos los ficheros en una sola partición (recomendado para novatos).
   - Finalizar el particionado y escribir los cambios en el disco (`Sí`).
7. **Selección de Programas (Software Selection)**:
   - ❌ **Desmarcar** todos los entornos de escritorio (GNOME, XFCE, etc.) para mantener el servidor ligero.
   - ✅ **Marcar**: `SSH server`
   - ✅ **Marcar**: `utilidades estándar del sistema`
8. **Cargador de arranque GRUB**:
   - Instalar el cargador de arranque GRUB en el unidad principal (`/dev/sda`).
9. **Finalizar instalación**: Reiniciar la VM y retirar la ISO virtual.

---

## 5. Configuración de IP Estática en Debian 13

Una vez iniciado Debian 13 e iniciado sesión como `root`:

1. Editar la configuración de interfaces de red:
```bash
nano /etc/network/interfaces
```

2. Modificar la interfaz primaria (ej. `eth0` o `enp0s3`) para asignar la IP fija `192.168.137.10`:
```text
auto enp0s3
iface enp0s3 inet static
    address 192.168.137.10
    netmask 255.255.255.0
    gateway 192.168.137.1
    dns-nameservers 127.0.0.1 192.168.137.1
```

3. Reiniciar el servicio de red o la máquina virtual:
```bash
systemctl restart networking
# o simplemente:
reboot
```

4. Verificar conectividad desde Windows (`cmd` o `PowerShell`):
```powershell
ping 192.168.137.10
```
Si responde el ping, ¡la Fase 1 está 100% completada!
