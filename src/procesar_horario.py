#!/opt/chatosync-venv/bin/python
"""
ChatoSync - Motor Autónomo de Procesamiento OCR, Generación de Calendarios y Sincronización
Desarrollado para: Taller de Conectividad (ULSA)
Estudiante: Eddy Ezequiel Martínez Solórzano
"""

import os
import sys
import re
import time
import json
import email
import shutil
from email import policy
from datetime import datetime, timedelta
from PIL import Image, ImageEnhance, ImageFilter
import pytesseract

# CONSTANTES Y CONFIGURACIÓN
MAILDIR_PATH = "/home/importar/Maildir/new/"
SAMBA_ENTRADA = "/srv/samba/hub/entrada/"
SAMBA_PROCESADOS = "/srv/samba/hub/procesados/"
OUTPUT_PDF_DIR = "/srv/samba/hub/"
OUTPUT_ICS_DIR = "/srv/samba/hub/"
LOG_FILE = "/var/log/chatosync.log"

DIAS_MAP = {
    "Lu": "MO",
    "Ma": "TU",
    "Mi": "WE",
    "Ju": "TH",
    "Vi": "FR",
    "Sa": "SA"
}

DIAS_NOMBRE = {
    "Lu": "Lunes",
    "Ma": "Martes",
    "Mi": "Miércoles",
    "Ju": "Jueves",
    "Vi": "Viernes",
    "Sa": "Sábado"
}

UNTIL_DATE = "20261218T235959Z"

def log(msg):
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    formatted = f"[{timestamp}] {msg}"
    print(formatted)
    sys.stdout.flush()

def preprocesar_imagen(image_path):
    try:
        img = Image.open(image_path)
        img = img.convert('L')
        img = img.filter(ImageFilter.SHARPEN)
        enhancer = ImageEnhance.Contrast(img)
        img = enhancer.enhance(2.5)
        temp_clean_path = "/tmp/cleaned_horario.png"
        img.save(temp_clean_path)
        return temp_clean_path
    except Exception as e:
        log(f"[-] Error en preprocesamiento de imagen: {e}")
        return image_path

def parsear_texto_horario(texto):
    materias = []
    lineas = texto.split('\n')
    texto_limpio = "\n".join([l.strip() for l in lineas if l.strip()])
    
    patron_bloque = re.compile(
        r'(Lu|Ma|Mi|Ju|Vi|Sa)\s+(\d{1,2}:\d{2}\s*[ap]m)\s*-\s*(\d{1,2}:\d{2}\s*[ap]m)\s*\[\s*([A-Z0-9]+)\s*\]',
        re.IGNORECASE
    )

    patron_docente = re.compile(
        r'(MSc\.|Ing\.)\s+([A-Za-zÁÉÍÓÚáéíóúñ\s]+)',
        re.IGNORECASE
    )

    coincidencias_materias = list(re.finditer(r'(\d{4})\s+([A-Za-zÁÉÍÓÚáéíóúñ\s]{3,40})', texto_limpio))
    
    for i, match in enumerate(coincidencias_materias):
        codigo = match.group(1)
        nombre_materia = match.group(2).strip()
        
        if "CÓDIGO" in nombre_materia.upper() or "FECHA" in nombre_materia.upper() or "ESTUDIANTE" in nombre_materia.upper():
            continue
            
        inicio = match.start()
        fin = coincidencias_materias[i+1].start() if i+1 < len(coincidencias_materias) else len(texto_limpio)
        segmento = texto_limpio[inicio:fin]
        
        bloques = patron_bloque.findall(segmento)
        docente_match = patron_docente.search(segmento)
        docente = f"{docente_match.group(1)} {docente_match.group(2).strip()}" if docente_match else "Docente Asignado"
        
        for dia, hora_ini, hora_fin, aula in bloques:
            dia_norm = dia.capitalize()
            materias.append({
                "codigo": codigo,
                "materia": nombre_materia,
                "dia": dia_norm,
                "dia_completo": DIAS_NOMBRE.get(dia_norm, dia_norm),
                "hora_inicio": hora_ini.strip().lower(),
                "hora_fin": hora_fin.strip().lower(),
                "aula": aula.upper(),
                "docente": docente
            })

    return materias

