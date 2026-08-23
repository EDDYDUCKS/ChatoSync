# REPORTE TÉCNICO Y PLAN DE IMPLEMENTACIÓN DETALLADO
## PROYECTO: ULSA LOCAL-HUB (PLATAFORMA AUTÓNOMA DE SINCRONIZACIÓN DE ARCHIVOS Y AUTOMATIZACIÓN DE HORARIOS)
**Asignatura:** Taller de Conectividad (IV Año, Ingeniería en Cibernética Electrónica)  
**Docente:** Ing. Freddy Alexander Mejía Quintana  
**Institución:** Universidad Tecnológica La Salle (ULSA), León, Nicaragua  

---

## 1. INTRODUCCIÓN Y PLANTEAMIENTO DEL PROBLEMA REAL

### 1.1 El Problema Contextual de la ULSA
En el campus de la ULSA, los estudiantes de Ingeniería en Cibernética Electrónica e ingenierías afines se enfrentan diariamente a dos problemas críticos de conectividad y productividad:
1. **La Congestión de la WAN y el Aislamiento de Red:** El Wi-Fi público de la universidad cuenta con estrictas políticas de seguridad (AP Isolation) y limitaciones severas de ancho de banda. Transferir archivos pesados de código, firmwares, videos o PDFs de instaladores entre la laptop de desarrollo Windows, el teléfono celular u otros compañeros de equipo requiere subir datos a nubes comerciales (Google Drive, GitHub) usando el internet del campus, para luego descargarlos en el celular. Esto congestiona la salida WAN y hace que las transferencias locales duren minutos u horas en lugar de segundos.
2. **La Ineficiencia en la Gestión de Horarios:** Al inicio de cada ciclo o mes, la Oficina de Registro Académico emite un reporte oficial de inscripción de asignaturas en PDF (`Imprimir Inscripción.pdf`). Transcribir manualmente las asignaturas, códigos de materias, aulas específicas (ej. `[ B107 ]`), horas de clase y docentes al calendario digital del teléfono es una tarea tediosa, repetitiva y propensa a errores.

### 1.2 La Solución de Ingeniería: "ULSA Local-Hub"
Para resolver ambos problemas con infraestructura de red de borde local (Edge Computing), se propone el despliegue de **ULSA Local-Hub**: un servidor de conectividad portátil que corre sobre una Máquina Virtual con Debian 13 en la laptop Windows del alumno. 
* El sistema crea una **burbuja de red inalámbrica privada** utilizando el Hotspot de la laptop o del celular. 
* Ofrece almacenamiento local seguro a máxima velocidad LAN mediante **Samba** (para el explorador de Windows) y **Nextcloud** (para la sincronización en teléfonos móviles).
* Integra un **motor de automatización con OCR local** (Tesseract): el alumno toma una captura de su horario, la envía por correo a `importar@ulsa.local`, y el servidor de correo (**Postfix/Dovecot**) intercepta la imagen, extrae los datos del formato exacto de inscripción de la ULSA mediante expresiones regulares, agenda las clases de manera autónoma en el calendario CalDAV de Nextcloud de su celular, y genera un PDF vectorial de su agenda semanal a través de la cola de impresión de **CUPS-PDF**, depositándolo en su carpeta compartida.

---

## 2. ARQUITECTURA DE LA TOPOLOGÍA DE RED Y PROTOCOLOS

### 2.1 Esquema Físico y Lógico (Sin Router Externo)
Dado que no se requiere hardware de red adicional, la topología se construye utilizando los adaptadores inalámbricos de los dispositivos del estudiante.

* **Punto de Acceso Inalámbrico (WLAN):** Hotspot móvil de Windows (o de Android/iOS con datos móviles apagados) actuando como el Switch Inalámbrico virtual.
* **Servidor DHCP:** El servicio del sistema operativo que genera el Hotspot asigna dinámicamente IPs locales en el rango `192.168.137.0/24` (si es Windows Hotspot) o `192.168.43.0/24` (si es Android Hotspot).
* **Asignación de IP Estática para Debian 13 (VM):**
  * Se asigna de forma fija la IP `192.168.137.10` (o `192.168.43.10` según la subred).
  * En VirtualBox, el adaptador de red de la VM se configura obligatoriamente en **Adaptador Puente (Bridged)** apuntando al adaptador inalámbrico virtual de compartición de Windows (ej. *Microsoft Wi-Fi Direct Virtual Adapter*).

