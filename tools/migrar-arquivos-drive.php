<?php
/**
 * Migração retroativa de PDFs entre pastas do Google Drive
 *
 * Contexto: troca da pasta/drive de destino dos PDFs assinados. Este script
 * move TODOS os arquivos da pasta de ORIGEM informada para a pasta de destino
 * configurada em GOOGLE_DRIVE_FOLDER_ID (.env), preservando o ID de cada
 * arquivo — os links =HYPERLINK já registrados na planilha continuam válidos.
 *
 * Uso (a partir da raiz do projeto):
 *   php tools/migrar-arquivos-drive.php --origem=<ID_ANTIGO>           DRY-RUN: apenas lista
 *   php tools/migrar-arquivos-drive.php --origem=<ID_ANTIGO> --apply   Move de fato
 *
 * Observações:
 * - A origem pode ser uma pasta comum ou um Shared Drive (ID com prefixo '0A').
 * - O destino é SEMPRE o GOOGLE_DRIVE_FOLDER_ID atual do .env.
 * - Arquivos movidos SAEM de cima do dono atual: ao sair de um Shared Drive,
 *   o dono passa a ser a Service Account (cota de 15 GB dela). Se preferir
 *   que o dono seja uma pessoa, mova via interface (arrastar e soltar), que
 *   também preserva os IDs.
 * - Requisitos: GOOGLE_CREDENTIALS_PATH e GOOGLE_DRIVE_FOLDER_ID no .env.
 *   Nenhum segredo é embutido aqui.
 */

// Guarda de CLI: este script nunca deve responder a uma requisição web
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Somente via CLI.');
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/config/config.php';

$apply = in_array('--apply', $argv ?? [], true);
$origem = '';
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--origem=') === 0) {
        $origem = substr($arg, strlen('--origem='));
    }
}

if (empty(GOOGLE_DRIVE_FOLDER_ID)) {
    fwrite(STDERR, "GOOGLE_DRIVE_FOLDER_ID nao configurado no .env (esse e o DESTINO da migracao).\n");
    exit(1);
}
if ($origem === '') {
    fwrite(STDERR, "Informe a origem: php tools/migrar-arquivos-drive.php --origem=<ID_ANTIGO> [--apply]\n");
    exit(1);
}
$destino = GOOGLE_DRIVE_FOLDER_ID;
if ($origem === $destino) {
    fwrite(STDERR, "Origem e destino sao o mesmo ID. Nada a fazer.\n");
    exit(1);
}

// Cliente montado como em tools/revogar-acesso-publico.php: escopo Drive
// completo, pois listar/mover arquivos (inclusive em Shared Drives) exige
// mais que o escopo DRIVE_FILE usado no upload.
$client = new Google_Client();
$client->setApplicationName('Ipsis - Migracao de Pastas do Drive');
$client->setScopes([Google_Service_Drive::DRIVE]);
$client->setAuthConfig(GOOGLE_CREDENTIALS_PATH);
$client->setAccessType('offline');

$drive = new Google_Service_Drive($client);

// 0) Confere se o destino é acessível com estas credenciais
try {
    $drive->files->get($destino, ['supportsAllDrives' => true, 'fields' => 'id, name']);
} catch (Exception $e) {
    $clientEmail = '';
    try {
        $cred = json_decode((string) file_get_contents(GOOGLE_CREDENTIALS_PATH), true);
        $clientEmail = $cred['client_email'] ?? '';
    } catch (Exception $ignore) {
    }
    fwrite(STDERR, "DESTINO inacessivel ({$destino}): " . $e->getMessage() . "\n");
    if ($clientEmail !== '') {
        fwrite(STDERR, "Compartilhe a pasta de destino com a Service Account (papel de Editor): {$clientEmail}\n");
    }
    exit(1);
}

echo $apply
    ? "MODO APPLY: os arquivos SERAO movidos.\n"
    : "DRY-RUN (padrao): apenas listando. Use --apply para mover.\n";
echo "Origem:  {$origem}\n";
echo "Destino: {$destino}\n\n";

// 1) Lista os arquivos da origem (primeira tentativa: '<ID> in parents')
function listarArquivos(Google_Service_Drive $drive, $folderId)
{
    $optParams = [
        'q' => "'" . $folderId . "' in parents and trashed = false",
        'supportsAllDrives' => true,
        'includeItemsFromAllDrives' => true,
        'fields' => 'nextPageToken, files(id, name, mimeType)',
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

    // Se veio vazio e o ID parece um Shared Drive (prefixo '0A'),
    // repete a busca escopada ao Drive inteiro
    if (empty($files) && strpos($folderId, '0A') === 0) {
        $optParams = [
            'q' => "'" . $folderId . "' in parents and trashed = false",
            'driveId' => $folderId,
            'corpora' => 'drive',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
            'fields' => 'nextPageToken, files(id, name, mimeType)',
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

    return $files;
}

$files = listarArquivos($drive, $origem);

if (empty($files)) {
    echo "Nenhum arquivo encontrado na origem.\n";
    exit(0);
}

// 2) Move (ou apenas lista, em dry-run) cada arquivo
$total = count($files);
$movid = 0;
$falhas = 0;

foreach ($files as $file) {
    $tipo = $file->mimeType === 'application/vnd.google-apps.folder' ? 'pasta' : 'arquivo';
    echo "[{$tipo}] {$file->name} ({$file->id})\n";

    if (!$apply) {
        continue; // dry-run: apenas reporta
    }

    try {
        // addParents/removeParents preserva o ID do arquivo: links existentes
        // (planilha, e-mails antigos) continuam apontando para o mesmo item
        $drive->files->update($file->id, new Google_Service_Drive_DriveFile(), [
            'addParents' => $destino,
            'removeParents' => $origem,
            'supportsAllDrives' => true,
            'fields' => 'id, parents',
        ]);
        $movid++;
        echo "  -> movido.\n";
    } catch (Exception $e) {
        $falhas++;
        echo "  -> FALHA: " . $e->getMessage() . "\n";
    }
}

echo "\nResumo: {$total} item(ns) na origem";
echo $apply
    ? ", {$movid} movido(s), {$falhas} falha(s).\n"
    : " (nada movido: dry-run).\n";
if (!$apply) {
    echo "AVISO: ao aplicar, arquivos que saem de um Shared Drive passam a ter a\n";
    echo "Service Account como dona (cota de 15 GB). Para manter um dono humano,\n";
    echo "mova via interface (arrastar e soltar), que tambem preserva os IDs.\n";
}
if ($apply && $falhas > 0) {
    exit(1);
}
