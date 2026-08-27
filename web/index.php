<?php
// ─── Datos del Hub (PHP renderizado en carga inicial) ───────────────────────
$hubDir = "/srv/samba/hub/";
$hubFiles = [];
if (is_dir($hubDir)) {
    foreach (scandir($hubDir) as $f) {
        if ($f === '.' || $f === '..' || is_dir($hubDir.$f)) continue;
        $hubFiles[] = ['name'=>$f,'size'=>filesize($hubDir.$f),'date'=>filemtime($hubDir.$f)];
    }
    usort($hubFiles, fn($a,$b)=>$b['date']-$a['date']);
}
$totalFiles = count($hubFiles);
$totalSize = array_sum(array_column($hubFiles,'size'));
function fmtSize($b){if($b>=1073741824)return round($b/1073741824,1).'GB';if($b>=1048576)return round($b/1048576,1).'MB';if($b>=1024)return round($b/1024,1).'KB';return $b.'B';}
function fmtIcon($ext){$m=['pdf'=>'📄','doc'=>'📝','docx'=>'📝','zip'=>'🗜️','rar'=>'🗜️','mp4'=>'🎬','avi'=>'🎬','mp3'=>'🎵','jpg'=>'🖼️','jpeg'=>'🖼️','png'=>'🖼️','apk'=>'📱','ova'=>'💻','exe'=>'⚙️'];return $m[$ext]??'📁';}

// ─── Último horario procesado ────────────────────────────────────────────────
$lastJson = "/srv/samba/hub/ultimo_horario.json";
$lastSchedule = [];
$lastProcessed = null;
if (file_exists($lastJson)){$d=json_decode(file_get_contents($lastJson),true);if(is_array($d)){$lastSchedule=$d;$lastProcessed=filemtime($lastJson);}}
$classCount = count($lastSchedule);

