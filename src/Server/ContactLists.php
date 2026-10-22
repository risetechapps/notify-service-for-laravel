<?php

namespace RiseTechApps\Notify\Server;

/**
 * Listas de contatos salvas, para usar nas campanhas (listId()). Canal email
 * (endereços) ou sms/whatsapp (telefones; uma lista de SMS também serve para
 * campanha de WhatsApp). Descadastrados ficam de fora no envio.
 */
class ContactLists extends Resource
{
    public function all(?string $channel = null): array
    {
        return $this->result($this->http()->get('/api/v1/contact-lists', self::filled(['channel' => $channel])));
    }

    public function get(string $id): array
    {
        return $this->result($this->http()->get("/api/v1/contact-lists/{$id}"));
    }

    public function create(string $channel, string $name, ?string $description = null): array
    {
        return $this->result($this->http()->post('/api/v1/contact-lists', self::filled(compact('channel', 'name', 'description'))));
    }

    public function update(string $id, array $fields): array
    {
        return $this->result($this->http()->put("/api/v1/contact-lists/{$id}", $fields));
    }

    public function delete(string $id): array
    {
        return $this->result($this->http()->delete("/api/v1/contact-lists/{$id}"));
    }

    public function members(string $id, ?string $search = null, ?int $page = null): array
    {
        return $this->result($this->http()->get("/api/v1/contact-lists/{$id}/members", self::filled(['search' => $search, 'page' => $page])));
    }

    /**
     * Inclui ou atualiza até 10.000 contatos por chamada.
     * E-mail: [['email' => ..., 'name' => ...]]; telefone: [['phone' => ..., 'name' => ...]].
     */
    public function addMembers(string $id, array $contacts): array
    {
        return $this->result($this->http()->post("/api/v1/contact-lists/{$id}/members", ['contacts' => $contacts]));
    }

    /** Remove por id de membro (`ids`) ou pelo contato (`contacts`: e-mails/telefones). */
    public function removeMembers(string $id, array $ids = [], array $contacts = []): array
    {
        return $this->result($this->http()->delete("/api/v1/contact-lists/{$id}/members", self::filled([
            'ids' => $ids ?: null,
            'contacts' => $contacts ?: null,
        ])));
    }
}
