# Changelog

Todas as mudanças notáveis para o pacote `risetechapps/notify-service-for-laravel` serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), e este projeto adere ao [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-03-17
- Implementado envio de arquivo html nas campanhas.

## [1.1.0] - 2026-03-12

### Adicionado

- **`NotifyCampaignBuilder`**: Nova classe fluent para disparo de campanhas em massa de SMS e Email, totalmente independente do sistema de notificações do Laravel.
    - Suporte a até 10.000 contatos por campanha com inserção em lotes de 500.
    - Três fontes de contatos: array direto (`->contacts()`), query Eloquent com chunks (`->fromQuery()`), e Collection (`->fromCollection()`).
    - Rate limiting configurável via `->ratePerMinute()` (padrão: 60, máx: 600).
    - Agendamento futuro via `->scheduledAt()`.
    - Campo do contato correto por canal: `phone` para SMS, `email` para Email.

- **`NotifyQuery`**: Nova classe de consultas com dois modos — banco local e API do servidor.
    - Consultas locais: `logs()`, `logsFor()`, `findLog()`, `campaigns()`, `findCampaign()`, `campaignContacts()`.
    - Consultas no servidor via `server()`: listagem e detalhes de notificações, timeline de eventos, listagem e detalhes de campanhas, contatos de campanha com busca e filtro.
    - Classes fluent internas: `ServerNotificationQuery`, `ServerCampaignQuery`, `ServerCampaignContactQuery`.

- **Rastreamento automático de envios**: Todos os 10 canais agora criam um `NotifyLog` automaticamente antes e após cada envio, sem necessidade de alteração nas classes de notificação do usuário.

- **Migrations**: Criadas as tabelas `notify_logs`, `notify_campaigns` e `notify_campaign_contacts`.

- **Models**: Adicionados `NotifyLog`, `NotifyCampaign` e `NotifyCampaignContact` com relacionamentos, casts, scopes e métodos de ciclo de vida (`markAsSent`, `markAsDelivered`, `markAsFailed`, `syncFromWebhook`).

- **`NotifyWebhookController`**: Controller para receber callbacks de status do servidor.
    - `notification()` — atualiza status de notificações individuais no banco local.
    - `campaign()` — atualiza contadores, status e contatos individuais de campanhas.

- **Rotas automáticas de webhook**: Registradas via `ServiceProvider` quando `notify.routes = true`.
    - `POST {prefix}/webhook` — notificações individuais.
    - `POST {prefix}/webhook/campaign` — campanhas.
    - Prefixo e middleware configuráveis via `config/notify.php` ou variáveis de ambiente.

- **Novos canais de notificação individual**: `notify.push`, `notify.apns`, `notify.telegram`, `notify.slack`, `notify.discord`, `notify.teams`, `notify.websocket`, `notify.webhook` (além dos já existentes `notify.sms` e `notify.mail`).

- **Novas classes de mensagem**:
    - `NotifyPush` — FCM com suporte a token, topic, título, body, imagem e data.
    - `NotifyApns` — APNS com badge, sound, category, threadId, collapseId e silent push.
    - `NotifyTelegram` — com parse_mode, imagem e inline buttons.
    - `NotifySlack` — com channel, color, title e fields.
    - `NotifyDiscord` — com embed completo: color, thumbnail, image, footer e fields.
    - `NotifyTeams` — com card adaptativo: facts e actions.
    - `NotifyWebSocket` — Pusher com suporte a canais private e presence.
    - `NotifyWebhook` — HTTP genérico com auth bearer, basic, api_key e hmac.

- **`README.md`**: Documentação completa com exemplos de todos os canais, campanhas, rastreamento, webhook receiver, consultas locais e no servidor, modelos e eventos.

### Alterado

- **`config/notify.php`**: Adicionadas as chaves `routes`, `routes_prefix` e `routes_middleware` para controle das rotas de webhook.
- **`NotifyServiceProvider`**: Atualizado para registrar todos os 10 canais individuais e as rotas de webhook condicionalmente.

### Removido

- **`Channel/Campaign/`** e **`Message/Campaign/`**: Removidas as classes de campanha baseadas no sistema de notificações do Laravel (`notify.sms.campaign`, `notify.mail.campaign`) em favor do `NotifyCampaignBuilder`, que oferece uma API mais adequada para disparos em massa.

---

## [1.0.0] - 2025-12-29

### Adicionado

- **Funcionalidade Principal**: Implementação do pacote `Notify Service for Laravel` para integração com a plataforma NotifyKit.
- **Canais de Notificação**: Adicionados os canais `notify.mail` e `notify.sms` ao sistema de Notificações do Laravel.
- **Mensagens Ricas**: Classes `NotifyMail` e `NotifySms` para construção de mensagens ricas em conteúdo (e-mail) e concisas (SMS).
- **Eventos**: Disparo de eventos (`NotifySendingEvent`, `NotifySentEvent`, `NotifyFailedEvent`) para monitoramento do ciclo de vida da notificação.
- **Configuração**: Publicação do arquivo de configuração `config/notify.php` para chave de API.
- **Estrutura de Pacote**: Arquivos de estrutura inicial, incluindo `composer.json`, `LICENSE.md` e `CONTRIBUTING.md`.
