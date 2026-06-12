/**
 * Form Handler - Cadastro de Clientes Ipsis
 */

// Estado da aplicação
const state = {
    isDrawing: false,
    signatureType: 'canvas',
    canvas: null,
    ctx: null
};

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    initializeCanvas();
    initializeMasks();
    initializeSignatureTabs();
    initializeForm();
});

/**
 * Inicializa canvas de assinatura
 */
function initializeCanvas() {
    state.canvas = document.getElementById('signatureCanvas');
    state.ctx = state.canvas.getContext('2d');
    
    // Configurar canvas
    state.ctx.strokeStyle = '#0066cc';
    state.ctx.lineWidth = 2;
    state.ctx.lineCap = 'round';
    state.ctx.lineJoin = 'round';
    
    // Eventos de desenho
    state.canvas.addEventListener('mousedown', startDrawing);
    state.canvas.addEventListener('mousemove', draw);
    state.canvas.addEventListener('mouseup', stopDrawing);
    state.canvas.addEventListener('mouseout', stopDrawing);
    
    // Eventos touch para mobile
    state.canvas.addEventListener('touchstart', handleTouchStart);
    state.canvas.addEventListener('touchmove', handleTouchMove);
    state.canvas.addEventListener('touchend', stopDrawing);
    
    // Botão limpar
    document.getElementById('clearCanvas').addEventListener('click', clearCanvas);
}

/**
 * Inicializa máscaras de input
 */
function initializeMasks() {
    // Máscara CNPJ/CPF
    const cnpjInput = document.getElementById('cnpj');
    
    function formatCpfCnpj(value) {
        // Remove tudo que não é número
        value = value.replace(/\D/g, '');
        
        // Limita a 14 dígitos (CNPJ)
        value = value.substring(0, 14);
        
        if (value.length <= 11) {
            // CPF: 000.000.000-00
            if (value.length > 9) {
                value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{1,2})$/, '$1.$2.$3-$4');
            } else if (value.length > 6) {
                value = value.replace(/^(\d{3})(\d{3})(\d{1,3})$/, '$1.$2.$3');
            } else if (value.length > 3) {
                value = value.replace(/^(\d{3})(\d{1,3})$/, '$1.$2');
            }
        } else {
            // CNPJ: 00.000.000/0000-00
            value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2})$/, '$1.$2.$3/$4-$5');
        }
        
        return value;
    }
    
    cnpjInput.addEventListener('input', function(e) {
        const cursorPos = e.target.selectionStart;
        const oldLength = e.target.value.length;
        
        e.target.value = formatCpfCnpj(e.target.value);
        
        // Ajusta posição do cursor
        const newLength = e.target.value.length;
        const diff = newLength - oldLength;
        e.target.setSelectionRange(cursorPos + diff, cursorPos + diff);
    });
    
    /* COMENTADO - Máscara de telefone não mais utilizada
    // Máscara Telefone
    const telefoneInput = document.getElementById('telefone');
    telefoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 10) {
            value = value.replace(/^(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
        } else {
            value = value.replace(/^(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
        }
        e.target.value = value;
    });
    */
}

/**
 * Inicializa tabs de assinatura
 */
function initializeSignatureTabs() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const signatureTyped = document.getElementById('signatureTyped');
    const signaturePreview = document.getElementById('signaturePreview');
    const assinaturaTipoInput = document.getElementById('assinaturaTipo');
    
    // Definir tipo inicial como canvas
    state.signatureType = 'canvas';
    assinaturaTipoInput.value = 'canvas';
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.dataset.tab;
            
            // Atualizar botões
            tabButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Atualizar conteúdo
            document.querySelectorAll('.signature-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(`tab-${tabName}`).classList.add('active');
            
            // Atualizar tipo
            state.signatureType = tabName;
            assinaturaTipoInput.value = tabName;
        });
    });
    
    // Preview da assinatura digitada
    signatureTyped.addEventListener('input', function(e) {
        signaturePreview.textContent = e.target.value || '';
    });
}

/**
 * Inicializa formulário
 */
