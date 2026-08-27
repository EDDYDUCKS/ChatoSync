#!/opt/chatosync-venv/bin/python
"""
ChatoSync - Motor OCR Híbrido Dinámico y Resiliente para Horarios ULSA
Combina parser de estados por filas de tabla con resolución difusa de asignaturas.
"""

import os
import sys
import re
import time
import json
from datetime import datetime
from PIL import Image, ImageEnhance, ImageFilter, ImageOps
import pytesseract

SAMBA_ENTRADA = "/srv/samba/hub/entrada/"
SAMBA_PROCESADOS = "/srv/samba/hub/procesados/"
LOG_FILE = "/var/log/chatosync.log"

DIAS_NOMBRE = {
    "Lu": "Lunes", "Ma": "Martes", "Mi": "Miércoles",
    "Ju": "Jueves", "Vi": "Viernes", "Sa": "Sábado"
}

# Diccionario de referencia para normalizar títulos de asignaturas y docentes de ULSA
DICCIONARIO_MATERIAS = {
    "0006": ("Análisis Numérico", "Lic. Pedro Pablo López Muñoz"),
    "0308": ("Control Lógico Programable", "Ing. Herson Eduardo Guzmán Castillo"),
    "0813": ("Formulación y Evaluación de Proyecto", "Ing. Ashley Madiel Salaverri Lainez"),
    "0003": ("Matemática III", "Lic. Julissa Cristina Mendoza Sánchez"),
    "0407": ("Organización de Archivos", "Ing. Lester Baltazar Sánchez Bárcenas"),
    "0410": ("Tecnologías de la Información", "MSc. Valeria Mercedes Medina Rodríguez"),
    "0406": ("Estructuras de Datos", "Ing. Freddy Alexander Mejía Quintana"),
    "0306": ("Introducción a la Nanotecnología", "MSc. Christian Eduardo Toval Ruiz"),
    "0302": ("Sistemas de Control", "Ing. Maria Martha Verónica Lacayo Trujillo"),
    "0808": ("Administración Financiera I", "MSc. María Auxiliadora González Mayorga"),
    "0305": ("Inteligencia Artificial", "MSc. Martha Elena Salmerón Rivera"),
    "0303": ("Robótica", "Ing. Freddy Alexander Mejía Quintana"),
    "0603": ("Taller de Conectividad", "Ing. Freddy Alexander Mejía Quintana")
}

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

def preparar_imagen(img, target_width=1400):
    if img.mode in ('RGBA', 'LA') or (img.mode == 'P' and 'transparency' in img.info):
        bg = Image.new('RGB', img.size, (255, 255, 255))
        bg.paste(img, mask=img.split()[-1] if img.mode == 'RGBA' else None)
        img = bg
        
    if img.width != target_width:
        scale = float(target_width) / float(img.width)
        img = img.resize((target_width, int(img.height * scale)), Image.Resampling.LANCZOS)
        
    img = img.convert('L')
    img = ImageOps.autocontrast(img)
    enh = ImageEnhance.Contrast(img)
    img = enh.enhance(1.8)
    return img

def extraer_horario_multilinea(texto):
    """
    Parser robusto de tabla que asocia cada bloque de horario con su código de asignatura real.
    """
    clases = []
    
    # Patrón de bloques de horario: ej. "Ma 08:00 am - 09:40 am [ D103 ]"
    patron_bloque = re.compile(
        r'(Lu|Ma|Mi|Ju|Vi|Sa)[a-z]*\s+(\d{1,2}[:.]\d{2}\s*[ap]m)\s*(?:-|–|\s+)\s*(\d{1,2}[:.]\d{2}\s*[ap]m)\s*(?:\[|\(|\s)\s*([A-Za-z0-9\-_]+)\s*(?:\]|\)|\s|$)',
        re.IGNORECASE
    )

    lineas = [l.strip() for l in texto.split('\n') if l.strip()]
    
    codigo_actual = "0000"
    materia_actual = "Asignatura Detectada"
    docente_actual = "Docente Asignado"

    for linea in lineas:
        # Detectar código de 4 dígitos (0006, 0308, 0406, 0306, 0302, etc.)
        m_cod = re.search(r'\b(0\d{3})\b', linea)
        if m_cod:
            cod_cand = m_cod.group(1)
            codigo_actual = cod_cand
            if cod_cand in DICCIONARIO_MATERIAS:
                materia_actual, docente_actual = DICCIONARIO_MATERIAS[cod_cand]
            else:
                materia_actual = f"Materia {cod_cand}"
                docente_actual = "Docente Asignado"

        # Detectar docente explícito en la línea si existe
        m_doc = re.search(r'\b(Ing\.|Lic\.|MSc\.|Dr\.|Dra\.)\s+([A-Za-zÁÉÍÓÚáéíóúñÑ\s]+)', linea, re.IGNORECASE)
        if m_doc:
            docente_actual = m_doc.group(0).strip()

        # Extraer bloques de horario en esta línea
        bloques = patron_bloque.findall(linea)
        if bloques:
            for dia, h_ini, h_fin, aula in bloques:
                d_norm = dia[:2].capitalize()
                h_ini_c = h_ini.replace(".", ":").lower()
                h_fin_c = h_fin.replace(".", ":").lower()
                aula_c = re.sub(r'[^A-Za-z0-9\-]', '', aula).upper() or "ULSA"

                clases.append({
                    "codigo": codigo_actual,
                    "materia": materia_actual,
                    "dia": d_norm,
                    "dia_completo": DIAS_NOMBRE.get(d_norm, d_norm),
                    "hora_inicio": h_ini_c,
                    "hora_fin": h_fin_c,
                    "aula": aula_c,
                    "docente": docente_actual
                })
                log(f"    [+] Detectada: [{codigo_actual}] {materia_actual} | {d_norm} {h_ini_c}-{h_fin_c} | Aula {aula_c}")

    return clases

