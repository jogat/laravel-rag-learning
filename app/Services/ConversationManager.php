<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

class ConversationManager
{
    private const int INACTIVITY_HOURS = 12;

    /** The user's most recent conversation in THIS project, if still active — else null (start fresh). */
    public function activeConversationId(User $user, Project $project): ?string
    {
        return Conversation::query()
            ->where('user_id', $user->id)
            ->where('project_id', $project->id)                       // ← per-project isolation
            ->where('updated_at', '>=', now()->subHours(self::INACTIVITY_HOURS)) // ← inactivity cutoff
            ->latest('updated_at')
            ->value('id');
    }

    /** Stamp the project onto a conversation the SDK just created (idempotent). */
    public function tagProject(string $conversationId, Project $project): void
    {
        Conversation::whereKey($conversationId)->update(['project_id' => $project->id]);
    }

    public function redactLastReply(string $conversationId, string $replacement): void
    {
        ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->latest('id')
            ->first()
            ?->update(['content' => $replacement]);
    }
}