def generar_ics_calendario(clases, ruta_salida="/srv/samba/hub/horario_ulsa.ics"):
    """
    Genera un archivo estándar .ics compatible con Google Calendar, Apple Calendar y Outlook
    con notificaciones silenciosas de 20 minutos antes de clase.
    """
    dias_offset = {"Lu": 0, "Ma": 1, "Mi": 2, "Ju": 3, "Vi": 4, "Sa": 5}
    hoy = datetime.now()
    lunes_base = hoy - timedelta(days=hoy.weekday())
    
    lineas = [
        "BEGIN:VCALENDAR",
        "VERSION:2.0",
        "PRODID:-//ULSA//ChatoSync Local-Hub//ES",
        "CALSCALE:GREGORIAN",
        "METHOD:PUBLISH",
        "X-WR-CALNAME:Horario ULSA - ChatoSync",
        "X-WR-TIMEZONE:America/Managua"
    ]
    
    for i, c in enumerate(clases):
        try:
            offset = dias_offset.get(c["dia"], 0)
            fecha_clase = lunes_base + timedelta(days=offset)
            
            hi = time.strptime(c["hora_inicio"], "%I:%M %p")
            hf = time.strptime(c["hora_fin"], "%I:%M %p")
            
            dt_start = fecha_clase.strftime("%Y%m%d") + f"T{hi.tm_hour:02d}{hi.tm_min:02d}00"
            dt_end = fecha_clase.strftime("%Y%m%d") + f"T{hf.tm_hour:02d}{hf.tm_min:02d}00"
            byday = DIAS_MAP.get(c["dia"], "MO")
            uid = f"chatosync-{c['codigo']}-{c['dia']}-{i}@ulsa.local"
            
            lineas.extend([
                "BEGIN:VEVENT",
                f"UID:{uid}",
                f"DTSTAMP:{datetime.now().strftime('%Y%m%dT%H%M%SZ')}",
                f"DTSTART;TZID=America/Managua:{dt_start}",
                f"DTEND;TZID=America/Managua:{dt_end}",
                f"RRULE:FREQ=WEEKLY;BYDAY={byday};UNTIL={UNTIL_DATE}",
                f"SUMMARY:📚 {c['materia']} [{c['aula']}]",
                f"LOCATION:Aula {c['aula']} - Universidad La Salle León",
                f"DESCRIPTION:Asignatura: {c['materia']}\\nCódigo: {c['codigo']}\\nDocente: {c['docente']}\\nAula: {c['aula']}",
                "BEGIN:VALARM",
                "TRIGGER:-PT20M",
                "ACTION:DISPLAY",
                f"DESCRIPTION:Recordatorio de clase: {c['materia']}",
                "END:VALARM",
                "END:VEVENT"
            ])
        except Exception as err:
            log(f"[-] Error formateando evento iCalendar: {err}")

    lineas.append("END:VCALENDAR")
    
    with open(ruta_salida, "w", encoding="utf-8") as f:
        f.write("\r\n".join(lineas) + "\r\n")
        
    log(f"[+] Archivo de calendario iCalendar generado exitosamente en: {ruta_salida}")
    return ruta_salida

def generar_pdf_agenda(clases, ruta_salida="/srv/samba/hub/Mi_Horario_Semanal_ULSA.pdf"):
    html_content = f"""
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <style>
            body {{ font-family: 'Helvetica', 'Arial', sans-serif; margin: 30px; color: #1e293b; }}
            .header {{ text-align: center; border-bottom: 3px solid #006633; padding-bottom: 12px; margin-bottom: 20px; }}
            .header h1 {{ color: #006633; margin: 0; font-size: 22px; }}
            .header p {{ color: #64748b; margin: 5px 0 0 0; font-size: 13px; }}
            .info-card {{ background: #f1f5f9; border-left: 4px solid #006633; border-radius: 4px; padding: 12px; margin-bottom: 20px; }}
            .info-card p {{ margin: 3px 0; font-size: 13px; }}
            table {{ width: 100%; border-collapse: collapse; margin-top: 10px; }}
            th, td {{ border: 1px solid #cbd5e1; padding: 9px; text-align: left; font-size: 12px; }}
            th {{ background-color: #006633; color: white; text-transform: uppercase; font-size: 11px; }}
            tr:nth-child(even) {{ background-color: #f8fafc; }}
            .badge-aula {{ background: #dc2626; color: white; padding: 2px 7px; border-radius: 4px; font-weight: bold; font-size: 11px; }}
            .footer {{ margin-top: 30px; font-size: 11px; text-align: center; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }}
        </style>
    </head>
    <body>
        <div class="header">
            <h1>UNIVERSIDAD TECNOLÓGICA LA SALLE</h1>
            <p>Reporte Oficial de Agenda Semanal Automatizada — ChatoSync Local-Hub</p>
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
                <td>{c['dia_completo']}</td>
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
            ChatoSync Edge Server | Servidor de Borde Portable ULSA
        </div>
    </body>
    </html>
    """

    temp_html = "/tmp/horario_agenda.html"
    with open(temp_html, "w", encoding="utf-8") as f:
        f.write(html_content)

    os.system(f"libreoffice --headless --convert-to pdf {temp_html} --outdir /tmp/ >/dev/null 2>&1")
    if os.path.exists("/tmp/horario_agenda.pdf"):
        shutil.copy("/tmp/horario_agenda.pdf", ruta_salida)
        log(f"[+] PDF de Agenda Semanal generado exitosamente en: {ruta_salida}")

