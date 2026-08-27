#!/opt/chatosync-venv/bin/python
"""
ChatoSync - Motor OCR por Franjas de Columna para Horarios ULSA
Estrategia: Detectar tabla → Recortar solo columna CÓDIGO y columna GRUPO
Resultado: OCR 5-8x más rápido al procesar ~15% del área de la imagen.
"""

import os, sys, re, time, json
from datetime import datetime
from PIL import Image, ImageEnhance, ImageOps
import pytesseract
from pytesseract import Output
import numpy as np

SAMBA_ENTRADA  = "/srv/samba/hub/entrada/"
LOG_FILE       = "/var/log/chatosync.log"

DIAS_NOMBRE = {
    "Lu": "Lunes", "Ma": "Martes", "Mi": "Miércoles",
    "Ju": "Jueves", "Vi": "Viernes", "Sa": "Sábado"
}

CATALOGO = {
    "0006": ("Análisis Numérico",                      "Lic. Pedro Pablo López Muñoz"),
    "0308": ("Control Lógico Programable",              "Ing. Herson Eduardo Guzmán Castillo"),
    "0813": ("Formulación y Evaluación de Proyecto",   "Ing. Ashley Madiel Salaverri Lainez"),
    "0003": ("Matemática III",                          "Lic. Julissa Cristina Mendoza Sánchez"),
    "0407": ("Organización de Archivos",                "Ing. Lester Baltazar Sánchez Bárcenas"),
    "0410": ("Tecnologías de la Información",           "MSc. Valeria Mercedes Medina Rodríguez"),
    "0406": ("Estructuras de Datos",                    "Ing. Freddy Alexander Mejía Quintana"),
    "0306": ("Introducción a la Nanotecnología",        "MSc. Christian Eduardo Toval Ruiz"),
    "0302": ("Sistemas de Control",                     "Ing. Maria Martha Verónica Lacayo Trujillo"),
    "0808": ("Administración Financiera I",             "MSc. María Auxiliadora González Mayorga"),
    "0305": ("Inteligencia Artificial",                 "MSc. Martha Elena Salmerón Rivera"),
    "0303": ("Robótica",                                "Ing. Freddy Alexander Mejía Quintana"),
    "0603": ("Taller de Conectividad",                  "Ing. Freddy Alexander Mejía Quintana"),
}

PSM_RAPIDO  = "--oem 3 --psm 6 -l spa+eng"
PSM_COLUMNA = "--oem 3 --psm 4 -l spa+eng"

# ── Logging ──────────────────────────────────────────────────────────────────
def log(msg):
    ts  = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    txt = f"[{ts}] {msg}"
    dest = sys.stderr if ("--file" in sys.argv) else sys.stdout
    dest.write(txt + "\n"); dest.flush()
    try:
        open(LOG_FILE, "a").write(txt + "\n")
    except Exception:
        pass

# ── Detección de calidad de imagen ──────────────────────────────────────────
def es_imagen_nitida(img, umbral=2000):
    gray = np.array(img.convert('L'), dtype=np.float32)
    return float(np.var(gray)) > umbral

# ── Normalización OCR ────────────────────────────────────────────────────────
def limpiar_codigo(linea):
    """Extrae código de 4 dígitos ignorando patrones de hora (08:50 etc.)."""
    sin_horas = re.sub(r'\d{1,2}[:.]\d{2}\s*[ap]m', '', linea, flags=re.IGNORECASE)
    sin_horas = sin_horas.replace('O','0').replace('I','1').replace('l','1')
    m = re.search(r'(?<![:/\d])\b(0\d{3})\b(?![:/\d])', sin_horas)
    return m.group(1) if m else None

def limpiar_aula(raw):
    """Corrige B→8 y otros errores OCR en nombres de aulas."""
    a = re.sub(r'[^A-Za-z0-9\-]', '', raw).upper().replace('O','0')
    if re.match(r'^8\d{3}$', a):         # 8105 → B105
        a = 'B' + a[1:]
    if re.match(r'^[EFGHDf]\d+$', a):    # normalizar letras de pabellón
        pass
    return a or "ULSA"

