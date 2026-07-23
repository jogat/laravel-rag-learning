<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('app:rag-completo {pregunta}')]
#[Description('Command description')]
class RagCompleto extends Command
{
    protected string $embedUrl = "http://localhost:1234/v1/embeddings";
    protected string $chatUrl  = "http://localhost:1234/v1/chat/completions";

    protected string $embedModel = "text-embedding-bge-m3";
    protected string $chatModel  = "qwen/qwen3-8b";

    // Cuantos fragmentos recuperamos para darle contexto a Qwen.
    protected int $topK = 2;

    // Aqui guardaremos el embedding de cada fragmento tras indexar.
    protected array $indice = [];

    protected array $baseConocimiento = [
        'El horario de atencion es de lunes a viernes de 9am a 6pm, y sabados de 10am a 2pm.',
        'Aceptamos pagos con tarjeta de credito, debito y transferencia bancaria. No aceptamos efectivo.',
        'El envio es gratis en compras mayores a 50 dolares. Los envios estandar tardan de 3 a 5 dias habiles.',
        'Ofrecemos garantia de 30 dias para devoluciones, siempre con el recibo original.',
        'Nuestra tienda esta ubicada en Avenida Principal 123, local 4, en el centro de la ciudad.',
    ];
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pregunta = $this->argument('pregunta');

        // PASO 1: indexar (esto en un RAG real se hace UNA vez, no en cada pregunta).
        $this->indexarDocumentos();

        // PASO 2: recuperar los fragmentos mas relevantes para la pregunta.
        $contexto = $this->recuperarContexto($pregunta);
        if ($contexto === null) {
            return self::FAILURE;
        }

        // PASO 3: darle a Qwen la pregunta + el contexto, y que responda.
        $respuesta = $this->generarRespuesta($pregunta, $contexto);
        if ($respuesta === null) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== RESPUESTA FINAL ===');
        $this->line($respuesta);

        return self::SUCCESS;
    }

    protected function generarRespuesta(string $pregunta, string $contexto): ?string
    {
        // El system prompt es clave: le ordenamos usar solo el contexto
        // y admitir cuando no sabe. Esto evita que Qwen "invente".
        $system = "Eres el asistente de un negocio. Responde la pregunta del cliente "
            . "usando UNICAMENTE la informacion del contexto. Si la respuesta no esta "
            . "en el contexto, di honestamente que no tienes esa informacion. "
            . "Responde breve y en espanol. /no_think";

        // Aqui armamos el mensaje del usuario: contexto + pregunta juntos.
        $contenidoUsuario = "Contexto:\n{$contexto}\n\nPregunta del cliente: {$pregunta}";

        $respuesta = Http::acceptJson()->post($this->chatUrl, [
            'model'    => $this->chatModel,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $contenidoUsuario],
            ],
            'temperature' => 0.3, // bajo: queremos respuestas fieles al contexto, no creativas
        ]);

        if ($respuesta->failed()) {
            $this->error('Fallo la peticion de chat. Esta cargado Qwen en LM Studio?');
            $this->line($respuesta->body());
            return null;
        }

        $texto = data_get($respuesta->json(), 'choices.0.message.content');
        if ($texto === null) {
            $this->error('No encontre la respuesta en el JSON:');
            $this->line($respuesta->body());
            return null;
        }

        return trim($texto);
    }

    protected function recuperarContexto(string $pregunta): ?string
    {
        $vectorPregunta = $this->embed($pregunta);
        if ($vectorPregunta === null) {
            return null;
        }

        // Calculamos la similitud contra cada fragmento indexado.
        $rankeados = [];
        foreach ($this->indice as $i => $vectorDoc) {
            $rankeados[] = [
                'texto'     => $this->baseConocimiento[$i],
                'similitud' => $this->cosineSimilarity($vectorPregunta, $vectorDoc),
            ];
        }

        // Ordenamos de mas a menos parecido y nos quedamos con los mejores.
        usort($rankeados, fn ($a, $b) => $b['similitud'] <=> $a['similitud']);
        $mejores = array_slice($rankeados, 0, $this->topK);

        // Mostramos que se recupero (transparencia: asi ves por que responde lo que responde).
        $this->newLine();
        $this->info("Contexto recuperado para: \"{$pregunta}\"");
        foreach ($mejores as $m) {
            $this->line('  [' . number_format($m['similitud'], 3) . '] ' . $m['texto']);
        }

        // Unimos los fragmentos en un solo bloque de texto para pasarselo a Qwen.
        return implode("\n", array_map(fn ($m) => '- ' . $m['texto'], $mejores));
    }

    protected function indexarDocumentos(): void
    {
        $this->info('Indexando base de conocimiento...');

        foreach ($this->baseConocimiento as $i => $texto) {
            $vector = $this->embed($texto); // bge-m3 no necesita prefijos
            if ($vector === null) {
                $this->error('Fallo al indexar. Revisa que bge-m3 este cargado.');
                exit(1);
            }
            $this->indice[$i] = $vector;
        }

        $this->line('  ' . count($this->indice) . ' fragmentos indexados.');
    }

    protected function embed(string $texto): ?array
    {
        $respuesta = Http::acceptJson()->post($this->embedUrl, [
            'model' => $this->embedModel,
            'input' => $texto,
        ]);

        if ($respuesta->failed()) {
            $this->error('La peticion de embedding fallo.');
            $this->line($respuesta->body());
            return null;
        }

        $vector = data_get($respuesta->json(), 'data.0.embedding');
        return is_array($vector) ? $vector : null;
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = $magA = $magB = 0.0;
        foreach ($a as $i => $valA) {
            $dot  += $valA * $b[$i];
            $magA += $valA ** 2;
            $magB += $b[$i] ** 2;
        }
        return $dot / (sqrt($magA) * sqrt($magB));
    }
}
