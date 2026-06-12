<?php

namespace Ipsis\services;

use setasign\Fpdi\Fpdi;

/**
 * Serviço para adicionar assinatura digital em PDF
 */
class PDFSignatureService
{
    private $originalPdfPath;
    private $outputDir;

    public function __construct()
    {
        $this->originalPdfPath = __DIR__ . '/../../doc/CodigoCliente.pdf';
        $this->outputDir = __DIR__ . '/../../uploads/signed/';
        
        // Criar diretório se não existir
        if (!file_exists($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    /**
     * Adiciona assinatura ao PDF
     * 
     * @param string $assinatura Base64 da imagem ou texto da assinatura
     * @param string $tipoAssinatura 'canvas' ou 'typed'
     * @param array $dadosCliente Dados do cliente para identificação
     * @return string|false Caminho do PDF assinado ou false em caso de erro
     */
    public function adicionarAssinatura($assinatura, $tipoAssinatura, $dadosCliente)
    {
        try {
            // Verificar se o PDF original existe
            if (!file_exists($this->originalPdfPath)) {
                throw new \Exception("PDF original não encontrado");
            }

            // Criar instância do FPDI
            $pdf = new Fpdi();
            
            // Obter número de páginas do PDF original
            $pageCount = $pdf->setSourceFile($this->originalPdfPath);
            
            // Importar todas as páginas
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                // Importar página
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                
                // Adicionar página com mesma orientação e tamanho
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                
                // Usar o template
                $pdf->useTemplate($templateId);
                
                // Se for a última página, adicionar assinatura
                if ($pageNo === $pageCount) {
                    $this->adicionarAssinaturaUltimaPagina($pdf, $assinatura, $tipoAssinatura, $dadosCliente, $size);
                }
            }
            
            // Gerar nome único para o arquivo
            $cnpj = preg_replace('/[^0-9]/', '', $dadosCliente['cnpj']);
            $timestamp = date('YmdHis');
            $filename = "codigo_cliente_assinado_{$cnpj}_{$timestamp}.pdf";
            $outputPath = $this->outputDir . $filename;
            
            // Salvar PDF
            $pdf->Output('F', $outputPath);
            
            return $outputPath;
            
        } catch (\Exception $e) {
            error_log("Erro ao gerar PDF assinado: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Adiciona a assinatura na última página do PDF
     */
    private function adicionarAssinaturaUltimaPagina($pdf, $assinatura, $tipoAssinatura, $dadosCliente, $pageSize)
    {
        $pageWidth = $pageSize['width'];
        $pageHeight = $pageSize['height'];
        
        // Posição da assinatura (canto inferior direito)
        $signatureX = $pageWidth - 80;
        $signatureY = $pageHeight - 60;
        $signatureWidth = 70;
        $signatureHeight = 30;
        
        // Adicionar caixa de assinatura
        $pdf->SetDrawColor(0, 102, 204);
        $pdf->SetLineWidth(0.5);
        $pdf->Rect($signatureX - 5, $signatureY - 5, $signatureWidth + 10, $signatureHeight + 20);
        
        if ($tipoAssinatura === 'canvas') {
            // Assinatura desenhada (base64)
            $this->adicionarAssinaturaImagem($pdf, $assinatura, $signatureX, $signatureY, $signatureWidth, $signatureHeight);
        } else {
            // Assinatura digitada (texto)
            $this->adicionarAssinaturaTexto($pdf, $assinatura, $signatureX, $signatureY, $signatureWidth);
        }
        
        // Adicionar informações do cliente
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY($signatureX - 5, $signatureY + $signatureHeight + 2);
        $pdf->Cell($signatureWidth + 10, 4, utf8_decode($dadosCliente['razao_social']), 0, 1, 'C');
        
        $pdf->SetXY($signatureX - 5, $signatureY + $signatureHeight + 6);
        $pdf->Cell($signatureWidth + 10, 4, 'CNPJ: ' . $dadosCliente['cnpj'], 0, 1, 'C');
        
        $pdf->SetXY($signatureX - 5, $signatureY + $signatureHeight + 10);
        $pdf->Cell($signatureWidth + 10, 4, 'Data: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    }

    /**
     * Adiciona assinatura como imagem (canvas)
     */
    private function adicionarAssinaturaImagem($pdf, $base64Image, $x, $y, $width, $height)
    {
        try {
            // Remover prefixo data:image/png;base64,
            $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $base64Image);
            $imageData = base64_decode($imageData);
            
            // Criar arquivo temporário
            $tempFile = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
            file_put_contents($tempFile, $imageData);
            
            // Adicionar imagem ao PDF
            $pdf->Image($tempFile, $x, $y, $width, $height, 'PNG');
            
            // Remover arquivo temporário
            unlink($tempFile);
            
        } catch (\Exception $e) {
            error_log("Erro ao adicionar imagem de assinatura: " . $e->getMessage());
            // Fallback: adicionar texto
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->SetXY($x, $y + 10);
            $pdf->Cell($width, 10, '[Assinatura Digital]', 0, 0, 'C');
        }
    }

    /**
     * Adiciona assinatura como texto
     */
    private function adicionarAssinaturaTexto($pdf, $texto, $x, $y, $width)
    {
        $pdf->SetFont('Brush Script MT', 'I', 16);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y + 8);
        $pdf->Cell($width, 10, utf8_decode($texto), 0, 0, 'C');
        
        // Linha abaixo do nome
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($x, $y + 20, $x + $width, $y + 20);
    }

    /**
     * Obtém o PDF assinado como string para download
     */
    public function getPDFAssinado($filePath)
    {
        if (file_exists($filePath)) {
            return file_get_contents($filePath);
        }
        return false;
    }

    /**
     * Remove PDFs assinados antigos (mais de 30 dias)
     */
    public function limparPDFsAntigos()
    {
        $files = glob($this->outputDir . '*.pdf');
        $now = time();
        $diasParaExpirar = 30;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $diasParaExpirar) {
                    unlink($file);
                }
            }
        }
    }
}
