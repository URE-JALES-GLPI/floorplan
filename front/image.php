<?php
/**
 * front/image.php — Serve arquivos de imagem da planta com controle de sessão
 */

include('../../../inc/includes.php');

Session::checkRight('entity', READ);

$file = basename($_GET['file'] ?? '');
if (empty($file)) {
    http_response_code(400);
    exit('Arquivo não especificado');
}

$path = GLPI_PLUGIN_DOC_DIR . '/floorplan/images/' . $file;

if (!file_exists($path)) {
    http_response_code(404);
    exit('Arquivo não encontrado');
}

$ext_map = [
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'svg'  => 'image/svg+xml',
    'pdf'  => 'application/pdf',
];

$ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = $ext_map[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
readfile($path);
exit;
