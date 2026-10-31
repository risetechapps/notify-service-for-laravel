<?php

namespace RiseTechApps\Notify\Support;

use RiseTechApps\Notify\Message\NotifyApns;
use RiseTechApps\Notify\Message\NotifyDiscord;
use RiseTechApps\Notify\Message\NotifyMail;
use RiseTechApps\Notify\Message\NotifyPush;
use RiseTechApps\Notify\Message\NotifySlack;
use RiseTechApps\Notify\Message\NotifySms;
use RiseTechApps\Notify\Message\NotifyTeams;
use RiseTechApps\Notify\Message\NotifyTelegram;
use RiseTechApps\Notify\Message\NotifyWebhook;
use RiseTechApps\Notify\Message\NotifyWebSocket;
use RiseTechApps\Notify\Message\NotifyWhatsApp;

/**
 * Canal de cada classe de mensagem, no nome que o lote (/send/batch) e o
 * multicanal (/send/multi) usam: `email`, não `mail`.
 */
class MessageChannel
{
    public const CHANNELS = [
        NotifySms::class => 'sms',
        NotifyMail::class => 'email',
        NotifyPush::class => 'push',
        NotifyApns::class => 'apns',
        NotifyTelegram::class => 'telegram',
        NotifyWhatsApp::class => 'whatsapp',
        NotifySlack::class => 'slack',
        NotifyDiscord::class => 'discord',
        NotifyTeams::class => 'teams',
        NotifyWebSocket::class => 'websocket',
        NotifyWebhook::class => 'webhook',
    ];

    public static function of(object $message): string
    {
        foreach (self::CHANNELS as $class => $channel) {
            if ($message instanceof $class) {
                return $channel;
            }
        }

        throw new \InvalidArgumentException('Mensagem sem canal conhecido: '.$message::class);
    }

    /** `mail` (o nome do /send/mail) vira `email`; os demais ficam iguais. */
    public static function normalize(string $channel): string
    {
        return $channel === 'mail' ? 'email' : $channel;
    }

    /** Corpo da mensagem: objeto (toArray mais o modelo salvo, se houver) ou array cru. */
    public static function data(array|object $message): array
    {
        if (is_array($message)) {
            return $message;
        }

        return [
            ...$message->toArray(),
            ...(method_exists($message, 'templateOptions') ? $message->templateOptions() : []),
        ];
    }
}
