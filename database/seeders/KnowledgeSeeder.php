<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class KnowledgeSeeder extends Seeder
{
    /**
     * Indexa docs.csv con el mismo comando que usa el resto del equipo.
     *
     * Si Ollama no esta listo no rompemos `migrate --seed`: la app queda usable y se avisa como terminar.
     * En tests no se ejecuta, para no depender de un servidor de modelos.
     */
    public function run(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if ($this->command->call('app:index-documents') !== 0) {
            $this->command->warn('Base de conocimiento sin indexar. Inicia Ollama (`ollama pull bge-m3`) y ejecuta `php artisan app:index-documents`.');
        }
    }
}
