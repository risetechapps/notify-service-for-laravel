# Changelog

Todas as mudanças notáveis para o pacote `risetechapps/notify-service-for-laravel` serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), e este projeto adere ao [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Não lançado]

Alinha o pacote às mudanças de canal do notify-server (revisão de 2026-10-30/31). Compatível com o notify-server a partir de 007591e (revisão de canais). Atualize antes do deploy do servidor: o formato de assinatura mudou.

### Corrigido

- **Callbacks recusados com 403 depois do novo servidor.** O `VerifyNotifySignature` só aceitava `X-Notify-Signature: t=…,v1=…`. Agora valida o formato do api-key, que o servidor usa em todos os webhooks (`X-Webhook-Signature: sha256=` + HMAC de `"{X-Webhook-Timestamp}.{corpo}"`, com janela anti-replay), e segue aceitando o formato antigo durante a transição. `notify.webhook_secret` aceita vários segredos separados por vírgula (o da conta e o de cada endpoint).
- **Campanha de e-mail sem `app_name`.** Com `notify.mail.app_name` vazio (a chave existe no config), o padrão `config('app.name')` não entrava e o servidor recusava a campanha com 422.
- **Webhooks no envelope chegavam vazios.** O envelope `{id, event, created_at, data}`, agora usado em todos os webhooks do servidor, é aberto antes de disparar o evento; o `id` vai em `payload['delivery_id']`.
- **Editar mensagem enviada não funcionava.** `edit()` mandava o texto em `text` e `editCaption()` em `caption`, mas o servidor espera `message` (e `content` no WhatsApp): toda edição pelo pacote voltava `422`.

### Adicionado

- As três rotas de webhook encaminham pelo conteúdo: notificação, `campaign.*` e `whatsapp.*` vão para o evento certo, mesmo chegando na mesma URL.
- `NotifyWebhookEvent`: `name()`, `is()` (aceita `sent` e `notification.sent`) e `test`. `NotifyCampaignWebhookEvent`: `event`.
- `NotifyQuery::multi()->add($mensagem)`: mensagens no multicanal, com canal, `config_id`, `webhook_url`, `failover` e modelo de cada uma; `tag()`, `scheduledAt()`, `test()` e `idempotencyKey()`.
- `batch()` aceita mensagens, inclui o modelo salvo, troca `mail` por `email` e aceita `test => true`.
- Telegram: `webAppButton()`, `loginButton()`, `copyButton()`, `switchInlineButton()` e `sameRow` em `button()`; enquete com `pollMultipleAnswers()` e `pollOpenPeriod()`.
- Campanha de e-mail: `appName()`.
- `tests/E2E`: contrato contra um notify-server de verdade (envio de todos os canais com os campos guardados, lote, multicanal, campanhas, ações, configurações e webhooks assinados de volta). Pulado sem `NOTIFY_E2E_URL`.
- APNs: `keyContent()` e `keyFile()` (a chave `.p8` vai no corpo; `keyPath()` é um caminho no servidor).
- Campanha de e-mail: `attachUrl()`.
- **E-mail:** `cc()`, `bcc()`, `replyTo()`, `preheader()`, `header()`, `html()`, `attachUrl($url, $nome)`, `attachData()`/`attachFile()` (anexo no corpo, sem URL pública) e `inline()` (imagem `cid:`).
- **APNs:** `image()`, `interruptionLevel()`, `relevanceScore()`, `ttl()`, `environment()`, `collapseId()`, `topic()`, `priority()`, `contentAvailable()`, `mutableContent()` e `criticalSound()`.
- **Telegram:** `documentUrl()`, `videoUrl()`, `audioUrl()`, `voiceUrl()`, `animationUrl()`, `media()`/`mediaFile()` (arquivo no corpo), `mediaGroup()`, `spoiler()`, `captionAbove()`, `quiz()`, extras da enquete e `replyTo(..., allowWithoutReply: true)`.
- `NotifyWebhook`: `asForm()` (`body_format: form`), `notifyMeta(false)` e `method('DELETE')`, alinhados ao servidor.
- **WhatsApp:** `group()`, `mentionAll()`, `mention()`, `mediaData()`/`mediaFile()`, `location()`, `contact()`, `linkPreview()` e `typing()`.
- **Slack:** `ephemeral()`, `replyBroadcast()`, `unfurl()`, `escape()`, `fileData()`/`filePath()` e `slackSchedule()` (agendamento no próprio Slack).
- **Discord:** `poll()`, `addFile()`, `fileData()`, `silent()`, `suppressEmbeds()`, `appliedTags()`, `timestamp()` e cor `#RRGGBB`.
- **Teams:** `mention()` e `fullWidth()`; `color()` aceita estilo (`good`, `attention`…) ou `#RRGGBB`.
- **WebSocket:** `channels()` (até 100), `except($socketId)` e `notifyMeta(false)`; na configuração, `maxPayloadKb()` e `encryptionMasterKey()`.
- **Webhook:** `apiKeyAuth($token, $header)` e `hmacAuth($segredo)`.
- **Ações sobre a mensagem enviada:** `unpin()`, `editMedia()` e `editButtons()` (Telegram), `pin()`, `unpin()` e `react()` (Slack), `edit()` e `delete()` no WhatsApp, e `edit(..., extra: [...])` para os demais campos. As ações devolvem também `errors`.
- **Configurações:** `telegramChats()`, `slackChannels()` e `whatsappGroups()`.

