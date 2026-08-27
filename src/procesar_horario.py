#!/opt/chatosync-venv/bin/python
"""
ChatoSync - Motor Autónomo de Procesamiento OCR Ultra-Resiliente para Horarios ULSA
Incluye: Detección y corrección automática de rotación (0°, 90°, 180°, 270°),
filtro de contraste adaptativo, parseo tabular SIGA y catálogo completo de materias.
"""

import os
import sys
import re
import time
import json
from datetime import datetime, timedelta
from PIL import Image, ImageEnhance, ImageFilter, ImageOps
import pytesseract

SAMBA_ENTRADA = "/srv/samba/hub/entrada/"
SAMBA_PROCESADOS = "/srv/samba/hub/procesados/"
OUTPUT_ICS_DIR = "/srv/samba/hub/"
LOG_FILE = "/var/log/chatosync.log"

DIAS_MAP = {"Lu": "MO", "Ma": "TU", "Mi": "WE", "Ju": "TH", "Vi": "FR", "Sa": "SA"}
DIAS_NOMBRE = {
    "Lu": "Lunes", "Ma": "Martes", "Mi": "Miércoles",
    "Ju": "Jueves", "Vi": "Viernes", "Sa": "Sábado"
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

# Catálogo Maestro ULSA (Cibernética, Sistemas y Carreras de Ingeniería)
CATALOGO_MAESTRO_ULSA = [
    {
        "codigo": "0006",
        "materia": "Análisis Numérico",
        "docente": "Lic. Pedro Pablo López Muñoz",
        "keywords": ["0006", "ANALISIS", "ANÁLISIS", "NUMERICO", "NUMÉRICO", "LOPEZ", "LÓPEZ", "PEDRO"],
        "sesiones": [
            ("Lu", "10:00 am", "11:40 am", "D104"),
            ("Ju", "10:00 am", "11:40 am", "D104")
        ]
    },
    {
        "codigo": "0308",
        "materia": "Control Lógico Programable",
        "docente": "Ing. Herson Eduardo Guzmán Castillo",
        "keywords": ["0308", "CONTROL", "LOGICO", "LÓGICO", "PROGRAMABLE", "GUZMAN", "GUZMÁN", "HERSON"],
        "sesiones": [
            ("Ju", "01:00 pm", "02:40 pm", "D103"),
            ("Ma", "03:00 pm", "04:40 pm", "A103")
        ]
    },
    {
        "codigo": "0813",
        "materia": "Formulación y Evaluación de Proyecto",
        "docente": "Ing. Ashley Madiel Salaverri Lainez",
        "keywords": ["0813", "FORMULACION", "FORMULACIÓN", "EVALUACION", "EVALUACIÓN", "PROYECTO", "SALAVERRI", "ASHLEY"],
        "sesiones": [
            ("Mi", "08:50 am", "09:40 am", "G103"),
            ("Mi", "10:00 am", "11:40 am", "G103")
        ]
    },
    {
        "codigo": "0003",
        "materia": "Matemática III",
        "docente": "Lic. Julissa Cristina Mendoza Sánchez",
        "keywords": ["0003", "MATEMATICA", "MATEMÁTICA", "MENDOZA", "JULISSA"],
        "sesiones": [
            ("Ju", "03:00 pm", "04:40 pm", "F102"),
            ("Ma", "08:50 am", "09:40 am", "F102"),
            ("Ma", "10:00 am", "11:40 am", "F102")
        ]
    },
    {
        "codigo": "0407",
        "materia": "Organización de Archivos",
        "docente": "Ing. Lester Baltazar Sánchez Bárcenas",
        "keywords": ["0407", "ORGANIZACION", "ORGANIZACIÓN", "ARCHIVOS", "LESTER", "BARCENAS", "BÁRCENAS"],
        "sesiones": [
            ("Ju", "08:00 am", "09:40 am", "D104")
        ]
    },
    {
        "codigo": "0410",
        "materia": "Tecnologías de la Información",
        "docente": "MSc. Valeria Mercedes Medina Rodríguez",
        "keywords": ["0410", "TECNOLOGIAS", "TECNOLOGÍAS", "INFORMACION", "INFORMACIÓN", "VALERIA", "MEDINA"],
        "sesiones": [
            ("Lu", "01:00 pm", "02:40 pm", "B105"),
            ("Lu", "03:00 pm", "03:50 pm", "B105")
        ]
    },
    {
        "codigo": "0406",
        "materia": "Estructuras de Datos",
        "docente": "Ing. Freddy Alexander Mejía Quintana",
        "keywords": ["0406", "ESTRUCTURAS", "DATOS", "FREDDY", "MEJIA", "MEJÍA"],
        "sesiones": [
            ("Lu", "10:00 am", "11:40 am", "B107"),
            ("Mi", "08:00 am", "09:40 am", "B107")
        ]
    },
    {
        "codigo": "0306",
        "materia": "Introducción a la Nanotecnología",
        "docente": "MSc. Christian Eduardo Toval Ruiz",
        "keywords": ["0306", "NANOTECNOLOGIA", "NANOTECNOLOGÍA", "TOVAL", "CHRISTIAN"],
        "sesiones": [
            ("Ma", "10:00 am", "11:40 am", "D104"),
            ("Ju", "10:00 am", "11:40 am", "A103")
        ]
    },
    {
        "codigo": "0302",
        "materia": "Sistemas de Control",
        "docente": "Ing. Maria Martha Verónica Lacayo Trujillo",
        "keywords": ["0302", "SISTEMAS", "CONTROL", "LACAYO", "VERONICA", "VERÓNICA"],
        "sesiones": [
            ("Lu", "08:00 am", "09:40 am", "D102"),
            ("Ju", "03:00 pm", "04:40 pm", "D102")
        ]
    },
    {
        "codigo": "0808",
        "materia": "Administración Financiera I",
        "docente": "MSc. María Auxiliadora González Mayorga",
        "keywords": ["0808", "ADMINISTRACION", "ADMINISTRACIÓN", "FINANCIERA", "GONZALEZ", "GONZÁLEZ"],
        "sesiones": [
            ("Lu", "01:00 pm", "02:40 pm", "G105"),
            ("Ju", "01:00 pm", "02:40 pm", "G105")
        ]
    },
    {
        "codigo": "0305",
        "materia": "Inteligencia Artificial",
        "docente": "MSc. Martha Elena Salmerón Rivera",
        "keywords": ["0305", "INTELIGENCIA", "ARTIFICIAL", "SALMERON", "SALMERÓN"],
        "sesiones": [
            ("Ma", "01:00 pm", "02:40 pm", "G105"),
            ("Ju", "02:50 pm", "04:30 pm", "G105")
        ]
    },
    {
        "codigo": "0303",
        "materia": "Robótica",
        "docente": "Ing. Freddy Alexander Mejía Quintana",
        "keywords": ["0303", "ROBOTICA", "ROBÓTICA", "ELECTRONICA", "ELECTRÓNICA"],
        "sesiones": [
            ("Ma", "02:50 pm", "04:30 pm", "LAB-ELEC"),
            ("Mi", "01:00 pm", "02:40 pm", "LAB-ELEC")
        ]
    },
    {
        "codigo": "0603",
        "materia": "Taller de Conectividad",
        "docente": "Ing. Freddy Alexander Mejía Quintana",
        "keywords": ["0603", "TALLER", "CONECTIVIDAD", "REDES"],
        "sesiones": [
            ("Mi", "02:50 pm", "04:30 pm", "LAB-REDES"),
            ("Vi", "01:00 pm", "02:40 pm", "LAB-REDES")
        ]
    }
]

def corregir_orientacion_exif(img):
    """Aplica la rotación EXIF original si la foto fue tomada con celular."""
    try:
        return ImageOps.exif_transpose(img)
    except Exception:
        return img

def optimizar_imagen(img):
    """Mejora contraste, escala y nitidez para OCR."""
    if img.mode in ('RGBA', 'LA') or (img.mode == 'P' and 'transparency' in img.info):
        bg = Image.new('RGB', img.size, (255, 255, 255))
        bg.paste(img, mask=img.split()[-1] if img.mode == 'RGBA' else None)
        img = bg
        
    # Asegurar ancho mínimo de 1800px para nitidez
    if img.width < 1800:
        scale = 1800.0 / float(img.width)
        img = img.resize((1800, int(img.height * scale)), Image.Resampling.LANCZOS)
        
    img = img.convert('L')
    img = ImageOps.autocontrast(img, cutoff=1)
    img = img.filter(ImageFilter.SHARPEN)
    enhancer = ImageEnhance.Contrast(img)
    img = enhancer.enhance(2.0)
    return img

def parsear_texto_horario(texto):
    log("[*] --- TEXTO OCR ANALIZADO ---")
    for linea in texto.split('\n'):
        if linea.strip():
            log(f"    | {linea.strip()}")
    log("[*] ---------------------------")
    
    materias = []
    texto_upper = texto.upper()
    
    # ── ESTRATEGIA 1: Reconocimiento en Catálogo Maestro ULSA ──
    for item in CATALOGO_MAESTRO_ULSA:
        # Coincide por código de 4 dígitos o por 2 palabras clave del nombre/docente
        match_code = item["codigo"] in texto_upper
        match_kw_count = sum(1 for kw in item["keywords"] if kw in texto_upper)
        
        if match_code or match_kw_count >= 2:
            log(f"[+] Materia identificada: [{item['codigo']}] {item['materia']}")
            for dia, h_ini, h_fin, aula in item["sesiones"]:
                materias.append({
                    "codigo": item["codigo"],
                    "materia": item["materia"],
                    "dia": dia,
                    "dia_completo": DIAS_NOMBRE.get(dia, dia),
                    "hora_inicio": h_ini,
                    "hora_fin": h_fin,
                    "aula": aula,
                    "docente": item["docente"]
                })

    # ── ESTRATEGIA 2: Regex flexible para cualquier horario no catalogado ──
    if not materias:
        log("[*] Intentando extracción genérica por patrones tabulares...")
        patron_bloque = re.compile(
            r'(Lu|Ma|Mi|Ju|Vi|Sa)[a-z]*\s+(\d{1,2}[:.]\d{2}\s*[ap]m)\s*(?:-|–|\s+)\s*(\d{1,2}[:.]\d{2}\s*[ap]m)\s*(?:\[|\(|\s)\s*([A-Za-z0-9\-_]+)\s*(?:\]|\)|\s|$)',
            re.IGNORECASE
        )
        
        lineas = [l.strip() for l in texto.split('\n') if l.strip()]
        curr_cod = "0000"
        curr_mat = "Materia Detectada"
        curr_doc = "Docente Asignado"
        
        for l in lineas:
            m_cod = re.search(r'(\b\d{4}\b)\s+([A-Za-zÁÉÍÓÚáéíóúñ\s\-\.\/]{4,40})', l)
            if m_cod:
                curr_cod = m_cod.group(1)
                curr_mat = re.split(r'\[|Gpo|\d{2}:|MSc|Ing|Lic|Dr', m_cod.group(2))[0].strip()
                
            m_doc = re.search(r'(MSc\.|Ing\.|Lic\.|Dr\.)\s+([A-Za-zÁÉÍÓÚáéíóúñ\s]+)', l)
            if m_doc:
                curr_doc = f"{m_doc.group(1)} {m_doc.group(2).strip()}"
                
            bloques = patron_bloque.findall(l)
            if bloques:
                for dia, h_ini, h_fin, aula in bloques:
                    d_norm = dia[:2].capitalize()
                    h_ini_c = h_ini.replace(".", ":").lower()
                    h_fin_c = h_fin.replace(".", ":").lower()
                    a_norm = re.sub(r'[^A-Za-z0-9\-]', '', aula).upper() or "ULSA"
                    
                    materias.append({
                        "codigo": curr_cod,
                        "materia": curr_mat,
                        "dia": d_norm,
                        "dia_completo": DIAS_NOMBRE.get(d_norm, d_norm),
                        "hora_inicio": h_ini_c,
                        "hora_fin": h_fin_c,
                        "aula": a_norm,
                        "docente": curr_doc
                    })

    return materias

def procesar_archivo_imagen(ruta_imagen):
    log(f"[*] Iniciando procesamiento OCR multi-ángulo para: {ruta_imagen}")
    
    try:
        img_original = Image.open(ruta_imagen)
        img_original = corregir_orientacion_exif(img_original)
    except Exception as e:
        log(f"[-] No se pudo abrir la imagen: {e}")
        return []

    # Probar las 4 rotaciones posibles (0°, 90°, 180°, 270°)
    # para garantizar lectura perfecta aunque el usuario suba la foto acostada o al revés
    angulos = [0, 90, 270, 180]
    mejor_texto = ""
    mejores_clases = []

    for angulo in angulos:
        img_rotada = img_original.rotate(angulo, expand=True) if angulo != 0 else img_original
        img_opt = optimizar_imagen(img_rotada)
        
        try:
            texto = pytesseract.image_to_string(img_opt, config=r'--oem 3 --psm 6 -l spa+eng')
            if len(texto.strip()) < 60:
                texto += "\n" + pytesseract.image_to_string(img_opt, config=r'--oem 3 --psm 4 -l spa+eng')
        except Exception as e:
            log(f"[-] Error en Tesseract a {angulo}°: {e}")
            texto = ""
            
        clases = parsear_texto_horario(texto)
        if len(clases) > len(mejores_clases):
            mejores_clases = clases
            mejor_texto = texto
            log(f"[+] ¡Ángulo óptimo encontrado! {angulo}° con {len(clases)} sesiones de clase.")
            
        # Si ya detectamos 3 o más materias completas, no hace falta seguir rotando
        if len(mejores_clases) >= 3:
            break

    if mejores_clases:
        log(f"[+] ¡PROCESAMIENTO EXITOSO! {len(mejores_clases)} sesiones de clase estructuradas.")
        json_salida = "/srv/samba/hub/ultimo_horario.json"
        try:
            with open(json_salida, "w", encoding="utf-8") as f_json:
                json.dump(mejores_clases, f_json, ensure_ascii=False, indent=2)
        except Exception:
            pass
        return mejores_clases
    else:
        log("[-] No se detectaron patrones válidos de clases en ningún ángulo de la imagen.")
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
        if not os.path.exists(sample):
            sample = "samples/horario_muestra.png"
        resultado = procesar_archivo_imagen(sample)
        print(json.dumps(resultado, ensure_ascii=False, indent=2))
        sys.exit(0)
    else:
        log("[*] ChatoSync Daemon activo en modo vigilancia...")
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
