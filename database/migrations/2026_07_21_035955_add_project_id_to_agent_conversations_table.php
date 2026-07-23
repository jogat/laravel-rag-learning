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
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');
        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained();
            $table->index(['user_id', 'project_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');
        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->dropIndex(['user_id', 'project_id', 'updated_at']);
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
