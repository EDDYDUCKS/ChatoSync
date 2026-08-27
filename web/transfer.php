<?php
// ── Manejar subida de archivos ──────────────────────────────────────────────
$uploadDir = "/srv/samba/hub/";
$message = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    header('Content-Type: application/json; charset=utf-8');
    $file = $_FILES['archivo'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        @chmod($uploadDir, 0777);

        $name = preg_replace('/[^a-zA-Z0-9\._\-]/', '_', basename($file['name']));
        $dest = $uploadDir . $name;
        if (file_exists($dest)) {
            $info = pathinfo($name);
            $name = $info['filename'] . '_' . time() . '.' . ($info['extension'] ?? 'bin');
            $dest = $uploadDir . $name;
        }
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            @chmod($dest, 0777);
            echo json_encode(['status' => 'ok', 'message' => "Archivo \"$name\" subido exitosamente."]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Error al guardar en el servidor. Verifique permisos en $uploadDir"]);
        }
    } else {
        $errText = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE => 'El archivo supera el tamaño máximo permitido por PHP (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo del formulario.',
            UPLOAD_ERR_PARTIAL => 'La subida se interrumpió.',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal en el servidor.',
            UPLOAD_ERR_CANT_WRITE => 'Error de escritura en disco en el servidor.',
            default => 'Código de error: ' . $file['error']
        };
        echo json_encode(['status' => 'error', 'message' => $errText]);
    }
    exit;
}

if (isset($_GET['del'])) {
    $del = basename($_GET['del']); $path = $uploadDir.$del;
    if (file_exists($path) && !is_dir($path)) { unlink($path); header("Location: transfer.php"); exit; }
}

// Archivos del sistema que NO deben mostrarse al público
$SYSTEM_FILES = ['ultimo_horario.json','horario_ulsa.ics','procesar_horario.py','chatosync.service'];
$SYSTEM_EXTS  = ['py','sh','json','ics','log','conf','service'];

$files = [];
if (is_dir($uploadDir)) {
    foreach (scandir($uploadDir) as $f) {
        if ($f==='.'||$f==='..'||is_dir($uploadDir.$f)) continue;
        if (str_starts_with($f,'.')) continue; // archivos ocultos
        if (in_array($f, $SYSTEM_FILES)) continue; // blacklist por nombre
        $ext = strtolower(pathinfo($f,PATHINFO_EXTENSION));
        if (in_array($ext, $SYSTEM_EXTS)) continue; // blacklist por extensión
        $files[] = ['name'=>$f,'size'=>filesize($uploadDir.$f),'date'=>filemtime($uploadDir.$f)];
    }
    usort($files, fn($a,$b)=>$b['date']-$a['date']);
}


