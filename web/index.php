<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChatoSync Hub | Panel de Control & OCR</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .ulsa-green { color: #006633; }
        .bg-ulsa-green { background-color: #006633; }
        .bg-ulsa-dark { background-color: #004d26; }
        .border-ulsa { border-color: #006633; }
        .custom-glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        .pulse-green { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); animation: pulse 2s infinite; }
        @keyframes pulse { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); } }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">

    <!-- Top Navigation -->
    <header class="bg-slate-800/80 border-b border-slate-700/60 sticky top-0 z-50 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/20">
                    <i class="fa-solid fa-graduation-cap text-xl text-white"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight bg-gradient-to-r from-emerald-400 to-teal-200 bg-clip-text text-transparent">
                        ChatoSync <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-semibold border border-emerald-500/30">ULSA Hub</span>
                    </h1>
                    <p class="text-xs text-slate-400">Servidor Edge & Sincronización Inteligente</p>
                </div>
            </div>

            <!-- Server Badges -->
            <div class="flex items-center gap-4">
                <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-700/50 border border-slate-600/50 text-xs">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 pulse-green"></span>
                    <span class="text-slate-300">IP Servidor: <strong class="text-white" id="serverIp">192.168.137.102</strong></span>
                </div>
                <a href="/transfer.php" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 transition-colors text-xs font-semibold text-white flex items-center gap-1.5 shadow-md shadow-emerald-600/20">
                    <i class="fa-solid fa-share-nodes"></i> Transfer
                </a>
                <a href="/nextcloud" target="_blank" class="px-3.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 transition-colors text-xs font-semibold text-white flex items-center gap-1.5 shadow-md shadow-blue-600/20">
                    <i class="fa-solid fa-cloud"></i> Nextcloud
                </a>
                <a href="http://192.168.137.102:631" target="_blank" class="px-3.5 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 transition-colors text-xs font-semibold text-slate-200 flex items-center gap-1.5 border border-slate-600">
                    <i class="fa-solid fa-print"></i> CUPS
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Banner de Bienvenida -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800 via-slate-800/90 to-emerald-950/40 border border-slate-700/60 p-6 md:p-8 shadow-xl">
            <div class="relative z-10 max-w-3xl space-y-3">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-medium">
                    <i class="fa-solid fa-bolt"></i> Sistema Autónomo 100% Local
                </div>
                <h2 class="text-2xl md:text-3xl font-extrabold text-white">
                    Bienvenido al Panel de Control ChatoSync
                </h2>
                <p class="text-slate-300 text-sm leading-relaxed">
                    Sube una captura de tu horario de clases de la ULSA o arrástrala a la carpeta Samba compartida. El motor OCR extraerá automáticamente las materias, asignará aulas y generará tu calendario listo para sincronizar.
                </p>
                <div class="pt-2 flex flex-wrap gap-3">
                    <button onclick="testSampleSchedule()" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold flex items-center gap-2 shadow-lg shadow-emerald-600/30 transition-all">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Probar Horario de Muestra ULSA
                    </button>
                    <a href="download_ics.php" class="px-4 py-2 rounded-xl bg-slate-700/80 hover:bg-slate-600 text-slate-200 text-sm font-semibold flex items-center gap-2 border border-slate-600 transition-all">
                        <i class="fa-solid fa-calendar-arrow-down"></i> Descargar .ICS (Google Calendar)
                    </a>
                </div>
            </div>
        </div>

        <!-- 6 Módulos del Sistema / Estados en Vivo -->
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-server text-emerald-400"></i> Estado de los 6 Módulos de Red
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3" id="serviceCards">
                <!-- Se llenan automáticamente vía JavaScript -->
                <div class="p-4 rounded-xl bg-slate-800/60 border border-slate-700/60 text-center animate-pulse">
                    <div class="text-xs text-slate-400">Cargando módulos...</div>
                </div>
            </div>
        </div>

        <!-- Sección Principal: Subida y Horario Extraído -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <!-- Columna Izquierda: Subir Archivo (5 cols) -->
            <div class="lg:col-span-5 space-y-4">
                <div class="rounded-2xl bg-slate-800/60 border border-slate-700/60 p-6 space-y-4">
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-file-arrow-up text-emerald-400"></i> Subir Horario (OCR)
                    </h3>
                    <p class="text-xs text-slate-400">
                        Soporta imágenes PNG, JPG o PDF de tu hoja de inscripción o captura de pantalla.
                    </p>

                    <!-- Zona Drag and Drop -->
                    <div id="dropZone" class="border-2 border-dashed border-slate-600 hover:border-emerald-500 rounded-xl p-8 text-center transition-colors cursor-pointer bg-slate-900/40 group">
                        <input type="file" id="fileInput" class="hidden" accept="image/*,.pdf">
                        <div class="space-y-3">
                            <div class="h-12 w-12 mx-auto rounded-full bg-emerald-500/10 group-hover:bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl transition-colors">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-200">Haz clic o arrastra tu archivo aquí</p>
                                <p class="text-xs text-slate-500 mt-1">PNG, JPG, JPEG hasta 20 MB</p>
                            </div>
                        </div>
                    </div>

                    <!-- Indicador de Carga -->
                    <div id="uploadLoader" class="hidden text-center py-4 space-y-2">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-emerald-500 border-t-transparent"></div>
                        <p class="text-xs font-semibold text-emerald-400">Procesando OCR con Tesseract en el servidor...</p>
                    </div>

                    <!-- Información de Carpeta de Red -->
                    <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-700/40 text-xs space-y-1">
                        <div class="text-slate-300 font-semibold flex items-center gap-1.5">
                            <i class="fa-solid fa-folder-open text-amber-400"></i> Carpeta de Red Samba:
                        </div>
                        <code class="text-emerald-400 font-mono select-all">\\192.168.137.102\hub\entrada</code>
                    </div>
                </div>

                <!-- Consola de Logs en Vivo -->
                <div class="rounded-2xl bg-slate-800/60 border border-slate-700/60 p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-300 flex items-center gap-2">
                            <i class="fa-solid fa-terminal text-emerald-400"></i> Registros de Actividad (Logs)
                        </h3>
                        <button onclick="refreshLogs()" class="text-xs text-slate-400 hover:text-emerald-400 transition-colors">
                            <i class="fa-solid fa-rotate"></i>
                        </button>
                    </div>
                    <pre id="logViewer" class="p-3 rounded-xl bg-slate-950 text-slate-300 text-xs font-mono h-48 overflow-y-auto whitespace-pre-wrap border border-slate-800">Cargando registros del sistema...</pre>
                </div>
            </div>

            <!-- Columna Derecha: Tabla de Horario Extraído (7 cols) -->
            <div class="lg:col-span-7">
                <div class="rounded-2xl bg-slate-800/60 border border-slate-700/60 p-6 space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                                <i class="fa-solid fa-calendar-check text-emerald-400"></i> Horario de Clases Detectado
                            </h3>
                            <p class="text-xs text-slate-400" id="lastUpdatedText">Sin procesar</p>
                        </div>
                        <div class="flex gap-2">
                            <a id="btnDownloadIcs" href="download_ics.php" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-xs font-semibold text-white flex items-center gap-1.5 shadow-md shadow-emerald-600/20">
                                <i class="fa-solid fa-calendar-plus"></i> Añadir a Calendario (.ics)
                            </a>
                        </div>
                    </div>

                    <!-- Tabla de Clases -->
                    <div class="overflow-x-auto rounded-xl border border-slate-700/60">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-900/80 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-700/60">
                                <tr>
                                    <th class="p-3.5">Código</th>
                                    <th class="p-3.5">Asignatura</th>
                                    <th class="p-3.5">Día</th>
                                    <th class="p-3.5">Horario</th>
                                    <th class="p-3.5">Aula</th>
                                    <th class="p-3.5">Docente</th>
                                </tr>
                            </thead>
                            <tbody id="scheduleTableBody" class="divide-y divide-slate-700/40 text-slate-200">
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-500">
                                        <i class="fa-solid fa-inbox text-3xl mb-2 block opacity-40"></i>
                                        Sube un horario o presiona "Probar Horario de Muestra" para visualizar las clases aquí.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <!-- Scripts JavaScript -->
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const uploadLoader = document.getElementById('uploadLoader');
        const scheduleTableBody = document.getElementById('scheduleTableBody');
        const lastUpdatedText = document.getElementById('lastUpdatedText');
        const logViewer = document.getElementById('logViewer');
        const serviceCards = document.getElementById('serviceCards');

        dropZone.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) uploadFile(e.target.files[0]);
        });

        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('border-emerald-500'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-emerald-500'));
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-emerald-500');
            if (e.dataTransfer.files.length > 0) uploadFile(e.dataTransfer.files[0]);
        });

        function uploadFile(file) {
            uploadLoader.classList.remove('hidden');
            const formData = new FormData();
            formData.append('action', 'upload');
            formData.append('horario', file);

            fetch('api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    uploadLoader.classList.add('hidden');
                    if (res.status === 'ok') {
                        renderSchedule(res.clases);
                        refreshLogs();
                    } else {
                        alert(res.message || 'Error al procesar el horario.');
                    }
                })
                .catch(err => {
                    uploadLoader.classList.add('hidden');
                    alert('Error en la comunicación con el servidor: ' + err);
                });
        }

        function testSampleSchedule() {
            uploadLoader.classList.remove('hidden');
            fetch('api.php?action=test_sample')
                .then(r => r.json())
                .then(res => {
                    uploadLoader.classList.add('hidden');
                    if (res.status === 'ok') {
                        renderSchedule(res.clases);
                        refreshLogs();
                    } else {
                        alert(res.message);
                    }
                })
                .catch(err => {
                    uploadLoader.classList.add('hidden');
                    alert('Error: ' + err);
                });
        }

        function renderSchedule(clases) {
            if (!clases || clases.length === 0) {
                scheduleTableBody.innerHTML = `<tr><td colspan="6" class="p-8 text-center text-slate-500">No se detectaron clases válidas en la imagen.</td></tr>`;
                return;
            }

            lastUpdatedText.innerText = `Total: ${clases.length} clases detectadas dinámicamente`;

            scheduleTableBody.innerHTML = clases.map(c => `
                <tr class="hover:bg-slate-700/30 transition-colors">
                    <td class="p-3.5 font-mono font-bold text-emerald-400">${c.codigo}</td>
                    <td class="p-3.5 font-medium text-white">${c.materia}</td>
                    <td class="p-3.5"><span class="px-2 py-0.5 rounded bg-slate-700 font-semibold text-slate-200">${c.dia_completo || c.dia}</span></td>
                    <td class="p-3.5 font-mono text-slate-300">${c.hora_inicio} - ${c.hora_fin}</td>
                    <td class="p-3.5"><span class="px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 font-bold border border-rose-500/30">${c.aula}</span></td>
                    <td class="p-3.5 text-slate-300">${c.docente}</td>
                </tr>
            `).join('');
        }

        function refreshStatus() {
            fetch('api.php?action=status')
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'ok') {
                        document.getElementById('serverIp').innerText = data.ip;
                        const services = data.services;
                        let html = '';
                        for (const key in services) {
                            const s = services[key];
                            const isAct = s.active;
                            html += `
                                <div class="p-3 rounded-xl bg-slate-800/80 border ${isAct ? 'border-emerald-500/30' : 'border-rose-500/30'} flex flex-col justify-between">
                                    <div class="text-[11px] text-slate-400 font-medium truncate">${s.name}</div>
                                    <div class="flex items-center justify-between mt-2">
                                        <span class="text-xs font-bold ${isAct ? 'text-emerald-400' : 'text-rose-400'}">${isAct ? 'ACTIVO' : 'INACTIVO'}</span>
                                        <span class="h-2 w-2 rounded-full ${isAct ? 'bg-emerald-400 pulse-green' : 'bg-rose-500'}"></span>
                                    </div>
                                </div>
                            `;
                        }
                        serviceCards.innerHTML = html;
                    }
                });
        }

        function refreshLogs() {
            fetch('api.php?action=logs')
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'ok') {
                        logViewer.innerText = d.logs;
                        logViewer.scrollTop = logViewer.scrollHeight;
                    }
                });
        }

        function loadInitialData() {
            fetch('api.php?action=last_data')
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'ok' && d.data && d.data.clases) {
                        renderSchedule(d.data.clases);
                    }
                });
        }

        // Iniciar
        refreshStatus();
        refreshLogs();
        loadInitialData();
        setInterval(refreshStatus, 10000);
        setInterval(refreshLogs, 5000);
    </script>
</body>
</html>
