# Changelog

Todas as mudanças notáveis para o pacote `risetechapps/notify-service-for-laravel` serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), e este projeto adere ao [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-12-29

### Adicionado

- **Funcionalidade Principal**: Implementação do pacote `Notify Service for Laravel` para integração com a plataforma NotifyKit.
- **Canais de Notificação**: Adicionados os canais `notify.mail` e `notify.sms` ao sistema de Notificações do Laravel.
- **Mensagens Ricas**: Classes `NotifyMail` e `NotifySms` para construção de mensagens ricas em conteúdo (e-mail) e concisas (SMS).
- **Eventos**: Disparo de eventos (`NotifySendingEvent`, `NotifySentEvent`, `NotifyFailedEvent`) para monitoramento do ciclo de vida da notificação.
- **Configuração**: Publicação do arquivo de configuração `config/notify.php` para chave de API.
- **Estrutura de Pacote**: Arquivos de estrutura inicial, incluindo `composer.json`, `LICENSE.md` e `CONTRIBUTING.md`.