### Documentação

- README: assinatura, formatos e roteamento dos webhooks, nomes `notification.*`, eventos de campanha, multicanal e lote com mensagens, canais só com configuração do cliente, campos do WebSocket e do APNs.

### Alterado

- **Teams:** `color()` não tira mais o `#` (o servidor converte o hex no estilo do Adaptive Card). O card montado pelo servidor agora é Adaptive Card.
- **Slack, Discord, Teams, WebSocket e webhook genérico** só enviam pela configuração do cliente no servidor: sem uma ativa (e sem a URL de destino na mensagem, quando o canal tem), o envio volta `422`.

## [1.0.0] - 2026-10-22

Primeira versão oficial, alinhada ao notify-server 1.0.0. As versões anteriores (até a 2.0.0) foram pré-lançamentos internos e saíram do histórico; o pacote passa a ser distribuído pelo Packagist.

### O que a versão inclui

- **11 canais** do Laravel Notifications (`notify.sms`, `notify.mail`, `notify.push`, `notify.apns`, `notify.telegram`, `notify.slack`, `notify.discord`, `notify.teams`, `notify.websocket`, `notify.webhook`, `notify.whatsapp`), com opções de envio em todas as mensagens: agendamento, failover, modelo salvo, `Idempotency-Key` e modo de teste.
- **Nova tentativa na fila:** em notificação com fila, falha passageira lança `NotifyDeliveryException` e o Laravel tenta de novo, com `Idempotency-Key` automática para não duplicar a mensagem.
- **Campanhas** de e-mail, SMS e WhatsApp (`NotifyCampaignBuilder`), com listas, modelos, janela de envio, teste A/B, relatório e CSV; envio em lote (`batch()`) e multicanal (`NotifyQuery::multi()`).
- **WhatsApp:** números (QR code, avisos, proteção), chat (conversas, respostas, transferência, atendentes do seu sistema, webhook por conversa, mídia, fluxos do bot) e equipe.
- **Eventos:** `NotifyWebhookEvent`, `NotifyCampaignWebhookEvent` e `NotifyWhatsAppEvent`, pelos webhooks assinados (`X-Notify-Signature`) ou pelo comando `notify:whatsapp-events`.
- **Consultas e recursos da conta** por `NotifyQuery::server()`: histórico por canal, campanhas, configurações de driver, modelos, listas, descadastros, entregas de webhook e consumo.

### Atualizando a partir da 2.0.0 (pré-lançamento)

Troque a restrição no `composer.json` para `^1.0`. O pacote vem do Packagist: se a aplicação declarava um repositório próprio para ele em `repositories`, pode removê-lo.

#### Adicionado

- **Webhook por conversa** no chat: `Conversation::webhook($url)` (vai na próxima resposta ou transferência; `null` volta ao do número) e o 4º argumento de `Chat::start()`.
- `Conversation::downloadMedia($mensagem)`: baixa a mídia pelo link assinado da mensagem.

#### Corrigido

- `NotifyWebhookEvent->type` vinha sempre `null`: o servidor manda o canal em `channel`.
- Callback de campanha sem `->webhookUrl()` chegava em `/notify/webhook` e virava `NotifyWebhookEvent`; agora dispara `NotifyCampaignWebhookEvent`.
- Canal WhatsApp: o número do notifiable não sobrescreve mais o `->to()` da mensagem.
- Falha de envio não grava mais o notifiable e a notificação inteiros no log (só classe, id e canal).
- **`notify:whatsapp-events` não consulta mais em laço.** O servidor deixou de fazer long polling e responde na hora; sem pausa, o comando esgotava o limite de requisições da conta e os envios levavam 429. Agora espera o `Retry-After` (ou `--interval`, padrão 10 s) quando não há novidade, e o tempo pedido pelo servidor no 429. Com `has_more`, segue sem pausa.
- `ChatEvents::get()` devolve `retry_after` (o cabeçalho `Retry-After`) quando vem.

#### Alterado

- **Notificação com fila tenta de novo em falha passageira.** Servidor fora, timeout, 5xx, 429 e 409 lançam `NotifyDeliveryException` quando a notificação é `ShouldQueue`; antes o canal devolvia o erro e o job terminava como sucesso, sem nova tentativa. Validação, plano e demais 4xx continuam devolvendo o corpo do erro. O envio síncrono não muda (devolve o erro, sem lançar). `NOTIFY_SERVICE_RETRY_FAILURES=false` (`notify.retry_failures`) desliga a exceção também na fila.
- Todo envio leva uma `Idempotency-Key` automática (id da notificação + canal + corpo), para a nova tentativa não duplicar a mensagem. A `->idempotencyKey()` da mensagem vence.

#### Interno

- As classes `ServerQuery`, `ServerCampaignQuery`, `ServerCampaignContactQuery`, `ServerDriverConfig` e `ServerChannelAlias` saíram do `NotifyQuery.php`, uma por arquivo (PSR-4). Nomes e namespace não mudam.
- CI do pacote refeito (PHP 8.3/8.4, Laravel 12, branch `main`); a suíte roda num clone limpo.
- Código formatado com o Pint (preset `laravel`, o mesmo do `.styleci.yml`), que passa a ser conferido no CI.

#### Obsoleto

- `ChatEvents::wait()` e a opção `--wait` do comando: o servidor ignora a espera. Continuam aceitos para não quebrar quem usa; o comando avisa.
