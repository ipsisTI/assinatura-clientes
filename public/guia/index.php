<?php

declare(strict_types=1);

/*
 * Página pública de visualização do Guia do Visitante da Matriz — slug /guia,
 * destino do QR code impresso pela recepção. Puramente leitura: sem
 * formulário e sem assinatura (o fluxo de aceite do Código do Cliente
 * continua na raiz do site).
 *
 * Autocontida: não requer bootstrap do app nem sessão — apenas arquivos
 * estáticos deste diretório e de /assets. Sem script inline: a URL do PDF
 * viaja em data-src de #guiaViewerConfig, lida por assets/js/guia-viewer.js.
 * Compatível com PHP 8.0 (servidor em upgrade pendente para 8.4).
 */
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Guia do Visitante — Ipsis</title>
<link rel="stylesheet" href="/assets/css/guia.css">
<style>:root { --guia-acento: #0066CC; }</style>
</head>
<body>

<header class="guia-cabecalho">
    <img src="/assets/images/logo.png" alt="Ipsis">
    <div>
        <h1>Guia do Visitante</h1>
        <p>Ipsis — Unidade Matriz</p>
    </div>
</header>

<main class="guia-corpo">
    <div class="guia-controles">
        <span class="guia-doc">Documento de leitura — nenhuma assinatura é necessária nesta página.</span>
        <div class="guia-zoom" role="group" aria-label="Zoom do documento">
            <button type="button" id="guiaZoomMenos" aria-label="Diminuir o zoom">&#8722;</button>
            <span id="guiaZoomValor" aria-live="polite">100%</span>
            <button type="button" id="guiaZoomMais" aria-label="Aumentar o zoom">+</button>
        </div>
        <a class="guia-baixar" href="/doc/GuiaVisitanteMatriz2026.pdf" download="guia-do-visitante-ipsis-matriz.pdf">Baixar PDF</a>
    </div>

    <p id="guiaLoading" class="guia-aviso">Carregando o guia…</p>
    <div id="guiaScroll" class="guia-scroll" hidden>
        <div id="guiaPaginas" class="guia-paginas"></div>
    </div>
    <noscript>
        <p class="guia-aviso">Para exibir o guia aqui é preciso JavaScript. <a href="/doc/GuiaVisitanteMatriz2026.pdf">Abra o PDF diretamente</a>.</p>
    </noscript>
</main>

<footer class="guia-rodape">
    <p>Documento oficial Ipsis — Unidade Matriz.</p>
    <p>Cliente Ipsis? O <a href="/">Código do Cliente</a> fica na página inicial.</p>
</footer>

<div id="guiaViewerConfig" data-src="/doc/GuiaVisitanteMatriz2026.pdf"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="/assets/js/guia-viewer.js"></script>
</body>
</html>
