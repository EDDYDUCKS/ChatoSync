<?php
$uploadDir = "/srv/samba/hub/";
$allowedExt = ['jpg','jpeg','png','gif','pdf','zip','rar','7z','mp4','mp3','avi','mkv','docx','doc','xlsx','xls','pptx','ppt','txt','apk','iso','ova','exe','py','sh'];
$message = '';
$messageType = '';

// SUBIR ARCHIVO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $file = $_FILES['archivo'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $name = basename($file['name']);
        $name = preg_replace('/[^a-zA-Z0-9\._\-]/', '_', $name);
        $dest = $uploadDir . $name;
        // Si ya existe, agregar timestamp
        if (file_exists($dest)) {
            $info = pathinfo($name);
            $name = $info['filename'] . '_' . time() . '.' . ($info['extension'] ?? 'bin');
            $dest = $uploadDir . $name;
        }
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            chmod($dest, 0777);
            $message = "✅ \"$name\" subido exitosamente al servidor.";
            $messageType = 'success';
        } else {
            $message = "❌ Error al guardar el archivo. Verifique permisos del servidor.";
            $messageType = 'error';
        }
    } else {
        $message = "❌ Error en la subida: código " . $file['error'];
        $messageType = 'error';
    }
}

// ELIMINAR ARCHIVO
if (isset($_GET['del'])) {
    $del = basename($_GET['del']);
    $path = $uploadDir . $del;
    if (file_exists($path) && !is_dir($path)) {
        unlink($path);
        header("Location: transfer.php?msg=deleted");
        exit;
    }
}

// LISTAR ARCHIVOS
$files = [];
if (is_dir($uploadDir)) {
    foreach (scandir($uploadDir) as $f) {
        if ($f === '.' || $f === '..' || is_dir($uploadDir.$f)) continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        $size = filesize($uploadDir.$f);
        $date = filemtime($uploadDir.$f);
        $files[] = ['name' => $f, 'ext' => $ext, 'size' => $size, 'date' => $date];
    }
    usort($files, fn($a,$b) => $b['date'] - $a['date']);
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) return round($bytes/1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes/1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes/1024, 1) . ' KB';
    return $bytes . ' B';
}

function fileIcon($ext) {
    $icons = [
        'pdf' => '📄', 'doc' => '📝', 'docx' => '📝', 'xls' => '📊', 'xlsx' => '📊',
        'ppt' => '📑', 'pptx' => '📑', 'zip' => '🗜️', 'rar' => '🗜️', '7z' => '🗜️',
        'mp4' => '🎬', 'avi' => '🎬', 'mkv' => '🎬', 'mp3' => '🎵',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️',
        'apk' => '📱', 'iso' => '💿', 'ova' => '💻', 'exe' => '⚙️',
        'py' => '🐍', 'sh' => '🔧', 'txt' => '📋'
    ];
    return $icons[$ext] ?? '📁';
}

$serverIP = trim(shell_exec("hostname -I | awk '{print $1}'") ?? '192.168.137.102');
$pageURL = "http://{$serverIP}/transfer.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChatoSync Transfer | Compartir Archivos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .drop-active { border-color: #10b981 !important; background: rgba(16,185,129,0.08) !important; }
        #progressBar { transition: width 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.3s ease; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">

