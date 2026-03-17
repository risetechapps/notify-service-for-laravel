# Notify Service for Laravel

[![Laravel](https://img.shields.io/badge/Laravel-12.x-red?logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE.md)

Client package para o servidor [NotifyKit](https://notifykit.app.br). Integra **10 canais de notificação**, **campanhas em massa**, **rastreamento automático** por banco de dados e **consultas de status** tanto locais quanto via API do servidor.

---

## Índice

- [Instalação](#instalação)
- [Configuração](#configuração)
- [Canais disponíveis](#canais-disponíveis)
- [Notificações individuais](#notificações-individuais)
  - [SMS](#sms)
  - [Email](#email)
  - [Push FCM](#push-fcm)
  - [APNS Apple Push](#apns-apple-push)
  - [Telegram](#telegram)
  - [Slack](#slack)
  - [Discord](#discord)
  - [Teams](#teams)
  - [WebSocket](#websocket)
  - [Webhook](#webhook)
- [Campanhas em massa](#campanhas-em-massa)
  - [Campanha SMS](#campanha-sms)
  - [Campanha Email](#campanha-email)
  - [Fontes de contatos](#fontes-de-contatos)
- [Rastreamento automático](#rastreamento-automático)
- [Webhook receiver](#webhook-receiver)
- [Consultas de status](#consultas-de-status)
  - [Consultas locais](#consultas-locais)
  - [Consultas no servidor](#consultas-no-servidor)
- [Modelos](#modelos)
- [Eventos Laravel](#eventos-laravel)

---

## Instalação

```bash
composer require risetechapps/notify-service-for-laravel
```

Publique a config e rode as migrations:

```bash
php artisan vendor:publish --provider="RiseTechApps\Notify\NotifyServiceProvider" --tag="config"
php artisan migrate
composer dump-autoload
```

---

## Configuração

Adicione as variáveis ao seu `.env`:

```env
NOTIFY_SERVICE_KEY=sua-api-key
NOTIFY_SERVICE_WEBHOOK=https://sua-app.com/notify/webhook

# Rotas automáticas de webhook (opcional)
NOTIFY_SERVICE_ROUTES=true
NOTIFY_SERVICE_ROUTES_PREFIX=notify
```

Arquivo `config/notify.php`:

```php
return [
    'key'     => env('NOTIFY_SERVICE_KEY', ''),
    'webhook' => env('NOTIFY_SERVICE_WEBHOOK', ''),

    // Registra automaticamente as rotas de webhook do package
    'routes'            => env('NOTIFY_SERVICE_ROUTES', true),
    'routes_prefix'     => env('NOTIFY_SERVICE_ROUTES_PREFIX', 'notify'),
    'routes_middleware' => ['api'],
];
```

Quando `routes = true`, o package registra automaticamente:

```
POST /notify/webhook           → recebe status de notificações individuais
POST /notify/webhook/campaign  → recebe status de campanhas
```

Para registrar manualmente (defina `routes = false`):

```php
// routes/api.php
use RiseTechApps\Notify\Http\Controllers\NotifyWebhookController;

Route::post('/notify/webhook',          [NotifyWebhookController::class, 'notification']);
Route::post('/notify/webhook/campaign', [NotifyWebhookController::class, 'campaign']);
```

---

## Canais disponíveis

| Canal | Drivers suportados | Método `via()` |
|---|---|---|
| SMS | Twilio, Zenvia, Mobizon | `notify.sms` |
| Email | SMTP, Mailgun, Resend, SendGrid, SES, Postmark | `notify.mail` |
| Push Android | FCM | `notify.push` |
| Push iOS | APNS | `notify.apns` |
| Telegram | Telegram Bot API | `notify.telegram` |
| Slack | Slack Webhooks | `notify.slack` |
| Discord | Discord Webhooks | `notify.discord` |
| Teams | Microsoft Teams Webhooks | `notify.teams` |
| WebSocket | Pusher | `notify.websocket` |
| Webhook | HTTP genérico | `notify.webhook` |

---

## Notificações individuais

Todas as notificações usam o sistema de notificações nativo do Laravel. Crie uma classe de notificação, declare o canal em `via()` e implemente o método correspondente.

O rastreamento é **automático** — um `NotifyLog` é criado no banco para cada envio sem nenhuma configuração adicional.

---

### SMS

```php
use RiseTechApps\Notify\Message\NotifySms;

class PedidoConfirmado extends Notification
{
    public function via($notifiable): array
    {
        return ['notify.sms'];
    }

    public function toNotifySms($notifiable): NotifySms
    {
        return (new NotifySms)
            ->to($notifiable->phone)
            ->content('Seu pedido #1234 foi confirmado!')
            ->webhookUrl('https://sua-app.com/notify/webhook'); // opcional
    }
}
```

| Método | Descrição |
|---|---|
| `->to(string)` | Número destino no formato E.164 sem `+`, ex: `5521981425950` |
| `->content(string)` | Texto do SMS. Máx: 160 chars |
| `->from(string)` | Remetente / sender ID |
| `->webhookUrl(string)` | URL de callback de status (sobrescreve o global do config) |

---

### Email

```php
use RiseTechApps\Notify\Message\NotifyMail;

class BemVindo extends Notification
{
    public function via($notifiable): array
    {
        return ['notify.mail'];
    }

    public function toNotifyMail($notifiable): NotifyMail
    {
        return (new NotifyMail)
            ->to($notifiable->email, $notifiable->name)
            ->from('noreply@app.com', 'Minha App')
            ->subject('Bem-vindo!')
            ->line('Obrigado por se cadastrar.')
            ->action('https://app.com/dashboard', 'Acessar painel')
            ->setSignature('Equipe Minha App');
    }
}
```

| Método | Descrição |
|---|---|
| `->to(email, name?)` | Destinatário |
| `->from(email, name?)` | Remetente |
| `->subject(string)` | Assunto do email |
| `->subjectMessage(string)` | Subtítulo / preview no cliente de email |
| `->line(string)` | Linha de texto principal |
| `->lineHeader(string)` | Linha no cabeçalho (chamadas múltiplas = múltiplas linhas) |
| `->lineFooter(string)` | Linha no rodapé |
| `->action(url, text)` | Botão de call-to-action |
| `->theme(string)` | Tema do template. Default: `default` |
| `->setSignature(string)` | Assinatura no final |
| `->attachFromUrl(string\|array)` | Anexar arquivo(s) por URL |
| `->addTable(EmailTable\|array)` | Tabela: `['headers' => [], 'rows' => [[]]]` |
| `->addList(type, items)` | Lista: tipo `ordered` ou `unordered` |
| `->webhookUrl(string)` | URL de callback |

---

### Push FCM

```php
use RiseTechApps\Notify\Message\NotifyPush;

public function toNotifyPush($notifiable): NotifyPush
{
    return (new NotifyPush)
        ->token($notifiable->fcm_token)   // string ou array de tokens
        ->title('Nova mensagem')
        ->body('Você tem uma nova mensagem de João.')
        ->imageUrl('https://app.com/image.png')
        ->data(['order_id' => '1234']);    // valores devem ser strings (regra FCM)
}
```

| Método | Descrição |
|---|---|
| `->token(string\|array)` | Device token(s) FCM |
| `->topic(string)` | Tópico FCM (alternativa ao token) |
| `->title(string)` | Título da notificação |
| `->body(string)` | Corpo da notificação |
| `->imageUrl(string)` | URL de imagem |
| `->data(array)` | Dados extras (todos os valores como string) |
| `->configId(string)` | UUID da config FCM salva no servidor |
| `->webhookUrl(string)` | URL de callback |

---

### APNS Apple Push

```php
use RiseTechApps\Notify\Message\NotifyApns;

public function toNotifyApns($notifiable): NotifyApns
{
    return (new NotifyApns)
        ->token($notifiable->apns_token)
        ->title('Novo pedido')
        ->body('Seu pedido foi enviado.')
        ->badge(1)
        ->sound('default')
        ->data(['order_id' => '1234']);
}
```

| Método | Descrição |
|---|---|
| `->token(string\|array)` | Device token(s) Apple |
| `->title(string)` | Título |
| `->body(string)` | Corpo |
| `->subtitle(string)` | Subtítulo |
| `->badge(int)` | Número no ícone do app |
| `->sound(string)` | Nome do arquivo de som |
| `->data(array)` | Payload customizado |
| `->category(string)` | Categoria para action buttons iOS |
| `->threadId(string)` | Agrupa notificações relacionadas |
| `->configId(string)` | UUID da config APNS |
| `->webhookUrl(string)` | URL de callback |

---

### Telegram

```php
use RiseTechApps\Notify\Message\NotifyTelegram;

public function toNotifyTelegram($notifiable): NotifyTelegram
{
    return (new NotifyTelegram)
        ->chatId($notifiable->telegram_chat_id)
        ->message('🚀 Seu deploy foi concluído com sucesso!')
        ->parseMode('Markdown')
        ->button('Ver logs', 'https://app.com/logs');
}
```

| Método | Descrição |
|---|---|
| `->chatId(string)` | ID numérico ou @username |
| `->message(string)` | Texto da mensagem. Máx: 4096 chars |
| `->parseMode(string)` | `Markdown` \| `MarkdownV2` \| `HTML` |
| `->imageUrl(string)` | URL de imagem |
| `->button(text, url)` | Botão inline |
| `->buttons(array)` | Múltiplos botões: `[[['text' => '', 'url' => '']]]` |
| `->configId(string)` | UUID da config Telegram |
| `->webhookUrl(string)` | URL de callback |

---

### Slack

```php
use RiseTechApps\Notify\Message\NotifySlack;

public function toNotifySlack($notifiable): NotifySlack
{
    return (new NotifySlack)
        ->channel('#alertas')
        ->title('Erro crítico')
        ->message('Ocorreu um erro na integração de pagamento.')
        ->color('#FF0000')
        ->field('Ambiente', 'Produção')
        ->field('Servidor', 'api-01');
}
```

| Método | Descrição |
|---|---|
| `->message(string)` | Texto principal. Máx: 4000 chars |
| `->channel(string)` | Canal destino, ex: `#alerts` ou `C01234ABC` |
| `->title(string)` | Título do bloco |
| `->color(string)` | Cor hex da barra lateral: `#FF0000` |
| `->field(label, value)` | Campo chave-valor no bloco |
| `->fields(array)` | Múltiplos campos de uma vez |
| `->configId(string)` | UUID da config Slack |
| `->webhookUrl(string)` | Webhook URL do Slack |

---

### Discord

```php
use RiseTechApps\Notify\Message\NotifyDiscord;

public function toNotifyDiscord($notifiable): NotifyDiscord
{
    return (new NotifyDiscord)
        ->username('NotifyBot')
        ->title('Novo usuário cadastrado')
        ->message('João Silva acabou de se cadastrar.')
        ->color(3066993) // verde em decimal
        ->field('Email', 'joao@email.com')
        ->field('Plano', 'Pro', inline: true);
}
```

| Método | Descrição |
|---|---|
| `->message(string)` | Texto da mensagem. Máx: 2000 chars |
| `->username(string)` | Nome exibido do bot. Máx: 80 chars |
| `->title(string)` | Título do embed |
| `->color(int)` | Cor em decimal, ex: `16729344` (vermelho) |
| `->imageUrl(string)` | Imagem grande no embed |
| `->thumbnail(string)` | Miniatura no embed |
| `->footer(string)` | Rodapé do embed |
| `->field(label, value, inline?)` | Campo do embed (máx: 25) |
| `->fields(array)` | Múltiplos campos de uma vez |
| `->configId(string)` | UUID da config Discord |
| `->webhookUrl(string)` | Webhook URL do Discord |

---

### Teams

```php
use RiseTechApps\Notify\Message\NotifyTeams;

public function toNotifyTeams($notifiable): NotifyTeams
{
    return (new NotifyTeams)
        ->title('Relatório semanal disponível')
        ->message('O relatório de vendas da semana está pronto.')
        ->color('0078D4')
        ->fact('Período', 'Mar/2026')
        ->fact('Total', 'R$ 48.200,00')
        ->action('Ver relatório', 'https://app.com/reports');
}
```

| Método | Descrição |
|---|---|
| `->message(string)` | Corpo da mensagem. Máx: 4000 chars |
| `->title(string)` | Título do card |
| `->color(string)` | Cor hex sem `#`, ex: `0078D4` |
| `->fact(label, value)` | Par chave-valor no card |
| `->facts(array)` | Múltiplos facts de uma vez |
| `->action(label, url)` | Botão de ação |
| `->actions(array)` | Múltiplos botões de uma vez |
| `->configId(string)` | UUID da config Teams |
| `->webhookUrl(string)` | Webhook URL do Teams |

---

### WebSocket

```php
use RiseTechApps\Notify\Message\NotifyWebSocket;

public function toNotifyWebSocket($notifiable): NotifyWebSocket
{
    return (new NotifyWebSocket)
        ->channel("private-user.{$notifiable->id}")
        ->event('OrderStatusUpdated')
        ->data(['order_id' => 1234, 'status' => 'shipped'])
        ->private();
}
```

| Método | Descrição |
|---|---|
| `->channel(string)` | Canal Pusher, ex: `notifications`, `private-user.123` |
| `->event(string)` | Nome do evento, ex: `OrderUpdated` |
| `->data(array)` | Payload do evento |
| `->private(bool?)` | Canal privado (requer auth Pusher) |
| `->presence(bool?)` | Canal de presence |
| `->configId(string)` | UUID da config Pusher |
| `->webhookUrl(string)` | URL de callback |

---

### Webhook

```php
use RiseTechApps\Notify\Message\NotifyWebhook;

public function toNotifyWebhook($notifiable): NotifyWebhook
{
    return (new NotifyWebhook)
        ->url('https://erp.empresa.com/api/eventos')
        ->method('POST')
        ->payload(['evento' => 'pedido_criado', 'id' => 1234])
        ->bearerAuth('token-secreto')
        ->timeout(15);
}
```

| Método | Descrição |
|---|---|
| `->url(string)` | URL de destino. Máx: 2048 chars |
| `->method(string)` | `POST` \| `GET` \| `PUT` \| `PATCH`. Default: `POST` |
| `->payload(array)` | Body da requisição |
| `->header(key, value)` | Header customizado |
| `->headers(array)` | Múltiplos headers de uma vez |
| `->bearerAuth(token)` | Autenticação Bearer |
| `->basicAuth(user, pass)` | Autenticação Basic |
| `->apiKeyAuth(token)` | Autenticação por API Key |
| `->hmacAuth()` | Assinatura HMAC |
| `->timeout(int)` | Timeout em segundos. Min: 1, Máx: 60 |
| `->configId(string)` | UUID da config Webhook |
| `->webhookUrl(string)` | URL de callback de status |

---

## Gerenciamento de configurações de driver

As credenciais de cada canal ficam salvas no servidor. Você pode ter múltiplas configurações por canal — uma marcada como `is_default` é usada automaticamente quando nenhum `config_id` é informado no envio.

```php
use RiseTechApps\Notify\NotifyConfig;

// ── Listar ────────────────────────────────────────────────────────────────────

NotifyConfig::all();             // todas as configs
NotifyConfig::channel('sms');    // só as configs de SMS
NotifyConfig::channel('email');  // só as configs de Email

// ── Detalhes ─────────────────────────────────────────────────────────────────

$config = NotifyConfig::find($id);
// Retorna: id, channel, driver, label, is_default, active, credential_keys
// credential_keys = chaves das credenciais (nunca os valores)

// ── Criar ─────────────────────────────────────────────────────────────────────

$config = NotifyConfig::create()
    ->channel('sms')
    ->driver('twilio')
    ->label('Twilio Principal')
    ->credentials([
        'account_sid' => 'ACxxxxxxxxxxxxxxxx',
        'auth_token'  => 'xxxxxxxxxxxxxxxx',
        'from'        => '+15551234567',
    ])
    ->asDefault()   // define como padrão do canal
    ->save();

// $config retorna: ['id' => '...', 'channel' => 'sms', 'driver' => 'twilio', 'label' => '...']

NotifyConfig::create()
    ->channel('email')
    ->driver('resend')
    ->label('Resend Transacional')
    ->credentials(['api_key' => 're_xxxxxxxx'])
    ->save();

// ── Atualizar ─────────────────────────────────────────────────────────────────
// As credenciais são mescladas (merge parcial) — envie só o que quer alterar

NotifyConfig::update($id)
    ->label('Twilio Backup')
    ->credentials(['auth_token' => 'novo-token'])
    ->save();

NotifyConfig::update($id)
    ->active(false)   // desativar
    ->save();

// ── Definir como padrão ───────────────────────────────────────────────────────

NotifyConfig::setDefault($id);  // remove is_default das outras configs do mesmo canal

// ── Remover ───────────────────────────────────────────────────────────────────

NotifyConfig::delete($id);
```

### Credenciais por driver

| Canal | Driver | Credenciais necessárias |
|---|---|---|
| `sms` | `twilio` | `account_sid`, `auth_token`, `from` |
| `sms` | `zenvia` | `api_token`, `from` |
| `sms` | `mobizon` | `api_key`, `from` |
| `email` | `smtp` | `host`, `port`, `username`, `password`, `encryption` |
| `email` | `mailgun` | `api_key`, `domain`, `endpoint` |
| `email` | `resend` | `api_key` |
| `email` | `sendgrid` | `api_key` |
| `email` | `ses` | `key`, `secret`, `region` |
| `email` | `postmark` | `server_token` |
| `push` | `fcm` | `credentials_json` |
| `apns` | `apns` | `key_id`, `team_id`, `bundle_id`, `private_key` |
| `telegram` | `telegram` | `bot_token` |
| `slack` | `slack` | `bot_token` |
| `discord` | `discord` | `webhook_url` |
| `teams` | `teams` | `webhook_url` |
| `websocket` | `pusher` | `app_id`, `app_key`, `app_secret`, `cluster` |
| `webhook` | `webhook` | `default_url` |

### Usando uma config específica no envio

Todos os canais e o `NotifyCampaignBuilder` aceitam `->configId(string)` para sobrescrever a config padrão:

```php
// Notificação individual
public function toNotifySms($notifiable): NotifySms
{
    return (new NotifySms)
        ->to($notifiable->phone)
        ->content('Mensagem')
        ->configId('uuid-da-config-twilio-backup');
}

// Campanha
NotifyCampaignBuilder::sms()
    ->name('Promo')
    ->content('Olá {{name}}!')
    ->contacts([...])
    ->configId('uuid-da-config-zenvia')
    ->send();
```

---

## Campanhas em massa

Campanhas não usam o sistema de notificações do Laravel — são disparadas diretamente pela classe `NotifyCampaignBuilder`. Suportam até **10.000 contatos** por disparo com rate limiting configurável.

---

### Campanha SMS

```php
use RiseTechApps\Notify\NotifyCampaignBuilder;

$campaign = NotifyCampaignBuilder::sms()
    ->name('Promo Dia das Mães')
    ->content('Olá {{name}}! 30% OFF hoje. Use: MAES30. Loja.com')
    ->contacts([
        ['phone' => '5521981425950', 'name' => 'João'],
        ['phone' => '5521969014860', 'name' => 'Maria'],
    ])
    ->webhookUrl('https://sua-app.com/notify/webhook/campaign')
    ->ratePerMinute(100)
    ->send();

// $campaign é um NotifyCampaign com server_campaign_id, status, progress, etc.
```

| Método | Descrição |
|---|---|
| `->name(string)` | Nome da campanha |
| `->content(string)` | Texto do SMS. Máx: 160 chars. Variáveis: `{{name}}`, `{{phone}}` + chaves de `extra_data` |
| `->from(string)` | Remetente / sender ID |
| `->contacts(array)` | Array direto: `[['phone' => '...', 'name' => '...']]` |
| `->fromQuery(Builder, col, nameCol?)` | Via query Eloquent — processada em chunks de 500 |
| `->fromCollection(Collection, col, nameCol?)` | Via Collection Laravel |
| `->configId(string)` | UUID da config SMS |
| `->webhookUrl(string)` | URL de callback de progresso |
| `->ratePerMinute(int)` | Envios por minuto. Min: 1, Máx: 600. Default: 60 |
| `->scheduledAt(string)` | Agendamento: `'2026-06-01 08:00:00'` |
| `->send()` | Envia e retorna `NotifyCampaign` |

---

### Campanha Email

```php
use RiseTechApps\Notify\NotifyCampaignBuilder;

$campaign = NotifyCampaignBuilder::email()
    ->name('Newsletter Março 2026')
    ->subject('Novidades de Março!')
    ->line('Confira as novidades deste mês.')
    ->action('https://app.com/blog', 'Ler mais')
    ->from('news@app.com', 'Minha App')
    ->contacts([
        ['email' => 'joao@email.com', 'name' => 'João'],
        ['email' => 'maria@email.com', 'name' => 'Maria'],
    ])
    ->webhookUrl('https://sua-app.com/notify/webhook/campaign')
    ->scheduledAt('2026-03-15 09:00:00')
    ->send();
```

| Método | Descrição |
|---|---|
| `->name(string)` | Nome da campanha |
| `->subject(string)` | Assunto do email |
| `->subjectMessage(string)` | Subtítulo / preview |
| `->line(string)` | Linha de texto principal |
| `->lineHeader(string)` | Linha no cabeçalho (chamadas múltiplas) |
| `->lineFooter(string)` | Linha no rodapé |
| `->action(url, text)` | Botão de call-to-action |
| `->theme(string)` | Tema do template. Default: `default` |
| `->signature(string)` | Assinatura |
| `->from(email, name?)` | Remetente |
| `->addTable(EmailTable\|array)` | Tabela no corpo: `['headers' => [], 'rows' => [[]]]` |
| `->addList(type, items)` | Lista `ordered` ou `unordered` |
| `->contacts(array)` | Array direto: `[['email' => '...', 'name' => '...']]` |
| `->fromQuery(Builder, col, nameCol?)` | Via query Eloquent |
| `->fromCollection(Collection, col, nameCol?)` | Via Collection |
| `->configId(string)` | UUID da config de email |
| `->webhookUrl(string)` | URL de callback |
| `->ratePerMinute(int)` | Envios por minuto. Default: 60 |
| `->scheduledAt(string)` | Agendamento futuro |
| `->send()` | Envia e retorna `NotifyCampaign` |

---

### Fontes de contatos

**Array direto:**
```php
->contacts([
    ['phone' => '5511999887766', 'name' => 'João'],                          // SMS
    ['email' => 'joao@email.com', 'name' => 'João', 'extra_data' => [...]], // Email
])
```

**Via query Eloquent** — não carrega todos os registros em memória, processa em chunks de 500:
```php
->fromQuery(
    User::where('active', true)->where('accepts_sms', true),
    contactColumn: 'phone',  // coluna com telefone ou email no banco
    nameColumn: 'name'       // opcional
)
```

**Via Collection:**
```php
->fromCollection($users, contactColumn: 'email', nameColumn: 'full_name')
```

---

## Gestão de configurações de driver

Use `NotifyConfiguration` para criar e gerenciar as credenciais de cada canal diretamente no servidor. As credenciais são armazenadas criptografadas e nunca são devolvidas nas consultas — apenas as chaves.

Ao criar uma configuração com `is_default: true`, ela passa a ser usada automaticamente em todos os envios daquele canal que não passarem um `configId` explícito.

### Criar configuração

```php
use RiseTechApps\Notify\NotifyConfiguration;

// SMS — Twilio
NotifyConfiguration::create([
    'channel'    => 'sms',
    'driver'     => 'twilio',
    'label'      => 'Twilio Principal',
    'is_default' => true,
    'credentials' => [
        'account_sid' => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'auth_token'  => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'from'        => '+15551234567',
    ],
]);

// SMS — Zenvia
NotifyConfiguration::create([
    'channel'    => 'sms',
    'driver'     => 'zenvia',
    'label'      => 'Zenvia',
    'credentials' => [
        'api_token' => 'seu-token-zenvia',
        'from'      => 'NomeSender',
    ],
]);

// Email — SMTP
NotifyConfiguration::create([
    'channel'    => 'email',
    'driver'     => 'smtp',
    'label'      => 'SMTP Produção',
    'is_default' => true,
    'credentials' => [
        'host'       => 'smtp.mailserver.com',
        'port'       => 587,
        'username'   => 'user@dominio.com',
        'password'   => 'senha',
        'encryption' => 'tls',
    ],
]);

// Email — Resend
NotifyConfiguration::create([
    'channel'    => 'email',
    'driver'     => 'resend',
    'label'      => 'Resend',
    'is_default' => true,
    'credentials' => [
        'api_key' => 're_xxxxxxxxxxxxxxxx',
    ],
]);

// Email — Mailgun
NotifyConfiguration::create([
    'channel'     => 'email',
    'driver'      => 'mailgun',
    'label'       => 'Mailgun',
    'credentials' => [
        'api_key' => 'key-xxxxxxxxxxxxxxxx',
        'domain'  => 'mg.seudominio.com',
        'region'  => 'us', // ou 'eu'
    ],
]);

// Push — FCM
NotifyConfiguration::create([
    'channel'    => 'push',
    'driver'     => 'fcm',
    'label'      => 'Firebase Produção',
    'is_default' => true,
    'credentials' => [
        'project_id'   => 'seu-projeto-firebase',
        'private_key'  => '-----BEGIN PRIVATE KEY-----\n...',
        'client_email' => 'firebase-adminsdk@projeto.iam.gserviceaccount.com',
    ],
]);

// Telegram
NotifyConfiguration::create([
    'channel'    => 'telegram',
    'driver'     => 'telegram',
    'label'      => 'Bot Principal',
    'is_default' => true,
    'credentials' => [
        'bot_token' => '123456789:AAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    ],
]);

// Slack
NotifyConfiguration::create([
    'channel'    => 'slack',
    'driver'     => 'slack',
    'label'      => 'Slack #alertas',
    'is_default' => true,
    'credentials' => [
        'webhook_url' => 'https://hooks.slack.com/services/T.../B.../xxx',
    ],
]);

// Discord
NotifyConfiguration::create([
    'channel'    => 'discord',
    'driver'     => 'discord',
    'label'      => 'Discord #geral',
    'is_default' => true,
    'credentials' => [
        'webhook_url' => 'https://discord.com/api/webhooks/xxx/yyy',
    ],
]);

// Teams
NotifyConfiguration::create([
    'channel'    => 'teams',
    'driver'     => 'teams',
    'label'      => 'Teams Engenharia',
    'is_default' => true,
    'credentials' => [
        'webhook_url' => 'https://outlook.office.com/webhook/xxx',
    ],
]);

// WebSocket — Pusher
NotifyConfiguration::create([
    'channel'    => 'websocket',
    'driver'     => 'pusher',
    'label'      => 'Pusher Produção',
    'is_default' => true,
    'credentials' => [
        'app_id'  => 'xxxxxxx',
        'app_key' => 'xxxxxxxxxxxxxxxxxxxxxxxx',
        'secret'  => 'xxxxxxxxxxxxxxxxxxxxxxxx',
        'cluster' => 'us2',
    ],
]);
```

### Listar, atualizar e remover

```php
// Listar todas
NotifyConfiguration::all();

// Filtrar por canal
NotifyConfiguration::channel('sms');
NotifyConfiguration::channel('email');

// Buscar por ID (retorna chaves das credenciais, nunca os valores)
NotifyConfiguration::find('uuid');

// Atualizar — merge parcial nas credenciais (só o que você enviar é alterado)
NotifyConfiguration::update('uuid', ['label' => 'Novo nome']);
NotifyConfiguration::update('uuid', ['credentials' => ['password' => 'nova-senha']]);
NotifyConfiguration::update('uuid', ['active' => false]);

// Definir como padrão do canal
NotifyConfiguration::setDefault('uuid');

// Remover
NotifyConfiguration::delete('uuid');
```

### Usar uma config específica no envio

Quando você quer usar uma config diferente da padrão em um envio pontual, passe o `configId`:

```php
// Notificação individual
public function toNotifySms($notifiable): NotifySms
{
    return (new NotifySms)
        ->to($notifiable->phone)
        ->content('Mensagem pelo remetente secundário.')
        ->configId('uuid-da-config-alternativa'); // sobrescreve o is_default
}

// Campanha
NotifyCampaignBuilder::sms()
    ->name('Campanha com config específica')
    ->content('Olá {{name}}!')
    ->contacts([...])
    ->configId('uuid-da-config-alternativa')
    ->send();
```

---

## Rastreamento automático

Cada envio — notificação individual ou campanha — é registrado automaticamente no banco. Nenhuma alteração é necessária nas suas classes de notificação.

**Tabelas criadas pelas migrations:**

| Tabela | Descrição |
|---|---|
| `notify_logs` | Um registro por envio individual. Armazena canal, status, payload, resposta do servidor |
| `notify_campaigns` | Uma linha por campanha disparada |
| `notify_campaign_contacts` | Um registro por contato de campanha |

**Ciclo de status — `notify_logs`:**

| Status | Momento |
|---|---|
| `sending` | Antes da chamada HTTP ao servidor |
| `sent` | Servidor aceitou com HTTP 200 |
| `delivered` | Webhook do servidor confirmou entrega |
| `error` | Falha no envio ou rejeição pelo provedor |

**Ciclo de status — `notify_campaigns`:**

| Status | Momento |
|---|---|
| `pending` | Campanha criada localmente, aguardando aceite |
| `processing` | Servidor aceitou e está processando os jobs |
| `paused` | Pausada no servidor |
| `completed` | Todos os contatos processados |
| `failed` | Falha geral |
| `cancelled` | Cancelada via DELETE no servidor |

---

## Webhook receiver

O package inclui `NotifyWebhookController` que recebe os callbacks do servidor e atualiza o banco local automaticamente.

**Payload esperado — notificação individual:**
```json
{
    "notification_id": "uuid-do-servidor",
    "status": "delivered",
    "delivered_at": "2026-03-12 10:30:00"
}
```

**Payload esperado — campanha:**
```json
{
    "campaign_id": "uuid-da-campanha-no-servidor",
    "status": "processing",
    "total": 1000,
    "sent": 450,
    "failed": 12,
    "started_at": "2026-03-12 09:00:00",
    "contact_updates": [
        { "contact": "joao@email.com", "status": "sent", "sent_at": "2026-03-12 09:01:00" },
        { "contact": "erro@email.com", "status": "failed", "error": "Mailbox full" }
    ]
}
```

---

## Consultas de status

A classe `NotifyQuery` oferece dois modos: **banco local** (sem HTTP, instantâneo) e **servidor** (tempo real via API).

### Consultas locais

```php
use RiseTechApps\Notify\NotifyQuery;

// ── Notificações ─────────────────────────────────────────────────────────────

// Todos os logs — retorna Eloquent Builder, use ->get(), ->paginate(), etc.
NotifyQuery::logs()->latest()->paginate(25);

// Com filtros
NotifyQuery::logs()->where('channel', 'sms')->where('status', 'error')->get();

// Logs de um model específico (User, Authentication, etc.)
NotifyQuery::logsFor($user)->latest()->get();
NotifyQuery::logsFor($user)->where('channel', 'email')->get();

// Buscar pelo ID retornado pelo servidor (útil no webhook)
NotifyQuery::findLog('server-notification-uuid');

// ── Campanhas ─────────────────────────────────────────────────────────────────

NotifyQuery::campaigns()->where('channel', 'sms')->latest()->get();
NotifyQuery::campaigns()->where('status', 'completed')->paginate(10);

// Buscar pelo ID do servidor
NotifyQuery::findCampaign('server-campaign-uuid');

// Contatos de uma campanha
NotifyQuery::campaignContacts('local-campaign-uuid')->where('status', 'failed')->get();
NotifyQuery::campaignContacts('local-campaign-uuid')->where('status', 'pending')->count();
```

---

### Consultas no servidor

Consultas em tempo real via API do servidor (faz requisição HTTP com a `NOTIFY_SERVICE_KEY`).

```php
use RiseTechApps\Notify\NotifyQuery;

// ── Notificações ─────────────────────────────────────────────────────────────

// Listar — retorna ['data' => [...], 'meta' => [...]]
$result = NotifyQuery::server()->notifications()
    ->channel('sms')          // sms|email|push|apns|telegram|slack|discord|teams|websocket|webhook
    ->status('send')          // created|sending|send|delivered|ready|error
    ->from('2026-01-01')
    ->to('2026-03-31')
    ->campaignId('uuid')      // filtra por campanha
    ->perPage(50)             // máx: 100, default: 25
    ->page(2)
    ->get();

$result['data']; // array de notificações
$result['meta']; // total, per_page, current_page, last_page

// Detalhes + timeline de eventos de uma notificação
$notification = NotifyQuery::server()->notification('server-uuid');
// Inclui todos os campos + array 'events' com a timeline completa

// Só a timeline (ideal para polling)
$events = NotifyQuery::server()->notificationEvents('server-uuid');
$events['current_status']; // status atual
$events['events'];         // array de eventos com timestamps

// ── Campanhas ─────────────────────────────────────────────────────────────────

// Listar campanhas
$result = NotifyQuery::server()->campaigns()
    ->channel('email')              // sms|email
    ->status('completed')           // pending|processing|paused|completed|failed|cancelled
    ->from('2026-01-01')
    ->to('2026-03-31')
    ->perPage(25)
    ->get();

// Detalhes + progresso de uma campanha
$campaign = NotifyQuery::server()->campaign('server-campaign-uuid');
$campaign['progress_percent']; // 0 a 100
$campaign['pending_count'];    // contatos ainda não processados
$campaign['sent_count'];
$campaign['failed_count'];

// Contatos de uma campanha
$result = NotifyQuery::server()->campaignContacts('server-campaign-uuid')
    ->status('failed')              // pending|sending|sent|failed|skipped
    ->search('joao@email.com')      // busca parcial por email ou telefone
    ->perPage(100)                  // máx: 200, default: 50
    ->page(1)
    ->get();

$result['campaign']; // dados resumidos da campanha
$result['data'];     // array de contatos
$result['meta'];     // paginação

// Detalhe de um contato específico
$contact = NotifyQuery::server()->campaignContact('campaign-uuid', 'contact-uuid');
// Inclui notification_id — use para cruzar com ->notification()
```

---

## Modelos

### `NotifyLog`

```php
use RiseTechApps\Notify\Models\NotifyLog;

$log = NotifyLog::find('uuid');

$log->channel;                // 'sms', 'email', etc.
$log->status;                 // 'sending', 'sent', 'delivered', 'error'
$log->server_notification_id; // UUID retornado pelo servidor
$log->payload;                // array — o que foi enviado
$log->server_response;        // array — resposta do servidor
$log->sent_at;                // Carbon
$log->delivered_at;           // Carbon
$log->failed_at;              // Carbon

// Relacionamentos
$log->notifiable;             // model notificado (User, Authentication, etc.)
$log->campaign;               // NotifyCampaign ou null

// Métodos
$log->markAsSent($serverId, $response);
$log->markAsDelivered();
$log->markAsFailed($errorMessage, $response);
```

---

### `NotifyCampaign`

```php
use RiseTechApps\Notify\Models\NotifyCampaign;

$campaign = NotifyCampaign::find('uuid');

$campaign->name;               // nome
$campaign->channel;            // 'sms' ou 'email'
$campaign->status;             // 'pending', 'processing', 'completed', etc.
$campaign->server_campaign_id; // UUID no servidor
$campaign->total_contacts;
$campaign->sent_count;
$campaign->failed_count;
$campaign->progress;           // atributo computado: % concluído (0-100)
$campaign->scheduled_at;       // Carbon ou null
$campaign->started_at;         // Carbon ou null
$campaign->finished_at;        // Carbon ou null

// Relacionamentos
$campaign->contacts;           // HasMany NotifyCampaignContact
$campaign->logs;               // HasMany NotifyLog

// Métodos
$campaign->syncFromWebhook($data); // atualiza contadores via payload do webhook
```

---

### `NotifyCampaignContact`

```php
use RiseTechApps\Notify\Models\NotifyCampaignContact;

$contact = NotifyCampaignContact::find('uuid');

$contact->contact;    // email ou telefone
$contact->name;
$contact->status;     // 'pending', 'sent', 'failed', 'skipped'
$contact->error;      // mensagem de erro (se failed)
$contact->sent_at;    // Carbon

// Relacionamento
$contact->campaign;   // NotifyCampaign
```

---

## Eventos Laravel

O package dispara eventos nativos do Laravel em cada etapa do envio individual:

```php
use RiseTechApps\Notify\Events\NotifySendingEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;
use RiseTechApps\Notify\Events\NotifyFailedEvent;

// NotifySendingEvent                                         — antes do envio HTTP
// NotifySentEvent($notifiable, $notification, $response, $channel)  — envio bem-sucedido
// NotifyFailedEvent($notifiable, $notification, $exception, $channel) — falha no envio
```

Registre listeners no `EventServiceProvider`:

```php
protected $listen = [
    \RiseTechApps\Notify\Events\NotifySentEvent::class => [
        App\Listeners\LogNotificacaoEnviada::class,
    ],
    \RiseTechApps\Notify\Events\NotifyFailedEvent::class => [
        App\Listeners\AlertarFalhaDeNotificacao::class,
    ],
];
```

---

## Configurações de driver

Gerencie as credenciais de cada canal diretamente pelo package, sem precisar acessar o painel do servidor.

### Listar

```php
use RiseTechApps\Notify\NotifyDriverConfig;

NotifyDriverConfig::list();          // todas as configs
NotifyDriverConfig::list('sms');     // filtra por canal
NotifyDriverConfig::get('uuid');     // detalhes (chaves das credenciais, sem valores)
```

### SMS

```php
// Twilio
NotifyDriverConfig::twilio('Twilio Principal')
    ->sid('ACxxxxxxxxxxxxxxxx')
    ->token('xxxxxxxxxxxxxxxx')
    ->from('+5511999999999')
    ->asDefault()
    ->save();

// Zenvia
NotifyDriverConfig::zenvia('Zenvia Prod')
    ->apiToken('xxxxxxxxxxxxxxxx')
    ->senderId('EMPRESA')
    ->save();

// Mobizon
NotifyDriverConfig::mobizon('Mobizon')
    ->key('xxxxxxxxxxxxxxxx')
    ->save();
```

### Email

```php
// SMTP
NotifyDriverConfig::smtp('SMTP Produção')
    ->host('smtp.empresa.com')
    ->port(587)
    ->username('user@empresa.com')
    ->password('senha')
    ->encryption('tls')
    ->asDefault()
    ->save();

// Mailgun
NotifyDriverConfig::mailgun('Mailgun')
    ->domain('mg.empresa.com')
    ->secret('key-xxxxxxxxxxxxxxxx')
    ->save();

// Resend
NotifyDriverConfig::resend('Resend')
    ->apiKey('re_xxxxxxxxxxxxxxxx')
    ->save();

// SendGrid
NotifyDriverConfig::sendgrid('SendGrid')
    ->apiKey('SG.xxxxxxxxxxxxxxxx')
    ->save();

// Amazon SES
NotifyDriverConfig::ses('SES us-east-1')
    ->key('AKIAXXXXXXXXXXXXXXXX')
    ->secret('xxxxxxxxxxxxxxxx')
    ->region('us-east-1')
    ->save();

// Postmark
NotifyDriverConfig::postmark('Postmark')
    ->token('xxxxxxxxxxxxxxxx')
    ->save();
```

### Push / APNS

```php
// FCM (Android)
NotifyDriverConfig::fcm('FCM Android')
    ->projectId('meu-projeto-firebase')
    ->credentialsFile('/path/to/service-account.json')
    ->save();

// APNS (iOS)
NotifyDriverConfig::apns('APNS iOS')
    ->keyPath('/path/to/AuthKey.p8')
    ->keyId('XXXXXXXXXX')
    ->teamId('XXXXXXXXXX')
    ->bundleId('com.empresa.app')
    ->production(true)
    ->save();
```

### Mensageiros

```php
// Telegram
NotifyDriverConfig::telegram('Telegram Bot')
    ->botToken('123456789:AAxxxxxxxxxx')
    ->save();

// Slack
NotifyDriverConfig::slack('Slack Workspace')
    ->webhookUrl('https://hooks.slack.com/services/xxx/yyy/zzz')
    ->defaultChannel('#geral')
    ->save();

// Discord
NotifyDriverConfig::discord('Discord Server')
    ->webhookUrl('https://discord.com/api/webhooks/xxx/yyy')
    ->defaultUsername('NotifyBot')
    ->avatarUrl('https://app.com/bot-avatar.png')
    ->save();

// Teams
NotifyDriverConfig::teams('Teams Canal Engenharia')
    ->webhookUrl('https://outlook.office.com/webhook/xxx')
    ->save();

// Pusher (WebSocket)
NotifyDriverConfig::pusher('Pusher Produção')
    ->appId('xxxxxxxx')
    ->key('xxxxxxxxxxxxxxxx')
    ->secret('xxxxxxxxxxxxxxxx')
    ->cluster('mt1')
    ->save();
```

### Atualizar, definir padrão e remover

```php
// Atualiza campos — credenciais fazem merge parcial (só o que você enviar é atualizado)
NotifyDriverConfig::update('config-uuid', [
    'label'       => 'Novo nome',
    'credentials' => ['password' => 'nova-senha'],  // só atualiza a senha, o resto permanece
    'is_default'  => true,
]);

// Define como padrão para o canal
NotifyDriverConfig::setDefault('config-uuid');

// Remove
NotifyDriverConfig::delete('config-uuid');
```

### Usando uma config específica em um envio

Depois de criar a config no servidor e ter o UUID dela, passe o `config_id` na mensagem:

```php
// Notificação individual
public function toNotifySms($notifiable): NotifySms
{
    return (new NotifySms)
        ->to($notifiable->phone)
        ->content('Mensagem via Zenvia')
        ->configId('uuid-da-config-zenvia');  // sobrescreve a config padrão
}

// Campanha
NotifyCampaignBuilder::sms()
    ->name('Promo')
    ->content('Olá {{name}}!')
    ->contacts($contacts)
    ->configId('uuid-da-config-zenvia')       // sobrescreve a config padrão
    ->send();
```

---

## Licença

MIT — veja [LICENSE.md](LICENSE.md) para detalhes.

&copy; 2026 [Rise Tech Apps](https://risetech.com.br)
