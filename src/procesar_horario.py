#!/usr/bin/env python3
"""
ULSA Local-Hub - Motor de Automatización OCR & Google Calendar Sync
Desarrollado para: Taller de Conectividad (ULSA)
Estudiante: Eddy Ezequiel Martínez Solórzano
"""

import os
import re
import time
import email
from email import policy
from datetime import datetime, timedelta
from PIL import Image, ImageEnhance, ImageFilter
import pytesseract

# Importaciones oficiales de la Google Calendar API
try:
    from google.auth.transport.requests import Request
    from google.oauth2.credentials import Credentials
    from google_auth_oauthlib.flow import InstalledAppFlow
    from googleapiclient.discovery import build
    GOOGLE_API_DISPONIBLE = True
except ImportError:
    GOOGLE_API_DISPONIBLE = False

# CONSTANTES Y CONFIGURACIÓN
MAILDIR_PATH = "/home/importar/Maildir/new/"
OUTPUT_PDF_DIR = "/srv/samba/hub/"
SCOPES = ['https://www.googleapis.com/auth/calendar.events']
CREDENTIALS_FILE = "/srv/samba/hub/credentials.json"
TOKEN_FILE = "/srv/samba/hub/token.json"

# Mapeo de días de la ULSA a códigos iCalendar / Google Recurrence
DIAS_MAP = {
    "Lu": "MO",
    "Ma": "TU",
    "Mi": "WE",
    "Ju": "TH",
    "Vi": "FR",
    "Sa": "SA"
}

# Fechas aproximadas de finalización del cuatrimestre activo (Cuatrimestre IIIC-2026)
UNTIL_DATE = "20261218T235959Z"

def preprocesar_imagen(image_path):
    """
    Mejora la calidad visual de la imagen para maximizar la tasa de éxito de Tesseract OCR.
    """
    img = Image.open(image_path)
    img = img.convert('L') # Convertir a escala de grises
    img = img.filter(ImageFilter.SHARPEN) # Aumentar nitidez de los bordes
    enhancer = ImageEnhance.Contrast(img)
    img = enhancer.enhance(2.5) # Elevar contraste
    temp_clean_path = "/tmp/cleaned_horario.png"
    img.save(temp_clean_path)
    return temp_clean_path

def parsear_texto_horario(texto):
    """
    Algoritmo de extracción dinámico utilizando Expresiones Regulares.
    Detecta automáticamente N cantidad de materias y N bloques horarios.
    """
    materias = []
    
    # Normalizar espacios y saltos de línea del OCR
    lineas = texto.split('\n')
    texto_limpio = "\n".join([l.strip() for l in lineas if l.strip()])
    
    # Regex 1: Buscar bloques de asignaturas con código de 4 dígitos (ej. 0808, 0305, 0303, 0603)
    patron_materia = re.compile(
        r'(\d{4})\s+([A-Za-zÁÉÍÓÚáéíóúñ\s]+?)(?=\s*\[|\s*Gpo|\s*\d{2}:|\s*MSc|\s*Ing|\n|$)',
        re.MULTILINE
    )
    
    # Regex 2: Buscar bloques de horario individuales dentro del texto (ej. Ju 01:00 pm - 02:40 pm [ G105 ])
    patron_bloque = re.compile(
        r'(Lu|Ma|Mi|Ju|Vi|Sa)\s+(\d{1,2}:\d{2}\s*[ap]m)\s*-\s*(\d{1,2}:\d{2}\s*[ap]m)\s*\[\s*([A-Z0-9]+)\s*\]',
        re.IGNORECASE
    )

    # Regex 3: Buscar nombre de docente (MSc. o Ing.)
    patron_docente = re.compile(
        r'(MSc\.|Ing\.)\s+([A-Za-zÁÉÍÓÚáéíóúñ\s]+)',
        re.IGNORECASE
    )

    # Extraer coincidencias de materias
    coincidencias_materias = list(re.finditer(r'(\d{4})\s+([A-Za-zÁÉÍÓÚáéíóúñ\s]{3,40})', texto_limpio))
    
    for i, match in enumerate(coincidencias_materias):
        codigo = match.group(1)
        nombre_materia = match.group(2).strip()
        
        # Ignorar encabezados del reporte ULSA
        if "CÓDIGO" in nombre_materia.upper() or "FECHA" in nombre_materia.upper():
            continue
            
        # Definir el segmento de texto asignado a esta materia
        inicio = match.start()
        fin = coincidencias_materias[i+1].start() if i+1 < len(coincidencias_materias) else len(texto_limpio)
        segmento = texto_limpio[inicio:fin]
        
        # Extraer bloques horarios en este segmento
        bloques = patron_bloque.findall(segmento)
        
        # Extraer docente en este segmento
        docente_match = patron_docente.search(segmento)
        docente = f"{docente_match.group(1)} {docente_match.group(2).strip()}" if docente_match else "Docente No Asignado"
        
        for dia, hora_ini, hora_fin, aula in bloques:
            # Formatear el día normalizado (Capitalizado: Lu, Ma, Mi, Ju, Vi, Sa)
            dia_norm = dia.capitalize()
            materias.append({
                "codigo": codigo,
                "materia": nombre_materia,
                "dia": dia_norm,
                "hora_inicio": hora_ini.strip().lower(),
                "hora_fin": hora_fin.strip().lower(),
                "aula": aula.upper(),
                "docente": docente
            })

    return materias