function fmtSz($b){if($b>=1073741824)return round($b/1073741824,2).' GB';if($b>=1048576)return round($b/1048576,1).' MB';if($b>=1024)return round($b/1024,1).' KB';return $b.' B';}
function fmtIco($e){$m=['pdf'=>'📄','doc'=>'📝','docx'=>'📝','zip'=>'🗜️','rar'=>'🗜️','7z'=>'🗜️','mp4'=>'🎬','avi'=>'🎬','mkv'=>'🎬','mp3'=>'🎵','jpg'=>'🖼️','jpeg'=>'🖼️','png'=>'🖼️','apk'=>'📱','iso'=>'💿','ova'=>'💻','exe'=>'⚙️','py'=>'🐍','sh'=>'🔧','txt'=>'📋'];return $m[$e]??'📁';}
$serverIP = trim(shell_exec("hostname -I | awk '{print $1}'") ?? '192.168.137.102');
$totalSize = array_sum(array_column($files,'size'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ChatoSync · Transfer</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
/* ── Dark theme (default) ── */
:root{
    --red:#dc2626;--red2:#ef4444;--redbg:rgba(220,38,38,.12);
    --bg:#0a0a0a;--card:#111111;--card2:#1a1a1a;--border:#2a2a2a;
    --text:#e5e5e5;--text2:#aaaaaa;--muted:#555555;
}
/* ── Light theme ── */
[data-theme="light"]{
    --bg:#f8fafc;--card:#ffffff;--card2:#f1f5f9;--border:#e2e8f0;
    --text:#0f172a;--text2:#334155;--muted:#64748b;
}
[data-theme="light"] body { background: #f8fafc !important; color: #0f172a !important; }
[data-theme="light"] .text-white,
[data-theme="light"] [class*="text-white"],
[data-theme="light"] strong,
[data-theme="light"] h1,
[data-theme="light"] h2,
[data-theme="light"] h3,
[data-theme="light"] h4 { color: #0f172a !important; }

[data-theme="light"] .text-slate-100,
[data-theme="light"] .text-slate-200,
[data-theme="light"] .text-slate-300 { color: #1e293b !important; }
[data-theme="light"] .text-slate-400 { color: #475569 !important; }
[data-theme="light"] .text-slate-500 { color: #64748b !important; }

[data-theme="light"] [style*="color:#fff"],
[data-theme="light"] [style*="color: #fff"],
[data-theme="light"] [style*="color:#ffffff"],
[data-theme="light"] [style*="color: #ffffff"],
[data-theme="light"] [style*="color:white"] { color: #0f172a !important; }

[data-theme="light"] [style*="color:#aaa"],
[data-theme="light"] [style*="color:#888"],
[data-theme="light"] [style*="color:#666"],
[data-theme="light"] [style*="color:#555"] { color: #475569 !important; }

*{box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .25s,color .25s;}
::-webkit-scrollbar{width:5px;} ::-webkit-scrollbar-track{background:var(--card2);} ::-webkit-scrollbar-thumb{background:var(--border);border-radius:9px;}
@keyframes fadeUp{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}
.fade-up{animation:fadeUp .35s ease both;}
.row{transition:background .15s;}
.row:hover{background:rgba(220,38,38,.06);}
#dropZ.over{border-color:var(--red)!important;background:var(--redbg)!important;}
.themed-card{background:var(--card);border:1px solid var(--border);}
.themed-sub{background:var(--card2);border:1px solid var(--border);}
.t-muted{color:var(--muted);}
.t-text2{color:var(--text2);}
/* Theme toggle button */
#themeBtn{cursor:pointer;transition:all .2s;}
#themeBtn:hover{opacity:.8;}
</style>
</head>
<body>

<!-- Header -->
<header class="h-14 flex items-center justify-between px-6 border-b sticky top-0 z-30"
        style="background:var(--bg);border-color:var(--border);">
    <div class="flex items-center gap-4">
        <a href="/" class="flex items-center gap-2 text-sm font-medium transition-colors t-muted"
           onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
            <i class="fa-solid fa-arrow-left text-xs"></i> Dashboard
        </a>
        <div class="h-4 w-px" style="background:var(--border);"></div>
        <div class="flex items-center gap-2">
            <div class="h-6 w-6 rounded flex items-center justify-center text-xs font-black text-white" style="background:var(--red);">
                <i class="fa-solid fa-share-nodes" style="font-size:10px;"></i>
            </div>
            <span class="text-sm font-bold" style="color:var(--text);">ChatoSync <span style="color:var(--red2);">Transfer</span></span>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <!-- Theme Toggle -->
        <button id="themeBtn" onclick="toggleTheme()"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium"
                style="background:var(--card2);border:1px solid var(--border);color:var(--text2);">
            <i id="themeIcon" class="fa-solid fa-moon"></i>
            <span id="themeLabel">Claro</span>
        </button>
        <div class="flex items-center gap-2 text-xs font-mono px-3 py-1.5 rounded-lg"
             style="background:var(--card);border:1px solid var(--border);">
            <span class="h-1.5 w-1.5 rounded-full" style="background:#22c55e;box-shadow:0 0 6px #22c55e;"></span>
            <span style="color:#22c55e;"><?=$serverIP?></span>
            <span class="t-muted">· <?=count($files)?> archivos · <?=fmtSz($totalSize)?></span>
        </div>
    </div>
</header>


<main class="max-w-5xl mx-auto px-4 py-6 space-y-5">

    <?php if($message):?>
    <div class="fade-up p-4 rounded-xl text-sm font-medium"
         style="background:<?=$msgType==='ok'?'rgba(34,197,94,.1)':'var(--redbg)'?>;
                border:1px solid <?=$msgType==='ok'?'rgba(34,197,94,.3)':'rgba(220,38,38,.3)'?>;
                color:<?=$msgType==='ok'?'#22c55e':'var(--red2)'?>;">
        <?=$message?>
    </div>
    <?php endif;?>

    <!-- Upload + QR grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

        <!-- Upload (2/3) -->
        <div class="md:col-span-2 rounded-xl" style="background:var(--card);border:1px solid var(--border);">
            <div class="px-5 py-4 border-b" style="border-color:var(--border);">
                <h2 class="text-sm font-bold text-white">
                    <i class="fa-solid fa-cloud-arrow-up mr-2" style="color:var(--red);"></i>Subir al Servidor
                </h2>
                <p class="text-xs mt-0.5" style="color:#555;">Cualquier archivo — todos en la red ULSA-Hub pueden descargarlo</p>
            </div>
            <div class="p-5 space-y-3">
                <form id="uploadForm" method="POST" enctype="multipart/form-data">
                    <div id="dropZ" class="border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-all relative"
                         style="border-color:#2a2a2a;"
                         onclick="document.getElementById('fi').click()">
                        <input type="file" name="archivo" id="fi" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                               onchange="handleSel(this)">
                        <div id="dc" class="space-y-3 pointer-events-none">
                            <div class="h-14 w-14 mx-auto rounded-2xl flex items-center justify-center text-2xl"
                                 style="background:var(--redbg);">
                                <i class="fa-solid fa-cloud-arrow-up" style="color:var(--red2);"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-white">Toca aquí o arrastra tu archivo</p>
                                <p class="text-xs mt-1" style="color:#555;">ZIP, APK, ISO, Video, PDF, Imagen, Instaladores…</p>
                            </div>
                        </div>
                        <div id="fp" class="hidden space-y-2 pointer-events-none">
                            <i class="fa-solid fa-file-circle-check text-3xl" style="color:var(--red2);"></i>
                            <p id="fn" class="text-sm font-semibold text-white truncate max-w-xs mx-auto"></p>
                            <p id="fs" class="text-xs" style="color:#888;"></p>
                        </div>
                    </div>

                    <div id="pg" class="hidden space-y-1">
                        <div class="flex justify-between text-xs" style="color:#555;">
                            <span>Subiendo…</span><span id="pt">0%</span>
                        </div>
                        <div class="h-1.5 rounded-full" style="background:#1a1a1a;">
                            <div id="pb" class="h-full rounded-full" style="width:0%;background:var(--red);transition:width .2s;"></div>
                        </div>
                    </div>

                    <button type="button" id="ubtn" onclick="doUpload()"
                            class="hidden w-full py-3 rounded-xl text-sm font-bold text-white mt-3"
                            style="background:var(--red);">
                        <i class="fa-solid fa-rocket mr-2"></i>Enviar al Servidor
                    </button>
                </form>

                <div class="p-3 rounded-lg text-xs" style="background:#0d0d0d;border:1px solid var(--border);">
                    <span style="color:#555;"><i class="fa-solid fa-folder-open mr-1" style="color:#f59e0b;"></i>Acceso directo Windows:</span>
                    <code class="ml-2 font-mono" style="color:var(--red2);">\\<?=$serverIP?>\hub</code>
                </div>
            </div>
        </div>

        <!-- QR (1/3) -->
        <div class="rounded-xl flex flex-col items-center justify-center text-center p-5 space-y-4"
             style="background:var(--card);border:1px solid var(--border);">
            <div class="text-xs font-semibold uppercase tracking-wider" style="color:#555;">
                <i class="fa-solid fa-qrcode mr-1" style="color:var(--red);"></i>Escanea para acceder
            </div>
            <div class="bg-white p-2.5 rounded-xl shadow-2xl">
                <div id="qrcode"></div>
            </div>
            <p class="text-xs" style="color:#555;">
                Conecta tu celular a<br>
                <strong class="text-white">ULSA-Hub</strong> Wi-Fi y escanea
            </p>
            <code class="text-[10px] font-mono break-all" style="color:var(--red2);">http://<?=$serverIP?>/transfer.php</code>
        </div>
    </div>

    <!-- File list -->
    <div class="rounded-xl" style="background:var(--card);border:1px solid var(--border);">
        <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:var(--border);">
            <h2 class="text-sm font-bold text-white">
                Archivos Disponibles
                <span class="ml-2 text-xs px-2 py-0.5 rounded font-semibold"
                      style="background:var(--redbg);color:var(--red2);"><?=count($files)?></span>
            </h2>
            <button onclick="location.reload()" class="text-xs" style="color:#555;">
                <i class="fa-solid fa-rotate"></i> Actualizar
            </button>
        </div>

        <?php if(empty($files)):?>
        <div class="p-12 text-center text-xs" style="color:#444;">
            <i class="fa-solid fa-inbox text-3xl mb-2 block opacity-20"></i>
            Hub vacío — sube el primer archivo arriba
        </div>
        <?php else: foreach($files as $f):
            $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));?>
        <div class="row flex items-center gap-3 px-5 py-3 border-b transition-colors"
             style="border-color:var(--border);">
            <span class="text-xl flex-shrink-0"><?=fmtIco($ext)?></span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-white truncate"><?=htmlspecialchars($f['name'])?></p>
                <p class="text-[11px] mt-0.5" style="color:#555;">
                    <?=fmtSz($f['size'])?> · <?=date('d/m/Y H:i',$f['date'])?>
                </p>
            </div>
            <?php
            $previewableExts = ['pdf','png','jpg','jpeg','gif','webp','svg','mp4','webm','mp3','wav','ogg','txt','log','json','py','sh','md','csv'];
            $canPreview = in_array($ext, $previewableExts);
            ?>
            <div class="flex items-center gap-2 flex-shrink-0">
                <?php if ($canPreview): ?>
                <button type="button"
                        onclick="openPreview('<?=urlencode($f['name'])?>', '<?=$ext?>', '<?=htmlspecialchars(addslashes($f['name']))?>', '<?=fmtSz($f['size'])?>')"
                        class="px-2.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors"
                        style="background:var(--card2);border:1px solid var(--border);color:var(--text);"
                        title="Previsualizar archivo">
                    <i class="fa-solid fa-eye" style="color:var(--red2);"></i>
                    <span class="hidden sm:inline">Ver</span>
                </button>
                <?php endif; ?>
                <a href="/download.php?file=<?=urlencode($f['name'])?>"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white flex items-center gap-1.5 shadow-sm"
                   style="background:var(--red);">
                    <i class="fa-solid fa-download"></i>
                    <span class="hidden sm:inline">Descargar</span>
                </a>
                <a href="?del=<?=urlencode($f['name'])?>"
                   onclick="return confirm('¿Eliminar <?=htmlspecialchars($f['name'])?>?')"
                   class="p-1.5 rounded-lg text-xs transition-colors"
                   style="background:#1a1a1a;border:1px solid var(--border);color:#555;"
                   title="Eliminar archivo">
                    <i class="fa-solid fa-trash"></i>
                </a>
            </div>
        </div>
        <?php endforeach; endif;?>
    </div>

</main>

<!-- ═══════════════════ MODAL DE PREVISUALIZACIÓN ═══════════════════ -->
<div id="previewModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="themed-card rounded-2xl w-full max-w-4xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden border"
         style="background:var(--card);border-color:var(--border);">
        <!-- Modal Header -->
        <div class="h-14 px-5 flex items-center justify-between border-b flex-shrink-0"
             style="border-color:var(--border);">
            <div class="flex items-center gap-2 min-w-0 pr-3">
                <span id="modalIcon" class="text-lg flex-shrink-0">📄</span>
                <h3 id="modalTitle" class="text-sm font-bold truncate text-white">Nombre de archivo</h3>
                <span id="modalSize" class="text-[11px] px-2 py-0.5 rounded font-mono font-semibold flex-shrink-0"
                      style="background:var(--card2);color:var(--text2);border:1px solid var(--border);">0 KB</span>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a id="modalDownloadBtn" href="#" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white flex items-center gap-1.5"
                   style="background:var(--red);">
                    <i class="fa-solid fa-download"></i>
                    <span class="hidden sm:inline">Descargar</span>
                </a>
                <button type="button" onclick="closePreview()"
                        class="h-8 w-8 rounded-lg flex items-center justify-center transition-colors text-slate-400 hover:text-white"
                        style="background:var(--card2);border:1px solid var(--border);">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
        </div>
        <!-- Modal Body Container -->
        <div id="modalBody" class="p-4 overflow-y-auto flex-1 flex flex-col items-center justify-center min-h-[300px]">
            <!-- Contenido dinámico inyectado por openPreview() -->
        </div>
    </div>
</div>


<script>
new QRCode(document.getElementById('qrcode'),{text:'http://<?=$serverIP?>/transfer.php',width:160,height:160,colorDark:'#000',colorLight:'#fff',correctLevel:QRCode.CorrectLevel.M});

const dropZ=document.getElementById('dropZ'),fi=document.getElementById('fi');
dropZ.addEventListener('dragover',e=>{e.preventDefault();dropZ.classList.add('over');});
dropZ.addEventListener('dragleave',()=>dropZ.classList.remove('over'));
dropZ.addEventListener('drop',e=>{
    e.preventDefault();dropZ.classList.remove('over');
    if(e.dataTransfer.files[0]){const dt=new DataTransfer();dt.items.add(e.dataTransfer.files[0]);fi.files=dt.files;handleSel(fi);}
});

function handleSel(inp){
    if(!inp.files.length) return;
    const f=inp.files[0];
    document.getElementById('dc').classList.add('hidden');
    document.getElementById('fp').classList.remove('hidden');
    document.getElementById('fn').textContent=f.name;
    const s=f.size;
    document.getElementById('fs').textContent=s>=1048576?(s/1048576).toFixed(1)+' MB':s>=1024?(s/1024).toFixed(0)+' KB':s+' B';
    document.getElementById('ubtn').classList.remove('hidden');
}

function showUploadMsg(ok, msg) {
    // Crear o reusar el div de mensaje
    let div = document.getElementById('uploadMsg');
    if (!div) {
        div = document.createElement('div');
        div.id = 'uploadMsg';
        div.style.cssText = 'margin-top:12px;padding:12px 16px;border-radius:10px;font-size:13px;font-weight:600;';
        document.getElementById('uploadForm').appendChild(div);
    }
    if (ok) {
        div.style.background = 'rgba(34,197,94,.1)';
        div.style.border     = '1px solid rgba(34,197,94,.3)';
        div.style.color      = '#22c55e';
    } else {
        div.style.background = 'var(--redbg)';
        div.style.border     = '1px solid rgba(220,38,38,.35)';
        div.style.color      = '#ef4444';
    }
    div.textContent = msg;
}

function doUpload(){
    if(!fi.files.length) return;
    const btn = document.getElementById('ubtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Enviando…';

    const fd = new FormData();
    fd.append('archivo', fi.files[0]);

    const xhr = new XMLHttpRequest();
    document.getElementById('pg').classList.remove('hidden');

    xhr.upload.onprogress = e => {
        if (e.lengthComputable) {
            const p = Math.round(e.loaded / e.total * 100);
            document.getElementById('pb').style.width = p + '%';
            document.getElementById('pt').textContent = p + '%';
        }
    };

    xhr.onload = () => {
        try {
            const res = JSON.parse(xhr.responseText);
            if (res.status === 'ok') {
                showUploadMsg(true, '✓ ' + (res.message || 'Archivo subido correctamente.'));
                setTimeout(() => window.location.reload(), 700);
            } else {
                showUploadMsg(false, '✗ ' + (res.message || 'Error al guardar el archivo.'));
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-rocket mr-2"></i>Reintentar';
            }
        } catch (e) {
            showUploadMsg(false, '✗ Error en respuesta del servidor: ' + xhr.responseText.substring(0, 100));
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-rocket mr-2"></i>Reintentar';
        }
    };

    xhr.onerror = () => {
        showUploadMsg(false, '✗ Error de conexión de red con el servidor.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-rocket mr-2"></i>Reintentar';
    };

    xhr.open('POST', 'transfer.php', true);
    xhr.send(fd);
}


// ── Theme Toggle ──────────────────────────────────────────────────────────────
function sweepInlineColors(isLight) {
    const colorPatches = [
        {match:'color:#555',light:'#444'},{match:'color:#666',light:'#555'},
        {match:'color:#888',light:'#444'},{match:'color:#aaa',light:'#666'},
        {match:'color:#e5e5e5',light:'#111'},
    ];
    const bgPatches = [
        {match:'background:#0d0d0d',light:'#f5f5f5'},
        {match:'background:#0a0a0a',light:'#f5f5f5'},
        {match:'background:#111111',light:'#ffffff'},
        {match:'background:#111;',  light:'#ffffff'},
        {match:'background:#1a1a1a',light:'#f0f0f0'},
    ];
    if (isLight) {
        colorPatches.forEach(({match,light})=>{
            document.querySelectorAll(`[style*="${match}"]`).forEach(el=>{
                if(!el.dataset.origStyle) el.dataset.origStyle=el.getAttribute('style')||'';
                el.style.color=light;
            });
        });
        bgPatches.forEach(({match,light})=>{
            document.querySelectorAll(`[style*="${match}"]`).forEach(el=>{
                if(!el.dataset.origStyle) el.dataset.origStyle=el.getAttribute('style')||'';
                el.style.background=light;
            });
        });
    } else {
        document.querySelectorAll('[data-orig-style]').forEach(el=>{
            el.setAttribute('style', el.dataset.origStyle||'');
            delete el.dataset.origStyle;
        });
    }
}

function applyTheme(t){
    const isLight = t==='light';
    document.documentElement.setAttribute('data-theme', t);
    sweepInlineColors(isLight);

    // Fix header hardcoded bg
    const header = document.querySelector('header');
    if(header){
        header.style.background = isLight ? '#ffffff' : '#0a0a0a';
        header.style.borderColor= isLight ? '#e0e0e0' : 'var(--border)';
    }

    // Update toggle button
    const icon = document.getElementById('themeIcon');
    const label= document.getElementById('themeLabel');
    if(icon && label){
        icon.className   = isLight ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
        label.textContent= isLight ? 'Oscuro' : 'Claro';
    }
}
function toggleTheme(){
    const cur  = document.documentElement.getAttribute('data-theme')||'dark';
    const next = cur==='light'?'dark':'light';
    localStorage.setItem('chatosync-theme', next);
    applyTheme(next);
}
// ─── Modal de Previsualización ────────────────────────────────────────────────
const previewModal = document.getElementById('previewModal');
const modalBody    = document.getElementById('modalBody');
const modalTitle   = document.getElementById('modalTitle');
const modalSize    = document.getElementById('modalSize');
const modalIcon    = document.getElementById('modalIcon');
const modalDownload= document.getElementById('modalDownloadBtn');

const extIcons = {
    'pdf':'📄','png':'🖼️','jpg':'🖼️','jpeg':'🖼️','gif':'🖼️','webp':'🖼️','svg':'🖼️',
    'mp4':'🎬','webm':'🎬','mov':'🎬','mp3':'🎵','wav':'🎵','ogg':'🎵',
    'txt':'📋','log':'📋','json':'📋','py':'🐍','sh':'🔧','md':'📝','csv':'📊'
};

function openPreview(encodedFilename, ext, displayName, size) {
    const rawUrl = 'download.php?file=' + encodedFilename;
    const previewUrl = rawUrl + '&preview=1';
    
    modalTitle.textContent = displayName;
    modalSize.textContent  = size;
    modalIcon.textContent  = extIcons[ext] || '📄';
    modalDownload.href     = rawUrl;
    modalBody.innerHTML    = '<div class="text-center py-10"><i class="fa-solid fa-spinner fa-spin text-3xl" style="color:var(--red2);"></i><p class="text-xs mt-2 text-slate-400">Cargando previsualización...</p></div>';
    
    previewModal.classList.remove('hidden');
    previewModal.classList.add('flex');

    if (['jpg','jpeg','png','gif','webp','svg'].includes(ext)) {
        modalBody.innerHTML = `<div class="flex items-center justify-center p-2"><img src="${previewUrl}" alt="${displayName}" class="max-h-[72vh] max-w-full rounded-xl object-contain shadow-2xl border border-neutral-700/50"></div>`;
    } else if (ext === 'pdf') {
        modalBody.innerHTML = `<iframe src="${previewUrl}" class="w-full h-[72vh] rounded-xl border border-neutral-700 bg-white" style="border:none;"></iframe>`;
    } else if (['mp4','webm','mov'].includes(ext)) {
        modalBody.innerHTML = `<div class="flex items-center justify-center w-full"><video controls autoplay class="max-h-[70vh] w-full max-w-3xl rounded-xl shadow-2xl bg-black"><source src="${previewUrl}">Tu navegador no soporta video HTML5.</video></div>`;
    } else if (['mp3','wav','ogg'].includes(ext)) {
        modalBody.innerHTML = `
            <div class="py-12 px-6 flex flex-col items-center justify-center gap-4 text-center">
                <div class="h-20 w-20 rounded-full flex items-center justify-center text-4xl shadow-xl" style="background:var(--redbg);">🎵</div>
                <h4 class="text-sm font-semibold text-white">${displayName}</h4>
                <audio controls autoplay class="w-full max-w-md mt-2"><source src="${previewUrl}">Tu navegador no soporta audio HTML5.</audio>
            </div>`;
    } else if (['txt','log','json','py','sh','md','csv'].includes(ext)) {
        fetch(previewUrl)
            .then(r => r.text())
            .then(text => {
                const escaped = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
                modalBody.innerHTML = `<pre class="w-full p-4 rounded-xl text-xs font-mono overflow-auto max-h-[70vh] whitespace-pre-wrap" style="background:var(--card2);color:var(--text);border:1px solid var(--border);">${escaped}</pre>`;
            })
            .catch(() => {
                modalBody.innerHTML = `<div class="text-center text-xs p-8" style="color:var(--red2);">No se pudo cargar el archivo de texto.</div>`;
            });
    } else {
        modalBody.innerHTML = `
            <div class="py-12 px-6 flex flex-col items-center justify-center gap-4 text-center">
                <div class="text-5xl">📑</div>
                <h4 class="text-sm font-semibold text-white">${displayName}</h4>
                <p class="text-xs text-slate-400 max-w-sm">Este tipo de archivo (${ext.toUpperCase()}) se puede descargar y abrir con la aplicación correspondiente en tu dispositivo.</p>
                <a href="${rawUrl}" class="mt-2 px-4 py-2 rounded-xl text-xs font-bold text-white flex items-center gap-2" style="background:var(--red);"><i class="fa-solid fa-download"></i> Descargar Ahora</a>
            </div>`;
    }
}

function closePreview() {
    previewModal.classList.add('hidden');
    previewModal.classList.remove('flex');
    modalBody.innerHTML = '';
}

// Cerrar con Escape o haciendo clic fuera
window.addEventListener('keydown', e => { if (e.key === 'Escape') closePreview(); });
previewModal.addEventListener('click', e => { if (e.target === previewModal) closePreview(); });

// Cargar preferencia guardada
(function(){ applyTheme(localStorage.getItem('chatosync-theme')||'dark'); })();
</script>
</body>
</html>

