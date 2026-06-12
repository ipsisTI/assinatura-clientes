<?php
/**
 * Configurações Gerais da Aplicação
 * 
 * Este arquivo carrega as variáveis de ambiente e define
 * constantes globais para a aplicação.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Configurações do Google Sheets
define('GOOGLE_SHEET_ID', $_ENV['GOOGLE_SHEET_ID']);
define('GOOGLE_CREDENTIALS_PATH', __DIR__ . '/../../' . $_ENV['GOOGLE_CREDENTIALS_PATH']);

// Configurações do Google Drive
define('GOOGLE_DRIVE_FOLDER_ID', $_ENV['GOOGLE_DRIVE_FOLDER_ID'] ?? '');

// Configurações do reCAPTCHA
define('RECAPTCHA_SITE_KEY', $_ENV['RECAPTCHA_SITE_KEY']);
define('RECAPTCHA_SECRET_KEY', $_ENV['RECAPTCHA_SECRET_KEY']);

// Configurações da Aplicação
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('APP_URL', $_ENV['APP_URL']);

// Configurações de Upload
define('MAX_FILE_SIZE', (int)$_ENV['MAX_FILE_SIZE']);
define('ALLOWED_FILE_TYPES', explode(',', $_ENV['ALLOWED_FILE_TYPES']));
define('UPLOAD_DIR', __DIR__ . '/../../uploads/');

// Configurações de Segurança
define('SESSION_LIFETIME', (int)$_ENV['SESSION_LIFETIME']);

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de erro baseadas no ambiente
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}
