# Changelog

Todas as mudanças notáveis para o pacote `risetechapps/notify-service-for-laravel` serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), e este projeto adere ao [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-22

Primeira versão oficial, alinhada ao notify-server 1.0.0. As versões anteriores (até a 2.0.0) foram pré-lançamentos internos e saíram do histórico; o pacote passa a ser distribuído pelo Repman.

### O que a versão inclui

- **11 canais** do Laravel Notifications (`notify.sms`, `notify.mail`, `notify.push`, `notify.apns`, `notify.telegram`, `notify.slack`, `notify.discord`, `notify.teams`, `notify.websocket`, `notify.webhook`, `notify.whatsapp`), com opções de envio em todas as mensagens: agendamento, failover, modelo salvo, `Idempotency-Key` e modo de teste.
- **Nova tentativa na fila:** em notificação com fila, falha passageira lança `NotifyDeliveryException` e o Laravel tenta de novo, com `Idempotency-Key` automática para não duplicar a mensagem.
- **Campanhas** de e-mail, SMS e WhatsApp (`NotifyCampaignBuilder`), com listas, modelos, janela de envio, teste A/B, relatório e CSV; envio em lote (`batch()`) e multicanal (`NotifyQuery::multi()`).
- **WhatsApp:** números (QR code, avisos, proteção), chat (conversas, respostas, transferência, atendentes do seu sistema, webhook por conversa, mídia, fluxos do bot) e equipe.
- **Eventos:** `NotifyWebhookEvent`, `NotifyCampaignWebhookEvent` e `NotifyWhatsAppEvent`, pelos webhooks assinados (`X-Notify-Signature`) ou pelo comando `notify:whatsapp-events`.
- **Consultas e recursos da conta** por `NotifyQuery::server()`: histórico por canal, campanhas, configurações de driver, modelos, listas, descadastros, entregas de webhook e consumo.

### Atualizando a partir da 2.0.0 (pré-lançamento)

Troque a restrição no `composer.json` para `^1.0` e adicione o Repman em `repositories`, como nos outros pacotes `risetechapps`.

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
