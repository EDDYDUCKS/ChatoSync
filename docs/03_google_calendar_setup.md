# Guía 03: Configuración de la API de Google Calendar

Esta guía explica cómo habilitar la API oficial de Google Calendar para que el servidor de borde **ULSA Local-Hub** pueda insertar tus clases de la ULSA directamente en tu calendario con notificaciones de 20 minutos.

---

## Paso 1: Crear Proyecto en Google Cloud Console (Solo 1 vez, Gratuito)

1. Ingresar a [Google Cloud Console](https://console.cloud.google.com/).
2. Iniciar sesión con tu cuenta personal de Google (la misma que usas en tu teléfono Android/iPhone).
3. Hacer clic en el selector de proyectos (arriba a la izquierda) → **Proyecto Nuevo**.
4. Nombre del proyecto: `ULSA-Local-Hub` → **Crear**.

---

## Paso 2: Habilitar la Google Calendar API

1. En el menú lateral izquierdo, ir a **API y servicios** → **Biblioteca**.
2. En el buscador escribe `Google Calendar API`.
3. Seleccionar **Google Calendar API** y hacer clic en **Habilitar**.

---

## Paso 3: Configurar Pantalla de Consentimiento OAuth

1. Ir a **API y servicios** → **Pantalla de consentimiento de OAuth**.
2. Tipo de usuario: Seleccionar **Externo** → **Crear**.
3. Rellenar los campos requeridos:
   - Nombre de la app: `ULSA Local-Hub Schedule Service`
   - Correo de soporte del usuario: Tu correo de Gmail.
   - Datos de contacto del desarrollador: Tu correo de Gmail.
4. Hacer clic en **Guardar y continuar**.
5. En **Permisos (Scopes)**: Hacer clic en **Agregar o quitar permisos**, buscar `https://www.googleapis.com/auth/calendar.events` y marcarlo.
6. En **Usuarios de prueba**: Agregar tu propia dirección de correo de Gmail.
7. Guardar los cambios.

---

## Paso 4: Crear Credenciales OAuth 2.0 (Archivo `credentials.json`)

1. Ir a **API y servicios** → **Credenciales**.
2. Hacer clic en **+ Crear credenciales** → **ID de cliente de OAuth**.
3. Tipo de aplicación: **Aplicación de escritorio**.
4. Nombre: `Debian-Local-Hub-Client`.
5. Hacer clic en **Crear**.
6. En la ventana emergente, hacer clic en **DESCARGAR JSON**.
7. Renombrar el archivo descargado a `credentials.json`.
8. Copiar el archivo `credentials.json` en tu VM de Debian 13 en la ruta:
   `/srv/samba/hub/credentials.json`

---

## Paso 5: Autenticación Inicial y Generación del `token.json`

En tu máquina Debian o desde tu entorno local, ejecuta la primera sincronización. Al ejecutar el script por primera vez, se abrirá un enlace en el navegador donde iniciarás sesión con tu cuenta de Google y autorizarás la aplicación. Esto generará el archivo `token.json` que permitirá que el servidor funcione de forma 100% autónoma en segundo plano sin pedir contraseña nunca más.
