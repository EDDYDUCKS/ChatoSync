# DOCUMENTO TÉCNICO COMPLETO DEL PROYECTO CHATOSYNC
## Servidor de Borde (Edge Server) para Procesamiento Inteligente de Horarios y Sincronización de Calendarios Universitarios

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

ChatoSync es una solución integral de infraestructura de red y computación de borde (*Edge Computing*) diseñada para automatizar la digitalización, extracción semántica y calendarización de horarios académicos dentro del entorno universitario de la Universidad Tecnológica La Salle (ULSA).

El sistema opera de forma 100% autónoma en una red de área local inalámbrica portable, prescindiendo de dependencia continua de Internet para sus funciones nucleares. Integra seis servicios de red esenciales sobre Linux (DNS BIND9, Correo Postfix/Dovecot, Almacenamiento Samba, Nube Privada Nextcloud en LAMP, Impresión Virtual CUPS-PDF y un Demonio Autónomo en Python) junto con un motor de Inteligencia Artificial basado en Visión por Computadora y Reconocimiento Óptico de Caracteres (OCR con Tesseract).

A través de ChatoSync, un estudiante puede tomar una fotografía o captura digital a su hoja de inscripción/horario, subirla mediante una interfaz web responsiva, correo electrónico o carpeta compartida de red, y el sistema extraerá dinámicamente cada asignatura, código, aula, docente y horario semanal, generando de forma instantánea archivos universales de calendario (`.ics` bajo el estándar RFC 5545) y sincronización con Google Calendar API v3, configurando alarmas/notificaciones de 20 minutos previas al inicio de cada clase.

---

## 3. PLANTEAMIENTO DEL PROBLEMA Y JUSTIFICACIÓN

### 3.1 Problemática Identificada
En los entornos universitarios actuales, los horarios de clases suelen entregarse a los estudiantes como reportes impresos en papel, documentos PDF estáticos o capturas de pantalla del portal web institucional (SIGA-ULSA). Esta modalidad genera las siguientes dificultades:
1. **Transcripción Manual Propensa a Errores:** Cada estudiante debe registrar a mano entre 5 y 8 materias semanales con diferentes bloques horarios, salones de clase y docentes en sus calendarios personales, generando errores frecuentes de solapamiento o confusión de aulas.
2. **Falta de Recordatorios Oportunos:** La ausencia de un sistema de alertas automatizado provoca llegadas tardías a los salones de clase o laboratorios especializados.
3. **Dependencia de Infraestructura Cloud Centralizada:** Muchos sistemas dependen de conexiones a Internet estables que pueden saturarse en el campus universitario.
4. **Heterogeneidad de Dispositivos:** Los estudiantes utilizan laptops (Windows/Linux/macOS) y dispositivos móviles (Android/iOS) con diferentes sistemas operativos y aplicaciones de calendario.

### 3.2 Solución Propuesta por ChatoSync
ChatoSync implementa un Servidor de Borde Portable (*Portable Edge Server*) capaz de desplegarse en una laptop o dispositivo embebido que emite su propia red Wi-Fi local (*Hotspot*). Ofrece múltiples vías de ingesta (Web, Red Samba, Correo Electrónico) y procesa localmente los documentos mediante OCR, entregando tanto un calendario digital interactivo como una nube privada de almacenamiento académico.

---

## 4. OBJETIVOS DEL PROYECTO

### 4.1 Objetivo General
Diseñar, implementar y evaluar un servidor de borde autónomo basado en Debian GNU/Linux que integre servicios de infraestructura de redes TCP/IP, almacenamiento colaborativo, servidores web y un motor de procesamiento OCR para la digitalización y sincronización automatizada de horarios universitarios con calendarios personales.

### 4.2 Objetivos Específicos
1. Configurar una infraestructura de red local sólida compuesta por servicios de resolución de nombres (**BIND9**), servidores de correo (**Postfix/Dovecot**), almacenamiento en red (**Samba**), nube privada (**Nextcloud en LAMP**) e impresión virtual (**CUPS-PDF**).
2. Desarrollar un algoritmo en Python con preprocesamiento de imagen y **Tesseract OCR** que parsee de forma dinámica cualquier horario universitario (número variable de materias y bloques de clase) sin requerir plantillas fijas.
3. Implementar la generación automática de archivos de calendario universal (**iCalendar `.ics`**) y la integración con **Google Calendar API v3**, programando notificaciones emergentes de 20 minutos antes de cada sesión académica.
4. Crear un **Panel de Control Web Moderno y Responsivo (Dashboard GUI)** que permita la interacción visual, subida de archivos por arrastre (*Drag & Drop*), monitoreo de estado de servicios y visualización de logs en tiempo real.
5. Garantizar la interoperabilidad multiplataforma en entornos móviles (Android e iOS) y de escritorio (Windows/Linux).