<!-- Header -->
<header class="bg-slate-800/90 border-b border-slate-700/60 sticky top-0 z-50 backdrop-blur-md">
    <div class="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="/" class="text-slate-400 hover:text-white transition-colors">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </a>
            <div class="h-8 w-8 rounded-lg bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center">
                <i class="fa-solid fa-share-nodes text-sm text-white"></i>
            </div>
            <div>
                <h1 class="text-base font-bold text-white">ChatoSync <span class="text-emerald-400">Transfer</span></h1>
                <p class="text-[11px] text-slate-400">Compartir archivos sin Internet • <?= count($files) ?> archivos</p>
            </div>
        </div>
        <div class="flex items-center gap-2 text-xs text-slate-400 bg-slate-700/50 px-3 py-1.5 rounded-lg border border-slate-600/50">
            <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <span class="font-mono text-emerald-400 font-semibold"><?= $serverIP ?></span>
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="max-w-5xl mx-auto px-4 py-6 space-y-6">

    <?php if ($message): ?>
    <div class="fade-in p-4 rounded-xl border <?= $messageType === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-rose-500/10 border-rose-500/30 text-rose-300' ?> text-sm font-medium">
        <?= $message ?>
    </div>
    <?php endif; ?>

    <!-- Grid Superior: Subir + QR -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

        <!-- Zona de Subida (2/3) -->
        <div class="md:col-span-2">
            <div class="rounded-2xl bg-slate-800/60 border border-slate-700/60 p-5 h-full">
                <h2 class="text-base font-bold text-white flex items-center gap-2 mb-4">
                    <i class="fa-solid fa-cloud-arrow-up text-emerald-400"></i>
                    Subir Archivo al Servidor
                </h2>

                <!-- Drop Zone -->
                <form id="uploadForm" method="POST" enctype="multipart/form-data">
                    <div id="dropZone" class="border-2 border-dashed border-slate-600 rounded-xl p-8 text-center transition-all cursor-pointer hover:border-emerald-500 group relative">
                        <input type="file" name="archivo" id="fileInput" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="handleFileSelect(this)">
                        <div id="dropContent" class="pointer-events-none space-y-3">
                            <div class="h-14 w-14 mx-auto rounded-2xl bg-emerald-500/10 group-hover:bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-2xl transition-colors">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-200">Toca aquí o arrastra tu archivo</p>
                                <p class="text-xs text-slate-500 mt-1">ZIP, PDF, APK, ISO, Video, Imagen, Instaladores...</p>
                            </div>
                        </div>

                        <!-- Preview de archivo seleccionado -->
                        <div id="filePreview" class="hidden pointer-events-none space-y-3">
                            <div class="h-14 w-14 mx-auto rounded-2xl bg-blue-500/20 text-blue-400 flex items-center justify-center text-2xl">
                                <i class="fa-solid fa-file-circle-check"></i>
                            </div>
                            <div>
                                <p id="fileName" class="text-sm font-semibold text-white truncate max-w-xs mx-auto"></p>
                                <p id="fileSize" class="text-xs text-slate-400 mt-0.5"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Barra de Progreso -->
                    <div id="progressContainer" class="hidden mt-3 space-y-1">
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span>Subiendo al servidor...</span>
                            <span id="progressText">0%</span>
                        </div>
                        <div class="h-2 bg-slate-700 rounded-full overflow-hidden">
                            <div id="progressBar" class="h-full bg-gradient-to-r from-emerald-500 to-teal-400 rounded-full" style="width:0%"></div>
                        </div>
                    </div>

                    <!-- Botón Enviar -->
                    <button type="button" id="uploadBtn" onclick="submitUpload()" class="hidden mt-3 w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 transition-colors text-white font-semibold text-sm flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/20">
                        <i class="fa-solid fa-rocket"></i>
                        Enviar al Servidor Ahora
                    </button>
                </form>

                <!-- Info SMB -->
                <div class="mt-4 p-3 rounded-xl bg-slate-900/60 border border-slate-700/40 text-xs">
                    <div class="text-slate-400 mb-1 flex items-center gap-1.5">
                        <i class="fa-solid fa-folder-open text-amber-400"></i>
                        También puedes arrastrar archivos directamente desde Windows:
                    </div>
                    <code class="text-emerald-400 font-mono select-all">\\<?= $serverIP ?>\hub</code>
                </div>
            </div>
        </div>

        <!-- Código QR (1/3) -->
        <div class="md:col-span-1">
            <div class="rounded-2xl bg-slate-800/60 border border-slate-700/60 p-5 h-full flex flex-col items-center justify-center text-center space-y-4">
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-qrcode text-emerald-400"></i>
                    Escanea para Compartir
                </h2>
                <p class="text-xs text-slate-400">Abre con la cámara de tu celular desde la misma red Wi-Fi</p>
                <div class="bg-white p-3 rounded-xl shadow-xl shadow-black/30">
                    <div id="qrcode"></div>
                </div>
                <div class="text-[10px] text-slate-500 font-mono break-all px-2"><?= $pageURL ?></div>
                <div class="flex items-center gap-1.5 text-xs text-emerald-400 font-medium">
                    <i class="fa-solid fa-wifi"></i>
                    Conectar a: <strong>ULSA-Hub</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Listado de Archivos -->
    <div class="rounded-2xl bg-slate-800/60 border border-slate-700/60 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-folder-open text-amber-400"></i>
                Archivos Disponibles para Descargar
                <span class="px-2 py-0.5 rounded-full bg-slate-700 text-slate-300 text-xs font-semibold"><?= count($files) ?></span>
            </h2>
            <button onclick="location.reload()" class="text-xs text-slate-400 hover:text-emerald-400 transition-colors flex items-center gap-1">
                <i class="fa-solid fa-rotate"></i> Actualizar
            </button>
        </div>

        <?php if (empty($files)): ?>
        <div class="text-center py-12 text-slate-500">
            <i class="fa-solid fa-inbox text-4xl mb-3 block opacity-30"></i>
            <p class="text-sm">No hay archivos todavía.</p>
            <p class="text-xs mt-1">Sube el primero desde tu laptop o celular.</p>
        </div>
        <?php else: ?>
        <div class="space-y-2" id="fileList">
            <?php foreach ($files as $f): ?>
            <div class="group flex items-center gap-3 p-3 rounded-xl bg-slate-900/50 hover:bg-slate-700/50 border border-slate-700/40 hover:border-slate-600/60 transition-all">
                <div class="text-2xl flex-shrink-0"><?= fileIcon($f['ext']) ?></div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate"><?= htmlspecialchars($f['name']) ?></p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <?= formatSize($f['size']) ?> • <?= date('d/m/Y H:i', $f['date']) ?>
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="download.php?file=<?= urlencode($f['name']) ?>" 
                       class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-md shadow-emerald-600/20">
                        <i class="fa-solid fa-download"></i>
                        <span class="hidden sm:inline">Descargar</span>
                    </a>
                    <a href="?del=<?= urlencode($f['name']) ?>" 
                       onclick="return confirm('¿Eliminar <?= htmlspecialchars($f['name']) ?>?')"
                       class="p-1.5 rounded-lg bg-slate-700 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 text-xs transition-colors">
                        <i class="fa-solid fa-trash"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</main>