function initializeForm() {
    const form = document.getElementById('clienteForm');
    
    // Inicializar visualizador de PDF
    initializePDFViewer();
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        console.log('Formulário submetido');
        
        // Limpar erros anteriores
        clearErrors();
        
        // Validar termos
        const termosAceitos = document.getElementById('termos_aceitos');
        console.log('Termos aceitos:', termosAceitos.checked);
        if (!termosAceitos.checked) {
            showError('termos_aceitos', 'Você deve ler e aceitar o Código do Cliente');
            return;
        }
        
        // Validar assinatura
        console.log('Validando assinatura...');
        if (!validateSignature()) {
            showError('assinatura', 'Assinatura é obrigatória');
            return;
        }
        console.log('Assinatura válida');
        
        // Obter token reCAPTCHA
        console.log('Obtendo token reCAPTCHA...');
        try {
            const token = await getRecaptchaToken();
            document.getElementById('recaptchaToken').value = token;
        } catch (error) {
            showMessage('Erro ao validar reCAPTCHA. Tente novamente.', 'error');
            return;
        }
        
        // Enviar formulário
        await submitForm(form);
    });
}

/**
 * Inicializa visualizador de PDF
 */
function initializePDFViewer() {
    const btnConfirmRead = document.getElementById('btnConfirmRead');
    const termosGroup = document.getElementById('termosGroup');
    const pdfConfirmContainer = document.getElementById('pdfConfirmContainer');
    const scrollIndicator = document.getElementById('scrollIndicator');
    
    // Quando o usuário clicar no botão confirmando que leu
    btnConfirmRead.addEventListener('click', function() {
        // Esconder botão de confirmação
        pdfConfirmContainer.style.display = 'none';
        
        // Mostrar checkbox de aceite
        termosGroup.style.display = 'block';
        
        // Atualizar indicador
        scrollIndicator.innerHTML = '<span style="color: #28a745;">✓ Documento visualizado - aceite os termos abaixo</span>';
        
        // Scroll suave até o checkbox
        termosGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

/**
 * Funções de desenho no canvas
 */
function startDrawing(e) {
    state.isDrawing = true;
    const rect = state.canvas.getBoundingClientRect();
    state.ctx.beginPath();
    state.ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
}

function draw(e) {
    if (!state.isDrawing) return;
    const rect = state.canvas.getBoundingClientRect();
    state.ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
    state.ctx.stroke();
}

function stopDrawing() {
    state.isDrawing = false;
}

function handleTouchStart(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent('mousedown', {
        clientX: touch.clientX,
        clientY: touch.clientY
    });
    state.canvas.dispatchEvent(mouseEvent);
}

function handleTouchMove(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent('mousemove', {
        clientX: touch.clientX,
        clientY: touch.clientY
    });
    state.canvas.dispatchEvent(mouseEvent);
}

function clearCanvas() {
    state.ctx.clearRect(0, 0, state.canvas.width, state.canvas.height);
}

/**
 * Valida assinatura
 */
function validateSignature() {
    const assinaturaInput = document.getElementById('assinatura');
    
    if (state.signatureType === 'canvas') {
        // Verificar se canvas não está vazio
        const imageData = state.ctx.getImageData(0, 0, state.canvas.width, state.canvas.height);
        const isEmpty = !imageData.data.some(channel => channel !== 0);
        
        if (isEmpty) {
            return false;
        }
        
        // Salvar como base64
        const base64Data = state.canvas.toDataURL('image/png');
        assinaturaInput.value = base64Data;
        console.log('Assinatura canvas salva, tamanho:', base64Data.length);
    } else {
        // Assinatura digitada
        const typedSignature = document.getElementById('signatureTyped').value.trim();
        
        if (!typedSignature) {
            return false;
        }
        
        assinaturaInput.value = typedSignature;
        console.log('Assinatura digitada salva:', typedSignature);
    }
    
    console.log('Valor final do campo assinatura:', assinaturaInput.value.substring(0, 50));
    return true;
}

/**
 * Obtém token reCAPTCHA
 */
function getRecaptchaToken() {
    return new Promise((resolve, reject) => {
        grecaptcha.ready(function() {
            grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'submit' })
                .then(resolve)
                .catch(reject);
        });
    });
}

/**
 * Envia formulário
 */
async function submitForm(form) {
    const submitBtn = document.getElementById('submitBtn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoader = submitBtn.querySelector('.btn-loader');
    
    // Desabilitar botão
    submitBtn.disabled = true;
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline-block';
    
    try {
        const formData = new FormData(form);
        
        // Garantir que a assinatura seja enviada
        const assinaturaValue = document.getElementById('assinatura').value;
        const assinaturaTipoValue = document.getElementById('assinaturaTipo').value;
        
        // Remover e adicionar novamente para garantir
        formData.delete('assinatura');
        formData.delete('assinatura_tipo');
        formData.append('assinatura', assinaturaValue);
        formData.append('assinatura_tipo', assinaturaTipoValue);
        
        console.log('=== ENVIANDO ===');
        console.log('Razao Social:', formData.get('razao_social'));
        console.log('CNPJ:', formData.get('cnpj'));
        console.log('Assinatura tipo:', formData.get('assinatura_tipo'));
        console.log('Assinatura tamanho:', formData.get('assinatura')?.length || 0);
        
        const response = await fetch('submit-form.php', {
            method: 'POST',
            body: formData
        });
        
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Erro do servidor:', errorText);
            throw new Error('Erro do servidor: ' + response.status);
        }
        
        const result = await response.json();
        console.log('Resultado:', result);
        
        if (result.success) {
            // Redirecionar para página de sucesso
            if (result.pdf_assinado) {
                window.location.href = 'sucesso.php?pdf=' + encodeURIComponent(result.pdf_assinado);
            } else {
                window.location.href = 'sucesso.php';
            }
        } else {
            if (result.errors) {
                // Mostrar erros de validação
                Object.keys(result.errors).forEach(field => {
                    showError(field, result.errors[field]);
                });
            }
            showMessage(result.message, 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showMessage('Erro ao enviar formulário. Tente novamente.', 'error');
    } finally {
        // Reabilitar botão
        submitBtn.disabled = false;
        btnText.style.display = 'inline';
        btnLoader.style.display = 'none';
    }
}

/**
 * Mostra erro em campo específico
 */
function showError(field, message) {
    const errorElement = document.getElementById(`error-${field}`);
    const formGroup = errorElement?.closest('.form-group');
    
    if (errorElement) {
        errorElement.textContent = message;
    }
    
    if (formGroup) {
        formGroup.classList.add('error');
    }
}

/**
 * Limpa todos os erros
 */
function clearErrors() {
    document.querySelectorAll('.error-message').forEach(el => {
        el.textContent = '';
    });
    
    document.querySelectorAll('.form-group').forEach(el => {
        el.classList.remove('error');
    });
}

/**
 * Mostra mensagem de sucesso/erro
 */
function showMessage(message, type) {
    const messageElement = document.getElementById('formMessage');
    messageElement.innerHTML = message;
    messageElement.className = `form-message ${type}`;
    messageElement.style.display = 'block';
    
    // Auto-hide após 30 segundos
    setTimeout(() => {
        messageElement.style.display = 'none';
    }, 30000);
}


/**
 * ===================================
 * PDF Viewer com PDF.js - Scroll Contínuo + Zoom
 * ===================================
 */
let pdfDocs = [];
let hasScrolledToBottom = false;
let currentZoom = 1.0;

// Configurar PDF.js
if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    
    // Elementos
    // Documentos exibidos no visualizador, na ordem de leitura.
    // Para adicionar um novo documento, basta incluir o caminho do PDF aqui.
    const pdfUrls = ['doc/CodigoCliente.pdf', 'doc/GuiaVisitante.pdf'];
    const loadingDiv = document.getElementById('pdfLoading');
    const pdfHeader = document.getElementById('pdfHeader');
    const scrollContainer = document.getElementById('pdfScrollContainer');
    const pagesContainer = document.getElementById('pdfPagesContainer');
    const scrollIndicator = document.getElementById('scrollIndicator');
    const zoomLevelSpan = document.getElementById('zoomLevel');
    
    // Detectar se é mobile
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    const baseScale = isMobile ? 2.0 : 1.0;
    
    /**
     * Renderizar uma página específica de um documento
     */
    function renderPage(doc, pageNumber, scale) {
        return doc.getPage(pageNumber).then(function(page) {
            // Calcular escala baseada na largura FIXA do container
            const containerWidth = scrollContainer.clientWidth - 40;
            const viewport = page.getViewport({scale: 1});
            const scaleToFit = containerWidth / viewport.width;
            const finalScale = scale * scaleToFit;
            
            const scaledViewport = page.getViewport({scale: finalScale});
            
            // Criar canvas para esta página
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.height = scaledViewport.height;
            canvas.width = scaledViewport.width;
            canvas.className = 'pdf-page';
            canvas.dataset.page = pageNumber;
            
            pagesContainer.appendChild(canvas);
            
            const renderContext = {
                canvasContext: ctx,
                viewport: scaledViewport
            };
            
            return page.render(renderContext).promise;
        });
    }
    
    /**
     * Renderizar todas as páginas de todos os documentos
     */
    async function renderAllPages(scale) {
        for (const doc of pdfDocs) {
            for (let pageNum = 1; pageNum <= doc.numPages; pageNum++) {
                await renderPage(doc, pageNum, scale);
            }
        }

        console.log('Todas as páginas renderizadas com escala:', scale);
    }
    
    /**
     * Re-renderizar com novo zoom
     */
    async function reRenderWithZoom() {
        // Salvar posição do scroll
        const scrollPercentage = scrollContainer.scrollTop / scrollContainer.scrollHeight;
        
        // Limpar páginas existentes
        pagesContainer.innerHTML = '';
        
        // Calcular nova escala
        const newScale = baseScale * currentZoom;
        
        // Renderizar novamente
        await renderAllPages(newScale);
        
        // Restaurar posição do scroll
        setTimeout(() => {
            scrollContainer.scrollTop = scrollContainer.scrollHeight * scrollPercentage;
        }, 100);
    }
    
    /**
     * Atualizar display do zoom
     */
    function updateZoomDisplay() {
        const percentage = Math.round(currentZoom * 100);
        zoomLevelSpan.textContent = percentage + '%';
    }
    
    /**
     * Zoom In
     */
    document.getElementById('zoomIn').addEventListener('click', function() {
        if (currentZoom < 2.0) {
            currentZoom += 0.2;
            updateZoomDisplay();
            reRenderWithZoom();
        }
    });
    
    /**
     * Zoom Out
     */
    document.getElementById('zoomOut').addEventListener('click', function() {
        if (currentZoom > 0.6) {
            currentZoom -= 0.2;
            updateZoomDisplay();
            reRenderWithZoom();
        }
    });
    
    /**
     * Reset Zoom
     */
    document.getElementById('zoomReset').addEventListener('click', function() {
        currentZoom = 1.0;
        updateZoomDisplay();
        reRenderWithZoom();
    });
    
    /**
     * Detectar scroll até o final
     */
    function checkScrollPosition() {
        const scrollTop = scrollContainer.scrollTop;
        const scrollHeight = scrollContainer.scrollHeight;
        const clientHeight = scrollContainer.clientHeight;
        
        // Verificar se chegou perto do final (90%)
        const scrollPercentage = (scrollTop + clientHeight) / scrollHeight;
        
        if (scrollPercentage >= 0.9 && !hasScrolledToBottom) {
            hasScrolledToBottom = true;
            scrollIndicator.style.display = 'none';
            document.getElementById('pdfConfirmContainer').style.display = 'block';
            
            // Scroll suave até o botão
            setTimeout(() => {
                document.getElementById('pdfConfirmContainer').scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }, 300);
        }
    }
    
    /**
     * Confirmar leitura do documento
     */
    document.getElementById('btnConfirmRead').addEventListener('click', function() {
        if (!hasScrolledToBottom) {
            alert('Por favor, role até o final do documento antes de continuar.');
            return;
        }
        
        // Mostrar checkbox de aceite
        document.getElementById('termosGroup').style.display = 'block';
        document.getElementById('pdfConfirmContainer').style.display = 'none';
        
        // Scroll suave até o checkbox
        document.getElementById('termosGroup').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        });
    });
    
    /**
     * Carregar e renderizar os documentos
     */
    Promise.all(pdfUrls.map(function(url) {
        return pdfjsLib.getDocument(url).promise;
    })).then(function(docs) {
        pdfDocs = docs;

        // Total de páginas somando todos os documentos
        const totalPages = pdfDocs.reduce(function(soma, doc) {
            return soma + doc.numPages;
        }, 0);
        document.getElementById('pageCount').textContent = totalPages;
        
        // Esconder loading e mostrar PDF
        loadingDiv.style.display = 'none';
        pdfHeader.style.display = 'flex';
        scrollContainer.style.display = 'block';
        scrollIndicator.style.display = 'block';
        
        // Atualizar display do zoom
        updateZoomDisplay();
        
        // Renderizar todas as páginas
        renderAllPages(baseScale * currentZoom).then(() => {
            // Adicionar listener de scroll
            scrollContainer.addEventListener('scroll', checkScrollPosition);
            
            // Verificar posição inicial
            checkScrollPosition();
        });
        
    }).catch(function(error) {
        console.error('Erro ao carregar PDF:', error);
        loadingDiv.innerHTML = 'Erro ao carregar o documento. <a href="' + pdfUrls[0] + '" target="_blank">Clique aqui para abrir em nova aba</a>';
    });
}