---

## 5. TOPOLOGÍA Y ARQUITECTURA DE RED

### 5.1 Segmentación y Direccionamiento IP
* **Subred de Operación:** `192.168.137.0/24` (Máscara `255.255.255.0`)
* **Punto de Acceso / Gateway (Laptop Host Windows):** `192.168.137.1` (Hotspot `ULSA-Hub`)
* **Servidor Edge ChatoSync (Debian 13 VM):** `192.168.137.102` (Configurable a IP Estática `192.168.137.10`)
* **Rango de Clientes DHCP (Estudiantes / Dispositivos Móviles):** `192.168.137.100 - 192.168.137.200`
* **Modo de Interfaz de Red VirtualBox:** *Adaptador Puente (Bridged Adapter)* vinculado al *Microsoft Wi-Fi Direct Virtual Adapter* para comunicación directa en Capa 2 / Capa 3.

```
       +--------------------------------------------------------------+
       |            LAPTOP HOST (Windows 11) - 192.168.137.1          |
       |      Zona Wi-Fi Móvil (SSID: ULSA-Hub / Subred 192.168.137.0/24) |
       +--------------------------------------------------------------+
                                      │
                                      ▼ [Adaptador Puente / Capa 2]
       +──────────────────────────────────────────────────────────────+
       |           SERVIDOR CHATOSYNC (Debian 13 VM - 192.168.137.102)|
       |                                                              |
       |  [DNS BIND9]        [CORREO POSTFIX/DOVECOT]   [SAMBA SMB]   |
       |  ulsa.local         importar@ulsa.local        \\192.168...  |
       |                                                              |
       |  [APACHE2 + PHP]    [NEXTCLOUD HUB]            [CUPS-PDF]    |
       |  Panel Web GUI      Nube Colaborativa          Impresora PDF |
       |                                                              |
       |  [DEMONIO CHATOSYNC PYTHON 3.13]                             |
       |  - Pillow Preprocessing (Escala de Grises + Contraste 2.5x)   |
       |  - Tesseract OCR Engine (spa)                                |
       |  - Dynamic Regex Parser (Materias / Días / Aulas / Docentes) |
       |  - Generador iCalendar (.ics RFC 5545)                       |
       |  - Google Calendar API v3 Client                             |
       +──────────────────────────────────────────────────────────────+
               ▲                           ▲                        ▲
               │                           │                        │
       [Navegador Web / HTTP]      [Samba File Share]      [Móvil Wi-Fi]
       http://192.168.137.102/    \\192.168.137.102\hub   Google Calendar
```

---

## 6. DESGLOSE TÉCNICO DE LOS 6 MÓDULOS DE INFRAESTRUCTURA

### 🔹 Módulo 1: Servidor de Nombres de Dominio (DNS BIND9)
* **Paquetes:** `bind9`, `bind9-utils`, `bind9-doc` (Servicio: `named.service`).
* **Función:** Provee resolución de nombres local para que los equipos de la red puedan acceder a los servicios mediante nombres legibles en lugar de direcciones IP numéricas.
* **Zona Directa (`ulsa.local`):**
  * `hub.ulsa.local` -> `192.168.137.10`
  * `cloud.ulsa.local` -> `192.168.137.10`
  * `mail.ulsa.local` -> `192.168.137.10`
  * Registros MX (Mail Exchanger) apuntando a `mail.ulsa.local` con prioridad 10.
* **Zona Inversa (`137.168.192.in-addr.arpa`):**
  * Puntero PTR que mapea `192.168.137.10` a `hub.ulsa.local`.