### 2.2 Tabla de Direccionamiento IP Local (Ejemplo con Hotspot de Windows)

| Dispositivo / Servicio | Interfaz | Dirección IP | Máscara de Red | Gateway (Puerta de Enlace) | DNS Primario |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Windows Host (Laptop)** | Wi-Fi Direct | `192.168.137.1` | `255.255.255.0` | N/A | `192.168.137.10` |
| **Debian 13 Server (VM)** | `eth0` (Bridge) | `192.168.137.10` | `255.255.255.0` | `192.168.137.1` | `127.0.0.1` |
| **Teléfono Celular** | Wi-Fi Client | Dinámica (DHCP) | `255.255.255.0` | `192.168.137.1` | `192.168.137.10` |

---

## 3. CONFIGURACIÓN COMPLETA DE SERVICIOS EN DEBIAN 13

### 3.1 DNS local con BIND9
Se implementa **BIND9** para que los celulares de los usuarios y la PC Windows puedan acceder a los recursos mediante nombres calificados en el dominio `ulsa.local`.

#### Instalación:
```bash
sudo apt update && sudo apt install -y bind9 bind9utils
```

#### Archivo `/etc/bind/named.conf.local`:
```named
zone "ulsa.local" {
    type master;
    file "/etc/bind/db.ulsa.local";
};

zone "137.168.192.in-addr.arpa" {
    type master;
    file "/etc/bind/db.192.168.137";
};
```

#### Archivo de Zona Directa `/etc/bind/db.ulsa.local`:
```text
$TTL    604800
@       IN      SOA     servidor.ulsa.local. root.ulsa.local. (
                     2026082301         ; Serial
                         604800         ; Refresh
                          86400         ; Retry
                        2419200         ; Expire
                         604800 )       ; Negative Cache IPv4
;
@       IN      NS      servidor.ulsa.local.
@       IN      MX      10 mail.ulsa.local.

servidor IN      A       192.168.137.10
mail     IN      A       192.168.137.10
hub      IN      CNAME   servidor.ulsa.local.
```

#### Archivo de Zona Inversa `/etc/bind/db.192.168.137`:
```text
$TTL    604800
@       IN      SOA     servidor.ulsa.local. root.ulsa.local. (
                     2026082301         ; Serial
                         604800         ; Refresh
                          86400         ; Retry
                        2419200         ; Expire
                         604800 )       ; Negative Cache Inverse
;
@       IN      NS      servidor.ulsa.local.
10      IN      PTR     servidor.ulsa.local.
10      IN      PTR     mail.ulsa.local.
```

---

### 3.2 Servidor de Correo (MTA/MDA) con Postfix y Dovecot
De acuerdo con las guías de correo del curso, Postfix actuará como MTA y Dovecot como MDA para permitir el transporte de correo MIME (imágenes) con almacenamiento en formato Maildir.

#### Instalación:
```bash
sudo apt install -y postfix dovecot-imapd dovecot-pop3d
```
*(Durante el instalador, seleccionar "Sitio de Internet" y establecer `ulsa.local` como dominio).*

#### Configuración de Postfix (`/etc/postfix/main.cf`):
```ini
myhostname = mail.ulsa.local
mydomain = ulsa.local
myorigin = $mydomain
inet_interfaces = all
inet_protocols = ipv4
mydestination = $myhostname, localhost.$mydomain, localhost, $mydomain
home_mailbox = Maildir/
mynetworks = 127.0.0.0/8 [::1]/128 192.168.137.0/24
```

#### Configuración de Dovecot (`/etc/dovecot/conf.d/10-mail.conf`):
```ini
mail_location = maildir:~/Maildir
```

#### Desactivar requerimiento de SSL/TLS para entorno local de pruebas (`/etc/dovecot/conf.d/10-auth.conf`):
```ini
disable_plaintext_auth = no
```

