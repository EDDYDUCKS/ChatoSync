#!/opt/chatosync-venv/bin/python
"""
ChatoSync - Motor OCR para Horarios ULSA
Sin trucos: imagen completa, PSM 6, parser multi-estado con bugfixes.
"""

import os, sys, re, time, json
from datetime import datetime
from PIL import Image, ImageEnhance, ImageOps
import pytesseract

SAMBA_ENTRADA = "/srv/samba/hub/entrada/"
LOG_FILE      = "/var/log/chatosync.log"

DIAS_NOMBRE = {
    "Lu": "Lunes", "Ma": "Martes", "Mi": "Miércoles",
    "Ju": "Jueves", "Vi": "Viernes", "Sa": "Sábado"
}

CATALOGO = {
    "0006": ("Análisis Numérico",                    "Lic. Pedro Pablo López Muñoz"),
    "0308": ("Control Lógico Programable",            "Ing. Herson Eduardo Guzmán Castillo"),
    "0813": ("Formulación y Evaluación de Proyecto", "Ing. Ashley Madiel Salaverri Lainez"),
    "0003": ("Matemática III",                        "Lic. Julissa Cristina Mendoza Sánchez"),
    "0407": ("Organización de Archivos",              "Ing. Lester Baltazar Sánchez Bárcenas"),
    "0410": ("Tecnologías de la Información",         "MSc. Valeria Mercedes Medina Rodríguez"),
    "0406": ("Estructuras de Datos",                  "Ing. Freddy Alexander Mejía Quintana"),
    "0306": ("Introducción a la Nanotecnología",      "MSc. Christian Eduardo Toval Ruiz"),
    "0302": ("Sistemas de Control",                   "Ing. Maria Martha Verónica Lacayo Trujillo"),
    "0808": ("Administración Financiera I",           "MSc. María Auxiliadora González Mayorga"),
    "0305": ("Inteligencia Artificial",               "MSc. Martha Elena Salmerón Rivera"),
    "0303": ("Robótica",                              "Ing. Freddy Alexander Mejía Quintana"),
    "0603": ("Taller de Conectividad",                "Ing. Freddy Alexander Mejía Quintana"),
}

# ── Logging ──────────────────────────────────────────────────────────────────
def log(msg):
    ts  = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    txt = f"[{ts}] {msg}"
    dest = sys.stderr if "--file" in sys.argv else sys.stdout
    dest.write(txt + "\n"); dest.flush()
    try:
        open(LOG_FILE, "a").write(txt + "\n")
    except Exception:
        pass

# ── Normalización ─────────────────────────────────────────────────────────────
def limpiar_codigo(linea):
    """Extrae código 4 dígitos. IGNORA horas como 08:50 am."""
    sin_horas = re.sub(r'\d{1,2}[:.]\d{2}\s*[ap]m', '', linea, flags=re.IGNORECASE)
    sin_horas = sin_horas.replace('O','0').replace('I','1').replace('l','1')
    m = re.search(r'(?<![:/\d])\b(0\d{3})\b(?![:/\d])', sin_horas)
    return m.group(1) if m else None

def limpiar_aula(raw):
    """Corrige OCR: 8105→B105, O→0."""
    a = re.sub(r'[^A-Za-z0-9\-]', '', raw).upper().replace('O','0')
    if re.match(r'^8\d{3}$', a):   # B leída como 8
        a = 'B' + a[1:]
    return a or "ULSA"

# Lookup inverso: nombre parcial → código
NOMBRE_A_COD = {
    "análisis numérico": "0006",
    "control lógico": "0308",
    "formulación": "0813",
    "matemática iii": "0003",
    "organización de archivos": "0407",
    "tecnologías de la información": "0410",
    "estructuras de datos": "0406",
    "nanotecnología": "0306",
    "sistemas de control": "0302",
    "robótica": "0303",
    "inteligencia artificial": "0305",
    "taller de conectividad": "0603",
    "administración financiera": "0808",
}

def cod_por_nombre(linea):
    """Detecta código por nombre parcial de asignatura en la línea."""
    ll = linea.lower()
    for nombre, cod in NOMBRE_A_COD.items():
        if nombre in ll:
            return cod
    return None

def extraer_sesiones(texto):
    """Extrae sesiones. Acepta formato con o sin espacio: Ma10:00am o Ma 10:00 am."""
    patron = re.compile(
        r'(Lu|Ma|Mi|Ju|Vi|Sa)[a-z]*\s*'          # día (espacio opcional)
        r'(\d{1,2}[:.]\d{2}\s*[ap]\.?m\.?)\s*'   # hora inicio
        r'[-–]\s*'
        r'(\d{1,2}[:.]\d{2}\s*[ap]\.?m\.?)\s*'   # hora fin
        r'(?:\[|\()?\s*([A-Za-z]\d{2,4})',        # aula
        re.IGNORECASE
    )
    result = []
    for dia, hi, hf, aula in patron.findall(texto):
        d = dia[:2].capitalize()
        result.append((d, hi.replace('.',':').lower(), hf.replace('.',':').lower(), limpiar_aula(aula)))
    return result