### 🔹 Módulo 2: Servidor de Correo Electrónico (Postfix + Dovecot)
* **Paquetes:** `postfix` (MTA - Mail Transfer Agent), `dovecot-imapd`, `dovecot-pop3d` (MDA - Mail Delivery Agent).
* **Función:** Ingesta de horarios mediante correos electrónicos directos.
* **Configuración Clave:**
  * Dominio: `ulsa.local`
  * Buzón de entrega: Formato **`Maildir/`** por usuario (`/home/importar/Maildir/new/`, `cur/`, `tmp/`).
  * Cuenta de servicio: `importar@ulsa.local` (Contraseña: `1234`).
  * Autenticación Dovecot 2.4: Activación de texto claro seguro en red local (`auth_allow_cleartext = yes`).

### 🔹 Módulo 3: Servidor de Archivos Compartidos (Samba SMB/CIFS)
* **Paquetes:** `samba`, `samba-common-bin` (Servicios: `smbd`, `nmbd`).
* **Función:** Punto de intercambio rápido de archivos entre clientes Windows/Linux/macOS y el servidor Debian.
* **Estructura del Share (`[hub]`):**
  * Ruta local: `/srv/samba/hub/`
  * Subcarpeta de entrada: `/srv/samba/hub/entrada/` (Buzón de recepción de imágenes).
  * Subcarpeta de procesados: `/srv/samba/hub/procesados/` (Historial de archivos procesados con timestamp).
  * Permisos: Lectura y escritura pública (`create mask = 0777`, `force user = root`) para permitir interacción transparente desde el Explorador de Windows mediante `\\192.168.137.102\hub`.

### 🔹 Módulo 4: Nube Privada y Colaborativa (Nextcloud LAMP Stack)
* **Stack Tecnológico:** Apache 2.4, MariaDB Server 11.x, PHP 8.4 (con extensiones `php-gd`, `php-mysql`, `php-curl`, `php-mbstring`, `php-xml`, `php-zip`).
* **Función:** Plataforma de almacenamiento en la nube on-premise (*Auto-alojada*) para sincronización de archivos de estudiantes y profesores sin requerir conexión a nubes públicas comerciales.
* **Base de Datos:**
  * Motor: MariaDB
  * Base de datos: `nextcloud`
  * Usuario: `nextcloud` (Contraseña: `1234`)
* **Acceso:** `http://192.168.137.102/nextcloud`

### 🔹 Módulo 5: Servidor de Impresión Virtual (CUPS + CUPS-PDF + LibreOffice)
* **Paquetes:** `cups`, `printer-driver-cups-pdf`, `libreoffice-writer` (Headless).
* **Función:** Generación de reportes impresos y conversión automatizada a formato PDF vectorial de alta fidelidad.
* **Impresora Virtual:** `Impresora_PDF` (`cups-pdf:/`) utilizando el controlador `CUPS-PDF_opt.ppd`.
* **Salida:** Permite transformar la agenda académica generada en HTML a un PDF oficial con membrete universitario de la ULSA.

### 🔹 Módulo 6: Demonio Autónomo ChatoSync (Python 3.13 + OCR + Sincronización)
* **Entorno de Ejecución:** Entorno Virtual Aislado (`/opt/chatosync-venv/`) administrado por un demonio de **`systemd`** (`chatosync.service`).
* **Librerías Principales:** `pytesseract`, `pillow` (PIL), `google-api-python-client`, `google-auth-httplib2`, `google-auth-oauthlib`.
* **Componentes del Algoritmo:**
  1. **Preprocesamiento Visual de la Imagen:**
     * Conversión a escala de grises (`img.convert('L')`).
     * Filtro de aumento de nitidez espacial (`ImageFilter.SHARPEN`).
     * Realce dinámico de contraste a factor 2.5x (`ImageEnhance.Contrast`).
  2. **Extracción Óptica (OCR):**
     * Ejecución de Tesseract con diccionario en español (`lang='spa'`).
  3. **Motor de Parsing Dinámico con Expresiones Regulares:**
     * **Detección de Asignaturas:** Expresión regular que identifica códigos numéricos de 4 dígitos universitarios (`\d{4}`) y el nombre de la materia:
       `r'(\d{4})\s+([A-Za-zÁÉÍÓÚáéíóúñ\s]{3,40})'`
     * **Detección de Bloques Horarios:** Identificación de patrones de días (Lu, Ma, Mi, Ju, Vi, Sa), rangos de horas y aula asignada:
       `r'(Lu|Ma|Mi|Ju|Vi|Sa)\s+(\d{1,2}:\d{2}\s*[ap]m)\s*-\s*(\d{1,2}:\d{2}\s*[ap]m)\s*\[\s*([A-Z0-9]+)\s*\]'`
     * **Detección de Docente:** Búsqueda de prefijos académicos (`MSc.` / `Ing.`):
       `r'(MSc\.|Ing\.)\s+([A-Za-zÁÉÍÓÚáéíóúñ\s]+)'`
  4. **Generador de Estándar Universal iCalendar (`.ics`):**
     * Construcción de objetos `VEVENT` bajo el estándar RFC 5545.
     * Regla de recurrencia semanal: `RRULE:FREQ=WEEKLY;BYDAY=...;UNTIL=20261218T235959Z`.
     * Bloque de alarma silenciosa: `VALARM` con `TRIGGER:-PT20M` (notificación emergente en pantalla 20 minutos antes).
  5. **Conector Google Calendar API v3:**
     * Autenticación OAuth2 para inserción remota de eventos en el calendario principal del usuario.

