# DOCUMENTO TÉCNICO COMPLETO DEL PROYECTO CHATOSYNC
## Servidor de Borde (Edge Server) para Transferencia Ultrarrápida de Archivos Pesados sin Internet y Procesamiento Inteligente de Horarios Universitarios

---

## 1. INFORMACIÓN GENERAL Y ACADÉMICA

* **Nombre del Proyecto:** ChatoSync (ULSA Local-Hub Edge Server)
* **Institución:** Universidad Tecnológica La Salle (ULSA) — León, Nicaragua
* **Facultad:** Facultad de Ingeniería
* **Carrera:** Ingeniería en Cibernética Electrónica (IV Año)
* **Asignatura:** Taller de Conectividad
* **Docente Evaluador:** Ing. Freddy Alexander Mejía Quintana
* **Estudiante / Autor:** Eddy Ezequiel Martínez Solórzano
* **Correo Institucional:** eddy@ulsa.edu.ni
* **Repositorio Oficial de Código Abierto:** https://github.com/EDDYDUCKS/ChatoSync.git
* **Sistema Operativo Base:** Debian GNU/Linux 13 (Trixie) x86_64

---

## 2. RESUMEN EJECUTIVO (ABSTRACT)

**ChatoSync** es una solución integral de infraestructura de red y computación de borde (*Edge Computing*) diseñada para resolver dos desafíos críticos de conectividad y productividad académica en la Universidad Tecnológica La Salle (ULSA):

1. **Transferencia Local Ultrarrápida de Archivos Pesados y Nube Colaborativa 100% Offline:** Provisión de un centro de distribución de archivos de alta velocidad mediante **Samba (SMB/CIFS)** y una nube privada **Nextcloud (LAMP Stack)** sobre una red local inalámbrica portable. Permite a grupos de estudiantes compartir instaladores de software de ingeniería (MATLAB, AutoCAD, Proteus, IDEs), máquinas virtuales, datasets, diapositivas y proyectos de gran tamaño (de varios Gigabytes) a velocidades de enlace local Wi-Fi (hasta 300–866 Mbps) de forma instantánea, sin consumir datos móviles, sin depender de conexión a Internet y eliminando el riesgo de propagación de virus por memorias USB.
2. **Digitalización y Calendarización Inteligente con Visión por Computadora (OCR):** Motor autónomo en Python que procesa capturas o fotografías de horarios universitarios con **Tesseract OCR**, extrae dinámicamente asignaturas, códigos, aulas, horarios y docentes mediante expresiones regulares dinámicas, y genera de forma automática archivos universales de calendario (`.ics` RFC 5545) y sincronización con **Google Calendar API v3** con alertas programadas 20 minutos antes de cada clase.

---

## 3. PLANTEAMIENTO DEL PROBLEMA Y JUSTIFICACIÓN

### 3.1 Problemática de la Conectividad y Compartición de Archivos en el Campus
En las carreras de ingeniería (Cibernética Electrónica, Mecatrónica, Industrial, Sistemas), los estudiantes y docentes manejan constantemente archivos de gran volumen:
* **Saturación del Ancho de Banda de Internet:** Intentar subir o descargar instaladores de software técnico (ej. MATLAB de 4 a 12 GB, máquinas virtuales `.ova` de 5 a 15 GB, librerías de programación o datasets) a través de servicios de nube pública (Google Drive, OneDrive) satura la red inalámbrica del campus y resulta inviable o extremadamente lento.
* **Falta de Cobertura en Zonas del Campus:** En talleres, laboratorios o áreas abiertas donde la señal de Internet es débil o nula, los estudiantes quedan incomunicados y sin acceso a sus recursos de estudio.
* **Riesgos de Seguridad por Memorias USB:** El método tradicional de pasarse archivos pesados mediante memorias USB infecta frecuentemente las computadoras de los laboratorios y las laptops de los estudiantes con malware, virus de accesos directos y troyanos.
* **Gasto Innecesario de Datos Móviles:** Los estudiantes que no cuentan con acceso al Wi-Fi institucional consumen sus planes de datos personales en descargas académicas pesadas.

### 3.2 Problemática de la Gestión de Horarios Académicos
* **Transcripción Manual y Errores de Registro:** Registrar a mano semanalmente entre 5 y 8 materias con diferentes bloques de horas, grupos, salones y docentes genera confusiones de aula y solapamientos.
* **Falta de Recordatorios Oportunos:** Ausencia de un sistema automatizado que avise con tiempo prudencial el inicio de la clase y el aula asignada.

### 3.3 La Solución Integral de ChatoSync
ChatoSync convierte una laptop en un **Servidor de Borde Portátil (*Portable Edge Server*)** que emite una red Wi-Fi dedicada (`ULSA-Hub`). Integra una nube privada local, carpetas compartidas de red de alta velocidad y un motor de inteligencia artificial OCR accesible desde navegadores web, exploradores de archivos y teléfonos celulares.

