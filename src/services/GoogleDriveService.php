<?php

namespace Ipsis\services;

/**
 * Serviço para upload de arquivos no Google Drive
 */
class GoogleDriveService
{
    private $service;
    private $folderId;

    public function __construct()
    {
        $this->folderId = GOOGLE_DRIVE_FOLDER_ID;
        
        // Criar cliente Google
        $client = new \Google_Client();
        $client->setApplicationName('Ipsis Clientes');
        $client->setScopes([\Google_Service_Drive::DRIVE_FILE]);
        $client->setAuthConfig(GOOGLE_CREDENTIALS_PATH);
        $client->setAccessType('offline');
        
        $this->service = new \Google_Service_Drive($client);
    }

    /**
     * Faz upload de um arquivo para o Google Drive (suporta Shared Drives)
     * 
     * @param string $filePath Caminho local do arquivo
     * @param string $fileName Nome do arquivo no Drive
     * @return array ['id' => file_id, 'link' => view_link]
     */
    public function uploadFile($filePath, $fileName)
    {
        try {
            if (!file_exists($filePath)) {
                // Caminho completo apenas no log do servidor; a exceção não deve
                // vazar informação de diretório até a resposta ao cliente
                error_log('Arquivo nao encontrado para upload no Drive: ' . $filePath);
                throw new \Exception('Arquivo nao encontrado para upload no Drive.');
            }

            // Metadados do arquivo
            $fileMetadata = new \Google_Service_Drive_DriveFile([
                'name' => $fileName,
                'parents' => [$this->folderId]
            ]);

            // Conteúdo do arquivo
            $content = file_get_contents($filePath);
            
            // Upload com suporte a Shared Drives
            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => 'application/pdf',
                'uploadType' => 'multipart',
                'fields' => 'id, webViewLink, webContentLink',
                'supportsAllDrives' => true
            ]);

            // Não cria mais permissão pública ("anyone" / "reader"):
            // o acesso ao arquivo é herdado da pasta de destino no Drive
            // (compartilhada com a equipe), de modo que o PDF deixa de
            // ficar público por link.

            return [
                'id' => $file->id,
                'link' => $file->webViewLink,
                'download_link' => $file->webContentLink
            ];

        } catch (\Exception $e) {
            error_log('Erro ao fazer upload no Drive: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Deleta um arquivo do Google Drive
     * 
     * @param string $fileId ID do arquivo no Drive
     * @return bool
     */
    public function deleteFile($fileId)
    {
        try {
            $this->service->files->delete($fileId);
            return true;
        } catch (\Exception $e) {
            error_log('Erro ao deletar arquivo do Drive: ' . $e->getMessage());
            return false;
        }
    }
}