---

## 7. PANEL DE CONTROL WEB RESPONSIVO (DASHBOARD GUI)

Para superar la limitación de trabajar exclusivamente en consolas de texto, se desarrolló un Panel Web Gráfico Integral accesible desde `http://192.168.137.102/`:

### Características Principales del Dashboard:
* **Diseño Moderno & Glassmorphism:** Construido con Tailwind CSS y FontAwesome, adaptado para pantallas de escritorio, tablets y smartphones.
* **Monitoreo de Red en Tiempo Real:** 6 tarjetas de estado que consultan vía AJAX (`api.php`) el estado de ejecución (`systemctl is-active`) de cada servicio:
  * DNS BIND9
  * Correo Postfix (SMTP)
  * Correo Dovecot (IMAP)
  * Samba File Share
  * Servidor Web Apache / Nextcloud
  * Servidor CUPS-PDF
  * Motor OCR ChatoSync
* **Zona de Carga Inteligente (Drag & Drop):** Permite arrastrar capturas de horarios o subir imágenes/PDFs directamente desde el explorador de archivos del cliente.
* **Botón de Demostración Rápida:** *"Probar Horario de Muestra ULSA"* para ejecutar una prueba completa en un solo clic.
* **Tabla Interactiva de Horarios:** Muestra las asignaturas extraídas con códigos resaltados, insignias de días de la semana, horarios formateados, etiquetas de aulas en color rojo institucional y docentes asignados.
* **Descarga Directa de Calendario (`.ics`):** Botón para descargar el archivo de calendario listo para importar en Google Calendar, Outlook o Apple Calendar.
* **Consola de Logs Integrada:** Visor de registros en tiempo real de `/var/log/chatosync.log` con auto-scroll.

---

## 8. EXPERIENCIA DE USUARIO EN DISPOSITIVOS MÓVILES (ANDROID / IOS)

El sistema está concebido para que los estudiantes no dependan de una computadora para beneficiarse de ChatoSync:

1. **Conexión:** El estudiante se conecta desde su celular a la red Wi-Fi `ULSA-Hub`.
2. **Acceso Web Móvil:** Entra a `http://192.168.137.102` desde el navegador de su teléfono.
3. **Captura Directa:** Al presionar "Subir Horario", puede seleccionar la cámara de su teléfono, tomar la foto a su hoja de horario y subirla al instante.
4. **Calendarización con 1 Toque:** Al presionar "Añadir a Calendario (.ics)", el sistema operativo móvil (Android o iOS) abre nativamente la app de Google Calendar o Apple Calendar, solicitando confirmación para añadir todos los eventos semanales con sus respectivas alarmas de 20 minutos.
5. **Nube en el Bolsillo:** Mediante la aplicación móvil oficial de Nextcloud, el estudiante sincroniza tareas y material de clase localmente sin consumir saldo de datos móviles.

---

## 9. JUSTIFICACIÓN DE DECISIONES DE INGENIERÍA

