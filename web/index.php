<?php
// ─── Datos del Hub (PHP renderizado en carga inicial) ───────────────────────
$hubDir = "/srv/samba/hub/";
$SYSTEM_FILES = ['ultimo_horario.json','horario_ulsa.ics','procesar_horario.py','chatosync.service','index.php','api.php','transfer.php','download.php'];
$SYSTEM_EXTS  = ['php','py','sh','json','ics','log','conf','service','bak'];


$hubFiles = [];
if (is_dir($hubDir)) {
    foreach (scandir($hubDir) as $f) {
        if ($f === '.' || $f === '..' || is_dir($hubDir.$f)) continue;
        if (str_starts_with($f, '.')) continue;
        if (in_array($f, $SYSTEM_FILES)) continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($ext, $SYSTEM_EXTS)) continue;
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
<title>ChatoSync · ULSA Local-Hub</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
:root {
    --red:   #dc2626;
    --red2:  #ef4444;
    --redbg: rgba(220,38,38,0.12);
    --card:  #111111;
    --card2: #1a1a1a;
    --border:#2a2a2a;
    --bg:    #0a0a0a;
    --text:  #e5e5e5;
    --text2: #aaaaaa;
    --muted: #555555;
    --sidebar:#0d0d0d;
}
/* ── Light theme ── */
[data-theme="light"] {
    --card:    #ffffff;
    --card2:   #f1f5f9;
    --border:  #e2e8f0;
    --bg:      #f8fafc;
    --text:    #0f172a;
    --text2:   #334155;
    --muted:   #64748b;
    --sidebar: #ffffff;
}
[data-theme="light"] body { background: #f8fafc !important; color: #0f172a !important; }
[data-theme="light"] .text-white,
[data-theme="light"] [class*="text-white"],
[data-theme="light"] strong,
[data-theme="light"] h1,
[data-theme="light"] h2,
[data-theme="light"] h3,
[data-theme="light"] h4,
[data-theme="light"] h5 { color: #0f172a !important; }

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

/* Structural backgrounds */
[data-theme="light"] #sidebar { background: #ffffff !important; border-color: #e2e8f0 !important; }
[data-theme="light"] header  { background: #ffffff !important; border-color: #e2e8f0 !important; }

/* Cards & Dividers */
[data-theme="light"] .divide-y > * { border-color: #e2e8f0 !important; }

/* Tablas */
[data-theme="light"] thead tr { background: #f1f5f9 !important; }
[data-theme="light"] thead th { color: #475569 !important; border-color: #e2e8f0 !important; }

/* Log / consola */
[data-theme="light"] pre { background: #f1f5f9 !important; color: #0f172a !important; border-color: #cbd5e1 !important; }

/* Sidebar nav buttons */
[data-theme="light"] .sidebar-btn { color: #334155 !important; }
[data-theme="light"] .sidebar-btn i { color: #64748b !important; }

/* Inputs y textareas */
[data-theme="light"] input,
[data-theme="light"] textarea,
[data-theme="light"] select { background: #f8fafc !important; border-color: #cbd5e1 !important; color: #0f172a !important; }

/* Drop zones */
[data-theme="light"] #dropZoneOCR,
[data-theme="light"] #dropZoneTransfer { border-color: #cbd5e1 !important; }

/* KPI numbers */
[data-theme="light"] .kpi-red { text-shadow: none !important; color: #dc2626 !important; }

*{box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;overflow-x:hidden;transition:background .25s,color .25s;}

/* Scrollbar */
::-webkit-scrollbar{width:5px;height:5px;} ::-webkit-scrollbar-track{background:var(--card2);} ::-webkit-scrollbar-thumb{background:var(--border);border-radius:9px;}
/* Animations */
@keyframes pulseRed{0%{box-shadow:0 0 0 0 rgba(220,38,38,.6);}70%{box-shadow:0 0 0 8px rgba(220,38,38,0);}100%{box-shadow:0 0 0 0 rgba(220,38,38,0);}}
@keyframes pulseGreen{0%{box-shadow:0 0 0 0 rgba(34,197,94,.5);}70%{box-shadow:0 0 0 7px rgba(34,197,94,0);}100%{box-shadow:0 0 0 0 rgba(34,197,94,0);}}
.pulse-red{animation:pulseRed 2s infinite;}
.pulse-green{animation:pulseGreen 2s infinite;}
.kpi-red{text-shadow:0 0 20px rgba(220,38,38,.5);}
@keyframes fadeUp{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}
.fade-up{animation:fadeUp .35s ease both;}
.trow:hover{background:rgba(220,38,38,.06);}
.themed-card{background:var(--card);border:1px solid var(--border);}
.themed-sub{background:var(--card2);border:1px solid var(--border);}
</style>
</head>
<body>

<!-- ═══════════════════ MOBILE BACKDROP ═══════════════════ -->
<div id="sidebarBackdrop" onclick="toggleMobileSidebar(false)"
     class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>

<!-- ═══════════════════ SIDEBAR ═══════════════════ -->
<aside id="sidebar"
       class="fixed top-0 left-0 h-full w-64 md:w-56 border-r z-50 flex flex-col transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out"
       style="background:#0d0d0d;border-color:var(--border);">
    <!-- Logo -->
    <div class="h-16 flex items-center justify-between px-5 border-b" style="border-color:var(--border);">
        <div class="flex items-center gap-3">
            <div class="h-8 w-8 rounded-lg flex items-center justify-center text-white font-black text-sm"
                 style="background:var(--red);">CS</div>
            <div>
                <div class="text-sm font-bold text-white">ChatoSync</div>
                <div class="text-[10px]" style="color:#666;">ULSA Hub v2</div>
            </div>
        </div>
        <button onclick="toggleMobileSidebar(false)" class="md:hidden p-1.5 rounded-lg text-slate-400 hover:text-white"
                style="background:var(--card2);border:1px solid var(--border);">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>

    <!-- Nav (4 clean pillars) -->
    <nav class="flex-1 py-4 space-y-0.5 px-2 overflow-y-auto">
        <?php
        $nav = [
            ['panel-main',    'fa-gauge-high',          'Dashboard',       true],
            ['panel-ocr',     'fa-wand-magic-sparkles',  'Horarios OCR',    false],
            ['panel-transfer','fa-share-nodes',          'Transferencia',   false],
            ['panel-services','fa-server',               'Servicios y Red', false],
            ['panel-logs',    'fa-terminal',             'Logs',            false],
        ];
        foreach($nav as [$id,$icon,$label,$active]):?>
        <button onclick="showPanel('<?=$id?>'); toggleMobileSidebar(false);"
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
<div class="ml-0 md:ml-56 flex-1 min-h-screen flex flex-col min-w-0 transition-all duration-300">

    <!-- Top bar -->
    <header class="h-16 flex items-center justify-between px-4 md:px-6 border-b sticky top-0 z-30"
            style="background:#0a0a0a;border-color:var(--border);">
        <div class="flex items-center gap-3 min-w-0">
            <button type="button" onclick="toggleMobileSidebar(true)"
                    class="md:hidden p-2 rounded-lg text-slate-300 hover:text-white flex items-center justify-center flex-shrink-0"
                    style="background:var(--card2);border:1px solid var(--border);"
                    title="Menú de Navegación">
                <i class="fa-solid fa-bars text-sm"></i>
            </button>
            <div class="truncate">
                <h2 class="text-sm md:text-base font-bold text-white truncate" id="pageTitle">Dashboard General</h2>
                <p class="text-[10px] md:text-xs truncate" style="color:#555;">ChatoSync · ULSA León · <?=date('d/m/Y H:i')?></p>
            </div>
        </div>
        <div class="flex items-center gap-2 md:gap-3 flex-shrink-0">
            <a href="/transfer.php"
               class="px-2.5 md:px-3.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all"
               style="background:var(--redbg);border:1px solid rgba(220,38,38,.35);color:var(--red2);">
                <i class="fa-solid fa-share-nodes"></i>
                <span class="hidden sm:inline">Transfer</span>
            </a>
            <!-- Theme Toggle -->
            <button id="themeBtn" onclick="toggleTheme()"
                    class="flex items-center gap-1.5 px-2.5 md:px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                    style="background:#1a1a1a;border:1px solid var(--border);color:#aaa;">
                <i id="themeIcon" class="fa-solid fa-moon"></i>
                <span id="themeLabel" class="hidden sm:inline">Claro</span>
            </button>
            <a href="/nextcloud" target="_blank"
               class="px-2.5 md:px-3.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all"
               style="background:#1a1a1a;border:1px solid var(--border);color:#aaa;">
                <i class="fa-solid fa-cloud"></i>
                <span class="hidden sm:inline">Nextcloud</span>
            </a>
        </div>
    </header>

    <!-- ─── PANEL 1: DASHBOARD GENERAL ─────────────────────────────── -->
    <section id="panel-main" class="panel p-4 md:p-6 space-y-6">

        <!-- KPI Cards (4 métricas clave) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
            <!-- Files -->
            <div class="rounded-xl p-4 md:p-5 space-y-2 md:space-y-3 fade-up themed-card">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] md:text-xs font-semibold uppercase tracking-wider" style="color:#666;">Archivos en Hub</span>
                    <i class="fa-solid fa-folder-open text-base" style="color:var(--red);"></i>
                </div>
                <div class="text-3xl md:text-4xl font-black text-white kpi-red" id="kpiFilesCount"><?=$totalFiles?></div>
                <div class="text-[11px]" style="color:#555;" id="kpiFilesSize"><?=fmtSize($totalSize)?> total · Samba + Web</div>
            </div>

            <!-- Classes -->
            <div class="rounded-xl p-4 md:p-5 space-y-2 md:space-y-3 fade-up themed-card" style="animation-delay:.05s">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] md:text-xs font-semibold uppercase tracking-wider" style="color:#666;">Clases en Horario</span>
                    <i class="fa-solid fa-calendar-check text-base" style="color:var(--red);"></i>
                </div>
                <div class="text-3xl md:text-4xl font-black text-white kpi-red" id="kpiClassCount"><?=$classCount?></div>
                <div class="text-[11px]" style="color:#555;" id="kpiLastProc"><?=$lastProcessed?'Último: '.date('d/m H:i',$lastProcessed):'Sin horario'?></div>
            </div>
            <!-- Services -->
            <div class="rounded-xl p-4 md:p-5 space-y-2 md:space-y-3 fade-up themed-card" style="animation-delay:.1s">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] md:text-xs font-semibold uppercase tracking-wider" style="color:#666;">Servicios Activos</span>
                    <span class="h-2 w-2 rounded-full pulse-green" style="background:#22c55e;"></span>
                </div>
                <div class="text-3xl md:text-4xl font-black text-white" style="color:#22c55e;" id="kpiServices">5/5</div>
                <div class="text-[11px]" style="color:#555;">DNS, Samba, Web, CUPS, OCR</div>
            </div>
            <!-- Network -->
            <div class="rounded-xl p-4 md:p-5 space-y-2 md:space-y-3 fade-up themed-card" style="animation-delay:.15s">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] md:text-xs font-semibold uppercase tracking-wider" style="color:#666;">Red WLAN</span>
                    <i class="fa-solid fa-wifi text-base" style="color:var(--red);"></i>
                </div>
                <div class="text-3xl md:text-4xl font-black text-white">LAN</div>
                <div class="text-[11px]" style="color:#555;">ULSA-Hub · Sin Internet</div>
            </div>
        </div>

        <!-- Fila central: Horario Rápido + Archivos Recientes -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Horario Visual (2 cols) -->
            <div class="lg:col-span-2 rounded-xl flex flex-col themed-card">
                <div class="px-5 py-4 border-b flex items-center justify-between flex-wrap gap-2" style="border-color:var(--border);">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-calendar-days text-sm" style="color:var(--red);"></i>
                        <h3 class="text-sm font-bold text-white">Mi Horario Semanal</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="testSampleSchedule()" class="px-2.5 py-1 rounded-lg text-xs font-semibold"
                                style="background:var(--redbg);border:1px solid rgba(220,38,38,.3);color:var(--red2);">
                            <i class="fa-solid fa-flask mr-1"></i>Probar Muestra
                        </button>
                        <button onclick="exportAndDownloadICS()" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-white"
                                style="background:var(--red);">
                            <i class="fa-solid fa-calendar-plus mr-1"></i>Sincronizar .ICS
                        </button>
                    </div>
                </div>

                <!-- Cuadrícula visual semanal -->
                <div class="p-4 overflow-x-auto flex-1">
                    <div class="min-w-[600px] grid grid-cols-6 gap-2 text-center" id="visualTimetable">
                        <!-- Cabeceras de días -->
                        <div class="p-2 rounded-lg text-xs font-bold text-white themed-sub">Lunes</div>
                        <div class="p-2 rounded-lg text-xs font-bold text-white themed-sub">Martes</div>
                        <div class="p-2 rounded-lg text-xs font-bold text-white themed-sub">Miércoles</div>
                        <div class="p-2 rounded-lg text-xs font-bold text-white themed-sub">Jueves</div>
                        <div class="p-2 rounded-lg text-xs font-bold text-white themed-sub">Viernes</div>
                        <div class="p-2 rounded-lg text-xs font-bold text-white themed-sub">Sábado</div>
                        
                        <!-- Columnas de clases inyectadas dinámicamente -->
                        <div id="col-Lu" class="space-y-2 min-h-[140px] p-1.5 rounded-lg border border-dashed" style="border-color:var(--border);"></div>
                        <div id="col-Ma" class="space-y-2 min-h-[140px] p-1.5 rounded-lg border border-dashed" style="border-color:var(--border);"></div>
                        <div id="col-Mi" class="space-y-2 min-h-[140px] p-1.5 rounded-lg border border-dashed" style="border-color:var(--border);"></div>
                        <div id="col-Ju" class="space-y-2 min-h-[140px] p-1.5 rounded-lg border border-dashed" style="border-color:var(--border);"></div>
                        <div id="col-Vi" class="space-y-2 min-h-[140px] p-1.5 rounded-lg border border-dashed" style="border-color:var(--border);"></div>
                        <div id="col-Sa" class="space-y-2 min-h-[140px] p-1.5 rounded-lg border border-dashed" style="border-color:var(--border);"></div>
                    </div>
                </div>

                <!-- Mini drop zone para subir horario rápido -->
                <div class="px-5 py-3 border-t flex items-center justify-between flex-wrap gap-2 text-xs" style="border-color:var(--border);">
                    <span style="color:#666;"><i class="fa-solid fa-cloud-arrow-up mr-1 text-red-500"></i>¿Tienes un horario nuevo?</span>
                    <button onclick="showPanel('panel-ocr')" class="font-semibold text-red-400 hover:underline">
                        Subir imagen o PDF en Horarios OCR ➔
                    </button>
                </div>
            </div>

            <!-- Archivos Recientes + QR (1 col) -->
            <div class="rounded-xl flex flex-col justify-between themed-card">
                <div>
                    <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:var(--border);">
                        <h3 class="text-sm font-bold text-white"><i class="fa-solid fa-cloud-arrow-down mr-2" style="color:var(--red);"></i>Archivos en Hub</h3>
                        <a href="/transfer.php" class="text-xs font-semibold text-red-400">Ver todos ➔</a>
                    </div>
                    <div id="recentFilesContainer" class="divide-y max-h-[300px] overflow-y-auto" style="border-color:var(--border);">
                        <?php if(empty($hubFiles)):?>
                        <div class="px-5 py-8 text-center text-xs" style="color:#444;">
                            <i class="fa-solid fa-folder-open text-2xl mb-2 block opacity-20"></i>
                            Hub vacío — Sube archivos desde Transfer
                        </div>
                        <?php else: foreach(array_slice($hubFiles,0,6) as $f):
                            $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
                            $previewableExts = ['pdf','png','jpg','jpeg','gif','webp','svg','mp4','webm','mp3','wav','ogg','txt','log','json','py','sh','md','csv'];
                            $canPrev = in_array($ext, $previewableExts);
                        ?>
                        <div class="flex items-center gap-3 px-5 py-2.5 hover:bg-white/5 transition-colors group">
                            <span class="text-lg flex-shrink-0"><?=fmtIcon($ext)?></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-white truncate"><?=htmlspecialchars($f['name'])?></p>
                                <p class="text-[10px]" style="color:#555;"><?=fmtSize($f['size'])?> · <?=date('d/m H:i',$f['date'])?></p>
                            </div>
                            <div class="flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                <?php if ($canPrev): ?>
                                <button type="button"
                                        onclick="openPreview('<?=urlencode($f['name'])?>', '<?=$ext?>', '<?=htmlspecialchars(addslashes($f['name']))?>', '<?=fmtSize($f['size'])?>')"
                                        class="text-xs px-2 py-1 rounded flex items-center gap-1"
                                        style="background:var(--card2);color:var(--text);border:1px solid var(--border);"
                                        title="Previsualizar">
                                    <i class="fa-solid fa-eye" style="color:var(--red2);"></i>
                                </button>
                                <?php endif; ?>
                                <a href="/download.php?file=<?=urlencode($f['name'])?>"
                                   class="text-xs px-2 py-1 rounded"
                                   style="background:var(--redbg);color:var(--red2);"
                                   title="Descargar">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; endif;?>
                    </div>
                </div>

                <!-- QR Mini Compartir -->
                <div class="px-5 py-4 border-t flex items-center gap-4" style="border-color:var(--border);">
                    <div class="bg-white p-1.5 rounded-lg flex-shrink-0" id="qrMini"></div>
                    <div>
                        <p class="text-xs font-semibold text-white">Escanea para compartir</p>
                        <p class="text-[11px] mt-0.5" style="color:#555;">Conecta a <strong class="text-white">ULSA-Hub</strong> Wi-Fi y comparte archivos a máxima velocidad</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estado de Servicios (Barra compacta) -->
        <div class="rounded-xl p-4 themed-card">
            <div class="text-xs font-bold uppercase tracking-wider mb-3 text-white flex items-center gap-2">
                <i class="fa-solid fa-shield-halved" style="color:var(--red);"></i> Estado de Infraestructura ChatoSync
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3" id="serviceBadges">
                <div class="p-3 rounded-lg flex items-center justify-between themed-sub animate-pulse">
                    <span class="text-xs" style="color:#777;">Cargando...</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── PANEL 2: HORARIOS OCR INTELIGENTE ─────────────────────── -->
    <section id="panel-ocr" class="panel p-4 md:p-6 space-y-6 hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Subir Horario (1 col) -->
            <div class="rounded-xl p-5 space-y-4 themed-card">
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-wand-magic-sparkles" style="color:var(--red);"></i>
                        Procesar Mi Horario
                    </h3>
                    <p class="text-xs mt-1" style="color:#555;">Sube tu captura de pantalla o foto del horario académico de la ULSA.</p>
                </div>

                <!-- Drop zone -->
                <div id="dropZoneOCR" class="border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-all relative"
                     style="border-color:var(--border);">
                    <input type="file" id="fileInputOCR" accept="image/*,.pdf" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                    <div id="ocrPrompt" class="space-y-2 pointer-events-none">
                        <i class="fa-solid fa-cloud-arrow-up text-3xl" style="color:var(--red2);"></i>
                        <p class="text-xs font-semibold text-white">Toca aquí o arrastra tu imagen</p>
                        <p class="text-[10px]" style="color:#555;">PNG, JPG, JPEG o PDF</p>
                    </div>
                    <div id="ocrLoader" class="hidden space-y-2 pointer-events-none">
                        <i class="fa-solid fa-circle-notch fa-spin text-2xl" style="color:var(--red);"></i>
                        <p class="text-xs font-semibold text-white">Extrayendo clases con Tesseract...</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <button onclick="testSampleSchedule()" class="w-full py-2.5 rounded-xl text-xs font-bold transition-colors"
                            style="background:var(--card2);border:1px solid var(--border);color:var(--text);">
                        <i class="fa-solid fa-flask mr-2" style="color:var(--red2);"></i>Probar con Horario de Muestra
                    </button>
                    <button onclick="addClassRow()" class="w-full py-2.5 rounded-xl text-xs font-bold transition-colors"
                            style="background:var(--card2);border:1px solid var(--border);color:var(--text);">
                        <i class="fa-solid fa-plus mr-2 text-green-500"></i>Agregar Materia Manualmente
                    </button>
                </div>

                <div class="p-3.5 rounded-xl text-[11px] space-y-1.5 themed-sub">
                    <div class="font-bold text-white flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info text-amber-500"></i> Sincronización Automática
                    </div>
                    <p style="color:#666;">Al exportar se genera un archivo <strong class="text-white">.ics</strong> con repetición semanal hasta el final del cuatrimestre y alarmas 15 min antes de cada clase.</p>
                </div>
            </div>

            <!-- Tabla de Clases Detectadas + Editor (2 cols) -->
            <div class="lg:col-span-2 rounded-xl flex flex-col themed-card">
                <div class="px-5 py-4 border-b flex items-center justify-between flex-wrap gap-2" style="border-color:var(--border);">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-white">Clases Extraídas</h3>
                        <span id="detectedCountBadge" class="text-xs px-2 py-0.5 rounded font-semibold"
                              style="background:var(--redbg);color:var(--red2);">0 clases</span>
                    </div>
                    <button onclick="exportAndDownloadICS()" class="px-3.5 py-1.5 rounded-lg text-xs font-bold text-white flex items-center gap-1.5 shadow-lg"
                            style="background:var(--red);">
                        <i class="fa-solid fa-calendar-plus"></i>
                        <span>📅 Sincronizar Calendario (.ICS)</span>
                    </button>
                </div>

                <!-- Tabla editable -->
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-xs text-left">
                        <thead class="uppercase font-semibold border-b" style="border-color:var(--border);background:#0d0d0d;color:#555;">
                            <tr>
                                <th class="px-4 py-3">Cód</th>
                                <th class="px-4 py-3">Asignatura</th>
                                <th class="px-4 py-3">Día</th>
                                <th class="px-4 py-3">Horario</th>
                                <th class="px-4 py-3">Aula</th>
                                <th class="px-4 py-3">Docente</th>
                                <th class="px-3 py-3 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="ocrTableBody" class="divide-y" style="border-color:var(--border);">
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-xs" style="color:#555;">
                                    <i class="fa-solid fa-wand-magic-sparkles text-2xl mb-2 block opacity-20"></i>
                                    Sube tu horario a la izquierda para extraer automáticamente las materias
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── PANEL 3: TRANSFERENCIA DE ARCHIVOS (XENDER) ────────────── -->
    <section id="panel-transfer" class="panel p-4 md:p-6 space-y-6 hidden">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- Subir (2 cols) -->
            <div class="md:col-span-2 rounded-xl p-5 space-y-4 themed-card">
                <div>
                    <h3 class="text-sm font-bold text-white"><i class="fa-solid fa-cloud-arrow-up mr-2" style="color:var(--red);"></i>Subir al Hub Local</h3>
                    <p class="text-xs mt-0.5" style="color:#555;">Cualquier archivo — todos en la red ULSA-Hub pueden descargarlo a 300+ Mbps sin internet.</p>
                </div>

                <div id="dropZoneTransfer" class="border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-all relative"
                     style="border-color:var(--border);" onclick="document.getElementById('fileInputTransfer').click()">
                    <input type="file" id="fileInputTransfer" class="hidden" onchange="handleTransferFile(this)">
                    <div id="transferDropContent" class="space-y-2 pointer-events-none">
                        <i class="fa-solid fa-cloud-arrow-up text-4xl" style="color:var(--red2);"></i>
                        <p class="text-sm font-semibold text-white">Toca aquí o arrastra tu archivo</p>
                        <p class="text-xs" style="color:#555;">APK, ZIP, ISO, PDF, Video, Instaladores, etc.</p>
                    </div>
                    <div id="transferFilePreview" class="hidden space-y-1 pointer-events-none">
                        <i class="fa-solid fa-file-circle-check text-3xl" style="color:var(--red2);"></i>
                        <p id="transferFileName" class="text-sm font-semibold text-white"></p>
                        <p id="transferFileSize" class="text-xs" style="color:#888;"></p>
                    </div>
                </div>

                <div id="transferProgress" class="hidden space-y-1.5">
                    <div class="flex justify-between text-xs font-mono" style="color:#666;">
                        <span>Enviando al servidor...</span><span id="transferPct">0%</span>
                    </div>
                    <div class="h-1.5 rounded-full" style="background:var(--card2);">
                        <div id="transferBar" class="h-full rounded-full" style="width:0%;background:var(--red);transition:width .2s;"></div>
                    </div>
                </div>

                <button id="transferSendBtn" onclick="doTransferUpload()" class="hidden w-full py-3 rounded-xl text-xs font-bold text-white shadow-lg"
                        style="background:var(--red);">
                    <i class="fa-solid fa-rocket mr-2"></i>Enviar al Servidor
                </button>

                <div class="p-3 rounded-xl text-xs flex items-center justify-between themed-sub">
                    <span style="color:#666;"><i class="fa-solid fa-folder-open mr-1.5 text-amber-500"></i>Acceso directo Windows Samba:</span>
                    <code class="font-mono text-red-400">\\<?=$serverIP?>\hub</code>
                </div>
            </div>

            <!-- QR Grande (1 col) -->
            <div class="rounded-xl p-5 flex flex-col items-center justify-center text-center space-y-4 themed-card">
                <div class="text-xs font-semibold uppercase tracking-wider" style="color:#666;">
                    <i class="fa-solid fa-qrcode mr-1 text-red-500"></i>Escanea con tu celular
                </div>
                <div class="bg-white p-3 rounded-2xl shadow-2xl">
                    <div id="qrTransfer"></div>
                </div>
                <p class="text-xs" style="color:#666;">
                    Conéctate al Wi-Fi <strong class="text-white">ULSA-Hub</strong> y escanea para transferir archivos
                </p>
                <code class="text-[11px] font-mono text-red-400">http://<?=$serverIP?>/transfer.php</code>
            </div>
        </div>

        <!-- Lista completa de archivos -->
        <div class="rounded-xl themed-card">
            <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:var(--border);">
                <h3 class="text-sm font-bold text-white">Todos los Archivos Disponibles (<?=count($hubFiles)?>)</h3>
                <button onclick="location.reload()" class="text-xs text-slate-400 hover:text-white">
                    <i class="fa-solid fa-rotate mr-1"></i>Actualizar
                </button>
            </div>
            <div id="fullFilesContainer" class="divide-y" style="border-color:var(--border);">
                <?php if(empty($hubFiles)):?>
                <div class="p-10 text-center text-xs" style="color:#444;">
                    <i class="fa-solid fa-inbox text-3xl mb-2 block opacity-20"></i>
                    No hay archivos en el Hub — sube el primero arriba
                </div>
                <?php else: foreach($hubFiles as $f):
                    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                    $canPrev2 = in_array($ext, ['pdf','png','jpg','jpeg','gif','webp','svg','mp4','webm','mp3','wav','ogg','txt','log','json','py','sh','md','csv']);
                ?>
                <div class="flex items-center gap-3 px-5 py-3 hover:bg-white/5 transition-colors group">
                    <span class="text-xl"><?=fmtIcon($ext)?></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate"><?=htmlspecialchars($f['name'])?></p>
                        <p class="text-[11px]" style="color:#555;"><?=fmtSize($f['size'])?> · <?=date('d/m/Y H:i',$f['date'])?></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($canPrev2): ?>
                        <button type="button"
                                onclick="openPreview('<?=urlencode($f['name'])?>', '<?=$ext?>', '<?=htmlspecialchars(addslashes($f['name']))?>', '<?=fmtSize($f['size'])?>')"
                                class="px-2.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1"
                                style="background:var(--card2);border:1px solid var(--border);color:var(--text);"
                                title="Previsualizar">
                            <i class="fa-solid fa-eye" style="color:var(--red2);"></i>
                            <span class="hidden sm:inline">Ver</span>
                        </button>
                        <?php endif; ?>
                        <a href="/download.php?file=<?=urlencode($f['name'])?>"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white flex items-center gap-1 shadow-sm"
                           style="background:var(--red);">
                            <i class="fa-solid fa-download"></i>
                            <span class="hidden sm:inline">Descargar</span>
                        </a>
                        <a href="transfer.php?del=<?=urlencode($f['name'])?>"
                           onclick="return confirm('¿Eliminar <?=htmlspecialchars($f['name'])?>?')"
                           class="px-2 py-1.5 rounded-lg text-xs transition-colors"
                           style="background:#1a1a1a;border:1px solid var(--border);color:#555;">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; endif;?>
            </div>
        </div>
    </section>

    <!-- ─── PANEL 4: SERVICIOS Y RED ─────────────────────────────── -->
    <section id="panel-services" class="panel p-4 md:p-6 space-y-6 hidden">
        <div class="rounded-xl themed-card">
            <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:var(--border);">
                <h3 class="text-sm font-bold text-white"><i class="fa-solid fa-server mr-2" style="color:var(--red);"></i>Estado Detallado de Servicios</h3>
                <button onclick="refreshStatus()" class="text-xs px-3 py-1.5 rounded-lg" style="background:var(--redbg);color:var(--red2);">
                    <i class="fa-solid fa-rotate mr-1"></i>Refrescar
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-px" style="background:var(--border);" id="serviceDetail">
                <div class="p-6 animate-pulse" style="background:var(--card);">
                    <div class="h-3 rounded w-1/2 mb-2" style="background:#1e1e1e;"></div>
                    <div class="h-6 rounded w-1/3" style="background:#1a1a1a;"></div>
                </div>
            </div>
        </div>

        <!-- Topología de red -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="rounded-xl p-5 space-y-2 themed-card">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-mono text-red-400 font-bold">192.168.137.1</span>
                    <i class="fa-solid fa-laptop text-slate-400"></i>
                </div>
                <div class="text-sm font-bold text-white">Laptop Anfitriona</div>
                <div class="text-xs" style="color:#555;">Gateway / Hotspot Wi-Fi (ULSA-Hub)</div>
            </div>
            <div class="rounded-xl p-5 space-y-2 themed-card">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-mono text-green-400 font-bold">192.168.137.102</span>
                    <i class="fa-solid fa-server text-green-400"></i>
                </div>
                <div class="text-sm font-bold text-white">Debian 13 VM (ChatoSync)</div>
                <div class="text-xs" style="color:#555;">DNS, Samba, Web, CUPS, OCR Hub</div>
            </div>
            <div class="rounded-xl p-5 space-y-2 themed-card">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-mono text-amber-400 font-bold">192.168.137.x</span>
                    <i class="fa-solid fa-mobile-screen text-amber-400"></i>
                </div>
                <div class="text-sm font-bold text-white">Clientes Conectados</div>
                <div class="text-xs" style="color:#555;">Smartphones y Laptops en el aula</div>
            </div>
        </div>
    </section>

    <!-- ─── PANEL 5: LOGS DEL SISTEMA ─────────────────────────────── -->
    <section id="panel-logs" class="panel p-4 md:p-6 space-y-4 hidden">
        <div class="rounded-xl themed-card">
            <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:var(--border);">
                <h3 class="text-sm font-bold text-white"><i class="fa-solid fa-terminal mr-2" style="color:var(--red);"></i>Consola en Vivo — /var/log/chatosync.log</h3>
                <button onclick="refreshLogs()" class="text-xs px-3 py-1.5 rounded-lg" style="background:var(--redbg);color:var(--red2);">
                    <i class="fa-solid fa-rotate mr-1"></i>Actualizar
                </button>
            </div>
            <pre id="logViewerFull" class="p-5 text-xs font-mono min-h-[400px] overflow-y-auto whitespace-pre-wrap" style="color:#888;">Cargando logs...</pre>
        </div>
    </section>

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
            <div id="modalBody" class="p-4 overflow-y-auto flex-1 flex flex-col items-center justify-center min-h-[300px]"></div>
        </div>
    </div>

</div><!-- /main content -->

<script>
// ─── Variables Globales ───────────────────────────────────────────────────────
const serverIP = '<?=$serverIP?>';
const transferURL = 'http://' + serverIP + '/transfer.php';
let currentSchedule = <?=json_encode($lastSchedule, JSON_UNESCAPED_UNICODE)?> || [];

// ─── QR Codes ────────────────────────────────────────────────────────────────
if(document.getElementById('qrMini')){
    new QRCode(document.getElementById('qrMini'),{text:transferURL,width:80,height:80,colorDark:'#000',colorLight:'#fff',correctLevel:QRCode.CorrectLevel.M});
}
if(document.getElementById('qrTransfer')){
    new QRCode(document.getElementById('qrTransfer'),{text:transferURL,width:160,height:160,colorDark:'#000',colorLight:'#fff',correctLevel:QRCode.CorrectLevel.M});
}

// ─── Navegación de Paneles ───────────────────────────────────────────────────
function showPanel(panelId) {
    document.querySelectorAll('.panel').forEach(p => p.classList.add('hidden'));
    const target = document.getElementById(panelId);
    if(target) target.classList.remove('hidden');

    document.querySelectorAll('.sidebar-btn').forEach(btn => {
        btn.style.color = '#888';
        btn.style.background = '';
        btn.style.borderColor = 'transparent';
        const icon = btn.querySelector('i');
        if(icon) icon.style.color = '#555';
    });

    const activeBtn = document.getElementById('btn-' + panelId);
    if(activeBtn) {
        activeBtn.style.color = '#fff';
        activeBtn.style.background = 'var(--redbg)';
        activeBtn.style.borderColor = 'rgba(220,38,38,0.3)';
        const icon = activeBtn.querySelector('i');
        if(icon) icon.style.color = 'var(--red)';
    }

    const titles = {
        'panel-main': 'Dashboard General',
        'panel-ocr': 'Horarios OCR & Calendario Inteligente',
        'panel-transfer': 'Hub de Transferencia de Archivos',
        'panel-services': 'Infraestructura y Servicios',
        'panel-logs': 'Consola de Actividad del Sistema'
    };
    const titleEl = document.getElementById('pageTitle');
    if(titleEl) titleEl.innerText = titles[panelId] || 'ChatoSync';
}

// ─── Renderizado de Horario (Cuadrícula Visual + Tabla Editable) ──────────────
function renderVisualSchedule(clases) {
    // Limpiar columnas
    ['Lu','Ma','Mi','Ju','Vi','Sa'].forEach(d => {
        const col = document.getElementById('col-' + d);
        if(col) col.innerHTML = '';
    });

    const colores = {
        '0808': { bg: 'rgba(239, 68, 68, 0.15)', border: 'rgba(239, 68, 68, 0.4)', text: '#ef4444' },
        '0305': { bg: 'rgba(59, 130, 246, 0.15)', border: 'rgba(59, 130, 246, 0.4)', text: '#3b82f6' },
        '0303': { bg: 'rgba(16, 185, 129, 0.15)', border: 'rgba(16, 185, 129, 0.4)', text: '#10b981' },
        '0603': { bg: 'rgba(245, 158, 11, 0.15)', border: 'rgba(245, 158, 11, 0.4)', text: '#f59e0b' },
    };

    if(!clases || !clases.length) return;

    clases.forEach((c, idx) => {
        const dia = c.dia || 'Lu';
        const col = document.getElementById('col-' + dia);
        if(!col) return;

        const colInfo = colores[c.codigo] || { bg: 'rgba(168, 85, 247, 0.15)', border: 'rgba(168, 85, 247, 0.4)', text: '#a855f7' };
        const card = document.createElement('div');
        card.className = 'p-2.5 rounded-lg text-left shadow-sm transition-all hover:scale-[1.02] cursor-pointer';
        card.style.background = colInfo.bg;
        card.style.border = '1px solid ' + colInfo.border;
        card.innerHTML = `
            <div class="flex items-center justify-between text-[10px] font-mono font-bold mb-1">
                <span style="color:${colInfo.text};">[${c.codigo}]</span>
                <span class="px-1.5 py-0.5 rounded text-[9px] bg-black/40 text-white font-bold">${c.aula}</span>
            </div>
            <div class="text-xs font-bold text-white leading-tight mb-1 truncate" title="${c.materia}">${c.materia}</div>
            <div class="text-[10px] font-mono" style="color:#aaa;">${c.hora_inicio} - ${c.hora_fin}</div>
            <div class="text-[9px] truncate mt-0.5" style="color:#777;">${c.docente || ''}</div>
        `;
        col.appendChild(card);
    });

    // Actualizar KPI
    const kpiCount = document.getElementById('kpiClassCount');
    if(kpiCount) kpiCount.textContent = clases.length;
}

function renderTableSchedule(clases) {
    const tbody = document.getElementById('ocrTableBody');
    if(!tbody) return;

    if(!clases || !clases.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-8 text-center text-xs" style="color:#555;">No hay clases detectadas</td></tr>`;
        return;
    }

    tbody.innerHTML = '';
    clases.forEach((c, i) => {
        const tr = document.createElement('tr');
        tr.className = 'trow transition-colors';
        tr.innerHTML = `
            <td class="px-4 py-2.5 font-mono font-bold text-red-400">
                <input type="text" value="${c.codigo}" onchange="updateClassField(${i},'codigo',this.value)" class="w-14 bg-transparent border-b border-transparent focus:border-red-500 font-mono text-xs text-red-400">
            </td>
            <td class="px-4 py-2.5 font-semibold text-white">
                <input type="text" value="${c.materia}" onchange="updateClassField(${i},'materia',this.value)" class="w-full bg-transparent border-b border-transparent focus:border-red-500 text-xs font-semibold text-white">
            </td>
            <td class="px-4 py-2.5">
                <select onchange="updateClassField(${i},'dia',this.value)" class="bg-transparent border border-neutral-700 rounded px-1.5 py-0.5 text-xs text-white">
                    <option value="Lu" ${c.dia==='Lu'?'selected':''}>Lunes</option>
                    <option value="Ma" ${c.dia==='Ma'?'selected':''}>Martes</option>
                    <option value="Mi" ${c.dia==='Mi'?'selected':''}>Miércoles</option>
                    <option value="Ju" ${c.dia==='Ju'?'selected':''}>Jueves</option>
                    <option value="Vi" ${c.dia==='Vi'?'selected':''}>Viernes</option>
                    <option value="Sa" ${c.dia==='Sa'?'selected':''}>Sábado</option>
                </select>
            </td>
            <td class="px-4 py-2.5 font-mono text-slate-300">
                <input type="text" value="${c.hora_inicio} - ${c.hora_fin}" onchange="updateClassHours(${i},this.value)" class="w-36 bg-transparent border-b border-transparent focus:border-red-500 font-mono text-xs text-slate-300">
            </td>
            <td class="px-4 py-2.5">
                <input type="text" value="${c.aula}" onchange="updateClassField(${i},'aula',this.value)" class="w-20 bg-transparent border-b border-transparent focus:border-red-500 font-bold text-xs text-white">
            </td>
            <td class="px-4 py-2.5 text-slate-400 truncate max-w-[140px]">
                <input type="text" value="${c.docente}" onchange="updateClassField(${i},'docente',this.value)" class="w-full bg-transparent border-b border-transparent focus:border-red-500 text-xs text-slate-400">
            </td>
            <td class="px-3 py-2.5 text-center">
                <button onclick="deleteClassRow(${i})" class="text-slate-500 hover:text-red-400 transition-colors p-1" title="Eliminar fila">
                    <i class="fa-solid fa-trash text-xs"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    const badge = document.getElementById('detectedCountBadge');
    if(badge) badge.textContent = clases.length + ' clases';
}

function updateClassField(index, field, value) {
    if(currentSchedule[index]) {
        currentSchedule[index][field] = value;
        renderVisualSchedule(currentSchedule);
    }
}

function updateClassHours(index, value) {
    if(currentSchedule[index]) {
        const parts = value.split('-');
        if(parts.length === 2) {
            currentSchedule[index].hora_inicio = parts[0].trim();
            currentSchedule[index].hora_fin = parts[1].trim();
            renderVisualSchedule(currentSchedule);
        }
    }
}

function addClassRow() {
    currentSchedule.push({
        codigo: '0000',
        materia: 'Nueva Materia',
        dia: 'Lu',
        dia_completo: 'Lunes',
        hora_inicio: '01:00 pm',
        hora_fin: '02:40 pm',
        aula: 'G105',
        docente: 'Docente Asignado'
    });
    renderVisualSchedule(currentSchedule);
    renderTableSchedule(currentSchedule);
}

function deleteClassRow(index) {
    currentSchedule.splice(index, 1);
    renderVisualSchedule(currentSchedule);
    renderTableSchedule(currentSchedule);
}

function exportAndDownloadICS() {
    if(!currentSchedule || !currentSchedule.length) {
        alert('No hay materias en el horario para exportar.');
        return;
    }

    fetch('api.php?action=export_ics', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(currentSchedule)
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'ok') {
            // Iniciar descarga automática del .ics
            const a = document.createElement('a');
            a.href = data.ics_url || '/download.php?file=horario_ulsa.ics';
            a.download = 'horario_ulsa.ics';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            alert('✓ Calendario generado con éxito con recurrencia para todo el cuatrimestre y alarmas automáticas.');
        } else {
            alert('Error al generar calendario: ' + (data.message || 'Error'));
        }
    })
    .catch(() => {
        window.location.href = '/download.php?file=horario_ulsa.ics';
    });
}

function testSampleSchedule() {
    const loader = document.getElementById('ocrLoader');
    const prompt = document.getElementById('ocrPrompt');
    if(loader) loader.classList.remove('hidden');
    if(prompt) prompt.classList.add('hidden');

    fetch('api.php?action=test_sample')
        .then(r => r.json())
        .then(d => {
            if(loader) loader.classList.add('hidden');
            if(prompt) prompt.classList.remove('hidden');
            if(d.status === 'ok' && d.clases && d.clases.length) {
                currentSchedule = d.clases;
                renderVisualSchedule(currentSchedule);
                renderTableSchedule(currentSchedule);
                alert('✓ Horario de muestra procesado: se detectaron ' + d.clases.length + ' clases.');
            } else {
                alert('No se pudieron extraer clases de la muestra.');
            }
        })
        .catch(err => {
            if(loader) loader.classList.add('hidden');
            if(prompt) prompt.classList.remove('hidden');
            alert('Error de conexión con el motor OCR.');
        });
}

// ─── Subida de Horarios (Drag & Drop) ─────────────────────────────────────────
function setupOCRUpload() {
    const drop = document.getElementById('dropZoneOCR');
    const fileInp = document.getElementById('fileInputOCR');
    if(!drop || !fileInp) return;

    drop.addEventListener('dragover', e => { e.preventDefault(); drop.style.borderColor = 'var(--red)'; });
    drop.addEventListener('dragleave', () => { drop.style.borderColor = 'var(--border)'; });
    drop.addEventListener('drop', e => {
        e.preventDefault();
        drop.style.borderColor = 'var(--border)';
        if(e.dataTransfer.files[0]) {
            fileInp.files = e.dataTransfer.files;
            processOCRFile(e.dataTransfer.files[0]);
        }
    });
    fileInp.addEventListener('change', () => {
        if(fileInp.files[0]) processOCRFile(fileInp.files[0]);
    });
}

function processOCRFile(file) {
    const loader = document.getElementById('ocrLoader');
    const prompt = document.getElementById('ocrPrompt');
    if(loader) loader.classList.remove('hidden');
    if(prompt) prompt.classList.add('hidden');

    const fd = new FormData();
    fd.append('horario', file);

    fetch('api.php?action=upload', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if(loader) loader.classList.add('hidden');
            if(prompt) prompt.classList.remove('hidden');
            if(d.status === 'ok' && d.clases && d.clases.length) {
                currentSchedule = d.clases;
                renderVisualSchedule(currentSchedule);
                renderTableSchedule(currentSchedule);
                alert('✓ Horario procesado con éxito: ' + d.clases.length + ' sesiones detectadas.');
            } else {
                alert('[-] No se detectaron patrones válidos en la imagen.');
            }
        })
        .catch(() => {
            if(loader) loader.classList.add('hidden');
            if(prompt) prompt.classList.remove('hidden');
            alert('Error al comunicarse con el servidor OCR.');
        });
}

// ─── Transfer Drag & Drop ────────────────────────────────────────────────────
function handleTransferFile(inp) {
    if(!inp.files.length) return;
    const f = inp.files[0];
    document.getElementById('transferDropContent').classList.add('hidden');
    document.getElementById('transferFilePreview').classList.remove('hidden');
    document.getElementById('transferFileName').textContent = f.name;
    const s = f.size;
    document.getElementById('transferFileSize').textContent = s>=1048576?(s/1048576).toFixed(1)+' MB':s>=1024?(s/1024).toFixed(0)+' KB':s+' B';
    document.getElementById('transferSendBtn').classList.remove('hidden');
}

function doTransferUpload() {
    const fi = document.getElementById('fileInputTransfer');
    if(!fi.files.length) return;
    const btn = document.getElementById('transferSendBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Enviando...';
    const fd = new FormData(); fd.append('archivo', fi.files[0]);
    const xhr = new XMLHttpRequest();
    document.getElementById('transferProgress').classList.remove('hidden');
    xhr.upload.onprogress = e => {
        if(e.lengthComputable) {
            const p = Math.round(e.loaded/e.total*100);
            document.getElementById('transferBar').style.width = p + '%';
            document.getElementById('transferPct').textContent = p + '%';
        }
    };
    xhr.onload = () => {
        try {
            const res = JSON.parse(xhr.responseText);
            if(res.status === 'ok') location.reload();
            else {
                alert(res.message || 'Error al subir archivo');
                btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-rocket mr-2"></i>Enviar al Servidor';
            }
        } catch(e) { location.reload(); }
    };
    xhr.open('POST', 'transfer.php', true);
    xhr.send(fd);
}

// ─── Monitoreo de Servicios & Logs ───────────────────────────────────────────
function refreshStatus() {
    fetch('api.php?action=status')
        .then(r => r.json())
        .then(d => {
            if(d.status === 'ok') {
                const s = d.services;
                const badges = document.getElementById('serviceBadges');
                const detail = document.getElementById('serviceDetail');
                
                let activeCount = 0;
                let totalCount = Object.keys(s).length;

                let bHtml = '';
                let dHtml = '';

                for(const [k, v] of Object.entries(s)) {
                    if(v.active) activeCount++;
                    const dotClass = v.active ? 'bg-green-500 pulse-green' : 'bg-red-500';
                    const txtColor = v.active ? 'text-green-400' : 'text-red-400';
                    const statusText = v.active ? 'Activo' : 'Inactivo';

                    bHtml += `
                        <div class="p-3 rounded-lg flex items-center justify-between themed-sub">
                            <span class="text-xs font-medium text-white truncate pr-2">${v.name.split('(')[0]}</span>
                            <span class="h-2 w-2 rounded-full ${dotClass} flex-shrink-0"></span>
                        </div>
                    `;

                    dHtml += `
                        <div class="p-5 flex items-center justify-between themed-card">
                            <div>
                                <div class="text-sm font-bold text-white">${v.name}</div>
                                <div class="text-xs font-mono mt-1 ${txtColor}">${statusText}</div>
                            </div>
                            <span class="h-3 w-3 rounded-full ${dotClass}"></span>
                        </div>
                    `;
                }

                if(badges) badges.innerHTML = bHtml;
                if(detail) detail.innerHTML = dHtml;
                const kpiServ = document.getElementById('kpiServices');
                if(kpiServ) kpiServ.textContent = `${activeCount}/${totalCount}`;
            }
        });
}

function refreshLogs() {
    fetch('api.php?action=logs')
        .then(r => r.json())
        .then(d => {
            if(d.status === 'ok') {
                const el = document.getElementById('logViewerFull');
                if(el) { el.textContent = d.logs; el.scrollTop = el.scrollHeight; }
            }
        });
}

// ─── Mobile Sidebar Toggle ───────────────────────────────────────────────────
function toggleMobileSidebar(open) {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if(!sidebar || !backdrop) return;
    const isOpen = open !== undefined ? open : sidebar.classList.contains('-translate-x-full');
    if(isOpen) {
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
    } else {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
    }
}

// ─── Theme Toggle & Sweep ────────────────────────────────────────────────────
function sweepInlineColors(isLight) {
    const colorPatches = [
        { match: 'color:#fff',    light: '#0f172a' },
        { match: 'color:#ffffff', light: '#0f172a' },
        { match: 'color:white',   light: '#0f172a' },
        { match: 'color:#e5e5e5', light: '#0f172a' },
        { match: 'color:#555',    light: '#475569' },
        { match: 'color:#555555', light: '#475569' },
        { match: 'color:#666',    light: '#475569' },
        { match: 'color:#666666', light: '#475569' },
        { match: 'color:#888',    light: '#475569' },
        { match: 'color:#888888', light: '#475569' },
        { match: 'color:#aaa',    light: '#334155' },
        { match: 'color:#aaaaaa', light: '#334155' },
    ];
    const bgPatches = [
        { match: 'background:#0d0d0d', light: '#ffffff' },
        { match: 'background:#0a0a0a', light: '#f8fafc' },
        { match: 'background:#111111', light: '#ffffff' },
        { match: 'background:#111;',   light: '#ffffff' },
        { match: 'background:#1a1a1a', light: '#f1f5f9' },
        { match: 'background:#141414', light: '#f8fafc' },
    ];
    if (isLight) {
        colorPatches.forEach(({match, light}) => {
            document.querySelectorAll(`[style*="${match}"]`).forEach(el => {
                if (!el.dataset.origStyle) el.dataset.origStyle = el.getAttribute('style') || '';
                el.style.color = light;
            });
        });
        bgPatches.forEach(({match, light}) => {
            document.querySelectorAll(`[style*="${match}"]`).forEach(el => {
                if (!el.dataset.origStyle) el.dataset.origStyle = el.getAttribute('style') || '';
                el.style.background = light;
            });
        });
    } else {
        document.querySelectorAll('[data-orig-style]').forEach(el => {
            el.setAttribute('style', el.dataset.origStyle || '');
            delete el.dataset.origStyle;
        });
    }
}

function applyTheme(t) {
    const isLight = t === 'light';
    document.documentElement.setAttribute('data-theme', t);
    sweepInlineColors(isLight);

    const sidebar = document.getElementById('sidebar');
    if(sidebar) { sidebar.style.background = isLight ? '#ffffff' : '#0d0d0d'; sidebar.style.borderColor = isLight ? '#e2e8f0' : 'var(--border)'; }
    const header = document.querySelector('header');
    if(header) { header.style.background = isLight ? '#ffffff' : '#0a0a0a'; header.style.borderColor = isLight ? '#e2e8f0' : 'var(--border)'; }

    const icon = document.getElementById('themeIcon');
    const label = document.getElementById('themeLabel');
    if(icon && label) {
        icon.className = isLight ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
        label.textContent = isLight ? 'Oscuro' : 'Claro';
    }
}

function toggleTheme() {
    const cur = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = cur === 'light' ? 'dark' : 'light';
    localStorage.setItem('chatosync-theme', next);
    applyTheme(next);
}

// ─── Modal de Previsualización ────────────────────────────────────────────────
const previewModal = document.getElementById('previewModal');
const modalBody = document.getElementById('modalBody');
const modalTitle = document.getElementById('modalTitle');
const modalSize = document.getElementById('modalSize');
const modalIcon = document.getElementById('modalIcon');
const modalDownload = document.getElementById('modalDownloadBtn');

const extIcons = {
    'pdf':'📄','png':'🖼️','jpg':'🖼️','jpeg':'🖼️','gif':'🖼️','webp':'🖼️','svg':'🖼️',
    'mp4':'🎬','webm':'🎬','mov':'🎬','mp3':'🎵','wav':'🎵','ogg':'🎵',
    'txt':'📋','log':'📋','json':'📋','py':'🐍','sh':'🔧','md':'📝','csv':'📊'
};

function openPreview(encodedFilename, ext, displayName, size) {
    const rawUrl = 'download.php?file=' + encodedFilename;
    const previewUrl = rawUrl + '&preview=1';
    
    modalTitle.textContent = displayName;
    modalSize.textContent = size;
    modalIcon.textContent = extIcons[ext] || '📄';
    modalDownload.href = rawUrl;
    modalBody.innerHTML = '<div class="text-center py-10"><i class="fa-solid fa-spinner fa-spin text-3xl" style="color:var(--red2);"></i><p class="text-xs mt-2 text-slate-400">Cargando...</p></div>';
    
    previewModal.classList.remove('hidden');
    previewModal.classList.add('flex');

    if(['jpg','jpeg','png','gif','webp','svg'].includes(ext)) {
        modalBody.innerHTML = `<div class="flex items-center justify-center p-2"><img src="${previewUrl}" alt="${displayName}" class="max-h-[72vh] max-w-full rounded-xl object-contain shadow-2xl border border-neutral-700/50"></div>`;
    } else if(ext === 'pdf') {
        modalBody.innerHTML = `<iframe src="${previewUrl}" class="w-full h-[72vh] rounded-xl border border-neutral-700 bg-white" style="border:none;"></iframe>`;
    } else if(['mp4','webm','mov'].includes(ext)) {
        modalBody.innerHTML = `<div class="flex items-center justify-center w-full"><video controls autoplay class="max-h-[70vh] w-full max-w-3xl rounded-xl shadow-2xl bg-black"><source src="${previewUrl}">Tu navegador no soporta video.</video></div>`;
    } else if(['mp3','wav','ogg'].includes(ext)) {
        modalBody.innerHTML = `
            <div class="py-12 px-6 flex flex-col items-center justify-center gap-4 text-center">
                <div class="h-20 w-20 rounded-full flex items-center justify-center text-4xl shadow-xl" style="background:var(--redbg);">🎵</div>
                <h4 class="text-sm font-semibold text-white">${displayName}</h4>
                <audio controls autoplay class="w-full max-w-md mt-2"><source src="${previewUrl}">Tu navegador no soporta audio.</audio>
            </div>`;
    } else if(['txt','log','json','py','sh','md','csv'].includes(ext)) {
        fetch(previewUrl)
            .then(r => r.text())
            .then(text => {
                const escaped = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
                modalBody.innerHTML = `<pre class="w-full p-4 rounded-xl text-xs font-mono overflow-auto max-h-[70vh] whitespace-pre-wrap themed-sub">${escaped}</pre>`;
            })
            .catch(() => {
                modalBody.innerHTML = `<div class="text-center text-xs p-8" style="color:var(--red2);">No se pudo cargar el archivo de texto.</div>`;
            });
    } else {
        modalBody.innerHTML = `
            <div class="py-12 px-6 flex flex-col items-center justify-center gap-4 text-center">
                <div class="text-5xl">📑</div>
                <h4 class="text-sm font-semibold text-white">${displayName}</h4>
                <p class="text-xs text-slate-400 max-w-sm">Este archivo (${ext.toUpperCase()}) se puede descargar y abrir en tu dispositivo.</p>
                <a href="${rawUrl}" class="mt-2 px-4 py-2 rounded-xl text-xs font-bold text-white flex items-center gap-2" style="background:var(--red);"><i class="fa-solid fa-download"></i> Descargar Ahora</a>
            </div>`;
    }
}

function closePreview() {
    previewModal.classList.add('hidden');
    previewModal.classList.remove('flex');
    modalBody.innerHTML = '';
}

window.addEventListener('keydown', e => { if (e.key === 'Escape') closePreview(); });
previewModal.addEventListener('click', e => { if (e.target === previewModal) closePreview(); });

// ─── Actualización en Tiempo Real de Archivos (Live Polling) ─────────────────
function refreshRealtimeFiles() {
    fetch('api.php?action=files')
        .then(r => r.json())
        .then(d => {
            if(d.status === 'ok') {
                // Actualizar KPIs de archivos
                const kpiFiles = document.getElementById('kpiFilesCount');
                if(kpiFiles) kpiFiles.textContent = d.count;
                const kpiSize = document.getElementById('kpiFilesSize');
                if(kpiSize) kpiSize.textContent = `${d.total_size_formatted} total · Samba + Web`;

                // Actualizar lista reciente en Dashboard
                const recentContainer = document.getElementById('recentFilesContainer');
                if(recentContainer) {
                    if(!d.files.length) {
                        recentContainer.innerHTML = `
                            <div class="px-5 py-8 text-center text-xs" style="color:#444;">
                                <i class="fa-solid fa-folder-open text-2xl mb-2 block opacity-20"></i>
                                Hub vacío — Sube archivos desde Transfer
                            </div>`;
                    } else {
                        const recents = d.files.slice(0, 6);
                        recentContainer.innerHTML = recents.map(f => `
                            <div class="flex items-center gap-3 px-5 py-2.5 hover:bg-white/5 transition-colors group">
                                <span class="text-lg flex-shrink-0">${f.icon}</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-white truncate">${f.name}</p>
                                    <p class="text-[10px]" style="color:#555;">${f.size_formatted} · ${f.date_formatted}</p>
                                </div>
                                <div class="flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                    ${f.can_preview ? `
                                    <button type="button"
                                            onclick="openPreview('${encodeURIComponent(f.name)}', '${f.ext}', '${f.name.replace(/'/g,"\\'")}', '${f.size_formatted}')"
                                            class="text-xs px-2 py-1 rounded flex items-center gap-1"
                                            style="background:var(--card2);color:var(--text);border:1px solid var(--border);"
                                            title="Previsualizar">
                                        <i class="fa-solid fa-eye" style="color:var(--red2);"></i>
                                    </button>` : ''}
                                    <a href="/download.php?file=${encodeURIComponent(f.name)}"
                                       class="text-xs px-2 py-1 rounded"
                                       style="background:var(--redbg);color:var(--red2);"
                                       title="Descargar">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        `).join('');
                    }
                }

                // Actualizar lista completa en panel Transfer
                const fullContainer = document.getElementById('fullFilesContainer');
                if(fullContainer) {
                    if(!d.files.length) {
                        fullContainer.innerHTML = `
                            <div class="p-10 text-center text-xs" style="color:#444;">
                                <i class="fa-solid fa-inbox text-3xl mb-2 block opacity-20"></i>
                                No hay archivos en el Hub — sube el primero arriba
                            </div>`;
                    } else {
                        fullContainer.innerHTML = d.files.map(f => `
                            <div class="flex items-center gap-3 px-5 py-3 hover:bg-white/5 transition-colors group">
                                <span class="text-xl">${f.icon}</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-white truncate">${f.name}</p>
                                    <p class="text-[11px]" style="color:#555;">${f.size_formatted} · ${f.date_formatted}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    ${f.can_preview ? `
                                    <button type="button"
                                            onclick="openPreview('${encodeURIComponent(f.name)}', '${f.ext}', '${f.name.replace(/'/g,"\\'")}', '${f.size_formatted}')"
                                            class="px-2.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1"
                                            style="background:var(--card2);border:1px solid var(--border);color:var(--text);"
                                            title="Previsualizar">
                                        <i class="fa-solid fa-eye" style="color:var(--red2);"></i>
                                        <span class="hidden sm:inline">Ver</span>
                                    </button>` : ''}
                                    <a href="/download.php?file=${encodeURIComponent(f.name)}"
                                       class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white flex items-center gap-1 shadow-sm"
                                       style="background:var(--red);">
                                        <i class="fa-solid fa-download"></i>
                                        <span class="hidden sm:inline">Descargar</span>
                                    </a>
                                    <a href="transfer.php?del=${encodeURIComponent(f.name)}"
                                       onclick="return confirm('¿Eliminar ${f.name}?')"
                                       class="px-2 py-1.5 rounded-lg text-xs transition-colors"
                                       style="background:#1a1a1a;border:1px solid var(--border);color:#555;">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        `).join('');
                    }
                }
            }
        })
        .catch(() => {});
}

// ─── Inicialización ──────────────────────────────────────────────────────────
setupOCRUpload();
renderVisualSchedule(currentSchedule);
renderTableSchedule(currentSchedule);
refreshStatus();
refreshRealtimeFiles();
refreshLogs();

// Live Real-Time Timers
setInterval(refreshRealtimeFiles, 3000); // Archivos en tiempo real cada 3s
setInterval(refreshStatus, 8000);        // Servicios cada 8s
setInterval(refreshLogs, 4000);          // Logs cada 4s

(function(){ applyTheme(localStorage.getItem('chatosync-theme') || 'dark'); })();
</script>
</body>
</html>

