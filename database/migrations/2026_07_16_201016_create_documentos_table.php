<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained();
            $table->text('content'); // el texto del fragmento
            $table->string('source')->nullable();
            $table->integer('chunk_index');
            $table->timestamps();

            $table->index(['project_id', 'chunk_index']);
        });

        // pgvector: el tipo vector(1024) no lo conoce Laravel, lo añadimos a mano.
        // 1024 = dimension de bge-m3. Si cambias de embedder, cambia este numero.
        DB::statement('ALTER TABLE documents ADD COLUMN embedding vector(1024)');

        // Indice para que la busqueda por similitud sea rapida a escala.
        // Usamos HNSW con distancia coseno (la misma que calculabamos a mano).
        DB::statement('CREATE INDEX ON documents USING hnsw (embedding vector_cosine_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