$serverIP = trim(shell_exec("hostname -I | awk '{print $1}'") ?? '192.168.137.102');
$uptime   = trim(shell_exec("uptime -p 2>/dev/null") ?? '—');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ChatoSync Hub · Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
:root {
    --red:   #dc2626;
    --red2:  #ef4444;
    --redbg: rgba(220,38,38,0.12);
    --card:  #111111;
    --card2: #1a1a1a;
    --border:#2a2a2a;
}
*{box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:#0a0a0a;color:#e5e5e5;min-height:100vh;overflow-x:hidden;}
/* Scrollbar */
::-webkit-scrollbar{width:5px;height:5px;} ::-webkit-scrollbar-track{background:#111;} ::-webkit-scrollbar-thumb{background:#333;border-radius:9px;}
/* Pulse red */
@keyframes pulseRed{0%{box-shadow:0 0 0 0 rgba(220,38,38,.6);}70%{box-shadow:0 0 0 8px rgba(220,38,38,0);}100%{box-shadow:0 0 0 0 rgba(220,38,38,0);}}
@keyframes pulseGreen{0%{box-shadow:0 0 0 0 rgba(34,197,94,.5);}70%{box-shadow:0 0 0 7px rgba(34,197,94,0);}100%{box-shadow:0 0 0 0 rgba(34,197,94,0);}}
.pulse-red{animation:pulseRed 2s infinite;}
.pulse-green{animation:pulseGreen 2s infinite;}
/* Metric glow */
.kpi-red{text-shadow:0 0 20px rgba(220,38,38,.5);}
/* Tabs */
.tab-btn{transition:all .2s;}
.tab-btn.active{background:var(--red);color:#fff;border-color:var(--red);}
/* Drop */
#dropZoneOCR.drag-over{border-color:var(--red)!important;background:var(--redbg)!important;}
/* Tooltip */
[data-tip]{position:relative;} [data-tip]:hover::after{content:attr(data-tip);position:absolute;bottom:110%;left:50%;transform:translateX(-50%);background:#222;color:#fff;font-size:10px;padding:3px 8px;border-radius:4px;white-space:nowrap;z-index:50;border:1px solid #333;}
/* Fade in */
@keyframes fadeUp{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}
.fade-up{animation:fadeUp .35s ease both;}
/* Table hover */
.trow:hover{background:rgba(220,38,38,.06);}
/* Progress ring */
.ring-track{fill:none;stroke:#222;stroke-width:4;}
.ring-fill{fill:none;stroke:var(--red);stroke-width:4;stroke-linecap:round;transform:rotate(-90deg);transform-origin:50%;transition:stroke-dashoffset .6s ease;}
</style>
</head>
<body>

<!-- ═══════════════════ SIDEBAR ═══════════════════ -->
<aside id="sidebar" class="fixed top-0 left-0 h-full w-56 border-r z-40 flex flex-col"
       style="background:#0d0d0d;border-color:var(--border);">
    <!-- Logo -->
    <div class="h-16 flex items-center gap-3 px-5 border-b" style="border-color:var(--border);">
        <div class="h-8 w-8 rounded-lg flex items-center justify-center text-white font-black text-sm"
             style="background:var(--red);">CS</div>
        <div>
            <div class="text-sm font-bold text-white">ChatoSync</div>
            <div class="text-[10px]" style="color:#666;">ULSA Hub v2</div>
        </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 py-4 space-y-0.5 px-2 overflow-y-auto">
        <?php
        $nav = [
            ['panel-main',    'fa-gauge-high',      'Dashboard',         true],
            ['panel-ocr',     'fa-wand-magic-sparkles','Horarios OCR',   false],
            ['panel-transfer','fa-share-nodes',      'Transferencia',     false],
            ['panel-mail',    'fa-envelope',         'Correo Local',      false],
            ['panel-services','fa-server',           'Servicios',         false],
            ['panel-logs',    'fa-terminal',         'Logs',              false],
        ];
        foreach($nav as [$id,$icon,$label,$active]):?>
        <button onclick="showPanel('<?=$id?>')"
                class="sidebar-btn w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left text-sm font-medium transition-all"
                style="color:<?=$active?'#fff':'#888'?>;background:<?=$active?'var(--redbg)':''?>;border:1px solid <?=$active?'rgba(220,38,38,.3)':'transparent'?>;"
                id="btn-<?=$id?>">
            <i class="fa-solid <?=$icon?> w-4 text-center" style="color:<?=$active?'var(--red)':'#555'?>;font-size:13px;"></i>
            <?=$label?>
        </button>
        <?php endforeach;?>
    </nav>

    <!-- Server status bottom -->
    <div class="p-4 border-t text-xs space-y-2" style="border-color:var(--border);">
        <div class="flex items-center justify-between">
            <span style="color:#666;">Servidor</span>
            <div class="flex items-center gap-1.5">
                <span class="h-1.5 w-1.5 rounded-full pulse-green" style="background:#22c55e;"></span>
                <span class="text-green-400 font-semibold font-mono text-[11px]"><?=$serverIP?></span>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <span style="color:#666;">Uptime</span>
            <span class="font-mono text-[10px]" style="color:#aaa;"><?=$uptime?></span>
        </div>
    </div>
</aside>

<!-- ═══════════════════ MAIN CONTENT ═══════════════════ -->
<div class="ml-56">

    <!-- Top bar -->
    <header class="h-16 flex items-center justify-between px-6 border-b sticky top-0 z-30"
            style="background:#0a0a0a;border-color:var(--border);">
        <div>
            <h2 class="text-base font-bold text-white" id="pageTitle">Dashboard General</h2>
            <p class="text-xs mt-0.5" style="color:#555;">ChatoSync · ULSA León · <?=date('d/m/Y H:i')?></p>
        </div>
        <div class="flex items-center gap-3">
            <a href="/transfer.php"
               class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-white flex items-center gap-1.5 transition-all"
               style="background:var(--redbg);border:1px solid rgba(220,38,38,.35);color:var(--red2);">
                <i class="fa-solid fa-share-nodes"></i> Transfer Rápido
            </a>
            <a href="/nextcloud" target="_blank"
               class="px-3.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all"
               style="background:#1a1a1a;border:1px solid var(--border);color:#aaa;">
                <i class="fa-solid fa-cloud"></i> Nextcloud
            </a>
        </div>
    </header>

    <!-- ─── PANEL: DASHBOARD ─────────────────────────────── -->
    <section id="panel-main" class="panel p-6 space-y-6">

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Files -->
            <div class="rounded-xl p-5 space-y-3 fade-up" style="background:var(--card);border:1px solid var(--border);">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider" style="color:#666;">Archivos en Hub</span>
                    <i class="fa-solid fa-folder-open" style="color:var(--red);opacity:.8;"></i>
                </div>
                <div class="text-4xl font-black text-white kpi-red"><?=$totalFiles?></div>
                <div class="text-xs" style="color:#555;"><?=fmtSize($totalSize)?> total · Samba + Web</div>
            </div>
            <!-- Classes -->
            <div class="rounded-xl p-5 space-y-3 fade-up" style="background:var(--card);border:1px solid var(--border);animation-delay:.05s">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider" style="color:#666;">Clases Detectadas</span>
                    <i class="fa-solid fa-calendar-check" style="color:var(--red);opacity:.8;"></i>
                </div>
                <div class="text-4xl font-black text-white kpi-red"><?=$classCount?></div>
                <div class="text-xs" style="color:#555;"><?=$lastProcessed?'Último: '.date('d/m H:i',$lastProcessed):'Sin horario procesado'?></div>
            </div>
            <!-- Services -->
            <div class="rounded-xl p-5 space-y-3 fade-up" style="background:var(--card);border:1px solid var(--border);animation-delay:.1s">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider" style="color:#666;">Servicios Activos</span>
                    <i class="fa-solid fa-server" style="color:var(--red);opacity:.8;"></i>
                </div>
                <div class="text-4xl font-black text-white kpi-red" id="kpiSvc">—</div>
                <div class="text-xs" style="color:#555;">de 7 servicios en línea</div>
            </div>
            <!-- Red -->
            <div class="rounded-xl p-5 space-y-3 fade-up" style="background:var(--card);border:1px solid var(--border);animation-delay:.15s">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider" style="color:#666;">Red WLAN</span>
                    <i class="fa-solid fa-wifi" style="color:var(--red);opacity:.8;"></i>
                </div>
                <div class="text-4xl font-black text-white kpi-red">LAN</div>
                <div class="text-xs" style="color:#555;">ULSA-Hub · Sin Internet</div>
            </div>
        </div>

        <!-- Main Grid: Horario + Files recientes -->
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">

            <!-- Horario de clases (7 cols) -->
            <div class="xl:col-span-7 rounded-xl" style="background:var(--card);border:1px solid var(--border);">
                <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color:var(--border);">
                    <div>
                        <h3 class="text-sm font-bold text-white">Horario de Clases Extraído</h3>
                        <p class="text-[11px] mt-0.5" style="color:#555;"><?=$classCount?> clases · Motor OCR Tesseract</p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="testSampleSchedule()"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                                style="background:var(--redbg);border:1px solid rgba(220,38,38,.3);color:var(--red2);">
                            <i class="fa-solid fa-flask-vial mr-1"></i>Probar muestra
                        </button>
                        <a href="download_ics.php"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                           style="background:#1a1a1a;border:1px solid var(--border);color:#aaa;">
                            <i class="fa-solid fa-calendar-plus mr-1"></i>.ICS
                        </a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-[10px] uppercase tracking-wider font-semibold" style="color:#555;border-bottom:1px solid var(--border);">
                                <th class="px-5 py-3 text-left">Código</th>
                                <th class="px-5 py-3 text-left">Asignatura</th>
                                <th class="px-5 py-3 text-left">Día</th>
                                <th class="px-5 py-3 text-left">Horario</th>
                                <th class="px-5 py-3 text-left">Aula</th>
                                <th class="px-5 py-3 text-left">Docente</th>
                            </tr>
                        </thead>
                        <tbody id="scheduleTableBody" class="divide-y" style="border-color:var(--border);">
                            <?php if(empty($lastSchedule)):?>
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center" style="color:#444;">
                                    <i class="fa-solid fa-inbox text-3xl mb-2 block opacity-20"></i>
                                    Sube un horario o pulsa "Probar muestra"
                                </td>
                            </tr>
                            <?php else: foreach($lastSchedule as $c):?>
                            <tr class="trow">
                                <td class="px-5 py-3 font-mono font-bold text-xs" style="color:var(--red2);"><?=htmlspecialchars($c['codigo']??'—')?></td>
                                <td class="px-5 py-3 font-medium text-white text-xs max-w-xs truncate"><?=htmlspecialchars($c['materia']??'—')?></td>
                                <td class="px-5 py-3"><span class="px-2 py-0.5 rounded text-[11px] font-semibold" style="background:#1e1e1e;color:#ccc;"><?=htmlspecialchars($c['dia_completo']??$c['dia']??'—')?></span></td>
                                <td class="px-5 py-3 font-mono text-[11px]" style="color:#aaa;"><?=htmlspecialchars($c['hora_inicio']??'—')?> – <?=htmlspecialchars($c['hora_fin']??'—')?></td>
                                <td class="px-5 py-3"><span class="px-2 py-0.5 rounded text-[11px] font-bold" style="background:var(--redbg);color:var(--red2);border:1px solid rgba(220,38,38,.25);"><?=htmlspecialchars($c['aula']??'—')?></span></td>
                                <td class="px-5 py-3 text-[11px]" style="color:#888;"><?=htmlspecialchars($c['docente']??'—')?></td>
                            </tr>
                            <?php endforeach; endif;?>
                        </tbody>
                    </table>
                </div>
                <!-- Upload inline -->
                <div class="px-5 py-4 border-t" style="border-color:var(--border);">
                    <div id="dropZoneOCR"
                         class="border-2 border-dashed rounded-lg p-4 text-center cursor-pointer transition-all"
                         style="border-color:#2a2a2a;"
                         onclick="document.getElementById('fileInputOCR').click()">
                        <input type="file" id="fileInputOCR" class="hidden" accept="image/*,.pdf">
                        <i class="fa-solid fa-cloud-arrow-up text-xl mb-1" style="color:#444;"></i>
                        <p class="text-xs" style="color:#555;">Arrastra tu horario PNG/JPG/PDF aquí para procesarlo</p>
                    </div>
                    <div id="ocrLoader" class="hidden mt-3 flex items-center gap-2 text-xs" style="color:var(--red2);">
                        <i class="fa-solid fa-spinner fa-spin"></i> Procesando OCR con Tesseract...
                    </div>
                </div>
            </div>

            <!-- Archivos recientes (5 cols) -->
            <div class="xl:col-span-5 rounded-xl" style="background:var(--card);border:1px solid var(--border);">
                <div class="flex items-center justify-between px-5 py-4 border-b" style="border-color:var(--border);">
                    <div>
                        <h3 class="text-sm font-bold text-white">Archivos en el Hub</h3>
                        <p class="text-[11px] mt-0.5" style="color:#555;"><?=$totalFiles?> archivos · <?=fmtSize($totalSize)?></p>
                    </div>
                    <a href="/transfer.php" class="text-xs font-semibold" style="color:var(--red2);">
                        Ver todos <i class="fa-solid fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="divide-y" style="border-color:var(--border);">
                    <?php if(empty($hubFiles)):?>
                    <div class="px-5 py-8 text-center text-xs" style="color:#444;">
                        <i class="fa-solid fa-folder-open text-2xl mb-2 block opacity-20"></i>
                        Hub vacío — Sube desde Transfer
                    </div>
                    <?php else: foreach(array_slice($hubFiles,0,8) as $f):
                        $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));?>
                    <div class="flex items-center gap-3 px-5 py-2.5 hover:bg-white/5 transition-colors group">
                        <span class="text-lg flex-shrink-0"><?=fmtIcon($ext)?></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-white truncate"><?=htmlspecialchars($f['name'])?></p>
                            <p class="text-[10px]" style="color:#555;"><?=fmtSize($f['size'])?> · <?=date('d/m H:i',$f['date'])?></p>
                        </div>
                        <a href="/download.php?file=<?=urlencode($f['name'])?>"
                           class="opacity-0 group-hover:opacity-100 transition-opacity text-xs px-2 py-1 rounded"
                           style="background:var(--redbg);color:var(--red2);">
                            <i class="fa-solid fa-download"></i>
                        </a>
                    </div>
                    <?php endforeach; endif;?>
                </div>
                <!-- QR -->
                <div class="px-5 py-4 border-t flex items-center gap-4" style="border-color:var(--border);">
                    <div class="bg-white p-1.5 rounded-lg flex-shrink-0" id="qrMini"></div>
                    <div>
                        <p class="text-xs font-semibold text-white">Escanea para compartir</p>
                        <p class="text-[11px] mt-0.5" style="color:#555;">Conecta a <strong class="text-white">ULSA-Hub</strong> Wi-Fi y escanea</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Services row -->
        <div class="rounded-xl" style="background:var(--card);border:1px solid var(--border);">
            <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:var(--border);">
                <h3 class="text-sm font-bold text-white">Estado de Servicios en Tiempo Real</h3>
                <button onclick="refreshStatus()" class="text-xs" style="color:#555;" id="svcRefreshBtn">
                    <i class="fa-solid fa-rotate"></i> Actualizar
                </button>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-px" style="background:var(--border);" id="serviceCards">
                <?php for($i=0;$i<7;$i++):?>
                <div class="px-4 py-3 animate-pulse" style="background:var(--card);">
                    <div class="h-2 rounded w-3/4 mb-2" style="background:#1e1e1e;"></div>
                    <div class="h-4 rounded w-1/2" style="background:#1a1a1a;"></div>
                </div>
                <?php endfor;?>
            </div>
        </div>

    </section>

    <!-- ─── PANEL: OCR ─────────────────────────────────────── -->
    <section id="panel-ocr" class="panel p-6 space-y-6 hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Upload -->
            <div class="rounded-xl" style="background:var(--card);border:1px solid var(--border);">
                <div class="px-5 py-4 border-b" style="border-color:var(--border);">
                    <h3 class="text-sm font-bold text-white"><i class="fa-solid fa-wand-magic-sparkles mr-2" style="color:var(--red);"></i>Motor OCR — Digitalización de Horarios</h3>
                    <p class="text-xs mt-1" style="color:#555;">Sube tu horario ULSA (PNG, JPG, PDF). Tesseract extraerá las clases automáticamente.</p>
                </div>
                <div class="p-5 space-y-4">
                    <div id="dropZoneOCR2" class="border-2 border-dashed rounded-xl p-10 text-center cursor-pointer transition-all"
                         style="border-color:#2a2a2a;"
                         onclick="document.getElementById('fileInputOCR2').click()">
                        <input type="file" id="fileInputOCR2" class="hidden" accept="image/*,.pdf">
                        <i class="fa-solid fa-file-image text-4xl mb-3" style="color:#333;"></i>
                        <p class="text-sm font-semibold text-white">Haz clic o arrastra tu horario aquí</p>
                        <p class="text-xs mt-1" style="color:#555;">PNG, JPG, JPEG, PDF · hasta 20 MB</p>
                    </div>
                    <div id="ocrLoader2" class="hidden flex items-center gap-2 text-xs" style="color:var(--red2);">
                        <i class="fa-solid fa-spinner fa-spin"></i> Procesando con Tesseract + 3 estrategias de parsing...
                    </div>
                    <button onclick="testSampleSchedule2()"
                            class="w-full py-3 rounded-xl text-sm font-semibold transition-all"
                            style="background:var(--redbg);border:1px solid rgba(220,38,38,.3);color:var(--red2);">
                        <i class="fa-solid fa-flask-vial mr-2"></i>Probar con Horario de Muestra ULSA
                    </button>
                    <div class="p-3 rounded-xl text-xs space-y-1" style="background:#0d0d0d;border:1px solid var(--border);">
                        <div class="font-semibold text-white flex items-center gap-1.5">
                            <i class="fa-solid fa-folder-open" style="color:#f59e0b;"></i> Carpeta Samba (alternativa):
                        </div>
                        <code class="font-mono" style="color:var(--red2);">\\<?=$serverIP?>\hub\entrada</code>
                    </div>
                </div>
            </div>

            <!-- Resultado OCR -->
            <div class="rounded-xl" style="background:var(--card);border:1px solid var(--border);">
                <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:var(--border);">
                    <h3 class="text-sm font-bold text-white">Resultado OCR</h3>
                    <a href="download_ics.php" class="text-xs font-semibold px-3 py-1.5 rounded-lg"
                       style="background:var(--redbg);border:1px solid rgba(220,38,38,.3);color:var(--red2);">
                        <i class="fa-solid fa-calendar-plus mr-1"></i>Exportar .ICS
                    </a>
                </div>
                <div class="overflow-auto p-2">
                    <table class="w-full text-xs">
                        <thead><tr class="text-[10px] uppercase" style="color:#555;">
                            <th class="p-2 text-left">Código</th><th class="p-2 text-left">Asignatura</th>
                            <th class="p-2 text-left">Día</th><th class="p-2 text-left">Hora</th>
                            <th class="p-2 text-left">Aula</th>
                        </tr></thead>
                        <tbody id="ocrResultTable" class="divide-y" style="border-color:var(--border);">
                            <tr><td colspan="5" class="p-8 text-center text-xs" style="color:#444;">Sin resultado OCR aún</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Log OCR -->
        <div class="rounded-xl" style="background:var(--card);border:1px solid var(--border);">
            <div class="px-5 py-3 border-b flex items-center justify-between" style="border-color:var(--border);">
                <h3 class="text-xs font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-terminal" style="color:var(--red);"></i>Consola de Actividad
                </h3>
                <button onclick="refreshLogs()" class="text-xs" style="color:#555;"><i class="fa-solid fa-rotate"></i></button>
            </div>
            <pre id="logViewer" class="p-4 text-xs font-mono h-52 overflow-y-auto whitespace-pre-wrap" style="color:#888;">Cargando registros...</pre>
        </div>
    </section>

    <!-- ─── PANEL: TRANSFER ────────────────────────────────── -->
    <section id="panel-transfer" class="panel p-6 space-y-6 hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Upload -->
            <div class="lg:col-span-2 rounded-xl" style="background:var(--card);border:1px solid var(--border);">
                <div class="px-5 py-4 border-b" style="border-color:var(--border);">
                    <h3 class="text-sm font-bold text-white"><i class="fa-solid fa-share-nodes mr-2" style="color:var(--red);"></i>Transferir Archivos — Sin Internet</h3>
                    <p class="text-xs mt-1" style="color:#555;">Todos los dispositivos en la red <strong class="text-white">ULSA-Hub</strong> pueden subir y descargar.</p>
                </div>
                <div class="p-5">
                    <form id="transferUploadForm" method="POST" action="transfer.php" enctype="multipart/form-data">
                        <div id="dropZoneTransfer"
                             class="border-2 border-dashed rounded-xl p-10 text-center cursor-pointer transition-all group"
                             style="border-color:#2a2a2a;"
                             onclick="document.getElementById('fileInputTransfer').click()">
                            <input type="file" name="archivo" id="fileInputTransfer" class="hidden" onchange="previewTransfer(this)">
                            <div id="transferDropContent">
                                <i class="fa-solid fa-cloud-arrow-up text-4xl mb-3" style="color:#333;"></i>
                                <p class="text-sm font-semibold text-white">Cualquier tipo de archivo</p>
                                <p class="text-xs mt-1" style="color:#555;">ZIP, APK, ISO, Video, PDF, Instaladores...</p>
                            </div>
                            <div id="transferFilePreview" class="hidden">
                                <i class="fa-solid fa-file-circle-check text-4xl mb-2" style="color:var(--red2);"></i>
                                <p id="transferFileName" class="text-sm font-semibold text-white truncate"></p>
                                <p id="transferFileSize" class="text-xs mt-0.5" style="color:#888;"></p>
                            </div>
                        </div>
                        <div id="transferProgress" class="hidden mt-3 space-y-1">
                            <div class="flex justify-between text-xs" style="color:#555;">
                                <span>Subiendo al servidor...</span>
                                <span id="transferPct">0%</span>
                            </div>
                            <div class="h-1.5 rounded-full" style="background:#1a1a1a;">
                                <div id="transferBar" class="h-full rounded-full" style="width:0%;background:var(--red);transition:width .2s;"></div>
                            </div>
                        </div>
                        <button type="button" id="transferSendBtn" onclick="doTransferUpload()"
                                class="hidden mt-3 w-full py-3 rounded-xl text-sm font-bold text-white transition-all"
                                style="background:var(--red);">
                            <i class="fa-solid fa-rocket mr-2"></i>Enviar al Servidor
                        </button>
                    </form>
                </div>
            </div>

            <!-- QR -->
            <div class="rounded-xl flex flex-col items-center justify-center text-center p-6 space-y-4"
                 style="background:var(--card);border:1px solid var(--border);">
                <div class="text-xs font-bold uppercase tracking-wider" style="color:#555;">
                    <i class="fa-solid fa-qrcode mr-1" style="color:var(--red);"></i>Escanea para acceder
                </div>
                <div class="bg-white p-3 rounded-xl shadow-2xl">
                    <div id="qrTransfer"></div>
                </div>
                <p class="text-xs" style="color:#555;">Abre con la cámara del celular<br>estando en <strong class="text-white">ULSA-Hub</strong> Wi-Fi</p>
                <code class="text-[10px] font-mono break-all" style="color:var(--red2);">http://<?=$serverIP?>/transfer.php</code>
                <div class="w-full p-3 rounded-xl text-xs text-left space-y-1" style="background:#0d0d0d;border:1px solid var(--border);">
                    <div class="font-semibold text-white mb-1">También desde Windows Explorer:</div>
                    <code class="font-mono" style="color:var(--red2);">\\<?=$serverIP?>\hub</code>
                </div>
            </div>
        </div>

        <!-- File List -->
        <div class="rounded-xl" style="background:var(--card);border:1px solid var(--border);">
            <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:var(--border);">
                <h3 class="text-sm font-bold text-white">
                    Archivos Disponibles
                    <span class="ml-2 text-xs px-2 py-0.5 rounded font-semibold" style="background:var(--redbg);color:var(--red2);"><?=$totalFiles?></span>
                </h3>
                <button onclick="location.reload()" class="text-xs" style="color:#555;"><i class="fa-solid fa-rotate"></i> Actualizar</button>
            </div>
            <?php if(empty($hubFiles)):?>
            <div class="p-10 text-center text-xs" style="color:#444;">
                <i class="fa-solid fa-inbox text-3xl mb-2 block opacity-20"></i>
                Hub vacío — sube el primer archivo arriba
            </div>
            <?php else:?>
            <div class="divide-y" style="border-color:var(--border);">
                <?php foreach($hubFiles as $f):
                    $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));?>
                <div class="flex items-center gap-3 px-5 py-3 hover:bg-white/5 transition-colors group">
                    <span class="text-xl"><?=fmtIcon($ext)?></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate"><?=htmlspecialchars($f['name'])?></p>
                        <p class="text-[11px]" style="color:#555;"><?=fmtSize($f['size'])?> · <?=date('d/m/Y H:i',$f['date'])?></p>
                    </div>
                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <a href="/download.php?file=<?=urlencode($f['name'])?>"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
                           style="background:var(--red);">
                            <i class="fa-solid fa-download mr-1"></i>Descargar
                        </a>
                        <a href="transfer.php?del=<?=urlencode($f['name'])?>"
                           onclick="return confirm('¿Eliminar <?=htmlspecialchars($f['name'])?>?')"
                           class="px-2 py-1.5 rounded-lg text-xs transition-colors"
                           style="background:#1a1a1a;border:1px solid var(--border);color:#555;">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach;?>
            </div>
            <?php endif;?>
        </div>
    </section>

    <!-- ─── PANEL: CORREO ──────────────────────────────────── -->
    <section id="panel-mail" class="panel p-6 space-y-6 hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Info + para qué sirve -->
            <div class="rounded-xl p-6 space-y-5" style="background:var(--card);border:1px solid var(--border);">
                <div>
                    <h3 class="text-sm font-bold text-white mb-1">
                        <i class="fa-solid fa-envelope mr-2" style="color:var(--red);"></i>Correo Local Offline
                    </h3>
                    <p class="text-xs" style="color:#666;">Postfix + Dovecot · Dominio <code class="font-mono" style="color:var(--red2);">ulsa.local</code></p>
                </div>

                <div class="space-y-3">
                    <div class="text-xs font-semibold uppercase tracking-wider" style="color:#555;">¿Para qué lo usamos?</div>
                    <?php
                    $uses = [
                        ['fa-bell','Alerta pre-clase','Aviso automático 20 minutos antes de cada clase detectada por OCR'],
                        ['fa-file-import','Notificación de archivos','Email cuando alguien sube un archivo al Hub de transferencia'],
                        ['fa-calendar-check','Confirmación OCR','Correo con el horario extraído cuando el motor lo procesa'],
                        ['fa-triangle-exclamation','Alertas del sistema','Errores críticos de servicios (Samba, Apache, DNS)'],
                    ];
                    foreach($uses as [$ico,$title,$desc]):?>
                    <div class="flex gap-3 p-3 rounded-lg" style="background:#0d0d0d;border:1px solid var(--border);">
                        <i class="fa-solid <?=$ico?> mt-0.5 flex-shrink-0" style="color:var(--red);font-size:13px;"></i>
                        <div>
                            <div class="text-xs font-semibold text-white"><?=$title?></div>
                            <div class="text-[11px] mt-0.5" style="color:#666;"><?=$desc?></div>
                        </div>
                    </div>
                    <?php endforeach;?>
                </div>

                <div class="p-3 rounded-xl space-y-2" style="background:#0d0d0d;border:1px solid var(--border);">
                    <div class="text-xs font-semibold text-white">Configuración IMAP</div>
                    <div class="font-mono text-[11px] space-y-0.5" style="color:#888;">
                        <div>Servidor: <span style="color:var(--red2);"><?=$serverIP?></span></div>
                        <div>IMAP: <span style="color:var(--red2);">143</span> · SMTP: <span style="color:var(--red2);">25</span></div>
                        <div>Usuario: <span style="color:var(--red2);">importar@ulsa.local</span></div>
                        <div>Contraseña: <span style="color:var(--red2);">1234</span></div>
                    </div>
                </div>
            </div>

            <!-- Enviar correo de prueba -->
            <div class="rounded-xl p-6 space-y-4" style="background:var(--card);border:1px solid var(--border);">
                <h3 class="text-sm font-bold text-white">
                    <i class="fa-solid fa-paper-plane mr-2" style="color:var(--red);"></i>Enviar Notificación de Prueba
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-semibold text-white block mb-1">Para:</label>
                        <input type="email" id="mailTo" value="importar@ulsa.local"
                               class="w-full px-3 py-2 rounded-lg text-sm text-white font-mono"
                               style="background:#111;border:1px solid var(--border);outline:none;">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-white block mb-1">Asunto:</label>
                        <input type="text" id="mailSubject" value="[ChatoSync] Prueba de Notificación"
                               class="w-full px-3 py-2 rounded-lg text-sm text-white"
                               style="background:#111;border:1px solid var(--border);outline:none;">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-white block mb-1">Mensaje:</label>
                        <textarea id="mailBody" rows="4"
                                  class="w-full px-3 py-2 rounded-lg text-sm text-white resize-none"
                                  style="background:#111;border:1px solid var(--border);outline:none;">Hola, este es un correo de prueba del sistema ChatoSync. El servidor está funcionando correctamente en la red ULSA-Hub. IP: <?=$serverIP?></textarea>
                    </div>
                    <button onclick="sendTestMail()"
                            class="w-full py-3 rounded-xl text-sm font-bold text-white"
                            style="background:var(--red);">
                        <i class="fa-solid fa-paper-plane mr-2"></i>Enviar Correo Local
                    </button>
                    <div id="mailResult" class="hidden text-xs p-3 rounded-lg"></div>
                </div>

                <!-- Cómo verificar -->
                <div class="p-4 rounded-xl" style="background:#0d0d0d;border:1px solid var(--border);">
                    <div class="text-xs font-semibold text-white mb-2">¿Cómo leo los correos?</div>
                    <div class="text-[11px] space-y-1.5" style="color:#666;">
                        <div><span class="text-white font-medium">Thunderbird / Outlook:</span> Conectar a IMAP <?=$serverIP?></div>
                        <div><span class="text-white font-medium">Terminal en VM:</span></div>
                        <code class="block font-mono text-[10px] p-2 rounded" style="background:#111;color:var(--red2);">su - importar -c "cat ~/Maildir/new/*"</code>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── PANEL: SERVICIOS ───────────────────────────────── -->
    <section id="panel-services" class="panel p-6 space-y-6 hidden">
        <div class="rounded-xl" style="background:var(--card);border:1px solid var(--border);">
            <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:var(--border);">
                <h3 class="text-sm font-bold text-white"><i class="fa-solid fa-server mr-2" style="color:var(--red);"></i>Estado Detallado de Servicios</h3>
                <button onclick="refreshStatus()" class="text-xs px-3 py-1.5 rounded-lg" style="background:var(--redbg);color:var(--red2);">
                    <i class="fa-solid fa-rotate mr-1"></i>Refrescar
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-px" style="background:var(--border);" id="serviceDetail">
                <div class="p-6 animate-pulse" style="background:var(--card);">
                    <div class="h-3 rounded w-1/2 mb-2" style="background:#1e1e1e;"></div>
                    <div class="h-6 rounded w-1/3" style="background:#1a1a1a;"></div>
                </div>
            </div>
        </div>

        <!-- Red info -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <?php
            $netCards = [
                ['192.168.137.1','Windows Laptop','Gateway / Hotspot ULSA-Hub','fa-laptop'],
                ['192.168.137.102','Debian 13 VM','Servidor ChatoSync (tú)','fa-server'],
                ['192.168.137.x','Dispositivos','Laptops y móviles conectados','fa-mobile-screen'],
            ];
            foreach($netCards as [$ip,$name,$desc,$ico]):?>
            <div class="rounded-xl p-5 space-y-3" style="background:var(--card);border:1px solid var(--border);">
                <i class="fa-solid <?=$ico?> text-xl" style="color:var(--red);"></i>
                <div>
                    <div class="text-lg font-black font-mono text-white"><?=$ip?></div>
                    <div class="text-xs font-semibold text-white mt-0.5"><?=$name?></div>
                    <div class="text-[11px] mt-1" style="color:#555;"><?=$desc?></div>
                </div>
            </div>
            <?php endforeach;?>
        </div>
    </section>

    <!-- ─── PANEL: LOGS ────────────────────────────────────── -->
    <section id="panel-logs" class="panel p-6 hidden">
        <div class="rounded-xl h-full" style="background:var(--card);border:1px solid var(--border);">
            <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:var(--border);">
                <h3 class="text-sm font-bold text-white"><i class="fa-solid fa-terminal mr-2" style="color:var(--red);"></i>Consola de Actividad — chatosync.log</h3>
                <button onclick="refreshLogs()" class="text-xs px-3 py-1.5 rounded-lg" style="background:var(--redbg);color:var(--red2);">
                    <i class="fa-solid fa-rotate mr-1"></i>Actualizar
                </button>
            </div>
            <pre id="logViewerFull" class="p-5 text-xs font-mono min-h-96 overflow-y-auto whitespace-pre-wrap" style="color:#888;">Cargando...</pre>
        </div>
    </section>

</div><!-- /ml-56 -->

<script>
// ─── QR Codes ────────────────────────────────────────────────────────────────
const serverIP = '<?=$serverIP?>';
const transferURL = 'http://'+serverIP+'/transfer.php';

if(document.getElementById('qrMini')){
    new QRCode(document.getElementById('qrMini'),{text:transferURL,width:80,height:80,colorDark:'#000',colorLight:'#fff',correctLevel:QRCode.CorrectLevel.M});
}
if(document.getElementById('qrTransfer')){
    new QRCode(document.getElementById('qrTransfer'),{text:transferURL,width:180,height:180,colorDark:'#000',colorLight:'#fff',correctLevel:QRCode.CorrectLevel.M});
}

// ─── Sidebar Navigation ──────────────────────────────────────────────────────
const panels = document.querySelectorAll('.panel');
const btnPrefix = 'btn-';
const titles = {
    'panel-main':'Dashboard General',
    'panel-ocr':'Motor OCR · Horarios',
    'panel-transfer':'Transferencia de Archivos',
    'panel-mail':'Correo Local Offline',
    'panel-services':'Estado de Servicios',
    'panel-logs':'Consola de Logs',
};

function showPanel(id){
    panels.forEach(p=>{p.classList.add('hidden');});
    document.getElementById(id).classList.remove('hidden');
    document.getElementById('pageTitle').innerText = titles[id]||id;
    document.querySelectorAll('.sidebar-btn').forEach(b=>{
        b.style.background='';b.style.borderColor='transparent';b.style.color='#888';
        b.querySelector('i').style.color='#555';
    });
    const btn=document.getElementById(btnPrefix+id);
    if(btn){
        btn.style.background='var(--redbg)';
        btn.style.borderColor='rgba(220,38,38,.3)';
        btn.style.color='#fff';
        btn.querySelector('i').style.color='var(--red)';
    }
    if(id==='panel-logs') refreshLogsAll();
}

// ─── Service Status ───────────────────────────────────────────────────────────
const svcNames = {
    dns:'DNS BIND9',mail_smtp:'SMTP (Postfix)',mail_imap:'IMAP Dovecot',
    samba:'Samba / SMB',web:'Apache Web',cups:'CUPS Impresión',ocr:'ChatoSync OCR'
};
function refreshStatus(){
    fetch('api.php?action=status').then(r=>r.json()).then(d=>{
        if(d.status!=='ok') return;
        const svcs=d.services;
        let active=Object.values(svcs).filter(s=>s.active).length;
        const kpi=document.getElementById('kpiSvc');
        if(kpi) kpi.innerText=active;

        // Cards mini (dashboard)
        const sc=document.getElementById('serviceCards');
        if(sc){
            sc.innerHTML=Object.entries(svcs).map(([k,s])=>`
                <div class="px-4 py-3" style="background:var(--card);">
                    <div class="text-[10px] font-semibold mb-1.5 truncate" style="color:#666;">${s.name}</div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold" style="color:${s.active?'#22c55e':'var(--red2)'};">${s.active?'ACTIVO':'INACTIVO'}</span>
                        <span class="h-2 w-2 rounded-full ${s.active?'pulse-green':'pulse-red'}" style="background:${s.active?'#22c55e':'var(--red)'}"></span>
                    </div>
                </div>`).join('');
        }

        // Detail panel
        const sd=document.getElementById('serviceDetail');
        if(sd){
            sd.innerHTML=Object.entries(svcs).map(([k,s])=>`
                <div class="p-5 space-y-2" style="background:var(--card);">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-white">${s.name}</span>
                        <span class="h-2.5 w-2.5 rounded-full ${s.active?'pulse-green':'pulse-red'}" style="background:${s.active?'#22c55e':'var(--red)'}"></span>
                    </div>
                    <div class="text-xs font-bold" style="color:${s.active?'#22c55e':'var(--red2)'};">${s.active?'✓ Activo y corriendo':'✗ Inactivo / Error'}</div>
                    <div class="text-[10px] font-mono" style="color:#555;">systemctl: ${k}</div>
                </div>`).join('');
        }
    });
}

// ─── Logs ─────────────────────────────────────────────────────────────────────
function refreshLogs(){
    fetch('api.php?action=logs').then(r=>r.json()).then(d=>{
        const v=document.getElementById('logViewer');
        if(v&&d.status==='ok'){v.innerText=d.logs;v.scrollTop=v.scrollHeight;}
    });
}
function refreshLogsAll(){
    fetch('api.php?action=logs').then(r=>r.json()).then(d=>{
        const v=document.getElementById('logViewerFull');
        if(v&&d.status==='ok'){v.innerText=d.logs;v.scrollTop=v.scrollHeight;}
    });
}

// ─── OCR Upload (dashboard dropzone) ─────────────────────────────────────────
function setupOCRDrop(dropId,inputId,loaderId,resultTbody){
    const drop=document.getElementById(dropId);
    const inp=document.getElementById(inputId);
    if(!drop||!inp) return;
    inp.addEventListener('change',e=>{if(e.target.files[0]) ocrUpload(e.target.files[0],loaderId,resultTbody);});
    drop.addEventListener('dragover',e=>{e.preventDefault();drop.classList.add('drag-over');});
    drop.addEventListener('dragleave',()=>drop.classList.remove('drag-over'));
    drop.addEventListener('drop',e=>{
        e.preventDefault();drop.classList.remove('drag-over');
        if(e.dataTransfer.files[0]) ocrUpload(e.dataTransfer.files[0],loaderId,resultTbody);
    });
}

function ocrUpload(file,loaderId,resultTbody){
    document.getElementById(loaderId).classList.remove('hidden');
    const fd=new FormData();fd.append('action','upload');fd.append('horario',file);
    fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
        document.getElementById(loaderId).classList.add('hidden');
        if(res.status==='ok') renderSchedule(res.clases,resultTbody);
        else alert(res.message||'Error al procesar');
        refreshLogs();
    });
}

