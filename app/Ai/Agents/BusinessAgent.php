<?php

namespace App\Ai\Agents;

use App\Ai\Middleware\LogAgentActivity;
use App\Enums\LanguagesEnum;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

class BusinessAgent implements Agent, Conversational, HasProviderOptions, HasTools, HasMiddleware
{
    use Promptable, RemembersConversations;

    protected array $tools = [];

    public function __construct(
        protected LanguagesEnum $language = LanguagesEnum::English,
        protected bool $think = true,
    ) {
    }

    /**
     * Get the provider-specific options to be passed to the provider.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        // Qwen3 en Ollama genera ~100 tokens de "pensamiento" oculto antes de
        // responder; desactivarlo baja la generacion de ~4s a ~0.3s. Solo aplica
        // a Ollama; otros proveedores no conocen la opcion 'think'.
        $driver = $provider instanceof Lab ? $provider->value : $provider;

        return $driver === 'ollama' ? ['think' => $this->think] : [];
    }


    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return match ($this->language) {
            LanguagesEnum::English => 'You are the entry point. For business questions delegate to product_specialist; for orders delegate to order_specialist; for greetings respond directly. Transmit ONLY what the specialist returns — never invent or add data.'
                . ' Respond briefly and in ' . LanguagesEnum::English->value . '.',
            LanguagesEnum::Spanish => 'Eres la puerta de entrada. Para preguntas del negocio delega en product_specialist; para pedidos delega en order_specialist; para saludos responde directo. Transmite ÚNICAMENTE lo que devuelva el especialista — nunca inventes ni agregues datos.'
                . ' Responde brevemente y en ' . LanguagesEnum::Spanish->value . '.',
            LanguagesEnum::French => "Vous êtes le point d'accès. Pour les demandes commerciales, transmettez-les au spécialiste produit ; pour les commandes, transmettez-les au spécialiste des commandes ; pour les salutations, répondez directement. Ne transmettez que les informations renvoyées par le spécialiste – n'inventez ni n'ajoutez jamais d'informations."
                . ' Répondez brièvement et en ' . LanguagesEnum::French->value . '.',
        };
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return $this->tools;
    }

    /**
     * @inheritDoc
     */
    protected function maxConversationMessages(): int
    {
        return 40;
    }

    /**
     * @param  array<int, Tool>  $tools
     */
    public function setTools(array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    public function middleware(): array
    {
        return [new LogAgentActivity()];
    }
}
