
import {request} from '../api'

export type ChatToolCall = {
    agent: string;
    tool: string;
    arguments: Record<string, unknown>;
    result: string;
    duration_ms: number;
};

/** Only present when the backend runs with CHAT_DEBUG=true. */
export type ChatDebug = {
    intent: string;
    intent_classifier_bypassed: boolean;
    blocked_by_intent_classifier: boolean;
    resumed_conversation_id: string | null;
    tool_calls: ChatToolCall[];
    leak_detected: boolean;
    unredacted_reply: string | null;
};

export type ChatReply = {reply: string, conversation_id: string | null, debug?: ChatDebug};
export const sendMessage = (project: string, message: string) =>
    request<ChatReply>('/api/chat', { method: 'POST', body: JSON.stringify({ project, message }) });