function renderSchedule(clases,tbodyId='scheduleTableBody'){
    const tbody=document.getElementById(tbodyId);
    if(!tbody) return;
    if(!clases||!clases.length){
        tbody.innerHTML='<tr><td colspan="6" class="p-8 text-center text-xs" style="color:#444;">No se detectaron clases válidas.</td></tr>';
        return;
    }
    tbody.innerHTML=clases.map(c=>`
        <tr class="trow">
            <td class="px-5 py-3 font-mono font-bold text-xs" style="color:var(--red2);">${c.codigo||'—'}</td>
            <td class="px-5 py-3 font-medium text-white text-xs">${c.materia||'—'}</td>
            <td class="px-5 py-3"><span class="px-2 py-0.5 rounded text-[11px] font-semibold" style="background:#1e1e1e;color:#ccc;">${c.dia_completo||c.dia||'—'}</span></td>
            <td class="px-5 py-3 font-mono text-[11px]" style="color:#aaa;">${c.hora_inicio||'—'} – ${c.hora_fin||'—'}</td>
            <td class="px-5 py-3"><span class="px-2 py-0.5 rounded text-[11px] font-bold" style="background:var(--redbg);color:var(--red2);border:1px solid rgba(220,38,38,.25);">${c.aula||'—'}</span></td>
            <td class="px-5 py-3 text-[11px]" style="color:#888;">${c.docente||'—'}</td>
        </tr>`).join('');
    // Sync both tables
    ['scheduleTableBody','ocrResultTable'].forEach(id=>{
        const t=document.getElementById(id);
        if(t&&t.id!==tbodyId) t.innerHTML=tbody.innerHTML;
    });
}

