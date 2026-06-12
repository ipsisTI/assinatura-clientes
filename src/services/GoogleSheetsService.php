<?php
namespace Ipsis\services;

require_once __DIR__ . '/../../vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

/**
 * Serviço Google Sheets
 * 
 * Gerencia a integração com Google Sheets API
 */
class GoogleSheetsService {
    
    private $service;
    private $spreadsheetId;
    
    public function __construct() {
        $this->spreadsheetId = GOOGLE_SHEET_ID;
        $this->initializeClient();
    }
    
    /**
     * Inicializa cliente Google Sheets
     */
    private function initializeClient() {
        try {
            $client = new Client();
            $client->setApplicationName('Ipsis Clientes');
            $client->setScopes([Sheets::SPREADSHEETS]);
            $client->setAuthConfig(GOOGLE_CREDENTIALS_PATH);
            $client->setAccessType('offline');
            
            $this->service = new Sheets($client);
        } catch (\Exception $e) {
            logError('Erro ao inicializar Google Sheets: ' . $e->getMessage());
            throw new \Exception('Erro ao conectar com Google Sheets');
        }
    }
    
    /**
     * Adiciona linha na planilha
     */
    public function appendRow($data) {
        try {
            $values = [
                [
                    date('d/m/Y H:i:s'),
                    $data['razao_social'],
                    $data['cnpj'],
                    $data['telefone'],
                    $data['email'],
                    $data['segmento'],
                    $data['assinatura_tipo'],
                    'Pendente'
                ]
            ];
            
            $body = new \Google\Service\Sheets\ValueRange([
                'values' => $values
            ]);
            
            $params = [
                'valueInputOption' => 'RAW'
            ];
            
            $result = $this->service->spreadsheets_values->append(
                $this->spreadsheetId,
                'A:H',
                $body,
                $params
            );
            
            return $result->getUpdates()->getUpdatedRows() > 0;
        } catch (\Exception $e) {
            logError('Erro ao adicionar linha no Sheets: ' . $e->getMessage(), $data);
            throw new \Exception('Erro ao salvar dados na planilha');
        }
    }
    
    /**
     * Obtém valores de um range
     */
    public function getValues($range) {
        try {
            $response = $this->service->spreadsheets_values->get(
                $this->spreadsheetId,
                $range
            );
            
            return $response->getValues();
        } catch (\Exception $e) {
            logError('Erro ao ler valores do Sheets: ' . $e->getMessage());
            throw new \Exception('Erro ao ler dados da planilha');
        }
    }
    
    /**
     * Cria cabeçalho da planilha se não existir
     */
    public function ensureHeader() {
        try {
            $values = $this->getValues('A1:H1');
            
            // Se não há cabeçalho, criar
            if (empty($values)) {
                $header = [
                    [
                        'Data/Hora',
                        'Razão Social',
                        'CNPJ',
                        'Telefone',
                        'Email',
                        'Segmento',
                        'Assinatura',
                        'Status'
                    ]
                ];
                
                $body = new \Google\Service\Sheets\ValueRange([
                    'values' => $header
                ]);
                
                $params = [
                    'valueInputOption' => 'RAW'
                ];
                
                $this->service->spreadsheets_values->update(
                    $this->spreadsheetId,
                    'A1:H1',
                    $body,
                    $params
                );
                
                // Formatar cabeçalho (negrito)
                $this->formatHeader();
            }
        } catch (\Exception $e) {
            logError('Erro ao criar cabeçalho: ' . $e->getMessage());
        }
    }
    
    /**
     * Formata cabeçalho da planilha
     */
    private function formatHeader() {
        try {
            $requests = [
                new \Google\Service\Sheets\Request([
                    'repeatCell' => [
                        'range' => [
                            'sheetId' => 0,
                            'startRowIndex' => 0,
                            'endRowIndex' => 1
                        ],
                        'cell' => [
                            'userEnteredFormat' => [
                                'backgroundColor' => [
                                    'red' => 0.0,
                                    'green' => 0.4,
                                    'blue' => 0.8
                                ],
                                'textFormat' => [
                                    'foregroundColor' => [
                                        'red' => 1.0,
                                        'green' => 1.0,
                                        'blue' => 1.0
                                    ],
                                    'fontSize' => 11,
                                    'bold' => true
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat(backgroundColor,textFormat)'
                    ]
                ])
            ];
            
            $batchUpdateRequest = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                'requests' => $requests
            ]);
            
            $this->service->spreadsheets->batchUpdate(
                $this->spreadsheetId,
                $batchUpdateRequest
            );
        } catch (\Exception $e) {
            logError('Erro ao formatar cabeçalho: ' . $e->getMessage());
        }
    }
}