---

## 4. PILARES FUNDAMENTALES DEL SISTEMA

```
                              ┌────────────────────────────────────────────────────────┐
                              │                 SISTEMA CHATOSYNC                      │
                              │           (Servidor Edge Debian 13 ULSA)               │
                              └──────────────────────────┬─────────────────────────────┘
                                                         │
                    ┌────────────────────────────────────┴────────────────────────────────────┐
                    ▼                                                                         ▼
     ╔═══════════════════════════════════════╗                 ╔═══════════════════════════════════════╗
     ║               PILAR 1                 ║                 ║               PILAR 2                 ║
     ║     TRANSFERENCIA DE ARCHIVOS         ║                 ║     DIGITALIZACIÓN OCR Y CALENDARIO   ║
     ║     PESADOS SIN INTERNET              ║                 ║     INTELIGENTE                     ║
     ╠═══════════════════════════════════════╣                 ╠═══════════════════════════════════════╣
     ║ • Samba File Share (SMB/CIFS)         ║                 ║ • Tesseract OCR en Español            ║
     ║ • Nextcloud Private Cloud (LAMP)      ║                 ║ • Preprocesamiento con Pillow         ║
     ║ • Velocidad LAN (300-866 Mbps)        ║                 ║ • Parser Dinámico Regex               ║
     ║ • Cero consumo de datos móviles       ║                 ║ • Generador Universal .ICS (RFC 5545) ║
     ║ • Sin virus de memorias USB           ║                 ║ • Google Calendar API v3 (Alertas 20m)║
     ║ • Gestión de permisos y carpetas      ║                 ║ • Generador de Reporte PDF (CUPS)     ║
     ╚═══════════════════════════════════════╝                 ╚═══════════════════════════════════════╝
```

---

## 5. DETALLE TÉCNICO DEL PILAR 1: TRANSFERENCIA DE ARCHIVOS PESADOS (OFF-GRID HUB)

### 5.1 Almacenamiento Compartido de Red Samba (SMB / CIFS)
* **Protocolo:** Server Message Block (SMB versión 2 y 3).
* **Rendimiento:** Transferencia a velocidad máxima de la tarjeta de red local (típicamente entre 25 MB/s y 60 MB/s en Wi-Fi local), permitiendo copiar un instalador de 4 GB en menos de 2 minutos.
* **Acceso Nativo sin Software Extra:**
  * **En Windows:** Presionando `Win + R` y escribiendo `\\192.168.137.102\hub`.
  * **En Android:** Con cualquier explorador de archivos (CX Explorer, Solid Explorer) conectando a `smb://192.168.137.102/hub`.
  * **En iPhone / iPad:** Desde la app nativa *Archivos* -> *Conectarse al servidor*.
  * **En Linux / macOS:** Montaje de recurso CIFS nativo.
* **Estructura de Carpetas:**
  * `/srv/samba/hub/`: Directorio raíz de intercambio público para proyectos, instaladores y recursos de clase.
  * `/srv/samba/hub/entrada/`: Buzón automatizado de procesamiento.
  * `/srv/samba/hub/procesados/`: Historial ordenado con marcas de tiempo.

### 5.2 Nube Privada y Colaborativa Nextcloud Hub (LAMP Stack)
* **Arquitectura:** Apache 2.4 + MariaDB 11 + PHP 8.4 nativo.
* **Capacidades:**
  * Subida y descarga de archivos mediante interfaz web moderna e intuitiva en `http://192.168.137.102/nextcloud`.
  * Creación de cuentas de usuario independientes para grupos de trabajo con cuotas de almacenamiento configurables.
  * Compartición de enlaces locales de descarga directa con o sin contraseña.
  * Sincronización automática de carpetas mediante los clientes oficiales de Nextcloud para Windows, Android e iOS.
  * Visor integrado de documentos PDF, imágenes y código fuente sin necesidad de descargar el archivo.

---

## 6. DETALLE TÉCNICO DEL PILAR 2: MOTOR OCR Y CALENDARIZACIÓN INTELIGENTE

### 6.1 Algoritmo de Preprocesamiento Visual
Para garantizar una tasa de acierto del OCR superior al 95% incluso con fotos tomadas con poca luz o capturas de baja resolución:
1. **Escala de Grises:** Eliminación de artefactos de color del portal web institucional (`img.convert('L')`).
2. **Filtro de Agudizamiento (Sharpen):** Realce de bordes de caracteres tipográficos (`ImageFilter.SHARPEN`).
3. **Amplificación de Contraste:** Aumento dinámico a 2.5x (`ImageEnhance.Contrast(img).enhance(2.5)`).

