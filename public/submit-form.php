<?php
date_default_timezone_set('America/Sao_Paulo');
ob_start();

$statusCode = 500;
$warnings = [];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $statusCode = 405;
        throw new Exception('Metodo nao permitido');
    }

    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../src/config/config.php';
    require_once __DIR__ . '/../src/utils/helpers.php';
    require_once __DIR__ . '/../src/services/GoogleDriveService.php';

    // Proteção CSRF: valida o token gerado no formulário
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $statusCode = 403;
        throw new Exception('Sua sessao expirou. Recarregue a pagina e tente novamente.');
    }

    // Verificação server-side do reCAPTCHA (fail-closed)
    if (!verifyRecaptcha($_POST['recaptcha_token'] ?? '')) {
        $statusCode = 422;
        throw new Exception('Falha na verificacao anti-spam. Recarregue a pagina e tente novamente.');
    }

    // Rate limit por sessão: mínimo de 30s entre envios
    if (isset($_SESSION['last_submit_at']) && (time() - $_SESSION['last_submit_at']) < 30) {
        $statusCode = 429;
        throw new Exception('Aguarde alguns instantes antes de enviar novamente.');
    }

    // Coleta e sanitização dos campos
    $nomeCompleto = trim(strip_tags($_POST['nome_completo'] ?? ''));
    $razaoSocial = trim(strip_tags($_POST['razao_social'] ?? ''));
    $cnpj = trim(strip_tags($_POST['cnpj'] ?? ''));
    $protetorAuricular = trim(strip_tags($_POST['protetor_auricular'] ?? ''));
    $assinatura = $_POST['assinatura'] ?? '';
    $assinaturaTipo = trim(strip_tags($_POST['assinatura_tipo'] ?? 'canvas'));

    // Campos comentados (não mais utilizados)
    // $telefone = trim($_POST['telefone'] ?? '');
    // $email = trim($_POST['email'] ?? '');
    // $segmento = trim($_POST['segmento'] ?? '');

    // Validação da assinatura conforme o tipo
    if (!in_array($assinaturaTipo, ['canvas', 'typed'], true)) {
        $statusCode = 422;
        throw new Exception('Tipo de assinatura invalido. Recarregue a pagina e tente novamente.');
    }

    if ($assinaturaTipo === 'canvas') {
        // Assinatura desenhada: precisa ser um data URI de imagem, com tamanho limitado
        $assinatura = trim((string)$assinatura);
        if (strpos($assinatura, 'data:image') !== 0 || strlen($assinatura) > 2000000) {
            $statusCode = 422;
            throw new Exception('Assinatura invalida. Desenhe sua assinatura novamente.');
        }
    } else {
        // Assinatura digitada: texto curto sanitizado
        $assinatura = mb_substr(trim(strip_tags((string)$assinatura)), 0, 150);
        if ($assinatura === '') {
            $statusCode = 422;
            throw new Exception('Digite sua assinatura para concluir.');
        }
    }

    // Validações específicas do formulário
    if (strlen($nomeCompleto) < 3) {
        $statusCode = 422;
        throw new Exception('Informe o nome completo');
    }

    if (!isValidCNPJOrCPF($cnpj)) {
        $statusCode = 422;
        throw new Exception('CNPJ/CPF inválido. Verifique os números informados.');
    }

    // Limites de tamanho dos campos que vão para a planilha
    if (mb_strlen($nomeCompleto) > 150) {
        $statusCode = 422;
        throw new Exception('Nome completo muito longo (máximo de 150 caracteres)');
    }

    if (mb_strlen($razaoSocial) > 150) {
        $statusCode = 422;
        throw new Exception('Razão social muito longa (máximo de 150 caracteres)');
    }

    // Whitelist: protetor_auricular é um select com opções fixas no formulário (index.php)
    if (!in_array($protetorAuricular, ['Sim', 'Não'], true)) {
        $statusCode = 422;
        throw new Exception('Valor inválido para protetor_auricular');
    }

    $isBase64Image = (strpos($assinatura, 'data:image') === 0);

    $pdf = new \setasign\Fpdi\Fpdi();

    // Documentos que compoem o formulario, na ordem em que aparecem.
    // Para adicionar um novo documento, basta incluir o caminho do PDF aqui.
    $documentos = [
        __DIR__ . '/doc/CodigoCliente.pdf',
        __DIR__ . '/doc/GuiaVisitante.pdf',
    ];

    foreach ($documentos as $pdfPath) {
        if (!file_exists($pdfPath)) {
            // Caminho absoluto nao vai ao cliente: fica apenas no error_log
            error_log('PDF original nao encontrado: ' . $pdfPath);
            throw new Exception('Documento base nao encontrado. Contate o suporte.');
        }

        try {
            $pageCount = $pdf->setSourceFile($pdfPath);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }
        } catch (\Exception $e) {
            // O FPDI gratuito le apenas PDFs ate a versao 1.4. Documentos 1.5+
            // que usam compressao (object streams) lancam excecao aqui.
            // Registramos o erro e seguimos para nao quebrar o cadastro.
            error_log('Falha ao importar documento "' . basename($pdfPath) . '": ' . $e->getMessage());
        }
    }

    $pdf->AddPage('P', 'A4');

    $logoPath = __DIR__ . '/assets/images/logo.png';
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 85, 15, 40, 0, 'PNG');
    }

    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(0, 102, 204);
    $pdf->SetY(45);
    $pdf->Cell(0, 10, 'TERMO DE ACEITE E ASSINATURA', 0, 1, 'C');

    $pdf->SetDrawColor(0, 102, 204);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(20, 60, 190, 60);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetY(70);
    $pdf->Cell(0, 10, 'DADOS DO CLIENTE', 0, 1, 'L');

    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(50, 8, 'Nome Completo:', 0, 0);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, utf8_decode($nomeCompleto), 0, 1);

    if (!empty($razaoSocial)) {
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(50, 8, 'Razao Social:', 0, 0);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, utf8_decode($razaoSocial), 0, 1);
    }

    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(50, 8, 'CNPJ/CPF:', 0, 0);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, $cnpj, 0, 1);

    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(50, 8, 'Protetor Auricular:', 0, 0);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, utf8_decode($protetorAuricular), 0, 1);

    $pdf->Ln(10);

    /* COMENTADO - Dados de Contato e Segmento não mais utilizados
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'DADOS DE CONTATO', 0, 1, 'L');

    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(50, 8, 'Telefone:', 0, 0);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, $telefone, 0, 1);

    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(50, 8, 'Email:', 0, 0);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, $email, 0, 1);

    if (!empty($segmento)) {
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(50, 8, 'Segmento:', 0, 0);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, utf8_decode($segmento), 0, 1);
    }

    $pdf->Ln(15);
    */

    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, 'Declaro que li e concordo integralmente com o Codigo do Cliente e o Guia do Visitante, comprometendo-me a cumprir todas as diretrizes, politicas e procedimentos estabelecidos nestes documentos.', 0, 'J');

    $pdf->Ln(20);
    $yAssinatura = $pdf->GetY();

    $pdf->SetDrawColor(0, 102, 204);
    $pdf->SetLineWidth(1);
    $pdf->Rect(55, $yAssinatura, 100, 40);

    // Adicionar assinatura
    if ($assinaturaTipo === 'canvas' && $isBase64Image) {
        // Assinatura desenhada
        try {
            $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $assinatura);
            $imageData = base64_decode($imageData);

            if ($imageData && strlen($imageData) > 0) {
                $tempFile = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
                file_put_contents($tempFile, $imageData);

                if (file_exists($tempFile) && filesize($tempFile) > 0) {
                    $pdf->Image($tempFile, 60, $yAssinatura + 5, 90, 30, 'PNG');
                    unlink($tempFile);
                } else {
                    throw new Exception('Arquivo vazio');
                }
            } else {
                throw new Exception('Dados vazios');
            }
        } catch (Exception $e) {
            // Fallback
            $pdf->SetFont('Arial', 'I', 16);
            $pdf->SetTextColor(0, 102, 204);
            $pdf->SetXY(55, $yAssinatura + 12);
            $pdf->Cell(100, 10, '[Assinatura Digital]', 0, 0, 'C');
        }
    } else {
        // Assinatura digitada ou fallback
        $pdf->SetFont('Arial', 'I', 20);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(55, $yAssinatura + 12);
        $textoAssinatura = strlen($assinatura) < 100 ? $assinatura : '[Assinatura Digital]';
        $pdf->Cell(100, 10, $textoAssinatura, 0, 0, 'C');
    }

    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.3);
    $yLinha = $yAssinatura + 40;
    $pdf->Line(55, $yLinha, 155, $yLinha);

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetXY(55, $yLinha + 2);
    $pdf->Cell(100, 5, utf8_decode($nomeCompleto), 0, 1, 'C');
    $pdf->SetX(55);
    $pdf->Cell(100, 5, 'CNPJ/CPF: ' . $cnpj, 0, 1, 'C');
    $pdf->SetX(55);
    $pdf->Cell(100, 5, 'Data: ' . date('d/m/Y H:i:s'), 0, 1, 'C');

    $outputDir = __DIR__ . '/../uploads/signed/';
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $cnpjLimpo = preg_replace('/[^0-9]/', '', $cnpj);
    $timestamp = date('YmdHis');
    $filename = "codigo_cliente_assinado_{$cnpjLimpo}_{$timestamp}.pdf";

    $pdf->Output('F', $outputDir . $filename);

    // Autoriza o download deste arquivo nesta sessão (máximo de 10 na lista)
    $_SESSION['downloadable_pdfs'][] = $filename;
    if (count($_SESSION['downloadable_pdfs']) > 10) {
        array_shift($_SESSION['downloadable_pdfs']);
    }

    $driveLink = '';
    try {
        $driveService = new \Ipsis\services\GoogleDriveService();
        $uploadResult = $driveService->uploadFile($outputDir . $filename, $filename);
        $driveLink = $uploadResult['link'];
    } catch (Exception $e) {
        error_log('Erro Drive: ' . $e->getMessage());
        $warnings[] = 'drive';
        logIntegration('drive_upload_failed', ['arquivo' => $filename, 'documento' => $cnpj, 'erro' => $e->getMessage()]);
    }

    // Inicializada para o log de falha, caso a exceção ocorra antes da montagem da linha
    $rowData = null;
    try {
        $client = new Google_Client();
        $client->setApplicationName('Ipsis Clientes');
        $client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
        $client->setAuthConfig(GOOGLE_CREDENTIALS_PATH);
        $client->setAccessType('offline');

        $service = new Google_Service_Sheets($client);

        if (!empty($driveLink)) {
            // Com Drive OK: hyperlink do PDF no Drive (fórmula construída pelo servidor)
            $pdfCell = '=HYPERLINK("' . $driveLink . '"; "' . sanitizeSheetsValue($filename) . '")';
        } else {
            // Sem Drive: apenas o nome do arquivo como TEXTO simples — o link do
            // download-pdf.php agora é bloqueado pelo gate de sessão (daria 403
            // para a equipe), então não gera mais =HYPERLINK nem URL própria
            $pdfCell = sanitizeSheetsValue($filename);
        }

        // Todo valor vindo do usuário é sanitizado contra injeção de fórmula;
        // a célula =HYPERLINK acima é construída pelo servidor e é intencional
        $rowData = [[
            date('d/m/Y H:i:s'),
            sanitizeSheetsValue($nomeCompleto),
            sanitizeSheetsValue($razaoSocial),
            sanitizeSheetsValue($cnpj),
            sanitizeSheetsValue($protetorAuricular),
            $pdfCell
        ]];

        $body = new Google_Service_Sheets_ValueRange(['values' => $rowData]);
        $params = ['valueInputOption' => 'USER_ENTERED'];

        $service->spreadsheets_values->append(GOOGLE_SHEET_ID, 'A:F', $body, $params);
    } catch (Exception $e) {
        error_log('Erro Sheets: ' . $e->getMessage());
        $warnings[] = 'sheets';
        logIntegration('sheets_append_failed', ['arquivo' => $filename, 'linha' => $rowData, 'drive_link' => $driveLink, 'erro' => $e->getMessage()]);
    }

    // Log estruturado do registro para auditoria/reprocesso
    logIntegration('assinatura_registrada', [
        'arquivo' => $filename,
        'drive_ok' => !in_array('drive', $warnings),
        'sheets_ok' => !in_array('sheets', $warnings),
        'dados' => [
            'nome_completo' => $nomeCompleto,
            'razao_social' => $razaoSocial,
            'cnpj' => $cnpj,
            'protetor_auricular' => $protetorAuricular,
            'assinatura_tipo' => $assinaturaTipo
        ]
    ]);

    // Registra o momento do envio somente aqui, imediatamente antes da resposta
    // de sucesso: se qualquer integração (PDF/Drive/Sheets) falhar antes disso,
    // o usuário ainda pode repetir o envio sem esperar os 30s do rate limit
    $_SESSION['last_submit_at'] = time();

    // Consome o token CSRF após o sucesso
    unset($_SESSION['csrf_token']);

    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Cadastro realizado com sucesso!',
        'pdf_assinado' => $filename,
        'warnings' => $warnings
    ]);

} catch (Throwable $e) {
    // Throwable (e não apenas Exception): erros do PHP (TypeError, Error etc.)
    // também caem aqui e retornam JSON em vez de HTML quebrado
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    // Em erros 5xx a mensagem bruta nunca vai ao cliente: exceções de
    // bibliotecas (FPDF/FPDI, Google API, Dotenv) podem vazar caminhos
    // absolutos e detalhes internos do servidor. O detalhe fica no error_log.
    if ($statusCode >= 500) {
        error_log('submit-form (' . date('Y-m-d H:i:s') . '): ' . $e->getMessage());
    }
    $mensagemErro = ($statusCode >= 500 && !defined('APP_DEBUG')) || ($statusCode >= 500 && !APP_DEBUG)
        ? 'Erro interno ao processar a assinatura. Tente novamente.'
        : $e->getMessage();
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $mensagemErro
    ]);
}