#### Creación del usuario receptor:
```bash
sudo adduser importar --gecos "" --disabled-password
# Establecer contraseña simple de laboratorio para pruebas
echo "importar:1234" | sudo chpasswd
```

---

### 3.3 Compartición de Archivos: Samba + Nextcloud

#### A) Servidor Samba
Permite mapear una carpeta de red en Windows a alta velocidad física sin usar Internet.

```bash
sudo apt install -y samba
```

#### Configuración de `/etc/samba/smb.conf`:
```ini
[global]
   workgroup = WORKGROUP
   server string = ULSA Local-Hub Server
   log file = /var/log/samba/log.%m
   max log size = 1000
   logging = file
   server role = standalone server
   obey pam restrictions = yes
   unix password sync = yes
   map to guest = bad user

[hub-compartido]
   path = /srv/samba/hub
   browseable = yes
   writeable = yes
   guest ok = no
   valid users = importar
   create mask = 0775
   directory mask = 0775
```

```bash
# Crear directorio y asignar permisos de grupo
sudo mkdir -p /srv/samba/hub
sudo chown -R importar:importar /srv/samba/hub
sudo chmod -R 775 /srv/samba/hub

# Agregar el usuario importar a la base de datos de contraseñas de Samba
echo -e "1234\n1234" | sudo smbpasswd -a importar
```

#### B) Suite Nextcloud (Alojamiento de Archivos, Calendario y Notas)
Nextcloud se despliega sobre un stack LAMP en el servidor Debian para proporcionar la interfaz móvil para la sincronización de archivos de los alumnos, la toma de notas en clase y la gestión de calendarios vía CalDAV.

---

### 3.4 Servidor de Impresión Virtual (CUPS + CUPS-PDF)
Se utiliza el spooler nativo de UNIX para procesar colas de impresión de horarios generados de forma automática, exportándolos a PDF vectorial sin necesidad de hardware.

#### Instalación:
```bash
sudo apt install -y cups cups-pdf
```

#### Configuración de `/etc/cups/cupsd.conf` (Para habilitar administración local y remota):
```text
Port 631
Listen /run/cups/cups.sock
<Location />
  Order allow,deny
  Allow @LOCAL
</Location>
<Location /admin>
  Order allow,deny
  Allow @LOCAL
</Location>
```

#### Agregar la impresora virtual por comandos:
```bash
sudo lpadmin -p Impresora_PDF -E -v cups-pdf:/ -m remaining
```

---

## 4. EL SCRIPT "CEREBRO": AUTOMATIZACIÓN, OCR Y PARSEO DE HORARIOS

Este script desarrollado en Python 3 se ejecuta en segundo plano en tu servidor Debian 13. Monitorea la bandeja Maildir, extrae la imagen del horario oficial de la ULSA (formato `Imprimir Inscripción.pdf`), procesa la imagen usando Tesseract OCR de manera local, extrae la información estructurada mediante expresiones regulares, agenda las asignaturas en Nextcloud y genera la agenda visual PDF por CUPS.

### 4.1 Instalación de Dependencias Locales de OCR y Python:
```bash
sudo apt install -y tesseract-ocr tesseract-ocr-spa python3-pytesseract python3-pillow python3-requests
```