function testSampleSchedule(){testSample('scheduleTableBody','ocrLoader');}
function testSampleSchedule2(){testSample('ocrResultTable','ocrLoader2');}
function testSample(tbodyId,loaderId){
    const l=document.getElementById(loaderId);
    if(l) l.classList.remove('hidden');
    fetch('api.php?action=test_sample').then(r=>r.json()).then(res=>{
        if(l) l.classList.add('hidden');
        if(res.status==='ok') renderSchedule(res.clases,tbodyId);
        else alert(res.message);
        refreshLogs();
    });
}

// ─── Transfer Upload ──────────────────────────────────────────────────────────
function previewTransfer(input){
    if(!input.files.length) return;
    const file=input.files[0];
    document.getElementById('transferDropContent').classList.add('hidden');
    document.getElementById('transferFilePreview').classList.remove('hidden');
    document.getElementById('transferFileName').textContent=file.name;
    const s=file.size;
    document.getElementById('transferFileSize').textContent=s>=1048576?(s/1048576).toFixed(1)+' MB':s>=1024?(s/1024).toFixed(0)+' KB':s+' B';
    document.getElementById('transferSendBtn').classList.remove('hidden');
}
function doTransferUpload(){
    const fi=document.getElementById('fileInputTransfer');
    if(!fi.files.length) return;
    const btn=document.getElementById('transferSendBtn');
    btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner fa-spin mr-2"></i>Enviando...';
    const fd=new FormData(); fd.append('archivo',fi.files[0]);
    const xhr=new XMLHttpRequest();
    document.getElementById('transferProgress').classList.remove('hidden');
    xhr.upload.onprogress=e=>{
        if(e.lengthComputable){
            const p=Math.round(e.loaded/e.total*100);
            document.getElementById('transferBar').style.width=p+'%';
            document.getElementById('transferPct').textContent=p+'%';
        }
    };
    xhr.onload=()=>location.reload();
    xhr.open('POST','transfer.php',true);
    xhr.send(fd);
}

