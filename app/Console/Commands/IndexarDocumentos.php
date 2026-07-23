<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('app:indexar-documentos')]
#[Description('Etapa 4: guarda la base de conocimiento con sus embeddings en Postgres')]
class IndexarDocumentos extends Command
{

    protected string $embedUrl   = "http://localhost:1234/v1/embeddings";
    protected string $embedModel = "text-embedding-bge-m3";

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
        Document::truncate();
        $this->info('Tabla documentos vaciada. Indexando...');

        foreach ($this->baseConocimiento as $texto) {
            $embedding = $this->embed($texto);
            if ($embedding === null) {
                $this->error('Fallo al generar embedding. Esta cargado bge-m3?');
                return self::FAILURE;
            }

            // Aqui pasa la magia del cast: le entregamos un ARRAY de floats
            // y VectorCast lo convierte al formato de pgvector al guardar.
            Document::create([
                'contenido' => $texto,
                'embedding' => $embedding,
            ]);

            $this->line('  Guardado: ' . mb_substr($texto, 0, 50) . '...');
        }

        $this->newLine();
        $this->info(count($this->baseConocimiento) . ' documentos indexados en Postgres.');
        $this->info('Ahora persisten: no hay que recalcularlos en cada consulta.');

        return self::SUCCESS;
    }

    protected function embed(string $texto): ?array
    {
        $respuesta = Http::acceptJson()->post($this->embedUrl, [
            'model' => $this->embedModel,
            'input' => $texto,
        ]);

        if ($respuesta->failed()) {
            $this->line($respuesta->body());
            return null;
        }

        $vector = data_get($respuesta->json(), 'data.0.embedding');
        return is_array($vector) ? $vector : null;
    }
}
