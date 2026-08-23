# Guía de Defensa ante el Jurado — ULSA Local-Hub

**Asignatura:** Taller de Conectividad (IV Año, Ingeniería en Cibernética Electrónica)  
**Docente:** Ing. Freddy Alexander Mejía Quintana  
**Estudiante:** Eddy Ezequiel Martínez Solórzano  
**Universidad Tecnológica La Salle (ULSA), León, Nicaragua**

---

## 📋 Lista de Verificación Previa (15 minutos antes de exponer)

- [ ] Laptop con Windows encendida y cargada.
- [ ] **Hotspot activo** en Windows con el nombre de red `ULSA-Hub`.
- [ ] Máquina virtual `Debian13-ULSA-Hub` iniciada en VirtualBox.
- [ ] Verificar IP del servidor: abrir terminal en Debian y ejecutar `ip a` (debe mostrar `192.168.137.10`).
- [ ] Celular conectado al Wi-Fi `ULSA-Hub`.
- [ ] Archivo `credentials.json` y `token.json` listos en `/srv/samba/hub/`.

---

## 🎬 Protocolo de Demostración en Vivo (Paso a Paso)

### 1. Presentación del Problema Real (2 minutos)
* **Explicación:** Exponer la congestión de la WAN en el campus de la ULSA y la ineficiencia de transcribir manualmente los horarios oficiales (`Imprimir Inscripción.pdf`) al calendario digital del teléfono.
* **Demostración de Red:** Mostrar cómo la laptop crea una burbuja de red de borde local (**Edge Computing**) usando el Hotspot de Windows en el rango `192.168.137.0/24`.

---

### 2. Demostración de Almacenamiento Local LAN de Alta Velocidad (3 minutos)
1. Pedir a los integrantes del jurado que conecten sus teléfonos a la red Wi-Fi `ULSA-Hub`.
2. En la laptop Windows, abrir el **Explorador de Archivos** y acceder a:
   `\\hub.ulsa.local\hub-compartido` (o `\\192.168.137.10\hub-compartido`).
3. Arrastrar un archivo pesado de varios cientos de megabytes. Mostrar cómo se transfiere a velocidad LAN máxima sin consumir 1 solo kilobyte de internet.
4. Mostrar la interfaz web de Nextcloud en el navegador del teléfono accediendo a:
   `http://hub.ulsa.local/nextcloud`

---

### 3. El Momento Cumbre: Automatización OCR + Google Calendar (5 minutos)
1. **Proyectar la pantalla del teléfono** en la pantalla del aula/proyector. Mostrar que la agenda de Google Calendar está vacía para los días de clase.
2. Abrir la aplicación de correo del teléfono.
3. Redactar un nuevo correo dirigido a: `importar@ulsa.local`.
4. Asunto: *Mi Horario ULSA IIIC-2026*.
5. Adjuntar la captura de pantalla oficial de la hoja de inscripción de la ULSA (`horario_muestra.png`).
6. Presionar **Enviar**.

---

### 4. La Reacción en Cadena (Verificación en Pantalla)
1. Mostrar la consola de logs del servidor Debian en vivo:
   `tail -f /var/log/ulsa-hub.log`
2. Observar cómo el demonio en segundo plano:
   - Detecta la llegada del mensaje en el Maildir de Postfix.
   - Extrae la imagen y aplica preprocesamiento de contraste.
   - Ejecuta **Tesseract OCR** e identifica dinámicamente las asignaturas (Robótica, Taller de Conectividad, IA, Admin Financiera), salones y docentes.
   - Conecta con la **Google Calendar API** e inserta los eventos con **notificación silenciosa (popup) de 20 minutos antes**.
3. **El Resultado:** En menos de 45 segundos, la aplicación Google Calendar del teléfono mostrará en vivo cómo se pueblan todas las clases en sus respectivos días y horarios.
4. Abrir la carpeta compartida Samba `\\hub.ulsa.local\hub-compartido`: allí aparecerá automáticamente el archivo `Mi_Horario_Semanal_ULSA.pdf` generado por **CUPS-PDF** con un formato vectorial institucional listo para guardar o imprimir.

---

## ❓ Preguntas Frecuentes del Jurado (Respuestas Técnicas Prepared)

**P1: ¿Por qué usaron BIND9 en lugar de modificar el archivo `hosts`?**  
*R: Porque los teléfonos celulares sin acceso a root no permiten modificar `/etc/hosts`. Con BIND9, el servidor Debian responde a las consultas DNS locales de todos los clientes conectados al Hotspot.*

**P2: ¿Qué sucede si cambio de cuatrimestre y las clases son diferentes?**  
*R: El algoritmo de parseo con expresiones regulares es 100% dinámico. No tiene nombres de materias ni salones hardcodeados. Al enviar la imagen del nuevo cuatrimestre, el sistema lee cualquier cantidad de materias y bloques.*

**P3: ¿Por qué el calendario usa notificaciones de 20 minutos tipo popup?**  
*R: Para evitar alarmas sonoras molestas durante clases anteriores, enviando notificaciones emergentes discretas en la pantalla del dispositivo 20 minutos antes de cada sesión.*
