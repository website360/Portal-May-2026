# Subir o Sistema May para produção

## O que é

Aplicação **PHP com Laravel 12** no servidor e **React 19 com TypeScript** na tela,
ligados por Inertia — não é uma API separada com um front à parte: as duas metades
vivem no mesmo projeto e sobem juntas. O banco é **MySQL**.

O React é compilado antes de subir e vira arquivos estáticos em `public/build`.
O servidor **não** precisa de Node rodando; Node só é necessário na hora de compilar.

## O que o servidor precisa ter

| | Versão | Por quê |
|---|---|---|
| PHP | 8.2 ou maior | O framework exige |
| MySQL | 8.0 ou maior | Banco de dados |
| Composer | 2.x | Instalar as dependências PHP |
| Node + npm | 20 ou maior | Só para compilar a tela |

**Extensões do PHP:** `mbstring`, `openssl`, `pdo_mysql`, `dom`, `fileinfo`, `gd`.

- `gd` serve para reduzir o papel timbrado. Sem ela o contrato ainda sai, mas cada
  PDF fica três vezes mais lento.
- `openssl` cifra a chave da Evolution no banco e valida o certificado do servidor
  de WhatsApp. Confirme que o `php.ini` aponta `curl.cainfo` e `openssl.cafile` para
  um `cacert.pem` — sem isso a conexão com o WhatsApp falha com erro de certificado.

## Passo a passo

```bash
# 1. Código no servidor
git clone <repositorio> /var/www/sistema-may   # ou envie os arquivos
cd /var/www/sistema-may

# 2. Dependências PHP, sem as de desenvolvimento
composer install --no-dev --optimize-autoloader

# 3. Compilar a tela (pode ser feito aqui ou na sua máquina)
npm ci
npm run build

# 4. Configuração
cp .env.example .env
php artisan key:generate          # gera a APP_KEY

# 5. Banco
php artisan migrate --force

# 6. Arquivos enviados (fotos, PDFs anexados)
php artisan storage:link

# 7. Cache de produção
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## O `.env` de produção

O que **precisa** mudar em relação ao de desenvolvimento:

```env
APP_ENV=production
APP_DEBUG=false                   # deixar true expõe senhas do banco na tela de erro
APP_URL=https://sistema.suaempresa.com.br
APP_TIMEZONE=America/Sao_Paulo
APP_LOCALE=pt_BR

DB_HOST=…
DB_DATABASE=…
DB_USERNAME=…
DB_PASSWORD=…

SESSION_DRIVER=database           # as tabelas já vêm nas migrações
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Dados da agência que entram em todo contrato gerado:

```env
CONTRATO_AGENCIA_NOME="AGÊNCIA MAY SERVICOS DE INFORMACAO NA INTERNET LTDA"
CONTRATO_AGENCIA_DOCUMENTO=40881499000108
CONTRATO_AGENCIA_ENDERECO="Rua Dentista Barreto, 1321 – Sala 02 – Vila Carrão - São Paulo/SP – CEP 03420-000"
CONTRATO_AGENCIA_CIDADE="São Paulo"
CONTRATO_AGENCIA_REPRESENTANTE="Caio Lima Francisco"
CONTRATO_AGENCIA_REPRESENTANTE_CPF=35589783828
CONTRATO_AGENCIA_REPRESENTANTE_RG=33.766.979-X
```

O WhatsApp **não** vai no `.env`: endereço, instância e chave da Evolution são
cadastrados pela tela, em Configurações › WhatsApp, e a chave fica cifrada no banco.

## Arquivos que não estão no código

Estes precisam ser copiados à mão para o servidor — são arte e tipografia, não código:

```
storage/app/contratos/timbrado.png          o papel timbrado
storage/app/contratos/fontes/regular.ttf    a fonte do contrato
storage/app/contratos/fontes/bold.ttf       o negrito
```

Sem eles o contrato sai com um cabeçalho simples e a fonte padrão. A tela
Configurações › Modelos de contrato mostra o que ele encontrou.

## Servidor web

A raiz do site **é a pasta `public/`**, nunca a raiz do projeto. Apontar para a raiz
deixaria o `.env` acessível pela internet.

Nginx:

```nginx
root /var/www/sistema-may/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Nunca crie pastas dentro de `public/` com nome de módulo — `contratos`,
`manutencao`, `clientes`, `financeiro`, `dominios`, `tarefas`. O servidor acha a
pasta antes da rota e a página passa a responder 404. Há teste automatizado
guardando isso.

## Permissões

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## Agendamento

Existe uma rotina diária que gera as cobranças recorrentes dos próximos 30 dias.
Sem ela, as recorrências param de aparecer no financeiro:

```cron
* * * * * cd /var/www/sistema-may && php artisan schedule:run >> /dev/null 2>&1
```

## A cada atualização

```bash
php artisan down                  # tira do ar durante a troca
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## Antes de considerar no ar

- [ ] `APP_DEBUG=false` e `APP_ENV=production`
- [ ] HTTPS ativo — o sistema trabalha com CPF, CNPJ e contratos assinados
- [ ] Backup automático do banco **e** da pasta `storage/app/public` (as fotos e os
      PDFs anexados moram lá, não no banco)
- [ ] Trocar a senha do usuário administrador
- [ ] `php artisan test` passando na máquina de onde saiu o código
