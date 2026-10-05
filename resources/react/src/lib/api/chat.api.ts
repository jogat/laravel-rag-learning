
import {request} from '../api'

export type ChatReply = {reply: string, conversation_id: string | null};
export const sendMessage = (project: string, message: string) =>
    request<ChatReply>('/api/chat', { method: 'POST', body: JSON.stringify({ project, message }) });