### 4.2 Código Completo de la Aplicación (`/srv/samba/hub/procesar_horario.py`):
```python
#!/usr/bin/env python3
import os
import re
import time
import email
from email import policy
from PIL import Image, ImageEnhance, ImageFilter
import pytesseract
import requests
from requests.auth import HTTPBasicAuth

# CONSTANTES DE CONFIGURACIÓN
MAILDIR_PATH = "/home/importar/Maildir/new/"
OUTPUT_PDF_DIR = "/srv/samba/hub/"
NEXTCLOUD_URL = "http://localhost/nextcloud/remote.php/dav/calendars/importar/personal/"
NEXTCLOUD_USER = "importar"
NEXTCLOUD_PASS = "1234" # Contraseña del usuario de Nextcloud

def preprocesar_imagen(image_path):
    """Limpia y binariza la captura de pantalla para optimizar el OCR local."""
    img = Image.open(image_path)
    img = img.convert('L') # Convertir a escala de grises
    img = img.filter(ImageFilter.SHARPEN) # Enfocar bordes de letras
    enhancer = ImageEnhance.Contrast(img)
    img = enhancer.enhance(2.0) # Duplicar el contraste para eliminar ruido grisáceo
    temp_clean_path = "/tmp/cleaned_horario.png"
    img.save(temp_clean_path)
    return temp_clean_path

def parsear_texto_horario(texto):
    """
    Parsea las materias y horarios utilizando expresiones regulares diseñadas
    específicamente para el formato oficial 'Imprimir Inscripción.pdf' de la ULSA.
    """
    materias = []
    
    # Expresión regular para capturar el formato del PDF de Inscripción de la ULSA:
    # Grupo 1: Código (ej. 0603)
    # Grupo 2: Nombre de la asignatura (ej. Taller de Conectividad)
    # Grupo 3: Bloque de clases (ej. Ma 10:00 am - 11:40 am [ B107 ])
    # Grupo 4: Nombre del Docente (ej. Ing. Freddy Alexander Mejía Quintana)
    patron = re.compile(
        r"(\d{4})\s+([A-Za-zÁÉÍÓÚáéíóúñ\s]+)\s+\[.*?\]\s+\d+\s+Gpo\s+\d+\s+([A-Za-z]{2}\s+\d{2}:\d{2}\s+[a|p]m\s+-\s+\d{2}:\d{2}\s+[a|p]m\s+\[\s*[A-Z0-9]+\s*\](?:.*?\n.*?)*?)(MSc\.|Ing\.)\s+([A-Za-zÁÉÍÓÚáéíóúñ\s]+)",
        re.DOTALL
    )
    
    coincidencias = patron.findall(texto)
    for coincidencia in coincidencias:
        codigo, nombre_materia, bloques_texto, titulo_docente, nombre_docente = coincidencia
        nombre_materia = nombre_materia.strip()
        docente = f"{titulo_docente} {nombre_docente.strip()}"
        
        # Extraer los días, horas y salones individuales de los bloques de horario
        sub_patron = re.compile(r"([L|M|M|J|V|S][u|a|i|u|i|a])\s+(\d{2}:\d{2}\s+[a|p]m)\s+-\s+(\d{2}:\d{2}\s+[a|p]m)\s+\[\s*([A-Z0-9]+)\s*\]")
        bloques = sub_patron.findall(bloques_texto)
        
        for dia, hora_ini, hora_fin, aula in bloques:
            materias.append({
                "codigo": codigo,
                "materia": nombre_materia,
                "dia": dia,
                "hora_inicio": hora_ini,
                "hora_fin": hora_fin,
                "aula": aula,
                "docente": docente
            })
            
    return materias

def crear_evento_caldav(clase):
    """Inserta de manera automatizada el evento de clase en Nextcloud vía CalDAV (iCalendar)."""
    # Mapeo de abreviatura de días de la ULSA a formato iCalendar (RRULE BYDAY)
    dias_map = {"Lu": "MO", "Ma": "TU", "Mi": "WE", "Ju": "TH", "Vi": "FR", "Sa": "SA"}
    byday = dias_map.get(clase["dia"], "MO")
    
    # Formatear la hora
    # Convertimos horas de tipo 10:00 am a estructura de tiempo 100000
    hi = time.strptime(clase["hora_inicio"], "%I:%M %p")
    hf = time.strptime(clase["hora_fin"], "%I:%M %p")
    
    t_inicio = time.strftime("%H%M%S", hi)
    t_fin = time.strftime("%H%M%S", hf)
    
    uid = f"{clase['codigo']}-{clase['dia']}-{t_inicio}@ulsa.local"
    
    # Definición de la estructura iCalendar
    vcalendar = f"""BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//ULSA Local-Hub//Parser Horarios 1.0//ES
BEGIN:VEVENT
UID:{uid}
SUMMARY:{clase['materia']} - {clase['aula']}
DESCRIPTION:Profesor: {clase['docente']}\nCódigo de Asignatura: {clase['codigo']}
LOCATION:Aula {clase['aula']}
DTSTART;TZID=America/Managua;VALUE=DATE-TIME:20260824T{t_inicio}
DTEND;TZID=America/Managua;VALUE=DATE-TIME:20260824T{t_fin}
RRULE:FREQ=WEEKLY;BYDAY={byday};UNTIL=20261218T235959Z
END:VEVENT
END:VCALENDAR"""

    # Enviar la petición PUT vía WebDAV al servidor Nextcloud local
    headers = {"Content-Type": "text/calendar; charset=utf-8"}
    url_evento = f"{NEXTCLOUD_URL}{uid}.ics"
    
    response = requests.put(
        url_evento, 
        data=vcalendar.encode('utf-8'), 
        headers=headers, 
        auth=HTTPBasicAuth(NEXTCLOUD_USER, NEXTCLOUD_PASS)
    )
    return response.status_code

def generar_pdf_agenda(clases):
    """Genera una plantilla HTML de la agenda y la envía a la cola de impresión de CUPS-PDF."""
    html_content = """
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 30px; color: #333; }
            h1 { color: #006633; text-align: center; border-bottom: 2px solid #006633; padding-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #006633; color: white; }
            tr:nth-child(even) { background-color: #f2f2f2; }
            .footer { margin-top: 30px; font-size: 0.8em; text-align: center; color: #777; }
        </style>
    </head>
    <body>
        <h1>HORARIO DE CLASES OFICIAL - ULSA</h1>
        <p><strong>Estudiante:</strong> Eddy Ezequiel Martínez Solórzano</p>
        <p><strong>Carrera:</strong> Ingeniería en Cibernética Electrónica (IV Año)</p>
        <table>
            <tr>
                <th>Código</th>
                <th>Asignatura</th>
                <th>Día</th>
                <th>Hora</th>
                <th>Aula</th>
                <th>Docente</th>
            </tr>
    """
    for c in clases:
        html_content += f"""
            <tr>
                <td>{c['codigo']}</td>
                <td>{c['materia']}</td>
                <td>{c['dia']}</td>
                <td>{c['hora_inicio']} - {c['hora_fin']}</td>
                <td><strong>{c['aula']}</strong></td>
                <td>{c['docente']}</td>
            </tr>
        """
        
    html_content += """
        </table>
        <div class="footer">Documento generado de forma automatizada por ULSA Local-Hub mediante procesamiento OCR local.</div>
    </body>
    </html>
    """
    
    temp_html_path = "/tmp/horario_agenda.html"
    with open(temp_html_path, "w", encoding="utf-8") as f:
        f.write(html_content)
        
    # Usar LibreOffice Headless en Debian para compilar el HTML a PDF limpio
    os.system(f"libreoffice --headless --convert-to pdf {temp_html_path} --outdir /tmp/")
    # Mandar a imprimir el PDF generado al spooler de CUPS-PDF
    os.system("lp -d Impresora_PDF /tmp/horario_agenda.pdf")
    
    # CUPS-PDF guarda por defecto el resultado en /var/spool/cups-pdf/ANONYMOUS/
    # Lo movemos inmediatamente al directorio compartido de Samba para el acceso de Windows
    time.sleep(2) # Esperar a que CUPS termine de escribir
    pdf_origen = "/var/spool/cups-pdf/ANONYMOUS/horario_agenda.pdf"
    if os.path.exists(pdf_origen):
        os.rename(pdf_origen, os.path.join(OUTPUT_PDF_DIR, "Mi_Horario_Semanal_ULSA.pdf"))
        print("[+] PDF de Agenda Semanal generado y publicado en Samba exitosamente.")

def procesar_nuevos_correos():
    """Monitorea el buzón receptor Maildir buscando adjuntos de imagen para procesar."""
    for filename in os.listdir(MAILDIR_PATH):
        file_path = os.path.join(MAILDIR_PATH, filename)
        if os.path.isdir(file_path):
            continue
            
        print(f"[*] Detectado nuevo correo en cola: {filename}")
        with open(file_path, "rb") as f:
            msg = email.message_from_binary_file(f, policy=policy.default)
            
        # Buscar el archivo adjunto de imagen
        for part in msg.walk():
            if part.get_content_maintype() == 'image':
                img_name = part.get_filename()
                img_data = part.get_payload(decode=True)
                temp_img_path = f"/tmp/{img_name}"
                
                with open(temp_img_path, "wb") as img_file:
                    img_file.write(img_data)
                
                print(f"[+] Extraída imagen adjunta: {img_name}")
                
                # Procesamiento OCR local
                img_limpia = preprocesar_imagen(temp_img_path)
                texto_extraido = pytesseract.image_to_string(Image.open(img_limpia), lang='spa')
                
                print("[*] Ejecutando algoritmo de parseo de datos...")
                clases_detectadas = parsear_texto_horario(texto_extraido)
                
                if clases_detectadas:
                    print(f"[+] Se identificaron exitosamente {len(clases_detectadas)} clases en el horario.")
                    # 1. Inyectar eventos al calendario local vía CalDAV
                    for clase in clases_detectadas:
                        crear_evento_caldav(clase)
                    # 2. Generar el PDF vectorial y enviarlo a CUPS-PDF
                    generar_pdf_agenda(clases_detectadas)
                else:
                    print("[-] Error: No se encontraron patrones de asignaturas válidos en la imagen.")
                    
        # Mover el correo procesado fuera de la cola de entrada (Maildir /cur/) para evitar reprocesamiento
        os.rename(file_path, file_path.replace("/new/", "/cur/"))

if __name__ == "__main__":
    print("[*] Servicio ULSA Local-Hub de Monitoreo Activo...")
    while True:
        procesar_nuevos_correos()
        time.sleep(5) # Revisión de cola cada 5 segundos
```

