# Notify Service for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/risetechapps/notify-service-for-laravel.svg?style=flat-square)](https://packagist.org/packages/risetechapps/notify-service-for-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/risetechapps/notify-service-for-laravel.svg?style=flat-square)](https://packagist.org/packages/risetechapps/notify-service-for-laravel)
[![License](https://img.shields.io/packagist/l/risetechapps/notify-service-for-laravel.svg?style=flat-square)](https://github.com/risetechapps/notify-service-for-laravel/blob/main/LICENSE.md)

## Visão Geral

O pacote **Notify Service for Laravel** (`risetechapps/notify-service-for-laravel`) estende o sistema de Notificações do Laravel, fornecendo canais de comunicação robustos para envio de **E-mail** e **SMS** através da plataforma **NotifyKit** [^1].

Este pacote simplifica o processo de envio de notificações transacionais e de marketing, permitindo a construção de mensagens ricas em conteúdo (como tabelas, listas e ações) para e-mail e mensagens de texto concisas para SMS, tudo isso utilizando a sintaxe familiar do Laravel.

## Requisitos

Para utilizar este pacote, você deve atender aos seguintes requisitos:

| Requisito | Versão Mínima |
| :--- | :--- |
| PHP | `^8.3` |
| Laravel/Illuminate Support | `^12` |
| Chave de API | Uma chave de API válida do NotifyKit |

## Instalação

Você pode instalar o pacote via Composer:

```bash
composer require risetechapps/notify-service-for-laravel
```

### Publicação do Arquivo de Configuração

Após a instalação, você deve publicar o arquivo de configuração do pacote. Isso criará o arquivo `config/notify.php` em sua aplicação:

```bash
php artisan vendor:publish --tag=config
```

## Configuração

O pacote requer que você defina sua chave de API do NotifyKit. Adicione a seguinte variável ao seu arquivo `.env`:

```dotenv
NOTIFY_SERVICE_KEY="SUA_CHAVE_DE_API_DO_NOTIFYKIT"
```

O arquivo de configuração publicado (`config/notify.php`) é o seguinte:

```php
<?php

return [
    'key' => env('NOTIFY_SERVICE_KEY', "")
];
```

## Uso

O pacote se integra perfeitamente ao sistema de Notificações do Laravel. Ele registra dois novos canais de notificação: `notify.mail` e `notify.sms`.

### Canais de Notificação

Para usar os canais, você deve incluí-los no método `via()` de sua classe de notificação:

| Canal | Descrição |
| :--- | :--- |
| `notify.mail` | Envia a notificação como E-mail através do NotifyKit. |
| `notify.sms` | Envia a notificação como SMS através do NotifyKit. |

### Exemplo de Notificação

Crie uma nova notificação, por exemplo, `OrderShippedNotification`:

```php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Message\NotifyMail;
use RiseTechApps\Notify\Message\NotifySms;

class OrderShippedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['notify.mail', 'notify.sms'];
    }

    /**
     * Converte a notificação para uma mensagem de E-mail do NotifyKit.
     */
    public function toNotifyMail(object $notifiable): NotifyMail
    {
        return (new NotifyMail())
            ->subject('Seu pedido foi enviado!')
            ->lineHeader('Olá, ' . $notifiable->name . '!')
            ->line('Seu pedido #12345 foi enviado e está a caminho.')
            ->action('Rastrear Pedido', 'https://rastreio.com/12345')
            ->lineFooter('Obrigado por comprar conosco!');
    }

    /**
     * Converte a notificação para uma mensagem de SMS do NotifyKit.
     */
    public function toNotifySms(object $notifiable): NotifySms
    {
        return (new NotifySms())
            ->content('Seu pedido #12345 foi enviado. Rastreie em: https://rastreio.com/12345');
    }
}
```

### Roteamento de Notificações

Seu modelo `Notifiable` (geralmente o `User`) deve implementar os métodos de roteamento para que o pacote saiba para onde enviar as mensagens:

```php
// No seu modelo User.php

use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * Rota para o canal de E-mail.
     */
    public function routeNotificationForMail(Notification $notification): string|array
    {
        return $this->email; // Ou um array de e-mails
    }

    /**
     * Rota para o canal de SMS.
     */
    public function routeNotificationForSms(Notification $notification): string
    {
        return $this->phone_number; // Deve ser o número de telefone
    }
}
```

### Construindo a Mensagem de Email (`NotifyMail`)

A classe `NotifyMail` oferece métodos fluentes para construir e-mails ricos em conteúdo:

| Método | Descrição |
| :--- | :--- |
| `subject(string $subject)` | Define o assunto do e-mail. **Obrigatório.** |
| `content(array $content)` | Define o conteúdo principal do e-mail (texto livre). |
| `lineHeader(string $line)` | Adiciona uma linha de texto antes do conteúdo principal. |
| `lineFooter(string $line)` | Adiciona uma linha de texto após o conteúdo principal. |
| `line(string $line)` | Define uma linha de texto centralizada. |
| `action(string $url, string $text)` | Adiciona um botão de ação com URL e texto. |
| `addTable(EmailTable|array $table)` | Adiciona uma tabela de dados. Use a classe `EmailTable` ou um array. |
| `addList(string $type, array $items)` | Adiciona uma lista (ordenada ou não). |
| `attachFromUrl(array|string $attach)` | Anexa arquivos, fornecendo a URL pública do arquivo. |
| `to(string $email, string $name)` | Define o destinatário (geralmente preenchido pelo roteamento). |
| `from(string $email, string $name)` | Define o remetente (pode sobrescrever o padrão). |
| `theme(string $theme)` | Define o tema visual do e-mail (se suportado pelo NotifyKit). |
| `subjectMessage(string $subjectMessage)` | Define uma mensagem de pré-cabeçalho (pre-header). |
| `setSignature(string $signature)` | Adiciona uma linha à assinatura do e-mail. |

### Construindo a Mensagem de SMS (`NotifySms`)

A classe `NotifySms` é mais simples, focada na mensagem de texto:

| Método | Descrição |
| :--- | :--- |
| `content(string $content)` | Define o conteúdo da mensagem SMS. **Obrigatório.** |
| `to(string $to)` | Define o destinatário (geralmente preenchido pelo roteamento). |
| `from(string $from)` | Define o remetente (pode ser o nome da sua aplicação). |

## Eventos

O pacote dispara eventos do Laravel durante o ciclo de vida do envio da notificação, permitindo que você monitore e reaja ao status das entregas:

| Evento | Descrição |
| :--- | :--- |
| `RiseTechApps\Notify\Events\NotifySendingEvent` | Disparado antes do envio da notificação para o NotifyKit. |
| `RiseTechApps\Notify\Events\NotifySentEvent` | Disparado após o envio bem-sucedido, contendo a resposta da API. |
| `RiseTechApps\Notify\Events\NotifyFailedEvent` | Disparado se ocorrer uma exceção durante o processo de envio. |

Você pode escutar esses eventos em seu `EventServiceProvider` para implementar lógica de *logging* ou *retry*.

## Contribuição

Sinta-se à vontade para contribuir com o desenvolvimento do `Notify Service for Laravel`. Por favor, consulte o arquivo [CONTRIBUTING.md](CONTRIBUTING.md) para detalhes.

## Licença

O pacote `Notify Service for Laravel` é um software de código aberto licenciado sob a [Licença MIT](LICENSE.md).

***

[^1]: O NotifyKit é uma plataforma de comunicação que oferece serviços de envio de e-mail e SMS via API. Este pacote atua como um *wrapper* para integrar esses serviços ao sistema de Notificações do Laravel.
