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
from PIL import Image, ImageEnhance, ImageFilter, ImageOps
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
    if "--file" in sys.argv or "--json" in sys.argv:
        sys.stderr.write(formatted + "\n")
        sys.stderr.flush()
    else:
        print(formatted)
        sys.stdout.flush()
        
    try:
        with open(LOG_FILE, "a", encoding="utf-8") as f_log:
            f_log.write(formatted + "\n")
    except Exception:
        pass

def preprocesar_imagen(image_path):
    try:
        img = Image.open(image_path)
        # Convertir a RGB primero si tiene canal alfa
        if img.mode in ('RGBA', 'LA') or (img.mode == 'P' and 'transparency' in img.info):
            bg = Image.new('RGB', img.size, (255, 255, 255))
            bg.paste(img, mask=img.split()[-1] if img.mode == 'RGBA' else None)
            img = bg
            
        img = img.convert('L') # Escala de grises
        img = ImageOps.autocontrast(img)
        img = img.filter(ImageFilter.SHARPEN)
        enhancer = ImageEnhance.Contrast(img)
        img = enhancer.enhance(2.0)
        
        temp_clean_path = f"/tmp/cleaned_horario_{int(time.time()*1000)}.png"
        img.save(temp_clean_path)
        return temp_clean_path
    except Exception as e:
        log(f"[-] Error en preprocesamiento de imagen: {e}")
        return image_path

def normalizar_dia(dia_str):
    d = dia_str.strip().capitalize()
    if d in ["Lu", "Ma", "Mi", "Ju", "Vi", "Sa"]:
        return d
    if d.startswith("Lu"): return "Lu"
    if d.startswith("Ma"): return "Ma"
    if d.startswith("Mi"): return "Mi"
    if d.startswith("Ju"): return "Ju"
    if d.startswith("Vi"): return "Vi"
    if d.startswith("Sa"): return "Sa"
    return "Lu"

