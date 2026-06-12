<?php
date_default_timezone_set('America/Sao_Paulo');
ob_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo nao permitido');
    }
    
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../src/config/config.php';
    require_once __DIR__ . '/../src/services/GoogleDriveService.php';
    
    $nomeCompleto = trim($_POST['nome_completo'] ?? '');
    $razaoSocial = trim($_POST['razao_social'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $protetorAuricular = trim($_POST['protetor_auricular'] ?? '');
    $assinatura = $_POST['assinatura'] ?? '';
    $assinaturaTipo = $_POST['assinatura_tipo'] ?? 'canvas';
    
    // Campos comentados (não mais utilizados)
    // $telefone = trim($_POST['telefone'] ?? '');
    // $email = trim($_POST['email'] ?? '');
    // $segmento = trim($_POST['segmento'] ?? '');
    
    if (empty($nomeCompleto) || empty($cnpj) || empty($protetorAuricular) || empty($assinatura)) {
        throw new Exception('Dados obrigatorios nao preenchidos');
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
            throw new Exception('PDF original nao encontrado: ' . $pdfPath);
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
    
    $driveLink = '';
    try {
        $driveService = new \Ipsis\services\GoogleDriveService();
        $uploadResult = $driveService->uploadFile($outputDir . $filename, $filename);
        $driveLink = $uploadResult['link'];
    } catch (Exception $e) {
        error_log('Erro Drive: ' . $e->getMessage());
    }
    
    try {
        $client = new Google_Client();
        $client->setApplicationName('Ipsis Clientes');
        $client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
        $client->setAuthConfig(GOOGLE_CREDENTIALS_PATH);
        $client->setAccessType('offline');
        
        $service = new Google_Service_Sheets($client);
        
        if (!empty($driveLink)) {
            $pdfCell = '=HYPERLINK("' . $driveLink . '"; "' . $filename . '")';
        } else {
            $downloadUrl = APP_URL . '/download-pdf.php?file=' . urlencode($filename);
            $pdfCell = '=HYPERLINK("' . $downloadUrl . '"; "' . $filename . '")';
        }
        
        $rowData = [[
            date('d/m/Y H:i:s'),
            $nomeCompleto,
            $razaoSocial,
            $cnpj,
            $protetorAuricular,
            $pdfCell
        ]];
        
        $body = new Google_Service_Sheets_ValueRange(['values' => $rowData]);
        $params = ['valueInputOption' => 'USER_ENTERED'];
        
        $service->spreadsheets_values->append(GOOGLE_SHEET_ID, 'A:F', $body, $params);
    } catch (Exception $e) {
        error_log('Erro Sheets: ' . $e->getMessage());
    }
    
    ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Cadastro realizado com sucesso!',
        'pdf_assinado' => $filename
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
