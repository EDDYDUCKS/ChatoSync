<?php
// download.php — Sirve y previsualiza archivos desde el hub de forma segura
$uploadDir = "/srv/samba/hub/";

if (!isset($_GET['file'])) {
    http_response_code(400);
    die('Archivo no especificado.');
}

$filename = basename($_GET['file']); // Sanitize path traversal
$filepath = $uploadDir . $filename;

if (!file_exists($filepath) || is_dir($filepath)) {
    http_response_code(404);
    die('Archivo no encontrado.');
}

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Mapeo preciso de tipos MIME para visualización en navegador
$mimeTypes = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml',
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'ogg'  => 'audio/ogg',
    'txt'  => 'text/plain; charset=utf-8',
    'log'  => 'text/plain; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'py'   => 'text/plain; charset=utf-8',
    'sh'   => 'text/plain; charset=utf-8',
    'md'   => 'text/plain; charset=utf-8',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

$mime = $mimeTypes[$ext] ?? (mime_content_type($filepath) ?: 'application/octet-stream');
$size = filesize($filepath);

$isInline = isset($_GET['preview']) || isset($_GET['inline']);
$disposition = $isInline ? 'inline' : 'attachment';

header("Content-Type: $mime");
header("Content-Disposition: $disposition; filename=\"" . rawurlencode($filename) . "\"");
header("Content-Length: $size");
header("Cache-Control: public, max-age=3600");
header("Accept-Ranges: bytes");

readfile($filepath);
exit;
?>
