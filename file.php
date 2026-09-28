<?php
// Documents sirf login user ko dikhein (uploads folder direct band hai)
require __DIR__ . '/includes/bootstrap.php';

$f = db_one('SELECT * FROM tenancy_files WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
$path = $f ? __DIR__ . '/uploads/tenancy/' . basename($f['file_name']) : null;
if (!$f || !is_file($path)) {
    http_response_code(404);
    exit('File nahi mili.');
}
header('Content-Type: ' . $f['mime']);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . rawurlencode($f['original_name'] ?: $f['file_name']) . '"');
header('Cache-Control: private, max-age=3600');
readfile($path);