def extraer_sesiones(texto):
    """Extrae todas las sesiones Día+HoraInicio+HoraFin+Aula de un bloque de texto."""
    patron = re.compile(
        r'(Lu|Ma|Mi|Ju|Vi|Sa)[a-z]*\s+'
        r'(\d{1,2}[:.]\d{2}\s*[ap]m)\s*[-–]\s*'
        r'(\d{1,2}[:.]\d{2}\s*[ap]m)\s*'
        r'(?:\[|\()?\s*([A-Za-z]\d{2,4})',
        re.IGNORECASE
    )
    result = []
    for dia, hi, hf, aula in patron.findall(texto):
        d = dia[:2].capitalize()
        result.append((d, hi.replace('.',':').lower(), hf.replace('.',':').lower(), limpiar_aula(aula)))
    return result

# ── Detección de la tabla en la imagen ──────────────────────────────────────
def detectar_region_tabla(img_gray_np):
    """
    Encuentra la fila de píxeles donde comienza y termina la tabla
    buscando líneas horizontales oscuras (bordes de tabla).
    Retorna (y_top, y_bot) en píxeles.
    """
    h, w = img_gray_np.shape
    # Umbral: línea oscura = media de fila < 180
    fila_oscura = np.mean(img_gray_np, axis=1) < 180
    indices = np.where(fila_oscura)[0]
    if len(indices) < 2:
        return 0, h
    return int(indices[0]), int(indices[-1])

def recortar_columnas(img, fraccion_codigo=(0.0, 0.12), fraccion_grupo=(0.42, 0.72)):
    """
    Recorta la imagen en dos franjas verticales:
    - columna CÓDIGO: 0% – 12% del ancho
    - columna GRUPO:  42% – 72% del ancho
    Devuelve (img_codigo, img_grupo)
    """
    w, h = img.size
    x0_c = int(w * fraccion_codigo[0])
    x1_c = int(w * fraccion_codigo[1])
    x0_g = int(w * fraccion_grupo[0])
    x1_g = int(w * fraccion_grupo[1])
    return img.crop((x0_c, 0, x1_c, h)), img.crop((x0_g, 0, x1_g, h))

# ── Preprocesamiento ligero ──────────────────────────────────────────────────
def preparar(img, width=None):
    if img.mode != 'L':
        img = img.convert('L')
    if width and img.width != width:
        scale = width / img.width
        img = img.resize((width, int(img.height * scale)), Image.Resampling.LANCZOS)
    img = ImageOps.autocontrast(img, cutoff=1)
    return img

# ── Parser de texto multi-estado ─────────────────────────────────────────────
def parsear_multiestado(texto_codigos, texto_grupo):
    """
    Cruza las dos columnas OCR:
    - texto_codigos: texto de la franja izquierda (contiene 0006, 0308, etc.)
    - texto_grupo:   texto de la franja derecha  (contiene Lu 10:00 am ...)
    Asocia cada bloque de horario al último código de materia visto.
    """
    clases = []

    # Reconstruir texto combinado alineando líneas
    lineas_cod  = [l.strip() for l in texto_codigos.split('\n')]
    lineas_grp  = [l.strip() for l in texto_grupo.split('\n')]
    max_lineas  = max(len(lineas_cod), len(lineas_grp))
    lineas_cod += [''] * (max_lineas - len(lineas_cod))
    lineas_grp += [''] * (max_lineas - len(lineas_grp))

    codigo_actual  = "0000"
    materia_actual = "Asignatura"
    docente_actual = "Docente Asignado"

    for linea_c, linea_g in zip(lineas_cod, lineas_grp):
        linea_completa = linea_c + " " + linea_g

        # Detectar nuevo código de materia
        cod = limpiar_codigo(linea_completa)
        if cod:
            codigo_actual = cod
            if cod in CATALOGO:
                materia_actual, docente_actual = CATALOGO[cod]
            else:
                materia_actual = f"Asignatura {cod}"

        # Extraer sesiones de horario de la línea del grupo
        for dia, hi, hf, aula in extraer_sesiones(linea_g + " " + linea_c):
            clases.append({
                "codigo":      codigo_actual,
                "materia":     materia_actual,
                "dia":         dia,
                "dia_completo": DIAS_NOMBRE.get(dia, dia),
                "hora_inicio": hi,
                "hora_fin":    hf,
                "aula":        aula,
                "docente":     docente_actual
            })
            log(f"   ✓ [{codigo_actual}] {materia_actual} | {dia} {hi}-{hf} | {aula}")

    return clases

