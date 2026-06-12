<?php
/**
 * Download de PDF Assinado
 * 
 * Permite download seguro do PDF com assinatura
 */

require_once __DIR__ . '/../src/config/config.php';

// Verificar se o arquivo foi especificado
if (!isset($_GET['file'])) {
    http_response_code(400);
    die('Arquivo não especificado');
}

// Sanitizar nome do arquivo
$filename = basename($_GET['file']);

// Caminho completo do arquivo
$filePath = __DIR__ . '/../uploads/signed/' . $filename;

// Verificar se o arquivo existe
if (!file_exists($filePath)) {
    http_response_code(404);
    die('Arquivo não encontrado');
}

// Verificar se é um PDF
if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'pdf') {
    http_response_code(403);
    die('Tipo de arquivo não permitido');
}

// Headers para download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Enviar arquivo
readfile($filePath);
exit;