def procesar_archivo_imagen(ruta_archivo):
    log(f"[*] Iniciando procesamiento OCR para: {ruta_archivo}")
    img_limpia = preprocesar_imagen(ruta_archivo)
    texto = pytesseract.image_to_string(Image.open(img_limpia), lang='spa')
    clases = parsear_texto_horario(texto)
    
    if clases:
        log(f"[+] ¡ÉXITO! Se detectaron {len(clases)} sesiones de clase en el horario.")
        for c in clases:
            log(f"    -> [{c['codigo']}] {c['materia']} | {c['dia_completo']} {c['hora_inicio']}-{c['hora_fin']} | Aula {c['aula']} | {c['docente']}")
        
        generar_ics_calendario(clases)
        generar_pdf_agenda(clases)
        
        # Guardar resultado en formato JSON para la interfaz web
        with open("/srv/samba/hub/ultimo_horario.json", "w", encoding="utf-8") as f:
            json.dump({
                "fecha_procesamiento": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                "archivo_origen": os.path.basename(ruta_archivo),
                "total_clases": len(clases),
                "clases": clases
            }, f, ensure_ascii=False, indent=2)
            
        return clases
    else:
        log("[-] No se detectaron patrones válidos de clases en la imagen.")
        return []

def escanear_carpeta_samba():
    if not os.path.exists(SAMBA_ENTRADA):
        os.makedirs(SAMBA_ENTRADA, exist_ok=True)
    if not os.path.exists(SAMBA_PROCESADOS):
        os.makedirs(SAMBA_PROCESADOS, exist_ok=True)
        
    for fname in os.listdir(SAMBA_ENTRADA):
        fpath = os.path.join(SAMBA_ENTRADA, fname)
        if os.path.isfile(fpath) and fname.lower().endswith(('.png', '.jpg', '.jpeg', '.bmp', '.pdf')):
            log(f"[+] Detectado nuevo archivo en carpeta compartida Samba: {fname}")
            procesar_archivo_imagen(fpath)
            destino = os.path.join(SAMBA_PROCESADOS, f"{int(time.time())}_{fname}")
            shutil.move(fpath, destino)
            log(f"[+] Archivo movido a carpeta procesados: {destino}")

def escanear_correos():
    if not os.path.exists(MAILDIR_PATH):
        return

    for fname in os.listdir(MAILDIR_PATH):
        fpath = os.path.join(MAILDIR_PATH, fname)
        if os.path.isdir(fpath):
            continue

        log(f"[*] Detectado nuevo correo en cola: {fname}")
        with open(fpath, "rb") as f:
            msg = email.message_from_binary_file(f, policy=policy.default)

        for part in msg.walk():
            if part.get_content_maintype() == 'image':
                img_name = part.get_filename() or "horario.png"
                img_data = part.get_payload(decode=True)
                temp_path = f"/tmp/{img_name}"
                with open(temp_path, "wb") as f_img:
                    f_img.write(img_data)
                log(f"[+] Extraída imagen de correo adjunto: {img_name}")
                procesar_archivo_imagen(temp_path)

        cur_path = fpath.replace("/new/", "/cur/")
        shutil.move(fpath, cur_path)

if __name__ == "__main__":
    if len(sys.argv) > 2 and sys.argv[1] == "--file":
        res = procesar_archivo_imagen(sys.argv[2])
        print(json.dumps(res, ensure_ascii=False))
        sys.exit(0)

    log("[*] Servicio ChatoSync activo. Monitoreando Samba (/entrada) y Correo (/Maildir)...")
    while True:
        try:
            escanear_carpeta_samba()
            escanear_correos()
        except Exception as e:
            log(f"[-] Error en bucle de monitoreo: {e}")
        time.sleep(3)