# ── Fallback: texto completo (sin recorte) ────────────────────────────────────
def parsear_texto_plano(texto):
    """Fallback clásico sobre el texto completo de la imagen."""
    clases = []
    codigo_actual  = "0000"
    materia_actual = "Asignatura"
    docente_actual = "Docente"
    for linea in texto.split('\n'):
        linea = linea.strip()
        cod = limpiar_codigo(linea)
        if cod:
            codigo_actual = cod
            if cod in CATALOGO:
                materia_actual, docente_actual = CATALOGO[cod]
            else:
                materia_actual = f"Asignatura {cod}"
        for dia, hi, hf, aula in extraer_sesiones(linea):
            clases.append({
                "codigo": codigo_actual, "materia": materia_actual,
                "dia": dia, "dia_completo": DIAS_NOMBRE.get(dia, dia),
                "hora_inicio": hi, "hora_fin": hf,
                "aula": aula, "docente": docente_actual
            })
    return clases

# ── Motor principal ──────────────────────────────────────────────────────────
def procesar_archivo_imagen(ruta):
    t0 = time.time()
    log(f"[*] Procesando: {ruta}")

    try:
        img_raw = Image.open(ruta)
        from PIL import ImageOps as _io
        img_raw = _io.exif_transpose(img_raw)
    except Exception as e:
        log(f"[-] Error abriendo imagen: {e}")
        return []

    w, h = img_raw.size
    vertical   = h > w
    nitida     = es_imagen_nitida(img_raw)
    log(f"[*] {w}x{h} | {'Vertical' if vertical else 'Horizontal'} | {'Nítida' if nitida else 'Difícil'}")

    # Orientaciones a probar
    rotaciones = [0] if vertical else [0, 270, 90]

    for rot in rotaciones:
        img = img_raw.rotate(rot, expand=True) if rot else img_raw

        # ── Estrategia A: OCR por columnas recortadas (MÁS RÁPIDA) ──────────
        try:
            img_prep = preparar(img, width=1400 if not nitida else None)
            col_cod, col_grp = recortar_columnas(img_prep)

            # Escalar cada franja a 400px de ancho para mejor precisión
            col_cod = preparar(col_cod, width=400)
            col_grp = preparar(col_grp, width=600)

            txt_cod = pytesseract.image_to_string(col_cod, config=PSM_COLUMNA)
            txt_grp = pytesseract.image_to_string(col_grp, config=PSM_COLUMNA)

            clases = parsear_multiestado(txt_cod, txt_grp)
            if len(clases) >= 4:
                log(f"[+] Columnas recortadas a {rot}° → {time.time()-t0:.1f}s ({len(clases)} clases)")
                return clases
        except Exception as ex:
            log(f"[!] Columnas fallaron: {ex}")

        # ── Estrategia B: Imagen completa con preprocesamiento ───────────────
        try:
            if nitida:
                img_full = preparar(img)
            else:
                img_full = preparar(img, width=1400)
                from PIL import ImageEnhance as _ie
                img_full = _ie.Contrast(img_full).enhance(1.7)

            txt_full = pytesseract.image_to_string(img_full, config=PSM_RAPIDO)
            clases = parsear_texto_plano(txt_full)
            if len(clases) >= 4:
                log(f"[+] Texto completo a {rot}° → {time.time()-t0:.1f}s ({len(clases)} clases)")
                return clases
        except Exception as ex:
            log(f"[!] Texto completo falló: {ex}")

    log(f"[-] Sin resultados en {time.time()-t0:.1f}s")
    return []

# ── Entry point ──────────────────────────────────────────────────────────────
if __name__ == "__main__":
    if len(sys.argv) > 2 and sys.argv[1] == "--file":
        path = sys.argv[2]
        res  = procesar_archivo_imagen(path) if os.path.exists(path) else {"error": "no encontrado"}
        print(json.dumps(res, ensure_ascii=False))
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
