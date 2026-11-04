/*
 * Viewer da página /guia (Guia do Visitante — só visualização): pdf.js,
 * todas as páginas em sequência ajustadas à largura do container, com
 * botões A−/A+ (0,6×–3×). Mesmo padrão do pdf-viewer.js da página de
 * sucesso (sem script inline: a URL do PDF chega em data-src de
 * #guiaViewerConfig), acrescido de zoom e render nítido em telas retina
 * (devicePixelRatio limitado a 2 — acima disso só custa memória).
 */
(function () {
    'use strict';

    var configEl = document.getElementById('guiaViewerConfig');
    var loading = document.getElementById('guiaLoading');
    var scroll = document.getElementById('guiaScroll');
    var pages = document.getElementById('guiaPaginas');
    var btnMenos = document.getElementById('guiaZoomMenos');
    var btnMais = document.getElementById('guiaZoomMais');
    var valorEl = document.getElementById('guiaZoomValor');

    if (!configEl || !loading || !scroll || !pages || typeof pdfjsLib === 'undefined') {
        if (loading) {
            loading.textContent = 'Não foi possível exibir o documento aqui — use o botão de download abaixo.';
        }
        return;
    }

    var src = configEl.dataset.src || '';
    if (!src) {
        loading.textContent = 'Não foi possível exibir o documento aqui — use o botão de download abaixo.';
        return;
    }

    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    var ZOOM_MIN = 0.6;
    var ZOOM_MAX = 3;
    var ZOOM_PASSO = 0.2;
    var zoom = 1;
    var dpr = Math.min(window.devicePixelRatio || 1, 2);

    function atualizarControles() {
        if (valorEl) {
            valorEl.textContent = Math.round(zoom * 100) + '%';
        }
        if (btnMenos) {
            btnMenos.disabled = zoom <= ZOOM_MIN + 0.001;
        }
        if (btnMais) {
            btnMais.disabled = zoom >= ZOOM_MAX - 0.001;
        }
    }

    var docPromise = pdfjsLib.getDocument(src).promise;
    var geracao = 0; // invalida a cadeia anterior quando um zoom/resize chega no meio

    function desenhar() {
        geracao += 1;
        var minha = geracao;

        // Revela o container ANTES de medir a largura: com ele escondido,
        // clientWidth é 0 e as páginas saem com largura 0.
        loading.hidden = true;
        scroll.hidden = false;
        pages.innerHTML = '';

        return docPromise.then(function (doc) {
            if (minha !== geracao) {
                return null;
            }

            // Largura útil = clientWidth menos o padding (2×16) do container.
            var larguraUtil = Math.max(scroll.clientWidth - 36, 280);

            // Renderiza em cadeia (uma página por vez, na ordem)
            var corrente = Promise.resolve();
            for (var pageNum = 1; pageNum <= doc.numPages; pageNum++) {
                corrente = corrente.then(function (n) {
                    if (minha !== geracao) {
                        return null;
                    }
                    return doc.getPage(n).then(function (page) {
                        var viewport1 = page.getViewport({ scale: 1 });
                        var largura = larguraUtil * zoom;
                        var escala = largura / viewport1.width;
                        var viewport = page.getViewport({ scale: escala * dpr });

                        var canvas = document.createElement('canvas');
                        canvas.className = 'guia-pagina';
                        canvas.width = Math.floor(viewport.width);
                        canvas.height = Math.floor(viewport.height);
                        // Tamanho de exibição em px CSS; o buffer interno sai
                        // multiplicado pelo dpr p/ ficar nítido em retina.
                        canvas.style.width = Math.round(largura) + 'px';
                        canvas.style.height = Math.round(viewport1.height * escala) + 'px';
                        pages.appendChild(canvas);

                        return page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
                    });
                }.bind(null, pageNum));
            }
            return corrente;
        });
    }

    function falha(error) {
        console.error('Erro ao renderizar PDF:', error);
        scroll.hidden = true;
        loading.hidden = false;
        loading.textContent = 'Não foi possível exibir o documento aqui — use o botão de download abaixo.';
    }

    function redimensionar() {
        desenhar().catch(falha);
    }

    if (btnMenos) {
        btnMenos.addEventListener('click', function () {
            zoom = Math.max(ZOOM_MIN, zoom - ZOOM_PASSO);
            atualizarControles();
            redimensionar();
        });
    }
    if (btnMais) {
        btnMais.addEventListener('click', function () {
            zoom = Math.min(ZOOM_MAX, zoom + ZOOM_PASSO);
            atualizarControles();
            redimensionar();
        });
    }

    atualizarControles();
    desenhar().catch(falha);

    // Rotação de tela / redimensionamento: re-renderiza na nova largura.
    var redesenhoTimer = null;
    window.addEventListener('resize', function () {
        window.clearTimeout(redesenhoTimer);
        redesenhoTimer = window.setTimeout(redimensionar, 250);
    });
})();