<!-- Scripts -->
<script>
// Generar QR Code
new QRCode(document.getElementById("qrcode"), {
    text: "<?= $pageURL ?>",
    width: 160,
    height: 160,
    colorDark: "#000000",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.M
});

// Drag & Drop
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');

dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('drop-active'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drop-active'));
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('drop-active');
    if (e.dataTransfer.files.length > 0) {
        const dt = new DataTransfer();
        dt.items.add(e.dataTransfer.files[0]);
        fileInput.files = dt.files;
        showPreview(e.dataTransfer.files[0]);
    }
});

function handleFileSelect(input) {
    if (input.files.length > 0) showPreview(input.files[0]);
}

function showPreview(file) {
    document.getElementById('dropContent').classList.add('hidden');
    document.getElementById('filePreview').classList.remove('hidden');
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = formatFileSize(file.size);
    document.getElementById('uploadBtn').classList.remove('hidden');
    document.getElementById('uploadBtn').classList.add('flex');
}

function formatFileSize(bytes) {
    if (bytes >= 1073741824) return (bytes/1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes/1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return (bytes/1024).toFixed(1) + ' KB';
    return bytes + ' B';
}

function submitUpload() {
    if (!fileInput.files.length) return;
    const btn = document.getElementById('uploadBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';
    
    const formData = new FormData();
    formData.append('archivo', fileInput.files[0]);
    
    const xhr = new XMLHttpRequest();
    document.getElementById('progressContainer').classList.remove('hidden');
    
    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            document.getElementById('progressBar').style.width = pct + '%';
            document.getElementById('progressText').textContent = pct + '%';
        }
    };
    
    xhr.onload = function() { window.location.reload(); };
    xhr.onerror = function() { 
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-rocket"></i> Reintentar';
    };
    
    xhr.open('POST', 'transfer.php', true);
    xhr.send(formData);
}
</script>
</body>
</html>
