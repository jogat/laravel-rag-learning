<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * laravel/ai 0.10 upgrade: conversations and messages belong to a polymorphic participant instead of a user_id.
 *
 * Mirrors the "Polymorphic Conversation Participants" section of the laravel/ai UPGRADE.md, plus moving
 * this app's own (user_id, project_id, updated_at) index onto the participant columns.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');
        $messagesTable = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->dropIndex(['user_id', 'updated_at']);
            $table->dropIndex(['user_id', 'project_id', 'updated_at']);
            $table->renameColumn('user_id', 'participant_id');
            $table->string('participant_type')->nullable()->after('id');
        });

        Schema::table($messagesTable, function (Blueprint $table) {
            $table->dropIndex('conversation_index');
            $table->dropIndex(['user_id']);
            $table->renameColumn('user_id', 'participant_id');
            $table->string('participant_type')->nullable()->after('conversation_id');
        });

        $participantType = (new User)->getMorphClass();

        DB::table($conversationsTable)->whereNotNull('participant_id')->update(['participant_type' => $participantType]);
        DB::table($messagesTable)->whereNotNull('participant_id')->update(['participant_type' => $participantType]);

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->index(['participant_type', 'participant_id', 'updated_at'], 'participant_updated_at_index');
            $table->index(['participant_type', 'participant_id', 'project_id', 'updated_at'], 'participant_project_updated_at_index');
        });

        Schema::table($messagesTable, function (Blueprint $table) {
            $table->index(['conversation_id', 'participant_type', 'participant_id', 'updated_at'], 'conversation_index');
            $table->index(['participant_type', 'participant_id'], 'participant_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');
        $messagesTable = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->dropIndex('participant_updated_at_index');
            $table->dropIndex('participant_project_updated_at_index');
            $table->dropColumn('participant_type');
            $table->renameColumn('participant_id', 'user_id');
        });

        Schema::table($messagesTable, function (Blueprint $table) {
            $table->dropIndex('conversation_index');
            $table->dropIndex('participant_index');
            $table->dropColumn('participant_type');
            $table->renameColumn('participant_id', 'user_id');
        });

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'project_id', 'updated_at']);
        });

        Schema::table($messagesTable, function (Blueprint $table) {
            $table->index(['conversation_id', 'user_id', 'updated_at'], 'conversation_index');
            $table->index(['user_id']);
        });
    }
};