### 6.2 Motor de Extracción Semántica (Parser Dinámico Regex)
El script `procesar_horario.py` es completamente agnóstico al diseño específico de la página web de la universidad y soporta:
* Cualquier cantidad de asignaturas inscritas (1 a 10 materias).
* Múltiples bloques de horario por asignatura (ej. Lunes 8:00am y Jueves 1:00pm).
* Detección de aulas institucionales (G105, A201, LAB-CIB, etc.).
* Detección automática del nombre del docente mediante prefijos académicos (`MSc.` / `Ing.`).

### 6.3 Sincronización de Calendarios y Notificaciones
* **Estándar Universal iCalendar (`.ics`):**
  * Cumple con la especificación internacional **RFC 5545**.
  * Reglas de recurrencia semanal: `RRULE:FREQ=WEEKLY;BYDAY=...;UNTIL=20261218T235959Z`.
  * **Alarma Silenciosa de 20 Minutos:**
    ```text
    BEGIN:VALARM
    TRIGGER:-PT20M
    ACTION:DISPLAY
    DESCRIPTION:Recordatorio de clase ULSA
    END:VALARM
    ```
* **Integración Google Calendar API v3:** Inserción directa de eventos mediante flujo OAuth2.

---

## 7. INFRAESTRUCTURA DE RED Y SERVICIOS LINUX DEBIAN 13

El servidor integra 6 servicios de nivel empresarial configurados como demonios del sistema (`systemd`):

| Servicio | Demonio / Puerto | Rol en ChatoSync |
| :--- | :--- | :--- |
| **DNS BIND9** | `named.service` (Puerto 53) | Resolución de dominios locales (`ulsa.local`, `hub.ulsa.local`, `cloud.ulsa.local`). |
| **Postfix SMTP** | `postfix.service` (Puerto 25) | Recepción de correos locales y transferencia de mensajes hacia el buzón Maildir. |
| **Dovecot IMAP/POP3** | `dovecot.service` (Puertos 143/110) | Servidor de buzones y entrega en formato Maildir con autenticación en texto claro local. |
| **Samba SMB/CIFS** | `smbd.service`, `nmbd.service` (Puertos 445/139) | Compartición de archivos en red local a máxima velocidad LAN sin Internet. |
| **Servidor Web Apache** | `apache2.service` (Puerto 80) | Alojamiento del Panel de Control GUI y de la plataforma Nextcloud Hub. |
| **Servidor CUPS-PDF** | `cups.service` (Puerto 631) | Servidor de impresión y generador de documentos PDF vectoriales con membrete oficial. |
| **Demonio ChatoSync** | `chatosync.service` (Background) | Vigilante autónomo de carpetas Samba y Maildir para procesamiento OCR en tiempo real. |

---

## 8. TOPOLOGÍA DE RED Y DIRECCIONAMIENTO

* **Segmento de Red:** `192.168.137.0/24` (Máscara `255.255.255.0`)
* **Punto de Acceso Wi-Fi (Laptop Host):** `192.168.137.1` (SSID: `ULSA-Hub`)
* **Servidor ChatoSync (Debian 13 VM):** `192.168.137.102` (Adaptador Puente)
* **Clientes Conectados (Laptops / Teléfonos):** Asignados dinámicamente por DHCP (`192.168.137.100 - .200`).

---

## 9. PANEL DE CONTROL WEB RESPONSIVO (DASHBOARD GUI)

Accesible desde cualquier navegador en la red local en **`http://192.168.137.102/`**:
* **Monitor en Tiempo Real:** 6 tarjetas interactivas que verifican el estado activo/inactivo de cada servicio de red mediante llamadas AJAX a `api.php`.
* **Zona Drag & Drop:** Arrastre y suelta de archivos de horario con procesamiento instantáneo.
* **Botón de Demostración:** *"Probar Horario de Muestra ULSA"* para pruebas de 1 solo clic.
* **Tabla Dinámica de Clases:** Visualización clara con códigos, materias, días, horas, aulas en rojo y docentes.
* **Descarga de Calendario (.ics):** Botón directo para importar todas las materias en Google Calendar, Apple Calendar o Outlook.
* **Consola de Logs en Vivo:** Visor de registros en tiempo real sin requerir acceso por terminal SSH.
* **Acceso Directo a la Nube:** Enlace al panel de Nextcloud para gestión de archivos pesados.

---

## 10. EXPERIENCIA MULTIPLATAFORMA (MÓVIL Y ESCRITORIO)

1. **En Teléfonos Móviles (Android / iPhone):**
   * Conexión al Wi-Fi `ULSA-Hub`.
   * Navegación a `http://192.168.137.102` (Diseño 100% responsivo Mobile-First).
   * Subida de fotos de horarios tomadas con la cámara del celular.
   * Descarga de archivos `.ics` que se integran en 1 toque con la app Google Calendar del teléfono.
   * Descarga/subida de archivos académicos pesados mediante la app móvil oficial de Nextcloud.
