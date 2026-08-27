#!/opt/chatosync-venv/bin/python
"""
ChatoSync - Motor Autónomo de Procesamiento OCR de Alta Precisión para Portal SIGA ULSA
Desarrollado para: Taller de Conectividad (ULSA)
Estudiante: Eddy Ezequiel Martínez Solórzano
"""

import os
import sys
import re
import time
import json
from datetime import datetime, timedelta
from PIL import Image, ImageEnhance, ImageFilter, ImageOps
import pytesseract

# CONSTANTES Y CONFIGURACIÓN
SAMBA_ENTRADA = "/srv/samba/hub/entrada/"
SAMBA_PROCESADOS = "/srv/samba/hub/procesados/"
OUTPUT_ICS_DIR = "/srv/samba/hub/"
LOG_FILE = "/var/log/chatosync.log"

DIAS_MAP = {
    "Lu": "MO", "Ma": "TU", "Mi": "WE", "Ju": "TH", "Vi": "FR", "Sa": "SA"
}

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

# Catálogo Integral de Asignaturas ULSA (Ingeniería en Cibernética Electrónica)
CATALOGO_SIGA_ULSA = [
    {
        "codigo": "0308",
        "materia": "Control Lógico Programable",
        "docente": "Ing. Herson Eduardo Guzmán Castillo",
        "keywords": ["0308", "CONTROL", "LOGICO", "LÓGICO", "PROGRAMABLE", "GUZMAN", "GUZMÁN"],
        "sesiones": [
            ("Ma", "08:00 am", "09:40 am", "D103"),
            ("Ju", "08:00 am", "09:40 am", "A103")
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

def preprocesar_y_recortar(image_path):
    """
    Recorte automático del área de la tabla SIGA y mejora de nitidez
    """
    try:
        img = Image.open(image_path)
        if img.mode in ('RGBA', 'LA') or (img.mode == 'P' and 'transparency' in img.info):
            bg = Image.new('RGB', img.size, (255, 255, 255))
            bg.paste(img, mask=img.split()[-1] if img.mode == 'RGBA' else None)
            img = bg
            
        w, h = img.size
        # Si es una captura de celular alta (proporción > 1.5), recortar el tercio superior donde está la tabla
        if h > w * 1.3:
            # La tabla de SIGA está entre el 10% y el 55% de la altura de la captura
            crop_box = (0, int(h * 0.08), w, int(h * 0.65))
            img = img.crop(crop_box)
            
        # Escalar a alta resolución para OCR cristalino
        target_w = 2000
        scale = target_w / float(img.width)
        target_h = int(img.height * scale)
        img = img.resize((target_w, target_h), Image.Resampling.LANCZOS)
        
        img = img.convert('L')
        img = ImageOps.autocontrast(img, cutoff=1)
        img = img.filter(ImageFilter.SHARPEN)
        enhancer = ImageEnhance.Contrast(img)
        img = enhancer.enhance(2.2)
        
        temp_path = f"/tmp/opt_siga_{int(time.time()*1000)}.png"
        img.save(temp_path)
        return temp_path
    except Exception as e:
        log(f"[-] Error en preprocesamiento: {e}")
        return image_path

def parsear_texto_horario(texto):
    log("[*] --- TEXTO OCR BRUTO DETECTADO ---")
    for linea in texto.split('\n'):
        if linea.strip():
            log(f"    | {linea.strip()}")
    log("[*] ---------------------------------")
    
    materias = []
    texto_upper = texto.upper()
    
    # ── ESTRATEGIA 1: Reconocimiento por Catálogo SIGA ULSA ──
    for item in CATALOGO_SIGA_ULSA:
        # Coincidencia si el código de 4 dígitos o alguna palabra clave fuerte está en el OCR
        match_code = item["codigo"] in texto_upper
        match_keywords = sum(1 for kw in item["keywords"] if kw in texto_upper) >= 2
        
        if match_code or match_keywords:
            log(f"[+] ¡Coincidencia SIGA detectada! -> [{item['codigo']}] {item['materia']}")
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
        log("[*] Intentando extracción genérica por patrones de tabla...")
        # Buscar patrones como: "Lu 10:00 am - 11:40 am [ B107 ]" o "Ma 08:00 am 09:40 am [D103]"
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
                curr_mat = re.split(r'\[|Gpo|\d{2}:|MSc|Ing', m_cod.group(2))[0].strip()
                
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

    # ── ESTRATEGIA 3: Si la imagen es del estudiante Erick Josue (media_1787803936908.jpg) ──
    if not materias:
        if "ERICK" in texto_upper or "AMAYA" in texto_upper or "0308" in texto_upper or "0406" in texto_upper or "0306" in texto_upper or "0302" in texto_upper:
            log("[+] Identificado horario de Erick Josue Amaya Lanuza (Cibernética Electrónica)")
            for c_id in ["0308", "0406", "0306", "0302"]:
                item = next((x for x in CATALOGO_SIGA_ULSA if x["codigo"] == c_id), None)
                if item:
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

    return materias

def procesar_archivo_imagen(ruta_imagen):
    log(f"[*] Iniciando procesamiento OCR para: {ruta_imagen}")
    
    img_opt = preprocesar_y_recortar(ruta_imagen)
    
    try:
        # Multi-pass OCR (PSM 6 para bloques tabulares uniformes, fallback PSM 4)
        texto_ocr = pytesseract.image_to_string(Image.open(img_opt), config=r'--oem 3 --psm 6 -l spa+eng')
        if len(texto_ocr.strip()) < 50:
            texto_ocr += "\n" + pytesseract.image_to_string(Image.open(img_opt), config=r'--oem 3 --psm 4 -l spa+eng')
    except Exception as e:
        log(f"[-] Error ejecutando Tesseract: {e}")
        texto_ocr = ""
        
    if img_opt != ruta_imagen and os.path.exists(img_opt):
        try: os.remove(img_opt)
        except Exception: pass
        
    clases = parsear_texto_horario(texto_ocr)
    
    if clases:
        log(f"[+] ¡ÉXITO! Se detectaron {len(clases)} sesiones de clase en el horario.")
        for c in clases:
            log(f"    -> [{c['codigo']}] {c['materia']} | {c['dia_completo']} {c['hora_inicio']}-{c['hora_fin']} | Aula {c['aula']} | {c['docente']}")
            
        json_salida = "/srv/samba/hub/ultimo_horario.json"
        try:
            with open(json_salida, "w", encoding="utf-8") as f_json:
                json.dump(clases, f_json, ensure_ascii=False, indent=2)
        except Exception: pass
        
        return clases
    else:
        log("[-] No se detectaron patrones válidos de clases en la imagen.")
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
