<?php
require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/utils/helpers.php';

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Clientes - Ipsis</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="apple-touch-icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo RECAPTCHA_SITE_KEY; ?>"></script>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo-container">
                <img src="assets/images/logo.png" alt="Ipsis Logo" class="logo">
            </div>
            <h1>Cadastro de Clientes</h1>
            <p class="subtitle">Preencha o formulário abaixo para se cadastrar como cliente da Ipsis</p>
        </header>

        <main class="main-content">
            <form id="clienteForm" class="form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="recaptcha_token" id="recaptchaToken">
                <input type="hidden" name="assinatura_tipo" id="assinaturaTipo" value="canvas">
                <input type="hidden" name="assinatura" id="assinatura">
                
                <!-- Dados Pessoais -->
                <section class="form-section">
                    <h2 class="section-title">Dados Pessoais</h2>
                    
                    <div class="form-group">
                        <label for="nome_completo" class="required">Nome Completo</label>
                        <input type="text" id="nome_completo" name="nome_completo" required>
                        <span class="error-message" id="error-nome_completo"></span>
                    </div>

                    <div class="form-group">
                        <label for="razao_social">Razão Social</label>
                        <input type="text" id="razao_social" name="razao_social">
                        <span class="error-message" id="error-razao_social"></span>
                    </div>

                    <div class="form-group">
                        <label for="cnpj" class="required">CNPJ/CPF</label>
                        <input type="text" id="cnpj" name="cnpj" placeholder="000.000.000-00 ou 00.000.000/0000-00" required>
                        <span class="error-message" id="error-cnpj"></span>
                    </div>

                    <div class="form-group">
                        <label for="protetor_auricular" class="required">Recebeu protetor auricular?</label>
                        <select id="protetor_auricular" name="protetor_auricular" required>
                            <option value="">Selecione...</option>
                            <option value="Sim">Sim</option>
                            <option value="Não">Não</option>
                        </select>
                        <span class="error-message" id="error-protetor_auricular"></span>
                    </div>
                </section>

                <!-- Contato (COMENTADO)
                <section class="form-section">
                    <h2 class="section-title">Contato</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="telefone" class="required">Telefone</label>
                            <input type="tel" id="telefone" name="telefone" placeholder="(00) 00000-0000" required>
                            <span class="error-message" id="error-telefone"></span>
                        </div>

                        <div class="form-group">
                            <label for="email" class="required">Email</label>
                            <input type="email" id="email" name="email" required>
                            <span class="error-message" id="error-email"></span>
                        </div>
                    </div>
                </section>
                -->

                <!-- Segmento (COMENTADO)
                <section class="form-section">
                    <h2 class="section-title">Segmento</h2>
                    
                    <div class="form-group">
                        <label for="segmento" class="required">Segmento de Atuação</label>
                        <select id="segmento" name="segmento" required>
                            <option value="">Selecione...</option>
                            <option value="Tecnologia">Tecnologia</option>
                            <option value="Saúde">Saúde</option>
                            <option value="Educação">Educação</option>
                            <option value="Varejo">Varejo</option>
                            <option value="Indústria">Indústria</option>
                            <option value="Serviços">Serviços</option>
                            <option value="Financeiro">Financeiro</option>
                            <option value="Outros">Outros</option>
                        </select>
                        <span class="error-message" id="error-segmento"></span>
                    </div>
                </section>
                -->

                <!-- Documentos para leitura -->
                <section class="form-section">
                    <h2 class="section-title">Código do Cliente e Guia do Visitante</h2>
                    <p class="section-description">Por favor, leia os documentos completos abaixo navegando entre as páginas.</p>
                    
                    <div class="pdf-viewer-container">
                        <div class="pdf-loading" id="pdfLoading">
                            Carregando documento...
                        </div>
                        
                        <div class="pdf-header" id="pdfHeader" style="display: none;">
                            <div class="pdf-info">
                                <span class="page-info">
                                    📄 Documento com <span id="pageCount">-</span> páginas
                                </span>
                            </div>
                            <div class="pdf-zoom-controls">
                                <button type="button" id="zoomOut" class="zoom-btn" title="Diminuir zoom">-</button>
                                <span id="zoomLevel">100%</span>
                                <button type="button" id="zoomIn" class="zoom-btn" title="Aumentar zoom">+</button>
                                <button type="button" id="zoomReset" class="zoom-btn-reset" title="Resetar zoom">↻</button>
                            </div>
                        </div>
                        
                        <div class="pdf-scroll-container" id="pdfScrollContainer" style="display: none;">
                            <div id="pdfPagesContainer"></div>
                        </div>
                        
                        <div class="pdf-scroll-indicator" id="scrollIndicator" style="display: none;">
                            <span>⬇️ Role até o final do documento para continuar</span>
                        </div>
                    </div>
                    
                    <div class="pdf-confirm-container" id="pdfConfirmContainer" style="display: none;">
                        <button type="button" class="btn-confirm-read" id="btnConfirmRead">
                            ✓ Li todo o documento e desejo continuar
                        </button>
                    </div>
                    
                    <div class="form-group" id="termosGroup" style="display: none;">
                        <label class="checkbox-container">
                            <input type="checkbox" id="termos_aceitos" name="termos_aceitos" required>
                            <span class="checkmark"></span>
                            <span class="checkbox-label">Li e concordo com o Código do Cliente e o Guia do Visitante</span>
                        </label>
                        <span class="error-message" id="error-termos_aceitos"></span>
                    </div>
                </section>

                <!-- Assinatura Digital -->
                <section class="form-section">
                    <h2 class="section-title">Assinatura Digital</h2>
                    
                    <div class="signature-tabs">
                        <button type="button" class="tab-button active" data-tab="canvas">Desenhar</button>
                        <button type="button" class="tab-button" data-tab="typed">Digitar</button>
                    </div>

                    <div class="signature-content">
                        <!-- Canvas para desenhar -->
                        <div class="signature-tab active" id="tab-canvas">
                            <canvas id="signatureCanvas" width="600" height="200"></canvas>
                            <button type="button" class="btn-secondary" id="clearCanvas">Limpar</button>
                        </div>

                        <!-- Input para digitar -->
                        <div class="signature-tab" id="tab-typed">
                            <input type="text" id="signatureTyped" placeholder="Digite seu nome completo">
                            <div class="signature-preview" id="signaturePreview"></div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="assinatura" id="assinatura">
                    <span class="error-message" id="error-assinatura"></span>
                </section>

                <!-- Botões -->
                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="submitBtn">
                        <span class="btn-text">Enviar Cadastro</span>
                        <span class="btn-loader" style="display: none;">Enviando...</span>
                    </button>
                </div>
            </form>

            <!-- Mensagem de sucesso/erro -->
            <div id="formMessage" class="form-message" style="display: none;"></div>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> Ipsis. Todos os direitos reservados.</p>
            <p><a href="https://ipsis.com.br" target="_blank">www.ipsis.com.br</a></p>
        </footer>
    </div>

    <script>
        const RECAPTCHA_SITE_KEY = '<?php echo RECAPTCHA_SITE_KEY; ?>';
    </script>
    <script src="assets/js/form.js"></script>
</body>
</html>
