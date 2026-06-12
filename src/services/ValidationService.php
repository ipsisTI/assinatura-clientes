<?php
namespace Ipsis\services;

require_once __DIR__ . '/../utils/helpers.php';

/**
 * Serviço de Validação
 * 
 * Responsável por validar dados do formulário e verificar duplicidades
 */
class ValidationService {
    
    private $errors = [];
    
    /**
     * Valida todos os campos do formulário
     */
    public function validateForm($data, $files) {
        $this->errors = [];
        
        // Validar Razão Social
        if (empty($data['razao_social'])) {
            $this->errors['razao_social'] = 'Razão Social é obrigatória';
        }
        
        // Validar CNPJ
        if (empty($data['cnpj'])) {
            $this->errors['cnpj'] = 'CNPJ é obrigatório';
        } elseif (!isValidCNPJ($data['cnpj'])) {
            $this->errors['cnpj'] = 'CNPJ inválido';
        }
        
        // Validar Telefone
        if (empty($data['telefone'])) {
            $this->errors['telefone'] = 'Telefone é obrigatório';
        } elseif (!isValidPhone($data['telefone'])) {
            $this->errors['telefone'] = 'Telefone inválido';
        }
        
        // Validar Email
        if (empty($data['email'])) {
            $this->errors['email'] = 'Email é obrigatório';
        } elseif (!isValidEmail($data['email'])) {
            $this->errors['email'] = 'Email inválido';
        }
        
        // Validar Segmento
        if (empty($data['segmento'])) {
            $this->errors['segmento'] = 'Segmento é obrigatório';
        }
        
        // Validar Assinatura
        if (empty($data['assinatura'])) {
            $this->errors['assinatura'] = 'Assinatura é obrigatória';
        }
        
        // Validar reCAPTCHA
        if (empty($data['recaptcha_token'])) {
            $this->errors['recaptcha'] = 'Validação reCAPTCHA é obrigatória';
        }
        
        return empty($this->errors);
    }
    
    /**
     * Verifica reCAPTCHA v3
     */
    public function verifyRecaptcha($token) {
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $token
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $response = json_decode($result);
        
        // Score mínimo de 0.5 (0.0 = bot, 1.0 = humano)
        return $response->success && $response->score >= 0.5;
    }
    
    /**
     * Retorna erros de validação
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Verifica se CNPJ já existe na planilha
     */
    public function checkDuplicateCNPJ($cnpj, $sheetsService) {
        try {
            $values = $sheetsService->getValues('A:C');
            
            if (empty($values)) {
                return false;
            }
            
            $cnpjClean = preg_replace('/[^0-9]/', '', $cnpj);
            
            // Pular cabeçalho (linha 1)
            for ($i = 1; $i < count($values); $i++) {
                if (isset($values[$i][2])) {
                    $existingCNPJ = preg_replace('/[^0-9]/', '', $values[$i][2]);
                    if ($existingCNPJ === $cnpjClean) {
                        return true;
                    }
                }
            }
            
            return false;
        } catch (\Exception $e) {
            logError('Erro ao verificar CNPJ duplicado: ' . $e->getMessage());
            return false;
        }
    }
}