def obtener_servicio_google_calendar():
    """
    Autentica y devuelve el cliente oficial de la API de Google Calendar.
    """
    if not GOOGLE_API_DISPONIBLE:
        print("[-] Error: Las librerías de Google API no están instaladas.")
        return None
        
    creds = None
    if os.path.exists(TOKEN_FILE):
        creds = Credentials.from_authorized_user_file(TOKEN_FILE, SCOPES)
        
    if not creds or not creds.valid:
        if creds and creds.expired and creds.refresh_token:
            creds.refresh(Request())
        else:
            if not os.path.exists(CREDENTIALS_FILE):
                print(f"[-] No se encontró el archivo de credenciales Google: {CREDENTIALS_FILE}")
                return None
            flow = InstalledAppFlow.from_client_secrets_file(CREDENTIALS_FILE, SCOPES)
            creds = flow.run_local_server(port=0)
            
        with open(TOKEN_FILE, 'w') as token:
            token.write(creds.to_json())

    return build('calendar', 'v3', credentials=creds)

def crear_eventos_google_calendar(clases):
    """
    Inserta las clases en Google Calendar con notificaciones emergentes de 20 minutos.
    """
    service = obtener_servicio_google_calendar()
    if not service:
        print("[-] Omitiendo sincronización con Google Calendar por falta de credenciales.")
        return False

    print("[*] Conectando con Google Calendar API...")
    
    for clase in clases:
        byday = DIAS_MAP.get(clase["dia"], "MO")
        
        # Parsear las horas
        hi = time.strptime(clase["hora_inicio"], "%I:%M %p")
        hf = time.strptime(clase["hora_fin"], "%I:%M %p")
        
        t_inicio = time.strftime("%H:%M:%S", hi)
        t_fin = time.strftime("%H:%M:%S", hf)
        
        # Usar como fecha base el próximo lunes
        hoy = datetime.now()
        proximo_lunes = hoy + timedelta(days=(0 - hoy.weekday()) % 7)
        fecha_str = proximo_lunes.strftime("%Y-%m-%d")
        
        evento = {
            'summary': f"📚 {clase['materia']} [{clase['aula']}]",
            'location': f"Aula {clase['aula']} - ULSA",
            'description': f"Asignatura: {clase['materia']}\nCódigo: {clase['codigo']}\nDocente: {clase['docente']}\nAula: {clase['aula']}",
            'start': {
                'dateTime': f"{fecha_str}T{t_inicio}",
                'timeZone': 'America/Managua',
            },
            'end': {
                'dateTime': f"{fecha_str}T{t_fin}",
                'timeZone': 'America/Managua',
            },
            'recurrence': [
                f"RRULE:FREQ=WEEKLY;BYDAY={byday};UNTIL={UNTIL_DATE}"
            ],
            'reminders': {
                'useDefault': False,
                'overrides': [
                    {'method': 'popup', 'minutes': 20}, # Notificación silenciosa en pantalla 20 min antes
                ],
            },
        }

        try:
            event = service.events().insert(calendarId='primary', body=evento).execute()
            print(f"[+] Evento creado en Google Calendar: {clase['materia']} ({clase['dia']} {clase['hora_inicio']})")
        except Exception as e:
            print(f"[-] Error al insertar evento en Google Calendar: {e}")

    return True