def procesar_archivo_imagen(ruta_imagen):
    t0 = time.time()
    log(f"[*] OCR Robusto para: {ruta_imagen}")
    
    try:
        img_raw = Image.open(ruta_imagen)
        img_raw = ImageOps.exif_transpose(img_raw)
    except Exception as e:
        log(f"[-] Error abriendo imagen: {e}")
        return []

    w, h = img_raw.size

    # Si es imagen vertical (capturas de pantalla de celular como Erick) -> probar 0°
    if h > w:
        log("[*] Orientación vertical detectada, probando ángulo 0°...")
        img_p = preparar_imagen(img_raw, 1600)
        
        # Probar con PSM 4 (asume columnas) y luego PSM 6
        for psm in [4, 6]:
            try:
                texto = pytesseract.image_to_string(img_p, config=f'--oem 3 --psm {psm} -l spa+eng')
            except Exception:
                texto = ""
            clases = extraer_horario_multilinea(texto)
            if len(clases) >= 4:
                log(f"[+] ¡Éxito en captura digital 0° (PSM {psm}) en {time.time() - t0:.2f}s! ({len(clases)} clases)")
                return clases

    # Para fotos de cámara (probar 270°, luego 90°, luego 0°)
    for rot in [270, 90, 0]:
        img_rot = img_raw.rotate(rot, expand=True) if rot != 0 else img_raw
        img_p = preparar_imagen(img_rot, 1400)
        
        for psm in [6, 4]:
            try:
                texto = pytesseract.image_to_string(img_p, config=f'--oem 3 --psm {psm} -l spa+eng')
            except Exception:
                texto = ""
                
            clases = extraer_horario_multilinea(texto)
            if len(clases) >= 4:
                log(f"[+] ¡Éxito en ángulo {rot}° (PSM {psm}) en {time.time() - t0:.2f}s! ({len(clases)} clases)")
                return clases

    log(f"[-] No se alcanzaron suficientes coincidencias en {time.time() - t0:.2f}s.")
    return []

if __name__ == "__main__":
    if len(sys.argv) > 2 and sys.argv[1] == "--file":
        archivo_path = sys.argv[2]
        if os.path.exists(archivo_path):
            resultado = procesar_archivo_imagen(archivo_path)
            print(json.dumps(resultado, ensure_ascii=False))
        else:
            print(json.dumps({"error": "Archivo no encontrado"}))
        sys.exit(0)
    elif len(sys.argv) > 1 and sys.argv[1] == "--sample":
        sample = "/srv/samba/hub/samples/horario_muestra.png"
        if not os.path.exists(sample): sample = "samples/horario_muestra.png"
        resultado = procesar_archivo_imagen(sample)
        print(json.dumps(resultado, ensure_ascii=False, indent=2))
        sys.exit(0)
    else:
        log("[*] Daemon ChatoSync activo...")
        os.makedirs(SAMBA_ENTRADA, exist_ok=True)
        while True:
            try:
                archivos = [f for f in os.listdir(SAMBA_ENTRADA) if os.path.isfile(os.path.join(SAMBA_ENTRADA, f))]
                for f in archivos:
                    if f.lower().endswith(('.png', '.jpg', '.jpeg', '.bmp', '.tiff')):
                        full_p = os.path.join(SAMBA_ENTRADA, f)
                        time.sleep(1)
                        procesar_archivo_imagen(full_p)
            except Exception as e:
                log(f"[-] Error en daemon: {e}")
            time.sleep(3)
