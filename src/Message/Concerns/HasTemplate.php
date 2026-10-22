<?php

namespace RiseTechApps\Notify\Message\Concerns;

/**
 * Modelo salvo no servidor (e-mail, SMS e WhatsApp): o conteúdo vem do modelo,
 * e as variáveis preenchem {{chave}} / {chave}. O que a mensagem definir
 * explicitamente tem prioridade sobre o modelo.
 *
 *   ->template('Pedido enviado', ['pedido' => '#1042'])   // id ou nome do modelo
 */
trait HasTemplate
{
    protected ?string $templateId = null;

    protected array $templateVariables = [];

    public function template(string $idOrName, array $variables = []): static
    {
        $this->templateId = $idOrName;
        $this->templateVariables = $variables;

        return $this;
    }

    public function templateOptions(): array
    {
        return array_filter([
            'template_id' => $this->templateId,
            'variables' => $this->templateVariables ?: null,
        ], fn ($value) => $value !== null);
    }
}