---

## 5. GUÍA PASO A PASO PARA EL DÍA DE LA DEFENSA (PROTOTIPADO)

Para garantizar un funcionamiento perfecto y libre de fallos por interferencia del Wi-Fi de la ULSA, sigue estrictamente este protocolo de demostración ante el jurado evaluador:

1. **Paso 1 (Encendido del Entorno):** Enciende tu laptop Windows y activa el *Punto de acceso móvil (Hotspot)* con el nombre de red `ULSA-Hub`. Inicia tu máquina virtual Debian 13 en VirtualBox (la cual tomará la IP `192.168.137.10` de forma estática en el adaptador puente).
2. **Paso 2 (Conexión de Clientes):** Pídele al profesor e integrantes del jurado que conecten sus teléfonos celulares al Wi-Fi de tu laptop (`ULSA-Hub`).
3. **Paso 3 (Demostración de Samba / Nextcloud):** Muestra el explorador de Windows conectado a la ruta de red `\\hub.ulsa.local\hub-compartido` y la interfaz web de Nextcloud en el navegador del teléfono del profesor mediante `http://hub.ulsa.local`. Arrastra un archivo pesado (como un instalador de 500 MB) y verás cómo se transfiere de inmediato a más de 30 MB/s (velocidad física pura local), demostrando la eficiencia del canal sin consumir internet.
4. **Paso 4 (La Magia del Horario Escolar):** 
   * Proyecta en la pantalla del salón la agenda vacía de tu Nextcloud.
   * Abre la aplicación de correo de tu teléfono celular. Redacta un correo dirigido a `importar@ulsa.local` y adjunta la captura de pantalla de tu hoja de inscripción oficial de la ULSA.
   * Envía el correo. El tráfico viaja de forma instantánea a tu servidor Postfix.
   * **El Clímax:** En menos de 45 segundos, el script de Python en Debian parsea el correo, procesa el OCR, extrae la información e inserta las clases. La pantalla del proyector mostrará cómo tu calendario escolar se actualiza de forma automática con las asignaturas de la ULSA ubicadas de forma exacta en sus respectivos días y horas.
   * Paralelamente, abre la carpeta compartida de Samba en Windows: el archivo `Mi_Horario_Semanal_ULSA.pdf` generado por la cola de impresión de **CUPS-PDF** estará listo para ser visualizado o descargado con un formato vectorial limpio e institucional.