# ── Parser multi-estado ───────────────────────────────────────────────────────
def parsear(texto):
    """
    Lee el texto OCR línea por línea.
    1. Detecta código numérico explícito (más confiable).
    2. Si no hay código, detecta por nombre de asignatura.
    3. Si la sesión está en la MISMA línea que el nombre, usa ese código inline.
    """
    clases = []
    codigo_actual  = "0000"
    materia_actual = "Asignatura"
    docente_actual = "Docente Asignado"

    for linea in texto.split('\n'):
        linea = linea.strip()
        if not linea:
            continue

        # 1. Código numérico explícito
        cod = limpiar_codigo(linea)
        if cod:
            codigo_actual = cod
            if cod in CATALOGO:
                materia_actual, docente_actual = CATALOGO[cod]
            else:
                materia_actual = f"Asignatura {cod}"
                docente_actual = "Docente Asignado"

        # 2. Fallback: nombre de asignatura sin código (OCR perdió el dígito)
        else:
            cod_n = cod_por_nombre(linea)
            if cod_n:
                codigo_actual = cod_n
                if cod_n in CATALOGO:
                    materia_actual, docente_actual = CATALOGO[cod_n]

        # 3. Docente explícito
        m_doc = re.search(r'\b(Ing\.|Lic\.|MSc\.|Dr\.|Dra\.)\s+[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+', linea)
        if m_doc:
            docente_actual = m_doc.group(0).strip()

        # 4. Extraer sesiones — si la línea también tiene nombre, usar ese código
        cod_inline = cod_por_nombre(linea)
        sesiones = extraer_sesiones(linea)
        for dia, hi, hf, aula in sesiones:
            cod_usar = cod_inline if cod_inline else codigo_actual
            mat_usar = CATALOGO[cod_usar][0] if cod_usar in CATALOGO else materia_actual
            doc_usar = CATALOGO[cod_usar][1] if cod_usar in CATALOGO else docente_actual
            clases.append({
                "codigo":       cod_usar,
                "materia":      mat_usar,
                "dia":          dia,
                "dia_completo": DIAS_NOMBRE.get(dia, dia),
                "hora_inicio":  hi,
                "hora_fin":     hf,
                "aula":         aula,
                "docente":      doc_usar
            })
            log(f"   ✓ [{cod_usar}] {mat_usar} | {dia} {hi}-{hf} | {aula}")

    return clases


# ── Preprocesamiento ──────────────────────────────────────────────────────────
def preparar(img, width=1400, contraste=False):
    if img.mode not in ('L', 'RGB'):
        img = img.convert('RGB')
    if img.width != width:
        scale = width / img.width
        img = img.resize((width, int(img.height * scale)), Image.Resampling.LANCZOS)
    img = img.convert('L')
    img = ImageOps.autocontrast(img, cutoff=1)
    if contraste:
        img = ImageEnhance.Contrast(img).enhance(1.7)
    return img

# ── Motor principal ───────────────────────────────────────────────────────────
def procesar_archivo_imagen(ruta):
    t0 = time.time()
    log(f"[*] Procesando: {ruta}")

    try:
        img = Image.open(ruta)
        img = ImageOps.exif_transpose(img)
    except Exception as e:
        log(f"[-] Error abriendo: {e}")
        return []

    w, h   = img.size
    vertical = h > w
    log(f"[*] {w}x{h} | {'Vertical' if vertical else 'Horizontal'}")

    rotaciones = [0] if vertical else [0, 270, 90]

    for rot in rotaciones:
        img_r = img.rotate(rot, expand=True) if rot else img

        # Intento 1: imagen sin contraste extra (rápido para imágenes nítidas)
        img_p = preparar(img_r, width=1400, contraste=False)
        try:
            txt = pytesseract.image_to_string(img_p, config='--oem 3 --psm 6 -l spa+eng')
        except Exception:
            txt = ""
        # Debug: mostrar texto crudo del OCR
        if "--debug" in sys.argv:
            log("=== TEXTO RAW OCR ===")
            log(repr(txt))
            log("=== FIN TEXTO RAW ===")

        clases = parsear(txt)
        if len(clases) >= 4:
            log(f"[+] OK sin contraste a {rot}° en {time.time()-t0:.1f}s ({len(clases)} clases)")
            return clases

        # Intento 2: con contraste aumentado (para fotos de cámara)
        img_p2 = preparar(img_r, width=1400, contraste=True)
        try:
            txt2 = pytesseract.image_to_string(img_p2, config='--oem 3 --psm 6 -l spa+eng')
        except Exception:
            txt2 = ""

        if "--debug" in sys.argv:
            log("=== TEXTO RAW OCR (contraste) ===")
            log(repr(txt2))
            log("=== FIN ===")

        clases2 = parsear(txt2)
        if len(clases2) >= 4:
            log(f"[+] OK con contraste a {rot}° en {time.time()-t0:.1f}s ({len(clases2)} clases)")
            return clases2

    log(f"[-] Sin resultados en {time.time()-t0:.1f}s")
    return []

# ── Entry point ───────────────────────────────────────────────────────────────
if __name__ == "__main__":
    if len(sys.argv) > 2 and sys.argv[1] == "--file":
        path = sys.argv[2]
        if os.path.exists(path):
            result = procesar_archivo_imagen(path)
        else:
            result = []
        # Markers únicos para que PHP extraiga el JSON con 100% de fiabilidad
        print("<<<JSON>>>")
        print(json.dumps(result, ensure_ascii=False))
        print("<<<END>>>")
    else:
        log("[*] Daemon ChatoSync activo...")
        os.makedirs(SAMBA_ENTRADA, exist_ok=True)
        while True:
            try:
                for f in os.listdir(SAMBA_ENTRADA):
                    fp = os.path.join(SAMBA_ENTRADA, f)
                    if os.path.isfile(fp) and f.lower().endswith(('.png','.jpg','.jpeg','.bmp')):
                        time.sleep(0.5)
                        procesar_archivo_imagen(fp)
            except Exception as e:
                log(f"[-] Daemon error: {e}")
            time.sleep(3)
