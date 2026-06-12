<?php
$pdfFile = $_GET['pdf'] ?? '';
$pdfFile = basename($pdfFile); // Segurança
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro Realizado - Ipsis</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="apple-touch-icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .pdf-viewer-container {
            margin: 30px 0;
            background: #f5f5f5;
            border-radius: 8px;
            padding: 20px;
        }
        
        .pdf-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .pdf-controls button {
            background: #0066cc;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .pdf-controls button:hover {
            background: #0052a3;
        }
        
        .pdf-controls button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .pdf-controls .page-info {
            font-weight: bold;
            color: #333;
        }
        
        .pdf-canvas-container {
            display: flex;
            justify-content: center;
            overflow-x: auto;
            background: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        #pdf-canvas {
            max-width: 100%;
            height: auto;
        }
        
        .pdf-loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .pdf-viewer-container {
                padding: 10px;
            }
            
            .pdf-canvas-container {
                padding: 10px;
            }
            
            .pdf-controls button {
                padding: 8px 15px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo-container">
                <img src="assets/images/logo.png" alt="Ipsis Logo" class="logo">
            </div>
            <h1>Cadastro Realizado com Sucesso!</h1>
        </header>

        <main class="main-content">
            <div class="success-container">
                <div class="success-icon">✓</div>
                <h2>Seu cadastro foi enviado com sucesso!</h2>
                <p>Recebemos suas informações e o termo de aceite assinado. Obrigado!</p>
                
                <?php if (!empty($pdfFile)): ?>
                
                <!-- Visualizador de PDF -->
                <div class="pdf-viewer-container">
                    <h3 style="text-align: center; margin-bottom: 20px;">Seu Código do Cliente Assinado</h3>
                    
                    <div class="pdf-loading" id="pdf-loading">
                        Carregando PDF...
                    </div>
                    
                    <div class="pdf-controls" id="pdf-controls" style="display: none;">
                        <button id="prev-page">← Anterior</button>
                        <span class="page-info">
                            Página <span id="page-num">1</span> de <span id="page-count">-</span>
                        </span>
                        <button id="next-page">Próxima →</button>
                    </div>
                    
                    <div class="pdf-canvas-container" id="pdf-canvas-container" style="display: none;">
                        <canvas id="pdf-canvas"></canvas>
                    </div>
                    
                    <!-- Fallback para mobile que não suporta canvas -->
                    <div id="pdf-embed-fallback" style="display: none;">
                        <iframe 
                            src="download-pdf.php?file=<?php echo urlencode($pdfFile); ?>" 
                            style="width: 100%; height: 600px; border: none; border-radius: 5px;"
                            title="PDF do Código do Cliente">
                        </iframe>
                    </div>
                </div>
                
                <div class="success-actions">
                    <a href="download-pdf.php?file=<?php echo urlencode($pdfFile); ?>" class="btn-primary" download>
                        📄 Baixar PDF
                    </a>
                    
                    <a href="index.php" class="btn-secondary">
                        ← Enviar Novo Cadastro
                    </a>
                </div>
                
                <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
                <script>
                    const pdfUrl = 'download-pdf.php?file=<?php echo urlencode($pdfFile); ?>';
                    const loadingDiv = document.getElementById('pdf-loading');
                    const canvasContainer = document.getElementById('pdf-canvas-container');
                    const controls = document.getElementById('pdf-controls');
                    const embedFallback = document.getElementById('pdf-embed-fallback');
                    
                    // Detectar se é mobile
                    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                    
                    // Tentar usar PDF.js primeiro
                    if (typeof pdfjsLib !== 'undefined') {
                        // Configurar PDF.js
                        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                        
                        let pdfDoc = null;
                        let pageNum = 1;
                        let pageRendering = false;
                        let pageNumPending = null;
                        const scale = isMobile ? 1.2 : 1.5;
                        const canvas = document.getElementById('pdf-canvas');
                        const ctx = canvas.getContext('2d');
                        
                        // Renderizar página
                        function renderPage(num) {
                            pageRendering = true;
                            
                            pdfDoc.getPage(num).then(function(page) {
                                const viewport = page.getViewport({scale: scale});
                                canvas.height = viewport.height;
                                canvas.width = viewport.width;
                                
                                const renderContext = {
                                    canvasContext: ctx,
                                    viewport: viewport
                                };
                                
                                const renderTask = page.render(renderContext);
                                
                                renderTask.promise.then(function() {
                                    pageRendering = false;
                                    
                                    if (pageNumPending !== null) {
                                        renderPage(pageNumPending);
                                        pageNumPending = null;
                                    }
                                });
                            });
                            
                            document.getElementById('page-num').textContent = num;
                        }
                        
                        // Enfileirar renderização
                        function queueRenderPage(num) {
                            if (pageRendering) {
                                pageNumPending = num;
                            } else {
                                renderPage(num);
                            }
                        }
                        
                        // Página anterior
                        function onPrevPage() {
                            if (pageNum <= 1) {
                                return;
                            }
                            pageNum--;
                            queueRenderPage(pageNum);
                            updateButtons();
                        }
                        document.getElementById('prev-page').addEventListener('click', onPrevPage);
                        
                        // Próxima página
                        function onNextPage() {
                            if (pageNum >= pdfDoc.numPages) {
                                return;
                            }
                            pageNum++;
                            queueRenderPage(pageNum);
                            updateButtons();
                        }
                        document.getElementById('next-page').addEventListener('click', onNextPage);
                        
                        // Atualizar botões
                        function updateButtons() {
                            document.getElementById('prev-page').disabled = (pageNum <= 1);
                            document.getElementById('next-page').disabled = (pageNum >= pdfDoc.numPages);
                        }
                        
                        // Carregar PDF
                        pdfjsLib.getDocument(pdfUrl).promise.then(function(pdfDoc_) {
                            pdfDoc = pdfDoc_;
                            document.getElementById('page-count').textContent = pdfDoc.numPages;
                            
                            // Mostrar canvas e controles
                            loadingDiv.style.display = 'none';
                            canvasContainer.style.display = 'flex';
                            controls.style.display = 'flex';
                            
                            renderPage(pageNum);
                            updateButtons();
                        }).catch(function(error) {
                            console.error('Erro ao carregar PDF com PDF.js:', error);
                            // Usar fallback
                            useFallback();
                        });
                    } else {
                        // PDF.js não carregou, usar fallback
                        useFallback();
                    }
                    
                    function useFallback() {
                        loadingDiv.style.display = 'none';
                        embedFallback.style.display = 'block';
                    }
                </script>
                
                <?php endif; ?>
                
                <div class="success-info">
                    <p><strong>Próximos passos:</strong></p>
                    <ul>
                        <li>Seu cadastro foi registrado em nosso sistema</li>
                        <li>Nossa equipe analisará as informações enviadas</li>
                        <li>Guarde o PDF assinado para seus registros</li>
                    </ul>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> Ipsis. Todos os direitos reservados.</p>
            <p><a href="https://ipsis.com.br" target="_blank">www.ipsis.com.br</a></p>
        </footer>
    </div>
</body>
</html>
