<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('app:lm-studio-conversation')]
#[Description('Command description')]
class LmStudioConversation extends Command
{
    /**
     * Execute the console command.
     */

    protected string $url = "http://localhost:1234/v1/chat/completions";

    public function handle()
    {
        $payload = [
            "model" => "qwen3-8b",
            "messages" => [
                [
                    "role"    => "system",
                    "content" => "Eres un asistente util que responde en espanol de forma breve."
                ],
                [
                    "role"    => "user",
                    "content" => "Hola, en una frase: que es un embedding? /no_think"
                ]
            ],
            "temperature" => 0.7
        ];

        $request = Http::withHeader('Content-Type', 'application/json')
            ->post($this->url, $payload);

        if ($request->failed()) {
            $this->error('La petición falló. ¿Está encendido el servidor de LM Studio?');
            $this->line($request->body());
            return self::FAILURE;
        }

        $content = data_get($request->json(), 'choices.0.message.content');

        if ($content === null) {
            $this->error('No encontré el texto en la respuesta:');
            $this->line($request->body());
            return self::FAILURE;
        }

        $this->info($content);
        return self::SUCCESS;
    }
}
