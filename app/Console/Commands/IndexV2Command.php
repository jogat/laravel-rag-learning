<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

#[Signature('app:index-v2-command')]
#[Description('Command description')]
class IndexV2Command extends Command
{
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

        $response = Embeddings::for($this->baseConocimiento)->generate();

        $this->line('  Generando embeddings...');
        $this->newLine();

        foreach ($this->baseConocimiento as $i => $conocimiento) {
            Document::create([
                'content' => $conocimiento,
                'embedding' => $response->embeddings[$i],
            ]);
            $this->line('  Guardado: ' . mb_substr($conocimiento, 0, 50) . '...');
        }

        $this->newLine();
        $this->info(count($this->baseConocimiento) . ' documentos indexados en Postgres.');
        return self::SUCCESS;
    }
}
