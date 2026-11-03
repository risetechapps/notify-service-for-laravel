<?php

namespace RiseTechApps\Notify\Events;

use Exception;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Exceptions\NotifyDeliveryException;

/**
 * Envio que falhou. Na recusa pela proteção do número de WhatsApp,
 * protectionReason() diz o motivo (ex.: daily_limit), para avisar o usuário
 * em vez de tentar de novo.
 */
class NotifyFailedEvent
{
    use Dispatchable;

    /**
     * @param  array  $response  corpo do erro do servidor ([] sem resposta)
     * @param  int  $status  HTTP do servidor (0 sem resposta)
     */
    public function __construct(
        public $notifiable,
        public Notification $notification,
        public Exception $exception,
        public string $channel,
        public array $response = [],
        public int $status = 0,
    ) {}

    /**
     * WhatsApp recusado pela proteção do número (429): paused, daily_limit,
     * new_contacts, duplicate, undelivered, invalid_numbers ou opt_outs.
     * null para qualquer outra falha.
     */
    public function protectionReason(): ?string
    {
        return $this->status === 429 ? NotifyDeliveryException::protectionReason($this->response) : null;
    }
}