def generar_pdf_agenda(clases):
    """
    Genera el PDF vectorial con el horario y lo coloca en el share de Samba.
    """
    html_content = f"""
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <style>
            body {{ font-family: 'Helvetica', 'Arial', sans-serif; margin: 30px; color: #2c3e50; }}
            .header {{ text-align: center; border-bottom: 3px solid #006633; padding-bottom: 12px; margin-bottom: 20px; }}
            .header h1 {{ color: #006633; margin: 0; font-size: 24px; }}
            .header p {{ color: #7f8c8d; margin: 5px 0 0 0; font-size: 14px; }}
            .info-card {{ background: #ecf0f1; border-radius: 6px; padding: 15px; margin-bottom: 20px; }}
            .info-card p {{ margin: 4px 0; font-size: 14px; }}
            table {{ width: 100%; border-collapse: collapse; margin-top: 10px; }}
            th, td {{ border: 1px solid #bdc3c7; padding: 10px; text-align: left; font-size: 13px; }}
            th {{ background-color: #006633; color: white; text-transform: uppercase; font-size: 12px; }}
            tr:nth-child(even) {{ background-color: #f8f9fa; }}
            .badge-aula {{ background: #e74c3c; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold; }}
            .footer {{ margin-top: 40px; font-size: 11px; text-align: center; color: #95a5a6; border-top: 1px solid #ddd; padding-top: 10px; }}
        </style>
    </head>
    <body>
        <div class="header">
            <h1>UNIVERSIDAD TECNOLÓGICA LA SALLE</h1>
            <p>Reporte Oficial de Agenda Semanal Automatizada — ULSA Local-Hub</p>
        </div>

        <div class="info-card">
            <p><strong>Estudiante:</strong> Eddy Ezequiel Martínez Solórzano</p>
            <p><strong>Carrera:</strong> Ingeniería en Cibernética Electrónica (IV Año)</p>
            <p><strong>Fecha de Generación:</strong> {datetime.now().strftime('%d/%m/%Y %H:%M:%S')}</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Asignatura</th>
                    <th>Día</th>
                    <th>Horario</th>
                    <th>Aula</th>
                    <th>Docente</th>
                </tr>
            </thead>
            <tbody>
    """
    for c in clases:
        html_content += f"""
            <tr>
                <td><strong>{c['codigo']}</strong></td>
                <td>{c['materia']}</td>
                <td>{c['dia']}</td>
                <td>{c['hora_inicio']} - {c['hora_fin']}</td>
                <td><span class="badge-aula">{c['aula']}</span></td>
                <td>{c['docente']}</td>
            </tr>
        """

    html_content += """
            </tbody>
        </table>
        <div class="footer">
            Documento generado de forma autónoma mediante procesamiento OCR local (Tesseract) e impresión vectorial CUPS-PDF.<br>
            ULSA Local-Hub Edge Server | Servidor de Borde Portable
        </div>
    </body>
    </html>
    """

    temp_html_path = "/tmp/horario_agenda.html"
    with open(temp_html_path, "w", encoding="utf-8") as f:
        f.write(html_content)

    os.system(f"libreoffice --headless --convert-to pdf {temp_html_path} --outdir /tmp/")
    os.system("lp -d Impresora_PDF /tmp/horario_agenda.pdf")

    time.sleep(2)
    pdf_origen = "/var/spool/cups-pdf/ANONYMOUS/horario_agenda.pdf"
    if os.path.exists(pdf_origen):
        os.rename(pdf_origen, os.path.join(OUTPUT_PDF_DIR, "Mi_Horario_Semanal_ULSA.pdf"))
        print("[+] PDF de Agenda Semanal generado y publicado exitosamente en Samba share.")

def procesar_nuevos_correos():
    """
    Inspecciona la bandeja de entrada Maildir de importar@ulsa.local.
    """
    if not os.path.exists(MAILDIR_PATH):
        return

    for filename in os.listdir(MAILDIR_PATH):
        file_path = os.path.join(MAILDIR_PATH, filename)
        if os.path.isdir(file_path):
            continue

        print(f"[*] Detectado nuevo correo en cola: {filename}")
        with open(file_path, "rb") as f:
            msg = email.message_from_binary_file(f, policy=policy.default)

        for part in msg.walk():
            if part.get_content_maintype() == 'image':
                img_name = part.get_filename() or "horario.png"
                img_data = part.get_payload(decode=True)
                temp_img_path = f"/tmp/{img_name}"

                with open(temp_img_path, "wb") as img_file:
                    img_file.write(img_data)

                print(f"[+] Extraída imagen adjunta: {img_name}")

                img_limpia = preprocesar_imagen(temp_img_path)
                texto_extraido = pytesseract.image_to_string(Image.open(img_limpia), lang='spa')

                clases_detectadas = parsear_texto_horario(texto_extraido)

                if clases_detectadas:
                    print(f"[+] Se identificaron exitosamente {len(clases_detectadas)} sesiones de clase dinámicas.")
                    crear_eventos_google_calendar(clases_detectadas)
                    generar_pdf_agenda(clases_detectadas)
                else:
                    print("[-] Advertencia: No se encontraron patrones de asignaturas válidos en la imagen.")

        # Mover correo procesado a /cur/
        destino_cur = file_path.replace("/new/", "/cur/")
        os.rename(file_path, destino_cur)

if __name__ == "__main__":
    print("[*] Servicio ULSA Local-Hub activo (Monitoreo de Maildir & OCR)...")
    while True:
        try:
            procesar_nuevos_correos()
        except Exception as err:
            print(f"[-] Error en bucle principal: {err}")
        time.sleep(5)