| Decisión de Diseño | Alternativa Considerada | Razón Técnica de la Elección |
| :--- | :--- | :--- |
| **Edge Computing Local** | Cloud Pública (AWS / Azure) | Garantiza 100% de disponibilidad sin depender de conexión a Internet ni pagar costos de suscripción por llamadas a APIs OCR comerciales. |
| **Entorno Virtual Python (`venv`)** | Paquetes Globales Pip | Cumple con la normativa **PEP 668** de Debian 13 (Trixie), evitando colisiones con los paquetes del gestor `apt` del sistema operativo. |
| **Nextcloud LAMP Nativo** | Contenedor Snap | Mayor rendimiento en máquinas virtuales con recursos moderados, control granular sobre MariaDB y compatibilidad con PHP 8.4. |
| **Generación `.ics` + Google API** | CalDAV Estricto | El formato `.ics` (RFC 5545) es universal y compatible sin configuración previa en cualquier teléfono inteligente, tablet o laptop. |
| **Ajustes Dovecot 2.4 en `99-chatosync.conf`** | Modificación de `10-auth.conf` | En Debian 13 / Dovecot 2.4, los archivos `conf.d/` se sobrescriben en actualizaciones; un archivo `99-*.conf` garantiza persistencia y modularidad. |

---

## 10. CONCLUSIONES

1. Se implementó exitosamente una arquitectura de red heterogénea y portable sobre Debian GNU/Linux 13, logrando la convivencia armónica de servicios de infraestructura (DNS, Correo, SMB, Web, Impresión) en un único nodo de borde.
2. El algoritmo de Visión por Computadora y OCR desarrollado demostró alta robustez al procesar documentos de horarios reales de la ULSA, abstrayendo variaciones en el número de asignaturas y distribuciones horarias gracias a expresiones regulares dinámicas.
3. La integración con estándares universales de calendarización (iCalendar RFC 5545) y la API de Google Calendar resuelve de forma definitiva el problema de la transcripción manual de horarios, entregando notificaciones automáticas y precisas a los estudiantes.
4. El desarrollo del Panel de Control Web transforma un proyecto tradicional de infraestructura de consola en un producto de software intuitivo, accesible tanto desde computadoras de escritorio como desde teléfonos móviles.

---

## 11. ESTRUCTURA DEL REPOSITORIO DE CÓDIGO (GITHUB)

```text
ChatoSync/
├── config/
│   ├── bind9/              # named.conf.local, db.ulsa.local, db.192.168.137
│   ├── postfix/            # main.cf (Configuración SMTP)
│   ├── dovecot/            # 99-chatosync.conf (Configuración IMAP/POP3 Dovecot 2.4)
│   ├── samba/              # smb.conf (Share [hub] con permisos de red)
│   └── cups/               # cupsd.conf (Configuración del servidor de impresión)
├── scripts/
│   ├── 00_instalar_todo.sh # Script maestro de despliegue automatizado
│   ├── 02_setup_bind9.sh   # Despliegue de DNS
│   ├── 03_setup_correo.sh  # Despliegue de Postfix + Dovecot
│   ├── 04_setup_samba.sh   # Despliegue de Samba
│   ├── 05_setup_nextcloud.sh # Despliegue de Nextcloud LAMP
│   ├── 06_setup_cups.sh    # Despliegue de CUPS-PDF
│   └── 08_setup_servicio.sh # Despliegue del entorno virtual y servicio systemd
├── src/
│   ├── procesar_horario.py # Motor principal de OCR, Regex y Calendario
│   └── requirements.txt    # Dependencias de Python
├── systemd/
│   └── chatosync.service   # Definición del servicio del sistema Linux
├── web/
│   ├── index.php           # Panel de Control Web Responsivo (Dashboard GUI)
│   ├── api.php             # API Backend en PHP para AJAX y estado de servicios
│   └── download_ics.php    # Endpoint de descarga del calendario .ics
├── samples/
│   └── horario_muestra.png # Captura de horario real de la ULSA
├── docs/
│   ├── 01_instalacion_debian_virtualbox.md
│   ├── 03_google_calendar_setup.md
│   ├── INFORME_TECNICO_PROYECTO_CHATOSYNC.md
│   └── GUIA_DEFENSA.md     # Guía de preguntas y respuestas para la evaluación
├── INFORME_TECNICO_PROYECTO_CHATOSYNC.md
├── EXPLICACION_PROYECTO_CHATOSYNC.txt
└── README.md
```
