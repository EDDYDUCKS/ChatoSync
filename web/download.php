<?php
// download.php — Sirve archivos desde el hub de forma segura
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

$mime = mime_content_type($filepath) ?: 'application/octet-stream';
$size = filesize($filepath);

header("Content-Type: $mime");
header("Content-Disposition: attachment; filename=\"" . rawurlencode($filename) . "\"");
header("Content-Length: $size");
header("Cache-Control: no-cache");
header("X-Content-Type-Options: nosniff");

readfile($filepath);
exit;
?>
