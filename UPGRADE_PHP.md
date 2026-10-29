# Upgrade do PHP no Servidor (Ubuntu + Nginx)

## Por quê

O sistema rodava em PHP 8.0/8.1, versões que estão **fim de vida (EOL)** e não recebem mais correções de segurança. A partir de outubro/2026 as versões com suporte são:

| Versão | Suporte de segurança até |
|--------|--------------------------|
| 8.3    | dez/2027 |
| 8.4    | dez/2028 (recomendada) |
| 8.5    | dez/2029 |

O `composer.json` do projeto agora exige `php >= 8.3`. **Atualize o servidor antes de fazer o próximo deploy** deste código, senão o `composer install` falha na checagem de plataforma.

> Máquina local (Windows): já atualizada para PHP 8.4 (winget), na frente do XAMPP no PATH — o XAMPP continua intacto para Apache/MySQL.

## Passos (produção, via SSH)

### 1. Instalar PHP 8.4 com as extensões usadas pelo sistema

```bash
sudo add-apt-repository ppa:ondrej/php
sudo apt update

# extensões obrigatórias: curl (reCAPTCHA), mbstring (assinatura), xml (Google API client)
# recomendadas: zip (composer), gd
sudo apt install php8.4-fpm php8.4-curl php8.4-mbstring php8.4-xml php8.4-zip php8.4-gd
```

### 2. Configurar o php.ini novo (`/etc/php/8.4/fpm/php.ini`)

Migrar do php.ini antigo (referência: `/etc/php/8.1/fpm/php.ini` ou `/etc/php/8.0/fpm/php.ini`):

```ini
date.timezone = America/Sao_Paulo
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 300
memory_limit = 256M
```

(session.cookie_secure é definido pelo próprio código em `src/config/config.php`.)

### 3. Apontar o Nginx para o socket novo

No site (`/etc/nginx/sites-available/<nome-do-site>`), trocar o `fastcgi_pass`:

```nginx
# antes: unix:/var/run/php/php8.1-fpm.sock   (ou php8.0)
# depois:
fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
```

```bash
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl enable --now php8.4-fpm
```

### 4. Reinstalar dependências e validar

```bash
cd /var/www/<pasta-do-projeto>
composer install --no-dev --optimize-autoloader
```

Teste ponta a ponta: abrir o formulário, assinar, conferir PDF gerado, linha na planilha, PDF no Drive e download na página de sucesso.

### 5. Limpeza (só depois de validar!)

```bash
sudo systemctl disable --now php8.1-fpm
sudo apt purge 'php8.1*' 'php8.0*'   # conforme a versão antiga
sudo apt autoremove
```

## Rollback

Reverter o `fastcgi_pass` no Nginx para o socket antigo e `sudo systemctl enable --now php8.1-fpm` (não desinstale a versão antiga antes de validar o novo fluxo em produção).
