<?php
// ── Manejar subida de archivos ──────────────────────────────────────────────
$uploadDir = "/srv/samba/hub/";
$message = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $file = $_FILES['archivo'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $name = preg_replace('/[^a-zA-Z0-9\._\-]/', '_', basename($file['name']));
        $dest = $uploadDir . $name;
        if (file_exists($dest)) { $info=pathinfo($name); $name=$info['filename'].'_'.time().'.'.(($info['extension'])??'bin'); $dest=$uploadDir.$name; }
        if (move_uploaded_file($file['tmp_name'], $dest)) { chmod($dest,0777); $message="✓ \"$name\" subido exitosamente."; $msgType='ok'; }
        else { $message="✗ Error al guardar. Verifique permisos del servidor."; $msgType='err'; }
    } else { $message="✗ Error en la subida: código ".$file['error']; $msgType='err'; }
}
if (isset($_GET['del'])) {
    $del = basename($_GET['del']); $path = $uploadDir.$del;
    if (file_exists($path) && !is_dir($path)) { unlink($path); header("Location: transfer.php"); exit; }
}

$files = [];
if (is_dir($uploadDir)) {
    foreach (scandir($uploadDir) as $f) {
        if ($f==='.'||$f==='..'||is_dir($uploadDir.$f)) continue;
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
:root{--red:#dc2626;--red2:#ef4444;--redbg:rgba(220,38,38,.12);--card:#111111;--border:#2a2a2a;}
*{box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:#0a0a0a;color:#e5e5e5;min-height:100vh;}
::-webkit-scrollbar{width:5px;} ::-webkit-scrollbar-track{background:#111;} ::-webkit-scrollbar-thumb{background:#333;border-radius:9px;}
@keyframes fadeUp{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}
.fade-up{animation:fadeUp .35s ease both;}
.row:hover{background:rgba(220,38,38,.05);}
#dropZ.over{border-color:var(--red)!important;background:var(--redbg)!important;}
</style>
</head>
<body>

<!-- Header -->
<header class="h-14 flex items-center justify-between px-6 border-b sticky top-0 z-30"
        style="background:#0a0a0a;border-color:var(--border);">
    <div class="flex items-center gap-4">
        <a href="/" class="flex items-center gap-2 text-sm font-medium transition-colors"
           style="color:#555;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#555'">
            <i class="fa-solid fa-arrow-left text-xs"></i> Dashboard
        </a>
        <div class="h-4 w-px" style="background:var(--border);"></div>
        <div class="flex items-center gap-2">
            <div class="h-6 w-6 rounded flex items-center justify-center text-xs font-black text-white" style="background:var(--red);">
                <i class="fa-solid fa-share-nodes" style="font-size:10px;"></i>
            </div>
            <span class="text-sm font-bold text-white">ChatoSync <span style="color:var(--red2);">Transfer</span></span>
        </div>
    </div>
    <div class="flex items-center gap-2 text-xs font-mono px-3 py-1.5 rounded-lg"
         style="background:var(--card);border:1px solid var(--border);">
        <span class="h-1.5 w-1.5 rounded-full" style="background:#22c55e;box-shadow:0 0 6px #22c55e;"></span>
        <span style="color:#22c55e;"><?=$serverIP?></span>
        <span style="color:#555;">· <?=count($files)?> archivos · <?=fmtSz($totalSize)?></span>
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
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="/download.php?file=<?=urlencode($f['name'])?>"
                   class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white flex items-center gap-1.5"
                   style="background:var(--red);">
                    <i class="fa-solid fa-download"></i>
                    <span class="hidden sm:inline">Descargar</span>
                </a>
                <a href="?del=<?=urlencode($f['name'])?>"
                   onclick="return confirm('¿Eliminar <?=htmlspecialchars($f['name'])?>?')"
                   class="p-1.5 rounded-lg text-xs transition-colors"
                   style="background:#1a1a1a;border:1px solid var(--border);color:#555;">
                    <i class="fa-solid fa-trash"></i>
                </a>
            </div>
        </div>
        <?php endforeach; endif;?>
    </div>

</main>

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

function doUpload(){
    if(!fi.files.length) return;
    const btn=document.getElementById('ubtn');
    btn.disabled=true;btn.innerHTML='<i class="fa-solid fa-spinner fa-spin mr-2"></i>Enviando…';
    const fd=new FormData();fd.append('archivo',fi.files[0]);
    const xhr=new XMLHttpRequest();
    document.getElementById('pg').classList.remove('hidden');
    xhr.upload.onprogress=e=>{
        if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);document.getElementById('pb').style.width=p+'%';document.getElementById('pt').textContent=p+'%';}
    };
    xhr.onload=()=>window.location.reload();
    xhr.open('POST','transfer.php',true);
    xhr.send(fd);
}
</script>
</body>
</html>
