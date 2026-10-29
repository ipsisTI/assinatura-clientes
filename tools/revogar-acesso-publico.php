<?php
/**
 * Revogação de acesso público (permissão "anyone") de PDFs no Google Drive
 *
 * Contexto: PDFs enviados antes do hardening podem ter ficado públicos por
 * link no Drive. Este script varre a pasta configurada em GOOGLE_DRIVE_FOLDER_ID
 * (suporta Shared Drives), lista os arquivos que têm permissão type=anyone e,
 * SOMENTE com o argumento --apply, exclui essas permissões.
 *
 * Uso (a partir da raiz do projeto):
 *   php tools/revogar-acesso-publico.php           DRY-RUN (padrão): apenas lista
 *   php tools/revogar-acesso-publico.php --apply   Revoga de fato cada permissão anyone
 *
 * Requisitos: credenciais da Service Account (GOOGLE_CREDENTIALS_PATH) e
 * GOOGLE_DRIVE_FOLDER_ID definidos no .env. Nenhum segredo é embutido aqui.
 */

// Guarda de CLI: este script nunca deve responder a uma requisição web
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Somente via CLI.');
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/config/config.php';

$apply = in_array('--apply', $argv ?? [], true);

if (empty(GOOGLE_DRIVE_FOLDER_ID)) {
    fwrite(STDERR, "GOOGLE_DRIVE_FOLDER_ID nao configurado no .env.\n");
    exit(1);
}

// Cliente montado como em src/services/GoogleDriveService.php (mesmas
// credenciais do .env), porém com escopo Drive completo: listar todos os
// arquivos da pasta e revogar permissões (inclusive em Shared Drives)
// exige mais que o escopo DRIVE_FILE usado no upload.
$client = new Google_Client();
$client->setApplicationName('Ipsis Clientes - Revogacao de Acesso Publico');
$client->setScopes([Google_Service_Drive::DRIVE]);
$client->setAuthConfig(GOOGLE_CREDENTIALS_PATH);
$client->setAccessType('offline');

$drive = new Google_Service_Drive($client);
$folderId = GOOGLE_DRIVE_FOLDER_ID;

echo $apply
    ? "MODO APPLY: as permissoes anyone SERAO revogadas.\n"
    : "DRY-RUN (padrao): apenas listando. Use --apply para revogar.\n";
echo "Destino: {$folderId}\n\n";

// 1) Lista os arquivos da pasta de destino (primeira tentativa: '<ID> in parents')
$optParams = [
    'q' => "'" . $folderId . "' in parents",
    'supportsAllDrives' => true,
    'includeItemsFromAllDrives' => true,
    'fields' => 'nextPageToken, files(id, name)',
    'pageSize' => 1000,
];

$files = [];
do {
    $resposta = $drive->files->listFiles($optParams);
    foreach ($resposta->getFiles() as $f) {
        $files[] = $f;
    }
    $optParams['pageToken'] = $resposta->getNextPageToken();
} while (!empty($optParams['pageToken']));

// 2) Se veio vazio e o ID parece um Shared Drive (prefixo '0A'),
//    repete a busca escopada ao Drive inteiro
if (empty($files) && strpos($folderId, '0A') === 0) {
    echo "Nenhum arquivo via 'in parents'. Repetindo com driveId/corpora=drive (Shared Drive)...\n\n";
    $optParams = [
        'q' => "'" . $folderId . "' in parents",
        'driveId' => $folderId,
        'corpora' => 'drive',
        'supportsAllDrives' => true,
        'includeItemsFromAllDrives' => true,
        'fields' => 'nextPageToken, files(id, name)',
        'pageSize' => 1000,
    ];
    do {
        $resposta = $drive->files->listFiles($optParams);
        foreach ($resposta->getFiles() as $f) {
            $files[] = $f;
        }
        $optParams['pageToken'] = $resposta->getNextPageToken();
    } while (!empty($optParams['pageToken']));
}

if (empty($files)) {
    echo "Nenhum arquivo encontrado no destino configurado.\n";
    exit(0);
}

// 3) Para cada arquivo, verifica se existe permissão pública (type=anyone)
$totalArquivos = count($files);
$permissoesEncontradas = 0;
$permissoesRevogadas = 0;

foreach ($files as $file) {
    try {
        $permissoes = $drive->permissions->listPermissions($file->id, [
            'supportsAllDrives' => true,
        ])->getPermissions();
    } catch (Exception $e) {
        fwrite(STDERR, "Erro ao listar permissoes de '{$file->name}' ({$file->id}): " . $e->getMessage() . "\n");
        continue;
    }

    foreach ($permissoes as $permissao) {
        if ($permissao->type !== 'anyone') {
            continue;
        }

        $permissoesEncontradas++;
        echo "PUBLICO: {$file->name} | arquivo={$file->id} | permissao={$permissao->id}\n";

        if (!$apply) {
            continue; // dry-run: apenas reporta
        }

        try {
            $drive->permissions->delete($file->id, $permissao->id, [
                'supportsAllDrives' => true,
            ]);
            $permissoesRevogadas++;
            echo "  -> Permissao anyone revogada.\n";
        } catch (Exception $e) {
            fwrite(STDERR, "  -> FALHA ao revogar: " . $e->getMessage() . "\n");
        }
    }
}

echo "\nResumo: {$totalArquivos} arquivo(s) verificado(s), {$permissoesEncontradas} permissao(oes) 'anyone' encontrada(s)";
echo $apply
    ? ", {$permissoesRevogadas} revogada(s).\n"
    : " (nenhuma revogada: dry-run).\n";