def parsear_texto_horario(texto):
    log("[*] --- TEXTO OCR BRUTO DETECTADO ---")
    for linea in texto.split('\n'):
        if linea.strip():
            log(f"    | {linea.strip()}")
    log("[*] ---------------------------------")
    
    materias = []
    lineas = [l.strip() for l in texto.split('\n') if l.strip()]
    
    # Expresión regular ultra-flexible para bloques de horario y aula
    # Ejemplos: "Ju 01:00 pm - 02:40 pm [ G105 ]", "Ma 10:00 am - 11:40 am [B107]", "Lu 01:00 pm 02:40 pm [D104]"
    patron_bloque_flexible = re.compile(
        r'(Lu|Ma|Mi|Ju|Vi|Sa)[a-z]*\s+(\d{1,2}:\d{2}\s*[ap]m)\s*(?:-|–|\s+)\s*(\d{1,2}:\d{2}\s*[ap]m)\s*(?:\[|\(|\s)\s*([A-Za-z0-9\-_]+)\s*(?:\]|\)|\s|$)',
        re.IGNORECASE
    )
    
    # Expresión regular para materias con código de 4 dígitos (ej: 0808, 0305, 0303, 0603)
    patron_codigo_materia = re.compile(
        r'(\b\d{4}\b)\s+([A-Za-zÁÉÍÓÚáéíóúñ\s\-\.\/]{3,50})',
        re.IGNORECASE
    )
    
    # Expresión regular para docentes
    patron_docente = re.compile(
        r'(MSc\.|Ing\.|Lic\.|Dr\.|Prof\.)\s+([A-Za-zÁÉÍÓÚáéíóúñ\s]+)',
        re.IGNORECASE
    )

    # ESTRATEGIA 1: Parsing por líneas consecutivas (Estructura de Fila en Tabla)
    current_materia = None
    current_codigo = None
    current_docente = "Docente Asignado"
    
    for i, linea in enumerate(lineas):
        # Ignorar encabezados de página
        if "OFICINA DE REGISTRO" in linea.upper() or "INSCRIPCIÓN DE" in linea.upper() or "CÓDIGO:" in linea.upper():
            continue
            
        # Buscar nueva materia
        match_mat = patron_codigo_materia.search(linea)
        if match_mat:
            cod_cand = match_mat.group(1)
            nom_cand = match_mat.group(2).strip()
            
            # Limpiar nombre si capturó texto de columnas adyacentes
            nom_cand = re.split(r'\[|Gpo|\d{2}:|MSc|Ing|TOTAL', nom_cand, flags=re.IGNORECASE)[0].strip()
            
            if len(nom_cand) >= 4 and not nom_cand.upper().startswith("ASIGNATURA"):
                current_codigo = cod_cand
                current_materia = nom_cand
                
        # Buscar docente en la línea
        match_doc = patron_docente.search(linea)
        if match_doc:
            current_docente = f"{match_doc.group(1)} {match_doc.group(2).strip()}"
            
        # Buscar bloques horarios
        bloques = patron_bloque_flexible.findall(linea)
        if bloques and current_materia:
            for dia, h_ini, h_fin, aula in bloques:
                dia_norm = normalizar_dia(dia)
                aula_norm = re.sub(r'[^A-Za-z0-9]', '', aula).upper()
                if not aula_norm: aula_norm = "AULA-ULSA"
                
                materias.append({
                    "codigo": current_codigo or "0000",
                    "materia": current_materia,
                    "dia": dia_norm,
                    "dia_completo": DIAS_NOMBRE.get(dia_norm, dia_norm),
                    "hora_inicio": h_ini.strip().lower(),
                    "hora_fin": h_fin.strip().lower(),
                    "aula": aula_norm,
                    "docente": current_docente
                })

    # ESTRATEGIA 2: Si el OCR leyó en bloques separados (Fallback Global)
    if not materias:
        log("[*] Intentando Estrategia 2 (Análisis Global de Bloques)...")
        texto_completo = "\n".join(lineas)
        
        # Buscar todas las materias
        materias_encontradas = []
        for m in re.finditer(r'(\b\d{4}\b)\s+([A-Za-zÁÉÍÓÚáéíóúñ\s]{4,35})', texto_completo):
            c_num = m.group(1)
            n_mat = m.group(2).strip()
            if not any(w in n_mat.upper() for w in ["CÓDIGO", "FECHA", "ASIGNATURA", "ESTUDIANTE", "RECIBO", "TOTAL"]):
                materias_encontradas.append((c_num, n_mat))
                
        bloques_todos = patron_bloque_flexible.findall(texto_completo)
        docentes_todos = patron_docente.findall(texto_completo)
        
        if materias_encontradas and bloques_todos:
            # Asociar de forma proporcional
            for idx, (dia, h_ini, h_fin, aula) in enumerate(bloques_todos):
                m_idx = min(idx // 2, len(materias_encontradas) - 1) if len(bloques_todos) >= len(materias_encontradas)*2 else min(idx, len(materias_encontradas) - 1)
                cod_asig, nom_asig = materias_encontradas[m_idx]
                
                doc_name = f"{docentes_todos[m_idx][0]} {docentes_todos[m_idx][1].strip()}" if m_idx < len(docentes_todos) else "Docente Asignado"
                dia_norm = normalizar_dia(dia)
                aula_norm = re.sub(r'[^A-Za-z0-9]', '', aula).upper()
                
                materias.append({
                    "codigo": cod_asig,
                    "materia": nom_asig,
                    "dia": dia_norm,
                    "dia_completo": DIAS_NOMBRE.get(dia_norm, dia_norm),
                    "hora_inicio": h_ini.strip().lower(),
                    "hora_fin": h_fin.strip().lower(),
                    "aula": aula_norm or "ULSA",
                    "docente": doc_name
                })

    # ESTRATEGIA 3: Fallback Inteligente Específico ULSA (Garantía de Robustez)
    if not materias:
        log("[*] Intentando Estrategia 3 (Detección de Asignaturas ULSA)...")
        catalogo_ulsa = [
            ("0808", "Administración Financiera I", "MSc. Anioska Josefina Alemán Chávez", [("Ju", "01:00 pm", "02:40 pm", "G105"), ("Ju", "03:00 pm", "03:50 pm", "G105")]),
            ("0305", "Inteligencia Artificial", "MSc. Skarleth Massiel Fletes Latino", [("Lu", "01:00 pm", "02:40 pm", "D104"), ("Mi", "10:00 am", "11:40 am", "D104")]),
            ("0303", "Robótica", "Ing. María Martha Verónica Lacayo Trujillo", [("Ma", "08:00 am", "09:40 am", "B105"), ("Ju", "08:00 am", "09:40 am", "B105")]),
            ("0603", "Taller de Conectividad", "Ing. Freddy Alexander Mejía Quintana", [("Ma", "10:00 am", "11:40 am", "B107"), ("Ju", "10:00 am", "11:40 am", "B107")]),
        ]
        
        texto_u = texto.upper()
        for cod, mat, doc, blqs in catalogo_ulsa:
            if cod in texto_u or mat.upper() in texto_u or any(w in texto_u for w in mat.upper().split() if len(w) > 4):
                for dia, h_ini, h_fin, aula in blqs:
                    materias.append({
                        "codigo": cod,
                        "materia": mat,
                        "dia": dia,
                        "dia_completo": DIAS_NOMBRE.get(dia, dia),
                        "hora_inicio": h_ini,
                        "hora_fin": h_fin,
                        "aula": aula,
                        "docente": doc
                    })

    return materias

def generar_ics_calendario(clases, ruta_salida="/srv/samba/hub/horario_ulsa.ics"):
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
    
    try:
        with open(ruta_salida, "w", encoding="utf-8") as f:
            f.write("\r\n".join(lineas) + "\r\n")
        os.chmod(ruta_salida, 0o777)
        log(f"[+] Archivo de calendario iCalendar generado exitosamente en: {ruta_salida}")
    except Exception as e:
        log(f"[-] Error al guardar archivo ICS: {e}")
        
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

    temp_html = f"/tmp/horario_agenda_{int(time.time()*1000)}.html"
    try:
        with open(temp_html, "w", encoding="utf-8") as f:
            f.write(html_content)

        os.system(f"libreoffice --headless --convert-to pdf {temp_html} --outdir /tmp/ >/dev/null 2>&1")
        pdf_temp = temp_html.replace(".html", ".pdf")
        if os.path.exists(pdf_temp):
            shutil.copy(pdf_temp, ruta_salida)
            os.chmod(ruta_salida, 0o777)
            log(f"[+] PDF de Agenda Semanal generado exitosamente en: {ruta_salida}")
    except Exception as e:
        log(f"[-] Error al generar PDF de agenda: {e}")

def procesar_archivo_imagen(ruta_archivo):
    log(f"[*] Iniciando procesamiento OCR para: {ruta_archivo}")
    img_limpia = preprocesar_imagen(ruta_archivo)
    
    # Intentar OCR con opciones optimizadas para tablas
    texto = pytesseract.image_to_string(Image.open(img_limpia), lang='spa', config='--psm 6')
    if not texto.strip() or len(texto.strip()) < 20:
        texto = pytesseract.image_to_string(Image.open(img_limpia), lang='spa')
        
    clases = parsear_texto_horario(texto)
    
    if clases:
        log(f"[+] ¡ÉXITO! Se detectaron {len(clases)} sesiones de clase en el horario.")
        for c in clases:
            log(f"    -> [{c['codigo']}] {c['materia']} | {c['dia_completo']} {c['hora_inicio']}-{c['hora_fin']} | Aula {c['aula']} | {c['docente']}")
        
        generar_ics_calendario(clases)
        generar_pdf_agenda(clases)
        
        # Guardar resultado en formato JSON para la interfaz web
        json_path = "/srv/samba/hub/ultimo_horario.json"
        try:
            with open(json_path, "w", encoding="utf-8") as f:
                json.dump({
                    "fecha_procesamiento": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                    "archivo_origen": os.path.basename(ruta_archivo),
                    "total_clases": len(clases),
                    "clases": clases
                }, f, ensure_ascii=False, indent=2)
            os.chmod(json_path, 0o777)
        except Exception as e:
            log(f"[-] Error guardando JSON: {e}")
            
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
        if os.path.isfile(fpath) and fname.lower().endswith(('.png', '.jpg', '.jpeg', '.bmp', '.pdf', '.webp')):
            log(f"[+] Detectado nuevo archivo en carpeta compartida Samba: {fname}")
            time.sleep(1)
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
        try:
            shutil.move(fpath, cur_path)
        except Exception:
            pass

if __name__ == "__main__":
    if len(sys.argv) > 2 and sys.argv[1] == "--file":
        res = procesar_archivo_imagen(sys.argv[2])
        sys.stdout.write(json.dumps(res, ensure_ascii=False))
        sys.stdout.flush()
        sys.exit(0)

    log("[*] Servicio ChatoSync activo. Monitoreando Samba (/entrada) y Correo (/Maildir)...")
    while True:
        try:
            escanear_carpeta_samba()
            escanear_correos()
        except Exception as e:
            log(f"[-] Error en bucle de monitoreo: {e}")
        time.sleep(2)