2. **En Laptops (Windows / macOS / Linux):**
   * Acceso por explorador de archivos a `\\192.168.137.102\hub` para transferir archivos a velocidad de red local.
   * Acceso web completo al Dashboard, Nextcloud y panel de administración CUPS (`:631`).

---

## 11. JUSTIFICACIÓN DE DECISIONES TÉCNICAS Y DE INGENIERÍA

| Decisión de Diseño | Alternativa Rechazada | Justificación Técnica |
| :--- | :--- | :--- |
| **Red Wi-Fi Local Autónoma (Edge)** | Nube Pública en Internet | Cero dependencia de conexión externa, máxima velocidad de transferencia (300+ Mbps) y cero consumo de datos móviles en el campus. |
| **Samba + Nextcloud Híbrido** | Solo memorias USB | Elimina vectores de propagación de virus/malware y permite acceso simultáneo de múltiples estudiantes al mismo repositorio de archivos. |
| **Entorno Virtual Python (`venv`)** | Instalación Global Pip | Cumplimiento estricto con la directiva **PEP 668** en Debian 13 para evitar rotura de paquetes del sistema operativo. |
| **Nextcloud en LAMP Nativo** | Contenedor Snap | Mayor eficiencia en memoria RAM y CPU, acceso directo a la base de datos MariaDB y optimización de PHP 8.4 con extensiones nativas. |
| **Formato iCalendar (`.ics`)** | CalDAV Exclusivo | Compatibilidad universal instantánea con el 100% de teléfonos y computadoras sin requerir configuración de cuentas complejas. |

---

## 12. CONCLUSIONES

1. **ChatoSync** demuestra la viabilidad de implementar una arquitectura de **Computación de Borde (*Edge Computing*)** altamente eficiente, capaz de prestar servicios de nube privada, transferencia masiva de archivos y procesamiento de inteligencia artificial en hardware accesible y portable.
2. Se resolvió de forma simultánea la problemática del intercambio de archivos pesados sin Internet en el campus universitario y la automatización del registro de horarios académicos con notificaciones tempranas de 20 minutos.
3. La combinación de servicios de red tradicionales (DNS, SMTP, IMAP, SMB, HTTP, CUPS) con tecnologías modernas de software (Python, Tesseract OCR, Tailwind CSS, API REST) ofrece una experiencia de usuario fluida, intuitiva y multiplataforma para toda la comunidad académica de la ULSA.

---

## 13. ESTRUCTURA DEL REPOSITORIO (GITHUB)

```text
ChatoSync/
├── config/
│   ├── bind9/              # Configuraciones de BIND9 DNS
│   ├── postfix/            # Configuración SMTP Postfix
│   ├── dovecot/            # 99-chatosync.conf (Dovecot 2.4 IMAP)
│   ├── samba/              # smb.conf (Share [hub] de alta velocidad)
│   └── cups/               # cupsd.conf (Servidor de impresión)
├── scripts/
│   ├── 00_instalar_todo.sh # Instalador maestro desatendido
│   ├── 02_setup_bind9.sh   # Despliegue de DNS
│   ├── 03_setup_correo.sh  # Despliegue de Correo
│   ├── 04_setup_samba.sh   # Despliegue de Samba
│   ├── 05_setup_nextcloud.sh # Despliegue de Nextcloud LAMP
│   ├── 06_setup_cups.sh    # Despliegue de CUPS-PDF
│   ├── 08_setup_servicio.sh # Despliegue del entorno virtual y demonio
│   └── 99_actualizar_y_reparar.sh # Script de reparación y diagnóstico
├── src/
│   ├── procesar_horario.py # Motor OCR, Parsing Regex y Generador ICS
│   └── requirements.txt    # Librerías Python
├── systemd/
│   └── chatosync.service   # Servicio systemd en segundo plano
├── web/
│   ├── index.php           # Panel Web GUI Responsivo
│   ├── api.php             # Backend API en PHP para llamadas AJAX
│   └── download_ics.php    # Endpoint de descarga del calendario
├── samples/
│   └── horario_muestra.png # Imagen de prueba de horario real ULSA
├── docs/
│   ├── 01_instalacion_debian_virtualbox.md
│   ├── 03_google_calendar_setup.md
│   ├── INFORME_TECNICO_PROYECTO_CHATOSYNC.md
│   └── GUIA_DEFENSA.md
├── INFORME_TECNICO_PROYECTO_CHATOSYNC.md
├── EXPLICACION_PROYECTO_CHATOSYNC.txt
└── README.md
```
