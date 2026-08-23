# 🚀 ChatoSync: Plataforma Autónoma de Sincronización de Archivos y Automatización de Horarios (ULSA Edge-Hub)

[![GitHub Repo](https://img.shields.io/badge/GitHub-ChatoSync-blue?logo=github)](https://github.com/EDDYDUCKS/ChatoSync.git)

**Asignatura:** Taller de Conectividad (IV Año, Ingeniería en Cibernética Electrónica)  
**Docente:** Ing. Freddy Alexander Mejía Quintana  
**Estudiante:** Eddy Ezequiel Martínez Solórzano  
**Institución:** Universidad Tecnológica La Salle (ULSA), León, Nicaragua  

---

## 📌 Descripción General

**ChatoSync** es un servidor de conectividad portátil que corre sobre una Máquina Virtual con Debian 13 en la laptop Windows del alumno. Transforma la laptop en una **burbuja de red local privada (Edge Computing)** utilizando el Hotspot de Windows.

### Problemas que resuelve:
1. **Aislamiento y congestión WAN:** Permite compartir archivos pesados a velocidad LAN máxima vía Samba y Nextcloud sin consumir internet ni sufrir restricciones de AP Isolation.
2. **Transcripción manual de horarios:** Al enviar la foto de la hoja de inscripción de la ULSA a `importar@ulsa.local`, un motor de visión por computadora (**Tesseract OCR**) extrae dinámicamente las materias, horarios, salones y docentes, creando automáticamente los eventos en **Google Calendar** con notificaciones silenciosas emergentes de 20 minutos antes y generando un reporte PDF vectorial listo en la carpeta compartida via **CUPS-PDF**.

---

## 🗂️ Estructura del Repositorio

```
ChatoSync/
├── README.md                         # Documentación general del proyecto
├── .gitignore                        # Archivos excluidos de Git
├── ulsa-local-hub.md                 # Reporte técnico original del proyecto
├── config/                           # Archivos de configuración del servidor Debian
│   ├── bind9/                        # BIND9 DNS (ulsa.local)
│   ├── postfix/                      # Postfix MTA Mail Server
│   ├── dovecot/                      # Dovecot MDA Maildir Server
│   ├── samba/                        # Samba File Share (hub-compartido)
│   ├── cups/                         # CUPS Spooler & Virtual PDF Printer
│   └── network/                      # Configuración de IP estática (192.168.137.10)
├── scripts/                          # Scripts de instalación automatizada
│   ├── 00_instalar_todo.sh           # Instalador Maestro de 1 solo clic
│   ├── 02_setup_bind9.sh
│   ├── 03_setup_correo.sh
│   ├── 04_setup_samba.sh
│   ├── 05_setup_nextcloud.sh
│   ├── 06_setup_cups.sh
│   ├── 07_setup_google_auth.sh
│   └── 08_setup_servicio.sh
├── src/                              # Código fuente del Motor "Cerebro"
│   ├── procesar_horario.py           # Parser OCR + Google Calendar API + CUPS PDF
│   └── requirements.txt              # Dependencias de Python
├── systemd/                          # Demonio en segundo plano
│   └── chatosync.service             # Unit file de systemd
├── samples/                          # Capturas de muestra para pruebas
│   └── horario_muestra.png           # Imagen real de inscripción ULSA
└── docs/                             # Documentación y guías paso a paso
    ├── 01_instalacion_debian_virtualbox.md # Guía para crear la VM
    ├── 03_google_calendar_setup.md    # Guía para configurar Google OAuth2
    └── GUIA_DEFENSA.md                # Protocolo paso a paso para el jurado
```

---

## ⚙️ Direccionamiento de Red

| Dispositivo | Interfaz | Dirección IP | Máscara | Gateway | DNS Primario |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Windows Host (Laptop)** | Wi-Fi Direct | `192.168.137.1` | `255.255.255.0` | N/A | `192.168.137.10` |
| **Debian 13 Server (VM)** | `enp0s3` (Puente) | `192.168.137.10` | `255.255.255.0` | `192.168.137.1` | `127.0.0.1` |
| **Dispositivos Móviles** | Wi-Fi Client | DHCP | `255.255.255.0` | `192.168.137.1` | `192.168.137.10` |

---

## 🛠️ Instalación en el Servidor Debian 13

En una instalación limpia de Debian 13 iniciada como `root`:

```bash
git clone https://github.com/EDDYDUCKS/ChatoSync.git
cd ChatoSync/scripts
chmod +x *.sh
./00_instalar_todo.sh
```

---

## 📖 Documentación de la Defensa

Revisa la guía [GUIA_DEFENSA.md](docs/GUIA_DEFENSA.md) para ejecutar el protocolo de prueba ante el jurado durante el día de la exposición.
