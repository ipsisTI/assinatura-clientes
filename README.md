# Sistema de Cadastro de Clientes - Ipsis

Formulário público de aceite e assinatura do Código do Cliente Ipsis: o cliente
confirma a leitura do PDF, preenche seus dados, assina (desenhando ou digitando)
e o sistema gera um PDF assinado que é enviado para o Google Drive e registrado
em uma planilha do Google Sheets.

## 🚀 Tecnologias

- PHP 8+ (sem framework)
- Composer (FPDF/FPDI, google/apiclient, vlucas/phpdotenv)
- Google Sheets API e Google Drive API
- Google reCAPTCHA v3
- JavaScript puro (PDF.js para visualização do documento)

## 📁 Estrutura do Projeto

```
public/          Docroot da aplicação (index, submit-form, sucesso, download-pdf)
src/config/      Configurações e constantes (config.php)
src/services/    Integrações (GoogleDriveService, GoogleSheetsService, PDF, etc.)
src/utils/       Funções auxiliares (helpers.php)
uploads/signed/  PDFs assinados gerados (bloqueados para acesso web direto)
logs/            Logs de auditoria/integração em JSONL (bloqueados para acesso web)
tools/           Scripts de manutenção via CLI
```

## ⚙️ Configuração

1. Copie `.env.example` para `.env` e preencha com suas credenciais (Google
   Sheets, Google Drive, reCAPTCHA v3 e URL da aplicação).
2. Coloque o JSON de credenciais da Service Account em `src/config/credentials.json`.
3. Instale as dependências: `composer install`.
4. Garanta permissão de escrita em `uploads/` e `logs/`.
5. Aponte o docroot do servidor web para `public/`.

## 🔒 Segurança

- CSRF token validado no servidor (hash_equals)
- reCAPTCHA v3 verificado no servidor (score mínimo 0,5, checagem de action e hostname)
- Validação de CNPJ/CPF (dígitos verificadores) no backend
- Rate limiting por sessão (30s)
- Download do PDF autorizado somente para a sessão que o gerou
- Arquivos no Google Drive restritos à pasta (sem permissão pública de link)
- Sanitização anti-injeção de fórmulas nos valores gravados no Google Sheets (campos iniciados por `=`, `+`, `-`, `@`, tab ou CR são prefixados com aspa simples para virar texto puro)
- Sanitização de inputs
- HTTPS em produção

## 🛠️ Revogar acesso público de PDFs antigos no Drive

`tools/revogar-acesso-publico.php` varre a pasta configurada em `GOOGLE_DRIVE_FOLDER_ID`, lista os arquivos que ainda têm permissão pública (`anyone`) e pode revogá-la — útil para PDFs publicados antes do hardening de permissões.

```bash
# Dry-run (padrão): apenas lista arquivo, nome e ID da permissão anyone
php tools/revogar-acesso-publico.php

# Revoga de fato cada permissão anyone encontrada
php tools/revogar-acesso-publico.php --apply
```

O script só roda via CLI (bloqueado no web), usa exclusivamente as credenciais do `.env` e suporta Shared Drives.
