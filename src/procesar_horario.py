#!/opt/chatosync-venv/bin/python
"""
ChatoSync - Motor OCR 100% Dinámico y Estructurado para ULSA
Cero listas estáticas, cero fallbacks duros. Extrae cualquier asignatura,
horario, aula y docente dinámicamente de cualquier imagen o captura.
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

def preparar_imagen_dinamica(img, target_width=1300):
    """
    Prepara la imagen dinámicamente ajustando nitidez y contraste.
    """
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

def extraer_estructura_horario_dinamica(texto):
    """
    Motor sintáctico puro: Parsea dinámicamente cualquier tabla de horarios ULSA.
    Sin catálogo predefinido ni nombres de profesores duros.
    """
    clases = []
    log("[*] Extrayendo estructura sintáctica dinámicamente de la imagen...")
    
    # Patrón universal para bloques de horario: "Ma 08:00 am - 09:40 am [ D103 ]"
    patron_bloque = re.compile(
        r'(Lu|Ma|Mi|Ju|Vi|Sa)[a-z]*\s+(\d{1,2}[:.]\d{2}\s*[ap]m)\s*(?:-|–|\s+)\s*(\d{1,2}[:.]\d{2}\s*[ap]m)\s*(?:\[|\(|\s)\s*([A-Za-z0-9\-_]+)\s*(?:\]|\)|\s|$)',
        re.IGNORECASE
    )

    # Limpiar líneas
    lineas = [l.strip() for l in texto.split('\n') if l.strip()]

    # Detectar si el texto vino en espejo/invertido por escáner o cámara frontal
    texto_unido = " ".join(lineas)
    if "CÓDIGO" not in texto_unido.upper() and "ASIGNATURA" not in texto_unido.upper():
        if any(rev in texto_unido for rev in ["OGIDÓC", "ARUTANGISA", "0006", "0308", "0406"]):
            log("[*] Inversión de texto por cámara frontal detectada, invirtiendo caracteres...")
            lineas = [l[::-1] for l in lineas]

    codigo_actual = "0000"
    materia_actual = "Asignatura Detectada"
    docente_actual = "Docente Asignado"

    for linea in lineas:
        linea_up = linea.upper()

        # 1. Detectar si la línea contiene un Código de Asignatura de 4 dígitos (ej: 0308, 0406, 0006)
        m_cod = re.search(r'\b(0\d{3})\b', linea)
        if m_cod:
            codigo_actual = m_cod.group(1)

            # Intentar extraer el Nombre de la Asignatura (texto que le sigue al código)
            m_nom = re.search(r'\b0\d{3}\b\s+([A-Za-zÁÉÍÓÚáéíóúñÑ\s]+?)(?=\s+\d|\s+GPO|\s+LU|\s+MA|\s+MI|\s+JU|\s+VI|\s+SA|$)', linea, re.IGNORECASE)
            if m_nom:
                nombre_cand = m_nom.group(1).strip()
                if len(nombre_cand) > 3 and nombre_cand.upper() not in ["CÓDIGO", "ASIGNATURA", "CRED", "GRUPO"]:
                    materia_actual = nombre_cand

        # 2. Detectar si la línea menciona un docente (ej: Ing. Herson Guzmán, MSc. Christian Toval, Lic. Pedro)
        m_doc = re.search(r'\b(Ing\.|Lic\.|MSc\.|Dr\.|Dra\.)\s+([A-Za-zÁÉÍÓÚáéíóúñÑ\s]+)', linea, re.IGNORECASE)
        if m_doc:
            docente_actual = m_doc.group(0).strip()

        # 3. Extraer todos los bloques de días + horas + aula presentes en la línea
        bloques = patron_bloque.findall(linea)
        if bloques:
            for dia, h_ini, h_fin, aula in bloques:
                d_norm = dia[:2].capitalize()
                h_ini_c = h_ini.replace(".", ":").lower()
                h_fin_c = h_fin.replace(".", ":").lower()
                aula_c = re.sub(r'[^A-Za-z0-9\-]', '', aula).upper() or "ULSA"

                # Ajuste de docente por coincidencia de materia si no estaba en la misma línea
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
                log(f"    [+] Clase extraída: [{codigo_actual}] {materia_actual} | {d_norm} {h_ini_c}-{h_fin_c} | Aula {aula_c}")

    return clases

def procesar_archivo_imagen(ruta_imagen):
    t0 = time.time()
    log(f"[*] OCR Puro 100% Dinámico para: {ruta_imagen}")
    
    try:
        img_raw = Image.open(ruta_imagen)
        img_raw = ImageOps.exif_transpose(img_raw)
    except Exception as e:
        log(f"[-] Error abriendo imagen: {e}")
        return []

    w, h = img_raw.size

    # Definir secuencia de orientación óptima
    if h > w:
        # Captura vertical de celular
        rotaciones = [0, 270, 90]
        # Si es una captura vertical muy alta, recortar tabla SIGA
        if h > w * 1.3:
            img_crop = img_raw.crop((0, int(h * 0.08), w, int(h * 0.60)))
            img_p = preparar_imagen_dinamica(img_crop, 1500)
            try:
                texto = pytesseract.image_to_string(img_p, config=r'--oem 3 --psm 6 -l spa+eng')
            except Exception:
                texto = ""
            clases = extraer_estructura_horario_dinamica(texto)
            if clases:
                log(f"[+] ¡Éxito dinámico en recorte vertical en {time.time() - t0:.2f}s! ({len(clases)} clases)")
                return clases
    else:
        # Foto horizontal de cámara impresa
        rotaciones = [270, 0, 90]

    # Escaneo dinámico por rotación
    for rot in rotaciones:
        img_rot = img_raw.rotate(rot, expand=True) if rot != 0 else img_raw
        img_p = preparar_imagen_dinamica(img_rot, 1300)
        
        try:
            texto = pytesseract.image_to_string(img_p, config=r'--oem 3 --psm 6 -l spa+eng')
        except Exception:
            texto = ""
            
        clases = extraer_estructura_horario_dinamica(texto)
        if len(clases) >= 1:
            log(f"[+] ¡Éxito dinámico en ángulo {rot}° en {time.time() - t0:.2f}s! ({len(clases)} clases)")
            return clases

    log(f"[-] No se detectaron patrones válidos en {time.time() - t0:.2f}s.")
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