// ─── Send Test Mail ───────────────────────────────────────────────────────────
function sendTestMail(){
    const to=document.getElementById('mailTo').value;
    const sub=document.getElementById('mailSubject').value;
    const body=document.getElementById('mailBody').value;
    const res=document.getElementById('mailResult');
    res.classList.remove('hidden');
    res.style.background='var(--redbg)';res.style.borderColor='rgba(220,38,38,.3)';
    res.style.color='var(--red2)';res.innerText='Enviando correo...';
    fetch(`api.php?action=send_mail&to=${encodeURIComponent(to)}&subject=${encodeURIComponent(sub)}&body=${encodeURIComponent(body)}`)
        .then(r=>r.json()).then(d=>{
            if(d.status==='ok'){
                res.style.background='rgba(34,197,94,.1)';res.style.borderColor='rgba(34,197,94,.3)';
                res.style.color='#22c55e';res.innerText='✓ Correo enviado a '+to;
            } else {
                res.innerText='✗ '+d.message;
            }
        }).catch(()=>{res.innerText='✗ Error de conexión con el servidor.';});
}

// ─── Init ─────────────────────────────────────────────────────────────────────
setupOCRDrop('dropZoneOCR','fileInputOCR','ocrLoader','scheduleTableBody');
setupOCRDrop('dropZoneOCR2','fileInputOCR2','ocrLoader2','ocrResultTable');

// Load saved schedule
fetch('api.php?action=last_data').then(r=>r.json()).then(d=>{
    if(d.status==='ok'&&d.data&&d.data.clases) renderSchedule(d.data.clases);
});

refreshStatus();
refreshLogs();
setInterval(refreshStatus,10000);
setInterval(refreshLogs,5000);
</script>
</body>
</html>
